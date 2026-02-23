function setupDom() {
  document.body.innerHTML = `
    <div id="questions"></div>
  `;
}

function makeCtx(overrides = {}) {
  return {
    apiFetch: vi.fn().mockResolvedValue({ ok: true, json: async () => [] }),
    notify: vi.fn(),
    withBase: (p) => p,
    getCurrentEventUid: () => null,
    cfgInitial: {},
    cfgFields: {},
    registerCacheReset: vi.fn(),
    TableManager: vi.fn().mockImplementation(() => ({
      getData: () => [],
      render: vi.fn(),
      setFilter: vi.fn(),
      setColumnLoading: vi.fn(),
      bindPagination: vi.fn(),
    })),
    createCellEditor: vi.fn().mockReturnValue({ open: vi.fn() }),
    appendNamespaceParam: (url) => url,
    transCatalogsFetchError: 'Error',
    transCatalogsForbidden: 'Forbidden',
    commentTextarea: null,
    commentModal: null,
    catalogEditInput: null,
    catalogEditError: null,
    resultsResetModal: null,
    resultsResetConfirm: null,
    commentState: { currentCommentItem: null },
    ...overrides,
  };
}

describe('initCatalog', () => {
  beforeEach(() => {
    vi.resetModules();
    setupDom();
  });

  it('returns the expected public API shape', async () => {
    const { initCatalog } = await import('../../public/js/admin-catalog.js');
    const api = initCatalog(makeCtx());
    expect(typeof api.loadCatalogs).toBe('function');
    expect(typeof api.applyCatalogList).toBe('function');
    expect(typeof api.saveCatalogs).toBe('function');
  });

  it('catSelect is null when #catalogSelect is not in DOM', async () => {
    const { initCatalog } = await import('../../public/js/admin-catalog.js');
    const api = initCatalog(makeCtx());
    expect(api.catSelect).toBeNull();
  });

  it('catSelect references the DOM element when #catalogSelect is present', async () => {
    document.body.innerHTML += '<select id="catalogSelect"></select>';
    const { initCatalog } = await import('../../public/js/admin-catalog.js');
    const api = initCatalog(makeCtx());
    expect(api.catSelect).toBe(document.getElementById('catalogSelect'));
  });

  it('starts with an empty catalogs array', async () => {
    const { initCatalog } = await import('../../public/js/admin-catalog.js');
    const api = initCatalog(makeCtx());
    expect(api.catalogs).toEqual([]);
  });

  it('applyCatalogList populates catalogs from an array', async () => {
    const { initCatalog } = await import('../../public/js/admin-catalog.js');
    const api = initCatalog(makeCtx());
    api.applyCatalogList([{ slug: 'quiz-a', name: 'Quiz A' }]);
    expect(api.catalogs).toHaveLength(1);
    expect(api.catalogs[0].slug).toBe('quiz-a');
    expect(api.catalogs[0].name).toBe('Quiz A');
  });

  it('applyCatalogList uses slug as the id when present', async () => {
    const { initCatalog } = await import('../../public/js/admin-catalog.js');
    const api = initCatalog(makeCtx());
    api.applyCatalogList([{ slug: 'my-quiz' }]);
    expect(api.catalogs[0].id).toBe('my-quiz');
  });

  it('applyCatalogList uses id field when provided', async () => {
    const { initCatalog } = await import('../../public/js/admin-catalog.js');
    const api = initCatalog(makeCtx());
    api.applyCatalogList([{ id: '42', slug: 'quiz-a' }]);
    expect(api.catalogs[0].id).toBe('42');
  });

  it('applyCatalogList with empty array clears catalogs', async () => {
    const { initCatalog } = await import('../../public/js/admin-catalog.js');
    const api = initCatalog(makeCtx());
    api.applyCatalogList([{ slug: 'quiz-a' }]);
    api.applyCatalogList([]);
    expect(api.catalogs).toEqual([]);
  });

  it('applyCatalogList handles multiple items', async () => {
    const { initCatalog } = await import('../../public/js/admin-catalog.js');
    const api = initCatalog(makeCtx());
    api.applyCatalogList([
      { slug: 'quiz-a', name: 'Quiz A' },
      { slug: 'quiz-b', name: 'Quiz B' },
    ]);
    expect(api.catalogs).toHaveLength(2);
    expect(api.catalogs[1].slug).toBe('quiz-b');
  });

  it('calls registerCacheReset during initialization', async () => {
    const registerCacheReset = vi.fn();
    const { initCatalog } = await import('../../public/js/admin-catalog.js');
    initCatalog(makeCtx({ registerCacheReset }));
    expect(registerCacheReset).toHaveBeenCalledOnce();
  });
});
