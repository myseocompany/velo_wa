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

## Resumen de avance estimado

- [ ] Fase 0-1: casi completa
- [ ] Fase 2: avanzada
- [ ] Fase 3: avanzada con huecos en `custom_fields` y flujo manual
- [ ] Fase 4: avanzada
- [ ] Fase 5: funcional con deuda tecnica
- [ ] Fase 6: funcional pero no lista para produccion
- [ ] Fase 7-8: mayormente pendientes
