<?php

namespace App\Http\Requests\Roasting;

use App\Models\RoastingRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoastingRequest extends FormRequest
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
            'priority' => ['required', Rule::in(RoastingRequest::PRIORITIES)],
            'scheduled_start_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'code' => ['prohibited'],
            'branch_id' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'assigned_to' => ['prohibited'],
            'started_at' => ['prohibited'],
            'completed_at' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.prohibited' => 'Roasting request code cannot be changed.',
            'branch_id.prohibited' => 'Roasting request branch cannot be changed.',
            'status.prohibited' => 'Update roasting status through the status endpoint.',
            'created_by.prohibited' => 'Roasting request creator cannot be changed.',
            'assigned_to.prohibited' => 'Assign roasting requests through the assignment endpoint.',
        ];
    }
}
