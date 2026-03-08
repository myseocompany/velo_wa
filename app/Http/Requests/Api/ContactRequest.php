<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name'        => ['nullable', 'string', 'max:120'],
            'email'       => ['nullable', 'email', 'max:254'],
            'company'     => ['nullable', 'string', 'max:120'],
            'notes'       => ['nullable', 'string', 'max:2000'],
            'tags'        => ['nullable', 'array'],
            'tags.*'      => ['string', 'max:50'],
            'assigned_to' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
