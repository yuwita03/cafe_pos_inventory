<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TablePagination from '@/components/ui/TablePagination.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { staff as staffRoute } from '@/routes/admin';
import { store, update } from '@/routes/admin/staff';
import type { User } from '@/types';
import type { TablePagination as Pagination } from '@/types/menu';

type Staff = Pick<User, 'id' | 'name' | 'email' | 'role' | 'is_active'>;
defineOptions({ layout: AdminLayout });
const props = defineProps<{
    staff: Staff[];
    filters: { q: string; role: string; status: string; per_page: number };
    pagination: Pagination;
    status: string | null;
}>();
const page = usePage();
const filters = ref({ ...props.filters });
watch(
    () => props.filters,
    (value) => {
        filters.value = { ...value };
    },
);
const busy = ref(false);
const open = ref(false);
const editing = ref<Staff | null>(null);
const confirmation = ref<'deactivate' | 'discard' | null>(null);
const isSelf = computed(() => editing.value?.id === page.props.auth.user?.id);
const form = useForm({
    name: '',
    email: '',
    role: 'cashier' as User['role'],
    is_active: true,
    password: '',
    password_confirmation: '',
});
const roleDescriptions: Record<User['role'], string> = {
    owner: 'Semua area: dashboard, menu, stok, pengadaan, meja, staf, POS, dan kitchen.',
    cashier:
        'Kasir POS, pesanan yang diizinkan, dan konfirmasi pembayaran tunai.',
    kitchen: 'Antrean kitchen dan pembaruan status persiapan pesanan.',
};
const roleLabels = { owner: 'Owner', cashier: 'Cashier', kitchen: 'Kitchen' };

function filter(perPage?: number): void {
    if (perPage) {
        filters.value.per_page = perPage;
    }
    router.get(staffRoute.url(), filters.value, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
            busy.value = true;
        },
        onFinish: () => {
            busy.value = false;
        },
    });
}
function edit(staff?: Staff): void {
    editing.value = staff ?? null;
    form.defaults({
        name: staff?.name ?? '',
        email: staff?.email ?? '',
        role: staff?.role ?? 'cashier',
        is_active: staff?.is_active ?? true,
        password: '',
        password_confirmation: '',
    });
    form.reset();
    form.clearErrors();
    confirmation.value = null;
    open.value = true;
}
function close(): void {
    if (form.processing) {
        return;
    }
    if (form.isDirty) {
        confirmation.value = 'discard';
        return;
    }
    open.value = false;
    form.reset('password', 'password_confirmation');
}
function save(): void {
    if (form.processing) {
        return;
    }
    if (editing.value?.is_active && !form.is_active) {
        confirmation.value = 'deactivate';
        return;
    }
    submit();
}
function submit(): void {
    if (form.processing) {
        return;
    }
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset('password', 'password_confirmation');
        },
        onFinish: () => {
            confirmation.value = null;
        },
    };
    if (editing.value) {
        form.put(update.url(editing.value.id), options);
    } else {
        form.post(store.url(), options);
    }
}
function discard(): void {
    confirmation.value = null;
    open.value = false;
    form.reset();
    form.clearErrors();
}
</script>

