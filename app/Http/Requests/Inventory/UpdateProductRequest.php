<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product?->id)],
            'category' => ['sometimes', 'required', Rule::in(['raw_coffee', 'roasted_coffee', 'packaging_material', 'beverage', 'supply', 'other'])],
            'unit' => ['sometimes', 'required', Rule::in(['kg', 'gram', 'piece', 'box', 'bottle', 'pack'])],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'minimum_quantity' => ['sometimes', 'numeric', 'min:0'],
            'branch_id' => ['sometimes', 'required', 'exists:branches,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
