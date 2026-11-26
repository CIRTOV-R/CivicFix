<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(IncidentStatus::class)],
            'priority' => ['sometimes', Rule::enum(IncidentPriority::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
