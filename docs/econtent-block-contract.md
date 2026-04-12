# eContent – Block-Contract Schema-Referenz

> **Zweck:** Vollständige Dokumentation des Block-Contract-Schemas für die Migration nach eForms. Quelle: `public/js/components/block-contract.schema.json` (JSON Schema Draft-07).

---

## 1. Block-Envelope (gemeinsame Struktur)

Jeder Block im `content`-Feld einer Seite ist ein JSON-Objekt mit folgender Grundstruktur:

```json
{
  "id": "string (required, min 1)",
  "type": "string (required)",
  "variant": "string (required)",
  "data": { },
  "tokens": { },
  "sectionAppearance": "contained|full|card|default|surface|contrast|image|image-fixed",
  "backgroundImage": "string",
  "meta": { }
}
```

### Pflichtfelder

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `id` | string | Eindeutige Block-ID (min. 1 Zeichen) |
| `type` | string | Block-Typ (siehe Abschnitt 2) |
| `variant` | string | Darstellungsvariante (typ-spezifisch) |
| `data` | object | Block-Daten (typ-spezifisch, siehe Abschnitt 3) |

### Optionale Felder

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `tokens` | Tokens | Design-Tokens (background, spacing, width, columns, accent) |
| `sectionAppearance` | enum | Abschnitts-Darstellung |
| `backgroundImage` | string | Hintergrundbild-URL |
| `meta` | BlockMeta | Anker-ID und Section-Style |

---

## 2. Block-Typen und Varianten

| Typ | Varianten | Data-Definition | Hinweis |
|-----|-----------|-----------------|---------|
| `hero` | `centered_cta`, `media-right`, `media_right`, `media-left`, `media_left`, `media_video`, `media-video`, `minimal`, `stat_tiles`, `small` | HeroData | |
| `feature_list` | `stacked_cards`, `icon_grid`, `detailed-cards`, `grid-bullets`, `text-columns`, `card-stack`, `slider`, `clustered-tabs` | FeatureListData | |
| `content_slider` | `words`, `images`, `detail-split` | ContentSliderData | |
| `process_steps` | `timeline_horizontal`, `timeline_vertical`, `timeline`, `timeline_cards`, `numbered-vertical`, `numbered-horizontal` | ProcessStepsData | |
| `testimonial` | `single_quote`, `quote_wall`, `slider` | TestimonialData | |
| `rich_text` | `prose` | RichTextData | |
| `info_media` | `stacked`, `image-left`, `image-right`, `switcher` | InfoMediaData | |
| `cta` | `full_width`, `split`, `newsletter` | CtaBlockData | |
| `stat_strip` | `inline`, `cards`, `centered`, `highlight`, `three-up`, `three_up`, `trust_bar`, `trust_band` | StatStripData | |
| `audience_spotlight` | `tabs`, `tiles`, `single-focus`, `single_focus` | AudienceSpotlightData | |
| `package_summary` | `toggle`, `comparison-cards` | PackageSummaryData | |
| `faq` | `accordion` | FaqData | |
| `latest_news` | `cards` | LatestNewsData | |
| `event_highlight` | `hero`, `card`, `compact` | EventHighlightData | |
| `subscription_plans` | `cards` | SubscriptionPlansData | |
| `contact_form` | `default`, `compact` | ContactFormData | |
| `proof` | `metric-callout` → StatStripData, `logo-row` → LogoRowData | varianten-abhängig | Polymorph |
| `system_module` | `switcher` | InfoMediaData | **Deprecated** → info_media switcher |
| `case_showcase` | `tabs` | AudienceSpotlightData | **Deprecated** → audience_spotlight tabs |

---

## 3. Data-Definitionen

### HeroData

