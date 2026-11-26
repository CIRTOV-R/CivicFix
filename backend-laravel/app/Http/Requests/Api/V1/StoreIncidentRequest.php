<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\IncidentPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:10'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address_reference' => ['nullable', 'string', 'max:255'],
            'priority' => ['sometimes', Rule::enum(IncidentPriority::class)],
            'before_photo' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ];
    }
}