<template>
    <div class="staff-page">
        <Head title="Akses staf" />
        <div class="page-heading">
            <div>
                <span class="eyebrow">AKSES STAF</span>
                <h1>Staf &amp; login<span class="title-dot">.</span></h1>
                <p>Kelola akun, role, dan status akses tim café.</p>
            </div>
            <BaseButton variant="primary" @click="edit()"
                ><AppIcon name="plus" :size="18" />Tambah staf</BaseButton
            >
        </div>
        <p v-if="status" class="notice" role="status">{{ status }}</p>
        <section class="staff-panel" aria-label="Daftar staf" :aria-busy="busy">
            <form
                class="catalog-toolbar staff-toolbar"
                @submit.prevent="filter()"
            >
                <label class="search-field"
                    ><AppIcon name="search" :size="18" /><span class="sr-only"
                        >Cari nama atau email staf</span
                    ><input
                        v-model="filters.q"
                        type="search"
                        maxlength="120"
                        placeholder="Cari nama atau email…"
                /></label>
                <div class="toolbar-filters">
                    <label
                        ><span class="sr-only">Role staf</span
                        ><select v-model="filters.role" @change="filter()">
                            <option value="all">Semua role</option>
                            <option value="owner">Owner</option>
                            <option value="cashier">Cashier</option>
                            <option value="kitchen">Kitchen</option>
                        </select></label
                    >
                    <label
                        ><span class="sr-only">Status staf</span
                        ><select v-model="filters.status" @change="filter()">
                            <option value="all">Semua status</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select></label
                    >
                    <BaseButton type="submit" :disabled="busy">{{
                        busy ? 'Memuat…' : 'Cari'
                    }}</BaseButton>
                </div>
            </form>
            <div class="table-scroll">
                <table class="product-table">
                    <thead>
                        <tr>
                            <th scope="col">NAMA</th>
                            <th scope="col">EMAIL</th>
                            <th scope="col">ROLE</th>
                            <th scope="col">STATUS</th>
                            <th scope="col">
                                <span class="sr-only">Aksi</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="staff in props.staff" :key="staff.id">
                            <td>
                                <strong>{{ staff.name }}</strong
                                ><small
                                    v-if="staff.id === page.props.auth.user?.id"
                                    class="staff-self"
                                    >Akun Anda</small
                                >
                            </td>
                            <td>{{ staff.email }}</td>
                            <td>{{ roleLabels[staff.role] }}</td>
                            <td><StatusBadge :active="staff.is_active" /></td>
                            <td>
                                <BaseButton
                                    :aria-label="'Edit akun ' + staff.name"
                                    @click="edit(staff)"
                                    >Edit</BaseButton
                                >
                            </td>
                        </tr>
                        <tr v-if="!props.staff.length">
                            <td colspan="5" class="staff-empty">
                                Tidak ada staf yang sesuai. Ubah pencarian atau
                                tambahkan akun baru.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <TablePagination
                :pagination="pagination"
                :busy="busy"
                noun="staf"
                @limit="filter"
            />
        </section>
        <p class="field-help staff-note">
            Role menentukan area yang dapat diakses. Akun nonaktif tidak dapat
            masuk; riwayat transaksi tetap tersimpan.
        </p>

        <AppDialog
            :open="open"
            :title="editing ? 'Edit akun staf' : 'Tambah staf'"
            eyebrow="AKSES STAF"
            @close="close"
        >
            <form
                id="staff-editor"
                class="staff-form editor-fields"
                @submit.prevent="save"
            >
                <fieldset :disabled="form.processing">
                    <label class="field"
                        ><span>Nama staf</span
                        ><input
                            v-model="form.name"
                            autocomplete="name"
                            required
                            maxlength="255"
                            :aria-invalid="!!form.errors.name"
                        /><small
                            v-if="form.errors.name"
                            class="field-error"
                            role="alert"
                            >{{ form.errors.name }}</small
                        ></label
                    >
                    <label class="field"
                        ><span>Email</span
                        ><input
                            v-model="form.email"
                            type="email"
                            autocomplete="off"
                            required
                            maxlength="255"
                            :aria-invalid="!!form.errors.email"
                        /><small
                            v-if="form.errors.email"
                            class="field-error"
                            role="alert"
                            >{{ form.errors.email }}</small
                        ></label
                    >
                    <label class="field"
                        ><span>Role</span
                        ><select
                            v-model="form.role"
                            :disabled="isSelf"
                            aria-describedby="staff-role-help"
                            :aria-invalid="!!form.errors.role"
                        >
                            <option value="owner">Owner</option>
                            <option value="cashier">Cashier</option>
                            <option value="kitchen">Kitchen</option></select
                        ><small id="staff-role-help" class="field-help">{{
                            roleDescriptions[form.role]
                        }}</small
                        ><small
                            v-if="form.errors.role"
                            class="field-error"
                            role="alert"
                            >{{ form.errors.role }}</small
                        ></label
                    >
                    <label class="check-label"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                            :disabled="isSelf"
                        />Akun aktif</label
                    >
                    <p v-if="isSelf" class="field-help">
                        Perubahan role atau penonaktifan akun Anda harus
                        dilakukan owner lain.
                    </p>
                    <p
                        v-if="form.errors.is_active"
                        class="field-error"
                        role="alert"
                    >
                        {{ form.errors.is_active }}
                    </p>
                    <div class="form-section">
                        <label class="field"
                            ><span>{{
                                editing
                                    ? 'Kata sandi baru (opsional)'
                                    : 'Kata sandi'
                            }}</span
                            ><input
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                :required="!editing"
                                minlength="8"
                                :aria-invalid="!!form.errors.password"
                            /><small class="field-help">{{
                                editing
                                    ? 'Kosongkan untuk mempertahankan kata sandi. Reset mengakhiri sesi lain akun ini.'
                                    : 'Minimal 8 karakter.'
                            }}</small
                            ><small
                                v-if="form.errors.password"
                                class="field-error"
                                role="alert"
                                >{{ form.errors.password }}</small
                            ></label
                        >
                        <label class="field"
                            ><span>Konfirmasi kata sandi</span
                            ><input
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                :required="!!form.password"
                                :aria-invalid="
                                    !!form.errors.password_confirmation
                                "
                            /><small
                                v-if="form.errors.password_confirmation"
                                class="field-error"
                                role="alert"
                                >{{ form.errors.password_confirmation }}</small
                            ></label
                        >
                    </div>
                </fieldset>
            </form>
            <template #footer
                ><BaseButton :disabled="form.processing" @click="close"
                    >Batal</BaseButton
                ><BaseButton
                    type="submit"
                    form="staff-editor"
                    variant="primary"
                    :disabled="form.processing"
                    >{{ form.processing ? 'Menyimpan…' : 'Simpan' }}</BaseButton
                ></template
            >
        </AppDialog>
        <AppDialog
            :open="confirmation !== null"
            :title="
                confirmation === 'discard'
                    ? 'Buang perubahan?'
                    : 'Nonaktifkan akun staf?'
            "
            @close="!form.processing && (confirmation = null)"
        >
            <p v-if="confirmation === 'discard'">
                Perubahan yang belum disimpan akan dibuang.
            </p>
            <p v-else>
                <strong>{{ editing?.name }}</strong> tidak dapat masuk dan akan
                keluar pada permintaan berikutnya. Riwayat transaksi tetap
                tersimpan.
            </p>
            <template #footer
                ><BaseButton
                    :disabled="form.processing"
                    @click="confirmation = null"
                    >{{
                        confirmation === 'discard' ? 'Lanjut edit' : 'Batal'
                    }}</BaseButton
                ><BaseButton
                    variant="danger"
                    :disabled="form.processing"
                    @click="confirmation === 'discard' ? discard() : submit()"
                    >{{
                        form.processing
                            ? 'Menyimpan…'
                            : confirmation === 'discard'
                              ? 'Buang perubahan'
                              : 'Nonaktifkan'
                    }}</BaseButton
                ></template
            >
        </AppDialog>
    </div>
</template>

<style scoped>
.staff-panel {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 12px;
    overflow: hidden;
}
.staff-form fieldset {
    border: 0;
    margin: 0;
    padding: 0;
    min-width: 0;
}
.staff-form .form-section {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--line);
}
.staff-self {
    display: block;
    color: var(--muted);
    margin-top: 3px;
}
.staff-empty {
    text-align: center;
    color: var(--muted);
    padding: 40px 20px !important;
}
.staff-note {
    margin-top: 16px;
}
@media (max-width: 767px) {
    .staff-toolbar {
        flex-direction: column;
        align-items: stretch;
        padding: 16px;
    }
    .staff-toolbar .search-field {
        max-width: none;
    }
    .staff-toolbar .toolbar-filters {
        flex-wrap: wrap;
    }
    .staff-toolbar .toolbar-filters label {
        flex: 1;
    }
    .staff-toolbar select {
        width: 100%;
        max-width: none;
    }
    .staff-toolbar input,
    .staff-form input,
    .staff-form select {
        font-size: 16px;
    }
    .staff-toolbar input,
    .staff-toolbar select,
    .staff-toolbar .button,
    .staff-page .product-table .button {
        min-height: 44px;
    }
}
</style>
