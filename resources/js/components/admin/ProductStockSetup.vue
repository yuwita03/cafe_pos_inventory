<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import type {
    Variant,
    ProductStockItem,
    ProductStockSetup,
} from '@/types/menu';

const props = defineProps<{
    productName: string;
    variants: Variant[];
    stocks: ProductStockItem[];
    processing: boolean;
    errors: Record<string, string>;
}>();
const emit = defineEmits<{ change: [] }>();
type Setup = Omit<ProductStockSetup, 'ingredients'> & {
    ingredients: { id: number; stockId: number; quantity: number }[];
};
const setups = reactive(new Map<Variant, Setup>());
const changedVariants = reactive(new Set<Variant>());
const selectedVariant = ref(0);
const errorElement = ref<HTMLElement>();
const localError = ref<{ index: number; message: string } | null>(null);
const stockErrors = computed(() => {
    const prefix = `variants.${selectedVariant.value}.stock`;
    return Object.entries(props.errors)
        .filter(([key]) => key === prefix || key.startsWith(`${prefix}.`))
        .map(([, message]) => message);
});
watch(
    () => props.errors,
    async (errors) => {
        const field = Object.keys(errors).find((key) =>
            /^variants\.\d+\.stock(?:\.|$)/.test(key),
        );
        if (field) {
            selectedVariant.value = Number(field.split('.')[1]);
            localError.value = null;
            await nextTick();
            errorElement.value?.focus();
        }
    },
);
let nextIngredientId = 0;
watch(
    () => [...props.variants],
    (variants) => {
        for (const item of setups.keys()) {
            if (!variants.includes(item)) {
                setups.delete(item);
                changedVariants.delete(item);
            }
        }
        selectedVariant.value = Math.min(
            selectedVariant.value,
            Math.max(0, variants.length - 1),
        );
        for (const item of variants) {
            if (!setups.has(item)) {
                setups.set(item, {
                    method: item.stock?.method ?? 'ingredients',
                    stockId: item.stock?.stockId ?? null,
                    openingQuantity: item.id ? undefined : 0,
                    reorderPoint: item.stock?.reorderPoint ?? 5,
                    ingredients: (item.stock?.ingredients ?? []).map((row) => ({
                        ...row,
                        id: nextIngredientId++,
                    })),
                });
            }
        }
    },
    { immediate: true },
);
const variant = computed(() => props.variants[selectedVariant.value]);
const setup = computed(() => setups.get(variant.value)!);
const methodLocked = computed(() =>
    Boolean(
        variant.value.id &&
        (variant.value.stock?.stockId ||
            variant.value.stock?.ingredients.length),
    ),
);
const linkedStock = computed(() =>
    props.stocks.find((item) => item.id === setup.value.stockId),
);
const ingredients = computed(() =>
    props.stocks.filter(
        (item) =>
            item.unit !== 'pcs' &&
            (item.active ||
                setup.value.ingredients.some((row) => row.stockId === item.id)),
    ),
);
const stockName = computed(() =>
    [
        props.productName.trim() || 'Nama produk',
        variant.value?.name && variant.value.name !== 'Regular'
            ? variant.value.name
            : '',
    ]
        .filter(Boolean)
        .join(' · '),
);
function addIngredient(): void {
    setup.value.ingredients.push({
        id: nextIngredientId++,
        stockId: 0,
        quantity: 0,
    });
    markChanged();
}
function removeIngredient(id: number): void {
    setup.value.ingredients = setup.value.ingredients.filter(
        (row) => row.id !== id,
    );
    markChanged();
}
function markChanged(): void {
    changedVariants.add(variant.value);
    localError.value = null;
    emit('change');
}
function synchronizeThreshold(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!setup.value.stockId || input.value === '') {
        return;
    }
    for (const [item, value] of setups) {
        if (
            value.method === 'finished' &&
            value.stockId === setup.value.stockId
        ) {
            value.reorderPoint = Number(input.value);
            changedVariants.add(item);
        }
    }
}
function getSettings(): (ProductStockSetup | undefined)[] {
    return props.variants.map((item) => {
        if (item.id && !changedVariants.has(item)) {
            return undefined;
        }
        const value = setups.get(item)!;
        return {
            method: value.method,
            stockId: value.stockId,
            ...(item.id ? {} : { openingQuantity: value.openingQuantity }),
            reorderPoint: value.reorderPoint,
            ingredients: value.ingredients.map((row) => ({
                stockId: row.stockId,
                quantity: row.quantity,
            })),
        };
    });
}
async function validate(): Promise<boolean> {
    localError.value = null;
    for (const [index, item] of props.variants.entries()) {
        if (item.id && !changedVariants.has(item)) {
            continue;
        }
        const value = setups.get(item)!;
        if (value.method !== 'ingredients') {
            continue;
        }
        let message = '';
        if (!value.ingredients.length) {
            message = 'Tambahkan minimal satu bahan untuk varian ini.';
        } else if (
            value.ingredients.some(
                (row) =>
                    !props.stocks.some(
                        (stock) =>
                            stock.id === row.stockId &&
                            stock.active &&
                            ['g', 'ml'].includes(stock.unit),
                    ),
            )
        ) {
            message = 'Pilih bahan aktif dengan unit g atau ml.';
        } else if (
            value.ingredients.some(
                (row) =>
                    !/^\d{1,9}(?:\.\d{1,3})?$/.test(String(row.quantity)) ||
                    Number(row.quantity) < 0.001,
            )
        ) {
            message =
                'Isi jumlah per porsi minimal 0,001 dengan maksimal tiga angka desimal.';
        } else if (
            new Set(value.ingredients.map((row) => row.stockId)).size !==
            value.ingredients.length
        ) {
            message =
                'Bahan yang sama tidak boleh dipilih dua kali dalam satu varian.';
        }
        if (message) {
            selectedVariant.value = index;
            localError.value = { index, message };
            await nextTick();
            errorElement.value?.focus();
            return false;
        }
    }
    return true;
}

