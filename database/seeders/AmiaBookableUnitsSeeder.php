<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BookableUnit;
use Illuminate\Database\Seeder;

class AmiaBookableUnitsSeeder extends Seeder
{
    private const TENANT_ID = '019d92aa-9b2a-72a3-ad07-d59168920642';

    public function run(): void
    {
        $definitions = [
            [
                'slug' => 'amia-default',
                'name' => 'Profesional AMIA',
                'type' => 'professional',
                'capacity' => 1,
                'services' => array_keys((array) config('amia.service_durations', [])),
            ],
        ];

        $seenSlugs = [];
        $created = 0;
        $updated = 0;

        foreach ($definitions as $definition) {
            $seenSlugs[] = $definition['slug'];
            $unit = BookableUnit::withoutGlobalScopes()
                ->where('tenant_id', self::TENANT_ID)
                ->where('settings->slug', $definition['slug'])
                ->first();

            $payload = [
                'tenant_id' => self::TENANT_ID,
                'type' => $definition['type'],
                'name' => $definition['name'],
                'capacity' => $definition['capacity'],
                'settings' => [
                    'slug' => $definition['slug'],
                    'services' => $definition['services'],
                ],
                'is_active' => true,
            ];

            if ($unit) {
                $unit->update($payload);
                $updated++;
            } else {
                BookableUnit::withoutGlobalScopes()->create($payload);
                $created++;
            }
        }

        $deactivated = 0;
        BookableUnit::withoutGlobalScopes()
            ->where('tenant_id', self::TENANT_ID)
            ->where('type', 'professional')
            ->get()
            ->each(function (BookableUnit $unit) use ($seenSlugs, &$deactivated): void {
                $slug = $unit->settings['slug'] ?? null;
                if (is_string($slug) && ! in_array($slug, $seenSlugs, true) && $unit->is_active) {
                    $unit->update(['is_active' => false]);
                    $deactivated++;
                }
            });

        $this->command?->info("AMIA bookable units: created {$created} | updated {$updated} | deactivated {$deactivated}");
    }
}
