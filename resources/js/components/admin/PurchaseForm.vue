<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { formatQuantity, formatRupiah } from '@/lib/utils';
import { purchases, suppliers as supplierList } from '@/routes/admin';
import { store, update, show } from '@/routes/admin/purchases';
import type { Purchase, PurchaseEditorProps } from '@/types/purchasing';
import '../../../css/purchasing.css';

const props = defineProps<PurchaseEditorProps & { purchase?: Purchase }>();
const confirmation = ref<'save' | 'discard' | null>(null);
const form = useForm({
    supplier_id: props.purchase?.supplier_id ?? '',
    items: props.purchase?.items.map((line) => ({
        stock_item_id: String(line.stock_item_id),
        quantity: String(Number(line.quantity)),
        line_cost_idr: String(line.line_cost_idr),
    })) ?? [{ stock_item_id: '', quantity: '', line_cost_idr: '' }],
});
const total = computed(() =>
    form.items.reduce(
        (sum, line) => sum + (Number(line.line_cost_idr) || 0),
        0,
    ),
);
const unavailable = computed(
    () => !props.suppliers.length || !props.stockItems.length,
);
function error(field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[field];
}
function save(): void {
    if (form.processing || unavailable.value) {
        return;
    }
    confirmation.value = 'save';
}
function cancel(): void {
    if (!form.processing) {
        confirmation.value = 'discard';
    }
}
function closeConfirmation(): void {
    if (!form.processing) {
        confirmation.value = null;
    }
}
function confirm(): void {
    if (form.processing || !confirmation.value) {
        return;
    }
    if (confirmation.value === 'discard') {
        router.visit(
            props.purchase ? show.url(props.purchase.id) : purchases.url(),
        );
        return;
    }
    const options = {
        onFinish: () => {
            confirmation.value = null;
        },
    };
    if (props.purchase) {
        form.put(update.url(props.purchase.id), options);
    } else {
        form.post(store.url(), options);
    }
}
defineExpose({ cancel });
</script>

