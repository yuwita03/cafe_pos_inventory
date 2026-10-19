<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { store, update } from '@/routes/admin/suppliers';
import type { Supplier, SupplierContact } from '@/types/purchasing';
import '../../../../css/purchasing.css';

defineOptions({ layout: AdminLayout });
const props = defineProps<{ suppliers: Supplier[] }>();
const query = ref('');
const status = ref('all');
const open = ref(false);
const confirmation = ref<'save' | 'discard' | null>(null);
const editingId = ref<number | null>(null);
const notice = ref('');
const newContactType = ref<'' | SupplierContact['type']>('');
const form = useForm<{
    name: string;
    contacts: SupplierContact[];
    is_active: boolean;
}>({ name: '', contacts: contactFields(), is_active: true });
const confirmationContacts = computed(() =>
    form.contacts
        .map((contact) => ({ ...contact, value: contact.value.trim() }))
        .filter((contact) => contact.value !== ''),
);
const visible = computed(() =>
    props.suppliers.filter(
        (supplier) =>
            supplier.name
                .toLocaleLowerCase('id')
                .includes(query.value.trim().toLocaleLowerCase('id')) &&
            (status.value === 'all' ||
                supplier.is_active === (status.value === 'active')),
    ),
);
function contactFields(saved: SupplierContact[] = []): SupplierContact[] {
    const remaining = saved.map((contact) => ({ ...contact }));
    const primary = (['phone', 'email'] as const).map(
        (type): SupplierContact => {
            const index = remaining.findIndex(
                (contact) => contact.type === type,
            );
            return index < 0
                ? { type, value: '' }
                : remaining.splice(index, 1)[0];
        },
    );
    return [...primary, ...remaining];
}
function edit(supplier?: Supplier): void {
    editingId.value = supplier?.id ?? null;
    form.defaults({
        name: supplier?.name ?? '',
        contacts: contactFields(supplier?.contacts),
        is_active: supplier?.is_active ?? true,
    });
    form.reset();
    form.clearErrors();
    confirmation.value = null;
    newContactType.value = '';
    open.value = true;
}
function addContact(): void {
    if (
        !newContactType.value ||
        form.processing ||
        form.contacts.length >= 10
    ) {
        return;
    }
    form.contacts.push({ type: newContactType.value, value: '' });
    newContactType.value = '';
}
function close(): void {
    if (form.processing) {
        return;
    }
    if (form.isDirty) {
        confirmation.value = 'discard';
    } else {
        open.value = false;
    }
}
function contactError(
    index: number,
    field: 'type' | 'value',
): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `contacts.${index}.${field}`
    ];
}
function save(): void {
    if (form.processing) {
        return;
    }
    confirmation.value = 'save';
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
        confirmation.value = null;
        open.value = false;
        return;
    }
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            notice.value = 'Supplier berhasil disimpan.';
        },
        onFinish: () => {
            confirmation.value = null;
        },
    };
    if (editingId.value) {
        form.put(update.url(editingId.value), options);
    } else {
        form.post(store.url(), options);
    }
}
</script>

