# Namespace-Design

## Überblick

In diesem Projekt bezeichnet **Namespace** kein PHP-Namespace, sondern ein **Multi-Tenant-Konzept**. Jeder Namespace steht für eine eigenständige Marke bzw. Mandanten-Instanz mit eigenem visuellen Erscheinungsbild.

Das Design eines Namespace wirkt auf **zwei Ebenen**:

- **Quiz-Frontend** -- die Spieleroberfläche, die Endnutzer sehen
- **CMS / Admin-Backend** -- die Redaktions- und Verwaltungsoberfläche

Beide Bereiche beziehen ihre Gestaltung aus denselben Design-Tokens und CSS-Custom-Properties. Dadurch ist sichergestellt, dass Quiz und CMS stets konsistent zur jeweiligen Marke gestaltet sind.

Ein Namespace-Name besteht aus Kleinbuchstaben, Ziffern und Bindestrichen (z.B. `calserver-neu`, `future-is-green`). Die Validierung erfolgt über `NamespaceValidator` (`src/Service/NamespaceValidator.php`):

| Regel | Wert |
|-------|------|
| Pattern | `^[a-z0-9][a-z0-9-]*$` |
| Max. Länge | 100 Zeichen |

---

## Namespace-Auflösung (Request-Lifecycle)

Jeder HTTP-Request durchläuft eine Middleware-Kette, die den aktiven Namespace ermittelt:

```
Request
  │
  ▼
NamespaceQueryMiddleware          ?namespace=<wert> aus Query-Parameter
  │
  ▼
MarketingNamespaceMiddleware      Namespace aus Marketing-URL-Slugs (/m/, /landing/)
  │
  ▼
NamespaceResolver.resolve()       Zusammenführung aller Kandidaten
  │
  ▼
NamespaceContext                  Immutables Value Object mit Ergebnis
```

### Auflösungspriorität

Der `NamespaceResolver` (`src/Service/NamespaceResolver.php`) prüft Kandidaten in dieser Reihenfolge:

1. **Expliziter Parameter** -- `legalPageNamespace`, `pageNamespace` oder `namespace` Request-Attribut
2. **Domain-basiert** -- `domainNamespace`-Attribut oder Lookup über `DomainService`
3. Wenn kein Kandidat gefunden wird, wirft der Resolver eine `RuntimeException`

Das Ergebnis ist ein `NamespaceContext` (`src/Service/NamespaceContext.php`) -- ein immutables Value Object mit:

- `namespace` -- der aufgelöste Namespace
- `candidates` -- alle gesammelten Kandidaten (für Fallback-Logik)
- `host` -- der normalisierte Hostname
- `usedFallback` -- ob ein Fallback-Namespace verwendet wurde

### Beteiligte Dateien

- `src/Application/Middleware/NamespaceQueryMiddleware.php`
- `src/Application/Middleware/MarketingNamespaceMiddleware.php`
- `src/Service/NamespaceResolver.php`
- `src/Service/NamespaceContext.php`

---

## Design-Token-System

Jeder Namespace kann über **Design-Tokens** individuell gestaltet werden. Die Tokens sind in vier Gruppen organisiert:

| Gruppe | Tokens | Beispielwerte |
|--------|--------|---------------|
| `brand` | `primary`, `accent`, `secondary` | `#1e87f0`, `#f97316` |
| `layout` | `profile` | `narrow`, `standard`, `wide` |
| `typography` | `preset` | `modern`, `classic`, `tech` |
| `components` | `cardStyle`, `buttonStyle` | `rounded`/`square`/`pill`, `filled`/`outline`/`ghost` |

### Vererbungskette

Tokens werden in einer dreistufigen Kaskade aufgelöst:

```
DEFAULT_TOKENS (Hardcoded-Fallback)
  │
  ▼
Default-Namespace (DB oder content/design/uikit-default.json)
  │
  ▼
Namespace-spezifisch (DB oder content/design/<namespace>.json)
```

Ein Namespace erbt alle Werte vom Default-Namespace und überschreibt nur die Tokens, die er explizit definiert. Die Methode `DesignTokenService::getTokensForNamespace()` (`src/Service/DesignTokenService.php:122`) implementiert diese Logik.

### Design-Presets

