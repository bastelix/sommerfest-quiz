# Block Rendering Handbook

Reference for external editors (MCP clients, AI agents) that create or modify page content via the edocs block contract. This document bridges the gap between the JSON schema and the actual visual output.

---

## Architecture Overview

```
Block JSON (.page.json / upsert_page)
    │
    ▼
normalizeBlock()          ← block-contract.js (validation + migration)
    │
    ▼
RENDERER_MATRIX[type][variant]()  ← block-renderer-matrix-data.js (HTML generation)
    │
    ▼
renderSection()           ← wraps content in <section> with intent/background/layout
    │
    ▼
<section> HTML            ← styled by sections.css via data-attributes + CSS variables
```

Key principle: Block JSON contains **semantic data only** — no HTML, no CSS classes. The renderer matrix converts data to UIkit 3 HTML. The section wrapper applies visual styling via CSS custom properties.

---

## Section Styling (meta.sectionStyle)

This is the primary mechanism for visual differentiation between sections. Every block is wrapped in a `<section>` element whose appearance is controlled by `meta.sectionStyle`.

### The Rendered HTML Structure

```html
<section
  class="section uk-section uk-section-large uk-container-large section--bg-muted"
  data-block-id="features"
  data-block-type="feature_list"
  data-block-variant="icon_grid"
  data-section-intent="feature"
  data-effect="reveal"
  data-section-background-mode="color"
  data-section-background-color-token="muted"
  data-section-surface-token="muted"
  data-section-text-token="text-on-muted"
  style="--section-surface:var(--surface-muted, ...); --section-bg-color:var(--surface-muted, ...); --section-text-color:var(...);"
>
  <div class="uk-container uk-container-large">
    <div class="section__inner">
      <!-- block content here -->
    </div>
  </div>
</section>
```

### sectionStyle Properties

```json
{
  "meta": {
    "sectionStyle": {
      "layout": "normal",
      "intent": "feature",
      "background": {
        "mode": "color",
        "colorToken": "muted"
      },
      "container": {
        "width": "wide",
        "frame": "none",
        "spacing": "generous"
      },
      "viewportHeight": "auto"
    }
  }
}
```

### Intent

Controls the overall visual weight and spacing of a section.

| Intent | Background | Text Colour | Padding | Container Width | Use For |
|---|---|---|---|---|---|
| `content` | `--surface` (white/transparent) | Dark text | Normal | Normal (1200px) | Prose, process steps |
| `plain` | `--surface` (white/transparent) | Dark text | Normal | Normal (1200px) | Trust bars, minimal sections |
| `feature` | `--surface-muted` (light grey) | Dark text | Large | Wide | Feature lists, testimonials |
| `highlight` | `--accent-primary` (brand colour) | White text | Large | Wide | Stats, CTAs, proof |
| `hero` | `--accent-secondary` (dark brand) | White text | Extra large | Full-width | Hero sections |

**Default intents per block type** (applied when no explicit intent is set):

| Block Type | Default Intent |
|---|---|
| `hero` | `hero` |
| `feature_list`, `info_media`, `audience_spotlight`, `testimonial`, `package_summary`, `content_slider` | `feature` |
| `stat_strip`, `proof`, `cta` | `highlight` |
| `process_steps`, `rich_text` | `content` |
| `faq` | `plain` |

### Background

Overrides the intent's default surface colour.

| Property | Values | Effect |
|---|---|---|
| `mode` | `none`, `color`, `image` | Type of background |
| `colorToken` | `primary`, `secondary`, `muted`, `accent`, `surface` | Named colour from namespace palette |
| `imageId` | string | Asset reference for background image |
| `attachment` | `scroll`, `fixed` | Parallax effect for images |
| `overlay` | 0.0 – 1.0 | Darkness overlay on images |

**Color tokens and their visual effect:**

| Token | Light Mode | Dark Mode | Text |
|---|---|---|---|
| `surface` | White / transparent | Dark surface | Dark text |
| `muted` | Light grey (`--surface-muted`) | Dark muted | Dark text |
| `primary` | Brand primary colour | Brand primary | **White text** |
| `secondary` | Darker brand colour | Dark blend | **White text** |
| `accent` | Accent colour | Accent | **White text** |

