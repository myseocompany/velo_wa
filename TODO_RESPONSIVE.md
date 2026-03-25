# TODO Responsive

Fecha: 2026-03-25
Estado: Pendiente
Metodo: revision estatica del codigo. No se hizo validacion visual en navegador durante esta auditoria.

## Objetivo

Lograr que la app sea usable en 360px-430px sin scroll horizontal global, sin depender de hover y sin layouts que obliguen a usar tablet o landscape.

## Resumen ejecutivo

- El mayor problema no esta en una sola pagina: el shell base sigue siendo desktop-first.
- Las vistas mas criticas en movil son `Inbox`, `Contactos` y `Pipeline`.
- `Dashboard`, `Team` y varias pantallas de `Settings` siguen siendo table-first o usan filas compactas que no bajan bien a movil.
- Hay varios modales y formularios con grids de 2 columnas y footers horizontales que deberian colapsar en `<sm>`.

## Reglas de implementacion

- Mobile-first en el shell principal.
- Una sola capa principal por vez en telefono para vistas complejas.
- No usar `overflow-x-hidden` global para esconder problemas de layout.
- No dejar acciones solo accesibles por hover.
- Tap targets minimo 44x44px (48x48dp en Android).
- En `<md>`, toda tabla debe tener fallback de cards/lista o una version compacta realmente usable.
- Los anchos fijos deben quedar limitados a `md` o `lg`, salvo casos donde el scroll horizontal sea intencional y controlado.

## Hallazgos actuales

### P0. Shell principal sin navegacion movil real

- Evidencia: `resources/js/Layouts/AppLayout.tsx:85-87`, `resources/js/Layouts/AppLayout.tsx:168-187`
- Problema: el layout usa `h-screen`, sidebar fija `w-60` y no tiene header movil, drawer ni menu colapsable.
- Impacto: en telefono el sidebar consume ancho util, fuerza una experiencia desktop comprimida y expone problemas clasicos de `100vh` en iOS/Android.
- Cambio esperado: shell mobile-first con `min-h-[100dvh]`, sidebar oculta en `<md>`, header movil, drawer superpuesto y manejo correcto de safe areas (`env(safe-area-inset-*)`).

### P0. Inbox sigue armado como vista de escritorio continua

- Evidencia: `resources/js/Pages/Inbox/Index.tsx:238-279`, `resources/js/Pages/Inbox/Index.tsx:522-659`, `resources/js/Pages/Inbox/Partials/ContactPanel.tsx:94-103`
- Problema: lista fija `w-80`, panel lateral fijo `w-72`, header del hilo con varias acciones en una sola fila y modal de nueva conversacion en `grid-cols-2`.
- Impacto: en movil la vista quiere renderizar lista + hilo + panel al mismo tiempo, el header se aprieta y el panel de contacto roba espacio del hilo.
- Cambio esperado: en `<md>` usar flujo de una sola capa:
  lista -> hilo -> panel contacto.
  El panel de contacto debe abrir como sheet/drawer y el hilo debe tener boton de volver.

### P0. Contactos depende de tabla y filtros desktop-first

- Evidencia: `resources/js/Pages/Contacts/Index.tsx:113-145`, `resources/js/Pages/Contacts/Index.tsx:217-229`, `resources/js/Pages/Contacts/Index.tsx:391-456`, `resources/js/Pages/Contacts/Index.tsx:536-547`
- Problema: buscador con `min-w-[220px]`, barra de filtros horizontal, tabla como unica representacion principal y paginacion en una sola fila.
- Impacto: en telefono la tabla obliga a scroll horizontal y los filtros no tienen una jerarquia clara.
- Cambio esperado: filtros apilados en movil, cards de contacto en `<md>`, tabla solo desde `md`, acciones secundarias visibles sin hover.

### P0. Pipeline fuerza desborde horizontal fuera del board

- Evidencia: `resources/js/Pages/Pipeline/Index.tsx:288-301`, `resources/js/Pages/Pipeline/Index.tsx:347-360`, `resources/js/Pages/Pipeline/Index.tsx:389-399`, `resources/js/Pages/Pipeline/Index.tsx:647-721`, `resources/js/Pages/Pipeline/Index.tsx:726-776`
- Problema: altura basada en `100vh`, barra de filtros con anchos fijos (`w-40`, `w-36`, `w-28`), resumen de 5 KPIs desde `md`, board con `minWidth: 'max-content'` y columnas de `272px`.
- Impacto: el usuario llega a una pantalla con desborde horizontal antes de tocar el board, que deberia ser el unico lugar con scroll lateral controlado.
- Cambio esperado: filtros en grid responsive, resumen mobile-first, scroll horizontal limitado al board y columnas con `snap` en movil.