Hero-Banner mit Bild, Titel, CTA und optionalen Statistik-Kacheln.

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `headline` | string | **ja** | Hauptüberschrift (min. 1 Zeichen) |
| `cta` | CallToActionGroup | **ja** | Call-to-Action (einzeln oder primary/secondary) |
| `eyebrow` | string | nein | Dachzeile über der Headline |
| `eyebrowAsTag` | boolean | nein | Eyebrow als Badge/Tag rendern |
| `subheadline` | string | nein | Unterüberschrift |
| `bullets` | string[] | nein | USP-Bulletpoints (Checkmark-Liste) |
| `media` | Media | nein | Bild mit Alt-Text und Focal-Point |
| `video` | HeroVideo | nein | Video-Embed |
| `referenceLink` | CallToAction | nein | Referenz-Link |
| `statTiles` | array | nein | Statistik-Kacheln (`value` + `label`) |
| `provenExpert` | object | nein | ProvenExpert-Widget (`rating`, `recommendation`, `reviewCount`, `reviewSource`) |

### HeroVideo

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `embedUrl` | string | Video-Embed-URL |
| `title` | string | Video-Titel |
| `subtitle` | string | Video-Untertitel |
| `note` | string | Hinweistext |
| `consentRequired` | boolean | Cookie-Consent erforderlich |
| `link` | CallToAction | Fallback-Link |

### FeatureListData

Feature-Liste mit Icons, optionalen Gruppen und Tabs.

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `title` | string | **ja** | Titel (min. 1 Zeichen) |
| `items` | FeatureItem[] | **ja** | Feature-Einträge (min. 1) |
| `eyebrow` | string | nein | Dachzeile |
| `subtitle` | string | nein | Untertitel |
| `lead` | string | nein | Einleitungstext |
| `intro` | string | nein | Intro-Text |
| `columns` | integer | nein | Spaltenanzahl (1–6) |
| `cta` | CallToAction | nein | Call-to-Action |
| `groups` | FeatureGroup[] | nein | Feature-Gruppen (für Tabs/Cluster) |

### ContentSliderData

Content-Karussell mit Slides.

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `slides` | ContentSlide[] | **ja** | Slides (min. 1) |
| `title` | string | nein | Titel |
| `eyebrow` | string | nein | Dachzeile |
| `intro` | string | nein | Intro-Text |

### ProcessStepsData

Prozess-/Schrittanleitung mit Timeline.

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `title` | string | **ja** | Titel (min. 1 Zeichen) |
| `steps` | ProcessStep[] | **ja** | Prozessschritte (min. 1) |
| `summary` | string | nein | Zusammenfassung |
| `intro` | string | nein | Intro-Text |
| `closing` | ClosingCopy | nein | Abschlusstext (`title` + `body`) |
| `ctaPrimary` | CallToAction | nein | Primärer CTA |
| `ctaSecondary` | CallToAction | nein | Sekundärer CTA |

### TestimonialData

Kundenstimmen/Zitate (einzeln oder als Wall/Slider).

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `title` | string | nein | Titel |
| `subtitle` | string | nein | Untertitel |
| `quote` | string | nein | Einzelzitat (min. 1 Zeichen, für `single_quote`) |
| `author` | Author | nein | Autor des Einzelzitats |
| `source` | string | nein | Quelle |
| `inlineHtml` | string | nein | Inline-HTML für Review-Widgets |
| `quotes` | array | nein | Mehrere Zitate (für `quote_wall`/`slider`, min. 1) |

**quotes[]-Einträge:**

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `quote` | string | Zitat-Text |
| `author` | Author | Autor |
| `source` | string | Quelle |
| `avatarInitials` | string | Initialen für Avatar |
| `inlineHtml` | string | Inline-HTML |
| `rating` | integer (1–5) | Sterne-Bewertung |

### RichTextData

Freitext-Block.

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `body` | string | **ja** | HTML/Markdown-Inhalt (min. 1 Zeichen) |
| `alignment` | enum | nein | `start`, `center`, `end`, `justify` |

### InfoMediaData

Info-Block mit Medien und optionalen Tabs (Switcher).

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `eyebrow` | string | nein | Dachzeile |
| `title` | string | nein | Titel |
| `subtitle` | string | nein | Untertitel |
| `body` | string | nein | Fließtext |
| `media` | Media | nein | Bild/Video |
| `cta` | CallToActionGroup | nein | Call-to-Action |
| `items` | InfoMediaItem[] | nein | Tabs/Switcher-Einträge (min. 1) |

