<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class InviteTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canManageTeam();
    }

    public function rules(): array
    {
        return [
            'name'                          => ['required', 'string', 'max:255'],
            'email'                         => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'                          => ['required', new Enum(UserRole::class)],
            'max_concurrent_conversations'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
