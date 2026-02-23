/* global UIkit */

import { withBase, getCsrfToken, resolveWithBase, normalizeEndpointToSameOrigin, warnExternalEndpoint } from './admin-utils.js';
import { getSwitchEpoch, registerScopedAbortController, isCurrentEpoch } from './event-switcher.js';

const initPageTypeDefaultsForm = () => {
  const form = document.querySelector('[data-page-types-form]');
  if (!form) {
    return;
  }

  const list = form.querySelector('[data-page-type-list]');
  const template = form.querySelector('[data-page-type-template]');
  const addBtn = form.querySelector('[data-page-type-add]');
  const emptyState = form.querySelector('[data-page-type-empty]');

  if (!list || !template) {
    return;
  }

  const resolveNextIndex = () => {
    const current = Number.parseInt(form.dataset.pageTypeIndex || '0', 10);
    const next = Number.isNaN(current) ? 0 : current;
    form.dataset.pageTypeIndex = String(next + 1);
    return next;
  };

  const updateEmptyState = () => {
    if (!emptyState) {
      return;
    }
    const hasRows = list.querySelectorAll('[data-page-type-row]').length > 0;
    emptyState.hidden = hasRows;
  };

  const addRow = () => {
    const index = resolveNextIndex();
    const html = template.innerHTML.replace(/__INDEX__/g, String(index));
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    const row = wrapper.firstElementChild;
    if (row) {
      list.append(row);
      updateEmptyState();
      const input = row.querySelector('input[type="text"]');
      if (input) {
        input.focus();
      }
    }
  };

  addBtn?.addEventListener('click', event => {
    event.preventDefault();
    addRow();
  });

  form.addEventListener('click', event => {
    const target = event.target instanceof Element ? event.target : null;
    const removeBtn = target?.closest?.('[data-page-type-remove]');
    if (!removeBtn) {
      return;
    }
    event.preventDefault();
    const row = removeBtn.closest('[data-page-type-row]');
    if (row) {
      row.remove();
      updateEmptyState();
    }
  });

  updateEmptyState();
};

const normalizeTreeNamespace = (namespace) => (namespace || 'default').trim() || 'default';

const normalizeTreePosition = value => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
};

const MAX_TREE_DEPTH = 50;

const flattenTreeNodes = (nodes, fallbackNamespace, depth = 0) => {
  if (depth > MAX_TREE_DEPTH) {
    return [];
  }
  const flat = [];
  nodes.forEach(node => {
    const namespace = normalizeTreeNamespace(node.namespace || fallbackNamespace);
    flat.push({
      id: Number.isFinite(Number(node.id)) ? Number(node.id) : null,
      parent_id: Number.isFinite(Number(node.parent_id)) ? Number(node.parent_id) : null,
      title: (node.title || node.slug || 'Ohne Titel').trim(),
      slug: (node.slug || '').trim(),
      namespace,
      status: (node.status || '').trim(),
      type: (node.type || '').trim(),
      language: (node.language || '').trim(),
      editUrl: typeof node.editUrl === 'string' ? node.editUrl : null,
      position: normalizeTreePosition(node.position ?? node.sort_order)
    });
    if (Array.isArray(node.children) && node.children.length) {
      flat.push(...flattenTreeNodes(node.children, namespace, depth + 1));
    }
  });
  return flat;
};

const sortTree = (nodes, depth = 0) => {
  if (depth > MAX_TREE_DEPTH) {
    return;
  }
  nodes.sort((a, b) => {
    const positionDiff = Number(a.position || 0) - Number(b.position || 0);
    if (positionDiff !== 0) {
      return positionDiff;
    }
    return (a.title || '').localeCompare(b.title || '');
  });
  nodes.forEach(node => {
    if (Array.isArray(node.children) && node.children.length) {
      sortTree(node.children, depth + 1);
    }
  });
};

const buildTreeFromFlatPages = (pages) => {
  const nodesById = new Map();
  const roots = [];

  pages.forEach(page => {
    const key = page.id ?? page.slug;
    if (key === null || key === undefined || key === '') {
      return;
    }
    nodesById.set(String(key), { ...page, children: [] });
  });

  nodesById.forEach(node => {
    const parentKey = node.parent_id !== null && node.parent_id !== undefined ? String(node.parent_id) : null;
    if (parentKey && nodesById.has(parentKey)) {
      nodesById.get(parentKey).children.push(node);
    } else {
      roots.push(node);
    }
  });

  sortTree(roots);

  return roots;
};

