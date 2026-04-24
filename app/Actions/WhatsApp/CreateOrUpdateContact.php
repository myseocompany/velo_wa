<?php

declare(strict_types=1);

namespace App\Actions\WhatsApp;

use App\Enums\ContactSource;
use App\Models\Contact;
use App\Models\ContactIdentityAlias;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CreateOrUpdateContact
{
    /**
     * @param  array{
     *   remoteJid: string,
     *   pushName: ?string,
     *   profilePicUrl: ?string,
     *   phone?: ?string,
     *   aliases?: array<int, string>
     * }  $waData
     */
    public function handle(Tenant $tenant, array $waData, ?string $linePhone = null): Contact
    {
        $aliases = $this->normalizeAliases(array_merge(
            [$waData['remoteJid']], // e.g. "573001234567@s.whatsapp.net"
            $waData['aliases'] ?? [],
        ));
        $primaryWaId = $this->selectPrimaryWaId($aliases);
        $phone       = $this->sanitizeResolvedPhone(
            $linePhone ?? $tenant->wa_phone,
            $this->resolvePhone($aliases, $waData['phone'] ?? null),
            $primaryWaId,
        );

        return DB::transaction(function () use ($tenant, $primaryWaId, $aliases, $phone, $waData): Contact {
            /** @var Contact|null $contact */
            $contact = Contact::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->whereIn('wa_id', $aliases)
                ->lockForUpdate()
                ->first();

            if (! $contact && $aliases !== []) {
                $contactId = ContactIdentityAlias::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('alias', $aliases)
                    ->value('contact_id');

                if ($contactId) {
                    $contact = Contact::withoutGlobalScope('tenant')
                        ->where('tenant_id', $tenant->id)
                        ->where('id', $contactId)
                        ->lockForUpdate()
                        ->first();
                }
            }

            if ($contact) {
                $nextWaId = $this->resolveNextWaId($contact->wa_id, $primaryWaId);
                $contact->update([
                    'wa_id'            => $nextWaId,
                    'phone'           => $phone ?? $contact->phone,
                    'push_name'       => $waData['pushName'] ?? $contact->push_name,
                    'profile_pic_url' => $waData['profilePicUrl'] ?? $contact->profile_pic_url,
                    'last_contact_at' => now(),
                ]);
            } else {
                // Fallback: find an existing contact with matching phone
                // (covers manually created records and LID/PN identity switches).
                $contact = null;

                if ($phone) {
                    $contact = Contact::withoutGlobalScope('tenant')
                        ->where('tenant_id', $tenant->id)
                        ->where('phone', $phone)
                        ->lockForUpdate()
                        ->orderByRaw('CASE WHEN wa_id IS NULL THEN 0 ELSE 1 END')
                        ->first();
                }

                if ($contact) {
                    // Link/refresh the WhatsApp identity for this phone.
                    $nextWaId = $this->resolveNextWaId($contact->wa_id, $primaryWaId);
                    $contact->update([
                        'wa_id'           => $nextWaId,
                        'phone'           => $phone ?? $contact->phone,
                        'push_name'       => $waData['pushName'] ?? $contact->push_name,
                        'profile_pic_url' => $waData['profilePicUrl'] ?? $contact->profile_pic_url,
                        'last_contact_at' => now(),
                    ]);
                } else {
                    $contact = Contact::create([
                        'tenant_id'        => $tenant->id,
                        'wa_id'            => $primaryWaId,
                        'phone'            => $phone,
                        'push_name'        => $waData['pushName'] ?? null,
                        'profile_pic_url'  => $waData['profilePicUrl'] ?? null,
                        'source'           => ContactSource::WhatsApp,
                        'first_contact_at' => now(),
                        'last_contact_at'  => now(),
                    ]);
                }
            }

            $this->upsertAliases($tenant->id, $contact->id, $aliases);

            return $contact;
        });
    }

    private function extractPhone(string $remoteJid): string
    {
        // "573001234567@s.whatsapp.net" → "573001234567"
        return explode('@', $remoteJid)[0];
    }

    private function resolvePhone(array $aliases, ?string $phoneHint): ?string
    {
        $normalizedHint = $this->normalizePhone($phoneHint);
        if ($normalizedHint !== null) {
            return $normalizedHint;
        }

        foreach ($aliases as $alias) {
            if (str_ends_with($alias, '@s.whatsapp.net')) {
                return $this->normalizePhone($this->extractPhone($alias));
            }
        }

        // LID/JID aliases are not always phone numbers.
        return null;
    }

    private function sanitizeResolvedPhone(?string $selfPhone, ?string $phone, ?string $primaryWaId): ?string
    {
        if ($phone === null) {
            return null;
        }

        $tenantPhone = $this->normalizePhone($selfPhone);
        $tenantWaId = $tenantPhone ? $tenantPhone.'@s.whatsapp.net' : null;

        if ($tenantPhone !== null && $phone === $tenantPhone && $primaryWaId !== $tenantWaId) {
            if ($primaryWaId && str_ends_with($primaryWaId, '@s.whatsapp.net')) {
                return $this->normalizePhone($this->extractPhone($primaryWaId));
            }

            return null;
        }

        return $phone;
    }

    private function normalizePhone(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<int, string>  $aliases
     * @return array<int, string>
     */
    private function normalizeAliases(array $aliases): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn ($v) => is_string($v) ? trim($v) : '',
            $aliases
        ))));

        return $normalized;
    }

    /**
     * @param  array<int, string>  $aliases
     */
    private function selectPrimaryWaId(array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (str_ends_with($alias, '@s.whatsapp.net')) {
                return $alias;
            }
        }

        return $aliases[0] ?? null;
    }

    private function resolveNextWaId(?string $currentWaId, ?string $incomingWaId): ?string
    {
        if (! $incomingWaId) {
            return $currentWaId;
        }

        if (! $currentWaId) {
            return $incomingWaId;
        }

        // Preserve PN identity as canonical whenever available.
        if (str_ends_with($currentWaId, '@s.whatsapp.net')) {
            return $currentWaId;
        }

        if (str_ends_with($incomingWaId, '@s.whatsapp.net')) {
            return $incomingWaId;
        }

        return $incomingWaId;
    }

    /**
     * @param  array<int, string>  $aliases
     */
    private function upsertAliases(string $tenantId, string $contactId, array $aliases): void
    {
        if ($aliases === []) {
            return;
        }

        $now = now();

        foreach ($aliases as $alias) {
            $record = ContactIdentityAlias::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('alias', $alias)
                ->lockForUpdate()
                ->first();

            if ($record) {
                $record->update([
                    'contact_id' => $contactId,
                    'alias_type' => $this->aliasType($alias),
                    'last_seen_at' => $now,
                ]);
                continue;
            }

            ContactIdentityAlias::create([
                'tenant_id' => $tenantId,
                'contact_id' => $contactId,
                'alias' => $alias,
                'alias_type' => $this->aliasType($alias),
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);
        }
    }

    private function aliasType(string $alias): string
    {
        if (str_ends_with($alias, '@lid')) {
            return 'lid';
        }

        if (str_ends_with($alias, '@s.whatsapp.net')) {
            return 'pn';
        }

        return 'other';
    }
}
