<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'assigned_to' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
