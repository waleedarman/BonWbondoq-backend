<?php

namespace App\Http\Requests\Distribution;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDistributionShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'destination' => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'inventory_assigned_to' => ['nullable', 'exists:users,id'],
            'shipment_code' => ['prohibited'],
            'branch_id' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'prepared_at' => ['prohibited'],
            'transferred_at' => ['prohibited'],
            'delivered_at' => ['prohibited'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipment_code.prohibited' => 'Shipment code cannot be changed after creation.',
            'branch_id.prohibited' => 'Shipment branch cannot be changed after creation.',
            'status.prohibited' => 'Use the shipment status endpoint to change shipment status.',
            'created_by.prohibited' => 'Shipment creator cannot be changed.',
        ];
    }
}
