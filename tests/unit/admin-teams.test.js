function setupDom() {
  document.body.innerHTML = `
    <input id="teamSearch" type="text">
    <form id="teamSearchForm"></form>
    <ul id="teamsList"></ul>
    <div id="teamsCards"></div>
    <button id="teamAddBtn"></button>
    <button id="teamDeleteAllBtn"></button>
    <button id="teamDeleteAllConfirm"></button>
    <input id="teamRestrict" type="checkbox">
  `;
}

function makeCtx(overrides = {}) {
  return {
    apiFetch: vi.fn().mockResolvedValue({
      ok: true,
      json: async () => [],
    }),
    notify: vi.fn(),
    withBase: (p) => p,
    getCurrentEventUid: () => 'event-123',
    cfgInitial: { QRRestrict: false },
    registerCacheReset: vi.fn(),
    TableManager: vi.fn().mockImplementation(() => ({
      getData: vi.fn().mockReturnValue([]),
      render: vi.fn(),
      setFilter: vi.fn(),
      setColumnLoading: vi.fn(),
      bindPagination: vi.fn(),
      pagination: null,
    })),
    createCellEditor: vi.fn().mockReturnValue({ open: vi.fn() }),
    ...overrides,
  };
}

describe('initTeams', () => {
  beforeEach(() => {
    vi.resetModules();
    setupDom();
    vi.stubGlobal('UIkit', undefined);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('returns loadTeamList function and teamListEl reference', async () => {
    const { initTeams } = await import('../../public/js/admin-teams.js');
    const api = initTeams(makeCtx());
    expect(typeof api.loadTeamList).toBe('function');
    expect(api.teamListEl).toBe(document.getElementById('teamsList'));
  });

  it('registers a cache reset handler during init', async () => {
    const registerCacheReset = vi.fn();
    const { initTeams } = await import('../../public/js/admin-teams.js');
    initTeams(makeCtx({ registerCacheReset }));
    expect(registerCacheReset).toHaveBeenCalledOnce();
  });

  it('creates the delete confirm modal if it does not exist', async () => {
    const { initTeams } = await import('../../public/js/admin-teams.js');
    initTeams(makeCtx());
    expect(document.getElementById('teamDeleteConfirmModal')).not.toBeNull();
    expect(document.getElementById('teamDeleteConfirmText')).not.toBeNull();
  });

  it('does not create a second delete modal on second call', async () => {
    const { initTeams } = await import('../../public/js/admin-teams.js');
    initTeams(makeCtx());
    initTeams(makeCtx());
    const modals = document.querySelectorAll('#teamDeleteConfirmModal');
    expect(modals.length).toBe(1);
  });

  it('loadTeamList calls apiFetch with the event UID in the URL', async () => {
    const apiFetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => [],
    });
    const { initTeams } = await import('../../public/js/admin-teams.js');
    const api = initTeams(makeCtx({ apiFetch }));
    // loadTeamList is also called automatically during init when teamListEl exists,
    // so reset the mock before manually calling it
    apiFetch.mockClear();
    api.loadTeamList();
    expect(apiFetch).toHaveBeenCalledWith(
      expect.stringContaining('event_uid=event-123'),
      expect.any(Object)
    );
  });

  it('loadTeamList omits event_uid param when no event is selected', async () => {
    const apiFetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => [],
    });
    const { initTeams } = await import('../../public/js/admin-teams.js');
    const api = initTeams(makeCtx({
      apiFetch,
      getCurrentEventUid: () => null,
    }));
    apiFetch.mockClear();
    api.loadTeamList();
    const calledUrl = apiFetch.mock.calls[0][0];
    expect(calledUrl).not.toContain('event_uid');
    expect(calledUrl).toBe('/teams.json');
  });
});
