<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ProductEditor from '@/components/admin/ProductEditor.vue';
import MenuPricePreview from '@/components/admin/MenuPricePreview.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TablePagination from '@/components/ui/TablePagination.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { categories, menu, options } from '@/routes/admin';
import {
    store as storeProduct,
    update as updateProduct,
    status as productStatus,
} from '@/routes/admin/products';
import {
    store as storeCategory,
    update as updateCategory,
} from '@/routes/admin/categories';
import {
    store as storeGroup,
    update as updateGroup,
} from '@/routes/admin/options';
import { formatRupiah as rupiah } from '@/lib/utils';
import type {
    Catalog,
    Category,
    OptionGroup,
    Product,
    ProductStockItem,
    MenuFilters,
    TablePagination as PaginationData,
} from '@/types/menu';

defineOptions({ layout: AdminLayout });
const props = defineProps<{
    section: 'products' | 'categories' | 'options';
    catalog: Catalog;
    filters: MenuFilters;
    pagination: PaginationData;
    summary: { products: number; activeProducts: number };
    stockItems: ProductStockItem[];
}>();
const catalog = computed(() => props.catalog);
const query = ref(props.section === 'products' ? props.filters.q : '');
const categoryFilter = ref(props.filters.category_id);
const statusFilter = ref(
    props.section === 'products' ? props.filters.status : 'all',
);
const perPage = ref(props.filters.per_page);
const editorOpen = ref(false);
const editingProduct = ref<Product | null>(null);
const pricingProduct = ref<Product | null>(null);
const smallEditor = ref<'category' | 'group' | null>(null);
const discardSmallEditorOpen = ref(false);
const notice = ref('');
const requestError = ref('');
const processing = ref(false);
const productErrors = ref<Record<string, string>>({});
const confirmProduct = ref<Product | null>(null);
const categoryDraft = reactive<Category>({ id: 0, name: '', active: true });
const groupDraft = reactive<OptionGroup>({
    id: 0,
    name: '',
    selection: 'single',
    active: true,
    options: [],
});
const smallOriginal = ref('');
const smallError = ref('');
const tabs = [
    { label: 'Produk', section: 'products', href: menu },
    { label: 'Kategori', section: 'categories', href: categories },
    { label: 'Grup opsi', section: 'options', href: options },
];
const title = computed(
    () =>
        ({ products: 'Produk', categories: 'Kategori', options: 'Grup opsi' })[
            props.section
        ],
);
const subtitle = computed(
    () =>
        ({
            products:
                'Kelola menu, varian, dan pilihan yang membuat setiap pesanan pas.',
            categories: 'Rapikan menu agar produk lebih mudah ditemukan.',
            options:
                'Atur pilihan tambahan yang bisa digunakan di berbagai produk.',
        })[props.section],
);
function matchesStatus(active: boolean): boolean {
    return (
        statusFilter.value === 'all' ||
        active === (statusFilter.value === 'active')
    );
}
const filteredProducts = computed(() => catalog.value.products);
const filteredCategories = computed(() =>
    catalog.value.categories
        .filter(
            (category) =>
                category.name
                    .toLowerCase()
                    .includes(query.value.trim().toLowerCase()) &&
                matchesStatus(category.active),
        )
        .sort((a, b) => a.name.localeCompare(b.name)),
);
const filteredGroups = computed(() =>
    catalog.value.groups
        .filter(
            (group) =>
                group.name
                    .toLowerCase()
                    .includes(query.value.trim().toLowerCase()) &&
                matchesStatus(group.active),
        )
        .sort((a, b) => a.name.localeCompare(b.name)),
);
const visibleCount = computed(() =>
    props.section === 'products'
        ? filteredProducts.value.length
        : props.section === 'categories'
          ? filteredCategories.value.length
          : filteredGroups.value.length,
);
watch(
    () => props.section,
    () => {
        query.value = '';
        categoryFilter.value = 0;
        statusFilter.value = 'all';
        notice.value = '';
        requestError.value = '';
    },
);

