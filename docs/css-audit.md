# CSS-Audit: Stylesheet-Konflikte und Legacy-Einbindungen

> **Stand:** 2026-02-17
> **Methode:** Statische Codeanalyse aller Templates, CSS-Dateien und PHP-Services
> **Scope:** Gesamtes sommerfest-quiz Projekt

---

## Inhaltsverzeichnis

1. [Fundstellen-Tabelle](#1-fundstellen-tabelle)
2. [Template-Kette](#2-template-kette)
3. [Lade-Reihenfolge pro Seitentyp](#3-lade-reihenfolge-pro-seitentyp)
4. [Hardcodierte Werte](#4-hardcodierte-werte)
5. [Konflikte](#5-konflikte)
6. [Lücken](#6-lücken)
7. [Bereinigungsplan](#7-bereinigungsplan)

---

## 1. Fundstellen-Tabelle

### 1.1 Zentrale CSS-Einbindung — `templates/layout.twig`

Das Base-Layout bindet Stylesheets **bedingt** ein. Jedes Child-Template kann die Flags per `{% set %}` überschreiben.

| Zeile | Stylesheet | Bedingung | Kat. |
|-------|-----------|-----------|------|
| 141 | `uikit.min.css` | `includeUikitStyles` (default: `true`) | ⚠️ LEGACY |
| 144 | `variables.css` | `includeVariablesStyles` (default: `true`) | ✅ DESIGN |
| 149 | `dark.css` | `shouldIncludeDarkStyles` (`includeMainStyles` ∧ `includeVariablesStyles` ∧ `includeDarkStyles`) | ✅ DESIGN (Hybrid) |
| 157–165 | `namespace-tokens.css` (global oder `/<namespace>/namespace-tokens.css`) | `includeNamespaceTokensStyles` (default: `true`) | ✅ DESIGN |
| 172 | `table.css` | `includeTableStyles` (default: `true`) | ✅ DESIGN |
| 175 | `topbar.css` | `includeTopbarStyles` (default: `true`), nicht bei `marketing-page` | ✅ DESIGN |
| 178 | Poppins Webfont (preload) | nur bei `marketing-page` | ⚠️ EXTERN |
| 188 | `theme-vars.twig` (inline `<style>`) | `shouldIncludeMarketingThemeVars` | ✅ DESIGN |

### 1.2 Quiz-Frontend-Templates

| Datei | Zeile | Eingebundenes CSS | Kat. |
|-------|-------|------------------|------|
| `templates/index.twig` | 7 | `main.css` | ✅ DESIGN (Hybrid) |
| | 9 | `dark.css` | ✅ DESIGN |
| | 10 | `highcontrast.css` | ✅ DESIGN |
| | 11 | `calhelp.css` | ⚠️ LEGACY — Brand-spezifisch, am Token-System vorbei |
| `templates/dashboard.twig` | 11 | `main.css` | ✅ DESIGN (Hybrid) |
| | 12–20 | Inline `<style>`: `--dashboard-accent`, `--dashboard-background-light: #eef2f7`, `--dashboard-card-light: #ffffff`, `--dashboard-background-dark: #050910`, `--dashboard-card-dark: #172132`, `--dashboard-border-dark: rgba(148,163,184,0.24)` | ⚠️ LEGACY — Hardcodierte Werte |
| `templates/help.twig` | — | `main.css`, `dark.css`, `highcontrast.css` | ✅ DESIGN |
| `templates/summary.twig` | — | `main.css`, `dark.css`, `highcontrast.css` | ✅ DESIGN |
| `templates/profile.twig` | — | `main.css`, `dark.css`, `highcontrast.css` | ✅ DESIGN |
| `templates/results-hub.twig` | — | `main.css`, `dark.css`, `highcontrast.css` | ✅ DESIGN |
| `templates/events_overview.twig` | — | `main.css`, `dark.css`, `highcontrast.css` | ✅ DESIGN |
| `templates/event_catalogs.twig` | — | `main.css`, `dark.css`, `highcontrast.css` | ✅ DESIGN |
| `templates/lizenz.twig` | — | `main.css`, `dark.css`, `highcontrast.css` | ✅ DESIGN |
| `templates/password_request.twig` | — | `main.css`, `dark.css`, `highcontrast.css` | ✅ DESIGN |
| `templates/password_confirm.twig` | — | `main.css`, `dark.css`, `highcontrast.css` | ✅ DESIGN |

### 1.3 Marketing-Templates

| Datei | Zeile | Eingebundenes CSS | Kat. |
|-------|-------|------------------|------|
| `templates/marketing/default.twig` | 10–11 | Google Fonts (Poppins, 9 Gewichte) | ⚠️ EXTERN |
| | 12–13 | `marketing.css` (preload + link) | ✅ DESIGN |
| | 14 | `highcontrast.css` | ✅ DESIGN |
| | 15 | `topbar.marketing.css` | ✅ DESIGN |
| `templates/marketing/landing.twig` | 12 | Google Fonts (Poppins, 9 Gewichte) | ⚠️ EXTERN |
| | 13–14 | `marketing.css` (preload + link) | ✅ DESIGN |
| | 15 | `sections.css` | ✅ DESIGN |
| | 16 | `highcontrast.css` | ✅ DESIGN |
| | 17 | `onboarding.css` | ⚠️ LEGACY — nutzt `--color-*` statt `--brand-*` |
| | 18 | `topbar.marketing.css` | ✅ DESIGN |
| `templates/marketing/calserver.twig` | 13–14 | Google Fonts (Poppins, 9 Gewichte) | ⚠️ EXTERN |
| | 15–16 | `marketing.css` (preload + link) | ✅ DESIGN |
| | 17 | `calserver.css` | ⚠️ LEGACY — eigenes `--calserver-*` / `--cs-*` System |
| | 18 | `highcontrast.css` | ✅ DESIGN |
| | 19 | `onboarding.css` | ⚠️ LEGACY |
| | 20 | `topbar.marketing.css` | ✅ DESIGN |
| `templates/marketing/calhelp.twig` | 15 | Google Fonts (Poppins, 5 Gewichte) | ⚠️ EXTERN |
| | 16–17 | `marketing.css` (preload + link) | ✅ DESIGN |
| | 18 | `calserver.css` | ⚠️ LEGACY |
| | 19 | `highcontrast.css` | ✅ DESIGN |
| | 20 | `onboarding.css` | ⚠️ LEGACY |
| | 21 | `topbar.marketing.css` | ✅ DESIGN |
| | 22 | `calhelp.css` | ⚠️ LEGACY — Brand-spezifisch |
| `templates/marketing/calserver-maintenance.twig` | 12–14 | Google Fonts + `marketing.css` (preload) | ✅/⚠️ |
| | 15 | `calserver.css` | ⚠️ LEGACY |
| | 16 | `calserver-maintenance.css` | ⚠️ LEGACY — 49 hardcodierte Hex-Werte |
| | 17 | `highcontrast.css` | ✅ DESIGN |
| | 18 | `onboarding.css` | ⚠️ LEGACY |
| | 19 | `topbar.marketing.css` | ✅ DESIGN |
| `templates/marketing/landing_news_show.twig` | 9–16 | Google Fonts (3 Gewichte) + `marketing.css` + `calserver.css` (conditional) + `highcontrast.css` + `onboarding.css` + `topbar.marketing.css` | ⚠️ Mixed |
| `templates/marketing/calserver-accessibility.twig` | 6–8 | `marketing.css`, `dark.css`, `highcontrast.css` | ✅ DESIGN |
| `templates/marketing/event_upcoming.twig` | 8–9 | `marketing.css` (preload + link) | ✅ DESIGN |
| | 21 | Inline `<style>` | ⚠️ Prüfung nötig |
| `templates/marketing/event_finished.twig` | 8–9 | `marketing.css` (preload + link) | ✅ DESIGN |
| | 21 | Inline `<style>` | ⚠️ Prüfung nötig |

### 1.4 Marketing-Wiki-Templates

| Datei | Zeile | Eingebundenes CSS | Kat. |
|-------|-------|------------------|------|
| `templates/marketing/wiki/index.twig` | 42–44 | `marketing.css` (preload + link), `highcontrast.css` | ✅ DESIGN |
| | 46–49 | Dynamische Stylesheets aus `stylesheets` Array (DB-konfiguriert) | ⚠️ DYNAMISCH — externe URLs oder lokale Pfade |
| | 50–65 | Inline `<style>`: `--marketing-wiki-header-from`, `--marketing-wiki-header-to`, `--marketing-wiki-detail-header-from`, `--marketing-wiki-detail-header-to`, `--marketing-wiki-header-text`, `--marketing-wiki-excerpt`, `--marketing-wiki-callout-border`, `--marketing-wiki-callout-bg`, `--marketing-wiki-callout-text` | ⚠️ LEGACY — Dynamische Werte aus DB, nicht über Token-System |
| `templates/marketing/wiki/show.twig` | 46–53 | Identisch zu `wiki/index.twig` | ⚠️ |
| | 54–65 | Identischer Inline-`<style>`-Block | ⚠️ |

### 1.5 CMS-Templates

| Datei | Zeile | Eingebundenes CSS | Kat. |
|-------|-------|------------------|------|
| `templates/layouts/cms_base.twig` | 17 | `main.css` (conditional: `includeMainStyles`) | ✅ DESIGN (Hybrid) |
| | 20 | `topbar.marketing.css` (conditional) | ✅ DESIGN |
| | 24 | `dark.css` (conditional) | ✅ DESIGN |
| | 27 | `highcontrast.css` (conditional) | ✅ DESIGN |
| | 29 | `footer-blocks.css` | ✅ DESIGN (Hybrid) |
| `templates/pages/render.twig` | — | Setzt `includeMainStyles=false`, `includeVariablesStyles=true`, `includeNamespaceTokensStyles=true`, `includeTableStyles=false`, `includeTopbarStyles=false` | ✅ DESIGN |

### 1.6 Admin-Templates

| Datei | Zeile | Eingebundenes CSS | Kat. |
|-------|-------|------------------|------|
| `templates/admin/base.twig` | 10 | `main.css` | ✅ DESIGN (Hybrid) |
| | 11 | `dark.css` | ✅ DESIGN |
| | 12 | `highcontrast.css` | ✅ DESIGN |
| `templates/admin/pages/design.twig` | 7 | `marketing.css` | ✅ DESIGN |
| | 8 | `admin-design.css` | ⚠️ LEGACY — Fallback-Werte mit hardcodierten Hex |
| `templates/admin/event_config.twig` | — | `admin-config.css` | ⚠️ LEGACY — nutzt `--color-text`, `--qr-card`, `--qr-border` |
| `templates/admin/newsletter.twig` | — | `admin-config.css` | ⚠️ LEGACY |
| `templates/admin/pages/content.twig` | — | `dark.css`, `highcontrast.css`, `uikit.min.css` (preview), `variables.css` (preview), `sections.css` (preview), `namespace-tokens.css` (preview), `table.css` (preview), `topbar.css` (preview), `marketing.css` (preview) | ✅ Mixed (Preview-Assets) |
| `templates/admin/pages/edit.twig` | — | Wie `content.twig` + `marketing-utilities.css` (preview) | ✅ Mixed |
| `templates/admin/navigation/footer-blocks.twig` | 211+ | Inline `<style>`: `background: #f8f9fa`, `border: 2px dashed #dee2e6`, `background: #fff`, `border-bottom: 1px solid #dee2e6` | ⚠️ LEGACY — Hardcodierte Admin-Styles |
| `templates/admin/navigation/_partials/footer_blocks_tab.twig` | 198+ | Inline `<style>` — ähnliche hardcodierte Werte | ⚠️ LEGACY |
| `templates/admin/navigation/menus_index.twig` | — | `menu-cards.css` | ⚠️ LEGACY — 26 hardcodierte Hex-Werte |

### 1.7 Statische HTML-Dateien

| Datei | Zeile | Eingebundenes CSS | Kat. |
|-------|-------|------------------|------|
| `public/Impressum.html` | 7 | `uikit.min.css` | ⚠️ LEGACY |
| | 8 | `main.css` | ✅ DESIGN (Hybrid) |
| | 9 | `dark.css` | ✅ DESIGN |
| `public/Lizenz.html` | 7 | `uikit.min.css` | ⚠️ LEGACY |
| | 8 | `main.css` | ✅ DESIGN (Hybrid) |
| | 9 | `dark.css` | ✅ DESIGN |
| `nginx-errors/50x.html` | 7–29 | Inline `<style>` — Standalone Error Page | ⚠️ AKZEPTABEL (Error Page braucht keine externe CSS) |

### 1.8 PHP-generierte CSS

| Datei | Zeile | Funktion | Kat. |
|-------|-------|---------|------|
| `src/Service/DesignTokenService.php` | 288–305 | `rebuildStylesheet()` → generiert `namespace-tokens.css` (global + pro Namespace) | ✅ DESIGN |
| | 524–545 | `buildCss()` → `:root` Block + `[data-namespace]` Overrides | ✅ DESIGN |
| | 551–597 | `renderTokenCssBlock()` → `--brand-primary`, `--brand-accent`, `--marketing-*`, `--contrast-*`, `--layout-*`, `--typography-*`, `--components-*` | ✅ DESIGN |
| | 643–661 | `mirrorCssToNamespacePaths()` → `/public/css/<namespace>/namespace-tokens.css` | ✅ DESIGN |

### 1.9 JavaScript-gesteuerte CSS-Ladung

| Datei | Beschreibung | Kat. |
|-------|-------------|------|
| `public/js/marketing-design.js` | Lädt als `<script type="module">`, steuert dynamische Marketing-Theme-Injection und CSS-Variable-Anwendung | ✅ DESIGN |
| `public/js/wiki-admin.js` | Verwaltet `data-wiki-theme-stylesheets` Attribut, serialisiert/deserialisiert Stylesheet-Daten | ⚠️ DYNAMISCH |

### 1.10 Externe Abhängigkeiten

| Quelle | Geladen in | URL |
|--------|-----------|-----|
| Google Fonts (Poppins) | `marketing/default.twig`, `marketing/landing.twig`, `marketing/calserver.twig`, `marketing/calserver-maintenance.twig` | `https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap` |
| Google Fonts (Poppins, reduziert) | `marketing/calhelp.twig` | `https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap` |
| Google Fonts (Poppins, minimal) | `marketing/landing_news_show.twig` | `https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap` |
| Wiki-Theme-Stylesheets | `marketing/wiki/index.twig`, `marketing/wiki/show.twig` | Dynamisch aus DB — beliebige URLs oder lokale Pfade |

---

## 2. Template-Kette

### Rendering-Pfade mit CSS-Ladepunkten

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ layout.twig                                                                 │
│ ├─ uikit.min.css ⚠️         (includeUikitStyles)                           │
│ ├─ variables.css ✅          (includeVariablesStyles)                       │
│ ├─ dark.css ✅               (shouldIncludeDarkStyles)                      │
│ ├─ namespace-tokens.css ✅   (includeNamespaceTokensStyles, nicht bei mktg) │
│ ├─ table.css ✅              (includeTableStyles)                           │
│ ├─ topbar.css ✅             (includeTopbarStyles, nicht bei mktg)          │
│ ├─ [Poppins font preload]    (nur bei marketing-page)                       │
│ └─ theme-vars.twig ✅        (shouldIncludeMarketingThemeVars)              │
│     ├── namespace-tokens.css ✅ (bei mktg: nach head_end)                   │
│                                                                              │
│ ┌── A) Quiz-Frontend ────────────────────────────────────────────┐          │
│ │ index.twig / dashboard.twig / help.twig / summary.twig ...    │          │
│ │ └─ main.css ✅, dark.css ✅, highcontrast.css ✅              │          │
│ │   └─ calhelp.css ⚠️ (nur index.twig!)                        │          │
│ │   └─ dashboard.twig: inline <style> ⚠️                       │          │
│ └────────────────────────────────────────────────────────────────┘          │
│                                                                              │
│ ┌── B) Marketing ────────────────────────────────────────────────┐          │
│ │ marketing/default.twig | landing.twig | calserver.twig ...    │          │
│ │ └─ Google Fonts ⚠️, marketing.css ✅, highcontrast.css ✅    │          │
│ │   └─ topbar.marketing.css ✅                                  │          │
│ │   └─ calserver.css ⚠️ (calserver/calhelp)                    │          │
│ │   └─ calhelp.css ⚠️ (calhelp)                                │          │
│ │   └─ calserver-maintenance.css ⚠️ (maintenance)              │          │
│ │   └─ onboarding.css ⚠️ (landing/calserver/calhelp)           │          │
│ │   └─ sections.css ✅ (landing)                                │          │
│ └────────────────────────────────────────────────────────────────┘          │
│                                                                              │
│ ┌── C) CMS-Pages ───────────────────────────────────────────────┐          │
│ │ layouts/cms_base.twig → pages/render.twig                     │          │
│ │ └─ [main.css] (optional), topbar.marketing.css ✅             │          │
│ │   └─ dark.css ✅, highcontrast.css ✅, footer-blocks.css ✅  │          │
│ │   render.twig setzt: includeMainStyles=false                  │          │
│ └────────────────────────────────────────────────────────────────┘          │
│                                                                              │
│ ┌── D) Admin ────────────────────────────────────────────────────┐          │
│ │ admin/base.twig → admin/*.twig                                │          │
│ │ └─ main.css ✅, dark.css ✅, highcontrast.css ✅              │          │
│ │   └─ admin-design.css ⚠️ (pages/design.twig)                 │          │
│ │   └─ admin-config.css ⚠️ (event_config.twig)                 │          │
│ │   └─ menu-cards.css ⚠️ (menus_index.twig)                    │          │
│ │   └─ footer-blocks.css + inline <style> ⚠️                   │          │
│ └────────────────────────────────────────────────────────────────┘          │
│                                                                              │
│ ┌── E) Brand-Seiten ────────────────────────────────────────────┐          │
│ │ = Marketing-Pfad + seitenspezifisches Brand-CSS               │          │
│ │ └─ calserver.css ⚠️ (eigenes --calserver-*/--cs-* System)    │          │
│ │ └─ calhelp.css ⚠️ (teilt calserver-Selektoren via :is())     │          │
│ │ └─ calserver-maintenance.css ⚠️ (komplett eigenständig)      │          │
│ │ └─ future-is-green.css ⚠️ (eigenes --fig-* System)           │          │
│ │ └─ fluke-metcal.css ⚠️ (eigenes --metcal-* System)           │          │
│ └────────────────────────────────────────────────────────────────┘          │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Lade-Reihenfolge pro Seitentyp

### 3.1 Quiz-Frontend (`index.twig`)

```
1. uikit.min.css              ⚠️ LEGACY — UIkit Framework
2. variables.css               ✅ Semantische Design-Tokens
3. dark.css                    ✅ Dark-Theme (aus layout.twig)
4. namespace-tokens.css        ✅ Auto-generierte Namespace-Tokens
5. table.css                   ✅ Token-basiert
6. topbar.css                  ✅ Token-basiert
   ── head Block (index.twig) ──
7. main.css                    ✅ Hybrid (var() mit Fallbacks)
8. dark.css                    ✅ Duplikat-Einbindung (index.twig)
9. highcontrast.css            ✅ Accessibility
10. calhelp.css                ⚠️ LEGACY — Brand-spezifisch, nicht Token-basiert
   ── head_end Block ──
11. theme-vars.twig (inline)   ✅ Dynamische Token-Injection
```

**Konflikte:** `calhelp.css` (Pos. 10) kann Marketing-Tokens in `theme-vars.twig` (Pos. 11) überschreiben, da es eigene Variablen definiert. UIkit (Pos. 1) definiert Basis-Styles die durch `main.css` (Pos. 7) und `variables.css` (Pos. 2) überschrieben werden müssen.

### 3.2 Marketing-Default (`marketing/default.twig`)

```
1. uikit.min.css              ⚠️ LEGACY
2. variables.css               ✅
   ── head Block (default.twig) ──
3. Google Fonts (Poppins)      ⚠️ EXTERN
4. marketing.css (preload)     (Performance-Hint)
5. marketing.css               ✅ Marketing-Tokens
6. highcontrast.css            ✅
7. topbar.marketing.css        ✅
   ── head_end Block ──
8. namespace-tokens.css        ✅ (nach head bei marketing-page)
9. theme-vars.twig (inline)    ✅ Dynamische Token-Injection
```

**Hinweis:** Bei Marketing-Pages wird `namespace-tokens.css` bewusst NACH den Marketing-Stylesheets geladen (Zeile 182–184 in layout.twig), damit die Namespace-Tokens die Marketing-Defaults korrekt überschreiben.

### 3.3 CalServer-Marketing (`marketing/calserver.twig`)

```
1. uikit.min.css              ⚠️ LEGACY
2. variables.css               ✅
   ── head Block (calserver.twig) ──
3. Google Fonts (Poppins)      ⚠️ EXTERN
4. marketing.css (preload)     (Performance-Hint)
5. marketing.css               ✅
6. calserver.css               ⚠️ LEGACY — eigenes --calserver-*/--cs-* System
7. highcontrast.css            ✅
8. onboarding.css              ⚠️ LEGACY — nutzt --color-* statt --brand-*
9. topbar.marketing.css        ✅
   ── head_end Block ──
10. namespace-tokens.css       ✅
11. theme-vars.twig (inline)   ✅
```

**Konflikte:** `calserver.css` (Pos. 6) definiert `--calserver-primary: var(--qr-landing-primary)` — ein eigenes Variablen-System das parallel zum Namespace-Token-System existiert. Die CalServer-Hero-Backgrounds, Logo-Styles und Section-Layouts sind komplett in `calserver.css` hardcodiert und werden nicht durch Namespace-Tokens gesteuert.

### 3.4 CMS-Page (`pages/render.twig`)

```
1. uikit.min.css              ⚠️ LEGACY
2. variables.css               ✅
3. namespace-tokens.css        ✅ (nicht-marketing, also normal in head)
   ── head Block (cms_base.twig) ──
4. topbar.marketing.css        ✅ (conditional)
5. footer-blocks.css           ✅ Hybrid (var() mit Fallbacks)
   ── head Block (render.twig) ──
6. dark.css                    ✅
7. highcontrast.css            ✅
8. sections.css                ✅ Token-basiert
9. marketing-utilities.css     ⚠️ LEGACY — dupliziert variables.css Tokens
10. marketing.css (preload)    (Performance-Hint)
11. marketing.css              ✅
12. marketing-cards.css        ✅ Token-basiert
13. topbar.marketing.css       ✅
   ── head_end Block ──
14. theme-vars.twig (inline)   ✅
```

**Hinweis:** `render.twig` setzt `includeMainStyles=false` — das CMS-Frontend lädt bewusst NICHT `main.css`, um Konflikte mit dem Marketing-Design zu vermeiden. `marketing-utilities.css` (Pos. 9) dupliziert Token-Definitionen aus `variables.css` als Fallback.

### 3.5 Admin-Base (`admin/base.twig`)

```
1. uikit.min.css              ⚠️ LEGACY
2. variables.css               ✅
3. dark.css                    ✅ (aus layout.twig)
4. namespace-tokens.css        ✅
5. table.css                   ✅
6. topbar.css                  ✅
   ── head Block (admin/base.twig) ──
7. main.css                    ✅ Hybrid
8. dark.css                    ✅
9. highcontrast.css            ✅
   ── head_end Block ──
10. theme-vars.twig (inline)   ✅
```

### 3.6 Admin-Design (`admin/pages/design.twig`)

```
1–9. (wie Admin-Base)
   ── admin_head Block ──
10. marketing.css              ✅
11. admin-design.css           ⚠️ LEGACY — Fallback-Hex-Werte in var()
```

### 3.7 Dashboard (`templates/dashboard.twig`)

```
1–6. (wie Quiz-Frontend aus layout.twig)
   ── head Block ──
7. main.css                    ✅ Hybrid
8. Inline <style>              ⚠️ LEGACY — --dashboard-* Tokens mit hardcodierten Hex-Werten
   ── head_end Block ──
9. theme-vars.twig (inline)    ✅
```

**Konflikt:** Der Inline-`<style>`-Block (Pos. 8) definiert `--dashboard-background-light: #eef2f7`, `--dashboard-card-light: #ffffff` etc. — diese Werte sollten stattdessen aus dem Token-System (`--surface-page`, `--surface-card`) bezogen werden.

---

## 4. Hardcodierte Werte

### 4.1 Übersicht pro CSS-Datei

| Datei | Token-Definitionen | Akzeptable Fallbacks | Standalone Hardcodiert | rgba/rgb Standalone |
|-------|-------------------|---------------------|----------------------|-------------------|
| `main.css` | 0 | ~88 | ~24 | ~37 |
| `dark.css` | 6 | ~33 | ~43 | ~10 |
| `highcontrast.css` | 0 | 0 | ~118 | 0 |
| `marketing.css` | ~35 | ~36 | ~6 | ~19 |
| `onboarding.css` | 0 | 0 | ~3 | 0 |
| `calserver.css` | 0 | 0 | 0 | 0 |
| `calhelp.css` | 0 | 0 | 0 | 0 |
| `calhelp-about.css` | 0 | 0 | 3 | 1 |
| `calserver-maintenance.css` | 0 | 0 | ~49 | ~12 |
| `future-is-green.css` | ~14 | 0 | ~4 | ~36 |
| `fluke-metcal.css` | 3 | 0 | ~22 | ~18 |
| `sections.css` | 0 | ~39 | 0 | 0 |
| `table.css` | 0 | 0 | 0 | 0 |
| `topbar.css` | 7 | 7 | 8 | 2 |
| `topbar.marketing.css` | 0 | ~24 | 0 | 0 |
| `admin-design.css` | 0 | ~23 | ~5 | ~10 |
| `admin-config.css` | 0 | 1 | 1 | 0 |
| `footer-blocks.css` | 0 | ~17 | ~3 | 0 |
| `menu-cards.css` | 0 | 0 | ~26 | ~3 |
| `marketing-cards.css` | 0 | 0 | 0 | 0 |
| `marketing-utilities.css` | 8 | 0 | 0 | 0 |

**Legende:**
- **Token-Definitionen:** `--my-var: #hex` — Definieren CSS Custom Properties (akzeptabel wenn Teil des Token-Systems)
- **Akzeptable Fallbacks:** `var(--token, #hex)` — Fallback-Werte in `var()` Funktionen
- **Standalone Hardcodiert:** `color: #hex` oder `background: #hex` — Direkte Farbwerte ohne Token-Referenz
- **rgba/rgb Standalone:** `rgba(...)` oder `rgb(...)` ohne Token-Referenz

### 4.2 Kritische Einzelfunde

#### `fluke-metcal.css` — Hardcodierte Backgrounds die Dark-Mode brechen

| Zeile | Selektor | Eigenschaft | Wert | Sollte sein |
|-------|---------|------------|------|-------------|
| 237 | `.metcal-report-card` | `background` | `#ffffff` | `var(--surface-card)` |
| 272 | `.metcal-card` | `background` | `#ffffff` | `var(--surface-card)` |
| 315 | `.metcal-timeline__item` | `background` | `rgba(255,255,255,0.96)` | `var(--surface-card)` |
| 405 | `.metcal-feature` | `background` | `rgba(255,255,255,0.96)` | `var(--surface-card)` |
| 438 | `.metcal-security` | `background` | `#ffffff` | `var(--surface-card)` |
| 453 | `.metcal-package` | `background` | `#ffffff` | `var(--surface-card)` |
| 489 | `.metcal-faq__item` | `background` | `#ffffff` | `var(--surface-card)` |
| 118 | `.metcal-hero__actions .uk-button-primary:hover` | `color` | `#fff` | `var(--text-on-primary)` |
| 133–134 | `.metcal-hero__actions .uk-button-default:hover` | `background` / `color` | `#fff` / `#0d1730` | Token-Referenz |
| 155 | `.metcal-hero__highlight-title` | `color` | `#fff` | `var(--text-heading)` |

#### `calserver-maintenance.css` — Komplett eigenständiges Farbschema

| Zeile | Eigenschaft | Wert | Sollte sein |
|-------|-----------|------|-------------|
| 3 | `background` (body) | `linear-gradient(180deg, #0f172a, #111827, #0b1220)` | Token-basierter Gradient |
| 4 | `color` (body) | `#f8fafc` | `var(--text-body)` |
| 36 | `.uk-navbar-nav > li > a` | `color: #cbd5f5` | `var(--text-muted)` |
| 42 | hover color | `#f8fafc` | `var(--text-body)` |

#### `menu-cards.css` — Admin-Styles komplett am Token-System vorbei

| Zeile | Selektor | Eigenschaft | Wert | Sollte sein |
|-------|---------|------------|------|-------------|
| 13 | `.menu-card` | `background` | `#fff` | `var(--surface-card)` |
| 14 | `.menu-card` | `border-color` | `#dee2e6` | `var(--border-muted)` |
| 20 | `.menu-card:hover` | `border-color` | `#1e87f0` | `var(--brand-primary)` |
| 30 | `.menu-card--editing` | `border-color` | `#1e87f0` | `var(--brand-primary)` |
| 34 | `.menu-card--drag-over` | `border-color` | `#32d296` | Token-Referenz |
| 50 | `.menu-card__drag` | `color` | `#ced4da` | `var(--text-muted)` |

#### `dashboard.twig` Inline `<style>` — Eigenes Farbsystem

| Zeile | Token | Wert | Sollte sein |
|-------|-------|------|-------------|
| 14 | `--dashboard-accent` | `{{ config.buttonColor\|default('#1e87f0') }}` | `var(--brand-primary)` |
| 15 | `--dashboard-background-light` | `#eef2f7` | `var(--surface-page)` |
| 16 | `--dashboard-card-light` | `#ffffff` | `var(--surface-card)` |
| 17 | `--dashboard-background-dark` | `#050910` | `var(--surface-page)` (dark) |
| 18 | `--dashboard-card-dark` | `#172132` | `var(--surface-card)` (dark) |

#### `future-is-green.css` — Eigenes `--fig-*` Token-System

| Zeile | Token | Hardcodierter Wert | Token-Äquivalent |
|-------|-------|-------------------|-----------------|
| 2 | `--fig-primary` | `#138f52` | → `--brand-primary` |
| 3 | `--fig-primary-dark` | `#0c6f3f` | (kein direktes Äquivalent) |
| 4 | `--fig-secondary` | `#ffffff` | → `--brand-secondary` |
| 5 | `--fig-background` | `#f0f9f3` | → `--surface-page` |
| 6 | `--fig-surface` | `rgba(255,255,255,0.96)` | → `--surface-card` |
| 11 | `--fig-text` | `#123524` | → `--text-body` |
| 12 | `--fig-muted` | `#3f5a4b` | → `--text-muted` |
| 38–47 | `--qr-bg`, `--qr-card`, `--qr-fg` etc. | (mapped from `--fig-*`) | Bereits Token-Aliase |

### 4.3 Inline `<style>` Blöcke mit hardcodierten Werten

| Template | Zeile | Hardcodierte Werte |
|----------|-------|-------------------|
| `dashboard.twig` | 14–19 | `#eef2f7`, `#ffffff`, `#050910`, `#172132`, `rgba(148,163,184,0.24)` |
| `admin/navigation/footer-blocks.twig` | 211+ | `#f8f9fa`, `#dee2e6`, `#fff`, `#dee2e6` |
| `admin/navigation/_partials/footer_blocks_tab.twig` | 198+ | Ähnliche Admin-Editor-Farben |
| `marketing/wiki/index.twig` | 50–65 | Dynamisch aus DB — Farben nicht über Token-System |
| `marketing/wiki/show.twig` | 54–65 | Identisch |

---

## 5. Konflikte

### 5.1 UIkit vs. Namespace-Tokens (Hauptkonflikt)

**Problem:** `uikit.min.css` ist ein vollständiges CSS-Framework (~279 KB) das komplett am Token-System vorbeiarbeitet. Es definiert hunderte Regeln mit hardcodierten Farben, Spacings und Layout-Werten.

**Spezifitäts-Kaskade:**
```
uikit.min.css (Pos. 1)    →  .uk-button-primary { background: #1e87f0 }
variables.css (Pos. 2)      →  :root { --brand-primary: #1e87f0 }
namespace-tokens.css (Pos. 4) → [data-namespace="aurora"] { --brand-primary: #1f6feb }
main.css (Pos. 7)           →  .uk-button-primary { background: var(--brand-primary) }
```

**Konsequenz:** `main.css` muss UIkit-Regeln explizit mit gleicher oder höherer Spezifität überschreiben. Wo main.css eine UIkit-Klasse NICHT überschreibt, gewinnt der hardcodierte UIkit-Wert — und das Namespace-Design setzt sich NICHT durch.

**Betroffene Bereiche:**
- `.uk-button-primary`, `.uk-button-default`, `.uk-button-secondary`
- `.uk-card`, `.uk-card-default`, `.uk-card-primary`
- `.uk-navbar-container`, `.uk-navbar`
- `.uk-background-muted`, `.uk-background-default`
- `.uk-text-*`, `.uk-heading-*`
- `.uk-form-*` Styles

### 5.2 Parallele Brand-Token-Systeme

Drei CSS-Dateien definieren eigene, vom Namespace-System unabhängige Token-Hierarchien:

| Datei | System | Anzahl Variablen | Nutzt `--brand-*`? | Nutzt `--surface-*`? |
|-------|--------|-----------------|--------------------|--------------------|
| `future-is-green.css` | `--fig-*` | 30+ (light) + 30+ (dark) | Nein | Nein |
| `fluke-metcal.css` | `--metcal-*` | 3 (light) + 3 (dark) | Nein | Nein |
| `calserver.css` | `--calserver-*`, `--cs-*` | 5+ | Indirekt via `--qr-landing-primary` | Nein |

**Konsequenz:** Wenn der Namespace in der Admin-UI geändert wird (z.B. `--brand-primary` wird rot), ändert sich auf CalServer/CalHelp/Future-is-Green-Seiten **nichts** — diese nutzen ihre eigenen Variablen. Das Namespace-Design-System hat dort keine Wirkung.

### 5.3 Dark-Mode-Inkompatibilität

| Datei | Problem |
|-------|---------|
| `fluke-metcal.css` | `background: #ffffff` auf 7+ Selektoren → bleiben im Dark-Mode weiß. Nur teilweise durch `body[data-theme='dark']` Selektoren korrigiert (Zeilen 517–574). |
| `calserver-maintenance.css` | Komplett eigenständiges dunkles Farbschema (`#0f172a`, `#111827`). Reagiert NICHT auf `data-theme` Wechsel. |
| `calhelp-about.css` | `background: linear-gradient(135deg, #e6f2ff, #f8fbff)` → bleibt im Dark-Mode hell. |

### 5.4 Inline-Styles vs. externe Sheets

| Template | Inline-Style | Überschreibt |
|----------|-------------|-------------|
| `dashboard.twig` | `--dashboard-background-light: #eef2f7` | Umgeht `--surface-page` aus variables.css |
| `footer-blocks.twig` | `background: #f8f9fa` | Ignoriert Token-System komplett |
| `wiki/index.twig` | `--marketing-wiki-header-from: {{ colors.headerFrom }}` | Setzt Werte dynamisch aus DB, nicht über Design-Presets |

### 5.5 `marketing-utilities.css` dupliziert `variables.css`

`marketing-utilities.css` definiert identische Token-Werte wie `variables.css`:

```css
/* marketing-utilities.css */
:root {
  --surface-page: #f7f9fb;      /* Duplikat aus variables.css */
  --surface-section: #ffffff;    /* Duplikat */
  --surface-card: #ffffff;       /* Duplikat */
  --surface-muted: #eef2f7;     /* Duplikat */
  --text-body: #111827;          /* Duplikat */
  --text-muted: #4b5563;         /* Duplikat */
  --text-on-primary: #ffffff;    /* Duplikat */
}
```

**Problem:** Auf CMS-Pages (render.twig) wird `marketing-utilities.css` geladen aber `variables.css` ist dort ebenfalls vorhanden (über layout.twig). Die doppelte Definition ist redundant und könnte bei Änderungen zu Inkonsistenzen führen.

---

## 6. Lücken

### 6.1 Was dem Namespace-System fehlt wenn Legacy wegfällt

| Bereich | Aktuell abgedeckt durch | Namespace-Token-Äquivalent vorhanden? |
|---------|------------------------|--------------------------------------|
| Responsive Grid (`uk-grid`, `uk-width-*`, `uk-container`) | `uikit.min.css` | **Nein** — kein Token-Ersatz |
| Komponenten (Navbar, Offcanvas, Accordion, Modal, Tab, Dropdown) | `uikit.min.css` | **Nein** — keine Token-Alternative |
| Utility Classes (`uk-margin-*`, `uk-padding-*`, `uk-text-*`, `uk-flex-*`) | `uikit.min.css` | **Nein** |
| CalServer Hero, Logo, Sections | `calserver.css` | **Teilweise** — Marketing-Tokens existieren, aber CalServer-Layout nutzt sie nicht |
| CalHelp-spezifische Overrides | `calhelp.css` | **Nein** — teilt CalServer-Selektoren via `:is()`, aber eigenständige Datei |
| Future-is-Green Theming | `future-is-green.css` | **Nein** — eigenes `--fig-*` System |
| Fluke-Metcal Layout | `fluke-metcal.css` | **Nein** — eigenes `--metcal-*` System + hardcodierte Werte |
| Maintenance-Page Design | `calserver-maintenance.css` | **Nein** — komplett eigenständig |
| Dashboard-Farben | Inline `<style>` in `dashboard.twig` | **Ja** — `--surface-page`, `--surface-card` existieren, werden aber nicht genutzt |
| Onboarding-Flow Styling | `onboarding.css` | **Teilweise** — nutzt `--color-*` Legacy-Tokens statt `--brand-*`/`--surface-*` |
| Admin-Editor Styling (Footer-Blocks, Menu-Cards) | Inline `<style>` + `menu-cards.css` | **Ja** — Token existieren, werden aber nicht genutzt |
| Wiki-Theme Farben | Inline `<style>` in Wiki-Templates | **Nein** — dynamisch aus DB, nicht über Design-Presets |

### 6.2 Visuell funktionierende Bereiche NUR durch Legacy-CSS

1. **Jedes UIkit-Layout** — Ohne `uikit.min.css` bricht das gesamte Grid-System, die Navbar, Offcanvas-Navigation, alle Accordion/Tab-Komponenten und sämtliche Utility-Classes
2. **CalServer/CalHelp Marketing-Seiten** — Hero-Backgrounds, Logo-Layouts, Section-Designs funktionieren NUR weil `calserver.css`/`calhelp.css` eigene Variablen und Layout-Regeln mitbringen
3. **Future-is-Green Landing** — Komplett eigene Farbpalette, Topbar-Design und Dark-Mode über `--fig-*` System
4. **Fluke-Metcal Landing** — Hero, Report-Cards, Timeline, FAQ, Packages — alle Layout-Komponenten sind in `fluke-metcal.css` definiert
5. **CalServer-Maintenance** — Gesamtes Design (Hero, Cards, Timeline, CTA) komplett in `calserver-maintenance.css`
6. **Admin Footer-Block-Editor** — Layout funktioniert nur durch hardcodierte Inline-Styles
7. **Admin Menu-Card-Editor** — Card-Styling funktioniert nur durch hardcodierte Werte in `menu-cards.css`

### 6.3 Bereiche die bereits korrekt Token-basiert sind

- `marketing-cards.css` — Nutzt ausschließlich `var(--marketing-*)` und `var(--qr-*)` Tokens, **0 hardcodierte Werte**
- `sections.css` — Nutzt ausschließlich `var(--section-*)`, `var(--surface-*)`, `var(--color-text)` Tokens
- `topbar.marketing.css` — Nutzt ausschließlich `var(--marketing-*)`, `var(--topbar-*)` Tokens
- `table.css` — Nutzt ausschließlich `var(--accent-color)`, `var(--qr-*)` Tokens
- `calserver.css` / `calhelp.css` — Nutzen `var()` Referenzen, keine hardcodierten Farben (aber eigenes Variablen-System)

---

## 7. Bereinigungsplan

### Leitprinzip

> **Alle seitenindividuellen CSS-Dateien (calserver, calhelp, future-is-green, fluke-metcal etc.) sollen entfernt werden.** Die Namespace-Themes sollen das jeweilige Referenzdesign so weit wie möglich über das Token-System abbilden — keine separaten Brand-CSS-Dateien mehr.

### Phase 1 — Brand-CSS eliminieren (Namespace-Themes als alleinige Quelle)

**Priorität: HOCH** — Diese Dateien untergraben das Namespace-Design-System fundamental.

#### 1. `calserver.css` → Namespace-Theme "calserver"

- **Was:** Alle `--calserver-primary`, `--cs-*` Variablen und Layout-Overrides in das Design-Preset JSON (`content/design/calserver.json`) und die generierten namespace-tokens übernehmen.
- **Migration:** Seitenspezifisches Layout-CSS (Hero-Backgrounds, Logo-Styles, Section-Layouts) in die gemeinsamen `marketing.css`/`sections.css` Komponenten migrieren — diese sind bereits via Token parametrierbar.
- **Aktion:** `calserver.css` entfernen, `<link>` aus `calserver.twig` (Zeile 17) entfernen.
- **Token-Mapping:** `--calserver-primary` → `--brand-primary`, `--cs-logo-text` → `var(--marketing-text-on-surface)`, `--cs-hero-overlay-*` → Marketing-Hero-Tokens.

#### 2. `calhelp.css` → Namespace-Theme "calhelp"

- **Was:** CalHelp teilt bereits CalServer-Selektoren via `:is(.calserver-layout, .calhelp-theme)`. CalHelp-spezifische Overrides in das `content/design/calhelp.json` Design-Preset migrieren.
- **Aktion:** `calhelp.css` entfernen, `<link>` aus `calhelp.twig` (Zeile 22) und `index.twig` (Zeile 11) entfernen.

#### 3. `calhelp-about.css` → entfernen

- **Was:** 3 hardcodierte Hex-Werte (`#1e87f0`, `#e6f2ff`, `#f8fbff`).
- **Migration:** `border-left: 4px solid #1e87f0` → `var(--brand-primary)`. `background: linear-gradient(135deg, #e6f2ff, #f8fbff)` → Token-basierte Variante. Rules in `marketing.css` oder eine shared Komponente verschieben.
- **Aktion:** `calhelp-about.css` entfernen.

#### 4. `calserver-maintenance.css` → Namespace-Theme + shared Styles

- **Was:** 49 hardcodierte Hex-Werte (`#0f172a`, `#111827`, `#f8fafc`, `#cbd5f5` etc.).
- **Migration:** Maintenance-Layout als generische Komponente in `sections.css`/`marketing.css` aufnehmen. Alle Farben aus Namespace-Tokens beziehen. Hero-Gradient, Card-Styles und CTA-Button über `var(--brand-primary)`, `var(--surface-card)`, `var(--text-body)` steuern.
- **Aktion:** `calserver-maintenance.css` entfernen, `<link>` aus `calserver-maintenance.twig` (Zeile 16) entfernen.

#### 5. `future-is-green.css` → Namespace-Theme "future-is-green"

- **Was:** Komplettes `--fig-*` System mit 30+ Variablen (Light) + 30+ (Dark) + High-Contrast-Overrides.
- **Migration:** Token-Mapping in `content/design/future-is-green.json`:
  - `--fig-primary: #138f52` → `--brand-primary`
  - `--fig-background: #f0f9f3` → `--surface-page`
  - `--fig-surface: rgba(255,255,255,0.96)` → `--surface-card`
  - `--fig-text: #123524` → `--text-body`
  - `--fig-muted: #3f5a4b` → `--text-muted`
- Die `--qr-*` Overrides (Zeilen 38–47, 85–92) als Token-Aliase in den generierten namespace-tokens.css behalten.
- Layout-spezifisches CSS (Topbar, Hero, Cards) in shared Komponenten migrieren.
- **Aktion:** `future-is-green.css` entfernen.

#### 6. `fluke-metcal.css` → Namespace-Theme + shared Styles

- **Was:** `--metcal-*` System (3 Token-Defs + 22 standalone hardcodierte Backgrounds/Colors) + Layout-Komponenten (Hero, Cards, Timeline, FAQ, Packages, Sticky-CTA).
- **Migration:**
  - `--metcal-text-strong` → `--text-heading`
  - `--metcal-text` → `--text-body`
  - `--metcal-text-muted` → `--text-muted`
  - 22× `background: #ffffff` → `var(--surface-card)`
  - Layout-Komponenten (Hero, Report-Grid, Card-Grid, Timeline, Feature-Grid, Security-Grid, Packages, FAQ, Sticky-CTA) als generische Marketing-Komponenten in `marketing.css` aufnehmen.
- **Aktion:** `fluke-metcal.css` entfernen.

### Phase 2 — Inline-Styles und Admin-CSS bereinigen

**Priorität: MITTEL** — Hardcodierte Werte die Token-Äquivalente haben.

| Nr. | Datei | Aktion |
|-----|-------|--------|
| 7 | `templates/dashboard.twig` inline `<style>` | `--dashboard-*` auf `--surface-*`/`--brand-*` umstellen: `--dashboard-background-light` → `var(--surface-page)`, `--dashboard-card-light` → `var(--surface-card)` etc. |
| 8 | `templates/admin/navigation/footer-blocks.twig` inline `<style>` | `#f8f9fa` → `var(--surface-muted)`, `#dee2e6` → `var(--border-muted)`, `#fff` → `var(--surface-card)` |
| 9 | `templates/admin/navigation/_partials/footer_blocks_tab.twig` inline `<style>` | Analog zu footer-blocks.twig |
| 10 | `public/css/menu-cards.css` | 26 hardcodierte Hex-Werte ersetzen: `#fff` → `var(--surface-card)`, `#dee2e6` → `var(--border-muted)`, `#1e87f0` → `var(--brand-primary)`, `#ced4da` → `var(--text-muted)` etc. |
| 11 | `public/css/onboarding.css` | Von `--color-text`/`--color-primary`/`--color-bg` auf `--text-body`/`--brand-primary`/`--surface-page` migrieren |

### Phase 3 — Dark-Mode-Konsistenz

**Priorität: MITTEL** — Verbessert Theme-Wechsel-Konsistenz.

| Nr. | Datei | Aktion |
|-----|-------|--------|
| 12 | `public/css/dark.css` | Hardcodierte `#f5f5f5` (Zeilen 26, 29, 33, 40) durch `var(--text-body)` aus variables.css ersetzen |
| 13 | `public/css/highcontrast.css` | 118 hardcodierte Werte sind funktional korrekt (WCAG AA/AAA-Compliance erfordert definierte Kontraste). Prüfen ob Token-Referenzen mit `forced-colors` Media Query möglich sind, aber Accessibility hat Vorrang |

### Phase 4 — Strategische Entscheidungen

**Priorität: NIEDRIG** — Langfristige Architektur-Entscheidungen.

| Nr. | Thema | Empfehlung |
|-----|-------|-----------|
| 14 | `marketing-utilities.css` | Dupliziert Token-Definitionen aus `variables.css`. Konsolidieren: Entweder `marketing-utilities.css` entfernen und sicherstellen dass `variables.css` auf CMS-Pages geladen wird, oder die Datei als expliziten "Fallback-Layer" dokumentieren |
| 15 | `uikit.min.css` | Langfristiger Plan: Schrittweises Entfernen oder Token-Wrapping der verwendeten UIkit-Komponenten. Kurzfristig: `main.css` und `variables.css` überschreiben UIkit-Farben bereits korrekt — UIkit bleibt für Grid/Layout/Komponenten-Funktionalität notwendig |
| 16 | Google Fonts (Poppins) | Integration ins Token-System: `--marketing-font-stack-modern` in `marketing.css` existiert bereits als `"Poppins", "Inter", "Roboto", "Helvetica Neue", Arial, sans-serif`. Font-Loading könnte zentral über Token-System gesteuert werden |
| 17 | Statische HTML (`Impressum.html`, `Lizenz.html`) | In Twig-Templates konvertieren (nutzen `layout.twig`) oder CSS-Referenzen auf Token-basierte Includes aktualisieren |
| 18 | Wiki-Theme-Stylesheets | Dynamische Stylesheet-Arrays aus DB sind ein potenzielles Einfallstor für beliebiges CSS. Langfristig auf Token-basierte Theme-Konfiguration umstellen |

---

## Zusammenfassung

### Statistik

| Kategorie | Anzahl |
|-----------|--------|
| CSS-Dateien gesamt | 38 (inkl. 14 auto-generierte Namespace-Tokens) |
| ✅ Namespace-Design-konform | 13 Dateien |
| ⚠️ Legacy/Fremd | 11 Dateien |
| Inline `<style>` Blöcke | 11 Stellen (5 mit hardcodierten Werten) |
| Externe Abhängigkeiten | 1 (Google Fonts / Poppins) |
| Hardcodierte Standalone-Farbwerte | ~300+ über alle Dateien |
| Brand-CSS mit parallelen Token-Systemen | 3 (`--fig-*`, `--metcal-*`, `--calserver-*`) |
| Zu entfernende Brand-CSS-Dateien | 6 (`calserver.css`, `calhelp.css`, `calhelp-about.css`, `calserver-maintenance.css`, `future-is-green.css`, `fluke-metcal.css`) |

### Kernaussage

Das Namespace-Design-System (`variables.css` + `namespace-tokens.css` + `theme-vars.twig`) ist architektonisch solide und gut durchdacht. Die Hauptprobleme sind:

1. **Brand-CSS-Dateien mit parallelen Token-Systemen** — Diese unterlaufen das Namespace-Design-System komplett. Ihre Design-Definitionen müssen in die Namespace-Theme-Presets migriert werden.
2. **UIkit als unvermeidbare Abhängigkeit** — Kurzfristig nicht ersetzbar, aber `main.css` überschreibt die relevanten Farb-Regeln bereits korrekt via Token.
3. **Inkonsistente Token-Nutzung** — Einige Dateien (`onboarding.css`, `dashboard.twig`) nutzen Legacy-Token-Namen (`--color-*`, `--dashboard-*`) statt der standardisierten `--brand-*`/`--surface-*` Tokens.