**Important:** `primary`, `secondary`, and `accent` are "dark tokens" — they automatically switch text to white. `surface` and `muted` keep dark text.

### Layout

| Value | Effect |
|---|---|
| `normal` | Standard contained layout |
| `full` | Full-bleed background (content still contained) |
| `card` | Content wrapped in a card panel |
| `full-card` | Full-bleed background + card panel for content |

### Container

| Property | Values | Effect |
|---|---|---|
| `width` | `normal`, `wide`, `full` | `uk-container` / `uk-container-large` / `uk-container-expand` |
| `frame` | `none`, `card` | Optional card wrapper around inner content |
| `spacing` | `compact`, `normal`, `generous` | `uk-section-medium` / `uk-section-medium` / `uk-section-large` |

### Viewport Height

| Value | Effect |
|---|---|
| `auto` | Normal flow (default) |
| `full` | Full viewport height (`uk-height-viewport`) |
| `reduced` | 80vh minimum height |
| `minus-next` | Viewport height minus next section |

---

## Legacy: sectionAppearance

`sectionAppearance` is a legacy shorthand that maps to `meta.sectionStyle`. **Prefer `meta.sectionStyle` for new content.**

| Value | Maps To | Effect |
|---|---|---|
| `contained` | layout: `normal` | Standard contained section |
| `default` | → `contained` | Alias, identical to contained |
| `surface` | → `contained` | Alias, identical to contained |
| `full` | layout: `full` | Full-bleed layout |
| `contrast` | → `full` | Alias for full (does NOT set dark background) |
| `card` | layout: `card` | Card-wrapped content |
| `image` | layout: `full` | Full-bleed with image support |
| `image-fixed` | layout: `full`, attachment: `fixed` | Parallax background |

**Known limitation:** `default`, `surface`, and `contrast` all resolve to near-identical output. They only affect layout, not background colour or intent. To get actual visual contrast, use `meta.sectionStyle` with explicit `intent` and `background.colorToken`.

---

## CSS Selectors for Custom CSS

The section wrapper emits data attributes that can be targeted in namespace custom CSS (via `update_custom_css`):

```css
/* Target by block type */
.section[data-block-type="stat_strip"] { }

/* Target by variant */
.section[data-block-variant="trust_band"] { }

/* Target by intent */
.section[data-section-intent="feature"] { }
.section[data-section-intent="highlight"] { }

/* Target by background */
.section[data-section-background-color-token="primary"] { }
.section[data-section-background-mode="color"] { }

/* Target by block ID */
.section[data-block-id="hero"] { }

/* Target inner content wrappers */
.section__inner { }
.section__inner--panel { }
.section__inner--accent { }
.section__inner--card { }

/* Override section CSS variables */
.section[data-block-id="my-block"] {
  --section-surface: #f0f4f8;
  --section-bg-color: #f0f4f8;
  --section-text-color: #1a1a2e;
}
```

### Available CSS Variables per Section

| Variable | Purpose |
|---|---|
| `--section-surface` | Base surface colour |
| `--section-bg-color` | Background colour (defaults to surface) |
| `--section-text-color` | Text colour |
| `--section-bg-image` | Background image URL |
| `--section-bg-attachment` | `scroll` or `fixed` |
| `--section-bg-overlay` | Overlay opacity (0–1) |
| `--section-padding-outer` | Vertical padding |
| `--section-padding-inner` | Inner content padding |

---

## Block Types & Variants — Visual Behaviour

### hero

| Variant | Layout |
|---|---|
| `centered_cta` | Centred text + CTA buttons |
| `media_right` / `media-right` | Two columns: text left, image right |
| `media_left` / `media-left` | Two columns: image left, text right |
| `media_video` / `media-video` | Text + embedded video |
| `minimal` | Text only, no media |
| `stat_tiles` | Text left + stat tiles right |
| `small` | Compact hero |

### feature_list

| Variant | Layout | Notes |
|---|---|---|
| `detailed-cards` | 3-column card grid | Cards with icon + title + description |
| `grid-bullets` | 3-column grid | Compact items with bullets |
| `icon_grid` | Alias for `grid-bullets` | Same output |
| `slider` | Horizontal carousel | Swipeable card slider |
| `text-columns` | Multi-column text | No icons, prose-style |
| `card-stack` | Stacked cards | Vertical card layout |
| `clustered-tabs` | Tab navigation | Grouped items in tabs |

