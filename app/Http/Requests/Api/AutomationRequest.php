<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\AutomationActionType;
use App\Enums\AutomationTriggerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutomationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'trigger_type'   => ['required', 'string', Rule::in(array_column(AutomationTriggerType::cases(), 'value'))],
            'trigger_config' => ['nullable', 'array'],
            // Keyword config
            'trigger_config.keywords'       => ['nullable', 'array'],
            'trigger_config.keywords.*'     => ['string', 'max:100'],
            'trigger_config.match_type'     => ['nullable', 'string', Rule::in(['any', 'all'])],
            'trigger_config.case_sensitive' => ['nullable', 'boolean'],
            // No-response timeout config
            'trigger_config.minutes'        => ['nullable', 'integer', 'min:1', 'max:10080'],
            // Outside hours — optional custom schedule
            'trigger_config.use_tenant_hours' => ['nullable', 'boolean'],

            'action_type'   => ['required', 'string', Rule::in(array_column(AutomationActionType::cases(), 'value'))],
            'action_config' => ['required', 'array'],
            // Send message
            'action_config.message'   => ['nullable', 'string', 'max:4096'],
            // Assign agent
            'action_config.agent_id'  => ['nullable', 'uuid'],
            // Add tag
            'action_config.tags'      => ['nullable', 'array'],
            'action_config.tags.*'    => ['string', 'max:50'],
            // Move stage
            'action_config.stage'     => ['nullable', 'string'],

            'is_active' => ['boolean'],
            'priority'  => ['integer', 'min:1', 'max:999'],
        ];
    }
}
