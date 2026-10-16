<?php

namespace App\Http\Requests\Admin;

use App\Models\StockItem;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStockItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->has('is_quick_add') || $this->user()?->role === 'owner';
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'reason'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $item = $this->route('stockItem');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('stock_items')->ignore($item instanceof StockItem ? $item->id : null)],
            'base_unit' => ['required', Rule::in($item instanceof StockItem ? [$item->base_unit] : ['g', 'ml', 'pcs'])],
            'reorder_point' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/D'],
            'is_active' => ['required', 'boolean'],
            'is_quick_add' => ['sometimes', 'boolean', function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->input('base_unit') === 'pcs' && $this->boolean('is_quick_add')) {
                    $fail('Tambah cepat bahan hanya tersedia untuk item dengan unit g atau ml.');
                }
            }],
            'on_hand_qty' => ['prohibited'],
        ];
    }
}
