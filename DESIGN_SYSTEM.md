# AriCRM — Design System Overhaul

## Context

AriCRM is a WhatsApp-first CRM for micro-enterprises in Latin America. Built with Laravel 11 + Tailwind CSS. The app currently has an inconsistent visual identity: the landing page uses dark theme + amber, the login uses gray + magenta, and the dashboard uses a mix of purple, green, coral, and gray with no coherent system.

We're doing a full design system refactor inspired by Nubank's approach: one dominant brand color, clean light backgrounds, and strict token usage across every screen.

## Brand Foundation

- **Name:** Ari (from Quechua: "Sí")
- **Tagline:** El CRM que significa Sí
- **Logo:** Already exists — the "Y-ari" mark uses a purple/pink gradient. Keep the logo as-is.
- **Domain:** app.aricrm.co
- **Stack:** Laravel 11, Blade/Livewire, Tailwind CSS

## New Color System

### Primary — Purple (brand identity)
```
--ari-purple-50:  #F5F3FF   /* bg: subtle highlights, selected rows, active nav bg */
--ari-purple-100: #EDE9FE   /* bg: hover states on light surfaces */
--ari-purple-200: #DDD6FE   /* borders: active inputs, selected items */
--ari-purple-300: #C4B5FD   /* icons: secondary purple icons */
--ari-purple-400: #A78BFA   /* decorative: illustrations, charts secondary */
--ari-purple-500: #8B5CF6   /* interactive: links, secondary buttons */
--ari-purple-600: #7C3AED   /* PRIMARY: main CTA buttons, active nav indicator, primary links */
--ari-purple-700: #6D28D9   /* hover: CTA hover state */
--ari-purple-800: #5B21B6   /* text: headings on purple-50 bg */
--ari-purple-900: #4C1D95   /* text: dark emphasis on light purple */
```

### WhatsApp Accent — Green (functional, NOT decorative)
```
--ari-green-50:  #F0FDF4   /* bg: success states, connected indicators */
--ari-green-100: #DCFCE7   /* bg: success badges */
--ari-green-500: #22C55E   /* icons: connected/online/sent status */
--ari-green-600: #16A34A   /* text: success messages */
--ari-green-700: #15803D   /* text: success on light green bg */
```
RULE: Green is ONLY for WhatsApp connection status, message sent/delivered, and success states. Never for navigation, CTAs, or decorative elements.

### Neutrals
```
--ari-gray-25:  #FAFAFA    /* bg: page background (main canvas) */
--ari-gray-50:  #F9FAFB    /* bg: card backgrounds, sidebar bg */
--ari-gray-100: #F3F4F6    /* bg: input backgrounds, table headers */
--ari-gray-200: #E5E7EB    /* borders: cards, dividers, input borders */
--ari-gray-300: #D1D5DB    /* borders: hover states */
--ari-gray-400: #9CA3AF    /* text: placeholders, disabled text */
--ari-gray-500: #6B7280    /* text: secondary/body text */
--ari-gray-600: #4B5563    /* text: labels, metadata */
--ari-gray-700: #374151    /* text: subheadings */
--ari-gray-800: #1F2937    /* text: primary headings */
--ari-gray-900: #111827    /* text: strongest emphasis */
```

### Semantic
```
--ari-danger-50:  #FEF2F2   --ari-danger-500: #EF4444   --ari-danger-700: #B91C1C
--ari-warning-50: #FFFBEB   --ari-warning-500: #F59E0B  --ari-warning-700: #B45309
--ari-info-50:    #EFF6FF   --ari-info-500:   #3B82F6   --ari-info-700:   #1D4ED8
```

## Tailwind Config

Map these to your `tailwind.config.js`:

```js
module.exports = {
  theme: {
    extend: {
      colors: {
        ari: {
          50:  '#F5F3FF',
          100: '#EDE9FE',
          200: '#DDD6FE',
          300: '#C4B5FD',
          400: '#A78BFA',
          500: '#8B5CF6',
          600: '#7C3AED',  // PRIMARY
          700: '#6D28D9',
          800: '#5B21B6',
          900: '#4C1D95',
        },
        whatsapp: {
          50:  '#F0FDF4',
          100: '#DCFCE7',
          500: '#22C55E',
          600: '#16A34A',
          700: '#15803D',
        },
      },
    },
  },
}
```

## Component Rules

### Buttons
- **Primary:** `bg-ari-600 hover:bg-ari-700 text-white rounded-lg px-5 py-2.5 font-medium text-sm`
- **Secondary:** `bg-white border border-ari-600 text-ari-600 hover:bg-ari-50 rounded-lg px-5 py-2.5 font-medium text-sm`
- **Ghost:** `bg-transparent border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-lg px-5 py-2.5 font-medium text-sm`
- **Danger:** `bg-red-500 hover:bg-red-600 text-white rounded-lg px-5 py-2.5 font-medium text-sm`
- **KILL the magenta/pink button** on the login page. Replace with Primary (purple).

