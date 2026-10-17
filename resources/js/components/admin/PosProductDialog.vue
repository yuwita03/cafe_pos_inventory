<script setup lang="ts">
import { computed, nextTick, ref, useId, watch } from 'vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { formatRupiah as rupiah } from '@/lib/utils';
import { type PosGroup, type CartLine, type PosProduct } from '@/types/pos';

const props = defineProps<{
    open: boolean;
    product: PosProduct | null;
    line: CartLine | null;
    groups: PosGroup[];
}>();
const emit = defineEmits<{ close: []; save: [line: CartLine] }>();
const formId = useId();
const formElement = ref<HTMLFormElement>();
const variantId = ref(0);
const quantity = ref(1);
const single = ref<Record<number, number | undefined>>({});
const multiple = ref<Record<number, number[]>>({});
const errors = ref<Record<string, string>>({});
const assigned = computed(() =>
    props.groups.filter((group) =>
        props.product?.groups.some((assignment) => assignment.id === group.id),
    ),
);
const variant = computed(() =>
    props.product?.variants.find((item) => item.id === variantId.value),
);
const selectedOptions = computed(() =>
    assigned.value.flatMap((group) =>
        group.options.filter((option) =>
            group.selection === 'single'
                ? option.id === single.value[group.id]
                : multiple.value[group.id]?.includes(option.id),
        ),
    ),
);
const unitPrice = computed(
    () =>
        (variant.value?.price ?? 0) +
        selectedOptions.value.reduce((sum, option) => sum + option.price, 0),
);
const regularOnly = computed(
    () =>
        props.product?.variants.length === 1 &&
        props.product.variants[0]?.name === 'Regular',
);

watch(
    () => [props.open, props.product, props.line] as const,
    ([open, product, line]) => {
        if (!open || !product) {
            return;
        }
        variantId.value =
            line?.variantId ??
            (product.variants.length === 1 ? product.variants[0]!.id : 0);
        quantity.value = line?.quantity ?? 1;
        single.value = {};
        multiple.value = {};
        errors.value = {};
        for (const group of assigned.value) {
            single.value[group.id] = line?.optionIds.find((id) =>
                group.options.some((option) => option.id === id),
            );
            multiple.value[group.id] =
                line?.optionIds.filter((id) =>
                    group.options.some((option) => option.id === id),
                ) ?? [];
        }
    },
    { immediate: true },
);

