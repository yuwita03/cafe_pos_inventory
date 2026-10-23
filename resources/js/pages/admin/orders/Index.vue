<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import '../../../../css/orders.css';
import AppIcon from '@/components/AppIcon.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatRupiah as rupiah } from '@/lib/utils';
import { orders as ordersRoute, pos } from '@/routes/admin';
import { confirmCash } from '@/routes/admin/orders';
import type { TablePagination } from '@/types/menu';
import { orderStates, paymentStates, type OrderData } from '@/types/orders';

defineOptions({ layout: AdminLayout });
const props = defineProps<{
    orders: OrderData[];
    filters: {
        q: string;
        status: string;
        payment_status: string;
        source: string;
    };
    pagination: TablePagination;
    selectedOrder: OrderData | null;
    summary: { total: number; pending: number; review: number };
}>();
const query = ref(props.filters.q);
const orderFilter = ref(props.filters.status);
const paymentFilter = ref(props.filters.payment_status);
const sourceFilter = ref(props.filters.source);
const selectedNumber = ref<string | null>(props.selectedOrder?.number ?? null);
const requestError = ref('');
const loading = ref(false);
const refreshing = ref(false);
const cashReceived = ref<number | string>('');
const confirmingCash = ref(false);
const retryingQris = ref(false);
function retrySelectedQris(order: OrderData): void {
    if (retryingQris.value) return;
    retryingQris.value = true;
    router.post(
        `/admin/orders/${order.id}/retry-qris`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                retryingQris.value = false;
            },
        },
    );
}
const cashError = ref('');
const selected = computed(() => {
    if (selectedNumber.value === props.selectedOrder?.number) {
        return props.selectedOrder;
    }
    return props.orders.find((order) => order.number === selectedNumber.value);
});
const filtered = computed(() => props.orders);
let filterTimer: ReturnType<typeof setTimeout> | undefined;

function filterData(): Record<string, string> {
    return {
        q: query.value,
        status: orderFilter.value,
        payment_status: paymentFilter.value,
        source: sourceFilter.value,
    };
}

function connectionError(): false {
    requestError.value =
        'Pembaruan gagal. Periksa koneksi lalu muat ulang data.';
    return false;
}

const poll = usePoll(
    5000,
    {
        only: ['orders', 'summary', 'selectedOrder', 'pagination'],
        onBefore: () => !loading.value && !refreshing.value,
        onSuccess: () => {
            requestError.value = '';
        },
        onError: connectionError,
        onNetworkError: connectionError,
    },
    { mode: 'rest', background: 'pause' },
);

function refresh(): void {
    if (refreshing.value || loading.value) {
        return;
    }
    refreshing.value = true;
    router.reload({
        only: ['orders', 'summary', 'selectedOrder', 'pagination'],
        onSuccess: () => {
            requestError.value = '';
            poll.start();
        },
        onError: connectionError,
        onNetworkError: connectionError,
        onFinish: () => {
            refreshing.value = false;
        },
    });
}

watch([query, orderFilter, paymentFilter, sourceFilter], () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        loading.value = true;
        router.get(ordersRoute(), filterData(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: () => {
                requestError.value = '';
            },
            onError: connectionError,
            onNetworkError: connectionError,
            onFinish: () => {
                loading.value = false;
            },
        });
    }, 300);
});
watch(
    () => props.selectedOrder?.number,
    (number) => {
        if (number) {
            selectedNumber.value = number;
        }
    },
);
onBeforeUnmount(() => clearTimeout(filterTimer));
const hasFilters = computed(() =>
    Boolean(
        query.value ||
        orderFilter.value ||
        paymentFilter.value ||
        sourceFilter.value,
    ),
);
const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Asia/Jakarta',
});

