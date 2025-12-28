# Block System Design for QuizRace & calServer Pages

## Phase 1 – Page Structure Analysis
**Recurring patterns across pages**
- Hero with headline, supporting copy, and one or two primary calls to action.
- Feature/value highlights grouped into cards or bullets to articulate benefits.
- Social proof (logos, testimonials, or short quotes) to build trust.
- Calls to action repeated mid-page and/or near the end.
- Informational sections with mixed media (text + imagery/illustrations) to explain how the product works.

**QuizRace-specific patterns**
- Multi-segment feature storytelling: “How it works” step flow and repeated callouts for events, schools, and companies.
- Gamification proof points: usage stats, highlights of competitive play, and quick-start guidance.
- Package-like summaries for different buyer types (e.g., event kit, class module, team-builder), often with distinct CTAs per audience.

**calServer-specific patterns**
- Product operations narrative: calibration lifecycle, inventory management, and compliance messaging.
- Deep-dive feature rows combining iconography, metric callouts, and workflow explanations.
- Support/compliance reassurance (certifications, SLAs, contact prompt) rather than entertainment value.

**Shared semantics (neutral terms)**
- Hero
- Value proposition / feature overview
- Process / step-by-step explanation
- Audience or use-case spotlight
- Trust / proof (logos, testimonials, stats)
- Package or offering summary (lightweight pricing analogue)
- Informational content (text + media)
- Call to action

## Phase 2 – Block Type Definition
1. **hero** – Introduces the page with headline, subhead, primary/secondary CTAs, optional media. Used by both.
2. **feature_list** – Lists key benefits or capabilities with optional icons/media. Used by both.
3. **process_steps** – Explains a workflow or journey in ordered steps. Used by both.
4. **audience_spotlight** – Highlights specific audiences/use cases with tailored copy and CTAs. Used mainly by QuizRace, optional for calServer case studies.
5. **proof** – Conveys trust via logos, testimonials, quotes, metrics. Used by both.
6. **package_summary** – Presents grouped offerings or bundles (pricing-like without amounts). Used by QuizRace, optional for calServer service tiers.
7. **info_media** – Mixed content section pairing narrative text with imagery/video/diagram. Used by both.
8. **cta** – Focused call-to-action block to drive sign-up/demo/contact. Used by both.
9. **stat_strip** – Concise numeric highlights in a bar/cluster. Used by QuizRace (engagement stats) and calServer (uptime/compliance metrics).
10. **faq** – Collapsible list of questions/answers for objections. Used by calServer, optional on QuizRace.

## Phase 3 – Variant Design
**hero**
- `centered-cta` – Central text and buttons for quick action; QuizRace uses for excitement entry.
- `media-right` – Narrative left, media right to showcase product UI; calServer uses to show dashboards.
- `minimal` – Reduced chrome for focused message; can fit short-form QuizRace or calServer campaigns.

**feature_list**
- `grid-icons` – Icon + short description per item to scan benefits; QuizRace uses for playful advantages, calServer for capability overview.
- `text-columns` – Two-column bullet/text lists when icons are unnecessary; calServer uses for technical features.
- `card-stack` – Stacked cards with deeper copy per item; QuizRace uses for audience-specific messaging.

**process_steps**
- `numbered-horizontal` – Linear steps for quick how-it-works overview; QuizRace onboarding flow.
- `numbered-vertical` – Detailed vertical steps with room for copy; calServer calibration workflow.
- `media-per-step` – Each step paired with image; QuizRace demo flow, calServer device lifecycle.

**audience_spotlight**
- `tiles` – Parallel tiles for multiple audiences (events/schools/companies); QuizRace primary use.
- `carousel` – Optional rotating spotlights when space is constrained; secondary for QuizRace.
- `single-focus` – One audience deep dive; calServer could use for a key industry case study.

**proof**
- `logo-row` – Partner/customer logos; both use.
- `testimonial-card` – Quote with attribution and optional portrait; both use.
- `metric-callout` – Bold stats to reinforce value; QuizRace engagement, calServer reliability.

