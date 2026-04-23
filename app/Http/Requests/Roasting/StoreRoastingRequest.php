<?php

namespace App\Http\Requests\Roasting;

use App\Models\RoastingRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoastingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', 'unique:roasting_requests,code'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'priority' => ['required', Rule::in(RoastingRequest::PRIORITIES)],
            'branch_id' => ['required', 'exists:branches,id'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'assigned_to' => ['prohibited'],
            'started_at' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.prohibited' => 'New roasting requests are created as pending by the system.',
            'created_by.prohibited' => 'Roasting request creator is resolved from the authenticated user.',
            'assigned_to.prohibited' => 'Assign roasting requests through the assignment endpoint.',
        ];
    }
}
