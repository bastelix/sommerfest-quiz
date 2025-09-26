/* global UIkit */
(function () {
  const config = window.mediaConfig || {};
  const basePath = config.basePath || '';
  const limits = config.limits || {};
  const translations = config.translations || {};

  const scopeSelect = document.getElementById('mediaScope');
  const searchInput = document.getElementById('mediaSearch');
  const refreshBtn = document.getElementById('mediaRefresh');
  const uploadForm = document.getElementById('mediaUploadForm');
  const uploadInput = document.getElementById('mediaFile');
  const uploadMessage = document.getElementById('mediaUploadMessage');
  const uploadName = document.getElementById('mediaName');
  const tableBody = document.getElementById('mediaTableBody');
  const paginationInfo = document.getElementById('mediaPagination');
  const prevBtn = document.getElementById('mediaPrev');
  const nextBtn = document.getElementById('mediaNext');
  const emptyHint = document.getElementById('mediaEmpty');
  const errorBox = document.getElementById('mediaError');

  const state = {
    scope: scopeSelect ? scopeSelect.value : 'global',
    search: '',
    page: 1,
    perPage: 20,
    totalPages: 1,
    eventUid: config.eventUid || ''
  };

  const formatSize = (size) => {
    if (!Number.isFinite(size)) return '0 B';
    if (size >= 1024 * 1024) {
      return (size / (1024 * 1024)).toFixed(1) + ' MB';
    }
    if (size >= 1024) {
      return Math.round(size / 1024) + ' KB';
    }
    return Math.round(size) + ' B';
  };

  const formatDate = (iso) => {
    try {
      const date = new Date(iso);
      if (!Number.isFinite(date.getTime())) return iso;
      return date.toLocaleString();
    } catch (e) {
      return iso;
    }
  };

  const notify = (message, status = 'primary') => {
    if (typeof UIkit !== 'undefined' && UIkit.notification) {
      UIkit.notification({ message, status, pos: 'top-center', timeout: 2000 });
    } else {
      window.alert(message);
    }
  };

  const csrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  };

  const buildUrl = (path) => {
    if (!basePath) return path;
    if (path.startsWith('http')) return path;
    return basePath.replace(/\/$/, '') + path;
  };

  const request = async (path, options = {}) => {
    const headers = Object.assign({}, options.headers || {}, {
      'X-Requested-With': 'fetch'
    });
    const token = csrfToken();
    if (token) {
      headers['X-CSRF-Token'] = token;
    }

    const opts = Object.assign({
      credentials: 'same-origin',
      cache: 'no-store'
    }, options, { headers });

    const res = await fetch(buildUrl(path), opts);
    const contentType = res.headers.get('Content-Type') || '';
    const isJson = contentType.includes('application/json');
    const data = isJson ? await res.json().catch(() => ({})) : {};
    if (!res.ok) {
      const err = new Error(data.error || translations.requestFailed || 'request-failed');
      err.data = data;
      err.status = res.status;
      throw err;
    }
    return data;
  };

  const renderFiles = (files, pagination) => {
    tableBody.innerHTML = '';
    if (!files.length) {
      emptyHint.hidden = false;
    } else {
      emptyHint.hidden = true;
    }

    files.forEach((file) => {
      const tr = document.createElement('tr');
      const nameTd = document.createElement('td');
      const link = document.createElement('a');
      link.href = buildUrl(file.path || file.url || '#');
      link.textContent = file.name;
      link.target = '_blank';
      nameTd.appendChild(link);

      const sizeTd = document.createElement('td');
      sizeTd.textContent = formatSize(file.size || 0);
      const dateTd = document.createElement('td');
      dateTd.textContent = formatDate(file.modified || '');
      const actionTd = document.createElement('td');

      const renameBtn = document.createElement('button');
      renameBtn.type = 'button';
      renameBtn.className = 'uk-button uk-button-default uk-button-small uk-margin-small-right';
      renameBtn.textContent = translations.rename || 'Rename';
      renameBtn.addEventListener('click', () => handleRename(file));

      const deleteBtn = document.createElement('button');
      deleteBtn.type = 'button';
      deleteBtn.className = 'uk-button uk-button-danger uk-button-small';
      deleteBtn.textContent = translations.delete || 'Delete';
      deleteBtn.addEventListener('click', () => handleDelete(file));

      actionTd.appendChild(renameBtn);
      actionTd.appendChild(deleteBtn);

      tr.appendChild(nameTd);
      tr.appendChild(sizeTd);
      tr.appendChild(dateTd);
      tr.appendChild(actionTd);
      tableBody.appendChild(tr);
    });

    state.totalPages = pagination.totalPages || 1;
    paginationInfo.textContent = `${pagination.page || 1} / ${state.totalPages}`;
    prevBtn.disabled = state.page <= 1;
    nextBtn.disabled = state.page >= state.totalPages;
  };

  const showError = (message) => {
    if (!errorBox) return;
    errorBox.textContent = message;
    errorBox.hidden = false;
  };

  const clearError = () => {
    if (!errorBox) return;
    errorBox.hidden = true;
    errorBox.textContent = '';
  };

  const loadFiles = async () => {
    if (state.scope === 'event' && !state.eventUid) {
      showError(translations.eventRequired || 'event required');
      tableBody.innerHTML = '';
      emptyHint.hidden = false;
      paginationInfo.textContent = '0 / 0';
      prevBtn.disabled = true;
      nextBtn.disabled = true;
      return;
    }

    clearError();
    const params = new URLSearchParams({
      scope: state.scope,
      page: String(state.page),
      perPage: String(state.perPage)
    });
    if (state.search) {
      params.set('search', state.search);
    }
    if (state.scope === 'event' && state.eventUid) {
      params.set('event', state.eventUid);
    }

    try {
      const data = await request('/admin/media/files?' + params.toString());
      renderFiles(data.files || [], data.pagination || {});
    } catch (err) {
      showError(err.message || translations.requestFailed || 'request failed');
    }
  };

  const handleRename = async (file) => {
    const promptText = translations.renamePrompt || 'Enter new filename:';
    const newName = window.prompt(promptText, file.name);
    if (!newName || newName === file.name) {
      return;
    }
    try {
      await request('/admin/media/rename', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          scope: state.scope,
          oldName: file.name,
          newName,
          event: state.scope === 'event' ? state.eventUid : ''
        })
      });
      notify(translations.renamed || 'Renamed');
      loadFiles();
    } catch (err) {
      showError(err.message || translations.requestFailed || 'request failed');
    }
  };

  const handleDelete = async (file) => {
    const confirmText = translations.deleteConfirm || 'Delete this file?';
    if (!window.confirm(confirmText)) {
      return;
    }
    try {
      await request('/admin/media/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          scope: state.scope,
          name: file.name,
          event: state.scope === 'event' ? state.eventUid : ''
        })
      });
      notify(translations.deleted || 'Deleted', 'danger');
      loadFiles();
    } catch (err) {
      showError(err.message || translations.requestFailed || 'request failed');
    }
  };

  const debounce = (fn, delay = 250) => {
    let timer = null;
    return (...args) => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => fn.apply(null, args), delay);
    };
  };

  const debouncedSearch = debounce((value) => {
    state.search = value.trim();
    state.page = 1;
    loadFiles();
  }, 300);

  if (scopeSelect) {
    scopeSelect.addEventListener('change', () => {
      state.scope = scopeSelect.value;
      state.page = 1;
      loadFiles();
    });
  }

  if (searchInput) {
    searchInput.addEventListener('input', (event) => {
      debouncedSearch(event.target.value);
    });
  }

  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadFiles();
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      if (state.page > 1) {
        state.page -= 1;
        loadFiles();
      }
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      if (state.page < state.totalPages) {
        state.page += 1;
        loadFiles();
      }
    });
  }

  if (uploadForm) {
    uploadForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!uploadInput || !uploadInput.files || !uploadInput.files.length) {
        showError(translations.requestFailed || 'request failed');
        return;
      }

      if (state.scope === 'event' && !state.eventUid) {
        showError(translations.eventRequired || 'event required');
        return;
      }

      const file = uploadInput.files[0];
      if (limits.maxSize && file.size > limits.maxSize) {
        showError(translations.requestFailed || 'file too large');
        return;
      }

      const formData = new FormData();
      formData.append('file', file);
      formData.append('scope', state.scope);
      if (state.scope === 'event' && state.eventUid) {
        formData.append('event', state.eventUid);
      }
      if (uploadName && uploadName.value.trim()) {
        formData.append('name', uploadName.value.trim());
      }

      try {
        await request('/admin/media/upload', {
          method: 'POST',
          body: formData
        });
        notify(translations.uploaded || 'Uploaded', 'success');
        uploadForm.reset();
        uploadMessage.textContent = '';
        loadFiles();
      } catch (err) {
        showError(err.message || translations.requestFailed || 'request failed');
      }
    });
  }

  if (uploadInput) {
    uploadInput.addEventListener('change', () => {
      const target = uploadInput.parentElement?.querySelector('input[type="text"]');
      if (target) {
        target.value = uploadInput.files?.[0]?.name || '';
      }
    });
  }

  loadFiles();
})();

