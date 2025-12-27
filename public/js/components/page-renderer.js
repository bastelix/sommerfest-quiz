const DEFAULT_OPTIONS = {
  mode: 'frontend',
  highlightBlockId: null,
  resolveAssetUrl: null
};

const ALIGNMENT_MAP = {
  start: 'left',
  center: 'center',
  end: 'right'
};

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

function resolveImageSrc(imageId, context) {
  if (!imageId) {
    return '';
  }
  if (typeof context.resolveAssetUrl === 'function') {
    return context.resolveAssetUrl(imageId) || '';
  }
  return imageId;
}

function wrapPreview(block, innerHtml, context) {
  if (context.mode !== 'preview') {
    return innerHtml;
  }
  const highlight = context.highlightBlockId && context.highlightBlockId === block.id;
  const styles = [
    'outline: 1px dashed #dfe3e8',
    'border-radius: 6px',
    'padding: 12px',
    'margin-bottom: 16px',
    'background: rgba(255,255,255,0.8)'
  ];
  if (highlight) {
    styles.push('box-shadow: 0 0 0 2px #1e87f0');
  }
  return `<div class="page-renderer-preview-block" data-block-id="${escapeAttribute(block.id)}" style="${styles.join('; ')}">${innerHtml}</div>`;
}

export function renderHero(block, context) {
  const data = block?.data || {};
  const media = data.media || {};
  const cta = data.cta || {};
  const imageSrc = resolveImageSrc(media.imageId, context);
  const focal = media.focalPoint || {};
  const focalStyle = typeof focal.x === 'number' && typeof focal.y === 'number'
    ? `object-position: ${Math.round(focal.x * 100)}% ${Math.round(focal.y * 100)}%;`
    : '';

  const ctaClass = cta.style === 'secondary' ? 'uk-button-default' : 'uk-button-primary';
  const hasCta = cta.label && cta.href;

  const heroContent = `
<section class="uk-section uk-section-large uk-section-muted" data-block-id="${escapeAttribute(block.id)}">
  <div class="uk-container">
    <div class="uk-grid-large uk-flex-middle" uk-grid>
      <div class="uk-width-1-1 uk-width-1-2@m">
        ${data.eyebrow ? `<p class="uk-text-meta uk-margin-remove-bottom">${escapeHtml(data.eyebrow)}</p>` : ''}
        ${data.headline ? `<h1 class="uk-heading-medium uk-margin-small-top">${data.headline}</h1>` : ''}
        ${data.subheadline ? `<div class="uk-text-lead uk-margin-small-top">${data.subheadline}</div>` : ''}
        ${hasCta ? `<div class="uk-margin-medium-top"><a class="uk-button ${ctaClass}" href="${escapeAttribute(cta.href)}">${escapeHtml(cta.label)}</a></div>` : ''}
      </div>
      ${imageSrc ? `
      <div class="uk-width-1-1 uk-width-1-2@m">
        <div class="uk-card uk-card-default uk-card-body uk-flex uk-flex-center">
          <img src="${escapeAttribute(imageSrc)}" alt="${escapeAttribute(media.alt || '')}" class="uk-border-rounded uk-box-shadow-medium" style="width:100%;height:auto;object-fit:cover;${focalStyle}" loading="lazy" />
        </div>
      </div>
      ` : ''}
    </div>
  </div>
</section>`;

  return wrapPreview(block, heroContent, context);
}

export function renderText(block, context) {
  const data = block?.data || {};
  const alignment = ALIGNMENT_MAP[data.alignment] || 'left';
  const textContent = data.body || '';

  const textHtml = `
<section class="uk-section" data-block-id="${escapeAttribute(block.id)}">
  <div class="uk-container">
    <div class="uk-width-1-1 uk-width-2-3@m uk-align-${alignment}">
      <div class="uk-text-${alignment} uk-article">${textContent}</div>
    </div>
  </div>
</section>`;

  return wrapPreview(block, textHtml, context);
}

export function renderFeatureList(block, context) {
  const data = block?.data || {};
  const items = Array.isArray(data.items) ? data.items : [];
  const layout = data.layout === 'grid' ? 'grid' : 'stacked';
  const gridClass = layout === 'grid' ? 'uk-child-width-1-2@m' : 'uk-child-width-1-1';

  const itemsHtml = items.map(item => {
    const iconHtml = item.icon ? `<span class="uk-margin-small-right" uk-icon="icon: ${escapeAttribute(item.icon)}"></span>` : '';
    return `
      <div>
        <div class="uk-card uk-card-default uk-card-body uk-height-1-1" data-block-id="${escapeAttribute(item.id || '')}">
          ${item.title ? `<h3 class="uk-card-title">${escapeHtml(item.title)}</h3>` : ''}
          ${item.description ? `<div>${item.description}</div>` : ''}
          ${iconHtml ? `<div class="uk-margin-small-top uk-text-primary">${iconHtml}</div>` : ''}
        </div>
      </div>`;
  }).join('');

  const featureList = `
<section class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}">
  <div class="uk-container">
    ${data.title ? `<div class="uk-width-1-1 uk-width-2-3@m"><h2 class="uk-heading-line uk-text-bold"><span>${data.title}</span></h2></div>` : ''}
    <div class="uk-grid-match uk-grid-small ${gridClass}" uk-grid>
      ${itemsHtml}
    </div>
  </div>
</section>`;

  return wrapPreview(block, featureList, context);
}

export function renderTestimonial(block, context) {
  const data = block?.data || {};
  const author = data.author || {};
  const avatarSrc = resolveImageSrc(author.avatarId, context);

  const avatarHtml = avatarSrc
    ? `<div class="uk-width-auto"><img class="uk-border-circle" src="${escapeAttribute(avatarSrc)}" alt="${escapeAttribute(author.name || '')}" width="64" height="64" loading="lazy" /></div>`
    : '';

  const testimonialHtml = `
<section class="uk-section uk-section-default" data-block-id="${escapeAttribute(block.id)}">
  <div class="uk-container">
    <div class="uk-card uk-card-primary uk-card-body">
      <div class="uk-flex uk-flex-middle" uk-grid>
        ${avatarHtml}
        <div class="uk-width-expand">
          ${data.quote ? `<blockquote class="uk-margin-remove">${data.quote}</blockquote>` : ''}
          ${author.name ? `<p class="uk-margin-small-top uk-margin-remove-bottom uk-text-bold">${escapeHtml(author.name)}</p>` : ''}
          ${author.role ? `<p class="uk-margin-remove-top">${escapeHtml(author.role)}</p>` : ''}
        </div>
      </div>
    </div>
  </div>
</section>`;

  return wrapPreview(block, testimonialHtml, context);
}

const RENDERERS = {
  hero: renderHero,
  text: renderText,
  feature_list: renderFeatureList,
  testimonial: renderTestimonial
};

export function renderPage(blocks, options = {}) {
  const normalizedBlocks = Array.isArray(blocks) ? blocks : [];
  const context = { ...DEFAULT_OPTIONS, ...options };
  return normalizedBlocks
    .map(block => {
      const renderer = RENDERERS[block?.type];
      if (!renderer) {
        return '';
      }
      return renderer(block, context);
    })
    .filter(Boolean)
    .join('\n');
}

export const PageRenderer = { renderPage, renderHero, renderText, renderFeatureList, renderTestimonial };
