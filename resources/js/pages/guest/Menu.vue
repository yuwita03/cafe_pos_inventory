<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import CustomerMenuCart from './CustomerMenuCart.vue';
import CustomerProductPage from './CustomerProductPage.vue';
import { menu } from '@/routes/guest';
import {
    cart as cartRoute,
    product as productRoute,
} from '@/routes/guest/menu';
import { formatRupiah as rupiah } from '@/lib/utils';
import { useHorizontalDrag } from '@/lib/useHorizontalDrag';
import type { CartLine, PosGroup, PosProduct } from '@/types/pos';
import '../../../css/admin.css';
import '../../../css/pos.css';
import '../../../css/customer-menu.css';

const props = defineProps<{
    screen: 'menu' | 'cart' | 'product';
    productId: number | null;
    cafeName: string;
    table: { id: number; label: string };
    catalog: {
        categories: { id: number; name: string }[];
        groups: PosGroup[];
        products: PosProduct[];
    };
}>();

const activeCategoryId = ref(0);
const searchQuery = ref('');
const failedImageIds = ref<number[]>([]);
const page = usePage();
const categoryNav = ref<HTMLElement | null>(null);
const {
    isDragging: isDraggingCategories,
    start: startCategoryDrag,
    move: moveCategoryDrag,
    stop: stopCategoryDrag,
    preventClick: preventDraggedCategoryClick,
} = useHorizontalDrag();
const categoryButtons = new Map<number, HTMLButtonElement>();
const cart = ref<CartLine[]>([]);
const menuUrl = computed(() => menu.url({ query: { table: props.table.id } }));
const cartUrl = computed(() =>
    cartRoute.url({ query: { table: props.table.id } }),
);
const pageTitle = computed(() =>
    props.screen === 'cart'
        ? 'Keranjang'
        : props.screen === 'product'
          ? 'Pilih menu'
          : 'Menu',
);
const returnToCart = computed(
    () =>
        new URL(page.url, 'https://local.invalid').searchParams.get('from') ===
        'cart',
);
const selectedProduct = computed(
    () =>
        products.value.find((product) => product.id === props.productId) ??
        null,
);
const editingLine = computed(() => {
    const key = new URL(page.url, 'https://local.invalid').searchParams.get(
        'edit',
    );
    return (
        cart.value.find(
            (line) => line.key === key && line.productId === props.productId,
        ) ?? null
    );
});
const total = computed(() =>
    cart.value.reduce((sum, line) => sum + line.unitPrice * line.quantity, 0),
);
const itemCount = computed(() =>
    cart.value.reduce((sum, line) => sum + line.quantity, 0),
);
const categories = computed(() => props.catalog.categories);
const products = computed(() => props.catalog.products);
const categorySections = computed(() => {
    return categories.value
        .map((category) => ({
            ...category,
            products: products.value.filter(
                (product) => product.categoryId === category.id,
            ),
        }))
        .filter((category) => category.products.length > 0);
});
const normalizedSearch = computed(() =>
    searchQuery.value.trim().toLocaleLowerCase('id'),
);
const visibleCategorySections = computed(() =>
    categorySections.value
        .map((category) => ({
            ...category,
            products: category.products.filter((product) =>
                `${product.name} ${product.description}`
                    .toLocaleLowerCase('id')
                    .includes(normalizedSearch.value),
            ),
        }))
        .filter((category) => category.products.length > 0),
);
const searchResultCount = computed(() =>
    visibleCategorySections.value.reduce(
        (count, category) => count + category.products.length,
        0,
    ),
);
let categoryScrollFrame: number | null = null;
const scrollBehavior = (): ScrollBehavior =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ? 'auto'
        : 'smooth';

function updateActiveCategoryOnScroll(): void {
    if (normalizedSearch.value) {
        return;
    }
    if (categoryScrollFrame !== null) {
        cancelAnimationFrame(categoryScrollFrame);
    }
    categoryScrollFrame = requestAnimationFrame(() => {
        categoryScrollFrame = null;
        const activationLine = window.innerHeight * 0.6;
        const activeSection = Array.from(
            document.querySelectorAll<HTMLElement>(
                '.customer-category-section',
            ),
        ).reduce<HTMLElement | null>((current, section) => {
            return section.getBoundingClientRect().top <= activationLine
                ? section
                : current;
        }, null);
        activeCategoryId.value = Number(
            activeSection?.getAttribute('data-category-id') ?? 0,
        );
    });
}

