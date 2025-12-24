/* global UIkit */

const manager = document.querySelector('[data-wiki-manager]');

if (manager) {
  (function initWikiManager() {
    const pagesData = (() => {
      try {
        return JSON.parse(manager.dataset.pages || '[]');
      } catch (error) {
        console.warn('Failed to parse pages dataset', error);
        return [];
      }
    })();

    const pageSelect = manager.querySelector('[data-wiki-page-select]');
    const selectedSlugLabel = manager.querySelector('[data-wiki-selected-slug]');
    const settingsCard = manager.querySelector('[data-wiki-settings]');
    const settingsForm = manager.querySelector('[data-wiki-settings-form]');
    const settingsUpdated = manager.querySelector('[data-wiki-settings-updated]');
    const settingsActiveInput = manager.querySelector('[data-wiki-active]');
    const settingsMenuLabelInputs = Array.from(manager.querySelectorAll('[data-wiki-menu-label-locale]'));
    const settingsSubmit = manager.querySelector('[data-wiki-settings-submit]');
    const themeColorInputs = Array.from(manager.querySelectorAll('[data-wiki-theme-color]'));
    const themeLogoInput = manager.querySelector('[data-wiki-theme-logo]');
    const themeBodyClassesInput = manager.querySelector('[data-wiki-theme-body-classes]');
    const themeStylesheetsInput = manager.querySelector('[data-wiki-theme-stylesheets]');
    const themeSaveButton = manager.querySelector('[data-wiki-theme-save]');
    const themeResetButton = manager.querySelector('[data-wiki-theme-reset]');
    const themeUpdatedLabel = manager.querySelector('[data-wiki-theme-updated]');
    const articlesCard = manager.querySelector('[data-wiki-articles]');
    const articlesTableBody = manager.querySelector('[data-wiki-article-list]');
    const articlesEmpty = manager.querySelector('[data-wiki-articles-empty]');
    const localeFilter = manager.querySelector('[data-wiki-locale-filter]');
    const createButton = manager.querySelector('[data-wiki-create]');
    const uploadButton = manager.querySelector('[data-wiki-upload]');
    const uploadInput = manager.querySelector('[data-wiki-upload-input]');
    const feedbackAlert = manager.querySelector('[data-wiki-feedback]');
    const loadingRow = manager.querySelector('[data-wiki-loading-row]');

    const modalElement = document.getElementById('wikiArticleModal');
    let modalInstance = null;
    const modalTitle = modalElement ? modalElement.querySelector('[data-wiki-modal-title]') : null;
    const modalError = modalElement ? modalElement.querySelector('[data-wiki-article-error]') : null;
    const modalForm = modalElement ? modalElement.querySelector('[data-wiki-article-form]') : null;
    const modalIdInput = modalElement ? modalElement.querySelector('[data-wiki-article-id]') : null;
    const modalLocaleInput = modalElement ? modalElement.querySelector('[data-wiki-article-locale]') : null;
    const modalStatusSelect = modalElement ? modalElement.querySelector('[data-wiki-article-status]') : null;
    const modalTitleInput = modalElement ? modalElement.querySelector('[data-wiki-article-title]') : null;
    const modalSlugInput = modalElement ? modalElement.querySelector('[data-wiki-article-slug]') : null;
    const modalExcerptInput = modalElement ? modalElement.querySelector('[data-wiki-article-excerpt]') : null;
    const modalContentInput = modalElement ? modalElement.querySelector('[data-wiki-article-content]') : null;
    const modalUpdatedLabel = modalElement ? modalElement.querySelector('[data-wiki-article-updated]') : null;
    const modalSubmit = modalElement ? modalElement.querySelector('[data-wiki-article-submit]') : null;
    const modalStartInput = modalElement ? modalElement.querySelector('[data-wiki-article-start]') : null;
    const basePath = manager.dataset.basePath || '';

    const resolveNamespace = () => {
      const select = document.getElementById('pageNamespaceSelect');
      const candidate = select?.value || manager.dataset.namespace || window.pageNamespace || '';
      return String(candidate || '').trim();
    };

    const withNamespace = (path) => {
      const namespace = resolveNamespace();
      if (!namespace) {
        return path;
      }
      const separator = path.includes('?') ? '&' : '?';
      return `${path}${separator}namespace=${encodeURIComponent(namespace)}`;
    };

    if (
      !pageSelect ||
      !settingsForm ||
      !articlesTableBody ||
      !localeFilter ||
      !modalForm ||
      !settingsActiveInput ||
      settingsMenuLabelInputs.length === 0 ||
      !settingsSubmit ||
      themeColorInputs.length === 0 ||
      !themeLogoInput ||
      !themeBodyClassesInput ||
      !themeStylesheetsInput ||
      !themeSaveButton ||
      !themeResetButton ||
      !themeUpdatedLabel ||
      !articlesCard ||
      !articlesEmpty ||
      !settingsCard ||
      !createButton ||
      !uploadButton ||
      !uploadInput ||
      !modalTitle ||
      !modalError ||
      !modalIdInput ||
      !modalLocaleInput ||
      !modalStatusSelect ||
      !modalTitleInput ||
      !modalSlugInput ||
      !modalExcerptInput ||
      !modalContentInput ||
      !modalUpdatedLabel ||
      !modalSubmit ||
      !modalStartInput
    ) {
      console.warn('Wiki manager requirements missing.');
      return;
    }

    const statusLabels = {
      draft: window.transWikiStatusDraft || 'Draft',
      published: window.transWikiStatusPublished || 'Published',
      archived: window.transWikiStatusArchived || 'Archived'
    };

    const defaultThemeColors = {
      headerFrom: '#111827',
      headerTo: '#1f2937',
      detailHeaderFrom: '#0f172a',
      detailHeaderTo: '#1d4ed8',
      headerText: '#ffffff',
      excerpt: '#4b5563',
      calloutBorder: '#3b82f6',
      calloutBackground: '#f9fafb',
      calloutText: '#0f172a'
    };

    const getMenuLabelInputs = () => settingsMenuLabelInputs.filter(input => input.dataset.wikiMenuLabelLocale);

    const buildMenuLabelsPayload = () => {
      const labels = {};
      getMenuLabelInputs().forEach(input => {
        const locale = String(input.dataset.wikiMenuLabelLocale || '').toLowerCase().trim();
        const value = input.value.trim();
        if (locale && value) {
          labels[locale] = value;
        }
      });
      return labels;
    };

    const setMenuLabelInputs = (labels, fallbackLabel = '') => {
      getMenuLabelInputs().forEach(input => {
        const locale = String(input.dataset.wikiMenuLabelLocale || '').toLowerCase().trim();
        if (!locale) {
          return;
        }
        if (labels && typeof labels === 'object' && labels[locale]) {
          input.value = labels[locale];
          return;
        }
        if (fallbackLabel && locale === 'de') {
          input.value = fallbackLabel;
          return;
        }
        input.value = '';
      });
    };

    const messages = {
      settingsSaved: window.transWikiSettingsSaved || 'Settings saved.',
      settingsError: window.transWikiSettingsError || 'Failed to save settings.',
      articleSaved: window.transWikiArticleSaved || 'Article saved.',
      articleError: window.transWikiArticleError || 'Failed to save article.',
      articleDeleted: window.transWikiArticleDeleted || 'Article deleted.',
      articleDeleteConfirm: window.transWikiArticleDeleteConfirm || 'Delete this article?',
      articleDuplicate: window.transWikiArticleDuplicate || 'Article duplicated.',
      articleDuplicateError: window.transWikiArticleDuplicateError || 'Failed to duplicate article.',
      sortSaved: window.transWikiSortSaved || 'Order saved.',
      sortError: window.transWikiSortError || 'Failed to save order.',
      loading: window.transWikiLoading || 'Loading…',
      empty: window.transWikiEmpty || 'No articles available.',
      articleImport: window.transWikiArticleImport || 'Markdown importiert.',
      articleImportError: window.transWikiArticleImportError || 'Markdown-Import fehlgeschlagen.',
      articleImportInvalid: window.transWikiArticleImportInvalid || 'Bitte eine Markdown-Datei auswählen.',
      articleImportNoPage: window.transWikiArticleImportNoPage || 'Bitte zuerst eine Marketing-Seite auswählen.',
      articleStartMarked: window.transWikiArticleStartMarked || 'Start document assigned.',
      articleStartRemoved: window.transWikiArticleStartRemoved || 'Start document removed.',
      startBadge: window.transWikiArticleStartBadge || 'Start',
      themeSaved: window.transWikiThemeSaved || 'Theme gespeichert.',
      themeError: window.transWikiThemeError || 'Theme konnte nicht gespeichert werden.',
      themeInvalid: window.transWikiThemeInvalid || 'Ungültige Theme-Angaben.'
    };

    const state = {
      pageId: Number(manager.dataset.selectedPageId || 0) || null,
      pageSlug: '',
      settings: null,
      theme: null,
      themeDefaults: {
        bodyClasses: ['marketing-wiki'],
        stylesheets: [],
        colors: { ...defaultThemeColors }
      },
      articles: [],
      filterLocale: 'all'
    };

    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
      if (!value) {
        return '';
      }
      try {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
          return '';
        }
        return date.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
      } catch (error) {
        return '';
      }
    }

    function slugify(value) {
      const base = String(value || '').toLowerCase();
      const normalized = typeof base.normalize === 'function' ? base.normalize('NFD') : base;
      const ascii = normalized
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ß/g, 'ss');
      const slug = ascii
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .replace(/-+/g, '-');
      return slug;
    }

    function isValidHexColor(value) {
      return /^#([0-9a-fA-F]{6})$/.test(String(value || ''));
    }

    function isValidUrl(value) {
      if (typeof value !== 'string') {
        return false;
      }
      const trimmed = value.trim();
      if (trimmed === '') {
        return false;
      }

      try {
        const parsed = new URL(trimmed);
        return Boolean(parsed.protocol && parsed.host);
      } catch (error) {
        return trimmed.startsWith('/') || /^[A-Za-z0-9._/-]+$/.test(trimmed);
      }
    }

    function parseBodyClassesInput() {
      const raw = (themeBodyClassesInput.value || '').trim();
      if (!raw) {
        return [];
      }
      const classes = raw.split(/\s+/).map(item => item.trim()).filter(Boolean);
      return classes.filter((cls, index) => classes.indexOf(cls) === index);
    }

    function parseStylesheetsInput() {
      const raw = (themeStylesheetsInput.value || '').replace(/\r/g, '\n');
      const entries = raw.split(/\n|,/).map(entry => entry.trim()).filter(Boolean);
      return entries.filter((entry, index) => entries.indexOf(entry) === index);
    }

    function getDefaultColors() {
      const colors = (state.themeDefaults && state.themeDefaults.colors) || {};
      return { ...defaultThemeColors, ...colors };
    }

    function resetThemeForm() {
      updateThemeForm({
        colors: getDefaultColors(),
        bodyClasses: [],
        stylesheets: [],
        logoUrl: null,
        updatedAt: null
      });
    }

    function updateThemeForm(theme) {
      const colors = theme?.colors || getDefaultColors();
      themeColorInputs.forEach(input => {
        const key = input.dataset.wikiThemeColor;
        if (!key) {
          return;
        }
        const value = colors[key] || getDefaultColors()[key] || '';
        if (value && isValidHexColor(value)) {
          input.value = value;
        } else {
          input.value = '#000000';
        }
      });

      themeLogoInput.value = theme?.logoUrl || '';
      const bodyClasses = Array.isArray(theme?.bodyClasses) ? theme.bodyClasses.join(' ') : '';
      themeBodyClassesInput.value = bodyClasses;
      const stylesheets = Array.isArray(theme?.stylesheets) ? theme.stylesheets.join('\n') : '';
      themeStylesheetsInput.value = stylesheets;

      if (themeUpdatedLabel) {
        themeUpdatedLabel.textContent = theme?.updatedAt ? `Aktualisiert: ${formatDate(theme.updatedAt)}` : '';
      }
    }

    function buildThemePayloadFromForm() {
      const payload = {};
      const colors = {};
      themeColorInputs.forEach(input => {
        const key = input.dataset.wikiThemeColor;
        const value = (input.value || '').trim();
        if (!key || value === '') {
          return;
        }
        if (!isValidHexColor(value)) {
          throw new Error(messages.themeInvalid);
        }
        colors[key] = value.toLowerCase();
      });

      const bodyClasses = parseBodyClassesInput();
      const stylesheets = parseStylesheetsInput();
      const logoUrl = (themeLogoInput.value || '').trim();

      stylesheets.forEach(entry => {
        if (!isValidUrl(entry)) {
          throw new Error(messages.themeInvalid);
        }
      });

      if (logoUrl && !isValidUrl(logoUrl)) {
        throw new Error(messages.themeInvalid);
      }

      if (Object.keys(colors).length > 0) {
        payload.colors = colors;
      }
      if (bodyClasses.length > 0) {
        payload.bodyClasses = bodyClasses;
      }
      if (stylesheets.length > 0) {
        payload.stylesheets = stylesheets;
      }
      if (logoUrl) {
        payload.logoUrl = logoUrl;
      }

      return payload;
    }

    function notify(message, status = 'primary') {
      if (typeof window.notify === 'function') {
        window.notify(message, status);
      } else {
        console.log(message);
      }
    }

    function showFeedback(message, status = 'primary') {
      if (!feedbackAlert) {
        return;
      }
      feedbackAlert.textContent = message;
      feedbackAlert.classList.remove('uk-alert-primary', 'uk-alert-success', 'uk-alert-danger');
      const cls = status === 'danger' ? 'uk-alert-danger' : status === 'success' ? 'uk-alert-success' : 'uk-alert-primary';
      feedbackAlert.classList.add(cls);
      feedbackAlert.hidden = false;
    }

    function hideFeedback() {
      if (feedbackAlert) {
        feedbackAlert.hidden = true;
      }
    }

    function setSelectedSlug(slug) {
      if (!selectedSlugLabel) {
        return;
      }
      selectedSlugLabel.textContent = slug ? `Pfad: /pages/${slug}/wiki` : '';
    }

    function findPageById(id) {
      return pagesData.find(page => Number(page.id) === id) || null;
    }

    function normalizeArticle(raw) {
      return {
        id: Number(raw.id),
        pageId: Number(raw.pageId),
        slug: String(raw.slug || ''),
        locale: String(raw.locale || '').toLowerCase(),
        title: String(raw.title || ''),
        excerpt: raw.excerpt ? String(raw.excerpt) : '',
        contentMarkdown: typeof raw.contentMarkdown === 'string' ? raw.contentMarkdown : '',
        status: String(raw.status || 'draft'),
        sortIndex: Number.isFinite(Number(raw.sortIndex)) ? Number(raw.sortIndex) : 0,
        publishedAt: typeof raw.publishedAt === 'string' ? raw.publishedAt : null,
        updatedAt: typeof raw.updatedAt === 'string' ? raw.updatedAt : null,
        isStartDocument:
          raw.isStartDocument === true ||
          raw.isStartDocument === 1 ||
          raw.isStartDocument === '1' ||
          raw.isStartDocument === 'true',
        editorState: raw.editorState && typeof raw.editorState === 'object' ? raw.editorState : { blocks: [] }
      };
    }

    function sortArticlesInState() {
      state.articles.sort((a, b) => {
        const localeCompare = (a.locale || '').localeCompare(b.locale || '', undefined, { sensitivity: 'base' });
        if (localeCompare !== 0) {
          return localeCompare;
        }
        if (a.isStartDocument !== b.isStartDocument) {
          return a.isStartDocument ? -1 : 1;
        }
        if (a.sortIndex !== b.sortIndex) {
          return a.sortIndex - b.sortIndex;
        }
        return a.id - b.id;
      });
    }

    function upsertArticle(normalizedArticle) {
      const sameLocale = normalizedArticle.locale;
      let found = false;

      state.articles = state.articles.map(item => {
        if (item.id === normalizedArticle.id) {
          found = true;
          return normalizedArticle;
        }

        if (normalizedArticle.isStartDocument && item.locale === sameLocale) {
          return { ...item, isStartDocument: false };
        }

        return item;
      });

      if (!found) {
        if (normalizedArticle.isStartDocument) {
          state.articles = state.articles.map(item =>
            item.locale === sameLocale ? { ...item, isStartDocument: false } : item
          );
        }
        state.articles.push(normalizedArticle);
      }

      sortArticlesInState();
    }

    function updateLocaleFilterOptions() {
      const current = state.filterLocale;
      const locales = Array.from(new Set(state.articles.map(article => article.locale))).filter(Boolean);
      locales.sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));

      localeFilter.innerHTML = '';
      const allOption = document.createElement('option');
      allOption.value = 'all';
      allOption.textContent = 'Alle';
      localeFilter.appendChild(allOption);

      locales.forEach(locale => {
        const option = document.createElement('option');
        option.value = locale;
        option.textContent = locale;
        localeFilter.appendChild(option);
      });

      if (current && current !== 'all' && locales.includes(current)) {
        localeFilter.value = current;
      } else {
        state.filterLocale = 'all';
        localeFilter.value = 'all';
      }
    }

    function toggleLoadingRow(show) {
      if (!loadingRow) {
        return;
      }
      if (show) {
        loadingRow.hidden = false;
        const cell = loadingRow.querySelector('td');
        if (cell) {
          cell.textContent = messages.loading;
        }
      } else {
        loadingRow.hidden = true;
      }
    }

    function renderArticles() {
      sortArticlesInState();
      toggleLoadingRow(false);
      articlesTableBody.innerHTML = '';
      const filtered = state.articles.filter(article => state.filterLocale === 'all' || article.locale === state.filterLocale);
      if (filtered.length === 0) {
        articlesEmpty.hidden = false;
        const emptyRow = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 6;
        cell.textContent = messages.empty;
        emptyRow.appendChild(cell);
        articlesTableBody.appendChild(emptyRow);
        return;
      }
      articlesEmpty.hidden = true;

      filtered.forEach(article => {
        const row = document.createElement('tr');
        row.dataset.articleId = String(article.id);
        row.dataset.locale = article.locale;

        const titleCell = document.createElement('td');
        const startBadge = article.isStartDocument
          ? `<span class="uk-label uk-label-success uk-margin-small-left" title="${escapeHtml(messages.startBadge)}"><span class="uk-margin-small-right" uk-icon="home" aria-hidden="true"></span>${escapeHtml(messages.startBadge)}</span>`
          : '';
        titleCell.innerHTML = `<strong>${escapeHtml(article.title)}</strong>${startBadge}<div class="uk-text-meta">${escapeHtml(article.slug)}</div>`;
        row.appendChild(titleCell);

        const localeCell = document.createElement('td');
        localeCell.textContent = article.locale || '-';
        row.appendChild(localeCell);

        const statusCell = document.createElement('td');
        const status = article.status in statusLabels ? article.status : 'draft';
        const badge = document.createElement('span');
        badge.className = 'uk-label ' + (status === 'published' ? 'uk-label-success' : status === 'draft' ? 'uk-label-warning' : 'uk-label');
        badge.textContent = statusLabels[status];
        statusCell.appendChild(badge);
        row.appendChild(statusCell);

        const publishedCell = document.createElement('td');
        publishedCell.textContent = formatDate(article.publishedAt) || '—';
        row.appendChild(publishedCell);

        const orderCell = document.createElement('td');
        orderCell.className = 'uk-table-shrink uk-text-nowrap';
        const orderGroup = document.createElement('div');
        orderGroup.className = 'uk-button-group';
        const upButton = document.createElement('button');
        upButton.type = 'button';
        upButton.className = 'uk-button uk-button-default uk-button-small';
        upButton.innerHTML = '&#x2191;';
        upButton.setAttribute('aria-label', 'Nach oben verschieben');
        upButton.dataset.action = 'move-up';
        const downButton = document.createElement('button');
        downButton.type = 'button';
        downButton.className = 'uk-button uk-button-default uk-button-small';
        downButton.innerHTML = '&#x2193;';
        downButton.setAttribute('aria-label', 'Nach unten verschieben');
        downButton.dataset.action = 'move-down';
        if (state.filterLocale !== 'all') {
          upButton.disabled = true;
          downButton.disabled = true;
        } else {
          const index = state.articles.findIndex(item => item.id === article.id);
          upButton.disabled = index <= 0;
          downButton.disabled = index === state.articles.length - 1;
        }
        orderGroup.append(upButton, downButton);
        orderCell.appendChild(orderGroup);
        row.appendChild(orderCell);

        const actionsCell = document.createElement('td');
        actionsCell.className = 'uk-table-shrink';

        const menuWrapper = document.createElement('div');
        menuWrapper.className = 'uk-inline';

        const menuToggle = document.createElement('button');
        menuToggle.type = 'button';
        menuToggle.className = 'uk-button uk-button-default uk-button-small';
        menuToggle.setAttribute('uk-icon', 'icon: more; ratio: 0.9');
        menuToggle.setAttribute('aria-label', 'Aktionen');
        menuToggle.innerHTML = '<span class="uk-hidden">Aktionen</span>';
        menuWrapper.appendChild(menuToggle);

        const dropdown = document.createElement('div');
        dropdown.setAttribute(
          'uk-dropdown',
          'mode: click; pos: bottom-right; offset: 0; boundary: window; container: .admin-page'
        );

        const dropdownList = document.createElement('ul');
        dropdownList.className = 'uk-nav uk-dropdown-nav';

        const appendActionItem = (label, action, { danger = false, className = '' } = {}) => {
          if (!label) {
            return;
          }
          const item = document.createElement('li');
          const link = document.createElement('a');
          link.href = '#';
          link.dataset.action = action;
          link.textContent = label;
          link.setAttribute('uk-dropdown-close', '');
          if (danger) {
            link.classList.add('uk-text-danger');
          }
          if (className) {
            className
              .split(/\s+/)
              .filter(Boolean)
              .forEach(cls => link.classList.add(cls));
          }
          item.appendChild(link);
          dropdownList.appendChild(item);
        };

        appendActionItem('Bearbeiten', 'edit');

        if (article.status === 'published') {
          appendActionItem('Als Entwurf', 'set-draft');
        } else {
          appendActionItem('Veröffentlichen', 'publish', { className: 'uk-text-primary' });
        }

        if (article.status === 'archived') {
          appendActionItem('Reaktivieren', 'restore');
        } else {
          appendActionItem('Archivieren', 'archive');
        }

        if (article.isStartDocument) {
          appendActionItem('Startdokument entfernen', 'unset-start');
        } else {
          appendActionItem('Als Startdokument markieren', 'set-start', { className: 'uk-text-primary' });
        }

        appendActionItem('Duplizieren', 'duplicate');
        appendActionItem('Download', 'download');

        const divider = document.createElement('li');
        divider.className = 'uk-nav-divider';
        dropdownList.appendChild(divider);

        appendActionItem('Löschen', 'delete', { danger: true });

        dropdown.appendChild(dropdownList);
        menuWrapper.appendChild(dropdown);
        actionsCell.appendChild(menuWrapper);
        row.appendChild(actionsCell);

        articlesTableBody.appendChild(row);

        if (window.UIkit) {
          if (typeof UIkit.update === 'function') {
            UIkit.update(menuWrapper, 'mutation');
          } else {
            if (typeof UIkit.icon === 'function') {
              UIkit.icon(menuToggle);
            }
            if (typeof UIkit.dropdown === 'function') {
              UIkit.dropdown(dropdown, { toggle: menuToggle });
            }
          }
        }
      });
    }

    function markdownToEditorState(markdown) {
      const sanitized = (markdown || '').replace(/\r\n/g, '\n');
      const segments = sanitized.split(/\n{2,}/).map(part => part.trim()).filter(Boolean);
      const blocks = [];

      segments.forEach(segment => {
        if (/^```/.test(segment) && /```$/.test(segment)) {
          const code = segment.replace(/^```[a-zA-Z0-9_-]*\n?/, '').replace(/```$/, '');
          blocks.push({ type: 'code', data: { code } });
          return;
        }

        const listLines = segment.split('\n');
        if (listLines.every(line => /^[-*]\s+/.test(line))) {
          const items = listLines.map(line => line.replace(/^[-*]\s+/, '').trim()).filter(Boolean);
          if (items.length) {
            blocks.push({ type: 'list', data: { style: 'unordered', items } });
            return;
          }
        }
        if (listLines.every(line => /^\d+\.\s+/.test(line))) {
          const items = listLines.map(line => line.replace(/^\d+\.\s+/, '').trim()).filter(Boolean);
          if (items.length) {
            blocks.push({ type: 'list', data: { style: 'ordered', items } });
            return;
          }
        }
        if (listLines.every(line => /^>\s?/.test(line))) {
          const text = listLines.map(line => line.replace(/^>\s?/, '').trim()).join(' ');
          blocks.push({ type: 'quote', data: { text } });
          return;
        }

        const headerMatch = segment.match(/^(#{1,6})\s+(.*)$/);
        if (headerMatch) {
          const level = Math.min(headerMatch[1].length, 6);
          const text = headerMatch[2].trim();
          blocks.push({ type: 'header', data: { level, text } });
          return;
        }

        const text = segment.replace(/\n/g, '<br>');
        blocks.push({ type: 'paragraph', data: { text } });
      });

      return { blocks };
    }

    function editorStateToMarkdown(article) {
      if (article && typeof article.contentMarkdown === 'string') {
        return article.contentMarkdown;
      }
      return '';
    }

    function resetSettingsForm() {
      settingsActiveInput.checked = false;
      setMenuLabelInputs(null, '');
      if (settingsUpdated) {
        settingsUpdated.textContent = '';
      }
      resetThemeForm();
    }

    function updateSettingsForm(settings) {
      if (!settings) {
        resetSettingsForm();
        return;
      }
      settingsActiveInput.checked = Boolean(settings.active);
      setMenuLabelInputs(settings.menuLabels, settings.menuLabel || '');
      if (settingsUpdated) {
        settingsUpdated.textContent = settings.updatedAt ? `Aktualisiert: ${formatDate(settings.updatedAt)}` : '';
      }
    }

    function fetchJson(path, options = {}) {
      return window.apiFetch(withNamespace(path), options).then(response => {
        if (!response.ok) {
          const error = new Error(`Request failed with status ${response.status}`);
          error.status = response.status;
          throw error;
        }
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
          return response.json();
        }
        return response;
      });
    }

    function loadPage(pageId) {
      const id = Number(pageId);
      if (!Number.isFinite(id) || id <= 0) {
        return;
      }
      state.pageId = id;
      state.settings = null;
      state.theme = null;
      state.articles = [];
      hideFeedback();
      toggleLoadingRow(true);
      settingsCard.hidden = true;
      articlesCard.hidden = true;

      const selectedPage = findPageById(id);
      if (selectedPage) {
        setSelectedSlug(selectedPage.slug);
      }

      fetchJson(`/admin/pages/${id}/wiki`).then(data => {
        state.pageSlug = data.page && data.page.slug ? data.page.slug : (selectedPage ? selectedPage.slug : '');
        setSelectedSlug(state.pageSlug);
        state.settings = data.settings || null;
        if (data.themeDefaults && typeof data.themeDefaults === 'object') {
          state.themeDefaults = {
            bodyClasses: Array.isArray(data.themeDefaults.bodyClasses) ? data.themeDefaults.bodyClasses : ['marketing-wiki'],
            stylesheets: Array.isArray(data.themeDefaults.stylesheets) ? data.themeDefaults.stylesheets : [],
            colors: { ...defaultThemeColors, ...(data.themeDefaults.colors || {}) },
          };
        }
        state.theme = data.theme || null;
        state.articles = Array.isArray(data.articles) ? data.articles.map(normalizeArticle) : [];
        sortArticlesInState();
        updateSettingsForm(state.settings);
        const effectiveTheme = state.theme || { ...state.themeDefaults };
        updateThemeForm(effectiveTheme);
        settingsCard.hidden = false;
        articlesCard.hidden = false;
        updateLocaleFilterOptions();
        renderArticles();
      }).catch(error => {
        console.error('Failed to load wiki data', error);
        showFeedback('Wiki-Daten konnten nicht geladen werden.', 'danger');
        resetSettingsForm();
        resetThemeForm();
        articlesTableBody.innerHTML = '';
        articlesEmpty.hidden = false;
      });
    }

    function saveSettings(event) {
      event.preventDefault();
      if (!state.pageId) {
        return;
      }
      const payload = {
        active: settingsActiveInput.checked,
        menuLabels: buildMenuLabelsPayload()
      };
      payload.menuLabel = payload.menuLabels.de || null;
      settingsSubmit.disabled = true;
      fetchJson(`/admin/pages/${state.pageId}/wiki/settings`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(data => {
        state.settings = data;
        updateSettingsForm(state.settings);
        notify(messages.settingsSaved, 'success');
      }).catch(error => {
        console.error('Failed to save settings', error);
        notify(messages.settingsError, 'danger');
      }).finally(() => {
        settingsSubmit.disabled = false;
      });
    }

    function saveTheme(event) {
      if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
      }
      if (!state.pageId) {
        return;
      }

      let payload;
      try {
        payload = buildThemePayloadFromForm();
      } catch (error) {
        notify(error?.message || messages.themeInvalid, 'danger');
        return;
      }

      themeSaveButton.disabled = true;
      fetchJson(`/admin/pages/${state.pageId}/wiki/theme`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(data => {
        state.theme = data || null;
        const effectiveTheme = state.theme || { ...state.themeDefaults };
        updateThemeForm(effectiveTheme);
        notify(messages.themeSaved, 'success');
      }).catch(error => {
        console.error('Failed to save theme', error);
        notify(messages.themeError, 'danger');
      }).finally(() => {
        themeSaveButton.disabled = false;
      });
    }

    function ensureModal() {
      if (modalInstance) {
        return modalInstance;
      }
      if (!modalElement || typeof UIkit === 'undefined' || !UIkit.modal) {
        return null;
      }
      try {
        modalInstance = UIkit.modal(modalElement);
      } catch (error) {
        console.warn('Failed to initialise wiki modal', error);
        modalInstance = null;
      }
      return modalInstance;
    }

    function openArticleModal(article) {
      const modal = ensureModal();
      if (!modal) {
        return;
      }
      modalError.hidden = true;
      modalError.textContent = '';
      if (article) {
        modalTitle.textContent = 'Artikel bearbeiten';
        modalIdInput.value = String(article.id);
        modalLocaleInput.value = article.locale;
        modalStatusSelect.value = article.status;
        modalTitleInput.value = article.title;
        modalSlugInput.value = article.slug;
        modalExcerptInput.value = article.excerpt;
        modalContentInput.value = editorStateToMarkdown(article);
        modalUpdatedLabel.textContent = article.updatedAt ? `Aktualisiert: ${formatDate(article.updatedAt)}` : '';
        modalStartInput.checked = Boolean(article.isStartDocument);
      } else {
        modalTitle.textContent = 'Artikel erstellen';
        modalIdInput.value = '';
        modalLocaleInput.value = 'de';
        modalStatusSelect.value = 'draft';
        modalTitleInput.value = '';
        modalSlugInput.value = '';
        modalExcerptInput.value = '';
        modalContentInput.value = '';
        modalUpdatedLabel.textContent = '';
        modalStartInput.checked = false;
      }
      modal.show();
    }

    function closeArticleModal() {
      const modal = ensureModal();
      if (modal) {
        modal.hide();
      }
    }

    function getArticleById(id) {
      return state.articles.find(article => article.id === id) || null;
    }

    function saveArticle(event) {
      event.preventDefault();
      if (!state.pageId) {
        return;
      }
      let locale = modalLocaleInput.value.trim();
      const title = modalTitleInput.value.trim();
      let slug = modalSlugInput.value.trim();
      const excerpt = modalExcerptInput.value.trim();
      const content = modalContentInput.value;
      const status = modalStatusSelect.value;
      const id = modalIdInput.value ? Number(modalIdInput.value) : null;

      if (locale) {
        locale = locale.toLowerCase();
        modalLocaleInput.value = locale;
      }

      if (!slug && title) {
        slug = slugify(title);
      }
      const normalizedSlug = slugify(slug);
      if (normalizedSlug) {
        slug = normalizedSlug;
        modalSlugInput.value = slug;
      }

      if (!locale || !title || !slug) {
        modalError.textContent = 'Locale, Titel und Slug dürfen nicht leer sein.';
        modalError.hidden = false;
        return;
      }

      modalError.hidden = true;
      modalSubmit.disabled = true;

      const payload = {
        id: id || undefined,
        locale,
        slug,
        title,
        excerpt: excerpt || null,
        status,
        editor: markdownToEditorState(content),
        isStartDocument: modalStartInput.checked
      };

      if (id) {
        const existing = getArticleById(id);
        if (existing) {
          payload.sortIndex = existing.sortIndex;
        }
      }

      fetchJson(`/admin/pages/${state.pageId}/wiki/articles`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(data => {
        const article = normalizeArticle(data);
        upsertArticle(article);
        updateLocaleFilterOptions();
        renderArticles();
        notify(messages.articleSaved, 'success');
        closeArticleModal();
      }).catch(error => {
        console.error('Failed to save article', error);
        modalError.textContent = messages.articleError;
        modalError.hidden = false;
      }).finally(() => {
        modalSubmit.disabled = false;
      });
    }

    function updateArticleStatus(articleId, status) {
      fetchJson(`/admin/pages/${state.pageId}/wiki/articles/${articleId}/status`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status })
      }).then(data => {
        const updated = normalizeArticle(data);
        upsertArticle(updated);
        renderArticles();
        notify(messages.articleSaved, 'success');
      }).catch(error => {
        console.error('Failed to update status', error);
        notify(messages.articleError, 'danger');
      });
    }

    function updateStartDocument(articleId, isStartDocument) {
      fetchJson(`/admin/pages/${state.pageId}/wiki/articles/${articleId}/start`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ isStartDocument })
      }).then(data => {
        const updated = normalizeArticle(data);
        upsertArticle(updated);
        renderArticles();
        notify(isStartDocument ? messages.articleStartMarked : messages.articleStartRemoved, 'success');
      }).catch(error => {
        console.error('Failed to update start document', error);
        notify(messages.articleError, 'danger');
      });
    }

    function duplicateArticle(articleId) {
      fetchJson(`/admin/pages/${state.pageId}/wiki/articles/${articleId}/duplicate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
      }).then(data => {
        const duplicate = normalizeArticle(data);
        upsertArticle(duplicate);
        updateLocaleFilterOptions();
        renderArticles();
        notify(messages.articleDuplicate, 'success');
      }).catch(error => {
        console.error('Failed to duplicate article', error);
        notify(messages.articleDuplicateError, 'danger');
      });
    }

    function resetUploadInput() {
      if (uploadInput) {
        uploadInput.value = '';
      }
    }

    function getUploadLocale() {
      if (state.filterLocale && state.filterLocale !== 'all') {
        return state.filterLocale;
      }
      return 'de';
    }

    function handleMarkdownUpload() {
      if (!uploadInput) {
        return;
      }

      const files = uploadInput.files;
      if (!files || files.length === 0) {
        return;
      }

      if (!state.pageId) {
        notify(messages.articleImportNoPage, 'danger');
        resetUploadInput();
        return;
      }

      const file = files[0];
      const name = typeof file.name === 'string' ? file.name : '';
      const lower = name.toLowerCase();
      if (!(lower.endsWith('.md') || lower.endsWith('.markdown'))) {
        notify(messages.articleImportInvalid, 'danger');
        resetUploadInput();
        return;
      }

      if (uploadButton) {
        uploadButton.disabled = true;
      }

      const formData = new FormData();
      formData.append('markdown', file);
      formData.append('locale', getUploadLocale());
      formData.append('status', 'draft');

      window
        .apiFetch(withNamespace(`/admin/pages/${state.pageId}/wiki/articles`), {
          method: 'POST',
          body: formData
        })
        .then(async response => {
          if (!response.ok) {
            let message = messages.articleImportError;
            try {
              const data = await response.json();
              if (data && typeof data.error === 'string' && data.error.trim() !== '') {
                message = data.error;
              }
            } catch (parseError) {
              // ignore json parse errors
            }
            const error = new Error(message);
            error.status = response.status;
            throw error;
          }
          return response.json();
        })
        .then(data => {
          const article = normalizeArticle(data);
          upsertArticle(article);
          updateLocaleFilterOptions();
          if (state.filterLocale !== 'all' && state.filterLocale !== article.locale) {
            state.filterLocale = article.locale;
            localeFilter.value = article.locale;
          }
          renderArticles();
          notify(messages.articleImport, 'success');
          openArticleModal(article);
        })
        .catch(error => {
          console.error('Failed to import markdown', error);
          notify(error.message || messages.articleImportError, 'danger');
        })
        .finally(() => {
          if (uploadButton) {
            uploadButton.disabled = false;
          }
          resetUploadInput();
        });
    }

    function deleteArticle(articleId) {
      if (!confirm(messages.articleDeleteConfirm)) {
        return;
      }
      window.apiFetch(withNamespace(`/admin/pages/${state.pageId}/wiki/articles/${articleId}`), {
        method: 'DELETE'
      }).then(response => {
        if (!response.ok && response.status !== 204) {
          throw new Error('Failed to delete');
        }
        const index = state.articles.findIndex(article => article.id === articleId);
        if (index >= 0) {
          state.articles.splice(index, 1);
        }
        sortArticlesInState();
        updateLocaleFilterOptions();
        renderArticles();
        notify(messages.articleDeleted, 'success');
      }).catch(error => {
        console.error('Failed to delete article', error);
        notify(messages.articleError, 'danger');
      });
    }

    function moveArticle(articleId, direction) {
      const index = state.articles.findIndex(article => article.id === articleId);
      if (index < 0) {
        return;
      }
      const newIndex = index + direction;
      if (newIndex < 0 || newIndex >= state.articles.length) {
        return;
      }
      const reordered = state.articles.slice();
      const [article] = reordered.splice(index, 1);
      reordered.splice(newIndex, 0, article);
      const orderPayload = reordered.map(item => item.id);

      fetchJson(`/admin/pages/${state.pageId}/wiki/articles/sort`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order: orderPayload })
      }).then(() => {
        state.articles = reordered.map((item, position) => ({ ...item, sortIndex: position + 1 }));
        sortArticlesInState();
        renderArticles();
        notify(messages.sortSaved, 'success');
      }).catch(error => {
        console.error('Failed to sort articles', error);
        notify(messages.sortError, 'danger');
      });
    }

    function handleArticleAction(event) {
      const target = event.target.closest('[data-action]');
      if (!target) {
        return;
      }
      event.preventDefault();
      const row = target.closest('tr');
      if (!row) {
        return;
      }
      const articleId = Number(row.dataset.articleId);
      if (!Number.isFinite(articleId)) {
        return;
      }

      switch (target.dataset.action) {
        case 'edit':
          openArticleModal(getArticleById(articleId));
          break;
        case 'publish':
          updateArticleStatus(articleId, 'published');
          break;
        case 'set-draft':
          updateArticleStatus(articleId, 'draft');
          break;
        case 'archive':
          updateArticleStatus(articleId, 'archived');
          break;
        case 'restore':
          updateArticleStatus(articleId, 'draft');
          break;
        case 'set-start':
          updateStartDocument(articleId, true);
          break;
        case 'unset-start':
          updateStartDocument(articleId, false);
          break;
        case 'duplicate':
          duplicateArticle(articleId);
          break;
        case 'download':
          window.open(withNamespace(`${basePath}/admin/pages/${state.pageId}/wiki/articles/${articleId}/download`), '_blank');
          break;
        case 'delete':
          deleteArticle(articleId);
          break;
        case 'move-up':
          moveArticle(articleId, -1);
          break;
        case 'move-down':
          moveArticle(articleId, 1);
          break;
        default:
          break;
      }

      const dropdownElement = target.closest('[uk-dropdown]');
      if (dropdownElement && typeof UIkit !== 'undefined' && UIkit.dropdown) {
        const instance = UIkit.dropdown(dropdownElement);
        if (instance) {
          instance.hide(false);
        }
      }
    }

    pageSelect.addEventListener('change', event => {
      const id = Number(event.target.value);
      state.filterLocale = 'all';
      localeFilter.value = 'all';
      loadPage(id);
    });

    themeSaveButton.addEventListener('click', saveTheme);
    themeResetButton.addEventListener('click', () => {
      state.theme = null;
      resetThemeForm();
    });
    settingsForm.addEventListener('submit', saveSettings);
    createButton.addEventListener('click', () => openArticleModal(null));
    uploadButton.addEventListener('click', () => {
      if (!state.pageId) {
        notify(messages.articleImportNoPage, 'danger');
        return;
      }
      uploadInput.click();
    });
    uploadInput.addEventListener('change', handleMarkdownUpload);
    articlesTableBody.addEventListener('click', handleArticleAction);
    localeFilter.addEventListener('change', event => {
      state.filterLocale = event.target.value;
      renderArticles();
    });
    modalForm.addEventListener('submit', saveArticle);

    if (!state.pageId && pagesData.length > 0) {
      state.pageId = Number(pagesData[0].id);
    }

    if (state.pageId) {
      pageSelect.value = String(state.pageId);
      loadPage(state.pageId);
    }
  })();
}

export default {};
