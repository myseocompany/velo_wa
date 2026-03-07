<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MessageStatus;
use App\Enums\WaStatus;
use App\Events\WaStatusUpdated;
use App\Jobs\HandleInboundMessage;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class WebhookHandlerService
{
    public function handle(array $payload): void
    {
        $event        = $payload['event'] ?? '';
        $instanceName = $payload['instance'] ?? '';

        Log::debug('Webhook received', ['event' => $event, 'instance' => $instanceName]);

        $tenant = $this->resolveTenant($instanceName);

        match (true) {
            str_starts_with($event, 'messages.upsert')  => $this->handleMessagesUpsert($payload, $tenant),
            str_starts_with($event, 'messages.update')  => $this->handleMessagesUpdate($payload['data'] ?? [], $tenant),
            str_starts_with($event, 'connection.update') => $this->handleConnectionUpdate($payload['data'] ?? [], $tenant),
            str_starts_with($event, 'qrcode.updated')   => $this->handleQrCodeUpdated($payload['data'] ?? [], $tenant),
            str_starts_with($event, 'contacts.upsert')  => $this->handleContactsUpsert($payload['data'] ?? [], $tenant),
            str_starts_with($event, 'contacts.update')  => $this->handleContactsUpsert($payload['data'] ?? [], $tenant),
            default => Log::debug('Unhandled webhook event', ['event' => $event]),
        };
    }

    // ─── Event handlers ───────────────────────────────────────────────────────

    private function handleMessagesUpsert(array $payload, ?Tenant $tenant): void
    {
        if (! $tenant) {
            return;
        }

        HandleInboundMessage::dispatch($payload, $tenant->id)->onQueue('whatsapp');
    }

    private function handleMessagesUpdate(array $updates, ?Tenant $tenant): void
    {
        if (! $tenant) {
            return;
        }

        // v2.3.7 sends a single object with keyId + status
        // v2.2.x sent {key: {id: ...}, update: {status: int}}
        $items = isset($updates['keyId']) ? [$updates] : (isset($updates['key']) ? [$updates] : $updates);

        foreach ($items as $item) {
            // v2.3.7: keyId at root level, status as string ("READ", "DELIVERED", etc.)
            $waMessageId = $item['keyId'] ?? $item['key']['id'] ?? null;
            $statusRaw   = $item['status'] ?? $item['update']['status'] ?? null;

            if (! $waMessageId || $statusRaw === null) {
                continue;
            }

            $status = is_string($statusRaw)
                ? $this->mapStatusString($statusRaw)
                : $this->mapStatusInt((int) $statusRaw);

            Message::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('wa_message_id', $waMessageId)
                ->update(['status' => $status->value]);
        }
    }

    private function handleConnectionUpdate(array $data, ?Tenant $tenant): void
    {
        if (! $tenant) {
            return;
        }

        $state = $data['state'] ?? 'close';

        $waStatus = match ($state) {
            'open'       => WaStatus::Connected,
            'connecting' => WaStatus::QrPending,
            default      => WaStatus::Disconnected,
        };

        $updates = ['wa_status' => $waStatus];

        if ($waStatus === WaStatus::Connected) {
            $updates['wa_connected_at'] = now();

            // Extract phone from wuid (e.g. "573004410097@s.whatsapp.net" → "573004410097")
            $wuid = $data['wuid'] ?? '';
            if ($wuid && str_contains($wuid, '@')) {
                $updates['wa_phone'] = explode('@', $wuid)[0];
            }
        }

        $tenant->update($updates);

        Log::info('Connection update processed', [
            'tenant'  => $tenant->id,
            'state'   => $state,
            'status'  => $waStatus->value,
            'phone'   => $updates['wa_phone'] ?? null,
        ]);

        broadcast(new WaStatusUpdated($tenant->fresh(), null))->toOthers();
    }

    private function handleQrCodeUpdated(array $data, ?Tenant $tenant): void
    {
        if (! $tenant) {
            return;
        }

        $qrBase64 = $data['qrcode']['base64'] ?? null;

        $tenant->update(['wa_status' => WaStatus::QrPending]);

        broadcast(new WaStatusUpdated($tenant, $qrBase64));
    }

    private function handleContactsUpsert(array $data, ?Tenant $tenant): void
    {
        if (! $tenant) {
            return;
        }

        // Can be a single contact or an array
        $contacts = isset($data['remoteJid']) ? [$data] : $data;

        foreach ($contacts as $contactData) {
            $remoteJid = $contactData['remoteJid'] ?? '';

            if (empty($remoteJid) || str_contains($remoteJid, '@g.us')) {
                continue;
            }

            $pushName      = $contactData['pushName'] ?? $contactData['notify'] ?? null;
            $profilePicUrl = $contactData['profilePicUrl'] ?? null;

            if (! $pushName && ! $profilePicUrl) {
                continue;
            }

            $contact = Contact::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('wa_id', $remoteJid)
                ->first();

            if ($contact) {
                $updates = [];
                if ($pushName) {
                    $updates['push_name'] = $pushName;
                    // Also set name if not manually set
                    if (! $contact->name || $contact->name === $contact->phone) {
                        $updates['name'] = $pushName;
                    }
                }
                if ($profilePicUrl) {
                    $updates['profile_pic_url'] = $profilePicUrl;
                }
                if ($updates) {
                    $contact->update($updates);
                }
            }
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function resolveTenant(string $instanceName): ?Tenant
    {
        if (empty($instanceName)) {
            return null;
        }

        return Tenant::where('wa_instance_id', $instanceName)->first();
    }

    private function mapStatusInt(int $status): MessageStatus
    {
        return match ($status) {
            1       => MessageStatus::Pending,
            2       => MessageStatus::Sent,
            3       => MessageStatus::Delivered,
            4       => MessageStatus::Read,
            default => MessageStatus::Sent,
        };
    }

    private function mapStatusString(string $status): MessageStatus
    {
        return match (strtoupper($status)) {
            'PENDING'             => MessageStatus::Pending,
            'SENT', 'SERVER_ACK' => MessageStatus::Sent,
            'DELIVERY_ACK', 'DELIVERED' => MessageStatus::Delivered,
            'READ', 'PLAYED'     => MessageStatus::Read,
            default               => MessageStatus::Sent,
        };
    }
}
