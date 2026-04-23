<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['prohibited'],
            'branch_id' => ['prohibited'],
            'is_active' => ['prohibited'],
            'approved_at' => ['prohibited'],
            'approved_by' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.prohibited' => 'Employee registration cannot assign roles directly.',
            'branch_id.prohibited' => 'Employee registration cannot assign branches directly.',
            'is_active.prohibited' => 'Employee registration cannot activate accounts directly.',
        ];
    }
}
