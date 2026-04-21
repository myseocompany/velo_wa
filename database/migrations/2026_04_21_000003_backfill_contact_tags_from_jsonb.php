<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Migrate existing free-text tags from contacts.tags (JSONB) → tags + contact_tag tables.
     * Uses PHP-side Str::slug() so the slug algorithm is consistent with the application layer.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('contacts', 'tags')) {
            return;
        }

        // Only run on pgsql (local SQLite tests have no real data to migrate)
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $rows = DB::table('contacts')
            ->whereNotNull('tags')
            ->whereRaw("tags != '[]'::jsonb")
            ->select('id', 'tenant_id', 'tags')
            ->get();

        foreach ($rows as $row) {
            $tagNames = json_decode($row->tags, true);
            if (! is_array($tagNames) || empty($tagNames)) {
                continue;
            }

            foreach ($tagNames as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }

                $slug = Str::slug($name);
                if ($slug === '') {
                    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
                }

                // Find or create the tag for this tenant
                $tag = Tag::withoutGlobalScope('tenant')->firstOrCreate(
                    ['tenant_id' => $row->tenant_id, 'slug' => $slug],
                    ['id' => Str::uuid()->toString(), 'name' => $name, 'color' => '#6366f1', 'exclude_from_metrics' => false],
                );

                // Attach to contact (ignore duplicate)
                DB::table('contact_tag')->insertOrIgnore([
                    'contact_id' => $row->id,
                    'tag_id'     => $tag->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Revert is handled by the drop-column migration's down() restoring the column.
        // Nothing to do here — data in contact_tag / tags will be dropped with those tables.
    }
};