### P1. Dashboard y Team siguen siendo table-first y tienen interacciones no touch-friendly

- Evidencia: `resources/js/Pages/Dashboard.tsx:289-303`, `resources/js/Pages/Dashboard.tsx:308-344`, `resources/js/Pages/Dashboard.tsx:401-415`, `resources/js/Pages/Team/Index.tsx:120-145`, `resources/js/Pages/Team/Index.tsx:150-189`
- Problema: menu de exportacion por hover, fila Dt1 en `grid-cols-3` desde base y tablas sin alternativa movil clara.
- Impacto: en touch hay acciones ocultas o incomodas y las tablas siguen siendo la vista primaria en pantallas pequenas.
- Cambio esperado: acciones visibles con tap, KPIs de una columna o dos columnas maximo en movil y vista compacta tipo cards para rendimiento por agente.

### P1. Settings operativos aun tienen filas y acciones apretadas

- Evidencia: `resources/js/Pages/Settings/General.tsx:148-163`, `resources/js/Pages/Settings/General.tsx:179-208`, `resources/js/Pages/Settings/ActivityLog.tsx:107-145`, `resources/js/Pages/Settings/ActivityLog.tsx:168-205`, `resources/js/Pages/Settings/Team.tsx:129-144`, `resources/js/Pages/Settings/Team.tsx:171-235`, `resources/js/Pages/Settings/Team.tsx:339-406`, `resources/js/Pages/Settings/QuickReplies.tsx:275-315`, `resources/js/Pages/Settings/Automations.tsx:720-820`, `resources/js/Pages/Settings/WhatsApp.tsx:138-182`
- Problema: formularios en una sola fila, tablas sin fallback, acciones compactas a la derecha, QR con tamano fijo `h-64 w-64`.
- Impacto: varias pantallas de configuracion son navegables pero no comodas en telefono, sobre todo para administracion rapida.
- Cambio esperado: formularios apilados en movil, listas con wrap real de acciones y tablas convertidas a cards cuando aplique.

### P1. Contact detail y modales aun tienen puntos de friccion en movil

- Evidencia: `resources/js/Pages/Contacts/Show.tsx:168-168`, `resources/js/Pages/Contacts/Show.tsx:277-289`, `resources/js/Pages/Contacts/Show.tsx:320-338`, `resources/js/Pages/Contacts/Show.tsx:386-417`
- Problema: la vista principal ya colapsa a una sola columna, pero el listado de conversaciones usa fila compacta con `justify-between`, el modal de edicion usa `grid-cols-2` y los custom fields usan una fila `w-1/3 + flex-1`.
- Impacto: no rompe toda la pagina, pero si genera formularios densos y filas dificiles de leer en telefono.
- Cambio esperado: mejorar solo los puntos de friccion del detalle y alinear sus modales con el patron responsive del resto del sistema.

## Plan de trabajo propuesto

### RWD-01 - Refactor del shell principal

- Prioridad: P0
- Scope: `resources/js/Layouts/AppLayout.tsx`
- Entregables:
  header movil, drawer para navegacion, menu de usuario usable con touch, `min-h-[100dvh]`, sidebar solo en `md+`.
- Hecho cuando:
  no hay sidebar fija visible en telefono, se puede navegar a todas las secciones desde un drawer y no hay scroll horizontal global al entrar a cualquier pagina.

### RWD-02 - Inbox mobile-first

- Prioridad: P0
- Scope: `resources/js/Pages/Inbox/Index.tsx`, `resources/js/Pages/Inbox/Partials/ContactPanel.tsx`, `resources/js/Pages/Inbox/Partials/ConversationList.tsx`, `resources/js/Pages/Inbox/Partials/MessageThread.tsx`
- Entregables:
  lista full width en movil, hilo full width al seleccionar conversacion, boton volver, panel de contacto como sheet, header con wrap, modal de nueva conversacion responsive.
- Hecho cuando:
  en 390px se puede abrir una conversacion, responder, abrir/cerrar panel de contacto y volver a la lista sin que convivan 2-3 columnas en la misma vista.

### RWD-03 - Contactos: lista, detalle y modales

