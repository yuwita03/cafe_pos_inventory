<script setup lang="ts">
import { ref, useId } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { formatRupiah as rupiah } from '@/lib/utils';
import type { CartLine } from '@/pages/admin/pos/fixtures';

const props = defineProps<{
    lines: CartLine[];
    total: number;
    customerName: string;
    fulfillment: 'dine_in' | 'takeaway';
    tableId: string;
    errors: { name?: string; table?: string };
    tables: { id: number; label: string }[];
}>();
const emit = defineEmits<{
    'update:customerName': [value: string];
    'update:fulfillment': [value: 'dine_in' | 'takeaway'];
    'update:tableId': [value: string];
    quantity: [key: string, quantity: number];
    remove: [key: string];
    edit: [line: CartLine];
    clear: [];
    checkout: [];
}>();
const id = useId();
const nameInput = ref<HTMLInputElement>();
const tableInput = ref<HTMLSelectElement>();
defineExpose({
    focusError: () =>
        (props.errors.name ? nameInput.value : tableInput.value)?.focus(),
});
</script>

<template>
    <form class="pos-cart" novalidate @submit.prevent="emit('checkout')">
        <header class="pos-cart-heading">
            <div>
                <span class="pos-kicker">PESANAN SAAT INI</span>
                <h2>Pesanan baru</h2>
            </div>
            <button
                type="button"
                class="pos-text-button"
                :disabled="!lines.length"
                @click="emit('clear')"
            >
                Kosongkan
            </button>
        </header>
        <div class="pos-customer-fields">
            <label class="field" :for="id + '-name'"
                >Nama pelanggan <span class="required-mark">*</span>
                <input
                    :id="id + '-name'"
                    ref="nameInput"
                    :value="customerName"
                    required
                    autocomplete="off"
                    placeholder="Nama untuk pesanan ini"
                    :aria-invalid="Boolean(errors.name)"
                    :aria-describedby="
                        errors.name ? id + '-name-error' : undefined
                    "
                    @input="
                        emit(
                            'update:customerName',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
            </label>
            <p
                v-if="errors.name"
                :id="id + '-name-error'"
                class="pos-error"
                role="alert"
            >
                {{ errors.name }}
            </p>
            <fieldset class="pos-service">
                <legend>Jenis pesanan</legend>
                <label
                    v-for="service in [
                        { id: 'dine_in', label: 'Dine-in', icon: 'grid' },
                        { id: 'takeaway', label: 'Takeaway', icon: 'purchase' },
                    ] as const"
                    :key="service.id"
                    :class="{ selected: fulfillment === service.id }"
                >
                    <input
                        type="radio"
                        :name="id + '-service'"
                        :value="service.id"
                        :checked="fulfillment === service.id"
                        @change="emit('update:fulfillment', service.id)"
                    />
                    <AppIcon :name="service.icon" :size="16" />{{
                        service.label
                    }}
                </label>
            </fieldset>
            <label
                v-if="fulfillment === 'dine_in'"
                class="field pos-table-field"
                :for="id + '-table'"
                >Nomor meja <span class="required-mark">*</span>
                <select
                    :id="id + '-table'"
                    ref="tableInput"
                    :value="tableId"
                    required
                    :aria-invalid="Boolean(errors.table)"
                    :aria-describedby="
                        errors.table ? id + '-table-error' : undefined
                    "
                    @change="
                        emit(
                            'update:tableId',
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="">Pilih meja pelanggan</option>
                    <option
                        v-for="table in tables"
                        :key="table.id"
                        :value="String(table.id)"
                    >
                        Meja {{ table.label }}
                    </option>
                </select>
            </label>
            <p
                v-if="errors.table"
                :id="id + '-table-error'"
                class="pos-error"
                role="alert"
            >
                {{ errors.table }}
            </p>
            <p v-if="fulfillment === 'takeaway'" class="pos-field-help">
                Pesanan dibawa pulang, tanpa nomor meja.
            </p>
        </div>

        <div class="pos-cart-lines">
            <div v-if="!lines.length" class="pos-cart-empty">
                <span class="pos-empty-icon"
                    ><AppIcon name="orders" :size="28"
                /></span>
                <h3>Belum ada pesanan</h3>
                <p>Pilih menu di katalog untuk mulai membuat pesanan.</p>
            </div>
            <article
                v-for="line in lines"
                :key="line.key"
                class="pos-cart-line"
            >
                <div class="pos-line-heading">
                    <h3>{{ line.productName }}</h3>
                    <strong>{{
                        rupiah(line.unitPrice * line.quantity)
                    }}</strong>
                </div>
                <p class="pos-line-options">
                    {{
                        [
                            line.variantName === 'Regular'
                                ? ''
                                : line.variantName,
                            ...line.optionNames,
                        ]
                            .filter(Boolean)
                            .join(' · ') || 'Regular'
                    }}
                </p>
                <div class="pos-line-actions">
                    <div class="pos-quantity">
                        <button
                            type="button"
                            :disabled="line.quantity <= 1"
                            :aria-label="'Kurangi jumlah ' + line.productName"
                            @click="
                                emit('quantity', line.key, line.quantity - 1)
                            "
                        >
                            −</button
                        ><span :aria-label="'Jumlah ' + line.productName">{{
                            line.quantity
                        }}</span
                        ><button
                            type="button"
                            :aria-label="'Tambah jumlah ' + line.productName"
                            @click="
                                emit('quantity', line.key, line.quantity + 1)
                            "
                        >
                            +
                        </button>
                    </div>
                    <button
                        type="button"
                        class="pos-text-button"
                        :aria-label="'Ubah ' + line.productName"
                        @click="emit('edit', line)"
                    >
                        Ubah
                    </button>
                    <button
                        type="button"
                        class="pos-remove"
                        :aria-label="'Hapus ' + line.productName"
                        @click="emit('remove', line.key)"
                    >
                        <AppIcon name="trash" :size="16" />
                    </button>
                </div>
            </article>
        </div>
        <footer class="pos-cart-footer">
            <div class="pos-total-row">
                <span
                    >Subtotal ·
                    {{ lines.reduce((sum, line) => sum + line.quantity, 0) }}
                    item</span
                ><span>{{ rupiah(total) }}</span>
            </div>
            <div class="pos-grand-total">
                <span>Total estimasi</span><strong>{{ rupiah(total) }}</strong>
            </div>
            <BaseButton
                type="submit"
                variant="primary"
                :disabled="!lines.length"
                class="pos-checkout"
                >Lanjut ke pembayaran<AppIcon name="arrow" :size="17"
            /></BaseButton>
            <p class="pos-field-help">
                Harga dan stok divalidasi kembali saat pembayaran tunai
                dikonfirmasi.
            </p>
        </footer>
    </form>
</template>
