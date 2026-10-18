<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { menu } from '@/routes/guest';
import { formatRupiah as rupiah } from '@/lib/utils';
import { orderStates, paymentStates, type OrderData } from '@/types/orders';

const props = defineProps<{ order: OrderData }>();
const heading = ref<HTMLHeadingElement | null>(null);
const menuUrl = computed(() =>
    menu.url({ query: { table: props.order.tableId ?? undefined } }),
);
const deadline = computed(() =>
    props.order.expiresAt
        ? new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
              timeZone: 'Asia/Jakarta',
          }).format(new Date(props.order.expiresAt)) + ' WIB'
        : null,
);

onMounted(() => heading.value?.focus());
</script>

<template>
    <section>
        <header class="customer-order-header">
            <span aria-hidden="true"></span>
            <h1 ref="heading" tabindex="-1">Pesanan tunai</h1>
            <span aria-hidden="true"></span>
        </header>
        <div class="customer-order-content customer-cash-content">
            <section
                class="customer-cash-receipt"
                aria-labelledby="cash-receipt-title"
            >
                <div class="customer-cash-heading">
                    <span class="customer-kicker">NOMOR PESANAN</span>
                    <h2 id="cash-receipt-title">{{ order.number }}</h2>
                    <p class="customer-cash-table">Meja {{ order.table }}</p>
                    <span class="customer-cash-unpaid"
                        >{{ paymentStates[order.paymentStatus].label }} ·
                        CASH</span
                    >
                </div>
                <dl class="customer-cash-details">
                    <div>
                        <dt>Nama pemesan</dt>
                        <dd>{{ order.customerName }}</dd>
                    </div>
                    <div v-if="order.customerEmail">
                        <dt>Email</dt>
                        <dd>{{ order.customerEmail }}</dd>
                    </div>
                    <div>
                        <dt>Status pesanan</dt>
                        <dd>{{ orderStates[order.status].label }}</dd>
                    </div>
                    <div v-if="deadline">
                        <dt>Batas bayar</dt>
                        <dd>{{ deadline }}</dd>
                    </div>
                </dl>
                <h3>Detail pesanan</h3>
                <article
                    v-for="line in order.lines"
                    :key="line.key"
                    class="customer-cash-item"
                >
                    <div>
                        <strong
                            >{{ line.quantity }} ×
                            {{ line.productName }}</strong
                        >
                        <p v-if="line.variantName !== 'Regular'">
                            {{ line.variantName }}
                        </p>
                        <p
                            v-for="(option, index) in line.optionNames"
                            :key="index"
                        >
                            {{ option }}
                        </p>
                        <small>{{ rupiah(line.unitPrice) }} / item</small>
                    </div>
                    <strong>{{
                        rupiah(line.unitPrice * line.quantity)
                    }}</strong>
                </article>
                <div class="customer-cash-total">
                    <span>Total</span><strong>{{ rupiah(order.total) }}</strong>
                </div>
                <p class="customer-cash-disclaimer">
                    {{
                        order.paymentStatus === 'paid'
                            ? 'Pembayaran tunai telah dikonfirmasi kasir.'
                            : 'Ringkasan ini bukan bukti pembayaran.'
                    }}
                </p>
                <div class="customer-receipt-download">
                    <BaseButton
                        disabled
                        aria-describedby="receipt-download-note"
                        >Unduh struk PDF</BaseButton
                    >
                    <p id="receipt-download-note">Unduh PDF belum tersedia.</p>
                </div>
            </section>
            <section
                class="customer-payment-guide"
                aria-labelledby="cash-next-title"
            >
                <h2 id="cash-next-title">Langkah berikutnya</h2>
                <p v-if="order.paymentStatus === 'pending'">
                    Tunjukkan nomor pesanan ini kepada kasir dan bayar sebelum
                    batas waktu. Pesanan mulai diproses setelah kasir
                    mengonfirmasi pembayaran.
                </p>
                <p v-else-if="order.paymentStatus === 'expired'">
                    Batas pembayaran telah lewat. Pesanan ini tidak dapat
                    dibayar; buat pesanan baru dengan harga dan stok terbaru.
                </p>
                <p v-else>
                    {{ orderStates[order.status].label }}. Halaman ini akan
                    memperbarui status pesanan.
                </p>
                <Link
                    v-if="order.paymentStatus === 'expired' && order.tableId"
                    :href="menuUrl"
                    class="button"
                    >Buat pesanan baru <AppIcon name="arrow" :size="16"
                /></Link>
            </section>
        </div>
    </section>
</template>
