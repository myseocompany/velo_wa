# WhatsApp Service Integration

## Overview

AriCRM uses **Evolution API v2** as the WhatsApp bridge service. It runs as a Docker container and handles all WhatsApp protocol communication via Baileys internally. Laravel communicates with it via REST API and receives events via webhooks.

---

## Evolution API Setup

### Docker Configuration

```yaml
# Part of docker-compose.yml
evolution-api:
    image: atendai/evolution-api:v2.2.3
    container_name: velo_evolution
    restart: unless-stopped
    ports:
      - "8080:8080"
    environment:
      - AUTHENTICATION_API_KEY=${EVOLUTION_API_KEY}
      - AUTHENTICATION_EXPOSE_IN_FETCH_INSTANCES=true

      # Database (uses own PostgreSQL or shared)
      - DATABASE_PROVIDER=postgresql
      - DATABASE_CONNECTION_URI=postgresql://${DB_USERNAME}:${DB_PASSWORD}@postgres:5432/evolution

      # Redis (session storage)
      - CACHE_REDIS_ENABLED=true
      - CACHE_REDIS_URI=redis://redis:6379/3

      # Webhook global config
      - WEBHOOK_GLOBAL_URL=${APP_URL}/api/v1/webhooks/whatsapp
      - WEBHOOK_GLOBAL_ENABLED=true
      - WEBHOOK_GLOBAL_WEBHOOK_BY_EVENTS=true

      # Storage (S3 for media)
      - S3_ENABLED=true
      - S3_ACCESS_KEY=${AWS_ACCESS_KEY_ID}
      - S3_SECRET_KEY=${AWS_SECRET_ACCESS_KEY}
      - S3_BUCKET=${AWS_BUCKET}
      - S3_REGION=${AWS_DEFAULT_REGION}
      - S3_ENDPOINT=${AWS_ENDPOINT}

      # Instance management
      - DEL_INSTANCE=false
      - DEL_TEMP_INSTANCES=true
      - PROVIDER_ENABLED=false
    volumes:
      - evolution_data:/evolution/instances
    networks:
      - velo_network
    depends_on:
      - redis
      - postgres
```

### Authentication

All requests to Evolution API require the API key header:

```
apikey: ${EVOLUTION_API_KEY}
```

---

## Instance Lifecycle

### 1. Create Instance (Tenant Onboarding)

When a tenant wants to connect WhatsApp:

```http
POST http://evolution-api:8080/instance/create
Content-Type: application/json
apikey: ${EVOLUTION_API_KEY}

{
    "instanceName": "tenant_{tenant_id}",
    "integration": "WHATSAPP-BAILEYS",
    "qrcode": true,
    "webhookUrl": "${APP_URL}/api/v1/webhooks/whatsapp",
    "webhookByEvents": true,
    "webhookBase64": false,
    "webhookEvents": [
        "MESSAGES_UPSERT",
        "MESSAGES_UPDATE",
        "CONNECTION_UPDATE",
        "QRCODE_UPDATED"
    ]
}
```

**Response** includes the QR code in base64 format.

### 2. Get QR Code (Reconnection)

```http
GET http://evolution-api:8080/instance/connect/{instanceName}
apikey: ${EVOLUTION_API_KEY}
```

```json
// Response
{
    "pairingCode": null,
    "code": "2@ABC...",
    "base64": "data:image/png;base64,iVBOR..."
}
```

### 3. Check Connection Status

```http
GET http://evolution-api:8080/instance/connectionState/{instanceName}
apikey: ${EVOLUTION_API_KEY}
```

```json
// Response
{
    "instance": {
        "instanceName": "tenant_abc",
        "state": "open"  // open, close, connecting
    }
}
```

### 4. Delete Instance (Tenant Offboarding)

```http
DELETE http://evolution-api:8080/instance/delete/{instanceName}
apikey: ${EVOLUTION_API_KEY}
```

---

## Sending Messages

### Text Message

