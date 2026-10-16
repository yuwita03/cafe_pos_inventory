<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    reactive,
    ref,
    useId,
    watch,
} from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { menu, options as menuOptions } from '@/routes/admin';
import type {
    VariantRecipe,
    RecipeCatalog,
    StockItem,
    Ingredient,
} from '@/types/inventory';
import {
    recipe as saveRecipe,
    option as saveOption,
    requirements as requirementsRoute,
} from '@/routes/admin/inventory';

type Product = RecipeCatalog['products'][number];
type Variant = Product['variants'][number];
const props = defineProps<{
    stocks: StockItem[];
    catalog: RecipeCatalog;
    configurations: Record<number, VariantRecipe>;
    optionConfigurations: Record<number, Ingredient[]>;
}>();
const recipeConfigurations = computed(() => props.configurations);
const optionConfigurations = computed(() => props.optionConfigurations);
const processing = ref(false);
const tab = ref<'recipes' | 'options' | 'pastry'>('recipes');
const search = ref('');
const completeness = ref<'all' | 'none' | 'partial' | 'complete'>('all');
const page = ref(1);
const managingProduct = ref(false);
const savedVariant = ref('');
const ingredientPickerOpen = ref(false);
const replacingIngredient = ref<number | null>(null);
const ingredientSearch = ref('');
const ingredientSearchInput = ref<HTMLInputElement>();
const pickerTrigger = ref<HTMLButtonElement>();
const ingredientPickerId = useId();
const dialog = ref<'recipe' | 'option' | 'calculator' | null>(null);
const productId = ref(0);
const variantId = ref(0);
const groupId = ref(0);
const optionId = ref(0);
const product = computed(() =>
    props.catalog.products.find((item) => item.id === productId.value),
);
const variant = computed(() =>
    product.value?.variants.find((item) => item.id === variantId.value),
);
const group = computed(() =>
    props.catalog.groups.find((item) => item.id === groupId.value),
);
const option = computed(() =>
    group.value?.options.find((item) => item.id === optionId.value),
);
const draft = reactive<{ ingredients: Ingredient[] }>({
    ingredients: [],
});
const original = ref('');
const error = ref('');
const notice = ref('');
const count = ref(1);
const selectedOptions = reactive<Record<number, number[]>>({});
const ingredients = computed(() =>
    props.stocks
        .filter((item) => item.active && item.unit !== 'pcs')
        .toSorted((a, b) => a.name.localeCompare(b.name, 'id') || a.id - b.id),
);
const quickIngredients = computed(() =>
    ingredients.value.filter((item) => item.quickAdd),
);
const remainingIngredients = computed(() =>
    ingredients.value.filter(
        (item) => !draft.ingredients.some((row) => row.stockId === item.id),
    ),
);
const ingredientResults = computed(() =>
    ingredients.value.filter(
        (item) =>
            !draft.ingredients.some(
                (row, index) =>
                    index !== replacingIngredient.value &&
                    row.stockId === item.id,
            ) &&
            `${item.name} ${item.unit}`
                .toLocaleLowerCase('id')
                .includes(
                    ingredientSearch.value.trim().toLocaleLowerCase('id'),
                ),
    ),
);
const stockItem = (id: number) => props.stocks.find((item) => item.id === id);
const formatQuantity = (value: number) =>
    new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(value);