window.pageTreeUtils = {
  normalizeNamespace: normalizeTreeNamespace,
  normalizePosition: normalizeTreePosition,
  flattenNodes: flattenTreeNodes,
  buildTree: buildTreeFromFlatPages,
};
function showUpgradeModal() {
  if (document.getElementById('upgrade-modal')) return;
  const modal = document.createElement('div');
  modal.id = 'upgrade-modal';
  modal.setAttribute('uk-modal', '');
  modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">' +
    '<h3 class="uk-modal-title">' + (window.transUpgradeTitle || 'Limit reached') + '</h3>' +
    '<p>' + (window.transUpgradeText || '') + '</p>' +
    '<p class="uk-text-center"><a class="uk-button uk-button-primary" href="' +
    (window.upgradeUrl || withBase('/admin/subscription')) + '">' +
    (window.transUpgradeAction || 'Upgrade') + '</a></p>' +
    '</div>';
  document.body.appendChild(modal);
  if (window.UIkit) {
    const ui = UIkit.modal(modal);
    if (UIkit.util) UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    ui.show();
  } else {
    modal.remove();
  }
}

window.apiFetch = (path, options = {}) => {
  const epoch = getSwitchEpoch();
  const controller = new AbortController();
  const cleanup = registerScopedAbortController(controller, epoch);

  const externalSignal = options.signal;
  if (typeof AbortSignal !== 'undefined' && externalSignal instanceof AbortSignal) {
    if (externalSignal.aborted) {
      controller.abort();
    } else {
      externalSignal.addEventListener('abort', () => controller.abort(), { once: true });
    }
  }

  const token = getCsrfToken();
  const headers = {
    ...(token ? { 'X-CSRF-Token': token } : {}),
    'X-Requested-With': 'fetch',
    ...(options.headers || {})
  };

  const opts = {
    credentials: 'same-origin',
    cache: 'no-store',
    ...options,
    headers,
    signal: controller.signal
  };

  const { endpoint: normalizedEndpoint, external, externalHost } = normalizeEndpointToSameOrigin(path);
  if (external) {
    warnExternalEndpoint(externalHost);
    controller.abort();
    cleanup();
    const err = new Error('External endpoint blocked');
    err.code = 'external-endpoint-blocked';
    return Promise.reject(err);
  }

  return fetch(resolveWithBase(normalizedEndpoint), opts)
    .then(res => {
      if (res.status === 402) {
        showUpgradeModal();
        const err = new Error(window.transUpgradeText || 'upgrade-required');
        err.code = 'upgrade-required';
        throw err;
      }
      if (!isCurrentEpoch(epoch) && !controller.signal.aborted) {
        const abortErr = new Error('Request aborted due to event switch');
        abortErr.name = 'AbortError';
        throw abortErr;
      }
      return res;
    })
    .finally(() => {
      cleanup();
    });
};
const apiFetch = window.apiFetch;

/* ── Page-tree action modals (singleton) ── */
let pageRenamePending = null;
let pageDeletePending = null;

