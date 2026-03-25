# Auditoria de responsividad

Fecha: 2026-03-19

## Alcance

- Revision estatica del codigo en `resources/js/Layouts/AppLayout.tsx`, `resources/js/Pages/Inbox/Index.tsx`, `resources/js/Pages/Inbox/Partials/ContactPanel.tsx`, `resources/js/Pages/Contacts/Index.tsx`, `resources/js/Pages/Pipeline/Index.tsx`, `resources/js/Pages/Dashboard.tsx` y `resources/js/Pages/Settings/Team.tsx`.
- No se hizo validacion visual en navegador durante esta auditoria. Los hallazgos salen de la estructura actual del layout, grids, tablas y anchos fijos.

## Resumen

La app esta construida con un shell claramente orientado a desktop. El problema principal no es un componente aislado: el layout base usa sidebar fija y altura de viewport fija, y varias paginas asumen tres columnas, tablas o filtros con ancho minimo. En pantallas pequenas eso provoca alguno de estos sintomas:

- scroll horizontal
- paneles apretados o cortados
- acciones dificiles de usar con touch
- pantallas funcionales solo en landscape o tablet

## Prioridad de correccion

| Prioridad | Hallazgo | Archivos |
| --- | --- | --- |
| Alta | El layout principal no tiene navegacion movil real | `resources/js/Layouts/AppLayout.tsx` |
| Alta | Inbox usa una composicion fija de escritorio | `resources/js/Pages/Inbox/Index.tsx`, `resources/js/Pages/Inbox/Partials/ContactPanel.tsx` |
| Alta | Contactos depende de tabla desktop-first | `resources/js/Pages/Contacts/Index.tsx` |
| Alta | Pipeline fuerza scroll horizontal desde filtros y tablero | `resources/js/Pages/Pipeline/Index.tsx` |
| Media | Dashboard mantiene grids rigidos en movil | `resources/js/Pages/Dashboard.tsx` |
| Media | Formularios y filas de equipo no colapsan bien en movil | `resources/js/Pages/Settings/Team.tsx`, `resources/js/Pages/Inbox/Index.tsx`, `resources/js/Pages/Contacts/Index.tsx` |

## Hallazgos y como corregirlos

### 1. El layout base no tiene navegacion movil

Referencia:

- `resources/js/Layouts/AppLayout.tsx:85`
- `resources/js/Layouts/AppLayout.tsx:87`
- `resources/js/Layouts/AppLayout.tsx:187`

Problema:

- El contenedor principal usa `flex h-screen`.
- La navegacion lateral siempre ocupa `w-60`.
- No existe header movil, drawer, sheet ni menu colapsable.
- En telefonos, esos `240px` de sidebar mas el contenido principal dejan muy poco ancho util.
- `h-screen` tambien suele fallar en iOS/Android cuando aparece la barra del navegador.

Como corregirlo:

1. Cambiar el shell a un layout mobile-first.
2. Mostrar sidebar solo desde `md`.
3. Crear un header movil con boton hamburguesa, titulo y acceso al menu de usuario.
4. Renderizar la navegacion movil como drawer/sheet sobrepuesto.
5. Sustituir `h-screen` por `min-h-[100dvh]` o una combinacion equivalente que no dependa del viewport clasico.

Patron recomendado:

```tsx
<div className="flex min-h-[100dvh] flex-col bg-gray-50 md:flex-row">
  <MobileHeader onMenuToggle={...} />

  <aside className="hidden md:flex md:w-60 md:flex-col md:border-r md:border-gray-200 md:bg-white">
    ...
  </aside>

  {mobileNavOpen && (
    <div className="fixed inset-0 z-40 bg-black/40 md:hidden">
      <aside className="h-full w-72 bg-white shadow-xl">
        ...
      </aside>
    </div>
  )}

  <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
    <main className="flex-1 overflow-y-auto">{children}</main>
  </div>
</div>
```

Importante:

- No tapes este problema con `overflow-x-hidden` global. Eso solo esconderia el desborde sin resolver la causa.

### 2. Inbox esta armado como escritorio fijo

Referencia:

- `resources/js/Pages/Inbox/Index.tsx:520`
- `resources/js/Pages/Inbox/Index.tsx:522`
- `resources/js/Pages/Inbox/Index.tsx:578`
- `resources/js/Pages/Inbox/Index.tsx:636`
- `resources/js/Pages/Inbox/Partials/ContactPanel.tsx:94`

Problema:

- La lista de conversaciones siempre ocupa `w-80`.
- El panel de contacto siempre ocupa `w-72`.
- La vista asume una composicion de 2 o 3 columnas dentro de la misma pantalla.
- En movil, la cabecera del hilo acumula avatar, texto, asignacion, cerrar y panel lateral en una sola fila.