- Prioridad: P0
- Scope: `resources/js/Pages/Contacts/Index.tsx`, `resources/js/Pages/Contacts/Show.tsx`
- Entregables:
  filtros apilados, cards de contacto en movil, paginacion en bloque vertical, acciones visibles sin hover, modales de crear/combinar/editar con una columna en `<sm>`.
- Hecho cuando:
  la lista de contactos es usable sin scroll horizontal y el detalle del contacto permite editar datos en telefono sin campos comprimidos.

### RWD-04 - Pipeline responsive

- Prioridad: P0
- Scope: `resources/js/Pages/Pipeline/Index.tsx`
- Entregables:
  top bar responsive, KPIs del summary mobile-first, board con scroll lateral intencional y `snap`, modal de deal con filas apiladas en movil.
- Hecho cuando:
  el unico scroll horizontal permitido en movil es el del board y los filtros/summary no desbordan el viewport.

### RWD-05 - Dashboard y Team con fallback movil

- Prioridad: P1
- Scope: `resources/js/Pages/Dashboard.tsx`, `resources/js/Pages/Team/Index.tsx`
- Entregables:
  KPIs reordenados para movil, menu de exportacion touch-friendly, tabla de agentes con version compacta o cards, acciones y controles que hagan wrap.
- Hecho cuando:
  ambas pantallas se pueden leer y operar en telefono sin zoom manual ni hover.

### RWD-06 - Settings responsive pass

- Prioridad: P1
- Scope: `resources/js/Pages/Settings/General.tsx`, `resources/js/Pages/Settings/ActivityLog.tsx`, `resources/js/Pages/Settings/Team.tsx`, `resources/js/Pages/Settings/QuickReplies.tsx`, `resources/js/Pages/Settings/Automations.tsx`, `resources/js/Pages/Settings/WhatsApp.tsx`
- Entregables:
  formularios apilados, listas con acciones en segunda linea si hace falta, QR adaptable, activity log con cards en movil o tabla compacta real.
- Hecho cuando:
  un owner/admin puede gestionar configuracion basica desde telefono sin depender de landscape ni scroll horizontal.

### RWD-07 - Patron comun para modales y formularios

- Prioridad: P1
- Scope: modales y formularios en `Inbox`, `Contacts`, `Pipeline`, `Settings`
- Entregables:
  convencion comun para grids `grid-cols-1 sm:grid-cols-2`, footers `flex-col-reverse sm:flex-row`, inputs `w-full min-w-0`, drawers/sheets donde aplique.
- Hecho cuando:
  no queden modales nuevos o viejos con footers apretados ni parejas de campos obligadas en pantallas chicas.

### RWD-08 - QA y cierre de regresiones

- Prioridad: P1
- Scope: validacion final en todas las vistas bajo `AppLayout`
- Entregables:
  checklist de QA por viewport, pruebas manuales de flujos criticos y ajustes finales de spacing/tipografia/touch targets.
- Hecho cuando:
  la app pasa la matriz de validacion sin scroll horizontal global ni acciones ocultas por falta de hover.

## Orden sugerido

1. `RWD-01` — shell primero, todo lo demas depende de esto
2. `RWD-07` — convenciones de modales/formularios antes de tocar vistas, asi se aplican desde el inicio
3. `RWD-02` — inbox mobile-first
4. `RWD-03` — contactos
5. `RWD-04` — pipeline
6. `RWD-05` — dashboard y team
7. `RWD-06` — settings
8. `RWD-08` — QA final

## Matriz minima de validacion

- 360x800 Android Chrome (portrait)
- 390x844 iPhone 13/14 Safari (portrait)
- 390x844 iPhone 13/14 Safari (landscape) — validar inbox y drawer
- 768x1024 iPad portrait
- 1280x800 desktop

Nota: en Safari iOS verificar que `env(safe-area-inset-*)` funciona correctamente en header, drawer y bottom actions.

## Flujos que deben probarse al cierre

- Navegar entre Dashboard, Inbox, Contactos, Pipeline, Team y Settings desde el menu movil.
- Abrir una conversacion, enviar mensaje y abrir/cerrar el panel de contacto.
- Buscar contactos, abrir un detalle y editar un contacto.
- Filtrar Pipeline, mover un deal y crear un deal nuevo.
- Revisar Dashboard y Team sin hover.
- Abrir Settings General, Activity Log, Team, Quick Replies, Automations y WhatsApp desde movil.
