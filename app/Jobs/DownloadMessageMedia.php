<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\MessageReceived;
use App\Models\Message;
use App\Services\WhatsAppClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadMessageMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly string $messageId,
        private readonly string $instanceName,
        private readonly array $messagePayload,
        private readonly string $mediaType,
        private readonly ?string $mediaMimeType,
        private readonly ?string $mediaFilename,
    ) {
        $this->onQueue('whatsapp');
    }

    public function handle(WhatsAppClientService $client): void
    {
        $message = Message::withoutGlobalScopes()->find($this->messageId);

        if (! $message || $message->media_url) {
            return; // Already downloaded or message deleted
        }

        try {
            $result = $client->getMediaBase64($this->instanceName, $this->messagePayload);

            $base64 = $result['base64'] ?? null;

            if (! $base64) {
                Log::warning('DownloadMessageMedia: no base64 returned', [
                    'message_id' => $this->messageId,
                    'message_type' => $this->messagePayload['messageType'] ?? null,
                ]);
                return;
            }

            // Remove data URI prefix if present (e.g. "data:audio/ogg;base64,...")
            if (str_contains($base64, ',')) {
                $base64 = explode(',', $base64, 2)[1];
            }

            $binary = base64_decode($base64, true);

            if ($binary === false) {
                Log::warning('DownloadMessageMedia: invalid base64', ['message_id' => $this->messageId]);
                return;
            }

            // Build storage path: tenantId/media/YYYY-MM/filename
            $extension = $this->guessExtension();
            $filename  = $this->mediaFilename ?? (Str::uuid() . '.' . $extension);
            $tenantId  = $message->tenant_id;
            $month     = now()->format('Y-m');
            $path      = "{$tenantId}/media/{$month}/{$filename}";
            $diskName  = (string) config('filesystems.media_disk', config('filesystems.default', 'local'));

            Storage::disk($diskName)->put($path, $binary, [
                'ContentType' => $this->mediaMimeType ?? 'application/octet-stream',
            ]);

            $message->update([
                'media_url'       => $path,
                'media_type'      => $this->mediaType,
                'media_mime_type' => $this->mediaMimeType,
                'media_filename'  => $filename,
            ]);

            // Notify the frontend so it replaces the "downloading" spinner with the media
            broadcast(new MessageReceived($message->fresh()));

            Log::info('Media downloaded and stored', [
                'message_id' => $this->messageId,
                'type'       => $this->mediaType,
                'path'       => $path,
                'size'       => strlen($binary),
            ]);
        } catch (\Throwable $e) {
            Log::error('DownloadMessageMedia failed', [
                'message_id' => $this->messageId,
                'error'      => $e->getMessage(),
            ]);
            throw $e; // Let the queue retry
        }
    }

    private function guessExtension(): string
    {
        return match ($this->mediaType) {
            'image'    => match (true) {
                str_contains($this->mediaMimeType ?? '', 'png')  => 'png',
                str_contains($this->mediaMimeType ?? '', 'gif')  => 'gif',
                str_contains($this->mediaMimeType ?? '', 'webp') => 'webp',
                default => 'jpg',
            },
            'video'    => 'mp4',
            'audio'    => str_contains($this->mediaMimeType ?? '', 'ogg') ? 'ogg' : 'm4a',
            'document' => $this->extensionFromFilename() ?? 'pdf',
            'sticker'  => 'webp',
            default    => 'bin',
        };
    }

    private function extensionFromFilename(): ?string
    {
        if (! $this->mediaFilename) {
            return null;
        }

        $ext = pathinfo($this->mediaFilename, PATHINFO_EXTENSION);

        return $ext ?: null;
    }
}
