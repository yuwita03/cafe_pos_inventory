<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import type { PendingVisit } from '@inertiajs/core';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
} from 'vue';
import '../../../../css/pos.css';
import AppIcon from '@/components/AppIcon.vue';
import PosCart from '@/components/admin/PosCart.vue';
import PosProductDialog from '@/components/admin/PosProductDialog.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatRupiah as rupiah } from '@/lib/utils';
import { orders } from '@/routes/admin';
import { checkout as cashCheckout } from '@/routes/admin/pos';
import type { OrderData } from '@/types/orders';
import type { Category } from '@/types/menu';
import type { CartLine, PosProduct, PosGroup } from './fixtures';

defineOptions({ layout: AdminLayout });
const props = defineProps<{
    catalog: {
        products: PosProduct[];
        categories: Category[];
        groups: PosGroup[];
    };
    tables: { id: number; label: string }[];
}>();
const products = computed(() => props.catalog.products);
const categories = computed(() => props.catalog.categories);
const query = ref('');
const categoryId = ref(0);
const cart = ref<CartLine[]>([]);
const customerName = ref('');
const fulfillment = ref<'dine_in' | 'takeaway'>('dine_in');
const tableId = ref('');
const errors = ref<{ name?: string; table?: string }>({});
const notice = ref('');
const mobileCartOpen = ref(false);
const desktopCart = ref<InstanceType<typeof PosCart>>();
const mobileCart = ref<InstanceType<typeof PosCart>>();
const selectedProduct = ref<PosProduct | null>(null);
const editingLine = ref<CartLine | null>(null);
const productOpen = ref(false);
const clearOpen = ref(false);
const leavingVisit = shallowRef<PendingVisit | null>(null);
const paymentOpen = ref(false);
const cashReceived = ref<number | string>('');
const processing = ref(false);
const paymentError = ref('');
const receipt = ref<OrderData | null>(null);
const receiptOpen = ref(false);
type CheckoutPayload = {
    checkout_key: string;
    customer_name: string;
    fulfillment_type: 'dine_in' | 'takeaway';
    table_id: number | null;
    expected_total_idr: number;
    cash_received_idr: number;
    items: { variant_id: number; option_ids: number[]; quantity: number }[];
};
const pendingCheckout = ref<CheckoutPayload | null>(null);
const page = usePage();
const recoveryKey = `cash-checkout:${page.props.auth.user?.id}`;
function clearRecovery(): void {
    try {
        sessionStorage.removeItem(recoveryKey);
    } catch {
        return;
    }
}
let returnToCart = false;
let viewingOrder = false;
let confirmedLeave = false;
let desktopQuery: MediaQueryList | undefined;
let removeNavigationListener: (() => void) | undefined;

const filteredProducts = computed(() =>
    products.value.filter(
        (product) =>
            product.active &&
            (!categoryId.value || product.categoryId === categoryId.value) &&
            `${product.name} ${product.description}`
                .toLocaleLowerCase('id')
                .includes(query.value.trim().toLocaleLowerCase('id')),
    ),
);
const total = computed(() =>
    cart.value.reduce((sum, line) => sum + line.unitPrice * line.quantity, 0),
);
const itemCount = computed(() =>
    cart.value.reduce((sum, line) => sum + line.quantity, 0),
);
const tableLabel = computed(
    () =>
        props.tables.find((table) => String(table.id) === tableId.value)
            ?.label ?? '',
);
const dirty = computed(
    () =>
        cart.value.length > 0 ||
        customerName.value.trim().length > 0 ||
        tableId.value !== '' ||
        fulfillment.value !== 'dine_in',
);
const cashValue = computed(() =>
    Number.isFinite(Number(cashReceived.value))
        ? Number(cashReceived.value)
        : 0,
);
const cashValid = computed(
    () =>
        Number.isSafeInteger(cashValue.value) && cashValue.value >= total.value,
);
const quickAmounts = computed(() =>
    [...new Set([total.value, 50000, 100000])]
        .filter((value) => value >= total.value)
        .sort((a, b) => a - b),
);

