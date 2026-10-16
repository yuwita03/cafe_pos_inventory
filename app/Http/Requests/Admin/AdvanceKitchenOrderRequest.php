<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvanceKitchenOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'kitchen'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['expected_status' => ['required', Rule::in(['queued', 'preparing', 'ready'])]];
    }
}
