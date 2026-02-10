# Regeln zur Chatbot-gestützten Landing-Page-Generierung

Dieses Dokument beschreibt alle Regeln, die ein Claude-Chat benötigt, um auf Basis einer HTML-Vorschau (UIkit) eine importfähige `.page.json`-Datei zu erzeugen. Der Workflow ist dreistufig:

1. **HTML-Vorschau** erstellen (UIkit-Markup als Entwurf)
2. **Block-JSON** aus dem HTML ableiten (semantische Daten, kein Markup)
3. **`.page.json`-Datei** mit Meta-Daten erzeugen (importfähig)

---

## Teil 1 – HTML-Vorschau (UIkit)

### 1.1 Allgemeine Regeln

- Die HTML-Vorschau dient **ausschließlich zur visuellen Abstimmung** mit dem Nutzer. Sie ist kein Produktions-Artefakt.
- Das HTML wird in den Content-Block von `templates/marketing/default.twig` injiziert – Header und Footer existieren bereits.
- **Kein** `<html>`, `<head>`, `<body>`, `<script>`, `<style>` – nur der innere Content.
- **Nur UIkit-Klassen** verwenden (Präfix `uk-`). Keine eigenen CSS-Klassen.
- Texte: **deutsch**, **nutzenorientiert**, **knapp**.

### 1.2 Erlaubte UIkit-Komponenten

| Kategorie | UIkit-Klassen |
|-----------|---------------|
| Layout | `uk-section`, `uk-section-default`, `uk-section-muted`, `uk-section-primary`, `uk-section-medium`, `uk-section-large`, `uk-container`, `uk-container-large`, `uk-container-expand` |
| Grid | `uk-grid`, `uk-grid-large`, `uk-grid-match`, `uk-child-width-1-2@m`, `uk-child-width-1-3@m`, `uk-child-width-1-4@m` |
| Cards | `uk-card`, `uk-card-default`, `uk-card-body`, `uk-card-title`, `uk-card-hover` |
| Buttons | `uk-button`, `uk-button-primary`, `uk-button-default`, `uk-button-large`, `uk-button-text` |
| Typografie | `uk-heading-medium`, `uk-heading-small`, `uk-text-lead`, `uk-text-meta`, `uk-text-muted`, `uk-text-center` |
| Listen | `uk-list`, `uk-list-bullet`, `uk-list-divider` |
| Accordion | `uk-accordion` (Attribut `uk-accordion`), `uk-accordion-title`, `uk-accordion-content` |
| Slider | `uk-slider` (Attribut `uk-slider`), `uk-slider-items`, `uk-slider-container` |
| Spacing | `uk-margin`, `uk-margin-large`, `uk-margin-medium`, `uk-margin-remove-top` |
| Utilities | `uk-border-rounded`, `uk-overflow-hidden`, `uk-width-*`, `uk-flex`, `uk-flex-center`, `uk-flex-middle` |

### 1.3 Farb-Tokens (CSS-Variablen)

Die Vorschau verwendet die Marketing-Design-Token-Variablen des Namespace:

| Zweck | CSS-Variable |
|-------|-------------|
| Primärfarbe | `--marketing-primary` |
| Akzentfarbe | `--marketing-accent` |
| Linkfarbe | `--marketing-link` |
| Oberfläche (Cards) | `--marketing-surface` |
| Seitenhintergrund | `--marketing-background` |
| Text auf Surface | `--marketing-text-on-surface` |
| Text auf Background | `--marketing-text-on-background` |
| Gedämpfter Text | `--marketing-text-muted-on-surface` |

Die Tokens werden vom Namespace-Design automatisch gesetzt und über `templates/marketing/partials/theme-vars.twig` emittiert. Die HTML-Vorschau muss sie **nicht** selbst definieren.

### 1.4 Sektions-Struktur der Vorschau

Jede logische Sektion wird als `<section>` mit UIkit-Klassen abgebildet:

```html
<section class="uk-section uk-section-default uk-section-medium">
  <div class="uk-container">
    <!-- Inhalt -->
  </div>
</section>
```