Vordefinierte Design-Presets liegen als JSON-Dateien unter `content/design/`:

```
content/design/
├── aurora.json
├── calhelp.json
├── calserver-neu.json
├── future-is-green.json
├── midnight.json
├── monochrome.json
├── sunset.json
└── uikit-default.json         (Default-Preset)
```

Jede Datei enthält:

```json
{
  "meta": { "name": "...", "description": "...", "version": "2.0.0" },
  "config": {
    "designTokens": { "brand": {...}, "layout": {...}, ... },
    "colors": { "surface": "#fff", "textOnPrimary": "#000", ... }
  },
  "designTokens": { ... },
  "effects": { "effectsProfile": "...", "sliderProfile": "..." }
}
```

Presets können über `DesignTokenService::importDesign()` in einen Namespace importiert werden. Dabei werden sowohl die Tokens als auch die Farbkonfiguration und Effekte übernommen.

### Beteiligte Dateien

- `src/Service/DesignTokenService.php`
- `src/Service/NamespaceDesignFileRepository.php`

---

## CSS-Generierung und Wirkung

### Automatische Stylesheet-Erzeugung

Beim Speichern von Design-Tokens wird `DesignTokenService::rebuildStylesheet()` aufgerufen. Dieser Vorgang erzeugt:

1. **Globales Stylesheet** -- `public/css/namespace-tokens.css`
   - Enthält `:root`-Block mit Default-Tokens
   - Enthält einen `[data-namespace="..."]`-Block pro Namespace
2. **Namespace-spezifische Stylesheets** -- `public/css/<namespace>/namespace-tokens.css`
   - Enthält nur den Block des jeweiligen Namespace

### CSS-Custom-Properties

Die Tokens werden als CSS-Custom-Properties (Variablen) ausgegeben:

```css
/* Default-Namespace auf :root */
:root {
  --brand-primary: #1e87f0;
  --brand-accent: #f97316;
  --brand-secondary: #f97316;
  --layout-profile: standard;
  --typography-preset: modern;
  --components-card-style: rounded;
  --components-button-style: filled;
  /* ... */
}

/* Namespace-spezifischer Override */
[data-namespace="aurora"] {
  --brand-primary: #6366f1;
  --brand-accent: #ec4899;
  /* nur überschriebene Werte, Rest erbt von :root */
}
```

### Kontrast-Tokens

Für jede Markenfarbe werden automatisch Kontrast-Tokens berechnet (`ColorContrastService`), die barrierefreie Textfarben garantieren:

- `--contrast-text-on-primary`
- `--contrast-text-on-secondary`
- `--contrast-text-on-accent`
- `--contrast-text-on-surface`

---

## Template-Integration (Quiz-Frontend und CMS)

### Render-Context

Controller nutzen `NamespaceRenderContextService::build()` (`src/Service/NamespaceRenderContextService.php`), um den vollständigen Design-Kontext für Twig-Templates aufzubauen:

```php
$context = $renderContextService->build($namespace);
// Ergebnis:
// [
//     'namespace'              => 'aurora',
//     'designActiveNamespace'  => 'aurora',
//     'design' => [
//         'config'     => [...],
//         'tokens'     => ['brand' => [...], 'layout' => [...], ...],
//         'appearance' => ['colors' => [...], 'variables' => [...]],
//         'layout'     => ['profile' => 'standard'],
//         'theme'      => 'light',
//     ],
// ]
```

### HTML-Attribute

Im Template wird der Namespace als `data-namespace`-Attribut auf das `<html>`-Element gesetzt:

```html
<html data-namespace="aurora" data-theme="light">
```

Die CSS-Selektoren `[data-namespace="aurora"]` aktivieren automatisch die passenden Design-Tokens. Dadurch wirkt der Namespace **sowohl im Quiz-Frontend als auch im CMS** -- überall dort, wo das HTML-Element dieses Attribut trägt.

### Appearance-Service

`NamespaceAppearanceService` (`src/Service/NamespaceAppearanceService.php`) löst zusätzliche Darstellungs-Details auf: Oberflächen-, Hintergrund- und Textfarben, Topbar-Farben und Marketing-Schema. Diese Werte fließen als CSS-Variablen oder Template-Kontext in die Darstellung ein.

---

## Zugriffssteuerung