<template>
    <div class="procurement-page">
        <Head title="Supplier" />
        <div class="page-heading">
            <div>
                <span class="eyebrow">PENGADAAN</span>
                <h1>Supplier<span class="title-dot">.</span></h1>
                <p>Kelola pemasok bahan dan pastry café.</p>
            </div>
            <BaseButton variant="primary" @click="edit()"
                ><AppIcon name="plus" :size="18" />Tambah supplier</BaseButton
            >
        </div>
        <p v-if="notice" class="notice" role="status">{{ notice }}</p>
        <div class="procurement-toolbar">
            <label class="search-field"
                ><AppIcon name="search" :size="18" /><span class="sr-only"
                    >Cari supplier</span
                ><input
                    v-model="query"
                    type="search"
                    placeholder="Cari supplier…"
            /></label>
            <label
                ><span class="sr-only">Status supplier</span
                ><select v-model="status">
                    <option value="all">Semua status</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select></label
            >
        </div>
        <div v-if="visible.length" class="procurement-panel table-scroll">
            <table class="product-table">
                <thead>
                    <tr>
                        <th scope="col">Supplier</th>
                        <th scope="col">Kontak</th>
                        <th scope="col">Status</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="supplier in visible" :key="supplier.id">
                        <td>
                            <strong>{{ supplier.name }}</strong>
                        </td>
                        <td>
                            <template v-if="supplier.contacts?.length">
                                <div
                                    v-for="(
                                        contact, index
                                    ) in supplier.contacts"
                                    :key="index"
                                >
                                    <strong
                                        >{{
                                            contact.type === 'phone'
                                                ? 'Telepon'
                                                : 'Email'
                                        }}:</strong
                                    >
                                    {{ contact.value }}
                                </div>
                            </template>
                            <span v-else>—</span>
                        </td>
                        <td><StatusBadge :active="supplier.is_active" /></td>
                        <td>
                            <BaseButton @click="edit(supplier)"
                                >Edit</BaseButton
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="empty-state">
            <h3>
                {{
                    suppliers.length
                        ? 'Tidak ada supplier yang cocok'
                        : 'Tambahkan supplier pertama'
                }}
            </h3>
            <p>Supplier aktif dapat dipilih saat membuat draft pembelian.</p>
            <BaseButton @click="edit()">Tambah supplier</BaseButton>
        </div>
        <p class="page-note">
            Nonaktifkan supplier untuk menghentikan pembelian baru. Riwayat dan
            draft yang sudah ada tetap tersimpan.
        </p>
        <AppDialog
            :open="open"
            :title="editingId ? 'Edit supplier' : 'Tambah supplier'"
            @close="close"
        >
            <form
                id="supplier-editor"
                class="small-editor"
                @submit.prevent="save"
            >
                <fieldset
                    :disabled="form.processing"
                    style="border: 0; padding: 0; margin: 0"
                >
                    <label class="field" for="supplier-name"
                        >Nama supplier<input
                            id="supplier-name"
                            v-model="form.name"
                            required
                            maxlength="120"
                            autofocus
                            :aria-invalid="Boolean(form.errors.name)"
                    /></label>
                    <p v-if="form.errors.name" class="field-error">
                        {{ form.errors.name }}
                    </p>
                    <div class="section-heading">
                        <h3>Kontak (opsional)</h3>
                        <label class="supplier-add-contact">
                            <AppIcon name="plus" :size="16" />
                            <span class="sr-only">Tambah kontak</span>
                            <select
                                v-model="newContactType"
                                :disabled="form.contacts.length >= 10"
                                @change="addContact"
                            >
                                <option disabled value="">Tambah kontak</option>
                                <option value="phone">Telepon</option>
                                <option value="email">Email</option>
                            </select>
                        </label>
                    </div>
                    <p class="field-help">
                        Isi telepon dan email jika tersedia. Tambah kontak untuk
                        nomor atau email lainnya.
                    </p>
                    <p v-if="form.errors.contacts" class="field-error">
                        {{ form.errors.contacts }}
                    </p>
                    <div
                        v-for="(contact, index) in form.contacts"
                        :key="index"
                        class="supplier-contact-row"
                        :class="{ 'supplier-primary-contact': index < 2 }"
                    >
                        <div>
                            <p
                                v-if="contactError(index, 'type')"
                                class="field-error"
                            >
                                {{ contactError(index, 'type') }}
                            </p>
                            <label
                                class="field"
                                :for="`supplier-contact-${index}`"
                                >{{
                                    contact.type === 'phone'
                                        ? 'Nomor telepon'
                                        : 'Alamat email'
                                }}<input
                                    :id="`supplier-contact-${index}`"
                                    v-model="contact.value"
                                    :type="
                                        contact.type === 'email'
                                            ? 'email'
                                            : 'tel'
                                    "
                                    :inputmode="
                                        contact.type === 'email'
                                            ? 'email'
                                            : 'tel'
                                    "
                                    maxlength="255"
                                    :placeholder="
                                        contact.type === 'email'
                                            ? 'supplier@example.com'
                                            : '+62 812-3456-7890'
                                    "
                                    :aria-invalid="
                                        Boolean(contactError(index, 'value'))
                                    "
                            /></label>
                            <p
                                v-if="contactError(index, 'value')"
                                class="field-error"
                            >
                                {{ contactError(index, 'value') }}
                            </p>
                        </div>
                        <BaseButton
                            v-if="index >= 2"
                            :aria-label="`Hapus kontak ${index + 1}`"
                            @click="
                                form.contacts.splice(index, 1);
                                form.clearErrors();
                            "
                            >Hapus</BaseButton
                        >
                    </div>
                    <label class="check-label"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                        />Supplier aktif</label
                    >
                    <p v-if="form.errors.is_active" class="field-error">
                        {{ form.errors.is_active }}
                    </p>
                </fieldset>
            </form>
            <template #footer
                ><BaseButton :disabled="form.processing" @click="close"
                    >Batal</BaseButton
                ><BaseButton
                    type="submit"
                    form="supplier-editor"
                    variant="primary"
                    :disabled="form.processing"
                    >{{
                        form.processing ? 'Menyimpan…' : 'Simpan supplier'
                    }}</BaseButton
                ></template
            >
        </AppDialog>
        <AppDialog
            :open="confirmation !== null"
            :title="
                confirmation === 'discard'
                    ? editingId
                        ? 'Batalkan perubahan supplier?'
                        : 'Batalkan pembuatan supplier?'
                    : editingId
                      ? 'Simpan perubahan supplier?'
                      : 'Buat supplier baru?'
            "
            @close="closeConfirmation"
        >
            <div class="small-editor">
                <template v-if="confirmation === 'discard'">
                    <p>Perubahan yang belum disimpan akan dibuang.</p>
                    <p v-if="editingId" class="field-help">
                        Data supplier yang sudah tersimpan tetap ada.
                    </p>
                </template>
                <template v-else>
                    <p>
                        {{
                            editingId
                                ? 'Simpan perubahan data supplier berikut?'
                                : 'Tambahkan supplier dengan data berikut?'
                        }}
                    </p>
                    <dl class="supplier-confirmation">
                        <dt>Nama supplier</dt>
                        <dd>{{ form.name.trim() }}</dd>
                        <dt>Kontak</dt>
                        <dd>
                            <template v-if="confirmationContacts.length"
                                ><div
                                    v-for="(
                                        contact, index
                                    ) in confirmationContacts"
                                    :key="index"
                                >
                                    <strong
                                        >{{
                                            contact.type === 'phone'
                                                ? 'Telepon'
                                                : 'Email'
                                        }}:</strong
                                    >
                                    {{ contact.value }}
                                </div></template
                            ><span v-else>Tanpa kontak</span>
                        </dd>
                        <dt>Status</dt>
                        <dd>{{ form.is_active ? 'Aktif' : 'Nonaktif' }}</dd>
                    </dl>
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
                              : editingId
                                ? 'Ya, simpan perubahan'
                                : 'Ya, buat supplier'
                    }}</BaseButton
                >
            </template>
        </AppDialog>
    </div>
</template>

<style scoped>
.supplier-add-contact {
    position: relative;
    display: inline-flex;
    align-items: center;
}
.supplier-add-contact > svg {
    position: absolute;
    left: 10px;
    pointer-events: none;
}
.supplier-add-contact select {
    padding: 9px 12px 9px 32px;
    border: 1px solid var(--line);
    border-radius: 7px;
    background: white;
    font: inherit;
    font-size: 13px;
    cursor: pointer;
}
.supplier-add-contact select:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.supplier-contact-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    margin: 14px 0;
    align-items: start;
}
.supplier-contact-row .button {
    margin-top: 23px;
}
.supplier-primary-contact {
    grid-template-columns: minmax(0, 1fr);
}
.supplier-confirmation dt {
    margin-top: 16px;
    color: #87897f;
    font-size: 12px;
}
.supplier-confirmation dd {
    margin: 6px 0 0;
    overflow-wrap: anywhere;
}
</style>
