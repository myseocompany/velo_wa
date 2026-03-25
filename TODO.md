# TODO

## Auditoria tecnica - 2026-03-13

### Prioridad alta

- [ ] Hacer compatible con SQLite y PostgreSQL la migracion [`database/migrations/2026_03_09_000001_fix_duplicate_conversations.php`](/Users/projects/velo_wa/database/migrations/2026_03_09_000001_fix_duplicate_conversations.php) para recuperar la suite de pruebas. Estado actual: `php artisan test` falla masivamente por SQL especifico de PostgreSQL.
- [ ] Evitar ejecuciones repetidas de automatizaciones `no_response_timeout`. Falta idempotencia por conversacion y registro de ejecucion para no reenviar follow-ups en cada corrida del scheduler.
- [ ] Aplicar RBAC real en backend para endpoints sensibles. Hoy varias restricciones viven en la UI, pero no en controladores, policies o `authorize()`.

### Prioridad media

- [ ] Unificar el contrato de automatizaciones por keyword: la UI usa `case_insensitive`, mientras validacion y motor usan `case_sensitive`.
- [ ] Conectar o eliminar el cache del dashboard. El job [`app/Jobs/RecalculateMetrics.php`](/Users/projects/velo_wa/app/Jobs/RecalculateMetrics.php) precalcula datos, pero el dashboard actual sigue resolviendo en vivo.
- [ ] Exponer `custom_fields` de contactos en requests y UI. El schema y el modelo los soportan, pero la edicion funcional aun no.

### Prioridad baja

- [ ] Actualizar [`docs/ROADMAP.md`](/Users/projects/velo_wa/docs/ROADMAP.md) para reflejar el avance real de Fases 2 a 6.
- [ ] Revisar peso de chunks del frontend. `npm run build` compila, pero Vite reporta bundles grandes en inbox, charts y app principal.

## UX / Pulido de producto

### Prioridad alta

- [ ] **Onboarding: QR inline en Step 2** — El paso "Conecta WhatsApp" solo dice "ve a Ajustes → WhatsApp" pero no ofrece el QR en contexto. Hay que incrustar el mismo flujo de generación de QR (llamada a `/api/v1/whatsapp/connect` + polling de estado) directamente en `Onboarding.tsx` Step 2, igual que funciona en la pantalla de Ajustes. El usuario no debería tener que salir del onboarding para conectar.
- [ ] **Saludo personalizado** — Step 1 del onboarding y otros saludos usan `tenant.name` (nombre de empresa). Cambiar a `auth()->user()->name` (nombre de la persona). Definir fallback si el campo está vacío (email o nombre del tenant).
- [ ] **Corregir Logo "AriCare" → "AriCRM"** — Hacer grep global para encontrar todas las ocurrencias del nombre incorrecto: sidebar, emails transaccionales, meta tags, landing page, facturas Stripe. La pantalla de onboarding ya dice "AriCRM" correctamente.

### Prioridad media

- [ ] **Corregir Header Usuario** — El dropdown de usuario en la navbar debería mostrar nombre y rol por separado (ej: "Carlos López" + badge "Agente"). El rol viene de `spatie/laravel-permission`, el dato ya existe.
- [ ] **Empty States con demo** — Cuando el tenant no tiene datos, mostrar contenido de ejemplo con banner "Vista de ejemplo" en: Conversaciones, Contactos, Deals y Dashboard. Decidir si es fake data visual o seed real en DB al crear el tenant.

### Prioridad baja

- [ ] **Tour guiado** — Product tour para nuevos usuarios post-onboarding. Evaluar Driver.js o Shepherd.js. Máximo 7 pasos. Decidir: ¿auto-disparo en primer login o botón "Ver tour"? ¿Estado guardado en DB o localStorage? ¿Tour diferente por rol?

## Resumen de avance estimado

- [ ] Fase 0-1: casi completa
- [ ] Fase 2: avanzada
- [ ] Fase 3: avanzada con huecos en `custom_fields` y flujo manual
- [ ] Fase 4: avanzada
- [ ] Fase 5: funcional con deuda tecnica
- [ ] Fase 6: funcional pero no lista para produccion
- [ ] Fase 7-8: mayormente pendientes
