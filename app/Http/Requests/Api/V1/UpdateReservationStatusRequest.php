<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReservationStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_column(ReservationStatus::cases(), 'value'))],
        ];
    }

    public function status(): ReservationStatus
    {
        return ReservationStatus::from($this->validated('status'));
    }
}

