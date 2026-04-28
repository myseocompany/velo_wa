# Tenant Selector Login

## Contexto

Un mismo correo puede existir en más de un tenant porque `users` tiene unicidad por `tenant_id + email`, no por email global.

Ejemplo validado en producción:

- `jahenao1986@hotmail.com` en `AMIA Ginecología`
- `jahenao1986@hotmail.com` en `Amia`

Antes, el login usaba `Auth::attempt(['email', 'password'])`, lo que no resolvía de forma explícita a cuál tenant debía entrar el usuario.

## Qué se implementó

Se agregó un flujo de selección de tenant:

1. El usuario inicia sesión con email y contraseña.
2. El backend busca todas las cuentas activas con ese email.
3. Solo conserva las cuentas donde la contraseña coincide.
4. Si hay una sola coincidencia, entra directo.
5. Si hay más de una coincidencia, redirige a `login/select-tenant`.
6. El usuario selecciona la empresa y se autentica con el usuario correspondiente a ese tenant.

También se agregó una opción en `https://app.aricrm.co/settings`:

- Tarjeta: `Empresa activa`
- Botón: `Cambiar empresa`
- Ruta: `/tenant/select`

Esta tarjeta solo aparece si el email autenticado tiene más de una cuenta activa asociada.

## Archivos principales

- `app/Http/Requests/Auth/LoginRequest.php`
  - Reemplaza el login ambiguo por búsqueda de usuarios activos por email.
  - Valida contraseña manualmente con `Hash::check`.
  - Retorna todas las cuentas válidas para decidir si se requiere selector.

- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
  - Si hay varias cuentas válidas, guarda temporalmente los `user_id` candidatos en sesión.
  - Renderiza `Auth/SelectTenant`.
  - Completa el login con el tenant seleccionado.

- `app/Http/Controllers/Auth/TenantSelectionController.php`
  - Permite cambiar de empresa desde una sesión ya autenticada.
  - Lista otras cuentas activas con el mismo email.
  - Cambia la sesión al usuario del tenant elegido.

- `routes/auth.php`
  - Agrega rutas:
    - `GET /login/select-tenant`
    - `POST /login/select-tenant`
    - `GET /tenant/select`
    - `POST /tenant/select`

- `resources/js/Pages/Auth/SelectTenant.tsx`
  - Pantalla reutilizable para escoger tenant durante login o desde configuración.

- `resources/js/Pages/Settings/Index.tsx`
  - Agrega la tarjeta `Empresa activa` cuando hay más de un tenant disponible.

- `app/Http/Middleware/HandleInertiaRequests.php`
  - Comparte `auth.tenant_switcher_available` con Inertia.

- `resources/js/types/index.d.ts`
  - Agrega el tipo `tenant_switcher_available`.

## Despliegue

Se desplegó manualmente en `waterfall` sobre:

```bash
/home/forge/app.aricrm.co/current
```

Comandos de verificación/despliegue ejecutados:

```bash
php -l app/Http/Controllers/Auth/TenantSelectionController.php
php -l app/Http/Middleware/HandleInertiaRequests.php
npm run build
php artisan optimize:clear
php artisan route:list --name=tenant
```

Las rutas nuevas quedaron activas en producción.

## Commit

El primer cambio del selector durante login quedó versionado en:

```text
679297a Add tenant selector during login
```

El cambio adicional de selector desde configuración debe commitearse/pushearse junto con este documento.

## Consideraciones

- El selector solo muestra tenants después de validar contraseña; no enumera empresas públicamente.
- Como hoy cada tenant tiene una fila distinta en `users`, un tenant solo aparece si esa cuenta tiene la misma contraseña enviada en login.
- Desde `/settings`, el selector lista tenants por email autenticado, no vuelve a pedir contraseña.
- Esto es una solución compatible con la arquitectura actual. Una evolución futura sería usar una identidad global de usuario y membresías como fuente principal, en lugar de duplicar usuarios por tenant.
