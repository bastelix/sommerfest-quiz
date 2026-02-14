export const SECTION_INTENTS = ['content', 'feature', 'highlight', 'hero', 'plain'];

export const DEFAULT_SECTION_INTENT_BY_TYPE = {
  hero: 'hero',
  feature_list: 'feature',
  info_media: 'feature',
  audience_spotlight: 'feature',
  testimonial: 'feature',
  package_summary: 'feature',
  contact_form: 'feature',
  content_slider: 'feature',
  stat_strip: 'highlight',
  proof: 'highlight',
  cta: 'highlight',
  process_steps: 'content',
  rich_text: 'content',
  faq: 'plain',
  latest_news: 'feature'
};

export function normalizeSectionIntent(intent) {
  if (typeof intent !== 'string') {
    return undefined;
  }

  const normalized = intent.trim();
  return SECTION_INTENTS.includes(normalized) ? normalized : undefined;
}

export function resolveDefaultSectionIntent(type) {
  if (!type) {
    return 'content';
  }

  return DEFAULT_SECTION_INTENT_BY_TYPE[type] || 'content';
}

export function resolveSectionIntent(blockOrType) {
  return resolveSectionIntentInfo(blockOrType).intent;
}

export function resolveSectionIntentInfo(blockOrType) {
  const rawIntent = blockOrType?.meta?.sectionStyle?.intent
    ?? (typeof blockOrType === 'object' ? blockOrType?.sectionIntent : undefined);
  const normalizedIntent = normalizeSectionIntent(rawIntent);
  if (normalizedIntent) {
    return { intent: normalizedIntent, isExplicit: true };
  }

  const type = typeof blockOrType === 'string' ? blockOrType : blockOrType?.type;
  return { intent: resolveDefaultSectionIntent(type), isExplicit: false };
}

export const CONTAINER_WIDTHS = ['normal', 'wide', 'full'];
export const CONTAINER_FRAMES = ['none', 'card'];
export const CONTAINER_SPACINGS = ['compact', 'normal', 'generous'];

const DARK_BACKGROUND_TOKENS = new Set(['primary', 'secondary', 'accent']);

export const DEFAULT_CONTAINER_BY_TYPE = {
  hero:               { width: 'full',   frame: 'none', spacing: 'generous' },
  feature_list:       { width: 'wide',   frame: 'none', spacing: 'generous' },
  info_media:         { width: 'wide',   frame: 'none', spacing: 'generous' },
  audience_spotlight: { width: 'wide',   frame: 'none', spacing: 'generous' },
  testimonial:        { width: 'wide',   frame: 'none', spacing: 'generous' },
  package_summary:    { width: 'wide',   frame: 'none', spacing: 'generous' },
  contact_form:       { width: 'wide',   frame: 'none', spacing: 'generous' },
  content_slider:     { width: 'wide',   frame: 'none', spacing: 'generous' },
  stat_strip:         { width: 'wide',   frame: 'none', spacing: 'generous' },
  proof:              { width: 'wide',   frame: 'none', spacing: 'generous' },
  cta:                { width: 'wide',   frame: 'none', spacing: 'generous' },
  process_steps:      { width: 'normal', frame: 'none', spacing: 'normal' },
  rich_text:          { width: 'normal', frame: 'none', spacing: 'normal' },
  faq:                { width: 'normal', frame: 'none', spacing: 'normal' },
  latest_news:        { width: 'wide',   frame: 'none', spacing: 'generous' }
};

export function deriveSectionIntent(container, background) {
  const bgMode = background?.mode;
  const colorToken = background?.colorToken;
  const isDark = bgMode === 'color' && DARK_BACKGROUND_TOKENS.has(colorToken);

  if (isDark) {
    return colorToken === 'secondary' ? 'hero' : 'highlight';
  }
  if (container?.width === 'full') return 'hero';
  if (container?.width === 'wide') return 'feature';
  if (container?.frame === 'card') return 'content';
  return 'content';
}

const INTENT_TO_CONTAINER_WIDTH = {
  content: 'normal',
  plain: 'normal',
  feature: 'wide',
  highlight: 'wide',
  hero: 'full'
};

const INTENT_TO_CONTAINER_SPACING = {
  content: 'normal',
  plain: 'normal',
  feature: 'generous',
  highlight: 'generous',
  hero: 'generous'
};

export function fromStoredSectionStyle(stored, blockType) {
  const layout = stored?.layout || 'normal';
  const intent = normalizeSectionIntent(stored?.intent) || resolveDefaultSectionIntent(blockType);
  const bg = stored?.background || {};

  const explicitContainer = stored?.container;
  const container = explicitContainer && typeof explicitContainer === 'object'
    ? {
        width: CONTAINER_WIDTHS.includes(explicitContainer.width) ? explicitContainer.width : (INTENT_TO_CONTAINER_WIDTH[intent] || 'normal'),
        frame: CONTAINER_FRAMES.includes(explicitContainer.frame) ? explicitContainer.frame : ((layout === 'card' || layout === 'full-card') ? 'card' : 'none'),
        spacing: CONTAINER_SPACINGS.includes(explicitContainer.spacing) ? explicitContainer.spacing : (INTENT_TO_CONTAINER_SPACING[intent] || 'normal')
      }
    : {
        width: INTENT_TO_CONTAINER_WIDTH[intent] || 'normal',
        frame: (layout === 'card' || layout === 'full-card') ? 'card' : 'none',
        spacing: INTENT_TO_CONTAINER_SPACING[intent] || 'normal'
      };

  return {
    container,
    background: {
      mode: bg.mode || 'none',
      colorToken: bg.colorToken,
      imageId: bg.imageId,
      attachment: bg.attachment,
      overlay: bg.overlay,
      bleed: layout === 'full' || layout === 'full-card'
    }
  };
}

export function toStoredSectionStyle(container, background) {
  const bleed = background?.bleed ?? false;
  const frame = container?.frame || 'none';
  const layout = bleed && frame === 'card' ? 'full-card' : bleed ? 'full' : frame === 'card' ? 'card' : 'normal';
  const intent = deriveSectionIntent(container, background);

  const bgMode = background?.mode || 'none';
  const storedBackground = { mode: bgMode };

  if (bgMode === 'color' && background?.colorToken) {
    storedBackground.colorToken = background.colorToken;
  }
  if (bgMode === 'image') {
    if (background?.imageId) storedBackground.imageId = background.imageId;
    if (background?.attachment) storedBackground.attachment = background.attachment;
    if (background?.overlay !== undefined) storedBackground.overlay = background.overlay;
  }

  const storedContainer = {
    width: container?.width || 'normal',
    frame: container?.frame || 'none',
    spacing: container?.spacing || 'normal'
  };

  return {
    layout,
    intent,
    background: storedBackground,
    container: storedContainer
  };
}