Der Zugriff auf Namespaces ist rollenbasiert über `NamespaceAccessService` (`src/Service/NamespaceAccessService.php`) gesteuert:

- **Admins** sehen und verwalten alle Namespaces
- **Andere Rollen** (Designer, Redakteur, Analyst etc.) sehen nur die ihnen zugewiesenen Namespaces

Die verfügbaren Rollen sind in `src/Domain/Roles.php` definiert (u.a. `admin`, `designer`, `redakteur`, `catalog-editor`, `event-manager`, `analyst`).

---

## Datenfluss-Diagramm

```
HTTP-Request
    │
    ▼
┌─────────────────────────────┐
│  NamespaceQueryMiddleware   │  ?namespace=aurora
│  MarketingNamespaceMiddleware│  /m/aurora/...
└─────────────┬───────────────┘
              │
              ▼
┌─────────────────────────────┐
│     NamespaceResolver       │  Priorisierung der Kandidaten
└─────────────┬───────────────┘
              │
              ▼
┌─────────────────────────────┐
│     NamespaceContext        │  Immutables Value Object
└─────────────┬───────────────┘
              │
              ▼
┌─────────────────────────────┐
│       Controller            │  Nutzt Namespace für Datenzugriff
└─────────────┬───────────────┘
              │
              ▼
┌─────────────────────────────┐
│ NamespaceRenderContextService│  Baut Design-Kontext auf
│ NamespaceAppearanceService  │  Löst Farben & Variablen auf
│ DesignTokenService          │  Liefert Token-Kaskade
└─────────────┬───────────────┘
              │
              ▼
┌─────────────────────────────┐
│      Twig-Template          │  <html data-namespace="aurora">
└─────────────┬───────────────┘
              │
              ▼
┌─────────────────────────────┐
│   CSS-Custom-Properties     │  [data-namespace="aurora"] { ... }
│   (namespace-tokens.css)    │  Farben, Layout, Typografie, Komponenten
└─────────────────────────────┘
```

---

## Bewertung & Verbesserungsvorschläge

### Kontext

Das System dient dazu, **schnell Webseiten mit festen Grundthemes zu erstellen**, die später **nach und nach minimal individualisiert** werden. Die folgende Bewertung prüft, ob die Architektur dafür passt und wo sich pragmatische Verbesserungen lohnen.

### Was gut funktioniert

| Aspekt | Warum es passt | Empfehlung |
|--------|----------------|------------|
| **Kleine Token-Palette (9 Tokens, 4 Gruppen)** | Verhindert, dass Nutzer Themes versehentlich kaputt machen. Die Enum-Werte (`narrow`/`standard`/`wide`, `modern`/`classic`/`tech` etc.) sind bewusste Leitplanken. | Beibehalten, nicht erweitern. |
| **Zweistufige Vererbung (Default -> Namespace)** | Einfach, vorhersagbar, ausreichend. Ein Namespace ueberschreibt nur, was abweichen soll, der Rest erbt automatisch. | Keine Namespace-zu-Namespace-Vererbung noetig. |
| **Automatische WCAG-Kontrast-Tokens** | Nicht-Designer koennen Brandfarben aendern, ohne unlesbaren Text zu erzeugen. Wird bei jeder CSS-Generierung automatisch berechnet (`ColorContrastService`). | Starkes Feature, beibehalten. |
| **Preset-Import als Einmalkopie** | Namespace wird nach Import autonom. Passt zu "Theme als Startpunkt, dann individualisieren." | Korrekt, keine live-verknuepften Presets. |
| **CSS mit `[data-namespace]`-Selektoren** | Globale Datei als Fallback + namespace-spezifische Datei. Sauber geloest. | Beibehalten. |

### Sinnvolle Verbesserungen (priorisiert)

#### 1. Quell-Preset im Namespace speichern

- **Problem**: Nach einem Preset-Import ist nicht mehr nachvollziehbar, welches Preset verwendet wurde.
- **Loesung**: `sourcePreset`-Key in `config.design_tokens` speichern bei `importDesign()`.
- **Warum**: Admins sehen "basiert auf Aurora"; ermoeglicht spaeter ein "Re-Import"-Feature.
- **Aufwand**: Klein -- 1 Key in JSON, 1 Zeile in `importDesign()`, 1 Zeile im Template.
- **Datei**: `src/Service/DesignTokenService.php:232`

