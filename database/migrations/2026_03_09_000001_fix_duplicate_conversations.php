<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTIVE_CONVERSATION_INDEX = 'unique_active_conversation_per_contact';

    private const ACTIVE_STATUSES = ['open', 'pending'];

    public function up(): void
    {
        // Steps 1 & 2: Find duplicate active conversations and merge them.
        // Written with the query builder (portable: PostgreSQL + SQLite).
        $rows = DB::table('conversations')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderBy('tenant_id')
            ->orderBy('contact_id')
            ->orderByRaw("COALESCE(first_message_at, created_at) ASC")
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'contact_id']);

        // Group by tenant+contact
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->tenant_id . '|' . $row->contact_id][] = $row->id;
        }

        foreach ($grouped as $ids) {
            if (count($ids) <= 1) {
                continue;
            }

            $canonicalId  = $ids[0]; // first = oldest (sorted ASC above)
            $duplicateIds = array_slice($ids, 1);

            // Reassign messages from duplicates to the canonical conversation
            DB::table('messages')
                ->whereIn('conversation_id', $duplicateIds)
                ->update(['conversation_id' => $canonicalId]);

            // Delete duplicate conversations
            DB::table('conversations')
                ->whereIn('id', $duplicateIds)
                ->delete();
        }

        $this->createActiveConversationIndex();
    }

    public function down(): void
    {
        $this->dropActiveConversationIndex();
    }

    private function createActiveConversationIndex(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new \RuntimeException("Driver [{$driver}] does not support this partial index migration.");
        }

        DB::statement(
            "CREATE UNIQUE INDEX IF NOT EXISTS " . self::ACTIVE_CONVERSATION_INDEX . "
            ON conversations (tenant_id, contact_id)
            WHERE status IN ('open', 'pending')"
        );
    }

    private function dropActiveConversationIndex(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS ' . self::ACTIVE_CONVERSATION_INDEX);
        }
    }
};
