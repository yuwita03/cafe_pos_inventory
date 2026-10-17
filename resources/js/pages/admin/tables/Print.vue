<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import '../../../../css/admin.css';
import { tables } from '@/routes/admin';
import { qr } from '@/routes/admin/tables';
const props = defineProps<{
    table: { id: number; label: string; menuUrl: string };
}>();
const loaded = ref(false);
const failed = ref(false);
function print(): void {
    window.print();
}
</script>

<template>
    <main class="admin-shell table-print">
        <Head :title="`Cetak QR Meja ${props.table.label}`" />
        <nav class="print-toolbar" aria-label="Cetak QR">
            <a :href="tables.url()" class="button">Kembali ke Meja & QR</a
            ><button
                class="button button-primary"
                :disabled="!loaded || failed"
                @click="print"
            >
                Cetak QR
            </button>
        </nav>
        <p v-if="failed" class="print-error" role="alert">
            QR tidak dapat dimuat. Pastikan meja aktif, lalu muat ulang halaman.
        </p>
        <article v-else class="print-card">
            <p class="print-brand">CAFÉ POS</p>
            <h1>Meja {{ table.label }}</h1>
            <img
                :src="qr.url(table.id)"
                width="320"
                height="320"
                :alt="`QR menu Meja ${table.label}`"
                @load="loaded = true"
                @error="failed = true"
            />
            <h2>Scan untuk lihat menu</h2>
            <p>Pemesanan melalui kasir.</p>
            <p class="print-url">{{ table.menuUrl }}</p>
        </article>
    </main>
</template>

<style scoped>
.table-print {
    padding: 32px 20px;
}
.print-toolbar {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-bottom: 32px;
    flex-wrap: wrap;
}
.print-card {
    background: white;
    max-width: 460px;
    margin: auto;
    text-align: center;
    padding: 36px 28px;
    border: 1px solid #e5e5e5;
    border-radius: 12px;
    break-inside: avoid;
}
.print-brand {
    color: #616161;
    font-size: 12px;
    letter-spacing: 3px;
}
.print-card h1 {
    font-size: 32px;
    font-weight: 600;
    margin: 12px 0;
    overflow-wrap: anywhere;
}
.print-card img {
    display: block;
    width: 100%;
    max-width: 320px;
    height: auto;
    margin: 12px auto;
}
.print-card h2 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 8px;
}
.print-url {
    margin-top: 18px;
    font-size: 11px;
    overflow-wrap: anywhere;
}
.print-error {
    text-align: center;
    color: #b91c1c;
}
@media print {
    .table-print {
        padding: 0;
        background: white;
        color: black;
        min-height: 0;
    }
    .print-toolbar,
    .print-error {
        display: none;
    }
    .print-card {
        border: 1px solid #bbb;
        border-radius: 0;
        width: 100mm;
        max-width: 100%;
        padding: 8mm;
    }
    .print-card img {
        width: 65mm;
        max-width: 100%;
    }
}
@page {
    margin: 15mm;
}
</style>
