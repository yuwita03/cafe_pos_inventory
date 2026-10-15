<script setup lang="ts">
defineProps<{ active: boolean }>();
</script>

<template>
    <span class="status-badge" :class="{ 'is-active': active }"><span class="status-dot" />{{ active ? 'Aktif' : 'Nonaktif' }}</span>
</template>
