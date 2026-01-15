import { MARKETING_SCHEMES } from './marketing-schemes.js';

const DEFAULT_NAMESPACE = 'default';
const DEFAULT_TYPOGRAPHY_PRESET = 'modern';
const DEFAULT_CARD_STYLE = 'rounded';
const DEFAULT_BUTTON_STYLE = 'filled';
const TYPOGRAPHY_PRESETS = ['modern', 'classic', 'tech'];
const CARD_STYLES = ['rounded', 'square', 'pill'];
const BUTTON_STYLES = ['filled', 'outline', 'ghost'];

const designRegistry = {};

const normalizeNamespace = namespace => {
  if (!namespace) {
    return DEFAULT_NAMESPACE;
  }
  return String(namespace).trim().toLowerCase() || DEFAULT_NAMESPACE;
};

const mergeIntoRegistry = (namespace, design) => {
  const normalized = normalizeNamespace(namespace);
  if (!design || typeof design !== 'object') {
    return;
  }
  designRegistry[normalized] = design;
};

const resolveDesignRegistry = () => {
  return designRegistry;
};

const resolveDesignForNamespace = namespace => {
  const registry = resolveDesignRegistry();
  const normalized = normalizeNamespace(namespace);
  return registry[normalized] || null;
};

const resolveFirstValue = (...values) => {
  return values.find(value => value !== null && value !== undefined && value !== '');
};

