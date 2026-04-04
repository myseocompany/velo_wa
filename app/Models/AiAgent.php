<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiAgent extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'system_prompt',
        'llm_model',
        'is_enabled',
        'context_messages',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'context_messages' => 'integer',
        ];
    }
}
