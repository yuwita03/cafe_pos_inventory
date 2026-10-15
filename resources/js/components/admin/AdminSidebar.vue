<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, useId, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { categories, dashboard, menu, options } from '@/routes/admin';
const emit = defineEmits<{ navigate: [] }>();
const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);
const menuExpanded = ref(false);
const menuNavigationId = useId();
const menuNavigation = [
    {
        label: 'Produk',
        description: 'Menu & varian',
        icon: 'coffee',
        href: menu,
    },
    {
        label: 'Kategori',
        description: 'Kelompok menu',
        icon: 'category',
        href: categories,
    },
    {
        label: 'Grup opsi',
        description: 'Pilihan pelanggan',
        icon: 'options',
        href: options,
    },
];
watch(
    currentPath,
    (path) => {
        menuExpanded.value = menuNavigation.some(
            (item) => path === item.href.url(),
        );
    },
    { immediate: true },
);
</script>

<template>
    <div class="sidebar-inner">
        <div class="brand">
            <span class="brand-mark"><AppIcon name="coffee" :size="25" /></span>
            <div>Café POS<span>Ruang pengelolaan</span></div>
        </div>
        <div class="workspace-label">
            <span class="workspace-dot" />Admin workspace<span
                class="workspace-version"
                >V1</span
            >
        </div>
        <nav aria-label="Navigasi admin">
            <p class="nav-heading">WORKSPACE</p>
            <Link
                :href="dashboard()"
                class="nav-item"
                :class="{ selected: currentPath === dashboard.url() }"
                :aria-current="
                    currentPath === dashboard.url() ? 'page' : undefined
                "
                @click="emit('navigate')"
            >
                <AppIcon name="grid" />
                <span>Dashboard<small>Ringkasan operasional</small></span>
                <AppIcon name="arrow" :size="14" />
            </Link>
            <button
                type="button"
                class="nav-item nav-group-toggle"
                :aria-expanded="menuExpanded"
                :aria-controls="menuNavigationId"
                @click="menuExpanded = !menuExpanded"
            >
                <AppIcon name="coffee" />
                <span>Kelola Menu<small>Katalog & pilihan</small></span>
                <AppIcon
                    name="arrow"
                    :size="14"
                    class="nav-group-arrow"
                    :class="{ expanded: menuExpanded }"
                />
            </button>
            <div
                v-show="menuExpanded"
                :id="menuNavigationId"
                class="nav-submenu"
            >
                <Link
                    v-for="item in menuNavigation"
                    :key="item.label"
                    :href="item.href()"
                    class="nav-item nav-child"
                    :class="{ selected: currentPath === item.href.url() }"
                    :aria-current="
                        currentPath === item.href.url() ? 'page' : undefined
                    "
                    @click="emit('navigate')"
                >
                    <AppIcon :name="item.icon" :size="17" />
                    <span>
                        {{ item.label }}<small>{{ item.description }}</small>
                    </span>
                </Link>
            </div>
        </nav>
        <div class="sidebar-bottom">
            <div class="prototype-note">
                <AppIcon name="info" :size="18" />
                <p>
                    <strong>{{
                        page.url.split('?')[0] === dashboard.url()
                            ? 'Pratinjau dashboard'
                            : 'Pengembangan lokal'
                    }}</strong
                    >{{
                        page.url.split('?')[0] === dashboard.url()
                            ? 'Dashboard menggunakan data contoh.'
                            : 'Menu tersimpan di database. Login staf belum diaktifkan.'
                    }}
                </p>
            </div>
            <div class="sidebar-profile">
                <span class="avatar">O</span>
                <div>Owner<small>Profil contoh · Admin</small></div>
                <span class="profile-dot" />
            </div>
        </div>
    </div>
</template>