function date(value: string): string {
    return `${dateFormatter.format(new Date(value))} WIB`;
}
function total(order: OrderData): number {
    return order.lines.reduce(
        (sum, line) => sum + line.unitPrice * line.quantity,
        0,
    );
}
function source(order: OrderData): string {
    return order.source === 'cashier' ? 'Kasir' : 'QR meja';
}
function resetFilters(): void {
    query.value = '';
    orderFilter.value = '';
    paymentFilter.value = '';
    sourceFilter.value = '';
}
function confirmSelectedCash(): void {
    const order = selected.value;
    if (
        !order ||
        confirmingCash.value ||
        !Number.isSafeInteger(Number(cashReceived.value)) ||
        Number(cashReceived.value) < order.total
    ) {
        cashError.value = 'Masukkan uang tunai minimal sebesar total pesanan.';
        return;
    }
    confirmingCash.value = true;
    cashError.value = '';
    router.post(
        confirmCash.url(order.id),
        { cash_received_idr: Number(cashReceived.value) },
        {
            preserveScroll: true,
            onSuccess: () => {
                cashReceived.value = '';
            },
            onError: (errors) => {
                cashError.value =
                    Object.values(errors).join(' ') ||
                    'Pembayaran belum dapat dikonfirmasi.';
            },
            onNetworkError: () => {
                cashError.value =
                    'Hasil pembayaran belum dapat dipastikan. Muat ulang pesanan sebelum mencoba lagi.';
                return false;
            },
            onFinish: () => {
                confirmingCash.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="orders-page">
        <Head title="Pesanan" />
        <header class="orders-heading">
            <div>
                <span class="eyebrow">OPERASIONAL</span>
                <h1>Pesanan</h1>
                <p>Pantau pembayaran dan proses pesanan dalam satu tempat.</p>
            </div>
            <Link :href="pos()" class="button button-primary"
                ><AppIcon name="plus" :size="18" />Pesanan dari kasir</Link
            >
        </header>
        <p v-if="requestError" class="orders-notice" role="alert">
            {{ requestError }}
        </p>
        <section class="orders-summary" aria-label="Ringkasan hasil filter">
            <div>
                <span>Pesanan sesuai filter</span
                ><strong>{{ summary.total }}</strong>
            </div>
            <div>
                <span>Pembayaran menunggu</span
                ><strong>{{ summary.pending }}</strong>
            </div>
            <div>
                <span>Perlu pemeriksaan</span
                ><strong>{{ summary.review }}</strong>
            </div>
        </section>
        <section class="orders-panel" aria-labelledby="orders-list-title">
            <div class="orders-panel-heading">
                <div>
                    <h2 id="orders-list-title">Daftar pesanan</h2>
                    <p>
                        Ringkasan mencakup seluruh hasil filter. Diperbarui
                        otomatis setiap 5 detik.
                    </p>
                </div>
                <BaseButton
                    :disabled="refreshing || loading"
                    @click="refresh"
                    >{{ refreshing ? 'Memuat…' : 'Muat ulang' }}</BaseButton
                >
            </div>
            <div class="orders-filters">
                <label class="orders-search"
                    ><span class="sr-only"
                        >Cari nomor pesanan, pelanggan, atau meja</span
                    ><AppIcon name="search" :size="18" /><input
                        v-model="query"
                        type="search"
                        placeholder="Cari nomor, pelanggan, meja…"
                /></label>
                <label
                    ><span>Status pesanan</span
                    ><select v-model="orderFilter">
                        <option value="">Semua status pesanan</option>
                        <option
                            v-for="(state, key) in orderStates"
                            :key="key"
                            :value="key"
                        >
                            {{ state.label }}
                        </option>
                    </select></label
                >
                <label
                    ><span>Status pembayaran</span
                    ><select v-model="paymentFilter">
                        <option value="">Semua pembayaran</option>
                        <option
                            v-for="(state, key) in paymentStates"
                            :key="key"
                            :value="key"
                        >
                            {{ state.label }}
                        </option>
                    </select></label
                >
                <label
                    ><span>Sumber</span
                    ><select v-model="sourceFilter">
                        <option value="">Semua sumber</option>
                        <option value="cashier">Kasir</option>
                        <option value="table_qr">QR meja</option>
                    </select></label
                >
            </div>
            <div class="orders-results" role="status">
                <span>{{
                    loading
                        ? 'Mencari pesanan…'
                        : `${pagination.total} pesanan ditemukan`
                }}</span
                ><button v-if="hasFilters" type="button" @click="resetFilters">
                    Reset filter
                </button>
            </div>
            <table v-if="filtered.length" class="orders-table">
                <caption class="sr-only">
                    Pesanan dengan status pembayaran dan proses terpisah. Waktu
                    ditampilkan dalam WIB.
                </caption>
                <thead>
                    <tr>
                        <th scope="col">Pesanan</th>
                        <th scope="col">Pelanggan / layanan</th>
                        <th scope="col">Pembayaran</th>
                        <th scope="col">Status pesanan</th>
                        <th scope="col">Total</th>
                        <th scope="col"><span class="sr-only">Detail</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="order in filtered" :key="order.number">
                        <td data-label="Pesanan">
                            <strong class="orders-number">{{
                                order.number
                            }}</strong
                            ><small>{{ date(order.createdAt) }}</small
                            ><small>{{ source(order) }}</small>
                        </td>
                        <td data-label="Pelanggan / layanan">
                            <strong>{{ order.customerName }}</strong
                            ><small>{{
                                order.fulfillment === 'dine_in'
                                    ? 'Dine-in · Meja ' + order.table
                                    : 'Takeaway'
                            }}</small>
                        </td>
                        <td data-label="Pembayaran">
                            <span
                                class="orders-status"
                                :data-tone="
                                    paymentStates[order.paymentStatus].tone
                                "
                                >{{
                                    paymentStates[order.paymentStatus].label
                                }}</span
                            ><small>{{
                                order.method === 'cash' ? 'Tunai' : 'QRIS'
                            }}</small>
                        </td>
                        <td data-label="Status pesanan">
                            <span
                                class="orders-status"
                                :data-tone="orderStates[order.status].tone"
                                >{{ orderStates[order.status].label }}</span
                            >
                        </td>
                        <td data-label="Total">
                            <strong class="orders-money">{{
                                rupiah(total(order))
                            }}</strong>
                        </td>
                        <td class="orders-detail-cell">
                            <BaseButton
                                :aria-label="'Lihat detail ' + order.number"
                                @click="selectedNumber = order.number"
                                >Detail<AppIcon name="arrow" :size="16"
                            /></BaseButton>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="orders-empty">
                <AppIcon name="search" :size="32" />
                <h3>Tidak ada pesanan yang cocok</h3>
                <p>Coba nomor pesanan, nama pelanggan, atau status lain.</p>
                <BaseButton v-if="hasFilters" @click="resetFilters"
                    >Reset pencarian dan filter</BaseButton
                >
            </div>
            <footer class="orders-list-footer">
                <nav
                    v-if="pagination.last_page > 1"
                    aria-label="Halaman pesanan"
                >
                    <Link
                        v-if="pagination.prev_page_url"
                        :href="pagination.prev_page_url"
                        preserve-state
                        preserve-scroll
                        class="button"
                        >Sebelumnya</Link
                    >
                    <span
                        >Halaman {{ pagination.current_page }} /
                        {{ pagination.last_page }} · {{ pagination.from }}–{{
                            pagination.to
                        }}
                        dari {{ pagination.total }}</span
                    >
                    <Link
                        v-if="pagination.next_page_url"
                        :href="pagination.next_page_url"
                        preserve-state
                        preserve-scroll
                        class="button"
                        >Berikutnya</Link
                    >
                </nav>
                Dibayar menunjukkan status pembayaran; Selesai menunjukkan
                proses pesanan sudah ditutup.
            </footer>
        </section>

        <AppDialog
            :open="Boolean(selected)"
            :title="selected?.number ?? 'Detail pesanan'"
            class="orders-detail-dialog"
            @close="selectedNumber = null"
        >
            <div v-if="selected" class="orders-detail">
                <div class="orders-detail-statuses">
                    <div>
                        <span>Status pesanan</span
                        ><strong
                            class="orders-status"
                            :data-tone="orderStates[selected.status].tone"
                            >{{ orderStates[selected.status].label }}</strong
                        >
                    </div>
                    <div>
                        <span>Status pembayaran</span
                        ><strong
                            class="orders-status"
                            :data-tone="
                                paymentStates[selected.paymentStatus].tone
                            "
                            >{{
                                paymentStates[selected.paymentStatus].label
                            }}</strong
                        >
                    </div>
                </div>
                <p
                    v-if="selected.status === 'payment_review'"
                    class="orders-review-note"
                >
                    <AppIcon name="info" :size="18" />Pembayaran diterima
                    terlambat. Owner perlu memeriksa referensi, nominal, dan
                    waktu pembayaran. Pesanan belum masuk antrean kitchen.
                </p>
                <p
                    v-else-if="selected.status === 'awaiting_payment'"
                    class="orders-detail-help"
                >
                    Pesanan belum masuk antrean kitchen karena pembayaran belum
                    terverifikasi berhasil.
                </p>
                <p
                    v-else-if="selected.status === 'cancelled'"
                    class="orders-detail-help"
                >
                    Status dibatalkan tidak berarti pembayaran telah
                    dikembalikan. Lihat status pembayaran secara terpisah.
                </p>
                <section class="orders-detail-section">
                    <h3>Informasi pesanan</h3>
                    <dl class="orders-info-grid">
                        <div>
                            <dt>Pelanggan</dt>
                            <dd>{{ selected.customerName }}</dd>
                        </div>
                        <div v-if="selected.customerEmail">
                            <dt>Email</dt>
                            <dd>{{ selected.customerEmail }}</dd>
                        </div>
                        <div>
                            <dt>Sumber</dt>
                            <dd>{{ source(selected) }}</dd>
                        </div>
                        <div>
                            <dt>Layanan / meja</dt>
                            <dd>
                                {{
                                    selected.fulfillment === 'dine_in'
                                        ? 'Dine-in · Meja ' + selected.table
                                        : 'Takeaway'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Dibuat</dt>
                            <dd>{{ date(selected.createdAt) }}</dd>
                        </div>
                    </dl>
                </section>
                <section class="orders-detail-section">
                    <h3>Pembayaran</h3>
                    <div
                        v-if="
                            selected.method === 'qris' &&
                            selected.status === 'awaiting_payment' &&
                            selected.paymentStatus === 'pending'
                        "
                    >
                        <div
                            v-if="selected.qrImageUrl"
                            class="orders-qris-image"
                        >
                            <img
                                :src="selected.qrImageUrl"
                                :alt="`QRIS pembayaran ${selected.number}`"
                            />
                        </div>
                        <BaseButton
                            v-else
                            :disabled="retryingQris"
                            @click="retrySelectedQris(selected)"
                            >{{
                                retryingQris
                                    ? 'Memuat QRIS…'
                                    : 'Coba tampilkan QRIS'
                            }}</BaseButton
                        >
                        <p v-if="selected.expiresAt" class="orders-detail-help">
                            Batas bayar: {{ date(selected.expiresAt) }}
                        </p>
                    </div>
                    <dl class="orders-info-grid">
                        <div>
                            <dt>Metode</dt>
                            <dd>
                                {{
                                    selected.method === 'cash'
                                        ? 'Tunai'
                                        : 'QRIS'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Nominal pesanan</dt>
                            <dd>{{ rupiah(total(selected)) }}</dd>
                        </div>
                        <div>
                            <dt>Referensi pembayaran</dt>
                            <dd>{{ selected.reference ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Waktu dibayar</dt>
                            <dd>
                                {{
                                    selected.paidAt
                                        ? date(selected.paidAt)
                                        : 'Belum dibayar'
                                }}
                            </dd>
                        </div>
                        <template v-if="selected.method === 'cash'"
                            ><div>
                                <dt>Uang diterima</dt>
                                <dd>
                                    {{
                                        selected.received === null
                                            ? '—'
                                            : rupiah(selected.received)
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt>Kembalian</dt>
                                <dd>
                                    {{
                                        selected.change === null
                                            ? '—'
                                            : rupiah(selected.change)
                                    }}
                                </dd>
                            </div></template
                        >
                    </dl>
                </section>
                <section class="orders-detail-section">
                    <h3>Item pesanan</h3>
                    <p class="orders-detail-help">
                        Nama, pilihan, dan harga yang tersimpan saat checkout.
                    </p>
                    <ul class="orders-items">
                        <li v-for="line in selected.lines" :key="line.key">
                            <div>
                                <strong
                                    >{{ line.quantity }} ×
                                    {{ line.productName }}</strong
                                ><span>{{
                                    [
                                        line.variantName,
                                        ...line.optionNames,
                                    ].join(' · ')
                                }}</span
                                ><small
                                    >{{ rupiah(line.unitPrice) }} / item</small
                                >
                            </div>
                            <strong>{{
                                rupiah(line.unitPrice * line.quantity)
                            }}</strong>
                        </li>
                    </ul>
                    <div class="orders-grand-total">
                        <span>Total pesanan</span
                        ><strong>{{ rupiah(total(selected)) }}</strong>
                    </div>
                </section>
            </div>
            <template #footer>
                <form
                    v-if="
                        selected?.source === 'table_qr' &&
                        selected.status === 'awaiting_payment' &&
                        selected.paymentStatus === 'pending' &&
                        selected.method === 'cash'
                    "
                    @submit.prevent="confirmSelectedCash"
                >
                    <label class="field"
                        >Uang tunai diterima
                        <input
                            v-model.number="cashReceived"
                            type="number"
                            min="1"
                            step="1"
                            required
                            :disabled="confirmingCash"
                        />
                    </label>
                    <p v-if="selected.expiresAt" class="orders-detail-help">
                        Batas bayar: {{ date(selected.expiresAt) }}
                    </p>
                    <p v-if="cashError" class="pos-error" role="alert">
                        {{ cashError }}
                    </p>
                    <BaseButton
                        type="submit"
                        variant="primary"
                        :disabled="confirmingCash"
                        >{{
                            confirmingCash
                                ? 'Memproses…'
                                : 'Konfirmasi pembayaran tunai'
                        }}</BaseButton
                    >
                </form>
                <BaseButton @click="selectedNumber = null"
                    >Tutup detail</BaseButton
                >
            </template>
        </AppDialog>
    </div>
</template>