Für visuelle Akzente:
- `uk-section-muted` – gedämpfter Hintergrund
- `uk-section-primary` – Primärfarbe als Hintergrund
- `uk-section-large` – größerer vertikaler Abstand

---

## Teil 2 – Block-JSON-Schema (block-contract-v1)

### 2.1 Grundstruktur eines Blocks

Jeder Block in der `.page.json` hat folgende **Pflichtfelder**:

```json
{
  "id": "string",       // Eindeutige ID (z.B. "hero", "faq", "pricing")
  "type": "string",     // Block-Typ aus der erlaubten Liste
  "variant": "string",  // Variante des Block-Typs
  "data": { }           // Typ-spezifische Nutzdaten
}
```

Optionale Felder:

```json
{
  "tokens": { },              // Design-Tokens (background, spacing, width, columns, accent)
  "sectionAppearance": "string",  // "contained" | "full" | "card" | "default" | "surface" | "contrast" | "image" | "image-fixed"
  "backgroundImage": "string",
  "meta": {
    "anchor": "string",       // HTML-Anker-ID für Navigation
    "sectionStyle": {
      "layout": "string",     // "normal" | "full" | "card"
      "intent": "string",     // "content" | "feature" | "highlight" | "hero" | "plain"
      "background": {
        "mode": "string",     // "none" | "color" | "image"
        "colorToken": "string" // "primary" | "secondary" | "muted" | "accent" | "surface"
      }
    }
  }
}
```

### 2.2 Erlaubte Block-Typen und Varianten

**Nur diese Typen und Varianten sind importfähig.** Alles andere wird abgelehnt oder als Error-Block importiert.

| Typ | Varianten | Beschreibung |
|-----|-----------|--------------|
| `hero` | `centered_cta`, `media-right`, `media_right`, `media-left`, `media_left`, `media_video`, `media-video`, `minimal` | Einstiegsblock mit Headline, Subheadline, CTA |
| `feature_list` | `stacked_cards`, `icon_grid`, `detailed-cards`, `grid-bullets`, `text-columns`, `card-stack`, `slider` | Feature-/Vorteils-Auflistung |
| `content_slider` | `words`, `images` | Slider für Texte oder Bilder |
| `process_steps` | `timeline_horizontal`, `timeline_vertical`, `timeline`, `numbered-vertical`, `numbered-horizontal` | Schritt-für-Schritt-Erklärung |
| `testimonial` | `single_quote`, `quote_wall` | Kundenzitat |
| `rich_text` | `prose` | Fließtext |
| `info_media` | `stacked`, `image-left`, `image-right`, `switcher` | Text + Bild/Medien |
| `cta` | `full_width`, `split` | Call-to-Action-Block |
| `stat_strip` | `inline`, `cards`, `centered`, `highlight`, `three-up`, `three_up` | Kennzahlen-Leiste |
| `audience_spotlight` | `tabs`, `tiles`, `single-focus`, `single_focus` | Zielgruppen-/Use-Case-Spotlight |
| `package_summary` | `toggle`, `comparison-cards` | Paket-/Preisvergleich |
| `faq` | `accordion` | FAQ-Akkordeon |
| `proof` | `metric-callout`, `logo-row` | Vertrauensbeweis (Zahlen/Logos) |

**Deprecated (nicht für neue Seiten verwenden):**
- `system_module` → stattdessen `info_media` / `switcher`
- `case_showcase` → stattdessen `audience_spotlight` / `tabs`

### 2.3 Daten-Schemas je Block-Typ

#### hero

```json
{
  "eyebrow": "string (optional)",
  "eyebrowAsTag": "boolean (optional)",
  "headline": "string (PFLICHT, minLength: 1)",
  "subheadline": "string (optional)",
  "media": { "imageId": "string", "image": "string", "alt": "string" },
  "cta": {
    // Entweder einfach:
    { "label": "string (PFLICHT)", "href": "string (PFLICHT)" }
    // Oder als Gruppe:
    {
      "primary": { "label": "string", "href": "string" },
      "secondary": { "label": "string", "href": "string" }
    }
  },
  "video": { "embedUrl": "string", "title": "string", "subtitle": "string" },
  "referenceLink": { "label": "string", "href": "string" }
}
```

