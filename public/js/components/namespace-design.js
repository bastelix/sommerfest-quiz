const DEFAULT_NAMESPACE = 'default';

const DEFAULT_BRAND_PRIMARY = '#1e87f0';
const DEFAULT_BRAND_ACCENT = '#f97316';

const normalizeNamespace = namespace => {
  if (!namespace) {
    return DEFAULT_NAMESPACE;
  }
  return String(namespace).trim().toLowerCase() || DEFAULT_NAMESPACE;
};

const resolveDesignRegistry = () => {
  if (typeof window === 'undefined') {
    return {};
  }
  const registry = window.namespaceDesign;
  if (registry && typeof registry === 'object') {
    return registry;
  }
  return {};
};

const resolveDesignForNamespace = namespace => {
  const registry = resolveDesignRegistry();
  const normalized = normalizeNamespace(namespace);
  return registry[normalized] || null;
};

const mergeAppearance = (namespace, appearance = {}) => {
  const design = resolveDesignForNamespace(namespace);
  const designAppearance = design?.appearance || design || {};
  const baseAppearance = appearance && typeof appearance === 'object' ? appearance : {};
  const tokens = {
    ...(baseAppearance.tokens || {}),
    ...(designAppearance.tokens || {}),
  };
  const colors = {
    ...(baseAppearance.colors || {}),
    ...(designAppearance.colors || {}),
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

  element.style.setProperty('--brand-primary', primary);
  element.style.setProperty('--accent-primary', primary);
  element.style.setProperty('--brand-accent', accent);
  element.style.setProperty('--accent-secondary', accent);

  if (surface) {
    element.style.setProperty('--surface', surface);
  }

  if (muted) {
    element.style.setProperty('--surface-muted', muted);
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