const normalizeTokenValue = (value, allowedValues, fallback) => {
  if (typeof value !== 'string') {
    return fallback;
  }
  const normalized = value.replace(/['"]/g, '').trim().toLowerCase();
  return allowedValues.includes(normalized) ? normalized : fallback;
};

const syncComponentTokens = (source, target = source) => {
  if (typeof window === 'undefined' || !source || !target) {
    return;
  }
  const styles = window.getComputedStyle(source);
  if (!styles) {
    return;
  }
  target.dataset.typographyPreset = normalizeTokenValue(
    styles.getPropertyValue('--typography-preset'),
    TYPOGRAPHY_PRESETS,
    DEFAULT_TYPOGRAPHY_PRESET,
  );
  target.dataset.cardStyle = normalizeTokenValue(
    styles.getPropertyValue('--components-card-style'),
    CARD_STYLES,
    DEFAULT_CARD_STYLE,
  );
  target.dataset.buttonStyle = normalizeTokenValue(
    styles.getPropertyValue('--components-button-style'),
    BUTTON_STYLES,
    DEFAULT_BUTTON_STYLE,
  );
};

const mergeAppearance = (namespace, appearance = {}) => {
  const design = resolveDesignForNamespace(namespace);
  const designAppearance = design?.appearance || design || {};
  const configColors = design?.config?.colors || {};
  const baseAppearance = appearance && typeof appearance === 'object' ? appearance : {};
  const sectionDefaults = {
    surface: resolveFirstValue(
      designAppearance?.colors?.surface,
      designAppearance?.variables?.surface,
      configColors.surface,
      configColors.background,
      configColors.backgroundColor,
      baseAppearance?.colors?.surface,
      baseAppearance?.variables?.surface,
    ),
    muted: resolveFirstValue(
      designAppearance?.colors?.surfaceMuted,
      designAppearance?.colors?.muted,
      designAppearance?.variables?.surfaceMuted,
      designAppearance?.variables?.muted,
      configColors.surfaceMuted,
      configColors.muted,
      baseAppearance?.colors?.surfaceMuted,
      baseAppearance?.colors?.muted,
      baseAppearance?.variables?.surfaceMuted,
      baseAppearance?.variables?.muted,
    ),
    accent: resolveFirstValue(
      designAppearance?.colors?.accent,
      designAppearance?.colors?.primary,
      designAppearance?.variables?.accent,
      designAppearance?.variables?.primary,
      configColors.accent,
      configColors.primary,
      baseAppearance?.colors?.accent,
      baseAppearance?.colors?.primary,
      baseAppearance?.variables?.accent,
      baseAppearance?.variables?.primary,
    ),
  };
  const tokens = {
    ...(baseAppearance.tokens || {}),
    ...(designAppearance.tokens || {}),
  };
  const colors = {
    ...(baseAppearance.colors || {}),
    ...(designAppearance.colors || {}),
    ...(configColors || {}),
  };
  const variables = {
    ...(baseAppearance.variables || {}),
    ...(designAppearance.variables || {}),
  };

  if (sectionDefaults.surface) {
    variables.sectionDefaultSurface = sectionDefaults.surface;
    if (!colors.surface) {
      colors.surface = 'var(--section-default-surface)';
    }
    if (!variables.surface) {
      variables.surface = 'var(--section-default-surface)';
    }
  }

  if (sectionDefaults.muted) {
    variables.sectionDefaultMuted = sectionDefaults.muted;
    if (!colors.surfaceMuted) {
      colors.surfaceMuted = 'var(--section-default-muted)';
    }
    if (!colors.muted) {
      colors.muted = 'var(--section-default-muted)';
    }
    if (!variables.surfaceMuted) {
      variables.surfaceMuted = 'var(--section-default-muted)';
    }
  }

  if (sectionDefaults.accent) {
    variables.sectionDefaultAccent = sectionDefaults.accent;
    if (!colors.primary) {
      colors.primary = 'var(--section-default-accent)';
    }
    if (!colors.accent) {
      colors.accent = 'var(--section-default-accent)';
    }
    if (!colors.brandPrimary) {
      colors.brandPrimary = 'var(--section-default-accent)';
    }
    if (!variables.primary) {
      variables.primary = 'var(--section-default-accent)';
    }
    if (!variables.accent) {
      variables.accent = 'var(--section-default-accent)';
    }
    if (!variables.brandPrimary) {
      variables.brandPrimary = 'var(--section-default-accent)';
    }
  }

  return {
    ...baseAppearance,
    ...designAppearance,
    tokens,
    colors,
    variables,
  };
};

const isSectionDefaultReference = value => {
  return typeof value === 'string' && value.includes('--section-default-');
};

const applyColorsToRoot = (element, appearance) => {
  if (!element || typeof element?.style?.setProperty !== 'function') {
    return;
  }

  const tokens = appearance?.tokens || {};
  const colors = appearance?.colors || {};
  const brand = tokens.brand || {};

  const primary = resolveFirstValue(
    colors.primary,
    colors.brandPrimary,
    colors.brand_primary,
    appearance?.variables?.primary,
    appearance?.variables?.brandPrimary,
    brand.primary,
  );
  const secondary = resolveFirstValue(
    colors.secondary,
    colors.brandSecondary,
    colors.brand_secondary,
    appearance?.variables?.secondary,
    appearance?.variables?.brandSecondary,
    brand.secondary,
    brand.accent,
  );
  const accent = resolveFirstValue(
    colors.accent,
    colors.brandAccent,
    colors.brand_accent,
    appearance?.variables?.accent,
    appearance?.variables?.brandAccent,
    brand.accent,
    brand.secondary,
  );
  const surface = resolveFirstValue(
    colors.surface,
    appearance?.variables?.surface,
  );
  const muted = resolveFirstValue(
    colors.surfaceMuted,
    colors.muted,
    appearance?.variables?.surfaceMuted,
  );
  const topbarLight = resolveFirstValue(
    colors.topbar_light,
    colors.topbarLight,
    appearance?.variables?.topbarLight,
  );
  const topbarDark = resolveFirstValue(
    colors.topbar_dark,
    colors.topbarDark,
    appearance?.variables?.topbarDark,
  );
  const pageBackground = resolveFirstValue(
    colors.background,
    colors.backgroundColor,
    appearance?.variables?.background,
    appearance?.variables?.backgroundColor,
  );
  const onAccent = resolveFirstValue(
    colors.on_accent,
    colors.onAccent,
    colors.on_primary,
    colors.onPrimary,
    colors.contrastOnPrimary,
    colors.text_on_primary,
    colors.textOnPrimary,
    appearance?.variables?.onAccent,
    appearance?.variables?.onPrimary,
    appearance?.variables?.textOnPrimary,
  );
  const textOnSurface = resolveFirstValue(
    colors.textOnSurface,
    colors.text_on_surface,
    colors.marketingTextOnSurface,
    colors.marketing_text_on_surface,
    appearance?.variables?.textOnSurface,
    appearance?.variables?.text_on_surface,
    appearance?.variables?.marketingTextOnSurface,
    appearance?.variables?.marketing_text_on_surface,
  );
  const textOnBackground = resolveFirstValue(
    colors.textOnBackground,
    colors.text_on_background,
    colors.marketingTextOnBackground,
    colors.marketing_text_on_background,
    appearance?.variables?.textOnBackground,
    appearance?.variables?.text_on_background,
    appearance?.variables?.marketingTextOnBackground,
    appearance?.variables?.marketing_text_on_background,
  );
  const textMutedOnSurface = resolveFirstValue(
    colors.textMutedOnSurface,
    colors.text_muted_on_surface,
    colors.marketingTextMutedOnSurface,
    colors.marketing_text_muted_on_surface,
    appearance?.variables?.textMutedOnSurface,
    appearance?.variables?.text_muted_on_surface,
    appearance?.variables?.marketingTextMutedOnSurface,
    appearance?.variables?.marketing_text_muted_on_surface,
  );
  const textMutedOnBackground = resolveFirstValue(
    colors.textMutedOnBackground,
    colors.text_muted_on_background,
    colors.marketingTextMutedOnBackground,
    colors.marketing_text_muted_on_background,
    appearance?.variables?.textMutedOnBackground,
    appearance?.variables?.text_muted_on_background,
    appearance?.variables?.marketingTextMutedOnBackground,
    appearance?.variables?.marketing_text_muted_on_background,
  );
  const textOnSurfaceDark = resolveFirstValue(
    colors.textOnSurfaceDark,
    colors.text_on_surface_dark,
    colors.marketingTextOnSurfaceDark,
    colors.marketing_text_on_surface_dark,
    appearance?.variables?.textOnSurfaceDark,
    appearance?.variables?.text_on_surface_dark,
    appearance?.variables?.marketingTextOnSurfaceDark,
    appearance?.variables?.marketing_text_on_surface_dark,
  );
  const textOnBackgroundDark = resolveFirstValue(
    colors.textOnBackgroundDark,
    colors.text_on_background_dark,
    colors.marketingTextOnBackgroundDark,
    colors.marketing_text_on_background_dark,
    appearance?.variables?.textOnBackgroundDark,
    appearance?.variables?.text_on_background_dark,
    appearance?.variables?.marketingTextOnBackgroundDark,
    appearance?.variables?.marketing_text_on_background_dark,
  );
  const textMutedOnSurfaceDark = resolveFirstValue(
    colors.textMutedOnSurfaceDark,
    colors.text_muted_on_surface_dark,
    colors.marketingTextMutedOnSurfaceDark,
    colors.marketing_text_muted_on_surface_dark,
    appearance?.variables?.textMutedOnSurfaceDark,
    appearance?.variables?.text_muted_on_surface_dark,
    appearance?.variables?.marketingTextMutedOnSurfaceDark,
    appearance?.variables?.marketing_text_muted_on_surface_dark,
  );
  const textMutedOnBackgroundDark = resolveFirstValue(
    colors.textMutedOnBackgroundDark,
    colors.text_muted_on_background_dark,
    colors.marketingTextMutedOnBackgroundDark,
    colors.marketing_text_muted_on_background_dark,
    appearance?.variables?.textMutedOnBackgroundDark,
    appearance?.variables?.text_muted_on_background_dark,
    appearance?.variables?.marketingTextMutedOnBackgroundDark,
    appearance?.variables?.marketing_text_muted_on_background_dark,
  );

  const marketingScheme = resolveFirstValue(
    appearance?.variables?.marketingScheme,
    appearance?.variables?.marketing_scheme,
    colors.marketingScheme,
    colors.marketing_scheme,
  );
  const marketingSchemeValues = marketingScheme ? MARKETING_SCHEMES[marketingScheme] : null;

  const marketingPrimary = resolveFirstValue(
    marketingSchemeValues?.primary,
    colors.marketingPrimary,
    colors.marketing_primary,
    appearance?.variables?.marketingPrimary,
    appearance?.variables?.marketing_primary,
    primary,
    'var(--brand-primary)',
  );
  const marketingAccent = resolveFirstValue(
    marketingSchemeValues?.accent,
    colors.marketingAccent,
    colors.marketing_accent,
    appearance?.variables?.marketingAccent,
    appearance?.variables?.marketing_accent,
    accent,
    'var(--brand-accent)',
  );
  const marketingSecondary = resolveFirstValue(
    marketingSchemeValues?.accent,
    colors.marketingSecondary,
    colors.marketing_secondary,
    appearance?.variables?.marketingSecondary,
    appearance?.variables?.marketing_secondary,
    secondary,
    'var(--brand-secondary)',
  );
  const marketingOnAccent = resolveFirstValue(
    marketingSchemeValues?.onAccent,
    colors.marketingOnAccent,
    colors.marketing_on_accent,
    appearance?.variables?.marketingOnAccent,
    appearance?.variables?.marketing_on_accent,
    onAccent,
    'var(--text-on-primary)',
  );
  const marketingSurface = resolveFirstValue(
    marketingSchemeValues?.surface,
    colors.marketingSurface,
    colors.marketing_surface,
    appearance?.variables?.marketingSurface,
    appearance?.variables?.marketing_surface,
    surface,
    'var(--surface)',
  );
  const marketingSurfaceDark = resolveFirstValue(
    marketingSchemeValues?.surfaceDark,
    colors.marketingSurfaceDark,
    colors.marketing_surface_dark,
    colors.surfaceDark,
    colors.surface_dark,
    appearance?.variables?.marketingSurfaceDark,
    appearance?.variables?.marketing_surface_dark,
    appearance?.variables?.surfaceDark,
    appearance?.variables?.surface_dark,
  );
  const marketingBackground = resolveFirstValue(
    marketingSchemeValues?.background,
    colors.marketingBackground,
    colors.marketing_background,
    appearance?.variables?.marketingBackground,
    appearance?.variables?.marketing_background,
  );
  const marketingBackgroundDark = resolveFirstValue(
    marketingSchemeValues?.backgroundDark,
    colors.marketingBackgroundDark,
    colors.marketing_background_dark,
    colors.backgroundDark,
    colors.background_dark,
    appearance?.variables?.marketingBackgroundDark,
    appearance?.variables?.marketing_background_dark,
    appearance?.variables?.backgroundDark,
    appearance?.variables?.background_dark,
    marketingBackground,
    pageBackground,
  );
  const marketingMuted = resolveFirstValue(
    colors.marketingSurfaceMuted,
    colors.marketing_surface_muted,
    appearance?.variables?.marketingSurfaceMuted,
    appearance?.variables?.marketing_surface_muted,
    muted,
    'var(--surface-muted)',
  );
  const marketingSurfaceMutedDark = resolveFirstValue(
    colors.marketingSurfaceMutedDark,
    colors.marketing_surface_muted_dark,
    colors.surfaceMutedDark,
    colors.surface_muted_dark,
    appearance?.variables?.marketingSurfaceMutedDark,
    appearance?.variables?.marketing_surface_muted_dark,
    appearance?.variables?.surfaceMutedDark,
    appearance?.variables?.surface_muted_dark,
  );
  const marketingCardDark = resolveFirstValue(
    colors.marketingCardDark,
    colors.marketing_card_dark,
    colors.cardDark,
    colors.card_dark,
    appearance?.variables?.marketingCardDark,
    appearance?.variables?.marketing_card_dark,
    appearance?.variables?.cardDark,
    appearance?.variables?.card_dark,
  );
  const marketingText = resolveFirstValue(
    marketingSchemeValues?.textOnBackground,
    textOnBackground,
    textOnSurface,
  );
  const marketingTextOnSurface = resolveFirstValue(
    marketingSchemeValues?.textOnSurface,
    textOnSurface,
  );
  const marketingTextOnBackground = resolveFirstValue(
    marketingSchemeValues?.textOnBackground,
    textOnBackground,
  );
  const marketingTextMutedOnSurface = resolveFirstValue(
    marketingSchemeValues?.textMutedOnSurface,
    textMutedOnSurface,
  );
  const marketingTextMutedOnBackground = resolveFirstValue(
    marketingSchemeValues?.textMutedOnBackground,
    textMutedOnBackground,
  );
  const marketingTextOnSurfaceDark = resolveFirstValue(
    marketingSchemeValues?.textOnSurfaceDark,
    textOnSurfaceDark,
  );
  const marketingTextOnBackgroundDark = resolveFirstValue(
    marketingSchemeValues?.textOnBackgroundDark,
    textOnBackgroundDark,
  );
  const marketingTextMutedOnSurfaceDark = resolveFirstValue(
    marketingSchemeValues?.textMutedOnSurfaceDark,
    textMutedOnSurfaceDark,
  );
  const marketingTextMutedOnBackgroundDark = resolveFirstValue(
    marketingSchemeValues?.textMutedOnBackgroundDark,
    textMutedOnBackgroundDark,
  );
  const marketingInk = resolveFirstValue(
    marketingSchemeValues?.marketingInk,
    marketingSchemeValues?.ink,
    colors.marketingInk,
    colors.marketing_ink,
    appearance?.variables?.marketingInk,
    appearance?.variables?.marketing_ink,
  );
  const marketingSurfaceGlass = resolveFirstValue(
    marketingSchemeValues?.surfaceGlass,
    colors.marketingSurfaceGlass,
    colors.marketing_surface_glass,
    appearance?.variables?.marketingSurfaceGlass,
    appearance?.variables?.marketing_surface_glass,
  );
  const marketingSurfaceGlassDark = resolveFirstValue(
    marketingSchemeValues?.surfaceGlassDark,
    colors.marketingSurfaceGlassDark,
    colors.marketing_surface_glass_dark,
    appearance?.variables?.marketingSurfaceGlassDark,
    appearance?.variables?.marketing_surface_glass_dark,
  );
  const marketingSurfaceAccentSoft = resolveFirstValue(
    marketingSchemeValues?.surfaceAccentSoft,
    colors.marketingSurfaceAccentSoft,
    colors.marketing_surface_accent_soft,
    appearance?.variables?.marketingSurfaceAccentSoft,
    appearance?.variables?.marketing_surface_accent_soft,
  );
  const marketingBorderLight = resolveFirstValue(
    marketingSchemeValues?.borderLight,
    colors.marketingBorderLight,
    colors.marketing_border_light,
    appearance?.variables?.marketingBorderLight,
    appearance?.variables?.marketing_border_light,
  );
  const marketingRingStrong = resolveFirstValue(
    marketingSchemeValues?.ringStrong,
    colors.marketingRingStrong,
    colors.marketing_ring_strong,
    appearance?.variables?.marketingRingStrong,
    appearance?.variables?.marketing_ring_strong,
  );
  const marketingRingStrongDark = resolveFirstValue(
    marketingSchemeValues?.ringStrongDark,
    colors.marketingRingStrongDark,
    colors.marketing_ring_strong_dark,
    appearance?.variables?.marketingRingStrongDark,
    appearance?.variables?.marketing_ring_strong_dark,
  );
  const marketingOverlaySoft = resolveFirstValue(
    marketingSchemeValues?.overlaySoft,
    colors.marketingOverlaySoft,
    colors.marketing_overlay_soft,
    appearance?.variables?.marketingOverlaySoft,
    appearance?.variables?.marketing_overlay_soft,
  );
  const marketingOverlayStrong = resolveFirstValue(
    marketingSchemeValues?.overlayStrong,
    colors.marketingOverlayStrong,
    colors.marketing_overlay_strong,
    appearance?.variables?.marketingOverlayStrong,
    appearance?.variables?.marketing_overlay_strong,
  );
  const marketingOverlayHero = resolveFirstValue(
    marketingSchemeValues?.overlayHero,
    colors.marketingOverlayHero,
    colors.marketing_overlay_hero,
    appearance?.variables?.marketingOverlayHero,
    appearance?.variables?.marketing_overlay_hero,
  );
  const marketingShadowSoft = resolveFirstValue(
    marketingSchemeValues?.shadowSoft,
    colors.marketingShadowSoft,
    colors.marketing_shadow_soft,
    appearance?.variables?.marketingShadowSoft,
    appearance?.variables?.marketing_shadow_soft,
  );
  const marketingShadowDark = resolveFirstValue(
    marketingSchemeValues?.shadowDark,
    colors.marketingShadowDark,
    colors.marketing_shadow_dark,
    appearance?.variables?.marketingShadowDark,
    appearance?.variables?.marketing_shadow_dark,
  );
  const marketingShadowPanel = resolveFirstValue(
    marketingSchemeValues?.shadowPanel,
    colors.marketingShadowPanel,
    colors.marketing_shadow_panel,
    appearance?.variables?.marketingShadowPanel,
    appearance?.variables?.marketing_shadow_panel,
  );
  const marketingShadowCardBase = resolveFirstValue(
    marketingSchemeValues?.shadowCardBase,
    colors.marketingShadowCardBase,
    colors.marketing_shadow_card_base,
    appearance?.variables?.marketingShadowCardBase,
    appearance?.variables?.marketing_shadow_card_base,
  );
  const marketingShadowCardSoftBase = resolveFirstValue(
    marketingSchemeValues?.shadowCardSoftBase,
    colors.marketingShadowCardSoftBase,
    colors.marketing_shadow_card_soft_base,
    appearance?.variables?.marketingShadowCardSoftBase,
    appearance?.variables?.marketing_shadow_card_soft_base,
  );
  const marketingShadowCard = resolveFirstValue(
    marketingSchemeValues?.shadowCard,
    colors.marketingShadowCard,
    colors.marketing_shadow_card,
    appearance?.variables?.marketingShadowCard,
    appearance?.variables?.marketing_shadow_card,
  );
  const marketingShadowAccent = resolveFirstValue(
    marketingSchemeValues?.shadowAccent,
    colors.marketingShadowAccent,
    colors.marketing_shadow_accent,
    appearance?.variables?.marketingShadowAccent,
    appearance?.variables?.marketing_shadow_accent,
  );
  const marketingShadowCardSoft = resolveFirstValue(
    marketingSchemeValues?.shadowCardSoft,
    colors.marketingShadowCardSoft,
    colors.marketing_shadow_card_soft,
    appearance?.variables?.marketingShadowCardSoft,
    appearance?.variables?.marketing_shadow_card_soft,
  );
  const marketingShadowCardHover = resolveFirstValue(
    marketingSchemeValues?.shadowCardHover,
    colors.marketingShadowCardHover,
    colors.marketing_shadow_card_hover,
    appearance?.variables?.marketingShadowCardHover,
    appearance?.variables?.marketing_shadow_card_hover,
  );
  const marketingShadowHeroMockup = resolveFirstValue(
    marketingSchemeValues?.shadowHeroMockup,
    colors.marketingShadowHeroMockup,
    colors.marketing_shadow_hero_mockup,
    appearance?.variables?.marketingShadowHeroMockup,
    appearance?.variables?.marketing_shadow_hero_mockup,
  );
  const marketingShadowPill = resolveFirstValue(
    marketingSchemeValues?.shadowPill,
    colors.marketingShadowPill,
    colors.marketing_shadow_pill,
    appearance?.variables?.marketingShadowPill,
    appearance?.variables?.marketing_shadow_pill,
  );
  const marketingShadowCallout = resolveFirstValue(
    marketingSchemeValues?.shadowCallout,
    colors.marketingShadowCallout,
    colors.marketing_shadow_callout,
    appearance?.variables?.marketingShadowCallout,
    appearance?.variables?.marketing_shadow_callout,
  );
  const marketingShadowStat = resolveFirstValue(
    marketingSchemeValues?.shadowStat,
    colors.marketingShadowStat,
    colors.marketing_shadow_stat,
    appearance?.variables?.marketingShadowStat,
    appearance?.variables?.marketing_shadow_stat,
  );
  const marketingShadowStatAccent = resolveFirstValue(
    marketingSchemeValues?.shadowStatAccent,
    colors.marketingShadowStatAccent,
    colors.marketing_shadow_stat_accent,
    appearance?.variables?.marketingShadowStatAccent,
    appearance?.variables?.marketing_shadow_stat_accent,
  );
  const marketingLinkContrastLight = resolveFirstValue(
    marketingSchemeValues?.linkContrastLight,
    colors.marketingLinkContrastLight,
    colors.marketing_link_contrast_light,
    appearance?.variables?.marketingLinkContrastLight,
    appearance?.variables?.marketing_link_contrast_light,
  );
  const marketingLinkContrastDark = resolveFirstValue(
    marketingSchemeValues?.linkContrastDark,
    colors.marketingLinkContrastDark,
    colors.marketing_link_contrast_dark,
    appearance?.variables?.marketingLinkContrastDark,
    appearance?.variables?.marketing_link_contrast_dark,
  );
  const marketingTopbarTextContrastLight = resolveFirstValue(
    marketingSchemeValues?.topbarTextContrastLight,
    colors.marketingTopbarTextContrastLight,
    colors.marketing_topbar_text_contrast_light,
    appearance?.variables?.marketingTopbarTextContrastLight,
    appearance?.variables?.marketing_topbar_text_contrast_light,
  );
  const marketingTopbarTextContrastDark = resolveFirstValue(
    marketingSchemeValues?.topbarTextContrastDark,
    colors.marketingTopbarTextContrastDark,
    colors.marketing_topbar_text_contrast_dark,
    appearance?.variables?.marketingTopbarTextContrastDark,
    appearance?.variables?.marketing_topbar_text_contrast_dark,
  );
  const marketingTopbarDropBgContrastLight = resolveFirstValue(
    marketingSchemeValues?.topbarDropBgContrastLight,
    colors.marketingTopbarDropBgContrastLight,
    colors.marketing_topbar_drop_bg_contrast_light,
    appearance?.variables?.marketingTopbarDropBgContrastLight,
    appearance?.variables?.marketing_topbar_drop_bg_contrast_light,
  );
  const marketingTopbarDropBgContrastDark = resolveFirstValue(
    marketingSchemeValues?.topbarDropBgContrastDark,
    colors.marketingTopbarDropBgContrastDark,
    colors.marketing_topbar_drop_bg_contrast_dark,
    appearance?.variables?.marketingTopbarDropBgContrastDark,
    appearance?.variables?.marketing_topbar_drop_bg_contrast_dark,
  );
  const marketingTopbarBtnBorderContrastLight = resolveFirstValue(
    marketingSchemeValues?.topbarBtnBorderContrastLight,
    colors.marketingTopbarBtnBorderContrastLight,
    colors.marketing_topbar_btn_border_contrast_light,
    appearance?.variables?.marketingTopbarBtnBorderContrastLight,
    appearance?.variables?.marketing_topbar_btn_border_contrast_light,
  );
  const marketingTopbarBtnBorderContrastDark = resolveFirstValue(
    marketingSchemeValues?.topbarBtnBorderContrastDark,
    colors.marketingTopbarBtnBorderContrastDark,
    colors.marketing_topbar_btn_border_contrast_dark,
    appearance?.variables?.marketingTopbarBtnBorderContrastDark,
    appearance?.variables?.marketing_topbar_btn_border_contrast_dark,
  );
  const marketingTopbarFocusRingContrastLight = resolveFirstValue(
    marketingSchemeValues?.topbarFocusRingContrastLight,
    colors.marketingTopbarFocusRingContrastLight,
    colors.marketing_topbar_focus_ring_contrast_light,
    appearance?.variables?.marketingTopbarFocusRingContrastLight,
    appearance?.variables?.marketing_topbar_focus_ring_contrast_light,
  );
  const marketingTopbarFocusRingContrastDark = resolveFirstValue(
    marketingSchemeValues?.topbarFocusRingContrastDark,
    colors.marketingTopbarFocusRingContrastDark,
    colors.marketing_topbar_focus_ring_contrast_dark,
    appearance?.variables?.marketingTopbarFocusRingContrastDark,
    appearance?.variables?.marketing_topbar_focus_ring_contrast_dark,
  );
  const marketingDanger500 = resolveFirstValue(
    marketingSchemeValues?.danger500,
    colors.marketingDanger500,
    colors.marketing_danger_500,
    appearance?.variables?.marketingDanger500,
    appearance?.variables?.marketing_danger_500,
  );
  const marketingDanger600 = resolveFirstValue(
    marketingSchemeValues?.danger600,
    colors.marketingDanger600,
    colors.marketing_danger_600,
    appearance?.variables?.marketingDanger600,
    appearance?.variables?.marketing_danger_600,
  );
  const marketingWhite = resolveFirstValue(
    marketingSchemeValues?.white,
    colors.marketingWhite,
    colors.marketing_white,
    appearance?.variables?.marketingWhite,
    appearance?.variables?.marketing_white,
  );
  const marketingBlack = resolveFirstValue(
    marketingSchemeValues?.black,
    colors.marketingBlack,
    colors.marketing_black,
    appearance?.variables?.marketingBlack,
    appearance?.variables?.marketing_black,
  );
  const marketingBlackRgb = resolveFirstValue(
    marketingSchemeValues?.blackRgb,
    colors.marketingBlackRgb,
    colors.marketing_black_rgb,
    appearance?.variables?.marketingBlackRgb,
    appearance?.variables?.marketing_black_rgb,
  );

  const sectionDefaultSurface = resolveFirstValue(
    appearance?.variables?.sectionDefaultSurface,
    colors.sectionDefaultSurface,
    surface,
    colors.surface,
    appearance?.variables?.surface,
  );
  const sectionDefaultMuted = resolveFirstValue(
    appearance?.variables?.sectionDefaultMuted,
    colors.sectionDefaultMuted,
    muted,
    colors.surfaceMuted,
    colors.muted,
    appearance?.variables?.surfaceMuted,
  );
  const sectionDefaultAccent = resolveFirstValue(
    appearance?.variables?.sectionDefaultAccent,
    colors.sectionDefaultAccent,
    accent,
    colors.primary,
    colors.accent,
    appearance?.variables?.primary,
    appearance?.variables?.accent,
  );

  if (sectionDefaultSurface && !isSectionDefaultReference(sectionDefaultSurface)) {
    element.style.setProperty('--section-default-surface', sectionDefaultSurface);
  }
  if (sectionDefaultMuted && !isSectionDefaultReference(sectionDefaultMuted)) {
    element.style.setProperty('--section-default-muted', sectionDefaultMuted);
  }
  if (sectionDefaultAccent && !isSectionDefaultReference(sectionDefaultAccent)) {
    element.style.setProperty('--section-default-accent', sectionDefaultAccent);
  }

  if (primary) {
    element.style.setProperty('--brand-primary', primary);
    element.style.setProperty('--accent-primary', primary);
  }
  if (secondary) {
    element.style.setProperty('--brand-secondary', secondary);
    element.style.setProperty('--accent-secondary', secondary);
  }
  if (accent) {
    element.style.setProperty('--brand-accent', accent);
  }
  element.style.setProperty('--marketing-primary', marketingPrimary);
  element.style.setProperty('--marketing-accent', marketingAccent);
  element.style.setProperty('--marketing-secondary', marketingSecondary);
  element.style.setProperty('--marketing-on-accent', marketingOnAccent);
  if (marketingText) {
    element.style.setProperty('--marketing-text', marketingText);
  }
  if (marketingTextOnSurface) {
    element.style.setProperty('--marketing-text-on-surface', marketingTextOnSurface);
  }
  if (marketingTextOnBackground) {
    element.style.setProperty('--marketing-text-on-background', marketingTextOnBackground);
  }
  if (marketingTextMutedOnSurface) {
    element.style.setProperty('--marketing-text-muted-on-surface', marketingTextMutedOnSurface);
  }
  if (marketingTextMutedOnBackground) {
    element.style.setProperty('--marketing-text-muted-on-background', marketingTextMutedOnBackground);
  }
  if (marketingTextOnSurfaceDark) {
    element.style.setProperty('--marketing-text-on-surface-dark', marketingTextOnSurfaceDark);
  }
  if (marketingTextOnBackgroundDark) {
    element.style.setProperty('--marketing-text-on-background-dark', marketingTextOnBackgroundDark);
  }
  if (marketingTextMutedOnSurfaceDark) {
    element.style.setProperty('--marketing-text-muted-on-surface-dark', marketingTextMutedOnSurfaceDark);
  }
  if (marketingTextMutedOnBackgroundDark) {
    element.style.setProperty('--marketing-text-muted-on-background-dark', marketingTextMutedOnBackgroundDark);
  }
  if (marketingInk) {
    element.style.setProperty('--marketing-ink', marketingInk);
  }
  if (marketingSurfaceGlass) {
    element.style.setProperty('--marketing-surface-glass', marketingSurfaceGlass);
  }
  if (marketingSurfaceGlassDark) {
    element.style.setProperty('--marketing-surface-glass-dark', marketingSurfaceGlassDark);
  }
  if (marketingSurfaceAccentSoft) {
    element.style.setProperty('--marketing-surface-accent-soft', marketingSurfaceAccentSoft);
  }
  if (marketingBorderLight) {
    element.style.setProperty('--marketing-border-light', marketingBorderLight);
  }
  if (marketingRingStrong) {
    element.style.setProperty('--marketing-ring-strong', marketingRingStrong);
  }
  if (marketingRingStrongDark) {
    element.style.setProperty('--marketing-ring-strong-dark', marketingRingStrongDark);
  }
  if (marketingOverlaySoft) {
    element.style.setProperty('--marketing-overlay-soft', marketingOverlaySoft);
  }
  if (marketingOverlayStrong) {
    element.style.setProperty('--marketing-overlay-strong', marketingOverlayStrong);
  }
  if (marketingOverlayHero) {
    element.style.setProperty('--marketing-overlay-hero', marketingOverlayHero);
  }
  if (marketingShadowSoft) {
    element.style.setProperty('--marketing-shadow-soft', marketingShadowSoft);
  }
  if (marketingShadowDark) {
    element.style.setProperty('--marketing-shadow-dark', marketingShadowDark);
  }
  if (marketingShadowPanel) {
    element.style.setProperty('--marketing-shadow-panel', marketingShadowPanel);
  }
  if (marketingShadowCardBase) {
    element.style.setProperty('--marketing-shadow-card-base', marketingShadowCardBase);
  }
  if (marketingShadowCardSoftBase) {
    element.style.setProperty('--marketing-shadow-card-soft-base', marketingShadowCardSoftBase);
  }
  if (marketingShadowCard) {
    element.style.setProperty('--marketing-shadow-card', marketingShadowCard);
  }
  if (marketingShadowAccent) {
    element.style.setProperty('--marketing-shadow-accent', marketingShadowAccent);
  }
  if (marketingShadowCardSoft) {
    element.style.setProperty('--marketing-shadow-card-soft', marketingShadowCardSoft);
  }
  if (marketingShadowCardHover) {
    element.style.setProperty('--marketing-shadow-card-hover', marketingShadowCardHover);
  }
  if (marketingShadowHeroMockup) {
    element.style.setProperty('--marketing-shadow-hero-mockup', marketingShadowHeroMockup);
  }
  if (marketingShadowPill) {
    element.style.setProperty('--marketing-shadow-pill', marketingShadowPill);
  }
  if (marketingShadowCallout) {
    element.style.setProperty('--marketing-shadow-callout', marketingShadowCallout);
  }
  if (marketingShadowStat) {
    element.style.setProperty('--marketing-shadow-stat', marketingShadowStat);
  }
  if (marketingShadowStatAccent) {
    element.style.setProperty('--marketing-shadow-stat-accent', marketingShadowStatAccent);
  }
  if (marketingLinkContrastLight) {
    element.style.setProperty('--marketing-link-contrast-light', marketingLinkContrastLight);
  }
  if (marketingLinkContrastDark) {
    element.style.setProperty('--marketing-link-contrast-dark', marketingLinkContrastDark);
  }
  if (marketingTopbarTextContrastLight) {
    element.style.setProperty('--marketing-topbar-text-contrast-light', marketingTopbarTextContrastLight);
  }
  if (marketingTopbarTextContrastDark) {
    element.style.setProperty('--marketing-topbar-text-contrast-dark', marketingTopbarTextContrastDark);
  }
  if (marketingTopbarDropBgContrastLight) {
    element.style.setProperty('--marketing-topbar-drop-bg-contrast-light', marketingTopbarDropBgContrastLight);
  }
  if (marketingTopbarDropBgContrastDark) {
    element.style.setProperty('--marketing-topbar-drop-bg-contrast-dark', marketingTopbarDropBgContrastDark);
  }
  if (marketingTopbarBtnBorderContrastLight) {
    element.style.setProperty('--marketing-topbar-btn-border-contrast-light', marketingTopbarBtnBorderContrastLight);
  }
  if (marketingTopbarBtnBorderContrastDark) {
    element.style.setProperty('--marketing-topbar-btn-border-contrast-dark', marketingTopbarBtnBorderContrastDark);
  }
  if (marketingTopbarFocusRingContrastLight) {
    element.style.setProperty('--marketing-topbar-focus-ring-contrast-light', marketingTopbarFocusRingContrastLight);
  }
  if (marketingTopbarFocusRingContrastDark) {
    element.style.setProperty('--marketing-topbar-focus-ring-contrast-dark', marketingTopbarFocusRingContrastDark);
  }
  if (marketingDanger500) {
    element.style.setProperty('--marketing-danger-500', marketingDanger500);
  }
  if (marketingDanger600) {
    element.style.setProperty('--marketing-danger-600', marketingDanger600);
  }
  if (marketingWhite) {
    element.style.setProperty('--marketing-white', marketingWhite);
  }
  if (marketingBlack) {
    element.style.setProperty('--marketing-black', marketingBlack);
  }
  if (marketingBlackRgb) {
    element.style.setProperty('--marketing-black-rgb', marketingBlackRgb);
  }
  element.style.setProperty('--bg-page', pageBackground || 'var(--surface-page, var(--surface))');
  element.style.setProperty('--bg-section', 'var(--surface-section, var(--surface))');
  element.style.setProperty('--bg-card', 'var(--surface-card, var(--surface))');
  element.style.setProperty('--bg-accent', 'var(--brand-primary)');

  element.style.setProperty('--marketing-surface', marketingSurface);
  element.style.setProperty('--marketing-surface-muted', marketingMuted);
  if (marketingSurfaceDark) {
    element.style.setProperty('--marketing-surface-dark', marketingSurfaceDark);
  }
  if (marketingSurfaceMutedDark) {
    element.style.setProperty('--marketing-surface-muted-dark', marketingSurfaceMutedDark);
  }
  if (marketingCardDark) {
    element.style.setProperty('--marketing-card-dark', marketingCardDark);
  }
  element.style.setProperty(
    '--marketing-background',
    marketingBackground || 'var(--surface-page, var(--marketing-surface))',
  );
  if (marketingBackgroundDark) {
    element.style.setProperty('--marketing-background-dark', marketingBackgroundDark);
  }

  if (surface) {
    element.style.setProperty('--surface', surface);
  }

  if (muted) {
    element.style.setProperty('--surface-muted', muted);
  }

  if (topbarLight) {
    element.style.setProperty('--marketing-topbar-light', topbarLight);
    element.style.setProperty('--qr-landing-topbar-bg-light', topbarLight);
  }

  if (topbarDark) {
    element.style.setProperty('--marketing-topbar-dark', topbarDark);
    element.style.setProperty('--qr-landing-topbar-bg-dark', topbarDark);
  }
};

export function resolveNamespaceAppearance(namespace, appearance = {}) {
  const resolvedNamespace = normalizeNamespace(namespace);
  return mergeAppearance(resolvedNamespace, appearance);
}

export function applyNamespaceDesign(target, namespace = DEFAULT_NAMESPACE, appearance = {}, options = {}) {
  const resolvedNamespace = normalizeNamespace(namespace);
  const resolvedAppearance = resolveNamespaceAppearance(resolvedNamespace, appearance);
  const root = target || (typeof document !== 'undefined' ? document.documentElement : null);
  const presetTarget = options?.presetTarget || root;
  const tokenSource = options?.tokenSource || root;

  applyColorsToRoot(root, resolvedAppearance);
  syncComponentTokens(tokenSource, presetTarget);

  return resolvedAppearance;
}

export function resolveNamespaceDesign(namespace) {
  const resolvedNamespace = normalizeNamespace(namespace);
  const design = resolveDesignForNamespace(resolvedNamespace);
  return design || null;
}

export function registerNamespaceDesign(namespace, design) {
  mergeIntoRegistry(namespace, design);
}