#### feature_list

```json
{
  "eyebrow": "string (optional)",
  "title": "string (PFLICHT, minLength: 1)",
  "subtitle": "string (optional)",
  "lead": "string (optional)",
  "intro": "string (optional)",
  "items": [
    {
      "id": "string (PFLICHT)",
      "icon": "string (optional)",
      "title": "string (PFLICHT)",
      "description": "string (PFLICHT)",
      "label": "string (optional)",
      "bullets": ["string"],
      "media": { "imageId": "string", "image": "string", "alt": "string" }
    }
  ],
  "cta": { "label": "string", "href": "string" }
}
```

#### process_steps

```json
{
  "title": "string (PFLICHT, minLength: 1)",
  "summary": "string (optional)",
  "intro": "string (optional)",
  "steps": [
    {
      "id": "string (PFLICHT)",
      "title": "string (PFLICHT)",
      "description": "string (PFLICHT)",
      "duration": "string (optional)",
      "media": { }
    }
  ],
  "closing": { "title": "string", "body": "string" },
  "ctaPrimary": { "label": "string", "href": "string" },
  "ctaSecondary": { "label": "string", "href": "string" }
}
```

**Constraint:** `steps` braucht mindestens 2 Einträge (`minItems: 2`).

#### testimonial

```json
{
  "quote": "string (PFLICHT, minLength: 1)",
  "author": {
    "name": "string (PFLICHT)",
    "role": "string (optional)",
    "avatarId": "string (optional)"
  },
  "source": "string (optional)",
  "inlineHtml": "string (optional, für Review-Widgets)"
}
```

#### rich_text

```json
{
  "body": "string (PFLICHT, minLength: 1)",
  "alignment": "start | center | end | justify (optional)"
}
```

#### info_media

```json
{
  "eyebrow": "string (optional)",
  "title": "string (optional)",
  "subtitle": "string (optional)",
  "body": "string (optional)",
  "media": { "imageId": "string", "image": "string", "alt": "string" },
  "items": [
    {
      "id": "string (PFLICHT)",
      "title": "string (PFLICHT)",
      "description": "string (PFLICHT)",
      "media": { },
      "bullets": ["string"]
    }
  ]
}
```

**Constraint:** `items` braucht mindestens 1 Eintrag (`minItems: 1`) wenn vorhanden.

#### cta

```json
{
  "title": "string (optional)",
  "body": "string (optional)",
  "primary": { "label": "string (PFLICHT)", "href": "string (PFLICHT)" },
  "secondary": { "label": "string", "href": "string" }
}
```

#### stat_strip

```json
{
  "title": "string (optional)",
  "lede": "string (optional)",
  "columns": "integer 1-6 (optional)",
  "metrics": [
    {
      "id": "string (PFLICHT)",
      "value": "string (PFLICHT)",
      "label": "string (PFLICHT)",
      "icon": "string (optional)",
      "asOf": "string (optional)",
      "tooltip": "string (optional)",
      "benefit": "string (optional)"
    }
  ],
  "marquee": ["string"]
}
```

#### audience_spotlight

```json
{
  "title": "string (PFLICHT, minLength: 1)",
  "subtitle": "string (optional)",
  "cases": [
    {
      "id": "string (PFLICHT)",
      "badge": "string (optional)",
      "title": "string (PFLICHT)",
      "lead": "string (optional)",
      "body": "string (optional)",
      "bullets": ["string"],
      "keyFacts": ["string"],
      "pdf": { "label": "string", "href": "string" },
      "media": { }
    }
  ]
}
```

#### package_summary

