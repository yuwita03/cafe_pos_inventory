<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { menu } from '@/routes/admin';
import { formatRupiah as rupiah } from '@/lib/utils';

defineOptions({ layout: AdminLayout });
const period = ref<'current' | 'previous'>('current');
const periods = {
    current: {
        label: '5–11 Oktober 2026',
        sales: [420000, 650000, 510000, 370000, 780000, 1120000, 630000],
        orders: 160,
        items: 178,
        review: 2,
        products: [
            { name: 'Americano', category: 'Coffee', quantity: 46 },
            { name: 'Latte', category: 'Coffee', quantity: 38 },
            { name: 'Cappuccino', category: 'Coffee', quantity: 25 },
            { name: 'Croissant', category: 'Pastry', quantity: 23 },
            { name: 'Chocolate', category: 'Non-Coffee', quantity: 20 },
        ],
    },
    previous: {
        label: '28 September–4 Oktober 2026',
        sales: [350000, 580000, 460000, 420000, 690000, 980000, 550000],
        orders: 144,
        items: 160,
        review: 1,
        products: [
            { name: 'Latte', category: 'Coffee', quantity: 40 },
            { name: 'Americano', category: 'Coffee', quantity: 34 },
            { name: 'Croissant', category: 'Pastry', quantity: 26 },
            { name: 'Cappuccino', category: 'Coffee', quantity: 22 },
            { name: 'Chocolate', category: 'Non-Coffee', quantity: 18 },
        ],
    },
};
const data = computed(() => periods[period.value]);
const totalSales = computed(() =>
    data.value.sales.reduce((total, value) => total + value, 0),
);
const maximum = 1200000;
const selectedDay = ref<number | null>(null);
const days = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const lowStock = [
    {
        name: 'Coffee beans',
        unit: 'g',
        available: 350,
        threshold: 500,
        icon: 'coffee',
    },
    {
        name: 'Fresh milk',
        unit: 'ml',
        available: 800,
        threshold: 1000,
        icon: 'category',
    },
    {
        name: 'Croissant',
        unit: 'pcs',
        available: 4,
        threshold: 10,
        icon: 'image',
    },
];
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
            <label class="dashboard-period"
                ><span>Periode contoh</span
                ><select
                    v-model="period"
                    aria-label="Pilih periode data contoh"
                    @change="selectedDay = null"
                >
                    <option value="current">5–11 Okt 2026</option>
                    <option value="previous">28 Sep–4 Okt 2026</option>
                </select></label
            >
        </div>
        <div class="dashboard-demo">
            <AppIcon name="info" :size="18" />
            <p>
                <strong>Pratinjau dashboard</strong> Seluruh angka dan grafik di
                bawah adalah data contoh, bukan transaksi café.
            </p>
            <span>DATA CONTOH</span>
        </div>
        <div class="dashboard-metrics">
            <article>
                <div class="metric-label">
                    <AppIcon name="coffee" :size="18" />Penjualan
                    terkonfirmasi<span class="metric-marker">IDR</span>
                </div>
                <strong class="metric-value">{{ rupiah(totalSales) }}</strong>
                <p>Contoh pembayaran terverifikasi · {{ data.label }}</p>
            </article>
            <article>
                <div class="metric-label">
                    <AppIcon name="check" :size="18" />Pesanan dibayar<span
                        class="metric-marker"
                        >ORDER</span
                    >
                </div>
                <strong class="metric-value"
                    >{{ data.orders }}<small>pesanan</small></strong
                >
                <p>Data contoh pada periode terpilih</p>
            </article>
            <article>
                <div class="metric-label">
                    <AppIcon name="category" :size="18" />Stok perlu
                    perhatian<span class="metric-marker">STOK</span>
                </div>
                <strong class="metric-value"
                    >{{ lowStock.length }}<small>item</small></strong
                >
                <p>Kondisi stok contoh saat ini · di luar filter periode</p>
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
                        <p>Contoh penjualan harian · {{ data.label }}</p>
                    </div>
                    <span class="dashboard-panel-tag"><span />Dibayar</span>
                </header>
                <div class="sales-total">
                    <strong>{{ rupiah(totalSales) }}</strong
                    ><span>Total periode contoh</span>
                </div>
                <div class="sales-chart">
                    <div class="chart-axis" aria-hidden="true">
                        <span>Rp1,2 jt</span><span>Rp900 rb</span
                        ><span>Rp600 rb</span><span>Rp300 rb</span
                        ><span>Rp0</span>
                    </div>
                    <div class="chart-plot">
                        <div class="chart-grid" aria-hidden="true">
                            <i /><i /><i /><i /><i />
                        </div>
                        <div class="chart-bars">
                            <button
                                v-for="(sale, index) in data.sales"
                                :key="index"
                                class="chart-bar-column"
                                :class="{
                                    highlighted:
                                        selectedDay === index ||
                                        (selectedDay === null && index === 5),
                                }"
                                :aria-label="`${days[index]}: ${rupiah(sale)}, data contoh`"
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
                                            height: `${(sale / maximum) * 100}%`,
                                        }"
                                        ><span class="chart-value">{{
                                            rupiah(sale)
                                        }}</span></span
                                    ></span
                                ><span class="chart-day">{{
                                    days[index]
                                }}</span>
                            </button>
                        </div>
                    </div>
                </div>
                <p class="chart-caption" aria-live="polite">
                    {{
                        selectedDay === null
                            ? 'Pilih batang untuk melihat nilai penjualan contoh.'
                            : `${days[selectedDay]} · ${rupiah(data.sales[selectedDay])} (data contoh)`
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
                        <p>Periode yang sama · data contoh</p>
                    </div>
                </header>
                <div class="business-stat business-lilac">
                    <span>Rata-rata nilai pesanan</span
                    ><strong>{{
                        rupiah(Math.round(totalSales / data.orders))
                    }}</strong
                    ><AppIcon name="coffee" :size="20" />
                </div>
                <div class="business-stat business-peach">
                    <span>Produk terjual</span
                    ><strong>{{ data.items }}<small>item</small></strong
                    ><AppIcon name="category" :size="20" />
                </div>
                <div class="business-stat business-sage">
                    <span>Perlu pemeriksaan pembayaran</span
                    ><strong
                        >{{ data.review }}<small>kasus contoh</small></strong
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
                        <p>Kondisi contoh saat ini, bukan riwayat periode</p>
                    </div>
                    <span class="stock-count">{{ lowStock.length }} item</span>
                </header>
                <div
                    v-for="item in lowStock"
                    :key="item.name"
                    class="dashboard-stock-row"
                >
                    <span class="stock-item-icon"
                        ><AppIcon :name="item.icon" :size="22"
                    /></span>
                    <div class="stock-item-info">
                        <h3>{{ item.name }}</h3>
                        <p>
                            Tersedia {{ item.available }} {{ item.unit
                            }}<span>·</span>Batas contoh {{ item.threshold }}
                            {{ item.unit }}
                        </p>
                        <div class="stock-meter" aria-hidden="true">
                            <span
                                :style="{
                                    width: `${(item.available / item.threshold) * 100}%`,
                                }"
                            />
                        </div>
                    </div>
                    <span class="stock-warning">Rendah</span>
                </div>
                <p class="stock-footnote">
                    Angka tersedia dan batas di atas hanya ilustrasi tampilan.
                </p>
            </section>
            <section
                class="dashboard-panel top-products-panel"
                aria-labelledby="top-products-heading"
            >
                <header class="dashboard-panel-heading">
                    <div>
                        <h2 id="top-products-heading">Produk terlaris</h2>
                        <p>Berdasarkan jumlah item · {{ data.label }}</p>
                    </div>
                    <Link
                        :href="menu()"
                        class="icon-button"
                        aria-label="Buka pengelolaan produk"
                        ><AppIcon name="arrow" :size="18"
                    /></Link>
                </header>
                <div
                    v-for="(product, index) in data.products"
                    :key="product.name"
                    class="top-product-row"
                >
                    <span class="product-rank">{{
                        String(index + 1).padStart(2, '0')
                    }}</span
                    ><span
                        class="top-product-image"
                        :class="{ pastry: product.category === 'Pastry' }"
                        ><AppIcon
                            :name="
                                product.category === 'Pastry'
                                    ? 'image'
                                    : 'coffee'
                            "
                            :size="21"
                    /></span>
                    <div>
                        <h3>{{ product.name }}</h3>
                        <p>{{ product.category }}</p>
                    </div>
                    <strong>{{ product.quantity }}<small>item</small></strong>
                </div>
            </section>
        </div>
        <p class="page-note">
            <AppIcon name="info" :size="16" />Prototipe tampilan admin. Belum
            ada koneksi data, perhitungan laporan, atau aksi operasional nyata.
        </p>
    </div>
</template>

<style scoped>
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
    padding-bottom: 28px;
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
    inset: 0 0 28px;
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
    height: calc(100% - 28px);
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
