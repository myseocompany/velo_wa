<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookableUnitRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'settings' => ['nullable', 'array'],
            'settings.services' => ['nullable', 'array'],
            'settings.services.*' => ['string', 'max:80'],
            'settings.slug' => ['nullable', 'string', 'max:120'],
            'settings.working_hours' => ['nullable', 'array'],
            'settings.working_hours.*.enabled' => ['nullable', 'boolean'],
            'settings.working_hours.*.blocks' => ['nullable', 'array'],
            'settings.working_hours.*.blocks.*.start' => ['required_with:settings.working_hours.*.blocks', 'date_format:H:i'],
            'settings.working_hours.*.blocks.*.end' => ['required_with:settings.working_hours.*.blocks', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
