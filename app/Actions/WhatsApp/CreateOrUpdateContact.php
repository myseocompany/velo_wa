<?php

declare(strict_types=1);

namespace App\Actions\WhatsApp;

use App\Enums\ContactSource;
use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CreateOrUpdateContact
{
    /**
     * @param  array{remoteJid: string, pushName: ?string, profilePicUrl: ?string, phone?: ?string}  $waData
     */
    public function handle(Tenant $tenant, array $waData): Contact
    {
        $waId  = $waData['remoteJid']; // e.g. "573001234567@s.whatsapp.net"
        $phone = $this->resolvePhone($waId, $waData['phone'] ?? null);

        return DB::transaction(function () use ($tenant, $waId, $phone, $waData): Contact {
            /** @var Contact|null $contact */
            $contact = Contact::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('wa_id', $waId)
                ->lockForUpdate()
                ->first();

            if ($contact) {
                $contact->update([
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
                    $contact->update([
                        'wa_id'           => $waId,
                        'phone'           => $phone ?? $contact->phone,
                        'push_name'       => $waData['pushName'] ?? $contact->push_name,
                        'profile_pic_url' => $waData['profilePicUrl'] ?? $contact->profile_pic_url,
                        'last_contact_at' => now(),
                    ]);
                } else {
                    $contact = Contact::create([
                        'tenant_id'        => $tenant->id,
                        'wa_id'            => $waId,
                        'phone'            => $phone,
                        'push_name'        => $waData['pushName'] ?? null,
                        'profile_pic_url'  => $waData['profilePicUrl'] ?? null,
                        'source'           => ContactSource::WhatsApp,
                        'first_contact_at' => now(),
                        'last_contact_at'  => now(),
                    ]);
                }
            }

            return $contact;
        });
    }

    private function extractPhone(string $remoteJid): string
    {
        // "573001234567@s.whatsapp.net" → "573001234567"
        return explode('@', $remoteJid)[0];
    }

    private function resolvePhone(string $remoteJid, ?string $phoneHint): ?string
    {
        $normalizedHint = $this->normalizePhone($phoneHint);
        if ($normalizedHint !== null) {
            return $normalizedHint;
        }

        if (str_ends_with($remoteJid, '@s.whatsapp.net')) {
            return $this->normalizePhone($this->extractPhone($remoteJid));
        }

        // LID/JID aliases are not always phone numbers.
        return null;
    }

    private function normalizePhone(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $value);

        return $normalized !== '' ? $normalized : null;
    }
}
