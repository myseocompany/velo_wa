<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutomationActionType;
use App\Enums\AutomationTriggerType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'trigger_type',
        'trigger_config',
        'action_type',
        'action_config',
        'is_active',
        'priority',
        'execution_count',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => AutomationTriggerType::class,
            'trigger_config' => 'array',
            'action_type' => AutomationActionType::class,
            'action_config' => 'array',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'execution_count' => 'integer',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class);
    }

    protected function triggerConfig(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): array {
                $config = is_array($value)
                    ? $value
                    : (json_decode((string) ($value ?? '[]'), true) ?: []);

                if (
                    array_key_exists('case_sensitive', $config)
                    && ! array_key_exists('case_insensitive', $config)
                ) {
                    $config['case_insensitive'] = ! (bool) $config['case_sensitive'];
                    unset($config['case_sensitive']);
                }

                return $config;
            },
        );
    }
}
