<script setup lang="ts">
withDefaults(defineProps<{ name: string; size?: number }>(), { size: 20 });
const paths: Record<string, string> = {
    coffee: 'M4 8h12v7a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8Zm12 1h2a3 3 0 1 1 0 6h-2M7 3v2m4-2v2M3 22h15',
    grid: 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
    menu: 'M4 5h16M4 12h16M4 19h16',
    category: 'M3 4h7l2 3h9v13H3V4Z',
    options: 'M4 6h16M4 12h16M4 18h16M8 3v6m8 0v6m-6 0v6',
    plus: 'M12 5v14M5 12h14',
    search: 'M21 21l-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z',
    arrow: 'm9 5 7 7-7 7',
    edit: 'm15 5 4 4M4 16 16 4a2 2 0 0 1 4 4L8 20H4v-4Z',
    close: 'm6 6 12 12M6 18 18 6',
    image: 'M3 3h18v18H3zM3 16l5-5 4 4 3-3 6 6M8 7h.01',
    upload: 'M12 16V3m-5 5 5-5 5 5M4 16v5h16v-5',
    check: 'm5 12 4 4L19 6',
    back: 'M19 12H5m6-6-6 6 6 6',
    trash: 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7',
    info: 'M12 11v6m0-10h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
    history: 'M3 11a9 9 0 1 1 2 7M3 4v7h7M12 7v5l3 2',
    purchase: 'M3 4h2l3 12h11l3-9H6M9 21h.01M18 21h.01',
    orders: 'M6 3h12v19l-3-2-3 2-3-2-3 2V3ZM9 7h6M9 11h6M9 15h3',
    pos: 'M3 3h18v13H3zM8 21h8M12 16v5M7 7h10M7 11h5',
    staff: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 3a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
};
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.65"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path :d="paths[name] ?? paths.grid" />
    </svg>
</template>
