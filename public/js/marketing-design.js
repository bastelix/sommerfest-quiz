import { MARKETING_SCHEMES } from './components/marketing-schemes.js';

const DEFAULT_BRAND_PRIMARY = 'var(--brand-primary)';
const DEFAULT_BRAND_ACCENT = 'var(--brand-secondary)';
const DEFAULT_SURFACE = 'var(--surface-section)';
const DEFAULT_SURFACE_MUTED = 'var(--surface-muted)';
const DEFAULT_SURFACE_DARK = 'var(--surface-section)';
const DEFAULT_SURFACE_MUTED_DARK = 'var(--surface-muted)';
const DEFAULT_CARD_DARK = 'var(--surface-card)';
const DEFAULT_LAYOUT_PROFILE = 'standard';
const DEFAULT_TYPOGRAPHY_PRESET = 'modern';
const DEFAULT_CARD_STYLE = 'rounded';
const DEFAULT_BUTTON_STYLE = 'filled';
const LAYOUT_PROFILES = ['standard', 'wide', 'narrow'];
const TYPOGRAPHY_PRESETS = ['modern', 'classic', 'tech'];
const CARD_STYLES = ['rounded', 'square', 'pill'];
const BUTTON_STYLES = ['filled', 'outline', 'ghost'];


const parseJson = value => {
  if (!value || typeof value !== 'string') {
    return null;
  }
  try {
    return JSON.parse(value);
  } catch (error) {
    return null;
  }
};

const resolveFirstValue = (...values) => {
  return values.find(value => value !== null && value !== undefined && value !== '');
};

const isSectionDefaultReference = value => {
  return typeof value === 'string' && value.includes('--section-default-');
};

const resolveWindowAppearance = () => {
  if (typeof window === 'undefined') {
    return null;
  }
  const data = window.pageAppearance;
  if (!data) {
    return null;
  }
  if (typeof data === 'string') {
    return parseJson(data);
  }
  return data;
};

const resolveDataAttributes = container => {
  if (!container) {
    return { appearance: null, config: null };
  }

  return {
    appearance: parseJson(container.dataset.appearance),
    config: parseJson(container.dataset.config),
  };
};

const resolveFallbackToken = (root, cssVar, fallback) => {
  if (!root) {
    return fallback;
  }
  const value = getComputedStyle(root).getPropertyValue(cssVar).trim();
  return value || fallback;
};

