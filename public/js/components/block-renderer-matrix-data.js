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

function renderHeroSection({ block, variant, content, sectionModifiers = '' }) {
  const sectionClasses = ['uk-section', 'uk-section-default', sectionModifiers].filter(Boolean).join(' ');
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  return `<section${anchor} class="${sectionClasses}" data-block-id="${escapeAttribute(block.id)}" data-block-type="hero" data-block-variant="${escapeAttribute(variant)}"><div class="uk-container">${content}</div></section>`;
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

  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const header = renderFeatureListHeader(block, context);
  const grid = variant === 'card-stack' ? renderFeatureListCardStack(items) : renderFeatureListTextColumns(items);

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="feature_list" data-block-variant="${escapeAttribute(variant)}"><div class="uk-container">${header}${grid}</div></section>`;
}

function renderFeatureListDetailedCards(block, options = {}) {
  const context = options?.context || 'frontend';
  const items = normalizeFeatureListItems(block);
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
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

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="feature_list" data-block-variant="detailed-cards"><div class="uk-container">${header}${grid}${footer}</div></section>`;
}

function renderFeatureListGridBullets(block, options = {}) {
  const context = options?.context || 'frontend';
  const items = normalizeFeatureListItems(block);
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
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

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="feature_list" data-block-variant="grid-bullets"><div class="uk-container">${header}${grid}</div></section>`;
}

function normalizeProcessStepsVariant(variant) {
  const mapping = {
    'timeline': 'timeline_vertical',
    'timeline-vertical': 'timeline_vertical',
    'timeline_vertical': 'timeline_vertical',
    'timeline-horizontal': 'timeline_horizontal',
    'timeline_horizontal': 'timeline_horizontal'
  };

  return mapping[variant] || variant;
}

function renderProcessSteps(block, variant, options = {}) {
  const normalizedVariant = normalizeProcessStepsVariant(variant);
  const allowedVariants = new Set(['numbered-vertical', 'numbered-horizontal', 'timeline_vertical', 'timeline_horizontal']);

  if (!allowedVariants.has(normalizedVariant)) {
    throw new Error(`Unsupported process_steps variant: ${variant}`);
  }

  const context = options?.context || 'frontend';

  const steps = Array.isArray(block.data?.steps) ? block.data.steps : [];

  if (!steps.length) {
    throw new Error('process_steps block requires at least one step');
  }

  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
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

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="process_steps" data-block-variant="${escapeAttribute(normalizedVariant)}"><div class="uk-container">${header}${layout}</div></section>`;
}

function renderTestimonialSingle(block) {
  const author = block.data?.author?.name ? ` by ${escapeHtml(block.data.author.name)}` : '';
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="testimonial" data-block-variant="single_quote"><!-- testimonial:single_quote${author} --></section>`;
}

function renderTestimonialWall(block) {
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="testimonial" data-block-variant="quote_wall"><!-- testimonial:quote_wall | grouped quotes --></section>`;
}

function renderRichTextProse(block, options = {}) {
  const context = options?.context || 'frontend';
  const editable = buildEditableAttributes(block, 'data.body', context, { type: 'richtext' });
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="rich_text" data-block-variant="prose"${editable}><!-- rich_text:prose -->${block.data.body || ''}</section>`;
}

function renderInfoMedia(block, variant, options = {}) {
  const allowedVariants = new Set(['stacked', 'image-left', 'image-right']);
  const hasSupportedVariant = allowedVariants.has(variant);
  const context = options?.context || 'frontend';
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const dataAttributes = ` data-block-id="${escapeAttribute(block.id)}" data-block-type="info_media" data-block-variant="${escapeAttribute(hasSupportedVariant ? variant : 'unsupported')}"`;

  if (!hasSupportedVariant) {
    const providedVariant = variant ? `"${escapeHtml(variant)}"` : 'nicht gesetzt';
    const allowed = Array.from(allowedVariants).join(', ');
    const warning = `<div class="uk-alert-warning" role="alert">Unsupported info_media variant ${providedVariant}. Erlaubte Varianten: ${escapeHtml(allowed)}.</div>`;
    return `<section${anchor} class="uk-section uk-section-default"${dataAttributes}"><div class="uk-container">${warning}</div></section>`;
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

  return `<section${anchor} class="uk-section uk-section-default"${dataAttributes}"><div class="uk-container">${grid}</div></section>`;
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
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
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

  return `<section${anchor} class="uk-section uk-section-primary" data-block-id="${escapeAttribute(block.id)}" data-block-type="cta" data-block-variant="full_width"><div class="uk-container">${inner}</div></section>`;
}

function renderCtaSplit(block, options = {}) {
  const context = options?.context || 'frontend';
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
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

  return `<section${anchor} class="uk-section uk-section-primary" data-block-id="${escapeAttribute(block.id)}" data-block-type="cta" data-block-variant="split"><div class="uk-container">${layout}</div></section>`;
}

