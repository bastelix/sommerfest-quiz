# Marketing design tokens

Marketing pages resolve their theme from the namespace design settings. The `namespace-tokens.css`
stylesheet exposes the following marketing tokens for each namespace:

* `--marketing-primary` – defaults to the namespace `brand.primary`.
* `--marketing-accent` – defaults to the namespace `brand.accent`.
* `--marketing-link` – defaults to the namespace `brand.primary`.
* `--marketing-surface` – defaults to the shared surface token (`--surface-card`).
* `--marketing-background` – defaults to the shared page background token (`--surface-page`) and
  powers the marketing page canvas.
* `--marketing-text-on-surface` – primary text color for content on cards/sections.
* `--marketing-text-on-background` – primary text color for content on the page background.
* `--marketing-text-muted-on-surface` – muted copy on cards/sections.
* `--marketing-text-muted-on-background` – muted copy on the page background.
* `--marketing-text-on-surface-dark` – dark-mode text color for cards/sections.
* `--marketing-text-on-background-dark` – dark-mode text color for the page background.
* `--marketing-text-muted-on-surface-dark` – dark-mode muted copy on cards/sections.
* `--marketing-text-muted-on-background-dark` – dark-mode muted copy on the page background.

The tokens are emitted in `templates/marketing/partials/theme-vars.twig`, which reads the namespace
design payload (`appearance.colors` + `appearance.variables`) and maps the values to `--marketing-*`
CSS variables. Global brand/surface tokens (`--brand-*`, `--surface*`) stay sourced from the base
namespace design and are not overridden by marketing presets; marketing pages that need app
components to follow marketing colors should rely on the scoped mappings in
`public/css/marketing.css` instead.

## Marketing presets

The Admin-Design UI includes predefined marketing palettes. Selecting a preset stores the choice in
`appearance.variables.marketingScheme` and overwrites the following tokens for light/dark mode:

* `--marketing-primary`, `--marketing-accent`, `--marketing-surface`, `--marketing-background`,
  `--marketing-on-accent`
* `--marketing-text-on-surface`, `--marketing-text-on-background`,
  `--marketing-text-muted-on-surface`, `--marketing-text-muted-on-background`
* `--marketing-text-on-surface-dark`, `--marketing-text-on-background-dark`,
  `--marketing-text-muted-on-surface-dark`, `--marketing-text-muted-on-background-dark`

The palette values live in `config/marketing-design-tokens.php` and are shared by the admin preview
and marketing theme variable output. The mapping applies only to marketing pages (templates that
include `templates/marketing/partials/theme-vars.twig`) and only when
`appearance.variables.marketingScheme` points at a known palette key.

Clear the preset to fall back to the regular namespace brand tokens.

## Live mappings for typography + component styles

The namespace tokens for typography and component styles are mapped to live CSS variables via
data-attributes on the document root. The mapping is applied in `public/css/marketing.css` and the
attribute sync happens in `public/js/marketing-design.js` (marketing pages) plus
`public/js/components/namespace-design.js` (CMS page renderer).

* `--typography-preset` → `data-typography-preset` → `--marketing-font-stack`,
  `--marketing-heading-font-stack`, `--marketing-heading-weight`, `--marketing-heading-letter-spacing`
* `--components-card-style` → `data-card-style` → `--marketing-card-radius`, `--marketing-shadow-card`,
  `--marketing-shadow-card-soft` (rounded, square, pill)
* `--components-button-style` → `data-button-style` → button surface variables for
  `uk-button-primary`/`uk-button-default` (filled, outline, ghost)

Marketing blocks already render cards/buttons with UIkit classes (`uk-card`, `uk-button`), so the
token mappings immediately affect the block renderer output when the page is hydrated.

## Marketing stylesheet entry point

Page-Editor Marketing-Seiten binden ausschließlich `public/css/marketing.css` ein. Die Datei ist
der alleinige Style-Entry für diese Marketing-Pages und soll ohne Abhängigkeiten zu älteren Landing-Styles
gepflegt werden.

## Build hook for marketing.css

When you need a production bundle for marketing pages, run a dedicated minify step that targets
only `public/css/marketing.css`. For example, add a CI hook or local script step such as:

```bash
npx postcss public/css/marketing.css --env production --no-map -o public/css/marketing.min.css
```

This keeps the marketing pipeline isolated and avoids touching legacy landing styles.

## Managing marketing tokens

1. Open the **Page Design** editor for the marketing namespace you want to adjust.
2. Update the brand colors in the **Namespace Design** panel.
3. Update the background color in the design panel to change the marketing page canvas.
4. Save the namespace. The marketing tokens above are regenerated automatically for the namespace.

If you need a different marketing color scheme per namespace, adjust the brand colors there. The
marketing stylesheet consumes only the `--marketing-*` tokens to keep the scope predictable.
