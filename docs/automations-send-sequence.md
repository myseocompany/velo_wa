# Automatizaciones: `send_sequence` (texto + multimedia)

## Resumen
Se agregó una nueva acción de automatización: `send_sequence`.
Permite enviar una mini conversación compuesta por varios pasos con delay acumulado.

## Qué soporta
- Paso `text`: requiere `body`.
- Paso multimedia (`image`, `video`, `audio`, `document`): requiere `media_url`.
- Cada paso puede incluir `body` como caption (opcional en multimedia).
- Variables de plantilla en `body`:
  - `{{name}}`
  - `{{phone}}`
  - `{{company}}`

## Estructura de `action_config`
```json
{
  "steps": [
    { "type": "text", "body": "Hola {{name}}", "delay_seconds": 0 },
    { "type": "image", "media_url": "https://.../promo.jpg", "body": "Mira esta promo", "delay_seconds": 8 },
    { "type": "text", "body": "¿Te interesa?", "delay_seconds": 12 }
  ]
}
```

## Comportamiento en backend
- `AutomationEngineService` normaliza y agenda cada paso en cola (`whatsapp`) mediante `SendAutomationSequenceStep`.
- El delay es acumulado entre pasos.
- `SendAutomationSequenceStep` corta el envío si:
  - la conversación ya no existe,
  - no está abierta,
  - o ya entró un mensaje inbound después del inicio de la secuencia.

## Envío de multimedia
- Si `media_url` es ruta interna almacenada, se envía como base64 (comportamiento existente).
- Si `media_url` es URL externa (`http/https`), se envía como URL directa a Evolution.

## UI
En `Settings > Automatizaciones`:
- Nueva acción: **Enviar secuencia**.
- Editor visual de pasos:
  - tipo,
  - delay en segundos,
  - texto/caption,
  - media URL (solo en multimedia).

## Notas de operación
- Máximo 12 pasos por secuencia.
- `delay_seconds` por paso: `0` a `86400`.
- Si un paso es inválido (por ejemplo multimedia sin `media_url`), se rechaza en validación.
