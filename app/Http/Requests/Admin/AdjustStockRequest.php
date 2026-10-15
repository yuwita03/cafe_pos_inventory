<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
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
        return [
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'quantity_delta' => ['required', 'numeric', 'not_in:0', 'regex:/^-?\d{1,9}(?:\.\d{1,3})?$/D'],
            'reason' => ['required', 'string', 'max:500'],
            'source_key' => ['required', 'uuid'],
        ];
    }
}
