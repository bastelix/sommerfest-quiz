import { resolveSectionIntentInfo } from './section-intents.js';

export function escapeHtml(value) {
  if (value === null || value === undefined) {
    return '';
  }
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

export function escapeAttribute(value) {
  return escapeHtml(value).replace(/`/g, '&#x60;');
}

const DEFAULT_APPEARANCE = {
  colors: {
    primary: 'var(--accent-primary, var(--brand-primary, var(--marketing-primary)))',
    secondary: 'var(--accent-secondary, var(--brand-accent, var(--marketing-accent, var(--marketing-primary))))',
    accent: 'var(--brand-accent, var(--accent-secondary, var(--accent-primary, var(--marketing-accent))))',
    muted: 'var(--surface-muted, var(--marketing-surface-muted))',
    surface: 'var(--surface, var(--marketing-surface))',
  },
  variables: {},
};

let activeAppearance = DEFAULT_APPEARANCE;
const DEFAULT_PAGE_CONTEXT = { sectionStyleDefaults: {} };
let activePageContext = DEFAULT_PAGE_CONTEXT;

function resolveComputedAppearance() {
  if (typeof window === 'undefined' || typeof window.getComputedStyle !== 'function') {
    return {};
  }

  try {
    const styles = window.getComputedStyle(document.documentElement);
    return {
      surface: styles.getPropertyValue('--surface')?.trim(),
      muted: styles.getPropertyValue('--surface-muted')?.trim(),
      primary: styles.getPropertyValue('--accent-primary')?.trim() || styles.getPropertyValue('--brand-primary')?.trim(),
      secondary: styles.getPropertyValue('--accent-secondary')?.trim() || styles.getPropertyValue('--brand-accent')?.trim(),
      accent: styles.getPropertyValue('--brand-accent')?.trim() || styles.getPropertyValue('--accent-secondary')?.trim(),
    };
  } catch (error) {
    return {};
  }
}

function normalizeAppearance(appearance) {
  const resolved = { ...DEFAULT_APPEARANCE, colors: { ...DEFAULT_APPEARANCE.colors }, variables: {} };
  const colors = appearance?.colors || {};
  Object.entries(colors).forEach(([key, value]) => {
    if (typeof value === 'string' && value.trim() !== '') {
      resolved.colors[key] = value.trim();
    }
  });

  const tokens = appearance?.tokens || {};
  const brand = tokens.brand || {};
  if (!colors.primary && typeof brand.primary === 'string' && brand.primary.trim()) {
    resolved.colors.primary = brand.primary.trim();
  }
  if (!colors.secondary && typeof brand.accent === 'string' && brand.accent.trim()) {
    resolved.colors.secondary = brand.accent.trim();
  }
  if (!colors.accent && typeof brand.accent === 'string' && brand.accent.trim()) {
    resolved.colors.accent = brand.accent.trim();
  }

  const computed = resolveComputedAppearance();
  Object.entries(computed).forEach(([key, value]) => {
    if (typeof value === 'string' && value !== '' && resolved.colors[key] === DEFAULT_APPEARANCE.colors[key]) {
      resolved.colors[key] = value;
    }
  });

  if (appearance?.variables && typeof appearance.variables === 'object') {
    resolved.variables = appearance.variables;
  }

  return resolved;
}

export function setActiveAppearance(appearance) {
  const previous = activeAppearance;
  activeAppearance = normalizeAppearance(appearance);
  return previous;
}

export function setPageContext(context) {
  const normalized = context && typeof context === 'object' ? context : {};
  const sectionStyleDefaults = normalized.sectionStyleDefaults && typeof normalized.sectionStyleDefaults === 'object'
    ? normalized.sectionStyleDefaults
    : {};
  const previous = activePageContext;
  activePageContext = { ...DEFAULT_PAGE_CONTEXT, ...normalized, sectionStyleDefaults };
  return previous;
}

export function withPageContext(context, callback) {
  const previous = setPageContext(context);
  const result = callback();
  activePageContext = previous;
  return result;
}

export function withAppearance(appearance, callback) {
  const previous = setActiveAppearance(appearance);
  const result = callback();
  activeAppearance = previous;
  return result;
}

function resolveActiveAppearance() {
  return activeAppearance || DEFAULT_APPEARANCE;
}

function resolveActivePageContext() {
  return activePageContext || DEFAULT_PAGE_CONTEXT;
}

const PREVIEW_CONTEXT = 'preview';

function buildEditableAttributes(block, fieldPath, context, { type = 'text' } = {}) {
  if (context !== PREVIEW_CONTEXT || !block?.id || !fieldPath) {
    return '';
  }

  const attributes = [
    'data-editable="true"',
    `data-block-id="${escapeAttribute(block.id)}"`,
    `data-field-path="${escapeAttribute(fieldPath)}"`,
    `data-editable-type="${type === 'richtext' ? 'richtext' : 'text'}"`
  ];

  return ` ${attributes.join(' ')}`;
}

function resolveBasePath() {
  if (typeof window === 'undefined' || typeof window.basePath !== 'string') {
    return '';
  }

  const trimmed = window.basePath.trim();
  if (trimmed === '' || trimmed === '/') {
    return '';
  }

  return trimmed.endsWith('/') ? trimmed.slice(0, -1) : trimmed;
}

function resolveContactEndpoint() {
  const basePath = resolveBasePath();
  const endpoint = `${basePath}/landing/contact`;
  return endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
}

function resolveCsrfToken() {
  if (typeof window === 'undefined') {
    return '';
  }

  if (typeof window.csrfToken === 'string' && window.csrfToken.trim() !== '') {
    return window.csrfToken.trim();
  }

  if (typeof document !== 'undefined') {
    const meta = document.querySelector('meta[name="csrf-token"]');
    const metaContent = meta?.getAttribute('content');
    if (metaContent) {
      return metaContent;
    }
  }

  return '';
}

const SECTION_LAYOUTS = ['normal', 'fullwidth', 'card'];
const BACKGROUND_MODES_BY_LAYOUT = {
  normal: ['none', 'color'],
  fullwidth: ['none', 'color', 'image'],
  card: ['none', 'color']
};

const BACKGROUND_COLOR_TOKENS = ['primary', 'secondary', 'muted', 'accent', 'surface'];

function clampBackgroundOverlay(value) {
  if (value === null || value === undefined) {
    return undefined;
  }

  const numeric = typeof value === 'number' ? value : Number.parseFloat(value);
  if (Number.isNaN(numeric)) {
    return undefined;
  }

  return Math.min(1, Math.max(0, numeric));
}

function normalizeSectionStyle(block) {
  const defaults = resolveActivePageContext().sectionStyleDefaults;
  const baseStyle = defaults && typeof defaults === 'object' ? defaults : {};
  const blockStyle = block?.meta?.sectionStyle && typeof block.meta.sectionStyle === 'object'
    ? block.meta.sectionStyle
    : {};

  const baseBackground = baseStyle.background && typeof baseStyle.background === 'object' ? baseStyle.background : {};
  const blockBackground = blockStyle.background && typeof blockStyle.background === 'object' ? blockStyle.background : {};

  return {
    ...baseStyle,
    ...blockStyle,
    background: { ...baseBackground, ...blockBackground }
  };
}

function resolveSectionLayout(block) {
  const sectionStyle = normalizeSectionStyle(block);
  const layout = typeof sectionStyle.layout === 'string' ? sectionStyle.layout.trim() : '';
  return SECTION_LAYOUTS.includes(layout) ? layout : 'normal';
}

function resolveSectionBackground(block, layout) {
  const allowedModes = BACKGROUND_MODES_BY_LAYOUT[layout] || ['none'];
  const background = normalizeSectionStyle(block).background || {};
  const rawMode = typeof background.mode === 'string'
    ? background.mode.trim()
    : typeof background.type === 'string'
      ? background.type.trim()
      : '';
  const colorToken = typeof background.colorToken === 'string'
    ? background.colorToken.trim()
    : typeof background.color === 'string'
      ? background.color.trim()
      : '';
  const imageId = typeof background.imageId === 'string'
    ? background.imageId.trim()
    : typeof background.image === 'string'
      ? background.image.trim()
      : '';
  const attachment = background.attachment === 'fixed' ? 'fixed' : 'scroll';
  const overlay = clampBackgroundOverlay(background.overlay);

  let mode = allowedModes.includes(rawMode) ? rawMode : '';

  if (!mode && colorToken) {
    mode = 'color';
  }

  if (!mode && imageId) {
    mode = 'image';
  }

  if (!mode || !allowedModes.includes(mode)) {
    mode = 'none';
  }

  if (mode === 'color') {
    return BACKGROUND_COLOR_TOKENS.includes(colorToken)
      ? { mode, colorToken }
      : { mode: 'none' };
  }

  if (mode === 'image') {
    if (layout !== 'fullwidth' || !imageId) {
      return { mode: 'none' };
    }

    const resolvedBackground = {
      mode: 'image',
      imageId,
      attachment
    };

    if (overlay !== undefined) {
      resolvedBackground.overlay = overlay;
    }

    return resolvedBackground;
  }

  return { mode: 'none' };
}

function resolveBackgroundColor(token) {
  const palette = resolveActiveAppearance().colors;
  if (token && palette[token]) {
    return palette[token];
  }

  return null;
}

function resolveBackgroundImage(imageId) {
  if (!imageId || typeof imageId !== 'string') {
    return null;
  }

  const trimmed = imageId.trim();
  if (trimmed === '') {
    return null;
  }
  if (/^https?:\/\//i.test(trimmed)) {
    return trimmed;
  }

  const basePath = resolveBasePath();
  return `${basePath}${trimmed.startsWith('/') ? '' : '/'}${trimmed}`;
}

function resolveSectionBackgroundStyles(background) {
  const styles = [];
  const dataAttributes = [];

  if (background.mode === 'color') {
    const colorValue = resolveBackgroundColor(background.colorToken);
    if (colorValue) {
      styles.push(`--section-bg-color:${colorValue}`);
      dataAttributes.push('data-section-background="color"');
    }
  }

  if (background.mode === 'image') {
    const imageUrl = resolveBackgroundImage(background.imageId);
    if (imageUrl) {
      styles.push(`--section-bg-image:url('${escapeAttribute(imageUrl)}')`);
      styles.push(`--section-bg-attachment:${background.attachment || 'scroll'}`);
      if (background.overlay !== undefined) {
        styles.push(`--section-bg-overlay:${background.overlay}`);
      }
      dataAttributes.push('data-section-background="image"');
    }
  }

  const style = styles.length ? `${styles.join('; ')};` : '';

  return { style, dataAttributes };
}

const SECTION_INTENT_CONFIG = {
  content: {
    sectionClass: 'uk-section-medium',
    containerClass: '',
    innerClass: 'section__inner--panel',
    surfaceToken: 'surface'
  },
  feature: {
    sectionClass: 'uk-section-large',
    containerClass: 'uk-container-large',
    innerClass: 'section__inner--card uk-card uk-card-default uk-card-body uk-box-shadow-medium',
    surfaceToken: 'muted'
  },
  highlight: {
    sectionClass: 'uk-section-large',
    containerClass: 'uk-container-large',
    innerClass: 'section__inner--accent',
    surfaceToken: 'primary',
    textToken: { token: 'text-on-primary', fallback: 'var(--text-on-primary, var(--marketing-text-on-primary))' }
  },
  hero: {
    sectionClass: 'uk-section-large',
    containerClass: 'uk-container-expand',
    innerClass: 'section__inner--hero uk-card uk-card-large uk-card-body',
    surfaceToken: 'secondary',
    textToken: { token: 'text-on-primary', fallback: 'var(--text-on-primary, var(--marketing-text-on-primary))' }
  }
};

function resolveAppearanceValue(token, fallback) {
  if (!token) {
    return fallback;
  }

  const palette = resolveActiveAppearance().colors || {};
  const normalizedToken = token.startsWith('--') ? token.slice(2) : token;
  const resolved = palette[normalizedToken];

  if (typeof resolved === 'string' && resolved.trim() !== '') {
    return resolved.trim();
  }

  const cssVariable = `var(--${normalizedToken})`;
  if (fallback && typeof fallback === 'string') {
    return fallback;
  }

  return cssVariable;
}

function resolveSectionIntentPreset(block) {
  const { intent, isExplicit } = resolveSectionIntentInfo(block);
  const basePreset = SECTION_INTENT_CONFIG[intent] || SECTION_INTENT_CONFIG.content;
  const surface = resolveAppearanceValue(basePreset.surfaceToken, DEFAULT_APPEARANCE.colors[basePreset.surfaceToken]);
  const textColor = basePreset.textToken
    ? resolveAppearanceValue(basePreset.textToken.token, basePreset.textToken.fallback)
    : undefined;
  const styleVariables = [];

  if (isExplicit && surface) {
    styleVariables.push(`--section-surface:${surface}`);
    styleVariables.push(`--section-bg-color:${surface}`);
  }

  if (isExplicit && textColor) {
    styleVariables.push(`--section-text-color:${textColor}`);
  }

  return {
    intent,
    preset: {
      ...basePreset,
      appearanceTokens: {
        surface: basePreset.surfaceToken,
        text: basePreset.textToken?.token
      },
      styleVariables
    }
  };
}

function normalizeClassList(classes) {
  if (Array.isArray(classes)) {
    return classes.flatMap(normalizeClassList);
  }
  if (typeof classes === 'string') {
    return classes.split(' ').filter(Boolean);
  }
  return [];
}

function renderSection({ block, variant, content, sectionClass = '', containerClass = '', container = true }) {
  const layout = resolveSectionLayout(block);
  const background = resolveSectionBackground(block, layout);
  const backgroundStyle = resolveSectionBackgroundStyles(background);
  const { intent, preset } = resolveSectionIntentPreset(block);
  const presetStyle = preset.styleVariables.length ? `${preset.styleVariables.join('; ')};` : '';
  const anchor = block?.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const classes = [
    'section',
    'uk-section',
    ...normalizeClassList(preset.sectionClass),
    `section--${layout}`,
    background.mode === 'color' ? `section--bg-${background.colorToken}` : '',
    background.mode === 'image' ? 'section--bg-image' : '',
    background.mode === 'image' && background.attachment === 'fixed' ? 'section--bg-fixed' : '',
    ...normalizeClassList(sectionClass)
  ]
    .filter(Boolean)
    .join(' ');
  const dataAttributes = [
    `data-block-id="${escapeAttribute(block.id)}"`,
    `data-block-type="${escapeAttribute(block.type)}"`,
    `data-block-variant="${escapeAttribute(variant)}"`,
    `data-section-intent="${escapeAttribute(intent)}"`,
    `data-section-layout="${escapeAttribute(layout)}"`,
    `data-section-background-mode="${escapeAttribute(background.mode)}"`,
    preset.appearanceTokens?.surface
      ? `data-section-surface-token="${escapeAttribute(preset.appearanceTokens.surface)}"`
      : null,
    preset.appearanceTokens?.text
      ? `data-section-text-token="${escapeAttribute(preset.appearanceTokens.text)}"`
      : null,
    background.mode === 'color' && background.colorToken
      ? `data-section-background-color-token="${escapeAttribute(background.colorToken)}"`
      : null,
    background.mode === 'image' && background.imageId
      ? `data-section-background-image-id="${escapeAttribute(background.imageId)}"`
      : null,
    background.mode === 'image' && background.attachment
      ? `data-section-background-attachment="${escapeAttribute(background.attachment)}"`
      : null,
    background.mode === 'image' && background.overlay !== undefined
      ? `data-section-background-overlay="${escapeAttribute(String(background.overlay))}"`
      : null,
    ...backgroundStyle.dataAttributes,
  ]
    .filter(Boolean)
    .join(' ');
  const innerClassName = ['section__inner', ...normalizeClassList(preset.innerClass)].filter(Boolean).join(' ');
  const contentWrapper = `<div class="${innerClassName}">${content}</div>`;
  const containerClasses = [
    ...normalizeClassList(containerClass),
    ...normalizeClassList(preset.containerClass)
  ].filter(Boolean).join(' ');
  const inner = container
    ? `<div class="uk-container${containerClasses ? ` ${containerClasses}` : ''}">${contentWrapper}</div>`
    : contentWrapper;

  const dataAttributesString = dataAttributes ? ` ${dataAttributes}` : '';

  const styleSegments = [presetStyle, backgroundStyle.style].filter(Boolean).map(value => value.trim().replace(/;$/, ''));
  const style = styleSegments.length ? ` style="${styleSegments.join('; ')};"` : '';

  return `<section${anchor} class="${classes}"${dataAttributesString}${style}>${inner}</section>`;
}

function renderHeroSection({ block, variant, content, sectionModifiers = '' }) {
  return renderSection({ block, variant, content, sectionClass: sectionModifiers });
}

function renderContentSliderHeader(block, context = 'frontend') {
  const eyebrow = block?.data?.eyebrow
    ? `<p class="uk-text-meta uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.eyebrow', context)}>${escapeHtml(block.data.eyebrow)}</p>`
    : '';
  const title = block?.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-small-top uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const intro = block?.data?.intro
    ? `<p class="uk-text-lead uk-margin-small-top"${buildEditableAttributes(block, 'data.intro', context)}>${escapeHtml(block.data.intro)}</p>`
    : '';

  const content = [eyebrow, title, intro].filter(Boolean).join('');
  if (!content) {
    return '';
  }

  return `<div class="uk-margin-medium-bottom uk-text-center">${content}</div>`;
}

function resolveContentSliderImage(slide) {
  const source = typeof slide?.imageId === 'string' && slide.imageId.trim() !== '' ? slide.imageId.trim() : '';
  if (!source) {
    return '';
  }

  return resolveBackgroundImage(source) || source;
}

function renderContentSliderSlide(block, slide, index, variant, context = 'frontend') {
  if (!slide || typeof slide !== 'object') {
    return '';
  }

  const labelAttributes = buildEditableAttributes(block, `data.slides.${index}.label`, context);
  const bodyAttributes = buildEditableAttributes(block, `data.slides.${index}.body`, context, { type: 'richtext' });
  const label = slide.label ? `<h3 class="uk-h4 uk-margin-remove-bottom"${labelAttributes}>${escapeHtml(slide.label)}</h3>` : '';
  const body = slide.body
    ? `<div class="uk-margin-small-top"${bodyAttributes}>${slide.body}</div>`
    : '';

  const link = slide.link?.label && slide.link?.href
    ? `<div class="uk-margin-top"><a class="uk-button uk-button-text" href="${escapeAttribute(slide.link.href)}">${escapeHtml(slide.link.label)}</a></div>`
    : '';

  const imageSrc = resolveContentSliderImage(slide);
  const imageAlt = slide.imageAlt ? escapeAttribute(slide.imageAlt) : slide.label ? escapeAttribute(slide.label) : '';
  const image = imageSrc
    ? `<div class="content-slider__media"><img class="uk-border-rounded uk-width-1-1" src="${escapeAttribute(imageSrc)}" alt="${imageAlt}" loading="lazy"></div>`
    : '';

  const textContent = `<div class="content-slider__body">${label}${body}${link}</div>`;
  const cardContent = variant === 'images' && image
    ? `<div class="uk-card uk-card-default uk-overflow-hidden">${image}<div class="uk-card-body">${textContent}</div></div>`
    : `<div class="uk-card uk-card-default uk-card-body">${image}${textContent}</div>`;

  const itemId = slide.id ? ` id="${escapeAttribute(slide.id)}"` : '';
  return `<li class="content-slider__item"${itemId}>${cardContent}</li>`;
}

function renderContentSlider(block, variant = 'words', options = {}) {
  const context = options?.context || 'frontend';
  const slides = Array.isArray(block?.data?.slides) ? block.data.slides : [];

  const header = renderContentSliderHeader(block, context);
  const sliderItems = slides
    .map((slide, index) => renderContentSliderSlide(block, slide, index, variant, context))
    .filter(Boolean)
    .join('');

  const fallback = '<li><div class="uk-alert-warning" role="alert">Keine Slides hinterlegt.</div></li>';

  const slider = `
    <div class="content-slider content-slider--${escapeAttribute(variant)}" data-uk-slider="finite: false">
      <div class="uk-position-relative">
        <div class="uk-slider-container uk-slider-container-offset">
          <ul class="uk-slider-items uk-grid uk-grid-small uk-child-width-1-1">${sliderItems || fallback}</ul>
        </div>
        <div class="uk-visible@s">
          <a class="uk-position-center-left uk-position-small uk-slidenav-large" href="#" data-uk-slidenav-previous data-uk-slider-item="previous"></a>
          <a class="uk-position-center-right uk-position-small uk-slidenav-large" href="#" data-uk-slidenav-next data-uk-slider-item="next"></a>
        </div>
        <div class="uk-hidden@s uk-light">
          <a class="uk-position-center-left uk-position-small" href="#" data-uk-slidenav-previous data-uk-slider-item="previous"></a>
          <a class="uk-position-center-right uk-position-small" href="#" data-uk-slidenav-next data-uk-slider-item="next"></a>
        </div>
        <ul class="uk-slider-nav uk-dotnav uk-flex-center uk-margin-top"></ul>
      </div>
    </div>`;

  return renderSection({ block, variant, content: `${header}${slider}` });
}

function renderEyebrow(block, alignmentClass = '', context = 'frontend') {
  const eyebrow = block?.data?.eyebrow;
  if (!eyebrow) {
    return '';
  }
  const alignment = alignmentClass ? ` ${alignmentClass}` : '';
  const editable = buildEditableAttributes(block, 'data.eyebrow', context);
  return `<p class="uk-text-meta uk-margin-remove-bottom${alignment}"${editable}>${escapeHtml(eyebrow)}</p>`;
}

function renderHeadline(block, alignmentClass = '', context = 'frontend') {
  const headline = block?.data?.headline;
  const alignment = alignmentClass ? ` ${alignmentClass}` : '';
  const editable = buildEditableAttributes(block, 'data.headline', context);
  return `<h1 class="uk-heading-medium uk-margin-small-top${alignment}"${editable}>${escapeHtml(headline || '')}</h1>`;
}

function renderSubheadline(block, alignmentClass = '', context = 'frontend') {
  const subheadline = block?.data?.subheadline;
  if (!subheadline) {
    return '';
  }
  const alignment = alignmentClass ? ` ${alignmentClass}` : '';
  const editable = buildEditableAttributes(block, 'data.subheadline', context);
  return `<p class="uk-text-lead uk-margin-small-top uk-margin-remove-bottom${alignment}"${editable}>${escapeHtml(subheadline)}</p>`;
}

function renderHeroMedia(media) {
  if (!media || !media.image) {
    return '';
  }
  const altText = media.alt ? escapeAttribute(media.alt) : '';
  return `<div class="uk-cover-container uk-height-medium uk-border-rounded uk-box-shadow-small"><img src="${escapeAttribute(media.image)}" alt="${altText}" loading="lazy" data-uk-cover><canvas width="800" height="600"></canvas></div>`;
}

function renderHeroCtas(cta, alignmentClass = '') {
  if (!cta) {
    return '';
  }

  const buttons = [];
  const primary = cta.primary || cta;
  const secondary = cta.secondary;

  if (primary?.label && primary?.href) {
    const ariaLabel = primary.ariaLabel ? ` aria-label="${escapeAttribute(primary.ariaLabel)}"` : '';
    buttons.push(`<a class="uk-button uk-button-primary" href="${escapeAttribute(primary.href)}"${ariaLabel}>${escapeHtml(primary.label)}</a>`);
  }

  if (secondary?.label && secondary?.href) {
    const ariaLabel = secondary.ariaLabel ? ` aria-label="${escapeAttribute(secondary.ariaLabel)}"` : '';
    const marginClass = buttons.length ? ' uk-margin-small-left' : '';
    buttons.push(`<a class="uk-button uk-button-default${marginClass}" href="${escapeAttribute(secondary.href)}"${ariaLabel}>${escapeHtml(secondary.label)}</a>`);
  }

  if (!buttons.length) {
    return '';
  }

  const alignment = alignmentClass ? ` ${alignmentClass}` : '';
  return `<div class="uk-margin-medium-top uk-flex uk-flex-middle uk-flex-wrap${alignment}">${buttons.join('')}</div>`;
}

function renderHeroCenteredCta(block, options = {}) {
  const context = options?.context || 'frontend';
  const eyebrow = renderEyebrow(block, 'uk-text-center', context);
  const headline = renderHeadline(block, 'uk-text-center', context);
  const subheadline = renderSubheadline(block, 'uk-text-center', context);
  const ctas = renderHeroCtas(block.data?.cta, 'uk-flex-center');
  const content = `<div class="uk-width-1-1 uk-width-2-3@m uk-align-center uk-text-center">${eyebrow}${headline}${subheadline}${ctas}</div>`;
  return renderHeroSection({ block, variant: 'centered_cta', content });
}

function renderHeroMediaRight(block, options = {}) {
  const context = options?.context || 'frontend';
  const eyebrow = renderEyebrow(block, '', context);
  const headline = renderHeadline(block, '', context);
  const subheadline = renderSubheadline(block, '', context);
  const ctas = renderHeroCtas(block.data?.cta);
  const media = renderHeroMedia(block.data?.media);
  const textColumnWidth = media ? 'uk-width-1-1 uk-width-1-2@m' : 'uk-width-1-1';
  const mediaColumn = media ? `<div class="uk-width-1-1 uk-width-1-2@m">${media}</div>` : '';
  const textColumn = `<div class="${textColumnWidth}">${eyebrow}${headline}${subheadline}${ctas}</div>`;
  const grid = `<div class="uk-grid-large uk-flex-middle" data-uk-grid>${textColumn}${mediaColumn}</div>`;
  return renderHeroSection({ block, variant: 'media_right', content: grid });
}

function renderHeroMediaLeft(block, options = {}) {
  const context = options?.context || 'frontend';
  const eyebrow = renderEyebrow(block, '', context);
  const headline = renderHeadline(block, '', context);
  const subheadline = renderSubheadline(block, '', context);
  const ctas = renderHeroCtas(block.data?.cta);
  const media = renderHeroMedia(block.data?.media);
  const textColumnWidth = media ? 'uk-width-1-1 uk-width-1-2@m' : 'uk-width-1-1';
  const mediaColumn = media ? `<div class="uk-width-1-1 uk-width-1-2@m">${media}</div>` : '';
  const textColumn = `<div class="${textColumnWidth}">${eyebrow}${headline}${subheadline}${ctas}</div>`;
  const grid = `<div class="uk-grid-large uk-flex-middle" data-uk-grid>${mediaColumn}${textColumn}</div>`;
  return renderHeroSection({ block, variant: 'media_left', content: grid });
}

function renderHeroMinimal(block, options = {}) {
  const context = options?.context || 'frontend';
  const eyebrow = renderEyebrow(block, '', context);
  const headline = renderHeadline(block, '', context);
  const subheadline = renderSubheadline(block, '', context);
  const content = `<div class="uk-width-1-1 uk-width-3-4@m uk-align-center">${eyebrow}${headline}${subheadline}</div>`;
  return renderHeroSection({ block, variant: 'minimal', content, sectionModifiers: 'uk-section-small' });
}

function renderFeatureBullets(bullets) {
  if (!Array.isArray(bullets) || bullets.length === 0) {
    return '';
  }

  const items = bullets
    .filter(text => typeof text === 'string' && text.length > 0)
    .map(text => `<li>${escapeHtml(text)}</li>`);

  if (!items.length) {
    return '';
  }

  return `<ul class="uk-list uk-list-bullet uk-margin-small-top">${items.join('')}</ul>`;
}

function renderFeatureMedia(media) {
  if (!media || !media.image) {
    return '';
  }

  const altText = media.alt ? escapeAttribute(media.alt) : '';
  return `<div class="uk-card-media-top"><img src="${escapeAttribute(media.image)}" alt="${altText}" loading="lazy" class="uk-border-rounded"></div>`;
}

function renderFeatureListItemContent(item) {
  const title = `<h3 class="uk-h3 uk-margin-remove-bottom">${escapeHtml(item.title || '')}</h3>`;
  const description = item.description
    ? `<div class="uk-margin-small-top uk-margin-remove-bottom">${item.description}</div>`
    : '';
  const bullets = renderFeatureBullets(item.bullets);

  return `${title}${description}${bullets}`;
}

function normalizeFeatureListItems(block) {
  if (!Array.isArray(block.data?.items)) {
    return [];
  }

  return block.data.items.filter(item => item && typeof item.title === 'string' && typeof item.description === 'string');
}

function renderFeatureListHeader(block, context = 'frontend') {
  const title = block?.data?.title;
  const subtitle = block?.data?.subtitle;

  if (!title && !subtitle) {
    return '';
  }

  const heading = title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(title)}</h2>`
    : '';
  const subheading = subtitle
    ? `<p class="uk-text-lead uk-margin-small-top"${buildEditableAttributes(block, 'data.subtitle', context)}>${escapeHtml(subtitle)}</p>`
    : '';

  return `<div class="uk-width-1-1 uk-margin-medium-bottom">${heading}${subheading}</div>`;
}

function renderFeatureListTextColumns(items) {
  const columns = items
    .map(item => {
      const content = renderFeatureListItemContent(item);
      return `<div class="uk-width-1-1 uk-width-1-2@m">${content}</div>`;
    })
    .join('');

  return `<div class="uk-grid uk-grid-large" data-uk-grid>${columns}</div>`;
}

function renderFeatureListCardStack(items) {
  const cards = items
    .map(item => {
      const media = renderFeatureMedia(item.media);
      const content = renderFeatureListItemContent(item);
      const body = `<div class="uk-card-body">${content}</div>`;
      return `<div class="uk-width-1-1 uk-width-1-2@m"><div class="uk-card uk-card-default uk-height-1-1">${media}${body}</div></div>`;
    })
    .join('');

  return `<div class="uk-grid uk-grid-medium" data-uk-grid>${cards}</div>`;
}

function renderFeatureList(block, variant, options = {}) {
  if (variant !== 'text-columns' && variant !== 'card-stack') {
    throw new Error(`Unsupported variant for feature_list: ${variant}`);
  }

  const context = options?.context || 'frontend';

  const items = normalizeFeatureListItems(block);

  const header = renderFeatureListHeader(block, context);
  const grid = variant === 'card-stack' ? renderFeatureListCardStack(items) : renderFeatureListTextColumns(items);

  return renderSection({ block, variant, content: `${header}${grid}` });
}

function renderFeatureListDetailedCards(block, options = {}) {
  const context = options?.context || 'frontend';
  const items = normalizeFeatureListItems(block);
  const eyebrow = renderEyebrow(block, '', context);
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const lead = block.data?.lead
    ? `<p class="uk-text-lead uk-margin-small-top uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.lead', context)}>${escapeHtml(block.data.lead)}</p>`
    : '';
  const header = (eyebrow || title || lead) ? `<div class="uk-width-1-1 uk-margin-medium-bottom">${eyebrow}${title}${lead}</div>` : '';

  const cards = items
    .map(item => {
      const content = renderFeatureListItemContent(item);
      return `<div class="uk-width-1-1 uk-width-1-2@m uk-width-1-3@l"><div class="uk-card uk-card-default uk-height-1-1"><div class="uk-card-body">${content}</div></div></div>`;
    })
    .join('');

  const grid = `<div class="uk-grid uk-grid-medium" data-uk-grid>${cards}</div>`;
  const ctas = renderHeroCtas(block.data?.cta);
  const footer = ctas || '';

  return renderSection({ block, variant: 'detailed-cards', content: `${header}${grid}${footer}` });
}

function renderFeatureListGridBullets(block, options = {}) {
  const context = options?.context || 'frontend';
  const items = normalizeFeatureListItems(block);
  const intro = block.data?.intro
    ? `<p class="uk-text-meta uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.intro', context)}>${escapeHtml(block.data.intro)}</p>`
    : '';
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-small-top uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const subtitle = block.data?.subtitle
    ? `<p class="uk-text-lead uk-margin-small-top"${buildEditableAttributes(block, 'data.subtitle', context)}>${escapeHtml(block.data.subtitle)}</p>`
    : '';
  const header = (intro || title || subtitle) ? `<div class="uk-width-1-1 uk-margin-medium-bottom">${intro}${title}${subtitle}</div>` : '';

  const cards = items
    .map(item => {
      const content = renderFeatureListItemContent(item);
      return `<div class="uk-width-1-1 uk-width-1-2@m uk-width-1-3@l"><div class="uk-card uk-card-default uk-card-small uk-height-1-1"><div class="uk-card-body">${content}</div></div></div>`;
    })
    .join('');

  const grid = `<div class="uk-grid uk-grid-small" data-uk-grid>${cards}</div>`;

  return renderSection({ block, variant: 'grid-bullets', content: `${header}${grid}` });
}

function renderFeatureListSlider(block, options = {}) {
  const context = options?.context || 'frontend';
  const items = normalizeFeatureListItems(block);

  if (!items.length) {
    return renderSection({
      block,
      variant: 'slider',
      content: '<div class="uk-alert-warning" role="alert">Keine Funktionskarten hinterlegt.</div>'
    });
  }

  const sliderId = `feature-slider-${block.id ? escapeAttribute(block.id) : 'section'}`;
  const navId = `${sliderId}-nav`;

  const title = block.data?.title
    ? `<h2 class="uk-heading-line"><span${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</span></h2>`
    : '';
  const subtitle = block.data?.subtitle
    ? `<span class="muted"${buildEditableAttributes(block, 'data.subtitle', context)}>${escapeHtml(block.data.subtitle)}</span>`
    : '';
  const header = title || subtitle
    ? `<div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">${title}${subtitle}</div>`
    : '';

  const introHeading = block.data?.intro
    ? `<h3 class="uk-heading-bullet"${buildEditableAttributes(block, 'data.intro', context)}>${escapeHtml(block.data.intro)}</h3>`
    : '';
  const lead = block.data?.lead
    ? `<p class="muted"${buildEditableAttributes(block, 'data.lead', context)}>${escapeHtml(block.data.lead)}</p>`
    : '';
  const intro = introHeading || lead ? `<div class="uk-text-center uk-margin-medium-bottom">${introHeading}${lead}</div>` : '';

  const nav = `<nav class="feature-nav" aria-label="Funktionsnavigation"><ul id="${escapeAttribute(navId)}" class="feature-nav__list">${items
    .map(item => `<li><a class="feature-nav__pill" href="#" data-target="${escapeAttribute(item.id)}">${escapeHtml(item.title || '')}</a></li>`)
    .join('')}</ul></nav>`;

  const slides = items
    .map(item => {
      const content = renderFeatureListItemContent(item);
      return `<li class="feature-slider__item" id="${escapeAttribute(item.id)}"><article class="feature-card">${content}</article></li>`;
    })
    .join('');

  const slider = `
    <div id="${escapeAttribute(sliderId)}" class="uk-position-relative uk-visible-toggle feature-slider" tabindex="-1" data-uk-slider="center: true; autoplay: true; autoplay-interval: 4200; finite: false">
      <div class="uk-slider-container">
        <ul class="uk-slider-items uk-child-width-1-1@s uk-child-width-1-2@m uk-child-width-1-3@l feature-slider__list" data-uk-scrollspy="cls: uk-animation-slide-bottom-small; target: .feature-slider__item; delay: 75; repeat: true">${slides}</ul>
      </div>
      <a class="uk-position-center-left uk-position-small uk-hidden-hover" href="#" data-uk-slidenav-previous data-uk-slider-item="previous" aria-label="Vorherige Funktion"></a>
      <a class="uk-position-center-right uk-position-small uk-hidden-hover" href="#" data-uk-slidenav-next data-uk-slider-item="next" aria-label="Nächste Funktion"></a>
    </div>`;

  const script = `
    <script>(function(){
      const sliderId = ${JSON.stringify(sliderId)};
      const navId = ${JSON.stringify(navId)};
      const sliderElement = document.getElementById(sliderId);
      const navElement = document.getElementById(navId);
      if (!sliderElement || !navElement || typeof UIkit === 'undefined') {
        return;
      }
      const slider = UIkit.slider(sliderElement);
      const pills = Array.from(navElement.querySelectorAll('li'));
      const slides = Array.from(sliderElement.querySelectorAll('.feature-slider__item'));
      const itemsContainer = sliderElement.querySelector('.uk-slider-items');
      if (!itemsContainer) {
        return;
      }

      const indexById = new Map(slides.map((slide, index) => [slide.id, index]));

      const applyFocusToCenter = () => {
        const slideElements = Array.from(itemsContainer.children);
        if (!slideElements.length) {
          return;
        }
        const viewportElement = sliderElement.querySelector('.uk-slider-container') || sliderElement;
        const viewportRect = viewportElement.getBoundingClientRect();
        const midpoint = viewportRect.left + viewportRect.width / 2;
        let nearest = null;
        let minDistance = Number.POSITIVE_INFINITY;

        slideElements.forEach(item => {
          const card = item.querySelector('.feature-card');
          if (!card) {
            return;
          }
          const rect = item.getBoundingClientRect();
          const centerX = rect.left + rect.width / 2;
          const distance = Math.abs(centerX - midpoint);
          if (distance < minDistance) {
            minDistance = distance;
            nearest = item;
          }
        });

        slideElements.forEach(item => {
          item.classList.remove('is-center');
          const card = item.querySelector('.feature-card');
          if (card) {
            card.classList.remove('feature-card--focus');
          }
        });

        if (nearest) {
          nearest.classList.add('is-center');
          const focusCard = nearest.querySelector('.feature-card');
          if (focusCard) {
            focusCard.classList.add('feature-card--focus');
          }
        }
      };

      const setActive = index => {
        pills.forEach((li, i) => {
          const isActive = i === index;
          li.classList.toggle('uk-active', isActive);
          const link = li.querySelector('.feature-nav__pill');
          if (!link) {
            return;
          }
          link.classList.toggle('is-active', isActive);
          if (isActive) {
            link.setAttribute('aria-current', 'true');
          } else {
            link.removeAttribute('aria-current');
          }
        });
      };

      const setCurrent = index => {
        slides.forEach((slide, i) => slide.classList.toggle('uk-current', i === index));
      };

      pills.forEach(li => {
        const link = li.querySelector('a[data-target]');
        if (!link) {
          return;
        }
        const targetId = link.dataset.target;
        const targetIndex = indexById.get(targetId);
        if (typeof targetIndex === 'undefined') {
          return;
        }
        link.addEventListener('click', event => {
          event.preventDefault();
          slider.show(targetIndex);
          setActive(targetIndex);
          setCurrent(targetIndex);
          applyFocusToCenter();
        });
      });

      UIkit.util.on(sliderElement, 'itemshown', () => {
        setActive(slider.index);
        setCurrent(slider.index);
        applyFocusToCenter();
      });

      const initialIndex = typeof slider.index === 'number' ? slider.index : 0;
      setActive(initialIndex);
      setCurrent(initialIndex);
      applyFocusToCenter();
      UIkit.util.on(window, 'resize', applyFocusToCenter);
    })();</script>
  `;

  return renderSection({ block, variant: 'slider', content: `${header}${intro}${nav}${slider}${script}` });
}

function normalizeProcessStepsVariant(variant) {
  const mapping = {
    'timeline': 'timeline',
    'timeline-vertical': 'timeline_vertical',
    'timeline_vertical': 'timeline_vertical',
    'timeline-horizontal': 'timeline_horizontal',
    'timeline_horizontal': 'timeline_horizontal'
  };

  return mapping[variant] || variant;
}

function renderProcessSteps(block, variant, options = {}) {
  const normalizedVariant = normalizeProcessStepsVariant(variant);
  const allowedVariants = new Set(['numbered-vertical', 'numbered-horizontal', 'timeline', 'timeline_vertical', 'timeline_horizontal']);

  if (!allowedVariants.has(normalizedVariant)) {
    throw new Error(`Unsupported process_steps variant: ${variant}`);
  }

  const context = options?.context || 'frontend';

  const steps = Array.isArray(block.data?.steps) ? block.data.steps : [];

  if (!steps.length) {
    throw new Error('process_steps block requires at least one step');
  }

  if (normalizedVariant === 'timeline') {
    return renderProcessStepsTimeline(block, options);
  }

  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const summary = block.data?.summary
    ? `<p class="uk-text-lead uk-margin-small-top"${buildEditableAttributes(block, 'data.summary', context)}>${escapeHtml(block.data.summary)}</p>`
    : '';
  const header = title || summary ? `<div class="uk-width-1-1 uk-margin-medium-bottom">${title}${summary}</div>` : '';

  const renderNumberBadge = (stepNumber) => `<div class="uk-flex uk-flex-middle uk-flex-center uk-background-primary uk-light uk-border-circle uk-text-bold" style="width:48px;height:48px;">${stepNumber}</div>`;

  const renderVerticalSteps = () => {
    const items = steps.map((step, index) => {
      const number = renderNumberBadge(index + 1);
      const stepTitle = step?.title ? `<h3 class="uk-h4 uk-margin-remove-bottom">${escapeHtml(step.title)}</h3>` : '';
      const stepDescription = step?.description ? `<p class="uk-margin-small-top uk-margin-remove-bottom">${escapeHtml(step.description)}</p>` : '';
      const numberColumn = `<div class="uk-width-auto uk-flex uk-flex-top">${number}</div>`;
      const contentColumn = `<div class="uk-width-expand">${stepTitle}${stepDescription}</div>`;
      return `<li class="uk-padding-small uk-margin-remove-bottom"><div class="uk-grid-small uk-flex-top" data-uk-grid>${numberColumn}${contentColumn}</div></li>`;
    });

    return `<ol class="uk-list uk-list-large uk-margin-remove">${items.join('')}</ol>`;
  };

  const renderHorizontalSteps = () => {
    const items = steps.map((step, index) => {
      const number = renderNumberBadge(index + 1);
      const stepTitle = step?.title ? `<h3 class="uk-h4 uk-margin-small-top uk-margin-remove-bottom">${escapeHtml(step.title)}</h3>` : '';
      const stepDescription = step?.description ? `<p class="uk-margin-small-top uk-margin-remove-bottom">${escapeHtml(step.description)}</p>` : '';
      return `<div class="uk-text-center uk-height-1-1"><div class="uk-flex uk-flex-center">${number}</div>${stepTitle}${stepDescription}</div>`;
    });

    return `<div class="uk-grid-large uk-child-width-1-1 uk-child-width-1-3@m" data-uk-grid>${items.join('')}</div>`;
  };

  const layout = normalizedVariant === 'numbered-horizontal' || normalizedVariant === 'timeline_horizontal'
    ? renderHorizontalSteps()
    : renderVerticalSteps();

  return renderSection({ block, variant: normalizedVariant, content: `${header}${layout}` });
}

function renderProcessStepsTimeline(block, options = {}) {
  const context = options?.context || 'frontend';
  const steps = Array.isArray(block.data?.steps) ? block.data.steps : [];

  if (!steps.length) {
    throw new Error('process_steps block requires at least one step');
  }

  const title = block.data?.title
    ? `<h2 class="uk-heading-line uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}><span>${escapeHtml(block.data.title)}</span></h2>`
    : '';
  const intro = block.data?.intro
    ? `<p class="trust-story__lead"${buildEditableAttributes(block, 'data.intro', context)}>${escapeHtml(block.data.intro)}</p>`
    : '';

  const header = title || intro
    ? `<div class="uk-grid-large uk-flex-middle" data-uk-grid>${title ? `<div class="uk-width-1-1 uk-width-2-3@m">${title}</div>` : ''}${intro ? `<div class="uk-width-1-1 uk-width-1-3@m">${intro}</div>` : ''}</div>`
    : '';

  const stepsList = steps
    .map((step, index) => {
      const stepId = `${block.id || 'step'}-${step.id || index}`;
      const labelId = `${stepId}-title`;
      const descriptionId = `${stepId}-description`;
      const badgeIcon = '<span class="trust-story__badge-icon" aria-hidden="true" data-uk-icon="icon: check"></span>';

      const marker = `
        <div class="trust-story__marker" aria-hidden="true">
          <span class="trust-story__connector trust-story__connector--before"></span>
          <span class="trust-story__badge" data-step-index="${index + 1}">
            ${badgeIcon}
            <span class="trust-story__sr">Schritt ${index + 1}</span>
          </span>
          <span class="trust-story__connector trust-story__connector--after"></span>
        </div>`;

      const content = `
        <div class="trust-story__content">
          <h3 id="${escapeAttribute(labelId)}" class="trust-story__title">${escapeHtml(step.title || '')}</h3>
          <p id="${escapeAttribute(descriptionId)}" class="trust-story__text">${escapeHtml(step.description || '')}</p>
        </div>`;

      return `
        <li class="trust-story__step" role="listitem" tabindex="0" aria-labelledby="${escapeAttribute(labelId)}" aria-describedby="${escapeAttribute(descriptionId)}">
          ${marker}
          ${content}
        </li>`;
    })
    .join('');

  const closingTitle = block.data?.closing?.title
    ? `<h3 class="trust-story__closing-title"${buildEditableAttributes(block, 'data.closing.title', context)}>${escapeHtml(block.data.closing.title)}</h3>`
    : '';
  const closingBody = block.data?.closing?.body
    ? `<p class="trust-story__closing-text"${buildEditableAttributes(block, 'data.closing.body', context)}>${escapeHtml(block.data.closing.body)}</p>`
    : '';

  const ctas = renderCtaButtons(block.data?.ctaPrimary, block.data?.ctaSecondary, {
    alignment: 'uk-flex-left@m',
    margin: 'uk-margin-medium-top'
  });

  const closing = closingTitle || closingBody || ctas
    ? `<div class="trust-story__closing">${closingTitle}${closingBody}${ctas ? `<div class="trust-story__cta-group">${ctas}</div>` : ''}</div>`
    : '';

  const list = `<ul class="trust-story trust-story--timeline" role="list" aria-label="${escapeAttribute(block.data?.title || 'Prozess')}">${stepsList}</ul>`;

  return renderSection({
    block,
    variant: 'timeline',
    sectionClass: 'uk-section-muted',
    content: `${header}${list}${closing}`
  });
}

function renderContactForm(block, variant = 'default', options = {}) {
  const context = options?.context || 'frontend';
  const normalizedVariant = variant === 'compact' ? 'compact' : 'default';
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const intro = block.data?.intro
    ? `<p class="uk-text-lead uk-margin-small-top"${buildEditableAttributes(block, 'data.intro', context)}>${escapeHtml(block.data.intro)}</p>`
    : '';

  const copyColumn = title || intro
    ? `<div class="uk-width-1-1 ${normalizedVariant === 'compact' ? 'uk-text-center' : 'uk-width-1-2@m'}">${title}${intro}</div>`
    : '';

  const isPreview = context === 'preview';
  const disabledAttr = isPreview ? ' disabled' : '';
  const submitType = isPreview ? 'button' : 'submit';
  const submitLabel = block.data?.submitLabel || 'Nachricht senden';
  const submitEditable = buildEditableAttributes(block, 'data.submitLabel', context);
  const privacyText = block.data?.privacyHint || '';
  const privacyLabel = privacyText
    ? `<div class="uk-margin"><label class="uk-text-small"><input class="uk-checkbox" name="privacy" type="checkbox" required${disabledAttr}> <span${buildEditableAttributes(block, 'data.privacyHint', context)}>${escapeHtml(privacyText)}</span></label></div>`
    : '';

  const formId = `contact-form-${block.id ? escapeAttribute(block.id) : 'section'}`;
  const endpoint = escapeAttribute(resolveContactEndpoint());
  const csrfToken = resolveCsrfToken();
  const csrfField = csrfToken ? `<input type="hidden" name="csrf_token" value="${escapeAttribute(csrfToken)}">` : '';
  const form = `
    <form
      id="${formId}"
      class="uk-form-stacked contact-form"
      method="post"
      action="${endpoint}"
      data-contact-endpoint="${endpoint}"
      ${isPreview ? 'data-preview-submit="true" novalidate' : ''}
    >
      <div class="uk-margin">
        <label class="uk-form-label" for="${formId}-name">Ihr Name</label>
        <input class="uk-input" id="${formId}-name" name="name" type="text" required${disabledAttr}>
      </div>
      <div class="uk-margin">
        <label class="uk-form-label" for="${formId}-email">E-Mail</label>
        <input class="uk-input" id="${formId}-email" name="email" type="email" required${disabledAttr}>
      </div>
      <div class="uk-margin">
        <label class="uk-form-label" for="${formId}-message">Nachricht</label>
        <textarea class="uk-textarea" id="${formId}-message" name="message" rows="5" required${disabledAttr}></textarea>
      </div>
      ${privacyLabel}
      <input type="hidden" name="recipient" value="${escapeAttribute(block.data?.recipient || '')}">
      ${csrfField}
      <input type="text" name="company" autocomplete="off" tabindex="-1" class="uk-hidden" aria-hidden="true">
      <div class="uk-margin">
        <button class="uk-button uk-button-primary uk-width-1-1" type="${submitType}"${submitEditable}${isPreview ? ' aria-disabled="true"' : ''}>${escapeHtml(submitLabel)}</button>
      </div>
    </form>
  `;

  const formColumnWidth = normalizedVariant === 'compact' ? 'uk-width-1-1 uk-width-2-3@m' : 'uk-width-1-1 uk-width-1-2@m';
  const formColumn = `<div class="${formColumnWidth}"><div class="uk-card uk-card-default uk-card-body">${form}</div></div>`;

  const gridContent = normalizedVariant === 'compact'
    ? `<div class="uk-grid uk-grid-medium uk-flex-center" data-uk-grid>${copyColumn}${formColumn}</div>`
    : `<div class="uk-grid uk-grid-large uk-flex-top" data-uk-grid>${copyColumn}${formColumn}</div>`;

  return renderSection({ block, variant: normalizedVariant, content: gridContent });
}

function renderTestimonialSingle(block) {
  const author = block.data?.author?.name ? ` by ${escapeHtml(block.data.author.name)}` : '';
  return renderSection({
    block,
    variant: 'single_quote',
    content: `<!-- testimonial:single_quote${author} -->`,
    container: false
  });
}

function renderTestimonialWall(block) {
  return renderSection({
    block,
    variant: 'quote_wall',
    content: '<!-- testimonial:quote_wall | grouped quotes -->',
    container: false
  });
}

function renderRichTextProse(block, options = {}) {
  const context = options?.context || 'frontend';
  const editable = buildEditableAttributes(block, 'data.body', context, { type: 'richtext' });
  return renderSection({
    block,
    variant: 'prose',
    content: `<!-- rich_text:prose -->${block.data.body || ''}`,
    container: false
  });
}

function renderInfoMedia(block, variant, options = {}) {
  const allowedVariants = new Set(['stacked', 'image-left', 'image-right', 'switcher']);
  const hasSupportedVariant = allowedVariants.has(variant);
  const context = options?.context || 'frontend';
  const displayVariant = hasSupportedVariant ? variant : 'unsupported';

  if (!hasSupportedVariant) {
    const providedVariant = variant ? `"${escapeHtml(variant)}"` : 'nicht gesetzt';
    const allowed = Array.from(allowedVariants).join(', ');
    const warning = `<div class="uk-alert-warning" role="alert">Unsupported info_media variant ${providedVariant}. Erlaubte Varianten: ${escapeHtml(allowed)}.</div>`;
    return renderSection({ block, variant: displayVariant, content: warning });
  }

  if (variant === 'switcher') {
    return renderInfoMediaSwitcher(block, options);
  }

  const bodyContent = typeof block.data?.body === 'string' && block.data.body.trim()
    ? block.data.body
    : '<p class="uk-text-muted uk-margin-remove">Noch kein Text hinterlegt.</p>';

  const warnings = [];
  if (!block.data || typeof block.data.body !== 'string') {
    warnings.push('<div class="uk-alert-warning uk-margin-small-bottom" role="alert">Blockinhalt fehlt oder ist ungültig.</div>');
  }

  const media = block.data?.media;
  const hasMedia = !!media?.image;
  const mediaContent = hasMedia
    ? `<div class="uk-border-rounded uk-overflow-hidden uk-box-shadow-small"><img class="uk-width-1-1" src="${escapeAttribute(media.image)}" alt="${media?.alt ? escapeAttribute(media.alt) : ''}" loading="lazy"></div>`
    : `<div class="uk-placeholder uk-text-center uk-border-rounded uk-box-shadow-small"><span class="uk-text-muted">Kein Bild ausgewählt</span></div>`;

  const textColumn = `<div class="${variant === 'stacked' ? 'uk-width-1-1' : 'uk-width-1-1 uk-width-1-2@m'}">${warnings.join('')}<div class="uk-article"${buildEditableAttributes(block, 'data.body', context, { type: 'richtext' })}>${bodyContent}</div></div>`;

  const mediaColumnRequired = variant !== 'stacked';
  const mediaColumn = mediaColumnRequired || hasMedia ? `<div class="${variant === 'stacked' ? 'uk-width-1-1' : 'uk-width-1-1 uk-width-1-2@m'}">${mediaContent}</div>` : '';

  const gridClass = variant === 'stacked' ? 'uk-grid uk-grid-small' : 'uk-grid uk-grid-large uk-flex-middle';
  const columnsByVariant = {
    stacked: `${textColumn}${mediaColumn}`,
    'image-left': `${mediaColumn}${textColumn}`,
    'image-right': `${textColumn}${mediaColumn}`
  };

  const grid = `<div class="${gridClass}" data-uk-grid>${columnsByVariant[variant]}</div>`;

  return renderSection({ block, variant: displayVariant, content: grid });
}

function renderInfoMediaSwitcher(block, options = {}) {
  const context = options?.context || 'frontend';
  const items = Array.isArray(block.data?.items)
    ? block.data.items.filter(item => item && typeof item.title === 'string' && typeof item.description === 'string')
    : [];

  if (!items.length) {
    return renderSection({
      block,
      variant: 'switcher',
      sectionClass: 'uk-section-muted',
      content: '<div class="uk-alert-warning" role="alert">Keine Module hinterlegt.</div>'
    });
  }

  const switcherId = `info-media-switcher-${block.id ? escapeAttribute(block.id) : 'section'}`;

  const title = block.data?.title
    ? `<h2 class="uk-heading-line"><span${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</span></h2>`
    : '';
  const subtitle = block.data?.subtitle
    ? `<span class="muted"${buildEditableAttributes(block, 'data.subtitle', context)}>${escapeHtml(block.data.subtitle)}</span>`
    : '';
  const header = title || subtitle
    ? `<div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">${title}${subtitle}</div>`
    : '';

  const nav = `<ul class="uk-tab calserver-modules-nav" data-uk-switcher="connect: #${escapeAttribute(switcherId)}; animation: uk-animation-fade">${items
    .map(item => {
      const description = item.description
        ? `<span class="calserver-modules-nav__desc">${escapeHtml(item.description)}</span>`
        : '';
      return `<li><a class="calserver-modules-nav__link" href="#${escapeAttribute(item.id)}"><span class="calserver-modules-nav__title">${escapeHtml(item.title)}</span>${description}</a></li>`;
    })
    .join('')}</ul>`;

  const panels = items
    .map(item => {
      const visual = item.media?.image
        ? `<div class="calserver-module-figure__visual" data-module="${escapeAttribute(item.id.replace('module-', ''))}"><img src="${escapeAttribute(item.media.image)}" alt="${item.media?.alt ? escapeAttribute(item.media.alt) : ''}" loading="lazy"></div>`
        : `<div class="calserver-module-figure__visual" data-module="${escapeAttribute(item.id.replace('module-', ''))}"><div class="uk-placeholder uk-text-center uk-border-rounded"><span class="uk-text-muted">Kein Bild ausgewählt</span></div></div>`;

      const bullets = renderFeatureBullets(item.bullets);

      return `<li><figure id="${escapeAttribute(item.id)}" class="calserver-module-figure"><div class="calserver-module-figure__visual-wrapper">${visual}</div><figcaption><h3 class="uk-h3">${escapeHtml(item.title)}</h3>${item.description ? `<p class="muted">${escapeHtml(item.description)}</p>` : ''}${bullets}</figcaption></figure></li>`;
    })
    .join('');

  const grid = `
    <div class="calserver-modules-grid" data-uk-scrollspy="cls: uk-animation-slide-bottom-small; target: > *; delay: 100; repeat: true">
      <div>${nav}</div>
      <div><ul id="${escapeAttribute(switcherId)}" class="uk-switcher calserver-modules-switcher">${panels}</ul></div>
    </div>`;

  return renderSection({ block, variant: 'switcher', sectionClass: 'uk-section-muted', content: `${header}${grid}` });
}

function renderCtaButton(cta, styleClass = 'uk-button-primary', additionalClasses = '') {
  if (!cta?.label || !cta?.href) {
    return '';
  }

  const ariaLabel = cta.ariaLabel ? ` aria-label="${escapeAttribute(cta.ariaLabel)}"` : '';
  const classes = ['uk-button', styleClass, additionalClasses].filter(Boolean).join(' ');

  return `<a class="${classes}" href="${escapeAttribute(cta.href)}"${ariaLabel}>${escapeHtml(cta.label)}</a>`;
}

function renderCtaButtons(primary, secondary, { alignment = '', margin = 'uk-margin-medium-top', sizeClass = '' } = {}) {
  const primaryButton = renderCtaButton(primary, ['uk-button-primary', sizeClass].filter(Boolean).join(' '));
  const secondaryButton = renderCtaButton(
    secondary,
    ['uk-button-default', sizeClass].filter(Boolean).join(' '),
    primaryButton ? 'uk-margin-small-top uk-margin-small-left@m' : ''
  );

  const buttons = [primaryButton, secondaryButton].filter(Boolean);
  if (!buttons.length) {
    return '';
  }

  const classes = [
    'uk-flex',
    'uk-flex-column',
    'uk-flex-row@m',
    'uk-flex-middle',
    'uk-flex-wrap',
    alignment,
    margin
  ]
    .filter(Boolean)
    .join(' ');

  return `<div class="${classes}">${buttons.join('')}</div>`;
}

function renderCta(block, options = {}) {
  const context = options?.context || 'frontend';
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const body = block.data?.body
    ? `<p class="uk-text-lead uk-margin-small-top uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.body', context)}>${escapeHtml(block.data.body)}</p>`
    : '';
  const primary = block.data?.primary;
  const secondary = block.data?.secondary;
  const buttons = renderCtaButtons(primary, secondary, {
    alignment: 'uk-flex-center',
    margin: 'uk-margin-medium-top',
    sizeClass: 'uk-button-large'
  });
  if (!buttons) {
    throw new Error('CTA block requires at least one valid action');
  }
  const inner = `<div class="uk-text-center uk-width-1-1 uk-width-2-3@m uk-margin-auto">${title}${body}${buttons}</div>`;

  return renderSection({ block, variant: 'full_width', content: inner });
}

function renderCtaSplit(block, options = {}) {
  const context = options?.context || 'frontend';
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const body = block.data?.body
    ? `<p class="uk-text-lead uk-margin-small-top uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.body', context)}>${escapeHtml(block.data.body)}</p>`
    : '';
  const textColumn = `<div class="uk-width-expand">${title}${body}</div>`;
  const primary = block.data?.primary;
  const secondary = block.data?.secondary;
  const buttons = renderCtaButtons(primary, secondary, {
    alignment: 'uk-flex-right@m',
    margin: 'uk-margin-small-top'
  });
  if (!buttons) {
    throw new Error('CTA split variant requires at least one valid action');
  }
  const ctaColumn = `<div class="uk-width-1-1 uk-width-auto@m">${buttons}</div>`;
  const layout = `<div class="uk-grid uk-grid-large uk-flex-middle" data-uk-grid>${textColumn}${ctaColumn}</div>`;

  return renderSection({ block, variant: 'split', content: layout });
}

function renderStatStripHeader(block, context, alignment = 'center') {
  const alignmentClass = alignment === 'left' ? 'uk-text-left' : 'uk-text-center';
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom ${alignmentClass}"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const lede = block.data?.lede
    ? `<p class="uk-text-lead uk-margin-small-top uk-margin-remove-bottom ${alignmentClass}"${buildEditableAttributes(block, 'data.lede', context)}>${escapeHtml(block.data.lede)}</p>`
    : '';
  if (!title && !lede) {
    return '';
  }
  const spacing = alignment === 'left' ? 'uk-margin-medium-bottom' : 'uk-margin-medium-bottom';
  return `<div class="uk-width-1-1 ${alignmentClass} ${spacing}">${title}${lede}</div>`;
}

function renderStatStripMarquee(block) {
  const marqueeItems = Array.isArray(block.data?.marquee)
    ? block.data.marquee.filter(item => typeof item === 'string' && item.trim() !== '')
    : [];

  if (!marqueeItems.length) {
    return '';
  }

  const duplicatedItems = marqueeItems.concat(marqueeItems);

  const track = duplicatedItems
    .map((text, index) => {
      const ariaHidden = index >= marqueeItems.length ? ' aria-hidden="true"' : '';
      return `<div class="calserver-logo-marquee__item" role="listitem"${ariaHidden}>${escapeHtml(text)}</div>`;
    })
    .join('');

  return `
    <div class="stat-strip__marquee uk-margin-large-top">
      <div class="calserver-logo-marquee" aria-label="${escapeAttribute(block.data?.title || 'Leistungsversprechen')}">
        <div class="calserver-logo-marquee__track" role="list">${track}</div>
      </div>
    </div>`;
}

function renderStatMetricValue(block, metric, index, context, options = {}) {
  const tooltip = metric.tooltip ? ` title="${escapeAttribute(metric.tooltip)}" data-uk-tooltip` : '';
  const icon = metric.icon ? `<span class="uk-margin-small-right" aria-hidden="true">${escapeHtml(metric.icon)}</span>` : '';
  const sizeClass = options.valueSize || 'uk-heading-large';
  const alignment = options.alignClass ? ` ${options.alignClass}` : '';
  return `<div class="${sizeClass} uk-margin-remove${alignment}"${tooltip}${buildEditableAttributes(
    block,
    `data.metrics.${index}.value`,
    context
  )}>${icon}${escapeHtml(metric.value)}</div>`;
}

function renderStatMetricLabel(block, metric, index, context, options = {}) {
  const alignment = options.alignClass ? ` ${options.alignClass}` : '';
  const labelClass = options.labelClass || 'uk-text-muted';
  const extraClass = options.extraClass ? ` ${options.extraClass}` : '';
  return `<p class="${labelClass} uk-margin-small-top uk-margin-remove-bottom${alignment}${extraClass}"${buildEditableAttributes(
    block,
    `data.metrics.${index}.label`,
    context
  )}>${escapeHtml(metric.label)}</p>`;
}

function renderStatMetricBenefit(block, metric, index, context, options = {}) {
  if (!metric.benefit) {
    return '';
  }
  const alignment = options.alignClass ? ` ${options.alignClass}` : '';
  const extraClass = options.extraClass ? ` ${options.extraClass}` : '';
  const textClass = options.textClass || 'uk-text-success';
  return `<p class="${textClass} uk-margin-small-top uk-margin-remove-bottom${alignment}${extraClass}"${buildEditableAttributes(
    block,
    `data.metrics.${index}.benefit`,
    context
  )}>${escapeHtml(metric.benefit)}</p>`;
}

function renderStatMetricAsOf(block, metric, index, context, options = {}) {
  if (!metric.asOf) {
    return '';
  }
  const alignment = options.alignClass ? ` ${options.alignClass}` : '';
  const extraClass = options.extraClass ? ` ${options.extraClass}` : '';
  return `<p class="uk-text-meta uk-margin-small-top uk-margin-remove-bottom${alignment}${extraClass}"${buildEditableAttributes(
    block,
    `data.metrics.${index}.asOf`,
    context
  )}>${escapeHtml(metric.asOf)}</p>`;
}

function getValidMetrics(block) {
  return (Array.isArray(block.data?.metrics) ? block.data.metrics : []).filter(
    metric => metric && typeof metric.value === 'string' && typeof metric.label === 'string'
  );
}

function renderStatStripInline(block, options = {}) {
  const context = options?.context || 'frontend';
  const header = renderStatStripHeader(block, context, 'left');
  const metrics = getValidMetrics(block);

  const items = metrics
    .map((metric, index) => {
      const value = renderStatMetricValue(block, metric, index, context, {
        valueSize: 'stat-strip__inline-value uk-heading-medium',
        alignClass: 'uk-text-left'
      });
      const label = renderStatMetricLabel(block, metric, index, context, {
        labelClass: 'stat-strip__inline-label uk-text-emphasis',
        alignClass: 'uk-text-left',
        extraClass: 'stat-strip__label'
      });
      const benefit = renderStatMetricBenefit(block, metric, index, context, {
        alignClass: 'uk-text-left',
        extraClass: 'stat-strip__inline-meta-item'
      });
      const asOf = renderStatMetricAsOf(block, metric, index, context, {
        alignClass: 'uk-text-left',
        extraClass: 'stat-strip__inline-meta-item stat-strip__inline-meta-item--muted'
      });
      const metaContent = benefit || asOf ? `${benefit}${asOf}` : '<span class="stat-strip__meta-placeholder" aria-hidden="true"></span>';
      const meta = `<div class="stat-strip__inline-meta">${metaContent}</div>`;
      return `<div role="listitem" class="stat-strip__inline-item">` +
        `<div class="stat-strip__inline-bar uk-flex uk-flex-column">` +
        `<div class="stat-strip__inline-main">${value}${label}</div>${meta}</div></div>`;
    })
    .join('');

  const metricsInline = items
    ? `<div class="stat-strip__grid stat-strip__grid--inline uk-grid uk-grid-small uk-child-width-1-1 uk-child-width-1-3@m" data-uk-grid role="list">${items}</div>`
    : '<div class="uk-alert-warning" role="alert">Keine Kennzahlen hinterlegt.</div>';

  const marquee = renderStatStripMarquee(block);
  return renderSection({ block, variant: 'inline', content: `${header}${metricsInline}${marquee}` });
}

function renderStatStripCards(block, options = {}) {
  const context = options?.context || 'frontend';
  const header = renderStatStripHeader(block, context, 'center');
  const metrics = getValidMetrics(block);

  const metricCards = metrics
    .map((metric, index) => {
      const value = renderStatMetricValue(block, metric, index, context, {
        valueSize: 'stat-strip__value uk-heading-large',
        alignClass: 'uk-text-left'
      });
      const label = renderStatMetricLabel(block, metric, index, context, {
        labelClass: 'stat-strip__label-text uk-text-semibold',
        alignClass: 'uk-text-left'
      });
      const asOf = renderStatMetricAsOf(block, metric, index, context, {
        alignClass: 'uk-text-left',
        extraClass: 'stat-strip__as-of'
      });
      const benefit = renderStatMetricBenefit(block, metric, index, context, {
        alignClass: 'uk-text-left',
        extraClass: 'stat-strip__benefit',
        textClass: 'uk-text-secondary'
      });
      const benefitBlock = benefit || '<span class="stat-strip__benefit stat-strip__benefit-placeholder" aria-hidden="true"></span>';

      return `<div role="listitem"><div class="stat-strip__card stat-strip__card--surface uk-card uk-card-body uk-height-1-1 uk-flex uk-flex-column">` +
        `<div class="stat-strip__kpi uk-flex uk-flex-column">${value}${label}${asOf || ''}</div>` +
        `${benefitBlock}</div></div>`;
    })
    .join('');

  const metricsGrid = metricCards
    ? `<div class="stat-strip__grid uk-grid uk-child-width-1-1 uk-child-width-1-3@m uk-grid-large uk-grid-match" data-uk-grid role="list">${metricCards}</div>`
    : '<div class="uk-alert-warning" role="alert">Keine Kennzahlen hinterlegt.</div>';

  const marquee = renderStatStripMarquee(block);
  return renderSection({ block, variant: 'cards', content: `${header}${metricsGrid}${marquee}` });
}

function renderStatStripCentered(block, options = {}) {
  const context = options?.context || 'frontend';
  const header = renderStatStripHeader(block, context, 'center');
  const metrics = getValidMetrics(block);

  const items = metrics
    .map((metric, index) => {
      const value = renderStatMetricValue(block, metric, index, context, {
        valueSize: 'stat-strip__centered-value uk-heading-large',
        alignClass: 'uk-text-center'
      });
      const label = renderStatMetricLabel(block, metric, index, context, {
        labelClass: 'stat-strip__centered-label uk-text-lead',
        alignClass: 'uk-text-center',
        extraClass: 'stat-strip__label'
      });
      const benefit = renderStatMetricBenefit(block, metric, index, context, {
        alignClass: 'uk-text-center',
        extraClass: 'stat-strip__centered-meta-item'
      });
      const asOf = renderStatMetricAsOf(block, metric, index, context, {
        alignClass: 'uk-text-center',
        extraClass: 'stat-strip__centered-meta-item stat-strip__centered-meta-item--muted'
      });
      const metaContent = benefit || asOf ? `${benefit}${asOf}` : '<span class="stat-strip__meta-placeholder" aria-hidden="true"></span>';
      const meta = `<div class="stat-strip__centered-meta">${metaContent}</div>`;
      return `<div role="listitem" class="stat-strip__centered-item">` +
        `<div class="stat-strip__centered-stack uk-flex uk-flex-column uk-flex-between">` +
        `<span class="stat-strip__centered-accent" aria-hidden="true"></span>` +
        `${value}${label}${meta}</div></div>`;
    })
    .join('');

  const layout = items
    ? `<div class="stat-strip__grid stat-strip__grid--centered uk-grid uk-grid-large uk-child-width-1-1 uk-child-width-1-3@m" data-uk-grid role="list">${items}</div>`
    : '<div class="uk-alert-warning" role="alert">Keine Kennzahlen hinterlegt.</div>';

  const marquee = renderStatStripMarquee(block);
  return renderSection({ block, variant: 'centered', content: `${header}${layout}${marquee}` });
}

function renderStatStripHighlight(block, options = {}) {
  const context = options?.context || 'frontend';
  const header = renderStatStripHeader(block, context, 'left');
  const metrics = getValidMetrics(block);

  const items = metrics
    .map((metric, index) => {
      const value = renderStatMetricValue(block, metric, index, context, {
        valueSize: 'stat-strip__highlight-value uk-heading-large',
        alignClass: 'uk-text-left'
      });
      const label = renderStatMetricLabel(block, metric, index, context, {
        labelClass: 'stat-strip__highlight-label',
        alignClass: 'uk-text-left',
        extraClass: 'stat-strip__label'
      });
      const benefit = renderStatMetricBenefit(block, metric, index, context, {
        alignClass: 'uk-text-left',
        extraClass: 'stat-strip__highlight-meta-item'
      });
      const asOf = renderStatMetricAsOf(block, metric, index, context, {
        alignClass: 'uk-text-left',
        extraClass: 'stat-strip__highlight-meta-item stat-strip__highlight-meta-item--muted'
      });
      const metaContent = benefit || asOf ? `${benefit}${asOf}` : '<span class="stat-strip__meta-placeholder" aria-hidden="true"></span>';
      const meta = `<div class="stat-strip__highlight-meta">${metaContent}</div>`;
      const icon = metric.icon
        ? `<span class="stat-strip__highlight-icon" aria-hidden="true">${escapeHtml(metric.icon)}</span>`
        : '<span class="stat-strip__highlight-dot" aria-hidden="true"></span>';

      return `<div role="listitem" class="stat-strip__highlight-item">` +
        `<div class="stat-strip__highlight-shell">` +
        `${icon}<div class="stat-strip__highlight-main">${value}${label}${meta}</div></div></div>`;
    })
    .join('');

  const layout = items
    ? `<div class="stat-strip__grid stat-strip__grid--highlight uk-grid uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m" data-uk-grid role="list">${items}</div>`
    : '<div class="uk-alert-warning" role="alert">Keine Kennzahlen hinterlegt.</div>';

  const marquee = renderStatStripMarquee(block);
  return renderSection({ block, variant: 'highlight', content: `${header}${layout}${marquee}` });
}

function renderProofMetricCallout(block, options = {}) {
  const context = options?.context || 'frontend';
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const subtitle = block.data?.subtitle
    ? `<p class="uk-text-lead uk-margin-small-top uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.subtitle', context)}>${escapeHtml(block.data.subtitle)}</p>`
    : '';
  const header = title || subtitle ? `<div class="uk-width-1-1 uk-text-center uk-margin-medium-bottom">${title}${subtitle}</div>` : '';

  const metricCards = (Array.isArray(block.data?.metrics) ? block.data.metrics : [])
    .filter(metric => metric && typeof metric.value === 'string' && typeof metric.label === 'string')
    .map(metric => {
      const tooltip = metric.tooltip ? ` title="${escapeAttribute(metric.tooltip)}" data-uk-tooltip` : '';
      const value = `<div class="uk-heading-large uk-margin-remove"${tooltip}>${escapeHtml(metric.value)}</div>`;
      const label = `<p class="uk-text-muted uk-margin-remove-top uk-margin-small-bottom">${escapeHtml(metric.label)}</p>`;
      const benefit = metric.benefit
        ? `<p class="uk-text-success uk-margin-small-top uk-margin-remove-bottom">${escapeHtml(metric.benefit)}</p>`
        : '';
      const asOf = metric.asOf
        ? `<p class="uk-text-meta uk-margin-small-top uk-margin-remove-bottom">${escapeHtml(metric.asOf)}</p>`
        : '';

      return `<div class="uk-width-1-1 uk-width-1-3@m"><div class="uk-card uk-card-default uk-card-body uk-text-center uk-height-1-1">${value}${label}${benefit}${asOf}</div></div>`;
    })
    .join('');

  const metricsGrid = metricCards
    ? `<div class="uk-grid uk-child-width-1-1 uk-child-width-1-3@m uk-grid-medium" data-uk-grid>${metricCards}</div>`
    : '';

  const marqueeItems = Array.isArray(block.data?.marquee)
    ? block.data.marquee.filter(item => typeof item === 'string' && item.trim() !== '')
    : [];

  const marquee = marqueeItems.length
    ? `<div class="uk-margin-large-top"><div class="uk-flex uk-flex-middle uk-flex-center uk-flex-wrap uk-text-muted uk-text-small">${marqueeItems
        .map(text => `<span class="uk-margin-small-left uk-margin-small-right">${escapeHtml(text)}</span>`)
        .join('<span class="uk-text-muted">•</span>')}</div></div>`
    : '';

  return renderSection({ block, variant: 'metric-callout', content: `${header}${metricsGrid}${marquee}` });
}

function renderAudienceSpotlightCard(item) {
  const badge = item.badge ? `<span class="uk-label uk-label-success uk-margin-small-bottom">${escapeHtml(item.badge)}</span>` : '';
  const title = `<h3 class="uk-h4 uk-margin-remove-bottom">${escapeHtml(item.title || '')}</h3>`;
  const lead = item.lead ? `<p class="uk-text-lead uk-margin-small-top">${escapeHtml(item.lead)}</p>` : '';
  const body = item.body ? `<p class="uk-margin-small-top">${escapeHtml(item.body)}</p>` : '';

  const bullets = Array.isArray(item.bullets)
    ? item.bullets
        .filter(text => typeof text === 'string' && text.trim() !== '')
        .map(text => `<li>${escapeHtml(text)}</li>`)
    : [];
  const bulletList = bullets.length ? `<ul class="uk-list uk-list-bullet uk-margin-small-top">${bullets.join('')}</ul>` : '';

  const keyFacts = Array.isArray(item.keyFacts)
    ? item.keyFacts
        .filter(text => typeof text === 'string' && text.trim() !== '')
        .map(text => `<li>${escapeHtml(text)}</li>`)
    : [];
  const keyFactList = keyFacts.length
    ? `<div class="uk-margin-small-top"><h4 class="uk-h6 uk-text-muted uk-margin-remove-bottom">Key Facts</h4><ul class="uk-list uk-list-divider uk-margin-small-top">${keyFacts
        .filter(Boolean)
        .map(text => `<li class="uk-text-small">${escapeHtml(text)}</li>`)
        .join('')}</ul></div>`
    : '';

  const media = item.media?.image
    ? `<div class="uk-card-media-top uk-border-rounded uk-overflow-hidden"><img src="${escapeAttribute(item.media.image)}" alt="${item.media.alt ? escapeAttribute(item.media.alt) : ''}" loading="lazy"></div>`
    : '';

  const content = `<div class="uk-card-body">${badge}${title}${lead}${body}${bulletList}${keyFactList}</div>`;

  return `<div><div class="uk-card uk-card-default uk-height-1-1">${media}${content}</div></div>`;
}

function renderAudienceSpotlight(block, variant = block.variant || 'tabs', options = {}) {
  const context = options?.context || 'frontend';
  const safeVariant = escapeAttribute(variant);
  const sectionTitle = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const sectionSubtitle = block.data?.subtitle
    ? `<p class="uk-text-lead uk-margin-small-top uk-margin-medium-bottom"${buildEditableAttributes(block, 'data.subtitle', context)}>${escapeHtml(block.data.subtitle)}</p>`
    : '';

  const cases = Array.isArray(block.data?.cases) ? block.data.cases : [];
  const gridVariants = {
    tabs: 'uk-child-width-1-1 uk-child-width-1-2@m',
    tiles: 'uk-child-width-1-1 uk-child-width-1-3@m',
    'single-focus': 'uk-child-width-1-1'
  };
  const gridClass = gridVariants[variant] || gridVariants.tabs;
  const cards = cases.map(item => renderAudienceSpotlightCard(item)).join('') ||
    '<div><div class="uk-alert-warning" role="alert">Keine Anwendungsfälle hinterlegt.</div></div>';

  const grid = `<div class="uk-grid uk-grid-medium ${gridClass}" data-uk-grid>${cards}</div>`;

  return renderSection({ block, variant: safeVariant, content: `${sectionTitle}${sectionSubtitle}${grid}` });
}

function renderPackageHighlightGroup(highlight) {
  const title = highlight.title ? `<h4 class="uk-h5 uk-margin-remove-bottom">${escapeHtml(highlight.title)}</h4>` : '';
  const bullets = Array.isArray(highlight.bullets)
    ? highlight.bullets
        .filter(text => typeof text === 'string' && text.trim() !== '')
        .map(text => `<li>${escapeHtml(text)}</li>`)
    : [];
  const list = bullets.length ? `<ul class="uk-list uk-list-bullet uk-margin-small-top">${bullets.join('')}</ul>` : '';
  return `${title}${list}`;
}

function renderPackageOption(option) {
  const title = `<h3 class="uk-h4 uk-margin-remove-bottom">${escapeHtml(option.title || '')}</h3>`;
  const intro = option.intro ? `<p class="uk-margin-small-top">${escapeHtml(option.intro)}</p>` : '';

  const highlights = Array.isArray(option.highlights)
    ? option.highlights
        .filter(item => item)
        .map(item => `<div class="uk-margin-small-top">${renderPackageHighlightGroup(item)}</div>`)
        .join('')
    : '';

  return `<div><div class="uk-card uk-card-default uk-height-1-1"><div class="uk-card-body">${title}${intro}${highlights}</div></div></div>`;
}

function renderPackagePlan(plan) {
  const badge = plan.badge ? `<span class="uk-label uk-label-success uk-margin-small-bottom">${escapeHtml(plan.badge)}</span>` : '';
  const title = `<h3 class="uk-h4 uk-margin-remove-bottom">${escapeHtml(plan.title || '')}</h3>`;
  const description = plan.description ? `<p class="uk-margin-small-top">${escapeHtml(plan.description)}</p>` : '';
  const features = Array.isArray(plan.features)
    ? plan.features
        .filter(text => typeof text === 'string' && text.trim() !== '')
        .map(text => `<li>${escapeHtml(text)}</li>`)
    : [];
  const featureList = features.length ? `<ul class="uk-list uk-list-bullet uk-margin-small-top">${features.join('')}</ul>` : '';
  const notes = Array.isArray(plan.notes)
    ? plan.notes
        .filter(text => typeof text === 'string' && text.trim() !== '')
        .map(text => `<li>${escapeHtml(text)}</li>`)
    : [];
  const noteList = notes.length
    ? `<div class="uk-margin-small-top"><ul class="uk-list uk-text-small uk-margin-remove">${notes.map(text => `<li>${escapeHtml(text)}</li>`).join('')}</ul></div>`
    : '';

  const ctas = [
    renderCtaButton(plan.primaryCta, 'uk-button-primary'),
    renderCtaButton(plan.secondaryCta, 'uk-button-default', 'uk-margin-small-left')
  ].filter(Boolean);
  const ctaGroup = ctas.length ? `<div class="uk-margin-medium-top uk-flex uk-flex-wrap uk-flex-left">${ctas.join('')}</div>` : '';

  return `<div><div class="uk-card uk-card-default uk-height-1-1 uk-flex uk-flex-column">` +
    `<div class="uk-card-body uk-flex-1">${badge}${title}${description}${featureList}${noteList}</div>` +
    (ctaGroup ? `<div class="uk-card-footer">${ctaGroup}</div>` : '') +
    `</div></div></div>`;
}

function renderPackageSummary(block, variant, options = {}) {
  const context = options?.context || 'frontend';
  const safeVariant = escapeAttribute(variant);
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const subtitle = block.data?.subtitle
    ? `<p class="uk-text-lead uk-margin-small-top uk-margin-medium-bottom"${buildEditableAttributes(block, 'data.subtitle', context)}>${escapeHtml(block.data.subtitle)}</p>`
    : '';

  const packageOptions = Array.isArray(block.data?.options) ? block.data.options : [];
  const plans = Array.isArray(block.data?.plans) ? block.data.plans : [];

  const isToggleVariant = variant === 'toggle';
  const itemsToRender = isToggleVariant ? packageOptions : plans;
  const renderItem = isToggleVariant ? renderPackageOption : renderPackagePlan;
  const gridClass = isToggleVariant ? 'uk-child-width-1-1 uk-child-width-1-2@m' : 'uk-child-width-1-1 uk-child-width-1-3@m';

  const cards = itemsToRender.length
    ? itemsToRender.map(item => renderItem(item)).join('')
    : '<div><div class="uk-alert-warning" role="alert">Keine Pakete hinterlegt.</div></div>';

  const disclaimer = block.data?.disclaimer
    ? `<p class="uk-text-small uk-text-muted uk-margin-medium-top"${buildEditableAttributes(block, 'data.disclaimer', context)}>${escapeHtml(block.data.disclaimer)}</p>`
    : '';

  const grid = `<div class="uk-grid uk-grid-medium ${gridClass}" data-uk-grid>${cards}</div>`;

  return renderSection({ block, variant: safeVariant, content: `${title}${subtitle}${grid}${disclaimer}` });
}

function renderFaqItem(block, item, index, context) {
  const questionAttributes = buildEditableAttributes(block, `data.items.${index}.question`, context);
  const answerAttributes = buildEditableAttributes(block, `data.items.${index}.answer`, context, { type: 'richtext' });
  const questionText = escapeHtml(item.question || '');
  const question = `<a class="uk-accordion-title" href="#"><span${questionAttributes}>${questionText}</span></a>`;
  const answer = item.answer
    ? `<div class="uk-accordion-content"><p class="uk-margin-remove-top"${answerAttributes}>${escapeHtml(item.answer)}</p></div>`
    : '';
  return `<li>${question}${answer}</li>`;
}

function renderFaq(block, options = {}) {
  const context = options?.context || 'frontend';
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const items = Array.isArray(block.data?.items) ? block.data.items : [];
  const accordionItems = items.length
    ? items.map((item, index) => renderFaqItem(block, item, index, context)).join('')
    : '<li><div class="uk-alert-warning" role="alert">Keine Fragen hinterlegt.</div></li>';
  const followUpData = block.data?.followUp;
  const followUpLinkLabel = followUpData?.linkLabel || followUpData?.text || 'Mehr erfahren';
  const followUpLink = followUpData?.href
    ? `<a class="uk-link-heading" href="${escapeAttribute(followUpData.href)}"><span${buildEditableAttributes(block, 'data.followUp.linkLabel', context)}>${escapeHtml(followUpLinkLabel)}</span></a>`
    : '';
  const followUpText = followUpData?.text
    ? `<span class="uk-text-muted"${buildEditableAttributes(block, 'data.followUp.text', context)}>${escapeHtml(followUpData.text)}</span>`
    : '';
  const followUp = followUpLink || followUpText
    ? `<div class="uk-margin-medium-top">${[followUpLink, followUpText].filter(Boolean).join(' ')}</div>`
    : '';

  const accordion = `<ul class="uk-accordion" data-uk-accordion>${accordionItems}</ul>`;

  return renderSection({ block, variant: 'accordion', content: `${title}${accordion}${followUp}` });
}

function renderLegacySystemModuleItem(item) {
  const media = item.media?.image
    ? `<div class="uk-card-media-top uk-border-rounded uk-overflow-hidden"><img src="${escapeAttribute(item.media.image)}" alt="${item.media.alt ? escapeAttribute(item.media.alt) : ''}" loading="lazy"></div>`
    : '';
  const title = `<h3 class="uk-h4 uk-margin-remove-bottom">${escapeHtml(item.title || '')}</h3>`;
  const description = item.description ? `<p class="uk-margin-small-top">${escapeHtml(item.description)}</p>` : '';
  const bullets = Array.isArray(item.bullets)
    ? item.bullets
        .filter(text => typeof text === 'string' && text.trim() !== '')
        .map(text => `<li>${escapeHtml(text)}</li>`)
    : [];
  const bulletList = bullets.length ? `<ul class="uk-list uk-list-bullet uk-margin-small-top">${bullets.join('')}</ul>` : '';
  return `<div><div class="uk-card uk-card-default uk-height-1-1">${media}<div class="uk-card-body">${title}${description}${bulletList}</div></div></div>`;
}

function renderLegacySystemModule(block) {
  const title = block.data?.title ? `<h2 class="uk-heading-medium uk-margin-remove-bottom">${escapeHtml(block.data.title)}</h2>` : '';
  const subtitle = block.data?.subtitle ? `<p class="uk-text-lead uk-margin-small-top uk-margin-medium-bottom">${escapeHtml(block.data.subtitle)}</p>` : '';
  const legacyNote = '<p class="uk-text-meta uk-margin-remove-top uk-margin-small-bottom">Legacy module block – consider migrating</p>';

  const items = Array.isArray(block.data?.items) ? block.data.items : [];
  const gridItems = items.length
    ? items.map(item => renderLegacySystemModuleItem(item)).join('')
    : '<div><div class="uk-alert-warning" role="alert">Keine Module hinterlegt.</div></div>';
  const grid = `<div class="uk-grid uk-grid-medium uk-child-width-1-1 uk-child-width-1-2@m" data-uk-grid>${gridItems}</div>`;

  return renderSection({ block, variant: 'switcher', content: `${legacyNote}${title}${subtitle}${grid}` });
}

function renderLegacyCaseShowcase(block, options = {}) {
  return renderAudienceSpotlight({ ...block, type: 'audience_spotlight' }, block.variant, options);
}

export const RENDERER_MATRIX = {
  hero: {
    centered_cta: renderHeroCenteredCta,
    media_right: renderHeroMediaRight,
    media_left: renderHeroMediaLeft,
    'media-right': renderHeroMediaRight,
    'media-left': renderHeroMediaLeft,
    minimal: renderHeroMinimal
  },
  feature_list: {
    'detailed-cards': renderFeatureListDetailedCards,
    'grid-bullets': renderFeatureListGridBullets,
    slider: renderFeatureListSlider,
    'text-columns': (block, options) => renderFeatureList(block, 'text-columns', options),
    'card-stack': (block, options) => renderFeatureList(block, 'card-stack', options),
    stacked_cards: (block, options) => renderFeatureList(block, 'card-stack', options),
    icon_grid: renderFeatureListGridBullets
  },
  process_steps: {
    'numbered-vertical': (block, options) => renderProcessSteps(block, 'numbered-vertical', options),
    'numbered-horizontal': (block, options) => renderProcessSteps(block, 'numbered-horizontal', options),
    timeline: renderProcessStepsTimeline,
    timeline_vertical: (block, options) => renderProcessSteps(block, 'timeline_vertical', options),
    timeline_horizontal: (block, options) => renderProcessSteps(block, 'timeline_horizontal', options)
  },
  testimonial: {
    single_quote: renderTestimonialSingle,
    quote_wall: renderTestimonialWall
  },
  rich_text: {
    prose: renderRichTextProse
  },
  info_media: {
    stacked: (block, options) => renderInfoMedia(block, 'stacked', options),
    'image-left': (block, options) => renderInfoMedia(block, 'image-left', options),
    'image-right': (block, options) => renderInfoMedia(block, 'image-right', options),
    switcher: (block, options) => renderInfoMedia(block, 'switcher', options)
  },
  content_slider: {
    words: (block, options) => renderContentSlider(block, 'words', options),
    images: (block, options) => renderContentSlider(block, 'images', options)
  },
  cta: {
    full_width: renderCta,
    split: renderCtaSplit
  },
  stat_strip: {
    inline: renderStatStripInline,
    cards: renderStatStripCards,
    centered: renderStatStripCentered,
    highlight: renderStatStripHighlight,
    'three-up': renderStatStripCards
  },
  proof: {
    'metric-callout': renderProofMetricCallout
  },
  audience_spotlight: {
    tabs: (block, options) => renderAudienceSpotlight(block, 'tabs', options),
    tiles: (block, options) => renderAudienceSpotlight(block, 'tiles', options),
    'single-focus': (block, options) => renderAudienceSpotlight(block, 'single-focus', options)
  },
  package_summary: {
    toggle: (block, options) => renderPackageSummary(block, 'toggle', options),
    'comparison-cards': (block, options) => renderPackageSummary(block, 'comparison-cards', options)
  },
  faq: {
    accordion: renderFaq
  },
  contact_form: {
    default: (block, options) => renderContactForm(block, 'default', options),
    compact: (block, options) => renderContactForm(block, 'compact', options)
  },
  system_module: {
    switcher: renderLegacySystemModule
  },
  case_showcase: {
    tabs: renderLegacyCaseShowcase
  }
};