defineExpose({ getSettings, validate });
</script>

<template>
    <details
        class="form-section product-form-section"
        :open="!!localError || stockErrors.length > 0"
        @input="markChanged"
    >
        <summary>
            <h3><span>03</span>Pengurangan stok</h3>
        </summary>
        <fieldset class="stock-configuration-fields" :disabled="processing">
            <legend class="sr-only">Pengaturan stok setiap varian</legend>
            <p class="field-help">
                Pilih cara produk ini menggunakan stok. Pengaturan berlaku untuk
                varian yang dipilih.
            </p>
            <div
                v-if="variants.length > 1"
                class="stock-variant-picker"
                role="group"
                aria-label="Pilih varian untuk mengatur stok"
            >
                <button
                    v-for="(item, index) in variants"
                    :key="index"
                    type="button"
                    :aria-pressed="selectedVariant === index"
                    @click="selectedVariant = index"
                >
                    {{ item.name || `Varian ${index + 1}` }}
                </button>
            </div>
            <p class="stock-variant-context">
                Untuk varian <strong>{{ variant?.name || 'Regular' }}</strong>
            </p>
            <p
                v-if="
                    variant?.id &&
                    !variant.stock?.stockId &&
                    !variant.stock?.ingredients.length &&
                    !changedVariants.has(variant)
                "
                class="field-help"
            >
                Pengaturan stok belum lengkap. Varian ini tidak dapat dipesan
                sampai pengaturan stok dilengkapi.
            </p>
            <div
                v-if="
                    stockErrors.length || localError?.index === selectedVariant
                "
                ref="errorElement"
                class="field-error"
                role="alert"
                tabindex="-1"
            >
                <p v-if="localError?.index === selectedVariant">
                    {{ localError.message }}
                </p>
                <p v-for="message in stockErrors" :key="message">
                    {{ message }}
                </p>
            </div>
            <div v-if="methodLocked" class="stock-method-summary">
                <AppIcon
                    :name="setup.method === 'finished' ? 'category' : 'coffee'"
                    :size="20"
                />
                <div>
                    <strong>{{
                        setup.method === 'finished'
                            ? 'Dari stok produk jadi'
                            : 'Dari bahan / resep'
                    }}</strong>
                    <p>
                        Cara pengurangan stok sudah diatur dan tetap
                        dipertahankan.
                    </p>
                </div>
            </div>
            <fieldset v-else class="stock-methods">
                <legend class="sr-only">Cara pengurangan stok</legend>
                <label :class="{ selected: setup.method === 'ingredients' }">
                    <input
                        v-model="setup.method"
                        type="radio"
                        name="product-stock-method"
                        value="ingredients"
                    />
                    <AppIcon name="coffee" :size="20" />
                    <span
                        ><strong>Dari bahan / resep</strong
                        ><small
                            >Untuk produk yang diracik, seperti kopi. Tentukan
                            bahan dan jumlah per porsi.</small
                        ></span
                    >
                </label>
                <label :class="{ selected: setup.method === 'finished' }">
                    <input
                        v-model="setup.method"
                        type="radio"
                        name="product-stock-method"
                        value="finished"
                    />
                    <AppIcon name="category" :size="20" />
                    <span
                        ><strong>Dari stok produk jadi</strong
                        ><small
                            >Untuk donat, pastry, atau minuman botol. Stok pcs
                            dibuat dan dihubungkan otomatis.</small
                        ></span
                    >
                </label>
            </fieldset>

            <div
                v-if="setup.method === 'ingredients'"
                class="stock-setup-details"
            >
                <div class="stock-details-heading">
                    <strong>Resep per porsi (wajib)</strong><span>g / ml</span>
                </div>
                <div
                    v-if="!setup.ingredients.length"
                    class="stock-recipe-empty"
                >
                    <AppIcon name="options" :size="24" />
                    <strong>Tambahkan bahan untuk varian ini</strong>
                    <p>
                        Pilih bahan dan jumlah yang digunakan untuk satu porsi.
                    </p>
                </div>
                <div
                    v-for="row in setup.ingredients"
                    :key="row.id"
                    class="stock-ingredient-row"
                >
                    <label class="field"
                        >Bahan<select v-model.number="row.stockId" required>
                            <option :value="0" disabled>Pilih bahan</option>
                            <option
                                v-for="item in ingredients"
                                :key="item.id"
                                :value="item.id"
                                :disabled="
                                    !item.active ||
                                    setup.ingredients.some(
                                        (other) =>
                                            other.id !== row.id &&
                                            other.stockId === item.id,
                                    )
                                "
                            >
                                {{ item.name }} · {{ item.unit
                                }}{{ item.active ? '' : ' (nonaktif)' }}
                            </option>
                        </select></label
                    >
                    <label class="field"
                        >Per porsi<span class="stock-quantity-input"
                            ><input
                                v-model.number="row.quantity"
                                type="number"
                                min="0.001"
                                max="999999999.999"
                                required
                                step="0.001"
                                inputmode="decimal"
                            /><span>{{
                                ingredients.find(
                                    (item) => item.id === row.stockId,
                                )?.unit || '—'
                            }}</span></span
                        ></label
                    >
                    <button
                        type="button"
                        class="icon-button danger-text"
                        :aria-label="`Hapus bahan ${ingredients.find((item) => item.id === row.stockId)?.name || 'kosong'}`"
                        @click="removeIngredient(row.id)"
                    >
                        <AppIcon name="trash" :size="17" />
                    </button>
                </div>
                <BaseButton
                    :disabled="setup.ingredients.length >= ingredients.length"
                    @click="addIngredient"
                    ><AppIcon name="plus" :size="16" />Tambah bahan</BaseButton
                >
                <p
                    v-if="!ingredients.length"
                    class="field-help stock-detail-note"
                >
                    Belum ada bahan aktif. Tambahkan bahan di halaman Stok
                    terlebih dahulu.
                </p>
                <p class="field-help stock-detail-note">
                    Minimal satu bahan per varian. Saldo stok boleh nol saat
                    mengatur resep. Opsi tambahan tetap diatur melalui Resep &
                    opsi.
                </p>
            </div>

            <div v-else class="stock-setup-details">
                <div class="stock-auto-heading">
                    <AppIcon name="check" :size="19" />
                    <div>
                        <strong>{{
                            linkedStock
                                ? 'Stok produk terhubung'
                                : 'Stok dibuat otomatis'
                        }}</strong>
                        <p>
                            {{
                                linkedStock
                                    ? 'Hubungan dan saldo stok tetap dipertahankan saat produk diedit.'
                                    : 'Simpan produk sekali. Tidak perlu membuat item stok lagi.'
                            }}
                        </p>
                    </div>
                </div>
                <dl class="stock-auto-summary">
                    <div class="stock-auto-name">
                        <dt>Nama stok</dt>
                        <dd>{{ linkedStock?.name || stockName }}</dd>
                    </div>
                    <div>
                        <dt>Satuan</dt>
                        <dd>pcs</dd>
                    </div>
                    <div>
                        <dt>
                            {{
                                linkedStock
                                    ? 'Stok fisik saat ini'
                                    : 'Stok saat dibuat'
                            }}
                        </dt>
                        <dd>
                            {{
                                linkedStock
                                    ? linkedStock.onHand
                                    : variant.id
                                      ? 0
                                      : setup.openingQuantity === ''
                                        ? '—'
                                        : setup.openingQuantity
                            }}
                            <small>pcs</small>
                        </dd>
                    </div>
                    <div>
                        <dt>Per item pesanan</dt>
                        <dd>1 <small>pcs</small></dd>
                    </div>
                </dl>
                <p class="stock-destination">
                    <AppIcon name="category" :size="16" />Dikelola di halaman
                    <strong>Stok</strong>
                </p>
                <div class="stock-opening-fields">
                    <label v-if="!variant.id" class="field stock-reorder-field">
                        Stok awal
                        <span class="stock-quantity-input"
                            ><input
                                v-model.number="setup.openingQuantity"
                                type="number"
                                min="0"
                                max="999999999"
                                step="1"
                                inputmode="numeric"
                                placeholder="0"
                                required
                                aria-describedby="product-opening-help"
                            /><span>pcs</span></span
                        >
                        <small id="product-opening-help" class="field-help"
                            >Jumlah barang yang sudah ada. Isi 0 jika belum
                            tersedia.</small
                        >
                    </label>
                    <label class="field stock-reorder-field">
                        Batas stok rendah
                        <span class="stock-quantity-input"
                            ><input
                                v-model.number="setup.reorderPoint"
                                type="number"
                                min="0"
                                step="1"
                                inputmode="numeric"
                                max="999999999"
                                required
                                aria-describedby="product-reorder-help"
                                @input="synchronizeThreshold"
                            /><span>pcs</span></span
                        >
                        <small id="product-reorder-help" class="field-help"
                            >Batas untuk menandai stok yang perlu diisi
                            kembali.</small
                        >
                    </label>
                </div>
                <p class="stock-setup-hint">
                    <AppIcon name="info" :size="17" />
                    <span
                        >Stok awal hanya diisi saat membuat produk dan akan
                        tercatat di <strong>Riwayat stok</strong>. Setelahnya,
                        ubah jumlah melalui <strong>Adjustment</strong> di
                        halaman Stok.</span
                    >
                </p>
            </div>
        </fieldset>
    </details>
