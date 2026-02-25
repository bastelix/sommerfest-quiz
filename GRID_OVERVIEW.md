# CSS Grid Migration – Ist-Stand & Fahrplan

> Erstellt: 2026-02-25 | Branch: `claude/review-grid-migration-qTpXu`

## 1. Ist-Stand

Die Codebase verwendet aktuell **110 Grid-** und **315 Flexbox-Deklarationen** (26 % Grid).

| Datei | Grid | Flex | Grid-Anteil | Status |
|---|---:|---:|---:|---|
| `admin-design.css` | 17 | 6 | 74 % | **Weitgehend migriert** |
| `marketing.css` | 46 | 148 | 24 % | Gemischt – größte Datei |
| `main.css` | 37 | 88 | 30 % | Gemischt |
| `sections.css` | 4 | 18 | 18 % | Überwiegend Flex |
| `footer-blocks.css` | 4 | 5 | 44 % | Nahezu ausgeglichen |
| `marketing-cards.css` | 1 | 13 | 7 % | Überwiegend Flex |
| `menu-cards.css` | 1 | 5 | 17 % | Überwiegend Flex |
| `topbar.css` | 0 | 8 | 0 % | Nur Flex |
| `topbar.marketing.css` | 0 | 7 | 0 % | Nur Flex |
| `table.css` | 0 | 5 | 0 % | Nur Flex |
| `card-row.css` | 0 | 4 | 0 % | Nur Flex |
| `default-theme.css` | 0 | 3 | 0 % | Nur Flex |
| `onboarding.css` | 0 | 1 | 0 % | Nur Flex |
| `dark.css` | 0 | 1 | 0 % | Nur Flex |
| `highcontrast.css` | 0 | 1 | 0 % | Nur Flex |
| `href-suggest.css` | 0 | 1 | 0 % | Nur Flex |
| **Gesamt** | **110** | **315** | **26 %** | |

---

## 2. Entscheidungsrahmen: Flex behalten vs. Grid migrieren

### Wann Flex BLEIBT (→ kein Handlungsbedarf)

| Muster | Beispiel-Selektoren | Grund |
|---|---|---|
| `inline-flex` Icon-/Badge-Centering | `.git-btn`, `.icon-picker__icon-btn`, `.question-timer` | Einachsig, inhaltsgetrieben, Grid bringt keinen Vorteil |
| Horizontale Toolbar/Navbar-Items | `.topbar`, `.uk-navbar-right`, `.card-row__actions` | Items fließen inline, `flex-shrink`/`margin-left: auto` sind Flex-Stärken |
| Sticky-Footer-Shell | `.wrapper { flex: 1 }` | Klassisches Flex-Pattern mit `min-height: 100vh` |
| Viewport-Height Centering | `.section--viewport-height > .uk-container` | `flex: 1; justify-content: center` für vertikale Zentrierung in Hero-Sections |
| Input + Button inline | `.footer-newsletter-form .uk-inline` | Zwei Items, eines mit `flex: 1` |
| Offcanvas/Dropdown-Scroll-Spalten | `#qr-offcanvas .uk-offcanvas-bar` | Scroll-Container mit overflow-y |

### Wann auf Grid MIGRIEREN

| Muster | Erkennung | Umstellung |
|---|---|---|
| Vertikaler Stack mit `gap` | `flex-direction: column; gap: X` ohne `flex: 1` auf Kindern | `display: grid; gap: X` – mechanisch, risikoarm |
| Responsive Wrapping-Grid | `flex-wrap: wrap` mit fester Item-Breite | `display: grid; grid-template-columns: repeat(auto-fill, minmax(…, 1fr))` |
| Card-Grid mit Breakpoints | Verschiedene `flex-direction` je Media Query | Einheitliches `grid-template-columns` mit Breakpoints |
| Komplexe Layouts mit `flex: 1` | `flex: 1` für proportionale Aufteilung | `grid-template-columns: 1fr 2fr` oder `grid-template-rows: 1fr auto` |

---

## 3. Detailanalyse nach Datei

### 3.1 `main.css` (37 Grid, 88 Flex)

