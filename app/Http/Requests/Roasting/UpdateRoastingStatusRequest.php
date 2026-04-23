<?php

namespace App\Http\Requests\Roasting;

use App\Models\RoastingRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoastingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(RoastingRequest::STATUSES)],
            'note' => ['nullable', 'string'],
        ];
    }
}
