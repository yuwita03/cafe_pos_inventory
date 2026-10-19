<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import PurchaseForm from '@/components/admin/PurchaseForm.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { Purchase, PurchaseEditorProps } from '@/types/purchasing';
defineOptions({ layout: AdminLayout });
defineProps<PurchaseEditorProps & { purchase: Purchase }>();
const editor = ref<InstanceType<typeof PurchaseForm>>();
</script>

<template>
    <div class="procurement-page">
        <Head :title="`Edit ${purchase.reference}`" />
        <div class="page-heading">
            <div>
                <span class="eyebrow">PENGADAAN / PEMBELIAN</span>
                <h1>Edit draft<span class="title-dot">.</span></h1>
                <p>
                    {{ purchase.reference }} · Sesuaikan jumlah aktual sebelum
                    penerimaan.
                </p>
            </div>
            <BaseButton @click="editor?.cancel()">Kembali ke detail</BaseButton>
        </div>
        <PurchaseForm
            ref="editor"
            :purchase="purchase"
            :suppliers="suppliers"
            :stock-items="stockItems"
        />
    </div>
</template>