#### MIGRIEREN (36 Selektoren)

**Priorität 1 – Rein mechanisch** (`flex-direction: column; gap` → `display: grid; gap`)

| Selektor | Zeile | Hinweis |
|---|---:|---|
| `.qr-sidebar-nav` | 439 | Inline-Stil, vertikaler Stack |
| `.stacked-upload` | 719 | Column-Stack für Upload-Felder |
| `.page-editor [data-editor-root="true"]` | 1257 | Editor-Scaffold |
| `.page-editor .content-editor-body` | 1265 | Editor-Body |
| `.page-editor [data-block-list="true"]` | 1280 | Block-Liste |
| `.section-heading` | 1310 | Title + Meta Stack |
| `.collection-list` | 1596 | Item-Liste |
| `.dashboard-tile > .uk-card` | 1764 | Dashboard-Karten |
| `.container-metrics` | 1788 | Metriken-Stack |
| `.dashboard-leader` | 1841 | Leaderboard-Stack |
| `.dashboard-leader__summary` | 1849 | Summary-Stack |
| `.player-ranking-card` | 2071 | Ranking-Karte |
| `.player-ranking-card__actions` | 2129 | Action-Stack |
| `.player-ranking-name-actions` | 2157 | Name + Actions |
| `.player-toplist-card` | 2210 | Topliste |
| `.player-toplist-card__list` | 2231 | Listeneinträge |
| `.menu-tree` | 2269 | Menübaum |
| `.menu-tree__branch` | 2366 | Zweig-Container |
| `.layout-style-picker` | 2379 | Style-Auswahl |
| `.section-background-config` | 2392 | Hintergrund-Config |
| `.color-token-chip__text` | 2456 | Chip-Text-Stack |
| `.background-image-fields` | 2473 | Bild-Felder |
| `.layout-style-card` | 2494 | Karten-Preview |
| `.stat-strip__card` | 3135 | Stat-Karte |
| `.stat-strip__meta` | 3152 | Meta-Stack |
| `.pricing-plan-card` | 3511 | Preiskarte |
| `.question-type-option` | 3737 | Fragetyp-Auswahl |

**Priorität 2 – Modifikator-Klassen** (Column-Modifier auf Flex-Basis)

| Selektor | Zeile | Hinweis |
|---|---:|---|
| `.layout-preview--stacked` | 2571 | Modifier setzt column |
| `.layout-preview--list` | 2582 | Modifier setzt column |
| `.layout-preview--steps-vertical` | 2604 | Modifier setzt column |
| `.page-editor-preview-layout [data-block-list]` | 2762 | Preview-Blockleiste |
| `.page-editor-preview-layout` | 2787 | Preview-Layout |

**Priorität 3 – Responsive/Bedingt**

| Selektor | Zeile | Hinweis |
|---|---:|---|
| `.container-metrics__row` | 1794 | Column mobile → Row desktop |
| `@media ≤640px .player-ranking-card__header` | 2142 | Column nur bei Mobile |
| `@media ≥960px .media-refresh-container` | 2040 | Column erst ab Tablet |
| `.wrapper` | 59 | **Sonderfall**: `flex: 1` auf `.content` → Alternative: `grid-template-rows: auto 1fr auto` |

#### BEIBEHALTEN (57 Selektoren)

Alle horizontalen Toolbars, Navbars, Icon-Centering-Regeln, Button-Gruppen und Inline-Layouts.
Betrifft u.a.: `.topbar`, `.uk-navbar-*`, `.drag-handle`, `.logo-frame`, `.js-upload`, `.onboarding-timeline`, `.flip-card-front/back`, `.block-form-section__title`, `.swipe-card`, `.media-*`, `.icon-picker__*`, `.page-preview-*`, `.news-editor-*`.

---

### 3.2 `marketing.css` (46 Grid, 148 Flex)

Die größte Datei mit ~8900 Zeilen. **82 KEEP, 53 MIGRATE** (davon 37 vertikale Stacks, 9 Wrapping-Rows, 7 responsive Axis-Switches).

#### MIGRIEREN – Vertikale Stacks (37 Selektoren, Prio 1)