### CtaBlockData

Call-to-Action-Block (auch Newsletter-Variante).

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `primary` | CallToAction | **ja** | Primärer CTA |
| `title` | string | nein | Titel |
| `body` | string | nein | Fließtext |
| `secondary` | CallToAction | nein | Sekundärer CTA |
| `newsletterPlaceholder` | string | nein | Placeholder (Newsletter-Variante) |
| `newsletterPrivacyHint` | string | nein | Datenschutzhinweis |
| `newsletterSuccessMessage` | string | nein | Erfolgsmeldung |
| `newsletterSource` | string | nein | Newsletter-Quelle |

### StatStripData

Statistik-Leiste / Kennzahlen (auch für `proof` mit Variante `metric-callout`).

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `title` | string | nein | Titel |
| `lede` | string | nein | Einleitungstext |
| `columns` | integer | nein | Spaltenanzahl (1–6) |
| `metrics` | Metric[] | nein | Kennzahlen (min. 1) |
| `items` | StatStripItem[] | nein | Items mit Icon + Label (min. 1) |
| `marquee` | string[] | nein | Marquee-Texte (Laufband) |

### AudienceSpotlightData

Zielgruppen-Highlight / Fallstudien (auch für deprecated `case_showcase`).

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `title` | string | **ja** | Titel (min. 1 Zeichen) |
| `subtitle` | string | nein | Untertitel |
| `cases` | AudienceCase[] | **ja** | Zielgruppen/Cases (min. 1) |

### LogoRowData

Logo-Reihe (für `proof` mit Variante `logo-row`).

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `logos` | LogoRowItem[] | **ja** | Logos (min. 1) |
| `title` | string | nein | Titel |
| `subtitle` | string | nein | Untertitel |
| `marquee` | boolean | nein | Automatisches horizontales Scrolling |
| `grayscale` | boolean | nein | Graustufen, Farbe bei Hover |

### PackageSummaryData

Paket-/Preisübersicht mit Toggle und Vergleichskarten.

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `title` | string | **ja** | Titel (min. 1 Zeichen) |
| `subtitle` | string | nein | Untertitel |
| `billingToggle` | BillingCycleToggle | nein | Umschalter (z.B. monatlich/jährlich) |
| `options` | PackageOption[] | nein | Paketoptionen (min. 1) |
| `plans` | PackagePlan[] | nein | Preispläne (min. 1) |
| `disclaimer` | string | nein | Hinweistext |
| `columns` | integer | nein | Spaltenanzahl (2–4) |

### FaqData

FAQ-Akkordeon.

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `title` | string | **ja** | Titel (min. 1 Zeichen) |
| `items` | FaqItem[] | **ja** | FAQ-Einträge (min. 1) |
| `followUp` | FaqFollowUp | nein | Follow-Up-Link unter dem FAQ |

### LatestNewsData

Neueste Nachrichten.

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `heading` | string | **ja** | Überschrift (min. 1 Zeichen) |
| `limit` | integer | nein | Max. Anzahl Beiträge (1–6) |
| `showAllLink` | boolean | nein | "Alle anzeigen"-Link |

### EventHighlightData

Event-Hervorhebung (Verknüpfung mit eQuiz-Events).

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `eventSlug` | string | **ja** | Event-Slug (min. 1 Zeichen) |
| `ctaLabel` | string | nein | CTA-Button-Text |
| `ctaAriaLabel` | string | nein | CTA-Aria-Label |
| `showCountdown` | boolean | nein | Countdown anzeigen |
| `showDescription` | boolean | nein | Event-Beschreibung anzeigen |
| `catalogSlug` | string | nein | Spezifischer Katalog-Slug |

### SubscriptionPlansData

Abo-Pläne (Stripe-Integration).

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `title` | string | **ja** | Titel (min. 1 Zeichen) |
| `subtitle` | string | nein | Untertitel |
| `stripeProduct` | string | nein | Stripe-Produkt-ID |
| `ctaLabel` | string | nein | CTA-Button-Text |
| `ctaTarget` | string | nein | CTA-Ziel-URL |

### ContactFormData

