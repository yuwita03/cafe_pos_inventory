<?php

namespace App\Http\Requests\Admin;

use App\StockQuantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SavePurchaseRequest extends FormRequest
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
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:stock_item_id,quantity,line_cost_idr'],
            'items.*.stock_item_id' => ['required', 'integer', 'distinct', 'exists:stock_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/D'],
            'items.*.line_cost_idr' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Tambahkan minimal satu item stok.',
            'items.*.stock_item_id.distinct' => 'Item stok tidak boleh duplikat.',
            'items.*.quantity.regex' => 'Kuantitas maksimal 999999999.999 dengan tiga angka desimal.',
            'items.*.quantity.gt' => 'Kuantitas harus lebih besar dari nol.',
            'items.*.line_cost_idr.integer' => 'Total biaya baris harus berupa rupiah bulat.',
        ];
    }

    /** @return array{supplier_id: int, items: list<array{stock_item_id: int, quantity: string, line_cost_idr: int}>} */
    public function purchaseData(): array
    {
        return [
            'supplier_id' => (int) $this->validated('supplier_id'),
            'items' => array_values(array_map(fn (array $line): array => [
                'stock_item_id' => (int) $line['stock_item_id'],
                'quantity' => StockQuantity::decimal(StockQuantity::milli($line['quantity'])),
                'line_cost_idr' => (int) $line['line_cost_idr'],
            ], $this->validated('items'))),
        ];
    }
}
