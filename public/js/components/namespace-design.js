const DEFAULT_NAMESPACE = 'default';

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

const resolveFirstValue = (...values) => {
  return values.find(value => value !== null && value !== undefined && value !== '');
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
  const accent = resolveFirstValue(
    colors.secondary,
    colors.accent,
    colors.brandAccent,
    colors.brand_accent,
    appearance?.variables?.secondary,
    appearance?.variables?.accent,
    appearance?.variables?.brandAccent,
    brand.accent,
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

  const marketingPrimary = resolveFirstValue(
    colors.marketingPrimary,
    colors.marketing_primary,
    appearance?.variables?.marketingPrimary,
    appearance?.variables?.marketing_primary,
    primary,
    'var(--brand-primary)',
  );
  const marketingAccent = resolveFirstValue(
    colors.marketingAccent,
    colors.marketing_accent,
    appearance?.variables?.marketingAccent,
    appearance?.variables?.marketing_accent,
    accent,
    'var(--brand-accent)',
  );
  const marketingOnAccent = resolveFirstValue(
    colors.marketingOnAccent,
    colors.marketing_on_accent,
    appearance?.variables?.marketingOnAccent,
    appearance?.variables?.marketing_on_accent,
    onAccent,
    'var(--text-on-primary)',
  );
  const marketingSurface = resolveFirstValue(
    colors.marketingSurface,
    colors.marketing_surface,
    appearance?.variables?.marketingSurface,
    appearance?.variables?.marketing_surface,
    surface,
    'var(--surface)',
  );
  const marketingMuted = resolveFirstValue(
    colors.marketingSurfaceMuted,
    colors.marketing_surface_muted,
    appearance?.variables?.marketingSurfaceMuted,
    appearance?.variables?.marketing_surface_muted,
    muted,
    'var(--surface-muted)',
  );

  if (primary) {
    element.style.setProperty('--brand-primary', primary);
    element.style.setProperty('--accent-primary', primary);
  }
  if (accent) {
    element.style.setProperty('--brand-accent', accent);
    element.style.setProperty('--accent-secondary', accent);
  }
  element.style.setProperty('--marketing-primary', marketingPrimary);
  element.style.setProperty('--marketing-accent', marketingAccent);
  element.style.setProperty('--marketing-on-accent', marketingOnAccent);
  element.style.setProperty('--bg-page', 'var(--surface)');
  element.style.setProperty('--bg-section', 'var(--surface)');
  element.style.setProperty('--bg-card', 'var(--surface)');
  element.style.setProperty('--bg-accent', 'var(--brand-primary)');

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
