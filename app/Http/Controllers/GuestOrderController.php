<?php

namespace App\Http\Controllers;

use App\CashOrders;
use App\Http\Requests\GuestOrderCheckoutRequest;
use App\Models\Order;
use App\QrisPayments;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GuestOrderController extends Controller
{
    public function store(GuestOrderCheckoutRequest $request, CashOrders $cash, QrisPayments $payments): JsonResponse
    {
        try {
            $order = $cash->guestCheckout($request->validated());
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['checkout_key' => 'Pesanan sedang diproses. Coba ulang dengan kunci yang sama.']);
            }
            throw $exception;
        }

        if (($request->validated('payment_method') ?? 'cash') === 'qris') {
            $payments->ensureCharge($order);
        }

        return response()->json([
            'order' => $cash->present(collect([$order->fresh()]))[0],
            'statusUrl' => route('guest.orders.show', ['order' => $order->public_token], false),
        ]);
    }

    public function show(Order $order, CashOrders $cash): Response
    {
        abort_unless($order->source === 'table_qr', 404);
        $cash->expire($order->id);

        return Inertia::render('guest/OrderStatus', [
            'order' => $cash->present(collect([$order->fresh()]))[0],
        ]);
    }

    public function retryQris(Order $order, QrisPayments $payments): RedirectResponse
    {
        abort_unless($order->source === 'table_qr', 404);
        $payments->ensureCharge($order);

        return redirect()->route('guest.orders.show', ['order' => $order->public_token]);
    }
}