const ensurePageRenameModal = () => {
  if (document.getElementById('pageRenameModal')) return;
  const el = document.createElement('div');
  el.id = 'pageRenameModal';
  el.setAttribute('uk-modal', '');
  el.innerHTML = [
    '<div class="uk-modal-dialog uk-modal-body">',
    '  <h3 class="uk-modal-title">' + (window.transRenamePage || 'Rename page') + '</h3>',
    '  <label class="uk-form-label" for="pageRenameInput">' + (window.transNewSlug || 'New slug') + '</label>',
    '  <input id="pageRenameInput" class="uk-input" type="text">',
    '  <div id="pageRenameError" class="uk-text-danger uk-margin-small-top" hidden></div>',
    '  <p id="pageRenameHint" class="uk-text-meta uk-margin-small-top"></p>',
    '  <div class="uk-margin-top uk-text-right">',
    '    <button class="uk-button uk-button-default uk-modal-close" type="button">' + (window.transCancel || 'Cancel') + '</button>',
    '    <button id="pageRenameSave" class="uk-button uk-button-primary uk-margin-small-left" type="button">' + (window.transRenameAction || 'Rename') + '</button>',
    '  </div>',
    '</div>'
  ].join('\n');
  document.body.appendChild(el);

  el.addEventListener('hidden', () => { pageRenamePending = null; });

  document.getElementById('pageRenameSave').addEventListener('click', async () => {
    if (!pageRenamePending) return;
    const input = document.getElementById('pageRenameInput');
    const errorEl = document.getElementById('pageRenameError');
    const newSlug = (input.value || '').trim();

    if (!newSlug || newSlug === pageRenamePending.slug) {
      errorEl.textContent = window.transSlugEmpty || 'Please enter a new slug.';
      errorEl.hidden = false;
      return;
    }
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(newSlug)) {
      errorEl.textContent = window.transSlugInvalid || 'The slug may only contain lowercase letters, digits and hyphens.';
      errorEl.hidden = false;
      return;
    }

    try {
      await executePageRename(pageRenamePending.slug, pageRenamePending.namespace, newSlug);
      UIkit.modal('#pageRenameModal').hide();
      window.notify(window.transPageRenamed || 'Page successfully renamed', 'success');
      window.location.reload();
    } catch (error) {
      errorEl.textContent = error.message || (window.transPageRenameFailed || 'Error renaming page');
      errorEl.hidden = false;
    }
  });

  document.getElementById('pageRenameInput').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      document.getElementById('pageRenameSave').click();
    }
  });
};

const ensurePageDeleteModal = () => {
  if (document.getElementById('pageDeleteConfirmModal')) return;
  const el = document.createElement('div');
  el.id = 'pageDeleteConfirmModal';
  el.setAttribute('uk-modal', '');
  el.innerHTML = [
    '<div class="uk-modal-dialog uk-modal-body">',
    '  <h3 class="uk-modal-title">' + (window.transPageDeleteTitle || 'Delete page') + '</h3>',
    '  <p id="pageDeleteConfirmText"></p>',
    '  <div id="pageDeleteSubtreeWarning" class="uk-alert-danger" uk-alert hidden>',
    '    <p>' + (window.transPageDeleteWarning || 'Warning: All child pages will also be deleted!') + '</p>',
    '  </div>',
    '  <div class="uk-margin-top uk-text-right">',
    '    <button class="uk-button uk-button-default uk-modal-close" type="button">' + (window.transCancel || 'Cancel') + '</button>',
    '    <button id="pageDeleteConfirm" class="uk-button uk-button-danger uk-margin-small-left" type="button">' + (window.transDeletePermanently || 'Delete permanently') + '</button>',
    '  </div>',
    '</div>'
  ].join('\n');
  document.body.appendChild(el);

  el.addEventListener('hidden', () => { pageDeletePending = null; });

  document.getElementById('pageDeleteConfirm').addEventListener('click', async () => {
    if (!pageDeletePending) return;
    try {
      await executePageDelete(pageDeletePending.slug, pageDeletePending.namespace);
      UIkit.modal('#pageDeleteConfirmModal').hide();
      window.notify(window.transPageDeleted || 'Page successfully deleted', 'success');
      window.location.reload();
    } catch (error) {
      UIkit.modal('#pageDeleteConfirmModal').hide();
      window.notify(error.message || (window.transPageDeleteFailed || 'Page could not be deleted.'), 'danger');
    }
  });
};

const openPageRenameModal = (slug, namespace, title) => {
  ensurePageRenameModal();
  pageRenamePending = { slug, namespace, title };
  const input = document.getElementById('pageRenameInput');
  const errorEl = document.getElementById('pageRenameError');
  const hint = document.getElementById('pageRenameHint');
  input.value = slug;
  errorEl.hidden = true;
  errorEl.textContent = '';
  hint.textContent = (window.transCurrentPage || 'Current page') + ': \u201e' + title + '\u201c';
  UIkit.modal('#pageRenameModal').show();
  setTimeout(() => { input.select(); }, 100);
};

const openPageDeleteModal = (slug, namespace, title, hasChildren) => {
  ensurePageDeleteModal();
  pageDeletePending = { slug, namespace, title, hasChildren };
  document.getElementById('pageDeleteConfirmText').textContent =
    (window.transConfirmPageDelete || 'Really delete \u201e:title\u201c (/:slug)?').replace(':title', title).replace(':slug', slug);
  document.getElementById('pageDeleteSubtreeWarning').hidden = !hasChildren;
  UIkit.modal('#pageDeleteConfirmModal').show();
};

