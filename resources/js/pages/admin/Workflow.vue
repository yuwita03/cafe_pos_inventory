<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { workflowModules } from '@/lib/adminNavigation';
import { index as inventory } from '@/routes/admin/inventory';
defineOptions({ layout: AdminLayout });
const props = defineProps<{ module: keyof typeof workflowModules }>();
const data = computed(() => workflowModules[props.module]);
</script>

<template>
    <div class="workflow-page">
        <Head :title="data.title + ' · Alur fitur'" />
        <div class="page-heading">
            <div>
                <span class="eyebrow">{{ data.group }}</span>
                <h1>{{ data.title }}<span class="title-dot">.</span></h1>
                <p>{{ data.description }}</p>
            </div>
            <span class="workflow-placeholder"
                >Placeholder · tahap {{ data.stage }}</span
            >
        </div>
        <div class="prototype-banner">
            <AppIcon name="info" :size="19" />
            <p>
                <strong>Peta alur fitur</strong>Halaman ini menunjukkan tempat
                dan urutan kerja modul berikutnya. Belum ada data, form
                operasional atau transaksi.
            </p>
        </div>
        <section class="workflow-panel">
            <div class="workflow-intro">
                <span class="summary-icon"
                    ><AppIcon :name="data.icon" :size="24"
                /></span>
                <div>
                    <h2>Bagaimana alurnya?</h2>
                    <p>
                        Gambaran sesuai scope V1. Inventory & Recipes adalah
                        tahap aktif saat ini.
                    </p>
                </div>
            </div>
            <ol class="workflow-steps">
                <li v-for="(step, index) in data.steps" :key="step">
                    <span>{{ String(index + 1).padStart(2, '0') }}</span>
                    <p>{{ step }}</p>
                </li>
            </ol>
            <div class="workflow-links">
                <Link :href="inventory()" class="button"
                    ><AppIcon name="back" :size="17" />Inventory aktif</Link
                ><Link :href="data.next()" class="button button-primary"
                    >{{ data.nextLabel }}<AppIcon name="arrow" :size="17"
                /></Link>
            </div>
        </section>
        <p class="page-note">
            <AppIcon name="info" :size="16" />Placeholder disediakan untuk
            melihat navigasi dan alur. Implementasi modul mengikuti urutan tahap
            proyek.
        </p>
    </div>
</template>

<style scoped>
.workflow-placeholder {
    padding: 7px 11px;
    border: 1px solid #eadfc8;
    border-radius: 7px;
    color: #79643a;
    background: #fffcf5;
    font-size: 12px;
    white-space: nowrap;
}
.workflow-panel {
    max-width: 900px;
    padding: 28px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: white;
}
.workflow-intro {
    display: flex;
    gap: 14px;
    align-items: center;
}
.workflow-intro h2 {
    font-size: 18px;
    font-weight: 600;
}
.workflow-intro p {
    margin-top: 5px;
    color: var(--muted);
}
.workflow-steps {
    list-style: none;
    padding: 0;
    margin: 26px 0;
}
.workflow-steps li {
    display: flex;
    gap: 18px;
    align-items: center;
    padding: 20px 0;
    border-top: 1px solid var(--line);
}
.workflow-steps li > span {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    flex-shrink: 0;
    border-radius: 8px;
    background: var(--soft);
    color: var(--primary);
    font-size: 12px;
    font-weight: 600;
}
.workflow-links {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding-top: 20px;
    border-top: 1px solid var(--line);
}
@media (max-width: 767px) {
    .workflow-panel {
        padding: 20px 16px;
    }
    .workflow-links .button {
        width: 100%;
    }
    .workflow-intro {
        align-items: flex-start;
    }
}
</style>
