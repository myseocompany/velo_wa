<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date', 'after:now'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
            'assigned_to' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId)->where('is_active', true),
            ],
            'contact_id' => [
                'nullable',
                'uuid',
                Rule::exists('contacts', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'conversation_id' => [
                'nullable',
                'uuid',
                Rule::exists('conversations', 'id')->where('tenant_id', $tenantId),
            ],
            'deal_id' => [
                'nullable',
                'uuid',
                Rule::exists('pipeline_deals', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
