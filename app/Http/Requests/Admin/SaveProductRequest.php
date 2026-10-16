<?php

namespace App\Http\Requests\Admin;

use App\Models\OptionGroup;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('groups')) {
            $this->merge(['groups' => []]);
        }
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:120'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:200'],
            'remove_image' => ['sometimes', 'boolean'],
            'variants' => ['required', 'array', 'min:1', 'max:50'],
            'variants.*' => ['array:id,name,price_idr,is_available,stock'],
            'variants.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('product_id', $product instanceof Product ? $product->id : 0)],
            'variants.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case', 'not_regex:/^\\s|\\s$/u'],
            'variants.*.price_idr' => ['required', 'integer', 'min:0', 'max:9007199254740991'],
            'variants.*.is_available' => ['required', 'boolean'],
            'variants.*.stock' => ['sometimes', 'array:kind,stock_item_id,opening_qty,reorder_point,ingredients'],
            'variants.*.stock.kind' => ['required_with:variants.*.stock', Rule::in(['ingredients', 'finished'])],
            'variants.*.stock.stock_item_id' => ['nullable', 'integer', Rule::exists('stock_items', 'id')->where('base_unit', 'pcs')],
            'variants.*.stock.opening_qty' => ['sometimes', 'integer', 'min:0', 'max:999999999'],
            'variants.*.stock.reorder_point' => ['sometimes', 'numeric', 'min:0', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/D'],
            'variants.*.stock.ingredients' => ['sometimes', 'array', 'max:100'],
            'variants.*.stock.ingredients.*' => ['array:stock_item_id,quantity'],
            'variants.*.stock.ingredients.*.stock_item_id' => ['required', 'integer', Rule::exists('stock_items', 'id')->whereIn('base_unit', ['g', 'ml'])],
            'variants.*.stock.ingredients.*.quantity' => ['required', 'numeric', 'min:0.001', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/D'],
            'groups' => ['present', 'array', 'max:50'],
            'groups.*' => ['array:id,is_required'],
            'groups.*.id' => ['required', 'integer', 'distinct', 'exists:option_groups,id'],
            'groups.*.is_required' => ['required', 'boolean'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($this->array('variants') as $index => $variant) {
                $stock = $variant['stock'] ?? null;
                if ($stock === null) {
                    continue;
                }
                if (! isset($stock['kind'])) {
                    $validator->errors()->add("variants.$index.stock.kind", 'Pilih cara pengurangan stok.');

                    continue;
                }
                $ids = array_column($stock['ingredients'] ?? [], 'stock_item_id');
                if (count($ids) !== count(array_unique($ids))) {
                    $validator->errors()->add("variants.$index.stock.ingredients", 'Bahan yang sama tidak boleh dipilih dua kali dalam satu varian.');
                }
                if (array_key_exists('opening_qty', $stock) && (! empty($variant['id']) || $stock['kind'] !== 'finished')) {
                    $validator->errors()->add("variants.$index.stock.opening_qty", 'Stok awal hanya untuk varian produk jadi yang baru.');
                }
                if (! empty($stock['stock_item_id']) && (empty($variant['id']) || $stock['kind'] !== 'finished')) {
                    $validator->errors()->add("variants.$index.stock.stock_item_id", 'Stok produk jadi dibuat otomatis dan tidak dapat dipilih manual.');
                }
                if ($stock['kind'] === 'finished' && $ids !== []) {
                    $validator->errors()->add("variants.$index.stock.ingredients", 'Produk jadi memakai stok pcs, bukan resep bahan.');
                }
            }
            foreach ($this->array('groups') as $index => $group) {
                if (mb_strtolower(OptionGroup::query()->whereKey($group['id'])->firstOrFail()->name) === 'sugar level' && ! $group['is_required']) {
                    $validator->errors()->add("groups.$index.is_required", 'Sugar Level wajib dipilih.');
                }
            }
        }];
    }
}
