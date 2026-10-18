import type { CartLine } from '@/types/pos';

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
export interface OrderData {
    id: number;
    number: string;
    total: number;
    customerName: string;
    customerEmail: string | null;
    source: 'cashier' | 'table_qr';
    fulfillment: 'dine_in' | 'takeaway';
    table: string | null;
    tableId: number | null;
    status: keyof typeof orderStates;
    paymentStatus: keyof typeof paymentStates;
    method: 'cash' | 'qris';
    reference: string | null;
    received: number | null;
    change: number | null;
    createdAt: string;
    paidAt: string | null;
    expiresAt: string | null;
    lines: CartLine[];
}
