<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canManageTeam();
    }

    public function rules(): array
    {
        return [
            'name'                          => ['sometimes', 'string', 'max:255'],
            'role'                          => ['sometimes', new Enum(UserRole::class)],
            'max_concurrent_conversations'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
