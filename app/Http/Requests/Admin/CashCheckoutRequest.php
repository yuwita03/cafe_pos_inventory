<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'cashier'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'checkout_key' => ['required', 'uuid'],
            'customer_name' => ['required', 'string', 'max:120'],
            'fulfillment_type' => ['required', Rule::in(['dine_in', 'takeaway'])],
            'table_id' => ['nullable', 'required_if:fulfillment_type,dine_in', 'prohibited_if:fulfillment_type,takeaway', 'integer', 'exists:cafe_tables,id'],
            'expected_total_idr' => ['required', 'integer', 'min:1', 'max:9007199254740991'],
            'cash_received_idr' => ['required', 'integer', 'min:1', 'max:9007199254740991'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:variant_id,option_ids,quantity'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.option_ids' => ['present', 'array', 'max:100'],
            'items.*.option_ids.*' => ['required', 'integer', 'exists:options,id'],
        ];
    }
}
