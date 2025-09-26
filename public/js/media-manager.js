const basePath = window.basePath || '';
const apiFetch = window.apiFetch || ((path, options = {}) => fetch(withBase(path), options));
const notify = typeof window.notify === 'function'
  ? window.notify.bind(window)
  : (message) => window.alert(message);

function ready(callback) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', callback, { once: true });
  } else {
    callback();
  }
}

function withBase(path) {
  if (!path && path !== 0) return '';

  const normalized = String(path).trim();
  if (!normalized) return '';

  if (/^https?:\/\//i.test(normalized) || normalized.startsWith('//')) {
    const url = new URL(normalized.startsWith('//') ? `https:${normalized}` : normalized);
    if (url.origin !== window.location.origin) {
      throw new Error(`Refusing to use cross-origin URL: ${normalized}`);
    }
    return `${url.pathname}${url.search}${url.hash}`;
  }

  if (/^[a-z][a-z\d+\-.]*:/i.test(normalized)) {
    throw new Error(`Unsupported URL scheme: ${normalized}`);
  }

  if (!basePath) {
    return normalized;
  }

  const trimmedBase = basePath.replace(/\/$/, '');
  if (normalized.startsWith('/')) {
    return `${trimmedBase}${normalized}`;
  }

  return `${trimmedBase}/${normalized}`;
}

function getCsrfToken() {
  return (
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
    window.csrfToken ||
    ''
  );
}

function formatSize(bytes) {
  const size = Number(bytes);
  if (!Number.isFinite(size) || size <= 0) {
    return '0 B';
  }
  if (size >= 1024 * 1024) {
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
  }
  if (size >= 1024) {
    return `${Math.round(size / 1024)} KB`;
  }
  return `${Math.round(size)} B`;
}

function formatDate(isoString) {
  if (!isoString) return '';
  try {
    const date = new Date(isoString);
    if (!Number.isFinite(date.getTime())) {
      return isoString;
    }
    return date.toLocaleString();
  } catch (err) {
    return isoString;
  }
}

function parseJsonAttribute(element, attribute, fallback = {}) {
  const raw = element.getAttribute(attribute);
  if (!raw) return fallback;
  try {
    return JSON.parse(raw);
  } catch (err) {
    return fallback;
  }
}