const currentConfiguration = computed(
    () => recipeConfigurations.value[variantId.value],
);
const viewingFinished = computed(
    () =>
        dialog.value === 'recipe' &&
        currentConfiguration.value?.kind === 'finished',
);
const assignedGroups = computed(() =>
    props.catalog.groups.filter((item) =>
        product.value?.groups.some((assignment) => assignment.id === item.id),
    ),
);
function configuredVariant(id: number): boolean {
    const configuration = recipeConfigurations.value[id];
    return Boolean(
        configuration &&
        (configuration.kind === 'finished'
            ? configuration.stockId > 0
            : configuration.ingredients.length > 0),
    );
}
function configuredCount(product: Product): number {
    return product.variants.filter((variant) => configuredVariant(variant.id))
        .length;
}
function configurationState(product: Product): 'none' | 'partial' | 'complete' {
    const configured = configuredCount(product);
    return configured === 0
        ? 'none'
        : configured === product.variants.length
          ? 'complete'
          : 'partial';
}
function configurationLabel(product: Product): string {
    const state = configurationState(product);
    return state === 'none'
        ? 'Belum diatur'
        : state === 'complete'
          ? 'Lengkap'
          : `${configuredCount(product)} dari ${product.variants.length} diatur`;
}
const filteredProducts = computed(() =>
    props.catalog.products
        .filter(
            (product) =>
                `${product.name} ${product.variants.map((variant) => variant.name).join(' ')}`
                    .toLocaleLowerCase('id')
                    .includes(search.value.trim().toLocaleLowerCase('id')) &&
                (completeness.value === 'all' ||
                    configurationState(product) === completeness.value),
        )
        .toSorted((a, b) => a.name.localeCompare(b.name, 'id') || a.id - b.id),
);
const visibleProducts = computed(() =>
    filteredProducts.value.slice((page.value - 1) * 10, page.value * 10),
);
const variantRows = computed(() =>
    props.catalog.products.flatMap((product) =>
        product.variants.map((variant) => ({ product, variant })),
    ),
);
const filteredVariants = computed(() =>
    variantRows.value.filter(
        (row) =>
            (tab.value !== 'pastry' ||
                recipeConfigurations.value[row.variant.id]?.kind ===
                    'finished') &&
            (row.product.name + ' ' + row.variant.name)
                .toLowerCase()
                .includes(search.value.trim().toLowerCase()),
    ),
);
const filteredOptions = computed(() =>
    props.catalog.groups
        .flatMap((group) => group.options.map((option) => ({ group, option })))
        .filter((row) =>
            (row.group.name + ' ' + row.option.name)
                .toLowerCase()
                .includes(search.value.trim().toLowerCase()),
        ),
);
const totalCount = computed(() =>
    tab.value === 'options'
        ? filteredOptions.value.length
        : tab.value === 'recipes'
          ? filteredProducts.value.length
          : filteredVariants.value.length,
);
const pageCount = computed(() => Math.max(1, Math.ceil(totalCount.value / 10)));
const visibleVariants = computed(() =>
    filteredVariants.value.slice((page.value - 1) * 10, page.value * 10),
);
const visibleOptions = computed(() =>
    filteredOptions.value.slice((page.value - 1) * 10, page.value * 10),
);
watch([tab, search, completeness], () => {
    page.value = 1;
    notice.value = '';
});
watch(pageCount, (value) => {
    page.value = Math.min(page.value, value);
});
const editing = computed(
    () => dialog.value === 'recipe' || dialog.value === 'option',
);
const draftState = () =>
    JSON.stringify({
        productId: productId.value,
        variantId: variantId.value,
        ...draft,
    });
