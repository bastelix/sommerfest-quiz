const container = document.querySelector('[data-username-blocklist]');

if (container) {
  const form = container.querySelector('[data-username-blocklist-form]');
  const input = form ? form.querySelector('input[name="term"]') : null;
  const submitButton = form ? form.querySelector('[data-username-blocklist-submit]') : null;
  const tableBody = container.querySelector('[data-username-blocklist-table]');
  const feedback = container.querySelector('[data-username-blocklist-feedback]');
  const createUrl = container.dataset.createUrl || '';
  const importUrl = container.dataset.importUrl || '';
  const csrfToken = container.dataset.csrf || '';
  const emptyMessage = container.dataset.emptyMessage || '';
  const defaultError = container.dataset.errorDefault || 'An error occurred.';
  const csrfError = container.dataset.csrfError || defaultError;
  const removeLabel = container.dataset.removeLabel || 'Remove';
  const importButtons = Array.from(container.querySelectorAll('[data-username-blocklist-import]'));

  if (feedback) {
    feedback.setAttribute('role', 'alert');
  }

  function clearFeedback() {
    if (!feedback) {
      return;
    }
    feedback.hidden = true;
    feedback.textContent = '';
    feedback.classList.remove('uk-alert-success', 'uk-alert-danger');
  }

  function showFeedback(type, message) {
    if (!feedback) {
      return;
    }
    feedback.hidden = false;
    feedback.textContent = message;
    feedback.classList.remove('uk-alert-success', 'uk-alert-danger');
    feedback.classList.add(type === 'error' ? 'uk-alert-danger' : 'uk-alert-success');
  }

  function resolveErrorMessage(payload) {
    if (payload && typeof payload.error === 'string') {
      if (payload.error === 'csrf') {
        return csrfError;
      }
      return payload.error;
    }
    return defaultError;
  }

  function setLoading(isLoading) {
    if (!submitButton) {
      return;
    }
    submitButton.disabled = isLoading;
    submitButton.classList.toggle('uk-disabled', isLoading);
  }

  function setImportLoading(isLoading) {
    importButtons.forEach((button) => {
      button.disabled = isLoading;
      button.classList.toggle('uk-disabled', isLoading);
      if (isLoading) {
        button.setAttribute('aria-busy', 'true');
      } else {
        button.removeAttribute('aria-busy');
      }
    });
  }

  function removeEmptyRow() {
    if (!tableBody) {
      return;
    }
    const emptyRow = tableBody.querySelector('[data-empty-row]');
    if (emptyRow) {
      emptyRow.remove();
    }
  }

  function ensureEmptyRow() {
    if (!tableBody) {
      return;
    }
    const hasEntries = tableBody.querySelector('[data-entry-id]') !== null;
    if (hasEntries) {
      return;
    }
    const row = document.createElement('tr');
    row.setAttribute('data-empty-row', '');
    const cell = document.createElement('td');
    cell.colSpan = 3;
    cell.className = 'uk-text-meta';
    cell.textContent = emptyMessage;
    row.appendChild(cell);
    tableBody.appendChild(row);
  }

  function buildActionButton(entry) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'uk-button uk-button-text uk-text-danger';
    button.setAttribute('data-username-blocklist-delete', 'true');
    button.setAttribute('data-delete-url', `${createUrl}/${entry.id}`);
    button.setAttribute('data-term', entry.term);
    button.setAttribute('aria-label', `${removeLabel} ${entry.term}`);

    const icon = document.createElement('span');
    icon.setAttribute('uk-icon', 'icon: trash');
    button.appendChild(icon);

    const text = document.createElement('span');
    text.className = 'uk-visible@s uk-margin-small-left';
    text.textContent = removeLabel;
    button.appendChild(text);

    return button;
  }

  function appendEntry(entry) {
    if (!tableBody) {
      return;
    }
    removeEmptyRow();

    const row = document.createElement('tr');
    row.setAttribute('data-entry-id', String(entry.id));

    const termCell = document.createElement('td');
    termCell.className = 'uk-table-expand';
    const termSpan = document.createElement('span');
    termSpan.className = 'uk-text-bold';
    termSpan.setAttribute('data-term', '');
    termSpan.textContent = entry.term;
    termCell.appendChild(termSpan);
    row.appendChild(termCell);

    const createdCell = document.createElement('td');
    createdCell.className = 'uk-text-meta';
    createdCell.setAttribute('data-created', entry.created_at);
    createdCell.textContent = entry.created_at_display || '';
    row.appendChild(createdCell);

    const actionCell = document.createElement('td');
    actionCell.className = 'uk-table-shrink uk-text-right';
    actionCell.appendChild(buildActionButton(entry));
    row.appendChild(actionCell);

    tableBody.appendChild(row);
  }

  function setEntries(entries) {
    if (!tableBody) {
      return;
    }

    tableBody.innerHTML = '';

    if (!Array.isArray(entries) || entries.length === 0) {
      ensureEmptyRow();
      return;
    }

    entries.forEach((entry) => {
      appendEntry(entry);
    });
  }

  async function parseJson(response) {
    const text = await response.text();
    if (!text) {
      return null;
    }
    try {
      return JSON.parse(text);
    } catch (error) {
      return null;
    }
  }

  async function handleSubmit(event) {
    event.preventDefault();
    if (!input || !form) {
      return;
    }
    const term = input.value.trim();
    if (term === '') {
      showFeedback('error', defaultError);
      return;
    }

    clearFeedback();
    setLoading(true);

    try {
      const response = await fetch(createUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
        },
        body: JSON.stringify({ term }),
      });

      const payload = await parseJson(response);
      if (!response.ok) {
        showFeedback('error', resolveErrorMessage(payload));
        return;
      }

      if (payload && payload.entry) {
        appendEntry(payload.entry);
      }

      if (payload && typeof payload.message === 'string' && payload.message !== '') {
        showFeedback('success', payload.message);
      } else {
        showFeedback('success', '');
      }

      form.reset();
      if (input) {
        input.focus();
      }
    } catch (error) {
      showFeedback('error', defaultError);
    } finally {
      setLoading(false);
    }
  }

  async function handleImportClick(event) {
    const button = event.currentTarget;
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    const preset = button.getAttribute('data-preset');
    if (!preset || !importUrl) {
      return;
    }

    event.preventDefault();
    clearFeedback();
    setImportLoading(true);

    try {
      const response = await fetch(importUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
        },
        body: JSON.stringify({ preset }),
      });

      const payload = await parseJson(response);
      if (!response.ok) {
        const message = payload && typeof payload.error === 'string' ? payload.error : defaultError;
        showFeedback('error', message);
        return;
      }

      if (payload && Array.isArray(payload.entries)) {
        setEntries(payload.entries);
      } else {
        ensureEmptyRow();
      }

      if (payload && typeof payload.message === 'string') {
        showFeedback('success', payload.message);
      } else {
        showFeedback('success', '');
      }
    } catch (error) {
      showFeedback('error', defaultError);
    } finally {
      setImportLoading(false);
    }
  }

  async function handleDelete(event) {
    if (!(event.target instanceof Element)) {
      return;
    }
    const button = event.target.closest('[data-username-blocklist-delete]');
    if (!button) {
      return;
    }
    const url = button.getAttribute('data-delete-url');
    if (!url) {
      return;
    }
    event.preventDefault();

    clearFeedback();

    try {
      const response = await fetch(url, {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
        },
      });

      const payload = await parseJson(response);
      if (!response.ok) {
        showFeedback('error', resolveErrorMessage(payload));
        return;
      }

      const id = button.closest('tr')?.getAttribute('data-entry-id');
      if (id) {
        const row = tableBody ? tableBody.querySelector(`tr[data-entry-id="${id}"]`) : null;
        if (row) {
          row.remove();
        }
      }

      ensureEmptyRow();

      if (payload && typeof payload.message === 'string') {
        showFeedback('success', payload.message);
      }
    } catch (error) {
      showFeedback('error', defaultError);
    }
  }

  if (form) {
    form.addEventListener('submit', handleSubmit);
  }

  if (tableBody) {
    tableBody.addEventListener('click', handleDelete);
  }

  importButtons.forEach((button) => {
    button.addEventListener('click', handleImportClick);
  });
}
