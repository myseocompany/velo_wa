<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiAgentRequest extends FormRequest
{
    private const AVAILABLE_MODELS = [
        'claude-haiku-4-5',
        'claude-sonnet-4-5',
        'claude-opus-4-1',
        'gpt-4o-mini',
        'gpt-4.1-mini',
        'gpt-4.1',
        'gemini-2.0-flash',
        'gemini-1.5-pro',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string', 'max:40000'],
            'llm_model' => ['required', 'string', Rule::in(self::AVAILABLE_MODELS)],
            'context_messages' => ['required', 'integer', 'min:3', 'max:50'],
            'is_enabled' => ['sometimes', 'boolean'],
            'tool_calling_enabled' => ['sometimes', 'boolean'],
            'whatsapp_line_id' => ['nullable', 'uuid', Rule::exists('whatsapp_lines', 'id')->where('tenant_id', $this->user()->tenant_id)],
        ];
    }

    public static function availableModels(): array
    {
        return self::AVAILABLE_MODELS;
    }
}
