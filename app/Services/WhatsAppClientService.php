<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppClientService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.evolution.url'), '/');
        $this->apiKey  = config('services.evolution.key');
    }

    /** Generate the instance name for a tenant (stable, deterministic). */
    public static function instanceName(string $tenantId): string
    {
        return 'tenant_' . substr(str_replace('-', '', $tenantId), 0, 8);
    }

    /**
     * Create a new Evolution API instance for a tenant.
     * Returns the full response array including initial QR code if available.
     */
    public function createInstance(string $instanceName, string $webhookUrl): array
    {
        $response = $this->post('/instance/create', [
            'instanceName' => $instanceName,
            'integration'  => 'WHATSAPP-BAILEYS',
            'qrcode'       => true,
        ]);

        $result = $response->json();

        // Evolution API v2.3+ requires webhook to be set separately after instance creation
        $this->setWebhook($instanceName, $webhookUrl);

        return $result;
    }

    /**
     * Set or update the webhook configuration for an existing instance.
     */
    public function setWebhook(string $instanceName, string $webhookUrl): void
    {
        $this->post("/webhook/set/{$instanceName}", [
            'url'      => $webhookUrl,
            'enabled'  => true,
            'events'   => [
                'MESSAGES_UPSERT',
                'MESSAGES_UPDATE',
                'CONNECTION_UPDATE',
                'QRCODE_UPDATED',
                'CONTACTS_UPSERT',
                'CONTACTS_UPDATE',
            ],
        ]);
    }

    /**
     * Get the current QR code for an instance (reconnection flow).
     * Returns ['base64' => 'data:image/png;base64,...', 'code' => '2@...'].
     */
    public function getQrCode(string $instanceName): array
    {
        $response = $this->get("/instance/connect/{$instanceName}");
        return $response->json();
    }

    /**
     * Get the connection state of an instance.
     * Returns: 'open' | 'close' | 'connecting'
     */
    public function getConnectionState(string $instanceName): string
    {
        $response = $this->get("/instance/connectionState/{$instanceName}");
        $data = $response->json();

        return $data['instance']['state'] ?? 'close';
    }

    /**
     * Delete an instance (disconnect + cleanup).
     */
    public function deleteInstance(string $instanceName): void
    {
        $this->delete("/instance/delete/{$instanceName}");
    }

    /**
     * Get media as base64 from a message.
     * Uses the Evolution API endpoint to decrypt and return media content.
     *
     * @return array{base64: string, mimetype: string, fileName?: string}
     */
    public function getMediaBase64(string $instanceName, array $messageKey): array
    {
        $response = $this->post("/chat/getBase64FromMediaMessage/{$instanceName}", [
            'message' => ['key' => $messageKey],
        ]);

        return $response->json();
    }

    /**
     * Send a media message via WhatsApp.
     */
    public function sendMedia(string $instanceName, string $number, string $mediaType, string $mediaUrl, ?string $caption = null): array
    {
        $response = $this->post("/message/sendMedia/{$instanceName}", [
            'number'    => $number,
            'mediatype' => $mediaType,
            'media'     => $mediaUrl,
            'caption'   => $caption ?? '',
        ]);

        return $response->json();
    }

    /**
     * Send a text message via WhatsApp.
     * Returns the Evolution API response with the message ID.
     */
    public function sendText(string $instanceName, string $number, string $text): array
    {
        $response = $this->post("/message/sendText/{$instanceName}", [
            'number' => $number,
            'text'   => $text,
        ]);

        return $response->json();
    }

    // ─── Private HTTP helpers ──────────────────────────────────────────────────

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeader('apikey', $this->apiKey)
            ->timeout(15)
            ->retry(2, 500);
    }

    private function get(string $path): Response
    {
        $response = $this->client()->get($path);
        $this->throwIfError($response, 'GET', $path);
        return $response;
    }

    private function post(string $path, array $body = []): Response
    {
        $response = $this->client()->post($path, $body);
        $this->throwIfError($response, 'POST', $path);
        return $response;
    }

    private function delete(string $path): Response
    {
        $response = $this->client()->delete($path);
        $this->throwIfError($response, 'DELETE', $path);
        return $response;
    }

    private function throwIfError(Response $response, string $method, string $path): void
    {
        if ($response->failed()) {
            Log::error('Evolution API error', [
                'method'   => $method,
                'path'     => $path,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            throw new RuntimeException(
                "Evolution API {$method} {$path} failed with status {$response->status()}: {$response->body()}"
            );
        }
    }
}
