<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ProductStockSetup from '@/components/admin/ProductStockSetup.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { formatRupiah as rupiah } from '@/lib/utils';
import type {
    Category,
    OptionGroup,
    Product,
    ProductStockItem,
} from '@/types/menu';

const props = defineProps<{
    product: Product | null;
    categories: Category[];
    groups: OptionGroup[];
    errors: Record<string, string>;
    processing: boolean;
    stocks: ProductStockItem[];
}>();
const emit = defineEmits<{
    close: [];
    save: [product: Product, image: File | null];
}>();
const draft = reactive<Product>(
    props.product
        ? {
              ...props.product,
              variants: props.product.variants.map((variant) => ({
                  ...variant,
                  stock: variant.stock
                      ? {
                            ...variant.stock,
                            ingredients: variant.stock.ingredients.map(
                                (row) => ({ ...row }),
                            ),
                        }
                      : undefined,
              })),
              groups: props.product.groups.map((group) => ({ ...group })),
          }
        : {
              id: Date.now(),
              name: '',
              description: '',
              categoryId: props.categories[0]?.id ?? 0,
              active: true,
              image: '',
              variants: [{ name: 'Regular', price: 0, available: true }],
              groups: [],
          },
);
const hasVariants = ref(
    draft.variants.length > 1 || draft.variants[0]?.name !== 'Regular',
);
const optionsEnabled = ref(draft.groups.length > 0);
const originalOptionsEnabled = optionsEnabled.value;
const pendingPricingMode = ref<'single' | 'variants' | null>(null);
const discardDialogOpen = ref(false);
const original = JSON.stringify(draft);
const originalHasVariants = hasVariants.value;
const imageError = ref('');
const selectedImage = ref<File | null>(null);
const formError = ref('');
const stockDirty = ref(false);
const stockEditor = ref<InstanceType<typeof ProductStockSetup>>();
const imageInput = ref<HTMLInputElement>();
const lowestPrice = computed(() =>
    Math.min(...draft.variants.map((variant) => Number(variant.price))),
);
function close(): void {
    if (props.processing) {
        return;
    }
    if (
        JSON.stringify(draft) !== original ||
        hasVariants.value !== originalHasVariants ||
        optionsEnabled.value !== originalOptionsEnabled ||
        selectedImage.value !== null ||
        stockDirty.value
    ) {
        discardDialogOpen.value = true;
        return;
    }
    emit('close');
}
function discardChanges(): void {
    if (!props.processing) {
        emit('close');
    }
}
function selectImage(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) {
        return;
    }
    if (
        !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
        file.size > 200 * 1024
    ) {
        imageError.value = 'Pilih JPG/JPEG, PNG, atau WebP hingga 200 KB.';
        return;
    }
    imageError.value = '';
    selectedImage.value = file;
    const reader = new FileReader();
    reader.onload = () => {
        draft.image = String(reader.result);
    };
    reader.onerror = () => {
        imageError.value = 'Gambar tidak dapat dibaca. Pilih ulang file.';
    };
    reader.readAsDataURL(file);
}
function toggleGroup(id: number, checked: boolean): void {
    draft.groups = checked
        ? [
              ...draft.groups,
              {
                  id,
                  required:
                      props.groups
                          .find((group) => group.id === id)
                          ?.name.toLowerCase() === 'sugar level',
              },
          ]
        : draft.groups.filter((group) => group.id !== id);
}
function useSinglePrice(): void {
    if (draft.variants.length === 1) {
        draft.variants[0].name = 'Regular';
        hasVariants.value = false;
    }
}
function confirmPricingMode(): void {
    if (props.processing) {
        return;
    }
    if (pendingPricingMode.value === 'single') {
        useSinglePrice();
    } else if (pendingPricingMode.value === 'variants') {
        hasVariants.value = true;
    }
    pendingPricingMode.value = null;
}
function removeVariant(index: number): void {
    if (props.processing) {
        return;
    }
    if (draft.variants.length === 1) {
        pendingPricingMode.value = 'single';
        return;
    }
    draft.variants.splice(index, 1);
}
function save(): void {
    if (props.processing) {
        return;
    }
    if (
        !draft.name.trim() ||
        !draft.categoryId ||
        draft.variants.some(
            (variant) =>
                !variant.name.trim() ||
                !Number.isSafeInteger(Number(variant.price)) ||
                Number(variant.price) < 0,
        )
    ) {
        formError.value =
            'Lengkapi nama, kategori, nama varian, dan harga IDR yang valid.';
        return;
    }
    if (
        new Set(
            draft.variants.map((variant) => variant.name.trim().toLowerCase()),
        ).size !== draft.variants.length
    ) {
        formError.value = 'Nama tiap varian harus berbeda.';
        return;
    }
    emit(
        'save',
        {
            ...draft,
            name: draft.name.trim(),
            variants: draft.variants.map((variant, index) => ({
                ...variant,
                price: Number(variant.price),
                stock: stockEditor.value?.getSettings()[index],
            })),
            groups: optionsEnabled.value
                ? draft.groups.map((group) => ({ ...group }))
                : [],
        },
        selectedImage.value,
    );
}
</script>

