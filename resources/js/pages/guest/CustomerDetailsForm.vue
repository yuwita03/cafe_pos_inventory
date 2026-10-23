<script setup lang="ts">
import { nextTick, onMounted, ref, useId, watch } from 'vue';
import { store as guestCheckout } from '@/routes/guest/orders';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import type { CartLine } from '@/types/pos';
import AppIcon from '@/components/AppIcon.vue';
import { formatRupiah as rupiah } from '@/lib/utils';

const props = defineProps<{
    tableId: number;
    tableLabel: string;
    total: number;
    lines: CartLine[];
}>();
const emit = defineEmits<{ back: [] }>();
const customerDetailsHeading = ref<HTMLHeadingElement | null>(null);
const paymentMethod = ref<'qris' | 'cash' | null>(null);
const customerName = ref('');
const customerEmail = ref('');
const confirmingOrder = ref(false);
const processing = ref(false);
const checkoutError = ref('');
const checkoutAlert = ref<HTMLElement | null>(null);
const statusUrl = ref<string | null>(null);
type CheckoutPayload = {
    checkout_key: string;
    payment_method: 'cash' | 'qris';
    table_id: number;
    customer_name: string;
    customer_email: string | null;
    expected_total_idr: number;
    items: { variant_id: number; option_ids: number[]; quantity: number }[];
};
const recoveryKey = `guest-cash-checkout:${props.tableId}`;
const statusRecoveryKey = `${recoveryKey}:status`;
const pendingCheckout = ref<CheckoutPayload | null>(null);
const formId = useId();
let alertTouch: { id: number; x: number; y: number } | null = null;

function dismissCheckoutError(): void {
    checkoutError.value = '';
    alertTouch = null;
    customerDetailsHeading.value?.focus({ preventScroll: true });
}

function startAlertSwipe(event: TouchEvent): void {
    const touch = event.touches.length === 1 ? event.touches[0] : null;
    alertTouch = touch
        ? { id: touch.identifier, x: touch.clientX, y: touch.clientY }
        : null;
}

function endAlertSwipe(event: TouchEvent): void {
    const start = alertTouch;
    alertTouch = null;
    if (!start) {
        return;
    }
    const touch = Array.from(event.changedTouches).find(
        (item) => item.identifier === start.id,
    );
    if (
        touch &&
        touch.clientX - start.x >= 64 &&
        touch.clientX - start.x > Math.abs(touch.clientY - start.y) * 1.5
    ) {
        dismissCheckoutError();
    }
}

watch(checkoutError, async (message) => {
    if (message) {
        confirmingOrder.value = false;
        await nextTick();
        await nextTick();
        checkoutAlert.value?.focus();
    }
});

function continueToPayment(): void {
    if (paymentMethod.value && customerName.value.trim()) {
        confirmingOrder.value = true;
    }
}

