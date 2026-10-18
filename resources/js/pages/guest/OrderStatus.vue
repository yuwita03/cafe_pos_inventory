<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import CashReceipt from './CashReceipt.vue';
import type { OrderData } from '@/types/orders';
import '../../../css/admin.css';
import '../../../css/pos.css';
import '../../../css/customer-menu.css';

const props = defineProps<{ order: OrderData }>();
const connectionError = ref(false);
onMounted(() => {
    const checkoutKey = `guest-cash-checkout:${props.order.tableId}`;
    const statusKey = `${checkoutKey}:status`;
    try {
        if (sessionStorage.getItem(statusKey) === window.location.pathname) {
            sessionStorage.removeItem(statusKey);
            sessionStorage.removeItem(checkoutKey);
            sessionStorage.removeItem(`customer-cart:${props.order.tableId}`);
        }
    } catch {
        // The receipt remains accessible if session storage is unavailable.
    }
});
usePoll(
    5000,
    {
        only: ['order'],
        onSuccess: () => {
            connectionError.value = false;
        },
        onNetworkError: () => {
            connectionError.value = true;
            return false;
        },
    },
    { mode: 'rest' },
);
</script>

<template>
    <main class="customer-menu customer-order-screen">
        <Head :title="`Pesanan ${order.number}`" />
        <p v-if="connectionError" class="customer-details-note" role="status">
            Koneksi terputus. Status akan diperbarui saat tersambung kembali.
        </p>
        <div class="customer-page customer-order-page">
            <CashReceipt :order="order" />
        </div>
    </main>
</template>
