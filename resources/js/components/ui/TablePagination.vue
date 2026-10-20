<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import BaseButton from "@/components/ui/BaseButton.vue";
import type { TablePagination } from "@/types/menu";

withDefaults(defineProps<{ pagination: TablePagination; busy?: boolean; noun?: string }>(), { noun: 'produk' });
defineEmits<{ limit: [value: number] }>();
</script>

<template>
    <footer class="table-pagination">
        <span aria-live="polite"
            >Menampilkan <strong>{{ pagination.from ?? 0 }}–{{ pagination.to ?? 0 }}</strong> dari
            <strong>{{ pagination.total }}</strong> {{ noun }}</span
        >
        <div class="pagination-controls">
            <label
                >Per halaman<select
                    :value="pagination.per_page"
                    :disabled="busy"
                    @change="$emit('limit', Number(($event.target as HTMLSelectElement).value))"
                >
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                </select></label
            >
            <nav :aria-label="'Pagination ' + noun">
                <Link
                    v-if="pagination.prev_page_url && !busy"
                    :href="pagination.prev_page_url"
                    preserve-state
                    preserve-scroll
                    class="button"
                    >Sebelumnya</Link
                ><BaseButton v-else disabled>Sebelumnya</BaseButton>
                <span>Halaman {{ pagination.current_page }} / {{ pagination.last_page }}</span>
                <Link
                    v-if="pagination.next_page_url && !busy"
                    :href="pagination.next_page_url"
                    preserve-state
                    preserve-scroll
                    class="button"
                    >Berikutnya</Link
                ><BaseButton v-else disabled>Berikutnya</BaseButton>
            </nav>
        </div>
    </footer>
</template>

<style scoped>
.table-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    border-top: 1px solid var(--line);
    padding: 16px 23px;
    color: var(--muted);
    font-size: 12px;
}
.table-pagination strong {
    font-weight: 550;
    color: var(--ink);
}
.pagination-controls,
.pagination-controls nav,
.pagination-controls label {
    display: flex;
    align-items: center;
    gap: 10px;
}
.pagination-controls {
    flex-wrap: wrap;
    gap: 18px;
}
.pagination-controls select {
    padding: 7px 9px;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: white;
    color: var(--ink);
    min-height: 38px;
}
.pagination-controls nav > span {
    font-size: 11px;
    white-space: nowrap;
}
.pagination-controls .button {
    min-height: 38px;
    padding: 7px 10px;
    text-decoration: none;
}
@media (max-width: 767px) {
    .table-pagination {
        padding: 16px;
    }
    .pagination-controls {
        width: 100%;
        justify-content: space-between;
    }
    .pagination-controls nav {
        width: 100%;
        justify-content: space-between;
        gap: 6px;
    }
    .pagination-controls .button,
    .pagination-controls select {
        min-height: 44px;
    }
}
</style>
