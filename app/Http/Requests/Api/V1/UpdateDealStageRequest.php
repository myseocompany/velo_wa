<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\DealStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDealStageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'stage' => ['required', 'string', Rule::in(array_column(DealStage::cases(), 'value'))],
        ];
    }

    public function stage(): DealStage
    {
        return DealStage::from($this->validated('stage'));
    }
}
