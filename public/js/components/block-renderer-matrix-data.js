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

function renderHeroSection({ block, variant, content, sectionModifiers = '' }) {
  const sectionClasses = ['uk-section', 'uk-section-default', sectionModifiers].filter(Boolean).join(' ');
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  return `<section${anchor} class="${sectionClasses}" data-block-id="${escapeAttribute(block.id)}" data-block-type="hero" data-block-variant="${escapeAttribute(variant)}"><div class="uk-container">${content}</div></section>`;
}

function renderEyebrow(eyebrow, alignmentClass = '') {
  if (!eyebrow) {
    return '';
  }
  const alignment = alignmentClass ? ` ${alignmentClass}` : '';
  return `<p class="uk-text-meta uk-margin-remove-bottom${alignment}">${escapeHtml(eyebrow)}</p>`;
}

function renderHeadline(headline, alignmentClass = '') {
  const alignment = alignmentClass ? ` ${alignmentClass}` : '';
  return `<h1 class="uk-heading-medium uk-margin-small-top${alignment}">${escapeHtml(headline || '')}</h1>`;
}

function renderSubheadline(subheadline, alignmentClass = '') {
  if (!subheadline) {
    return '';
  }
  const alignment = alignmentClass ? ` ${alignmentClass}` : '';
  return `<p class="uk-text-lead uk-margin-small-top uk-margin-remove-bottom${alignment}">${escapeHtml(subheadline)}</p>`;
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

function renderHeroCenteredCta(block) {
  const eyebrow = renderEyebrow(block.data?.eyebrow, 'uk-text-center');
  const headline = renderHeadline(block.data?.headline, 'uk-text-center');
  const subheadline = renderSubheadline(block.data?.subheadline, 'uk-text-center');
  const ctas = renderHeroCtas(block.data?.cta, 'uk-flex-center');
  const content = `<div class="uk-width-1-1 uk-width-2-3@m uk-align-center uk-text-center">${eyebrow}${headline}${subheadline}${ctas}</div>`;
  return renderHeroSection({ block, variant: 'centered_cta', content });
}

function renderHeroMediaRight(block) {
  const eyebrow = renderEyebrow(block.data?.eyebrow);
  const headline = renderHeadline(block.data?.headline);
  const subheadline = renderSubheadline(block.data?.subheadline);
  const ctas = renderHeroCtas(block.data?.cta);
  const media = renderHeroMedia(block.data?.media);
  const textColumnWidth = media ? 'uk-width-1-1 uk-width-1-2@m' : 'uk-width-1-1';
  const mediaColumn = media ? `<div class="uk-width-1-1 uk-width-1-2@m">${media}</div>` : '';
  const textColumn = `<div class="${textColumnWidth}">${eyebrow}${headline}${subheadline}${ctas}</div>`;
  const grid = `<div class="uk-grid-large uk-flex-middle" data-uk-grid>${textColumn}${mediaColumn}</div>`;
  return renderHeroSection({ block, variant: 'media_right', content: grid });
}

function renderHeroMediaLeft(block) {
  const eyebrow = renderEyebrow(block.data?.eyebrow);
  const headline = renderHeadline(block.data?.headline);
  const subheadline = renderSubheadline(block.data?.subheadline);
  const ctas = renderHeroCtas(block.data?.cta);
  const media = renderHeroMedia(block.data?.media);
  const textColumnWidth = media ? 'uk-width-1-1 uk-width-1-2@m' : 'uk-width-1-1';
  const mediaColumn = media ? `<div class="uk-width-1-1 uk-width-1-2@m">${media}</div>` : '';
  const textColumn = `<div class="${textColumnWidth}">${eyebrow}${headline}${subheadline}${ctas}</div>`;
  const grid = `<div class="uk-grid-large uk-flex-middle" data-uk-grid>${mediaColumn}${textColumn}</div>`;
  return renderHeroSection({ block, variant: 'media_left', content: grid });
}

function renderHeroMinimal(block) {
  const eyebrow = renderEyebrow(block.data?.eyebrow);
  const headline = renderHeadline(block.data?.headline);
  const subheadline = renderSubheadline(block.data?.subheadline);
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
  const description = item.description ? `<p class="uk-margin-small-top uk-margin-remove-bottom">${escapeHtml(item.description)}</p>` : '';
  const bullets = renderFeatureBullets(item.bullets);

  return `${title}${description}${bullets}`;
}

function normalizeFeatureListItems(block) {
  if (!Array.isArray(block.data?.items)) {
    return [];
  }

  return block.data.items.filter(item => item && typeof item.title === 'string' && typeof item.description === 'string');
}

function renderFeatureListHeader(title, subtitle) {
  if (!title && !subtitle) {
    return '';
  }

  const heading = title ? `<h2 class="uk-heading-medium uk-margin-remove-bottom">${escapeHtml(title)}</h2>` : '';
  const subheading = subtitle ? `<p class="uk-text-lead uk-margin-small-top">${escapeHtml(subtitle)}</p>` : '';

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

function renderFeatureList(block, variant) {
  if (variant !== 'text-columns' && variant !== 'card-stack') {
    throw new Error(`Unsupported variant for feature_list: ${variant}`);
  }

  const items = normalizeFeatureListItems(block);

  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const header = renderFeatureListHeader(block.data?.title, block.data?.subtitle);
  const grid = variant === 'card-stack' ? renderFeatureListCardStack(items) : renderFeatureListTextColumns(items);

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="feature_list" data-block-variant="${escapeAttribute(variant)}"><div class="uk-container">${header}${grid}</div></section>`;
}

function renderFeatureListDetailedCards(block) {
  const items = normalizeFeatureListItems(block);
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const eyebrow = renderEyebrow(block.data?.eyebrow);
  const title = block.data?.title ? `<h2 class="uk-heading-medium uk-margin-remove-bottom">${escapeHtml(block.data.title)}</h2>` : '';
  const lead = block.data?.lead ? `<p class="uk-text-lead uk-margin-small-top uk-margin-remove-bottom">${escapeHtml(block.data.lead)}</p>` : '';
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

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="feature_list" data-block-variant="detailed-cards"><div class="uk-container">${header}${grid}${footer}</div></section>`;
}

function renderFeatureListGridBullets(block) {
  const items = normalizeFeatureListItems(block);
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const intro = block.data?.intro ? `<p class="uk-text-meta uk-margin-remove-bottom">${escapeHtml(block.data.intro)}</p>` : '';
  const title = block.data?.title ? `<h2 class="uk-heading-medium uk-margin-small-top uk-margin-remove-bottom">${escapeHtml(block.data.title)}</h2>` : '';
  const subtitle = block.data?.subtitle ? `<p class="uk-text-lead uk-margin-small-top">${escapeHtml(block.data.subtitle)}</p>` : '';
  const header = (intro || title || subtitle) ? `<div class="uk-width-1-1 uk-margin-medium-bottom">${intro}${title}${subtitle}</div>` : '';

  const cards = items
    .map(item => {
      const content = renderFeatureListItemContent(item);
      return `<div class="uk-width-1-1 uk-width-1-2@m uk-width-1-3@l"><div class="uk-card uk-card-default uk-card-small uk-height-1-1"><div class="uk-card-body">${content}</div></div></div>`;
    })
    .join('');

  const grid = `<div class="uk-grid uk-grid-small" data-uk-grid>${cards}</div>`;

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="feature_list" data-block-variant="grid-bullets"><div class="uk-container">${header}${grid}</div></section>`;
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

function renderCta(block) {
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="cta" data-block-variant="full_width"><!-- cta:full_width | ${escapeHtml(block.data.label || '')} --></section>`;
}

function renderCtaSplit(block) {
  const primary = block.data?.primary?.label ? escapeHtml(block.data.primary.label) : '';
  const secondary = block.data?.secondary?.label ? escapeHtml(block.data.secondary.label) : '';
  const labels = [primary, secondary].filter(Boolean).join(' / ');
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="cta" data-block-variant="split"><!-- cta:split | ${labels} --></section>`;
}

function renderStatStrip(block) {
  const count = Array.isArray(block.data.metrics) ? block.data.metrics.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="stat_strip" data-block-variant="three-up"><!--stat_strip:three-up | ${count} metrics --></section>`;
}

function renderAudienceSpotlight(block, variant = block.variant || 'tabs') {
  const count = Array.isArray(block.data.cases) ? block.data.cases.length : 0;
  const safeVariant = escapeAttribute(variant);
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="audience_spotlight" data-block-variant="${safeVariant}"><!-- audience_spotlight:${escapeHtml(variant)} | ${count} cases --></section>`;
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
    media_left: renderHeroMediaLeft,
    minimal: renderHeroMinimal
  },
  feature_list: {
    'detailed-cards': renderFeatureListDetailedCards,
    'grid-bullets': renderFeatureListGridBullets,
    'text-columns': block => renderFeatureList(block, 'text-columns'),
    'card-stack': block => renderFeatureList(block, 'card-stack')
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
    full_width: renderCta,
    split: renderCtaSplit
  },
  stat_strip: {
    'three-up': renderStatStrip
  },
  audience_spotlight: {
    tabs: block => renderAudienceSpotlight(block, 'tabs'),
    tiles: block => renderAudienceSpotlight(block, 'tiles'),
    'single-focus': block => renderAudienceSpotlight(block, 'single-focus')
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
