<?php

namespace App;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class MidtransQris
{
    public function available(): bool
    {
        return (bool) config('services.midtrans.enabled')
            && config('services.midtrans.environment') === 'sandbox'
            && str_starts_with((string) config('services.midtrans.sandbox_server_key'), 'SB-Mid-server-');
    }

    public function create(Order $order): void
    {
        if (! $this->available()) {
            return;
        }
        $payment = DB::table('payments')->where('order_id', $order->id)->where('provider', 'midtrans_sandbox')->where('status', 'pending')->first();
        if (! $payment || $payment->charge_requested_at || ! $payment->qris_expires_at || Carbon::parse($payment->qris_expires_at)->isPast()) {
            return;
        }
        $claimed = DB::table('payments')->where('id', $payment->id)->whereNull('charge_requested_at')->update(['charge_requested_at' => now()]);
        if ($claimed !== 1) {
            return;
        }

        try {
            $response = Http::withBasicAuth((string) config('services.midtrans.sandbox_server_key'), '')
                ->acceptJson()->asJson()->connectTimeout(5)->timeout(15)->withoutRedirecting()
                ->post('https://api.sandbox.midtrans.com/v2/charge', [
                    'payment_type' => 'qris',
                    'transaction_details' => ['order_id' => $payment->provider_reference, 'gross_amount' => $order->total_idr],
                    'custom_expiry' => [
                        'order_time' => Carbon::parse($payment->qris_expires_at)->subMinutes(15)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s O'),
                        'expiry_duration' => 15, 'unit' => 'minute',
                    ],
                ]);
        } catch (ConnectionException) {
            return;
        }

        $data = $response->json();
        if (! $response->successful() || ! is_array($data)
            || ($data['status_code'] ?? null) !== '201'
            || ($data['transaction_status'] ?? null) !== 'pending'
            || ($data['payment_type'] ?? null) !== 'qris'
            || ($data['order_id'] ?? null) !== $payment->provider_reference
            || ($data['currency'] ?? null) !== 'IDR'
            || ! in_array($data['gross_amount'] ?? null, [(string) $order->total_idr, $order->total_idr.'.00'], true)
            || ! is_string($data['transaction_id'] ?? null) || strlen($data['transaction_id']) > 255
            || ! is_array($data['actions'] ?? null)) {
            return;
        }

        foreach ($data['actions'] as $action) {
            if (! is_array($action) || ($action['name'] ?? null) !== 'generate-qr-code' || ($action['method'] ?? null) !== 'GET') {
                continue;
            }
            $url = $action['url'] ?? null;
            if (! is_string($url) || ! preg_match('~^https://api\.(?:sandbox\.)?midtrans\.com/v2/qris/'.preg_quote($data['transaction_id'], '~').'/qr-code$~D', $url)) {
                continue;
            }
            DB::table('payments')->where('id', $payment->id)->update([
                'provider_transaction_id' => $data['transaction_id'], 'qr_image_url' => $url,
            ]);

            return;
        }
    }
}
