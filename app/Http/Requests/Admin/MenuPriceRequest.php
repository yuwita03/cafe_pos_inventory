<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MenuPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'option_ids' => ['present', 'array', 'max:100'],
            'option_ids.*' => ['required', 'integer', 'distinct', 'exists:options,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
