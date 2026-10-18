<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import CustomerDetailsForm from './CustomerDetailsForm.vue';
import { formatRupiah as rupiah } from '@/lib/utils';
import type { CartLine } from '@/types/pos';

const props = defineProps<{
    lines: CartLine[];
    menuUrl: string;
    tableId: number;
    tableLabel: string;
}>();
const emit = defineEmits<{
    quantity: [key: string, quantity: number];
    edit: [line: CartLine];
    remove: [key: string];
}>();
const total = computed(() =>
    props.lines.reduce((sum, line) => sum + line.unitPrice * line.quantity, 0),
);
const showingCustomerDetails = ref(false);
const checkoutButton = ref<HTMLButtonElement | null>(null);

async function showCustomerDetails(show: boolean): Promise<void> {
    showingCustomerDetails.value = show;
    await nextTick();
    if (!show) {
        checkoutButton.value?.focus();
    }
    window.scrollTo({ top: 0, behavior: 'instant' });
}
</script>

<template>
    <section class="customer-cart-page">
        <CustomerDetailsForm
            v-if="showingCustomerDetails"
            :table-id="tableId"
            :table-label="tableLabel"
            :total="total"
            :lines="lines"
            @back="showCustomerDetails(false)"
        />
        <template v-else>
            <header class="customer-order-header">
                <Link
                    :href="menuUrl"
                    preserve-state
                    class="customer-order-back"
                    aria-label="Kembali ke menu"
                    ><AppIcon name="back"
                /></Link>
                <h1>Pesanan</h1>
                <span aria-hidden="true"></span>
            </header>
            <div class="customer-order-content">
                <div class="customer-order-context">
                    <span>Jenis pesanan</span>
                    <strong
                        >Makan di tempat <AppIcon name="check" :size="16"
                    /></strong>
                </div>
                <div class="customer-order-table">
                    <AppIcon name="grid" :size="20" />
                    <div>
                        <strong>Meja {{ tableLabel }}</strong
                        ><span>Pesanan untuk meja ini</span>
                    </div>
                </div>

                <section aria-labelledby="customer-items-heading">
                    <div class="customer-order-section-heading">
                        <h2 id="customer-items-heading">
                            Item pesanan <span>({{ lines.length }})</span>
                        </h2>
                        <Link
                            :href="menuUrl"
                            preserve-state
                            class="customer-add-item"
                            ><AppIcon name="plus" :size="14" /> Tambah
                            item</Link
                        >
                    </div>
                    <div v-if="lines.length" class="customer-cart-lines">
                        <article
                            v-for="line in lines"
                            :key="line.key"
                            class="customer-cart-line"
                        >
                            <div class="customer-cart-line-title">
                                <h3>{{ line.productName }}</h3>
                                <button
                                    type="button"
                                    class="customer-edit-item"
                                    :aria-label="'Ubah ' + line.productName"
                                    @click="emit('edit', line)"
                                >
                                    <AppIcon name="edit" :size="14" /> Ubah
                                </button>
                            </div>
                            <div class="customer-order-options">
                                <p v-if="line.variantName !== 'Regular'">
                                    {{ line.variantName }}
                                </p>
                                <p
                                    v-for="(option, index) in line.optionNames"
                                    :key="index"
                                >
                                    {{ option }}
                                </p>
                            </div>
                            <div class="customer-cart-line-actions">
                                <div class="customer-line-price">
                                    <strong>{{
                                        rupiah(line.unitPrice * line.quantity)
                                    }}</strong
                                    ><button
                                        type="button"
                                        class="customer-delete-item"
                                        :aria-label="
                                            'Hapus ' + line.productName
                                        "
                                        @click="emit('remove', line.key)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                                <div class="customer-quantity">
                                    <button
                                        type="button"
                                        :aria-label="
                                            'Kurangi jumlah ' + line.productName
                                        "
                                        :disabled="line.quantity <= 1"
                                        @click="
                                            emit(
                                                'quantity',
                                                line.key,
                                                line.quantity - 1,
                                            )
                                        "
                                    >
                                        −
                                    </button>
                                    <span>{{ line.quantity }}</span>
                                    <button
                                        type="button"
                                        :aria-label="
                                            'Tambah jumlah ' + line.productName
                                        "
                                        @click="
                                            emit(
                                                'quantity',
                                                line.key,
                                                line.quantity + 1,
                                            )
                                        "
                                    >
                                        +
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div v-else class="customer-cart-empty">
                        <AppIcon name="orders" :size="28" />
                        <h3>Keranjang kosong</h3>
                        <p>Pilih menu untuk mulai memesan.</p>
                        <Link
                            :href="menuUrl"
                            preserve-state
                            class="customer-back"
                            >Lihat menu</Link
                        >
                    </div>
                </section>
            </div>
            <footer v-if="lines.length" class="customer-order-summary">
                <div class="customer-order-summary-main">
                    <div>
                        <span>Total estimasi</span
                        ><strong>{{ rupiah(total) }}</strong>
                    </div>
                    <button
                        ref="checkoutButton"
                        type="button"
                        @click="showCustomerDetails(true)"
                        aria-describedby="customer-checkout-note"
                    >
                        Lanjut ke pembayaran <AppIcon name="arrow" :size="16" />
                    </button>
                </div>
                <p id="customer-checkout-note">
                    Lengkapi data pemesan pada langkah berikutnya.
                </p>
            </footer>
        </template>
    </section>
</template>
