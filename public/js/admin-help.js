/* global UIkit, Stripe */

import { applyLazyImage } from './lazy-images.js';

/**
 * Initialise the Help-Sidebar / QR-Design / Summary / Tab-Navigation /
 * Event-Changed / Profile / Payment / Newsletter / Namespace sections.
 *
 * @param {object} ctx – dependencies injected from admin.js
 * @returns {{ loadSummary: Function }}
 */
export function initHelp(ctx) {
  const {
    apiFetch,
    withBase,
    basePath,
    isAllowed,
    escape,
    registerCacheReset,
    isCurrentEpoch,
    TableManager,
    createCellEditor,
    appendNamespaceParam,
    replaceInitialConfig,
    updateDashboardShareLinks,
    renderCfg,
    loadCatalogs,
    applyCatalogList,
    bindTeamPrintButtons,
    collectRagChatPayload,
    renderRagChatSettings,
    buildMarketingNewsletterPath,
    labelFromSlug,
    notify,
    cfgInitial,
    settingsInitial,
    ragChatSecretPlaceholder,
    transRagChatSaved,
    transRagChatSaveError,
    adminTabs,
    adminMenu,
    adminNav,
    adminMenuToggle,
    adminRoutes,
    ragChatFields,
    catSelect,
    teamsApi,
    eventsApi,
    refreshTenantList,
    profileForm,
    profileSaveBtn,
    welcomeMailBtn,
    planSelect,
    emailInput,
    checkoutContainer,
    marketingNewsletterSection,
    marketingNewsletterSlugInput,
    marketingNewsletterTableBody,
    marketingNewsletterTable,
    marketingNewsletterSlugOptions,
    marketingNewsletterAddBtn,
    marketingNewsletterSaveBtn,
    marketingNewsletterResetBtn,
    marketingNewsletterData,
    marketingNewsletterSlugs,
    marketingNewsletterStyles,
    marketingNewsletterStyleLabels,
    transMarketingNewsletterSaved,
    transMarketingNewsletterError,
    transMarketingNewsletterInvalidSlug,
    transMarketingNewsletterRemove,
    transMarketingNewsletterEmpty,
    state,
  } = ctx;

  const {
    populateEventSelectors,
    renderCurrentEventIndicator,
    updateEventButtons,
    highlightCurrentEvent,
    updateActiveHeader,
    eventDependentSections,
    eventSettingsHeading,
    catalogsHeading,
    questionsHeading,
  } = eventsApi;

  /* ---- mutable state accessed via getter / setter ---- */
  const getCurrentEventUid = () => state.currentEventUid;
  const setCurrentEventUid = v => { state.currentEventUid = v; };
  const getCurrentEventName = () => state.currentEventName;
  const setCurrentEventName = v => { state.currentEventName = v; };
  const getCurrentEventSlug = () => state.currentEventSlug;
  const setCurrentEventSlug = v => { state.currentEventSlug = v; };
  const getAvailableEvents = () => state.availableEvents;

  // --------- Hilfe-Seitenleiste ---------
  const helpButtons = document.querySelectorAll('.help-toggle');
  const helpSidebar = document.getElementById('helpSidebar');
  const helpContent = document.getElementById('helpContent');
  const qrDesignModal = document.getElementById('qrDesignModal');
  const qrLabelInput = document.getElementById('qrLabelInput');
  const qrPunchoutInput = document.getElementById('qrPunchoutInput');
  const qrRoundModeSelect = document.getElementById('qrRoundModeSelect');
  const qrColorInput = document.getElementById('qrColorInput');
  const qrBgColorInput = document.getElementById('qrBgColorInput');
  const qrSizeInput = document.getElementById('qrSizeInput');
  const qrMarginInput = document.getElementById('qrMarginInput');
  const qrEcSelect = document.getElementById('qrEcSelect');
  const qrRoundedInput = document.getElementById('qrRoundedInput');
  const qrLogoWidthInput = document.getElementById('qrLogoWidthInput');
  const qrPreview = document.getElementById('qrDesignPreview');
  const qrApplyBtn = document.getElementById('qrDesignApply');
  const qrLogoFile = document.getElementById('qrLogoFile');
  let qrLogoPath = '';
  const designAllBtn = document.getElementById('summaryDesignAllBtn');
  let currentQrImg = null;
  let currentQrEndpoint = '';
  let currentQrTarget = '';
  let isGlobalDesign = false;

  designAllBtn?.addEventListener('click', () => {
    const eventQr = document.getElementById('summaryEventQr');
    let target = eventQr?.dataset.target;
    if (!target) {
      const src = eventQr?.getAttribute('src') || '';
      try {
        const url = new URL(src, window.location.origin);
        target = url.searchParams.get('t') || '';
      } catch (_) {
        target = '';
      }
    }
    if (target) {
      openQrDesignModal(null, '/qr/event', target, '', true);
    }
  });

  function updateQrPreview() {
    if (!currentQrEndpoint) return;
    const currentEventUid = getCurrentEventUid();
    const params = new URLSearchParams();
    params.set('t', currentQrTarget);
    if (currentQrEndpoint === '/qr/event' && currentEventUid) {
      params.set('event', currentEventUid);
    }
    const label = qrLabelInput?.value || '';
    const lines = label.split(/\n/, 2).map(s => s.trim());
    if (qrLogoPath) {
      params.set('logo_path', qrLogoPath);
    } else {
      if (lines[0]) params.set('text1', lines[0]);
      if (lines[1]) params.set('text2', lines[1]);
    }
    const color = qrColorInput?.value ? qrColorInput.value.replace('#', '') : '';
    if (color) params.set('fg', color);
    const bg = qrBgColorInput?.value ? qrBgColorInput.value.replace('#', '') : '';
    if (bg) params.set('bg', bg);
    const size = qrSizeInput?.value || '';
    if (size) params.set('size', size);
    const margin = qrMarginInput?.value || '';
    if (margin) params.set('margin', margin);
    const ec = qrEcSelect?.value || '';
    if (ec) params.set('ec', ec);
    const w = qrLogoWidthInput?.value || '';
    if (w) params.set('logo_width', w);
    const rounded = qrRoundedInput?.checked !== false;
    const roundMode = rounded ? (qrRoundModeSelect?.value || 'margin') : 'none';
    params.set('round_mode', roundMode);
    params.set('rounded', rounded ? '1' : '0');
    params.set('logo_punchout', qrPunchoutInput?.checked ? '1' : '0');
    if (qrPreview) qrPreview.src = withBase(currentQrEndpoint + '?' + params.toString());
  }

  function openQrDesignModal(img, endpoint, target, label, global = false) {
    currentQrImg = global ? null : img;
    currentQrEndpoint = endpoint;
    currentQrTarget = target;
    isGlobalDesign = global;
    if (qrLabelInput) {
      if (global) {
        const l1 = cfgInitial.qrLabelLine1 || '';
        const l2 = cfgInitial.qrLabelLine2 || '';
        qrLabelInput.value = l2 ? l1 + '\n' + l2 : l1;
      } else {
        qrLabelInput.value = label || '';
      }
    }
    if (qrPunchoutInput) {
      qrPunchoutInput.checked = global ? cfgInitial.qrLogoPunchout !== false : true;
    }
    if (qrColorInput) {
      const field = endpoint === '/qr/team' ? 'qrColorTeam'
        : endpoint === '/qr/catalog' ? 'qrColorCatalog'
        : 'qrColorEvent';
      let val = cfgInitial[field] || '';
      if (!val) {
        val = endpoint === '/qr/team' ? '#004bc8'
          : endpoint === '/qr/catalog' ? '#dc0000'
          : '#00a65a';
      }
      qrColorInput.value = val;
    }
    if (qrBgColorInput) {
      qrBgColorInput.value = cfgInitial.qrBgColor || '#ffffff';
    }
    if (qrSizeInput) {
      qrSizeInput.value = cfgInitial.qrSize || '360';
    }
    if (qrMarginInput) {
      qrMarginInput.value = cfgInitial.qrMargin || '20';
    }
    if (qrEcSelect) {
      qrEcSelect.value = cfgInitial.qrEc || 'medium';
    }
    if (qrRoundedInput) {
      qrRoundedInput.checked = cfgInitial.qrRounded !== false;
    }
    if (qrRoundModeSelect) {
      const mode = cfgInitial.qrRoundMode || 'margin';
      qrRoundModeSelect.value = cfgInitial.qrRounded === false ? 'none' : mode;
    }
    if (qrLogoWidthInput) {
      qrLogoWidthInput.value = global ? (cfgInitial.qrLogoWidth || '') : '';
    }
    qrLogoPath = global ? (cfgInitial.qrLogoPath || '') : '';
    if (qrLogoFile) qrLogoFile.value = '';
    updateQrPreview();
    if (qrDesignModal && window.UIkit) UIkit.modal(qrDesignModal).show();
  }

  [
    qrLabelInput,
    qrPunchoutInput,
    qrRoundModeSelect,
    qrColorInput,
    qrBgColorInput,
    qrSizeInput,
    qrMarginInput,
    qrEcSelect,
    qrRoundedInput,
    qrLogoWidthInput,
  ].forEach(el => {
    el?.addEventListener('input', updateQrPreview);
    el?.addEventListener('change', updateQrPreview);
  });

  qrLogoFile?.addEventListener('change', () => {
    const currentEventUid = getCurrentEventUid();
    const file = qrLogoFile.files && qrLogoFile.files[0];
    if (!file) return;
    const ext = file.type === 'image/webp' ? 'webp' : 'png';
    const fd = new FormData();
    fd.append('file', file);
    const uploadPath = '/qrlogo.' + ext + (currentEventUid ? `?event_uid=${encodeURIComponent(currentEventUid)}` : '');
    apiFetch(uploadPath, { method: 'POST', body: fd })
      .then(() => {
        const cfgPath = currentEventUid ? `/events/${currentEventUid}/config.json` : '/config.json';
        return apiFetch(cfgPath, { headers: { 'Accept': 'application/json' } });
      })
      .then(r => r.json())
      .then(cfg => {
        qrLogoPath = cfg.qrLogoPath || '';
        cfgInitial.qrLogoPath = qrLogoPath;
        if (typeof cfg.qrLogoWidth !== 'undefined') {
          cfgInitial.qrLogoWidth = cfg.qrLogoWidth;
          if (qrLogoWidthInput) qrLogoWidthInput.value = cfg.qrLogoWidth || '';
        }
        updateQrPreview();
      })
      .catch(() => {});
  });

  qrApplyBtn?.addEventListener('click', () => {
    const currentEventUid = getCurrentEventUid();
    const colorVal = qrColorInput?.value || '';
    const bgVal = qrBgColorInput?.value || '';
    const sizeVal = qrSizeInput?.value || '';
    const marginVal = qrMarginInput?.value || '';
    const ecVal = qrEcSelect?.value || '';
    const rounded = qrRoundedInput?.checked !== false;
    const roundMode = rounded ? (qrRoundModeSelect?.value || 'margin') : 'none';
    const punchout = qrPunchoutInput?.checked ? '1' : '0';
    const logoWidthVal = qrLogoWidthInput?.value || '';
    const field = currentQrEndpoint === '/qr/team' ? 'qrColorTeam'
      : currentQrEndpoint === '/qr/catalog' ? 'qrColorCatalog'
      : 'qrColorEvent';
    if (isGlobalDesign) {
      document.querySelectorAll('.qr-img').forEach(img => {
        const endpoint = img.dataset.endpoint;
        const target = img.dataset.target;
        if (!endpoint || !target) {
          console.warn('Skipping QR image without endpoint/target', img);
          return;
        }
        const params = new URLSearchParams();
        params.set('t', target);
        if (endpoint === '/qr/event' && currentEventUid) {
          params.set('event', currentEventUid);
        }
        if (qrLogoPath) {
          params.set('logo_path', qrLogoPath);
        } else {
          const label = img.nextElementSibling?.textContent || '';
          const lns = label.split('\n');
          if (lns[0]) params.set('text1', lns[0]);
          if (lns[1]) params.set('text2', lns[1]);
        }
        if (colorVal) params.set('fg', colorVal.replace('#', ''));
        if (bgVal) params.set('bg', bgVal.replace('#', ''));
        if (sizeVal) params.set('size', sizeVal);
        if (marginVal) params.set('margin', marginVal);
        if (ecVal) params.set('ec', ecVal);
        if (logoWidthVal) params.set('logo_width', logoWidthVal);
        params.set('round_mode', roundMode);
        params.set('rounded', rounded ? '1' : '0');
        params.set('logo_punchout', punchout);
        applyLazyImage(img, withBase(endpoint + '?' + params.toString()), { forceLoad: true });
      });
      const lines = (qrLabelInput?.value || '').split(/\n/, 2);
      const data = {
        qrLabelLine1: lines[0] || '',
        qrLabelLine2: lines[1] || '',
        qrRoundMode: roundMode,
        qrLogoPunchout: punchout === '1',
        qrRounded: rounded,
      };
      if (qrLogoPath) data.qrLogoPath = qrLogoPath;
      data[field] = colorVal;
      if (logoWidthVal) data.qrLogoWidth = parseInt(logoWidthVal, 10);
      const cfgPath = currentEventUid ? `/admin/event/${currentEventUid}` : '/config.json';
      const method = currentEventUid ? 'PATCH' : 'POST';
      apiFetch(cfgPath, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).catch(() => {});
      Object.assign(cfgInitial, data, {
        qrBgColor: bgVal,
        qrSize: sizeVal,
        qrMargin: marginVal,
        qrEc: ecVal,
      });
    } else if (currentQrImg) {
      applyLazyImage(currentQrImg, qrPreview.src, { forceLoad: true });
      const data = { qrRounded: rounded };
      data[field] = colorVal;
      if (logoWidthVal) data.qrLogoWidth = parseInt(logoWidthVal, 10);
      const cfgPath = currentEventUid ? `/admin/event/${currentEventUid}` : '/config.json';
      const method = currentEventUid ? 'PATCH' : 'POST';
      apiFetch(cfgPath, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).catch(() => {});
      Object.assign(cfgInitial, data);
    }
    if (qrDesignModal && window.UIkit) UIkit.modal(qrDesignModal).hide();
  });

  function setQrImage(img, endpoint, target, params, options = {}) {
    if (!img) return;
    img.classList.add('qr-img');
    img.dataset.endpoint = endpoint;
    img.dataset.target = target;
    const url = withBase(endpoint + '?' + params.toString());
    applyLazyImage(img, url, options);
  }

  function clearQrImage(img) {
    if (!img) return;
    img.classList.add('qr-img');
    img.dataset.endpoint = '';
    img.dataset.target = '';
    applyLazyImage(img, null);
  }

  const summaryTeamQrLoadBtn = document.getElementById('summaryTeamQrLoadBtn');
  const summaryTeamQrQueue = [];
  let summaryTeamQrTriggered = false;

  function resetSummaryTeamQrQueue() {
    summaryTeamQrQueue.length = 0;
    summaryTeamQrTriggered = false;
    if (summaryTeamQrLoadBtn) {
      summaryTeamQrLoadBtn.setAttribute('disabled', 'disabled');
    }
  }

  function enqueueSummaryTeamQr(img, endpoint, target, params) {
    if (!img) {
      return;
    }
    if (summaryTeamQrTriggered) {
      setQrImage(img, endpoint, target, params);
      return;
    }
    if (summaryTeamQrLoadBtn) {
      summaryTeamQrLoadBtn.removeAttribute('disabled');
    }
    img.classList.add('qr-img');
    img.dataset.endpoint = endpoint;
    img.dataset.target = target;
    img.dataset.qrParams = params.toString();
    if (img.dataset) {
      delete img.dataset.src;
      delete img.dataset.lazyLoaded;
    }
    if (typeof img.removeAttribute === 'function') {
      img.removeAttribute('src');
    } else {
      img.src = '';
    }
    summaryTeamQrQueue.push(img);
  }

  function flushSummaryTeamQrQueue() {
    if (summaryTeamQrTriggered) {
      return;
    }
    summaryTeamQrTriggered = true;
    const queue = summaryTeamQrQueue.splice(0, summaryTeamQrQueue.length);
    queue.forEach(img => {
      if (!img) {
        return;
      }
      const endpoint = img.dataset.endpoint || '/qr/team';
      const target = img.dataset.target || '';
      const paramStr = img.dataset.qrParams || '';
      const params = paramStr ? new URLSearchParams(paramStr) : new URLSearchParams();
      if (!params.has('t') && target) {
        params.set('t', target);
      }
      setQrImage(img, endpoint, target, params);
    });
    if (summaryTeamQrLoadBtn) {
      summaryTeamQrLoadBtn.setAttribute('disabled', 'disabled');
    }
  }

  summaryTeamQrLoadBtn?.addEventListener('click', e => {
    e.preventDefault();
    flushSummaryTeamQrQueue();
  });

  let summaryRequestId = 0;

  registerCacheReset(() => {
    summaryRequestId += 1;
  });

  function createSummaryPager(options) {
    const {
      container,
      metaEl,
      loadMoreBtn,
      pagerWrapper,
      loadingText,
      emptyText,
      progressText,
      perPage,
      fetchPage,
      renderItems,
      afterRender,
      isActive
    } = options;

    let loading = false;
    let loaded = 0;
    let total = 0;
    let currentPerPage = typeof perPage === 'number' && perPage > 0 ? perPage : 0;

    const ensureActive = () => (typeof isActive === 'function' ? isActive() : true);

    const showWrapper = () => {
      if (pagerWrapper) {
        pagerWrapper.hidden = false;
      }
    };

    const setMetaText = text => {
      if (!metaEl) {
        return;
      }
      const normalized = typeof text === 'string' ? text : '';
      metaEl.textContent = normalized;
      metaEl.hidden = normalized === '';
    };

    const hideMeta = () => {
      if (!metaEl) {
        return;
      }
      metaEl.textContent = '';
      metaEl.hidden = true;
    };

    const hideLoadMore = () => {
      if (!loadMoreBtn) {
        return;
      }
      loadMoreBtn.hidden = true;
      loadMoreBtn.dataset.nextPage = '';
    };

    const showLoadMore = nextPage => {
      if (!loadMoreBtn) {
        return;
      }
      if (nextPage && (!total || loaded < total)) {
        loadMoreBtn.hidden = false;
        loadMoreBtn.dataset.nextPage = String(nextPage);
      } else {
        hideLoadMore();
      }
    };

    const reset = () => {
      if (container) {
        container.innerHTML = '';
      }
      loaded = 0;
      total = 0;
      currentPerPage = typeof perPage === 'number' && perPage > 0 ? perPage : currentPerPage;
      hideLoadMore();
      if (loadMoreBtn) {
        loadMoreBtn.removeAttribute('disabled');
      }
      if (loadingText) {
        showWrapper();
        setMetaText(loadingText);
      } else {
        hideMeta();
        if (pagerWrapper) {
          pagerWrapper.hidden = true;
        }
      }
    };

    async function load(page = 1, append = false) {
      if (!container || loading) {
        return;
      }
      loading = true;
      if (!append) {
        container.innerHTML = '';
        loaded = 0;
      }
      if (loadingText) {
        showWrapper();
        setMetaText(loadingText);
      }
      if (loadMoreBtn) {
        loadMoreBtn.hidden = true;
        loadMoreBtn.dataset.nextPage = '';
        loadMoreBtn.setAttribute('disabled', 'disabled');
      }

      try {
        const data = await fetchPage(page, currentPerPage);
        if (!ensureActive()) {
          return;
        }
        let items = [];
        let pager = null;
        if (Array.isArray(data)) {
          items = data;
        } else if (data && typeof data === 'object') {
          items = Array.isArray(data.items) ? data.items : [];
          pager = data.pager && typeof data.pager === 'object' ? data.pager : null;
        }
        if (!append) {
          container.innerHTML = '';
          loaded = 0;
        }
        if (!items.length && loaded === 0) {
          showWrapper();
          setMetaText(emptyText);
          hideLoadMore();
          return;
        }
        renderItems(items, append);
        loaded += items.length;
        if (pager) {
          if (typeof pager.perPage === 'number' && pager.perPage > 0) {
            currentPerPage = pager.perPage;
          }
          if (typeof pager.total === 'number' && pager.total >= 0) {
            total = pager.total;
          }
          if (!total && typeof pager.count === 'number' && pager.count >= 0) {
            total = pager.count;
          }
          if (!total) {
            total = loaded;
          }
          const hasNext = typeof pager.nextPage === 'number' && pager.nextPage > 0;
          if (hasNext && (!total || loaded < total)) {
            showLoadMore(pager.nextPage);
          } else if (total && loaded < total && currentPerPage > 0) {
            showLoadMore(page + 1);
          } else {
            hideLoadMore();
          }
        } else {
          total = Math.max(total, loaded);
          hideLoadMore();
        }
        showWrapper();
        if (progressText && total && metaEl) {
          const text = progressText
            .replace('%current%', String(Math.min(loaded, total)))
            .replace('%total%', String(total));
          setMetaText(text);
        } else if (!loaded) {
          setMetaText(emptyText);
        } else if (metaEl) {
          hideMeta();
        }
        if (typeof afterRender === 'function') {
          afterRender();
        }
      } catch (err) {
        if (ensureActive()) {
          showWrapper();
          setMetaText(window.transSummaryLoadError || emptyText);
          hideLoadMore();
        }
      } finally {
        if (loadMoreBtn) {
          loadMoreBtn.removeAttribute('disabled');
        }
        loading = false;
      }
    }

    loadMoreBtn?.addEventListener('click', e => {
      e.preventDefault();
      const next = parseInt(loadMoreBtn.dataset.nextPage || '0', 10);
      if (next > 0) {
        load(next, true);
      }
    });

    return {
      load,
      reset
    };
  }

  function loadSummary() {
    const currentEventUid = getCurrentEventUid();
    const nameEl = document.getElementById('summaryEventName');
    const descEl = document.getElementById('summaryEventDesc');
    const qrImg = document.getElementById('summaryEventQr');
    const resultsCodeEl = document.getElementById('summaryResultsUrl');
    const resultsLinkEl = document.getElementById('summaryResultsLink');
    const catalogsEl = document.getElementById('summaryCatalogs');
    const teamsEl = document.getElementById('summaryTeams');
    if (!nameEl || !catalogsEl || !teamsEl) return;

    resetSummaryTeamQrQueue();

    const requestId = ++summaryRequestId;
    const isActive = () => requestId === summaryRequestId;

    const opts = { headers: { 'Accept': 'application/json' } };
    const loadingEventText = window.transSummaryLoadingEvent || '';
    nameEl.textContent = loadingEventText;
    if (descEl) descEl.textContent = '';
    if (qrImg) {
      clearQrImage(qrImg);
      qrImg.hidden = true;
    }

    const catalogsWrapper = document.getElementById('summaryCatalogsPager');
    const teamsWrapper = document.getElementById('summaryTeamsPager');
    const catalogsMeta = document.getElementById('summaryCatalogsMeta');
    const teamsMeta = document.getElementById('summaryTeamsMeta');
    const catalogsMoreBtn = document.getElementById('summaryCatalogsMore');
    const teamsMoreBtn = document.getElementById('summaryTeamsMore');

    const catalogsEmpty = catalogsEl.dataset.emptyText || window.transSummaryNoCatalogs || '';
    const teamsEmpty = teamsEl.dataset.emptyText || window.transSummaryNoTeams || '';
    const catalogsProgress = window.transSummaryCatalogProgress || '';
    const teamsProgress = window.transSummaryTeamProgress || '';
    const catalogsLoading = window.transSummaryLoadingCatalogs || '';
    const teamsLoading = window.transSummaryLoadingTeams || '';

    const parsePageSize = el => {
      const raw = el?.dataset?.summaryPageSize || '';
      const size = parseInt(raw, 10);
      return Number.isFinite(size) && size > 0 ? size : 0;
    };

    const applySummaryDesign = (params, colorKey) => {
      if (cfgInitial.qrLogoPath) {
        params.set('logo_path', cfgInitial.qrLogoPath);
      } else {
        const l1 = cfgInitial.qrLabelLine1 || '';
        const l2 = cfgInitial.qrLabelLine2 || '';
        if (l1) params.set('text1', l1);
        if (l2) params.set('text2', l2);
      }
      if (cfgInitial.qrLogoWidth) {
        params.set('logo_width', String(cfgInitial.qrLogoWidth));
      }
      const rounded = cfgInitial.qrRounded !== false;
      const roundMode = rounded ? (cfgInitial.qrRoundMode || 'margin') : 'none';
      params.set('round_mode', roundMode);
      params.set('rounded', rounded ? '1' : '0');
      params.set('logo_punchout', cfgInitial.qrLogoPunchout !== false ? '1' : '0');
      const col = cfgInitial[colorKey] || '';
      if (col) params.set('fg', col.replace('#', ''));
    };

    const catalogPager = createSummaryPager({
      container: catalogsEl,
      metaEl: catalogsMeta,
      loadMoreBtn: catalogsMoreBtn,
      pagerWrapper: catalogsWrapper,
      loadingText: catalogsLoading,
      emptyText: catalogsEmpty,
      progressText: catalogsProgress,
      perPage: parsePageSize(catalogsEl),
      fetchPage: (page, perPage) => {
        const params = new URLSearchParams();
        params.set('page', String(page));
        if (perPage) params.set('per_page', String(perPage));
        return apiFetch(`/kataloge/catalogs.json?${params.toString()}`, opts).then(r => r.json());
      },
      renderItems: items => {
        items.forEach(c => {
          if (!c || typeof c !== 'object') {
            return;
          }
          const slug = typeof c.slug === 'string' ? c.slug : '';
          const wrapper = document.createElement('div');
          wrapper.className = 'uk-width-1-1 uk-width-1-2@s';
          const card = document.createElement('div');
          card.className = 'export-card uk-card qr-card uk-card-body';
          const path = currentEventUid
            ? '/?event=' + encodeURIComponent(currentEventUid) + '&katalog=' + encodeURIComponent(slug)
            : '/?katalog=' + encodeURIComponent(slug);
          const qrLink = window.baseUrl ? window.baseUrl + path : withBase(path);
          const linkEl = document.createElement('a');
          linkEl.href = qrLink;
          linkEl.target = '_blank';
          linkEl.textContent = c.name || '';
          const h4 = document.createElement('h4');
          h4.className = 'uk-card-title';
          h4.appendChild(linkEl);
          const p = document.createElement('p');
          p.textContent = c.description || '';
          const img = document.createElement('img');
          img.alt = 'QR';
          img.width = 96;
          img.height = 96;
          const params = new URLSearchParams();
          params.set('t', qrLink);
          applySummaryDesign(params, 'qrColorCatalog');
          setQrImage(img, '/qr/catalog', qrLink, params);
          const designBtn = document.createElement('button');
          designBtn.className = 'uk-icon-button uk-margin-small-top';
          designBtn.setAttribute('uk-icon', 'icon: paint-bucket');
          designBtn.type = 'button';
          designBtn.addEventListener('click', () => {
            openQrDesignModal(img, '/qr/catalog', qrLink, c.name || '');
          });
          card.appendChild(h4);
          card.appendChild(p);
          card.appendChild(img);
          card.appendChild(designBtn);
          wrapper.appendChild(card);
          catalogsEl.appendChild(wrapper);
        });
      },
      afterRender: () => {},
      isActive
    });
    catalogPager.reset();

    const teamPager = createSummaryPager({
      container: teamsEl,
      metaEl: teamsMeta,
      loadMoreBtn: teamsMoreBtn,
      pagerWrapper: teamsWrapper,
      loadingText: teamsLoading,
      emptyText: teamsEmpty,
      progressText: teamsProgress,
      perPage: parsePageSize(teamsEl),
      fetchPage: (page, perPage) => {
        const params = new URLSearchParams();
        params.set('page', String(page));
        if (perPage) params.set('per_page', String(perPage));
        if (currentEventUid) {
          params.set('event_uid', currentEventUid);
        }
        return apiFetch(`/teams.json?${params.toString()}`, opts).then(r => r.json());
      },
      renderItems: items => {
        items.forEach(teamName => {
          if (typeof teamName !== 'string' || teamName === '') {
            return;
          }
          const wrapper = document.createElement('div');
          wrapper.className = 'uk-width-1-1 uk-width-1-2@s';
          const card = document.createElement('div');
          card.className = 'export-card uk-card qr-card uk-card-body uk-position-relative';
          const btn = document.createElement('button');
          btn.className = 'qr-print-btn uk-icon-button uk-position-top-right';
          btn.setAttribute('data-team', teamName);
          btn.setAttribute('uk-icon', 'icon: print');
          btn.setAttribute('aria-label', 'QR-Code drucken');
          const h4 = document.createElement('h4');
          h4.className = 'uk-card-title';
          h4.textContent = teamName;
          const img = document.createElement('img');
          let link;
          if (currentEventUid) {
            const eventParam = encodeURIComponent(currentEventUid);
            link = window.baseUrl
              ? window.baseUrl + '/?event=' + eventParam + '&t=' + encodeURIComponent(teamName)
              : withBase('/?event=' + eventParam + '&t=' + encodeURIComponent(teamName));
          } else {
            link = window.baseUrl
              ? window.baseUrl + '/?t=' + encodeURIComponent(teamName)
              : withBase('/?t=' + encodeURIComponent(teamName));
          }
          const params = new URLSearchParams();
          params.set('t', link);
          applySummaryDesign(params, 'qrColorTeam');
          img.alt = 'QR';
          img.width = 96;
          img.height = 96;
          enqueueSummaryTeamQr(img, '/qr/team', link, params);
          const designBtn = document.createElement('button');
          designBtn.className = 'uk-icon-button uk-position-top-left';
          designBtn.setAttribute('uk-icon', 'icon: paint-bucket');
          designBtn.type = 'button';
          designBtn.addEventListener('click', () => {
            openQrDesignModal(img, '/qr/team', link, teamName);
          });
          card.appendChild(btn);
          card.appendChild(h4);
          card.appendChild(img);
          card.appendChild(designBtn);
          wrapper.appendChild(card);
          teamsEl.appendChild(wrapper);
        });
        bindTeamPrintButtons(teamsEl);
      },
      afterRender: () => {},
      isActive
    });
    teamPager.reset();

    const cfgPromise = currentEventUid
      ? apiFetch(`/events/${currentEventUid}/config.json`, opts).then(r => r.json()).catch(() => ({}))
      : apiFetch('/config.json', opts).then(r => r.json()).catch(() => ({}));

    Promise.all([
      cfgPromise,
      apiFetch(appendNamespaceParam('/events.json'), opts).then(r => r.json()).catch(() => [])
    ]).then(([cfg, events]) => {
      if (!isActive()) {
        return;
      }
      let currentEventName = getCurrentEventName();
      const availableEvents = getAvailableEvents();
      const nextConfig = (cfg && typeof cfg === 'object') ? cfg : {};
      populateEventSelectors(events);
      const selectableHasEvents = availableEvents.length > 0;
      const previousUid = currentEventUid;
      let ev = events.find(e => e.uid === currentEventUid) || null;
      if (!ev && previousUid) {
        const configClone = replaceInitialConfig({});
        setCurrentEventUid('');
        setCurrentEventName('');
        cfgInitial.event_uid = getCurrentEventUid();
        window.quizConfig = configClone;
        ev = {};
      } else {
        const configClone = replaceInitialConfig(nextConfig);
        if (!ev) {
          setCurrentEventUid('');
          setCurrentEventName('');
          ev = {};
        } else {
          setCurrentEventName(ev.name || currentEventName);
        }
        cfgInitial.event_uid = getCurrentEventUid();
        window.quizConfig = configClone;
      }
      currentEventName = getCurrentEventName();
      const currentEventSlug = getCurrentEventSlug();
      setCurrentEventSlug(ev?.slug || (getCurrentEventUid() ? currentEventSlug : ''));
      updateDashboardShareLinks();
      eventDependentSections.forEach(sec => { sec.hidden = !getCurrentEventUid(); });
      renderCurrentEventIndicator(currentEventName, getCurrentEventUid(), selectableHasEvents);
      updateEventButtons(getCurrentEventUid());
      updateActiveHeader(currentEventName);
      highlightCurrentEvent();
      nameEl.textContent = ev.name || '';
      if (descEl) {
        descEl.textContent = ev.description || '';
      }
      if (qrImg) {
        if (ev.uid) {
          const eventParam = encodeURIComponent(ev.uid);
          qrImg.hidden = false;
          const link = window.baseUrl ? window.baseUrl : withBase('/?event=' + eventParam);
          const params = new URLSearchParams();
          params.set('t', link);
          params.set('event', ev.uid);
          applySummaryDesign(params, 'qrColorEvent');
          setQrImage(qrImg, '/qr/event', link, params);
        } else {
          clearQrImage(qrImg);
          qrImg.hidden = true;
        }
      }
      if (resultsCodeEl && resultsLinkEl && typeof window.buildResultsUrl === 'function') {
        const targetUrl = window.buildResultsUrl(nextConfig, ev.uid || '', '', {
          baseUrl: window.baseUrl || '',
          basePath,
          forceResults: true
        });
        if (targetUrl) {
          resultsCodeEl.textContent = targetUrl;
          resultsLinkEl.href = targetUrl;
        }
      }
      catalogPager.load(1);
      teamPager.load(1);
    }).catch(() => {
      if (!isActive()) {
        return;
      }
      if (catalogsWrapper) {
        catalogsWrapper.hidden = false;
      }
      if (catalogsMeta) {
        catalogsMeta.textContent = window.transSummaryLoadError || catalogsEmpty;
        catalogsMeta.hidden = false;
      }
      if (teamsWrapper) {
        teamsWrapper.hidden = false;
      }
      if (teamsMeta) {
        teamsMeta.textContent = window.transSummaryLoadError || teamsEmpty;
        teamsMeta.hidden = false;
      }
    });
  }



  function activeHelpText() {
    if (!adminTabs) return '';
    const active = adminTabs.querySelector('li.uk-active');
    return active ? active.getAttribute('data-help') || '' : '';
  }

  if (helpButtons.length) {
    helpButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (!helpSidebar || !helpContent) return;
        let text = activeHelpText();
        if (
          !text
          && (
            window.location.pathname.endsWith('/admin/event/settings')
            || window.location.pathname.endsWith('/admin/event/dashboard')
          )
        ) {
          text = window.transEventSettingsHelp || '';
        }
        helpContent.innerHTML = text;
        if (window.UIkit && UIkit.offcanvas) UIkit.offcanvas(helpSidebar).show();
      });
    });
  }

  adminMenuToggle?.addEventListener('click', e => {
    e.preventDefault();
    if (adminNav && window.UIkit && UIkit.offcanvas) UIkit.offcanvas(adminNav).show();
  });

  if (adminMenu && adminTabs) {
    const tabControl = (window.UIkit && UIkit.tab) ? UIkit.tab(adminTabs) : null;
    adminTabs.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', e => {
        e.preventDefault();
        const url = a.dataset.routeUrl;
        if (url && window.history?.replaceState) {
          window.history.replaceState(null, '', url);
        }
      });
    });
    const path = window.location.pathname.replace(basePath + '/admin/', '');
    const initRoute = path === '' ? 'dashboard' : path.replace(/^\/?/, '');
    const summaryIdx = adminRoutes.indexOf('summary');
    const tenantIdx = adminRoutes.indexOf('tenants');
    const initIdx = adminRoutes.indexOf(initRoute);
    if (tabControl && initIdx >= 0) {
      tabControl.show(initIdx);
      if (initRoute === 'summary') {
        loadSummary();
      }
      if (initRoute === 'tenants') {
        refreshTenantList();
      }
    }
    if (tabControl && window.UIkit && UIkit.util) {
      UIkit.util.on(adminTabs, 'shown', (e, tab) => {
        const index = Array.prototype.indexOf.call(adminTabs.children, tab);
        const route = adminRoutes[index];
        if (route) {
          const url = basePath + '/admin/' + route;
          if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', url);
          }
        }
        if (index === summaryIdx) {
          loadSummary();
        }
        if (index === tenantIdx) {
          refreshTenantList();
        }
      });
    }
    if (summaryIdx >= 0) {
      adminTabs.children[summaryIdx]?.addEventListener('click', () => {
        loadSummary();
      });
    }
    if (tenantIdx >= 0) {
      adminTabs.children[tenantIdx]?.addEventListener('click', () => {
        refreshTenantList();
      });
    }
    adminMenu.querySelectorAll('[data-tab]').forEach(item => {
      item.addEventListener('click', e => {
        e.preventDefault();
        const idx = parseInt(item.getAttribute('data-tab'), 10);
        if (!isNaN(idx) && tabControl) {
          tabControl.show(idx);
          const route = adminRoutes[idx];
          if (route && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', basePath + '/admin/' + route);
          }
          if (adminNav && window.UIkit && UIkit.offcanvas) UIkit.offcanvas(adminNav).hide();
          if (idx === summaryIdx) {
            loadSummary();
          }
          if (idx === tenantIdx) {
            refreshTenantList();
          }
        }
      });
    });
  }

    profileSaveBtn?.addEventListener('click', e => {
      e.preventDefault();
      if (!profileForm) return;
      const formData = new FormData(profileForm);
      const data = {};
      formData.forEach((value, key) => { data[key] = value; });
      const allowedPlans = ['free', 'starter', 'standard'];
      const allowedBilling = ['credit'];
      if (!allowedPlans.includes(data.plan)) delete data.plan;
      if (!allowedBilling.includes(data.billing_info)) delete data.billing_info;
      apiFetch('/admin/profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify(window.transProfileSaved || 'Profile saved', 'success');
      }).catch(() => notify(window.transErrorSaveFailed || 'Save failed', 'danger'));
    });

    welcomeMailBtn?.addEventListener('click', e => {
      e.preventDefault();
      apiFetch('/admin/profile/welcome', { method: 'POST' })
        .then(r => {
          if (!r.ok) throw new Error('failed');
          notify(window.transWelcomeMailSent || 'Welcome email sent', 'success');
        })
        .catch(() => notify(window.transErrorSendFailed || 'Send failed', 'danger'));
    });

    planSelect?.addEventListener('change', async () => {
      const plan = planSelect.value;
      const isDemo = window.location.hostname.split('.')[0] === 'demo';
      if (window.domainType === 'main' || isDemo) {
        apiFetch('/admin/subscription/toggle', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ plan })
        })
          .then(r => (r.ok ? r.json() : null))
          .then(data => {
            notify('Plan: ' + (data?.plan || 'none'), 'success');
            window.location.reload();
          })
          .catch(() => notify(window.transErrorGeneric || 'Error', 'danger'));
        return;
      }
      if (!plan) return;
      const payload = { plan, embedded: true };
      if (emailInput) {
        const email = emailInput.value.trim();
        if (email === '') {
          emailInput.classList.add('uk-form-danger');
          emailInput.focus();
          notify(window.transEmailRequired || 'Please enter an email address', 'warning');
          return;
        }
        payload.email = email;
      }
      try {
        const res = await apiFetch('/admin/subscription/checkout', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        let data;
        if (res.ok) {
          data = await res.json();
        } else {
          try {
            data = await res.json();
          } catch (e) {
            data = {};
          }
          let msg = window.transErrorPaymentStart || 'Error starting payment';
          if (data.error) {
            msg += ': ' + data.error;
          }
          if (data.log) {
            msg += '<br><pre>' + data.log + '</pre>';
          }
          notify(msg, 'danger', 0);
          return;
        }
        if ([data.client_secret, data.publishable_key, window.Stripe, checkoutContainer].every(Boolean)) {
          const stripe = Stripe(data.publishable_key);
          const checkout = await stripe.initEmbeddedCheckout({ clientSecret: data.client_secret });
          checkout.mount('#stripe-checkout');
          return;
        }
        if (data.url) {
          if (isAllowed(data.url)) {
            window.location.href = escape(data.url);
          } else {
            console.error('Blocked redirect to untrusted URL:', data.url);
          }
        }
      } catch (e) {
        console.error(e);
        notify(window.transErrorPaymentStart || 'Error starting payment', 'danger', 0);
      }
    });

  function updateHeading(el, name) {
    if (!el) return;
    el.textContent = name ? `${name} – ${el.dataset.title}` : el.dataset.title;
  }

  document.addEventListener('event:changed', e => {
    const currentEventUid = getCurrentEventUid();
    let currentEventName = getCurrentEventName();
    const availableEvents = getAvailableEvents();
    const detail = e.detail || {};
    const { uid, name, config, epoch, pending } = detail;
    if (typeof epoch === 'number' && !isCurrentEpoch(epoch)) {
      return;
    }
    if (pending) {
      setCurrentEventUid('');
      setCurrentEventName('');
      const configClone = replaceInitialConfig({});
      cfgInitial.event_uid = '';
      window.quizConfig = configClone;
      renderCfg(configClone);
      updateActiveHeader('');
      renderCurrentEventIndicator('', '', availableEvents.length > 0);
      updateEventButtons('');
      updateHeading(eventSettingsHeading, '');
      updateHeading(catalogsHeading, '');
      updateHeading(questionsHeading, '');
      eventDependentSections.forEach(sec => { sec.hidden = true; });
      highlightCurrentEvent();
      return;
    }
    setCurrentEventUid(uid || '');
    setCurrentEventName(getCurrentEventUid() ? (name || currentEventName) : '');
    currentEventName = getCurrentEventName();
    const nextConfig = (config && typeof config === 'object') ? config : {};
    const configClone = replaceInitialConfig(nextConfig);
    cfgInitial.event_uid = getCurrentEventUid();
    window.quizConfig = configClone;
    renderCfg(configClone);
    updateActiveHeader(currentEventName);
    renderCurrentEventIndicator(currentEventName, getCurrentEventUid(), availableEvents.length > 0);
    updateEventButtons(getCurrentEventUid());
    updateHeading(eventSettingsHeading, currentEventName);
    updateHeading(catalogsHeading, currentEventName);
    updateHeading(questionsHeading, currentEventName);
    eventDependentSections.forEach(sec => { sec.hidden = !getCurrentEventUid(); });
    highlightCurrentEvent();
    if (catSelect) {
      if (getCurrentEventUid()) {
        loadCatalogs();
      } else {
        applyCatalogList([]);
      }
    }
    if (teamsApi.teamListEl) teamsApi.loadTeamList();
    loadSummary();
  });

  // Page editors are handled in tiptap-pages.js

  ragChatFields.token?.addEventListener('input', () => {
    if (!ragChatFields.tokenClear) return;
    if (ragChatFields.token.value.trim() !== '') {
      ragChatFields.tokenClear.checked = false;
    }
  });

  ragChatFields.tokenClear?.addEventListener('change', () => {
    if (ragChatFields.tokenClear.checked && ragChatFields.token) {
      ragChatFields.token.value = '';
    }
  });

  if (marketingNewsletterSection && marketingNewsletterSlugInput && marketingNewsletterTableBody) {
    const columnCount = marketingNewsletterTable
      ? marketingNewsletterTable.querySelectorAll('thead th').length || 5
      : 5;
    const normalizeNewsletterSlug = value => (typeof value === 'string' ? value.trim().toLowerCase() : '');
    const ensureSlugTracked = slug => {
      if (slug === '') {
        return;
      }
      if (!Object.prototype.hasOwnProperty.call(marketingNewsletterData, slug)) {
        marketingNewsletterData[slug] = [];
      }
      if (!marketingNewsletterSlugs.includes(slug)) {
        marketingNewsletterSlugs.push(slug);
      }
    };
    const refreshSlugOptions = () => {
      if (!marketingNewsletterSlugOptions) {
        return;
      }
      const sorted = Array.from(new Set(marketingNewsletterSlugs.map(normalizeNewsletterSlug)))
        .filter(slug => slug !== '')
        .sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
      marketingNewsletterSlugOptions.innerHTML = '';
      sorted.forEach(slug => {
        const option = document.createElement('option');
        option.value = slug;
        marketingNewsletterSlugOptions.appendChild(option);
      });
    };
    const applyNewsletterPayload = payload => {
      Object.keys(marketingNewsletterData).forEach(key => {
        delete marketingNewsletterData[key];
      });
      marketingNewsletterSlugs.length = 0;
      const items = payload && typeof payload === 'object' ? payload : {};
      Object.entries(items).forEach(([slug, entries]) => {
        const normalizedSlug = normalizeNewsletterSlug(slug);
        if (normalizedSlug === '') {
          return;
        }
        marketingNewsletterData[normalizedSlug] = Array.isArray(entries)
          ? entries.map(item => ({
              label: typeof item.label === 'string' ? item.label : '',
              url: typeof item.url === 'string' ? item.url : '',
              style: typeof item.style === 'string' && item.style !== '' ? item.style : (marketingNewsletterStyles[0] || 'primary')
            }))
          : [];
        if (!marketingNewsletterSlugs.includes(normalizedSlug)) {
          marketingNewsletterSlugs.push(normalizedSlug);
        }
      });
      refreshSlugOptions();
    };
    const loadNewsletterConfigs = () => {
      const path = buildMarketingNewsletterPath();
      return apiFetch(path)
        .then(res => {
          if (!res.ok) {
            throw new Error('load-failed');
          }
          return res.json().catch(() => ({}));
        })
        .then(payload => {
          const items = payload?.items || {};
          applyNewsletterPayload(items);
          if (Array.isArray(payload?.styles) && payload.styles.length) {
            marketingNewsletterStyles.splice(0, marketingNewsletterStyles.length, ...payload.styles);
          }
        });
    };
    const createStyleSelect = selected => {
      const select = document.createElement('select');
      select.className = 'uk-select';
      select.setAttribute('data-newsletter-field', 'style');
      marketingNewsletterStyles.forEach(style => {
        const option = document.createElement('option');
        option.value = style;
        option.textContent = marketingNewsletterStyleLabels[style] || labelFromSlug(style);
        select.appendChild(option);
      });
      select.value = marketingNewsletterStyles.includes(selected) ? selected : (marketingNewsletterStyles[0] || 'primary');

      return select;
    };
    const createNewsletterRow = (entry, index) => {
      const tr = document.createElement('tr');
      tr.dataset.newsletterRow = '1';

      const positionCell = document.createElement('td');
      positionCell.className = 'uk-text-muted';
      positionCell.dataset.newsletterPosition = '1';
      positionCell.textContent = String(index + 1);
      tr.appendChild(positionCell);

      const labelCell = document.createElement('td');
      const labelInput = document.createElement('input');
      labelInput.type = 'text';
      labelInput.className = 'uk-input';
      labelInput.value = entry.label || '';
      labelInput.setAttribute('data-newsletter-field', 'label');
      labelCell.appendChild(labelInput);
      tr.appendChild(labelCell);

      const urlCell = document.createElement('td');
      const urlInput = document.createElement('input');
      urlInput.type = 'text';
      urlInput.className = 'uk-input';
      urlInput.value = entry.url || '';
      urlInput.setAttribute('data-newsletter-field', 'url');
      urlCell.appendChild(urlInput);
      tr.appendChild(urlCell);

      const styleCell = document.createElement('td');
      styleCell.appendChild(createStyleSelect(entry.style));
      tr.appendChild(styleCell);

      const actionsCell = document.createElement('td');
      actionsCell.className = 'uk-text-center';
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'uk-button uk-button-text uk-text-danger';
      removeBtn.textContent = transMarketingNewsletterRemove;
      removeBtn.setAttribute('data-remove-newsletter-row', '1');
      actionsCell.appendChild(removeBtn);
      tr.appendChild(actionsCell);

      return tr;
    };
    const showNewsletterPlaceholder = () => {
      marketingNewsletterTableBody.innerHTML = '';
      const tr = document.createElement('tr');
      tr.dataset.placeholder = '1';
      const td = document.createElement('td');
      td.colSpan = columnCount;
      td.className = 'uk-text-muted';
      td.textContent = transMarketingNewsletterEmpty;
      tr.appendChild(td);
      marketingNewsletterTableBody.appendChild(tr);
    };
    const updateNewsletterPositions = () => {
      Array.from(marketingNewsletterTableBody.querySelectorAll('tr[data-newsletter-row]')).forEach((row, index) => {
        const cell = row.querySelector('[data-newsletter-position]');
        if (cell) {
          cell.textContent = String(index + 1);
        }
      });
    };
    const renderNewsletterRows = slug => {
      const normalizedSlug = normalizeNewsletterSlug(slug);
      marketingNewsletterTableBody.innerHTML = '';
      if (normalizedSlug === '') {
        showNewsletterPlaceholder();
        return;
      }
      const entries = (marketingNewsletterData[normalizedSlug] || []).map(item => ({ ...item }));
      if (entries.length === 0) {
        showNewsletterPlaceholder();
        return;
      }
      entries.forEach((entry, index) => {
        marketingNewsletterTableBody.appendChild(createNewsletterRow(entry, index));
      });
      updateNewsletterPositions();
    };
    const gatherNewsletterEntries = () => {
      return Array.from(marketingNewsletterTableBody.querySelectorAll('tr[data-newsletter-row]')).map(row => {
        const label = row.querySelector('[data-newsletter-field="label"]');
        const url = row.querySelector('[data-newsletter-field="url"]');
        const style = row.querySelector('[data-newsletter-field="style"]');
        return {
          label: label && typeof label.value === 'string' ? label.value.trim() : '',
          url: url && typeof url.value === 'string' ? url.value.trim() : '',
          style: style && typeof style.value === 'string' ? style.value.trim() : ''
        };
      });
    };
    const syncNewsletterEntries = (slug, entries) => {
      const normalizedSlug = normalizeNewsletterSlug(slug);
      if (normalizedSlug === '') {
        return;
      }
      marketingNewsletterData[normalizedSlug] = entries.map(item => ({
        label: item.label || '',
        url: item.url || '',
        style: marketingNewsletterStyles.includes(item.style) ? item.style : (marketingNewsletterStyles[0] || 'primary')
      }));
      ensureSlugTracked(normalizedSlug);
      refreshSlugOptions();
    };

    refreshSlugOptions();
    let initialSlug = normalizeNewsletterSlug(marketingNewsletterSlugInput.value);
    if (initialSlug === '') {
      if (marketingNewsletterSlugs.includes('landing')) {
        initialSlug = 'landing';
      } else if (marketingNewsletterSlugs.length) {
        initialSlug = marketingNewsletterSlugs[0];
      }
      if (initialSlug !== '') {
        marketingNewsletterSlugInput.value = initialSlug;
      }
    }
    renderNewsletterRows(initialSlug);

    const handleSlugChange = () => {
      const slug = normalizeNewsletterSlug(marketingNewsletterSlugInput.value);
      if (slug !== '' && !Object.prototype.hasOwnProperty.call(marketingNewsletterData, slug)) {
        marketingNewsletterData[slug] = [];
      }
      renderNewsletterRows(slug);
    };

    marketingNewsletterSlugInput.addEventListener('change', handleSlugChange);
    marketingNewsletterSlugInput.addEventListener('blur', handleSlugChange);

    marketingNewsletterAddBtn?.addEventListener('click', () => {
      const slug = normalizeNewsletterSlug(marketingNewsletterSlugInput.value);
      if (slug === '') {
        notify(transMarketingNewsletterInvalidSlug, 'warning');
        marketingNewsletterSlugInput.focus();
        return;
      }
      ensureSlugTracked(slug);
      const placeholder = marketingNewsletterTableBody.querySelector('tr[data-placeholder]');
      if (placeholder) {
        placeholder.remove();
      }
      marketingNewsletterTableBody.appendChild(
        createNewsletterRow(
          { label: '', url: '', style: marketingNewsletterStyles[0] || 'primary' },
          marketingNewsletterTableBody.querySelectorAll('tr[data-newsletter-row]').length
        )
      );
      updateNewsletterPositions();
    });

    marketingNewsletterTableBody.addEventListener('click', event => {
      const target = event.target instanceof HTMLElement ? event.target.closest('[data-remove-newsletter-row]') : null;
      if (!target) {
        return;
      }
      const row = target.closest('tr');
      if (row) {
        row.remove();
        if (!marketingNewsletterTableBody.querySelector('tr[data-newsletter-row]')) {
          showNewsletterPlaceholder();
        } else {
          updateNewsletterPositions();
        }
      }
    });

    marketingNewsletterSaveBtn?.addEventListener('click', () => {
      const slug = normalizeNewsletterSlug(marketingNewsletterSlugInput.value);
      if (slug === '') {
        notify(transMarketingNewsletterInvalidSlug, 'warning');
        marketingNewsletterSlugInput.focus();
        return;
      }
      const entries = gatherNewsletterEntries();
      apiFetch(buildMarketingNewsletterPath(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ slug, entries })
      })
        .then(res => {
          if (!res.ok) {
            throw new Error('save-failed');
          }
          syncNewsletterEntries(slug, entries);
          notify(transMarketingNewsletterSaved, 'success');
          renderNewsletterRows(slug);
        })
        .catch(() => {
          notify(transMarketingNewsletterError, 'danger');
        });
    });

    marketingNewsletterResetBtn?.addEventListener('click', () => {
      const slug = normalizeNewsletterSlug(marketingNewsletterSlugInput.value);
      loadNewsletterConfigs()
        .then(() => {
          renderNewsletterRows(slug);
        })
        .catch(() => {
          notify(transMarketingNewsletterError, 'danger');
          renderNewsletterRows(slug);
        });
    });
  }

  const namespaceManager = document.querySelector('[data-namespace-management]');
  if (namespaceManager) {
    const listUrl = namespaceManager.dataset.listUrl || '/admin/namespaces/data';
    const createUrl = namespaceManager.dataset.createUrl || '/admin/namespaces';
    const updateUrlTemplate = namespaceManager.dataset.updateUrl || '/admin/namespaces/{namespace}';
    const deleteUrlTemplate = namespaceManager.dataset.deleteUrl || '/admin/namespaces/{namespace}';
    const defaultNamespace = namespaceManager.dataset.defaultNamespace || 'default';
    const columnCount = Number.parseInt(namespaceManager.dataset.columnCount || '3', 10) || 3;
    const tableBody = namespaceManager.querySelector('[data-namespace-table-body]');
    const cards = document.getElementById('namespaceCards');
    const cardsEmpty = document.getElementById('namespaceCardsEmpty');
    const form = namespaceManager.querySelector('[data-namespace-form]');
    const input = namespaceManager.querySelector('[data-namespace-input]');
    const labelInput = namespaceManager.querySelector('[data-namespace-label-input]');
    const formError = namespaceManager.querySelector('[data-namespace-error]');
    const editModalEl = document.getElementById('namespaceEditModal');
    const editModal = editModalEl ? UIkit.modal(editModalEl) : null;
    const editTitle = editModalEl?.querySelector('[data-namespace-edit-title]') || null;
    const editLabel = editModalEl?.querySelector('[data-namespace-edit-label]') || null;
    const editInput = document.getElementById('namespaceEditInput');
    const editError = editModalEl?.querySelector('[data-namespace-edit-error]') || null;
    const editSave = document.getElementById('namespaceEditSave');
    const editCancel = document.getElementById('namespaceEditCancel');
    const labelDefault = namespaceManager.dataset.labelDefault || 'Default';
    const labelInactive = namespaceManager.dataset.labelInactive || 'Inactive';
    const namespacePattern = namespaceManager.dataset.namespacePattern || '^[a-z0-9][a-z0-9-]*$';
    const namespaceMaxLength = Number.parseInt(namespaceManager.dataset.namespaceMaxLength || '100', 10) || 100;
    const columnNamespace = namespaceManager.dataset.columnNamespace || 'Namespace';
    const columnLabel = namespaceManager.dataset.columnLabel || 'Label';
    const columnStatus = namespaceManager.dataset.columnStatus || 'Status';
    const messages = {
      created: namespaceManager.dataset.messageCreated || 'Namespace created.',
      updated: namespaceManager.dataset.messageUpdated || 'Namespace updated.',
      deleted: namespaceManager.dataset.messageDeleted || 'Namespace deleted.',
      invalid: namespaceManager.dataset.messageInvalid || 'Invalid namespace.',
      invalidEmpty: namespaceManager.dataset.messageInvalidEmpty || 'Please enter a namespace.',
      invalidLength: namespaceManager.dataset.messageInvalidLength || 'Namespace is too long.',
      invalidFormat: namespaceManager.dataset.messageInvalidFormat || 'Namespace format is invalid.',
      duplicate: namespaceManager.dataset.messageDuplicate || 'Namespace exists.',
      notFound: namespaceManager.dataset.messageNotFound || 'Namespace not found.',
      defaultLocked: namespaceManager.dataset.messageDefaultLocked || 'Default namespace cannot be changed.',
      inUse: namespaceManager.dataset.messageInUse || 'Namespace is still in use.',
      tableMissing: namespaceManager.dataset.messageTableMissing || 'Namespaces table is missing.',
      error: namespaceManager.dataset.messageError || 'Action failed.',
      loading: namespaceManager.dataset.textLoading || 'Loading namespaces...',
      empty: namespaceManager.dataset.textEmpty || 'No namespaces configured yet.',
      confirmDelete: namespaceManager.dataset.confirmDelete || 'Delete namespace?'
    };

    const namespaceColumns = [
      { key: 'namespace', label: columnNamespace, editable: true, ariaDesc: columnNamespace },
      { key: 'label', label: columnLabel, editable: true, ariaDesc: columnLabel, render: item => item.label || '-' },
      {
        key: 'status',
        label: columnStatus,
        render: item => {
          if (item.is_default) {
            return labelDefault;
          }
          if (item.is_active === false) {
            return labelInactive;
          }
          return '-';
        }
      }
    ];

    const namespaceTable = new TableManager({
      tbody: tableBody,
      columns: namespaceColumns,
      mobileCards: cards ? { container: cards } : null,
      onEdit: cell => openNamespaceEditor(cell),
      onDelete: id => handleNamespaceDelete(id)
    });

    const namespaceEditState = { id: null, key: null };

    const normalizeNamespace = value => String(value || '').trim().toLowerCase();
    const normalizeLabel = value => {
      const normalized = String(value ?? '').trim();
      return normalized === '' ? null : normalized;
    };
    const namespaceRegex = new RegExp(namespacePattern);
    const resolveErrorMessage = (error) => {
      const candidate = error?.message || '';
      if (typeof candidate === 'string' && candidate.trim().startsWith('{')) {
        try {
          const parsed = JSON.parse(candidate);
          if (parsed && typeof parsed.error === 'string') {
            return parsed.error;
          }
        } catch (_) {}
      }

      return candidate || messages.error;
    };

    const getNamespaceError = value => {
      if (value === '') {
        return messages.invalidEmpty || messages.invalid;
      }
      if (value.length > namespaceMaxLength) {
        return messages.invalidLength || messages.invalid;
      }
      if (!namespaceRegex.test(value)) {
        return messages.invalidFormat || messages.invalid;
      }
      return null;
    };

    const buildUrl = (template, namespace) => template.replace('{namespace}', encodeURIComponent(namespace));

    const showFormError = (element, message) => {
      if (!element) return;
      const target = element === input ? formError : editError;
      if (target) {
        target.textContent = message;
        target.classList.remove('uk-hidden');
      }
      element.classList.add('uk-form-danger');
      element.setAttribute('aria-invalid', 'true');
    };

    const clearFormError = element => {
      if (!element) return;
      const target = element === input ? formError : editError;
      if (target) {
        target.textContent = '';
        target.classList.add('uk-hidden');
      }
      element.classList.remove('uk-form-danger');
      element.removeAttribute('aria-invalid');
    };

    const renderNamespaceMessage = message => {
      if (tableBody) {
        tableBody.innerHTML = '';
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = columnCount;
        td.textContent = message;
        tr.appendChild(td);
        tableBody.appendChild(tr);
      }
      if (cardsEmpty) {
        cardsEmpty.hidden = false;
        if (message) {
          cardsEmpty.textContent = message;
        }
      }
    };

    const clearNamespaceMessage = () => {
      if (cardsEmpty) {
        cardsEmpty.hidden = true;
        cardsEmpty.textContent = cardsEmpty.dataset.errorText || '';
      }
    };

    const parseJsonResponse = res => res
      .json()
      .catch(() => ({}))
      .then(data => {
        if (!res.ok) {
          throw new Error(data?.error || messages.error);
        }
        return data;
      });

    const mapNamespaces = entries => entries
      .map(item => {
        const namespaceValue = normalizeNamespace(item.namespace);
        return {
          id: namespaceValue,
          namespace: namespaceValue,
          label: typeof item.label === 'string' ? item.label : '',
          is_default: Boolean(item.is_default) || namespaceValue === defaultNamespace,
          is_active: item.is_active !== false
        };
      })
      .filter(item => item.namespace);

    const loadNamespaces = () => {
      namespaceTable.setColumnLoading('namespace', true);
      renderNamespaceMessage(messages.loading);
      apiFetch(listUrl)
        .then(parseJsonResponse)
        .then(data => {
          const entries = Array.isArray(data?.namespaces) ? data.namespaces : [];
          const normalized = mapNamespaces(entries);
          if (!normalized.length) {
            namespaceTable.render([]);
            renderNamespaceMessage(messages.empty);
            return;
          }
          clearNamespaceMessage();
          namespaceTable.render(normalized);
        })
        .catch(err => {
          const message = resolveErrorMessage(err);
          const finalMessage = message === messages.error && messages.tableMissing
            ? messages.tableMissing
            : message;
          namespaceTable.render([]);
          renderNamespaceMessage(finalMessage);
        })
        .finally(() => {
          namespaceTable.setColumnLoading('namespace', false);
        });
    };

    const persistNamespaceUpdate = (originalId, next) => {
      const payload = {
        namespace: normalizeNamespace(next.namespace),
        label: normalizeLabel(next.label)
      };
      return apiFetch(buildUrl(updateUrlTemplate, originalId), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(parseJsonResponse)
        .then(() => {
          notify(messages.updated, 'success');
          loadNamespaces();
        })
        .catch(err => {
          const message = resolveErrorMessage(err);
          if (message === messages.defaultLocked || message === messages.inUse) {
            notify(message, 'warning');
          } else {
            notify(message, 'danger');
          }
          throw err;
        });
    };

    const openNamespaceEditor = (cell) => {
      const key = cell?.dataset.key;
      const id = cell?.dataset.id;
      const item = namespaceTable.getData().find(entry => entry.id === id);
      if (!item || !key) {
        return;
      }
      if (!editModal || !editInput) {
        return;
      }
      if (item.is_default) {
        notify(messages.defaultLocked, 'warning');
        return;
      }
      namespaceEditState.id = id;
      namespaceEditState.key = key;
      clearFormError(editInput);
      if (editTitle) {
        editTitle.textContent = key === 'label' ? columnLabel : columnNamespace;
      }
      if (editLabel) {
        editLabel.textContent = key === 'label' ? columnLabel : columnNamespace;
      }
      editInput.maxLength = key === 'namespace' ? namespaceMaxLength : 255;
      editInput.value = item[key] || '';
      editModal.show();
    };

    const resetEditState = () => {
      namespaceEditState.id = null;
      namespaceEditState.key = null;
      clearFormError(editInput);
      if (editInput) {
        editInput.value = '';
      }
    };

    const saveNamespaceEdit = () => {
      const { id, key } = namespaceEditState;
      if (!id || !key || !editInput) {
        return;
      }
      const nextValue = key === 'namespace'
        ? normalizeNamespace(editInput.value)
        : editInput.value.trim();

      if (key === 'namespace') {
        const validationMessage = getNamespaceError(nextValue);
        if (validationMessage) {
          showFormError(editInput, validationMessage);
          notify(validationMessage, 'warning');
          return;
        }
      }

      const current = namespaceTable.getData().find(entry => entry.id === id);
      if (!current) {
        return;
      }

      const updated = { ...current, [key]: key === 'label' ? normalizeLabel(nextValue) : nextValue };
      persistNamespaceUpdate(current.id, updated)
        .then(() => {
          resetEditState();
          if (editModal) {
            editModal.hide();
          }
        })
        .catch(() => {});
    };

    const handleNamespaceDelete = id => {
      const item = namespaceTable.getData().find(entry => entry.id === id);
      if (!item) return;
      if (item.is_default) {
        notify(messages.defaultLocked, 'warning');
        return;
      }
      if (!window.confirm(messages.confirmDelete)) {
        return;
      }
      apiFetch(buildUrl(deleteUrlTemplate, item.id), { method: 'DELETE' })
        .then(parseJsonResponse)
        .then(() => {
          notify(messages.deleted, 'success');
          loadNamespaces();
        })
        .catch(err => {
          const message = resolveErrorMessage(err);
          if (message === messages.defaultLocked || message === messages.inUse) {
            notify(message, 'warning');
          } else {
            notify(message, 'danger');
          }
        });
    };

    if (form && input) {
      input.maxLength = namespaceMaxLength;
      form.addEventListener('submit', event => {
        event.preventDefault();
        const value = normalizeNamespace(input.value);
        const labelValue = normalizeLabel(labelInput?.value);
        const validationMessage = getNamespaceError(value);
        if (validationMessage) {
          showFormError(input, validationMessage);
          notify(validationMessage, 'warning');
          input.focus();
          return;
        }
        clearFormError(input);
        input.disabled = true;
        if (labelInput) {
          labelInput.disabled = true;
        }

        apiFetch(createUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ namespace: value, label: labelValue })
        })
          .then(parseJsonResponse)
          .then(() => {
            notify(messages.created, 'success');
            input.value = '';
            if (labelInput) {
              labelInput.value = '';
            }
            loadNamespaces();
          })
          .catch(err => {
            const message = resolveErrorMessage(err);
            if (message === messages.duplicate) {
              notify(messages.duplicate, 'warning');
            } else {
              notify(message, 'danger');
            }
          })
          .finally(() => {
            input.disabled = false;
            if (labelInput) {
              labelInput.disabled = false;
            }
          });
      });

      input.addEventListener('input', () => {
        clearFormError(input);
      });
    }

    editSave?.addEventListener('click', saveNamespaceEdit);
    editCancel?.addEventListener('click', () => {
      resetEditState();
      clearFormError(editInput);
    });

    loadNamespaces();
  }

  ragChatFields.form?.addEventListener('submit', event => {
    event.preventDefault();
    const payload = collectRagChatPayload();

    apiFetch('/settings.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(res => {
        if (!res.ok) {
          throw new Error('save-failed');
        }
      })
      .then(() => {
        Object.assign(settingsInitial, payload);
        if (Object.prototype.hasOwnProperty.call(payload, 'rag_chat_service_token')) {
          if (payload.rag_chat_service_token === '') {
            settingsInitial.rag_chat_service_token_present = '0';
            settingsInitial.rag_chat_service_token = '';
          } else {
            settingsInitial.rag_chat_service_token_present = '1';
            settingsInitial.rag_chat_service_token = ragChatSecretPlaceholder;
          }
        }

        renderRagChatSettings();
        notify(transRagChatSaved, 'success');
      })
      .catch(err => {
        console.error(err);
        notify(transRagChatSaveError, 'danger');
      });
  });

  return { loadSummary };
}
