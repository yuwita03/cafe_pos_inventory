<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { formatRupiah as rupiah } from '@/lib/utils';
import { price } from '@/routes/admin/menu';
import type { OptionGroup, Product } from '@/types/menu';

const props = defineProps<{ product: Product; groups: OptionGroup[] }>();
defineEmits<{ close: [] }>();
const variantId = ref(
    props.product.variants.find((variant) => variant.available)?.id ??
        props.product.variants[0]?.id ??
        0,
);
const quantity = ref(1);
const selections = ref<Record<number, number[]>>({});
const processing = ref(false);
const error = ref('');
const result = ref<{ total_idr: number; unit_price_idr: number } | null>(null);
const controller = new AbortController();
onBeforeUnmount(() => controller.abort());
const assignedGroups = computed(() =>
    props.product.groups
        .map((assignment) => ({
            ...props.groups.find((group) => group.id === assignment.id),
            required: assignment.required,
        }))
        .filter((group) => group.id && group.active),
);
watch(
    [variantId, quantity, selections],
    () => {
        result.value = null;
    },
    { deep: true },
);
async function checkPrice(): Promise<void> {
    if (processing.value) {
        return;
    }
    processing.value = true;
    error.value = '';
    result.value = null;
    try {
        const token =
            document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.slice(11) ?? '';
        const response = await fetch(price.url(), {
            method: 'POST',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(token),
            },
            body: JSON.stringify({
                variant_id: variantId.value,
                option_ids: Object.values(selections.value).flat(),
                quantity: quantity.value,
            }),
        });
        const payload = (await response.json()) as {
            total_idr: number;
            unit_price_idr: number;
            errors?: Record<string, string[]>;
        };
        if (!response.ok) {
            error.value = payload.errors
                ? Object.values(payload.errors).flat().join(' ')
                : 'Harga tidak dapat diperiksa. Muat ulang halaman dan coba lagi.';
            return;
        }
        result.value = payload;
    } catch (exception) {
        if (
            !(
                exception instanceof DOMException &&
                exception.name === 'AbortError'
            )
        ) {
            error.value = 'Koneksi gagal. Coba periksa harga kembali.';
        }
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <AppDialog
        open
        :title="`Cek harga · ${product.name}`"
        @close="$emit('close')"
    >
        <form id="price-form" class="small-editor" @submit.prevent="checkPrice">
            <p class="form-intro">
                Periksa harga resmi dari server. Ini tidak membuat pesanan atau
                memeriksa stok.
            </p>
            <fieldset
                :disabled="processing"
                style="border: 0; padding: 0; margin: 0"
            >
                <label v-if="product.variants.length > 1" class="field"
                    >Varian<select v-model="variantId">
                        <option
                            v-for="variant in product.variants"
                            :key="variant.id"
                            :value="variant.id"
                            :disabled="!variant.available"
                        >
                            {{ variant.name
                            }}{{ variant.available ? '' : ' (tidak tersedia)' }}
                        </option>
                    </select></label
                >
                <section
                    v-for="group in assignedGroups"
                    :key="group.id"
                    class="form-section"
                >
                    <h3>
                        {{ group.name }}<span v-if="group.required">Wajib</span>
                    </h3>
                    <label
                        v-for="option in group.options?.filter(
                            (item) => item.active,
                        )"
                        :key="option.id"
                        class="check-label"
                        style="margin-bottom: 12px"
                    >
                        <input
                            v-if="group.selection === 'single'"
                            type="radio"
                            :name="`group-${group.id}`"
                            :checked="
                                selections[group.id!]?.includes(option.id!)
                            "
                            @change="selections[group.id!] = [option.id!]"
                        />
                        <input
                            v-else
                            v-model="selections[group.id!]"
                            type="checkbox"
                            :value="option.id"
                            @focus="selections[group.id!] ??= []"
                        />
                        {{ option.name }} ·
                        {{
                            option.price
                                ? '+' + rupiah(option.price)
                                : 'Tanpa tambahan'
                        }}
                    </label>
                    <BaseButton
                        v-if="!group.required && group.selection === 'single'"
                        variant="ghost"
                        @click="selections[group.id!] = []"
                        >Tanpa opsi</BaseButton
                    >
                </section>
                <label class="field" style="margin-top: 20px"
                    >Jumlah<input
                        v-model.number="quantity"
                        type="number"
                        min="1"
                        max="2147483647"
                        step="1"
                        required
                /></label>
            </fieldset>
            <p v-if="error" class="field-error" role="alert">{{ error }}</p>
            <div v-if="result" class="notice" role="status">
                <div>
                    Harga per item:
                    <strong>{{ rupiah(result.unit_price_idr) }}</strong
                    ><br />Total dari server:
                    <strong>{{ rupiah(result.total_idr) }}</strong>
                </div>
            </div>
        </form>
        <template #footer
            ><BaseButton @click="$emit('close')">Tutup</BaseButton
            ><BaseButton
                form="price-form"
                type="submit"
                variant="primary"
                :disabled="processing"
                >{{ processing ? 'Memeriksa…' : 'Cek harga' }}</BaseButton
            ></template
        >
    </AppDialog>
</template>