function setCategoryButton(categoryId: number, element: unknown): void {
    if (element instanceof HTMLButtonElement) {
        categoryButtons.set(categoryId, element);
    } else {
        categoryButtons.delete(categoryId);
    }
}

function keepActiveCategoryVisible(): void {
    const nav = categoryNav.value;
    const button = categoryButtons.get(activeCategoryId.value);
    if (!nav || !button) {
        return;
    }
    const navBounds = nav.getBoundingClientRect();
    const buttonBounds = button.getBoundingClientRect();
    const left = buttonBounds.left - navBounds.left + nav.scrollLeft;
    const right = left + buttonBounds.width;
    if (left < nav.scrollLeft) {
        nav.scrollTo({
            left: Math.max(0, left - 12),
            behavior: scrollBehavior(),
        });
    } else if (right > nav.scrollLeft + nav.clientWidth) {
        nav.scrollTo({
            left: right - nav.clientWidth + 12,
            behavior: scrollBehavior(),
        });
    }
}

function scrollToCategory(categoryId: number): void {
    document
        .getElementById(`customer-category-${categoryId}`)
        ?.scrollIntoView({ behavior: scrollBehavior(), block: 'start' });
    activeCategoryId.value = categoryId;
}

onMounted(() => {
    restoreCart();
    window.addEventListener('scroll', updateActiveCategoryOnScroll, {
        passive: true,
    });
    updateActiveCategoryOnScroll();
});
function restoreCart(): void {
    try {
        const saved: unknown = JSON.parse(
            sessionStorage.getItem(`customer-cart:${props.table.id}`) ?? '[]',
        );
        cart.value =
            Array.isArray(saved) &&
            saved.every(
                (line) =>
                    line &&
                    typeof line.key === 'string' &&
                    typeof line.productName === 'string' &&
                    typeof line.variantName === 'string' &&
                    Array.isArray(line.optionNames) &&
                    line.optionNames.every(
                        (name: unknown) => typeof name === 'string',
                    ) &&
                    Array.isArray(line.optionIds) &&
                    line.optionIds.every(Number.isSafeInteger) &&
                    Number.isSafeInteger(line.productId) &&
                    Number.isSafeInteger(line.variantId) &&
                    Number.isSafeInteger(line.unitPrice) &&
                    line.unitPrice >= 0 &&
                    Number.isSafeInteger(line.quantity) &&
                    line.quantity > 0,
            )
                ? saved
                : [];
    } catch {
        cart.value = [];
    }
}
watch(() => props.table.id, restoreCart);
watch(
    cart,
    (lines) => {
        try {
            sessionStorage.setItem(
                `customer-cart:${props.table.id}`,
                JSON.stringify(lines),
            );
        } catch {
            // Keep the in-memory cart usable when browser storage is unavailable.
        }
    },
    { deep: true },
);
watch(activeCategoryId, async () => {
    await nextTick();
    keepActiveCategoryVisible();
});
watch(searchQuery, async () => {
    await nextTick();
    updateActiveCategoryOnScroll();
});
watch(categorySections, async () => {
    await nextTick();
    if (
        activeCategoryId.value !== 0 &&
        !categorySections.value.some(
            (category) => category.id === activeCategoryId.value,
        )
    ) {
        activeCategoryId.value = categorySections.value[0]?.id ?? 0;
    }
    updateActiveCategoryOnScroll();
});
onBeforeUnmount(() => {
    window.removeEventListener('scroll', updateActiveCategoryOnScroll);
    if (categoryScrollFrame !== null) {
        cancelAnimationFrame(categoryScrollFrame);
    }
});

function isAvailable(product: PosProduct): boolean {
    return product.variants.some((variant) => variant.available);
}

function markImageUnavailable(productId: number): void {
    if (!failedImageIds.value.includes(productId)) {
        failedImageIds.value.push(productId);
    }
}

