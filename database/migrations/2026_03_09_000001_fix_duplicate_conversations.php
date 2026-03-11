<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Merge messages from duplicate active conversations into the oldest one.
        // "Oldest" = smallest first_message_at (or created_at as fallback).
        DB::statement("
            UPDATE messages m
            SET conversation_id = canonical.id
            FROM (
                SELECT DISTINCT ON (tenant_id, contact_id) id, tenant_id, contact_id
                FROM conversations
                WHERE status IN ('open', 'pending')
                ORDER BY tenant_id, contact_id, COALESCE(first_message_at, created_at) ASC
            ) canonical
            JOIN conversations dup
                ON  dup.tenant_id  = canonical.tenant_id
                AND dup.contact_id = canonical.contact_id
                AND dup.id        != canonical.id
                AND dup.status    IN ('open', 'pending')
            WHERE m.conversation_id = dup.id
        ");

        // Step 2: Delete duplicate active conversations (keep the oldest per contact/tenant).
        DB::statement("
            DELETE FROM conversations
            WHERE id IN (
                SELECT id FROM (
                    SELECT id,
                           ROW_NUMBER() OVER (
                               PARTITION BY tenant_id, contact_id
                               ORDER BY COALESCE(first_message_at, created_at) ASC
                           ) AS rn
                    FROM conversations
                    WHERE status IN ('open', 'pending')
                ) ranked
                WHERE rn > 1
            )
        ");

        // Step 3: Add partial unique index — only one active (open/pending) conversation
        // per contact per tenant is allowed at the database level.
        DB::statement("
            CREATE UNIQUE INDEX unique_active_conversation_per_contact
            ON conversations (tenant_id, contact_id)
            WHERE status IN ('open', 'pending')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_active_conversation_per_contact');
    }
};
