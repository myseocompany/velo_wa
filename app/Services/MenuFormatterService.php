<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MenuCategory;
use App\Models\Tenant;

class MenuFormatterService
{
    private const MAX_MESSAGE_LENGTH = 1024;

    /**
     * Generate WhatsApp-formatted menu message(s) for a tenant.
     *
     * @return string[] One or more message chunks (split if > 1024 chars)
     */
    public function format(Tenant $tenant): array
    {
        $categories = MenuCategory::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['availableItems'])
            ->get()
            ->filter(fn ($cat) => $cat->availableItems->isNotEmpty());

        if ($categories->isEmpty()) {
            return ["No hay ítems disponibles en el menú en este momento."];
        }

        $lines   = [];
        $lines[] = "🍽️ *Menú — {$tenant->name}*";
        $lines[] = '';

        foreach ($categories as $category) {
            $lines[] = "*{$category->name}*";

            foreach ($category->availableItems as $item) {
                $line = "• {$item->name} — {$item->formattedPrice()}";
                $lines[] = $line;

                if ($item->description) {
                    $lines[] = "  _{$item->description}_";
                }
            }

            $lines[] = '';
        }

        $lines[] = "📲 Para pedir, escribe el nombre del plato";

        return $this->splitIntoChunks(implode("\n", $lines));
    }

    /**
     * Split a long message into chunks no larger than MAX_MESSAGE_LENGTH.
     *
     * @return string[]
     */
    private function splitIntoChunks(string $text): array
    {
        if (mb_strlen($text) <= self::MAX_MESSAGE_LENGTH) {
            return [$text];
        }

        $chunks    = [];
        $lines     = explode("\n", $text);
        $current   = '';

        foreach ($lines as $line) {
            $candidate = $current === '' ? $line : "{$current}\n{$line}";

            if (mb_strlen($candidate) > self::MAX_MESSAGE_LENGTH) {
                if ($current !== '') {
                    $chunks[]  = $current;
                }
                $current = $line;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
