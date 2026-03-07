<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactSource;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'wa_id',
        'phone',
        'name',
        'push_name',
        'profile_pic_url',
        'email',
        'company',
        'notes',
        'tags',
        'custom_fields',
        'assigned_to',
        'source',
        'first_contact_at',
        'last_contact_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'custom_fields' => 'array',
            'source' => ContactSource::class,
            'first_contact_at' => 'datetime',
            'last_contact_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class)->latest('last_message_at');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(PipelineDeal::class);
    }

    public function displayName(): string
    {
        return $this->name ?? $this->push_name ?? $this->phone ?? 'Desconocido';
    }
}