ready(() => {
  const tab = document.querySelector('[data-media-tab]');
  if (!tab) return;

  const switcher = document.getElementById('adminSwitcher');
  let initialized = false;

  const init = () => {
    if (initialized) return;
    initialized = true;

    const root = tab.querySelector('[data-media-root]');
    if (!root) return;

    const fallbackTranslations = {
      renamePrompt: 'Enter a new filename:',
      deleteConfirm: 'Delete this file?',
      uploaded: 'File uploaded.',
      renamed: 'File renamed.',
      deleted: 'File deleted.',
      requestFailed: 'Action failed.',
      eventRequired: 'Please select an event first.',
      eventHint: '',
      noFiles: 'No files available.',
      download: 'Download',
      rename: 'Rename',
      delete: 'Delete',
      preview: 'Preview',
      selectPreview: 'Select a file to view its preview.',
      sizeLabel: 'Size',
      modifiedLabel: 'Modified',
      nameLabel: 'File',
      selectedLabel: 'Selected file:',
      uploading: 'Uploading…'
    };

    const translations = {
      ...fallbackTranslations,
      ...parseJsonAttribute(root, 'data-translations', {})
    };

    const limitText = tab.querySelector('[data-media-limit]');
    const scopeSelect = root.querySelector('[data-media-scope]');
    const searchInput = root.querySelector('[data-media-search]');
    const refreshBtn = root.querySelector('[data-media-refresh]');
    const prevBtn = root.querySelector('[data-media-prev]');
    const nextBtn = root.querySelector('[data-media-next]');
    const tableBody = root.querySelector('[data-media-table-body]');
    const paginationLabel = root.querySelector('[data-media-pagination]');
    const emptyMessage = root.querySelector('[data-media-empty]');
    const errorBox = root.querySelector('[data-media-error]');
    const eventHint = root.querySelector('[data-media-event-hint]');
    const dropZone = root.querySelector('[data-media-dropzone]');
    const chooseBtn = root.querySelector('[data-media-choose]');
    const fileInput = root.querySelector('[data-media-file]');
    const nameInput = root.querySelector('[data-media-name]');
    const form = root.querySelector('[data-media-form]');
    const progress = root.querySelector('[data-media-progress]');
    const statusText = root.querySelector('[data-media-upload-status]');
    const submitBtn = root.querySelector('[data-media-submit]');
    const selectedText = root.querySelector('[data-media-selected]');
    const previewImage = root.querySelector('[data-media-preview-image]');
    const previewPlaceholder = root.querySelector('[data-media-preview-placeholder]');
    const previewMeta = root.querySelector('[data-media-preview-meta]');
    const previewName = root.querySelector('[data-media-preview-name]');
    const previewSize = root.querySelector('[data-media-preview-size]');
    const previewModified = root.querySelector('[data-media-preview-modified]');
    const previewActions = root.querySelector('[data-media-preview-actions]');
    const previewDownload = root.querySelector('[data-media-download]');
    const previewRename = root.querySelector('[data-media-rename]');
    const previewDelete = root.querySelector('[data-media-delete]');

    const initialLimits = parseJsonAttribute(root, 'data-limits', {});
    const limitTemplate = limitText?.dataset.template || '';
    const initialEventUid = root.getAttribute('data-event-uid') || '';

    const state = {
      scope: 'global',
      search: '',
      page: 1,
      perPage: 20,
      totalPages: 1,
      files: [],
      selectedName: '',
      pendingFile: null,
      uploading: false,
      eventUid: initialEventUid,
      loading: false
    };

    function updateLimitText(limits) {
      if (!limitText || !limitTemplate) return;
      if (!limits || typeof limits !== 'object') return;
      const maxSize = Number(limits.maxSize || 0);
      const sizeMb = maxSize > 0 ? (maxSize / (1024 * 1024)).toFixed(1) : '0';
      const extensions = Array.isArray(limits.allowedExtensions)
        ? limits.allowedExtensions.join(', ')
        : '';
      let message = limitTemplate;
      if (message.includes('%s')) {
        message = message.replace('%s', sizeMb);
        message = message.replace('%s', extensions);
      }
      limitText.textContent = message;
    }

    updateLimitText(initialLimits);

    function clearError() {
      if (!errorBox) return;
      errorBox.hidden = true;
      errorBox.textContent = '';
    }

    function showError(message) {
      if (!errorBox) return;
      errorBox.textContent = message || translations.requestFailed;
      errorBox.hidden = false;
    }

    function updatePagination() {
      if (!paginationLabel) return;
      const total = Math.max(1, state.totalPages);
      const page = Math.min(state.page, total);
      paginationLabel.textContent = `${page} / ${total}`;
      if (prevBtn) prevBtn.disabled = page <= 1;
      if (nextBtn) nextBtn.disabled = page >= total;
    }

    function highlightRows() {
      if (!tableBody) return;
      tableBody.querySelectorAll('tr').forEach((row) => {
        row.classList.toggle('is-active', row.dataset.name === state.selectedName);
      });
    }

    function getSelectedFile() {
      return state.files.find((file) => file.name === state.selectedName) || null;
    }

    function updatePreview(file) {
      if (!previewImage || !previewPlaceholder || !previewMeta || !previewActions) return;
      if (!file) {
        previewImage.hidden = true;
        previewImage.src = '';
        previewPlaceholder.hidden = false;
        previewMeta.hidden = true;
        previewActions.hidden = true;
        if (previewDownload) {
          previewDownload.href = '#';
          previewDownload.removeAttribute('download');
        }
        return;
      }
      previewPlaceholder.hidden = true;
      const url = withBase(file.url || file.path || '');
      previewImage.src = url;
      previewImage.alt = `${translations.preview}: ${file.name}`;
      previewImage.hidden = false;
      if (previewName) previewName.textContent = file.name;
      if (previewSize) previewSize.textContent = formatSize(file.size);
      if (previewModified) previewModified.textContent = formatDate(file.modified);
      previewMeta.hidden = false;
      previewActions.hidden = false;
      if (previewDownload) {
        previewDownload.href = url || '#';
        previewDownload.setAttribute('download', file.name || '');
      }
    }

    function renderFiles() {
      if (!tableBody) return;
      tableBody.innerHTML = '';
      if (!Array.isArray(state.files) || state.files.length === 0) {
        if (emptyMessage) {
          emptyMessage.textContent = translations.noFiles;
          emptyMessage.hidden = false;
        }
        updatePreview(null);
        highlightRows();
        return;
      }
      if (emptyMessage) emptyMessage.hidden = true;
      state.files.forEach((file) => {
        const row = document.createElement('tr');
        row.dataset.name = file.name || '';
        row.addEventListener('click', () => selectFile(file));
        row.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            selectFile(file);
          }
        });

        const nameTd = document.createElement('td');
        const nameBtn = document.createElement('button');
        nameBtn.type = 'button';
        nameBtn.className = 'uk-button uk-button-text uk-text-left media-select';
        nameBtn.textContent = file.name;
        nameBtn.setAttribute('aria-label', `${translations.preview}: ${file.name}`);
        nameBtn.addEventListener('click', (event) => {
          event.stopPropagation();
          selectFile(file);
        });
        nameTd.appendChild(nameBtn);

        const sizeTd = document.createElement('td');
        sizeTd.textContent = formatSize(file.size);
        const modifiedTd = document.createElement('td');
        modifiedTd.textContent = formatDate(file.modified);

        const actionsTd = document.createElement('td');
        actionsTd.className = 'media-actions';
        const downloadLink = document.createElement('a');
        downloadLink.href = withBase(file.url || file.path || '#');
        downloadLink.className = 'uk-button uk-button-default uk-button-small';
        downloadLink.textContent = translations.download;
        downloadLink.setAttribute('download', file.name || '');
        downloadLink.addEventListener('click', (event) => event.stopPropagation());
        actionsTd.appendChild(downloadLink);

        const renameBtn = document.createElement('button');
        renameBtn.type = 'button';
        renameBtn.className = 'uk-button uk-button-default uk-button-small';
        renameBtn.textContent = translations.rename;
        renameBtn.addEventListener('click', (event) => {
          event.stopPropagation();
          handleRename(file);
        });
        actionsTd.appendChild(renameBtn);

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'uk-button uk-button-danger uk-button-small';
        deleteBtn.textContent = translations.delete;
        deleteBtn.addEventListener('click', (event) => {
          event.stopPropagation();
          handleDelete(file);
        });
        actionsTd.appendChild(deleteBtn);

        row.appendChild(nameTd);
        row.appendChild(sizeTd);
        row.appendChild(modifiedTd);
        row.appendChild(actionsTd);
        tableBody.appendChild(row);
      });
      highlightRows();
      let selected = getSelectedFile();
      if (state.selectedName && !selected) {
        state.selectedName = '';
        highlightRows();
        selected = null;
      }
      if (!selected) {
        updatePreview(null);
      } else {
        updatePreview(selected);
      }
    }

    function updateEventHint() {
      if (!eventHint) return;
      const visible = state.scope === 'event' && !state.eventUid;
      eventHint.hidden = !visible;
      if (visible) {
        eventHint.textContent = translations.eventHint || translations.eventRequired;
      }
    }

    function refreshEventOption() {
      if (!scopeSelect) return;
      const eventOption = scopeSelect.querySelector('option[value="event"]');
      if (eventOption) {
        eventOption.disabled = !state.eventUid;
      }
    }

    function updateSelectedLabel() {
      if (!selectedText) return;
      if (!state.pendingFile) {
        selectedText.hidden = true;
        selectedText.textContent = '';
        return;
      }
      selectedText.hidden = false;
      selectedText.textContent = `${translations.selectedLabel} ${state.pendingFile.name}`;
    }

    function updateUploadState() {
      const needsEvent = state.scope === 'event' && !state.eventUid;
      const canUpload = !!state.pendingFile && !state.uploading && !needsEvent;
      if (submitBtn) submitBtn.disabled = !canUpload;
      if (dropZone) {
        dropZone.classList.toggle('is-disabled', state.uploading);
        dropZone.setAttribute('aria-disabled', state.uploading ? 'true' : 'false');
        dropZone.tabIndex = state.uploading ? -1 : 0;
      }
      updateEventHint();
      updateSelectedLabel();
    }

    function selectFile(file) {
      state.selectedName = file.name || '';
      highlightRows();
      updatePreview(file);
    }

    async function fetchJson(path, options = {}) {
      const headers = {
        Accept: 'application/json',
        ...(options.headers || {})
      };
      const response = await apiFetch(path, { ...options, headers });
      let data = {};
      try {
        data = await response.clone().json();
      } catch (err) {
        data = {};
      }
      if (!response.ok) {
        const error = new Error(data.error || translations.requestFailed);
        error.data = data;
        throw error;
      }
      return data;
    }

    async function loadFiles() {
      if (state.scope === 'event' && !state.eventUid) {
        state.files = [];
        state.page = 1;
        state.totalPages = 1;
        renderFiles();
        showError(translations.eventRequired);
        updatePagination();
        return;
      }
      state.loading = true;
      clearError();
      if (root) root.setAttribute('aria-busy', 'true');
      const params = new URLSearchParams({
        scope: state.scope,
        page: String(state.page),
        perPage: String(state.perPage)
      });
      if (state.search) params.set('search', state.search);
      if (state.scope === 'event' && state.eventUid) {
        params.set('event', state.eventUid);
      }
      try {
        const data = await fetchJson(`/admin/media/files?${params.toString()}`);
        state.files = Array.isArray(data.files) ? data.files : [];
        const pagination = data.pagination || {};
        state.page = Number(pagination.page) || 1;
        state.totalPages = Number(pagination.totalPages) || 1;
        updateLimitText(data.limits || {});
        renderFiles();
        clearError();
      } catch (err) {
        state.files = [];
        renderFiles();
        showError(err.message);
      } finally {
        state.loading = false;
        if (root) root.setAttribute('aria-busy', 'false');
        updatePagination();
      }
    }

    function clearPendingFile() {
      state.pendingFile = null;
      if (fileInput) fileInput.value = '';
      if (nameInput) nameInput.value = '';
      if (progress) {
        progress.hidden = true;
        progress.value = 0;
      }
      if (statusText) {
        statusText.textContent = '';
        statusText.classList.remove('uk-text-danger');
      }
      updateUploadState();
    }

    function handleFiles(fileList, autoUpload) {
      if (!fileList || !fileList.length) return;
      const file = fileList[0];
      state.pendingFile = file;
      updateUploadState();
      if (autoUpload) {
        startUpload(file);
      }
    }

    function startUpload(file) {
      if (!file || state.uploading) return;
      if (state.scope === 'event' && !state.eventUid) {
        showError(translations.eventRequired);
        return;
      }
      const formData = new FormData();
      formData.append('file', file);
      formData.append('scope', state.scope);
      if (state.scope === 'event' && state.eventUid) {
        formData.append('event', state.eventUid);
      }
      const nameValue = nameInput?.value?.trim();
      if (nameValue) {
        formData.append('name', nameValue);
      }

      const xhr = new XMLHttpRequest();
      xhr.open('POST', withBase('/admin/media/upload'));
      xhr.withCredentials = true;
      xhr.setRequestHeader('X-Requested-With', 'fetch');
      const token = getCsrfToken();
      if (token) {
        xhr.setRequestHeader('X-CSRF-Token', token);
      }

      state.uploading = true;
      updateUploadState();
      if (progress) {
        progress.hidden = false;
        progress.value = 0;
      }
      if (statusText) {
        statusText.textContent = translations.uploading;
        statusText.classList.remove('uk-text-danger');
      }

      xhr.upload.addEventListener('progress', (event) => {
        if (!progress || !event.lengthComputable) return;
        const percentage = Math.round((event.loaded / event.total) * 100);
        progress.value = percentage;
      });

      xhr.addEventListener('error', () => {
        state.uploading = false;
        notify(translations.requestFailed, 'danger');
        if (statusText) {
          statusText.textContent = translations.requestFailed;
          statusText.classList.add('uk-text-danger');
        }
        updateUploadState();
      });

      xhr.addEventListener('load', () => {
        state.uploading = false;
        let response = {};
        try {
          response = JSON.parse(xhr.responseText || '{}');
        } catch (err) {
          response = {};
        }
        if (xhr.status >= 200 && xhr.status < 300) {
          notify(translations.uploaded, 'success');
          updateLimitText(response.limits || {});
          clearPendingFile();
          loadFiles();
        } else {
          const errorMessage = response.error || translations.requestFailed;
          if (statusText) {
            statusText.textContent = errorMessage;
            statusText.classList.add('uk-text-danger');
          }
          notify(errorMessage, 'danger');
        }
        updateUploadState();
      });

      xhr.send(formData);
    }

    async function handleRename(file) {
      const newName = window.prompt(translations.renamePrompt, file.name);
      if (!newName || newName === file.name) {
        return;
      }
      try {
        const data = await fetchJson('/admin/media/rename', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            scope: state.scope,
            oldName: file.name,
            newName,
            event: state.scope === 'event' ? state.eventUid : ''
          })
        });
        updateLimitText(data.limits || {});
        notify(translations.renamed, 'success');
        state.selectedName = newName;
        await loadFiles();
      } catch (err) {
        notify(err.message, 'danger');
      }
    }

    async function handleDelete(file) {
      if (!window.confirm(translations.deleteConfirm)) {
        return;
      }
      try {
        const data = await fetchJson('/admin/media/delete', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            scope: state.scope,
            name: file.name,
            event: state.scope === 'event' ? state.eventUid : ''
          })
        });
        updateLimitText(data.limits || {});
        notify(translations.deleted, 'success');
        if (state.selectedName === file.name) {
          state.selectedName = '';
          updatePreview(null);
        }
        await loadFiles();
      } catch (err) {
        notify(err.message, 'danger');
      }
    }

    if (scopeSelect) {
      scopeSelect.addEventListener('change', () => {
        state.scope = scopeSelect.value || 'global';
        state.page = 1;
        updateUploadState();
        loadFiles();
      });
    }

    if (searchInput) {
      let searchTimer = null;
      searchInput.addEventListener('input', () => {
        state.search = searchInput.value.trim();
        state.page = 1;
        if (searchTimer) window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => loadFiles(), 300);
      });
    }

    refreshBtn?.addEventListener('click', () => {
      state.page = 1;
      loadFiles();
    });

    prevBtn?.addEventListener('click', () => {
      if (state.page <= 1) return;
      state.page -= 1;
      loadFiles();
    });

    nextBtn?.addEventListener('click', () => {
      if (state.page >= state.totalPages) return;
      state.page += 1;
      loadFiles();
    });

    chooseBtn?.addEventListener('click', () => {
      if (state.uploading) return;
      fileInput?.click();
    });

    fileInput?.addEventListener('change', (event) => {
      handleFiles(event.target.files, false);
    });

    if (dropZone) {
      dropZone.addEventListener('dragover', (event) => {
        if (state.uploading) return;
        event.preventDefault();
        dropZone.classList.add('is-dragover');
      });
      dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('is-dragover');
      });
      dropZone.addEventListener('drop', (event) => {
        if (state.uploading) return;
        event.preventDefault();
        dropZone.classList.remove('is-dragover');
        if (event.dataTransfer?.files?.length) {
          handleFiles(event.dataTransfer.files, true);
        }
      });
      dropZone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          if (!state.uploading) {
            fileInput?.click();
          }
        }
      });
    }

    form?.addEventListener('submit', (event) => {
      event.preventDefault();
      if (state.pendingFile) {
        startUpload(state.pendingFile);
      }
    });

    previewRename?.addEventListener('click', () => {
      const file = getSelectedFile();
      if (file) {
        handleRename(file);
      }
    });

    previewDelete?.addEventListener('click', () => {
      const file = getSelectedFile();
      if (file) {
        handleDelete(file);
      }
    });

    document.addEventListener('current-event-changed', (event) => {
      const detail = event.detail || {};
      state.eventUid = detail.uid || '';
      refreshEventOption();
      updateUploadState();
      if (state.scope === 'event') {
        state.page = 1;
        loadFiles();
      }
    });

    refreshEventOption();
    updateUploadState();
    updatePagination();
    loadFiles();
  };

  if (tab.classList.contains('uk-active')) {
    init();
  }

  if (switcher && window.UIkit?.util?.on) {
    window.UIkit.util.on(switcher, 'shown', (event) => {
      if (event?.target === tab) {
        init();
      }
    });
  } else {
    init();
  }
});
