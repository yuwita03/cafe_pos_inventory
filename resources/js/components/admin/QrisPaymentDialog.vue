<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { formatRupiah } from '@/lib/utils';
import type { OrderData } from '@/types/orders';

const props = defineProps<{ open: boolean; order: OrderData | null }>();
const emit = defineEmits<{ close: []; viewOrder: [] }>();
const remaining = ref(0);
const imageFailed = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;
let receivedAt = performance.now();
let serverTime = Date.now();

watch(
    () => props.order?.qris,
    (payment) => {
        receivedAt = performance.now();
        serverTime = payment ? Date.parse(payment.serverTime) : Date.now();
    },
    { immediate: true },
);

watch(
    () => [props.open, props.order] as const,
    () => {
        clearInterval(timer);
        imageFailed.value = false;
        const payment = props.order?.qris;
        if (!props.open || !payment?.expiresAt) {
            remaining.value = 0;
            return;
        }
        const deadline = Date.parse(payment.expiresAt);
        const update = (): void => {
            remaining.value = Math.max(
                0,
                Math.ceil(
                    (deadline - serverTime - (performance.now() - receivedAt)) /
                        1000,
                ),
            );
        };
        update();
        timer = setInterval(update, 1000);
    },
    { immediate: true },
);
onBeforeUnmount(() => clearInterval(timer));
const countdown = computed(
    () =>
        `${String(Math.floor(remaining.value / 60)).padStart(2, '0')}:${String(remaining.value % 60).padStart(2, '0')}`,
);
const showQr = computed(
    () =>
        props.order?.qris?.state === 'ready' &&
        remaining.value > 0 &&
        !imageFailed.value,
);
</script>

<template>
    <AppDialog :open="open" title="Pembayaran QRIS" @close="emit('close')">
        <div v-if="order" class="pos-modal-content qris-payment">
            <p class="qris-sandbox">QRIS Sandbox · Uji coba</p>
            <h3>{{ order.number }}</h3>
            <p>
                {{ order.customerName }} ·
                {{
                    order.fulfillment === 'dine_in'
                        ? 'Meja ' + order.table
                        : 'Takeaway'
                }}
            </p>
            <strong class="qris-amount">{{ formatRupiah(order.total) }}</strong>
            <p class="qris-countdown">
                Batas pembayaran <time>{{ countdown }}</time>
            </p>
            <img
                v-if="showQr && order.qris?.qrUrl"
                :src="order.qris.qrUrl"
                :alt="
                    'QRIS untuk ' +
                    order.number +
                    ', ' +
                    formatRupiah(order.total)
                "
                class="qris-image"
                width="248"
                height="248"
                referrerpolicy="no-referrer"
                @error="imageFailed = true"
            />
            <p v-else-if="imageFailed" class="pos-error" role="alert">
                Gambar QR tidak dapat dimuat. Pesanan tetap menunggu pembayaran;
                jangan membuat pembayaran pengganti.
            </p>
            <p v-else class="qris-unresolved" role="status">
                Status pembayaran belum dipastikan. Pesanan dan stok tetap
                direservasi sampai hasil pembayaran dikonfirmasi.
            </p>
            <p v-if="showQr" role="status">
                Menunggu pembayaran · Belum masuk Kitchen
            </p>
            <p class="pos-field-help">
                Gunakan simulator Midtrans. Pembayaran nyata belum diaktifkan.
            </p>
            <div class="qris-actions" aria-describedby="qris-limitations">
                <BaseButton disabled>Periksa status</BaseButton>
                <BaseButton disabled>Batalkan pembayaran</BaseButton>
            </div>
            <p id="qris-limitations" class="pos-field-help">
                Konfirmasi otomatis, pemeriksaan status, dan pembatalan belum
                tersedia pada tahap sandbox ini. Menutup tampilan tidak
                membatalkan pembayaran atau melepas stok.
            </p>
        </div>
        <template #footer>
            <BaseButton @click="emit('viewOrder')">Lihat pesanan</BaseButton>
            <BaseButton variant="primary" @click="emit('close')"
                >Tutup tampilan</BaseButton
            >
        </template>
    </AppDialog>
</template>

<style scoped>
.qris-payment {
    text-align: center;
}
.qris-sandbox {
    color: var(--primary, #b45309);
    font-weight: 600;
}
.qris-payment h3 {
    margin: 12px 0 4px;
    font-size: 24px;
}
.qris-amount {
    display: block;
    margin: 20px 0 12px;
    font-size: 32px;
}
.qris-countdown {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
}
.qris-countdown time {
    font-size: 20px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.qris-image {
    display: block;
    box-sizing: content-box;
    width: min(248px, 100%);
    height: auto;
    margin: 16px auto;
    background: white;
}
.qris-unresolved {
    margin: 24px 0;
    font-weight: 600;
}
.qris-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
    margin-top: 16px;
}
</style>
