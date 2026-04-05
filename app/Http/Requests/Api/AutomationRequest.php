<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\AutomationActionType;
use App\Enums\AutomationTriggerType;
use App\Enums\DealStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'action_config' => [
                in_array($actionType, [AutomationActionType::SendMenu->value], true) ? 'nullable' : 'required',
                'array',
            ],
            // Send message
            'action_config.message'   => [
                Rule::requiredIf($actionType === AutomationActionType::SendMessage->value),
                'nullable',
                'string',
                'max:4096',
            ],
            // Send sequence
            'action_config.steps' => [
                Rule::requiredIf($actionType === AutomationActionType::SendSequence->value),
                'nullable',
                'array',
                'min:1',
                'max:12',
            ],
            'action_config.steps.*.type' => [
                Rule::requiredIf($actionType === AutomationActionType::SendSequence->value),
                'nullable',
                'string',
                Rule::in(['text', 'image', 'video', 'audio', 'document']),
            ],
            'action_config.steps.*.body' => ['nullable', 'string', 'max:4096'],
            'action_config.steps.*.media_url' => ['nullable', 'string', 'max:2048'],
            'action_config.steps.*.delay_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('action_type') !== AutomationActionType::SendSequence->value) {
                return;
            }

            $steps = $this->input('action_config.steps', []);
            if (! is_array($steps)) {
                return;
            }

            foreach ($steps as $index => $step) {
                if (! is_array($step)) {
                    continue;
                }

                $type = (string) ($step['type'] ?? '');
                $body = trim((string) ($step['body'] ?? ''));
                $mediaUrl = trim((string) ($step['media_url'] ?? ''));

                if ($type === 'text' && $body === '') {
                    $validator->errors()->add(
                        "action_config.steps.$index.body",
                        'Los pasos de texto requieren contenido en body.'
                    );
                }

                if ($type !== '' && $type !== 'text' && $mediaUrl === '') {
                    $validator->errors()->add(
                        "action_config.steps.$index.media_url",
                        'Los pasos multimedia requieren media_url.'
                    );
                }
            }
        });
    }
}