### Inputs
- Default: `border-gray-200 bg-gray-50 focus:border-ari-500 focus:ring-ari-500/20 rounded-lg`
- Error: `border-red-500 focus:border-red-500 focus:ring-red-500/20`
- NO more light blue background on inputs. Use `bg-gray-50` or `bg-white`.

### Sidebar Navigation
- Container: `bg-white border-r border-gray-200`
- Item default: `text-gray-600 hover:bg-gray-50 hover:text-gray-800 rounded-lg px-3 py-2`
- Item active: `bg-ari-50 text-ari-700 font-medium rounded-lg px-3 py-2`
- **KILL the green/mint active state.** Replace with purple-50 bg + purple-700 text.

### Metric Cards (Dashboard)
- Container: `bg-white border border-gray-200 rounded-xl p-5`
- Icon circle: `bg-ari-50 text-ari-600` (ALL icons use the same purple treatment, not 4 different colors)
- Value: `text-2xl font-semibold text-gray-900`
- Label: `text-sm text-gray-500`
- Exception: "Mensajes hoy" can use `bg-whatsapp-50 text-whatsapp-600` icon since it represents WhatsApp activity.

### Charts
- Primary series: `#7C3AED` (purple-600)
- Secondary series: `#C4B5FD` (purple-300)
- WhatsApp-related data: `#22C55E` (whatsapp-500)
- NO more teal/turquoise. Use purple ramp for all non-WhatsApp data.

### Time Filter Pills (top right of dashboard)
- Default: `border border-gray-200 text-gray-600 bg-white rounded-lg px-3 py-1.5 text-sm`
- Active: `bg-ari-600 text-white rounded-lg px-3 py-1.5 text-sm font-medium`
- **KILL the coral/pink "Hoy" button.** Replace with purple.

### Badges/Status
- Lead status: `bg-ari-50 text-ari-700` (new), `bg-blue-50 text-blue-700` (in progress), `bg-gray-100 text-gray-600` (closed)
- WhatsApp status: `bg-whatsapp-50 text-whatsapp-700` (connected), `bg-red-50 text-red-700` (disconnected)
- **Ganado (pipeline):** Keep the trophy icon but change color to `text-ari-600`, not green.

## Screens to Update (Priority Order)

### 1. Login Page
- [ ] Change LOG IN button from magenta to `bg-ari-600 hover:bg-ari-700 text-white`
- [ ] Change input focus state from red border to `focus:border-ari-500 focus:ring-2 focus:ring-ari-500/20`
- [ ] Keep the error state red (that's correct semantic usage)
- [ ] Change "Forgot your password?" link to `text-ari-600 hover:text-ari-700`
- [ ] Background: keep `bg-gray-50` (that's fine)

### 2. Dashboard
- [ ] Sidebar active item: replace green/mint bg with `bg-ari-50 text-ari-700`
- [ ] Time filter active pill: replace coral/pink with `bg-ari-600 text-white`
- [ ] Metric card icons: unify to purple treatment (`bg-ari-50 text-ari-600`)
- [ ] Charts: replace teal with purple-600, keep green only for WhatsApp "Salientes"
- [ ] "Ver >" link on Pipeline card: `text-ari-600 hover:text-ari-700`
- [ ] "Ganado" trophy: `text-ari-600`

### 3. Landing Page (app.aricrm.co)
- [ ] Switch from dark theme to light theme (bg-white / bg-gray-50)
- [ ] Replace ALL amber/gold (#F59E0B) with purple-600
- [ ] Hero: white/light background, dark text, purple CTA
- [ ] Feature cards: white cards on gray-50 bg, purple icons
- [ ] Pricing: white cards, featured plan with purple border
- [ ] Testimonials: light cards, purple accent quotes
- [ ] Footer: dark (gray-900) is acceptable for contrast

### 4. Inbox, Contacts, Pipeline, Equipo, Configuración
- Apply the same sidebar, button, badge, and input patterns consistently.

## Typography

Keep whatever font is currently in use (looks like a clean sans-serif, possibly the system font stack or Inter). If you want to upgrade:
- Headings: `font-sans font-semibold` (existing)
- Body: `font-sans font-normal`
- Monospace/data: `font-mono` for metrics, timestamps

## What NOT to Change
- The logo mark (Y-ari gradient) — it already works with purple
- The overall layout structure of the dashboard — it's well organized
- The Dt1 metrics concept — that's great product thinking
- The page background being slightly off-white — that's correct

## Implementation Notes

1. Start by updating `tailwind.config.js` with the new color tokens
2. Create a Blade component or CSS class for each button variant so changes propagate everywhere
3. Search the codebase for hardcoded hex values (especially `#10B981`, `#F59E0B`, `#EC4899` / magenta, any teal/turquoise) and replace with the new token references
4. Test in both light context and ensure the purple has sufficient contrast (WCAG AA minimum)

## One Rule to Remember

> Purple is the protagonist. Green is the WhatsApp signal. Gray is everything else. Any other color needs a semantic reason to exist (red = error, yellow = warning, blue = info).
