<?php

declare(strict_types=1);

namespace App\Actions\WhatsApp;

use App\Enums\ContactSource;
use App\Models\Contact;
use App\Models\Tenant;

class CreateOrUpdateContact
{
    /**
     * @param  array{remoteJid: string, pushName: ?string, profilePicUrl: ?string}  $waData
     */
    public function handle(Tenant $tenant, array $waData): Contact
    {
        $waId  = $waData['remoteJid']; // e.g. "573001234567@s.whatsapp.net"
        $phone = $this->extractPhone($waId);

        /** @var Contact $contact */
        $contact = Contact::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('wa_id', $waId)
            ->first();

        if ($contact) {
            $contact->update([
                'push_name'       => $waData['pushName'] ?? $contact->push_name,
                'profile_pic_url' => $waData['profilePicUrl'] ?? $contact->profile_pic_url,
                'last_contact_at' => now(),
            ]);
        } else {
            $contact = Contact::create([
                'tenant_id'       => $tenant->id,
                'wa_id'           => $waId,
                'phone'           => $phone,
                'push_name'       => $waData['pushName'] ?? null,
                'profile_pic_url' => $waData['profilePicUrl'] ?? null,
                'source'          => ContactSource::WhatsApp,
                'first_contact_at' => now(),
                'last_contact_at'  => now(),
            ]);
        }

        return $contact;
    }

    private function extractPhone(string $remoteJid): string
    {
        // "573001234567@s.whatsapp.net" → "573001234567"
        return explode('@', $remoteJid)[0];
    }
}
