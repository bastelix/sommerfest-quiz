const DEFAULT_NAMESPACE = 'default';

const designRegistry = {};
const MARKETING_SCHEMES = {
  aurora: {
    primary: '#0ea5e9',
    accent: '#22c55e',
    surface: '#f8fafc',
    background: '#eef2ff',
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
    background: '#ffedd5',
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
    background: '#020617',
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
};

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
