<script setup lang="ts">
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import '../../../../css/kitchen.css';
import AppIcon from '@/components/AppIcon.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { orders, pos } from '@/routes/admin';
import { advance as advanceOrder } from '@/routes/admin/kitchen';
import { orderStates, type OrderData } from '@/types/orders';

defineOptions({ layout: AdminLayout });
const props = defineProps<{ orders: OrderData[] }>();
const page = usePage();
const canUsePos = computed(() => page.props.auth.capabilities['use-pos']);
type KitchenFilter = 'active' | 'queued' | 'preparing' | 'ready' | 'completed';
const filter = ref<KitchenFilter>('active');
const notice = ref('');
const requestError = ref('');
const pending = ref<Set<number>>(new Set());
const refreshing = ref(false);
const ticketGrid = ref<HTMLDivElement>();
const emptyHeading = ref<HTMLHeadingElement>();
const tabs: { key: KitchenFilter; label: string }[] = [
    { key: 'active', label: 'Semua aktif' },
    { key: 'queued', label: 'Dalam antrean' },
    { key: 'preparing', label: 'Sedang dibuat' },
    { key: 'ready', label: 'Siap' },
    { key: 'completed', label: 'Selesai' },
];
const actions = {
    queued: {
        label: 'Mulai buat',
        hint: 'Mulai membuat mengonsumsi reservasi dan memotong stok fisik sekali.',
    },
    preparing: {
        label: 'Tandai siap',
        hint: 'Tandai siap setelah semua item pesanan selesai dibuat.',
    },
    ready: {
        label: 'Selesaikan',
        hint: 'Tutup proses pesanan setelah diserahkan atau diantar ke pelanggan.',
    },
};
const visibleOrders = computed(() =>
    props.orders
        .filter((order) =>
            filter.value === 'active'
                ? order.status !== 'completed'
                : order.status === filter.value,
        )
        .toSorted((a, b) =>
            (a.paidAt ?? a.createdAt).localeCompare(b.paidAt ?? b.createdAt),
        ),
);
const count = (key: KitchenFilter): number =>
    props.orders.filter((order) =>
        key === 'active' ? order.status !== 'completed' : order.status === key,
    ).length;
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
function action(
    order: OrderData,
): (typeof actions)[keyof typeof actions] | null {
    return order.status === 'queued' ||
        order.status === 'preparing' ||
        order.status === 'ready'
        ? actions[order.status]
        : null;
}
function connectionError(): false {
    requestError.value =
        'Pembaruan gagal. Periksa koneksi lalu muat ulang data sebelum melanjutkan.';
    return false;
}

const poll = usePoll(
    5000,
    {
        only: ['orders'],
        onBefore: () => pending.value.size === 0 && !refreshing.value,
        onSuccess: () => {
            requestError.value = '';
        },
        onError: connectionError,
        onNetworkError: connectionError,
    },
    { mode: 'rest', background: 'pause' },
);

