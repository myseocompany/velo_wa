<?php

declare(strict_types=1);

namespace App\Services\AiAgent\Tools;

use App\Models\Conversation;
use Illuminate\Support\Str;

class UpdateContactTool implements Tool
{
    public function name(): string
    {
        return 'update_contact';
    }

    public function description(): string
    {
        return 'Actualiza nombre o notas del contacto actual.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'notes' => ['type' => 'string'],
                'service_of_interest' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Conversation $conversation, array $input): array
    {
        $contact = $conversation->contact;
        if (! $contact) {
            return ['error' => 'contact_not_found'];
        }

        $updates = [];
        if (is_string($input['name'] ?? null) && trim($input['name']) !== '') {
            $updates['name'] = Str::limit(trim($input['name']), 255, '');
        }

        $notes = [];
        if (is_string($input['service_of_interest'] ?? null) && trim($input['service_of_interest']) !== '') {
            $notes[] = '[interes: ' . Str::limit(trim($input['service_of_interest']), 80, '') . ']';
        }
        if (is_string($input['notes'] ?? null) && trim($input['notes']) !== '') {
            $notes[] = trim($input['notes']);
        }
        if ($notes !== []) {
            $updates['notes'] = trim(implode("\n", array_filter([(string) $contact->notes, ...$notes])));
        }

        if ($updates !== []) {
            $contact->update($updates);
        }

        return ['ok' => true];
    }
}