<template>
    <AppDialog
        open
        wide
        class="product-editor-scroll"
        :title="product ? 'Edit produk' : 'Tambah produk'"
        @close="close"
    >
        <form id="product-form" class="product-editor" @submit.prevent="save">
            <div
                class="editor-fields"
                tabindex="0"
                role="region"
                aria-label="Form informasi dan pengaturan produk"
            >
                <div
                    v-if="Object.keys(errors).length"
                    class="field-error"
                    role="alert"
                >
                    <p v-for="(message, field) in errors" :key="field">
                        {{ message }}
                    </p>
                </div>
                <p class="form-intro">
                    Lengkapi informasi produk yang akan tampil di menu café.
                </p>
                <section class="form-section">
                    <h3><span>01</span>Informasi produk</h3>
                    <label class="field"
                        >Nama produk <span class="required-mark">*</span
                        ><input
                            v-model="draft.name"
                            required
                            maxlength="120"
                            placeholder="Contoh: Americano"
                            autofocus /></label
                    ><label class="field"
                        >Kategori <span class="required-mark">*</span
                        ><select v-model="draft.categoryId" required>
                            <option :value="0" disabled>Pilih kategori</option>
                            <option
                                v-for="category in props.categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name
                                }}{{ category.active ? '' : ' (nonaktif)' }}
                            </option>
                        </select></label
                    ><label class="field"
                        >Deskripsi <span class="optional-label">Opsional</span
                        ><textarea
                            v-model="draft.description"
                            rows="3"
                            maxlength="1000"
                            placeholder="Ceritakan rasa atau karakter produk…"
                        />
                    </label>
                    <div class="field">
                        Foto produk <span class="optional-label">Opsional</span>
                        <div class="upload-area">
                            <img
                                v-if="draft.image"
                                :src="draft.image"
                                alt="Pratinjau foto produk"
                            /><span v-else class="upload-icon"
                                ><AppIcon name="upload" :size="24"
                            /></span>
                            <div>
                                <strong>{{
                                    draft.image
                                        ? 'Foto siap dipratinjau'
                                        : 'Tambahkan foto produk'
                                }}</strong>
                                <p>JPG/JPEG, PNG, WebP · maksimal 200 KB</p>
                                <BaseButton @click="imageInput?.click()">{{
                                    draft.image ? 'Ganti foto' : 'Pilih foto'
                                }}</BaseButton
                                ><BaseButton
                                    v-if="draft.image"
                                    variant="ghost"
                                    @click="
                                        draft.image = '';
                                        selectedImage = null;
                                    "
                                    >Hapus</BaseButton
                                >
                            </div>
                            <input
                                ref="imageInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                aria-label="Pilih foto produk"
                                @change="selectImage"
                            />
                        </div>
                        <span
                            v-if="imageError"
                            class="field-error"
                            role="alert"
                            >{{ imageError }}</span
                        ><small class="field-help"
                            >Foto divalidasi oleh server sebelum
                            disimpan.</small
                        >
                    </div>
                </section>
                <section class="form-section">
                    <div class="section-heading price-section-heading">
                        <h3>
                            <span>02</span
                            >{{
                                hasVariants ? 'Varian & harga' : 'Harga produk'
                            }}
                        </h3>
                        <BaseButton
                            v-if="!hasVariants"
                            variant="ghost"
                            :disabled="processing"
                            @click="pendingPricingMode = 'variants'"
                        >
                            <AppIcon name="plus" :size="16" />Gunakan varian
                        </BaseButton>
                        <div v-else class="variant-controls">
                            <BaseButton
                                v-if="draft.variants.length === 1"
                                variant="ghost"
                                :disabled="processing"
                                @click="pendingPricingMode = 'single'"
                                >Harga tunggal</BaseButton
                            >
                            <BaseButton
                                variant="ghost"
                                @click="
                                    draft.variants.push({
                                        name: '',
                                        price: 0,
                                        available: true,
                                    })
                                "
                                ><AppIcon name="plus" :size="16" />Tambah
                                varian</BaseButton
                            >
                        </div>
                    </div>
                    <div v-if="!hasVariants">
                        <p class="field-help">
                            Satu harga untuk produk ini. Gunakan varian jika ada
                            pilihan seperti Hot atau Iced.
                        </p>
                        <label class="field"
                            >Harga (IDR)<input
                                v-model.number="draft.variants[0].price"
                                type="number"
                                min="0"
                                step="1"
                                required
                                placeholder="Contoh: 24000"
                        /></label>
                        <label class="check-label"
                            ><input
                                v-model="draft.variants[0].available"
                                type="checkbox"
                            />Tersedia</label
                        >
                    </div>
                    <template v-else>
                        <p class="field-help">
                            Tentukan nama dan harga untuk setiap pilihan produk.
                            Hapus pilihan yang tidak digunakan.
                        </p>
                        <div
                            v-for="(variant, index) in draft.variants"
                            :key="index"
                            class="variant-row"
                        >
                            <label class="field"
                                >Nama varian<input
                                    v-model="variant.name"
                                    required
                                    placeholder="Hot / Iced / Regular" /></label
                            ><label class="field"
                                >Harga (IDR)<input
                                    v-model.number="variant.price"
                                    type="number"
                                    min="0"
                                    step="1"
                                    required /></label
                            ><label class="check-label variant-availability"
                                ><input
                                    v-model="variant.available"
                                    type="checkbox"
                                />Tersedia</label
                            ><button
                                type="button"
                                class="icon-button danger-text"
                                :disabled="processing"
                                :aria-label="`Hapus varian ${index + 1}`"
                                @click="removeVariant(index)"
                            >
                                <AppIcon name="trash" :size="17" />
                            </button>
                        </div>
                    </template>
                </section>
                <ProductStockSetup
                    ref="stockEditor"
                    :product-name="draft.name"
                    :variants="draft.variants"
                    :stocks="stocks"
                    :processing="processing"
                    @change="stockDirty = true"
                />
                <section class="form-section">
                    <div class="section-heading options-section-heading">
                        <h3><span>04</span>Pilihan tambahan</h3>
                        <label class="check-label options-toggle">
                            <input
                                v-model="optionsEnabled"
                                type="checkbox"
                                role="switch"
                                aria-label="Aktifkan pilihan tambahan"
                                :disabled="processing"
                            />
                            {{ optionsEnabled ? 'Aktif' : 'Nonaktif' }}
                        </label>
                    </div>
                    <p v-if="!optionsEnabled" class="field-help">
                        Produk ini tidak menggunakan pilihan tambahan.
                    </p>
                    <template v-else>
                        <p class="field-help">
                            Hubungkan grup opsi ke produk ini, lalu tentukan
                            apakah wajib dipilih.
                        </p>
                        <p v-if="!props.groups.length" class="field-help">
                            Belum ada grup opsi. Tambahkan grup di halaman Opsi.
                        </p>
                        <div
                            v-for="group in props.groups"
                            :key="group.id"
                            class="group-assignment"
                        >
                            <label class="check-label"
                                ><input
                                    type="checkbox"
                                    :checked="
                                        draft.groups.some(
                                            (assigned) =>
                                                assigned.id === group.id,
                                        )
                                    "
                                    @change="
                                        toggleGroup(
                                            group.id,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                /><span
                                    ><strong>{{ group.name }}</strong
                                    ><small>{{
                                        group.options
                                            .map((option) => option.name)
                                            .join(' · ')
                                    }}</small></span
                                ></label
                            ><template
                                v-for="assigned in draft.groups.filter(
                                    (item) => item.id === group.id,
                                )"
                                :key="assigned.id"
                                ><label class="check-label required-checkbox"
                                    ><input
                                        v-model="assigned.required"
                                        type="checkbox"
                                    />Wajib</label
                                ></template
                            >
                        </div>
                    </template>
                </section>
                <p v-if="formError" role="alert" class="field-error">
                    {{ formError }}
                </p>
            </div>
            <aside class="editor-preview">
                <span class="eyebrow">PRATINJAU PRODUK</span>
                <div class="preview-image">
                    <img v-if="draft.image" :src="draft.image" alt="" /><AppIcon
                        v-else
                        name="coffee"
                        :size="52"
                    />
                </div>
                <div class="editor-preview-info">
                    <span class="category-caption">{{
                        props.categories.find(
                            (category) => category.id === draft.categoryId,
                        )?.name ?? 'Kategori'
                    }}</span>
                    <h3>{{ draft.name || 'Nama produk' }}</h3>
                    <p>
                        {{
                            draft.description ||
                            'Deskripsi singkat produk akan tampil di sini.'
                        }}
                    </p>
                    <strong class="preview-price"
                        >{{ draft.variants.length > 1 ? 'Mulai ' : ''
                        }}{{ rupiah(lowestPrice) }}</strong
                    >
                    <div v-if="hasVariants" class="preview-variants">
                        <span
                            v-for="(variant, index) in draft.variants"
                            :key="index"
                            >{{ variant.name || 'Varian' }}</span
                        >
                    </div>
                    <div class="preview-visibility">
                        <label class="check-label"
                            ><input
                                v-model="draft.active"
                                type="checkbox"
                            /><strong>Produk aktif</strong></label
                        >
                        <p>
                            Produk nonaktif tidak ditawarkan untuk pesanan baru.
                        </p>
                    </div>
                    <p class="preview-disclaimer">
                        Pratinjau dalam admin. Halaman pelanggan akan dibuat
                        terpisah.
                    </p>
                </div>
            </aside>
        </form>
        <AppDialog
            :open="pendingPricingMode !== null"
            :title="
                pendingPricingMode === 'single'
                    ? 'Ubah ke harga tunggal?'
                    : 'Gunakan varian?'
            "
            @close="pendingPricingMode = null"
        >
            <div class="editor-confirmation">
                <p v-if="pendingPricingMode === 'single'">
                    Varian terakhir akan menjadi harga tunggal dengan nama
                    Regular. Harga dan status tersedia tetap dipertahankan.
                </p>
                <p v-else>
                    Harga saat ini akan menjadi harga varian pertama. Kamu bisa
                    mengubah namanya dan menambahkan varian lain.
                </p>
                <p class="field-help">
                    Perubahan diterapkan setelah kamu klik Simpan produk.
                </p>
            </div>
            <template #footer>
                <BaseButton @click="pendingPricingMode = null"
                    >Batal</BaseButton
                >
                <BaseButton
                    variant="primary"
                    :disabled="processing"
                    @click="confirmPricingMode"
                >
                    Ya, ubah
                </BaseButton>
            </template>
        </AppDialog>
        <AppDialog
            :open="discardDialogOpen"
            title="Buang perubahan?"
            @close="discardDialogOpen = false"
        >
            <div class="editor-confirmation">
                <p>
                    Perubahan produk belum disimpan. Jika kamu keluar, perubahan
                    akan dibuang.
                </p>
            </div>
            <template #footer>
                <BaseButton @click="discardDialogOpen = false">
                    Lanjut edit
                </BaseButton>
                <BaseButton
                    variant="danger"
                    :disabled="processing"
                    @click="discardChanges"
                >
                    Buang perubahan
                </BaseButton>
            </template>
        </AppDialog>
        <template #footer
            ><span class="save-hint"
                >Produk dan pengaturan stok disimpan bersama.</span
            ><BaseButton @click="close">Batal</BaseButton
            ><BaseButton
                :disabled="processing"
                type="submit"
                form="product-form"
                variant="primary"
                ><AppIcon name="check" :size="17" />Simpan produk</BaseButton
            ></template
        >
    </AppDialog>
</template>

<style scoped>
@media (min-width: 924px) {
    .product-editor-scroll {
        height: 90dvh;
    }
    .product-editor-scroll :deep(> .dialog-body) {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .product-editor-scroll .product-editor {
        flex: 1;
        min-height: 0;
        grid-template-rows: minmax(0, 1fr);
        align-items: stretch;
        overflow: hidden;
    }
    .product-editor-scroll .editor-fields {
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
    }
    .product-editor-scroll .editor-preview {
        max-height: calc(100% - 48px);
        overflow-y: auto;
        overscroll-behavior: contain;
    }
}
.editor-confirmation {
    padding: 24px 28px;
}
.price-section-heading {
    flex-wrap: wrap;
}
.options-section-heading {
    flex-wrap: wrap;
}
.options-toggle input {
    appearance: none;
    width: 36px;
    height: 22px;
    padding: 3px;
    border-radius: 999px;
    background: var(--line);
    cursor: pointer;
}
.options-toggle input::before {
    content: '';
    display: block;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: white;
}
.options-toggle input:checked {
    background: var(--primary);
}
.options-toggle input:checked::before {
    transform: translateX(14px);
}
.options-toggle input:disabled {
    cursor: wait;
    opacity: 0.6;
}
.variant-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
</style>
