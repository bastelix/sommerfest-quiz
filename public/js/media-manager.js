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
      replaced: 'File replaced.',
      converted: 'Converted file created.',
      renamed: 'File renamed.',
      deleted: 'File deleted.',
      requestFailed: 'Action failed.',
      noFiles: 'No files available.',
      download: 'Download',
      replace: 'Replace',
      rename: 'Rename',
      delete: 'Delete',
      preview: 'Preview',
      selectPreview: 'Select a file to view its preview.',
      noPreview: 'Preview not available for this file type.',
      sizeLabel: 'Size',
      modifiedLabel: 'Modified',
      nameLabel: 'File',
      selectedLabel: 'Selected file:',
      uploading: 'Uploading…',
      metadataSaved: 'Metadata saved.',
      addTag: 'Add tag',
      clearFolder: 'Clear folder',
      saveFolder: 'Save folder',
      noTags: 'No tags yet.',
      filterTags: 'Filter by tags',
      filterFolder: 'Filter by folder',
      allFolders: 'All folders',
      noFolder: 'Without folder',
      clearFilters: 'Reset filters',
      copyUrl: 'Copy URL',
      copyUrlSuccess: 'Link copied to clipboard.',
      copyUrlFallback: 'Copy the URL manually from the field.',
      copyUrlError: 'Copy failed. Copy the link manually.',
      convert: 'Convert file',
      landingFilter: 'Landing page',
      landingAll: 'All landing pages',
      landingMissingHeading: 'Missing landing assets',
      landingMissingEmpty: 'All landing assets available.',
      landingPrefill: 'Prefill upload',
      landingOpenPdf: 'Open PDF',
      landingUsage: 'Landing pages',
      landingPreview: 'Landing references',
      landingMarkup: 'Landing content',
      landingSeo: 'Landing SEO'
    };

    const translations = {
      ...fallbackTranslations,
      ...parseJsonAttribute(root, 'data-translations', {})
    };

    const limitText = tab.querySelector('[data-media-limit]');
    const searchInput = root.querySelector('[data-media-search]');
    const refreshBtn = root.querySelector('[data-media-refresh]');
    const prevBtn = root.querySelector('[data-media-prev]');
    const nextBtn = root.querySelector('[data-media-next]');
    const tableBody = root.querySelector('[data-media-table-body]');
    const paginationLabel = root.querySelector('[data-media-pagination]');
    const emptyMessage = root.querySelector('[data-media-empty]');
    const errorBox = root.querySelector('[data-media-error]');
    const tagFilterContainer = root.querySelector('[data-media-filter-tags]');
    const folderFilterSelect = root.querySelector('[data-media-filter-folder]');
    const clearFiltersBtn = root.querySelector('[data-media-clear-filters]');
    const dropZone = root.querySelector('[data-media-dropzone]');
    const chooseBtn = root.querySelector('[data-media-choose]');
    const fileInput = root.querySelector('[data-media-file]');
    const nameInput = root.querySelector('[data-media-name]');
    const uploadTagsInput = root.querySelector('[data-media-upload-tags]');
    const uploadFolderInput = root.querySelector('[data-media-upload-folder]');
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
    const previewUrlContainer = root.querySelector('[data-media-preview-url]');
    const previewUrlInput = root.querySelector('[data-media-preview-url-input]');
    const previewCopyButton = root.querySelector('[data-media-preview-copy]');
    const previewDownload = root.querySelector('[data-media-download]');
    const previewReplace = root.querySelector('[data-media-replace]');
    const previewConvert = root.querySelector('[data-media-convert]');
    const previewConvertContainer = root.querySelector('[data-media-convert-container]');
    const previewRename = root.querySelector('[data-media-rename]');
    const previewDelete = root.querySelector('[data-media-delete]');
    const metadataPanel = root.querySelector('[data-media-metadata]');
    const metadataTagList = root.querySelector('[data-media-meta-tags]');
    const metadataTagInput = root.querySelector('[data-media-meta-tag-input]');
    const metadataAddTag = root.querySelector('[data-media-meta-add-tag]');
    const metadataFolderInput = root.querySelector('[data-media-meta-folder]');
    const metadataSaveFolder = root.querySelector('[data-media-meta-save-folder]');
    const metadataClearFolder = root.querySelector('[data-media-meta-clear-folder]');
    const landingFilterContainer = root.querySelector('[data-media-landing-filter]');
    const landingFilterSelect = root.querySelector('[data-media-landing]');
    const landingMissingCard = root.querySelector('[data-media-landing-missing-card]');
    const landingMissingList = root.querySelector('[data-media-landing-missing]');
    const landingMissingEmpty = root.querySelector('[data-media-landing-missing-empty]');
    const previewLanding = root.querySelector('[data-media-preview-landing]');
    const previewLandingList = root.querySelector('[data-media-preview-landing-list]');

    const defaultPreviewPlaceholderText = previewPlaceholder?.textContent || '';

    const initialLimits = parseJsonAttribute(root, 'data-limits', {});
    const limitTemplate = limitText?.dataset.template || '';
    const initialLandingSlugs = parseJsonAttribute(root, 'data-landing-slugs', []);
    const NO_FOLDER_FILTER = '__no_folder__';
    const allowedScopes = new Set(['global', 'project', 'event']);
    const scopeAttribute = root.getAttribute('data-media-scope') || '';
    const namespaceAttribute = root.getAttribute('data-media-namespace') || '';
    const initialScope = allowedScopes.has(scopeAttribute) ? scopeAttribute : 'global';
    const initialNamespace = typeof namespaceAttribute === 'string' ? namespaceAttribute.trim() : '';

    const state = {
      scope: initialScope,
      namespace: initialNamespace,
      search: '',
      page: 1,
      perPage: 20,
      totalPages: 1,
      files: [],
      selectedName: '',
      pendingFile: null,
      uploading: false,
      loading: false,
      filters: {
        tags: [],
        folder: '',
      },
      available: {
        tags: [],
        folders: [],
      },
      landing: {
        slug: '',
        slugs: Array.isArray(initialLandingSlugs) ? initialLandingSlugs : [],
        missing: [],
      },
      metadataSaving: false,
      replacing: false,
      converting: false
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
    renderLandingFilter();
    updateLandingFilterVisibility();
    renderLandingMissing();

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

    function tagKey(tag) {
      return String(tag ?? '').toLocaleLowerCase();
    }

    function sanitizeTagValue(value) {
      if (value === null || value === undefined) return '';
      let tag = String(value);
      if (typeof tag.normalize === 'function') {
        tag = tag.normalize('NFC');
      }
      tag = tag.replace(/[^\p{L}\p{N}\s_-]+/gu, ' ');
      tag = tag.replace(/\s+/g, ' ');
      tag = tag.trim();
      return tag;
    }

    function uniqueTags(list) {
      const result = [];
      const seen = new Set();
      list.forEach((item) => {
        const tag = sanitizeTagValue(item);
        if (!tag) {
          return;
        }
        const key = tagKey(tag);
        if (seen.has(key)) {
          return;
        }
        seen.add(key);
        result.push(tag);
      });
      return result;
    }

    function canConvert(file) {
      if (!file) {
        return false;
      }
      const name = String(file.name || '').toLowerCase();
      const extension = String(file.extension || '').toLowerCase();
      const ext = extension || (name.includes('.') ? name.split('.').pop() : '');
      if (!ext) {
        return false;
      }
      if (ext === 'webp' || ext === 'svg') {
        return false;
      }
      return ['png', 'jpg', 'jpeg', 'mp4'].includes(ext);
    }

    function canPreview(file) {
      if (!file) {
        return false;
      }
      const name = String(file.name || '').toLowerCase();
      const extension = String(file.extension || '').toLowerCase();
      const ext = extension || (name.includes('.') ? name.split('.').pop() : '');
      if (!ext) {
        return false;
      }
      return ['png', 'jpg', 'jpeg', 'webp', 'svg'].includes(ext);
    }

    function getSelectedFile() {
      return state.files.find((file) => file.name === state.selectedName) || null;
    }

    function renderLandingPreview(file) {
      if (!previewLanding || !previewLandingList) return;
      previewLandingList.innerHTML = '';
      const references = Array.isArray(file?.landing) ? file.landing : [];
      const titleCounts = references.reduce((accumulator, reference) => {
        if (!reference || typeof reference !== 'object') {
          return accumulator;
        }
        const rawTitle = typeof reference.title === 'string' ? reference.title.trim() : '';
        if (!rawTitle) {
          return accumulator;
        }
        accumulator[rawTitle] = (accumulator[rawTitle] || 0) + 1;
        return accumulator;
      }, {});
      if (!references.length) {
        previewLanding.hidden = true;
        return;
      }
      previewLanding.hidden = false;
      references.forEach((reference) => {
        if (!reference || typeof reference !== 'object') {
          return;
        }
        const item = document.createElement('li');
        item.className = 'media-landing-preview-item';
        const title = document.createElement('div');
        title.className = 'uk-text-small uk-text-bold';
        const rawTitle = typeof reference.title === 'string' ? reference.title.trim() : '';
        const slug = typeof reference.slug === 'string' ? reference.slug : '';
        const hasDuplicateTitle = rawTitle && titleCounts[rawTitle] > 1;
        let label = rawTitle || slug || '';
        if (hasDuplicateTitle && slug) {
          label = `${rawTitle} (${slug})`;
        }
        title.textContent = label || (slug ? String(slug) : '');
        item.appendChild(title);
        const meta = document.createElement('div');
        meta.className = 'uk-text-meta';
        const type = typeof reference.type === 'string' ? reference.type.toLowerCase() : '';
        const typeLabel = type === 'seo'
          ? (translations.landingSeo || fallbackTranslations.landingSeo)
          : (translations.landingMarkup || fallbackTranslations.landingMarkup);
        const field = reference.field ? String(reference.field) : '';
        meta.textContent = field ? `${typeLabel} · ${field}` : typeLabel;
        item.appendChild(meta);
        previewLandingList.appendChild(item);
      });
    }

    function updatePreview(file) {
      if (!previewImage || !previewPlaceholder || !previewMeta || !previewActions) return;
      const placeholderText = typeof defaultPreviewPlaceholderText === 'string'
        ? defaultPreviewPlaceholderText
        : (previewPlaceholder?.textContent || '');
      const convertContainer = typeof previewConvertContainer !== 'undefined'
        ? previewConvertContainer
        : null;
      const convertButton = typeof previewConvert !== 'undefined'
        ? previewConvert
        : null;
      const landingSection = typeof previewLanding !== 'undefined'
        ? previewLanding
        : null;
      const landingListEl = typeof previewLandingList !== 'undefined'
        ? previewLandingList
        : null;
      if (!file) {
        previewImage.hidden = true;
        previewImage.src = '';
        previewPlaceholder.textContent = placeholderText;
        previewPlaceholder.hidden = false;
        previewMeta.hidden = true;
        previewActions.hidden = true;
        if (previewUrlContainer) previewUrlContainer.hidden = true;
        if (previewUrlInput) previewUrlInput.value = '';
        if (previewCopyButton) {
          previewCopyButton.disabled = true;
          previewCopyButton.setAttribute('aria-disabled', 'true');
        }
        if (previewDownload) {
          previewDownload.href = '#';
          previewDownload.removeAttribute('download');
        }
        if (convertContainer) convertContainer.hidden = true;
        if (convertButton) {
          convertButton.disabled = true;
          convertButton.setAttribute('aria-disabled', 'true');
        }
        if (landingSection) landingSection.hidden = true;
        if (landingListEl) landingListEl.innerHTML = '';
        renderMetadataEditor(null);
        return;
      }
      const url = withBase(file.url || file.path || '');
      const hasUrl = !!url;
      const canPreviewFn = typeof canPreview === 'function'
        ? canPreview
        : (candidate) => {
          if (!candidate) {
            return false;
          }
          const name = String(candidate.name || '').toLowerCase();
          const extension = String(candidate.extension || '').toLowerCase();
          const ext = extension || (name.includes('.') ? name.split('.').pop() : '');
          if (!ext) {
            return false;
          }
          return ['png', 'jpg', 'jpeg', 'webp', 'svg'].includes(ext);
        };
      const previewable = canPreviewFn(file) && hasUrl;
      if (previewable) {
        previewImage.src = url;
        previewImage.alt = `${translations.preview}: ${file.name}`;
        previewImage.hidden = false;
        previewPlaceholder.textContent = placeholderText;
        previewPlaceholder.hidden = true;
      } else {
        previewImage.hidden = true;
        previewImage.src = '';
        previewPlaceholder.textContent = translations.noPreview || placeholderText;
        previewPlaceholder.hidden = false;
      }
      if (previewName) previewName.textContent = file.name;
      if (previewSize) previewSize.textContent = formatSize(file.size);
      if (previewModified) previewModified.textContent = formatDate(file.modified);
      previewMeta.hidden = false;
      previewActions.hidden = false;
      if (previewUrlInput) {
        previewUrlInput.value = hasUrl ? url : '';
      }
      if (previewUrlContainer) {
        previewUrlContainer.hidden = !hasUrl;
      }
      if (previewCopyButton) {
        previewCopyButton.disabled = !hasUrl;
        previewCopyButton.setAttribute('aria-disabled', previewCopyButton.disabled ? 'true' : 'false');
      }
      if (previewDownload) {
        previewDownload.href = url || '#';
        previewDownload.setAttribute('download', file.name || '');
      }
      const canConvertFn = typeof canConvert === 'function'
        ? canConvert
        : (candidate) => {
          if (!candidate) {
            return false;
          }
          const name = String(candidate.name || '').toLowerCase();
          const extension = String(candidate.extension || '').toLowerCase();
          const ext = extension || (name.includes('.') ? name.split('.').pop() : '');
          if (!ext) {
            return false;
          }
          if (ext === 'webp' || ext === 'svg') {
            return false;
          }
          return ['png', 'jpg', 'jpeg', 'mp4'].includes(ext);
        };
      const convertible = canConvertFn(file);
      if (convertContainer) {
        convertContainer.hidden = !convertible;
      }
      if (convertButton) {
        convertButton.disabled = !convertible || state.converting;
        convertButton.setAttribute('aria-disabled', convertButton.disabled ? 'true' : 'false');
      }
      if (typeof renderLandingPreview === 'function') {
        renderLandingPreview(file);
      }
      renderMetadataEditor(file);
    }

    async function handleCopyUrl() {
      const value = previewUrlInput?.value?.trim();
      if (!value) {
        return;
      }
      if (
        typeof navigator !== 'undefined' &&
        navigator.clipboard &&
        typeof navigator.clipboard.writeText === 'function'
      ) {
        try {
          await navigator.clipboard.writeText(value);
          notify(translations.copyUrlSuccess || translations.metadataSaved, 'success');
          return;
        } catch (err) {
          console.error(err);
        }
      }
      try {
        previewUrlInput?.focus?.();
        previewUrlInput?.select?.();
      } catch (err) {
        console.error(err);
      }
      notify(
        translations.copyUrlFallback ||
          translations.copyUrlError ||
          translations.requestFailed,
        'warning'
      );
    }

    function updateMetadataControlsState(file) {
      const disabled = state.metadataSaving || !file;
      [metadataTagInput, metadataAddTag, metadataFolderInput, metadataSaveFolder, metadataClearFolder]
        .filter(Boolean)
        .forEach((element) => {
          element.disabled = disabled;
        });
    }

    function renderMetadataTags(file) {
      if (!metadataTagList) return;
      metadataTagList.innerHTML = '';
      const tags = Array.isArray(file?.tags) ? file.tags : [];
      if (!tags.length) {
        const empty = document.createElement('span');
        empty.className = 'uk-text-meta';
        empty.textContent = translations.noTags || '';
        metadataTagList.appendChild(empty);
        return;
      }

      tags.forEach((tag) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'uk-button uk-button-default uk-button-xsmall uk-margin-small-right';
        button.textContent = tag;
        button.dataset.mediaRemoveTag = 'true';
        button.dataset.tag = tag;
        button.setAttribute('aria-label', `${translations.delete || 'Delete'}: ${tag}`);
        metadataTagList.appendChild(button);
      });
    }

    function renderMetadataEditor(file) {
      if (!metadataPanel) return;
      if (!file) {
        metadataPanel.hidden = true;
        if (metadataTagList) metadataTagList.innerHTML = '';
        if (metadataTagInput) metadataTagInput.value = '';
        if (metadataFolderInput) metadataFolderInput.value = '';
        updateMetadataControlsState(null);
        return;
      }

      metadataPanel.hidden = false;
      renderMetadataTags(file);
      if (metadataFolderInput && !state.metadataSaving) {
        metadataFolderInput.value = file.folder || '';
      }
      updateMetadataControlsState(file);
    }

    function setMetadataSaving(busy) {
      state.metadataSaving = !!busy;
      updateMetadataControlsState(getSelectedFile());
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

        if (Array.isArray(file.tags) && file.tags.length) {
          const tagWrap = document.createElement('div');
          tagWrap.className = 'media-file-tags uk-margin-small-top';
          file.tags.forEach((tag) => {
            const badge = document.createElement('span');
            badge.className = 'uk-label uk-label-light uk-margin-small-right';
            badge.textContent = tag;
            tagWrap.appendChild(badge);
          });
          nameTd.appendChild(tagWrap);
        }
        if (typeof file.folder === 'string' && file.folder !== '') {
          const folderInfo = document.createElement('span');
          folderInfo.className = 'uk-text-meta uk-display-block';
          folderInfo.textContent = file.folder;
          nameTd.appendChild(folderInfo);
        }

        if (Array.isArray(file.landing) && file.landing.length) {
          const landingWrap = document.createElement('div');
          landingWrap.className = 'media-landing-badges uk-margin-small-top';
          file.landing.forEach((reference) => {
            if (!reference || typeof reference !== 'object') {
              return;
            }
            const badge = document.createElement('span');
            badge.className = 'uk-label uk-label-warning uk-margin-small-right';
            const label = reference.title || reference.slug || '';
            badge.textContent = label;
            const type = typeof reference.type === 'string' ? reference.type.toLowerCase() : '';
            const tooltip = type === 'seo'
              ? (translations.landingSeo || fallbackTranslations.landingSeo)
              : (translations.landingMarkup || fallbackTranslations.landingMarkup);
            badge.title = tooltip;
            badge.setAttribute('aria-label', tooltip);
            landingWrap.appendChild(badge);
          });
          nameTd.appendChild(landingWrap);
        }

        const sizeTd = document.createElement('td');
        sizeTd.textContent = formatSize(file.size);
        const modifiedTd = document.createElement('td');
        modifiedTd.textContent = formatDate(file.modified);

        const actionsTd = document.createElement('td');
        actionsTd.className = 'media-actions';

        const actionsWrapper = document.createElement('div');
        actionsWrapper.className = 'uk-inline';

        const toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.className = 'uk-icon-button uk-button-default uk-button-small';
        toggleButton.setAttribute('uk-icon', 'more');
        toggleButton.setAttribute('aria-label', translations.actionsMenu || translations.actions || 'Actions');
        toggleButton.addEventListener('click', (event) => event.stopPropagation());

        const dropdown = document.createElement('div');
        dropdown.setAttribute('uk-dropdown', 'mode: click; pos: bottom-right');
        dropdown.className = 'media-actions-dropdown';

        const hideDropdown = () => {
          try {
            const component = window.UIkit?.dropdown?.(dropdown);
            component?.hide?.(false);
          } catch (err) {
            console.error(err);
          }
        };

        const dropdownList = document.createElement('ul');
        dropdownList.className = 'uk-nav uk-dropdown-nav';

        const downloadItem = document.createElement('li');
        const downloadLink = document.createElement('a');
        downloadLink.href = withBase(file.url || file.path || '#');
        downloadLink.textContent = translations.download;
        downloadLink.setAttribute('download', file.name || '');
        downloadLink.addEventListener('click', (event) => {
          event.stopPropagation();
          hideDropdown();
        });
        downloadItem.appendChild(downloadLink);
        dropdownList.appendChild(downloadItem);

        const replaceItem = document.createElement('li');
        const replaceLink = document.createElement('a');
        replaceLink.href = '#';
        replaceLink.setAttribute('role', 'button');
        replaceLink.textContent = translations.replace;
        replaceLink.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          handleReplace(file);
          hideDropdown();
        });
        replaceItem.appendChild(replaceLink);
        dropdownList.appendChild(replaceItem);

        if (canConvert(file)) {
          const convertItem = document.createElement('li');
          const convertLink = document.createElement('a');
          convertLink.href = '#';
          convertLink.setAttribute('role', 'button');
          convertLink.textContent = translations.convert;
          convertLink.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            handleConvert(file);
            hideDropdown();
          });
          convertItem.appendChild(convertLink);
          dropdownList.appendChild(convertItem);
        }

        const renameItem = document.createElement('li');
        const renameLink = document.createElement('a');
        renameLink.href = '#';
        renameLink.setAttribute('role', 'button');
        renameLink.textContent = translations.rename;
        renameLink.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          handleRename(file);
          hideDropdown();
        });
        renameItem.appendChild(renameLink);
        dropdownList.appendChild(renameItem);

        const deleteItem = document.createElement('li');
        const deleteLink = document.createElement('a');
        deleteLink.href = '#';
        deleteLink.setAttribute('role', 'button');
        deleteLink.className = 'uk-text-danger';
        deleteLink.textContent = translations.delete;
        deleteLink.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          handleDelete(file);
          hideDropdown();
        });
        deleteItem.appendChild(deleteLink);
        dropdownList.appendChild(deleteItem);

        dropdown.appendChild(dropdownList);
        actionsWrapper.appendChild(toggleButton);
        actionsWrapper.appendChild(dropdown);
        actionsTd.appendChild(actionsWrapper);

        row.appendChild(nameTd);
        row.appendChild(sizeTd);
        row.appendChild(modifiedTd);
        row.appendChild(actionsTd);
        if (state.landing.slug) {
          const matchesLanding = Array.isArray(file.landing)
            && file.landing.some((reference) => (reference?.slug || '') === state.landing.slug);
          row.classList.toggle('media-landing-match', matchesLanding);
        }
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

    function renderTagFilters() {
      if (!tagFilterContainer) return;
      tagFilterContainer.innerHTML = '';

      const tags = Array.isArray(state.available.tags) ? state.available.tags.slice() : [];
      state.filters.tags.forEach((activeTag) => {
        if (!tags.map(tagKey).includes(tagKey(activeTag))) {
          tags.push(activeTag);
        }
      });
      if (!tags.length) {
        const hint = document.createElement('span');
        hint.className = 'uk-text-meta';
        hint.textContent = translations.noTags || '';
        tagFilterContainer.appendChild(hint);
        return;
      }

      const activeKeys = state.filters.tags.map(tagKey);
      tags.forEach((tag) => {
        const button = document.createElement('button');
        button.type = 'button';
        const isActive = activeKeys.includes(tagKey(tag));
        button.className = 'uk-button uk-button-default uk-button-xsmall uk-margin-small-right';
        if (isActive) {
          button.classList.add('uk-button-primary');
        }
        button.dataset.tag = tag;
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        button.textContent = tag;
        tagFilterContainer.appendChild(button);
      });
    }

    function renderFolderFilter() {
      if (!folderFilterSelect) return;
      const folders = Array.isArray(state.available.folders) ? state.available.folders : [];
      const current = state.filters.folder || '';
      folderFilterSelect.innerHTML = '';

      const allOption = document.createElement('option');
      allOption.value = '';
      allOption.textContent = translations.allFolders || '';
      folderFilterSelect.appendChild(allOption);

      const noneOption = document.createElement('option');
      noneOption.value = NO_FOLDER_FILTER;
      noneOption.textContent = translations.noFolder || '';
      folderFilterSelect.appendChild(noneOption);

      folders.forEach((folder) => {
        const option = document.createElement('option');
        option.value = folder;
        option.textContent = folder;
        folderFilterSelect.appendChild(option);
      });

      if (
        current &&
        current !== NO_FOLDER_FILTER &&
        !folders.map((folder) => tagKey(folder)).includes(tagKey(current))
      ) {
        const customOption = document.createElement('option');
        customOption.value = current;
        customOption.textContent = current;
        folderFilterSelect.appendChild(customOption);
      }

      folderFilterSelect.value = current;
    }

    function renderLandingFilter() {
      if (!landingFilterSelect) return;
      const options = Array.isArray(state.landing.slugs) ? state.landing.slugs : [];
      const selected = state.landing.slug || '';
      const defaultText = translations.landingAll || fallbackTranslations.landingAll;
      landingFilterSelect.innerHTML = '';
      const defaultOption = document.createElement('option');
      defaultOption.value = '';
      defaultOption.textContent = defaultText;
      landingFilterSelect.appendChild(defaultOption);
      options.forEach((entry) => {
        if (!entry || typeof entry !== 'object') {
          return;
        }
        const slug = typeof entry.slug === 'string' ? entry.slug : '';
        if (!slug) {
          return;
        }
        const option = document.createElement('option');
        option.value = slug;
        const title = typeof entry.title === 'string' && entry.title !== '' ? entry.title : slug;
        option.textContent = title;
        landingFilterSelect.appendChild(option);
      });
      landingFilterSelect.value = selected;
    }

    function updateLandingFilterVisibility() {
      const isGlobal = state.scope === 'global';
      if (landingFilterContainer) {
        landingFilterContainer.hidden = !isGlobal;
      }
      if (landingFilterSelect) {
        landingFilterSelect.disabled = !isGlobal;
      }
    }

    function renderLandingMissing() {
      if (!landingMissingCard) return;
      const isGlobal = state.scope === 'global';
      const missing = Array.isArray(state.landing.missing) ? state.landing.missing : [];
      if (!isGlobal || missing.length === 0) {
        landingMissingCard.hidden = true;
        if (landingMissingList) landingMissingList.innerHTML = '';
        if (landingMissingEmpty) {
          landingMissingEmpty.hidden = false;
          landingMissingEmpty.textContent = translations.landingMissingEmpty
            || fallbackTranslations.landingMissingEmpty;
        }
        return;
      }

      landingMissingCard.hidden = false;
      if (landingMissingEmpty) landingMissingEmpty.hidden = true;
      if (landingMissingList) {
        landingMissingList.innerHTML = '';
        missing.forEach((entry) => {
          if (!entry || typeof entry !== 'object') {
            return;
          }
          const item = document.createElement('li');
          item.className = 'media-landing-missing-item';
          const pathLabel = document.createElement('div');
          pathLabel.className = 'uk-text-small uk-text-bold';
          const displayPath = typeof entry.displayPath === 'string' && entry.displayPath !== ''
            ? entry.displayPath
            : entry.path ? `/${String(entry.path).replace(/^\/+/, '')}` : '';
          pathLabel.textContent = displayPath;
          item.appendChild(pathLabel);
          const meta = document.createElement('div');
          meta.className = 'uk-text-meta';
          const slugTitle = entry.title || entry.slug || '';
          const type = typeof entry.type === 'string' ? entry.type.toLowerCase() : '';
          const extension = typeof entry.extension === 'string' ? entry.extension.toLowerCase() : '';
          const typeLabel = type === 'seo'
            ? (translations.landingSeo || fallbackTranslations.landingSeo)
            : (translations.landingMarkup || fallbackTranslations.landingMarkup);
          const altText = typeof entry.alt === 'string' ? entry.alt.trim() : '';
          const defaultMeta = slugTitle ? `${slugTitle} · ${typeLabel}` : typeLabel;
          meta.textContent = altText || defaultMeta;
          item.appendChild(meta);
          const actions = document.createElement('div');
          actions.className = 'uk-margin-small-top';
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'uk-button uk-button-default uk-button-xsmall';
          button.dataset.mediaLandingPrefill = 'true';
          button.dataset.name = entry.suggestedName || '';
          const slugFolder = typeof entry.slug === 'string' ? entry.slug.trim() : '';
          const fallbackFolder = entry.suggestedFolder && typeof entry.suggestedFolder === 'string'
            ? entry.suggestedFolder
            : '';
          button.dataset.folder = slugFolder || fallbackFolder;
          button.dataset.slug = entry.slug || '';
          button.dataset.extension = entry.extension || '';
          button.textContent = translations.landingPrefill || fallbackTranslations.landingPrefill;
          actions.appendChild(button);
          if (extension === 'pdf') {
            const path = typeof entry.path === 'string' ? entry.path.trim() : '';
            if (path) {
              const urlPath = path.startsWith('/') ? path : `/${path}`;
              const link = document.createElement('a');
              link.href = withBase(urlPath);
              link.target = '_blank';
              link.rel = 'noopener';
              link.className = 'uk-button uk-button-default uk-button-xsmall uk-margin-small-left';
              link.textContent = translations.landingOpenPdf || fallbackTranslations.landingOpenPdf;
              actions.appendChild(link);
            }
          }
          item.appendChild(actions);
          landingMissingList.appendChild(item);
        });
      }
    }

    function renderFilterControls() {
      renderTagFilters();
      renderFolderFilter();
      renderLandingFilter();
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
      const canUpload = !!state.pendingFile && !state.uploading;
      if (submitBtn) submitBtn.disabled = !canUpload;
      if (dropZone) {
        dropZone.classList.toggle('is-disabled', state.uploading);
        dropZone.setAttribute('aria-disabled', state.uploading ? 'true' : 'false');
        dropZone.tabIndex = state.uploading ? -1 : 0;
      }
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
      state.loading = true;
      clearError();
      if (root) root.setAttribute('aria-busy', 'true');
      const params = new URLSearchParams({
        scope: state.scope,
        page: String(state.page),
        perPage: String(state.perPage)
      });
      if (state.scope === 'project' && state.namespace) {
        params.set('namespace', state.namespace);
      }
      if (state.search) params.set('search', state.search);
      if (state.filters.tags.length) {
        params.set('tags', state.filters.tags.join(','));
      }
      if (state.filters.folder) {
        params.set('folder', state.filters.folder);
      }
      if (state.scope === 'global' && state.landing.slug) {
        params.set('landing', state.landing.slug);
      }
      try {
        const data = await fetchJson(`/admin/media/files?${params.toString()}`);
        state.files = Array.isArray(data.files) ? data.files : [];
        const pagination = data.pagination || {};
        state.page = Number(pagination.page) || 1;
        state.totalPages = Number(pagination.totalPages) || 1;
        updateLimitText(data.limits || {});
        const filters = data.filters || {};
        state.available.tags = Array.isArray(filters.tags) ? filters.tags : [];
        state.available.folders = Array.isArray(filters.folders) ? filters.folders : [];
        const activeFilters = filters.active || {};
        state.filters.tags = Array.isArray(activeFilters.tags) ? uniqueTags(activeFilters.tags) : [];
        state.filters.folder = typeof activeFilters.folder === 'string' ? activeFilters.folder : '';
        const landing = data.landing || {};
        if (Array.isArray(landing.slugs)) {
          state.landing.slugs = landing.slugs;
        }
        state.landing.missing = Array.isArray(landing.missing) ? landing.missing : [];
        state.landing.slug = typeof landing.active === 'string' ? landing.active : state.landing.slug;
        renderFiles();
        clearError();
        renderLandingMissing();
      } catch (err) {
        state.files = [];
        renderFiles();
        showError(err.message);
        state.available.tags = [];
        state.available.folders = [];
        state.landing.missing = [];
      } finally {
        state.loading = false;
        if (root) root.setAttribute('aria-busy', 'false');
        updatePagination();
        renderFilterControls();
        renderLandingMissing();
        updateLandingFilterVisibility();
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
      const formData = new FormData();
      formData.append('file', file);
      appendScopeData(formData);
      const nameValue = nameInput?.value?.trim();
      if (nameValue) {
        formData.append('name', nameValue);
      }
      const tagsValue = uploadTagsInput?.value?.trim();
      if (tagsValue) {
        formData.append('tags', tagsValue);
      }
      const folderValue = uploadFolderInput?.value?.trim();
      if (folderValue) {
        formData.append('folder', folderValue);
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

    async function updateMetadata(file, changes) {
      if (!file || state.metadataSaving) return;
      const payload = {
        ...buildScopePayload(),
        oldName: file.name,
        newName: file.name,
      };

      if (Object.prototype.hasOwnProperty.call(changes, 'tags')) {
        payload.tags = changes.tags;
      }
      if (Object.prototype.hasOwnProperty.call(changes, 'folder')) {
        payload.folder = changes.folder;
      }

      try {
        setMetadataSaving(true);
        const data = await fetchJson('/admin/media/rename', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        updateLimitText(data.limits || {});
        notify(translations.metadataSaved, 'success');
        state.selectedName = data.file?.name || file.name;
        await loadFiles();
      } catch (err) {
        notify(err.message || translations.requestFailed, 'danger');
      } finally {
        setMetadataSaving(false);
      }
    }

    function handleAddTag() {
      if (!metadataTagInput) return;
      const value = sanitizeTagValue(metadataTagInput.value);
      if (!value) return;
      const file = getSelectedFile();
      if (!file) return;
      const existing = Array.isArray(file.tags) ? file.tags.slice() : [];
      if (existing.map(tagKey).includes(tagKey(value))) {
        metadataTagInput.value = '';
        return;
      }
      metadataTagInput.value = '';
      const tags = uniqueTags([...existing, value]);
      updateMetadata(file, { tags });
    }

    function handleSaveFolder() {
      const file = getSelectedFile();
      if (!file || !metadataFolderInput) return;
      const value = metadataFolderInput.value.trim();
      if ((file.folder || '') === value) {
        return;
      }
      updateMetadata(file, { folder: value });
    }

    function handleClearFolder() {
      const file = getSelectedFile();
      if (!file) return;
      if (metadataFolderInput) {
        metadataFolderInput.value = '';
      }
      if (!file.folder) {
        return;
      }
      updateMetadata(file, { folder: null });
    }

    function handleReplace(file) {
      if (!file || state.replacing || state.uploading) {
        return;
      }
      const picker = document.createElement('input');
      picker.type = 'file';
      picker.style.display = 'none';
      const acceptAttr = fileInput?.getAttribute('accept');
      if (acceptAttr) {
        picker.setAttribute('accept', acceptAttr);
      }
      document.body.appendChild(picker);
      const cleanup = () => {
        picker.remove();
      };
      picker.addEventListener('change', () => {
        const replacement = picker.files && picker.files[0];
        if (replacement) {
          startReplace(file, replacement);
        }
        cleanup();
      }, { once: true });
      picker.addEventListener('cancel', cleanup, { once: true });
      picker.addEventListener('blur', cleanup, { once: true });
      picker.click();
    }

    async function startReplace(file, replacement) {
      if (!file || !replacement || state.replacing) {
        return;
      }
      const formData = new FormData();
      formData.append('file', replacement);
      appendScopeData(formData);
      formData.append('name', file.name);
      try {
        state.replacing = true;
        const data = await fetchJson('/admin/media/replace', {
          method: 'POST',
          body: formData
        });
        updateLimitText(data.limits || {});
        notify(translations.replaced, 'success');
        state.selectedName = data.file?.name || file.name;
        await loadFiles();
      } catch (err) {
        notify(err.message || translations.requestFailed, 'danger');
      } finally {
        state.replacing = false;
      }
    }

    function handleConvert(file) {
      if (!file || state.converting || !canConvert(file)) {
        return;
      }
      startConvert(file);
    }

    async function startConvert(file) {
      if (!file || state.converting) {
        return;
      }
      if (!canConvert(file)) {
        return;
      }
      try {
        state.converting = true;
        updatePreview(file);
        const data = await fetchJson('/admin/media/convert', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            ...buildScopePayload(),
            name: file.name
          })
        });
        updateLimitText(data.limits || {});
        notify(translations.converted || translations.metadataSaved, 'success');
        if (data.file?.name) {
          state.selectedName = data.file.name;
        }
        await loadFiles();
      } catch (err) {
        notify(err.message || translations.requestFailed, 'danger');
      } finally {
        state.converting = false;
        const current = getSelectedFile();
        updatePreview(current);
      }
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
            ...buildScopePayload(),
            oldName: file.name,
            newName
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
            ...buildScopePayload(),
            name: file.name
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

    if (searchInput) {
      let searchTimer = null;
      searchInput.addEventListener('input', () => {
        state.search = searchInput.value.trim();
        state.page = 1;
        if (searchTimer) window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => loadFiles(), 300);
      });
    }

    tagFilterContainer?.addEventListener('click', (event) => {
      const target = event.target;
      const button = target && typeof target.closest === 'function'
        ? target.closest('button[data-tag]')
        : null;
      if (!button) return;
      event.preventDefault();
      const tag = button.dataset.tag || '';
      if (!tag) return;
      const key = tagKey(tag);
      const existing = state.filters.tags.slice();
      const index = existing.findIndex((item) => tagKey(item) === key);
      if (index === -1) {
        existing.push(tag);
      } else {
        existing.splice(index, 1);
      }
      state.filters.tags = uniqueTags(existing);
      state.page = 1;
      loadFiles();
    });

    folderFilterSelect?.addEventListener('change', () => {
      const value = folderFilterSelect.value || '';
      if (state.filters.folder === value) {
        return;
      }
      state.filters.folder = value;
      state.page = 1;
      loadFiles();
    });

    clearFiltersBtn?.addEventListener('click', () => {
      if (!state.filters.tags.length && !state.filters.folder && !state.landing.slug) {
        return;
      }
      state.filters.tags = [];
      state.filters.folder = '';
      state.landing.slug = '';
      if (landingFilterSelect) landingFilterSelect.value = '';
      state.page = 1;
      loadFiles();
    });

    landingFilterSelect?.addEventListener('change', () => {
      const value = landingFilterSelect.value || '';
      if (state.landing.slug === value) {
        return;
      }
      state.landing.slug = value;
      state.page = 1;
      loadFiles();
    });

    landingMissingList?.addEventListener('click', (event) => {
      const target = event.target;
      const button = target && typeof target.closest === 'function'
        ? target.closest('[data-media-landing-prefill]')
        : null;
      if (!button) {
        return;
      }
      event.preventDefault();
      const suggestedName = button.getAttribute('data-name') || '';
      const suggestedFolder = button.getAttribute('data-folder') || '';
      const slug = button.getAttribute('data-slug') || '';
      if (nameInput && suggestedName) {
        nameInput.value = suggestedName;
      }
      if (uploadFolderInput) {
        uploadFolderInput.value = suggestedFolder;
      }
      if (slug && landingFilterSelect) {
        landingFilterSelect.value = slug;
        state.landing.slug = slug;
      }
      updateUploadState();
      nameInput?.focus?.();
    });

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

    metadataAddTag?.addEventListener('click', () => {
      handleAddTag();
    });

    metadataTagInput?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        handleAddTag();
      }
    });

    metadataTagList?.addEventListener('click', (event) => {
      const target = event.target;
      const button = target && typeof target.closest === 'function'
        ? target.closest('[data-media-remove-tag]')
        : null;
      if (!button) return;
      event.preventDefault();
      const tag = button.getAttribute('data-tag') || '';
      if (!tag) return;
      const file = getSelectedFile();
      if (!file) return;
      const existing = Array.isArray(file.tags) ? file.tags : [];
      const updated = existing.filter((item) => tagKey(item) !== tagKey(tag));
      if (updated.length === existing.length) {
        return;
      }
      updateMetadata(file, { tags: updated });
    });

    metadataSaveFolder?.addEventListener('click', () => {
      handleSaveFolder();
    });

    metadataClearFolder?.addEventListener('click', () => {
      handleClearFolder();
    });

    metadataFolderInput?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        handleSaveFolder();
      }
    });

    previewCopyButton?.addEventListener('click', () => {
      handleCopyUrl();
    });

    previewReplace?.addEventListener('click', () => {
      const file = getSelectedFile();
      if (file) {
        handleReplace(file);
      }
    });

    previewConvert?.addEventListener('click', () => {
      const file = getSelectedFile();
      if (file) {
        handleConvert(file);
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

    function appendScopeData(formData) {
      formData.append('scope', state.scope);
      if (state.scope === 'project' && state.namespace) {
        formData.append('namespace', state.namespace);
      }
    }

    function buildScopePayload() {
      const payload = { scope: state.scope };
      if (state.scope === 'project' && state.namespace) {
        payload.namespace = state.namespace;
      }
      return payload;
    }

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
