<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { store, update, qr, print as printTable } from '@/routes/admin/tables';
import type { CafeTable } from '@/types/tables';

defineOptions({ layout: AdminLayout });
const props = defineProps<{ tables: CafeTable[] }>();
const query = ref('');
const status = ref('active');
const notice = ref('');
const editorOpen = ref(false);
const editingId = ref<number | null>(null);
const labelInput = ref<HTMLInputElement>();
const qrId = ref<number | null>(null);
const qrFailed = ref(false);
const form = useForm({ label: '', is_active: true });
const activeCount = computed(
    () => props.tables.filter((table) => table.active).length,
);
const visible = computed(() =>
    props.tables.filter(
        (table) =>
            table.label
                .toLocaleLowerCase('id')
                .includes(query.value.trim().toLocaleLowerCase('id')) &&
            (status.value === 'all' ||
                table.active === (status.value === 'active')),
    ),
);
const qrTable = computed(() =>
    props.tables.find((table) => table.id === qrId.value),
);

function openEditor(table?: CafeTable): void {
    editingId.value = table?.id ?? null;
    form.defaults({
        label: table?.label ?? '',
        is_active: table?.active ?? true,
    });
    form.reset();
    form.clearErrors();
    editorOpen.value = true;
    void nextTick(() => labelInput.value?.focus());
}
function closeEditor(): void {
    if (
        form.processing ||
        (form.isDirty &&
            !window.confirm('Buang perubahan meja yang belum disimpan?'))
    ) {
        return;
    }
    editorOpen.value = false;
}
function save(): void {
    if (form.processing) {
        return;
    }
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            notice.value = editingId.value
                ? 'Meja berhasil diperbarui.'
                : 'Meja berhasil ditambahkan.';
            editorOpen.value = false;
        },
        onError: () => {
            void nextTick(() => labelInput.value?.focus());
        },
    };
    if (editingId.value) {
        form.put(update.url(editingId.value), options);
    } else {
        form.post(store.url(), options);
    }
}
function viewQr(table: CafeTable): void {
    qrFailed.value = false;
    qrId.value = table.id;
}
</script>

