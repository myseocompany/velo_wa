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
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string', 'max:12000'],
            'llm_model' => ['required', 'string', Rule::in(self::AVAILABLE_MODELS)],
            'context_messages' => ['required', 'integer', 'min:3', 'max:50'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public static function availableModels(): array
    {
        return self::AVAILABLE_MODELS;
    }
}
