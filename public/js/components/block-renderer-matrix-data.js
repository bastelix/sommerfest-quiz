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

function renderHeroCenteredCta(block) {
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="hero" data-block-variant="centered_cta"><!-- hero:centered_cta | ${escapeHtml(block.data.headline || '')} --></section>`;
}

function renderHeroMediaRight(block) {
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="hero" data-block-variant="media_right"><!-- hero:media_right | ${escapeHtml(block.data.headline || '')} --></section>`;
}

function renderHeroMediaLeft(block) {
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="hero" data-block-variant="media_left"><!-- hero:media_left | ${escapeHtml(block.data.headline || '')} --></section>`;
}

function renderFeatureList(block, variant) {
  const itemCount = Array.isArray(block.data.items) ? block.data.items.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="feature_list" data-block-variant="${escapeAttribute(variant)}"><!-- feature_list:${escapeHtml(variant)} | ${itemCount} items --></section>`;
}

function renderProcessSteps(block, variant) {
  const stepCount = Array.isArray(block.data.steps) ? block.data.steps.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="process_steps" data-block-variant="${escapeAttribute(variant)}"><!-- process_steps:${escapeHtml(variant)} | ${stepCount} steps --></section>`;
}

function renderTestimonialSingle(block) {
  const author = block.data?.author?.name ? ` by ${escapeHtml(block.data.author.name)}` : '';
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="testimonial" data-block-variant="single_quote"><!-- testimonial:single_quote${author} --></section>`;
}

function renderTestimonialWall(block) {
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="testimonial" data-block-variant="quote_wall"><!-- testimonial:quote_wall | grouped quotes --></section>`;
}

function renderRichTextProse(block) {
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="rich_text" data-block-variant="prose"><!-- rich_text:prose -->${block.data.body || ''}</section>`;
}

function renderInfoMedia(block, variant) {
  const title = block.data?.title || block.data?.subtitle || block.data?.body || '';
  const count = Array.isArray(block.data.items) ? block.data.items.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="info_media" data-block-variant="${escapeAttribute(variant)}"><!-- info_media:${escapeHtml(variant)} | ${escapeHtml(title)} (${count} items) --></section>`;
}

function renderCta(block, variant) {
  if (variant === 'split') {
    const primaryLabel = block?.data?.primary?.label || '';
    const secondaryLabel = block?.data?.secondary?.label || '';
    return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="cta" data-block-variant="split"><!-- cta:split | ${escapeHtml(primaryLabel)} / ${escapeHtml(secondaryLabel)} --></section>`;
  }

  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="cta" data-block-variant="full_width"><!-- cta:full_width | ${escapeHtml(block.data.label || '')} --></section>`;
}

function renderStatStrip(block) {
  const count = Array.isArray(block.data.metrics) ? block.data.metrics.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="stat_strip" data-block-variant="three-up"><!--stat_strip:three-up | ${count} metrics --></section>`;
}

function renderAudienceSpotlight(block) {
  const count = Array.isArray(block.data.cases) ? block.data.cases.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="audience_spotlight" data-block-variant="tabs"><!-- audience_spotlight:tabs | ${count} cases --></section>`;
}

function renderPackageSummary(block, variant) {
  const plans = Array.isArray(block.data.plans) ? block.data.plans.length : 0;
  const options = Array.isArray(block.data.options) ? block.data.options.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="package_summary" data-block-variant="${escapeAttribute(variant)}"><!-- package_summary:${escapeHtml(variant)} | ${plans} plans, ${options} options --></section>`;
}

function renderFaq(block) {
  const count = Array.isArray(block.data.items) ? block.data.items.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="faq" data-block-variant="accordion"><!-- faq:accordion | ${count} questions --></section>`;
}

function renderLegacySystemModule(block) {
  return renderInfoMedia({ ...block, type: 'info_media' }, 'switcher');
}

function renderLegacyCaseShowcase(block) {
  return renderAudienceSpotlight({ ...block, type: 'audience_spotlight' });
}

export const RENDERER_MATRIX = {
  hero: {
    centered_cta: renderHeroCenteredCta,
    media_right: renderHeroMediaRight,
    media_left: renderHeroMediaLeft
  },
  feature_list: {
    stacked_cards: block => renderFeatureList(block, 'stacked_cards'),
    icon_grid: block => renderFeatureList(block, 'icon_grid'),
    'detailed-cards': block => renderFeatureList(block, 'detailed-cards'),
    'grid-bullets': block => renderFeatureList(block, 'grid-bullets')
  },
  process_steps: {
    timeline_horizontal: block => renderProcessSteps(block, 'timeline_horizontal'),
    timeline_vertical: block => renderProcessSteps(block, 'timeline_vertical'),
    timeline: block => renderProcessSteps(block, 'timeline')
  },
  testimonial: {
    single_quote: renderTestimonialSingle,
    quote_wall: renderTestimonialWall
  },
  rich_text: {
    prose: renderRichTextProse
  },
  info_media: {
    stacked: block => renderInfoMedia(block, 'stacked'),
    switcher: block => renderInfoMedia(block, 'switcher')
  },
  cta: {
    full_width: block => renderCta(block, 'full_width'),
    split: block => renderCta(block, 'split')
  },
  stat_strip: {
    'three-up': renderStatStrip
  },
  audience_spotlight: {
    tabs: renderAudienceSpotlight
  },
  package_summary: {
    toggle: block => renderPackageSummary(block, 'toggle'),
    'comparison-cards': block => renderPackageSummary(block, 'comparison-cards')
  },
  faq: {
    accordion: renderFaq
  },
  system_module: {
    switcher: renderLegacySystemModule
  },
  case_showcase: {
    tabs: renderLegacyCaseShowcase
  }
};
