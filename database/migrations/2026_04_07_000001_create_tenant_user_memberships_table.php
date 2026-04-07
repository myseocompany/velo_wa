<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('role')->default('agent');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('max_concurrent_conversations')->default(10);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'role']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        $this->backfillFromLegacyUsers();
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_memberships');
    }

    private function backfillFromLegacyUsers(): void
    {
        $now = now();
        $batch = [];

        $users = DB::table('users')
            ->select(['id', 'tenant_id', 'role', 'is_active', 'max_concurrent_conversations', 'last_seen_at', 'created_at'])
            ->whereNotNull('tenant_id')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->cursor();

        foreach ($users as $user) {
            $batch[] = [
                'id' => (string) Str::uuid(),
                'tenant_id' => (string) $user->tenant_id,
                'user_id' => (string) $user->id,
                'role' => (string) ($user->role ?: 'agent'),
                'is_active' => (bool) $user->is_active,
                'max_concurrent_conversations' => (int) ($user->max_concurrent_conversations ?? 10),
                'last_seen_at' => $user->last_seen_at,
                'created_at' => $user->created_at ?? $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 500) {
                DB::table('tenant_user_memberships')->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('tenant_user_memberships')->insert($batch);
        }
    }
};
