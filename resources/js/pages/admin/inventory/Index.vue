<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import InventoryRecipes from '@/components/admin/InventoryRecipes.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index as inventory, recipes, history } from '@/routes/admin/inventory';
import {
    type StockItem,
    type StockUnit,
    type RecipeCatalog,
    type Movement,
    type VariantRecipe,
    type Ingredient,
} from '@/types/inventory';
import {
    store as storeItem,
    update as updateItem,
} from '@/routes/admin/inventory/items';
import { adjustments } from '@/routes/admin/inventory';
import '../../../../css/inventory.css';

defineOptions({ layout: AdminLayout });
const props = defineProps<{
    section: 'stock' | 'recipes' | 'history';
    recipeCatalog: RecipeCatalog;
    stockItems: StockItem[];
    stockMovements: Movement[];
    variantRecipes: Record<number, VariantRecipe>;
    optionIngredients: Record<number, Ingredient[]>;
}>();
const stocks = computed(() => props.stockItems);
const movements = computed(() => props.stockMovements);
const processing = ref(false);
const query = ref('');
const filter = ref('all');
const selectedStockId = ref(0);
const notice = ref('');
const dialog = ref<'item' | 'adjust' | 'detail' | null>(null);
const error = ref('');
const draft = reactive({
    id: 0,
    name: '',
    unit: 'g' as StockUnit,
    threshold: 0,
    active: true,
});
const adjustment = reactive({
    stockId: 1,
    direction: 'add',
    quantity: 0,
    reason: '',
    sourceKey: '',
});
const original = ref('');
const detailItem = computed(() =>
    stocks.value.find((item) => item.id === selectedStockId.value),
);
const adjustmentItem = computed(() =>
    stocks.value.find((item) => item.id === adjustment.stockId),
);
const quantity = (value: number) =>
    new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(value);
