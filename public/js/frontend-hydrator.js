const bootstrapHydrationContext = () => {
  const root = document.documentElement;
  const { dataset } = root || {};
  const namespace = dataset?.namespace || 'default';
  const basePath = dataset?.basePath || '';
  const blockRoot = document.querySelector('#page-render-target')
    || document.querySelector('.marketing-page-content');

  return { root, namespace, basePath, blockRoot };
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
    console.error('Failed to build payload URL', error);
    return null;
  }
};

const fetchPagePayload = async () => {
  const url = buildPayloadUrl();
  if (!url) {
    return null;
  }

  const response = await fetch(url, {
    headers: {
      Accept: 'application/json'
    }
  });

  if (!response.ok) {
    console.error('Failed to fetch page payload', response.status, response.statusText);
    return null;
  }

  try {
    const payload = await response.json();
    if (!payload || typeof payload !== 'object') {
      return null;
    }

    const blocks = Array.isArray(payload.blocks) ? payload.blocks : [];
    const design = payload.design && typeof payload.design === 'object' ? payload.design : null;
    const namespace = typeof payload.namespace === 'string' ? payload.namespace : null;
    const slug = typeof payload.slug === 'string' ? payload.slug : null;

    return { blocks, design, namespace, slug };
  } catch (error) {
    console.error('Failed to parse page payload', error);
    return null;
  }
};

const hydratePage = async () => {
  const { root, namespace, basePath, blockRoot } = bootstrapHydrationContext();
  if (!root || !blockRoot) {
    return;
  }

  try {
    if (typeof window !== 'undefined') {
      window.basePath = basePath;
    }

    const [designModule, matrixModule, effectsModule, payload] = await Promise.all([
      import(`${basePath}/js/components/namespace-design.js`),
      import(`${basePath}/js/components/block-renderer-matrix.js`),
      import(`${basePath}/js/effects/initEffects.js`),
      fetchPagePayload()
    ]);

    if (!payload) {
      console.error('Marketing payload missing – cannot render page');
      return;
    }

    const designNamespace = payload.design?.namespace || payload.namespace || namespace;
    if (payload.design && designModule?.registerNamespaceDesign) {
      designModule.registerNamespaceDesign(designNamespace, payload.design);
    }

    const resolvedAppearance = designModule?.applyNamespaceDesign
      ? designModule.applyNamespaceDesign(root, designNamespace, payload.design?.appearance || {})
      : payload.design?.appearance || {};

    if (!Array.isArray(payload.blocks) || payload.blocks.length === 0) {
      console.error('Marketing payload contains no blocks');
    }

    const html = matrixModule.renderPage(payload.blocks, {
      rendererMatrix: matrixModule.RENDERER_MATRIX,
      context: 'frontend',
      appearance: resolvedAppearance,
      basePath
    });

    if (!html || typeof html !== 'string' || html.trim() === '') {
      console.error('renderPage returned empty markup for marketing payload');
    }
    blockRoot.innerHTML = html;

    if (effectsModule?.initEffects) {
      effectsModule.initEffects(blockRoot, { namespace, mode: 'frontend' });
    }
  } catch (error) {
    console.error('Failed to hydrate page', error);
  }
};

hydratePage();
