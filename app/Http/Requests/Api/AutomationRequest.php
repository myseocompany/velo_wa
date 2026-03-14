<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\AutomationActionType;
use App\Enums\AutomationTriggerType;
use App\Enums\DealStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutomationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $triggerConfig = $this->input('trigger_config');

        if (! is_array($triggerConfig)) {
            return;
        }

        if (
            array_key_exists('case_sensitive', $triggerConfig)
            && ! array_key_exists('case_insensitive', $triggerConfig)
        ) {
            $triggerConfig['case_insensitive'] = ! filter_var(
                $triggerConfig['case_sensitive'],
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE
            );
            unset($triggerConfig['case_sensitive']);

            $this->merge(['trigger_config' => $triggerConfig]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $actionType = $this->input('action_type');

        return [
            'name'           => ['required', 'string', 'max:255'],
            'trigger_type'   => ['required', 'string', Rule::in(array_column(AutomationTriggerType::cases(), 'value'))],
            'trigger_config' => ['nullable', 'array'],
            // Keyword config
            'trigger_config.keywords'       => ['nullable', 'array'],
            'trigger_config.keywords.*'     => ['string', 'max:100'],
            'trigger_config.match_type'     => ['nullable', 'string', Rule::in(['any', 'all'])],
            'trigger_config.case_insensitive' => ['nullable', 'boolean'],
            // No-response timeout config
            'trigger_config.minutes'        => ['nullable', 'integer', 'min:1', 'max:10080'],
            // Outside hours — optional custom schedule
            'trigger_config.use_tenant_hours' => ['nullable', 'boolean'],

            'action_type'   => ['required', 'string', Rule::in(array_column(AutomationActionType::cases(), 'value'))],
            'action_config' => ['required', 'array'],
            // Send message
            'action_config.message'   => [
                Rule::requiredIf($actionType === AutomationActionType::SendMessage->value),
                'nullable',
                'string',
                'max:4096',
            ],
            // Assign agent
            'action_config.agent_id'  => [
                Rule::requiredIf($actionType === AutomationActionType::AssignAgent->value),
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)),
            ],
            // Add tag
            'action_config.tags'      => ['nullable', 'array'],
            'action_config.tags.*'    => ['string', 'max:50'],
            // Move stage
            'action_config.stage'     => [
                Rule::requiredIf($actionType === AutomationActionType::MoveStage->value),
                'nullable',
                Rule::enum(DealStage::class),
            ],

            'is_active' => ['boolean'],
            'priority'  => ['integer', 'min:1', 'max:999'],
        ];
    }
}
