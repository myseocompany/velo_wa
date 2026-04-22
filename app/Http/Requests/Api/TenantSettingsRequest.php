<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isOwner();
    }

    public function rules(): array
    {
        return [
            'timezone'          => ['sometimes', 'required', 'string', 'timezone:all'],
            'auto_close_hours'  => ['nullable', 'integer', 'min:1', 'max:8760'],
            'business_hours'                    => ['nullable', 'array'],
            'business_hours.*'                  => ['array'],
            'business_hours.*.enabled'          => ['boolean'],
            'business_hours.*.blocks'           => ['nullable', 'array'],
            'business_hours.*.blocks.*'         => ['array'],
            'business_hours.*.blocks.*.start'   => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'business_hours.*.blocks.*.end'     => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'webhook_url'               => ['nullable', 'url', 'max:500'],
            'webhook_secret'            => ['nullable', 'string', 'max:255'],
        ];
    }
}
