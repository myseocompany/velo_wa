# Tarea: Agregar drawer movil al sidebar de AppLayout

## Archivo

`resources/js/Layouts/AppLayout.tsx`

## Contexto

App React + Inertia.js + Tailwind CSS. El layout tiene un sidebar fijo `w-60` que se muestra siempre. En movil (<md) no hay forma de navegar — el sidebar ocupa espacio fijo y rompe el layout.

## Que hacer

### 1. Agregar estado para el drawer

```tsx
const [sidebarOpen, setSidebarOpen] = useState(false);
```

### 2. Extraer contenido del sidebar

Para NO duplicar JSX, extraer el contenido del aside actual (logo, nav items, user menu) en una variable o funcion `sidebarContent` que se renderice tanto en el drawer movil como en el aside desktop.

### 3. Sidebar desktop (md+) — sin cambios funcionales

Agregar `hidden md:flex` al aside actual para que solo se muestre en desktop:

```
<aside className="hidden md:flex w-60 flex-col border-r border-gray-200 bg-white">
    {sidebarContent}
</aside>
```

### 4. Sidebar movil (<md) — drawer overlay

Agregar ANTES del aside desktop:

```tsx
{/* Mobile sidebar overlay */}
{sidebarOpen && (
    <div className="fixed inset-0 z-40 md:hidden">
        <div className="fixed inset-0 bg-black/40" onClick={() => setSidebarOpen(false)} />
        <aside className="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-white shadow-xl">
            {/* Boton cerrar en esquina superior derecha */}
            <div className="flex h-16 items-center justify-between px-4">
                {/* Logo (mismo que sidebar desktop) */}
                <button
                    onClick={() => setSidebarOpen(false)}
                    className="flex h-11 w-11 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                >
                    <X className="h-5 w-5" />
                </button>
            </div>
            {sidebarContent}
        </aside>
    </div>
)}
```

### 5. Top bar movil con hamburger

Agregar un header fijo que solo se muestra en movil, ANTES del `<main>` y dentro del wrapper principal que contiene el contenido (NO dentro del aside):

```tsx
<div className="flex h-14 items-center gap-3 border-b border-gray-200 bg-white px-4 md:hidden">
    <button
        onClick={() => setSidebarOpen(true)}
        className="flex h-10 w-10 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100"
    >
        <Menu className="h-5 w-5" />
    </button>
    {/* Usar el mismo logo que el sidebar desktop */}
    <img src="/images/logo.svg" alt="Logo" className="h-7" />
</div>
```

**IMPORTANTE**: Verificar la ruta real del logo en el sidebar actual y usar la misma. Puede ser `/images/logo.svg`, `/images/logo-ari.svg` u otra.

### 6. Cerrar drawer al navegar

Los links del sidebar usan `<Link>` de Inertia. Agregar `onClick={() => setSidebarOpen(false)}` a cada link de navegacion para que el drawer se cierre al cambiar de pagina.

### 7. Cerrar drawer al hacer click en items del user menu

El user menu tiene links a Profile y Logout. Agregar `onClick={() => setSidebarOpen(false)}` tambien.

## Imports necesarios

Agregar a los imports de `lucide-react`:

```tsx
import { Menu, X } from 'lucide-react';
```

Verificar si `X` ya esta importado antes de duplicar.

## Reglas

- NO cambiar la estructura ni estilos del sidebar en desktop (md+)
- NO duplicar el JSX del sidebar — usar variable/funcion compartida
- El boton X de cerrar debe tener min tap target de 44px (`h-11 w-11`)
- NO tocar otros archivos
- El drawer debe tener `overflow-y-auto` en la seccion de nav items
- NO agregar animaciones/transiciones (mantenerlo simple)

## Verificacion

Despues de los cambios:

- En desktop (md+): sidebar visible como siempre, sin hamburger visible
- En movil (<md): hamburger en top bar -> abre drawer -> click en link -> cierra drawer -> navega
- El empty state del main content no debe tener doble scroll
