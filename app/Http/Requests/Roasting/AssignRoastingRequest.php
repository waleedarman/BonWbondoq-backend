<?php

namespace App\Http\Requests\Roasting;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoastingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'exists:users,id'],
            'note' => ['nullable', 'string'],
        ];
    }
}