Kontaktformular.

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `title` | string | **ja** | Titel (min. 1 Zeichen) |
| `intro` | string | **ja** | Einleitungstext (min. 1 Zeichen) |
| `recipient` | string | **ja** | Empfänger-E-Mail (min. 1 Zeichen) |
| `submitLabel` | string | **ja** | Submit-Button-Text (min. 1 Zeichen) |
| `privacyHint` | string | **ja** | Datenschutzhinweis (min. 1 Zeichen) |
| `fields` | ContactFormField[] | nein | Zusätzliche Felder |
| `successMessage` | string | nein | Erfolgsmeldung |

---

## 4. Shared Definitions (wiederverwendbare Typen)

### Tokens

Design-Tokens zur visuellen Steuerung jedes Blocks.

| Feld | Typ | Werte |
|------|-----|-------|
| `background` | enum | `default`, `muted`, `primary` |
| `spacing` | enum | `small`, `normal`, `large` |
| `width` | enum | `narrow`, `normal`, `wide` |
| `columns` | enum | `single`, `two`, `three`, `four` |
| `accent` | enum | `brandA`, `brandB`, `brandC` |

### BlockMeta

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `anchor` | string | Anker-ID für In-Page-Navigation |
| `sectionStyle` | SectionStyle | Abschnitts-Layout und Hintergrund |

### SectionStyle

| Feld | Typ | Required | Werte/Beschreibung |
|------|-----|----------|-------------------|
| `layout` | enum | **ja** | `normal`, `full`, `card` |
| `intent` | enum | nein | `content`, `feature`, `highlight`, `hero`, `plain` |
| `background` | SectionBackground | nein | Hintergrund-Konfiguration |
| `viewportHeight` | enum | nein | `auto`, `full`, `reduced`, `minus-next` |

### SectionBackground

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `mode` | enum | `none`, `color`, `image` |
| `colorToken` | enum | `primary`, `secondary`, `muted`, `accent`, `surface` |
| `imageId` | string | Bild-ID |
| `attachment` | enum | `scroll`, `fixed` |
| `overlay` | number (0–1) | Overlay-Transparenz |

### Media

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `imageId` | string | Bild-ID (Asset-Manager) |
| `image` | string | Fallback-URL |
| `alt` | string | Alt-Text |
| `focalPoint` | FocalPoint | Fokuspunkt (`x`: 0–1, `y`: 0–1) |
| `frameless` | boolean | Ohne Rahmen rendern |

### FocalPoint

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `x` | number (0–1) | Horizontaler Fokuspunkt |
| `y` | number (0–1) | Vertikaler Fokuspunkt |

### CallToAction

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `label` | string | **ja** | Button-Text (min. 1 Zeichen) |
| `href` | string | **ja** | Link-Ziel (min. 1 Zeichen) |
| `ariaLabel` | string | nein | Aria-Label |

### CallToActionGroup

Entweder ein einzelner `CallToAction` oder ein Objekt mit:

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `primary` | CallToAction | **ja** | Primärer CTA |
| `secondary` | CallToAction | nein | Sekundärer CTA |

### Author

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `name` | string | **ja** | Name (min. 1 Zeichen) |
| `role` | string | nein | Rolle/Position |
| `avatarId` | string | nein | Avatar-Bild-ID |

### FeatureItem

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `id` | string | **ja** | Eindeutige ID |
| `title` | string | **ja** | Titel |
| `description` | string | **ja** | Beschreibung |
| `icon` | string | nein | Icon-Name |
| `label` | string | nein | Label |
| `bullets` | string[] | nein | Aufzählungspunkte |
| `media` | Media | nein | Bild |
| `group` | string | nein | Gruppen-ID (für clustered-tabs) |

### FeatureGroup

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `id` | string | **ja** | Gruppen-ID |
| `label` | string | **ja** | Gruppen-Label |

### ProcessStep

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `id` | string | **ja** | Eindeutige ID |
| `title` | string | **ja** | Schritt-Titel |
| `description` | string | **ja** | Schritt-Beschreibung |
| `duration` | string | nein | Dauer-Angabe |
| `icon` | string | nein | UIkit-Icon (statt Schrittnummer) |
| `media` | Media | nein | Bild |

