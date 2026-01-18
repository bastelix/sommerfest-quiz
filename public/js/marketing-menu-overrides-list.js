const overridesList = document.querySelector('[data-navigation-overrides-list]');

if (overridesList) {
  (() => {
    const localeFilter = overridesList.querySelector('[data-override-locale-filter]');
    const headerFilter = overridesList.querySelector('[data-override-header-filter]');
    const footerFilter = overridesList.querySelector('[data-override-footer-filter]');
    const sortSelect = overridesList.querySelector('[data-override-sort]');
    const tbody = overridesList.querySelector('[data-override-rows]');
    const emptyRow = overridesList.querySelector('[data-override-empty]');

    if (!tbody) {
      return;
    }

    const rows = Array.from(tbody.querySelectorAll('[data-override-row]'));

    const parseLocales = value =>
      String(value || '')
        .split(',')
        .map(locale => locale.trim())
        .filter(Boolean);

    const normalize = value => String(value || '').trim().toLowerCase();

    const getRowData = row => {
      const headerLocales = parseLocales(row.dataset.headerLocales);
      const footerLocales = parseLocales(row.dataset.footerLocales);
      return {
        title: normalize(row.dataset.pageTitle || ''),
        updated: Date.parse(row.dataset.pageUpdated || '') || 0,
        headerOverride: row.dataset.headerOverride === '1',
        footerOverride: row.dataset.footerOverride === '1',
        headerLocales,
        footerLocales
      };
    };

    const applyFilters = () => {
      const selectedLocale = normalize(localeFilter?.value || '');
      const headerMode = normalize(headerFilter?.value || '');
      const footerMode = normalize(footerFilter?.value || '');
      let visibleCount = 0;

      rows.forEach(row => {
        const data = getRowData(row);
        const headerOverrideForLocale = selectedLocale
          ? data.headerLocales.map(normalize).includes(selectedLocale)
          : data.headerOverride;
        const footerOverrideForLocale = selectedLocale
          ? data.footerLocales.map(normalize).includes(selectedLocale)
          : data.footerOverride;

        let visible = true;

        if (headerMode === 'override' && !headerOverrideForLocale) {
          visible = false;
        }
        if (headerMode === 'standard' && headerOverrideForLocale) {
          visible = false;
        }
        if (footerMode === 'override' && !footerOverrideForLocale) {
          visible = false;
        }
        if (footerMode === 'standard' && footerOverrideForLocale) {
          visible = false;
        }

        if (selectedLocale && !headerMode && !footerMode) {
          if (!headerOverrideForLocale && !footerOverrideForLocale) {
            visible = false;
          }
        }

        row.hidden = !visible;
        if (visible) {
          visibleCount += 1;
        }
      });

      if (emptyRow) {
        emptyRow.hidden = visibleCount > 0;
      }
    };

    const applySorting = () => {
      const sortMode = normalize(sortSelect?.value || 'title');
      const sorted = [...rows].sort((a, b) => {
        const first = getRowData(a);
        const second = getRowData(b);
        if (sortMode === 'updated') {
          return second.updated - first.updated;
        }
        return first.title.localeCompare(second.title, 'de');
      });
      sorted.forEach(row => tbody.appendChild(row));
    };

    const refresh = () => {
      applyFilters();
      applySorting();
    };

    localeFilter?.addEventListener('change', refresh);
    headerFilter?.addEventListener('change', refresh);
    footerFilter?.addEventListener('change', refresh);
    sortSelect?.addEventListener('change', refresh);

    refresh();
  })();
}
