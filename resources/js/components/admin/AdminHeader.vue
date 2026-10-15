<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { adminNavigation } from '@/lib/adminNavigation';
const page = usePage();
const activeItem = computed(() =>
    adminNavigation
        .flatMap((group) => group.items)
        .find((item) => item.href.url() === page.url.split('?')[0]),
);
defineEmits<{ menu: [] }>();
</script>

<template>
    <header class="admin-header">
        <div class="header-breadcrumb">
            <button
                class="icon-button mobile-menu"
                aria-label="Buka navigasi"
                @click="$emit('menu')"
            >
                <AppIcon name="menu" /></button
            ><span>Workspace</span><AppIcon name="arrow" :size="13" /><strong>{{
                activeItem?.label ?? 'Workspace'
            }}</strong>
        </div>
        <span class="preview-pill"
            ><span />{{
                activeItem &&
                ('prototype' in activeItem || 'placeholder' in activeItem)
                    ? 'Prototipe UI'
                    : 'Pengembangan lokal'
            }}</span
        >
    </header>
</template>
