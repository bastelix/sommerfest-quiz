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
  faq: 'plain'
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
