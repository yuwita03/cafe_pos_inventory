<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import { dashboard } from '@/routes/admin';
const page = usePage();
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
                page.url.split('?')[0] === dashboard.url()
                    ? 'Dashboard'
                    : 'Pengelolaan menu'
            }}</strong>
        </div>
        <span class="preview-pill"
            ><span />{{
                page.url.split('?')[0] === dashboard.url()
                    ? 'Prototipe UI'
                    : 'Pengembangan lokal'
            }}</span
        >
    </header>
</template>