| Selektor | Zeile | Hinweis |
|---|---:|---|
| `.marketing-chat__dialog` | 541 | Dialog-Stack |
| `.marketing-chat__context-list` | 665 | Kontext-Liste |
| `.marketing-cookie-banner` | 779 | Banner-Stack |
| `.hero-media-card` | 1115 | Video-Card |
| `.hero-media-card__meta-actions` | 1176 | Meta-Stack (responsive) |
| `.hero-media-card__consent` | 1229 | Consent-Overlay |
| `.metcal-hero__bullets` | 1934 | Bullet-Liste |
| `.metcal-report-card` | 2093 | Report-Karte |
| `.metcal-package` | 2309 | Paket-Karte |
| `.metcal-sticky-cta__copy` | 2466 | CTA-Text-Stack |
| `.fig-hero__stat-label` | 3086 | Stat-Label |
| `.fig-trust` | 3147 | Trust-Section |
| `.fig-case-card` | 3395 | Case-Karte |
| `.fig-technology-card` | 3452 | Technologie-Karte |
| `.fig-technology-nav__button` | 3518 | Nav-Button |
| `.fig-technology-panel` | 3569 | Panel-Stack |
| `.fig-pricing-card` | 3657 | Preis-Karte |
| `.calserver-hero-video` | 4365 | Hero-Video |
| `.calserver-hero-video__embed` | 4401 | Embed-Overlay |
| `.calserver-highlight-card` | 5023 | Highlight-Karte |
| `.calserver-stats-strip__item` | 5193 | Stat-Item |
| `.calserver-stats-strip__title-group` | 5207 | Title-Stack |
| `.calserver-pricing-card` | 5330 | Preis-Karte |
| `#trust .trust-story` (calserver) | 5380 | Story-Stack (axis-switch) |
| `#trust .trust-story__marker` (calserver) | 5406 | Marker-Stack |
| `.feature-card` (calserver) | 5828 | Feature-Karte |
| `.calserver-modules-nav` | 5876 | Nav-Liste |
| `.calserver-modules-nav__link` | 5888 | Nav-Button |
| `.calserver-module-figure` | 5993 | Figure-Stack |
| `.calserver-module-figure figcaption` | 6096 | Caption-Stack |
| Duplikate calhelp-theme (Zeilen 6780–8891) | — | 16× identische Patterns für calhelp |

#### MIGRIEREN – Wrapping-Rows (9 Selektoren, Prio 2)

| Selektor | Zeile | Hinweis |
|---|---:|---|
| `.marketing-chat__form-actions` | 718 | `flex-wrap` → `grid auto-fill` |
| `.calserver-maintenance-hero__actions` | 1413 | CTA-Wrap |
| `.metcal-hero__actions` | 1953 | CTA-Wrap |
| `.metcal-sticky-cta__actions` | 2476 | CTA-Wrap |
| `.fig-hero__actions` | 2988 | CTA-Wrap |
| `.fig-pill-list` | 3624 | Tag-Cloud |
| `.calserver-metcal__cta` | 6313 | CTA-Wrap |

#### BEIBEHALTEN (82 Selektoren)

Alle `inline-flex` Buttons/Badges/Tags, Navbar-Items, Marquee-Tracks, Logo-Centering, horizontale Icon+Text Rows, Scroll-Karusselle.

---

### 3.3 `sections.css` (4 Grid, 18 Flex)

#### MIGRIEREN (3 Selektoren)

| Selektor | Zeile | Hinweis |
|---|---:|---|
| `.trust-band__list` | 710 | Wrapping Logo-Row → `grid + auto-fill` |
| `.page-modules__grid` | 1109 | Vertikaler Modul-Stack |
| `.info-media-switcher` | 1652 | Wird ab 960px ohnehin grid – von Anfang an grid machen |

#### BEIBEHALTEN (15 Selektoren)

Viewport-Height-Centering, Hero-Eyebrow-Tags, Trust-Band-Items, Stat-Strip-Varianten, CTA-Gruppen.

---

### 3.4 `marketing-cards.css` (1 Grid, 13 Flex)

