<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestOrderCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'checkout_key' => ['required', 'uuid'],
            'table_id' => ['required', 'integer', 'exists:cafe_tables,id'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'expected_total_idr' => ['required', 'integer', 'min:1', 'max:9007199254740991'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:variant_id,option_ids,quantity'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.option_ids' => ['present', 'array', 'max:100'],
            'items.*.option_ids.*' => ['required', 'integer', 'exists:options,id'],
        ];
    }
}
