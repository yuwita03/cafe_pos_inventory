<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatQuantity, formatRupiah } from '@/lib/utils';
import { purchases } from '@/routes/admin';
import { edit, receive, cancel } from '@/routes/admin/purchases';
import { history } from '@/routes/admin/inventory';
import type { Purchase } from '@/types/purchasing';
import '../../../../css/purchasing.css';
defineOptions({ layout: AdminLayout });
const props = defineProps<{ purchase: Purchase }>();
const action = ref<'receive' | 'cancel' | null>(null);
const form = useForm({});
const errors = () => Object.values(form.errors as Record<string, string>);
function close(): void {
    if (!form.processing) {
        action.value = null;
    }
}
function submit(): void {
    if (form.processing || !action.value) {
        return;
    }
    const route = action.value === 'receive' ? receive : cancel;
    form.post(route.url(props.purchase.id), {
        preserveScroll: true,
        onSuccess: () => {
            action.value = null;
        },
        onError: () => {
            action.value = null;
        },
    });
}
</script>

<template>
    <div class="procurement-page">
        <Head :title="purchase.reference" />
        <div class="page-heading">
            <div>
                <span class="eyebrow">PENGADAAN / PEMBELIAN</span>
                <h1>
                    {{ purchase.reference }}<span class="title-dot">.</span>
                </h1>
                <p>Detail barang, biaya, dan penerimaan pembelian.</p>
            </div>
            <Link :href="purchases()" class="button">Daftar pembelian</Link>
        </div>
        <p
            v-for="message in errors()"
            :key="message"
            class="field-error"
            role="alert"
        >
            {{ message }}
        </p>
        <div v-if="purchase.status === 'draft'" class="procurement-note">
            Draft belum menambah stok. Jika jumlah barang datang berbeda, edit
            draft sebelum menerima seluruh barang.
        </div>
        <div class="procurement-panel">
            <dl class="procurement-details">
                <div>
                    <dt>Supplier</dt>
                    <dd>
                        {{ purchase.supplier
                        }}{{ purchase.supplierActive ? '' : ' (nonaktif)' }}
                    </dd>
                </div>
                <div>
                    <dt>Dibuat</dt>
                    <dd>{{ purchase.date }} · {{ purchase.creator }}</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd>
                        <span
                            class="purchase-status"
                            :class="purchase.status"
                            >{{ purchase.status }}</span
                        >
                    </dd>
                </div>
            </dl>
            <div class="table-scroll">
                <table class="product-table">
                    <thead>
                        <tr>
                            <th scope="col">Item stok</th>
                            <th scope="col">Kuantitas</th>
                            <th scope="col">Total biaya baris</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="line in purchase.items"
                            :key="line.stock_item_id"
                        >
                            <td>
                                <strong>{{ line.name }}</strong>
                                <p
                                    v-if="
                                        !line.active &&
                                        purchase.status === 'draft'
                                    "
                                    class="field-error"
                                >
                                    Item nonaktif. Aktifkan kembali atau hapus
                                    dari draft sebelum penerimaan.
                                </p>
                            </td>
                            <td>
                                {{ formatQuantity(line.quantity) }}
                                {{ line.unit }}
                            </td>
                            <td>{{ formatRupiah(line.line_cost_idr) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="procurement-form">
                <div class="procurement-total">
                    <span>Total pembelian</span
                    ><strong>{{ formatRupiah(purchase.total) }}</strong>
                </div>
                <div
                    v-if="purchase.status === 'draft'"
                    class="procurement-actions"
                >
                    <div class="row-actions">
                        <Link :href="edit(purchase.id)" class="button"
                            >Edit draft</Link
                        ><BaseButton variant="danger" @click="action = 'cancel'"
                            >Batalkan</BaseButton
                        >
                    </div>
                    <BaseButton
                        variant="primary"
                        :disabled="purchase.items.some((line) => !line.active)"
                        @click="action = 'receive'"
                        >Terima seluruh barang</BaseButton
                    >
                </div>
                <p v-else class="field-help">
                    Pembelian {{ purchase.status }} tidak dapat diubah.
                </p>
            </div>
            <div
                v-if="purchase.status === 'received'"
                class="procurement-receipt"
                role="status"
            >
                Diterima oleh {{ purchase.receiver }} pada
                {{ purchase.receivedAt }}. Stok telah ditambahkan satu kali.
                <Link :href="history()">Lihat riwayat stok</Link>
            </div>
        </div>
        <AppDialog
            :open="action !== null"
            :title="
                action === 'receive'
                    ? 'Terima seluruh barang?'
                    : 'Batalkan pembelian?'
            "
            @close="close"
        >
            <div class="small-editor">
                <p v-if="action === 'receive'">
                    Stok berikut akan bertambah satu kali. Pastikan jumlah
                    sesuai barang yang datang.
                </p>
                <p v-else>
                    Pembelian akan dibatalkan tanpa mengubah stok. Tindakan ini
                    tidak dapat dibatalkan.
                </p>
                <ul
                    v-if="action === 'receive'"
                    class="procurement-dialog-lines"
                >
                    <li
                        v-for="line in purchase.items"
                        :key="line.stock_item_id"
                    >
                        <span>{{ line.name }}</span
                        ><strong
                            >+{{ formatQuantity(line.quantity) }}
                            {{ line.unit }}</strong
                        >
                    </li>
                </ul>
                <p>{{ purchase.reference }} · {{ purchase.supplier }}</p>
            </div>
            <template #footer
                ><BaseButton :disabled="form.processing" @click="close"
                    >Kembali</BaseButton
                ><BaseButton
                    :variant="action === 'receive' ? 'primary' : 'danger'"
                    :disabled="form.processing"
                    @click="submit"
                    >{{
                        form.processing
                            ? 'Memproses…'
                            : action === 'receive'
                              ? 'Ya, terima seluruh barang'
                              : 'Ya, batalkan pembelian'
                    }}</BaseButton
                ></template
            >
        </AppDialog>
    </div>
</template>
