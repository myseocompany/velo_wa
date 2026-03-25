# Tarea: Responsive para Pipeline Kanban

## Archivo

`resources/js/Pages/Pipeline/Index.tsx`

## Contexto

App React + Tailwind. El pipeline es un Kanban con 6 columnas de `w-[272px]` cada una, usando `@hello-pangea/dnd` para drag-and-drop. Ya tiene `overflow-x-auto` en el contenedor del board. Los summary cards ya tienen `grid-cols-2 md:grid-cols-5`. El funnel ya tiene `grid-cols-1 md:grid-cols-2`.

## Que hacer

### 1. Columnas del Kanban

Las columnas tienen `w-[272px] shrink-0`. En movil esto funciona con scroll horizontal, pero una columna no cabe completa en pantallas pequenas.

Cambiar `w-[272px]` a `w-[85vw] sm:w-[272px]` — en movil una columna ocupa casi toda la pantalla (scroll horizontal de una en una), en sm+ vuelve al ancho fijo.

Buscar todas las ocurrencias de `w-[272px]` en el archivo y aplicar el mismo cambio.

### 2. Top bar / filtros

Buscar la barra de filtros (search input, selects de agente, date pickers, value inputs). Si estan en fila horizontal sin responsive:

- Cambiar el contenedor a `flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end`
- Inputs y selects deben ser `w-full sm:w-auto`
- Botones de accion (buscar, limpiar) deben ser `min-h-11 w-full sm:w-auto`

### 3. DealModal (modal de crear/editar deal)

Buscar el componente DealModal o similar. Aplicar:

- Cualquier `grid-cols-2` sin breakpoint -> `grid-cols-1 sm:grid-cols-2`
- Footer con botones -> `flex flex-col-reverse gap-2 sm:flex-row sm:justify-end`
- Botones -> `min-h-11 w-full sm:w-auto`
- Inputs -> asegurar que tengan `w-full min-w-0`
- Boton de cerrar modal (X) -> `flex h-11 w-11 items-center justify-center rounded-lg`

### 4. Tap targets

Botones de icono pequenos en todo el archivo:

- Edit deal button -> minimo `h-11 w-11` con flex center
- Grip/drag handle -> puede quedar pequeno (es para desktop drag)
- Close modal X -> `h-11 w-11`
- Cualquier boton con solo `p-1` o `p-1.5` -> agregar `min-h-11` o `h-11 w-11`

### 5. Summary cards

Ya tienen `grid-cols-2 md:grid-cols-5` — solo verificar que los valores numericos no se trunquen. Si algun card tiene texto largo, agregar `min-w-0 truncate` donde haga falta.

### 6. Seccion de header del board

Si hay un header con titulo + boton "Nuevo deal" en fila:

- Cambiar a `flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between`
- Boton "Nuevo deal" -> `min-h-11 w-full sm:w-auto`

## NO tocar

- La logica de drag-and-drop (`onDragEnd`, `DragDropContext`, `Droppable`, `Draggable`)
- Los endpoints de API
- La logica de filtros (solo el layout de los inputs)
- Los colores de stages
- Los imports

## Reglas

- Mobile-first: clases base para movil, breakpoints para desktop
- Tap targets minimo 44px (h-11 = 44px en Tailwind)
- NO usar `overflow-x-hidden` en body o contenedores principales
- Anchos fijos solo desde `sm:` o `md:`
- NO agregar componentes nuevos ni extraer subcomponentes
- NO agregar state nuevo — solo cambios de clases CSS

## Verificacion

Despues de los cambios:

- En movil (<sm): columnas del kanban se ven una a la vez con scroll horizontal suave
- Filtros apilados verticalmente, inputs full-width
- Modal de deal funcional con campos apilados y botones full-width
- En desktop (sm+/md+): todo se ve igual que antes
