import { ref } from 'vue';

export function useHorizontalDrag() {
    const isDragging = ref(false);
    let drag: {
        pointerId: number;
        startX: number;
        scrollLeft: number;
    } | null = null;
    let suppressClick = false;

    function start(event: PointerEvent): void {
        suppressClick = false;
        if (event.pointerType !== 'mouse' || event.button !== 0) {
            return;
        }
        const row = event.currentTarget as HTMLElement;
        drag = {
            pointerId: event.pointerId,
            startX: event.clientX,
            scrollLeft: row.scrollLeft,
        };
    }

    function move(event: PointerEvent): void {
        if (!drag || event.pointerId !== drag.pointerId) {
            return;
        }
        const delta = event.clientX - drag.startX;
        if (!isDragging.value && Math.abs(delta) < 6) {
            return;
        }
        const row = event.currentTarget as HTMLElement;
        row.setPointerCapture(event.pointerId);
        isDragging.value = true;
        suppressClick = true;
        row.scrollLeft = drag.scrollLeft - delta;
        event.preventDefault();
    }

    function stop(): void {
        drag = null;
        isDragging.value = false;
    }

    function preventClick(event: MouseEvent): void {
        if (suppressClick) {
            event.preventDefault();
            event.stopPropagation();
            suppressClick = false;
        }
    }
    return { isDragging, start, move, stop, preventClick };
}
