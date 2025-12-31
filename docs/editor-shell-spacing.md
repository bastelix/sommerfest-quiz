# Page editor inspector spacing analysis

## Step 1 – Vertical layout owner
- The inspector stack lives inside `[data-block-editor="true"]`, which is a grid with `gap: 14px` and `padding: 16px` that sits in the right column of `.content-editor-body` (`gap: 16px`).
- This grid already provides a vertical rhythm for the panel children (layout picker, appearance picker, block form sections).
- **Spacing owner that should own vertical rhythm:** `[data-block-editor="true"]` should be the single source of vertical spacing between inspector sections; child components should not add their own external margins.

## Step 2 – Additive spacing sources
- `[data-block-editor="true"]` grid gap (14px) + child margins:
  - `.layout-style-picker` adds `margin-bottom: 16px` on every section (`Layout-Stil`, `Abschnitts-Stil`, `Hintergrund`), stacking with the parent grid gap.
  - `.block-form-section` uses its own padding (12px) and internal `gap: 10px`, so when stacked next to layout pickers the outer margin + parent gap inflate the distance.
- Shell-level spacing also stacks: `.content-editor-body` uses `gap: 16px` while `[data-block-list="true"]` has `gap: 10px` and a border, so the gutter between list and inspector feels wider than intended.
- **Unintentional stacking:** parent grid gaps plus child margins (`.layout-style-picker` margin-bottom) are doubling the vertical whitespace between inspector sections; shell grid gap plus list border/padding add extra gutter between list and inspector.

## Step 3 – Selection outline impact
- Selected or focused inspector cards add visual thickness via `outline` and `box-shadow` (e.g., `.layout-style-card:focus-visible` and `[data-block-row][aria-selected="true"]`), which renders outside normal flow. This makes cards appear larger and can visually crowd adjacent elements even though layout spacing is unchanged.
- **Impact:** selection styling increases perceived size via shadows/outlines outside the flow.

## Step 4 – Layout contract
- The intended contract is a vertical stack owned by `[data-block-editor="true"]` (grid with gap). Child inspector sections currently behave as independent blocks that also define external margins.
- **Missing abstraction:** there is no single “stack” abstraction; margins on `.layout-style-picker` emulate stacking instead of letting the parent grid handle it.

## Minimal architectural fix
- Centralize vertical spacing in `[data-block-editor="true"]` by removing external margins from inspector children (`.layout-style-picker` and similar). Treat `[data-block-editor="true"]` as the sole stack controller for inspector sections so gaps come from one place instead of additive margins.
