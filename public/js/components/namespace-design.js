const DEFAULT_NAMESPACE = 'default';

const DEFAULT_BRAND_PRIMARY = '#1e87f0';
const DEFAULT_BRAND_ACCENT = '#f97316';

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

const mergeAppearance = (namespace, appearance = {}) => {
  const design = resolveDesignForNamespace(namespace);
  const designAppearance = design?.appearance || design || {};
  const configColors = design?.config?.colors || {};
  const baseAppearance = appearance && typeof appearance === 'object' ? appearance : {};
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

  return {
    ...baseAppearance,
    ...designAppearance,
    tokens,
    colors,
    variables,
  };
};

const applyColorsToRoot = (element, appearance) => {
  if (!element || typeof element?.style?.setProperty !== 'function') {
    return;
  }

  const tokens = appearance?.tokens || {};
  const colors = appearance?.colors || {};
  const brand = tokens.brand || {};

  const primary = colors.primary || brand.primary || DEFAULT_BRAND_PRIMARY;
  const accent = colors.secondary || colors.accent || brand.accent || DEFAULT_BRAND_ACCENT;
  const surface = colors.surface || appearance?.variables?.surface;
  const muted = colors.muted || appearance?.variables?.surfaceMuted;
  const topbarLight = colors.topbar_light || colors.topbarLight || appearance?.variables?.topbarLight;
  const topbarDark = colors.topbar_dark || colors.topbarDark || appearance?.variables?.topbarDark;
  const onAccent =
    colors.on_accent ||
    colors.onAccent ||
    colors.on_primary ||
    colors.onPrimary ||
    colors.contrastOnPrimary ||
    colors.text_on_primary ||
    colors.textOnPrimary ||
    appearance?.variables?.onAccent ||
    appearance?.variables?.onPrimary ||
    appearance?.variables?.textOnPrimary;

  const marketingPrimary = primary || 'var(--brand-primary)';
  const marketingAccent = accent || 'var(--brand-accent)';
  const marketingOnAccent = onAccent || 'var(--text-on-primary)';

  element.style.setProperty('--brand-primary', primary);
  element.style.setProperty('--accent-primary', primary);
  element.style.setProperty('--brand-accent', accent);
  element.style.setProperty('--accent-secondary', accent);
  element.style.setProperty('--marketing-primary', marketingPrimary);
  element.style.setProperty('--marketing-accent', marketingAccent);
  element.style.setProperty('--marketing-on-accent', marketingOnAccent);
  element.style.setProperty('--bg-page', 'var(--surface)');
  element.style.setProperty('--bg-section', 'var(--surface)');
  element.style.setProperty('--bg-card', 'var(--surface)');
  element.style.setProperty('--bg-accent', 'var(--brand-primary)');

  const marketingSurface = surface || 'var(--surface)';
  const marketingMuted = muted || 'var(--surface-muted)';

  element.style.setProperty('--marketing-surface', marketingSurface);
  element.style.setProperty('--marketing-surface-muted', marketingMuted);

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

export function applyNamespaceDesign(target, namespace = DEFAULT_NAMESPACE, appearance = {}) {
  const resolvedNamespace = normalizeNamespace(namespace);
  const resolvedAppearance = resolveNamespaceAppearance(resolvedNamespace, appearance);
  const root = target || (typeof document !== 'undefined' ? document.documentElement : null);

  applyColorsToRoot(root, resolvedAppearance);

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