const lowStock = (item: StockItem) => item.lowStock;
const activeCount = computed(
    () => stocks.value.filter((item) => item.active).length,
);
const lowCount = computed(() => stocks.value.filter(lowStock).length);
const filteredStocks = computed(() =>
    stocks.value
        .filter(
            (item) =>
                item.name
                    .toLowerCase()
                    .includes(query.value.trim().toLowerCase()) &&
                (filter.value === 'all' ||
                    (filter.value === 'low'
                        ? lowStock(item)
                        : filter.value === 'inactive'
                          ? !item.active
                          : item.unit === filter.value)),
        )
        .sort((a, b) => a.name.localeCompare(b.name)),
);
const stockById = (id: number) => stocks.value.find((item) => item.id === id);
const filteredMovements = computed(() =>
    movements.value.filter(
        (movement) =>
            (!selectedStockId.value ||
                movement.stockId === selectedStockId.value) &&
            (stockById(movement.stockId)?.name ?? '')
                .toLowerCase()
                .includes(query.value.trim().toLowerCase()),
    ),
);
const adjustmentAfter = computed(
    () =>
        Math.round(
            ((adjustmentItem.value?.onHand ?? 0) +
                (adjustment.direction === 'add' ? 1 : -1) *
                    Number(adjustment.quantity)) *
                1000,
        ) / 1000,
);
const titles = {
    stock: 'Stok bahan & pastry',
    recipes: 'Resep & opsi',
    history: 'Riwayat stok',
};
const tabs = [
    { label: 'Stok', href: inventory, section: 'stock' },
    { label: 'Resep & opsi', href: recipes, section: 'recipes' },
    { label: 'Riwayat stok', href: history, section: 'history' },
];
const dirty = computed(() =>
    dialog.value === 'item'
        ? JSON.stringify(draft) !== original.value
        : dialog.value === 'adjust'
          ? JSON.stringify(adjustment) !== original.value
          : false,
);
function closeDialog(): void {
    if (processing.value) {
        return;
    }
    if (
        dirty.value &&
        !window.confirm('Buang perubahan yang belum disimpan?')
    ) {
        return;
    }
    dialog.value = null;
}
const removeBeforeVisit = router.on(
    'before',
    () =>
        processing.value ||
        !dirty.value ||
        window.confirm('Tinggalkan form dan buang perubahan form?'),
);
function confirmUnload(event: BeforeUnloadEvent): void {
    if (dirty.value) {
        event.preventDefault();
    }
}
if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', confirmUnload);
}
onBeforeUnmount(() => {
    removeBeforeVisit();
    window.removeEventListener('beforeunload', confirmUnload);
});
watch(
    () => props.section,
    () => {
        query.value = '';
        filter.value = 'all';
        selectedStockId.value = 0;
        notice.value = '';
        dialog.value = null;
    },
);
function openItem(item?: StockItem): void {
    Object.assign(
        draft,
        item
            ? {
                  id: item.id,
                  name: item.name,
                  unit: item.unit,
                  threshold: item.threshold,
                  active: item.active,
              }
            : { id: 0, name: '', unit: 'g', threshold: 0, active: true },
    );
    original.value = JSON.stringify(draft);
    error.value = '';
    dialog.value = 'item';
}
function saveItem(): void {
    if (processing.value) {
        return;
    }
    error.value = '';
    processing.value = true;
    const data = {
        name: draft.name,
        base_unit: draft.unit,
        reorder_point: draft.threshold,
        is_active: draft.active,
    };
    const callbacks = {
        preserveScroll: true,
        onSuccess: () => {
            dialog.value = null;
            notice.value = 'Item stok berhasil disimpan.';
        },
        onError: (errors: Record<string, string>) => {
            error.value = Object.values(errors).join(' ');
        },
        onFinish: () => {
            processing.value = false;
        },
    };
    if (draft.id) {
        router.put(updateItem.url(draft.id), data, callbacks);
    } else {
        router.post(storeItem.url(), data, callbacks);
    }
}
function openAdjustment(item?: StockItem): void {
    Object.assign(adjustment, {
        stockId:
            item?.id ?? stocks.value.find((stock) => stock.active)?.id ?? 0,
        direction: 'add',
        quantity: 0,
        reason: '',
        sourceKey: crypto.randomUUID(),
    });
    original.value = JSON.stringify(adjustment);
    error.value = '';
    dialog.value = 'adjust';
}
function saveAdjustment(): void {
    if (processing.value) {
        return;
    }
    error.value = '';
    processing.value = true;
    const delta =
        (adjustment.direction === 'add' ? 1 : -1) * Number(adjustment.quantity);
    router.post(
        adjustments.url(),
        {
            stock_item_id: adjustment.stockId,
            quantity_delta: Number.isFinite(delta) ? delta.toFixed(3) : '',
            reason: adjustment.reason,
            source_key: adjustment.sourceKey,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                dialog.value = null;
                notice.value = 'Adjustment dan riwayat stok berhasil disimpan.';
            },
            onError: (errors) => {
                error.value = Object.values(errors).join(' ');
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
function openDetail(item: StockItem): void {
    selectedStockId.value = item.id;
    dialog.value = 'detail';
}
</script>

<template>
    <div class="inventory-page">
        <Head :title="titles[section] + ' · Inventory'" />
        <div class="page-heading">
            <div>
                <span class="eyebrow">INVENTORY</span>
                <h1>{{ titles[section] }}<span class="title-dot">.</span></h1>
                <p>
                    {{
                        section === 'recipes'
                            ? 'Atur bahan untuk setiap varian produk.'
                            : 'Bahan yang terukur, resep yang jelas, riwayat yang mudah ditelusuri.'
                    }}
                </p>
            </div>
            <div v-if="section !== 'recipes'" class="inventory-heading-actions">
                <BaseButton
                    :disabled="!stocks.some((item) => item.active)"
                    @click="openAdjustment()"
                    ><AppIcon name="options" :size="17" />Adjustment</BaseButton
                >
                <BaseButton
                    v-if="section === 'stock'"
                    variant="primary"
                    @click="openItem()"
                    ><AppIcon name="plus" :size="17" />Tambah item</BaseButton
                >
            </div>
        </div>
        <div class="prototype-banner">
            <AppIcon name="info" :size="19" />
            <p>
                <strong>Inventory · data tersimpan</strong>Saldo berubah melalui
                adjustment dengan alasan dan riwayat. Reservasi pesanan belum
                digunakan pada tahap ini.
            </p>
        </div>
        <div v-if="notice" class="notice" role="status">
            <AppIcon name="check" :size="18" /><span>{{ notice }}</span
            ><button
                class="icon-button"
                aria-label="Tutup pemberitahuan"
                @click="notice = ''"
            >
                <AppIcon name="close" :size="16" />
            </button>
        </div>
        <div
            v-if="section !== 'recipes'"
            class="inventory-flow"
            aria-label="Alur Inventory"
        >
            <span><b>1</b>Daftarkan item</span
            ><AppIcon name="arrow" :size="15" /><span
                ><b>2</b>Atur resep & opsi</span
            ><AppIcon name="arrow" :size="15" /><span
                ><b>3</b>Adjustment & riwayat</span
            >
        </div>
        <div v-if="section === 'stock'" class="inventory-summary">
            <article>
                <span class="summary-icon"><AppIcon name="category" /></span>
                <div>
                    <strong>{{ stocks.length }}<small>item</small></strong>
                    <p>{{ activeCount }} aktif · g / ml / pcs</p>
                </div>
            </article>
            <article>
                <span class="summary-icon green"><AppIcon name="check" /></span>
                <div>
                    <strong>Terpisah</strong>
                    <p>Fisik, reservasi dan tersedia</p>
                </div>
            </article>
            <article>
                <span class="summary-icon"><AppIcon name="info" /></span>
                <div>
                    <strong
                        >{{ lowCount }}<small>perlu perhatian</small></strong
                    >
                    <p>Stok tersedia ≤ reorder point</p>
                </div>
            </article>
        </div>
        <section class="catalog-panel inventory-panel">
            <div class="catalog-tabs">
                <nav aria-label="Halaman Inventory">
                    <Link
                        v-for="tab in tabs"
                        :key="tab.section"
                        :href="tab.href()"
                        :class="{ active: section === tab.section }"
                        :aria-current="
                            section === tab.section ? 'page' : undefined
                        "
                        >{{ tab.label }}</Link
                    >
                </nav>
            </div>
            <InventoryRecipes
                v-if="section === 'recipes'"
                :stocks="stocks"
                :catalog="recipeCatalog"
                :configurations="variantRecipes"
                :option-configurations="optionIngredients"
            />
            <template v-else>
                <div class="catalog-toolbar">
                    <label class="search-field"
                        ><AppIcon name="search" :size="18" /><input
                            v-model="query"
                            type="search"
                            aria-label="Cari item stok"
                            placeholder="Cari bahan atau pastry…"
                    /></label>
                    <div class="toolbar-filters">
                        <select
                            v-if="section === 'stock'"
                            v-model="filter"
                            aria-label="Filter item stok"
                        >
                            <option value="all">Semua item</option>
                            <option value="low">Stok rendah</option>
                            <option value="g">Unit gram (g)</option>
                            <option value="ml">Unit mililiter (ml)</option>
                            <option value="pcs">Unit pcs</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                        <select
                            v-else
                            v-model="selectedStockId"
                            aria-label="Filter riwayat per item"
                        >
                            <option :value="0">Semua item</option>
                            <option
                                v-for="item in stocks"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.name }}
                            </option>
                        </select>
                    </div>
                </div>
                <template v-if="section === 'stock'">
                    <div
                        v-if="filteredStocks.length"
                        class="table-scroll"
                        tabindex="0"
                        aria-label="Tabel stok, geser untuk melihat semua kolom"
                    >
                        <table class="product-table inventory-table">
                            <thead>
                                <tr>
                                    <th scope="col">Item / unit</th>
                                    <th scope="col" class="align-right">
                                        Fisik
                                    </th>
                                    <th scope="col" class="align-right">
                                        Direservasi
                                    </th>
                                    <th scope="col" class="align-right">
                                        Tersedia
                                    </th>
                                    <th scope="col" class="align-right">
                                        Reorder point
                                    </th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="align-right">
                                        Tindakan
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in filteredStocks"
                                    :key="item.id"
                                    :class="{ 'is-inactive': !item.active }"
                                >
                                    <td>
                                        <button
                                            class="product-name"
                                            @click="openDetail(item)"
                                        >
                                            {{ item.name }}
                                        </button>
                                        <p class="inventory-meta">
                                            {{
                                                item.unit === 'pcs'
                                                    ? 'Pastry siap jual'
                                                    : 'Bahan'
                                            }}
                                            · {{ item.unit }}
                                        </p>
                                    </td>
                                    <td class="align-right inventory-number">
                                        {{ quantity(item.onHand) }}
                                        <small>{{ item.unit }}</small>
                                    </td>
                                    <td class="align-right inventory-number">
                                        {{ quantity(item.reserved) }}
                                        <small>{{ item.unit }}</small>
                                    </td>
                                    <td class="align-right inventory-number">
                                        <strong>{{
                                            quantity(item.available)
                                        }}</strong>
                                        <small>{{ item.unit }}</small>
                                    </td>
                                    <td class="align-right inventory-number">
                                        {{ quantity(item.threshold) }}
                                        <small>{{ item.unit }}</small>
                                    </td>
                                    <td>
                                        <StatusBadge
                                            v-if="!item.active"
                                            :active="false"
                                        /><span
                                            v-else
                                            class="inventory-badge"
                                            :class="
                                                lowStock(item)
                                                    ? 'warning'
                                                    : 'healthy'
                                            "
                                            >{{
                                                lowStock(item)
                                                    ? 'Rendah'
                                                    : 'Cukup'
                                            }}</span
                                        >
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <button
                                                class="text-button"
                                                @click="openDetail(item)"
                                            >
                                                Detail</button
                                            ><button
                                                class="icon-button"
                                                :aria-label="
                                                    'Edit ' + item.name
                                                "
                                                @click="openItem(item)"
                                            >
                                                <AppIcon
                                                    name="edit"
                                                    :size="17"
                                                /></button
                                            ><button
                                                class="icon-button"
                                                :disabled="!item.active"
                                                :aria-label="
                                                    'Adjustment ' + item.name
                                                "
                                                @click="openAdjustment(item)"
                                            >
                                                <AppIcon
                                                    name="options"
                                                    :size="17"
                                                />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="empty-state">
                        <span class="empty-icon"
                            ><AppIcon name="search" :size="28"
                        /></span>
                        <h3>Tidak ada item untuk filter ini</h3>
                        <p>Coba nama lain atau tampilkan semua item.</p>
                        <BaseButton
                            @click="
                                query = '';
                                filter = 'all';
                            "
                            >Reset filter</BaseButton
                        >
                    </div>
                    <div class="catalog-footer">
                        <span>{{ filteredStocks.length }} item</span
                        ><span>Reservasi belum terhubung ke order</span>
                    </div>
                </template>
                <template v-else>
                    <div
                        v-if="filteredMovements.length"
                        class="table-scroll"
                        tabindex="0"
                        aria-label="Riwayat perubahan stok"
                    >
                        <table class="product-table movement-table">
                            <thead>
                                <tr>
                                    <th scope="col">Waktu</th>
                                    <th scope="col">Item</th>
                                    <th scope="col" class="align-right">
                                        Perubahan
                                    </th>
                                    <th scope="col">Alasan / sumber</th>
                                    <th scope="col">Aktor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="movement in filteredMovements"
                                    :key="movement.id"
                                >
                                    <td>{{ movement.time }}</td>
                                    <td>
                                        <strong>{{
                                            stockById(movement.stockId)?.name
                                        }}</strong>
                                    </td>
                                    <td
                                        class="align-right inventory-number"
                                        :class="
                                            movement.delta > 0
                                                ? 'movement-positive'
                                                : 'danger-text'
                                        "
                                    >
                                        {{ movement.delta > 0 ? '+' : ''
                                        }}{{ quantity(movement.delta) }}
                                        {{ stockById(movement.stockId)?.unit }}
                                    </td>
                                    <td>
                                        {{ movement.reason
                                        }}<small class="inventory-meta">{{
                                            movement.source
                                        }}</small>
                                    </td>
                                    <td>{{ movement.actor }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="empty-state">
                        <span class="empty-icon"
                            ><AppIcon name="history" :size="28"
                        /></span>
                        <h3>Belum ada movement untuk pilihan ini</h3>
                        <p>Pilih item lain atau buat adjustment.</p>
                        <BaseButton
                            @click="
                                selectedStockId = 0;
                                query = '';
                            "
                            >Reset filter</BaseButton
                        >
                    </div>
                    <div class="catalog-footer">
                        <span>{{ filteredMovements.length }} movement </span
                        ><span>Riwayat hanya dapat dibaca</span>
                    </div>
                </template>
            </template>
        </section>
        <p v-if="section !== 'recipes'" class="page-note">
            <AppIcon name="info" :size="16" />{{
                section === 'stock'
                    ? 'Warning memakai stok tersedia ≤ reorder point.'
                    : section === 'history'
                      ? 'Semua perubahan saldo nantinya dicatat bersama movement dalam satu transaksi. Riwayat tidak dapat diedit.'
                      : 'Produk dan varian berasal dari Menu. Konfigurasi resep disimpan per varian.'
            }}
        </p>

        <AppDialog
            :open="dialog === 'item'"
            :title="draft.id ? 'Edit item stok' : 'Tambah item stok'"
            @close="closeDialog"
        >
            <form
                id="stock-item-form"
                class="small-editor"
                @submit.prevent="saveItem"
            >
                <fieldset
                    :disabled="processing"
                    style="border: 0; padding: 0; margin: 0"
                >
                    <p class="form-intro">
                        Item baru dimulai dari saldo nol. Gunakan adjustment
                        untuk mencatat saldo awal dengan alasan.
                    </p>
                    <label class="field"
                        >Nama item<input
                            v-model="draft.name"
                            required
                            maxlength="120"
                            placeholder="Contoh: Coffee beans"
                    /></label>
                    <label class="field"
                        >Unit dasar<select
                            v-model="draft.unit"
                            :disabled="Boolean(draft.id)"
                        >
                            <option value="g">Gram (g)</option>
                            <option value="ml">Mililiter (ml)</option>
                            <option value="pcs">
                                Buah (pcs) · pastry siap jual
                            </option></select
                        ><span class="field-help">{{
                            draft.id
                                ? 'Unit dasar dikunci saat edit agar saldo dan resep tetap dapat dibaca konsisten.'
                                : 'Semua kuantitas menggunakan unit dasar ini. Tidak ada konversi otomatis.'
                        }}</span></label
                    >
                    <label class="field"
                        >Reorder point ({{ draft.unit }})<input
                            v-model.number="draft.threshold"
                            type="number"
                            min="0"
                            step="0.001"
                            required
                    /></label>
                    <label class="check-label"
                        ><input v-model="draft.active" type="checkbox" />Item
                        aktif</label
                    >
                    <p v-if="error" class="field-error" role="alert">
                        {{ error }}
                    </p>
                </fieldset>
            </form>
            <template #footer
                ><BaseButton @click="closeDialog">Batal</BaseButton
                ><BaseButton
                    :disabled="processing"
                    type="submit"
                    form="stock-item-form"
                    variant="primary"
                    >{{ processing ? 'Menyimpan…' : 'Simpan item' }}</BaseButton
                ></template
            >
        </AppDialog>
        <AppDialog
            :open="dialog === 'adjust'"
            title="Adjustment stok"
            @close="closeDialog"
        >
            <form
                id="stock-adjustment-form"
                class="small-editor"
                @submit.prevent="saveAdjustment"
            >
                <fieldset
                    :disabled="processing"
                    style="border: 0; padding: 0; margin: 0"
                >
                    <p class="form-intro">
                        Catat penambahan atau pengurangan stok. Alasan wajib
                        disertakan.
                    </p>
                    <label class="field"
                        >Item<select v-model="adjustment.stockId" required>
                            <option
                                v-for="item in stocks.filter(
                                    (stock) => stock.active,
                                )"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.name }} ({{ item.unit }})
                            </option>
                        </select></label
                    >
                    <div class="inventory-form-grid">
                        <label class="field"
                            >Arah perubahan<select
                                v-model="adjustment.direction"
                            >
                                <option value="add">Tambah (+)</option>
                                <option value="subtract">Kurangi (−)</option>
                            </select></label
                        ><label class="field"
                            >Jumlah ({{ adjustmentItem?.unit }})<input
                                v-model.number="adjustment.quantity"
                                type="number"
                                min="0.001"
                                step="0.001"
                                required
                        /></label>
                    </div>
                    <label class="field"
                        >Alasan wajib<textarea
                            v-model="adjustment.reason"
                            rows="3"
                            required
                            maxlength="500"
                            placeholder="Contoh: koreksi hasil hitung fisik"
                        />
                    </label>
                    <div class="adjustment-preview">
                        <span
                            >Fisik saat ini<strong
                                >{{ quantity(adjustmentItem?.onHand ?? 0) }}
                                {{ adjustmentItem?.unit }}</strong
                            ></span
                        ><AppIcon name="arrow" /><span
                            >Perkiraan setelah perubahan<strong
                                :class="{ 'danger-text': adjustmentAfter < 0 }"
                                >{{ quantity(adjustmentAfter) }}
                                {{ adjustmentItem?.unit }}</strong
                            ></span
                        >
                    </div>
                    <span class="field-help"
                        >Ringkasan ini adalah perkiraan. Server memvalidasi
                        saldo saat adjustment disimpan.</span
                    >
                    <p v-if="error" class="field-error" role="alert">
                        {{ error }}
                    </p>
                </fieldset>
            </form>
            <template #footer
                ><BaseButton @click="closeDialog">Batal</BaseButton
                ><BaseButton
                    :disabled="processing"
                    type="submit"
                    form="stock-adjustment-form"
                    variant="primary"
                    >{{
                        processing ? 'Menyimpan…' : 'Simpan adjustment'
                    }}</BaseButton
                ></template
            >
        </AppDialog>
        <AppDialog
            :open="dialog === 'detail'"
            :title="detailItem?.name ?? 'Detail stok'"
            @close="dialog = null"
        >
            <div v-if="detailItem" class="small-editor">
                <span class="inventory-badge"
                    >Unit · {{ detailItem.unit }}</span
                >
                <div class="stock-detail-balances">
                    <div>
                        Fisik<strong
                            >{{ quantity(detailItem.onHand) }}
                            {{ detailItem.unit }}</strong
                        >
                    </div>
                    <div>
                        Direservasi<strong
                            >{{ quantity(detailItem.reserved) }}
                            {{ detailItem.unit }}</strong
                        >
                    </div>
                    <div>
                        Tersedia<strong
                            >{{ quantity(detailItem.available) }}
                            {{ detailItem.unit }}</strong
                        >
                    </div>
                </div>
                <h3>Riwayat movement</h3>
                <div
                    v-for="movement in movements.filter(
                        (entry) => entry.stockId === detailItem?.id,
                    )"
                    :key="movement.id"
                    class="stock-detail-movement"
                >
                    <div>
                        <strong
                            :class="
                                movement.delta > 0
                                    ? 'movement-positive'
                                    : 'danger-text'
                            "
                            >{{ movement.delta > 0 ? '+' : ''
                            }}{{ quantity(movement.delta) }}
                            {{ detailItem.unit }}</strong
                        ><span>{{ movement.time }}</span>
                    </div>
                    <p>{{ movement.reason }}</p>
                    <small>{{ movement.source }} · {{ movement.actor }}</small>
                </div>
                <p
                    v-if="
                        !movements.some(
                            (entry) => entry.stockId === detailItem?.id,
                        )
                    "
                    class="field-help"
                >
                    Belum ada movement untuk item ini.
                </p>
                <p class="field-help">
                    Movement hanya dapat dibaca. Koreksi dilakukan lewat
                    adjustment baru.
                </p>
            </div>
            <template #footer
                ><BaseButton @click="dialog = null">Tutup</BaseButton
                ><BaseButton
                    v-if="detailItem?.active"
                    variant="primary"
                    @click="openAdjustment(detailItem)"
                    >Adjustment</BaseButton
                ></template
            >
        </AppDialog>
    </div>
</template>
