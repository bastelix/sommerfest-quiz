# calServer Block Consolidation & Model Evaluation

## 1) Editorial consolidation plan
Goal: calm page flow without losing meaning; merge overlapping trust/capability/commercial sections while keeping all statements.

### Proposed consolidated block order
1. **Hero – MET/CAL promise**: Keep existing hero with dual CTA and device visual to frame migration story.
2. **Credibility runway**: Merge stat strip + trust timeline into a single trust block. Show metrics/marquee first, then the three-step timeline with reassurance and CTAs.
3. **MET/CAL migration deep dive**: Retain the detailed migration cards (eyebrow/lead + three bullet cards) as a focused follow-up.
4. **Capability & module suite**: Combine the capability grid and module switcher into one section: open with the grid-bullets overview, then group the four module showcases beneath it.
5. **Use-case gallery**: Keep the tabbed case stories as one block to illustrate industry breadth.
6. **Operations & plans**: Merge operating-model toggle with the subscription comparison cards. Present operating modes first (Cloud vs. On-Prem), then the three plan cards plus disclaimer.
7. **FAQ**: Keep as-is for objection handling.
8. **Closing CTA**: Keep the final split CTA.

### Source → target mapping (preserve all content)
- **Hero** → Hero (unchanged).
- **Stat strip + Trust timeline** → Credibility runway (metrics + marquee stacked above the three trust steps, closing note, and dual CTAs).
- **MET/CAL migration cards** → MET/CAL migration deep dive (no content changes).
- **Capability grid + Module switcher** → Capability & module suite (grid content followed by module items, keeping all bullets/media).
- **Use-case tabs** → Use-case gallery (unchanged structure, only simplified placement).
- **Operating models + Subscription cards** → Operations & plans (toggle content kept verbatim, followed by plan cards and disclaimer).
- **FAQ** → FAQ (unchanged).
- **Closing CTA** → Closing CTA (unchanged).

## 2) Block model evaluation
- **stat_strip (`three-up`)**: Keep as independent block type; reused across brands for concise reliability metrics and marquee claims.
- **process_steps (`timeline` trust journey)**: Treat as a variant of existing `process_steps`; no separate block type needed.
- **feature_list (`detailed-cards` for MET/CAL)**: Keep as variant of `feature_list`; needed for eyebrow/lead + bullet cards.
- **feature_list (`grid-bullets` capability grid)**: Keep as variant of `feature_list`; covers large capability matrices without new type.
- **system_module (`switcher` module showcase)**: Convert to an `info_media` variant (multi-item switcher) to avoid a standalone module block type while keeping media+bullet bundles.
- **case_showcase (`tabs` use cases)**: Convert into an `audience_spotlight` variant; semantics match multi-audience/use-case storytelling.
- **package_summary (`toggle` operating modes)**: Keep as variant of `package_summary`; mirrors dual-mode operating choice patterns.
- **package_summary (`comparison-cards` subscriptions)**: Keep as variant of `package_summary`; aligns with existing package/plan semantics.
- **faq (`accordion`)**: Keep as independent block type for objections/answers shared across pages.
