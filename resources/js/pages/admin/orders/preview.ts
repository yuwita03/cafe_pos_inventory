import { computed, ref } from 'vue';
import type { CartLine } from '../pos/fixtures';

export const orderStates = {
    awaiting_payment: { label: 'Menunggu pembayaran', tone: 'amber' },
    queued: { label: 'Dalam antrean', tone: 'blue' },
    preparing: { label: 'Sedang dibuat', tone: 'blue' },
    ready: { label: 'Siap', tone: 'green' },
    completed: { label: 'Selesai', tone: 'neutral' },
    cancelled: { label: 'Dibatalkan', tone: 'red' },
    payment_review: { label: 'Perlu pemeriksaan', tone: 'amber' },
};
export const paymentStates = {
    pending: { label: 'Menunggu', tone: 'amber' },
    paid: { label: 'Dibayar', tone: 'green' },
    failed: { label: 'Gagal', tone: 'red' },
    expired: { label: 'Kedaluwarsa', tone: 'neutral' },
    refunded: { label: 'Dikembalikan', tone: 'neutral' },
};
export type OrderStatus = keyof typeof orderStates;
export type PaymentStatus = keyof typeof paymentStates;
export interface PreviewOrder {
    number: string;
    customerName: string;
    source: 'cashier' | 'table_qr';
    fulfillment: 'dine_in' | 'takeaway';
    table: string | null;
    status: OrderStatus;
    paymentStatus: PaymentStatus;
    method: 'cash' | 'qris';
    reference: string | null;
    received: number | null;
    change: number | null;
    createdAt: string;
    paidAt: string | null;
    lines: CartLine[];
}

function sampleLine(name: string, price: number, quantity = 1): CartLine {
    return {
        key: name,
        productId: 0,
        variantId: 0,
        optionIds: [],
        productName: name,
        variantName: 'Regular',
        optionNames: [],
        unitPrice: price,
        quantity,
    };
}

const samples: {
    name: string;
    status: OrderStatus;
    payment: PaymentStatus;
    method: 'cash' | 'qris';
    source: 'cashier' | 'table_qr';
    table: string | null;
    lines: CartLine[];
}[] = [
    {
        name: 'Alya',
        status: 'completed',
        payment: 'paid',
        method: 'cash',
        source: 'cashier',
        table: 'A03',
        lines: [
            sampleLine('Americano', 25000),
            sampleLine('Butter Croissant', 23000),
        ],
    },
    {
        name: 'Bima',
        status: 'preparing',
        payment: 'paid',
        method: 'qris',
        source: 'table_qr',
        table: 'A02',
        lines: [sampleLine('Latte', 30000, 2)],
    },
    {
        name: 'Citra',
        status: 'queued',
        payment: 'paid',
        method: 'cash',
        source: 'cashier',
        table: null,
        lines: [sampleLine('Cappuccino', 28000)],
    },
    {
        name: 'Danu',
        status: 'awaiting_payment',
        payment: 'pending',
        method: 'qris',
        source: 'table_qr',
        table: 'A05',
        lines: [sampleLine('Mocha', 35000)],
    },
    {
        name: 'Eka',
        status: 'cancelled',
        payment: 'expired',
        method: 'qris',
        source: 'table_qr',
        table: 'A06',
        lines: [sampleLine('Cookies', 18000, 2)],
    },
    {
        name: 'Fajar',
        status: 'payment_review',
        payment: 'paid',
        method: 'qris',
        source: 'table_qr',
        table: 'A01',
        lines: [sampleLine('Americano', 25000)],
    },
    {
        name: 'Gina',
        status: 'awaiting_payment',
        payment: 'failed',
        method: 'qris',
        source: 'cashier',
        table: 'A04',
        lines: [sampleLine('Latte', 30000)],
    },
    {
        name: 'Hani',
        status: 'ready',
        payment: 'paid',
        method: 'cash',
        source: 'cashier',
        table: 'A08',
        lines: [
            sampleLine('Tea', 18000),
            sampleLine('Butter Croissant', 23000),
        ],
    },
];

// Prototype memory only: navigation retains examples; a page reload resets them.
export const previewOrders = ref<PreviewOrder[]>(
    samples.map((sample, index) => {
        const createdAt = new Date(
            Date.UTC(2026, 9, 16, 3, index * 5),
        ).toISOString();
        const total = sample.lines.reduce(
            (sum, line) => sum + line.unitPrice * line.quantity,
            0,
        );
        return {
            number: `DEMO-${String(index + 1).padStart(3, '0')}`,
            customerName: sample.name,
            source: sample.source,
            fulfillment: sample.table ? 'dine_in' : 'takeaway',
            table: sample.table,
            status: sample.status,
            paymentStatus: sample.payment,
            method: sample.method,
            reference:
                sample.method === 'qris' ? `DEMO-QRIS-${index + 1}` : null,
            received: sample.method === 'cash' ? 100000 : null,
            change: sample.method === 'cash' ? 100000 - total : null,
            createdAt,
            paidAt:
                sample.payment === 'paid'
                    ? new Date(Date.parse(createdAt) + 60000).toISOString()
                    : null,
            lines: sample.lines,
        };
    }),
);

export const kitchenOrders = computed(() =>
    previewOrders.value.filter(
        (order) =>
            order.paymentStatus === 'paid' &&
            ['queued', 'preparing', 'ready', 'completed'].includes(
                order.status,
            ),
    ),
);

const kitchenNextStatus: Partial<Record<OrderStatus, OrderStatus>> = {
    queued: 'preparing',
    preparing: 'ready',
    ready: 'completed',
};

export function advanceKitchenPreview(
    number: string,
    expectedStatus: OrderStatus,
): boolean {
    const order = kitchenOrders.value.find((item) => item.number === number);
    const nextStatus = kitchenNextStatus[expectedStatus];
    if (!order || order.status !== expectedStatus || !nextStatus) {
        return false;
    }
    order.status = nextStatus;
    return true;
}

let sequence = 0;
export function savePosPreview(
    input: Omit<
        PreviewOrder,
        'number' | 'source' | 'reference' | 'createdAt' | 'paidAt'
    >,
    number: string | null,
): PreviewOrder {
    const existing = previewOrders.value.find(
        (order) => order.number === number,
    );
    const orderNumber =
        existing?.number ?? `DEMO-POS-${String(++sequence).padStart(3, '0')}`;
    const order: PreviewOrder = {
        ...input,
        number: orderNumber,
        source: 'cashier',
        reference: input.method === 'qris' ? `${orderNumber}-QRIS` : null,
        createdAt: existing?.createdAt ?? new Date().toISOString(),
        paidAt:
            input.paymentStatus === 'paid'
                ? (existing?.paidAt ?? new Date().toISOString())
                : null,
        lines: input.lines.map((line) => ({
            ...line,
            optionIds: [...line.optionIds],
            optionNames: [...line.optionNames],
        })),
    };
    if (existing) {
        previewOrders.value.splice(
            previewOrders.value.indexOf(existing),
            1,
            order,
        );
    } else {
        previewOrders.value.unshift(order);
    }
    return order;
}
