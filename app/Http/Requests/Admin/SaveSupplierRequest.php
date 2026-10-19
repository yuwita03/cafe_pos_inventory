<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'contacts' => ['sometimes', 'array', 'list', 'max:10'],
            'contacts.*' => ['required', 'array:type,value'],
            'contacts.*.type' => ['required', Rule::in(['phone', 'email'])],
            'contacts.*.value' => ['present', 'nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('contacts.*.value', 'email', fn (Fluent $input, mixed $contact): bool => $contact instanceof Fluent && $contact->get('type') === 'email');
        $validator->sometimes('contacts.*.value', ['regex:/^\+?[0-9][0-9 ()-]*$/D'], fn (Fluent $input, mixed $contact): bool => $contact instanceof Fluent && $contact->get('type') === 'phone');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
        if (is_array($this->input('contacts'))) {
            $this->merge(['contacts' => array_map(fn (mixed $contact): mixed => is_array($contact) && is_string($contact['value'] ?? null) ? [...$contact, 'value' => trim($contact['value'])] : $contact, $this->input('contacts'))]);
        }
    }

    /** @return array<string, mixed> */
    public function supplierData(): array
    {
        $data = $this->safe()->except('contacts');
        if ($this->has('contacts')) {
            $contacts = array_values(array_filter($this->validated('contacts'), fn (array $contact): bool => filled($contact['value'])));
            $data['contact'] = $contacts === [] ? null : $contacts;
        }

        return $data;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contacts.max' => 'Maksimal 10 kontak per supplier.',
            'contacts.*.type.in' => 'Pilih Telepon atau Email.',
            'contacts.*.value.max' => 'Setiap kontak maksimal 255 karakter.',
            'contacts.*.value.email' => 'Masukkan alamat email yang valid.',
            'contacts.*.value.regex' => 'Masukkan nomor telepon yang valid, misalnya +62 812-3456-7890.',
        ];
    }
}
