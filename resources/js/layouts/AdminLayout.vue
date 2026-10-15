<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import '../../css/admin.css';
import AdminHeader from '@/components/admin/AdminHeader.vue';
import AdminSidebar from '@/components/admin/AdminSidebar.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
const menuOpen = ref(false);
let desktopQuery: MediaQueryList | undefined;
function closeOnDesktop(event: MediaQueryListEvent): void {
    if (event.matches) {
        menuOpen.value = false;
    }
}
onMounted(() => {
    desktopQuery = window.matchMedia('(min-width: 1280px)');
    desktopQuery.addEventListener('change', closeOnDesktop);
});
onBeforeUnmount(() =>
    desktopQuery?.removeEventListener('change', closeOnDesktop),
);
</script>

<template>
    <div class="admin-shell" lang="id">
        <a class="skip-link" href="#main-content">Lewati ke konten</a>
        <aside class="desktop-sidebar"><AdminSidebar /></aside>
        <div class="admin-workspace">
            <AdminHeader @menu="menuOpen = true" />
            <main id="main-content" class="admin-content"><slot /></main>
            <footer class="workspace-footer">
                <span
                    >Café POS <span class="footer-dot">·</span> Admin
                    workspace</span
                ><span>Dirancang untuk operasional sehari-hari.</span>
            </footer>
        </div>
        <AppDialog
            drawer
            :open="menuOpen"
            title="Navigasi admin"
            @close="menuOpen = false"
            ><AdminSidebar @navigate="menuOpen = false"
        /></AppDialog>
    </div>
</template>