function available(product: PosProduct): boolean {
    return product.variants.some((variant) => variant.available);
}
function startingPrice(product: PosProduct): number {
    return Math.min(
        ...product.variants
            .filter((variant) => variant.available || !available(product))
            .map((variant) => variant.price),
    );
}
async function openProduct(
    product: PosProduct,
    line: CartLine | null = null,
): Promise<void> {
    if (!available(product)) {
        return;
    }
    editingLine.value = line;
    returnToCart = mobileCartOpen.value;
    mobileCartOpen.value = false;
    if (
        !line &&
        product.variants.length === 1 &&
        product.variants[0]?.name === 'Regular' &&
        !product.groups.length
    ) {
        const variant = product.variants[0];
        saveLine({
            key: `${product.id}:${variant.id}`,
            productId: product.id,
            variantId: variant.id,
            optionIds: [],
            productName: product.name,
            variantName: variant.name,
            optionNames: [],
            unitPrice: variant.price,
            quantity: 1,
        });
        return;
    }
    selectedProduct.value = product;
    await nextTick();
    productOpen.value = true;
}
async function closeProduct(): Promise<void> {
    productOpen.value = false;
    await nextTick();
    if (returnToCart && !desktopQuery?.matches) {
        mobileCartOpen.value = true;
    }
    returnToCart = false;
}
function saveLine(line: CartLine): void {
    const remaining = cart.value.filter(
        (item) => item.key !== editingLine.value?.key,
    );
    const existing = remaining.find((item) => item.key === line.key);
    if (existing) {
        existing.quantity += line.quantity;
    } else {
        remaining.push({ ...line });
    }
    cart.value = remaining;
    notice.value = `${line.productName} ${editingLine.value ? 'diperbarui' : 'ditambahkan ke pesanan'}.`;
    void closeProduct();
}
function changeQuantity(key: string, quantity: number): void {
    const line = cart.value.find((item) => item.key === key);
    if (
        line &&
        Number.isSafeInteger(quantity) &&
        quantity >= 1 &&
        quantity <= 10000 &&
        Number.isSafeInteger(line.unitPrice * quantity)
    ) {
        line.quantity = quantity;
    }
}
function removeLine(key: string): void {
    cart.value = cart.value.filter((line) => line.key !== key);
    notice.value = 'Item dihapus dari draf pesanan.';
}
function setFulfillment(value: 'dine_in' | 'takeaway'): void {
    fulfillment.value = value;
    if (value === 'takeaway') {
        tableId.value = '';
    }
    errors.value.table = undefined;
}
function editLine(line: CartLine): void {
    const product = products.value.find((item) => item.id === line.productId);
    if (product) {
        void openProduct(product, line);
    }
}
async function checkout(): Promise<void> {
    errors.value = {};
    if (!cart.value.length) {
        return;
    }
    if (!customerName.value.trim()) {
        errors.value.name = 'Masukkan nama pelanggan terlebih dahulu.';
    }
    if (fulfillment.value === 'dine_in' && !tableLabel.value) {
        errors.value.table = 'Pilih nomor meja untuk pesanan dine-in.';
    }
    if (errors.value.name || errors.value.table) {
        await nextTick();
        (mobileCartOpen.value
            ? mobileCart.value
            : desktopCart.value
        )?.focusError();
        return;
    }
    returnToCart = mobileCartOpen.value;
    mobileCartOpen.value = false;
    cashReceived.value = '';
    paymentError.value = '';
    await nextTick();
    paymentOpen.value = true;
}
async function closePayment(): Promise<void> {
    if (processing.value || pendingCheckout.value) {
        return;
    }
    paymentOpen.value = false;
    await nextTick();
    if (returnToCart && !desktopQuery?.matches) {
        mobileCartOpen.value = true;
    }
    returnToCart = false;
}
async function confirmCash(): Promise<void> {
    if (processing.value || (!pendingCheckout.value && !cashValid.value)) {
        return;
    }
    pendingCheckout.value ??= {
        checkout_key: crypto.randomUUID(),
        customer_name: customerName.value.trim(),
        fulfillment_type: fulfillment.value,
        table_id:
            fulfillment.value === 'dine_in' ? Number(tableId.value) : null,
        expected_total_idr: total.value,
        cash_received_idr: cashValue.value,
        items: cart.value.map((line) => ({
            variant_id: line.variantId,
            option_ids: [...line.optionIds],
            quantity: line.quantity,
        })),
    };
    try {
        sessionStorage.setItem(
            recoveryKey,
            JSON.stringify({
                payload: pendingCheckout.value,
                lines: cart.value,
            }),
        );
    } catch {
        pendingCheckout.value = null;
        paymentError.value =
            'Browser tidak dapat menyimpan pemulihan checkout. Aktifkan penyimpanan sesi sebelum mengonfirmasi tunai.';
        return;
    }
    processing.value = true;
    paymentError.value = '';
    try {
        const token =
            document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.slice('XSRF-TOKEN='.length) ?? '';
        const response = await fetch(cashCheckout.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(token),
            },
            body: JSON.stringify(pendingCheckout.value),
        });
        if (response.status === 422) {
            const data = (await response.json()) as {
                errors?: Record<string, string[]>;
            };
            pendingCheckout.value = null;
            clearRecovery();
            paymentError.value =
                Object.values(data.errors ?? {})
                    .flat()
                    .join(' ') || 'Periksa pesanan dan coba lagi.';
            return;
        }
        if (
            response.status === 401 ||
            response.status === 419 ||
            response.status === 403
        ) {
            paymentError.value =
                'Sesi atau izin kasir tidak berlaku. Masuk kembali sebelum melanjutkan.';
            return;
        }
        if (!response.ok) {
            throw new Error('Checkout belum dapat dipastikan.');
        }
        const data = (await response.json()) as { order: OrderData };
        receipt.value = data.order;
        pendingCheckout.value = null;
        clearRecovery();
        paymentOpen.value = false;
        returnToCart = false;
        cart.value = [];
        customerName.value = '';
        tableId.value = '';
        fulfillment.value = 'dine_in';
        await nextTick();
        receiptOpen.value = true;
    } catch {
        paymentError.value =
            'Hasil pembayaran belum dapat dipastikan. Coba ulang dengan data yang sama, atau periksa Pesanan sebelum membuat transaksi baru.';
    } finally {
        processing.value = false;
    }
}
function viewOrder(): void {
    viewingOrder = true;
    paymentOpen.value = false;
    receiptOpen.value = false;
    router.visit(
        orders(
            receipt.value
                ? { query: { order: receipt.value.number } }
                : undefined,
        ),
        {
            onFinish: () => {
                viewingOrder = false;
            },
        },
    );
}
function resetDraft(): void {
    cart.value = [];
    customerName.value = '';
    fulfillment.value = 'dine_in';
    tableId.value = '';
    errors.value = {};
    clearOpen.value = false;
    receiptOpen.value = false;
    receipt.value = null;
    pendingCheckout.value = null;
    notice.value = 'Draf baru siap dibuat.';
}
function confirmLeaving(event: BeforeUnloadEvent): void {
    if (dirty.value || processing.value) {
        event.preventDefault();
        event.returnValue = '';
    }
}
function leavePos(): void {
    const visit = leavingVisit.value;
    leavingVisit.value = null;
    if (!visit || processing.value) {
        return;
    }
    confirmedLeave = true;
    try {
        router.visit(visit.url, visit);
    } finally {
        confirmedLeave = false;
    }
}
function closeCartOnDesktop(event: MediaQueryListEvent): void {
    if (event.matches) {
        mobileCartOpen.value = false;
    }
}
onMounted(() => {
    try {
        const cached = sessionStorage.getItem(recoveryKey);
        if (cached) {
            const recovery = JSON.parse(cached) as {
                payload: CheckoutPayload;
                lines: CartLine[];
            };
            if (
                typeof recovery.payload?.checkout_key === 'string' &&
                Array.isArray(recovery.lines)
            ) {
                pendingCheckout.value = recovery.payload;
                cart.value = recovery.lines;
                customerName.value = recovery.payload.customer_name;
                fulfillment.value = recovery.payload.fulfillment_type;
                tableId.value =
                    recovery.payload.table_id === null
                        ? ''
                        : String(recovery.payload.table_id);
                cashReceived.value = recovery.payload.cash_received_idr;
                paymentOpen.value = true;
                paymentError.value =
                    'Ada checkout yang belum dipastikan. Coba ulang untuk mengambil hasil pesanan yang sama sebelum membuat transaksi baru.';
            }
        }
    } catch {
        paymentError.value =
            'Data pemulihan checkout tidak dapat dibaca. Periksa Pesanan sebelum membuat transaksi baru.';
        paymentOpen.value = true;
    }
    removeNavigationListener = router.on('before', (event) => {
        if (
            event.detail.visit.prefetch ||
            viewingOrder ||
            confirmedLeave ||
            (!dirty.value && !processing.value)
        ) {
            return;
        }
        event.preventDefault();
        if (processing.value) {
            notice.value =
                'Tunggu sampai hasil checkout diterima sebelum meninggalkan POS.';
            return;
        }
        leavingVisit.value = event.detail.visit;
    });
    window.addEventListener('beforeunload', confirmLeaving);
    desktopQuery = window.matchMedia('(min-width: 900px)');
    desktopQuery.addEventListener('change', closeCartOnDesktop);
});
onBeforeUnmount(() => {
    removeNavigationListener?.();
    window.removeEventListener('beforeunload', confirmLeaving);
    desktopQuery?.removeEventListener('change', closeCartOnDesktop);
});
</script>

