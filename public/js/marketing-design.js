const DEFAULT_BRAND_PRIMARY = 'var(--brand-primary)';
const DEFAULT_BRAND_ACCENT = 'var(--brand-secondary)';
const DEFAULT_SURFACE = 'var(--surface-section)';
const DEFAULT_SURFACE_MUTED = 'var(--surface-muted)';
const DEFAULT_SURFACE_DARK = 'var(--surface-section)';
const DEFAULT_SURFACE_MUTED_DARK = 'var(--surface-muted)';
const DEFAULT_CARD_DARK = 'var(--surface-card)';
const DEFAULT_TYPOGRAPHY_PRESET = 'modern';
const DEFAULT_CARD_STYLE = 'rounded';
const DEFAULT_BUTTON_STYLE = 'filled';
const TYPOGRAPHY_PRESETS = ['modern', 'classic', 'tech'];
const CARD_STYLES = ['rounded', 'square', 'pill'];
const BUTTON_STYLES = ['filled', 'outline', 'ghost'];

const MARKETING_SCHEMES = {
  aurora: {
    primary: '#0ea5e9',
    accent: '#22c55e',
    surface: '#f8fafc',
    surfaceMuted: '#e2e8f0',
    surfaceDark: '#0f172a',
    surfaceMutedDark: '#1e293b',
    background: '#eef2ff',
    backgroundDark: '#020617',
    onAccent: '#ffffff',
    textOnSurface: '#0f172a',
    textOnBackground: '#0f172a',
    textMutedOnSurface: '#475569',
    textMutedOnBackground: '#475569',
    textOnSurfaceDark: '#f8fafc',
    textOnBackgroundDark: '#f8fafc',
    textMutedOnSurfaceDark: '#cbd5e1',
    textMutedOnBackgroundDark: '#cbd5e1',
  },
  sunset: {
    primary: '#f97316',
    accent: '#ec4899',
    surface: '#fff7ed',
    surfaceMuted: '#fed7aa',
    surfaceDark: '#0f172a',
    surfaceMutedDark: '#1e293b',
    background: '#ffedd5',
    backgroundDark: '#020617',
    onAccent: '#1f2937',
    textOnSurface: '#1f2937',
    textOnBackground: '#1f2937',
    textMutedOnSurface: '#6b7280',
    textMutedOnBackground: '#6b7280',
    textOnSurfaceDark: '#f8fafc',
    textOnBackgroundDark: '#f8fafc',
    textMutedOnSurfaceDark: '#cbd5e1',
    textMutedOnBackgroundDark: '#cbd5e1',
  },
  midnight: {
    primary: '#6366f1',
    accent: '#14b8a6',
    surface: '#0f172a',
    surfaceMuted: '#1e293b',
    surfaceDark: '#0f172a',
    surfaceMutedDark: '#1e293b',
    background: '#020617',
    backgroundDark: '#020617',
    onAccent: '#f8fafc',
    textOnSurface: '#e2e8f0',
    textOnBackground: '#e2e8f0',
    textMutedOnSurface: '#94a3b8',
    textMutedOnBackground: '#94a3b8',
    textOnSurfaceDark: '#f8fafc',
    textOnBackgroundDark: '#f8fafc',
    textMutedOnSurfaceDark: '#cbd5e1',
    textMutedOnBackgroundDark: '#cbd5e1',
  },
  monochrome: {
    primary: '#111111',
    accent: '#1f1f1f',
    surface: '#ffffff',
    surfaceMuted: '#f2f2f2',
    surfaceDark: '#111111',
    surfaceMutedDark: '#1c1c1c',
    background: '#f9f9f9',
    backgroundDark: '#0a0a0a',
    onAccent: '#ffffff',
    textOnSurface: '#111111',
    textOnBackground: '#111111',
    textMutedOnSurface: '#4b4b4b',
    textMutedOnBackground: '#4b4b4b',
    textOnSurfaceDark: '#f5f5f5',
    textOnBackgroundDark: '#f5f5f5',
    textMutedOnSurfaceDark: '#bdbdbd',
    textMutedOnBackgroundDark: '#bdbdbd',
  },
};

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

const resolveDataAttributes = () => {
  if (typeof document === 'undefined') {
    return { appearance: null, config: null };
  }
  const container = document.getElementById('marketing-design-data');
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
  const dataAttributes = resolveDataAttributes();
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

  const { appearance, config } = resolveMarketingAppearance();
  const tokens = appearance?.tokens || {};
  const colors = appearance?.colors || {};
  const variables = appearance?.variables || {};
  const configColors = config?.colors || {};
  const brand = tokens.brand || {};

  const fallbackPrimary = resolveFallbackToken(
    root,
    '--marketing-primary',
    resolveFallbackToken(root, '--brand-primary', DEFAULT_BRAND_PRIMARY),
  );
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

  const marketingScheme = resolveFirstValue(
    variables.marketingScheme,
    variables.marketing_scheme,
    colors.marketingScheme,
    colors.marketing_scheme,
    configColors.marketingScheme,
    configColors.marketing_scheme,
  );
  const marketingSchemeValues = marketingScheme ? MARKETING_SCHEMES[marketingScheme] : null;
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
    marketingSchemeValues?.accent,
    configColors.marketingSecondary,
    configColors.marketing_secondary,
    colors.marketingSecondary,
    colors.marketing_secondary,
    variables.marketingSecondary,
    variables.marketing_secondary,
    secondary,
  );
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
    configColors.surfaceMuted ||
    colors.surfaceMuted ||
    colors.muted ||
    variables.surfaceMuted ||
    fallbackMuted;
  const marketingSurfaceMuted = resolveFirstValue(
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

  root.style.setProperty('--marketing-primary', marketingPrimary);
  root.style.setProperty('--marketing-accent', marketingAccent);
  root.style.setProperty('--marketing-secondary', marketingSecondary);
  root.style.setProperty('--marketing-background', marketingBackground);
  if (marketingBackgroundDark) {
    root.style.setProperty('--marketing-background-dark', marketingBackgroundDark);
  }
  root.style.setProperty('--marketing-on-accent', marketingOnAccent);
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
  if (surfaceDark) {
    root.style.setProperty('--marketing-surface-dark', surfaceDark);
  }
  if (surfaceMutedDark) {
    root.style.setProperty('--marketing-surface-muted-dark', surfaceMutedDark);
  }
  if (cardDark) {
    root.style.setProperty('--marketing-card-dark', cardDark);
  }
  root.style.setProperty('--brand-primary', 'var(--marketing-primary)');
  root.style.setProperty('--accent-primary', 'var(--marketing-primary)');
  root.style.setProperty('--brand-accent', 'var(--marketing-accent)');
  root.style.setProperty('--brand-secondary', 'var(--marketing-secondary)');
  root.style.setProperty('--accent-secondary', 'var(--marketing-secondary)');
  root.style.setProperty('--surface', 'var(--marketing-surface)');
  root.style.setProperty('--surface-muted', 'var(--marketing-surface-muted)');
  root.style.setProperty('--bg-page', 'var(--surface)');
  root.style.setProperty('--bg-section', 'var(--surface)');
  root.style.setProperty('--bg-card', 'var(--surface)');
  root.style.setProperty('--bg-accent', 'var(--brand-primary)');

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

  applyComponentTokens(root);
};

applyMarketingDesign();
