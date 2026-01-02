const safeParseJson = (value, fallback = {}) => {
  if (!value) {
    return fallback;
  }
  try {
    const parsed = JSON.parse(value);
    return parsed && typeof parsed === 'object' ? parsed : fallback;
  } catch (error) {
    console.error('Failed to parse JSON payload', error);
    return fallback;
  }
};

const parseBlocks = raw => {
  if (!raw) {
    return [];
  }
  try {
    const parsed = JSON.parse(raw);
    if (Array.isArray(parsed)) {
      return parsed;
    }
    if (parsed && typeof parsed === 'object' && Array.isArray(parsed.blocks)) {
      return parsed.blocks;
    }
  } catch (error) {
    console.error('Failed to parse page blocks', error);
  }
  return [];
};

const bootstrapHydrationContext = () => {
  const root = document.documentElement;
  const { dataset } = root || {};
  const namespace = dataset?.namespace || 'default';
  const basePath = dataset?.basePath || '';
  const appearance = safeParseJson(dataset?.appearance, {});
  const design = safeParseJson(dataset?.namespaceDesign, null);
  const blockRoot = document.querySelector('[data-page-blocks]');

  return { root, namespace, basePath, appearance, design, blockRoot };
};

const registerDesignPayload = async (design, namespace, basePath) => {
  if (!design) {
    return null;
  }
  try {
    const module = await import(`${basePath}/js/components/namespace-design.js`);
    if (module?.registerNamespaceDesign) {
      const targetNamespace = design.namespace || namespace;
      module.registerNamespaceDesign(targetNamespace, design);
      if (targetNamespace !== namespace) {
        module.registerNamespaceDesign(namespace, design);
      }
    }
    return module;
  } catch (error) {
    console.error('Failed to register namespace design', error);
    return null;
  }
};

const hydratePage = async () => {
  const { root, namespace, basePath, appearance, design, blockRoot } = bootstrapHydrationContext();
  if (!root) {
    return;
  }

  try {
    if (typeof window !== 'undefined') {
      window.basePath = basePath;
    }

    const designModule = await registerDesignPayload(design, namespace, basePath) || await import(`${basePath}/js/components/namespace-design.js`);
    const matrixModule = await import(`${basePath}/js/components/block-renderer-matrix.js`);
    const effectsModule = await import(`${basePath}/js/effects/initEffects.js`);

    const resolvedAppearance = designModule?.resolveNamespaceAppearance
      ? designModule.resolveNamespaceAppearance(namespace, appearance)
      : appearance;

    if (designModule?.applyNamespaceDesign) {
      designModule.applyNamespaceDesign(root, namespace, resolvedAppearance);
    }

    if (blockRoot && blockRoot.dataset.pageBlocks) {
      const blocks = parseBlocks(blockRoot.dataset.pageBlocks);
      const html = matrixModule.renderPage(Array.isArray(blocks) ? blocks : [], {
        rendererMatrix: matrixModule.RENDERER_MATRIX,
        context: 'frontend',
        appearance: resolvedAppearance,
        basePath,
      });
      blockRoot.innerHTML = html;
      if (effectsModule?.initEffects) {
        effectsModule.initEffects(blockRoot, { namespace, mode: 'frontend' });
      }
    }
  } catch (error) {
    console.error('Failed to hydrate page', error);
  }
};

hydratePage();