### ClosingCopy

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `title` | string | Abschluss-Titel |
| `body` | string | Abschluss-Text |

### ContentSlide

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `id` | string | **ja** | Eindeutige ID |
| `label` | string | **ja** | Slide-Label/Tab-Titel |
| `body` | string | nein | Slide-Inhalt |
| `imageId` | string | nein | Bild-ID |
| `imageAlt` | string | nein | Bild-Alt-Text |
| `link` | CallToAction | nein | Slide-Link |

### InfoMediaItem

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `id` | string | **ja** | Eindeutige ID |
| `title` | string | **ja** | Tab-/Item-Titel |
| `description` | string | **ja** | Beschreibung |
| `media` | Media | nein | Bild |
| `bullets` | string[] | nein | Aufzählungspunkte |

### Metric

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `id` | string | **ja** | Eindeutige ID |
| `value` | string | **ja** | Kennzahl-Wert (z.B. "500+") |
| `label` | string | **ja** | Beschriftung |
| `icon` | string | nein | Icon-Name |
| `asOf` | string | nein | Stand-Datum |
| `tooltip` | string | nein | Tooltip-Text |
| `benefit` | string | nein | Nutzen-Beschreibung |

### StatStripItem

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `icon` | string | Icon-Name |
| `label` | string | Beschriftung |

### AudienceCase

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `title` | string | **ja** | Case-Titel |
| `id` | string | nein | Eindeutige ID |
| `badge` | string | nein | Badge-Text |
| `lead` | string | nein | Einleitungstext |
| `body` | string | nein | Fließtext |
| `bullets` | string[] | nein | Aufzählungspunkte |
| `keyFacts` | string[] | nein | Kernaussagen |
| `pdf` | CallToAction | nein | PDF-Download-Link |
| `media` | Media | nein | Bild |

### LogoRowItem

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `id` | string | **ja** | Eindeutige ID |
| `alt` | string | **ja** | Alt-Text |
| `imageId` | string | nein | Bild-ID (Asset-Manager) |
| `image` | string | nein | Externe Fallback-URL |
| `href` | string | nein | Link zur Kunden-Website |

### PackageOption

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `id` | string | **ja** | Eindeutige ID |
| `title` | string | **ja** | Paket-Titel |
| `intro` | string | nein | Einleitungstext |
| `highlights` | PackageHighlight[] | nein | Feature-Highlights |

### PackageHighlight

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `title` | string | **ja** | Highlight-Titel |
| `bullets` | string[] | nein | Detail-Punkte |

### BillingCycleToggle

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `labelA` | string | **ja** | Label A (z.B. "Monatlich") |
| `labelB` | string | **ja** | Label B (z.B. "Jährlich") |

### PackagePlan

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `title` | string | **ja** | Plan-Titel |
| `id` | string | nein | Eindeutige ID |
| `badge` | string | nein | Badge (z.B. "Beliebteste") |
| `description` | string | nein | Beschreibung |
| `features` | string[] | nein | Feature-Liste |
| `notes` | string[] | nein | Hinweise |
| `priceA` | string | nein | Preis A (Toggle-Position A) |
| `priceB` | string | nein | Preis B (Toggle-Position B) |
| `primaryCta` | CallToAction | nein | Primärer CTA |
| `secondaryCta` | CallToAction | nein | Sekundärer CTA |

### FaqItem

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `id` | string | **ja** | Eindeutige ID |
| `question` | string | **ja** | Frage |
| `answer` | string | **ja** | Antwort |

### FaqFollowUp

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `text` | string | Follow-Up-Text |
| `linkLabel` | string | Link-Text |
| `href` | string | Link-Ziel |

### ContactFormField

| Feld | Typ | Required | Beschreibung |
|------|-----|----------|-------------|
| `key` | enum | **ja** | `subject`, `phone`, `company_name` |
| `label` | string | **ja** | Feld-Label |
| `enabled` | boolean | **ja** | Feld aktiviert |
| `required` | boolean | nein | Feld erforderlich |