<template>
    <div class="tables-page">
        <Head title="Meja & QR" />
        <div class="page-heading">
            <div>
                <span class="eyebrow">OPERASIONAL</span>
                <h1>Meja & QR<span class="title-dot">.</span></h1>
                <p>Atur meja dan siapkan QR untuk menu café.</p>
            </div>
            <BaseButton variant="primary" @click="openEditor()"
                ><AppIcon name="plus" :size="18" />Tambah meja</BaseButton
            >
        </div>
        <div class="tables-overview">
            <div class="tables-count">
                <span class="summary-icon"><AppIcon name="grid" /></span>
                <div>
                    <strong>{{ activeCount }} <small>meja aktif</small></strong>
                    <p>
                        {{ tables.length }} meja terdaftar · status aktif bukan
                        tanda meja kosong.
                    </p>
                </div>
            </div>
            <p class="tables-stage">
                <AppIcon name="info" :size="18" /><span
                    >QR disiapkan untuk menu pelanggan. Halaman pelanggan belum
                    tersedia.</span
                >
            </p>
        </div>
        <p v-if="notice" class="tables-notice" role="status">{{ notice }}</p>
        <div class="tables-toolbar">
            <label class="search-field"
                ><AppIcon name="search" :size="18" /><span class="sr-only"
                    >Cari meja</span
                ><input
                    v-model="query"
                    type="search"
                    placeholder="Cari nama atau nomor meja…"
            /></label>
            <label class="tables-filter"
                ><span>Status</span
                ><select v-model="status">
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                    <option value="all">Semua status</option>
                </select></label
            >
        </div>
        <div v-if="visible.length" class="tables-grid">
            <article
                v-for="table in visible"
                :key="table.id"
                class="table-card"
            >
                <div class="table-card-top">
                    <span class="table-symbol"
                        ><AppIcon name="grid" :size="23" /></span
                    ><StatusBadge :active="table.active" />
                </div>
                <h2>Meja {{ table.label }}</h2>
                <p>
                    {{
                        table.active
                            ? 'QR siap digunakan saat menu pelanggan tersedia.'
                            : 'Akses menu dan pilihan kasir dinonaktifkan.'
                    }}
                </p>
                <div class="table-card-actions">
                    <BaseButton @click="openEditor(table)"
                        ><AppIcon name="edit" :size="16" />Edit</BaseButton
                    ><BaseButton
                        :disabled="!table.active"
                        @click="viewQr(table)"
                        >Lihat QR<AppIcon name="arrow" :size="16"
                    /></BaseButton>
                </div>
            </article>
        </div>
        <div v-else class="empty-state">
            <span class="empty-icon"><AppIcon name="grid" :size="28" /></span>
            <h3>
                {{
                    tables.length
                        ? 'Tidak ada meja yang cocok'
                        : 'Tambahkan meja pertama'
                }}
            </h3>
            <p>
                {{
                    tables.length
                        ? 'Coba nama atau status meja yang lain.'
                        : 'Buat meja untuk pilihan dine-in di kasir dan akses menu lewat QR.'
                }}
            </p>
            <BaseButton
                v-if="tables.length"
                @click="
                    query = '';
                    status = 'all';
                "
                >Tampilkan semua meja</BaseButton
            >
            <BaseButton v-else variant="primary" @click="openEditor()"
                >Tambah meja</BaseButton
            >
        </div>
        <AppDialog
            :open="editorOpen"
            :title="editingId ? 'Edit meja' : 'Tambah meja'"
            @close="closeEditor"
        >
            <form id="table-editor" class="small-editor" @submit.prevent="save">
                <fieldset class="table-fields" :disabled="form.processing">
                    <legend class="sr-only">Informasi meja</legend>
                    <label class="field"
                        >Nama atau nomor meja
                        <span class="required-mark">*</span
                        ><input
                            ref="labelInput"
                            v-model="form.label"
                            maxlength="30"
                            required
                            placeholder="Contoh: A01 atau Teras 1"
                            :aria-invalid="Boolean(form.errors.label)"
                            :aria-describedby="
                                form.errors.label
                                    ? 'table-label-error'
                                    : undefined
                            "
                    /></label>
                    <p
                        v-if="form.errors.label"
                        id="table-label-error"
                        class="field-error"
                    >
                        {{ form.errors.label }}
                    </p>
                    <label class="table-active"
                        ><input v-model="form.is_active" type="checkbox" /><span
                            ><strong>Meja aktif</strong
                            ><small
                                >Tersedia untuk dine-in kasir dan link QR
                                meja.</small
                            ></span
                        ></label
                    >
                    <p v-if="form.errors.is_active" class="field-error">
                        {{ form.errors.is_active }}
                    </p>
                    <p v-if="!form.is_active" class="table-edit-note">
                        <AppIcon name="info" :size="18" />QR meja tidak dapat
                        membuka menu. Pesanan yang sudah ada tetap tersimpan dan
                        dapat diproses.
                    </p>
                    <p v-else-if="editingId" class="field-help">
                        Mengubah label tidak mengubah link meja. Cetak ulang QR
                        agar label di meja sesuai.
                    </p>
                </fieldset>
            </form>
            <template #footer
                ><BaseButton :disabled="form.processing" @click="closeEditor"
                    >Batal</BaseButton
                ><BaseButton
                    type="submit"
                    form="table-editor"
                    variant="primary"
                    :disabled="form.processing"
                    >{{
                        form.processing ? 'Menyimpan…' : 'Simpan meja'
                    }}</BaseButton
                ></template
            >
        </AppDialog>
        <AppDialog
            :open="Boolean(qrTable)"
            :title="`QR Meja ${qrTable?.label ?? ''}`"
            @close="qrId = null"
        >
            <div v-if="qrTable" class="small-editor table-qr-preview">
                <span class="eyebrow">CAFÉ POS</span>
                <h3>Meja {{ qrTable.label }}</h3>
                <p
                    v-if="!qrTable.active || qrFailed"
                    class="field-error"
                    role="alert"
                >
                    QR tidak dapat dimuat. Periksa koneksi dan pastikan meja
                    masih aktif.
                </p>
                <img
                    v-else
                    :src="qr.url(qrTable.id)"
                    width="280"
                    height="280"
                    :alt="`QR menu untuk Meja ${qrTable.label}`"
                    @error="qrFailed = true"
                />
                <strong>Scan untuk lihat menu</strong>
                <p>Halaman menu pelanggan belum tersedia.</p>
                <a
                    :href="qrTable.menuUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="table-menu-url"
                    >{{ qrTable.menuUrl }}</a
                >
                <p class="field-help">
                    Pastikan alamat menu dapat dibuka dari ponsel pelanggan
                    sebelum QR dicetak.
                </p>
            </div>
            <template #footer
                ><template v-if="qrTable?.active && !qrFailed"
                    ><a
                        :href="qr.url(qrTable.id, { query: { download: 1 } })"
                        class="button"
                        >Unduh SVG</a
                    ><a
                        :href="printTable.url(qrTable.id)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="button button-primary"
                        >Cetak</a
                    ></template
                ><BaseButton v-else @click="qrId = null"
                    >Tutup</BaseButton
                ></template
            >
        </AppDialog>
    </div>