const normalizeTokenValue = (value, allowedValues, fallback) => {
  if (typeof value !== 'string') {
    return fallback;
  }
  const normalized = value.replace(/['"]/g, '').trim().toLowerCase();
  return allowedValues.includes(normalized) ? normalized : fallback;
};

const normalizeMarketingScheme = value => {
  if (typeof value !== 'string') {
    return null;
  }
  const normalized = value.replace(/['"]/g, '').trim().toLowerCase();
  if (!normalized) {
    return null;
  }
  const mapped = normalized === 'monochrom' ? 'monochrome' : normalized;
  return Object.prototype.hasOwnProperty.call(MARKETING_SCHEMES, mapped) ? mapped : null;
};

const applyComponentTokens = root => {
  if (!root || typeof window === 'undefined') {
    return;
  }
  const styles = window.getComputedStyle(root);
  if (!styles) {
    return;
  }
  root.dataset.typographyPreset = normalizeTokenValue(
    styles.getPropertyValue('--typography-preset'),
    TYPOGRAPHY_PRESETS,
    DEFAULT_TYPOGRAPHY_PRESET,
  );
  root.dataset.cardStyle = normalizeTokenValue(
    styles.getPropertyValue('--components-card-style'),
    CARD_STYLES,
    DEFAULT_CARD_STYLE,
  );
  root.dataset.buttonStyle = normalizeTokenValue(
    styles.getPropertyValue('--components-button-style'),
    BUTTON_STYLES,
    DEFAULT_BUTTON_STYLE,
  );
};

const resolveMarketingAppearance = () => {
  const windowAppearance = resolveWindowAppearance();
  const dataAttributes = resolveDataAttributes(
    typeof document === 'undefined' ? null : document.getElementById('marketing-design-data'),
  );
  const baseAppearance = windowAppearance?.appearance || windowAppearance || dataAttributes.appearance || {};
  const config = windowAppearance?.config || dataAttributes.config || {};

  return {
    appearance: baseAppearance,
    config,
  };
};

const applyMarketingDesign = () => {
  if (typeof document === 'undefined') {
    return;
  }
  const root = document.documentElement;
  if (!root?.style?.setProperty) {
    return;
  }
  const initialStyles = window.getComputedStyle(root);
  const getInitialTokenValue = variableName => initialStyles.getPropertyValue(variableName).trim();
  const shouldSkipTokenWrite = (variableName, hasExplicitOverride) =>
    !hasExplicitOverride && getInitialTokenValue(variableName);
  const setTokenProperty = (variableName, value, hasExplicitOverride) => {
    if (!value) {
      return;
    }
    if (shouldSkipTokenWrite(variableName, hasExplicitOverride)) {
      return;
    }
    root.style.setProperty(variableName, value);
  };
  if (root.getAttribute('data-lock-design') === 'true') {
    applyComponentTokens(root);
    return;
  }

  const { appearance, config } = resolveMarketingAppearance();
  const tokens = appearance?.tokens || {};
  const colors = appearance?.colors || {};
  const variables = appearance?.variables || {};
  const configColors = config?.colors || {};
  const brand = tokens.brand || {};
  const layout = tokens.layout || {};
  const typography = tokens.typography || {};
  const components = tokens.components || {};

  const fallbackPrimary = resolveFallbackToken(
    root,
    '--marketing-primary',
    resolveFallbackToken(root, '--brand-primary', DEFAULT_BRAND_PRIMARY),
  );
  const fallbackLayoutProfile = resolveFallbackToken(root, '--layout-profile', DEFAULT_LAYOUT_PROFILE);
  const fallbackTypographyPreset = resolveFallbackToken(root, '--typography-preset', DEFAULT_TYPOGRAPHY_PRESET);
  const fallbackCardStyle = resolveFallbackToken(root, '--components-card-style', DEFAULT_CARD_STYLE);
  const fallbackButtonStyle = resolveFallbackToken(root, '--components-button-style', DEFAULT_BUTTON_STYLE);
  const fallbackAccent = resolveFallbackToken(
    root,
    '--marketing-accent',
    resolveFallbackToken(root, '--brand-accent', DEFAULT_BRAND_ACCENT),
  );
  const fallbackSecondary = resolveFallbackToken(
    root,
    '--marketing-secondary',
    resolveFallbackToken(root, '--brand-secondary', fallbackAccent),
  );
  const fallbackSurface = resolveFallbackToken(
    root,
    '--marketing-surface',
    resolveFallbackToken(root, '--surface', resolveFallbackToken(root, '--surface-card', DEFAULT_SURFACE)),
  );
  const fallbackBackground = resolveFallbackToken(
    root,
    '--marketing-background',
    resolveFallbackToken(root, '--bg-page', fallbackSurface),
  );
  const fallbackMarketingText = resolveFallbackToken(
    root,
    '--marketing-text',
    resolveFallbackToken(root, '--text-primary', ''),
  );
  const fallbackMuted = resolveFallbackToken(
    root,
    '--marketing-surface-muted',
    resolveFallbackToken(root, '--surface-muted', DEFAULT_SURFACE_MUTED),
  );
  const fallbackTextOnSurface = resolveFallbackToken(
    root,
    '--marketing-text-on-surface',
    resolveFallbackToken(root, '--text-primary', ''),
  );
  const fallbackTextOnBackground = resolveFallbackToken(
    root,
    '--marketing-text-on-background',
    fallbackTextOnSurface,
  );
  const fallbackTextMutedOnSurface = resolveFallbackToken(
    root,
    '--marketing-text-muted-on-surface',
    fallbackTextOnSurface,
  );
  const fallbackTextMutedOnBackground = resolveFallbackToken(
    root,
    '--marketing-text-muted-on-background',
    fallbackTextMutedOnSurface,
  );
  const fallbackTextOnSurfaceDark = resolveFallbackToken(
    root,
    '--marketing-text-on-surface-dark',
    fallbackTextOnSurface,
  );
  const fallbackTextOnBackgroundDark = resolveFallbackToken(
    root,
    '--marketing-text-on-background-dark',
    fallbackTextOnSurfaceDark,
  );
  const fallbackTextMutedOnSurfaceDark = resolveFallbackToken(
    root,
    '--marketing-text-muted-on-surface-dark',
    fallbackTextOnSurfaceDark,
  );
  const fallbackTextMutedOnBackgroundDark = resolveFallbackToken(
    root,
    '--marketing-text-muted-on-background-dark',
    fallbackTextMutedOnSurfaceDark,
  );
  const fallbackSurfaceDark = resolveFallbackToken(
    root,
    '--marketing-surface-dark',
    resolveFallbackToken(root, '--surface-dark', DEFAULT_SURFACE_DARK),
  );
  const fallbackSurfaceMutedDark = resolveFallbackToken(
    root,
    '--marketing-surface-muted-dark',
    resolveFallbackToken(root, '--surface-muted-dark', DEFAULT_SURFACE_MUTED_DARK),
  );
  const fallbackCardDark = resolveFallbackToken(
    root,
    '--marketing-card-dark',
    resolveFallbackToken(root, '--marketing-surface-glass-dark', DEFAULT_CARD_DARK),
  );
  const fallbackTopbarLight = resolveFallbackToken(
    root,
    '--marketing-topbar-light',
    resolveFallbackToken(root, '--qr-landing-topbar-bg-light', ''),
  );
  const fallbackTopbarDark = resolveFallbackToken(
    root,
    '--marketing-topbar-dark',
    resolveFallbackToken(root, '--qr-landing-topbar-bg-dark', ''),
  );
  const fallbackOnAccent = resolveFallbackToken(
    root,
    '--marketing-on-accent',
    resolveFallbackToken(root, '--text-on-primary', ''),
  );
  const fallbackInk = resolveFallbackToken(root, '--marketing-ink', '');
  const fallbackSurfaceGlass = resolveFallbackToken(root, '--marketing-surface-glass', '');
  const fallbackSurfaceGlassDark = resolveFallbackToken(root, '--marketing-surface-glass-dark', '');
  const fallbackSurfaceAccentSoft = resolveFallbackToken(root, '--marketing-surface-accent-soft', '');
  const fallbackBorderLight = resolveFallbackToken(root, '--marketing-border-light', '');
  const fallbackBorder = resolveFallbackToken(root, '--marketing-border', '');
  const fallbackBorderMuted = resolveFallbackToken(root, '--marketing-border-muted', '');
  const fallbackTextEmphasis = resolveFallbackToken(root, '--marketing-text-emphasis', '');
  const fallbackTextInverse = resolveFallbackToken(root, '--marketing-text-inverse', '');
  const fallbackLink = resolveFallbackToken(root, '--marketing-link', '');
  const fallbackLinkHover = resolveFallbackToken(root, '--marketing-link-hover', '');
  const fallbackSuccess = resolveFallbackToken(root, '--marketing-success', '');
  const fallbackWarning = resolveFallbackToken(root, '--marketing-warning', '');
  const fallbackDanger = resolveFallbackToken(root, '--marketing-danger', '');
  const fallbackRingStrong = resolveFallbackToken(root, '--marketing-ring-strong', '');
  const fallbackRingStrongDark = resolveFallbackToken(root, '--marketing-ring-strong-dark', '');
  const fallbackOverlaySoft = resolveFallbackToken(root, '--marketing-overlay-soft', '');
  const fallbackOverlayStrong = resolveFallbackToken(root, '--marketing-overlay-strong', '');
  const fallbackOverlayHero = resolveFallbackToken(root, '--marketing-overlay-hero', '');
  const fallbackShadowSoft = resolveFallbackToken(root, '--marketing-shadow-soft', '');
  const fallbackShadowDark = resolveFallbackToken(root, '--marketing-shadow-dark', '');
  const fallbackShadowPanel = resolveFallbackToken(root, '--marketing-shadow-panel', '');
  const fallbackShadowCardBase = resolveFallbackToken(root, '--marketing-shadow-card-base', '');
  const fallbackShadowCardSoftBase = resolveFallbackToken(root, '--marketing-shadow-card-soft-base', '');
  const fallbackShadowCard = resolveFallbackToken(root, '--marketing-shadow-card', '');
  const fallbackShadowAccent = resolveFallbackToken(root, '--marketing-shadow-accent', '');
  const fallbackShadowCardSoft = resolveFallbackToken(root, '--marketing-shadow-card-soft', '');
  const fallbackShadowCardHover = resolveFallbackToken(root, '--marketing-shadow-card-hover', '');
  const fallbackShadowHeroMockup = resolveFallbackToken(root, '--marketing-shadow-hero-mockup', '');
  const fallbackShadowPill = resolveFallbackToken(root, '--marketing-shadow-pill', '');
  const fallbackShadowCallout = resolveFallbackToken(root, '--marketing-shadow-callout', '');
  const fallbackShadowStat = resolveFallbackToken(root, '--marketing-shadow-stat', '');
  const fallbackShadowStatAccent = resolveFallbackToken(root, '--marketing-shadow-stat-accent', '');
  const fallbackLinkContrastLight = resolveFallbackToken(root, '--marketing-link-contrast-light', '');
  const fallbackLinkContrastDark = resolveFallbackToken(root, '--marketing-link-contrast-dark', '');
  const fallbackTopbarTextContrastLight = resolveFallbackToken(
    root,
    '--marketing-topbar-text-contrast-light',
    '',
  );
  const fallbackTopbarTextContrastDark = resolveFallbackToken(
    root,
    '--marketing-topbar-text-contrast-dark',
    '',
  );
  const fallbackTopbarDropBgContrastLight = resolveFallbackToken(
    root,
    '--marketing-topbar-drop-bg-contrast-light',
    '',
  );
  const fallbackTopbarDropBgContrastDark = resolveFallbackToken(
    root,
    '--marketing-topbar-drop-bg-contrast-dark',
    '',
  );
  const fallbackTopbarBtnBorderContrastLight = resolveFallbackToken(
    root,
    '--marketing-topbar-btn-border-contrast-light',
    '',
  );
  const fallbackTopbarBtnBorderContrastDark = resolveFallbackToken(
    root,
    '--marketing-topbar-btn-border-contrast-dark',
    '',
  );
  const fallbackTopbarFocusRingContrastLight = resolveFallbackToken(
    root,
    '--marketing-topbar-focus-ring-contrast-light',
    '',
  );
  const fallbackTopbarFocusRingContrastDark = resolveFallbackToken(
    root,
    '--marketing-topbar-focus-ring-contrast-dark',
    '',
  );
  const fallbackDanger500 = resolveFallbackToken(root, '--marketing-danger-500', '');
  const fallbackDanger600 = resolveFallbackToken(root, '--marketing-danger-600', '');
  const fallbackMarketingWhite = resolveFallbackToken(root, '--marketing-white', '');
  const fallbackMarketingBlack = resolveFallbackToken(root, '--marketing-black', '');
  const fallbackMarketingBlackRgb = resolveFallbackToken(root, '--marketing-black-rgb', '');

  const normalizedLayoutProfile = normalizeTokenValue(
    layout.profile,
    LAYOUT_PROFILES,
    fallbackLayoutProfile,
  );
  const normalizedTypographyPreset = normalizeTokenValue(
    typography.preset,
    TYPOGRAPHY_PRESETS,
    fallbackTypographyPreset,
  );
  const normalizedCardStyle = normalizeTokenValue(
    components.cardStyle,
    CARD_STYLES,
    fallbackCardStyle,
  );
  const normalizedButtonStyle = normalizeTokenValue(
    components.buttonStyle,
    BUTTON_STYLES,
    fallbackButtonStyle,
  );

  const marketingScheme = normalizeMarketingScheme(
    resolveFirstValue(
      variables.marketingScheme,
      variables.marketing_scheme,
      colors.marketingScheme,
      colors.marketing_scheme,
      configColors.marketingScheme,
      configColors.marketing_scheme,
    ),
  );
  const marketingSchemeValues = marketingScheme ? MARKETING_SCHEMES[marketingScheme] : null;
  if (marketingScheme) {
    root.dataset.marketingScheme = marketingScheme;
  } else {
    delete root.dataset.marketingScheme;
  }
  const hasMarketingPrimaryOverride = Boolean(
    marketingSchemeValues?.primary ||
      configColors.primary ||
      configColors.marketingPrimary ||
      configColors.marketing_primary ||
      variables.marketingPrimary ||
      variables.marketing_primary,
  );
  const hasMarketingAccentOverride = Boolean(
    marketingSchemeValues?.accent ||
      configColors.accent ||
      configColors.secondary ||
      configColors.marketingAccent ||
      configColors.marketing_accent ||
      variables.marketingAccent ||
      variables.marketing_accent,
  );
  const hasMarketingSecondaryOverride = Boolean(
    marketingSchemeValues?.accent ||
      configColors.secondary ||
      configColors.marketingSecondary ||
      configColors.marketing_secondary ||
      variables.marketingSecondary ||
      variables.marketing_secondary,
  );
  const hasBrandPrimaryOverride = Boolean(
    configColors.primary || configColors.brandPrimary || configColors.brand_primary,
  );
  const hasBrandAccentOverride = Boolean(
    configColors.accent || configColors.brandAccent || configColors.brand_accent,
  );
  const hasBrandSecondaryOverride = Boolean(
    configColors.secondary || configColors.brandSecondary || configColors.brand_secondary,
  );
  const marketingBackgroundToken =
    configColors['--marketing-background'] ||
    colors['--marketing-background'] ||
    variables['--marketing-background'];
  const marketingBackgroundDarkToken =
    configColors['--marketing-background-dark'] ||
    colors['--marketing-background-dark'] ||
    variables['--marketing-background-dark'];
  const marketingTextToken =
    configColors['--marketing-text'] ||
    colors['--marketing-text'] ||
    variables['--marketing-text'];
  const marketingTextOnSurfaceToken =
    configColors['--marketing-text-on-surface'] ||
    colors['--marketing-text-on-surface'] ||
    variables['--marketing-text-on-surface'];
  const marketingTextOnBackgroundToken =
    configColors['--marketing-text-on-background'] ||
    colors['--marketing-text-on-background'] ||
    variables['--marketing-text-on-background'];
  const marketingTextMutedOnSurfaceToken =
    configColors['--marketing-text-muted-on-surface'] ||
    colors['--marketing-text-muted-on-surface'] ||
    variables['--marketing-text-muted-on-surface'];
  const marketingTextMutedOnBackgroundToken =
    configColors['--marketing-text-muted-on-background'] ||
    colors['--marketing-text-muted-on-background'] ||
    variables['--marketing-text-muted-on-background'];
  const marketingTextOnSurfaceDarkToken =
    configColors['--marketing-text-on-surface-dark'] ||
    colors['--marketing-text-on-surface-dark'] ||
    variables['--marketing-text-on-surface-dark'];
  const marketingTextOnBackgroundDarkToken =
    configColors['--marketing-text-on-background-dark'] ||
    colors['--marketing-text-on-background-dark'] ||
    variables['--marketing-text-on-background-dark'];
  const marketingTextMutedOnSurfaceDarkToken =
    configColors['--marketing-text-muted-on-surface-dark'] ||
    colors['--marketing-text-muted-on-surface-dark'] ||
    variables['--marketing-text-muted-on-surface-dark'];
  const marketingTextMutedOnBackgroundDarkToken =
    configColors['--marketing-text-muted-on-background-dark'] ||
    colors['--marketing-text-muted-on-background-dark'] ||
    variables['--marketing-text-muted-on-background-dark'];

  const primary =
    marketingSchemeValues?.primary ||
    configColors.primary ||
    colors.primary ||
    brand.primary ||
    fallbackPrimary;
  const accent =
    marketingSchemeValues?.accent ||
    configColors.accent ||
    configColors.secondary ||
    colors.accent ||
    colors.secondary ||
    brand.accent ||
    fallbackAccent;
  const secondary =
    marketingSchemeValues?.accent ||
    configColors.secondary ||
    colors.secondary ||
    brand.secondary ||
    brand.accent ||
    fallbackSecondary ||
    accent;

  const surface =
    marketingSchemeValues?.surface ||
    configColors.surface ||
    colors.surface ||
    variables.surface ||
    fallbackSurface;
  const background =
    marketingBackgroundToken ||
    marketingSchemeValues?.background ||
    configColors.background ||
    configColors.backgroundColor ||
    colors.background ||
    colors.backgroundColor ||
    variables.background ||
    variables.backgroundColor ||
    fallbackBackground;
  const marketingPrimary = resolveFirstValue(
    marketingSchemeValues?.primary,
    configColors.marketingPrimary,
    configColors.marketing_primary,
    colors.marketingPrimary,
    colors.marketing_primary,
    variables.marketingPrimary,
    variables.marketing_primary,
    primary,
  );
  const marketingAccent = resolveFirstValue(
    marketingSchemeValues?.accent,
    configColors.marketingAccent,
    configColors.marketing_accent,
    colors.marketingAccent,
    colors.marketing_accent,
    variables.marketingAccent,
    variables.marketing_accent,
    accent,
  );
  const marketingSecondary = resolveFirstValue(
    marketingSchemeValues?.secondary,
    marketingSchemeValues?.accent,
    configColors.marketingSecondary,
    configColors.marketing_secondary,
    colors.marketingSecondary,
    colors.marketing_secondary,
    variables.marketingSecondary,
    variables.marketing_secondary,
    secondary,
  );
  const onAccent =
    marketingSchemeValues?.onAccent ||
    configColors.onAccent ||
    configColors.on_accent ||
    configColors.contrastOnPrimary ||
    configColors.onPrimary ||
    configColors.on_primary ||
    colors.onAccent ||
    colors.on_accent ||
    colors.contrastOnPrimary ||
    colors.onPrimary ||
    colors.on_primary ||
    colors.textOnPrimary ||
    colors.text_on_primary ||
    variables.onAccent ||
    variables.onPrimary ||
    variables.textOnPrimary ||
    fallbackOnAccent;
  const marketingOnAccent = resolveFirstValue(
    marketingSchemeValues?.onAccent,
    configColors.marketingOnAccent,
    configColors.marketing_on_accent,
    colors.marketingOnAccent,
    colors.marketing_on_accent,
    variables.marketingOnAccent,
    variables.marketing_on_accent,
    onAccent,
  );
  const marketingSurface = resolveFirstValue(
    marketingSchemeValues?.surface,
    configColors.marketingSurface,
    configColors.marketing_surface,
    colors.marketingSurface,
    colors.marketing_surface,
    variables.marketingSurface,
    variables.marketing_surface,
    surface,
  );
  const marketingBackground = resolveFirstValue(
    marketingBackgroundToken,
    marketingSchemeValues?.background,
    configColors.marketingBackground,
    configColors.marketing_background,
    colors.marketingBackground,
    colors.marketing_background,
    variables.marketingBackground,
    variables.marketing_background,
    background,
  );
  const marketingBackgroundDark = resolveFirstValue(
    marketingBackgroundDarkToken,
    marketingSchemeValues?.backgroundDark,
    configColors.marketingBackgroundDark,
    configColors.marketing_background_dark,
    configColors.backgroundDark,
    configColors.background_dark,
    colors.marketingBackgroundDark,
    colors.marketing_background_dark,
    colors.backgroundDark,
    colors.background_dark,
    variables.marketingBackgroundDark,
    variables.marketing_background_dark,
    variables.backgroundDark,
    variables.background_dark,
    marketingBackground,
    background,
  );
  const surfaceMuted =
    marketingSchemeValues?.surfaceMuted ||
    configColors.surfaceMuted ||
    colors.surfaceMuted ||
    colors.muted ||
    variables.surfaceMuted ||
    fallbackMuted;
  const marketingSurfaceMuted = resolveFirstValue(
    marketingSchemeValues?.surfaceMuted,
    configColors.marketingSurfaceMuted,
    configColors.marketing_surface_muted,
    colors.marketingSurfaceMuted,
    colors.marketing_surface_muted,
    variables.marketingSurfaceMuted,
    variables.marketing_surface_muted,
    surfaceMuted,
  );
  const topbarLight =
    configColors.topbarLight ||
    configColors.topbar_light ||
    colors.topbarLight ||
    colors.topbar_light ||
    variables.topbarLight ||
    fallbackTopbarLight;
  const topbarDark =
    configColors.topbarDark ||
    configColors.topbar_dark ||
    colors.topbarDark ||
    colors.topbar_dark ||
    variables.topbarDark ||
    fallbackTopbarDark;
  const textOnSurface =
    marketingTextOnSurfaceToken ||
    marketingSchemeValues?.textOnSurface ||
    configColors.textOnSurface ||
    configColors.text_on_surface ||
    configColors.marketingTextOnSurface ||
    configColors.marketing_text_on_surface ||
    colors.textOnSurface ||
    colors.text_on_surface ||
    colors.marketingTextOnSurface ||
    colors.marketing_text_on_surface ||
    variables.textOnSurface ||
    variables.text_on_surface ||
    variables.marketingTextOnSurface ||
    variables.marketing_text_on_surface ||
    fallbackTextOnSurface;
  const textOnBackground =
    marketingTextOnBackgroundToken ||
    marketingSchemeValues?.textOnBackground ||
    configColors.textOnBackground ||
    configColors.text_on_background ||
    configColors.marketingTextOnBackground ||
    configColors.marketing_text_on_background ||
    colors.textOnBackground ||
    colors.text_on_background ||
    colors.marketingTextOnBackground ||
    colors.marketing_text_on_background ||
    variables.textOnBackground ||
    variables.text_on_background ||
    variables.marketingTextOnBackground ||
    variables.marketing_text_on_background ||
    fallbackTextOnBackground;
  const textMutedOnSurface =
    marketingTextMutedOnSurfaceToken ||
    marketingSchemeValues?.textMutedOnSurface ||
    configColors.textMutedOnSurface ||
    configColors.text_muted_on_surface ||
    configColors.marketingTextMutedOnSurface ||
    configColors.marketing_text_muted_on_surface ||
    colors.textMutedOnSurface ||
    colors.text_muted_on_surface ||
    colors.marketingTextMutedOnSurface ||
    colors.marketing_text_muted_on_surface ||
    variables.textMutedOnSurface ||
    variables.text_muted_on_surface ||
    variables.marketingTextMutedOnSurface ||
    variables.marketing_text_muted_on_surface ||
    fallbackTextMutedOnSurface;
  const textMutedOnBackground =
    marketingTextMutedOnBackgroundToken ||
    marketingSchemeValues?.textMutedOnBackground ||
    configColors.textMutedOnBackground ||
    configColors.text_muted_on_background ||
    configColors.marketingTextMutedOnBackground ||
    configColors.marketing_text_muted_on_background ||
    colors.textMutedOnBackground ||
    colors.text_muted_on_background ||
    colors.marketingTextMutedOnBackground ||
    colors.marketing_text_muted_on_background ||
    variables.textMutedOnBackground ||
    variables.text_muted_on_background ||
    variables.marketingTextMutedOnBackground ||
    variables.marketing_text_muted_on_background ||
    fallbackTextMutedOnBackground;
  const textOnSurfaceDark =
    marketingTextOnSurfaceDarkToken ||
    marketingSchemeValues?.textOnSurfaceDark ||
    configColors.textOnSurfaceDark ||
    configColors.text_on_surface_dark ||
    configColors.marketingTextOnSurfaceDark ||
    configColors.marketing_text_on_surface_dark ||
    colors.textOnSurfaceDark ||
    colors.text_on_surface_dark ||
    colors.marketingTextOnSurfaceDark ||
    colors.marketing_text_on_surface_dark ||
    variables.textOnSurfaceDark ||
    variables.text_on_surface_dark ||
    variables.marketingTextOnSurfaceDark ||
    variables.marketing_text_on_surface_dark ||
    fallbackTextOnSurfaceDark;
  const textOnBackgroundDark =
    marketingTextOnBackgroundDarkToken ||
    marketingSchemeValues?.textOnBackgroundDark ||
    configColors.textOnBackgroundDark ||
    configColors.text_on_background_dark ||
    configColors.marketingTextOnBackgroundDark ||
    configColors.marketing_text_on_background_dark ||
    colors.textOnBackgroundDark ||
    colors.text_on_background_dark ||
    colors.marketingTextOnBackgroundDark ||
    colors.marketing_text_on_background_dark ||
    variables.textOnBackgroundDark ||
    variables.text_on_background_dark ||
    variables.marketingTextOnBackgroundDark ||
    variables.marketing_text_on_background_dark ||
    fallbackTextOnBackgroundDark;
  const textMutedOnSurfaceDark =
    marketingTextMutedOnSurfaceDarkToken ||
    marketingSchemeValues?.textMutedOnSurfaceDark ||
    configColors.textMutedOnSurfaceDark ||
    configColors.text_muted_on_surface_dark ||
    configColors.marketingTextMutedOnSurfaceDark ||
    configColors.marketing_text_muted_on_surface_dark ||
    colors.textMutedOnSurfaceDark ||
    colors.text_muted_on_surface_dark ||
    colors.marketingTextMutedOnSurfaceDark ||
    colors.marketing_text_muted_on_surface_dark ||
    variables.textMutedOnSurfaceDark ||
    variables.text_muted_on_surface_dark ||
    variables.marketingTextMutedOnSurfaceDark ||
    variables.marketing_text_muted_on_surface_dark ||
    fallbackTextMutedOnSurfaceDark;
  const textMutedOnBackgroundDark =
    marketingTextMutedOnBackgroundDarkToken ||
    marketingSchemeValues?.textMutedOnBackgroundDark ||
    configColors.textMutedOnBackgroundDark ||
    configColors.text_muted_on_background_dark ||
    configColors.marketingTextMutedOnBackgroundDark ||
    configColors.marketing_text_muted_on_background_dark ||
    colors.textMutedOnBackgroundDark ||
    colors.text_muted_on_background_dark ||
    colors.marketingTextMutedOnBackgroundDark ||
    colors.marketing_text_muted_on_background_dark ||
    variables.textMutedOnBackgroundDark ||
    variables.text_muted_on_background_dark ||
    variables.marketingTextMutedOnBackgroundDark ||
    variables.marketing_text_muted_on_background_dark ||
    fallbackTextMutedOnBackgroundDark;
  const marketingInk = resolveFirstValue(
    marketingSchemeValues?.marketingInk,
    marketingSchemeValues?.ink,
    configColors.marketingInk,
    configColors.marketing_ink,
    colors.marketingInk,
    colors.marketing_ink,
    variables.marketingInk,
    variables.marketing_ink,
    fallbackInk,
  );
  const marketingSurfaceGlass = resolveFirstValue(
    marketingSchemeValues?.surfaceGlass,
    marketingSchemeValues?.marketingSurfaceGlass,
    configColors.marketingSurfaceGlass,
    configColors.marketing_surface_glass,
    colors.marketingSurfaceGlass,
    colors.marketing_surface_glass,
    variables.marketingSurfaceGlass,
    variables.marketing_surface_glass,
    fallbackSurfaceGlass,
  );
  const marketingSurfaceGlassDark = resolveFirstValue(
    marketingSchemeValues?.surfaceGlassDark,
    marketingSchemeValues?.marketingSurfaceGlassDark,
    configColors.marketingSurfaceGlassDark,
    configColors.marketing_surface_glass_dark,
    colors.marketingSurfaceGlassDark,
    colors.marketing_surface_glass_dark,
    variables.marketingSurfaceGlassDark,
    variables.marketing_surface_glass_dark,
    fallbackSurfaceGlassDark,
  );
  const marketingSurfaceAccentSoft = resolveFirstValue(
    marketingSchemeValues?.surfaceAccentSoft,
    marketingSchemeValues?.marketingSurfaceAccentSoft,
    configColors.marketingSurfaceAccentSoft,
    configColors.marketing_surface_accent_soft,
    colors.marketingSurfaceAccentSoft,
    colors.marketing_surface_accent_soft,
    variables.marketingSurfaceAccentSoft,
    variables.marketing_surface_accent_soft,
    fallbackSurfaceAccentSoft,
  );
  const marketingBorderLight = resolveFirstValue(
    marketingSchemeValues?.borderLight,
    marketingSchemeValues?.marketingBorderLight,
    configColors.marketingBorderLight,
    configColors.marketing_border_light,
    colors.marketingBorderLight,
    colors.marketing_border_light,
    variables.marketingBorderLight,
    variables.marketing_border_light,
    fallbackBorderLight,
  );
  const marketingBorder = resolveFirstValue(
    marketingSchemeValues?.border,
    marketingSchemeValues?.marketingBorder,
    configColors.marketingBorder,
    configColors.marketing_border,
    colors.marketingBorder,
    colors.marketing_border,
    variables.marketingBorder,
    variables.marketing_border,
    fallbackBorder,
  );
  const marketingBorderMuted = resolveFirstValue(
    marketingSchemeValues?.borderMuted,
    marketingSchemeValues?.marketingBorderMuted,
    configColors.marketingBorderMuted,
    configColors.marketing_border_muted,
    colors.marketingBorderMuted,
    colors.marketing_border_muted,
    variables.marketingBorderMuted,
    variables.marketing_border_muted,
    fallbackBorderMuted,
  );
  const marketingTextEmphasis = resolveFirstValue(
    marketingSchemeValues?.textEmphasis,
    marketingSchemeValues?.marketingTextEmphasis,
    configColors.marketingTextEmphasis,
    configColors.marketing_text_emphasis,
    colors.marketingTextEmphasis,
    colors.marketing_text_emphasis,
    variables.marketingTextEmphasis,
    variables.marketing_text_emphasis,
    fallbackTextEmphasis,
  );
  const marketingTextInverse = resolveFirstValue(
    marketingSchemeValues?.textInverse,
    marketingSchemeValues?.marketingTextInverse,
    configColors.marketingTextInverse,
    configColors.marketing_text_inverse,
    colors.marketingTextInverse,
    colors.marketing_text_inverse,
    variables.marketingTextInverse,
    variables.marketing_text_inverse,
    fallbackTextInverse,
  );
  const marketingRingStrong = resolveFirstValue(
    marketingSchemeValues?.ringStrong,
    marketingSchemeValues?.marketingRingStrong,
    configColors.marketingRingStrong,
    configColors.marketing_ring_strong,
    colors.marketingRingStrong,
    colors.marketing_ring_strong,
    variables.marketingRingStrong,
    variables.marketing_ring_strong,
    fallbackRingStrong,
  );
  const marketingRingStrongDark = resolveFirstValue(
    marketingSchemeValues?.ringStrongDark,
    marketingSchemeValues?.marketingRingStrongDark,
    configColors.marketingRingStrongDark,
    configColors.marketing_ring_strong_dark,
    colors.marketingRingStrongDark,
    colors.marketing_ring_strong_dark,
    variables.marketingRingStrongDark,
    variables.marketing_ring_strong_dark,
    fallbackRingStrongDark,
  );
  const marketingOverlaySoft = resolveFirstValue(
    marketingSchemeValues?.overlaySoft,
    marketingSchemeValues?.marketingOverlaySoft,
    configColors.marketingOverlaySoft,
    configColors.marketing_overlay_soft,
    colors.marketingOverlaySoft,
    colors.marketing_overlay_soft,
    variables.marketingOverlaySoft,
    variables.marketing_overlay_soft,
    fallbackOverlaySoft,
  );
  const marketingOverlayStrong = resolveFirstValue(
    marketingSchemeValues?.overlayStrong,
    marketingSchemeValues?.marketingOverlayStrong,
    configColors.marketingOverlayStrong,
    configColors.marketing_overlay_strong,
    colors.marketingOverlayStrong,
    colors.marketing_overlay_strong,
    variables.marketingOverlayStrong,
    variables.marketing_overlay_strong,
    fallbackOverlayStrong,
  );
  const marketingOverlayHero = resolveFirstValue(
    marketingSchemeValues?.overlayHero,
    marketingSchemeValues?.marketingOverlayHero,
    configColors.marketingOverlayHero,
    configColors.marketing_overlay_hero,
    colors.marketingOverlayHero,
    colors.marketing_overlay_hero,
    variables.marketingOverlayHero,
    variables.marketing_overlay_hero,
    fallbackOverlayHero,
  );
  const marketingShadowSoft = resolveFirstValue(
    marketingSchemeValues?.shadowSoft,
    marketingSchemeValues?.marketingShadowSoft,
    configColors.marketingShadowSoft,
    configColors.marketing_shadow_soft,
    colors.marketingShadowSoft,
    colors.marketing_shadow_soft,
    variables.marketingShadowSoft,
    variables.marketing_shadow_soft,
    fallbackShadowSoft,
  );
  const marketingShadowDark = resolveFirstValue(
    marketingSchemeValues?.shadowDark,
    marketingSchemeValues?.marketingShadowDark,
    configColors.marketingShadowDark,
    configColors.marketing_shadow_dark,
    colors.marketingShadowDark,
    colors.marketing_shadow_dark,
    variables.marketingShadowDark,
    variables.marketing_shadow_dark,
    fallbackShadowDark,
  );
  const marketingShadowPanel = resolveFirstValue(
    marketingSchemeValues?.shadowPanel,
    marketingSchemeValues?.marketingShadowPanel,
    configColors.marketingShadowPanel,
    configColors.marketing_shadow_panel,
    colors.marketingShadowPanel,
    colors.marketing_shadow_panel,
    variables.marketingShadowPanel,
    variables.marketing_shadow_panel,
    fallbackShadowPanel,
  );
  const marketingShadowCardBase = resolveFirstValue(
    marketingSchemeValues?.shadowCardBase,
    marketingSchemeValues?.marketingShadowCardBase,
    configColors.marketingShadowCardBase,
    configColors.marketing_shadow_card_base,
    colors.marketingShadowCardBase,
    colors.marketing_shadow_card_base,
    variables.marketingShadowCardBase,
    variables.marketing_shadow_card_base,
    fallbackShadowCardBase,
  );
  const marketingShadowCardSoftBase = resolveFirstValue(
    marketingSchemeValues?.shadowCardSoftBase,
    marketingSchemeValues?.marketingShadowCardSoftBase,
    configColors.marketingShadowCardSoftBase,
    configColors.marketing_shadow_card_soft_base,
    colors.marketingShadowCardSoftBase,
    colors.marketing_shadow_card_soft_base,
    variables.marketingShadowCardSoftBase,
    variables.marketing_shadow_card_soft_base,
    fallbackShadowCardSoftBase,
  );
  const marketingShadowCard = resolveFirstValue(
    marketingSchemeValues?.shadowCard,
    marketingSchemeValues?.marketingShadowCard,
    configColors.marketingShadowCard,
    configColors.marketing_shadow_card,
    colors.marketingShadowCard,
    colors.marketing_shadow_card,
    variables.marketingShadowCard,
    variables.marketing_shadow_card,
    fallbackShadowCard,
  );
  const marketingShadowAccent = resolveFirstValue(
    marketingSchemeValues?.shadowAccent,
    marketingSchemeValues?.marketingShadowAccent,
    configColors.marketingShadowAccent,
    configColors.marketing_shadow_accent,
    colors.marketingShadowAccent,
    colors.marketing_shadow_accent,
    variables.marketingShadowAccent,
    variables.marketing_shadow_accent,
    fallbackShadowAccent,
  );
  const marketingShadowCardSoft = resolveFirstValue(
    marketingSchemeValues?.shadowCardSoft,
    marketingSchemeValues?.marketingShadowCardSoft,
    configColors.marketingShadowCardSoft,
    configColors.marketing_shadow_card_soft,
    colors.marketingShadowCardSoft,
    colors.marketing_shadow_card_soft,
    variables.marketingShadowCardSoft,
    variables.marketing_shadow_card_soft,
    fallbackShadowCardSoft,
  );
  const marketingShadowCardHover = resolveFirstValue(
    marketingSchemeValues?.shadowCardHover,
    marketingSchemeValues?.marketingShadowCardHover,
    configColors.marketingShadowCardHover,
    configColors.marketing_shadow_card_hover,
    colors.marketingShadowCardHover,
    colors.marketing_shadow_card_hover,
    variables.marketingShadowCardHover,
    variables.marketing_shadow_card_hover,
    fallbackShadowCardHover,
  );
  const marketingShadowHeroMockup = resolveFirstValue(
    marketingSchemeValues?.shadowHeroMockup,
    marketingSchemeValues?.marketingShadowHeroMockup,
    configColors.marketingShadowHeroMockup,
    configColors.marketing_shadow_hero_mockup,
    colors.marketingShadowHeroMockup,
    colors.marketing_shadow_hero_mockup,
    variables.marketingShadowHeroMockup,
    variables.marketing_shadow_hero_mockup,
    fallbackShadowHeroMockup,
  );
  const marketingShadowPill = resolveFirstValue(
    marketingSchemeValues?.shadowPill,
    marketingSchemeValues?.marketingShadowPill,
    configColors.marketingShadowPill,
    configColors.marketing_shadow_pill,
    colors.marketingShadowPill,
    colors.marketing_shadow_pill,
    variables.marketingShadowPill,
    variables.marketing_shadow_pill,
    fallbackShadowPill,
  );
  const marketingShadowCallout = resolveFirstValue(
    marketingSchemeValues?.shadowCallout,
    marketingSchemeValues?.marketingShadowCallout,
    configColors.marketingShadowCallout,
    configColors.marketing_shadow_callout,
    colors.marketingShadowCallout,
    colors.marketing_shadow_callout,
    variables.marketingShadowCallout,
    variables.marketing_shadow_callout,
    fallbackShadowCallout,
  );
  const marketingShadowStat = resolveFirstValue(
    marketingSchemeValues?.shadowStat,
    marketingSchemeValues?.marketingShadowStat,
    configColors.marketingShadowStat,
    configColors.marketing_shadow_stat,
    colors.marketingShadowStat,
    colors.marketing_shadow_stat,
    variables.marketingShadowStat,
    variables.marketing_shadow_stat,
    fallbackShadowStat,
  );
  const marketingShadowStatAccent = resolveFirstValue(
    marketingSchemeValues?.shadowStatAccent,
    marketingSchemeValues?.marketingShadowStatAccent,
    configColors.marketingShadowStatAccent,
    configColors.marketing_shadow_stat_accent,
    colors.marketingShadowStatAccent,
    colors.marketing_shadow_stat_accent,
    variables.marketingShadowStatAccent,
    variables.marketing_shadow_stat_accent,
    fallbackShadowStatAccent,
  );
  const marketingLinkContrastLight = resolveFirstValue(
    marketingSchemeValues?.linkContrastLight,
    marketingSchemeValues?.marketingLinkContrastLight,
    configColors.marketingLinkContrastLight,
    configColors.marketing_link_contrast_light,
    colors.marketingLinkContrastLight,
    colors.marketing_link_contrast_light,
    variables.marketingLinkContrastLight,
    variables.marketing_link_contrast_light,
    fallbackLinkContrastLight,
  );
  const marketingLink = resolveFirstValue(
    marketingSchemeValues?.link,
    marketingSchemeValues?.marketingLink,
    configColors.marketingLink,
    configColors.marketing_link,
    colors.marketingLink,
    colors.marketing_link,
    variables.marketingLink,
    variables.marketing_link,
    fallbackLink,
  );
  const marketingLinkHover = resolveFirstValue(
    marketingSchemeValues?.linkHover,
    marketingSchemeValues?.marketingLinkHover,
    configColors.marketingLinkHover,
    configColors.marketing_link_hover,
    colors.marketingLinkHover,
    colors.marketing_link_hover,
    variables.marketingLinkHover,
    variables.marketing_link_hover,
    fallbackLinkHover,
  );
  const marketingLinkContrastDark = resolveFirstValue(
    marketingSchemeValues?.linkContrastDark,
    marketingSchemeValues?.marketingLinkContrastDark,
    configColors.marketingLinkContrastDark,
    configColors.marketing_link_contrast_dark,
    colors.marketingLinkContrastDark,
    colors.marketing_link_contrast_dark,
    variables.marketingLinkContrastDark,
    variables.marketing_link_contrast_dark,
    fallbackLinkContrastDark,
  );
  const marketingTopbarTextContrastLight = resolveFirstValue(
    marketingSchemeValues?.topbarTextContrastLight,
    marketingSchemeValues?.marketingTopbarTextContrastLight,
    configColors.marketingTopbarTextContrastLight,
    configColors.marketing_topbar_text_contrast_light,
    colors.marketingTopbarTextContrastLight,
    colors.marketing_topbar_text_contrast_light,
    variables.marketingTopbarTextContrastLight,
    variables.marketing_topbar_text_contrast_light,
    fallbackTopbarTextContrastLight,
  );
  const marketingTopbarTextContrastDark = resolveFirstValue(
    marketingSchemeValues?.topbarTextContrastDark,
    marketingSchemeValues?.marketingTopbarTextContrastDark,
    configColors.marketingTopbarTextContrastDark,
    configColors.marketing_topbar_text_contrast_dark,
    colors.marketingTopbarTextContrastDark,
    colors.marketing_topbar_text_contrast_dark,
    variables.marketingTopbarTextContrastDark,
    variables.marketing_topbar_text_contrast_dark,
    fallbackTopbarTextContrastDark,
  );
  const marketingTopbarDropBgContrastLight = resolveFirstValue(
    marketingSchemeValues?.topbarDropBgContrastLight,
    marketingSchemeValues?.marketingTopbarDropBgContrastLight,
    configColors.marketingTopbarDropBgContrastLight,
    configColors.marketing_topbar_drop_bg_contrast_light,
    colors.marketingTopbarDropBgContrastLight,
    colors.marketing_topbar_drop_bg_contrast_light,
    variables.marketingTopbarDropBgContrastLight,
    variables.marketing_topbar_drop_bg_contrast_light,
    fallbackTopbarDropBgContrastLight,
  );
  const marketingTopbarDropBgContrastDark = resolveFirstValue(
    marketingSchemeValues?.topbarDropBgContrastDark,
    marketingSchemeValues?.marketingTopbarDropBgContrastDark,
    configColors.marketingTopbarDropBgContrastDark,
    configColors.marketing_topbar_drop_bg_contrast_dark,
    colors.marketingTopbarDropBgContrastDark,
    colors.marketing_topbar_drop_bg_contrast_dark,
    variables.marketingTopbarDropBgContrastDark,
    variables.marketing_topbar_drop_bg_contrast_dark,
    fallbackTopbarDropBgContrastDark,
  );
  const marketingTopbarBtnBorderContrastLight = resolveFirstValue(
    marketingSchemeValues?.topbarBtnBorderContrastLight,
    marketingSchemeValues?.marketingTopbarBtnBorderContrastLight,
    configColors.marketingTopbarBtnBorderContrastLight,
    configColors.marketing_topbar_btn_border_contrast_light,
    colors.marketingTopbarBtnBorderContrastLight,
    colors.marketing_topbar_btn_border_contrast_light,
    variables.marketingTopbarBtnBorderContrastLight,
    variables.marketing_topbar_btn_border_contrast_light,
    fallbackTopbarBtnBorderContrastLight,
  );
  const marketingTopbarBtnBorderContrastDark = resolveFirstValue(
    marketingSchemeValues?.topbarBtnBorderContrastDark,
    marketingSchemeValues?.marketingTopbarBtnBorderContrastDark,
    configColors.marketingTopbarBtnBorderContrastDark,
    configColors.marketing_topbar_btn_border_contrast_dark,
    colors.marketingTopbarBtnBorderContrastDark,
    colors.marketing_topbar_btn_border_contrast_dark,
    variables.marketingTopbarBtnBorderContrastDark,
    variables.marketing_topbar_btn_border_contrast_dark,
    fallbackTopbarBtnBorderContrastDark,
  );
  const marketingTopbarFocusRingContrastLight = resolveFirstValue(
    marketingSchemeValues?.topbarFocusRingContrastLight,
    marketingSchemeValues?.marketingTopbarFocusRingContrastLight,
    configColors.marketingTopbarFocusRingContrastLight,
    configColors.marketing_topbar_focus_ring_contrast_light,
    colors.marketingTopbarFocusRingContrastLight,
    colors.marketing_topbar_focus_ring_contrast_light,
    variables.marketingTopbarFocusRingContrastLight,
    variables.marketing_topbar_focus_ring_contrast_light,
    fallbackTopbarFocusRingContrastLight,
  );
  const marketingTopbarFocusRingContrastDark = resolveFirstValue(
    marketingSchemeValues?.topbarFocusRingContrastDark,
    marketingSchemeValues?.marketingTopbarFocusRingContrastDark,
    configColors.marketingTopbarFocusRingContrastDark,
    configColors.marketing_topbar_focus_ring_contrast_dark,
    colors.marketingTopbarFocusRingContrastDark,
    colors.marketing_topbar_focus_ring_contrast_dark,
    variables.marketingTopbarFocusRingContrastDark,
    variables.marketing_topbar_focus_ring_contrast_dark,
    fallbackTopbarFocusRingContrastDark,
  );
  const marketingDanger500 = resolveFirstValue(
    marketingSchemeValues?.danger500,
    marketingSchemeValues?.marketingDanger500,
    configColors.marketingDanger500,
    configColors.marketing_danger_500,
    colors.marketingDanger500,
    colors.marketing_danger_500,
    variables.marketingDanger500,
    variables.marketing_danger_500,
    fallbackDanger500,
  );
  const marketingSuccess = resolveFirstValue(
    marketingSchemeValues?.success,
    marketingSchemeValues?.marketingSuccess,
    configColors.marketingSuccess,
    configColors.marketing_success,
    colors.marketingSuccess,
    colors.marketing_success,
    variables.marketingSuccess,
    variables.marketing_success,
    fallbackSuccess,
  );
  const marketingWarning = resolveFirstValue(
    marketingSchemeValues?.warning,
    marketingSchemeValues?.marketingWarning,
    configColors.marketingWarning,
    configColors.marketing_warning,
    colors.marketingWarning,
    colors.marketing_warning,
    variables.marketingWarning,
    variables.marketing_warning,
    fallbackWarning,
  );
  const marketingDanger = resolveFirstValue(
    marketingSchemeValues?.danger,
    marketingSchemeValues?.marketingDanger,
    configColors.marketingDanger,
    configColors.marketing_danger,
    colors.marketingDanger,
    colors.marketing_danger,
    variables.marketingDanger,
    variables.marketing_danger,
    fallbackDanger,
  );
  const marketingDanger600 = resolveFirstValue(
    marketingSchemeValues?.danger600,
    marketingSchemeValues?.marketingDanger600,
    configColors.marketingDanger600,
    configColors.marketing_danger_600,
    colors.marketingDanger600,
    colors.marketing_danger_600,
    variables.marketingDanger600,
    variables.marketing_danger_600,
    fallbackDanger600,
  );
  const marketingWhite = resolveFirstValue(
    marketingSchemeValues?.white,
    marketingSchemeValues?.marketingWhite,
    configColors.marketingWhite,
    configColors.marketing_white,
    colors.marketingWhite,
    colors.marketing_white,
    variables.marketingWhite,
    variables.marketing_white,
    fallbackMarketingWhite,
  );
  const marketingBlack = resolveFirstValue(
    marketingSchemeValues?.black,
    marketingSchemeValues?.marketingBlack,
    configColors.marketingBlack,
    configColors.marketing_black,
    colors.marketingBlack,
    colors.marketing_black,
    variables.marketingBlack,
    variables.marketing_black,
    fallbackMarketingBlack,
  );
  const marketingBlackRgb = resolveFirstValue(
    marketingSchemeValues?.blackRgb,
    marketingSchemeValues?.marketingBlackRgb,
    configColors.marketingBlackRgb,
    configColors.marketing_black_rgb,
    colors.marketingBlackRgb,
    colors.marketing_black_rgb,
    variables.marketingBlackRgb,
    variables.marketing_black_rgb,
    fallbackMarketingBlackRgb,
  );
  const marketingTextOnSurface = resolveFirstValue(
    marketingSchemeValues?.textOnSurface,
    textOnSurface,
    fallbackTextOnSurface,
  );
  const marketingTextOnBackground = resolveFirstValue(
    marketingSchemeValues?.textOnBackground,
    textOnBackground,
    fallbackTextOnBackground,
  );
  const marketingTextMutedOnSurface = resolveFirstValue(
    marketingSchemeValues?.textMutedOnSurface,
    textMutedOnSurface,
    fallbackTextMutedOnSurface,
  );
  const marketingTextMutedOnBackground = resolveFirstValue(
    marketingSchemeValues?.textMutedOnBackground,
    textMutedOnBackground,
    fallbackTextMutedOnBackground,
  );
  const marketingTextOnSurfaceDark = resolveFirstValue(
    marketingSchemeValues?.textOnSurfaceDark,
    textOnSurfaceDark,
    fallbackTextOnSurfaceDark,
  );
  const marketingTextOnBackgroundDark = resolveFirstValue(
    marketingSchemeValues?.textOnBackgroundDark,
    textOnBackgroundDark,
    fallbackTextOnBackgroundDark,
  );
  const marketingTextMutedOnSurfaceDark = resolveFirstValue(
    marketingSchemeValues?.textMutedOnSurfaceDark,
    textMutedOnSurfaceDark,
    fallbackTextMutedOnSurfaceDark,
  );
  const marketingTextMutedOnBackgroundDark = resolveFirstValue(
    marketingSchemeValues?.textMutedOnBackgroundDark,
    textMutedOnBackgroundDark,
    fallbackTextMutedOnBackgroundDark,
  );
  const marketingText = resolveFirstValue(
    marketingTextToken,
    marketingSchemeValues?.textOnBackground,
    marketingTextOnBackground,
    marketingTextOnSurface,
    fallbackMarketingText,
  );
  const surfaceDark =
    marketingSchemeValues?.surfaceDark ||
    configColors.surfaceDark ||
    configColors.surface_dark ||
    configColors.marketingSurfaceDark ||
    configColors.marketing_surface_dark ||
    colors.surfaceDark ||
    colors.surface_dark ||
    colors.marketingSurfaceDark ||
    colors.marketing_surface_dark ||
    variables.surfaceDark ||
    variables.surface_dark ||
    variables.marketingSurfaceDark ||
    variables.marketing_surface_dark ||
    fallbackSurfaceDark;
  const surfaceMutedDark =
    marketingSchemeValues?.surfaceMutedDark ||
    configColors.surfaceMutedDark ||
    configColors.surface_muted_dark ||
    configColors.marketingSurfaceMutedDark ||
    configColors.marketing_surface_muted_dark ||
    colors.surfaceMutedDark ||
    colors.surface_muted_dark ||
    colors.marketingSurfaceMutedDark ||
    colors.marketing_surface_muted_dark ||
    variables.surfaceMutedDark ||
    variables.surface_muted_dark ||
    variables.marketingSurfaceMutedDark ||
    variables.marketing_surface_muted_dark ||
    fallbackSurfaceMutedDark;
  const cardDark =
    marketingSchemeValues?.surfaceGlassDark ||
    marketingSchemeValues?.cardDark ||
    configColors.cardDark ||
    configColors.card_dark ||
    configColors.marketingCardDark ||
    configColors.marketing_card_dark ||
    colors.cardDark ||
    colors.card_dark ||
    colors.marketingCardDark ||
    colors.marketing_card_dark ||
    variables.cardDark ||
    variables.card_dark ||
    variables.marketingCardDark ||
    variables.marketing_card_dark ||
    fallbackCardDark;

  const sectionDefaultSurface = resolveFirstValue(
    variables.sectionDefaultSurface,
    colors.sectionDefaultSurface,
    surface,
    colors.surface,
    variables.surface,
  );
  const sectionDefaultMuted = resolveFirstValue(
    variables.sectionDefaultMuted,
    colors.sectionDefaultMuted,
    surfaceMuted,
    colors.surfaceMuted,
    colors.muted,
    variables.surfaceMuted,
  );
  const sectionDefaultAccent = resolveFirstValue(
    variables.sectionDefaultAccent,
    colors.sectionDefaultAccent,
    accent,
    colors.primary,
    colors.accent,
    variables.primary,
    variables.accent,
  );

  if (sectionDefaultSurface && !isSectionDefaultReference(sectionDefaultSurface)) {
    root.style.setProperty('--section-default-surface', sectionDefaultSurface);
  }
  if (sectionDefaultMuted && !isSectionDefaultReference(sectionDefaultMuted)) {
    root.style.setProperty('--section-default-muted', sectionDefaultMuted);
  }
  if (sectionDefaultAccent && !isSectionDefaultReference(sectionDefaultAccent)) {
    root.style.setProperty('--section-default-accent', sectionDefaultAccent);
  }

  root.style.setProperty('--layout-profile', normalizedLayoutProfile);
  root.style.setProperty('--typography-preset', normalizedTypographyPreset);
  root.style.setProperty('--components-card-style', normalizedCardStyle);
  root.style.setProperty('--components-button-style', normalizedButtonStyle);
  root.dataset.typographyPreset = normalizedTypographyPreset;
  root.dataset.cardStyle = normalizedCardStyle;
  root.dataset.buttonStyle = normalizedButtonStyle;

  setTokenProperty('--marketing-primary', marketingPrimary, hasMarketingPrimaryOverride);
  setTokenProperty('--marketing-accent', marketingAccent, hasMarketingAccentOverride);
  setTokenProperty('--marketing-secondary', marketingSecondary, hasMarketingSecondaryOverride);
  root.style.setProperty('--marketing-background', marketingBackground);
  if (marketingBackgroundDark) {
    root.style.setProperty('--marketing-background-dark', marketingBackgroundDark);
  }
  const contrastToken = getComputedStyle(root).getPropertyValue('--contrast-text-on-primary').trim();
  const resolvedOnAccent = contrastToken || marketingOnAccent;
  root.style.setProperty('--marketing-on-accent', resolvedOnAccent);
  root.style.setProperty('--text-on-primary', resolvedOnAccent);
  if (!contrastToken && resolvedOnAccent) {
    root.style.setProperty('--contrast-text-on-primary', resolvedOnAccent);
  }
  root.style.setProperty('--marketing-text', marketingText);
  root.style.setProperty('--marketing-surface', marketingSurface);
  root.style.setProperty('--marketing-surface-muted', marketingSurfaceMuted);
  root.style.setProperty('--marketing-text-on-surface', marketingTextOnSurface);
  root.style.setProperty('--marketing-text-on-background', marketingTextOnBackground);
  root.style.setProperty('--marketing-text-muted-on-surface', marketingTextMutedOnSurface);
  root.style.setProperty('--marketing-text-muted-on-background', marketingTextMutedOnBackground);
  root.style.setProperty('--marketing-text-on-surface-dark', marketingTextOnSurfaceDark);
  root.style.setProperty('--marketing-text-on-background-dark', marketingTextOnBackgroundDark);
  root.style.setProperty('--marketing-text-muted-on-surface-dark', marketingTextMutedOnSurfaceDark);
  root.style.setProperty('--marketing-text-muted-on-background-dark', marketingTextMutedOnBackgroundDark);
  if (marketingInk) {
    root.style.setProperty('--marketing-ink', marketingInk);
  }
  if (marketingSurfaceGlass) {
    root.style.setProperty('--marketing-surface-glass', marketingSurfaceGlass);
  }
  if (marketingSurfaceGlassDark) {
    root.style.setProperty('--marketing-surface-glass-dark', marketingSurfaceGlassDark);
  }
  if (marketingSurfaceAccentSoft) {
    root.style.setProperty('--marketing-surface-accent-soft', marketingSurfaceAccentSoft);
  }
  if (marketingBorderLight) {
    root.style.setProperty('--marketing-border-light', marketingBorderLight);
  }
  if (marketingBorder) {
    root.style.setProperty('--marketing-border', marketingBorder);
  }
  if (marketingBorderMuted) {
    root.style.setProperty('--marketing-border-muted', marketingBorderMuted);
  }
  if (marketingTextEmphasis) {
    root.style.setProperty('--marketing-text-emphasis', marketingTextEmphasis);
  }
  if (marketingTextInverse) {
    root.style.setProperty('--marketing-text-inverse', marketingTextInverse);
  }
  if (marketingRingStrong) {
    root.style.setProperty('--marketing-ring-strong', marketingRingStrong);
  }
  if (marketingRingStrongDark) {
    root.style.setProperty('--marketing-ring-strong-dark', marketingRingStrongDark);
  }
  if (marketingOverlaySoft) {
    root.style.setProperty('--marketing-overlay-soft', marketingOverlaySoft);
  }
  if (marketingOverlayStrong) {
    root.style.setProperty('--marketing-overlay-strong', marketingOverlayStrong);
  }
  if (marketingOverlayHero) {
    root.style.setProperty('--marketing-overlay-hero', marketingOverlayHero);
  }
  if (marketingShadowSoft) {
    root.style.setProperty('--marketing-shadow-soft', marketingShadowSoft);
  }
  if (marketingShadowDark) {
    root.style.setProperty('--marketing-shadow-dark', marketingShadowDark);
  }
  if (marketingShadowPanel) {
    root.style.setProperty('--marketing-shadow-panel', marketingShadowPanel);
  }
  if (marketingShadowCardBase) {
    root.style.setProperty('--marketing-shadow-card-base', marketingShadowCardBase);
  }
  if (marketingShadowCardSoftBase) {
    root.style.setProperty('--marketing-shadow-card-soft-base', marketingShadowCardSoftBase);
  }
  if (marketingShadowCard) {
    root.style.setProperty('--marketing-shadow-card', marketingShadowCard);
  }
  if (marketingShadowAccent) {
    root.style.setProperty('--marketing-shadow-accent', marketingShadowAccent);
  }
  if (marketingShadowCardSoft) {
    root.style.setProperty('--marketing-shadow-card-soft', marketingShadowCardSoft);
  }
  if (marketingShadowCardHover) {
    root.style.setProperty('--marketing-shadow-card-hover', marketingShadowCardHover);
  }
  if (marketingShadowHeroMockup) {
    root.style.setProperty('--marketing-shadow-hero-mockup', marketingShadowHeroMockup);
  }
  if (marketingShadowPill) {
    root.style.setProperty('--marketing-shadow-pill', marketingShadowPill);
  }
  if (marketingShadowCallout) {
    root.style.setProperty('--marketing-shadow-callout', marketingShadowCallout);
  }
  if (marketingShadowStat) {
    root.style.setProperty('--marketing-shadow-stat', marketingShadowStat);
  }
  if (marketingShadowStatAccent) {
    root.style.setProperty('--marketing-shadow-stat-accent', marketingShadowStatAccent);
  }
  if (marketingLinkContrastLight) {
    root.style.setProperty('--marketing-link-contrast-light', marketingLinkContrastLight);
  }
  if (marketingLink) {
    root.style.setProperty('--marketing-link', marketingLink);
  }
  if (marketingLinkHover) {
    root.style.setProperty('--marketing-link-hover', marketingLinkHover);
  }
  if (marketingLinkContrastDark) {
    root.style.setProperty('--marketing-link-contrast-dark', marketingLinkContrastDark);
  }
  if (marketingTopbarTextContrastLight) {
    root.style.setProperty('--marketing-topbar-text-contrast-light', marketingTopbarTextContrastLight);
  }
  if (marketingTopbarTextContrastDark) {
    root.style.setProperty('--marketing-topbar-text-contrast-dark', marketingTopbarTextContrastDark);
  }
  if (marketingTopbarDropBgContrastLight) {
    root.style.setProperty('--marketing-topbar-drop-bg-contrast-light', marketingTopbarDropBgContrastLight);
  }
  if (marketingTopbarDropBgContrastDark) {
    root.style.setProperty('--marketing-topbar-drop-bg-contrast-dark', marketingTopbarDropBgContrastDark);
  }
  if (marketingTopbarBtnBorderContrastLight) {
    root.style.setProperty('--marketing-topbar-btn-border-contrast-light', marketingTopbarBtnBorderContrastLight);
  }
  if (marketingTopbarBtnBorderContrastDark) {
    root.style.setProperty('--marketing-topbar-btn-border-contrast-dark', marketingTopbarBtnBorderContrastDark);
  }
  if (marketingTopbarFocusRingContrastLight) {
    root.style.setProperty('--marketing-topbar-focus-ring-contrast-light', marketingTopbarFocusRingContrastLight);
  }
  if (marketingTopbarFocusRingContrastDark) {
    root.style.setProperty('--marketing-topbar-focus-ring-contrast-dark', marketingTopbarFocusRingContrastDark);
  }
  if (marketingSuccess) {
    root.style.setProperty('--marketing-success', marketingSuccess);
  }
  if (marketingWarning) {
    root.style.setProperty('--marketing-warning', marketingWarning);
  }
  if (marketingDanger) {
    root.style.setProperty('--marketing-danger', marketingDanger);
  }
  if (marketingDanger500) {
    root.style.setProperty('--marketing-danger-500', marketingDanger500);
  }
  if (marketingDanger600) {
    root.style.setProperty('--marketing-danger-600', marketingDanger600);
  }
  if (marketingWhite) {
    root.style.setProperty('--marketing-white', marketingWhite);
  }
  if (marketingBlack) {
    root.style.setProperty('--marketing-black', marketingBlack);
  }
  if (marketingBlackRgb) {
    root.style.setProperty('--marketing-black-rgb', marketingBlackRgb);
  }
  if (surfaceDark) {
    root.style.setProperty('--marketing-surface-dark', surfaceDark);
  }
  if (surfaceMutedDark) {
    root.style.setProperty('--marketing-surface-muted-dark', surfaceMutedDark);
  }
  if (cardDark) {
    root.style.setProperty('--marketing-card-dark', cardDark);
  }
  const shouldSyncBrandPrimary = hasBrandPrimaryOverride || hasMarketingPrimaryOverride;
  const shouldSyncBrandAccent = hasBrandAccentOverride || hasMarketingAccentOverride;
  const shouldSyncBrandSecondary = hasBrandSecondaryOverride || hasMarketingSecondaryOverride;
  setTokenProperty('--brand-primary', 'var(--marketing-primary)', shouldSyncBrandPrimary);
  setTokenProperty('--accent-primary', 'var(--marketing-primary)', shouldSyncBrandPrimary);
  setTokenProperty('--brand-accent', 'var(--marketing-accent)', shouldSyncBrandAccent);
  setTokenProperty('--brand-secondary', 'var(--marketing-secondary)', shouldSyncBrandSecondary);
  setTokenProperty('--accent-secondary', 'var(--marketing-secondary)', shouldSyncBrandSecondary);
  const hasExplicitSurfaceOverride = Boolean(
    marketingSchemeValues?.surface || configColors.surface || colors.surface || variables.surface,
  );
  setTokenProperty('--surface', 'var(--marketing-surface)', hasExplicitSurfaceOverride);
  setTokenProperty('--surface-muted', 'var(--marketing-surface-muted)', hasExplicitSurfaceOverride);
  setTokenProperty('--bg-page', 'var(--surface)', hasExplicitSurfaceOverride);
  setTokenProperty('--bg-section', 'var(--surface)', hasExplicitSurfaceOverride);
  setTokenProperty('--bg-card', 'var(--surface)', hasExplicitSurfaceOverride);
  setTokenProperty('--bg-accent', 'var(--brand-primary)', shouldSyncBrandPrimary);

  if (topbarLight) {
    root.style.setProperty('--marketing-topbar-light', topbarLight);
    root.style.setProperty('--qr-landing-topbar-bg-light', 'var(--marketing-topbar-light)');
  }
  if (topbarDark) {
    root.style.setProperty('--marketing-topbar-dark', topbarDark);
    root.style.setProperty('--qr-landing-topbar-bg-dark', 'var(--marketing-topbar-dark)');
  }
  if (topbarLight || topbarDark) {
    root.style.setProperty('--qr-hero-grad-start', 'var(--qr-landing-topbar-bg)');
    root.style.setProperty('--qr-hero-grad-end', 'var(--qr-bg-soft)');
  }

  // Apply font/heading/card/button tokens from scheme (these are already set
  // server-side via theme-vars.twig but must also be applied client-side for
  // consistency and to prevent specificity conflicts with inline styles).
  const schemeFontStack = resolveFirstValue(
    marketingSchemeValues?.fontStack,
    configColors.fontStack,
    configColors.font_stack,
    colors.fontStack,
    variables.fontStack,
  );
  if (schemeFontStack) {
    root.style.setProperty('--marketing-font-stack', schemeFontStack);
  }
  const schemeHeadingFontStack = resolveFirstValue(
    marketingSchemeValues?.headingFontStack,
    configColors.headingFontStack,
    configColors.heading_font_stack,
    colors.headingFontStack,
    variables.headingFontStack,
  );
  if (schemeHeadingFontStack) {
    root.style.setProperty('--marketing-heading-font-stack', schemeHeadingFontStack);
  }
  const schemeHeadingWeight = resolveFirstValue(
    marketingSchemeValues?.headingWeight,
    configColors.headingWeight,
    configColors.heading_weight,
    colors.headingWeight,
    variables.headingWeight,
  );
  if (schemeHeadingWeight) {
    root.style.setProperty('--marketing-heading-weight', schemeHeadingWeight);
  }
  const schemeHeadingLetterSpacing = resolveFirstValue(
    marketingSchemeValues?.headingLetterSpacing,
    configColors.headingLetterSpacing,
    configColors.heading_letter_spacing,
    colors.headingLetterSpacing,
    variables.headingLetterSpacing,
  );
  if (schemeHeadingLetterSpacing !== null && schemeHeadingLetterSpacing !== undefined) {
    root.style.setProperty('--marketing-heading-letter-spacing', schemeHeadingLetterSpacing);
  }
  const schemeHeadingLineHeight = resolveFirstValue(
    marketingSchemeValues?.headingLineHeight,
    configColors.headingLineHeight,
    configColors.heading_line_height,
    colors.headingLineHeight,
    variables.headingLineHeight,
  );
  if (schemeHeadingLineHeight) {
    root.style.setProperty('--marketing-heading-line-height', schemeHeadingLineHeight);
  }
  const schemeCardRadius = resolveFirstValue(
    marketingSchemeValues?.cardRadius,
    configColors.cardRadius,
    configColors.card_radius,
    colors.cardRadius,
    variables.cardRadius,
  );
  if (schemeCardRadius) {
    root.style.setProperty('--marketing-card-radius', schemeCardRadius);
  }
  const schemeButtonPrimaryBg = resolveFirstValue(
    marketingSchemeValues?.buttonPrimaryBg,
    configColors.buttonPrimaryBg,
    configColors.button_primary_bg,
    colors.buttonPrimaryBg,
    variables.buttonPrimaryBg,
  );
  if (schemeButtonPrimaryBg) {
    root.style.setProperty('--marketing-button-primary-bg', schemeButtonPrimaryBg);
  }
  const schemeButtonPrimaryText = resolveFirstValue(
    marketingSchemeValues?.buttonPrimaryText,
    configColors.buttonPrimaryText,
    configColors.button_primary_text,
    colors.buttonPrimaryText,
    variables.buttonPrimaryText,
  );
  if (schemeButtonPrimaryText) {
    root.style.setProperty('--marketing-button-primary-text', schemeButtonPrimaryText);
  }
  const schemeButtonPrimaryBorderColor = resolveFirstValue(
    marketingSchemeValues?.buttonPrimaryBorderColor,
    configColors.buttonPrimaryBorderColor,
    configColors.button_primary_border_color,
    colors.buttonPrimaryBorderColor,
    variables.buttonPrimaryBorderColor,
  );
  if (schemeButtonPrimaryBorderColor) {
    root.style.setProperty('--marketing-button-primary-border-color', schemeButtonPrimaryBorderColor);
  }
  const schemeButtonPrimaryHoverBg = resolveFirstValue(
    marketingSchemeValues?.buttonPrimaryHoverBg,
    configColors.buttonPrimaryHoverBg,
    configColors.button_primary_hover_bg,
    colors.buttonPrimaryHoverBg,
    variables.buttonPrimaryHoverBg,
  );
  if (schemeButtonPrimaryHoverBg) {
    root.style.setProperty('--marketing-button-primary-hover-bg', schemeButtonPrimaryHoverBg);
  }
  const schemeButtonPrimaryFocusBg = resolveFirstValue(
    marketingSchemeValues?.buttonPrimaryFocusBg,
    configColors.buttonPrimaryFocusBg,
    configColors.button_primary_focus_bg,
    colors.buttonPrimaryFocusBg,
    variables.buttonPrimaryFocusBg,
  );
  if (schemeButtonPrimaryFocusBg) {
    root.style.setProperty('--marketing-button-primary-focus-bg', schemeButtonPrimaryFocusBg);
  }
  const schemeButtonPrimaryActiveBg = resolveFirstValue(
    marketingSchemeValues?.buttonPrimaryActiveBg,
    configColors.buttonPrimaryActiveBg,
    configColors.button_primary_active_bg,
    colors.buttonPrimaryActiveBg,
    variables.buttonPrimaryActiveBg,
  );
  if (schemeButtonPrimaryActiveBg) {
    root.style.setProperty('--marketing-button-primary-active-bg', schemeButtonPrimaryActiveBg);
  }
  const schemeButtonSecondaryBg = resolveFirstValue(
    marketingSchemeValues?.buttonSecondaryBg,
    configColors.buttonSecondaryBg,
    configColors.button_secondary_bg,
    colors.buttonSecondaryBg,
    variables.buttonSecondaryBg,
  );
  if (schemeButtonSecondaryBg !== null && schemeButtonSecondaryBg !== undefined) {
    root.style.setProperty('--marketing-button-secondary-bg', schemeButtonSecondaryBg);
  }
  const schemeButtonSecondaryText = resolveFirstValue(
    marketingSchemeValues?.buttonSecondaryText,
    configColors.buttonSecondaryText,
    configColors.button_secondary_text,
    colors.buttonSecondaryText,
    variables.buttonSecondaryText,
  );
  if (schemeButtonSecondaryText) {
    root.style.setProperty('--marketing-button-secondary-text', schemeButtonSecondaryText);
  }
  const schemeButtonSecondaryBorderColor = resolveFirstValue(
    marketingSchemeValues?.buttonSecondaryBorderColor,
    configColors.buttonSecondaryBorderColor,
    configColors.button_secondary_border_color,
    colors.buttonSecondaryBorderColor,
    variables.buttonSecondaryBorderColor,
  );
  if (schemeButtonSecondaryBorderColor) {
    root.style.setProperty('--marketing-button-secondary-border-color', schemeButtonSecondaryBorderColor);
  }
  const schemeButtonSecondaryHoverBg = resolveFirstValue(
    marketingSchemeValues?.buttonSecondaryHoverBg,
    configColors.buttonSecondaryHoverBg,
    configColors.button_secondary_hover_bg,
    colors.buttonSecondaryHoverBg,
    variables.buttonSecondaryHoverBg,
  );
  if (schemeButtonSecondaryHoverBg) {
    root.style.setProperty('--marketing-button-secondary-hover-bg', schemeButtonSecondaryHoverBg);
  }

  applyComponentTokens(root);
};

const runMarketingDesign = (attempt = 0) => {
  if (typeof document === 'undefined') {
    return;
  }
  const container = document.getElementById('marketing-design-data');
  if (!container && attempt < 10) {
    requestAnimationFrame(() => runMarketingDesign(attempt + 1));
    return;
  }

  applyMarketingDesign();
};

const scheduleMarketingDesign = () => {
  if (typeof document === 'undefined') {
    return;
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      requestAnimationFrame(() => runMarketingDesign());
    });
    return;
  }

  requestAnimationFrame(() => runMarketingDesign());
};

scheduleMarketingDesign();
