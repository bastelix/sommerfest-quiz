/* global UIkit */

/**
 * Events / Veranstaltungen section – extracted from admin.js.
 *
 * @param {object} ctx  Shared dependencies injected by admin.js
 * @returns {object}    Public API consumed by the rest of admin.js
 */
export function initEvents(ctx) {
  const {
    apiFetch,
    escape,
    transEventsFetchError,
    normalizeId,
    cfgInitial,
    eventIndicators,
    indicatorNodes,
    eventSelectNodes,
    switchEvent,
    switchPending,
    lastSwitchFailed,
    resetSwitchState,
    TableManager,
    createCellEditor,
    resolveEventNamespace,
    appendNamespaceParam,
    notify,
    updateDashboardShareLinks,
    syncRandomNameOptionsState,
    invalidateRandomNamePreview,
    state,
  } = ctx;

  // --------- Veranstaltungen ---------
  const eventsListEl = document.getElementById('eventsList');
  const eventsCardsEl = document.getElementById('eventsCards');
  const eventsCardsEmptyEl = document.getElementById('eventsCardsEmpty');
  const eventAddBtn = document.getElementById('eventAddBtn');
  const hasEventsContainer = !!(eventsListEl || eventsCardsEl);

  const eventDependentSections = document.querySelectorAll('[data-event-dependent]');
  const eventSettingsHeading = document.getElementById('eventSettingsHeading');
  const catalogsHeading = document.getElementById('catalogsHeading');
  const questionsHeading = document.getElementById('questionsHeading');
  const langSelect = document.getElementById('langSelect');
  const eventButtons = document.querySelectorAll('[data-event-btn]');

  function populateEventSelectors(list) {
    const normalized = Array.isArray(list)
      ? list
          .map(item => {
            const rawUid = item?.uid ?? item?.id ?? '';
            const uid = typeof rawUid === 'string' ? rawUid : (rawUid ? String(rawUid) : '');
            const name = typeof item?.name === 'string' ? item.name.trim() : '';
            const slug = typeof item?.slug === 'string' ? item.slug.trim() : '';
            return { uid, name, slug };
          })
          .filter(ev => ev.uid !== '' && ev.name !== '' && !ev.name.startsWith('__draft__'))
      : [];
    state.availableEvents = normalized;
    eventSelectNodes.forEach(select => {
      const indicator = select.closest('[data-current-event-indicator]');
      const placeholderText = indicator?.dataset.placeholder || indicator?.dataset.empty || '';
      const previousValue = select.value;
      select.innerHTML = '';
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = placeholderText;
      select.appendChild(placeholder);
      normalized.forEach(ev => {
        const option = document.createElement('option');
        option.value = ev.uid;
        option.textContent = ev.name;
        if (ev.uid === state.currentEventUid) {
          option.selected = true;
        }
        select.appendChild(option);
      });
      const hasCurrent = normalized.some(ev => ev.uid === state.currentEventUid);
      if (hasCurrent) {
        select.value = state.currentEventUid;
      } else if (previousValue && normalized.some(ev => ev.uid === previousValue)) {
        select.value = previousValue;
      } else {
        select.value = '';
      }
      select.disabled = normalized.length === 0;
    });
  }

  function renderCurrentEventIndicator(name, uid, hasEvents = null) {
    const normalizedName = name || '';
    const normalizedUid = uid || '';
    const hasAnyEvents = typeof hasEvents === 'boolean' ? hasEvents : state.availableEvents.length > 0;
    eventIndicators.forEach(indicator => {
      const empty = indicator.querySelector('[data-current-event-empty]');
      const select = indicator.querySelector('[data-current-event-select]');
      indicator.dataset.currentEventUid = normalizedUid;
      indicator.dataset.currentEventName = normalizedName;
      if (select) {
        const options = Array.from(select.options || []);
        const hasOption = options.some(opt => opt.value === normalizedUid && normalizedUid !== '');
        if (hasOption) {
          select.value = normalizedUid;
        } else if (options.length) {
          select.value = '';
        }
        const disable = !hasAnyEvents || state.availableEvents.length === 0;
        select.disabled = disable;
      }
      if (empty) {
        if (!hasAnyEvents || state.availableEvents.length === 0) {
          const message = indicator.dataset.none || indicator.dataset.empty || '';
          empty.textContent = message;
          empty.hidden = false;
        } else if (!normalizedUid) {
          empty.textContent = indicator.dataset.empty || '';
          empty.hidden = false;
        } else {
          empty.hidden = true;
        }
      }
    });
  }

  function updateEventButtons(uid) {
    const enabled = !!uid;
    eventButtons.forEach(btn => {
      if ('disabled' in btn) {
        btn.disabled = !enabled;
      } else {
        btn.classList.toggle('uk-disabled', !enabled);
        btn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
      }
    });
  }

  function syncCurrentEventState(list) {
    populateEventSelectors(Array.isArray(list) ? list : []);
    const hasEvents = Array.isArray(list) && list.length > 0;
    if (!hasEvents) {
      state.currentEventUid = '';
      state.currentEventName = '';
      state.currentEventSlug = '';
      cfgInitial.event_uid = '';
      window.quizConfig = {};
      updateActiveHeader('');
      renderCurrentEventIndicator('', '', false);
      updateEventButtons('');
      eventDependentSections.forEach(sec => { sec.hidden = true; });
      return;
    }
    const match = list.find(ev => ev.uid === state.currentEventUid);
    if (match) {
      state.currentEventName = match.name || state.currentEventName;
      state.currentEventSlug = match.slug || state.currentEventSlug;
      updateActiveHeader(state.currentEventName);
      renderCurrentEventIndicator(state.currentEventName, state.currentEventUid, state.availableEvents.length > 0);
      updateEventButtons(state.currentEventUid);
      eventDependentSections.forEach(sec => { sec.hidden = !state.currentEventUid; });
      updateDashboardShareLinks();
    } else {
      state.currentEventUid = '';
      state.currentEventName = '';
      state.currentEventSlug = '';
      cfgInitial.event_uid = '';
      window.quizConfig = {};
      updateDashboardShareLinks();
      updateActiveHeader('');
      renderCurrentEventIndicator('', '', state.availableEvents.length > 0);
      updateEventButtons('');
      eventDependentSections.forEach(sec => { sec.hidden = true; });
    }
  }

  renderCurrentEventIndicator(state.currentEventName, state.currentEventUid, true);
  updateActiveHeader(state.currentEventName);
  updateEventButtons(state.currentEventUid);
  eventDependentSections.forEach(sec => { sec.hidden = !state.currentEventUid; });

  // Wire up the event-selector dropdowns (moved here from the shared-variable
  // block in admin.js so that setCurrentEvent / renderCurrentEventIndicator are
  // in scope).
  eventSelectNodes.forEach(select => {
    select.addEventListener('change', () => {
      const uid = normalizeId(select.value || '');
      const option = select.options[select.selectedIndex] || null;
      const name = option ? (option.textContent || '') : '';
      if (uid === (state.currentEventUid || '')) {
        renderCurrentEventIndicator(state.currentEventName, state.currentEventUid, state.availableEvents.length > 0);
        return;
      }
      setCurrentEvent(uid, name);
    });
  });

  let eventManager;
  let eventEditor;
  const eventsCardsEmptyDefault = eventsCardsEmptyEl?.textContent || '';

  function updateEventsCardsEmptyState({ force = null, useError = false } = {}) {
    if (!eventsCardsEmptyEl) {
      return;
    }
    const count = eventsCardsEl ? eventsCardsEl.children.length : 0;
    const shouldShow = typeof force === 'boolean' ? force : count === 0;
    if (useError) {
      eventsCardsEmptyEl.textContent = eventsCardsEmptyEl.dataset.errorText || transEventsFetchError;
    } else {
      eventsCardsEmptyEl.textContent = eventsCardsEmptyDefault;
    }
    eventsCardsEmptyEl.hidden = !shouldShow;
  }

  function createEventItem(ev = {}) {
    const id = ev.uid || ev.id || crypto.randomUUID();
    return {
      id,
      uid: id,
      slug: ev.slug || ev.uid || id,
      name: ev.name || '',
      namespace: ev.namespace || resolveEventNamespace() || 'default',
      start_date: ev.start_date || new Date().toISOString().slice(0, 16),
      end_date: ev.end_date || new Date().toISOString().slice(0, 16),
      description: ev.description || '',
      published: ev.published || false
    };
  }

  function saveEvents() {
    if (!eventManager) return;
    const mapped = eventManager.getData().map(ev => {
      const trimmedName = (ev.name || '').trim();
      const isDraft = trimmedName === '';
      return {
        uid: ev.id,
        slug: ev.slug || ev.id,
        name: isDraft ? `__draft__${ev.id}` : trimmedName,
        namespace: ev.namespace || resolveEventNamespace() || 'default',
        start_date: ev.start_date,
        end_date: ev.end_date,
        description: ev.description,
        published: ev.published,
        draft: isDraft
      };
    });

    const hasOnlyDrafts = mapped.length > 0 && mapped.every(ev => ev.draft);
    if (hasOnlyDrafts) {
      return;
    }

    const payload = mapped.map(({ draft, ...rest }) => rest);
    const selectable = mapped
      .filter(ev => !ev.draft)
      .map(({ draft, ...rest }) => rest);

    apiFetch(appendNamespaceParam('/events.json'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify(window.transEventsSaved || 'Events saved', 'success');
        syncCurrentEventState(selectable);
        highlightCurrentEvent();
      })
      .catch(() => notify(window.transErrorSaveFailed || 'Save failed', 'danger'));
  }

  function highlightCurrentEvent() {
    const normalizedCurrent = normalizeId(state.currentEventUid);
    Array.from(eventsListEl?.querySelectorAll('tr') || []).forEach(row => {
      const isCurrent = normalizeId(row.dataset.id) === normalizedCurrent;
      row.classList.toggle('active-event', isCurrent);
      const input = row.querySelector('input[name="currentEventList"]');
      if (input) input.checked = isCurrent;
    });
    Array.from(eventsCardsEl?.children || []).forEach(card => {
      const isCurrent = normalizeId(card.dataset.id) === normalizedCurrent;
      card.classList.toggle('active-event', isCurrent);
      const input = card.querySelector('input[name="currentEventCard"]');
      if (input) input.checked = isCurrent;
    });
  }

  function setCurrentEvent(uid, name) {
    const normalizedUid = normalizeId(uid);
    const normalizedCurrentUid = normalizeId(state.currentEventUid);

    if (normalizedUid === normalizedCurrentUid) {
      // Short-circuit when selecting the already active event to avoid redundant network calls
      highlightCurrentEvent();
      return Promise.resolve();
    }

    if (switchPending()) {
      highlightCurrentEvent();
      return Promise.resolve();
    }
    if (lastSwitchFailed()) {
      resetSwitchState();
    }
    const prevUid = state.currentEventUid;
    const prevName = state.currentEventName;
    const prevSlug = state.currentEventSlug;
    return switchEvent(normalizedUid, name)
      .then(cfg => {
        state.currentEventUid = normalizedUid;
        state.currentEventName = state.currentEventUid ? (name || state.currentEventName) : '';
        const matched = state.availableEvents.find(ev => normalizeId(ev.uid) === state.currentEventUid);
        state.currentEventSlug = matched?.slug || state.currentEventSlug;
        updateDashboardShareLinks();
        cfgInitial.event_uid = state.currentEventUid;
        Object.assign(cfgInitial, cfg);
        state.dashboardQrCatalogs = [];
        state.dashboardQrFetchEpoch += 1;
        window.quizConfig = cfg || {};
        updateActiveHeader(state.currentEventName);
        renderCurrentEventIndicator(state.currentEventName, state.currentEventUid);
        updateEventButtons(state.currentEventUid);
        eventDependentSections.forEach(sec => { sec.hidden = !state.currentEventUid; });
        const url = new URL(window.location);
        if (state.currentEventUid) url.searchParams.set('event', state.currentEventUid); else url.searchParams.delete('event');
        if (window.history && window.history.replaceState) {
          window.history.replaceState(null, '', url.toString());
        }
        highlightCurrentEvent();
        syncRandomNameOptionsState();
        invalidateRandomNamePreview();
      })
      .catch(err => {
        console.error(err);
        notify(err.message || (window.transErrorEventSwitch || 'Error switching event'), 'danger');
        state.currentEventUid = prevUid;
        state.currentEventName = prevName;
        updateActiveHeader(prevName);
        renderCurrentEventIndicator(prevName, prevUid);
        updateEventButtons(prevUid);
        eventDependentSections.forEach(sec => { sec.hidden = !prevUid; });
        const url = new URL(window.location);
        if (prevUid) url.searchParams.set('event', prevUid); else url.searchParams.delete('event');
        if (window.history && window.history.replaceState) {
          window.history.replaceState(null, '', url.toString());
        }
        state.currentEventSlug = prevSlug;
        updateDashboardShareLinks();
        highlightCurrentEvent();
      });
  }

  if (hasEventsContainer) {
    const labels = eventsListEl?.dataset || eventsCardsEl?.dataset || {};
    const eventColumns = [
      { className: 'row-num' },
      { key: 'name', label: labels.labelName || 'Name', className: 'event-name', editable: true },
      { key: 'start_date', label: labels.labelStart || 'Start', className: 'event-start', editable: true },
      { key: 'end_date', label: labels.labelEnd || 'Ende', className: 'event-end', editable: true },
      { key: 'description', label: labels.labelDescription || 'Beschreibung', className: 'event-desc', editable: true },
      {
        className: 'uk-table-shrink',
        render: ev => {
          const label = document.createElement('label');
          label.className = 'switch';
          label.setAttribute('uk-tooltip', `title: ${labels.tipSelectEvent || ''}; pos: top`);
          const input = document.createElement('input');
          input.type = 'radio';
          input.name = 'currentEventList';
          const normalizedId = normalizeId(ev.id);
          input.dataset.id = normalizedId;
          input.checked = normalizedId === normalizeId(state.currentEventUid);
          input.addEventListener('change', () => {
            if (!input.checked) return;
            if (switchPending()) {
              highlightCurrentEvent();
              return;
            }
            const twin = eventsCardsEl?.querySelector(`input[name="currentEventCard"][data-id="${normalizedId}"]`);
            if (twin) twin.checked = true;
            if (lastSwitchFailed()) {
              resetSwitchState();
            }
            setCurrentEvent(normalizedId, ev.name).finally(highlightCurrentEvent);
          });
          const slider = document.createElement('span');
          slider.className = 'slider';
          label.appendChild(input);
          label.appendChild(slider);
          return label;
        },
        renderCard: ev => {
          const label = document.createElement('label');
          label.className = 'switch';
          label.setAttribute('uk-tooltip', `title: ${labels.tipSelectEvent || ''}; pos: top`);
          const input = document.createElement('input');
          input.type = 'radio';
          input.name = 'currentEventCard';
          const normalizedId = normalizeId(ev.id);
          input.dataset.id = normalizedId;
          input.checked = normalizedId === normalizeId(state.currentEventUid);
          input.addEventListener('change', () => {
            if (!input.checked) return;
            if (switchPending()) {
              highlightCurrentEvent();
              return;
            }
            const twin = eventsListEl?.querySelector(`input[name="currentEventList"][data-id="${normalizedId}"]`);
            if (twin) twin.checked = true;
            if (lastSwitchFailed()) {
              resetSwitchState();
            }
            setCurrentEvent(normalizedId, ev.name).finally(highlightCurrentEvent);
          });
          const slider = document.createElement('span');
          slider.className = 'slider';
          label.appendChild(input);
          label.appendChild(slider);
          return label;
        }
      },
      {
        className: 'uk-table-shrink',
        render: ev => {
          const wrapper = document.createElement('div');
          wrapper.className = 'uk-flex uk-flex-middle uk-flex-right';

          const delBtn = document.createElement('button');
          delBtn.className = 'uk-icon-button qr-action uk-text-danger';
          delBtn.setAttribute('uk-icon', 'trash');
          delBtn.setAttribute('aria-label', window.transDelete || 'Delete');
          delBtn.setAttribute('uk-tooltip', 'title: ' + (window.transDelete || 'Delete') + '; pos: left');
          delBtn.addEventListener('click', () => removeEvent(ev.id));

          wrapper.appendChild(delBtn);
          return wrapper;
        },
        renderCard: ev => {
          const wrapper = document.createElement('div');
          wrapper.className = 'uk-flex uk-flex-middle uk-flex-right qr-action';

          const delBtn = document.createElement('button');
          delBtn.className = 'uk-icon-button qr-action uk-text-danger';
          delBtn.setAttribute('uk-icon', 'trash');
          delBtn.setAttribute('aria-label', window.transDelete || 'Delete');
          delBtn.addEventListener('click', () => removeEvent(ev.id));

          wrapper.appendChild(delBtn);
          return wrapper;
        }
      }
    ];
    if (!document.getElementById('eventEditModal')) {
      const modal = document.createElement('div');
      modal.id = 'eventEditModal';
      modal.setAttribute('uk-modal', '');
      modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">'
        + '<h3 class="uk-modal-title"></h3>'
        + '<input id="eventEditInput" class="uk-input" type="text">'
        + '<div class="uk-margin-top uk-text-right">'
        + `<button id="eventEditCancel" class="uk-button uk-button-default" type="button">${window.transCancel || 'Cancel'}</button>`
        + `<button id="eventEditSave" class="uk-button uk-button-primary" type="button">${window.transSave || 'Save'}</button>`
        + '</div>'
        + '</div>';
      document.body.appendChild(modal);
    }
    const eventTableBody = eventsListEl || (eventsCardsEl ? document.createElement('tbody') : null);
    if (eventTableBody && !eventTableBody.id && eventsCardsEl?.id) {
      eventTableBody.id = `${eventsCardsEl.id}-table`;
    }
    eventManager = new TableManager({
      tbody: eventTableBody,
      mobileCards: { container: eventsCardsEl },
      sortable: true,
      columns: eventColumns,
      onEdit: cell => eventEditor.open(cell),
      onReorder: () => saveEvents()
    });
    eventEditor = createCellEditor(eventManager, {
      modalSelector: '#eventEditModal',
      inputSelector: '#eventEditInput',
      saveSelector: '#eventEditSave',
      cancelSelector: '#eventEditCancel',
      getTitle: key => ({
        name: labels.labelName || 'Name',
        start_date: labels.labelStart || 'Start',
        end_date: labels.labelEnd || 'Ende',
        description: labels.labelDescription || 'Beschreibung'
      })[key] || '',
      getType: key => (key === 'start_date' || key === 'end_date') ? 'datetime-local' : 'text',
      onSave: () => {
        highlightCurrentEvent();
        saveEvents();
      }
    });
  }

  function removeEvent(id) {
    const list = eventManager.getData();
    const idx = list.findIndex(e => e.id === id);
    if (idx !== -1) {
      list.splice(idx, 1);
      eventManager.render(list);
      highlightCurrentEvent();
      updateEventsCardsEmptyState();
      saveEvents();
      syncCurrentEventState(list);
    }
  }

  if (eventManager || indicatorNodes.length > 0) {
    const initial = Array.isArray(window.initialEvents)
      ? window.initialEvents.map(d => createEventItem(d))
      : [];
    const initialEmpty = initial.length === 0;
    populateEventSelectors(initial);
    if (eventManager) {
      eventManager.render(initial);
      highlightCurrentEvent();
      updateEventsCardsEmptyState();
    }
    if (!initialEmpty) {
      syncCurrentEventState(initial);
    } else {
      renderCurrentEventIndicator(state.currentEventName, state.currentEventUid, false);
      updateEventButtons(state.currentEventUid);
      eventDependentSections.forEach(sec => { sec.hidden = !state.currentEventUid; });
    }
    // initialEvents from the server are the authoritative source at page load.
    // A redundant /events.json refetch was removed here – the selectors are
    // already populated from window.initialEvents above and will be refreshed
    // on demand when switching events or opening the summary tab.
  }

  if (!hasEventsContainer && eventAddBtn) {
    if ('disabled' in eventAddBtn) {
      eventAddBtn.disabled = true;
    } else {
      eventAddBtn.classList.add('uk-disabled');
      eventAddBtn.setAttribute('aria-disabled', 'true');
    }
  }

  eventAddBtn?.addEventListener('click', e => {
    e.preventDefault();
    if (!eventManager) return;
    const list = eventManager.getData();
    const item = createEventItem();
    list.push(item);
    eventManager.render(list);
    highlightCurrentEvent();
    updateEventsCardsEmptyState();
    const nameCell = eventsListEl?.querySelector(`tr[data-id="${item.id}"] td[data-key="name"]`);
    const nameCard = eventsCardsEl?.querySelector(`.qr-cell[data-id="${item.id}"][data-key="name"]`);
    const target = nameCell || nameCard;
    if (target && eventEditor) {
      requestAnimationFrame(() => {
        eventEditor.open(target);
      });
    }
  });


  function updateActiveHeader(name) {
    const top = document.getElementById('topbar-title');
    if (top) {
      const fallback = top.dataset.defaultTitle || top.dataset.default || top.textContent || '';
      top.textContent = name || fallback;
    }
    const activeHeading = document.querySelector('[data-active-event-title]');
    if (activeHeading) {
      const baseTitle = activeHeading.dataset.activeEventTitle || activeHeading.dataset.title || activeHeading.textContent || '';
      activeHeading.textContent = name ? `${name} – ${baseTitle}` : baseTitle;
    }
  }

  langSelect?.addEventListener('change', () => {
    const lang = langSelect.value;
    const url = new URL(window.location.href);
    url.searchParams.set('lang', lang);
    window.location.href = escape(url.toString());
  });

  return {
    populateEventSelectors,
    renderCurrentEventIndicator,
    updateEventButtons,
    syncCurrentEventState,
    updateEventsCardsEmptyState,
    highlightCurrentEvent,
    setCurrentEvent,
    updateActiveHeader,
    eventDependentSections,
    eventSettingsHeading,
    catalogsHeading,
    questionsHeading,
  };
}
