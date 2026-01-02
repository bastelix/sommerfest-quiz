const DEFAULT_NAMESPACE = 'default';

const DEFAULT_BRAND_PRIMARY = '#1e87f0';
const DEFAULT_BRAND_ACCENT = '#f97316';

const designRegistry = {};
let hasBootstrappedRegistry = false;

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

const bootstrapRegistryFromDocument = () => {
  if (hasBootstrappedRegistry) {
    return;
  }
  hasBootstrappedRegistry = true;

  if (typeof document === 'undefined') {
    return;
  }

  const payload = document.documentElement?.dataset?.namespaceDesign;
  if (!payload) {
    return;
  }

  try {
    const parsed = JSON.parse(payload);
    const candidates = Array.isArray(parsed) ? parsed : [parsed];
    candidates
      .filter(entry => entry && typeof entry === 'object')
      .forEach(entry => mergeIntoRegistry(entry.namespace, entry));
  } catch (error) {
    console.error('Failed to bootstrap namespace design registry', error);
  }
};

const resolveDesignRegistry = () => {
  if (!Object.keys(designRegistry).length) {
    bootstrapRegistryFromDocument();
  }

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

  if (topbarLight) {
    element.style.setProperty('--qr-landing-topbar-bg-light', topbarLight);
  }

  if (topbarDark) {
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