async function save(): Promise<void> {
    const product = props.product;
    if (!product) {
        return;
    }
    errors.value = {};
    if (!variant.value?.available) {
        errors.value.variant = 'Pilih varian yang tersedia.';
    }
    for (const group of assigned.value) {
        if (
            product.groups.find((assignment) => assignment.id === group.id)
                ?.required &&
            !selectedOptions.value.some((option) =>
                group.options.some((candidate) => candidate.id === option.id),
            )
        ) {
            errors.value[group.id] = `Pilih ${group.name} terlebih dahulu.`;
        }
    }
    if (
        !Number.isSafeInteger(quantity.value) ||
        quantity.value < 1 ||
        !Number.isSafeInteger(unitPrice.value * quantity.value)
    ) {
        errors.value.quantity = 'Masukkan jumlah bulat minimal 1 yang valid.';
    }
    if (Object.keys(errors.value).length || !variant.value) {
        await nextTick();
        formElement.value
            ?.querySelector<HTMLElement>('[aria-invalid="true"]')
            ?.focus();
        return;
    }
    const optionIds = selectedOptions.value
        .map((option) => option.id)
        .sort((a, b) => a - b);
    emit('save', {
        key: [product.id, variant.value.id, ...optionIds].join(':'),
        productId: product.id,
        variantId: variant.value.id,
        optionIds,
        productName: product.name,
        variantName: variant.value.name,
        optionNames: selectedOptions.value.map((option) => option.name),
        unitPrice: unitPrice.value,
        quantity: quantity.value,
    });
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="line ? 'Ubah pesanan' : 'Pilih pesanan'"
        @close="emit('close')"
    >
        <form
            v-if="product"
            :id="formId"
            ref="formElement"
            class="pos-config"
            novalidate
            @submit.prevent="save"
        >
            <div class="pos-config-intro">
                <span class="pos-kicker">MENU CONTOH</span>
                <h3>{{ product.name }}</h3>
                <p>{{ product.description }}</p>
            </div>
            <fieldset
                v-if="!regularOnly"
                class="pos-choice-group"
                tabindex="-1"
                :aria-invalid="Boolean(errors.variant)"
                :aria-describedby="
                    errors.variant ? formId + '-variant-error' : undefined
                "
            >
                <legend>
                    Varian <span class="pos-required-label">Wajib</span>
                </legend>
                <div class="pos-choice-grid">
                    <label
                        v-for="item in product.variants"
                        :key="item.id"
                        :class="{
                            selected: variantId === item.id,
                            unavailable: !item.available,
                        }"
                    >
                        <input
                            v-model="variantId"
                            type="radio"
                            :name="formId + '-variant'"
                            :value="item.id"
                            :disabled="!item.available"
                        />
                        <span
                            >{{ item.name
                            }}<small>{{
                                item.available
                                    ? rupiah(item.price)
                                    : 'Tidak tersedia'
                            }}</small></span
                        >
                    </label>
                </div>
                <p
                    v-if="errors.variant"
                    :id="formId + '-variant-error'"
                    class="pos-error"
                    role="alert"
                >
                    {{ errors.variant }}
                </p>
            </fieldset>
            <fieldset
                v-for="group in assigned"
                :key="group.id"
                class="pos-choice-group"
                tabindex="-1"
                :aria-invalid="Boolean(errors[group.id])"
                :aria-describedby="
                    errors[group.id] ? formId + '-group-' + group.id : undefined
                "
            >
                <legend>
                    {{ group.name
                    }}<span class="pos-required-label">{{
                        product.groups.find(
                            (assignment) => assignment.id === group.id,
                        )?.required
                            ? 'Wajib'
                            : 'Opsional'
                    }}</span>
                </legend>
                <div class="pos-choice-grid">
                    <label
                        v-for="option in group.options"
                        :key="option.id"
                        :class="{
                            selected:
                                group.selection === 'single'
                                    ? single[group.id] === option.id
                                    : multiple[group.id]?.includes(option.id),
                        }"
                    >
                        <input
                            v-if="group.selection === 'single'"
                            v-model="single[group.id]"
                            type="radio"
                            :name="formId + '-group-' + group.id"
                            :value="option.id"
                        />
                        <input
                            v-else
                            v-model="multiple[group.id]"
                            type="checkbox"
                            :value="option.id"
                        />
                        <span
                            >{{ option.name
                            }}<small v-if="option.price"
                                >+{{ rupiah(option.price) }}</small
                            ></span
                        >
                    </label>
                </div>
                <p
                    v-if="errors[group.id]"
                    :id="formId + '-group-' + group.id"
                    class="pos-error"
                    role="alert"
                >
                    {{ errors[group.id] }}
                </p>
            </fieldset>
            <div class="pos-config-quantity">
                <label class="field" :for="formId + '-qty'"
                    >Jumlah<input
                        :id="formId + '-qty'"
                        v-model.number="quantity"
                        type="number"
                        min="1"
                        step="1"
                        required
                        :aria-invalid="Boolean(errors.quantity)"
                        :aria-describedby="
                            errors.quantity
                                ? formId + '-quantity-error'
                                : undefined
                        "
                /></label>
                <div>
                    <small>Harga satuan estimasi</small
                    ><strong>{{
                        variant ? rupiah(unitPrice) : 'Pilih varian'
                    }}</strong>
                </div>
            </div>
            <p
                v-if="errors.quantity"
                :id="formId + '-quantity-error'"
                class="pos-error"
                role="alert"
            >
                {{ errors.quantity }}
            </p>
        </form>
        <template #footer
            ><BaseButton @click="emit('close')">Batal</BaseButton
            ><BaseButton type="submit" :form="formId" variant="primary">{{
                line ? 'Simpan perubahan' : 'Tambah ke pesanan'
            }}</BaseButton></template
        >
    </AppDialog>
</template>