async function confirmOrder(): Promise<void> {
    if (processing.value || statusUrl.value) {
        return;
    }
    pendingCheckout.value ??= {
        checkout_key: crypto.randomUUID(),
        payment_method: paymentMethod.value ?? 'cash',
        table_id: props.tableId,
        customer_name: customerName.value.trim(),
        customer_email: customerEmail.value.trim() || null,
        expected_total_idr: props.total,
        items: props.lines.map((line) => ({
            variant_id: line.variantId,
            option_ids: [...line.optionIds],
            quantity: line.quantity,
        })),
    };
    try {
        sessionStorage.setItem(
            recoveryKey,
            JSON.stringify(pendingCheckout.value),
        );
    } catch {
        checkoutError.value =
            'Browser tidak dapat menyimpan pemulihan pesanan. Aktifkan penyimpanan sesi sebelum melanjutkan.';
        pendingCheckout.value = null;
        return;
    }
    processing.value = true;
    checkoutError.value = '';
    try {
        const token =
            document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.slice('XSRF-TOKEN='.length) ?? '';
        const response = await fetch(guestCheckout.url(), {
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
            checkoutError.value =
                Object.values(data.errors ?? {})
                    .flat()
                    .join(' ') || 'Periksa kembali pesanan.';
            if (data.errors?.checkout_key) {
                return;
            }
            pendingCheckout.value = null;
            sessionStorage.removeItem(recoveryKey);
            confirmingOrder.value = false;
            return;
        }
        if (!response.ok) {
            throw new Error('Hasil pesanan belum dapat dipastikan.');
        }
        const data = (await response.json()) as { statusUrl: string };
        sessionStorage.setItem(statusRecoveryKey, data.statusUrl);
        statusUrl.value = data.statusUrl;
        window.location.assign(data.statusUrl);
    } catch {
        checkoutError.value = statusUrl.value
            ? 'Pesanan sudah dibuat. Buka halaman pesanan untuk melihat pembayaran.'
            : 'Hasil pesanan belum dapat dipastikan. Coba ulang dengan data yang sama.';
    } finally {
        processing.value = false;
    }
}

onMounted(() => {
    customerDetailsHeading.value?.focus();
    try {
        statusUrl.value = sessionStorage.getItem(statusRecoveryKey);
        if (statusUrl.value) {
            checkoutError.value =
                'Pesanan sudah dibuat. Buka halaman pesanan untuk melihat pembayaran.';
            return;
        }
        const saved = sessionStorage.getItem(recoveryKey);
        if (saved) {
            const payload = JSON.parse(saved) as CheckoutPayload;
            if (
                payload.table_id === props.tableId &&
                typeof payload.checkout_key === 'string'
            ) {
                pendingCheckout.value = payload;
                customerName.value = payload.customer_name;
                customerEmail.value = payload.customer_email ?? '';
                paymentMethod.value = payload.payment_method ?? 'cash';
                checkoutError.value =
                    'Ada pesanan yang hasilnya belum dipastikan. Coba ulang sebelum membuat pesanan lain.';
                confirmingOrder.value = true;
            }
        }
    } catch {
        checkoutError.value =
            'Data pemulihan pesanan tidak dapat dibaca. Hubungi kasir sebelum mencoba lagi.';
    }
});
</script>

<template>
    <section>
        <AppDialog
            :open="confirmingOrder"
            title="Apakah kamu yakin dengan pesananmu?"
            eyebrow="KONFIRMASI PESANAN"
            class="customer-confirm-dialog"
            @close="confirmingOrder = false"
        >
            <p>
                Pastikan menu, jumlah, dan data pemesan sudah sesuai sebelum
                melanjutkan.
            </p>
            <p class="customer-confirm-note">
                Pesanan akan dibuat dengan status belum dibayar.
                {{
                    paymentMethod === 'qris'
                        ? 'Kode QRIS akan tampil pada halaman pesanan.'
                        : 'Tunjukkan nomor pesanan kepada kasir untuk membayar tunai.'
                }}
            </p>
            <a v-if="statusUrl" :href="statusUrl">Lihat struk pesanan</a>
            <template #footer>
                <BaseButton @click="confirmingOrder = false"
                    >Cek lagi</BaseButton
                >
                <BaseButton
                    variant="primary"
                    :disabled="processing || Boolean(statusUrl)"
                    @click="confirmOrder"
                    >{{
                        processing
                            ? 'Membuat pesanan…'
                            : pendingCheckout
                              ? 'Coba ulang pesanan'
                              : 'Buat pesanan'
                    }}</BaseButton
                >
            </template>
        </AppDialog>
        <div class="customer-checkout-header">
            <header class="customer-order-header">
                <button
                    type="button"
                    class="customer-order-back customer-details-back"
                    aria-label="Kembali ke keranjang"
                    @click="emit('back')"
                >
                    <AppIcon name="back" />
                </button>
                <h1 ref="customerDetailsHeading" tabindex="-1">Data pemesan</h1>
                <span aria-hidden="true"></span>
            </header>
            <Transition name="customer-alert">
                <div
                    v-if="checkoutError"
                    ref="checkoutAlert"
                    class="customer-checkout-alert"
                    role="alert"
                    tabindex="-1"
                    @touchstart.passive="startAlertSwipe"
                    @touchend.passive="endAlertSwipe"
                    @touchcancel="alertTouch = null"
                >
                    <span>{{ checkoutError }}</span>
                    <button
                        type="button"
                        class="customer-alert-close"
                        aria-label="Tutup notifikasi"
                        @click="dismissCheckoutError"
                    >
                        <AppIcon name="close" :size="18" />
                    </button>
                </div>
            </Transition>
        </div>
        <div class="customer-order-content customer-details-content">
            <div class="customer-order-table">
                <AppIcon name="grid" :size="20" />
                <div>
                    <strong>Meja {{ tableLabel }}</strong
                    ><span>Makan di tempat</span>
                </div>
            </div>
            <h2>Siapa yang memesan?</h2>
            <p>Isi data pemesan agar staf dapat mengenali pesananmu.</p>
            <p id="customer-details-preview" class="customer-details-note">
                Data pemesan dikirim saat kamu membuat pesanan.
            </p>
            <form
                :id="formId"
                class="customer-details-form"
                aria-describedby="customer-details-preview"
                @submit.prevent="continueToPayment"
            >
                <label for="customer-name">Nama pemesan (wajib)</label>
                <input
                    id="customer-name"
                    v-model="customerName"
                    name="customer_name"
                    type="text"
                    autocomplete="name"
                    required
                    pattern=".*\S.*"
                    placeholder="Masukkan nama kamu"
                />
                <label for="customer-email">Email (opsional)</label>
                <input
                    id="customer-email"
                    v-model="customerEmail"
                    name="customer_email"
                    type="email"
                    autocomplete="email"
                    placeholder="nama@email.com"
                />
                <fieldset class="customer-payment-methods">
                    <legend>Metode pembayaran</legend>
                    <label>
                        <input
                            type="radio"
                            v-model="paymentMethod"
                            name="payment_method"
                            value="qris"
                        />
                        <span
                            ><strong
                                ><AppIcon name="qr" :size="18" /> QRIS</strong
                            ><small
                                >Bayar dengan aplikasi bank atau dompet digital
                            </small></span
                        >
                    </label>
                    <label>
                        <input
                            type="radio"
                            v-model="paymentMethod"
                            name="payment_method"
                            value="cash"
                        />
                        <span
                            ><strong
                                ><AppIcon name="money" :size="18" />
                                CASH</strong
                            ><small>Bayar tunai di kasir</small></span
                        >
                    </label>
                </fieldset>
                <section
                    v-if="paymentMethod"
                    class="customer-payment-guide"
                    aria-labelledby="payment-guide-title"
                >
                    <h2 id="payment-guide-title">Cara pembayaran</h2>
                    <h3>{{ paymentMethod === 'qris' ? 'QRIS' : 'CASH' }}</h3>
                    <ol v-if="paymentMethod === 'qris'">
                        <li>Buat pesanan dan tampilkan gambar QRIS.</li>
                        <li>
                            Gunakan aplikasi bank atau dompet digital untuk
                            membayar sesuai nominal.
                        </li>
                        <li>
                            Tunggu status pembayaran terverifikasi sebelum
                            pesanan diproses.
                        </li>
                    </ol>
                    <ol v-else>
                        <li>Datang ke kasir dan tunjukkan struk pesananmu.</li>
                        <li>
                            Bayar tunai sesuai total yang dikonfirmasi kasir.
                        </li>
                        <li>
                            Tunggu kasir mengonfirmasi pembayaran sebelum
                            pesanan diproses.
                        </li>
                    </ol>
                    <p v-if="paymentMethod === 'cash'">
                        Pembayaran tunai dilakukan di kasir setelah pesanan
                        dibuat.
                    </p>
                </section>
            </form>
        </div>
        <footer class="customer-order-summary">
            <div class="customer-order-summary-main">
                <div>
                    <span>Total estimasi</span
                    ><strong>{{ rupiah(total) }}</strong>
                </div>
                <button
                    type="submit"
                    :form="formId"
                    :disabled="!paymentMethod"
                    aria-describedby="customer-details-next-note"
                >
                    Lanjut <AppIcon name="arrow" :size="16" />
                </button>
            </div>
            <p id="customer-details-next-note">
                {{
                    paymentMethod === 'cash'
                        ? 'Buat pesanan, lalu bayar tunai di kasir sebelum batas waktu.'
                        : paymentMethod === 'qris'
                          ? 'Buat pesanan, lalu bayar lewat QRIS sebelum batas waktu.'
                          : 'Pilih metode pembayaran untuk melanjutkan.'
                }}
            </p>
            <a v-if="statusUrl" :href="statusUrl">Lihat struk pesanan</a>
        </footer>
    </section>
</template>