</template>

<style scoped>
.tables-overview {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    padding: 22px 24px;
    background: white;
    border: 1px solid var(--line);
    border-radius: 12px;
    margin-bottom: 24px;
}
.tables-count {
    display: flex;
    align-items: center;
    gap: 14px;
}
.tables-count strong {
    font-size: 26px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}
.tables-count small {
    font-size: 14px;
    font-weight: 500;
}
.tables-count p,
.tables-stage {
    color: #616161;
    font-size: 12px;
}
.tables-stage {
    display: flex;
    align-items: center;
    gap: 9px;
    max-width: 245px;
}
.tables-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}
.tables-toolbar .search-field {
    flex: 1;
    max-width: 420px;
}
.tables-filter {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #616161;
    font-size: 12px;
}
.tables-filter select {
    min-height: 44px;
    background: white;
    padding: 0 34px 0 12px;
    border: 1px solid #dedfd8;
    border-radius: 7px;
    color: var(--ink);
}
.tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 16px;
}
.table-card {
    min-width: 0;
    background: white;
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 22px;
}
.table-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
}
.table-symbol {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    background: #f7f7f4;
    border-radius: 9px;
    color: #62645c;
}
.table-card h2 {
    font-size: 22px;
    font-weight: 550;
    overflow-wrap: anywhere;
}
.table-card > p {
    font-size: 12px;
    color: #616161;
    margin-top: 7px;
    min-height: 36px;
}
.table-card-actions {
    display: flex;
    gap: 8px;
    margin-top: 22px;
    padding-top: 16px;
    border-top: 1px solid var(--line);
}
.table-card-actions .button {
    flex: 1;
}
.tables-notice {
    color: #166534;
    background: #ecfdf3;
    border: 1px solid #ccebd5;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
}
.table-fields {
    padding: 0;
    margin: 0;
    border: 0;
    min-width: 0;
}
.table-active {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin: 22px 0;
    cursor: pointer;
}
.table-active input {
    margin-top: 4px;
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
}
.table-active strong,
.table-active small {
    display: block;
}
.table-active small {
    margin-top: 4px;
    font-size: 12px;
    color: #616161;
}
.table-edit-note {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    padding: 12px;
    background: #fffbeb;
    color: #854d0e;
    border-radius: 8px;
    font-size: 12px;
    line-height: 1.7;
}
.table-qr-preview {
    display: grid;
    justify-items: center;
    gap: 10px;
    text-align: center;
}
.table-qr-preview h3 {
    font-size: 25px;
    overflow-wrap: anywhere;
    max-width: 100%;
}
.table-qr-preview img {
    max-width: 100%;
    height: auto;
    background: white;
}
.table-qr-preview > p {
    color: #616161;
    font-size: 13px;
}
.table-menu-url {
    font-size: 12px;
    color: var(--primary);
    overflow-wrap: anywhere;
    max-width: 100%;
    padding: 12px;
    background: #fafaf8;
    border-radius: 7px;
}
@media (max-width: 640px) {
    .tables-overview {
        align-items: flex-start;
        flex-direction: column;
        gap: 15px;
        padding: 18px;
    }
    .tables-stage {
        max-width: none;
    }
    .tables-toolbar {
        align-items: stretch;
        flex-direction: column;
        gap: 10px;
    }
    .tables-toolbar .search-field {
        max-width: none;
    }
    .tables-filter select {
        flex: 1;
    }
    .tables-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
