const DEFAULT_BRAND_PRIMARY = '#1e87f0';
const DEFAULT_BRAND_ACCENT = '#f97316';
const DEFAULT_SURFACE = '#ffffff';
const DEFAULT_SURFACE_MUTED = '#eef2f7';

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
  const fallbackSurface = resolveFallbackToken(
    root,
    '--marketing-surface',
    resolveFallbackToken(root, '--surface', resolveFallbackToken(root, '--surface-card', DEFAULT_SURFACE)),
  );
  const fallbackMuted = resolveFallbackToken(
    root,
    '--marketing-surface-muted',
    resolveFallbackToken(root, '--surface-muted', DEFAULT_SURFACE_MUTED),
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

  const primary = configColors.primary || colors.primary || brand.primary || fallbackPrimary;
  const accent =
    configColors.accent ||
    configColors.secondary ||
    colors.accent ||
    colors.secondary ||
    brand.accent ||
    fallbackAccent;

  const surface =
    configColors.surface ||
    colors.surface ||
    variables.surface ||
    fallbackSurface;
  const surfaceMuted =
    configColors.surfaceMuted ||
    colors.surfaceMuted ||
    colors.muted ||
    variables.surfaceMuted ||
    fallbackMuted;
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

  root.style.setProperty('--marketing-primary', primary);
  root.style.setProperty('--marketing-accent', accent);
  root.style.setProperty('--marketing-surface', surface);
  root.style.setProperty('--marketing-surface-muted', surfaceMuted);
  root.style.setProperty('--brand-primary', 'var(--marketing-primary)');
  root.style.setProperty('--accent-primary', 'var(--marketing-primary)');
  root.style.setProperty('--brand-accent', 'var(--marketing-accent)');
  root.style.setProperty('--accent-secondary', 'var(--marketing-accent)');
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
};

applyMarketingDesign();
