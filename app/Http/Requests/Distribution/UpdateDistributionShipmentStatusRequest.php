<?php

namespace App\Http\Requests\Distribution;

use App\Models\DistributionShipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistributionShipmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(DistributionShipment::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