**Icon rendering:** Items with an `icon` property render via UIkit's SVG icon system (`data-uk-icon`). See [Icon Reference](#icon-reference) below.

### info_media

| Variant | Layout | Notes |
|---|---|---|
| `stacked` | Single column | Media above text |
| `image-left` | Two columns | Image left, text + items right |
| `image-right` | Two columns | Text + items left, image right |
| `switcher` | Tabbed content | Multiple items as switchable tabs |

**Key for layout variety:** `image-left` and `image-right` produce genuine 2-column layouts that break the grid monotony of feature_list variants.

### stat_strip

| Variant | Layout | Notes |
|---|---|---|
| `inline` | Horizontal metrics | Large values + labels in a row |
| `cards` | Metric cards | Each metric in a card |
| `centered` | Centred metrics | Centred alignment |
| `highlight` | Accent background | Designed for dark intent sections |
| `trust_bar` | Inline icon + label list | Small, separated by dividers |
| `trust_band` | Icon + label list | Slightly larger than trust_bar |

**Icon support:** `trust_bar` and `trust_band` use `data.items[].icon`. All other variants use `data.metrics[].icon`.

### process_steps

| Variant | Layout |
|---|---|
| `numbered-vertical` | Vertical numbered steps |
| `numbered-horizontal` | Horizontal numbered steps |
| `timeline` | Timeline with connectors |
| `timeline_horizontal` | Horizontal timeline |
| `timeline_vertical` | Vertical timeline |

### testimonial

| Variant | Layout |
|---|---|
| `single_quote` | Single prominent quote |
| `quote_wall` | Grid of 2–3 quotes |

### cta

| Variant | Layout |
|---|---|
| `full_width` | Full-width CTA bar |
| `split` | Two CTA buttons side by side |

### faq

| Variant | Layout |
|---|---|
| `accordion` | Collapsible Q&A sections |

### package_summary

| Variant | Data Source |
|---|---|
| `toggle` | Uses `data.options[]` with highlights |
| `comparison-cards` | Uses `data.plans[]` with features |

### audience_spotlight

