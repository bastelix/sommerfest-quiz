import { BLOCK_TYPES, getBlockVariants, validateBlockContract } from './block-contract.js';

function escapeHtml(value) {
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

function escapeAttribute(value) {
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

function renderFeatureListStacked(block) {
  const itemCount = Array.isArray(block.data.items) ? block.data.items.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="feature_list" data-block-variant="stacked_cards"><!-- feature_list:stacked_cards | ${itemCount} items --></section>`;
}

function renderFeatureListIconGrid(block) {
  const itemCount = Array.isArray(block.data.items) ? block.data.items.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="feature_list" data-block-variant="icon_grid"><!-- feature_list:icon_grid | ${itemCount} items --></section>`;
}

function renderProcessStepsHorizontal(block) {
  const stepCount = Array.isArray(block.data.steps) ? block.data.steps.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="process_steps" data-block-variant="timeline_horizontal"><!-- process_steps:timeline_horizontal | ${stepCount} steps --></section>`;
}

function renderProcessStepsVertical(block) {
  const stepCount = Array.isArray(block.data.steps) ? block.data.steps.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="process_steps" data-block-variant="timeline_vertical"><!-- process_steps:timeline_vertical | ${stepCount} steps --></section>`;
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

export const RENDERER_MATRIX = {
  hero: {
    centered_cta: renderHeroCenteredCta,
    media_right: renderHeroMediaRight,
    media_left: renderHeroMediaLeft
  },
  feature_list: {
    stacked_cards: renderFeatureListStacked,
    icon_grid: renderFeatureListIconGrid
  },
  process_steps: {
    timeline_horizontal: renderProcessStepsHorizontal,
    timeline_vertical: renderProcessStepsVertical
  },
  testimonial: {
    single_quote: renderTestimonialSingle,
    quote_wall: renderTestimonialWall
  },
  rich_text: {
    prose: renderRichTextProse
  }
};

function assertRenderable(block) {
  const validation = validateBlockContract(block);
  if (!validation.valid) {
    throw new Error(validation.reason || 'Block contract validation failed');
  }
  const variants = RENDERER_MATRIX[block.type];
  if (!variants) {
    throw new Error(`No renderer registered for type: ${block.type}`);
  }
  const renderer = variants[block.variant];
  if (!renderer) {
    const allowedVariants = getBlockVariants(block.type).join(', ');
    throw new Error(`Unsupported variant for ${block.type}: ${block.variant}. Allowed: ${allowedVariants}`);
  }
  return renderer;
}

export function renderBlock(block) {
  const renderer = assertRenderable(block);
  return renderer(block);
}

export function renderPage(blocks = []) {
  return (Array.isArray(blocks) ? blocks : [])
    .map(block => renderBlock(block))
    .join('\n');
}

export function listSupportedBlocks() {
  return BLOCK_TYPES.reduce((accumulator, type) => {
    accumulator[type] = getBlockVariants(type);
    return accumulator;
  }, {});
}