<template>
    <div class="pos-page">
        <Head title="Kasir POS" />
        <div class="page-heading pos-page-heading">
            <div>
                <span class="eyebrow">OPERASIONAL</span>
                <h1>Kasir POS<span class="title-dot">.</span></h1>
                <p>Pilih menu, atur pesanan, lalu lanjutkan ke pembayaran.</p>
            </div>
            <span class="pos-prototype-badge"><span />Pembayaran tunai</span>
        </div>
        <div class="pos-prototype-banner">
            <AppIcon name="info" :size="19" />
            <p>
                <strong>Kasir tunai</strong> Pembayaran tersimpan di database.
                Stok direservasi saat checkout dan dipotong sekali ketika
                Kitchen mulai membuat.
            </p>
        </div>
        <div class="pos-workspace">
            <section class="pos-catalog" aria-labelledby="catalog-title">
                <div class="pos-catalog-heading">
                    <div>
                        <h2 id="catalog-title">Pilih menu</h2>
                        <p>{{ filteredProducts.length }} menu tersedia</p>
                    </div>
                    <label class="pos-search"
                        ><span class="sr-only">Cari menu</span
                        ><AppIcon name="search" :size="18" /><input
                            v-model="query"
                            type="search"
                            placeholder="Cari nama menu…"
                    /></label>
                </div>
                <div
                    class="pos-categories"
                    role="group"
                    aria-label="Filter kategori"
                >
                    <button
                        type="button"
                        :aria-pressed="categoryId === 0"
                        @click="categoryId = 0"
                    >
                        Semua menu<span>{{ products.length }}</span></button
                    ><button
                        v-for="category in categories"
                        :key="category.id"
                        type="button"
                        :aria-pressed="categoryId === category.id"
                        @click="categoryId = category.id"
                    >
                        {{ category.name
                        }}<span>{{
                            products.filter(
                                (product) => product.categoryId === category.id,
                            ).length
                        }}</span>
                    </button>
                </div>
                <p class="pos-notice" role="status" aria-live="polite">
                    {{ notice || 'Pilih menu untuk ditambahkan ke pesanan.' }}
                </p>
                <div v-if="filteredProducts.length" class="pos-product-grid">
                    <button
                        v-for="product in filteredProducts"
                        :key="product.id"
                        type="button"
                        class="pos-product"
                        :disabled="!available(product)"
                        @click="openProduct(product)"
                    >
                        <div
                            class="pos-product-image"
                            :class="'pos-image-category-' + product.categoryId"
                        >
                            <img
                                v-if="product.image"
                                :src="product.image"
                                :alt="product.name"
                            /><template v-else
                                ><AppIcon
                                    :name="
                                        product.categoryId === 1
                                            ? 'coffee'
                                            : product.categoryId === 2
                                              ? 'orders'
                                              : 'coffee'
                                    "
                                    :size="42"
                                /><span>Foto belum tersedia</span></template
                            ><span
                                v-if="!available(product)"
                                class="pos-sold-out"
                                >Tidak tersedia</span
                            >
                        </div>
                        <div class="pos-product-details">
                            <span class="pos-product-category">{{
                                categories.find(
                                    (category) =>
                                        category.id === product.categoryId,
                                )?.name
                            }}</span>
                            <h3>{{ product.name }}</h3>
                            <p>{{ product.description }}</p>
                            <div class="pos-product-price">
                                <span
                                    ><small
                                        v-if="
                                            new Set(
                                                product.variants.map(
                                                    (variant) => variant.price,
                                                ),
                                            ).size > 1
                                        "
                                        >Mulai </small
                                    >{{ rupiah(startingPrice(product)) }}</span
                                ><span
                                    v-if="available(product)"
                                    class="pos-add-icon"
                                    ><AppIcon name="plus" :size="17"
                                /></span>
                            </div>
                        </div>
                    </button>
                </div>
                <div v-else class="pos-search-empty">
                    <AppIcon name="search" :size="30" />
                    <h3>Menu tidak ditemukan</h3>
                    <p>Coba kata kunci atau kategori lain.</p>
                    <BaseButton
                        @click="
                            query = '';
                            categoryId = 0;
                        "
                        >Reset pencarian</BaseButton
                    >
                </div>
            </section>
            <aside class="pos-desktop-cart" aria-label="Pesanan pelanggan">
                <PosCart
                    ref="desktopCart"
                    :tables="props.tables"
                    :lines="cart"
                    :total="total"
                    :customer-name="customerName"
                    :fulfillment="fulfillment"
                    :table-id="tableId"
                    :errors="errors"
                    @update:customer-name="
                        customerName = $event;
                        errors.name = undefined;
                    "
                    @update:fulfillment="setFulfillment"
                    @update:table-id="
                        tableId = $event;
                        errors.table = undefined;
                    "
                    @quantity="changeQuantity"
                    @remove="removeLine"
                    @edit="editLine"
                    @clear="clearOpen = true"
                    @checkout="checkout"
                />
            </aside>
        </div>
        <button
            type="button"
            class="pos-mobile-cart-button"
            @click="mobileCartOpen = true"
        >
            <span
                ><AppIcon name="orders" :size="20" />Lihat pesanan ·
                {{ itemCount }} item</span
            ><strong>{{ rupiah(total) }}</strong>
        </button>

        <AppDialog
            :open="mobileCartOpen"
            title="Pesanan pelanggan"
            class="pos-cart-dialog"
            @close="mobileCartOpen = false"
            ><PosCart
                ref="mobileCart"
                :tables="props.tables"
                :lines="cart"
                :total="total"
                :customer-name="customerName"
                :fulfillment="fulfillment"
                :table-id="tableId"
                :errors="errors"
                @update:customer-name="
                    customerName = $event;
                    errors.name = undefined;
                "
                @update:fulfillment="setFulfillment"
                @update:table-id="
                    tableId = $event;
                    errors.table = undefined;
                "
                @quantity="changeQuantity"
                @remove="removeLine"
                @edit="editLine"
                @clear="clearOpen = true"
                @checkout="checkout"
        /></AppDialog>
        <PosProductDialog
            :open="productOpen"
            :groups="props.catalog.groups"
            :product="selectedProduct"
            :line="editingLine"
            @close="closeProduct"
            @save="saveLine"
        />
        <AppDialog
            :open="clearOpen"
            title="Kosongkan pesanan?"
            @close="clearOpen = false"
            ><div class="pos-modal-content">
                <p>
                    Hapus seluruh item, nama pelanggan, dan pilihan meja dari
                    draf ini?
                </p>
            </div>
            <template #footer
                ><BaseButton @click="clearOpen = false">Kembali</BaseButton
                ><BaseButton variant="danger" @click="resetDraft"
                    >Kosongkan pesanan</BaseButton
                ></template
            ></AppDialog
        >

        <AppDialog
            :open="paymentOpen"
            title="Pembayaran tunai"
            class="pos-payment-dialog"
            @close="closePayment"
        >
            <div class="pos-modal-content">
                <div class="pos-payment-order">
                    <span class="pos-kicker">TOTAL ESTIMASI</span
                    ><strong>{{ rupiah(total) }}</strong>
                    <p>
                        {{ customerName }} ·
                        {{
                            fulfillment === 'dine_in'
                                ? 'Dine-in · Meja ' + tableLabel
                                : 'Takeaway'
                        }}
                    </p>
                </div>
                <p class="pos-demo-note">
                    <AppIcon name="info" :size="16" />Konfirmasi setelah uang
                    tunai diterima. Sistem akan memvalidasi harga dan stok
                    sebelum menyimpan pesanan.
                </p>
                <fieldset class="pos-service pos-payment-method">
                    <legend>Metode pembayaran</legend>
                    <label class="selected"
                        ><input
                            type="radio"
                            name="pos-payment"
                            value="cash"
                            checked
                        />Tunai</label
                    ><label
                        ><input
                            type="radio"
                            name="pos-payment"
                            value="qris"
                            disabled
                        />QRIS · belum tersedia</label
                    >
                </fieldset>
                <label class="field pos-cash-input" for="pos-cash"
                    >Uang diterima<input
                        id="pos-cash"
                        v-model.number="cashReceived"
                        type="number"
                        min="0"
                        step="1"
                        inputmode="numeric"
                        placeholder="Masukkan nominal rupiah"
                        :readonly="processing || Boolean(pendingCheckout)"
                        aria-describedby="pos-cash-help"
                /></label>
                <div class="pos-quick-amounts">
                    <button
                        v-for="amount in quickAmounts"
                        :key="amount"
                        type="button"
                        :disabled="processing || Boolean(pendingCheckout)"
                        @click="cashReceived = amount"
                    >
                        {{ amount === total ? 'Uang pas' : rupiah(amount) }}
                    </button>
                </div>
                <p
                    id="pos-cash-help"
                    class="pos-field-help"
                    :class="{ 'pos-error': cashReceived !== '' && !cashValid }"
                >
                    {{
                        cashReceived !== '' && !cashValid
                            ? 'Nominal harus berupa rupiah bulat dan mencukupi total pesanan.'
                            : 'Harga akhir ditentukan oleh server saat konfirmasi.'
                    }}
                </p>
                <div class="pos-change">
                    <span>Kembalian</span
                    ><strong>{{
                        rupiah(Math.max(0, cashValue - total))
                    }}</strong>
                </div>
                <p v-if="paymentError" class="pos-error" role="alert">
                    {{ paymentError }}
                </p>
            </div>
            <template #footer
                ><BaseButton
                    :disabled="processing || Boolean(pendingCheckout)"
                    @click="closePayment"
                    >Kembali ke pesanan</BaseButton
                ><BaseButton
                    v-if="pendingCheckout && !processing"
                    @click="viewOrder"
                    >Periksa Pesanan</BaseButton
                ><BaseButton
                    variant="primary"
                    :disabled="processing || (!pendingCheckout && !cashValid)"
                    :aria-busy="processing"
                    @click="confirmCash"
                    >{{
                        processing
                            ? 'Menyimpan pembayaran…'
                            : pendingCheckout
                              ? 'Coba ulang checkout'
                              : 'Konfirmasi tunai'
                    }}</BaseButton
                ></template
            >
        </AppDialog>
        <AppDialog
            :open="receiptOpen"
            title="Pembayaran berhasil"
            @close="receiptOpen = false"
        >
            <div v-if="receipt" class="pos-modal-content pos-receipt">
                <span class="pos-receipt-icon"
                    ><AppIcon name="check" :size="28" /></span
                ><span class="pos-kicker"
                    >TUNAI DITERIMA · PESANAN TERSIMPAN</span
                >
                <h3>{{ receipt.number }}</h3>
                <p>Pesanan masuk antrean Kitchen. Stok sudah direservasi.</p>
                <dl>
                    <div>
                        <dt>Pelanggan</dt>
                        <dd>{{ receipt.customerName }}</dd>
                    </div>
                    <div>
                        <dt>Layanan</dt>
                        <dd>
                            {{
                                receipt.fulfillment === 'dine_in'
                                    ? 'Dine-in · Meja ' + receipt.table
                                    : 'Takeaway'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt>Pembayaran</dt>
                        <dd>Tunai · Dibayar</dd>
                    </div>
                </dl>
                <div class="pos-receipt-items">
                    <div v-for="line in receipt.lines" :key="line.key">
                        <span
                            >{{ line.quantity }} × {{ line.productName
                            }}<small>{{
                                [line.variantName, ...line.optionNames].join(
                                    ' · ',
                                )
                            }}</small></span
                        ><strong>{{
                            rupiah(line.unitPrice * line.quantity)
                        }}</strong>
                    </div>
                </div>
                <div class="pos-grand-total">
                    <span>Total dibayar</span
                    ><strong>{{
                        rupiah(
                            receipt.lines.reduce(
                                (sum, line) =>
                                    sum + line.unitPrice * line.quantity,
                                0,
                            ),
                        )
                    }}</strong>
                </div>
                <div class="pos-total-row">
                    <span>Tunai / kembalian</span
                    ><span
                        >{{ rupiah(receipt.received ?? 0) }} /
                        {{ rupiah(receipt.change ?? 0) }}</span
                    >
                </div>
            </div>
            <template #footer
                ><BaseButton @click="viewOrder">Lihat pesanan</BaseButton
                ><BaseButton variant="primary" @click="resetDraft"
                    >Pesanan baru</BaseButton
                ></template
            >
        </AppDialog>
        <AppDialog
            :open="Boolean(leavingVisit)"
            title="Tinggalkan POS?"
            @close="leavingVisit = null"
        >
            <div class="pos-modal-content">
                <p>
                    Draf pesanan belum disimpan. Jika meninggalkan POS, draf ini
                    akan hilang.
                </p>
                <p v-if="pendingCheckout" class="pos-field-help">
                    Hasil checkout belum dipastikan. Data pemulihan tetap
                    disimpan di tab ini; periksa Pesanan sebelum membuat
                    transaksi baru.
                </p>
            </div>
            <template #footer>
                <BaseButton @click="leavingVisit = null"
                    >Tetap di POS</BaseButton
                >
                <BaseButton variant="danger" @click="leavePos"
                    >Tinggalkan POS</BaseButton
                >
            </template>
        </AppDialog>
    </div>
</template>
