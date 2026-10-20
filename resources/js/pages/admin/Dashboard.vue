<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { dashboard, menu } from '@/routes/admin';
import { formatQuantity, formatRupiah as rupiah } from '@/lib/utils';

type Period =
    | 'today'
    | 'yesterday'
    | 'this_week'
    | 'last_week'
    | 'this_month'
    | 'last_month'
    | 'custom';

interface DashboardAnalytics {
    period: {
        selected: Period;
        label: string;
        start: string;
        end: string;
        comparisonLabel: string;
        timezone: string;
        today: string;
        cutoff: string | null;
        options: Array<{ value: Period; label: string }>;
    };
    summary: {
        confirmedRevenue: number;
        paidOrders: number;
        averageOrderValue: number;
        itemsSold: number;
        paymentReviews: number;
    };
    comparison: {
        confirmedRevenue: number | null;
        paidOrders: number | null;
        averageOrderValue: number | null;
        itemsSold: number | null;
    };
    chart: {
        granularity: 'day' | 'week' | 'month';
        points: Array<{
            start: string;
            end: string;
            label: string;
            revenue: number;
        }>;
    };
    topProducts: Array<{ name: string; quantity: number }>;
    lowStock: Array<{
        id: number;
        name: string;
        unit: string;
        available: number;
        threshold: number;
    }>;
}

defineOptions({ layout: AdminLayout });
const { analytics } = defineProps<{ analytics: DashboardAnalytics }>();
const form = useForm({
    period: analytics.period.selected,
    start: analytics.period.start,
    end: analytics.period.end,
});
const selectedDay = ref<number | null>(null);
const selectedSale = computed(() =>
    selectedDay.value === null
        ? null
        : (analytics.chart.points[selectedDay.value] ?? null),
);
const maximum = computed(() =>
    Math.max(...analytics.chart.points.map((sale) => sale.revenue), 1),
);
const axisValues = computed(() =>
    [1, 0.75, 0.5, 0.25, 0].map((ratio) =>
        rupiah(Math.round(maximum.value * ratio)),
    ),
);

function changePeriod(): void {
    form.clearErrors();
    if (form.period !== 'custom') {
        applyPeriod();
    }
}

function applyPeriod(): void {
    selectedDay.value = null;
    form.transform((data) =>
        data.period === 'custom' ? data : { period: data.period },
    ).get(dashboard.url(), {
        preserveScroll: true,
        preserveState: 'errors',
    });
}

function comparisonText(value: number | null): string {
    if (value === null) {
        return 'Belum ada pembanding';
    }
    if (value === 0) {
        return 'Tetap dari periode pembanding';
    }

    return `${value > 0 ? '+' : ''}${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(value)}% dari periode pembanding`;
}

const chartHeading = computed(
    () =>
        ({
            day: 'Penjualan harian',
            week: 'Penjualan mingguan',
            month: 'Penjualan bulanan',
        })[analytics.chart.granularity],
);

function stockPercentage(item: DashboardAnalytics['lowStock'][number]): number {
    if (item.threshold <= 0) {
        return item.available > 0 ? 100 : 0;
    }

    return Math.max(0, Math.min(100, (item.available / item.threshold) * 100));
}
</script>

