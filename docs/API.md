# API Contracts

## Overview

- Base URL: `/api/v1/`
- Auth: Laravel Sanctum (cookie-based for SPA, token for external)
- All responses follow consistent format
- Tenant context resolved from authenticated user

---

## Response Format

### Success

```json
{
    "data": { ... },
    "meta": {
        "current_page": 1,
        "per_page": 25,
        "total": 100
    }
}
```

### Error

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "field": ["Error description"]
    }
}
```

---

## Authentication

### POST /api/v1/login

```json
// Request
{ "email": "agent@empresa.com", "password": "secret" }

// Response 200
{
    "data": {
        "user": { "id": "uuid", "name": "...", "email": "...", "role": "agent" },
        "tenant": { "id": "uuid", "name": "...", "plan": "pro" }
    }
}
```

### POST /api/v1/logout

```json
// Response 204 (No Content)
```

---

## Conversations

### GET /api/v1/conversations

List conversations for current tenant.

```
Query params:
  status    = open|pending|closed (default: open)
  assigned  = me|unassigned|all|{user_id} (default: all)
  search    = string (searches contact name/phone)
  cursor    = string (cursor-based pagination)
  limit     = integer (default: 25, max: 100)
```

```json
// Response 200
{
    "data": [
        {
            "id": "uuid",
            "contact": {
                "id": "uuid",
                "name": "Juan Pérez",
                "phone": "573001234567",
                "profile_pic_url": "https://..."
            },
            "status": "open",
            "assigned_to": {
                "id": "uuid",
                "name": "María García"
            },
            "last_message": {
                "body": "Hola, quiero cotizar",
                "direction": "in",
                "created_at": "2024-03-06T15:30:00Z"
            },
            "unread_count": 3,
            "first_message_at": "2024-03-06T15:28:00Z",
            "first_response_at": null,
            "created_at": "2024-03-06T15:28:00Z"
        }
    ],
    "meta": {
        "next_cursor": "eyJ...",
        "has_more": true
    }
}
```

### GET /api/v1/conversations/{id}

Single conversation with recent messages.

### POST /api/v1/conversations/{id}/assign

```json
// Request
{ "user_id": "uuid" }

// Response 200
{ "data": { "id": "uuid", "assigned_to": { ... } } }
```

### POST /api/v1/conversations/{id}/close

```json
// Response 200
{ "data": { "id": "uuid", "status": "closed", "closed_at": "..." } }
```

### POST /api/v1/conversations/{id}/reopen

```json
// Response 200
{ "data": { "id": "uuid", "status": "open" } }
```

---

## Messages

### GET /api/v1/conversations/{conversationId}/messages

```
Query params:
  cursor = string (cursor-based, newest first)
  limit  = integer (default: 50, max: 200)
```

```json
// Response 200
{
    "data": [
        {
            "id": "uuid",
            "direction": "in",
            "body": "Hola, quiero cotizar un servicio",
            "media_url": null,
            "media_type": null,
            "status": "read",
            "sent_by": null,
            "is_automated": false,
            "created_at": "2024-03-06T15:28:00Z"
        },
        {
            "id": "uuid",
            "direction": "out",
            "body": "¡Hola Juan! Claro, con gusto te ayudo.",
            "media_url": null,
            "media_type": null,
            "status": "delivered",
            "sent_by": { "id": "uuid", "name": "María García" },
            "is_automated": false,
            "created_at": "2024-03-06T15:30:00Z"
        }
    ],
    "meta": {
        "next_cursor": "eyJ...",
        "has_more": true
    }
}
```

### POST /api/v1/conversations/{conversationId}/messages

Send a message.

```json
// Request (text)
{
    "body": "¡Hola! Te envío la cotización.",
    "type": "text"
}

// Request (media)
{
    "body": "Aquí va el documento",
    "type": "media",
    "media_file": "<multipart file upload>",
    "media_type": "document"
}

// Response 201
{
    "data": {
        "id": "uuid",
        "direction": "out",
        "body": "¡Hola! Te envío la cotización.",
        "status": "pending",
        "created_at": "2024-03-06T15:30:00Z"
    }
}
```

---

## Contacts

### GET /api/v1/contacts

```
Query params:
  search      = string (name, phone, email)
  tag         = string (filter by tag)
  assigned_to = uuid|unassigned
  page        = integer
  per_page    = integer (default: 25)
  sort        = name|created_at|last_contact_at (default: last_contact_at)
  direction   = asc|desc (default: desc)
```

### POST /api/v1/contacts

```json
// Request
{
    "phone": "573001234567",
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "company": "Empresa X",
    "tags": ["vip", "bogota"],
    "notes": "Referido por María"
}

