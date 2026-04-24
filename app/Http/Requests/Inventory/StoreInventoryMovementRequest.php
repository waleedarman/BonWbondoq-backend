<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'movement_type' => ['required', Rule::in(InventoryMovement::TYPES)],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', Rule::in(InventoryMovement::REASONS)],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'performed_by' => ['prohibited'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'performed_by.prohibited' => 'Inventory movement performer is resolved from the authenticated user.',
        ];
    }
}