<template>
    <div class="dashboard-page">
        <Head title="Dashboard · Admin" />
        <div class="page-heading dashboard-heading">
            <div>
                <span class="eyebrow">RUANG OPERASIONAL</span>
                <h1>Dashboard<span class="title-dot">.</span></h1>
                <p>
                    Selamat datang kembali. Lihat ringkasan café dalam satu
                    tempat.
                </p>
            </div>
            <form class="dashboard-filter" @submit.prevent="applyPeriod">
                <label class="dashboard-period"
                    ><span>Periode laporan</span
                    ><select
                        v-model="form.period"
                        :disabled="form.processing"
                        aria-label="Pilih periode laporan"
                        @change="changePeriod"
                    >
                        <option
                            v-for="option in analytics.period.options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select></label
                >
                <div v-if="form.period === 'custom'" class="custom-dates">
                    <label>
                        <span>Tanggal mulai</span>
                        <input
                            v-model="form.start"
                            type="date"
                            required
                            :max="analytics.period.today"
                            :disabled="form.processing"
                            :aria-invalid="Boolean(form.errors.start)"
                            aria-describedby="start-error"
                        />
                        <small
                            v-if="form.errors.start"
                            id="start-error"
                            class="filter-error"
                            role="alert"
                            >{{ form.errors.start }}</small
                        >
                    </label>
                    <label>
                        <span>Tanggal akhir</span>
                        <input
                            v-model="form.end"
                            type="date"
                            required
                            :min="form.start"
                            :max="analytics.period.today"
                            :disabled="form.processing"
                            :aria-invalid="Boolean(form.errors.end)"
                            aria-describedby="end-error"
                        />
                        <small
                            v-if="form.errors.end"
                            id="end-error"
                            class="filter-error"
                            role="alert"
                            >{{ form.errors.end }}</small
                        >
                    </label>
                    <button
                        type="submit"
                        class="filter-apply"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Memuat…' : 'Terapkan' }}
                    </button>
                </div>
                <small
                    v-if="form.errors.period"
                    class="filter-error"
                    role="alert"
                    >{{ form.errors.period }}</small
                >
            </form>
        </div>
        <div class="dashboard-demo">
            <AppIcon name="info" :size="18" />
            <p>
                <strong>Data transaksi</strong> Penjualan dihitung dari
                pembayaran yang sudah terkonfirmasi.
            </p>
            <span
                >{{ analytics.period.label }} ·
                {{ analytics.period.timezone }}</span
            >
        </div>
        <p class="report-comparison">
            Dibandingkan dengan {{ analytics.period.comparisonLabel }} · WIB
            <span v-if="analytics.period.cutoff">
                · Data dan pembanding hingga {{ analytics.period.cutoff }}</span
            >
        </p>
        <div class="dashboard-metrics">
            <article>
                <div class="metric-label">
                    <AppIcon name="coffee" :size="18" />Penjualan
                    terkonfirmasi<span class="metric-marker">IDR</span>
                </div>
                <strong class="metric-value">{{
                    rupiah(analytics.summary.confirmedRevenue)
                }}</strong>
                <p>
                    {{ comparisonText(analytics.comparison.confirmedRevenue) }}
                </p>
            </article>
            <article>
                <div class="metric-label">
                    <AppIcon name="check" :size="18" />Pesanan dibayar<span
                        class="metric-marker"
                        >ORDER</span
                    >
                </div>
                <strong class="metric-value"
                    >{{ analytics.summary.paidOrders
                    }}<small>pesanan</small></strong
                >
                <p>{{ comparisonText(analytics.comparison.paidOrders) }}</p>
            </article>
            <article>
                <div class="metric-label">
                    <AppIcon name="category" :size="18" />Stok perlu
                    perhatian<span class="metric-marker">STOK</span>
                </div>
                <strong class="metric-value"
                    >{{ analytics.lowStock.length }}<small>item</small></strong
                >
                <p>Kondisi stok saat ini · di luar filter periode</p>
            </article>
        </div>
        <div class="dashboard-middle">
            <section
                class="dashboard-panel sales-panel"
                aria-labelledby="sales-heading"
            >
                <header class="dashboard-panel-heading">
                    <div>
                        <h2 id="sales-heading">Ringkasan penjualan</h2>
                        <p>{{ chartHeading }} · {{ analytics.period.label }}</p>
                    </div>
                    <span class="dashboard-panel-tag"><span />Dibayar</span>
                </header>
                <div class="sales-total">
                    <strong>{{
                        rupiah(analytics.summary.confirmedRevenue)
                    }}</strong
                    ><span>Total periode terpilih</span>
                </div>
                <div class="sales-chart-scroll">
                    <div
                        class="sales-chart"
                        :style="{
                            minWidth: `${Math.max(280, analytics.chart.points.length * 70 + 66)}px`,
                        }"
                    >
                        <div class="chart-axis" aria-hidden="true">
                            <span
                                v-for="(value, index) in axisValues"
                                :key="index"
                                >{{ value }}</span
                            >
                        </div>
                        <div class="chart-plot">
                            <div class="chart-grid" aria-hidden="true">
                                <i /><i /><i /><i /><i />
                            </div>
                            <div class="chart-bars">
                                <button
                                    v-for="(sale, index) in analytics.chart
                                        .points"
                                    :key="sale.start"
                                    class="chart-bar-column"
                                    :class="{
                                        highlighted: selectedDay === index,
                                    }"
                                    :aria-label="`${sale.label}: ${rupiah(sale.revenue)}`"
                                    :aria-pressed="selectedDay === index"
                                    @click="
                                        selectedDay =
                                            selectedDay === index ? null : index
                                    "
                                >
                                    <span class="chart-bar-slot"
                                        ><span
                                            class="chart-bar"
                                            :style="{
                                                height: `${(sale.revenue / maximum) * 100}%`,
                                            }"
                                            ><span class="chart-value">{{
                                                rupiah(sale.revenue)
                                            }}</span></span
                                        ></span
                                    ><span class="chart-day">{{
                                        sale.label.replace(/ \d{4}/g, '')
                                    }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="chart-caption" aria-live="polite">
                    {{
                        selectedSale === null
                            ? analytics.summary.paidOrders === 0
                                ? 'Belum ada penjualan pada periode ini.'
                                : 'Pilih batang untuk melihat nilai penjualan.'
                            : `${selectedSale.label} · ${rupiah(selectedSale.revenue)}`
                    }}
                </p>
            </section>
            <section
                class="dashboard-panel business-panel"
                aria-labelledby="business-heading"
            >
                <header class="dashboard-panel-heading">
                    <div>
                        <h2 id="business-heading">Sekilas bisnis</h2>
                        <p>{{ analytics.period.label }}</p>
                    </div>
                </header>
                <div class="business-stat business-lilac">
                    <span>Rata-rata nilai pesanan</span
                    ><strong>{{
                        rupiah(analytics.summary.averageOrderValue)
                    }}</strong
                    ><small class="business-comparison">{{
                        comparisonText(analytics.comparison.averageOrderValue)
                    }}</small
                    ><AppIcon name="coffee" :size="20" />
                </div>
                <div class="business-stat business-peach">
                    <span>Produk terjual</span
                    ><strong
                        >{{ analytics.summary.itemsSold
                        }}<small>item</small></strong
                    ><small class="business-comparison">{{
                        comparisonText(analytics.comparison.itemsSold)
                    }}</small
                    ><AppIcon name="category" :size="20" />
                </div>
                <div class="business-stat business-sage">
                    <span>Perlu pemeriksaan pembayaran</span
                    ><strong
                        >{{ analytics.summary.paymentReviews
                        }}<small>kasus</small></strong
                    ><AppIcon name="info" :size="20" />
                </div>
                <p class="business-note">
                    Payment review ditampilkan terpisah dari penjualan
                    terkonfirmasi.
                </p>
            </section>
        </div>
        <div class="dashboard-bottom">
            <section
                class="dashboard-panel stock-panel"
                aria-labelledby="stock-heading"
            >
                <header class="dashboard-panel-heading">
                    <div>
                        <h2 id="stock-heading">Peringatan stok</h2>
                        <p>Kondisi saat ini, bukan riwayat periode</p>
                    </div>
                    <span class="stock-count"
                        >{{ analytics.lowStock.length }} item</span
                    >
                </header>
                <p v-if="analytics.lowStock.length === 0" class="empty-state">
                    Tidak ada stok yang perlu diperhatikan.
                </p>
                <div
                    v-for="item in analytics.lowStock"
                    :key="item.id"
                    class="dashboard-stock-row"
                >
                    <span class="stock-item-icon"
                        ><AppIcon
                            :name="item.unit === 'pcs' ? 'image' : 'category'"
                            :size="22"
                    /></span>
                    <div class="stock-item-info">
                        <h3>{{ item.name }}</h3>
                        <p>
                            Tersedia {{ formatQuantity(item.available) }}
                            {{ item.unit }}<span>·</span>Batas
                            {{ formatQuantity(item.threshold) }} {{ item.unit }}
                        </p>
                        <div class="stock-meter" aria-hidden="true">
                            <span
                                :style="{
                                    width: `${stockPercentage(item)}%`,
                                }"
                            />
                        </div>
                    </div>
                    <span class="stock-warning">Rendah</span>
                </div>
                <p class="stock-footnote">
                    Stok tersedia sudah dikurangi reservasi pesanan aktif.
                </p>
            </section>
            <section
                class="dashboard-panel top-products-panel"
                aria-labelledby="top-products-heading"
            >
                <header class="dashboard-panel-heading">
                    <div>
                        <h2 id="top-products-heading">Produk terlaris</h2>
                        <p>
                            Berdasarkan jumlah item ·
                            {{ analytics.period.label }}
                        </p>
                    </div>
                    <Link
                        :href="menu()"
                        class="icon-button"
                        aria-label="Buka pengelolaan produk"
                        ><AppIcon name="arrow" :size="18"
                    /></Link>
                </header>
                <p
                    v-if="analytics.topProducts.length === 0"
                    class="empty-state"
                >
                    Belum ada produk terjual pada periode ini.
                </p>
                <div
                    v-for="(product, index) in analytics.topProducts"
                    :key="product.name"
                    class="top-product-row"
                >
                    <span class="product-rank">{{
                        String(index + 1).padStart(2, '0')
                    }}</span
                    ><span class="top-product-image"
                        ><AppIcon name="coffee" :size="21"
                    /></span>
                    <div>
                        <h3>{{ product.name }}</h3>
                        <p>Produk terjual</p>
                    </div>
                    <strong>{{ product.quantity }}<small>item</small></strong>
                </div>
            </section>
        </div>
        <p class="page-note">
            <AppIcon name="info" :size="16" />Penjualan mengikuti waktu
            pembayaran lunas. Peringatan stok selalu menunjukkan kondisi
            terkini.
        </p>
    </div>