let filterTimer: ReturnType<typeof setTimeout> | undefined;
const tableLoading = ref(false);
function visitProducts(overrides: Partial<MenuFilters> = {}): void {
    clearTimeout(filterTimer);
    router.get(
        menu.url(),
        {
            q: query.value.trim(),
            category_id: categoryFilter.value,
            status: statusFilter.value,
            per_page: perPage.value,
            ...overrides,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                tableLoading.value = true;
            },
            onError: (errors) => {
                requestError.value = Object.values(errors).join(' ');
            },
            onFinish: () => {
                tableLoading.value = false;
            },
        },
    );
}
watch(
    () => props.filters,
    (filters) => {
        if (props.section !== 'products') {
            return;
        }
        clearTimeout(filterTimer);
        query.value = filters.q;
        categoryFilter.value = filters.category_id;
        statusFilter.value = filters.status;
        perPage.value = filters.per_page;
    },
);
watch([query, categoryFilter, statusFilter, perPage], (current, previous) => {
    clearTimeout(filterTimer);
    if (
        props.section !== 'products' ||
        (query.value.trim() === props.filters.q &&
            categoryFilter.value === props.filters.category_id &&
            statusFilter.value === props.filters.status &&
            perPage.value === props.filters.per_page)
    ) {
        return;
    }
    if (
        current.slice(1).some((value, index) => value !== previous[index + 1])
    ) {
        visitProducts();
        return;
    }
    filterTimer = setTimeout(() => {
        if (props.section === 'products') {
            visitProducts();
        }
    }, 300);
});
onBeforeUnmount(() => clearTimeout(filterTimer));