<template>
    <form class="procurement-panel procurement-form" @submit.prevent="save">
        <p class="procurement-note">
            Simpan draft belum mengubah stok. Pastikan kuantitas sesuai barang
            yang datang sebelum menerima seluruh pembelian.
        </p>
        <p v-if="unavailable" class="field-error" role="alert">
            Siapkan supplier dan item stok aktif sebelum membuat pembelian.
            <Link :href="supplierList()">Kelola supplier</Link>.
        </p>
        <p v-if="error('status')" class="field-error" role="alert">
            {{ error('status') }}
        </p>
        <fieldset :disabled="form.processing">
            <legend class="sr-only">Draft pembelian</legend>
            <label class="field" for="purchase-supplier"
                >Supplier<select
                    id="purchase-supplier"
                    v-model="form.supplier_id"
                    required
                    :aria-invalid="Boolean(form.errors.supplier_id)"
                >
                    <option disabled value="">Pilih supplier</option>
                    <option
                        v-for="supplier in suppliers"
                        :key="supplier.id"
                        :value="supplier.id"
                    >
                        {{ supplier.name
                        }}{{
                            supplier.is_active ? '' : ' (nonaktif — draft lama)'
                        }}
                    </option>
                </select></label
            >
            <p v-if="form.errors.supplier_id" class="field-error">
                {{ form.errors.supplier_id }}
            </p>
            <div class="section-heading">
                <h3>Item pembelian</h3>
                <BaseButton
                    :disabled="form.items.length >= 100"
                    @click="
                        form.items.push({
                            stock_item_id: '',
                            quantity: '',
                            line_cost_idr: '',
                        })
                    "
                    >Tambah item</BaseButton
                >
            </div>
            <p class="field-help">
                Kuantitas memakai satuan dasar stok. Biaya adalah total per
                baris, bukan harga per g/ml/pcs.
            </p>
            <p v-if="error('items')" class="field-error">
                {{ error('items') }}
            </p>
            <div class="procurement-lines">
                <div
                    v-for="(line, index) in form.items"
                    :key="index"
                    class="procurement-line"
                >
                    <div>
                        <label class="field" :for="`purchase-stock-${index}`"
                            >Item stok<select
                                :id="`purchase-stock-${index}`"
                                v-model="line.stock_item_id"
                                required
                                :aria-invalid="
                                    Boolean(
                                        error(`items.${index}.stock_item_id`),
                                    )
                                "
                            >
                                <option disabled value="">Pilih item</option>
                                <option
                                    v-for="item in stockItems"
                                    :key="item.id"
                                    :value="String(item.id)"
                                    :disabled="
                                        (!item.active &&
                                            String(item.id) !==
                                                line.stock_item_id) ||
                                        form.items.some(
                                            (other, otherIndex) =>
                                                otherIndex !== index &&
                                                other.stock_item_id ===
                                                    String(item.id),
                                        )
                                    "
                                >
                                    {{ item.name }} ({{ item.unit }}){{
                                        item.active ? '' : ' — nonaktif'
                                    }}
                                </option>
                            </select></label
                        >
                        <p
                            v-if="error(`items.${index}.stock_item_id`)"
                            class="field-error"
                        >
                            {{ error(`items.${index}.stock_item_id`) }}
                        </p>
                        <p
                            v-if="
                                stockItems.find(
                                    (item) =>
                                        String(item.id) === line.stock_item_id,
                                )?.active === false
                            "
                            class="field-error"
                        >
                            Aktifkan kembali atau hapus item ini sebelum
                            penerimaan.
                        </p>
                    </div>
                    <div>
                        <label class="field" :for="`purchase-qty-${index}`"
                            >Kuantitas
                            {{
                                stockItems.find(
                                    (item) =>
                                        String(item.id) === line.stock_item_id,
                                )?.unit ?? ''
                            }}<input
                                :id="`purchase-qty-${index}`"
                                v-model="line.quantity"
                                type="number"
                                inputmode="decimal"
                                min="0.001"
                                max="999999999.999"
                                step="0.001"
                                required
                                :aria-invalid="
                                    Boolean(error(`items.${index}.quantity`))
                                "
                        /></label>
                        <p
                            v-if="error(`items.${index}.quantity`)"
                            class="field-error"
                        >
                            {{ error(`items.${index}.quantity`) }}
                        </p>
                    </div>
                    <div>
                        <label class="field" :for="`purchase-cost-${index}`"
                            >Total biaya baris (IDR)<input
                                :id="`purchase-cost-${index}`"
                                v-model="line.line_cost_idr"
                                type="number"
                                inputmode="numeric"
                                min="0"
                                max="999999999999"
                                step="1"
                                required
                                :aria-invalid="
                                    Boolean(
                                        error(`items.${index}.line_cost_idr`),
                                    )
                                "
                        /></label>
                        <p
                            v-if="error(`items.${index}.line_cost_idr`)"
                            class="field-error"
                        >
                            {{ error(`items.${index}.line_cost_idr`) }}
                        </p>
                    </div>
                    <BaseButton
                        :disabled="form.items.length === 1"
                        :aria-label="`Hapus item ${index + 1}`"
                        @click="
                            form.items.splice(index, 1);
                            form.clearErrors();
                        "
                        >Hapus</BaseButton
                    >
                </div>
            </div>
            <div class="procurement-total">
                <span>Total pembelian</span
                ><strong>{{ formatRupiah(total) }}</strong>
            </div>
            <div class="procurement-actions">
                <BaseButton @click="cancel">Batal</BaseButton>
                <BaseButton
                    type="submit"
                    variant="primary"
                    :disabled="unavailable || form.processing"
                    >{{
                        form.processing ? 'Menyimpan…' : 'Simpan draft'
                    }}</BaseButton
                >
            </div>
        </fieldset>
        <AppDialog
            :open="confirmation !== null"
            :title="
                confirmation === 'discard'
                    ? purchase
                        ? 'Batalkan perubahan draft?'
                        : 'Batalkan pembuatan draft?'
                    : purchase
                      ? 'Simpan perubahan draft?'
                      : 'Buat draft pembelian?'
            "
            @close="closeConfirmation"
        >
            <div class="small-editor">
                <template v-if="confirmation === 'discard'">
                    <p>Perubahan yang belum disimpan akan dibuang.</p>
                    <p v-if="purchase" class="field-help">
                        Draft yang sudah tersimpan tetap ada dan tidak
                        dibatalkan.
                    </p>
                </template>
                <template v-else>
                    <p>
                        {{
                            purchase
                                ? 'Perubahan akan disimpan pada'
                                : 'Buat draft pembelian untuk'
                        }}
                        <strong>{{
                            purchase?.reference ??
                            suppliers.find(
                                (supplier) =>
                                    supplier.id === Number(form.supplier_id),
                            )?.name
                        }}</strong
                        >.
                    </p>
                    <ul class="procurement-dialog-lines">
                        <li v-for="(line, index) in form.items" :key="index">
                            <span
                                >{{
                                    stockItems.find(
                                        (item) =>
                                            String(item.id) ===
                                            line.stock_item_id,
                                    )?.name
                                }}
                                · {{ formatQuantity(line.quantity) }}
                                {{
                                    stockItems.find(
                                        (item) =>
                                            String(item.id) ===
                                            line.stock_item_id,
                                    )?.unit
                                }}</span
                            >
                            <strong>{{
                                formatRupiah(Number(line.line_cost_idr))
                            }}</strong>
                        </li>
                    </ul>
                    <p>
                        <strong>Total: {{ formatRupiah(total) }}</strong>
                    </p>
                    <p class="field-help">
                        Menyimpan draft tidak mengubah stok.
                    </p>
                </template>
            </div>
            <template #footer>
                <BaseButton
                    :disabled="form.processing"
                    @click="closeConfirmation"
                    >Kembali ke form</BaseButton
                >
                <BaseButton
                    :variant="confirmation === 'discard' ? 'danger' : 'primary'"
                    :disabled="form.processing"
                    @click="confirm"
                    >{{
                        form.processing
                            ? 'Menyimpan…'
                            : confirmation === 'discard'
                              ? 'Ya, buang perubahan'
                              : purchase
                                ? 'Ya, simpan perubahan'
                                : 'Ya, buat draft'
                    }}</BaseButton
                >
            </template>
        </AppDialog>
    </form>
</template>
