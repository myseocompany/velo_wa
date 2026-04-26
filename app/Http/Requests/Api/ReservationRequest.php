<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReservationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'uuid', Rule::exists('contacts', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'conversation_id' => ['nullable', 'uuid', Rule::exists('conversations', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'assigned_to' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'bookable_unit_id' => ['nullable', 'uuid', Rule::exists('bookable_units', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'service' => $this->serviceRules(),
            'status' => ['nullable', 'string', Rule::in(array_column(ReservationStatus::cases(), 'value'))],
            'starts_at' => ['required', 'date', 'after_or_equal:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function serviceRules(): array
    {
        $rules = ['nullable', 'string', 'max:80'];
        $tenant = $this->user()?->tenant;

        if (($tenant?->onboarding_vertical ?? null) === 'health') {
            $services = array_keys((array) config('amia.service_durations', []));
            if ($services !== []) {
                $rules[] = Rule::in($services);
            }
        }

        return $rules;
    }
}