function refresh(): void {
    if (refreshing.value || pending.value.size) {
        return;
    }
    refreshing.value = true;
    router.reload({
        only: ['orders'],
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

function advance(order: OrderData, event: MouseEvent): void {
    if (pending.value.size || refreshing.value) {
        return;
    }
    const button = event.currentTarget as HTMLButtonElement;
    const previous = order.status;
    pending.value.add(order.id);
    requestError.value = '';
    router.post(
        advanceOrder(order.id),
        { expected_status: previous },
        {
            preserveScroll: true,
            onSuccess: async () => {
                const updated = props.orders.find(
                    (item) => item.id === order.id,
                );
                notice.value = `${order.number}: ${updated ? orderStates[updated.status].label : 'Status diperbarui'}. ${previous === 'queued' ? 'Reservasi dikonsumsi dan stok fisik dipotong.' : ''}`;
                await nextTick();
                if (!button.isConnected) {
                    const nextAction =
                        ticketGrid.value?.querySelector<HTMLButtonElement>(
                            'button',
                        );
                    (nextAction ?? emptyHeading.value)?.focus();
                }
            },
            onError: (errors) => {
                requestError.value =
                    Object.values(errors).join(' ') ||
                    'Status gagal diperbarui. Muat ulang tiket sebelum melanjutkan.';
            },
            onNetworkError: connectionError,
            onFinish: () => {
                pending.value.delete(order.id);
            },
        },
    );
}
</script>

<template>
    <div class="kitchen-page">
        <Head title="Kitchen" />
        <header class="kitchen-heading">
            <div>
                <span class="eyebrow">OPERASIONAL</span>
                <h1>Kitchen</h1>
                <p>Antrean pesanan tunai yang sudah dikonfirmasi kasir.</p>
            </div>
            <Link v-if="canUsePos" :href="pos()" class="button"
                ><AppIcon name="pos" :size="18" />Buka kasir POS</Link
            >
        </header>
        <ol class="kitchen-flow" aria-label="Alur pesanan setelah pembayaran">
            <li>
                <span>01</span>
                <div>
                    <strong>Dalam antrean</strong
                    ><small>Pembayaran sudah dikonfirmasi</small>
                </div>
            </li>
            <li>
                <span>02</span>
                <div>
                    <strong>Sedang dibuat</strong
                    ><small>Mulai buat · konsumsi stok sekali</small>
                </div>
            </li>
            <li>
                <span>03</span>
                <div>
                    <strong>Siap</strong
                    ><small>Semua item siap diserahkan</small>
                </div>
            </li>
            <li>
                <span>04</span>
                <div>
                    <strong>Selesai</strong
                    ><small>Proses pesanan ditutup</small>
                </div>
            </li>
        </ol>
        <div
            class="kitchen-filters"
            role="group"
            aria-label="Filter status kitchen"
        >
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                :aria-pressed="filter === tab.key"
                @click="filter = tab.key"
            >
                <span>{{ tab.label }}</span
                ><strong>{{ count(tab.key) }}</strong>
            </button>
        </div>
        <div class="kitchen-list-heading">
            <div>
                <h2>{{ tabs.find((tab) => tab.key === filter)?.label }}</h2>
                <p>
                    {{ visibleOrders.length }} tiket · urutan pembayaran terlama
                    lebih dulu
                    {{
                        filter === 'completed'
                            ? '· Maksimal 50 pesanan selesai terbaru'
                            : ''
                    }}
                </p>
            </div>
            <BaseButton
                :disabled="refreshing || pending.size > 0"
                @click="refresh"
                >{{ refreshing ? 'Memuat…' : 'Muat ulang' }}</BaseButton
            >
        </div>
        <p v-if="requestError" class="kitchen-notice" role="alert">
            {{ requestError }}
        </p>
        <p class="kitchen-notice" role="status" aria-live="polite">
            {{
                notice ||
                'Pembaruan otomatis setiap 5 detik. Pesanan belum dibayar dan pembayaran perlu pemeriksaan tidak masuk antrean ini.'
            }}
        </p>
        <div v-if="visibleOrders.length" ref="ticketGrid" class="kitchen-grid">
            <article
                v-for="order in visibleOrders"
                :key="order.number"
                class="kitchen-ticket"
                :data-state="order.status"
                :aria-labelledby="'ticket-' + order.number"
            >
                <header class="kitchen-ticket-heading">
                    <div>
                        <h3 :id="'ticket-' + order.number">
                            {{ order.number }}
                        </h3>
                        <p>
                            {{
                                order.source === 'cashier' ? 'Kasir' : 'QR meja'
                            }}
                            · {{ order.customerName }}
                        </p>
                    </div>
                    <span class="kitchen-state">{{
                        orderStates[order.status].label
                    }}</span>
                </header>
                <div class="kitchen-destination">
                    <AppIcon
                        :name="
                            order.fulfillment === 'dine_in'
                                ? 'grid'
                                : 'purchase'
                        "
                        :size="23"
                    />
                    <div>
                        <strong>{{
                            order.fulfillment === 'dine_in'
                                ? 'Meja ' + order.table
                                : 'Takeaway'
                        }}</strong
                        ><span>{{
                            order.fulfillment === 'dine_in'
                                ? 'Antar ke meja pelanggan'
                                : 'Serahkan di kasir'
                        }}</span>
                    </div>
                </div>
                <p class="kitchen-ticket-time">
                    Masuk setelah dibayar ·
                    {{ date(order.paidAt ?? order.createdAt) }}
                </p>
                <ul class="kitchen-items">
                    <li v-for="line in order.lines" :key="line.key">
                        <span class="kitchen-quantity"
                            >{{ line.quantity }}<small>×</small></span
                        >
                        <div>
                            <strong>{{ line.productName }}</strong>
                            <p>{{ line.variantName }}</p>
                            <p
                                v-if="line.optionNames.length"
                                class="kitchen-options"
                            >
                                {{ line.optionNames.join(' · ') }}
                            </p>
                        </div>
                    </li>
                </ul>
                <footer class="kitchen-ticket-footer">
                    <template v-if="action(order)"
                        ><p>{{ action(order)?.hint }}</p>
                        <BaseButton
                            variant="primary"
                            :disabled="pending.size > 0 || refreshing"
                            :aria-busy="pending.has(order.id)"
                            :aria-label="
                                action(order)?.label + ' ' + order.number
                            "
                            @click="advance(order, $event)"
                            >{{
                                pending.has(order.id)
                                    ? 'Menyimpan…'
                                    : action(order)?.label
                            }}<AppIcon name="arrow" :size="17" /></BaseButton
                    ></template>
                    <div v-else class="kitchen-finished">
                        <AppIcon name="check" :size="18" />Pemrosesan selesai
                    </div>
                    <Link
                        v-if="canUsePos"
                        :href="orders({ query: { order: order.number } })"
                        class="kitchen-order-link"
                        >Lihat detail pesanan<AppIcon name="arrow" :size="14"
                    /></Link>
                </footer>
            </article>
        </div>
        <section v-else class="kitchen-empty">
            <AppIcon name="coffee" :size="36" />
            <h3 ref="emptyHeading" tabindex="-1">
                {{
                    filter === 'completed'
                        ? 'Belum ada pesanan selesai'
                        : 'Tidak ada tiket pada status ini'
                }}
            </h3>
            <p>
                {{
                    filter === 'completed'
                        ? 'Pesanan yang diselesaikan akan muncul di sini.'
                        : canUsePos
                          ? 'Coba filter lain atau buat pesanan tunai dari POS.'
                          : 'Coba filter lain atau tunggu pesanan yang dikonfirmasi kasir.'
                }}
            </p>
            <BaseButton v-if="filter !== 'active'" @click="filter = 'active'"
                >Lihat semua aktif</BaseButton
            ><Link
                v-else-if="canUsePos"
                :href="pos()"
                class="button button-primary"
                >Buat pesanan di POS</Link
            >
        </section>
    </div>
</template>