</template>

<style scoped>
.stock-configuration-fields {
    margin: 0;
    padding: 0;
    min-width: 0;
    border: 0;
}
.stock-method-summary {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 0;
    border-block: 1px solid var(--line);
}
.stock-method-summary strong {
    font-size: 13px;
}
.stock-method-summary p {
    margin-top: 4px;
    font-size: 12px;
    color: #616161;
}
.stock-variant-picker {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin: 16px 0 10px;
}
.stock-variant-picker button {
    min-height: 44px;
    padding: 8px 14px;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: white;
    color: var(--muted);
    font-size: 12px;
}
.stock-variant-picker button[aria-pressed='true'] {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--soft);
}
.stock-variant-context {
    margin: 12px 0 !important;
    font-size: 12px;
    color: #616161;
}
.stock-variant-context strong {
    color: var(--ink);
}
.stock-methods {
    min-width: 0;
    padding: 0;
    margin: 0;
    border: 0;
}
.stock-methods {
    display: grid;
    gap: 9px;
}
.stock-methods > label {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 76px;
    padding: 13px;
    border: 1px solid var(--line);
    border-radius: 8px;
    cursor: pointer;
}
.stock-methods > label.selected {
    border-color: var(--primary);
    background: #fff9f5;
}
.stock-methods input {
    accent-color: var(--primary);
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}
.stock-methods svg {
    color: var(--primary);
}
.stock-methods strong,
.stock-recipe-empty strong {
    display: block;
    font-size: 13px;
    font-weight: 550;
}
.stock-methods small {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    line-height: 1.6;
    color: #616161;
}
.stock-setup-details {
    margin-top: 19px;
}
.stock-details-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 14px;
    font-size: 12px;
}
.stock-details-heading > span {
    color: #616161;
    font-size: 12px;
}
.stock-auto-heading {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}
.stock-auto-heading > svg {
    color: var(--primary);
}
.stock-auto-heading strong {
    font-size: 14px;
    font-weight: 550;
}
.stock-auto-heading p {
    margin-top: 3px;
    font-size: 12px;
    line-height: 1.7;
    color: #616161;
}
.stock-auto-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin: 0;
    padding: 16px 0;
    border-block: 1px solid var(--line);
}
.stock-auto-summary dt {
    margin-bottom: 5px;
    font-size: 12px;
    color: #616161;
}
.stock-auto-summary dd {
    min-width: 0;
    margin: 0;
    font-size: 17px;
    font-weight: 550;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
}
.stock-auto-summary dd small {
    font-size: 12px;
    font-weight: 400;
    color: #616161;
}
.stock-auto-name {
    grid-column: 1 / -1;
}
.stock-auto-name dd {
    font-size: 14px;
}
.stock-destination {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin: 12px 0 20px !important;
    font-size: 12px;
    color: #616161;
}
.stock-destination strong {
    color: var(--ink);
    font-weight: 500;
}
.stock-reorder-field > .stock-quantity-input {
    max-width: 160px;
}
.stock-opening-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}
.stock-reorder-field > small {
    display: block;
    margin-top: 7px;
}
.stock-setup-hint {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    padding: 12px;
    background: #f6f6f3;
    border-radius: 6px;
    color: #616161;
    font-size: 12px;
    line-height: 1.7;
}
.stock-recipe-empty {
    display: grid;
    justify-items: center;
    gap: 7px;
    margin-bottom: 14px;
    padding: 22px 14px;
    border: 1px dashed #dcded6;
    border-radius: 7px;
    color: #616161;
    text-align: center;
}
.stock-recipe-empty p {
    font-size: 12px;
    line-height: 1.7;
}
.stock-ingredient-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 110px 44px;
    gap: 9px;
    align-items: start;
}
.stock-ingredient-row > button {
    margin-top: 24px;
    width: 44px;
    min-height: 44px;
}
.stock-quantity-input {
    display: flex;
    align-items: center;
    gap: 8px;
}
.stock-quantity-input input {
    min-width: 0;
}
.stock-quantity-input > span {
    padding-top: 7px;
    font-weight: 400;
    color: #616161;
}
.stock-detail-note {
    margin-top: 12px !important;
}
@container (max-width: 480px) {
    .stock-opening-fields {
        grid-template-columns: minmax(0, 1fr);
        gap: 0;
    }
    .stock-ingredient-row {
        grid-template-columns: minmax(0, 1fr) 85px 44px;
        gap: 6px;
    }
    .stock-methods > label {
        gap: 8px;
        padding: 11px;
    }
    .stock-methods > label > svg {
        display: none;
    }
}
</style>
