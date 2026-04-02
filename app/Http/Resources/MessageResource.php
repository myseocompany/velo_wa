<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'direction'      => $this->direction->value,
            'body'           => $this->body,
            'media_url'      => $this->resolveMediaUrl(),
            'media_type'     => $this->media_type,
            'media_mime_type' => $this->media_mime_type,
            'media_filename' => $this->media_filename,
            'status'         => $this->status->value,
            'wa_message_id'  => $this->wa_message_id,
            'is_automated'   => $this->is_automated,
            'sent_by'        => $this->sent_by,
            'error_message'  => $this->error_message,
            'created_at'     => $this->created_at->toIso8601String(),
        ];
    }

    private function resolveMediaUrl(): ?string
    {
        $stored = $this->media_url;

        if (! $stored) {
            return null;
        }

        // Resolve the S3 path, whether stored as a relative path or a legacy full URL
        // (e.g. "http://minio:9000/velo-media/..." from previous versions).
        $path = $this->extractS3Path($stored);

        if (! $path) {
            return null;
        }

        $diskName = (string) config('filesystems.media_disk', config('filesystems.default', 'local'));
        $disk     = Storage::disk($diskName);

        try {
            return $disk->temporaryUrl($path, now()->addHours(6));
        } catch (Throwable) {
            return $disk->url($path);
        }
    }

    private function extractS3Path(string $stored): ?string
    {
        // Already a relative path — use directly.
        if (! str_starts_with($stored, 'http')) {
            return $stored;
        }

        $publicPath = parse_url($stored, PHP_URL_PATH);
        if (is_string($publicPath) && preg_match('~/storage/(.+)$~', $publicPath, $m)) {
            return $m[1];
        }

        // Legacy full URL: strip scheme + host + "/bucket/" prefix.
        $bucket = config('filesystems.disks.s3.bucket', 'velo-media');

        if (preg_match('~/' . preg_quote($bucket, '~') . '/(.+)~', $stored, $m)) {
            return $m[1];
        }

        return null;
    }
}
