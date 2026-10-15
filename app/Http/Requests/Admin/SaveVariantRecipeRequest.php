<?php

namespace App\Http\Requests\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVariantRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['ingredients', 'finished'])],
            'stock_item_id' => ['required_if:kind,finished', 'nullable', 'integer', Rule::exists('stock_items', 'id')->where('base_unit', 'pcs')->where('is_active', true)],
            'ingredients' => ['present', 'array', 'max:100', 'min:'.($this->input('kind') === 'ingredients' ? '1' : '0')],
            'ingredients.*' => ['array:stock_item_id,quantity'],
            'ingredients.*.stock_item_id' => ['required', 'integer', 'distinct', Rule::exists('stock_items', 'id')->where('is_active', true)->where(fn (Builder $query) => $query->whereIn('base_unit', ['g', 'ml']))],
            'ingredients.*.quantity' => ['required', 'numeric', 'min:0.001', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/D'],
        ];
    }
}