```json
{
  "title": "string (PFLICHT, minLength: 1)",
  "subtitle": "string (optional)",
  "options": [
    {
      "id": "string (PFLICHT)",
      "title": "string (PFLICHT)",
      "intro": "string (optional)",
      "highlights": [
        {
          "title": "string (PFLICHT)",
          "bullets": ["string"]
        }
      ]
    }
  ],
  "plans": [
    {
      "id": "string (PFLICHT)",
      "title": "string (PFLICHT)",
      "badge": "string (optional)",
      "description": "string (optional)",
      "features": ["string"],
      "notes": ["string"],
      "primaryCta": { "label": "string", "href": "string" },
      "secondaryCta": { "label": "string", "href": "string" }
    }
  ],
  "disclaimer": "string (optional)"
}
```

**Hinweis:** `options` wird von der Variante `toggle`, `plans` von `comparison-cards` verwendet.

#### faq

```json
{
  "title": "string (PFLICHT, minLength: 1)",
  "items": [
    {
      "id": "string (PFLICHT)",
      "question": "string (PFLICHT)",
      "answer": "string (PFLICHT)"
    }
  ],
  "followUp": {
    "text": "string",
    "linkLabel": "string",
    "href": "string"
  }
}
```

#### content_slider

```json
{
  "title": "string (optional)",
  "eyebrow": "string (optional)",
  "intro": "string (optional)",
  "slides": [
    {
      "id": "string (PFLICHT)",
      "label": "string (PFLICHT)",
      "body": "string (optional)",
      "imageId": "string (optional)",
      "imageAlt": "string (optional)",
      "link": { "label": "string", "href": "string" }
    }
  ]
}
```

#### proof

Variante bestimmt das Daten-Schema:
- `metric-callout` → verwendet `StatStripData` (gleich wie `stat_strip`)
- `logo-row` → verwendet `AudienceSpotlightData` (gleich wie `audience_spotlight`)

### 2.4 Design-Tokens (optional pro Block)

```json
{
  "tokens": {
    "background": "default | muted | primary",
    "spacing": "small | normal | large",
    "width": "narrow | normal | wide",
    "columns": "single | two | three | four",
    "accent": "brandA | brandB | brandC"
  }
}
```

### 2.5 Section-Style (meta.sectionStyle)

Steuert die visuelle Darstellung der Sektion im Renderer:

```json
{
  "meta": {
    "anchor": "features",
    "sectionStyle": {
      "layout": "normal | full | card",
      "intent": "content | feature | highlight | hero | plain",
      "background": {
        "mode": "none | color | image",
        "colorToken": "primary | secondary | muted | accent | surface",
        "imageId": "string",
        "attachment": "scroll | fixed",
        "overlay": 0.0
      }
    }
  }
}
```

**Empfehlungen für Landing Pages:**
- Hero: `layout: "full"`, `intent: "hero"`, `background.colorToken: "secondary"`
- Features: `layout: "normal"`, `intent: "feature"`
- Highlight-Stats: `layout: "normal"`, `intent: "highlight"`
- CTA-Closing: `layout: "full"`, `intent: "highlight"`, `background.colorToken: "primary"`
- FAQ: `layout: "normal"`, `intent: "plain"`
- Content: `layout: "normal"`, `intent: "content"`

---

## Teil 3 – `.page.json` Dateiformat

### 3.1 Gesamtstruktur

```json
{
  "meta": {
    "namespace": "string",
    "slug": "string",
    "title": "string",
    "exportedAt": "ISO-8601",
    "schemaVersion": "block-contract-v1"
  },
  "blocks": [ ]
}
```

### 3.2 Regeln für den Import

1. **`schemaVersion`** muss exakt `"block-contract-v1"` sein.
2. **`meta.slug`** muss zum Zielseiten-Slug passen (Import überschreibt nur Blöcke, erstellt keine neuen Seiten).
3. **`meta.namespace`** wird beim Import ignoriert (Namespace kommt aus dem Request).
4. **Nur `blocks`** wird geschrieben – Seitenidentität, Berechtigungen und Relationen bleiben unverändert.
5. Jeder Block muss gegen `block-contract.schema.json` validieren.
6. Ungültige Blöcke werden in Error-Placeholder-Blöcke gewrappt statt die gesamte Datei abzulehnen.
7. Block-IDs werden normalisiert: gültige IDs bleiben erhalten, fehlende werden als `imported-{hex}` generiert.
8. Legacy-Varianten werden normalisiert (z.B. `centered-cta` → `centered_cta`).