function startingPrice(product: PosProduct): number {
    return Math.min(
        ...product.variants
            .filter((variant) => variant.available || !isAvailable(product))
            .map((variant) => variant.price),
    );
}

function openProduct(product: PosProduct, line: CartLine | null = null): void {
    if (!isAvailable(product)) {
        return;
    }
    router.get(
        productRoute.url(product.id, {
            query: {
                table: props.table.id,
                edit: line?.key,
                from: props.screen === 'cart' ? 'cart' : undefined,
            },
        }),
        {},
        { preserveState: true },
    );
}

function saveLine(line: CartLine): void {
    const previousLine = editingLine.value;
    if (previousLine) {
        cart.value = cart.value.filter((item) => item.key !== previousLine.key);
    }
    const existing = cart.value.find((item) => item.key === line.key);
    if (existing) {
        existing.quantity += line.quantity;
    } else {
        cart.value.push(line);
    }
    router.get(
        previousLine || returnToCart.value ? cartUrl.value : menuUrl.value,
        {},
        { preserveState: true },
    );
}

function changeQuantity(key: string, quantity: number): void {
    const line = cart.value.find((item) => item.key === key);
    if (line && quantity > 0 && Number.isSafeInteger(quantity)) {
        line.quantity = quantity;
    }
}

function editCartLine(line: CartLine): void {
    const product = products.value.find((item) => item.id === line.productId);
    if (product) {
        openProduct(product, line);
    }
}

function removeCartLine(key: string): void {
    cart.value = cart.value.filter((line) => line.key !== key);
}
</script>

