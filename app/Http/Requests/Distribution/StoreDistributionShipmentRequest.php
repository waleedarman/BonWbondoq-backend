<?php

namespace App\Http\Requests\Distribution;

use Illuminate\Foundation\Http\FormRequest;

class StoreDistributionShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipment_code' => ['required', 'string', 'max:255', 'unique:distribution_shipments,shipment_code'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'destination' => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'inventory_assigned_to' => ['nullable', 'exists:users,id'],
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
            'status.prohibited' => 'New shipments are created as pending by the system.',
            'created_by.prohibited' => 'Shipment creator is resolved from the authenticated user.',
        ];
    }
}
