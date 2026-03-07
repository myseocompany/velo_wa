<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssignmentRuleType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentRule extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'priority',
        'is_active',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssignmentRuleType::class,
            'priority' => 'integer',
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }
}