#### MIGRIEREN (3 Selektoren)

| Selektor | Zeile | Hinweis |
|---|---:|---|
| `.module-card` | 248 | Card-Content-Stack |
| `.news-card` | 466 | Card-Content-Stack |
| `.news-kpis` | 600 | Wrapping Badge-Row → `grid + auto-fill` |

#### BEIBEHALTEN (10 Selektoren)

Card-Header-Rows, Meta-Alignment, `inline-flex` Links/Badges.

---

### 3.5 `footer-blocks.css` (4 Grid, 5 Flex)

#### MIGRIEREN (1 Selektor)

| Selektor | Zeile | Hinweis |
|---|---:|---|
| `.cms-footer__blocks[data-layout="centered"]` | 74 | → `grid; justify-items: center` |

#### BEIBEHALTEN (4 Selektoren)

Social-Links-Row, Contact-Item-Row, Newsletter-Form-Inline, Social-Link Icon.

---

### 3.6 `table.css` (0 Grid, 5 Flex)

#### MIGRIEREN (1 Selektor)

| Selektor | Zeile | Hinweis |
|---|---:|---|
| `.qr-card-content` | 60 | Vertikaler Card-Stack |

#### BEIBEHALTEN (4 Selektoren)

Handle-Centering, Action-Buttons, Dropdown-Row, Page-Tree-Actions.

---

### 3.7 `onboarding.css` (0 Grid, 1 Flex)

#### MIGRIEREN (1 Selektor)

| Selektor | Zeile | Hinweis |
|---|---:|---|
| `.pricing-grid .uk-card-quizrace` | 67 | Card-Stack mit `margin-top: auto` → `grid-template-rows: 1fr auto` |

---

### 3.8 Dateien ohne Migrationsbedarf

| Datei | Flex | Grund |
|---|---:|---|
| `topbar.css` | 8 | Alles Navbar/Button-Alignment |
| `topbar.marketing.css` | 7 | Alles Navbar/Button-Alignment |
| `card-row.css` | 4 | Summary-Row, Drag-Handle, Actions |
| `menu-cards.css` | 5 | Edit-Toggles, Toolbar, Button-Gruppen |
| `default-theme.css` | 3 | Star-Row, Avatar, Logo |
| `dark.css` | 1 | Handle-Centering Override |
| `highcontrast.css` | 1 | Handle-Centering Override |
| `href-suggest.css` | 1 | Option-Row |

---

## 4. Migrations-Fahrplan

### Phase 1: Risikoarme vertikale Stacks (Prio 1)

**Scope**: ~55 Selektoren über `main.css`, `marketing-cards.css`, `sections.css`, `table.css`, `onboarding.css`
**Muster**: `display: flex; flex-direction: column; gap: X` → `display: grid; gap: X`
**Risiko**: Minimal – identisches visuelles Ergebnis
**Aufwand**: ~2h

Checkliste:
- [ ] `main.css`: 27 Selektoren (Prio 1 Liste oben)
- [ ] `marketing-cards.css`: `.module-card`, `.news-card`
- [ ] `sections.css`: `.page-modules__grid`
- [ ] `table.css`: `.qr-card-content`
- [ ] `onboarding.css`: `.pricing-grid .uk-card-quizrace`

**Test**: Visueller Vergleich aller Admin-Seiten und Spieler-Ansichten.

---

### Phase 2: Modifikator-Klassen und Responsive-Column (Prio 2)

**Scope**: ~15 Selektoren in `main.css` und `sections.css`
**Muster**: Column-Modifier auf gemeinsamer Flex-Basis, responsive column↔row Wechsel
**Risiko**: Mittel – Basis-Selektor muss ggf. angepasst werden
**Aufwand**: ~2h

Checkliste:
- [ ] `main.css`: Layout-Preview-Modifikatoren (`.layout-preview--stacked`, `--list`, `--steps-vertical`)
- [ ] `main.css`: Responsive-Wechsel (`.container-metrics__row`, `.player-ranking-card__header`)
- [ ] `sections.css`: `.trust-band__list` → `grid + auto-fill`, `.info-media-switcher` → einheitlich grid