### 3.3 Dateiname und Pfad

- Schema: `content/{namespace}/{slug}.page.json`
- Export-URL: `/admin/pages/{slug}/export`
- Import-URL: `/admin/pages/{slug}/import` (POST mit JSON-Datei)

---

## Teil 4 – Workflow für den Chatbot

### Schritt 1: Anforderungen sammeln

Der Chat fragt den Nutzer nach:
- **Namespace** (z.B. `marketing`, `calserver`, `quizrace`)
- **Slug** (z.B. `mein-produkt`)
- **Titel** der Seite
- **Ziel/Produkt** der Landing Page
- **Gewünschte Sektionen** (oder Vorschlag machen lassen)

### Schritt 2: HTML-Vorschau generieren

Der Chat erstellt ein UIkit-HTML-Fragment als Vorschau. Regeln:
- Nur `uk-*` Klassen
- Struktur: verschachtelte `<section>` → `<div class="uk-container">` → Inhalte
- Keine `<script>`, `<style>`, `<html>`, `<head>`, `<body>` Tags
- Text in Deutsch, nutzenorientiert
- Sektionen mit IDs versehen (werden später zu `meta.anchor`)

**Empfohlener Sektionsaufbau für eine Standard-Landing-Page:**

1. Hero – `hero` / `centered_cta`
2. Kennzahlen – `stat_strip` / `three-up`
3. Features – `feature_list` / `icon_grid` oder `grid-bullets`
4. So funktioniert es – `process_steps` / `timeline` oder `numbered-horizontal`
5. Module/Details – `info_media` / `switcher` oder `image-left`
6. Zielgruppen – `audience_spotlight` / `tabs` oder `tiles`
7. Pakete/Preise – `package_summary` / `comparison-cards` oder `toggle`
8. FAQ – `faq` / `accordion`
9. Abschluss-CTA – `cta` / `split` oder `full_width`

### Schritt 3: JSON-Blocks ableiten

Aus jedem HTML-Abschnitt werden die semantischen Daten extrahiert und in das Block-Schema überführt:

| HTML-Element | Block-Feld |
|--------------|------------|
| `<h1>`, `<h2>` mit `uk-heading-*` | `data.headline` / `data.title` |
| `<p>` mit `uk-text-lead` | `data.subheadline` / `data.lead` |
| `<p>` mit `uk-text-meta` | `data.eyebrow` |
| `<a>` mit `uk-button-primary` | `data.cta.primary` |
| `<a>` mit `uk-button-default` | `data.cta.secondary` |
| `uk-card` Blöcke | `data.items[]` Einträge |
| `uk-accordion` | `data.items[]` mit `question`/`answer` |
| `uk-list-bullet` | `data.bullets[]` |
| `<section>` ID-Attribut | `meta.anchor` |
| `uk-section-muted` | `meta.sectionStyle.background.colorToken: "muted"` |
| `uk-section-primary` | `meta.sectionStyle.background.colorToken: "primary"` |

### Schritt 4: `.page.json` zusammensetzen

```json
{
  "meta": {
    "namespace": "{{namespace}}",
    "slug": "{{slug}}",
    "title": "{{title}}",
    "exportedAt": "{{ISO-8601 Zeitstempel}}",
    "schemaVersion": "block-contract-v1"
  },
  "blocks": [
    // Alle Blöcke aus Schritt 3, in Reihenfolge
  ]
}
```

### Schritt 5: Validierung

Vor der Ausgabe prüfen:

- [ ] `schemaVersion` ist `"block-contract-v1"`
- [ ] Jeder Block hat `id`, `type`, `variant`, `data`
- [ ] Alle `type`-Werte stehen in der erlaubten Liste (Teil 2.2)
- [ ] Alle `variant`-Werte passen zum jeweiligen `type`
- [ ] Pflichtfelder in `data` sind gesetzt und nicht leer
- [ ] Array-Felder erfüllen `minItems`-Constraints
- [ ] Keine deprecated Typen (`system_module`, `case_showcase`)
- [ ] Keine `additionalProperties` in `data`-Objekten
- [ ] Block-IDs sind eindeutig innerhalb der Seite
- [ ] CTA-Gruppen haben die korrekte Struktur (einfach oder primary/secondary)

---

## Teil 5 – Referenzbeispiel

### Minimal-Landing-Page (3 Blöcke)

```json
{
  "meta": {
    "namespace": "marketing",
    "slug": "mein-produkt",
    "title": "Mein Produkt – Einfach besser arbeiten",
    "schemaVersion": "block-contract-v1"
  },
  "blocks": [
    {
      "id": "hero",
      "type": "hero",
      "variant": "centered_cta",
      "meta": {
        "sectionStyle": {
          "layout": "full",
          "intent": "hero",
          "background": {
            "mode": "color",
            "colorToken": "secondary"
          }
        }
      },
      "data": {
        "headline": "Einfach besser arbeiten.",
        "subheadline": "Mein Produkt organisiert Ihre Abläufe – übersichtlich, schnell und sicher.",
        "cta": {
          "primary": {
            "label": "Jetzt testen",
            "href": "#trial"
          },
          "secondary": {
            "label": "Demo anfragen",
            "href": "#contact"
          }
        }
      }
    },
    {
      "id": "features",
      "type": "feature_list",
      "variant": "icon_grid",
      "meta": {
        "anchor": "features",
        "sectionStyle": {
          "layout": "normal",
          "intent": "feature"
        }
      },
      "data": {
        "title": "Was Mein Produkt auszeichnet",
        "items": [
          {
            "id": "feat-1",
            "icon": "check",
            "title": "Einfache Bedienung",
            "description": "Intuitives Interface, das sofort verstanden wird."
          },
          {
            "id": "feat-2",
            "icon": "lock",
            "title": "Sicher gehostet",
            "description": "Hosting in Deutschland, DSGVO-konform."
          },
          {
            "id": "feat-3",
            "icon": "users",
            "title": "Teamfähig",
            "description": "Rollen und Rechte für jedes Teammitglied."
          }
        ]
      }
    },
    {
      "id": "closing-cta",
      "type": "cta",
      "variant": "split",
      "meta": {
        "sectionStyle": {
          "layout": "full",
          "intent": "highlight",
          "background": {
            "mode": "color",
            "colorToken": "primary"
          }
        }
      },
      "data": {
        "primary": {
          "label": "Jetzt starten",
          "href": "#trial"
        },
        "secondary": {
          "label": "Kontakt aufnehmen",
          "href": "#contact"
        }
      }
    }
  ]
}
```

---

## Teil 6 – Wichtige Einschränkungen

1. **Kein Framework-Markup im JSON:** Block-Daten enthalten niemals `uk-*` Klassen oder HTML-Tags (Ausnahme: Felder vom Typ `html` für Rich-Text, z.B. `headline`).
2. **Renderer ist zuständig:** UIkit-Klassen werden ausschließlich vom Renderer (`block-renderer-matrix.js`) hinzugefügt, nicht vom Content.
3. **`additionalProperties: false`:** Das JSON-Schema verbietet zusätzliche Felder. Keine eigenen Felder erfinden.
4. **Block-IDs stabil halten:** Einmal vergebene IDs nicht ändern. Beim Erstellen: kurze, sprechende IDs wie `"hero"`, `"faq"`, `"pricing"`.
5. **Bilder als Pfad oder ID:** `media.image` für direkte Pfade (`/uploads/...`), `media.imageId` für Asset-Referenzen.
6. **Legacy-Templates verboten:** Keine Inhalte für `templates/marketing/*.twig` generieren. Alles läuft über das Page-Editor-System mit Block-JSON.
7. **Keine Seiten erzeugen:** Import überschreibt nur Blöcke bestehender Seiten. Die Seite muss vorher im Admin angelegt werden.
