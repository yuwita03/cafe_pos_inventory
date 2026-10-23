<?php

namespace App;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class QrisPayments
{
    public function __construct(private readonly MidtransQrisGateway $gateway) {}

    public function ensureCharge(Order $order): void
    {
        app(CashOrders::class)->expire($order->id);
        $payment = DB::table('payments')->where('order_id', $order->id)->where('method', 'qris')->first();
        if (! $payment || $payment->status !== 'pending') {
            return;
        }

        try {
            Cache::lock('qris-charge:'.$payment->id, 40)->block(5, function () use ($order): void {
                $currentOrder = Order::findOrFail($order->id);
                $payment = DB::table('payments')->where('order_id', $order->id)->where('method', 'qris')->first();
                if ($currentOrder->status !== 'awaiting_payment' || ! $payment || $payment->status !== 'pending') {
                    return;
                }
                try {
                    try {
                        $response = $this->gateway->status($payment->provider_reference);
                        if (is_array($response)) {
                            $response = (object) $response;
                        }
                    } catch (Throwable $exception) {
                        if ((int) $exception->getCode() !== 404 || $payment->qr_image_url) {
                            throw $exception;
                        }
                        $response = $this->gateway->charge([
                            'payment_type' => 'qris',
                            'qris' => ['acquirer' => 'gopay'],
                            'transaction_details' => ['order_id' => $payment->provider_reference, 'gross_amount' => (int) $payment->amount_idr],
                            'custom_expiry' => ['order_time' => $currentOrder->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s O'), 'expiry_duration' => 30, 'unit' => 'minute'],
                        ], $payment->provider_reference);
                    }
                    $this->applyProviderState((array) $response, true);
                    if (($response->transaction_status ?? null) !== 'pending' || $payment->qr_image_url) {
                        return;
                    }
                    $transactionId = $response->transaction_id ?? null;
                    if (! is_string($transactionId) || $transactionId === '') {
                        throw new RuntimeException('Midtrans did not return a transaction ID.');
                    }
                    $url = $this->imageUrl($response, $transactionId);
                    DB::table('payments')->where('id', $payment->id)->where('status', 'pending')->update(['provider_transaction_id' => $transactionId, 'qr_image_url' => $url]);
                } catch (Throwable $exception) {
                    Log::warning('QRIS charge is not ready', ['order_id' => $order->id, 'error_type' => $exception::class]);
                }
            });
        } catch (Throwable $exception) {
            Log::warning('QRIS charge lock is unavailable', ['order_id' => $order->id, 'error_type' => $exception::class]);
        }
    }

    private function imageUrl(object $response, string $transactionId): string
    {
        foreach (($response->actions ?? []) as $action) {
            if (in_array($action->name ?? null, ['generate-qr-code-v2', 'generate-qr-code'], true) && is_string($action->url ?? null) && $this->validImageUrl($action->url)) {
                return $action->url;
            }
        }

        return 'https://api.sandbox.midtrans.com/v2/qris/'.rawurlencode($transactionId).'/qr-code';
    }

    private function validImageUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts) && ($parts['scheme'] ?? null) === 'https' && ($parts['host'] ?? null) === 'api.sandbox.midtrans.com'
            && ! isset($parts['user']) && ! isset($parts['pass']) && preg_match('~^/v[24]/qris/[^/]+/qr-code$~', $parts['path'] ?? '') === 1;
    }

    /** @param array<string, mixed> $payload */
    public function handleNotification(array $payload): void
    {
        $key = config('services.midtrans.server_key');
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (! is_string($payload[$field] ?? null)) {
                throw new RuntimeException('Invalid Midtrans notification.');
            }
        }
        if (! is_string($key) || $key === '' || ! hash_equals(hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$key), $payload['signature_key'])) {
            throw new RuntimeException('Invalid Midtrans signature.');
        }
        $this->applyProviderState($payload, false);
    }

    /** @param array<string, mixed> $payload */
    private function applyProviderState(array $payload, bool $trustedApi): void
    {
        $reference = $payload['order_id'] ?? null;
        $transactionId = $payload['transaction_id'] ?? null;
        $amount = $payload['gross_amount'] ?? null;
        if (! is_string($reference) || ! is_string($transactionId) || $transactionId === '' || ! is_string($amount)
            || ($payload['payment_type'] ?? null) !== 'qris' || ($payload['currency'] ?? null) !== 'IDR') {
            throw new RuntimeException('Invalid QRIS provider response.');
        }
        DB::transaction(function () use ($payload, $reference, $transactionId, $amount, $trustedApi): void {
            $candidate = DB::table('payments')->where('provider', 'midtrans')->where('method', 'qris')->where('provider_reference', $reference)->first();
            if (! $candidate) {
                throw new RuntimeException('Unknown QRIS payment.');
            }
            $order = Order::whereKey($candidate->order_id)->lockForUpdate()->firstOrFail();
            $payment = DB::table('payments')->where('id', $candidate->id)->lockForUpdate()->first();
            if (! $payment || ($payment->provider_transaction_id && $payment->provider_transaction_id !== $transactionId)
                || ! preg_match('/^(\d+)(?:\.00)?$/', $amount, $matches) || (int) $matches[1] !== (int) $payment->amount_idr
                || (int) $payment->amount_idr !== $order->total_idr) {
                throw new RuntimeException('QRIS payment does not match the order.');
            }
            $status = $payload['transaction_status'] ?? null;
            if ($status === 'settlement') {
                if (($payload['status_code'] ?? null) !== '200' || (isset($payload['fraud_status']) && $payload['fraud_status'] !== 'accept')) {
                    throw new RuntimeException('QRIS settlement is not accepted.');
                }
                if ($payment->status === 'paid') {
                    return;
                }
                $expiresAt = DB::table('stock_reservations')->where('order_id', $order->id)->where('status', 'active')->min('expires_at');
                $canQueue = $order->status === 'awaiting_payment' && $expiresAt && Carbon::parse($expiresAt)->isFuture();
                DB::table('payments')->where('id', $payment->id)->update(['status' => 'paid', 'provider_transaction_id' => $transactionId, 'paid_at' => now()]);
                if ($canQueue) {
                    DB::table('stock_reservations')->where('order_id', $order->id)->where('status', 'active')->update(['expires_at' => null]);
                    $order->update(['status' => 'queued']);
                } else {
                    DB::table('stock_reservations')->where('order_id', $order->id)->where('status', 'active')->update(['status' => 'released']);
                    $order->update(['status' => 'payment_review']);
                }
            } elseif (in_array($status, ['expire', 'deny', 'cancel', 'failure'], true)) {
                if ($payment->status !== 'pending' || $order->status !== 'awaiting_payment') {
                    return;
                }
                DB::table('payments')->where('id', $payment->id)->update(['status' => $status === 'expire' ? 'expired' : 'failed', 'provider_transaction_id' => $transactionId]);
                DB::table('stock_reservations')->where('order_id', $order->id)->where('status', 'active')->update(['status' => 'released']);
                $order->update(['status' => 'cancelled']);
            } elseif ($status === 'pending') {
                if ($trustedApi && ! $payment->provider_transaction_id) {
                    DB::table('payments')->where('id', $payment->id)->update(['provider_transaction_id' => $transactionId]);
                }
            } else {
                throw new RuntimeException('Unsupported QRIS status.');
            }
        }, 3);
    }
}
