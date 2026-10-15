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
            'variants.*' => ['array:id,name,price_idr,is_available'],
            'variants.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('product_id', $product instanceof Product ? $product->id : 0)],
            'variants.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case', 'not_regex:/^\\s|\\s$/u'],
            'variants.*.price_idr' => ['required', 'integer', 'min:0', 'max:9007199254740991'],
            'variants.*.is_available' => ['required', 'boolean'],
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
            foreach ($this->array('groups') as $index => $group) {
                if (mb_strtolower(OptionGroup::query()->whereKey($group['id'])->firstOrFail()->name) === 'sugar level' && ! $group['is_required']) {
                    $validator->errors()->add("groups.$index.is_required", 'Sugar Level wajib dipilih.');
                }
            }
        }];
    }
}