</template>

<style scoped>
.dashboard-filter {
    display: grid;
    gap: 12px;
    max-width: 100%;
}
.custom-dates {
    display: flex;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 10px;
}
.custom-dates label {
    display: grid;
    gap: 6px;
    flex: 1;
    min-width: 140px;
}
.custom-dates label > span {
    color: #7c8272;
    font-size: 11px;
}
.custom-dates input,
.filter-apply {
    min-height: 44px;
    padding: 10px;
    border: 1px solid var(--line);
    border-radius: 7px;
    background: white;
    font: inherit;
    font-size: 12px;
}
.filter-apply {
    margin-top: 20px;
    background: var(--primary);
    color: white;
}
.filter-apply:disabled {
    opacity: 0.6;
}
.filter-error {
    max-width: 220px;
    color: #b42318;
    font-size: 11px;
}
.report-comparison {
    margin: -12px 0 20px;
    color: #7c8272;
    font-size: 12px;
    line-height: 1.7;
}
.sales-chart-scroll {
    overflow-x: auto;
}
.dashboard-period {
    display: grid;
    gap: 6px;
    color: var(--muted);
    font-size: 12px;
    flex-shrink: 0;
}
.dashboard-period > span {
    text-align: right;
}
.dashboard-period select {
    min-height: 42px;
    padding: 8px 32px 8px 13px;
    border: 1px solid #dedfd8;
    border-radius: 7px;
    background: white;
    color: #4c5144;
    font-size: 12px;
}
.dashboard-demo {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 22px;
    padding: 12px 16px;
    border: 1px solid #eee3cc;
    border-radius: 8px;
    background: #fffcf5;
    color: #8a6f40;
    font-size: 12px;
}
.dashboard-demo strong {
    margin-right: 6px;
    font-weight: 600;
}
.dashboard-demo > span {
    margin-left: auto;
    flex-shrink: 0;
    font-size: 10px;
    letter-spacing: 1px;
}
.dashboard-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 22px;
}
.dashboard-metrics article {
    padding: 22px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: white;
}
.metric-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #5e6256;
    font-size: 12px;
}
.metric-label svg {
    color: var(--primary);
}
.metric-marker {
    margin-left: auto;
    padding: 4px 6px;
    border: 1px solid #efefe9;
    border-radius: 5px;
    color: #92968a;
    font-size: 9px;
    letter-spacing: 0.6px;
}
.metric-value {
    display: block;
    margin-top: 18px;
    font-size: clamp(24px, 2.1vw, 34px);
    font-weight: 600;
    letter-spacing: -1.1px;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}
