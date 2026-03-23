<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\DealStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PipelineDealRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:255'],
            'contact_id'      => ['required', 'uuid', Rule::exists('contacts', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'conversation_id' => ['nullable', 'uuid', Rule::exists('conversations', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'stage'           => ['nullable', 'string', Rule::in(array_column(DealStage::cases(), 'value'))],
            'value'           => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'currency'        => ['nullable', 'string', 'size:3'],
            'assigned_to'     => ['nullable', 'uuid', Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'notes'           => ['nullable', 'string', 'max:5000'],
            'won_product'     => ['nullable', 'string', 'max:500'],
            'lost_reason'     => ['nullable', 'string', 'max:500'],
            'follow_up_at'    => ['nullable', 'date', 'after:now'],
            'follow_up_note'  => ['nullable', 'string', 'max:1000'],
        ];
    }
}