<template>
    <main
        class="customer-menu"
        :class="{
            'customer-order-screen': screen === 'cart',
            'customer-catalog-screen': screen === 'menu',
        }"
    >
        <Head :title="pageTitle" />
        <header v-if="screen === 'menu'" class="customer-header">
            <div class="customer-brand">
                <strong>{{ cafeName || 'Café' }}</strong>
            </div>
            <span class="customer-table"
                ><AppIcon name="grid" :size="16" />Meja {{ table.label }}</span
            >
        </header>

        <section v-if="screen === 'menu'" class="customer-intro">
            <h1>Pilih menu</h1>
            <p>Minuman dan camilan untuk meja kamu.</p>
        </section>

        <div
            v-if="screen === 'menu' && categorySections.length"
            class="customer-discovery"
        >
            <div class="customer-search-wrap">
                <label class="customer-search" for="customer-menu-search">
                    <AppIcon name="search" :size="20" />
                    <input
                        id="customer-menu-search"
                        v-model="searchQuery"
                        type="search"
                        aria-label="Cari menu"
                        placeholder="Cari menu, misalnya latte"
                        autocomplete="off"
                    />
                </label>
                <button
                    v-if="searchQuery"
                    type="button"
                    class="customer-search-clear"
                    aria-label="Hapus pencarian"
                    @click="searchQuery = ''"
                >
                    <AppIcon name="close" :size="18" />
                </button>
            </div>
            <nav
                v-if="!normalizedSearch"
                ref="categoryNav"
                class="customer-categories"
                :class="{ 'is-dragging': isDraggingCategories }"
                aria-label="Kategori menu"
                tabindex="0"
                @pointerdown="startCategoryDrag"
                @pointermove="moveCategoryDrag"
                @pointerup="stopCategoryDrag"
                @pointercancel="stopCategoryDrag"
                @lostpointercapture="stopCategoryDrag"
                @pointerleave="!isDraggingCategories && stopCategoryDrag()"
                @click.capture="preventDraggedCategoryClick"
                @dragstart.prevent
            >
                <button
                    v-for="category in categorySections"
                    :key="category.id"
                    :ref="(element) => setCategoryButton(category.id, element)"
                    type="button"
                    :aria-pressed="activeCategoryId === category.id"
                    @click="scrollToCategory(category.id)"
                >
                    {{ category.name }}
                </button>
            </nav>
        </div>

        <div
            v-if="screen === 'menu' && categorySections.length"
            class="customer-catalog"
        >
            <p
                v-if="normalizedSearch"
                class="customer-search-summary"
                aria-live="polite"
            >
                {{ searchResultCount }} menu ditemukan
            </p>
            <section
                v-for="category in visibleCategorySections"
                :id="`customer-category-${category.id}`"
                :key="category.id"
                class="customer-category-section"
                :data-category-id="category.id"
                :aria-label="category.name"
            >
                <div class="customer-section-heading">
                    <h2>{{ category.name }}</h2>
                    <span>{{ category.products.length }} menu</span>
                </div>
                <div class="customer-product-grid">
                    <button
                        v-for="product in category.products"
                        :key="product.id"
                        class="customer-product"
                        type="button"
                        :disabled="!isAvailable(product)"
                        @click="openProduct(product)"
                    >
                        <div class="customer-product-image">
                            <img
                                v-if="
                                    product.image &&
                                    !failedImageIds.includes(product.id)
                                "
                                :src="product.image"
                                alt=""
                                loading="lazy"
                                @error="markImageUnavailable(product.id)"
                            /><span v-else class="customer-image-placeholder"
                                ><AppIcon name="image" :size="25" /></span
                            ><span
                                v-if="!isAvailable(product)"
                                class="customer-unavailable"
                                >Tidak tersedia</span
                            >
                        </div>
                        <span class="customer-product-info">
                            <span class="customer-product-name">{{
                                product.name
                            }}</span>
                            <span
                                v-if="product.description"
                                class="customer-product-description"
                                >{{ product.description }}</span
                            >
                            <span class="customer-product-bottom">
                                <span class="customer-product-price">
                                    <small
                                        v-if="
                                            new Set(
                                                product.variants.map(
                                                    (variant) => variant.price,
                                                ),
                                            ).size > 1
                                        "
                                        >Mulai </small
                                    >{{ rupiah(startingPrice(product)) }}
                                </span>
                                <span
                                    v-if="isAvailable(product)"
                                    class="customer-product-action"
                                    >Pilih</span
                                >
                                <span v-else class="customer-product-sold-out"
                                    >Habis</span
                                >
                            </span>
                        </span>
                    </button>
                </div>
            </section>
            <div
                v-if="normalizedSearch && !searchResultCount"
                class="customer-empty customer-search-empty"
            >
                <h2>Menu tidak ditemukan</h2>
                <p>Coba nama lain atau lihat kategori menu.</p>
                <button type="button" @click="searchQuery = ''">
                    Lihat semua menu
                </button>
            </div>
        </div>
        <div v-else-if="screen === 'menu'" class="customer-empty">
            <span class="customer-empty-icon"
                ><AppIcon name="purchase" :size="25"
            /></span>
            <h3>Menu belum tersedia</h3>
            <p>Belum ada menu yang tersedia saat ini.</p>
        </div>

        <section
            v-if="screen !== 'menu'"
            class="customer-page"
            :class="{
                'customer-order-page': screen === 'cart',
                'customer-detail-page': screen === 'product',
            }"
        >
            <CustomerMenuCart
                v-if="screen === 'cart'"
                :lines="cart"
                :menu-url="menuUrl"
                :table-id="table.id"
                :table-label="table.label"
                @quantity="changeQuantity"
                @edit="editCartLine"
                @remove="removeCartLine"
            />
            <CustomerProductPage
                v-else
                :key="`${productId}:${editingLine?.key ?? 'new'}`"
                :groups="catalog.groups"
                :product="selectedProduct"
                :line="editingLine"
                @close="
                    router.get(
                        editingLine || returnToCart ? cartUrl : menuUrl,
                        {},
                        { preserveState: true },
                    )
                "
                @save="saveLine"
            />
        </section>
        <Link
            v-if="screen === 'menu' && cart.length"
            :href="cartUrl"
            preserve-state
            class="customer-cart-bar"
        >
            <span
                ><AppIcon name="orders" :size="20" /> Keranjang ·
                {{ itemCount }} item</span
            >
            <strong
                >{{ rupiah(total) }} <AppIcon name="arrow" :size="18"
            /></strong>
        </Link>
    </main>
</template>
