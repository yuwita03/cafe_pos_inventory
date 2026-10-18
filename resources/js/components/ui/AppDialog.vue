<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
const props = withDefaults(defineProps<{ open: boolean; title: string; eyebrow?: string; wide?: boolean; drawer?: boolean }>(), { eyebrow: 'CAFÉ POS' });
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement>();
const titleId = useId();
function closeFromBackdrop(event: MouseEvent): void {
    if (!props.drawer || !dialog.value) {
        return;
    }
    const bounds = dialog.value.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
        emit('close');
    }
}
watch(() => props.open, async (open) => {
    await nextTick();
    if (open && !dialog.value?.open) { dialog.value?.showModal(); }
    if (!open && dialog.value?.open) { dialog.value?.close(); }
}, { immediate: true });
onBeforeUnmount(() => dialog.value?.close());
</script>

<template>
    <dialog ref="dialog" class="app-dialog" :class="{ 'dialog-wide': wide, 'dialog-drawer': drawer }" :aria-labelledby="titleId" @cancel.prevent="emit('close')" @click.self="closeFromBackdrop">
        <header class="dialog-header"><div><span class="eyebrow">{{ eyebrow }}</span><h2 :id="titleId">{{ title }}</h2></div><button type="button" class="icon-button" aria-label="Tutup dialog" @click="emit('close')"><AppIcon name="close" /></button></header>
        <div class="dialog-body"><slot /></div>
        <footer v-if="$slots.footer" class="dialog-footer"><slot name="footer" /></footer>
    </dialog>
</template>
