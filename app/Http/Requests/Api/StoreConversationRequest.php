<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->has('phone')
            ? preg_replace('/[\s\+\-\(\)]+/', '', $this->string('phone')->toString())
            : null;

        $this->merge([
            'phone' => $phone,
            'name' => $this->filled('name') ? trim($this->input('name')) : null,
            'email' => $this->filled('email') ? trim($this->input('email')) : null,
            'company' => $this->filled('company') ? trim($this->input('company')) : null,
            'notes' => $this->filled('notes') ? trim($this->input('notes')) : null,
            'assigned_to' => $this->filled('assigned_to') ? $this->input('assigned_to') : null,
            'whatsapp_line_id' => $this->filled('whatsapp_line_id') ? $this->input('whatsapp_line_id') : null,
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'phone' => ['required', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:254'],
            'company' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'whatsapp_line_id' => [
                'nullable',
                'uuid',
                Rule::exists('whatsapp_lines', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