**Test**: Alle Breakpoints prüfen (Mobile, Tablet, Desktop).

---

### Phase 3: `marketing.css` Card-Stacks (37 Selektoren)

**Scope**: 37 vertikale Stacks (`flex-direction: column; gap` → `display: grid; gap`)
**Muster**: Identisch zu Phase 1, aber in der größten/komplexesten Datei
**Risiko**: Mittel – marketing.css hat viele Media-Query-Duplikate und calhelp/calserver Varianten
**Aufwand**: ~3h

Checkliste:
- [ ] Chat/Cookie-Banner: `.marketing-chat__dialog`, `__context-list`, `.marketing-cookie-banner`
- [ ] Hero-Media: `.hero-media-card`, `__meta-actions`, `__consent`
- [ ] Metcal-Theme: `.metcal-hero__bullets`, `.metcal-report-card`, `.metcal-package`, `__sticky-cta__copy`
- [ ] Future-is-Green: `.fig-*` Cards (7 Selektoren)
- [ ] calserver-Theme: `.calserver-*` Cards + Nav (11 Selektoren)
- [ ] calhelp-Theme Duplikate (16 Selektoren – identische Patterns)
- [ ] Dark-Mode und High-Contrast prüfen

**Test**: Alle Marketing-/Landing-Pages in allen Viewports und Themes prüfen.

---

### Phase 4: Responsive Wraps → Grid (12 Selektoren)

**Scope**: 9 Selektoren in `marketing.css` + 3 in `marketing-cards.css` / `footer-blocks.css`
**Muster**: `flex-wrap: wrap` mit `gap` → `grid-template-columns: repeat(auto-fill, …)`
**Risiko**: Mittel-Hoch – Wrap-Verhalten ändert sich subtil (Grid füllt Zeilen gleichmäßig)
**Aufwand**: ~2h

Checkliste:
- [ ] `marketing.css`: CTA-Gruppen (`.calserver-maintenance-hero__actions`, `.metcal-hero__actions`, `.fig-hero__actions`, etc.)
- [ ] `marketing.css`: Tag-Cloud (`.fig-pill-list`)
- [ ] `marketing-cards.css`: `.news-kpis`
- [ ] `footer-blocks.css`: `.cms-footer__blocks[data-layout="centered"]`

**Test**: Visual Regression auf Marketing-Pages empfohlen.

---

### Phase 5: Komplexe Layouts (7 Selektoren)

**Scope**: Layouts mit `flex: 1`, axis-switching, `margin-top: auto`
**Risiko**: Hoch – semantisch unterschiedliches Verhalten
**Aufwand**: ~2h, aber mehr Testaufwand

Checkliste:
- [ ] `.wrapper` → `grid-template-rows: auto 1fr auto` (optional, funktioniert gut als flex)
- [ ] `#trust .trust-story` (2× calserver/calhelp) → responsive axis-switch column↔row
- [ ] `.container-metrics__row`, `.player-ranking-card__header` (responsive column↔row)
- [ ] Cards mit `margin-top: auto` → `grid-template-rows: 1fr auto`

**Test**: Intensiver manueller Test aller betroffenen Seiten.

---

## 5. Zusammenfassung

| | Flex BEIBEHALTEN | Flex → GRID |
|---|---:|---:|
| `main.css` | 57 | 36 |
| `marketing.css` | 82 | 53 |
| Restliche 16 Dateien | 63 | 9 |
| **Gesamt** | **202** | **98** |

Nach vollständiger Migration:
- **Grid**: 110 + 98 = **208 Stellen** (51 %)
- **Flex**: 315 − 98 = **217 Stellen** (49 %)
- **Verhältnis**: Nahezu ausgeglichen – jedes Tool dort wo es semantisch passt

### Empfohlener nächster Schritt

**Phase 1 starten**: Die risikoarmen vertikalen Stacks in `main.css` (27×), `marketing-cards.css` (2×), `sections.css` (1×), `table.css` (1×) und `onboarding.css` (1×) umstellen. Dafür einen eigenen Branch `refactor/grid-phase-1-vertical-stacks` erstellen.