const executePageRename = async (oldSlug, namespace, newSlug) => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (!csrfToken) {
    throw new Error(window.transCsrfMissing || 'CSRF token missing. Please reload the page.');
  }
  const response = await fetch(
    withBase('/admin/pages/' + encodeURIComponent(oldSlug) + '/rename?namespace=' + encodeURIComponent(namespace)),
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ newSlug })
    }
  );
  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}));
    throw new Error(errorData.error || 'Error: ' + response.status);
  }
  return response.json();
};

const executePageDelete = async (slug, namespace) => {
  const url = '/admin/pages/' + encodeURIComponent(slug) + '?namespace=' + encodeURIComponent(namespace);
  const response = await apiFetch(url, { method: 'DELETE' });
  if (response.status === 204) return;
  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}));
    throw new Error(errorData.error || 'Error: ' + response.status);
  }
};

let menuAssignPending = null;

const updateMenuBadge = (pageId, menuLabel) => {
  const existing = document.querySelector('[data-menu-badge="' + pageId + '"]');
  if (menuLabel) {
    if (existing) {
      existing.textContent = menuLabel;
      existing.title = (window.transTopMenu || 'Top menu') + ': ' + menuLabel;
    } else {
      const row = document.querySelector('[data-page-row="' + pageId + '"]');
      if (row) {
        let meta = row.querySelector('.uk-flex.uk-flex-middle.uk-flex-wrap');
        if (!meta) {
          meta = document.createElement('div');
          meta.className = 'uk-flex uk-flex-middle uk-flex-wrap';
          const actions = row.querySelector('.page-tree-actions');
          if (actions) {
            row.insertBefore(meta, actions);
          } else {
            row.appendChild(meta);
          }
        }
        const badge = document.createElement('span');
        badge.className = 'uk-label uk-label-success uk-margin-small-left';
        badge.textContent = menuLabel;
        badge.setAttribute('data-menu-badge', pageId);
        badge.title = (window.transTopMenu || 'Top menu') + ': ' + menuLabel;
        meta.appendChild(badge);
      }
    }
  } else {
    if (existing) existing.remove();
  }
};

const updateStatusBadge = (pageId, status) => {
  const existing = document.querySelector('[data-status-badge="' + pageId + '"]');
  if (!existing) return;
  existing.className = 'uk-label uk-margin-small-left';
  if (status === 'published') {
    existing.classList.add('uk-label-success');
    existing.textContent = window.transStatusPublished || 'Published';
  } else if (status === 'archived') {
    existing.classList.add('uk-label-warning');
    existing.textContent = window.transStatusArchived || 'Archived';
  } else {
    existing.textContent = window.transStatusDraft || 'Draft';
  }
};

let pageStatusPending = null;