Como corregirlo:

1. En `< md`, mostrar una sola capa por vez:
   - lista de conversaciones
   - hilo activo
   - panel de contacto como sheet
2. Mantener el layout de 2-3 columnas solo desde `lg`.
3. Convertir el panel de contacto en `fixed inset-y-0 right-0 w-full max-w-sm` para movil.
4. Agregar un boton "volver" en el header del hilo para regresar a la lista.
5. Permitir que las acciones del header hagan wrap o se muevan a una segunda fila.

Patron recomendado:

```tsx
<div className="flex h-full overflow-hidden">
  <aside className={`${activeConv ? 'hidden md:flex' : 'flex'} w-full md:w-80 ...`}>
    ...
  </aside>

  <main className={`${activeConv ? 'flex' : 'hidden md:flex'} min-w-0 flex-1 flex-col overflow-hidden`}>
    ...
  </main>

  {showContactPanel && (
    <aside className="fixed inset-y-0 right-0 z-40 w-full max-w-sm border-l border-gray-200 bg-white md:static md:w-72">
      ...
    </aside>
  )}
</div>
```

Tambien corregir:

- `resources/js/Pages/Inbox/Index.tsx:238`: cambiar `grid grid-cols-2` por `grid grid-cols-1 sm:grid-cols-2` en el modal de nueva conversacion.
- `resources/js/Pages/Inbox/Index.tsx:279`: en el footer del modal usar `flex-col-reverse sm:flex-row`.

### 3. Contactos depende de tabla y filtros que no bajan bien a movil

Referencia:

- `resources/js/Pages/Contacts/Index.tsx:391`
- `resources/js/Pages/Contacts/Index.tsx:392`
- `resources/js/Pages/Contacts/Index.tsx:454`
- `resources/js/Pages/Contacts/Index.tsx:456`

Problema:

- El buscador usa `min-w-[220px] flex-1 max-w-sm`, que dificulta un apilado limpio en pantallas pequenas.
- Los filtros quedan en una sola franja horizontal con tags, select y picker.
- La tabla es la unica representacion de los datos.
- `overflow-x-auto` permite desplazamiento, pero la experiencia en telefono sigue siendo pobre.

Como corregirlo:

1. Hacer que el bloque de filtros sea `flex-col sm:flex-row`.
2. Cambiar el buscador a `w-full min-w-0 sm:max-w-sm`.
3. Mostrar tarjetas en movil y tabla desde `md`.
4. Mover acciones secundarias como fusionar o asignado a una segunda linea dentro de la tarjeta.

Patron recomendado:

```tsx
<div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
  <div className="relative w-full min-w-0 sm:max-w-sm">
    ...
  </div>
  <select className="w-full sm:w-auto" />
</div>

<div className="md:hidden space-y-3">
  {contacts.map(contact => (
    <article className="rounded-xl border border-gray-200 bg-white p-4">
      ...
    </article>
  ))}
</div>

<div className="hidden md:block overflow-x-auto">
  <table className="min-w-full ...">
    ...
  </table>
</div>
```

Tambien corregir:

- `resources/js/Pages/Contacts/Index.tsx:113`: cambiar el modal de creacion de `grid-cols-2` a `grid-cols-1 sm:grid-cols-2`.

### 4. Pipeline fuerza desborde horizontal desde la barra superior y el board

Referencia:

- `resources/js/Pages/Pipeline/Index.tsx:647`
- `resources/js/Pages/Pipeline/Index.tsx:657`
- `resources/js/Pages/Pipeline/Index.tsx:663`
- `resources/js/Pages/Pipeline/Index.tsx:677`
- `resources/js/Pages/Pipeline/Index.tsx:721`
- `resources/js/Pages/Pipeline/Index.tsx:726`

Problema:

- La pagina usa `h-[calc(100vh-3.5rem)]`, que en movil es fragil.
- La barra de filtros acumula multiples inputs con ancho fijo (`w-40`, `w-36`, `w-28`).
- El tablero usa `minWidth: 'max-content'` y columnas de `w-[272px]`.
- Eso garantiza scroll horizontal incluso antes de entrar al board.

Como corregirlo:

1. Sustituir la altura fija por `min-h-[100dvh]` o una variante que no dependa del `100vh` clasico.
2. Convertir la barra de filtros en grid responsive:
   - `grid-cols-1`
   - `sm:grid-cols-2`
   - `xl:grid-cols-6` si hace falta