| Variant | Layout | Status |
|---|---|---|
| `tabs` | Tabbed cases | ⚠ May render as accordion (known issue #5) |
| `tiles` | Tile grid | ⚠ May render as accordion (known issue #5) |
| `single-focus` / `single_focus` | Single case highlighted | Works |

### content_slider

| Variant | Layout |
|---|---|
| `words` | Text slide carousel |
| `images` | Image slide carousel |

### proof

| Variant | Data Schema |
|---|---|
| `metric-callout` | Same as `stat_strip` |
| `logo-row` | Same as `audience_spotlight` |

---

## Icon Reference

Icons render as UIkit 3 SVGs via the `data-uk-icon` attribute. Only names from the UIkit icon library and the registered custom icons are valid.

### Available Icons by Category

| Category | Icons |
|---|---|
| **General** | `check`, `close`, `plus`, `minus`, `star`, `heart`, `bolt`, `bell`, `bookmark`, `tag`, `ban`, `info`, `question`, `warning`, `settings`, `cog`, `search`, `home`, `grid`, `list`, `hashtag`, `happy`, `clock`, `calendar`, `history`, `sun`, `moon`, `handbook`, `future` |
| **Media** | `image`, `camera`, `play`, `video-camera`, `microphone`, `tv`, `album`, `thumbnails`, `file`, `file-text`, `file-pdf`, `file-edit`, `folder`, `copy`, `code`, `print` |
| **Navigation** | `arrow-up`, `arrow-down`, `arrow-left`, `arrow-right`, `arrow-up-right`, `chevron-up`, `chevron-down`, `chevron-left`, `chevron-right`, `chevron-double-left`, `chevron-double-right`, `expand`, `shrink`, `move`, `forward`, `reply`, `refresh` |
| **Communication** | `mail`, `comment`, `commenting`, `comments`, `receiver`, `phone`, `social`, `users`, `user`, `location`, `world`, `link`, `link-external`, `rss` |
| **Devices** | `desktop`, `laptop`, `tablet`, `server`, `database`, `cloud-upload`, `cloud-download`, `download`, `upload` |
| **Security** | `lock`, `unlock`, `key`, `shield`, `fingerprint`, `eye`, `eye-slash`, `sign-in`, `sign-out`, `credit-card` |
| **Editing** | `pencil`, `paint-bucket`, `bold`, `italic`, `strikethrough`, `quote-right`, `nut`, `crosshairs`, `trash`, `bag`, `cart`, `lifesaver` |
| **Brands** | `github`, `google`, `facebook`, `instagram`, `x`, `linkedin`, `youtube`, `tiktok`, `discord`, `whatsapp`, `telegram`, `signal`, `bluesky`, `mastodon`, `reddit`, `pinterest` |

### Custom Icons (registered via custom-icons.js)

`sun`, `moon`, `handbook`, `key`, `shield`, `fingerprint`

**Note:** Icon names like `shield-check` or `badge-check` are NOT available. Use the exact names listed above. Using an invalid name renders nothing (UIkit silently fails).

---

## Recipes: Common Section Patterns

### Light content section (default)

```json
{
  "meta": {
    "sectionStyle": {
      "layout": "normal",
      "intent": "content"
    }
  }
}
```

Result: White background, dark text, normal container width, standard padding.

### Muted feature section

```json
{
  "meta": {
    "sectionStyle": {
      "layout": "normal",
      "intent": "feature"
    }
  }
}
```

Result: Light grey background (`--surface-muted`), dark text, wide container, generous padding.

### Dark highlight section (brand colour)

```json
{
  "meta": {
    "sectionStyle": {
      "layout": "normal",
      "intent": "highlight",
      "background": {
        "mode": "color",
        "colorToken": "primary"
      }
    }
  }
}
```

Result: Brand primary colour background, white text, wide container, generous padding.

### Hero with dark background

```json
{
  "meta": {
    "sectionStyle": {
      "layout": "full",
      "intent": "hero",
      "background": {
        "mode": "color",
        "colorToken": "secondary"
      }
    }
  }
}
```

Result: Dark blended brand colour, white text, full-width container, extra-large padding.

### Minimal trust bar (no background)

```json
{
  "meta": {
    "sectionStyle": {
      "layout": "normal",
      "intent": "plain"
    }
  }
}
```

Result: Transparent background, dark text, no visual weight. Ideal for trust_bar / trust_band.

### Full-bleed with background image

```json
{
  "meta": {
    "sectionStyle": {
      "layout": "full",
      "background": {
        "mode": "image",
        "imageId": "/uploads/hero-bg.jpg",
        "attachment": "fixed",
        "overlay": 0.4
      }
    }
  }
}
```

Result: Full-width parallax background image with 40% dark overlay.

---

## Recommended Page Flow

For visual variety, alternate section intents and avoid consecutive identical backgrounds:

```
1. hero          → intent: hero,      bg: secondary     (dark)
2. stat_strip    → intent: plain                         (transparent)
3. feature_list  → intent: feature                       (light grey)
4. info_media    → intent: content                       (white)
5. info_media    → intent: feature,   bg: muted          (light grey)
6. process_steps → intent: content                       (white)
7. testimonial   → intent: highlight, bg: primary        (dark, brand)
8. faq           → intent: plain                         (transparent)
9. cta           → intent: highlight, bg: primary        (dark, brand)
```

**Key rules for visual rhythm:**
- Never place two `feature` (muted) sections back to back
- Alternate light (`content`/`plain`) with tinted (`feature`) and dark (`highlight`/`hero`)
- Use `info_media` (`image-left` / `image-right`) to break grid monotony
- Reserve `highlight` intent for sections that need to stand out (stats, CTAs, testimonials)

---

## Known Limitations

| # | Issue | Workaround |
|---|---|---|
| #5 | `audience_spotlight` tabs/tiles render as accordion | Use `feature_list` or `info_media` instead |
| #6 | German compound words break in 3-column grids | Use shorter titles or 2-column layouts (`info_media`) |
| #7 | Several `feature_list` variants render identically as 3-column grid | `detailed-cards`, `icon_grid`, `grid-bullets` look the same. Use `slider`, `text-columns`, or `clustered-tabs` for actual layout variety |
| #8 | `sectionAppearance` values `default`/`surface`/`contrast` produce no visual difference | Use `meta.sectionStyle` with explicit `intent` + `background` instead |