const ensurePageStatusModal = () => {
  if (document.getElementById('pageStatusModal')) return;
  const el = document.createElement('div');
  el.id = 'pageStatusModal';
  el.setAttribute('uk-modal', '');
  el.innerHTML = [
    '<div class="uk-modal-dialog uk-modal-body">',
    '  <h3 class="uk-modal-title">' + (window.transChangeStatus || 'Change status') + '</h3>',
    '  <p id="pageStatusHint" class="uk-text-meta"></p>',
    '  <label class="uk-form-label" for="pageStatusSelect">' + (window.transSelectStatus || 'Select status') + '</label>',
    '  <select id="pageStatusSelect" class="uk-select">',
    '    <option value="draft">' + (window.transStatusDraft || 'Draft') + '</option>',
    '    <option value="published">' + (window.transStatusPublished || 'Published') + '</option>',
    '    <option value="archived">' + (window.transStatusArchived || 'Archived') + '</option>',
    '  </select>',
    '  <div id="pageStatusError" class="uk-text-danger uk-margin-small-top" hidden></div>',
    '  <div class="uk-margin-top uk-text-right">',
    '    <button class="uk-button uk-button-default uk-modal-close" type="button">' + (window.transCancel || 'Cancel') + '</button>',
    '    <button id="pageStatusSave" class="uk-button uk-button-primary uk-margin-small-left" type="button">' + (window.transSave || 'Save') + '</button>',
    '  </div>',
    '</div>'
  ].join('\n');
  document.body.appendChild(el);

  el.addEventListener('hidden', () => { pageStatusPending = null; });

  document.getElementById('pageStatusSave').addEventListener('click', async () => {
    if (!pageStatusPending) return;
    const { node } = pageStatusPending;
    const select = document.getElementById('pageStatusSelect');
    const errorEl = document.getElementById('pageStatusError');
    const newStatus = select.value;
    const csrfToken = getCsrfToken();

    if (!csrfToken) {
      errorEl.textContent = window.transCsrfMissing || 'CSRF token missing. Please reload the page.';
      errorEl.hidden = false;
      return;
    }

    errorEl.hidden = true;
    const namespaceParam = '?namespace=' + encodeURIComponent(node.namespace || 'default');

    try {
      const resp = await fetch(
        withBase('/admin/pages/' + node.id + '/status' + namespaceParam),
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ status: newStatus })
        }
      );
      if (!resp.ok) {
        const err = await resp.json().catch(() => ({}));
        throw new Error(err.error || (window.transUnknownError || 'Unknown error'));
      }
      node.status = newStatus;
      updateStatusBadge(node.id, newStatus);
      UIkit.modal('#pageStatusModal').hide();
      window.notify(window.transPageStatusUpdated || 'Page status updated.', 'success');
    } catch (error) {
      errorEl.textContent = error.message || (window.transUnknownError || 'Unknown error');
      errorEl.hidden = false;
    }
  });
};

const openPageStatusModal = (node) => {
  ensurePageStatusModal();
  pageStatusPending = { node };
  const hint = document.getElementById('pageStatusHint');
  hint.textContent = (node.title || node.slug) + ' (/' + node.slug + ')';
  const select = document.getElementById('pageStatusSelect');
  select.value = node.status || 'draft';
  document.getElementById('pageStatusError').hidden = true;
  UIkit.modal('#pageStatusModal').show();
};