```http
POST http://evolution-api:8080/message/sendText/{instanceName}
Content-Type: application/json
apikey: ${EVOLUTION_API_KEY}

{
    "number": "573001234567",
    "text": "Hola, ¿cómo estás?"
}
```

### Media Message (Image, Video, Audio)

```http
POST http://evolution-api:8080/message/sendMedia/{instanceName}
Content-Type: application/json
apikey: ${EVOLUTION_API_KEY}

{
    "number": "573001234567",
    "mediatype": "image",
    "mimetype": "image/jpeg",
    "caption": "Aquí va la imagen",
    "media": "https://s3.example.com/velo-media/tenant_abc/images/photo.jpg"
}
```

### Document

```http
POST http://evolution-api:8080/message/sendMedia/{instanceName}
Content-Type: application/json
apikey: ${EVOLUTION_API_KEY}

{
    "number": "573001234567",
    "mediatype": "document",
    "mimetype": "application/pdf",
    "caption": "Cotización adjunta",
    "media": "https://s3.example.com/velo-media/tenant_abc/documents/cotizacion.pdf",
    "fileName": "cotizacion.pdf"
}
```

### Audio (Voice Note)

```http
POST http://evolution-api:8080/message/sendWhatsAppAudio/{instanceName}
Content-Type: application/json
apikey: ${EVOLUTION_API_KEY}

{
    "number": "573001234567",
    "audio": "https://s3.example.com/velo-media/tenant_abc/audio/note.ogg"
}
```

---

## Webhook Events

### Webhook Authentication (Laravel side)

Inbound webhooks must include the same `EVOLUTION_API_KEY` configured in Laravel.
Accepted headers in AriCRM:

- `apikey: <EVOLUTION_API_KEY>`
- `x-api-key: <EVOLUTION_API_KEY>`
- `x-evolution-apikey: <EVOLUTION_API_KEY>`
- `Authorization: Bearer <EVOLUTION_API_KEY>`

Requests without a valid key are rejected with `401 Unauthorized`.

### MESSAGES_UPSERT (Incoming Message)

```json
{
    "event": "messages.upsert",
    "instance": "tenant_abc",
    "data": {
        "key": {
            "remoteJid": "573001234567@s.whatsapp.net",
            "fromMe": false,
            "id": "3EB0A0C0..."
        },
        "pushName": "Juan Pérez",
        "message": {
            // Text message
            "conversation": "Hola, quiero cotizar"

            // OR Image message
            // "imageMessage": {
            //     "url": "...",
            //     "mimetype": "image/jpeg",
            //     "caption": "Mira esta foto",
            //     "fileSha256": "...",
            //     "fileLength": 123456,
            //     "mediaKey": "..."
            // }

            // OR Document message
            // "documentMessage": {
            //     "url": "...",
            //     "mimetype": "application/pdf",
            //     "title": "documento.pdf",
            //     "fileName": "documento.pdf"
            // }

            // OR Audio message
            // "audioMessage": {
            //     "url": "...",
            //     "mimetype": "audio/ogg; codecs=opus",
            //     "seconds": 15,
            //     "ptt": true
            // }
        },
        "messageType": "conversation",  // conversation, imageMessage, documentMessage, audioMessage, etc.
        "messageTimestamp": 1709740800
    }
}
```

### MESSAGES_UPDATE (Delivery/Read Receipt)

```json
{
    "event": "messages.update",
    "instance": "tenant_abc",
    "data": [
        {
            "key": {
                "remoteJid": "573001234567@s.whatsapp.net",
                "fromMe": true,
                "id": "OUTGOING_MSG_ID"
            },
            "update": {
                "status": 3
                // 1 = pending, 2 = sent (server), 3 = delivered, 4 = read
            }
        }
    ]
}
```

### CONNECTION_UPDATE

```json
{
    "event": "connection.update",
    "instance": "tenant_abc",
    "data": {
        "state": "open",
        "statusReason": 200
    }
}
```

