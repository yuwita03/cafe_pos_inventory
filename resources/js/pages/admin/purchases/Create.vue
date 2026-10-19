<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import PurchaseForm from '@/components/admin/PurchaseForm.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { PurchaseEditorProps } from '@/types/purchasing';
defineOptions({ layout: AdminLayout });
defineProps<PurchaseEditorProps>();
const editor = ref<InstanceType<typeof PurchaseForm>>();
</script>

<template>
    <div class="procurement-page">
        <Head title="Buat pembelian" />
        <div class="page-heading">
            <div>
                <span class="eyebrow">PENGADAAN / PEMBELIAN</span>
                <h1>Buat pembelian<span class="title-dot">.</span></h1>
                <p>Catat pembelian bahan atau pastry dari supplier.</p>
            </div>
            <BaseButton @click="editor?.cancel()">Daftar pembelian</BaseButton>
        </div>
        <PurchaseForm
            ref="editor"
            :suppliers="suppliers"
            :stock-items="stockItems"
        />
    </div>
</template>