**package_summary**
- `comparison-cards` – Parallel cards describing bundles without prices; QuizRace event vs. school vs. company kits.
- `accordion-detail` – Expandable detail per package; calServer service tiers with compliance notes.
- `highlighted-pick` – Emphasises a recommended option; QuizRace most-popular kit.

**info_media**
- `image-left` – Media left, text right; calServer deep feature explainer.
- `image-right` – Media right, text left; QuizRace gameplay visuals.
- `stacked` – Text then media for mobile-first storytelling; both use.

**cta**
- `full-width` – Strong bar with single CTA; both use as footer prompt.
- `split` – Text + dual CTAs (demo/contact); calServer uses for sales/demo, QuizRace for play now vs. learn more.
- `inline` – Lightweight CTA embedded between sections; QuizRace mid-page prompt.

**stat_strip**
- `three-up` – Three to four concise metrics in a row; both use.
- `marquee` – Scrolling/clustered numbers for energy; QuizRace excitement.
- `paired` – Two larger stats for credibility; calServer uptime/compliance.

**faq**
- `accordion` – Standard expand/collapse list; both can use where included.
- `compact-list` – Short Q&A bullets for quick objections; QuizRace quick answers.

## Phase 4 – Design Tokens
Tokens influence renderer styling without entering block content. Each block references tokens; renderer applies theme per brand (QuizRace vs. calServer).
- **background:** `default` | `muted` | `primary`
- **spacing:** `small` | `normal` | `large`
- **width:** `narrow` | `normal` | `wide`
- **columns (where applicable):** `single` | `two` | `three` | `four`
- **accent palette:** `brandA` | `brandB` (mapped to QuizRace/calServer tones)

Render logic: tokens pick section padding, background color, and max-width, while variants control structure; content stays semantic and UIkit-free.

## Phase 5 – Page Mapping
### 1) Typical QuizRace Landing Page
1. `hero` – `centered-cta` (wide, primary background) to invite play/signup.
2. `stat_strip` – `marquee` (normal width) to show engagement numbers.
3. `feature_list` – `grid-icons` (wide) for fun benefits.
4. `process_steps` – `numbered-horizontal` (wide) to explain how to start a game.
5. `audience_spotlight` – `tiles` (wide) for events/schools/companies with tailored CTAs.
6. `package_summary` – `comparison-cards` (muted background) describing kits per audience.
7. `info_media` – `image-right` (wide) showing gameplay/screens.
8. `proof` – `testimonial-card` plus `logo-row` (muted) for trust.
9. `cta` – `inline` mid-page for quick start; later `full-width` to close.
10. `faq` – `compact-list` to address quick objections.

### 2) calServer Landing Page
1. `hero` – `media-right` (wide, primary background) showcasing dashboard.
2. `feature_list` – `text-columns` (wide) for core capabilities.
3. `stat_strip` – `paired` highlighting uptime/compliance.
4. `process_steps` – `numbered-vertical` (narrow) for calibration lifecycle.
5. `info_media` – `image-left` deep dive into inventory and calibration flows.
6. `proof` – `logo-row` plus `metric-callout` for enterprise credibility.
7. `package_summary` – `accordion-detail` for service tiers/support levels.
8. `audience_spotlight` – `single-focus` on regulated industries (optional but available).
9. `faq` – `accordion` handling compliance and integration questions.
10. `cta` – `split` encouraging demo vs. consultation.

## Why this system works
- **Scales to future pages:** Blocks are semantic and channel-agnostic; new pages mix existing types/variants without new bespoke blocks.
- **Avoids editor chaos:** Clear responsibilities per block type and limited variants reduce overlap; tokens centralize theming instead of ad-hoc styles.
- **Keeps UIkit isolated:** Content JSON stores semantics and tokens only; UIkit appears solely in the renderer that interprets tokens/variants.
