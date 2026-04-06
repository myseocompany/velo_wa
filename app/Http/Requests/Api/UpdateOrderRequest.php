<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contact_id' => ['sometimes', 'required', 'uuid', Rule::exists('contacts', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'conversation_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('conversations', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'assigned_to' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            'total' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'items' => ['sometimes', 'nullable', 'array', 'max:100'],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.qty' => ['nullable', 'integer', 'min:1'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
