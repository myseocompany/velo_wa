<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'uuid', Rule::exists('contacts', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'conversation_id' => ['nullable', 'uuid', Rule::exists('conversations', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'assigned_to' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'status' => ['nullable', 'string', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            'total' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.qty' => ['nullable', 'integer', 'min:1'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

