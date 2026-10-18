<script setup lang="ts">
import { computed, nextTick, ref, useId, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import AppIcon from '@/components/AppIcon.vue';
import CustomerVariantSelector from './CustomerVariantSelector.vue';
import { formatRupiah as rupiah } from '@/lib/utils';
import { type PosGroup, type CartLine, type PosProduct } from '@/types/pos';

const props = defineProps<{
    product: PosProduct | null;
    line: CartLine | null;
    groups: PosGroup[];
}>();
const emit = defineEmits<{ close: []; save: [line: CartLine] }>();
const formId = useId();
const formElement = ref<HTMLFormElement>();
const quantityInput = ref<HTMLInputElement>();
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

watch(
    () => [props.product, props.line] as const,
    ([product, line]) => {
        if (!product) {
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
        if (errors.value.quantity) {
            quantityInput.value?.focus();
        } else {
            formElement.value
                ?.querySelector<HTMLElement>('[aria-invalid="true"]')
                ?.focus();
        }
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
    <section class="customer-product-page">
        <form
            v-if="product"
            :id="formId"
            ref="formElement"
            class="pos-config"
            novalidate
            @submit.prevent="save"
        >
            <div class="customer-product-hero">
                <img
                    v-if="product.image"
                    class="customer-detail-image"
                    :src="product.image"
                    :alt="product.name"
                />
                <div
                    v-else
                    class="customer-detail-placeholder"
                    aria-hidden="true"
                >
                    <AppIcon name="coffee" :size="40" />
                </div>
                <button
                    type="button"
                    class="customer-product-close"
                    aria-label="Kembali ke menu atau keranjang"
                    @click="emit('close')"
                >
                    <AppIcon name="close" />
                </button>
            </div>
            <div class="pos-config-intro">
                <h1>{{ product.name }}</h1>
                <strong class="customer-detail-price">{{
                    variant
                        ? rupiah(unitPrice)
                        : 'Mulai ' +
                          rupiah(
                              Math.min(
                                  ...product.variants.map((item) => item.price),
                              ),
                          )
                }}</strong>
                <p v-if="product.description">{{ product.description }}</p>
            </div>
            <CustomerVariantSelector
                v-model="variantId"
                :variants="product.variants"
                :form-id="formId"
                :error="errors.variant"
            />
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
        </form>
        <footer v-if="product" class="customer-product-footer">
            <div class="customer-product-quantity-row">
                <label :for="formId + '-qty'">Jumlah pesanan</label>
                <div class="customer-quantity">
                    <button
                        type="button"
                        aria-label="Kurangi jumlah"
                        :disabled="quantity <= 1"
                        @click="quantity--"
                    >
                        −
                    </button>
                    <input
                        :id="formId + '-qty'"
                        ref="quantityInput"
                        :form="formId"
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
                    />
                    <button
                        type="button"
                        aria-label="Tambah jumlah"
                        :disabled="!Number.isSafeInteger(quantity + 1)"
                        @click="quantity++"
                    >
                        +
                    </button>
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
            <BaseButton type="submit" :form="formId" variant="primary"
                >{{ line ? 'Simpan perubahan' : 'Tambah ke keranjang'
                }}<span aria-hidden="true"> · </span
                >{{
                    variant &&
                    Number.isSafeInteger(quantity) &&
                    quantity > 0 &&
                    Number.isSafeInteger(unitPrice * quantity)
                        ? rupiah(unitPrice * quantity)
                        : 'Pilih varian'
                }}</BaseButton
            >
        </footer>
    </section>
</template>