const dirty = computed(() => editing.value && draftState() !== original.value);
const dialogTitle = computed(() =>
    dialog.value === 'option'
        ? (group.value?.name ?? '') + ' · ' + (option.value?.name ?? '')
        : managingProduct.value && editing.value
          ? 'Kelola resep · ' + (product.value?.name ?? '')
          : dialog.value === 'calculator'
            ? 'Coba hitung kebutuhan'
            : (product.value?.name ?? '') + ' · ' + (variant.value?.name ?? ''),
);
function resetDraft(): void {
    ingredientPickerOpen.value = false;
    replacingIngredient.value = null;
    ingredientSearch.value = '';
    const configuration =
        dialog.value === 'option' ? undefined : currentConfiguration.value;
    draft.ingredients = (
        dialog.value === 'option'
            ? (optionConfigurations.value[optionId.value] ?? [])
            : (configuration?.ingredients ?? [])
    ).map((row) => ({ stockId: row.stockId, quantity: row.quantity }));
    original.value = draftState();
    error.value = '';
}
function openVariant(
    row: { product: Product; variant: Variant },
    grouped = false,
): void {
    managingProduct.value = grouped;
    savedVariant.value = '';
    productId.value = row.product.id;
    variantId.value = row.variant.id;
    dialog.value = 'recipe';
    resetDraft();
}
function openProduct(product: Product): void {
    const variant =
        product.variants.find((item) => !configuredVariant(item.id)) ??
        product.variants[0];
    if (variant) {
        openVariant({ product, variant }, true);
    }
}
function chooseRecipeVariant(selected: Variant): void {
    if (
        !processing.value &&
        product.value &&
        selected.id !== variantId.value &&
        mayDiscard()
    ) {
        openVariant({ product: product.value, variant: selected }, true);
    }
}
function testCurrentRecipe(): void {
    if (!processing.value && product.value && variant.value && mayDiscard()) {
        openCalculator({ product: product.value, variant: variant.value });
    }
}
function openOption(row: (typeof filteredOptions.value)[number]): void {
    managingProduct.value = false;
    groupId.value = row.group.id;
    optionId.value = row.option.id;
    dialog.value = 'option';
    resetDraft();
}
function openCalculator(row: { product: Product; variant: Variant }): void {
    productId.value = row.product.id;
    variantId.value = row.variant.id;
    count.value = 1;
    for (const key of Object.keys(selectedOptions)) {
        delete selectedOptions[Number(key)];
    }
    calculationVersion++;
    requirements.value = null;
    calculationError.value = '';
    dialog.value = 'calculator';
}
function mayDiscard(): boolean {
    return (
        !dirty.value || window.confirm('Buang perubahan yang belum disimpan?')
    );
}
function closeDialog(): void {
    if (processing.value) {
        return;
    }
    if (mayDiscard()) {
        dialog.value = null;
    }
}
async function openIngredientPicker(
    index: number | null,
    event: MouseEvent,
): Promise<void> {
    replacingIngredient.value = index;
    ingredientSearch.value = '';
    pickerTrigger.value = event.currentTarget as HTMLButtonElement;
    ingredientPickerOpen.value = true;
    await nextTick();
    ingredientSearchInput.value?.focus();
}
async function closeIngredientPicker(restoreFocus = true): Promise<void> {
    ingredientPickerOpen.value = false;
    replacingIngredient.value = null;
    ingredientSearch.value = '';
    if (restoreFocus) {
        await nextTick();
        pickerTrigger.value?.focus();
    }
}
async function selectIngredient(
    item: StockItem,
    index: number | null = null,
): Promise<void> {
    if (
        processing.value ||
        !item.active ||
        item.unit === 'pcs' ||
        draft.ingredients.some(
            (row, rowIndex) => rowIndex !== index && row.stockId === item.id,
        )
    ) {
        return;
    }
    const target = index ?? draft.ingredients.length;
    if (index === null) {
        draft.ingredients.push({ stockId: item.id, quantity: 1 });
    } else if (draft.ingredients[index]) {
        draft.ingredients[index].stockId = item.id;
    } else {
        return;
    }
    await closeIngredientPicker(false);
    await nextTick();
    document
        .getElementById(`${ingredientPickerId}-quantity-${target}`)
        ?.focus();
}
function removeIngredient(index: number): void {
    void closeIngredientPicker(false);
    draft.ingredients.splice(index, 1);
}
function applyDraft(): void {
    if (processing.value || viewingFinished.value) {
        return;
    }
    processing.value = true;
    error.value = '';
    const ingredients = draft.ingredients.map((row) => ({
        stock_item_id: row.stockId,
        quantity: row.quantity,
    }));
    const callbacks = {
        preserveScroll: true,
        onSuccess: async () => {
            if (managingProduct.value) {
                await nextTick();
                resetDraft();
                savedVariant.value = `Konfigurasi ${variant.value?.name ?? ''} berhasil disimpan.`;
            } else {
                dialog.value = null;
                notice.value = 'Konfigurasi berhasil disimpan.';
            }
        },
        onError: (errors: Record<string, string>) => {
            error.value = Object.values(errors).join(' ');
        },
        onFinish: () => {
            processing.value = false;
        },
    };
    if (dialog.value === 'option') {
        router.put(saveOption.url(optionId.value), { ingredients }, callbacks);
    } else {
        router.put(
            saveRecipe.url(variantId.value),
            {
                kind: 'ingredients',
                ingredients,
            },
            callbacks,
        );
    }
}
function selectSingle(groupId: number, event: Event): void {
    const value = Number((event.target as HTMLSelectElement).value);
    selectedOptions[groupId] = value ? [value] : [];
}
function toggleMultiple(groupId: number, optionId: number): void {
    const values = selectedOptions[groupId] ?? [];
    selectedOptions[groupId] = values.includes(optionId)
        ? values.filter((id) => id !== optionId)
        : [...values, optionId];
}
const calculatorMessage = computed(() => {
    if (!variant.value) {
        return 'Pilih produk dan varian terlebih dahulu.';
    }
    if (!product.value?.active || !variant.value.available) {
        return 'Produk atau varian ini tidak aktif/tersedia untuk pesanan baru.';
    }
    if (!currentConfiguration.value) {
        return 'Belum ada resep untuk varian ini. Simpan resep terlebih dahulu.';
    }
    if (
        !Number.isInteger(Number(count.value)) ||
        count.value < 1 ||
        count.value > 999
    ) {
        return 'Jumlah porsi harus bilangan bulat dari 1 sampai 999.';
    }
    for (const assigned of product.value.groups) {
        const matching = assignedGroups.value.find(
            (item) => item.id === assigned.id,
        );
        if (
            assigned.required &&
            (!matching?.active || !selectedOptions[assigned.id]?.length)
        ) {
            return (
                'Pilih opsi wajib ' +
                (matching?.name ?? '') +
                ' untuk menghitung kebutuhan.'
            );
        }
    }
    return '';
});
const requirements = ref<
    | {
          item: StockItem | undefined;
          base: number;
          extra: number;
          total: number;
      }[]
    | null
>(null);
const calculationError = ref('');
const calculating = ref(false);
const controller = new AbortController();
let calculationVersion = 0;
watch(
    [variantId, count, selectedOptions],
    () => {
        calculationVersion++;
        requirements.value = null;
        calculationError.value = '';
    },
    { deep: true },
);
async function calculateRequirements(): Promise<void> {
    if (calculating.value) {
        return;
    }
    calculating.value = true;
    const version = calculationVersion;
    calculationError.value = '';
    requirements.value = null;
    try {
        const token =
            document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.slice(11) ?? '';
        const response = await fetch(requirementsRoute.url(), {
            method: 'POST',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(token),
            },
            body: JSON.stringify({
                variant_id: variantId.value,
                option_ids: Object.values(selectedOptions).flat(),
                quantity: count.value,
            }),
        });
        const payload = (await response.json()) as {
            items?: {
                stockId: number;
                base: string;
                extra: string;
                total: string;
            }[];
            errors?: Record<string, string[]>;
        };
        if (version !== calculationVersion) {
            return;
        }
        if (!response.ok || !payload.items) {
            calculationError.value = payload.errors
                ? Object.values(payload.errors).flat().join(' ')
                : 'Kebutuhan stok tidak dapat dihitung. Muat ulang halaman dan coba lagi.';
            return;
        }
        requirements.value = payload.items.map((row) => ({
            item: stockItem(row.stockId),
            base: Number(row.base),
            extra: Number(row.extra),
            total: Number(row.total),
        }));
    } catch (exception) {
        if (version !== calculationVersion) {
            return;
        }
        if (
            !(
                exception instanceof DOMException &&
                exception.name === 'AbortError'
            )
        ) {
            calculationError.value = 'Koneksi gagal. Coba hitung kembali.';
        }
    } finally {
        calculating.value = false;
    }
}
const removeBeforeVisit = router.on(
    'before',
    () => processing.value || mayDiscard(),
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
    controller.abort();
    removeBeforeVisit();
    window.removeEventListener('beforeunload', confirmUnload);
});
</script>