3. Hacer que los inputs usen `w-full`.
4. Limitar el scroll horizontal solo al board, no a toda la cabecera.
5. En movil, usar columnas de `w-[85vw] max-w-[272px] snap-start`.

Patron recomendado:

```tsx
<div className="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-6">
  <input className="w-full ..." />
  <select className="w-full ..." />
  <input className="w-full ..." type="date" />
  <input className="w-full ..." type="number" />
</div>

<div className="overflow-x-auto pb-4">
  <div className="flex gap-3 snap-x snap-mandatory">
    <section className="w-[85vw] max-w-[272px] shrink-0 snap-start md:w-[272px]">
      ...
    </section>
  </div>
</div>
```

### 5. Dashboard tiene grids demasiado rigidos para base mobile

Referencia:

- `resources/js/Pages/Dashboard.tsx:263`
- `resources/js/Pages/Dashboard.tsx:308`
- `resources/js/Pages/Dashboard.tsx:318`
- `resources/js/Pages/Dashboard.tsx:401`

Problema:

- La primera fila de KPIs parte de `grid-cols-2`, que es aceptable pero ajustada cuando los labels crecen.
- La segunda fila usa `grid-cols-3` desde el breakpoint base, lo que comprime demasiado el texto de Dt1.
- La seccion inferior mezcla tabla, resumen y recientes sin un estado intermedio para tablet pequena.

Como corregirlo:

1. Hacer la segunda fila mobile-first con `grid-cols-1 sm:grid-cols-3`.
2. Mantener la primera fila como `grid-cols-1 sm:grid-cols-2 xl:grid-cols-4` si se quiere evitar tarjetas muy estrechas.
3. Confirmar que la tabla de agentes tenga una version compacta o al menos padding menor en `< md`.
4. Revisar el menu de exportacion hover-only para touch.

Cambios puntuales:

- `resources/js/Pages/Dashboard.tsx:318`: cambiar a `grid grid-cols-1 sm:grid-cols-3 gap-3`.
- `resources/js/Pages/Dashboard.tsx:293`: reemplazar `group-hover:block` por un estado controlado con click o por un `Menu` accesible en touch.

### 6. Formularios y filas de equipo no colapsan bien

Referencia:

- `resources/js/Pages/Settings/Team.tsx:171`
- `resources/js/Pages/Settings/Team.tsx:220`
- `resources/js/Pages/Settings/Team.tsx:339`
- `resources/js/Pages/Settings/Team.tsx:365`

Problema:

- El formulario de invitacion resuelve mejor que otros modales, pero las acciones finales siguen en una sola fila.
- Cada `MemberRow` usa avatar + info + rol + menu en una sola linea.
- Cuando el nombre o correo son largos, el rol y el menu quedan apretados.

Como corregirlo:

1. En movil, convertir la fila del miembro en `flex-col items-start`.
2. Pasar el badge de rol y el menu a una segunda fila.
3. En el formulario de invitacion, usar `flex-col-reverse sm:flex-row` en acciones y `w-full sm:w-auto` en botones.

Patron recomendado:

```tsx
<div className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center">
  <div className="flex min-w-0 flex-1 items-center gap-3">
    ...
  </div>
  <div className="flex w-full items-center justify-between gap-2 sm:w-auto sm:justify-end">
    ...
  </div>
</div>
```

## Orden sugerido de implementacion

1. Corregir `AppLayout` y definir una navegacion movil comun.
2. Resolver `Inbox`, porque hoy es la pantalla mas expuesta al problema.
3. Resolver `Contacts` con cards en movil y tabla en desktop.
4. Ajustar `Pipeline` para que el unico scroll horizontal permitido sea el del board.
5. Afinar `Dashboard` y `Settings/Team`.
6. Recorrer modales y reemplazar todos los `grid-cols-2` base por `grid-cols-1 sm:grid-cols-2`.

## Checklist de validacion despues de corregir

- Probar en anchos de 320px, 375px, 390px, 768px, 1024px y 1280px.
- Confirmar que no haya scroll horizontal en `body`.
- Verificar que el inbox funcione en estos estados:
  - lista
  - hilo abierto
  - panel de contacto abierto
  - modal de nueva conversacion abierto
- Verificar que contactos tenga lectura clara sin depender de scroll lateral.
- Verificar que pipeline permita recorrer columnas sin romper la barra superior.
- Verificar que todos los botones clave sean tocables con una sola mano.

## Regla practica

Si una vista depende de `w-72`, `w-80`, `grid-cols-2`, `grid-cols-3`, `h-screen`, `100vh` o `min-width` fijo sin un prefijo responsive, asumir que necesita una variante mobile-first antes de considerarla terminada.