States: `open` (connected), `close` (disconnected), `connecting` (attempting reconnect)

### QRCODE_UPDATED

```json
{
    "event": "qrcode.updated",
    "instance": "tenant_abc",
    "data": {
        "qrcode": {
            "pairingCode": null,
            "code": "2@ABC...",
            "base64": "data:image/png;base64,..."
        }
    }
}
```

---

## Laravel Integration Layer

### WhatsAppClientService

```php
// App\Services\WhatsAppClientService

class WhatsAppClientService
{
    // Instance management
    public function createInstance(string $instanceName): array;
    public function getQrCode(string $instanceName): array;
    public function getConnectionState(string $instanceName): string;
    public function deleteInstance(string $instanceName): void;

    // Messaging
    public function sendText(string $instanceName, string $number, string $text): array;
    public function sendMedia(string $instanceName, string $number, string $mediaUrl, string $mediaType, ?string $caption): array;
    public function sendDocument(string $instanceName, string $number, string $documentUrl, string $fileName, ?string $caption): array;
    public function sendAudio(string $instanceName, string $number, string $audioUrl): array;
}
```

### WebhookHandlerService

```php
// App\Services\WebhookHandlerService

class WebhookHandlerService
{
    public function handle(array $payload): void
    {
        // 1. Log raw webhook
        // 2. Resolve tenant by instance name
        // 3. Route to specific handler by event type
    }

    protected function handleMessageUpsert(Tenant $tenant, array $data): void;
    protected function handleMessageUpdate(Tenant $tenant, array $data): void;
    protected function handleConnectionUpdate(Tenant $tenant, array $data): void;
    protected function handleQrCodeUpdate(Tenant $tenant, array $data): void;
}
```

---

## Media Handling Flow

```
Inbound media:
1. Evolution API receives media message
2. Webhook includes media metadata (type, mimetype, url)
3. Laravel job SyncMediaFile:
   a. GET media from Evolution API's temporary storage
   b. Upload to S3 under tenant's prefix
   c. Update message record with S3 URL
   d. Delete temporary file

Outbound media:
1. Agent uploads file via frontend
2. Laravel stores in S3
3. SendWhatsAppMessage job sends media URL to Evolution API
4. Evolution API downloads from S3 and sends to WhatsApp
```

---

## Session Management & Resilience

### Auto-reconnection

Evolution API handles reconnection internally. Laravel monitors via:

1. **Webhook-based**: `CONNECTION_UPDATE` events notify of state changes
2. **Polling-based**: `CheckInstanceHealth` job runs every 5 minutes
   - Calls `/instance/connectionState/{name}` for each active tenant
   - If disconnected > 10 min, attempts reconnect
   - If disconnected > 1 hour, marks tenant and alerts

### Ban Detection

WhatsApp can ban numbers for:
- Sending too many messages too fast
- Being reported by recipients
- Using unofficial API (inherent risk with Baileys)

Detection signals:
- Connection drops and can't reconnect
- `statusReason: 401` or `statusReason: 403` in connection updates
- Repeated QR scan failures

Response:
- Mark `wa_status = banned` on tenant
- Alert tenant admin via email
- Provide guidance on appeal process

---

## Rate Limiting Strategy

To minimize ban risk:

```
Per instance:
- Max 30 messages/minute for existing conversations
- Max 10 new conversations/hour (first message to new number)
- Max 5 media messages/minute
- Minimum 2-second delay between messages to same number

Implementation:
- Redis-based token bucket per instance
- Queue job checks rate limit before sending
- If throttled, delay job with backoff
- Dashboard warning when approaching limits
```

---

## Future: Migration to Baileys Direct

When ready to migrate (50+ tenants, need optimization):

1. Build Node.js microservice with same REST API interface
2. Same webhook format to Laravel (drop-in replacement)
3. Custom session management with Redis
4. Per-instance process isolation
5. Graceful migration: run both services, switch per tenant
6. Laravel `WhatsAppClientService` stays identical — just change base URL
