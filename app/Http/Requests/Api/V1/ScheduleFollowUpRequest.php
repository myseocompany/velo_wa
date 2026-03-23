<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleFollowUpRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'follow_up_at'   => ['nullable', 'date'],
            'follow_up_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
