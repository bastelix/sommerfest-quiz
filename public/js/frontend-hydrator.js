const bootstrapHydrationContext = () => {
  const root = document.documentElement;
  if (!root) {
    console.error('[CMS] Missing <html> root');
    return null;
  }

  const pageRoot = document.querySelector('[data-page-root]');
  if (!pageRoot) {
    console.error('[CMS] Missing [data-page-root]');
    return null;
  }

  return {
    root,
    pageRoot,
    namespace: root.dataset.namespace || 'default',
    theme: root.dataset.theme || 'light',
    basePath: root.dataset.basePath || '',
    page: {
      slug: pageRoot.dataset.pageSlug,
      namespace: pageRoot.dataset.pageNamespace,
      contentNamespace: pageRoot.dataset.contentNamespace
    }
  };
};

const buildPayloadUrl = () => {
  if (typeof window === 'undefined') {
    return null;
  }
  try {
    const url = new URL(window.location.href);
    url.searchParams.set('format', 'json');
    return url.toString();
  } catch (error) {
    console.error('[CMS] Failed to build payload URL', error);
    return null;
  }
};

const normalizePagePayload = (payload, sourceLabel) => {
  if (!payload || typeof payload !== 'object') {
    console.error(`[CMS] Invalid ${sourceLabel} payload`);
    return null;
  }

  const blocks = Array.isArray(payload.blocks) ? payload.blocks : [];
  const design = payload.design && typeof payload.design === 'object' ? payload.design : null;
  const namespace = typeof payload.namespace === 'string' ? payload.namespace : null;
  const slug = typeof payload.slug === 'string' ? payload.slug : null;
  const pageType = typeof payload.pageType === 'string'
    ? payload.pageType
    : (typeof payload.type === 'string' ? payload.type : null);
  const sectionStyleDefaults = payload.sectionStyleDefaults && typeof payload.sectionStyleDefaults === 'object'
    ? payload.sectionStyleDefaults
    : {};
  const content = typeof payload.content === 'string' ? payload.content : '';

  if (blocks.length === 0 && content.trim() === '') {
    console.error(`[CMS] Invalid ${sourceLabel} payload – missing blocks and fallback content`);
    return null;
  }

  return { blocks, design, namespace, slug, content, pageType, sectionStyleDefaults };
};

const parseEmbeddedPayload = () => {
  const script = document.querySelector('script[data-json="page"]');
  if (!script) {
    return null;
  }

  const rawPayload = script.textContent?.trim();
  if (!rawPayload) {
    console.error('[CMS] Embedded payload is empty');
    return null;
  }

  try {
    const payload = JSON.parse(rawPayload);
    return normalizePagePayload(payload, 'embedded');
  } catch (error) {
    console.error('[CMS] Failed to parse embedded payload', error);
    return null;
  }
};

const fetchPagePayload = async (fallbackPayload) => {
  const url = buildPayloadUrl();
  if (!url) {
    console.error('[CMS] Missing payload URL');
    return fallbackPayload || parseEmbeddedPayload();
  }

  let response;
  try {
    response = await fetch(url, {
      headers: {
        Accept: 'application/json'
      }
    });
  } catch (error) {
    console.error('[CMS] Payload fetch failed', error);
    return fallbackPayload || parseEmbeddedPayload();
  }

  if (!response.ok) {
    console.error('[CMS] Payload fetch failed', response.status, response.statusText);
    return fallbackPayload || parseEmbeddedPayload();
  }

  try {
    const payload = await response.json();
    return normalizePagePayload(payload, 'remote');
  } catch (error) {
    console.error('[CMS] Failed to parse page payload', error);
    return fallbackPayload || parseEmbeddedPayload();
  }
};

const hydratePage = async () => {
  const ctx = bootstrapHydrationContext();
  if (!ctx) return;

  const { root, pageRoot, basePath } = ctx;

  try {
    if (typeof window !== 'undefined') {
      window.basePath = basePath;
    }

    const embeddedPayload = parseEmbeddedPayload();
    const [designModule, matrixModule, effectsModule, remotePayload] = await Promise.all([
      import(`${basePath}/js/components/namespace-design.js`),
      import(`${basePath}/js/components/block-renderer-matrix.js`),
      import(`${basePath}/js/effects/initEffects.js`),
      fetchPagePayload(embeddedPayload)
    ]);

    const payload = remotePayload || embeddedPayload;
    if (!payload) {
      console.error('[CMS] Missing payload – cannot render page');
      return;
    }

    const designNamespace = payload.design?.namespace || payload.namespace || ctx.namespace;
    const fallbackHtml = typeof payload.content === 'string' ? payload.content : '';
    if (payload.design && designModule?.registerNamespaceDesign) {
      designModule.registerNamespaceDesign(designNamespace, payload.design);
    }

    const appearance = payload.design?.appearance || {};
    const resolvedAppearance = designModule?.applyNamespaceDesign
      ? designModule.applyNamespaceDesign(root, designNamespace, appearance)
      : appearance;

    const pageContext = {
      type: payload.pageType || payload.type,
      sectionStyleDefaults: payload.sectionStyleDefaults || {}
    };

    if (typeof window !== 'undefined') {
      window.pageContext = pageContext;
    }

    const hasBlocks = Array.isArray(payload.blocks) && payload.blocks.length > 0;

    let html = '';
    if (hasBlocks) {
      html = matrixModule.renderPage(payload.blocks, {
        rendererMatrix: matrixModule.RENDERER_MATRIX,
        context: 'frontend',
        appearance: resolvedAppearance || {},
        basePath,
        page: {
          type: payload.pageType || payload.type,
          sectionStyleDefaults: payload.sectionStyleDefaults || {}
        }
      });
    }

    if ((!html || typeof html !== 'string' || html.trim() === '') && fallbackHtml.trim() !== '') {
      html = fallbackHtml;
    }

    if (!html || typeof html !== 'string' || html.trim() === '') {
      console.error('[CMS] Empty render output');
      return;
    }

    pageRoot.innerHTML = html;

    if (effectsModule?.initEffects) {
      effectsModule.initEffects(pageRoot, {
        namespace: designNamespace,
        mode: 'frontend'
      });
    }
  } catch (error) {
    console.error('[CMS] Failed to hydrate page', error);
  }
};

document.addEventListener('DOMContentLoaded', hydratePage);
