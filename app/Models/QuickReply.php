<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuickReply extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'shortcut',
        'title',
        'body',
        'has_variables',
        'category',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'has_variables' => 'boolean',
            'usage_count' => 'integer',
        ];
    }

    /** Interpolate variables in the body */
    public function interpolate(array $variables): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($variables) {
            return $variables[$matches[1]] ?? $matches[0];
        }, $this->body);
    }
}