function renderStatStrip(block) {
  const count = Array.isArray(block.data.metrics) ? block.data.metrics.length : 0;
  return `<section data-block-id="${escapeAttribute(block.id)}" data-block-type="stat_strip" data-block-variant="three-up"><!--stat_strip:three-up | ${count} metrics --></section>`;
}

function renderProofMetricCallout(block, options = {}) {
  const context = options?.context || 'frontend';
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
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

  return `<section${anchor} class="uk-section uk-section-muted" data-block-id="${escapeAttribute(block.id)}" data-block-type="proof" data-block-variant="metric-callout"><div class="uk-container">${header}${metricsGrid}${marquee}</div></section>`;
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
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
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

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="audience_spotlight" data-block-variant="${safeVariant}"><div class="uk-container">${sectionTitle}${sectionSubtitle}${grid}</div></section>`;
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
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
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

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="package_summary" data-block-variant="${safeVariant}"><div class="uk-container">${title}${subtitle}${grid}${disclaimer}</div></section>`;
}

function renderFaqItem(item) {
  const question = `<a class="uk-accordion-title" href="#">${escapeHtml(item.question || '')}</a>`;
  const answer = item.answer ? `<div class="uk-accordion-content"><p class="uk-margin-remove-top">${escapeHtml(item.answer)}</p></div>` : '';
  return `<li>${question}${answer}</li>`;
}

function renderFaq(block, options = {}) {
  const context = options?.context || 'frontend';
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const title = block.data?.title
    ? `<h2 class="uk-heading-medium uk-margin-remove-bottom"${buildEditableAttributes(block, 'data.title', context)}>${escapeHtml(block.data.title)}</h2>`
    : '';
  const items = Array.isArray(block.data?.items) ? block.data.items : [];
  const accordionItems = items.length
    ? items.map(item => renderFaqItem(item)).join('')
    : '<li><div class="uk-alert-warning" role="alert">Keine Fragen hinterlegt.</div></li>';
  const followUpData = block.data?.followUp;
  const followUpLink = followUpData?.href
    ? `<a class="uk-link-heading" href="${escapeAttribute(followUpData.href)}">${escapeHtml(followUpData.linkLabel || followUpData.text || 'Mehr erfahren')}</a>`
    : '';
  const followUpText = followUpData?.text
    ? `<span class="uk-text-muted"${buildEditableAttributes(block, 'data.followUp.text', context)}>${escapeHtml(followUpData.text)}</span>`
    : '';
  const followUp = followUpLink || followUpText
    ? `<div class="uk-margin-medium-top">${[followUpLink, followUpText].filter(Boolean).join(' ')}</div>`
    : '';

  const accordion = `<ul class="uk-accordion" data-uk-accordion>${accordionItems}</ul>`;

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="faq" data-block-variant="accordion"><div class="uk-container">${title}${accordion}${followUp}</div></section>`;
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
  const anchor = block.meta?.anchor ? ` id="${escapeAttribute(block.meta.anchor)}"` : '';
  const title = block.data?.title ? `<h2 class="uk-heading-medium uk-margin-remove-bottom">${escapeHtml(block.data.title)}</h2>` : '';
  const subtitle = block.data?.subtitle ? `<p class="uk-text-lead uk-margin-small-top uk-margin-medium-bottom">${escapeHtml(block.data.subtitle)}</p>` : '';
  const legacyNote = '<p class="uk-text-meta uk-margin-remove-top uk-margin-small-bottom">Legacy module block – consider migrating</p>';

  const items = Array.isArray(block.data?.items) ? block.data.items : [];
  const gridItems = items.length
    ? items.map(item => renderLegacySystemModuleItem(item)).join('')
    : '<div><div class="uk-alert-warning" role="alert">Keine Module hinterlegt.</div></div>';
  const grid = `<div class="uk-grid uk-grid-medium uk-child-width-1-1 uk-child-width-1-2@m" data-uk-grid>${gridItems}</div>`;

  return `<section${anchor} class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}" data-block-type="system_module" data-block-variant="switcher"><div class="uk-container">${legacyNote}${title}${subtitle}${grid}</div></section>`;
}

function renderLegacyCaseShowcase(block, options = {}) {
  return renderAudienceSpotlight({ ...block, type: 'audience_spotlight' }, block.variant, options);
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
    'text-columns': (block, options) => renderFeatureList(block, 'text-columns', options),
    'card-stack': (block, options) => renderFeatureList(block, 'card-stack', options),
    stacked_cards: (block, options) => renderFeatureList(block, 'card-stack', options),
    icon_grid: renderFeatureListGridBullets
  },
  process_steps: {
    'numbered-vertical': (block, options) => renderProcessSteps(block, 'numbered-vertical', options),
    'numbered-horizontal': (block, options) => renderProcessSteps(block, 'numbered-horizontal', options),
    timeline: (block, options) => renderProcessSteps(block, 'timeline', options),
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
    'image-right': (block, options) => renderInfoMedia(block, 'image-right', options)
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
  system_module: {
    switcher: renderLegacySystemModule
  },
  case_showcase: {
    tabs: renderLegacyCaseShowcase
  }
};