#### 2. Verwaiste CSS-Dateien beim Loeschen aufraeumen

- **Problem**: `NamespaceService::delete()` entfernt DB-Eintraege, aber `public/css/<namespace>/` bleibt liegen.
- **Loesung**: `cleanupNamespaceCss()`-Methode in `DesignTokenService`, aufgerufen aus `delete()`. Danach `rebuildStylesheet()`.
- **Aufwand**: Klein.
- **Dateien**: `src/Service/NamespaceService.php:169`, `src/Service/DesignTokenService.php`

#### 3. Redundante Tokens in Preset-JSON bereinigen

- **Problem**: Jede Preset-Datei enthaelt Tokens doppelt (`config.designTokens` UND `designTokens` top-level). Bei Abweichung gewinnt `config.designTokens` still.
- **Loesung**: Top-level `designTokens` aus allen 8 Preset-Dateien entfernen. `NamespaceDesignFileRepository::loadTokens()` liest primaer aus `config.designTokens`.
- **Aufwand**: Klein.
- **Dateien**: `content/design/*.json`, `src/Service/NamespaceDesignFileRepository.php:71`

#### 4. CSS-Duplikation `:root` / `[data-namespace="default"]` entfernen

- **Problem**: `buildCss()` gibt die Default-Tokens identisch zweimal aus -- einmal auf `:root`, einmal auf `[data-namespace="default"]`.
- **Loesung**: `[data-namespace="default"]`-Block entfernen, `:root` reicht durch CSS-Cascade.
- **Aufwand**: Minimal -- 1 Zeile in `buildCss()`.
- **Datei**: `src/Service/DesignTokenService.php:531`

#### 5. CamelCase/snake_case-Inkonsistenz bei Farb-Keys normalisieren

- **Problem**: `NamespaceAppearanceService` prueft fuer jeden Wert zwei Schreibweisen (`topbar_light` / `topbarLight`, `text_on_surface` / `textOnSurface`). Daten koennen unter beiden Keys gespeichert sein.
- **Loesung**: Migration auf camelCase-only in der `config.colors`-Spalte. `pickColor()`-Fallbacks eine Version lang beibehalten, dann entfernen.
- **Aufwand**: Mittel (inkl. Migration).
- **Dateien**: `src/Service/NamespaceAppearanceService.php:61-70`, `src/Service/ConfigService.php:88-102`

### Nett, aber nicht kritisch

#### 6. Marketing-Scheme -> Token-Kopplung transparent machen

Beim Scheme-Wechsel werden `layout`, `typography`, `cardStyle`, `buttonStyle` still mit Scheme-Defaults ueberschrieben. Eine Flash-Message im Admin ("Preset 'sunset' hat Typografie auf 'classic' zurueckgesetzt") wuerde das sichtbar machen.

- **Datei**: `src/Controller/Admin/PagesDesignController.php:220-247`

#### 7. Validierungs-Feedback statt stiller Drops

`validateTokens()` verwirft unbekannte Gruppen/Keys ohne Rueckmeldung. Ein Warnings-Array mit Ausgabe im Admin-Flash wuerde die Fehlersuche vereinfachen.

- **Datei**: `src/Service/DesignTokenService.php:427`

### Nicht bauen (Over-Engineering fuer diesen Use-Case)

| Idee | Warum nicht |
|------|-------------|
| **Namespace-zu-Namespace-Vererbung** | Erzeugt zirkulaere Abhaengigkeiten, kaskadierte Breakage, schwer nachvollziehbar. Preset-Import deckt den Bedarf ab. |
| **Dynamisches Token-Registry** | Wuerde die bewusste 9-Token-Begrenzung aushebeln. Neue Tokens gehoeren als Code-Aenderung rein, mit Validierung. |
| **Live-verknuepfte Presets** | Unkontrolliertes Update ueber alle Namespaces. Ein "Re-Import"-Button (nach Punkt 1) ist die kontrollierte Alternative. |
| **Config-Tabelle in Einzelspalten** | JSON-Spalte ist fuer seltene Admin-Writes voellig ausreichend. Kein Concurrent-Edit-Szenario. |
