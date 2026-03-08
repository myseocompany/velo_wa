<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $quickReplyId = $this->route('quickReply')?->id;

        return [
            'shortcut' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('quick_replies', 'shortcut')
                    ->where('tenant_id', $tenantId)
                    ->ignore($quickReplyId),
            ],
            'title'    => ['required', 'string', 'max:100'],
            'body'     => ['required', 'string', 'max:4096'],
            'category' => ['nullable', 'string', 'max:50'],
        ];
    }
}
