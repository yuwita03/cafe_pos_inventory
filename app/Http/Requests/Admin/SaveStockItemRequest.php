<?php

namespace App\Http\Requests\Admin;

use App\Models\StockItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStockItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'on_hand_qty' => ['prohibited'],
        ];
    }
}
