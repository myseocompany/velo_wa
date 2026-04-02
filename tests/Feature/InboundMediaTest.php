<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Jobs\DownloadMessageMedia;
use App\Jobs\HandleInboundMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Services\AssignmentEngineService;
use App\Services\AutomationEngineService;
use App\Services\WhatsAppClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InboundMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_media_dispatches_download_with_full_message_payload(): void
    {
        Queue::fake();

        $tenant = Tenant::create([
            'name' => 'Tenant Media',
            'slug' => 'tenant-media',
            'wa_instance_id' => 'tenant_media',
        ]);

        $payload = [
            'data' => [
                'key' => [
                    'id' => 'MSG-123',
                    'fromMe' => false,
                    'remoteJid' => '573001112233@s.whatsapp.net',
                ],
                'pushName' => 'Cliente Demo',
                'messageType' => 'imageMessage',
                'messageTimestamp' => now()->timestamp,
                'message' => [
                    'imageMessage' => [
                        'url' => 'https://mmg.whatsapp.net/example',
                        'mimetype' => 'image/jpeg',
                        'caption' => 'Foto de prueba',
                        'mediaKey' => 'abc123',
                        'directPath' => '/v/t62.7118-24/example',
                    ],
                ],
            ],
        ];

        $job = new HandleInboundMessage($payload, $tenant->id);
        $job->handle(
            app(\App\Actions\WhatsApp\CreateOrUpdateContact::class),
            app(\App\Actions\WhatsApp\CreateOrUpdateConversation::class),
            app(\App\Actions\WhatsApp\StoreInboundMessage::class),
            $this->createMock(AssignmentEngineService::class),
            $this->createMock(AutomationEngineService::class),
        );

        Queue::assertPushed(DownloadMessageMedia::class, function (DownloadMessageMedia $job) {
            $reflection = new \ReflectionClass($job);

            $payload = $reflection->getProperty('messagePayload');
            $payload->setAccessible(true);
            $messagePayload = $payload->getValue($job);

            return ($messagePayload['messageType'] ?? null) === 'imageMessage'
                && ($messagePayload['message']['imageMessage']['mediaKey'] ?? null) === 'abc123'
                && ($messagePayload['key']['id'] ?? null) === 'MSG-123';
        });
    }

    public function test_download_message_media_uses_full_payload_and_stores_file(): void
    {
        Storage::fake('s3');

        $tenant = Tenant::create([
            'name' => 'Tenant Media',
            'slug' => 'tenant-media',
            'wa_instance_id' => 'tenant_media',
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'phone' => '573001112233',
            'wa_id' => '573001112233@s.whatsapp.net',
            'name' => 'Cliente Demo',
        ]);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'status' => ConversationStatus::Open,
            'channel' => Channel::WhatsApp,
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenant->id,
            'direction' => MessageDirection::In,
            'body' => 'Foto de prueba',
            'media_type' => 'image',
            'media_mime_type' => 'image/jpeg',
            'status' => MessageStatus::Delivered,
            'wa_message_id' => 'MSG-123',
        ]);

        $messagePayload = [
            'key' => [
                'id' => 'MSG-123',
                'fromMe' => false,
                'remoteJid' => '573001112233@s.whatsapp.net',
            ],
            'messageType' => 'imageMessage',
            'message' => [
                'imageMessage' => [
                    'url' => 'https://mmg.whatsapp.net/example',
                    'mimetype' => 'image/jpeg',
                    'caption' => 'Foto de prueba',
                    'mediaKey' => 'abc123',
                ],
            ],
        ];

        $client = $this->createMock(WhatsAppClientService::class);
        $client->expects($this->once())
            ->method('getMediaBase64')
            ->with('tenant_media', $messagePayload)
            ->willReturn([
                'base64' => 'data:image/jpeg;base64,' . base64_encode('fake-image-binary'),
                'mimetype' => 'image/jpeg',
            ]);

        $job = new DownloadMessageMedia(
            $message->id,
            'tenant_media',
            $messagePayload,
            'image',
            'image/jpeg',
            null,
        );

        $job->handle($client);

        $fresh = $message->fresh();

        $this->assertNotNull($fresh->media_url);
        $this->assertNotNull($fresh->media_filename);
        Storage::disk('s3')->assertExists($fresh->media_url);
    }
}