const ensureMenuAssignModal = () => {
  if (document.getElementById('menuAssignModal')) return;
  const el = document.createElement('div');
  el.id = 'menuAssignModal';
  el.setAttribute('uk-modal', '');
  el.innerHTML = [
    '<div class="uk-modal-dialog uk-modal-body">',
    '  <h3 class="uk-modal-title">' + (window.transAssignTopMenu || 'Assign top menu') + '</h3>',
    '  <p id="menuAssignHint" class="uk-text-meta"></p>',
    '  <label class="uk-form-label" for="menuAssignSelect">' + (window.transSelectMenu || 'Select menu') + '</label>',
    '  <select id="menuAssignSelect" class="uk-select"></select>',
    '  <div id="menuAssignError" class="uk-text-danger uk-margin-small-top" hidden></div>',
    '  <div class="uk-margin-top uk-text-right">',
    '    <button class="uk-button uk-button-default uk-modal-close" type="button">' + (window.transCancel || 'Cancel') + '</button>',
    '    <button id="menuAssignSave" class="uk-button uk-button-primary uk-margin-small-left" type="button">' + (window.transSave || 'Save') + '</button>',
    '  </div>',
    '</div>'
  ].join('\n');
  document.body.appendChild(el);

  el.addEventListener('hidden', () => { menuAssignPending = null; });

  document.getElementById('menuAssignSave').addEventListener('click', async () => {
    if (!menuAssignPending) return;
    const { node, currentAssignment, menuAssignmentMap, availableMenus } = menuAssignPending;
    const select = document.getElementById('menuAssignSelect');
    const errorEl = document.getElementById('menuAssignError');
    const selectedMenuId = select.value;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!csrfToken) {
      errorEl.textContent = window.transCsrfMissing || 'CSRF token missing. Please reload the page.';
      errorEl.hidden = false;
      return;
    }

    const namespaceParam = '?namespace=' + encodeURIComponent(node.namespace || 'default');

    try {
      if (selectedMenuId === '' && currentAssignment) {
        const resp = await fetch(
          withBase('/admin/menu-assignments/' + currentAssignment.assignmentId + namespaceParam),
          { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken } }
        );
        if (!resp.ok && resp.status !== 204) {
          const err = await resp.json().catch(() => ({}));
          throw new Error(err.error || 'Error: ' + resp.status);
        }
        delete menuAssignmentMap[node.id];
        updateMenuBadge(node.id, null);
        window.notify(window.transMenuAssignmentRemoved || 'Menu assignment removed', 'success');

      } else if (selectedMenuId !== '' && !currentAssignment) {
        const resp = await fetch(
          withBase('/admin/menu-assignments' + namespaceParam),
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
              menuId: parseInt(selectedMenuId, 10),
              pageId: node.id,
              slot: 'main',
              locale: node.language || 'de',
              isActive: true
            })
          }
        );
        if (!resp.ok) {
          const err = await resp.json().catch(() => ({}));
          throw new Error(err.error || 'Error: ' + resp.status);
        }
        const data = await resp.json();
        const menuLabel = (availableMenus.find(m => String(m.id) === selectedMenuId) || {}).label || '';
        menuAssignmentMap[node.id] = {
          assignmentId: data.assignment.id,
          menuId: data.assignment.menuId,
          menuLabel: menuLabel,
          locale: data.assignment.locale,
          isActive: data.assignment.isActive
        };
        updateMenuBadge(node.id, menuLabel);
        window.notify(window.transMenuAssignmentSaved || 'Menu assignment saved', 'success');

      } else if (selectedMenuId !== '' && currentAssignment) {
        const resp = await fetch(
          withBase('/admin/menu-assignments/' + currentAssignment.assignmentId + namespaceParam),
          {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
              menuId: parseInt(selectedMenuId, 10),
              pageId: node.id,
              slot: 'main',
              locale: node.language || 'de',
              isActive: true
            })
          }
        );
        if (!resp.ok) {
          const err = await resp.json().catch(() => ({}));
          throw new Error(err.error || 'Error: ' + resp.status);
        }
        const data = await resp.json();
        const menuLabel = (availableMenus.find(m => String(m.id) === selectedMenuId) || {}).label || '';
        menuAssignmentMap[node.id] = {
          assignmentId: data.assignment.id,
          menuId: data.assignment.menuId,
          menuLabel: menuLabel,
          locale: data.assignment.locale,
          isActive: data.assignment.isActive
        };
        updateMenuBadge(node.id, menuLabel);
        window.notify(window.transMenuAssignmentSaved || 'Menu assignment saved', 'success');
      }

      UIkit.modal('#menuAssignModal').hide();
    } catch (error) {
      errorEl.textContent = error.message || (window.transMenuAssignmentFailed || 'Menu assignment could not be saved.');
      errorEl.hidden = false;
    }
  });
};

const openMenuAssignModal = (node, availableMenus, menuAssignmentMap) => {
  ensureMenuAssignModal();
  const select = document.getElementById('menuAssignSelect');
  const hint = document.getElementById('menuAssignHint');
  const errorEl = document.getElementById('menuAssignError');
  errorEl.hidden = true;
  errorEl.textContent = '';

  select.innerHTML = '<option value="">' + (window.transNoMenu || '\u2014 No menu \u2014') + '</option>';
  availableMenus.forEach(menu => {
    const opt = document.createElement('option');
    opt.value = menu.id;
    opt.textContent = menu.label + (menu.locale ? ' (' + menu.locale + ')' : '');
    select.appendChild(opt);
  });

  const currentAssignment = node.id ? menuAssignmentMap[node.id] : null;
  if (currentAssignment) {
    select.value = String(currentAssignment.menuId);
  } else {
    select.value = '';
  }

  hint.textContent = (window.transCurrentPage || 'Page') + ': \u201e' + (node.title || node.slug) + '\u201c';
  menuAssignPending = { node, currentAssignment, menuAssignmentMap, availableMenus };

  UIkit.modal('#menuAssignModal').show();
};

window.openMenuAssignModal = openMenuAssignModal;
window.openPageStatusModal = openPageStatusModal;
window.updateMenuBadge = updateMenuBadge;

export {
  initPageTypeDefaultsForm,
  normalizeTreeNamespace,
  normalizeTreePosition,
  MAX_TREE_DEPTH,
  flattenTreeNodes,
  sortTree,
  buildTreeFromFlatPages,
  showUpgradeModal,
  apiFetch,
  updateMenuBadge,
  updateStatusBadge,
  openPageStatusModal,
  openMenuAssignModal,
  openPageRenameModal,
  openPageDeleteModal
};
