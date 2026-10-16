import assert from 'node:assert/strict';
import test from 'node:test';
import {
    advanceKitchenPreview,
    kitchenOrders,
    previewOrders,
    savePosPreview,
} from '../orders/preview.ts';

await test('only paid work enters kitchen and actions advance the shared order one step', () => {
    const input = {
        customerName: 'Kitchen preview check',
        source: 'cashier',
        fulfillment: 'dine_in',
        table: 'A01',
        method: 'qris',
        status: 'awaiting_payment',
        paymentStatus: 'pending',
        received: null,
        change: null,
        lines: [],
    };
    const order = savePosPreview(input, null);
    const stored = () =>
        previewOrders.value.find((item) => item.number === order.number);
    const visible = () =>
        kitchenOrders.value.some((item) => item.number === order.number);
    assert.equal(visible(), false);
    assert.equal(
        advanceKitchenPreview(order.number, 'awaiting_payment'),
        false,
    );
    for (const paymentStatus of ['pending', 'failed', 'expired', 'refunded']) {
        savePosPreview(
            { ...input, status: 'queued', paymentStatus },
            order.number,
        );
        assert.equal(visible(), false);
        assert.equal(advanceKitchenPreview(order.number, 'queued'), false);
    }
    savePosPreview(
        { ...input, status: 'payment_review', paymentStatus: 'paid' },
        order.number,
    );
    assert.equal(visible(), false);
    assert.equal(advanceKitchenPreview(order.number, 'payment_review'), false);
    savePosPreview(
        { ...input, status: 'queued', paymentStatus: 'paid' },
        order.number,
    );
    assert.equal(visible(), true);
    assert.equal(advanceKitchenPreview(order.number, 'preparing'), false);
    assert.equal(advanceKitchenPreview(order.number, 'queued'), true);
    assert.equal(stored().status, 'preparing');
    assert.equal(advanceKitchenPreview(order.number, 'queued'), false);
    assert.equal(stored().status, 'preparing');
    assert.equal(advanceKitchenPreview(order.number, 'preparing'), true);
    assert.equal(stored().status, 'ready');
    assert.equal(advanceKitchenPreview(order.number, 'ready'), true);
    assert.equal(stored().status, 'completed');
    assert.equal(stored().paymentStatus, 'paid');
    assert.equal(advanceKitchenPreview(order.number, 'completed'), false);
    assert.equal(advanceKitchenPreview('missing', 'queued'), false);
});
