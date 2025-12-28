# calServer Page Import (block-based)

## 1) Legacy page structure
Identified semantic sections from the legacy `content/marketing/calserver.html`:
- **Hero & high-level promise** (headline, subline, dual CTA) referencing MET/CAL hybrid migration.
- **Stat strip + marquee** (“calServer in Zahlen” with three metrics and running claims about hosting/compliance).
- **Trust timeline** (“So fühlt sich calServer im Alltag an”) – three guided steps, closing reassurance, dual CTA.
- **MET/CAL migration** (“Ein System. Klare Prozesse.”) – three detailed cards (Migration, Hybrid & METTEAM, Auditfähige Zertifikate) plus CTA to MET/CAL landing.
- **Feature spotlight** (“Funktionen, die den Alltag erleichtern”) – eleven capability cards with bullets.
- **Product modules** (“Module, die den Unterschied machen”) – four switchable media+bullet modules (Geräteverwaltung, Kalender, Auftrags-/Ticketverwaltung, Self-Service).
- **Use cases** (“Anwendungsfälle aus der Praxis”) – eight tabbed stories with badge, lead, body, key facts, and screenshot.
- **Operating models** (“Betriebsarten, die zu Ihnen passen”) – Cloud vs. On-Prem intros with paired highlight lists.
- **Abomodelle** – three plan cards (Standard, Performance, Enterprise) with features, notes, CTAs, and detail links.
- **FAQ** – five accordion items plus contact nudge.
- **Closing CTA** – demo/test dual CTA.

## 2) Gap analysis vs. existing blocks
Existing block types: `hero`, `feature_list`, `process_steps`, `info_media`, `proof`, `cta`.

| Legacy section | Fit with existing blocks | Gap / needed addition |
| --- | --- | --- |
| Hero | Fits `hero` (`media-right`). | None. |
| Stat strip + marquee | No metric block available. | Need `stat_strip` (`three-up`) with optional marquee claims. |
| Trust timeline | `process_steps` exists but lacks closing note + CTAs timeline style. | Add `timeline` variant with closing text and dual CTA support. |
| MET/CAL migration cards | `feature_list` works but needs bullet lists and eyebrow/lead fields. | Add `detailed-cards` variant supporting bullets and optional eyebrow/lead + CTA. |
| Feature spotlight (11 cards) | `feature_list` exists but current variants lack nested bullets for many items. | Add `grid-bullets` variant (multi-column cards with bullets). |
| Product modules (media switcher) | `info_media` handles single item only. | New `system_module` block (`switcher`) for multiple media+bullet entries. |
| Use cases | No block for multi-story tabs. | New `case_showcase` block (`tabs`) with badge/lead/body/bullets/key facts/media. |
| Operating models | Pricing-like toggle not covered. | `package_summary` block (`toggle`) for two-mode highlight lists. |
| Abomodelle | Pricing comparison absent. | `package_summary` block (`comparison-cards`) for parallel plans with badges/notes/CTAs. |
| FAQ | Missing block type. | `faq` block (`accordion`). |
| Closing CTA | Fits `cta` (`split`). | None. |

## 3) Missing block models / variants
- **stat_strip · `three-up`**
  - `data.metrics[]`: `id`, `value`, `label`, optional `asOf`, `tooltip`, `benefit`.
  - Optional `data.marquee[]` for short claims.
  - Captures concise reliability/compliance metrics.
- **process_steps · `timeline`**
  - `data.title`, optional `intro`.
  - `data.steps[]`: `id`, `title`, `description`.
  - Optional `data.closing` (`title`, `body`) and dual CTAs.
  - Extends steps with reassurance + CTA support.
- **feature_list · `detailed-cards`**
  - Adds `eyebrow`, optional `lead`, per-item `bullets[]`, optional block-level `cta`.
  - For deep-dive feature/migration cards.
- **feature_list · `grid-bullets`**
  - Multi-column capability cards with `description` + `bullets[]`; supports `subtitle`.
  - For larger capability grids formerly shown as sliders.
- **system_module · `switcher` (new block type)**
  - `data.title`, `subtitle`, `items[]` with `id`, `title`, `description`, `media{image,alt}`, `bullets[]`.
  - Groups core modules with paired media without UIkit switching logic.
- **case_showcase · `tabs` (new block type)**
  - `data.title`, `subtitle`, `cases[]` with `id`, `badge`, `title`, `lead`, `body`, `bullets[]`, `keyFacts[]`, `media{image,alt}`.
  - Represents tabbed industry use cases.
- **package_summary · `toggle` (new variant)**
  - `data.title`, `subtitle`, `options[]` each with `id`, `title`, `intro`, `highlights[]` (`title`, `bullets[]`).
  - For dual-mode (Cloud vs. On-Prem) operating models.
- **package_summary · `comparison-cards`**
  - `plans[]` with `id`, `title`, optional `badge`, `description`, `features[]`, `notes[]`, `primaryCta`, `secondaryCta`, optional `disclaimer` at block level.
  - Pricing-style plan overview without amounts.
- **faq · `accordion`**
  - `items[]` with `id`, `question`, `answer`; optional `followUp` link.
  - Standard question/answer list.

## 4) Import mapping
| Legacy section | Source content | Target block | Variant | Extraction rules |
| --- | --- | --- | --- | --- |
| Hero | Legacy hero headline/subheadline/CTAs, device screenshot | `hero` | `media-right` | Copy headline/subheadline; map primary/secondary CTAs; use device image + alt. |
| Stat strip + marquee | “calServer in Zahlen” metrics + marquee claims | `stat_strip` | `three-up` | Three metrics → `metrics[]` (`value`, `label`, `asOf`, `tooltip`, `benefit`); marquee badges → `marquee[]`. |
| Trust timeline | “So fühlt sich calServer im Alltag an” | `process_steps` | `timeline` | Intro → `intro`; steps 1–3 → `steps[]`; closing paragraph → `closing`; CTA buttons → `ctaPrimary`/`ctaSecondary`. |
| MET/CAL migration | MET/CAL cards | `feature_list` | `detailed-cards` | Eyebrow/title/lead from section header; each card → `items[]` (`title`, `description`, `bullets`); CTA link to MET/CAL page. |
| Feature spotlight | Slider cards (11 items) | `feature_list` | `grid-bullets` | Title/subtitle from section header; each card’s title/description/bullets → `items[]`. |
| Modules | Switcher with four modules | `system_module` | `switcher` | Section title/subtitle mapped; each module’s title/description/bullets/media → `items[]`. |
| Use cases | Eight tabbed stories | `case_showcase` | `tabs` | Section title/subtitle; for each tab map badge/title/lead/body/bullets/key facts/media. |
| Operating models | Cloud vs. On-Prem highlights | `package_summary` | `toggle` | Section title/subtitle; Cloud/On-Prem intros + highlight cards → `options[]` with `highlights[]`. |
| Abomodelle | Three pricing cards + detail links | `package_summary` | `comparison-cards` | Plans mapped to `plans[]` with badges, description, features, notes, CTAs; include disclaimer. |
| FAQ | Accordion list + follow-up link | `faq` | `accordion` | Questions/answers → `items[]`; contact prompt → `followUp`. |
| Closing CTA | Final dual CTA | `cta` | `split` | Title/body + primary/secondary CTAs. |

## 5) Resulting page content
Implemented in `content/marketing/calserver.page.json` as an ordered block list reflecting the mapping above.
