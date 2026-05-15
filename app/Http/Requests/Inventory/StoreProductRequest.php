<?php

namespace App\Http\Requests\Inventory;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku'],
            'category' => ['required', Rule::in(Product::CATEGORIES)],
            'unit' => ['required', Rule::in(['kg', 'gram', 'piece', 'box', 'bottle', 'pack'])],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'minimum_quantity' => ['nullable', 'numeric', 'min:0'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