// Response 201
{ "data": { "id": "uuid", ... } }
```

### PUT /api/v1/contacts/{id}

### DELETE /api/v1/contacts/{id}

Soft delete.

---

## Pipeline Deals

### GET /api/v1/deals

```
Query params:
  stage       = lead|qualified|proposal|negotiation|closed_won|closed_lost
  assigned_to = uuid
  page, per_page, sort, direction
```

### POST /api/v1/deals

```json
{
    "contact_id": "uuid",
    "title": "Cotización hosting",
    "stage": "lead",
    "value": 1500000,
    "currency": "COP"
}
```

### PUT /api/v1/deals/{id}

### PATCH /api/v1/deals/{id}/stage

Move deal to a new stage. Auto-timestamps the transition.

```json
// Request
{ "stage": "qualified" }

// Response 200
{ "data": { "id": "uuid", "stage": "qualified", "qualified_at": "2024-03-06T..." } }
```

---

## WhatsApp Management

### GET /api/v1/whatsapp/status

```json
// Response 200
{
    "data": {
        "status": "connected",
        "phone": "573001234567",
        "connected_at": "2024-03-01T10:00:00Z",
        "instance_id": "tenant_abc123"
    }
}
```

### POST /api/v1/whatsapp/connect

Start connection flow (generate QR).

```json
// Response 200
{
    "data": {
        "qr_code": "base64_encoded_qr_image",
        "expires_at": "2024-03-06T15:35:00Z"
    }
}
```

### POST /api/v1/whatsapp/disconnect

Disconnect WhatsApp instance.

---

## Metrics / Dashboard

### GET /api/v1/metrics/overview

```
Query params:
  period = today|week|month|custom
  from   = date (if custom)
  to     = date (if custom)
```

```json
// Response 200
{
    "data": {
        "dt1": {
            "average_seconds": 145,
            "median_seconds": 90,
            "p95_seconds": 600
        },
        "conversations": {
            "total": 250,
            "open": 45,
            "closed": 205,
            "avg_per_day": 12
        },
        "messages": {
            "inbound": 1200,
            "outbound": 980
        },
        "pipeline": {
            "total_deals": 80,
            "total_value": 45000000,
            "won_value": 12000000,
            "conversion_rate": 0.35
        },
        "agents": [
            {
                "id": "uuid",
                "name": "María García",
                "avg_dt1_seconds": 120,
                "conversations_handled": 45,
                "messages_sent": 230
            }
        ]
    }
}
```

---

## Quick Replies

### GET /api/v1/quick-replies

### POST /api/v1/quick-replies

```json
{
    "shortcut": "/precio",
    "title": "Información de precios",
    "body": "Hola {{contact_name}}, nuestros precios son...",
    "category": "ventas"
}
```

### PUT /api/v1/quick-replies/{id}

### DELETE /api/v1/quick-replies/{id}

---

## Team / Users

### GET /api/v1/team

List team members.

### POST /api/v1/team/invite

```json
{ "email": "nuevo@empresa.com", "role": "agent", "name": "Carlos López" }
```

### PUT /api/v1/team/{id}

Update role, specialties, max conversations.

### DELETE /api/v1/team/{id}

Deactivate user (soft).

---

## Webhooks (Incoming from Evolution API)

### POST /api/v1/webhooks/whatsapp

Internal endpoint. Not authenticated via Sanctum — validated by webhook secret.

```json
// Incoming message
{
    "event": "messages.upsert",
    "instance": "tenant_abc123",
    "data": {
        "key": {
            "remoteJid": "573001234567@s.whatsapp.net",
            "fromMe": false,
            "id": "ABCDEF123456"
        },
        "message": {
            "conversation": "Hola, quiero cotizar"
        },
        "messageTimestamp": 1709740800,
        "pushName": "Juan Pérez"
    }
}
```

```json
// Message status update
{
    "event": "messages.update",
    "instance": "tenant_abc123",
    "data": [{
        "key": {
            "remoteJid": "573001234567@s.whatsapp.net",
            "id": "OUTGOING_MSG_ID"
        },
        "update": {
            "status": 3  // 2=delivered, 3=read
        }
    }]
}
```

```json
// Connection status
{
    "event": "connection.update",
    "instance": "tenant_abc123",
    "data": {
        "state": "open",  // open, close, connecting
        "statusReason": 200
    }
}
```

---

## Rate Limits

| Endpoint Group | Limit | Window |
|---------------|-------|--------|
| Auth | 5 req | 1 min |
| Messages (send) | 30 req | 1 min |
| API general | 60 req | 1 min |
| Webhooks (internal) | 300 req | 1 min |