function openProduct(product: Product | null = null): void {
    editingProduct.value = product;
    productErrors.value = {};
    editorOpen.value = true;
}
function saveProduct(product: Product, image: File | null): void {
    if (processing.value) {
        return;
    }
    processing.value = true;
    productErrors.value = {};
    router.post(
        editingProduct.value
            ? updateProduct.url(editingProduct.value.id)
            : storeProduct.url(),
        {
            ...(editingProduct.value ? { _method: 'put' } : {}),
            name: product.name,
            description: product.description,
            category_id: product.categoryId,
            is_active: product.active,
            image,
            remove_image: !product.image,
            variants: product.variants.map((variant) => ({
                id: variant.id ?? null,
                name: variant.name.trim(),
                price_idr: variant.price,
                is_available: variant.available,
                stock: variant.stock
                    ? {
                          kind: variant.stock.method,
                          ...(variant.stock.method === 'finished'
                              ? {
                                    reorder_point: variant.stock.reorderPoint,
                                    ...(!variant.id
                                        ? {
                                              opening_qty:
                                                  variant.stock
                                                      .openingQuantity ?? 0,
                                          }
                                        : {}),
                                }
                              : {}),
                          ingredients:
                              variant.stock.method === 'ingredients'
                                  ? variant.stock.ingredients.map((row) => ({
                                        stock_item_id: row.stockId,
                                        quantity: row.quantity,
                                    }))
                                  : [],
                      }
                    : undefined,
            })),
            groups: product.groups.map((group) => ({
                id: group.id,
                is_required: group.required,
            })),
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                editorOpen.value = false;
                notice.value = 'Produk berhasil disimpan.';
            },
            onError: (errors) => {
                productErrors.value = errors;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
function openCategory(category?: Category): void {
    discardSmallEditorOpen.value = false;
    Object.assign(categoryDraft, category ?? { id: 0, name: '', active: true });
    smallOriginal.value = JSON.stringify(categoryDraft);
    smallError.value = '';
    smallEditor.value = 'category';
}
function openGroup(group?: OptionGroup): void {
    discardSmallEditorOpen.value = false;
    Object.assign(
        groupDraft,
        group
            ? {
                  ...group,
                  options: group.options.map((option) => ({ ...option })),
              }
            : {
                  id: 0,
                  name: '',
                  selection: 'single',
                  active: true,
                  options: [{ name: '', price: 0, active: true }],
              },
    );
    smallOriginal.value = JSON.stringify(groupDraft);
    smallError.value = '';
    smallEditor.value = 'group';
}
function closeSmallEditor(): void {
    if (processing.value || !smallEditor.value) {
        return;
    }
    const draft = smallEditor.value === 'category' ? categoryDraft : groupDraft;
    if (JSON.stringify(draft) !== smallOriginal.value) {
        discardSmallEditorOpen.value = true;
        return;
    }
    smallEditor.value = null;
}
function discardSmallEditorChanges(): void {
    if (processing.value) {
        return;
    }
    discardSmallEditorOpen.value = false;
    smallEditor.value = null;
}
function saveSmallEditor(): void {
    if (processing.value) {
        return;
    }
    processing.value = true;
    smallError.value = '';
    const callbacks = {
        preserveScroll: true,
        onSuccess: () => {
            smallEditor.value = null;
            notice.value = 'Perubahan berhasil disimpan.';
        },
        onError: (errors: Record<string, string>) => {
            smallError.value = Object.values(errors).join(' ');
        },
        onFinish: () => {
            processing.value = false;
        },
    };
    if (smallEditor.value === 'category') {
        const payload = {
            name: categoryDraft.name.trim(),
            is_active: categoryDraft.active,
        };
        if (categoryDraft.id) {
            router.put(updateCategory(categoryDraft.id), payload, callbacks);
        } else {
            router.post(storeCategory(), payload, callbacks);
        }
    } else {
        const payload = {
            name: groupDraft.name.trim(),
            selection_type: groupDraft.selection,
            is_active: groupDraft.active,
            options: groupDraft.options.map((option) => ({
                id: option.id ?? null,
                name: option.name.trim(),
                price_delta_idr: option.price,
                is_active: option.active,
            })),
        };
        if (groupDraft.id) {
            router.put(updateGroup(groupDraft.id), payload, callbacks);
        } else {
            router.post(storeGroup(), payload, callbacks);
        }
    }
}
function addItem(): void {
    if (props.section === 'products') {
        openProduct();
    } else if (props.section === 'categories') {
        openCategory();
    } else {
        openGroup();
    }
}
function resetFilters(): void {
    if (props.section === 'products') {
        visitProducts({ q: '', category_id: 0, status: 'all' });
        return;
    }
    query.value = '';
    categoryFilter.value = 0;
    statusFilter.value = 'all';
}
function toggleProduct(): void {
    if (!confirmProduct.value || processing.value) {
        return;
    }
    processing.value = true;
    requestError.value = '';
    router.patch(
        productStatus(confirmProduct.value.id),
        { is_active: !confirmProduct.value.active },
        {
            preserveScroll: true,
            onSuccess: () => {
                confirmProduct.value = null;
                notice.value = 'Status produk berhasil diperbarui.';
            },
            onError: (errors) => {
                requestError.value = Object.values(errors).join(' ');
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="catalog-page">
        <Head :title="`${title} · Admin`" />
        <div class="page-heading">
            <div>
                <span class="eyebrow">PENGELOLAAN MENU</span>
                <h1>{{ title }}<span class="title-dot">.</span></h1>
                <p>{{ subtitle }}</p>
            </div>
            <div class="catalog-heading-actions">
                <BaseButton variant="primary" @click="addItem"
                    ><AppIcon name="plus" :size="18" />Tambah
                    {{
                        section === 'products'
                            ? 'produk'
                            : section === 'categories'
                              ? 'kategori'
                              : 'grup opsi'
                    }}</BaseButton
                >
            </div>
        </div>
        <div class="catalog-summary">
            <div>
                <span class="summary-icon"><AppIcon name="coffee" /></span>
                <div>
                    <strong>{{ summary.products }}<small>produk</small></strong>
                    <p>Dalam katalog</p>
                </div>
            </div>
            <div>
                <span class="summary-icon green"><AppIcon name="check" /></span>
                <div>
                    <strong
                        >{{ summary.activeProducts
                        }}<small>aktif</small></strong
                    >
                    <p>Ditawarkan di menu</p>
                </div>
            </div>
            <div>
                <span class="summary-icon"><AppIcon name="category" /></span>
                <div>
                    <strong
                        >{{ catalog.categories.length
                        }}<small>kategori</small></strong
                    >
                    <p>Kelompok menu</p>
                </div>
            </div>
            <div>
                <span class="summary-icon"><AppIcon name="options" /></span>
                <div>
                    <strong
                        >{{ catalog.groups.length
                        }}<small>grup opsi</small></strong
                    >
                    <p>Pilihan tambahan</p>
                </div>
            </div>
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
        <section
            class="catalog-panel"
            :aria-label="`Daftar ${title}`"
            :aria-busy="section === 'products' && tableLoading"
        >
            <div class="catalog-tabs">
                <nav aria-label="Bagian pengelolaan menu">
                    <Link
                        v-for="tab in tabs"
                        :key="tab.section"
                        :href="tab.href()"
                        :class="{ active: section === tab.section }"
                        :aria-current="
                            section === tab.section ? 'page' : undefined
                        "
                        >{{ tab.label
                        }}<span>{{
                            tab.section === 'products'
                                ? summary.products
                                : tab.section === 'categories'
                                  ? catalog.categories.length
                                  : catalog.groups.length
                        }}</span></Link
                    >
                </nav>
                <span class="sample-label">KATALOG MENU</span>
            </div>
            <div class="catalog-toolbar">
                <label class="search-field"
                    ><AppIcon name="search" :size="19" /><input
                        v-model="query"
                        type="search"
                        maxlength="120"
                        :placeholder="`Cari ${title.toLowerCase()}…`"
                        :aria-label="`Cari ${title.toLowerCase()}`"
                    /><kbd>/</kbd></label
                >
                <div class="toolbar-filters">
                    <label
                        v-if="section === 'products'"
                        class="sr-only"
                        for="category-filter"
                        >Filter kategori</label
                    ><select
                        v-if="section === 'products'"
                        id="category-filter"
                        v-model="categoryFilter"
                    >
                        <option :value="0">Semua kategori</option>
                        <option
                            v-for="category in catalog.categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option></select
                    ><label class="sr-only" for="status-filter"
                        >Filter status</label
                    ><select id="status-filter" v-model="statusFilter">
                        <option value="all">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
            </div>
            <p v-if="requestError" class="field-error" role="alert">
                {{ requestError }}
            </p>
            <div v-if="visibleCount === 0" class="empty-state">
                <span class="empty-icon"
                    ><AppIcon name="search" :size="28"
                /></span>
                <h3>Tidak ada hasil untuk filter ini</h3>
                <p>Coba kata kunci lain atau tampilkan semua data.</p>
                <BaseButton @click="resetFilters">Reset filter</BaseButton>
            </div>
            <div v-else-if="section === 'products'" class="table-scroll">
                <table class="product-table">
                    <thead>
                        <tr>
                            <th scope="col">PRODUK</th>
                            <th scope="col">KATEGORI</th>
                            <th scope="col">VARIAN & HARGA</th>
                            <th scope="col">STATUS</th>
                            <th scope="col" class="align-right">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="product in filteredProducts"
                            :key="product.id"
                            :class="{ 'is-inactive': !product.active }"
                        >
                            <td>
                                <div class="product-cell">
                                    <span
                                        class="product-thumbnail"
                                        :class="`thumbnail-${product.categoryId}`"
                                        ><img
                                            v-if="product.image"
                                            :src="product.image"
                                            alt="" /><AppIcon
                                            v-else
                                            :name="
                                                product.categoryId === 3
                                                    ? 'image'
                                                    : 'coffee'
                                            "
                                            :size="26"
                                    /></span>
                                    <div>
                                        <button
                                            class="product-name"
                                            @click="openProduct(product)"
                                        >
                                            {{ product.name }}
                                        </button>
                                        <p>{{ product.description }}</p>
                                        <span
                                            v-if="product.groups.length"
                                            class="product-option-hint"
                                            >{{
                                                product.groups
                                                    .map(
                                                        (assigned) =>
                                                            catalog.groups.find(
                                                                (group) =>
                                                                    group.id ===
                                                                    assigned.id,
                                                            )?.name,
                                                    )
                                                    .join(' · ')
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="category-tag">{{
                                    catalog.categories.find(
                                        (category) =>
                                            category.id === product.categoryId,
                                    )?.name
                                }}</span>
                            </td>
                            <td>
                                <strong class="price-label"
                                    ><small v-if="product.variants.length > 1"
                                        >Mulai </small
                                    >{{
                                        rupiah(
                                            Math.min(
                                                ...product.variants.map(
                                                    (variant) => variant.price,
                                                ),
                                            ),
                                        )
                                    }}</strong
                                >
                                <p class="variant-caption">
                                    {{
                                        product.variants
                                            .map((variant) => variant.name)
                                            .join(' / ')
                                    }}
                                </p>
                            </td>
                            <td><StatusBadge :active="product.active" /></td>
                            <td>
                                <div class="row-actions">
                                    <button
                                        class="icon-button"
                                        :aria-label="`Cek harga ${product.name}`"
                                        @click="pricingProduct = product"
                                    >
                                        <AppIcon name="options" :size="18" />
                                    </button>
                                    <button
                                        class="icon-button"
                                        :aria-label="`Edit ${product.name}`"
                                        @click="openProduct(product)"
                                    >
                                        <AppIcon
                                            name="edit"
                                            :size="18"
                                        /></button
                                    ><button
                                        class="text-button status-action"
                                        @click="confirmProduct = product"
                                    >
                                        {{
                                            product.active
                                                ? 'Nonaktifkan'
                                                : 'Aktifkan'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else-if="section === 'categories'" class="category-list">
                <article
                    v-for="category in filteredCategories"
                    :key="category.id"
                    class="category-row"
                >
                    <span class="category-large-icon"
                        ><AppIcon name="category" :size="24"
                    /></span>
                    <div class="category-details">
                        <h3>{{ category.name }}</h3>
                        <p>
                            {{ category.productCount ?? 0 }}
                            produk dalam kategori ini
                        </p>
                    </div>
                    <StatusBadge :active="category.active" /><BaseButton
                        @click="openCategory(category)"
                        ><AppIcon name="edit" :size="16" />Edit</BaseButton
                    >
                </article>
            </div>
            <div v-else class="option-group-grid">
                <article
                    v-for="group in filteredGroups"
                    :key="group.id"
                    class="option-group-card"
                >
                    <header>
                        <span class="summary-icon"
                            ><AppIcon name="options" /></span
                        ><StatusBadge :active="group.active" /><button
                            class="icon-button"
                            :aria-label="`Edit grup ${group.name}`"
                            @click="openGroup(group)"
                        >
                            <AppIcon name="edit" :size="18" />
                        </button>
                    </header>
                    <h3>{{ group.name }}</h3>
                    <p>
                        {{
                            group.selection === 'single'
                                ? 'Satu pilihan'
                                : 'Bisa pilih beberapa'
                        }}<span class="footer-dot">·</span
                        >{{ group.productCount ?? 0 }}
                        produk
                    </p>
                    <div class="option-values">
                        <div v-for="option in group.options" :key="option.name">
                            <span :class="{ 'muted-option': !option.active }"
                                >{{ option.name
                                }}{{ option.active ? '' : ' (nonaktif)' }}</span
                            ><strong>{{
                                option.price
                                    ? `+${rupiah(option.price)}`
                                    : 'Tanpa tambahan'
                            }}</strong>
                        </div>
                    </div>
                </article>
            </div>
            <TablePagination
                v-if="section === 'products'"
                :pagination="pagination"
                :busy="tableLoading"
                @limit="perPage = $event"
            />
            <footer v-else class="catalog-footer">
                <span
                    >Menampilkan <strong>{{ visibleCount }}</strong>
                    {{ title.toLowerCase() }}</span
                ><span>Diurutkan A–Z</span>
            </footer>
        </section>
        <p class="page-note">
            <AppIcon name="info" :size="16" />Data menu tersimpan di server.
            Harga dan pilihan pesanan divalidasi oleh Laravel.
        </p>
        <MenuPricePreview
            v-if="pricingProduct"
            :product="pricingProduct"
            :groups="catalog.groups"
            @close="pricingProduct = null"
        />
        <ProductEditor
            v-if="editorOpen"
            :product="editingProduct"
            :categories="catalog.categories"
            :groups="catalog.groups"
            :errors="productErrors"
            :processing="processing"
            :stocks="stockItems"
            @close="editorOpen = false"
            @save="saveProduct"
        />
        <AppDialog
            :open="smallEditor !== null"
            :title="
                smallEditor === 'category'
                    ? 'Kelola kategori'
                    : 'Kelola grup opsi'
            "
            @close="closeSmallEditor"
            ><form
                id="small-editor"
                class="small-editor"
                @submit.prevent="saveSmallEditor"
            >
                <template v-if="smallEditor === 'category'"
                    ><p class="form-intro">
                        Kategori membantu mengelompokkan produk di menu.
                    </p>
                    <label class="field"
                        >Nama kategori<input
                            v-model="categoryDraft.name"
                            required
                            maxlength="100"
                            autofocus
                            placeholder="Contoh: Coffee" /></label
                    ><label class="check-label"
                        ><input
                            v-model="categoryDraft.active"
                            type="checkbox"
                        />Kategori aktif</label
                    >
                    <p class="field-help">
                        Kategori nonaktif tidak ditawarkan untuk pesanan baru.
                        Produk dan riwayat tetap tersimpan.
                    </p></template
                ><template v-else
                    ><label class="field"
                        >Nama grup<input
                            v-model="groupDraft.name"
                            @input="
                                groupDraft.name.trim().toLowerCase() ===
                                    'sugar level' &&
                                (groupDraft.selection = 'single')
                            "
                            required
                            maxlength="100"
                            autofocus
                            placeholder="Contoh: Sugar Level" /></label
                    ><label class="field"
                        >Jenis pilihan<select
                            v-model="groupDraft.selection"
                            :disabled="
                                groupDraft.name.trim().toLowerCase() ===
                                'sugar level'
                            "
                        >
                            <option value="single">
                                Satu pilihan (single)
                            </option>
                            <option value="multiple">
                                Beberapa pilihan (multiple)
                            </option>
                        </select></label
                    ><label class="check-label"
                        ><input
                            v-model="groupDraft.active"
                            type="checkbox"
                        />Grup opsi aktif</label
                    >
                    <div class="section-heading">
                        <h3>Daftar opsi</h3>
                        <BaseButton
                            variant="ghost"
                            @click="
                                groupDraft.options.push({
                                    name: '',
                                    price: 0,
                                    active: true,
                                })
                            "
                            ><AppIcon
                                name="plus"
                                :size="16"
                            />Tambah</BaseButton
                        >
                    </div>
                    <div
                        v-for="(option, index) in groupDraft.options"
                        :key="index"
                        class="option-editor-row"
                    >
                        <label class="field"
                            >Nama opsi<input
                                v-model="option.name"
                                required
                                placeholder="Contoh: Low" /></label
                        ><label class="field"
                            >Tambahan (IDR)<input
                                v-model.number="option.price"
                                required
                                type="number"
                                min="0"
                                step="1" /></label
                        ><label class="check-label"
                            ><input
                                v-model="option.active"
                                type="checkbox"
                            />Aktif</label
                        ><button
                            type="button"
                            class="icon-button danger-text"
                            :disabled="
                                processing || groupDraft.options.length === 1
                            "
                            :aria-label="`Hapus opsi ${index + 1}`"
                            @click="groupDraft.options.splice(index, 1)"
                        >
                            <AppIcon name="trash" :size="17" />
                        </button></div
                ></template>
                <p v-if="smallError" class="field-error" role="alert">
                    {{ smallError }}
                </p>
            </form>
            <AppDialog
                :open="discardSmallEditorOpen"
                title="Buang perubahan?"
                @close="discardSmallEditorOpen = false"
            >
                <div class="small-editor">
                    <p>
                        Perubahan
                        {{
                            smallEditor === 'category'
                                ? 'kategori'
                                : 'grup opsi'
                        }}
                        belum disimpan. Jika kamu keluar, perubahan akan
                        dibuang.
                    </p>
                </div>
                <template #footer>
                    <BaseButton @click="discardSmallEditorOpen = false">
                        Lanjut edit
                    </BaseButton>
                    <BaseButton
                        variant="danger"
                        :disabled="processing"
                        @click="discardSmallEditorChanges"
                    >
                        Buang perubahan
                    </BaseButton>
                </template>
            </AppDialog>
            <template #footer
                ><BaseButton @click="closeSmallEditor">Batal</BaseButton
                ><BaseButton
                    type="submit"
                    form="small-editor"
                    variant="primary"
                    :disabled="processing"
                    >{{ processing ? 'Menyimpan…' : 'Simpan' }}</BaseButton
                ></template
            ></AppDialog
        >
        <AppDialog
            :open="confirmProduct !== null"
            :title="
                confirmProduct?.active
                    ? 'Nonaktifkan produk?'
                    : 'Aktifkan produk?'
            "
            @close="!processing && (confirmProduct = null)"
            ><div class="confirmation-content">
                <span class="confirmation-icon"
                    ><AppIcon name="coffee" :size="28"
                /></span>
                <p v-if="confirmProduct?.active">
                    <strong>{{ confirmProduct?.name }}</strong> akan
                    dinonaktifkan pada katalog. produk nonaktif tidak ditawarkan
                    untuk pesanan baru; riwayat tetap tersimpan.
                </p>
                <p v-else>
                    <strong>{{ confirmProduct?.name }}</strong> akan diaktifkan
                    pada katalog.
                </p>
                <p class="field-help">
                    Perubahan berlaku untuk pesanan baru. Riwayat tetap
                    tersimpan.
                </p>
            </div>
            <template #footer
                ><BaseButton
                    :disabled="processing"
                    @click="confirmProduct = null"
                    >Batal</BaseButton
                ><BaseButton
                    :variant="confirmProduct?.active ? 'danger' : 'primary'"
                    :disabled="processing"
                    @click="toggleProduct"
                    >{{
                        confirmProduct?.active ? 'Nonaktifkan' : 'Aktifkan'
                    }}</BaseButton
                ></template
            ></AppDialog
        >
    </div>
</template>

<style scoped>
.catalog-heading-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
@media (max-width: 767px) {
    .catalog-heading-actions {
        width: 100%;
    }
    .catalog-heading-actions > .button {
        flex: 1 1 auto;
        min-height: 44px;
    }
}
</style>
