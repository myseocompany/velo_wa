<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoyaltyAdjustRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'not_in:0', 'min:-10000', 'max:10000'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}

