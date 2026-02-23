/* global UIkit */

/**
 * Teams/Personen section – extracted from admin.js.
 *
 * @param {object} ctx - shared dependencies injected from admin.js
 * @param {function} ctx.apiFetch        - API fetch wrapper
 * @param {function} ctx.notify          - notification helper (window.notify)
 * @param {function} ctx.withBase        - URL helper
 * @param {function} ctx.getCurrentEventUid - getter for current event UID
 * @param {object}   ctx.cfgInitial      - reference to config object
 * @param {function} ctx.registerCacheReset - from event-switcher
 * @param {function} ctx.TableManager    - TableManager class
 * @param {function} ctx.createCellEditor - cell editor factory
 */
export function initTeams(ctx) {
  const {
    apiFetch,
    notify,
    withBase,
    getCurrentEventUid,
    cfgInitial,
    registerCacheReset,
    TableManager,
    createCellEditor
  } = ctx;

  // --------- Teams/Personen ---------
  const teamSearchInput = document.getElementById('teamSearch');
  const teamSearchForm = document.getElementById('teamSearchForm') || teamSearchInput?.form || null;
  const teamListEl = document.getElementById('teamsList');
  const teamCardsEl = document.getElementById('teamsCards');
  const teamAddBtn = document.getElementById('teamAddBtn');
  const teamDeleteAllBtn = document.getElementById('teamDeleteAllBtn');
  const teamDeleteAllConfirmBtn = document.getElementById('teamDeleteAllConfirm');
  const teamDeleteAllModal = window.UIkit ? UIkit.modal('#teamDeleteAllModal') : null;
  const teamRestrictTeams = document.getElementById('teamRestrict');
  const teamDeleteTitle = window.transTeamDeleteTitle || 'Delete team';
  const teamDeleteConfirmTemplate = window.transTeamDeleteConfirm || 'Delete ":name" and all associated results?';
  let teamDeletePendingId = null;

  if (!document.getElementById('teamDeleteConfirmModal')) {
    const modal = document.createElement('div');
    modal.id = 'teamDeleteConfirmModal';
    modal.setAttribute('uk-modal', '');
    modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">'
      + `<h3 class="uk-modal-title">${teamDeleteTitle}</h3>`
      + '<p id="teamDeleteConfirmText"></p>'
      + '<div class="uk-text-right">'
      + `<button id="teamDeleteCancel" class="uk-button uk-button-default uk-modal-close" type="button">${window.transCancel || 'Cancel'}</button>`
      + `<button id="teamDeleteConfirm" class="uk-button uk-button-danger" type="button">${window.transDelete || 'Delete'}</button>`
      + '</div>'
      + '</div>';
    document.body.appendChild(modal);
  }

  const teamDeleteModalEl = document.getElementById('teamDeleteConfirmModal');
  const teamDeleteConfirmTextEl = document.getElementById('teamDeleteConfirmText');
  const teamDeleteConfirmBtn = document.getElementById('teamDeleteConfirm');
  const teamDeleteCancelBtn = document.getElementById('teamDeleteCancel');
  const teamDeleteModal = teamDeleteModalEl && window.UIkit ? UIkit.modal(teamDeleteModalEl) : null;

  teamSearchForm?.addEventListener('submit', event => {
    event.preventDefault();
    applyTeamFilter();
  });

  teamSearchInput?.addEventListener('input', () => {
    applyTeamFilter();
  });

  teamDeleteModalEl?.addEventListener('hidden', () => {
    teamDeletePendingId = null;
    if (teamDeleteConfirmTextEl) {
      teamDeleteConfirmTextEl.textContent = '';
    }
  });

  teamDeleteCancelBtn?.addEventListener('click', () => {
    teamDeletePendingId = null;
    if (teamDeleteConfirmTextEl) {
      teamDeleteConfirmTextEl.textContent = '';
    }
  });

  teamDeleteConfirmBtn?.addEventListener('click', event => {
    event.preventDefault();
    if (teamDeletePendingId) {
      applyTeamRemoval(teamDeletePendingId);
    }
    teamDeletePendingId = null;
    if (teamDeleteModal) {
      teamDeleteModal.hide();
    }
  });

  if (!document.getElementById('teamEditModal')) {
    const modal = document.createElement('div');
    modal.id = 'teamEditModal';
    modal.setAttribute('uk-modal', '');
    modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">'
      + '<h3 class="uk-modal-title"></h3>'
      + '<input id="teamEditInput" class="uk-input" type="text">'
      + '<div id="teamEditError" class="uk-text-danger uk-margin-small-top" hidden></div>'
      + '<div class="uk-margin-top uk-text-right">'
      + `<button id="teamEditCancel" class="uk-button uk-button-default" type="button">${window.transCancel || 'Cancel'}</button>`
      + `<button id="teamEditSave" class="uk-button uk-button-primary" type="button">${window.transSave || 'Save'}</button>`
      + '</div>'
      + '</div>';
    document.body.appendChild(modal);
  }

  const teamEditInput = document.getElementById('teamEditInput');
  const teamEditError = document.getElementById('teamEditError');
  const TEAMS_PER_PAGE = 50;
  const teamPaginationEl = document.createElement('ul');
  teamPaginationEl.id = 'teamsPagination';
  teamPaginationEl.className = 'uk-pagination uk-flex-center';
  teamAddBtn?.parentElement?.before(teamPaginationEl);

  let teamManager;
  let teamEditor;

  registerCacheReset(() => {
    teamManager?.render([]);
    if (teamRestrictTeams) {
      teamRestrictTeams.checked = false;
    }
  });
  if (teamListEl) {
    const teamColumns = [
      { key: 'name', label: 'Name', className: 'team-name', editable: true },
      {
        className: 'uk-table-shrink',
        render: item => {
          const wrapper = document.createElement('div');
          wrapper.className = 'uk-flex uk-flex-middle uk-flex-right';

          const pdfBtn = document.createElement('button');
          pdfBtn.className = 'uk-icon-button qr-action';
          pdfBtn.setAttribute('uk-icon', 'file-text');
          pdfBtn.setAttribute('aria-label', window.transTeamPdf || 'PDF');
          pdfBtn.setAttribute('uk-tooltip', 'title: ' + (window.transTeamPdf || 'PDF') + '; pos: left');
          pdfBtn.addEventListener('click', () => openTeamPdf(item.name));
          wrapper.appendChild(pdfBtn);

          const delBtn = document.createElement('button');
          delBtn.className = 'uk-icon-button qr-action uk-text-danger uk-margin-small-left';
          delBtn.setAttribute('uk-icon', 'trash');
          delBtn.setAttribute('aria-label', window.transDelete || 'Delete');
          delBtn.setAttribute('uk-tooltip', 'title: ' + (window.transDelete || 'Delete') + '; pos: left');
          delBtn.addEventListener('click', () => requestTeamRemoval(item));
          wrapper.appendChild(delBtn);

          return wrapper;
        },
        renderCard: item => {
          const wrapper = document.createElement('div');
          wrapper.className = 'uk-flex uk-flex-middle qr-action';

          const pdfBtn = document.createElement('button');
          pdfBtn.className = 'uk-icon-button qr-action';
          pdfBtn.setAttribute('uk-icon', 'file-text');
          pdfBtn.setAttribute('aria-label', window.transTeamPdf || 'PDF');
          pdfBtn.addEventListener('click', () => openTeamPdf(item.name));

          const delBtn = document.createElement('button');
          delBtn.className = 'uk-icon-button qr-action uk-text-danger uk-margin-small-left';
          delBtn.setAttribute('uk-icon', 'trash');
          delBtn.setAttribute('aria-label', window.transDelete || 'Delete');
          delBtn.addEventListener('click', () => requestTeamRemoval(item));

          wrapper.appendChild(pdfBtn);
          wrapper.appendChild(delBtn);
          return wrapper;
        }
      }
    ];
    teamManager = new TableManager({
      tbody: teamListEl,
      mobileCards: { container: teamCardsEl },
      columns: teamColumns,
      sortable: true,
      onEdit: cell => {
        teamEditError.hidden = true;
        teamEditor.open(cell);
      },
      onReorder: () => reorderTeams(teamManager.getData())
    });
    teamEditor = createCellEditor(teamManager, {
      modalSelector: '#teamEditModal',
      inputSelector: '#teamEditInput',
      saveSelector: '#teamEditSave',
      cancelSelector: '#teamEditCancel',
      getTitle: key => teamColumns.find(c => c.key === key)?.label || '',
      validate: val => {
        if (!val) {
          teamEditError.textContent = window.transNameEmpty || 'Name must not be empty';
          teamEditError.hidden = false;
          return false;
        }
        return true;
      },
      onSave: list => saveTeamList(list)
    });
    teamManager.bindPagination(teamPaginationEl, TEAMS_PER_PAGE);
    if (teamSearchInput?.value?.trim()) {
      applyTeamFilter();
    }
  }

  function saveTeamList(list = teamManager?.getData() || [], show = false, retries = 1) {
    const names = list.map(t => t.name);
    apiFetch(teamsUrl(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(names)
    })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        if (show) notify(window.transListSaved || 'List saved', 'success');
      })
      .catch(err => {
        console.error(err);
        if (retries > 0) {
          notify(window.transErrorSaveRetry || 'Save failed, please try again\u2026', 'warning');
          setTimeout(() => saveTeamList(list, show, retries - 1), 1000);
        } else {
          notify(window.transErrorSaveFailed || 'Save failed', 'danger');
        }
      });
  }

  function reorderTeams(list) {
    saveTeamList(list);
  }

  function normalizeTeamSearchValue(value = '') {
    if (typeof value !== 'string') {
      return '';
    }
    let normalized = value.trim().toLocaleLowerCase();
    if (!normalized) {
      return '';
    }
    if (typeof normalized.normalize === 'function') {
      normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    return normalized;
  }

  function applyTeamFilter() {
    if (!teamManager) {
      return;
    }
    const term = normalizeTeamSearchValue(teamSearchInput?.value || '');
    if (!term) {
      teamManager.setFilter(null);
      return;
    }
    teamManager.setFilter(item => normalizeTeamSearchValue(item?.name || '').includes(term));
  }

  function formatTeamDeleteMessage(name) {
    const template = teamDeleteConfirmTemplate;
    if (!template.includes(':name')) {
      return template;
    }
    const trimmed = typeof name === 'string' ? name.trim() : '';
    const replacement = trimmed !== '' ? trimmed : '\u2026';
    return template.replace(':name', replacement);
  }

  function requestTeamRemoval(item) {
    if (!teamManager) {
      return;
    }
    const list = teamManager.getData();
    const current = list.find(team => team.id === item.id);
    const message = formatTeamDeleteMessage(current?.name ?? item.name ?? '');
    if (teamDeleteModal && teamDeleteConfirmTextEl) {
      teamDeletePendingId = item.id;
      teamDeleteConfirmTextEl.textContent = message;
      teamDeleteModal.show();
      return;
    }
    if (window.confirm(message)) {
      applyTeamRemoval(item.id);
    }
  }

  function applyTeamRemoval(id) {
    if (!teamManager) {
      return;
    }
    const list = teamManager.getData();
    const idx = list.findIndex(t => t.id === id);
    if (idx === -1) {
      return;
    }
    list.splice(idx, 1);
    teamManager.render(list);
    saveTeamList(list);
  }

  function deleteAllTeams() {
    if (!teamManager) {
      return;
    }
    const items = teamManager.getData();
    if (!items.length) {
      notify(window.transTeamDeleteAllEmpty || 'No teams available', 'warning');
      return;
    }

    teamManager.setColumnLoading('name', true);
    apiFetch(teamsUrl(), { method: 'DELETE' })
      .then(r => {
        if (!r.ok) {
          throw new Error(r.statusText || `HTTP ${r.status}`);
        }
        teamManager.render([]);
        if (teamRestrictTeams) {
          teamRestrictTeams.checked = false;
        }
        if (typeof cfgInitial === 'object' && cfgInitial !== null) {
          cfgInitial.QRRestrict = false;
        }
        notify(
          window.transTeamDeleteAllSuccess || 'All teams and results have been deleted',
          'success'
        );
      })
      .catch(err => {
        console.error(err);
        const fallback = window.transTeamDeleteAllError || 'Unable to delete teams';
        if (err instanceof TypeError) {
          notify(fallback, 'danger');
          return;
        }
        const message = err.message && err.message.trim() ? err.message : fallback;
        notify(message, 'danger');
      })
      .finally(() => {
        teamManager.setColumnLoading('name', false);
      });
  }

  function openTeamPdf(teamName){
    const currentEventUid = getCurrentEventUid();
    let pdfUrl = '/results.pdf?team=' + encodeURIComponent(teamName);
    if (currentEventUid) {
      pdfUrl += '&event_uid=' + encodeURIComponent(currentEventUid);
    }
    window.open(withBase(pdfUrl), '_blank');
  }

  function teamsUrl() {
    const currentEventUid = getCurrentEventUid();
    return currentEventUid
      ? `/teams.json?event_uid=${encodeURIComponent(currentEventUid)}`
      : '/teams.json';
  }

  function loadTeamList() {
    if (!teamManager) return;
    teamManager.setColumnLoading('name', true);
    apiFetch(teamsUrl(), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(data => {
        const list = data.map(n => ({ id: crypto.randomUUID(), name: n }));
        teamManager.render(list);
      })
      .catch(() => {})
      .finally(() => teamManager.setColumnLoading('name', false));
    if (teamRestrictTeams) {
      teamRestrictTeams.checked = !!cfgInitial.QRRestrict;
    }
  }

  if (teamListEl) {
    loadTeamList();
  }

  teamDeleteAllBtn?.addEventListener('click', e => {
    e.preventDefault();
    if (!teamManager) {
      return;
    }
    if (teamManager.getData().length === 0) {
      notify(window.transTeamDeleteAllEmpty || 'No teams available', 'warning');
      return;
    }
    if (teamDeleteAllModal) {
      teamDeleteAllModal.show();
    } else {
      deleteAllTeams();
    }
  });

  teamDeleteAllConfirmBtn?.addEventListener('click', e => {
    e.preventDefault();
    teamDeleteAllModal?.hide();
    deleteAllTeams();
  });

  teamAddBtn?.addEventListener('click', e => {
    e.preventDefault();
    if (!teamManager) return;
    const id = crypto.randomUUID();
    const team = { id, name: '' };
    const list = teamManager.getData();
    list.push(team);
    if (teamManager.pagination) {
      teamManager.pagination.page = Math.max(1, Math.ceil(list.length / TEAMS_PER_PAGE));
    }
    teamManager.render(list);
    const cell = document.querySelector(`[data-id="${id}"][data-key="name"]`);
    if (cell) {
      teamEditError.hidden = true;
      teamEditor.open(cell);
    }
  });

  return { loadTeamList, teamListEl };
}