<template>
    <div class="recipe-list-page">
        <div class="recipe-list-topbar">
            <nav class="recipe-list-tabs" aria-label="Konfigurasi resep">
                <button
                    type="button"
                    :class="{ active: tab === 'recipes' }"
                    :aria-pressed="tab === 'recipes'"
                    @click="tab = 'recipes'"
                >
                    Resep produk
                </button>
                <button
                    type="button"
                    :class="{ active: tab === 'options' }"
                    :aria-pressed="tab === 'options'"
                    @click="tab = 'options'"
                >
                    Tambahan opsi
                </button>
                <button
                    type="button"
                    :class="{ active: tab === 'pastry' }"
                    :aria-pressed="tab === 'pastry'"
                    @click="tab = 'pastry'"
                >
                    Stok pastry
                </button>
            </nav>
            <Link v-if="tab === 'pastry'" :href="menu()" class="button"
                >Buka Katalog Produk</Link
            >
        </div>
        <div v-if="notice" class="notice recipe-list-notice" role="status">
            <AppIcon name="check" :size="18" /><span>{{ notice }}</span
            ><button
                class="icon-button"
                aria-label="Tutup pemberitahuan"
                @click="notice = ''"
            >
                <AppIcon name="close" :size="16" />
            </button>
        </div>
        <div class="catalog-toolbar recipe-list-toolbar">
            <label class="search-field"
                ><AppIcon name="search" :size="18" /><input
                    v-model="search"
                    type="search"
                    :aria-label="
                        tab === 'options'
                            ? 'Cari grup atau pilihan opsi'
                            : 'Cari produk atau varian'
                    "
                    :placeholder="
                        tab === 'options'
                            ? 'Cari Sugar Level, Medium…'
                            : 'Cari produk atau varian…'
                    " /></label
            ><label v-if="tab === 'recipes'" class="recipe-completeness-filter"
                ><span>Kelengkapan resep</span
                ><select v-model="completeness">
                    <option value="all">Semua konfigurasi</option>
                    <option value="none">Belum diatur</option>
                    <option value="partial">Sebagian diatur</option>
                    <option value="complete">Lengkap</option>
                </select></label
            ><span class="recipe-list-caption">{{
                tab === 'options'
                    ? 'Bahan tambahan dari pilihan pelanggan.'
                    : tab === 'pastry'
                      ? 'Satu unit pesanan memakai 1 pcs.'
                      : 'Produk A–Z · resep disimpan terpisah per varian.'
            }}</span>
        </div>
        <div
            v-if="totalCount"
            class="table-scroll recipe-list-scroll"
            tabindex="0"
            :aria-label="
                tab === 'options'
                    ? 'Daftar tambahan opsi'
                    : 'Daftar resep produk dan varian'
            "
        >
            <table class="product-table recipe-list-table">
                <thead>
                    <tr>
                        <th scope="col">
                            {{ tab === 'options' ? 'Grup opsi' : 'Produk' }}
                        </th>
                        <th scope="col">
                            {{ tab === 'options' ? 'Pilihan' : 'Varian' }}
                        </th>
                        <th scope="col">
                            {{
                                tab === 'pastry'
                                    ? 'Stok terhubung'
                                    : tab === 'recipes'
                                      ? 'Kelengkapan resep'
                                      : 'Konfigurasi'
                            }}
                        </th>
                        <th scope="col" class="align-right">Aksi</th>
                    </tr>
                </thead>
                <tbody v-if="tab === 'options'">
                    <tr v-for="row in visibleOptions" :key="row.option.id">
                        <td>
                            <strong>{{ row.group.name }}</strong
                            ><small
                                v-if="!row.group.active"
                                class="inventory-meta"
                                >Grup nonaktif</small
                            >
                        </td>
                        <td>
                            {{ row.option.name
                            }}<small
                                v-if="!row.option.active"
                                class="inventory-meta"
                                >Nonaktif</small
                            >
                        </td>
                        <td>
                            <span
                                class="inventory-badge"
                                :class="{
                                    healthy:
                                        row.option.id in optionConfigurations,
                                }"
                                >{{
                                    row.option.id in optionConfigurations
                                        ? optionConfigurations[row.option.id]
                                              ?.length
                                            ? optionConfigurations[
                                                  row.option.id
                                              ]?.length + ' bahan tambahan'
                                            : 'Tanpa tambahan bahan'
                                        : 'Belum diatur'
                                }}</span
                            >
                        </td>
                        <td class="align-right">
                            <BaseButton @click="openOption(row)">{{
                                row.option.id in optionConfigurations
                                    ? 'Edit tambahan'
                                    : 'Atur tambahan'
                            }}</BaseButton>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else-if="tab === 'recipes'">
                    <tr v-for="product in visibleProducts" :key="product.id">
                        <td>
                            <strong>{{ product.name }}</strong
                            ><small
                                v-if="!product.active"
                                class="inventory-meta"
                                >Produk nonaktif</small
                            >
                        </td>
                        <td>
                            <span class="recipe-product-variants">{{
                                product.variants
                                    .map((variant) => variant.name)
                                    .join(', ') || 'Belum ada varian'
                            }}</span>
                        </td>
                        <td>
                            <span
                                class="inventory-badge"
                                :class="{
                                    healthy:
                                        configurationState(product) ===
                                        'complete',
                                    'recipe-partial':
                                        configurationState(product) ===
                                        'partial',
                                }"
                                >{{ configurationLabel(product) }}</span
                            >
                        </td>
                        <td class="align-right">
                            <BaseButton
                                :disabled="!product.variants.length"
                                :aria-label="'Kelola resep ' + product.name"
                                @click="openProduct(product)"
                                >Kelola resep</BaseButton
                            >
                        </td>
                    </tr>
                </tbody>
                <tbody v-else>
                    <tr v-for="row in visibleVariants" :key="row.variant.id">
                        <td>
                            <strong>{{ row.product.name }}</strong
                            ><small
                                v-if="!row.product.active"
                                class="inventory-meta"
                                >Produk nonaktif</small
                            >
                        </td>
                        <td>
                            {{ row.variant.name
                            }}<small
                                v-if="!row.variant.available"
                                class="inventory-meta"
                                >Tidak tersedia</small
                            >
                        </td>
                        <td>
                            <span
                                v-if="
                                    recipeConfigurations[row.variant.id]
                                        ?.kind === 'finished'
                                "
                                class="inventory-badge healthy"
                                >{{
                                    tab === 'pastry'
                                        ? stockItem(
                                              recipeConfigurations[
                                                  row.variant.id
                                              ]?.stockId ?? 0,
                                          )?.name
                                        : 'Stok jadi · 1 pcs'
                                }}</span
                            ><span
                                v-else
                                class="inventory-badge"
                                :class="{
                                    healthy:
                                        recipeConfigurations[row.variant.id],
                                }"
                                >{{
                                    recipeConfigurations[row.variant.id]
                                        ? recipeConfigurations[row.variant.id]
                                              ?.ingredients.length + ' bahan'
                                        : 'Belum diatur'
                                }}</span
                            >
                        </td>
                        <td>
                            <div class="recipe-list-actions">
                                <button
                                    v-if="recipeConfigurations[row.variant.id]"
                                    type="button"
                                    class="text-button"
                                    :aria-label="
                                        'Coba hitung ' +
                                        row.product.name +
                                        ' ' +
                                        row.variant.name
                                    "
                                    @click="openCalculator(row)"
                                >
                                    Coba hitung</button
                                ><Link
                                    v-if="
                                        recipeConfigurations[row.variant.id]
                                            ?.kind === 'finished'
                                    "
                                    :href="
                                        menu({ query: { q: row.product.name } })
                                    "
                                    class="button"
                                    >Kelola produk</Link
                                ><BaseButton v-else @click="openVariant(row)">{{
                                    recipeConfigurations[row.variant.id]
                                        ? 'Edit resep'
                                        : 'Atur resep'
                                }}</BaseButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="empty-state">
            <span class="empty-icon"
                ><AppIcon
                    :name="
                        search
                            ? 'search'
                            : tab === 'pastry'
                              ? 'category'
                              : 'coffee'
                    "
                    :size="28"
            /></span>
            <h3>
                {{
                    search
                        ? 'Tidak ada hasil pencarian'
                        : tab === 'pastry'
                          ? 'Belum ada produk siap jual'
                          : tab === 'options'
                            ? 'Belum ada pilihan opsi'
                            : completeness !== 'all'
                              ? 'Tidak ada produk yang cocok'
                              : 'Belum ada produk'
                }}
            </h3>
            <p>
                {{
                    search
                        ? 'Coba nama produk, varian atau opsi yang lain.'
                        : tab === 'pastry'
                          ? 'Buat produk dengan pilihan Dari stok produk jadi di Katalog Produk. Stok akan dibuat otomatis.'
                          : tab === 'options'
                            ? 'Tambahkan grup dan pilihan opsi di Menu terlebih dahulu.'
                            : completeness !== 'all'
                              ? 'Coba kelengkapan resep yang lain atau tampilkan semua produk.'
                              : 'Buat produk beserta variannya di Katalog Produk.'
                }}
            </p>
            <BaseButton
                v-if="search || (tab === 'recipes' && completeness !== 'all')"
                @click="
                    search = '';
                    completeness = 'all';
                "
                >Reset pencarian dan filter</BaseButton
            ><Link
                v-else
                :href="tab === 'options' ? menuOptions() : menu()"
                class="button"
                >{{
                    tab === 'options' ? 'Buka Grup Opsi' : 'Buka Katalog Produk'
                }}</Link
            >
        </div>
        <footer v-if="totalCount" class="recipe-list-pagination">
            <span role="status"
                >{{ (page - 1) * 10 + 1 }}–{{
                    Math.min(page * 10, totalCount)
                }}
                dari {{ totalCount }}
                {{
                    tab === 'options'
                        ? 'opsi'
                        : tab === 'recipes'
                          ? 'produk'
                          : 'varian'
                }}</span
            >
            <nav aria-label="Halaman daftar resep">
                <BaseButton :disabled="page === 1" @click="page--"
                    >Sebelumnya</BaseButton
                ><span>{{ page }} / {{ pageCount }}</span
                ><BaseButton :disabled="page === pageCount" @click="page++"
                    >Berikutnya</BaseButton
                >
            </nav>
        </footer>

        <AppDialog
            :open="editing"
            :title="dialogTitle"
            class="recipe-editor-dialog"
            @close="closeDialog"
        >
            <form
                id="recipe-configuration-form"
                class="recipe-focused-form"
                @submit.prevent="applyDraft"
            >
                <fieldset
                    :disabled="processing"
                    style="border: 0; padding: 0; margin: 0"
                >
                    <div v-if="managingProduct" class="recipe-product-editor">
                        <p class="recipe-product-editor-help">
                            Pilih varian untuk mengatur bahan atau stoknya.
                            Setiap varian disimpan terpisah.
                        </p>
                        <div
                            v-if="(product?.variants.length ?? 0) > 1"
                            class="recipe-variant-selector"
                            role="group"
                            aria-label="Pilih varian resep"
                        >
                            <button
                                v-for="item in product?.variants"
                                :key="item.id"
                                type="button"
                                :aria-pressed="variantId === item.id"
                                @click="chooseRecipeVariant(item)"
                            >
                                <strong>{{ item.name }}</strong
                                ><span>{{
                                    configuredVariant(item.id)
                                        ? 'Diatur'
                                        : 'Belum diatur'
                                }}</span>
                            </button>
                        </div>
                        <p v-else-if="variant" class="recipe-single-variant">
                            Varian: <strong>{{ variant.name }}</strong>
                        </p>
                        <p
                            v-if="variant && !variant.available"
                            class="field-help"
                        >
                            Varian ini sedang tidak tersedia untuk pesanan baru.
                        </p>
                        <p
                            v-if="savedVariant && !dirty"
                            class="recipe-saved-variant"
                            role="status"
                        >
                            <AppIcon name="check" :size="16" />{{
                                savedVariant
                            }}
                        </p>
                    </div>
                    <p class="form-intro">
                        {{
                            dialog === 'option'
                                ? 'Tambahan per porsi. Digunakan bersama oleh produk yang memakai opsi ini.'
                                : viewingFinished
                                  ? 'Produk siap jual menggunakan stok yang sudah dibuat otomatis.'
                                  : 'Bahan yang dibutuhkan untuk satu porsi varian ini.'
                        }}
                    </p>
                    <template v-if="viewingFinished">
                        <p class="recipe-focused-context">
                            <strong>{{
                                stockItem(currentConfiguration?.stockId ?? 0)
                                    ?.name || 'Stok produk'
                            }}</strong>
                            · 1 pcs per item pesanan
                        </p>
                        <p class="field-help">
                            Jumlah barang dikelola di halaman Stok. Ubah
                            informasi produk dan batas stok rendah melalui
                            Katalog Produk.
                        </p>
                        <Link
                            :href="menu({ query: { q: product?.name ?? '' } })"
                            class="button"
                            >Kelola produk</Link
                        >
                    </template>
                    <template v-else>
                        <section
                            class="recipe-quick-add"
                            aria-label="Tambah cepat bahan"
                        >
                            <div class="recipe-picker-heading">
                                <h3>Tambah cepat</h3>
                                <span>Diatur oleh owner di halaman Stok</span>
                            </div>
                            <div
                                v-if="quickIngredients.length"
                                class="recipe-quick-buttons"
                            >
                                <button
                                    v-for="item in quickIngredients"
                                    :key="item.id"
                                    type="button"
                                    :disabled="
                                        draft.ingredients.some(
                                            (row) => row.stockId === item.id,
                                        )
                                    "
                                    :aria-label="
                                        'Tambah ' + item.name + ' ke resep'
                                    "
                                    @click="selectIngredient(item)"
                                >
                                    <AppIcon
                                        :name="
                                            draft.ingredients.some(
                                                (row) =>
                                                    row.stockId === item.id,
                                            )
                                                ? 'check'
                                                : 'plus'
                                        "
                                        :size="15"
                                    /><span>{{ item.name }}</span
                                    ><small>{{ item.unit }}</small>
                                </button>
                            </div>
                            <p v-else class="field-help">
                                Belum ada pintasan. Aktifkan toggle “Tambah
                                cepat” pada baris bahan di halaman Stok.
                            </p>
                        </section>
                        <div
                            v-for="(row, index) in draft.ingredients"
                            :key="index"
                            class="recipe-ingredient-row"
                        >
                            <div class="field recipe-ingredient-choice">
                                <span>Bahan</span
                                ><button
                                    type="button"
                                    class="recipe-stock-select"
                                    :aria-label="
                                        'Ganti bahan ' +
                                        (stockItem(row.stockId)?.name ?? '')
                                    "
                                    :aria-expanded="
                                        ingredientPickerOpen &&
                                        replacingIngredient === index
                                    "
                                    :aria-controls="ingredientPickerId"
                                    @click="openIngredientPicker(index, $event)"
                                >
                                    <span>{{
                                        stockItem(row.stockId)?.name ??
                                        'Pilih bahan'
                                    }}</span
                                    ><AppIcon
                                        name="search"
                                        :size="16"
                                    /></button
                                ><small
                                    v-if="!stockItem(row.stockId)?.active"
                                    class="field-help"
                                    >Bahan nonaktif · pilih pengganti</small
                                >
                            </div>
                            <label class="field"
                                >Jumlah ({{
                                    stockItem(row.stockId)?.unit
                                }})<input
                                    :id="
                                        ingredientPickerId +
                                        '-quantity-' +
                                        index
                                    "
                                    v-model.number="row.quantity"
                                    type="number"
                                    :min="dialog === 'recipe' ? 0.001 : 0"
                                    step="0.001"
                                    required /></label
                            ><button
                                type="button"
                                class="icon-button"
                                :aria-label="'Hapus baris bahan ' + (index + 1)"
                                @click="removeIngredient(index)"
                            >
                                <AppIcon name="trash" :size="17" />
                            </button>
                        </div>
                        <p
                            v-if="!draft.ingredients.length"
                            class="recipe-focused-empty"
                        >
                            {{
                                dialog === 'option'
                                    ? 'Tidak ada tambahan bahan. Biarkan kosong untuk pilihan seperti Zero Sugar.'
                                    : 'Belum ada bahan. Tambahkan bahan pertama untuk varian ini.'
                            }}
                        </p>
                        <BaseButton
                            :disabled="!remainingIngredients.length"
                            :aria-expanded="
                                ingredientPickerOpen &&
                                replacingIngredient === null
                            "
                            :aria-controls="ingredientPickerId"
                            @click="openIngredientPicker(null, $event)"
                            ><AppIcon name="search" :size="16" />Tambah
                            bahan</BaseButton
                        >
                        <p v-if="!ingredients.length" class="field-help">
                            Tambahkan item stok aktif dengan unit g atau ml
                            terlebih dahulu.
                        </p>
                        <section
                            v-if="ingredientPickerOpen"
                            :id="ingredientPickerId"
                            class="recipe-stock-picker"
                            aria-label="Pilih bahan stok"
                            @keydown.esc.stop.prevent="closeIngredientPicker()"
                        >
                            <div class="recipe-picker-heading">
                                <h3>
                                    {{
                                        replacingIngredient === null
                                            ? 'Tambah bahan'
                                            : 'Ganti bahan'
                                    }}
                                </h3>
                                <button
                                    type="button"
                                    class="icon-button"
                                    aria-label="Tutup pencarian bahan"
                                    @click="closeIngredientPicker()"
                                >
                                    <AppIcon name="close" :size="17" />
                                </button>
                            </div>
                            <label class="recipe-stock-search"
                                ><span class="sr-only"
                                    >Cari nama bahan stok</span
                                ><AppIcon name="search" :size="17" /><input
                                    ref="ingredientSearchInput"
                                    v-model="ingredientSearch"
                                    type="search"
                                    @keydown.enter.prevent
                                    placeholder="Cari kopi, susu, matcha…"
                            /></label>
                            <p class="recipe-picker-result-count" role="status">
                                {{ ingredientResults.length }} bahan tersedia
                                untuk dipilih
                            </p>
                            <ul
                                v-if="ingredientResults.length"
                                class="recipe-stock-results"
                            >
                                <li
                                    v-for="item in ingredientResults"
                                    :key="item.id"
                                >
                                    <button
                                        type="button"
                                        @click="
                                            selectIngredient(
                                                item,
                                                replacingIngredient,
                                            )
                                        "
                                    >
                                        <span
                                            >{{ item.name
                                            }}<small>{{
                                                item.unit
                                            }}</small></span
                                        ><AppIcon
                                            :name="
                                                replacingIngredient !== null &&
                                                draft.ingredients[
                                                    replacingIngredient
                                                ]?.stockId === item.id
                                                    ? 'check'
                                                    : 'plus'
                                            "
                                            :size="17"
                                        />
                                    </button>
                                </li>
                            </ul>
                            <p v-else class="recipe-picker-empty">
                                {{
                                    ingredientSearch
                                        ? 'Tidak ada bahan yang cocok. Coba nama lain.'
                                        : 'Semua bahan aktif sudah ditambahkan.'
                                }}
                            </p>
                        </section>
                    </template>
                    <p v-if="error" class="field-error" role="alert">
                        {{ error }}
                    </p>
                </fieldset>
            </form>
            <template #footer
                ><span class="save-hint">{{
                    viewingFinished
                        ? 'Stok produk dikelola otomatis.'
                        : 'Perubahan berlaku setelah disimpan'
                }}</span
                ><BaseButton
                    v-if="managingProduct && currentConfiguration"
                    :disabled="processing"
                    @click="testCurrentRecipe"
                    >Coba hitung</BaseButton
                ><BaseButton @click="closeDialog">{{
                    managingProduct ? 'Tutup' : 'Batal'
                }}</BaseButton
                ><BaseButton
                    v-if="!viewingFinished"
                    :disabled="processing"
                    type="submit"
                    form="recipe-configuration-form"
                    variant="primary"
                    >{{
                        processing
                            ? 'Menyimpan…'
                            : managingProduct
                              ? 'Simpan varian'
                              : 'Simpan konfigurasi'
                    }}</BaseButton
                ></template
            >
        </AppDialog>
        <AppDialog
            :open="dialog === 'calculator'"
            title="Coba hitung kebutuhan"
            class="recipe-editor-dialog"
            @close="dialog = null"
        >
            <div class="recipe-focused-form recipe-focused-calculator">
                <p class="recipe-focused-context">
                    <strong>{{ product?.name }}</strong> · {{ variant?.name }}
                </p>
                <label class="field"
                    >Jumlah porsi<input
                        v-model.number="count"
                        type="number"
                        min="1"
                        max="999"
                        step="1"
                        required
                /></label>
                <template v-for="assigned in assignedGroups" :key="assigned.id"
                    ><label
                        v-if="
                            assigned.active && assigned.selection === 'single'
                        "
                        class="field"
                        >{{ assigned.name
                        }}{{
                            product?.groups.find(
                                (item) => item.id === assigned.id,
                            )?.required
                                ? ' · wajib'
                                : ' · opsional'
                        }}<select
                            :value="selectedOptions[assigned.id]?.[0] ?? ''"
                            @change="selectSingle(assigned.id, $event)"
                        >
                            <option value="">Pilih {{ assigned.name }}</option>
                            <option
                                v-for="choice in assigned.options.filter(
                                    (item) => item.active,
                                )"
                                :key="choice.id"
                                :value="choice.id"
                            >
                                {{ choice.name }}
                            </option>
                        </select></label
                    >
                    <fieldset
                        v-else-if="assigned.active"
                        class="recipe-option-fieldset"
                    >
                        <legend>
                            {{ assigned.name
                            }}{{
                                product?.groups.find(
                                    (item) => item.id === assigned.id,
                                )?.required
                                    ? ' · wajib'
                                    : ' · opsional'
                            }}
                        </legend>
                        <label
                            v-for="choice in assigned.options.filter(
                                (item) => item.active,
                            )"
                            :key="choice.id"
                            class="check-label"
                            ><input
                                type="checkbox"
                                :checked="
                                    selectedOptions[assigned.id]?.includes(
                                        choice.id,
                                    ) ?? false
                                "
                                @change="toggleMultiple(assigned.id, choice.id)"
                            />{{ choice.name }}</label
                        >
                    </fieldset></template
                >
                <p
                    v-if="calculatorMessage"
                    class="recipe-focused-empty"
                    role="status"
                >
                    {{ calculatorMessage }}
                </p>

                <p v-if="calculationError" class="field-error" role="alert">
                    {{ calculationError }}
                </p>
                <BaseButton
                    :disabled="calculating || Boolean(calculatorMessage)"
                    variant="primary"
                    @click="calculateRequirements"
                    >{{
                        calculating ? 'Menghitung…' : 'Hitung kebutuhan'
                    }}</BaseButton
                >
                <div
                    v-if="requirements"
                    class="requirements-result"
                    aria-live="polite"
                >
                    <p>Total kebutuhan bahan</p>
                    <div
                        v-for="requirement in requirements"
                        :key="requirement.item?.id"
                    >
                        <span
                            >{{ requirement.item?.name
                            }}<small
                                >{{ formatQuantity(requirement.base) }} +
                                {{ formatQuantity(requirement.extra) }}
                                {{ requirement.item?.unit }} / porsi</small
                            ></span
                        ><strong
                            >{{ formatQuantity(requirement.total)
                            }}<small>{{
                                requirement.item?.unit
                            }}</small></strong
                        >
                    </div>
                </div>
                <p class="field-help">
                    Perhitungan memakai resep tersimpan. Tidak membuat pesanan
                    atau mengubah stok.
                </p>
            </div>
            <template #footer
                ><BaseButton @click="dialog = null">Tutup</BaseButton></template
            >
        </AppDialog>
    </div>
</template>
