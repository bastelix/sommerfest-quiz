# Marketing design tokens

Marketing pages resolve their theme from the namespace design settings. The `namespace-tokens.css`
stylesheet exposes the following marketing tokens for each namespace:

* `--marketing-primary` – defaults to the namespace `brand.primary`.
* `--marketing-accent` – defaults to the namespace `brand.accent`.
* `--marketing-link` – defaults to the namespace `brand.primary`.
* `--marketing-surface` – defaults to the shared surface token (`--surface-card`).

## Marketing stylesheet entry point

Page-Editor Marketing-Seiten binden ausschließlich `public/css/marketing.css` ein. Die Datei ist
der alleinige Style-Entry für diese Marketing-Pages und soll ohne Abhängigkeit zu `landing.css`
gepflegt werden.

## Managing marketing tokens

1. Open the **Page Design** editor for the marketing namespace you want to adjust.
2. Update the brand colors in the **Namespace Design** panel.
3. Save the namespace. The marketing tokens above are regenerated automatically for the namespace.

If you need a different marketing color scheme per namespace, adjust the brand colors there. The
marketing stylesheet consumes only the `--marketing-*` tokens to keep the scope predictable.
