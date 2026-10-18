<script setup lang="ts">
import { computed } from 'vue';
import { formatRupiah as rupiah } from '@/lib/utils';
import type { PosProduct } from '@/types/pos';

const props = defineProps<{
    variants: PosProduct['variants'];
    formId: string;
    error?: string;
}>();
const selectedVariantId = defineModel<number>({ required: true });
const regularOnly = computed(
    () => props.variants.length === 1 && props.variants[0]?.name === 'Regular',
);
</script>

<template>
    <fieldset
        v-if="!regularOnly"
        class="pos-choice-group"
        tabindex="-1"
        :aria-invalid="Boolean(error)"
        :aria-describedby="error ? formId + '-variant-error' : undefined"
    >
        <legend>Varian <span class="pos-required-label">Wajib</span></legend>
        <div class="pos-choice-grid">
            <label
                v-for="item in variants"
                :key="item.id"
                :class="{
                    selected: selectedVariantId === item.id,
                    unavailable: !item.available,
                }"
            >
                <input
                    v-model="selectedVariantId"
                    type="radio"
                    :name="formId + '-variant'"
                    :value="item.id"
                    :disabled="!item.available"
                />
                <span
                    >{{ item.name
                    }}<small>{{
                        item.available ? rupiah(item.price) : 'Tidak tersedia'
                    }}</small></span
                >
            </label>
        </div>
        <p
            v-if="error"
            :id="formId + '-variant-error'"
            class="pos-error"
            role="alert"
        >
            {{ error }}
        </p>
    </fieldset>
</template>
