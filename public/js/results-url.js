(() => {
  const normalizeBase = (value) => {
    if (typeof value !== 'string') {
      return '';
    }
    const trimmed = value.trim();
    return trimmed.replace(/\/$/, '');
  };

  const normalizeMode = (value, fallback = 'split') => {
    if (value === null || value === undefined) {
      return fallback;
    }
    const normalized = String(value).trim().toLowerCase();
    return normalized === 'hub' ? 'hub' : 'split';
  };

  const resolveResultsViewMode = (cfg, fallback = 'split', override = '') => {
    if (override) {
      return normalizeMode(override, fallback);
    }
    if (cfg && typeof cfg === 'object') {
      const cfgMode = cfg.resultsViewMode ?? cfg.results_view_mode ?? '';
      return normalizeMode(cfgMode, fallback);
    }
    return normalizeMode(fallback, fallback);
  };

  const buildResultsUrl = (cfg, eventUid = '', playerUid = '', options = {}) => {
    const basePath = normalizeBase(
      typeof options.basePath === 'string' ? options.basePath : window.basePath || ''
    );
    const baseUrl = normalizeBase(typeof options.baseUrl === 'string' ? options.baseUrl : '');
    const mode = resolveResultsViewMode(cfg, 'split', options.resultsViewMode || '');
    const destination = mode === 'hub' ? '/results-hub' : '/ranking';
    const params = new URLSearchParams();
    const normalizedEvent = eventUid ? String(eventUid).trim() : '';
    const normalizedPlayer = playerUid ? String(playerUid).trim() : '';
    if (normalizedEvent) {
      params.set('event_uid', normalizedEvent);
    }
    if (normalizedPlayer) {
      params.set('player_uid', normalizedPlayer);
    }
    if (options.forceResults) {
      params.set('results', '1');
    }
    const query = params.toString();
    const path = `${destination}${query ? `?${query}` : ''}`;
    if (baseUrl) {
      return `${baseUrl}${path}`;
    }
    const withBase = `${basePath}${path}`;
    if (options.absolute) {
      try {
        return new URL(withBase, window.location.origin).toString();
      } catch (error) {
        return withBase;
      }
    }
    return withBase;
  };

  window.buildResultsUrl = buildResultsUrl;
  window.resolveResultsViewMode = resolveResultsViewMode;
})();
