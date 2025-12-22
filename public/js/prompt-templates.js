/* global UIkit */

const table = document.getElementById('promptTemplatesTable');

if (table) {
  const tbody = table.querySelector('tbody');
  const modalEl = document.getElementById('promptTemplateModal');
  const form = document.getElementById('promptTemplateForm');
  const info = document.getElementById('promptTemplateInfo');
  const fields = {
    id: document.getElementById('promptTemplateId'),
    name: document.getElementById('promptTemplateName'),
    prompt: document.getElementById('promptTemplatePrompt')
  };

  const transEdit = window.transPromptTemplateEdit || 'Edit';
  const transSaved = window.transPromptTemplateSaved || 'Saved';
  const transSaveError = window.transPromptTemplateSaveError || 'Save failed';
  const transMissing = window.transPromptTemplateMissing || 'Template missing';
  const transInvalid = window.transPromptTemplateInvalid || 'Invalid data';

  const loadingText = table.dataset.loading || 'Loading...';
  const emptyText = table.dataset.empty || 'No templates.';
  const errorText = table.dataset.error || 'Unable to load templates.';

  let templates = [];

  const setStatusRow = (text, colSpan = 3) => {
    if (!tbody) return;
    tbody.innerHTML = '';
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = colSpan;
    cell.textContent = text;
    row.appendChild(cell);
    tbody.appendChild(row);
  };

  const truncateText = (value, maxLength = 240) => {
    const trimmed = String(value || '').trim();
    if (trimmed.length <= maxLength) {
      return trimmed;
    }
    return trimmed.slice(0, maxLength).trim() + '…';
  };

  const openModal = template => {
    if (!template) return;
    fields.id.value = template.id;
    fields.name.value = template.name;
    fields.prompt.value = template.prompt;
    if (info) {
      info.textContent = template.name;
    }
    if (modalEl && window.UIkit) {
      UIkit.modal(modalEl).show();
    }
  };

  const renderRows = () => {
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!templates.length) {
      setStatusRow(emptyText);
      return;
    }

    templates.forEach(template => {
      const row = document.createElement('tr');
      const nameCell = document.createElement('td');
      nameCell.textContent = template.name;

      const promptCell = document.createElement('td');
      const promptText = document.createElement('div');
      promptText.className = 'uk-text-small uk-text-muted';
      promptText.textContent = truncateText(template.prompt);
      promptCell.appendChild(promptText);

      const actionCell = document.createElement('td');
      actionCell.className = 'uk-text-center';
      const editButton = document.createElement('button');
      editButton.type = 'button';
      editButton.className = 'uk-button uk-button-default uk-button-small';
      editButton.textContent = transEdit;
      editButton.addEventListener('click', () => openModal(template));
      actionCell.appendChild(editButton);

      row.appendChild(nameCell);
      row.appendChild(promptCell);
      row.appendChild(actionCell);
      tbody.appendChild(row);
    });
  };

  const loadTemplates = async () => {
    setStatusRow(loadingText);
    try {
      const response = await window.apiFetch('/admin/prompt-templates/data');
      if (!response.ok) {
        setStatusRow(errorText);
        return;
      }
      const data = await response.json();
      templates = Array.isArray(data.templates) ? data.templates : [];
      renderRows();
    } catch (error) {
      setStatusRow(errorText);
    }
  };

  const updateTemplate = async (id, name, prompt) => {
    const response = await window.apiFetch(`/admin/prompt-templates/${id}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ name, prompt })
    });

    if (!response.ok) {
      const payload = await response.json().catch(() => null);
      const message = payload?.message || (response.status === 404 ? transMissing : transSaveError);
      window.notify(message, 'danger');
      return null;
    }

    const payload = await response.json();
    const template = payload?.template;
    if (!template) {
      window.notify(transSaveError, 'danger');
      return null;
    }

    window.notify(payload?.message || transSaved, 'success');
    return template;
  };

  if (form) {
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const id = Number.parseInt(fields.id.value, 10);
      const name = fields.name.value.trim();
      const prompt = fields.prompt.value.trim();

      if (!id || !name || !prompt) {
        window.notify(transInvalid, 'warning');
        return;
      }

      const updated = await updateTemplate(id, name, prompt);
      if (!updated) {
        return;
      }

      templates = templates.map(item => (item.id === updated.id ? updated : item));
      renderRows();
      if (modalEl && window.UIkit) {
        UIkit.modal(modalEl).hide();
      }
    });
  }

  loadTemplates();
}
