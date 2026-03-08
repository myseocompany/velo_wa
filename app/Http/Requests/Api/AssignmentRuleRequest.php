<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\AssignmentRuleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignmentRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:100'],
            'type'      => ['required', Rule::enum(AssignmentRuleType::class)],
            'priority'  => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['boolean'],
            'config'    => ['nullable', 'array'],

            // Round-robin / least-busy: optional agent pool
            'config.agent_ids'   => ['nullable', 'array'],
            'config.agent_ids.*' => ['uuid'],

            // Least-busy: max conversations cap
            'config.max_conversations' => ['nullable', 'integer', 'min:1'],

            // Tag-based: mappings array
            'config.tag_mappings'            => ['nullable', 'array'],
            'config.tag_mappings.*.tag'      => ['required_with:config.tag_mappings', 'string', 'max:50'],
            'config.tag_mappings.*.agent_ids' => ['required_with:config.tag_mappings', 'array'],
        ];
    }
}
