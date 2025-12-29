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
  const allowedVariants = new Set(['numbered-vertical', 'numbered-horizontal']);

  if (!allowedVariants.has(variant)) {
    throw new Error(`Unsupported process_steps variant: ${variant}`);
  }

  const steps = Array.isArray(block.data?.steps) ? block.data.steps : [];

  if (!steps.length) {
    throw new Error('process_steps block requires at least one step');
  }

  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const title = block.data?.title ? `<h2 class="uk-heading-medium uk-margin-remove-bottom">${escapeHtml(block.data.title)}</h2>` : '';
  const summary = block.data?.summary ? `<p class="uk-text-lead uk-margin-small-top">${escapeHtml(block.data.summary)}</p>` : '';
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

  const layout = variant === 'numbered-horizontal' ? renderHorizontalSteps() : renderVerticalSteps();

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="process_steps" data-block-variant="${escapeAttribute(variant)}"><div class="uk-container">${header}${layout}</div></section>`;
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
  const allowedVariants = new Set(['stacked', 'image-left', 'image-right']);

  if (!allowedVariants.has(variant)) {
    throw new Error(`Unsupported info_media variant: ${variant}`);
  }

  const body = block.data?.body;
  if (!body) {
    throw new Error('info_media block requires body content');
  }

  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom">${escapeHtml(block.data.title)}</h2>`
    : '';
  const textColumn = `<div class="${variant === 'stacked' ? 'uk-width-1-1' : 'uk-width-1-1 uk-width-1-2@m'}">${title}<div class="uk-margin-small-top">${body}</div></div>`;

  const media = block.data?.media;
  const hasMedia = !!media?.image;
  if (!hasMedia && variant !== 'stacked') {
    throw new Error(`info_media:${variant} variant requires media.image`);
  }

  const mediaColumn = hasMedia
    ? `<div class="uk-width-1-1 uk-width-1-2@m"><div class="uk-border-rounded uk-overflow-hidden uk-box-shadow-small"><img class="uk-width-1-1" src="${escapeAttribute(media.image)}" alt="${media.alt ? escapeAttribute(media.alt) : ''}" loading="lazy"></div></div>`
    : '';

  const columnsByVariant = {
    stacked: `${textColumn}${mediaColumn}`,
    'image-left': `${mediaColumn}${textColumn}`,
    'image-right': `${textColumn}${mediaColumn}`
  };

  const grid = `<div class="uk-grid-large uk-flex-middle" data-uk-grid>${columnsByVariant[variant]}</div>`;

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="info_media" data-block-variant="${escapeAttribute(variant)}"><div class="uk-container">${grid}</div></section>`;
}

function renderCtaButton(cta, styleClass = 'uk-button-primary', additionalClasses = '') {
  if (!cta?.label || !cta?.href) {
    return '';
  }

  const ariaLabel = cta.ariaLabel ? ` aria-label="${escapeAttribute(cta.ariaLabel)}"` : '';
  const classes = ['uk-button', styleClass, additionalClasses].filter(Boolean).join(' ');

  return `<a class="${classes}" href="${escapeAttribute(cta.href)}"${ariaLabel}>${escapeHtml(cta.label)}</a>`;
}

function renderCtaButtons(primary, secondary, { alignment = '', margin = 'uk-margin-medium-top' } = {}) {
  const primaryButton = renderCtaButton(primary, 'uk-button-primary');
  const secondaryButton = renderCtaButton(secondary, 'uk-button-default', primaryButton ? 'uk-margin-small-top uk-margin-small-left@m' : '');

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

function renderCta(block) {
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom">${escapeHtml(block.data.title)}</h2>`
    : '';
  const body = block.data?.body
    ? `<p class="uk-text-lead uk-margin-small-top uk-margin-remove-bottom">${escapeHtml(block.data.body)}</p>`
    : '';
  const primary = block.data?.primary || block.data;
  const secondary = block.data?.secondary;
  const buttons = renderCtaButtons(primary, secondary, {
    alignment: 'uk-flex-center',
    margin: 'uk-margin-medium-top'
  });
  if (!buttons) {
    throw new Error('CTA block requires at least one valid action');
  }
  const inner = `<div class="uk-text-center uk-width-1-1 uk-width-2-3@m uk-margin-auto">${title}${body}${buttons}</div>`;

  return `<section${anchor} class="uk-section uk-section-primary" data-block-id="${escapeAttribute(block.id)}" data-block-type="cta" data-block-variant="full_width"><div class="uk-container">${inner}</div></section>`;
}

function renderCtaSplit(block) {
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom">${escapeHtml(block.data.title)}</h2>`
    : '';
  const body = block.data?.body
    ? `<p class="uk-text-lead uk-margin-small-top uk-margin-remove-bottom">${escapeHtml(block.data.body)}</p>`
    : '';
  const textColumn = `<div class="uk-width-expand">${title}${body}</div>`;
  const primary = block.data?.primary || block.data;
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

  return `<section${anchor} class="uk-section uk-section-primary" data-block-id="${escapeAttribute(block.id)}" data-block-type="cta" data-block-variant="split"><div class="uk-container">${layout}</div></section>`;
}

function renderStatStrip(block) {
  const count = Array.isArray(block.data.metrics) ? block.data.metrics.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="stat_strip" data-block-variant="three-up"><!--stat_strip:three-up | ${count} metrics --></section>`;
}

function renderProofMetricCallout(block) {
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const title = block.data?.title ? `<h2 class="uk-heading-medium uk-margin-remove-bottom">${escapeHtml(block.data.title)}</h2>` : '';
  const subtitle = block.data?.subtitle
    ? `<p class="uk-text-lead uk-margin-small-top uk-margin-remove-bottom">${escapeHtml(block.data.subtitle)}</p>`
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

  return `<section${anchor} class="uk-section uk-section-muted" data-block-id="${escapeAttribute(block.id)}" data-block-type="proof" data-block-variant="metric-callout"><div class="uk-container">${header}${metricsGrid}${marquee}</div></section>`;
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
  throw new Error('system_module/switcher variant is deprecated; migrate to a supported info_media layout');
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
    'card-stack': block => renderFeatureList(block, 'card-stack'),
    stacked_cards: block => renderFeatureList(block, 'card-stack'),
    icon_grid: renderFeatureListGridBullets
  },
  process_steps: {
    'numbered-vertical': block => renderProcessSteps(block, 'numbered-vertical'),
    'numbered-horizontal': block => renderProcessSteps(block, 'numbered-horizontal')
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
    'image-left': block => renderInfoMedia(block, 'image-left'),
    'image-right': block => renderInfoMedia(block, 'image-right')
  },
  cta: {
    full_width: renderCta,
    split: renderCtaSplit
  },
  stat_strip: {
    'three-up': renderStatStrip
  },
  proof: {
    'metric-callout': renderProofMetricCallout
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