.metric-value small {
    margin-left: 8px;
    color: #8a8f80;
    font-size: 13px;
    font-weight: 400;
    letter-spacing: 0;
}
.dashboard-metrics article > p {
    margin-top: 9px;
    font-size: 11px;
    line-height: 1.6;
    color: #7b8171;
}
.dashboard-middle {
    display: grid;
    grid-template-columns: minmax(0, 1.8fr) minmax(280px, 1fr);
    gap: 22px;
    margin-bottom: 22px;
}
.dashboard-panel {
    min-width: 0;
    padding: 23px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: white;
}
.dashboard-panel-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
}
.dashboard-panel-heading h2 {
    font-size: 16px;
    font-weight: 600;
    letter-spacing: -0.3px;
}
.dashboard-panel-heading p {
    margin-top: 4px;
    color: #7c8272;
    font-size: 11px;
}
.dashboard-panel-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 8px;
    border: 1px solid #eceee7;
    border-radius: 6px;
    color: #7b836f;
    font-size: 11px;
}
.dashboard-panel-tag > span {
    width: 6px;
    height: 6px;
    border-radius: 2px;
    background: #df7048;
}
.sales-total {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin: 2px 0 25px;
}
.sales-total strong {
    font-size: 25px;
    font-weight: 550;
    letter-spacing: -0.7px;
}
.sales-total > span {
    color: #8a9080;
    font-size: 11px;
}
.sales-chart {
    display: flex;
    gap: 12px;
    height: 197px;
}
.chart-axis {
    display: flex;
    width: 54px;
    flex-shrink: 0;
    flex-direction: column;
    justify-content: space-between;
    padding-bottom: 48px;
    color: #8b9180;
    font-size: 10px;
}
.chart-plot {
    position: relative;
    flex: 1;
    min-width: 0;
}
.chart-grid {
    position: absolute;
    inset: 0 0 48px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.chart-grid i {
    display: block;
    border-top: 1px dashed #eceee6;
}
.chart-bars {
    display: flex;
    gap: 13px;
    height: 100%;
}
.chart-bar-column {
    display: flex;
    align-items: stretch;
    flex: 1;
    min-width: 0;
    flex-direction: column;
    border: 0;
    padding: 0;
    background: transparent;
}
.chart-bar-slot {
    display: flex;
    align-items: flex-end;
    height: calc(100% - 48px);
}
.chart-bar {
    display: block;
    position: relative;
    width: 100%;
    min-height: 3px;
    border-radius: 6px 6px 3px 3px;
    background: repeating-linear-gradient(
        -45deg,
        #f2f1ec,
        #f2f1ec 3px,
        #f8f8f4 3px,
        #f8f8f4 6px
    );
}
.chart-bar-column.highlighted .chart-bar,
.chart-bar-column:hover .chart-bar,
.chart-bar-column:focus-visible .chart-bar {
    background: #e5693c;
}
.chart-day {
    display: block;
    padding-top: 9px;
    color: #929786;
    font-size: 11px;
}
.highlighted .chart-day {
    color: var(--primary);
    font-weight: 600;
}
.chart-value {
    display: none;
    position: absolute;
    left: 50%;
    bottom: calc(100% + 7px);
    transform: translateX(-50%);
    z-index: 2;
    padding: 5px 7px;
    border-radius: 4px;
    background: #34392c;
    color: white;
    white-space: nowrap;
    font-size: 10px;
}
.chart-bar-column:hover .chart-value,
.chart-bar-column:focus-visible .chart-value,
.chart-bar-column[aria-pressed='true'] .chart-value {
    display: block;
}
.chart-caption {
    min-height: 18px;
    margin-top: 14px !important;
    color: #8c9380;
    font-size: 11px;
}
.business-stat {
    position: relative;
    margin-bottom: 12px;
    padding: 15px;
    border-radius: 8px;
}
.business-lilac {
    background: #f1effb;
}
.business-peach {
    background: #fcf3e9;
}
.business-sage {
    background: #edf3ed;
}
.business-stat > span {
    display: block;
    padding-right: 25px;
    color: #666b5d;
    font-size: 11px;
}
.business-stat strong {
    display: block;
    margin-top: 6px;
    font-size: 23px;
    font-weight: 550;
    letter-spacing: -0.6px;
}
.business-stat small {
    margin-left: 7px;
    color: #818877;
    font-size: 11px;
    font-weight: 400;
    letter-spacing: 0;
}
.business-stat .business-comparison {
    display: block;
    margin: 6px 0 0;
    font-size: 10px;
}
.business-stat > svg {
    position: absolute;
    right: 14px;
    top: 16px;
    color: #89917b;
}
.business-note {
    margin-top: 14px !important;
    color: #88917b;
    font-size: 11px;
    line-height: 1.6;
}
.dashboard-bottom {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}
.stock-count {
    flex-shrink: 0;
    padding: 5px 8px;
    border-radius: 5px;
    background: #fff6df;
    color: #8d6b22;
    font-size: 11px;
}
.dashboard-stock-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 19px 0;
    border-top: 1px solid #f0f0ea;
}
.stock-item-icon,
.top-product-image {
    display: grid;
    place-items: center;
    width: 43px;
    height: 43px;
    flex-shrink: 0;
    border-radius: 9px;
    background: #f6f0e5;
    color: #af9268;
}
.stock-item-info {
    flex: 1;
    min-width: 0;
}
.stock-item-info h3,
.top-product-row h3 {
    font-size: 13px;
    font-weight: 550;
}
.stock-item-info p {
    margin-top: 3px;
    color: #7b8470;
    font-size: 11px;
}
.stock-item-info p > span {
    padding: 0 6px;
    color: #bdc3b3;
}
.stock-meter {
    max-width: 190px;
    height: 4px;
    margin-top: 11px;
    overflow: hidden;
    border-radius: 4px;
    background: #f2f1e8;
}
.stock-meter > span {
    display: block;
    height: 100%;
    border-radius: 4px;
    background: #d2a563;
}
.stock-warning {
    padding: 4px 7px;
    border-radius: 5px;
    background: #fff8e6;
    color: #9a7426;
    font-size: 10px;
}
.stock-footnote {
    margin-top: 12px !important;
    color: #89917e;
    font-size: 11px;
}
.empty-state {
    padding: 26px 0;
    color: #89917e;
    font-size: 12px;
    text-align: center;
}
.top-products-panel .dashboard-panel-heading {
    margin-bottom: 9px;
}
.top-product-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 0;
    border-bottom: 1px solid #f0f0ea;
}
.top-product-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}
.product-rank {
    min-width: 18px;
    color: #a6ad9b;
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}
.top-product-image {
    width: 36px;
    height: 36px;
    border-radius: 7px;
}
.top-product-image.pastry {
    background: #f9ead7;
    color: #b99568;
}
.top-product-row p {
    margin-top: 2px;
    color: #8a9380;
    font-size: 10px;
}
.top-product-row > strong {
    margin-left: auto;
    color: #4b5540;
    font-size: 21px;
    font-weight: 550;
    letter-spacing: -0.5px;
}
.top-product-row > strong small {
    margin-left: 7px;
    color: #aa7853;
    font-size: 10px;
    font-weight: 400;
    letter-spacing: 0;
}
@media (max-width: 1100px) {
    .dashboard-metrics article {
        padding: 18px;
    }
    .metric-marker {
        display: none;
    }
    .metric-value {
        font-size: 26px;
    }
    .dashboard-middle {
        grid-template-columns: minmax(0, 1.6fr) minmax(260px, 1fr);
        gap: 17px;
    }
    .dashboard-panel {
        padding: 19px;
    }
    .chart-bars {
        gap: 9px;
    }
}
@media (max-width: 767px) {
    .dashboard-heading {
        flex-direction: row;
        flex-wrap: wrap;
    }
    .dashboard-period {
        width: 100%;
    }
    .dashboard-period > span {
        text-align: left;
    }
    .dashboard-period select {
        min-height: 44px;
    }
    .dashboard-demo {
        align-items: flex-start;
        line-height: 1.7;
    }
    .dashboard-demo > svg {
        margin-top: 3px;
    }
    .dashboard-demo > span {
        display: none;
    }
    .dashboard-demo strong {
        display: block;
    }
    .dashboard-metrics {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .dashboard-metrics article {
        padding: 19px;
    }
    .metric-marker {
        display: block;
    }
    .metric-value {
        font-size: 30px;
        margin-top: 12px;
    }
    .dashboard-middle,
    .dashboard-bottom {
        grid-template-columns: minmax(0, 1fr);
        gap: 18px;
    }
    .dashboard-panel {
        padding: 19px 16px;
    }
    .dashboard-panel-heading h2 {
        font-size: 16px;
    }
    .dashboard-panel-heading p {
        line-height: 1.7;
    }
    .sales-total {
        align-items: flex-start;
        flex-direction: column;
        gap: 3px;
    }
    .chart-bars {
        gap: 8px;
    }
    .chart-axis {
        width: 46px;
        font-size: 9px;
    }
    .sales-chart {
        gap: 8px;
    }
    .dashboard-stock-row {
        gap: 10px;
    }
    .stock-item-icon {
        width: 35px;
        height: 35px;
    }
    .stock-item-info p {
        font-size: 10px;
    }
    .stock-item-info p > span {
        padding: 0 3px;
    }
    .stock-warning {
        font-size: 9px;
        padding: 4px 5px;
    }
    .chart-value {
        font-size: 9px;
    }
}
</style>
