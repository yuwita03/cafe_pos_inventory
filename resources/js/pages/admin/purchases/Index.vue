<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatRupiah } from '@/lib/utils';
import { create, show } from '@/routes/admin/purchases';
import type { PurchaseSummary } from '@/types/purchasing';
import '../../../../css/purchasing.css';
defineOptions({ layout: AdminLayout });
const props = defineProps<{ purchases: PurchaseSummary[] }>();
const query = ref('');
const status = ref('all');
const visible = computed(() =>
    props.purchases.filter(
        (purchase) =>
            `${purchase.reference} ${purchase.supplier}`
                .toLocaleLowerCase('id')
                .includes(query.value.trim().toLocaleLowerCase('id')) &&
            (status.value === 'all' || status.value === purchase.status),
    ),
);
</script>

<template>
    <div class="procurement-page">
        <Head title="Pembelian" />
        <div class="page-heading">
            <div>
                <span class="eyebrow">PENGADAAN</span>
                <h1>Pembelian<span class="title-dot">.</span></h1>
                <p>Dari draft hingga seluruh barang diterima.</p>
            </div>
            <Link :href="create()" class="button button-primary"
                ><AppIcon name="plus" :size="18" />Buat pembelian</Link
            >
        </div>
        <div class="procurement-toolbar">
            <label class="search-field"
                ><AppIcon name="search" :size="18" /><span class="sr-only"
                    >Cari pembelian</span
                ><input
                    v-model="query"
                    type="search"
                    placeholder="Cari referensi atau supplier…" /></label
            ><label
                ><span class="sr-only">Status pembelian</span
                ><select v-model="status">
                    <option value="all">Semua status</option>
                    <option value="draft">Draft</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select></label
            >
        </div>
        <div v-if="visible.length" class="procurement-panel table-scroll">
            <table class="product-table">
                <thead>
                    <tr>
                        <th scope="col">Referensi</th>
                        <th scope="col">Supplier</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Total</th>
                        <th scope="col">Status</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="purchase in visible" :key="purchase.id">
                        <td>
                            <strong>{{ purchase.reference }}</strong>
                        </td>
                        <td>{{ purchase.supplier }}</td>
                        <td>{{ purchase.date }}</td>
                        <td>{{ formatRupiah(purchase.total) }}</td>
                        <td>
                            <span
                                class="purchase-status"
                                :class="purchase.status"
                                >{{ purchase.status }}</span
                            >
                        </td>
                        <td>
                            <Link :href="show(purchase.id)" class="button"
                                >Detail</Link
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="empty-state">
            <h3>
                {{
                    purchases.length
                        ? 'Tidak ada pembelian yang cocok'
                        : 'Belum ada pembelian'
                }}
            </h3>
            <p>Buat draft untuk mencatat barang dan biaya dari supplier.</p>
            <Link :href="create()" class="button button-primary"
                >Buat pembelian</Link
            >
        </div>
        <p class="page-note">
            Draft tidak mengubah stok. Penerimaan dilakukan seluruhnya, tepat
            sekali.
        </p>
    </div>
</template>
