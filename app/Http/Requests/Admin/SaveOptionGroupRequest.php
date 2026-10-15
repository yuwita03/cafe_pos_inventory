<?php

namespace App\Http\Requests\Admin;

use App\Models\OptionGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOptionGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $group = $this->route('optionGroup');
        $isSugar = mb_strtolower($this->string('name')->toString()) === 'sugar level';

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('option_groups')->ignore($group instanceof OptionGroup ? $group->id : null)],
            'selection_type' => ['required', Rule::in($isSugar ? ['single'] : ['single', 'multiple'])],
            'is_active' => ['required', 'boolean'],
            'options' => ['required', 'array', 'min:1', 'max:50'],
            'options.*' => ['array:id,name,price_delta_idr,is_active'],
            'options.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('options', 'id')->where('option_group_id', $group instanceof OptionGroup ? $group->id : 0)],
            'options.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case', 'not_regex:/^\\s|\\s$/u'],
            'options.*.price_delta_idr' => ['required', 'integer', 'min:0', 'max:9007199254740991'],
            'options.*.is_active' => ['required', 'boolean'],
        ];
    }
}
