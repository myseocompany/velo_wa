<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/[\s\+\-\(\)]+/', '', $this->string('phone')->toString()),
            ]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'phone' => [
                'required', 'string', 'max:30',
                Rule::unique('contacts', 'phone')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'name'        => ['nullable', 'string', 'max:120'],
            'email'       => ['nullable', 'email', 'max:254'],
            'company'     => ['nullable', 'string', 'max:120'],
            'notes'       => ['nullable', 'string', 'max:2000'],
            'tag_ids'   => ['nullable', 'array'],
            'tag_ids.*' => ['uuid', 'exists:tags,id'],
            'custom_fields'   => ['nullable', 'array'],
            'custom_fields.*' => ['nullable', 'string', 'max:500'],
            'assigned_to' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
