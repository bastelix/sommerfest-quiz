/* global UIkit */

import './marketing-menu-admin.js';
import TableManager from './table-manager.js';
import { createCellEditor } from './edit-helpers.js';
import {
  setCurrentEvent as switchEvent,
  switchPending,
  lastSwitchFailed,
  getSwitchEpoch,
  registerCacheReset,
  registerScopedAbortController,
  isCurrentEpoch
} from './event-switcher.js';
import { applyLazyImage } from './lazy-images.js';

const basePath = window.basePath || '';
const withBase = path => basePath + path;
const escape = url => encodeURI(url);
const transEventsFetchError = window.transEventsFetchError || 'Veranstaltungen konnten nicht geladen werden';
const transDashboardLinkCopied = window.transDashboardLinkCopied || 'Link kopiert';
const transDashboardLinkMissing = window.transDashboardLinkMissing || 'Kein Link verfügbar';
const transDashboardCopyFailed = window.transDashboardCopyFailed || 'Kopieren fehlgeschlagen';
const transDashboardTokenRotated = window.transDashboardTokenRotated || 'Neues Token erstellt';
const transDashboardTokenRotateError = window.transDashboardTokenRotateError || 'Token konnte nicht erneuert werden';
const transDashboardNoEvent = window.transDashboardNoEvent || 'Kein Event ausgewählt';

const parseBooleanOption = (candidate) => {
  if (candidate === null || candidate === undefined) {
    return null;
  }
  if (typeof candidate === 'boolean') {
    return candidate;
  }
  if (typeof candidate === 'number') {
    if (!Number.isFinite(candidate)) {
      return null;
    }
    if (candidate === 0) {
      return false;
    }
    if (candidate === 1) {
      return true;
    }
    return candidate > 0;
  }
  if (typeof candidate === 'string') {
    const normalized = candidate.trim().toLowerCase();
    if (normalized === '') {
      return null;
    }
    if (['1', 'true', 'yes', 'on'].includes(normalized)) {
      return true;
    }
    if (['0', 'false', 'no', 'off'].includes(normalized)) {
      return false;
    }
  }
  return null;
};

const resolveBooleanOption = (value, fallback = false) => {
  const parsed = parseBooleanOption(value);
  if (parsed !== null) {
    return parsed;
  }
  const fallbackParsed = parseBooleanOption(fallback);
  if (fallbackParsed !== null) {
    return fallbackParsed;
  }
  return Boolean(fallback);
};

const formUtils = {
  toArray(list) {
    if (Array.isArray(list)) {
      return list;
    }
    if (!list) {
      return [];
    }
    if (typeof list[Symbol.iterator] === 'function') {
      return Array.from(list);
    }
    return [];
  },
  checkBoxes(list, selectedValues = []) {
    const normalized = new Set(
      (Array.isArray(selectedValues) ? selectedValues : [])
        .map(value => String(value))
    );
    formUtils.toArray(list).forEach(input => {
      if (!input || typeof input.checked === 'undefined') {
        return;
      }
      input.checked = normalized.has(String(input.value));
    });
  },
  readChecked(list) {
    return formUtils.toArray(list)
      .filter(input => input && input.checked)
      .map(input => String(input.value));
  }
};

function isAllowed(url, allowedPaths = []) {
  try {
    const parsed = new URL(url, window.location.origin);
    const domains = [];
    if (window.location.hostname) domains.push(window.location.hostname.toLowerCase());
    if (window.mainDomain) domains.push(window.mainDomain.toLowerCase());
    const host = parsed.hostname.toLowerCase();
    const domainOk = parsed.protocol === 'https:' && domains.some(d => host === d || host.endsWith('.' + d));
    const pathOk = !allowedPaths.length || allowedPaths.some(p => parsed.pathname.startsWith(p));
    return domainOk && pathOk;
  } catch (e) {
    return false;
  }
}
const getCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
  window.csrfToken || '';
const parseDatasetJson = (value, fallback = []) => {
  if (!value) {
    return fallback;
  }
  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? parsed : fallback;
  } catch (error) {
    return fallback;
  }
};
function showUpgradeModal() {
  if (document.getElementById('upgrade-modal')) return;
  const modal = document.createElement('div');
  modal.id = 'upgrade-modal';
  modal.setAttribute('uk-modal', '');
  modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">' +
    '<h3 class="uk-modal-title">' + (window.transUpgradeTitle || 'Limit erreicht') + '</h3>' +
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

  return fetch(withBase(path), opts)
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
window.notify = (msg, status = 'primary', timeout = 2000) => {
  if (typeof UIkit !== 'undefined' && UIkit.notification) {
    UIkit.notification({ message: msg, status, pos: 'top-center', timeout });
  } else {
    alert(msg);
  }
};

const buildProjectPageTreeList = (nodes, level = 0) => {
  const list = document.createElement('ul');
  list.className = 'uk-list uk-list-collapse';
  if (level > 0) {
    list.classList.add('uk-margin-small-left');
  }

  nodes.forEach(node => {
    const item = document.createElement('li');
    const row = document.createElement('div');
    row.className = 'uk-flex uk-flex-between uk-flex-middle uk-flex-wrap';

    const info = document.createElement('div');
    const label = node.title || node.slug || 'Ohne Titel';
    const title = node.editUrl
      ? createProjectLink(label, node.editUrl, 'uk-text-bold')
      : document.createElement('span');
    if (!node.editUrl) {
      title.className = 'uk-text-bold';
      title.textContent = label;
    }
    info.appendChild(title);

    if (node.slug) {
      const slug = document.createElement('span');
      slug.className = 'uk-text-meta uk-margin-small-left';
      slug.textContent = `/${node.slug}`;
      info.appendChild(slug);
    }

    row.appendChild(info);

    const meta = document.createElement('div');
    meta.className = 'uk-flex uk-flex-middle uk-flex-wrap';
    if (node.type) {
      const typeLabel = document.createElement('span');
      typeLabel.className = 'uk-label uk-label-default';
      typeLabel.textContent = node.type;
      meta.appendChild(typeLabel);
    }
    if (node.language) {
      const language = document.createElement('span');
      language.className = 'uk-text-meta uk-margin-small-left';
      language.textContent = node.language;
      meta.appendChild(language);
    }
    if (meta.childElementCount > 0) {
      row.appendChild(meta);
    }

    item.appendChild(row);
    if (Array.isArray(node.children) && node.children.length) {
      item.appendChild(buildProjectPageTreeList(node.children, level + 1));
    }
    list.appendChild(item);
  });

  return list;
};

const createProjectEmptyState = message => {
  const empty = document.createElement('div');
  empty.className = 'uk-text-meta';
  empty.textContent = message;
  return empty;
};

const buildProjectAdminUrl = (path, namespace, query = {}, fragment = '') => {
  const params = new URLSearchParams();
  if (namespace) {
    params.set('namespace', namespace);
  }
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      params.set(key, String(value));
    }
  });
  const queryString = params.toString();
  let url = withBase(path);
  if (queryString) {
    url += `?${queryString}`;
  }
  if (fragment) {
    url += `#${String(fragment).replace(/^#/, '')}`;
  }
  return url;
};

const createProjectEmptyStateWithActions = namespace => {
  const wrapper = document.createElement('div');
  wrapper.className = 'uk-alert uk-alert-primary uk-margin-small';

  const title = document.createElement('div');
  title.className = 'uk-text-bold';
  title.textContent = 'Noch keine Inhalte vorhanden.';
  wrapper.appendChild(title);

  const hint = document.createElement('div');
  hint.className = 'uk-text-meta uk-margin-small-top';
  hint.textContent = 'Jetzt Inhalte anlegen:';
  wrapper.appendChild(hint);

  const list = document.createElement('ul');
  list.className = 'uk-list uk-list-collapse uk-margin-small-top';
  const actions = [
    {
      label: 'Jetzt Inhalte anlegen (Seiten)',
      url: buildProjectAdminUrl('/admin/pages/content', namespace)
    },
    {
      label: 'Jetzt Inhalte anlegen (Wiki)',
      url: buildProjectAdminUrl('/admin/pages/wiki', namespace)
    },
    {
      label: 'Jetzt Inhalte anlegen (Landing-News)',
      url: buildProjectAdminUrl('/admin/landing-news/create', namespace)
    },
    {
      label: 'Jetzt Inhalte anlegen (Newsletter)',
      url: buildProjectAdminUrl('/admin/newsletter', namespace)
    }
  ];
  actions.forEach(action => {
    const item = document.createElement('li');
    item.appendChild(createProjectLink(action.label, action.url));
    list.appendChild(item);
  });
  wrapper.appendChild(list);

  return wrapper;
};

const createProjectStatusLabel = (text, status) => {
  const label = document.createElement('span');
  const classMap = {
    published: 'uk-label-success',
    draft: 'uk-label-warning',
    archived: 'uk-label-default',
    active: 'uk-label-success',
    inactive: 'uk-label-warning'
  };
  const className = classMap[status] || 'uk-label-default';
  label.className = `uk-label ${className}`;
  label.textContent = text;
  return label;
};

const createProjectLink = (label, url, className = '') => {
  const link = document.createElement('a');
  link.textContent = label;
  link.href = url;
  if (className) {
    link.className = className;
  }
  return link;
};

const buildProjectWikiList = (entries) => {
  const list = document.createElement('ul');
  list.className = 'uk-list uk-list-divider';

  entries.forEach(entry => {
    const item = document.createElement('li');
    const header = document.createElement('div');
    header.className = 'uk-flex uk-flex-between uk-flex-middle uk-flex-wrap';

    const info = document.createElement('div');
    const pageLabel = entry.page?.title || entry.page?.slug || 'Ohne Titel';
    const pageTitle = entry.page?.editUrl
      ? createProjectLink(pageLabel, entry.page.editUrl, 'uk-text-bold')
      : document.createElement('span');
    if (!entry.page?.editUrl) {
      pageTitle.className = 'uk-text-bold';
      pageTitle.textContent = pageLabel;
    }
    info.appendChild(pageTitle);

    if (entry.page?.slug) {
      const slug = document.createElement('span');
      slug.className = 'uk-text-meta uk-margin-small-left';
      slug.textContent = `/${entry.page.slug}`;
      info.appendChild(slug);
    }
    header.appendChild(info);
    item.appendChild(header);

    const articles = Array.isArray(entry.articles) ? entry.articles : [];
    if (articles.length) {
      const articleList = document.createElement('ul');
      articleList.className = 'uk-list uk-list-collapse uk-margin-small-top';
      articles.forEach(article => {
        const articleItem = document.createElement('li');
        const articleRow = document.createElement('div');
        articleRow.className = 'uk-flex uk-flex-between uk-flex-middle uk-flex-wrap';

        const articleInfo = document.createElement('div');
        const articleLabel = article.title || article.slug || 'Ohne Titel';
        const articleTitle = article.editUrl
          ? createProjectLink(articleLabel, article.editUrl)
          : document.createElement('span');
        if (!article.editUrl) {
          articleTitle.textContent = articleLabel;
        }
        articleInfo.appendChild(articleTitle);
        if (article.slug) {
          const articleSlug = document.createElement('span');
          articleSlug.className = 'uk-text-meta uk-margin-small-left';
          articleSlug.textContent = article.slug;
          articleInfo.appendChild(articleSlug);
        }
        articleRow.appendChild(articleInfo);

        const meta = document.createElement('div');
        meta.className = 'uk-flex uk-flex-middle uk-flex-wrap';
        if (article.locale) {
          const locale = document.createElement('span');
          locale.className = 'uk-text-meta';
          locale.textContent = article.locale;
          meta.appendChild(locale);
        }
        if (article.status) {
          const statusLabel = createProjectStatusLabel(
            article.status === 'published' ? 'Veröffentlicht' : article.status,
            article.status
          );
          statusLabel.classList.add('uk-margin-small-left');
          meta.appendChild(statusLabel);
        }
        if (article.isStartDocument) {
          const startDoc = createProjectStatusLabel('Start', 'active');
          startDoc.classList.add('uk-margin-small-left');
          meta.appendChild(startDoc);
        }
        if (meta.childElementCount > 0) {
          articleRow.appendChild(meta);
        }
        articleItem.appendChild(articleRow);
        articleList.appendChild(articleItem);
      });
      item.appendChild(articleList);
    }

    list.appendChild(item);
  });

  return list;
};

const buildProjectNewsList = (entries) => {
  const list = document.createElement('ul');
  list.className = 'uk-list uk-list-divider';

  entries.forEach(entry => {
    const item = document.createElement('li');
    const header = document.createElement('div');
    header.className = 'uk-flex uk-flex-between uk-flex-middle uk-flex-wrap';

    const info = document.createElement('div');
    const pageLabel = entry.page?.title || entry.page?.slug || 'Ohne Titel';
    const pageTitle = entry.page?.editUrl
      ? createProjectLink(pageLabel, entry.page.editUrl, 'uk-text-bold')
      : document.createElement('span');
    if (!entry.page?.editUrl) {
      pageTitle.className = 'uk-text-bold';
      pageTitle.textContent = pageLabel;
    }
    info.appendChild(pageTitle);

    if (entry.page?.slug) {
      const slug = document.createElement('span');
      slug.className = 'uk-text-meta uk-margin-small-left';
      slug.textContent = `/${entry.page.slug}`;
      info.appendChild(slug);
    }
    header.appendChild(info);
    item.appendChild(header);

    const newsItems = Array.isArray(entry.items) ? entry.items : [];
    if (newsItems.length) {
      const newsList = document.createElement('ul');
      newsList.className = 'uk-list uk-list-collapse uk-margin-small-top';
      newsItems.forEach(news => {
        const newsItem = document.createElement('li');
        const newsRow = document.createElement('div');
        newsRow.className = 'uk-flex uk-flex-between uk-flex-middle uk-flex-wrap';

        const newsInfo = document.createElement('div');
        const newsLabel = news.title || news.slug || 'Ohne Titel';
        const newsTitle = news.editUrl
          ? createProjectLink(newsLabel, news.editUrl)
          : document.createElement('span');
        if (!news.editUrl) {
          newsTitle.textContent = newsLabel;
        }
        newsInfo.appendChild(newsTitle);
        if (news.slug) {
          const newsSlug = document.createElement('span');
          newsSlug.className = 'uk-text-meta uk-margin-small-left';
          newsSlug.textContent = news.slug;
          newsInfo.appendChild(newsSlug);
        }
        newsRow.appendChild(newsInfo);

        const meta = document.createElement('div');
        meta.className = 'uk-flex uk-flex-middle uk-flex-wrap';
        if (news.isPublished !== undefined) {
          const labelText = news.isPublished ? 'Veröffentlicht' : 'Entwurf';
          const labelState = news.isPublished ? 'published' : 'draft';
          meta.appendChild(createProjectStatusLabel(labelText, labelState));
        }
        if (news.publishedAt) {
          const publishedAt = document.createElement('span');
          publishedAt.className = 'uk-text-meta uk-margin-small-left';
          publishedAt.textContent = new Date(news.publishedAt).toLocaleDateString('de-DE');
          meta.appendChild(publishedAt);
        }
        if (meta.childElementCount > 0) {
          newsRow.appendChild(meta);
        }

        newsItem.appendChild(newsRow);
        newsList.appendChild(newsItem);
      });
      item.appendChild(newsList);
    }

    list.appendChild(item);
  });

  return list;
};

const buildProjectSlugList = (slugs) => {
  const list = document.createElement('ul');
  list.className = 'uk-list uk-list-collapse';
  slugs.forEach(slug => {
    const entry = typeof slug === 'string' ? { slug } : slug || {};
    const label = entry.slug || '';
    const item = document.createElement('li');
    if (entry.editUrl) {
      item.appendChild(createProjectLink(label, entry.editUrl));
    } else {
      item.textContent = label;
    }
    list.appendChild(item);
  });
  return list;
};

const buildProjectMediaList = (media) => {
  const wrapper = document.createElement('div');

  const files = Array.isArray(media?.files) ? media.files : [];
  const missing = Array.isArray(media?.missing) ? media.missing : [];

  if (files.length) {
    const fileList = document.createElement('ul');
    fileList.className = 'uk-list uk-list-divider';
    files.forEach(file => {
      const item = document.createElement('li');
      const row = document.createElement('div');
      row.className = 'uk-flex uk-flex-between uk-flex-middle uk-flex-wrap';

      const info = document.createElement('div');
      const path = document.createElement('span');
      path.textContent = file.path || 'Ohne Pfad';
      info.appendChild(path);
      row.appendChild(info);

      const meta = document.createElement('div');
      meta.className = 'uk-flex uk-flex-middle uk-flex-wrap';
      const count = document.createElement('span');
      count.className = 'uk-text-meta';
      count.textContent = `${file.count || 0} Referenzen`;
      meta.appendChild(count);
      row.appendChild(meta);

      item.appendChild(row);
      fileList.appendChild(item);
    });
    wrapper.appendChild(fileList);
  }

  if (missing.length) {
    const missingHeading = document.createElement('div');
    missingHeading.className = 'uk-text-bold uk-margin-small-top';
    missingHeading.textContent = 'Fehlende Medien';
    wrapper.appendChild(missingHeading);

    const missingList = document.createElement('ul');
    missingList.className = 'uk-list uk-list-collapse';
    missing.forEach(entry => {
      const item = document.createElement('li');
      item.textContent = entry.displayPath || entry.path || '';
      missingList.appendChild(item);
    });
    wrapper.appendChild(missingList);
  }

  if (!files.length && !missing.length) {
    wrapper.appendChild(createProjectEmptyState('Keine Medien-Referenzen.'));
  }

  return wrapper;
};

const appendProjectBlock = (container, title, content) => {
  const block = document.createElement('div');
  block.className = 'uk-margin-small';
  const heading = document.createElement('h5');
  heading.className = 'uk-margin-small-bottom';
  heading.textContent = title;
  block.appendChild(heading);
  block.appendChild(content);
  container.appendChild(block);
};

const isProjectContentEmpty = section => {
  const pages = Array.isArray(section.pages) ? section.pages : [];
  const wikiEntries = Array.isArray(section.wiki) ? section.wiki : [];
  const newsEntries = Array.isArray(section.landingNews) ? section.landingNews : [];
  const newsletterSlugs = Array.isArray(section.newsletterSlugs) ? section.newsletterSlugs : [];
  const mediaRefs = section.mediaReferences || {};
  const mediaFiles = Array.isArray(mediaRefs.files) ? mediaRefs.files : [];
  const mediaMissing = Array.isArray(mediaRefs.missing) ? mediaRefs.missing : [];
  return (
    pages.length === 0 &&
    wikiEntries.length === 0 &&
    newsEntries.length === 0 &&
    newsletterSlugs.length === 0 &&
    mediaFiles.length === 0 &&
    mediaMissing.length === 0
  );
};

const countProjectPages = (nodes) => {
  if (!Array.isArray(nodes)) {
    return 0;
  }
  return nodes.reduce((total, node) => {
    const children = Array.isArray(node.children) ? node.children : [];
    return total + 1 + countProjectPages(children);
  }, 0);
};

const buildProjectKpis = (namespaces) => {
  return namespaces.reduce((totals, section) => {
    totals.pages += countProjectPages(section.pages);
    totals.wiki += (Array.isArray(section.wiki) ? section.wiki : []).reduce(
      (sum, entry) => sum + (Array.isArray(entry.articles) ? entry.articles.length : 0),
      0
    );
    totals.news += (Array.isArray(section.landingNews) ? section.landingNews : []).reduce(
      (sum, entry) => sum + (Array.isArray(entry.items) ? entry.items.length : 0),
      0
    );
    totals.newsletter += Array.isArray(section.newsletterSlugs) ? section.newsletterSlugs.length : 0;
    const mediaRefs = section.mediaReferences || {};
    totals.media += (Array.isArray(mediaRefs.files) ? mediaRefs.files.length : 0)
      + (Array.isArray(mediaRefs.missing) ? mediaRefs.missing.length : 0);
    return totals;
  }, {
    pages: 0,
    wiki: 0,
    news: 0,
    newsletter: 0,
    media: 0
  });
};

const updateProjectKpis = (namespaces) => {
  const container = document.querySelector('[data-project-kpis]');
  if (!container) {
    return;
  }
  const totals = buildProjectKpis(namespaces);
  Object.entries(totals).forEach(([key, value]) => {
    const element = container.querySelector(`[data-project-kpi=\"${key}\"]`);
    if (element) {
      element.textContent = String(value);
    }
  });
};

const createProjectNamespaceBadge = (label, className) => {
  const badge = document.createElement('span');
  badge.className = className;
  badge.textContent = label;
  return badge;
};

const buildProjectNamespaceHeading = (section) => {
  const heading = document.createElement('h4');
  heading.className = 'uk-heading-line uk-margin-small-top';

  const headingText = document.createElement('span');
  headingText.textContent = section.namespace || 'default';
  heading.appendChild(headingText);

  const info = section.namespaceInfo || {};
  const label = typeof info.label === 'string' ? info.label.trim() : '';
  if (label) {
    const labelText = document.createElement('span');
    labelText.className = 'uk-text-meta uk-margin-small-left';
    labelText.textContent = label;
    heading.appendChild(labelText);
  }

  const badgeWrapper = document.createElement('span');
  badgeWrapper.className = 'uk-margin-small-left uk-flex uk-flex-middle uk-flex-wrap';

  const isDefault = info.is_default === true;
  const isActive = info.is_active !== false;
  if (isDefault) {
    badgeWrapper.appendChild(createProjectNamespaceBadge('default', 'uk-label uk-label-default uk-margin-small-left'));
  }
  if (!isActive) {
    badgeWrapper.appendChild(createProjectNamespaceBadge('inaktiv', 'uk-label uk-label-danger uk-margin-small-left'));
  }
  if (badgeWrapper.children.length > 0) {
    heading.appendChild(badgeWrapper);
  }

  return heading;
};

const renderProjectTree = (container, namespaces, emptyMessage) => {
  container.innerHTML = '';
  if (!namespaces.length) {
    container.appendChild(createProjectEmptyState(emptyMessage));
    updateProjectKpis([]);
    return;
  }

  namespaces.forEach(section => {
    const wrapper = document.createElement('div');
    wrapper.className = 'project-tree-section uk-margin';

    wrapper.appendChild(buildProjectNamespaceHeading(section));

    if (isProjectContentEmpty(section)) {
      wrapper.appendChild(createProjectEmptyStateWithActions(section.namespace || ''));
    }

    const pages = Array.isArray(section.pages) ? section.pages : [];
    appendProjectBlock(
      wrapper,
      'Pages',
      pages.length ? buildProjectPageTreeList(pages) : createProjectEmptyState('Keine Seiten vorhanden.')
    );

    const wikiEntries = Array.isArray(section.wiki) ? section.wiki : [];
    appendProjectBlock(
      wrapper,
      'Wiki-Artikel',
      wikiEntries.length ? buildProjectWikiList(wikiEntries) : createProjectEmptyState('Keine Wiki-Artikel vorhanden.')
    );

    const newsEntries = Array.isArray(section.landingNews) ? section.landingNews : [];
    appendProjectBlock(
      wrapper,
      'Landing-News',
      newsEntries.length ? buildProjectNewsList(newsEntries) : createProjectEmptyState('Keine Landing-News vorhanden.')
    );

    const newsletterSlugs = Array.isArray(section.newsletterSlugs) ? section.newsletterSlugs : [];
    appendProjectBlock(
      wrapper,
      'Newsletter-Slugs',
      newsletterSlugs.length ? buildProjectSlugList(newsletterSlugs) : createProjectEmptyState('Keine Newsletter-Slugs vorhanden.')
    );

    appendProjectBlock(
      wrapper,
      'Medien-Refs',
      buildProjectMediaList(section.mediaReferences || {})
    );

    container.appendChild(wrapper);
  });
};

const resolveProjectNamespace = (container) => {
  const candidate = container?.dataset.namespace || '';
  return candidate.trim();
};

const resolveNamespaceQuery = () => {
  const params = new URLSearchParams(window.location.search);
  return (params.get('namespace') || '').trim();
};

const withProjectNamespace = (endpoint, namespace) => {
  if (!namespace) {
    return endpoint;
  }
  const separator = endpoint.includes('?') ? '&' : '?';
  return `${endpoint}${separator}namespace=${encodeURIComponent(namespace)}`;
};

const initProjectTree = () => {
  const container = document.querySelector('[data-project-tree]');
  if (!container) {
    return;
  }
  const loading = container.querySelector('[data-project-tree-loading]');
  const emptyMessage = container.dataset.empty || 'Keine Namespace-Daten vorhanden.';
  const errorMessage = container.dataset.error || 'Namespace-Übersicht konnte nicht geladen werden.';
  const endpoint = container.dataset.endpoint || '/admin/projects/tree';
  const namespaceSelect = document.getElementById('namespaceSelect')
    || document.getElementById('projectNamespaceSelect')
    || document.getElementById('pageNamespaceSelect');
  const selectedNamespace = namespaceSelect?.value || '';
  const activeNamespace = (selectedNamespace || resolveProjectNamespace(container)).trim();
  const endpointWithNamespace = withProjectNamespace(endpoint, activeNamespace);

  if (loading) {
    loading.textContent = loading.textContent || 'Namespace-Übersicht wird geladen…';
  }

  apiFetch(endpointWithNamespace)
    .then(response => {
      if (!response.ok) {
        throw new Error('project-tree-request-failed');
      }
      return response.json();
    })
    .then(payload => {
      const namespaces = Array.isArray(payload?.namespaces) ? payload.namespaces : [];
      const filtered = activeNamespace
        ? namespaces.filter(section => (section.namespace || '').trim() === activeNamespace)
        : namespaces;
      renderProjectTree(container, filtered, emptyMessage);
      updateProjectKpis(filtered);
    })
    .catch(() => {
      if (loading) {
        loading.textContent = errorMessage;
      } else {
        const error = document.createElement('div');
        error.className = 'uk-text-danger';
        error.textContent = errorMessage;
        container.appendChild(error);
      }
      updateProjectKpis([]);
    });
};

const initProjectSettings = () => {
  const form = document.querySelector('[data-project-settings-form]');
  if (!form) {
    return;
  }

  const wrapper = form.closest('[data-project-settings]');
  const status = wrapper ? wrapper.querySelector('[data-project-settings-status]') : null;
  const updatedLabel = wrapper ? wrapper.querySelector('[data-project-settings-updated]') : null;
  const endpoint = wrapper?.dataset.endpoint || '/admin/projects/settings';
  const namespaceSelect = document.getElementById('namespaceSelect')
    || document.getElementById('projectNamespaceSelect')
    || document.getElementById('pageNamespaceSelect');

  const setStatus = (message, isError) => {
    if (!status) {
      return;
    }
    status.textContent = message;
    status.classList.toggle('uk-text-danger', Boolean(isError));
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const namespace = (namespaceSelect?.value || '').trim();
    if (!namespace) {
      setStatus('Namespace fehlt.', true);
      return;
    }

    const payload = { namespace };
    const consentInput = form.querySelector('#cookieConsentEnabled');
    const storageInput = form.querySelector('#cookieStorageKey');
    const bannerDeInput = form.querySelector('#cookieBannerTextDe');
    const bannerEnInput = form.querySelector('#cookieBannerTextEn');
    const vendorFlagsInput = form.querySelector('#cookieVendorFlags');
    const privacyInput = form.querySelector('#privacyUrl');
    const privacyDeInput = form.querySelector('#privacyUrlDe');
    const privacyEnInput = form.querySelector('#privacyUrlEn');

    if (consentInput) {
      payload.cookieConsentEnabled = Boolean(consentInput.checked);
    }
    if (storageInput) {
      payload.cookieStorageKey = storageInput.value || '';
    }
    if (bannerDeInput) {
      payload.cookieBannerTextDe = bannerDeInput.value || '';
    }
    if (bannerEnInput) {
      payload.cookieBannerTextEn = bannerEnInput.value || '';
    }
    if (vendorFlagsInput) {
      payload.cookieVendorFlags = vendorFlagsInput.value || '';
    }
    if (privacyInput) {
      payload.privacyUrl = privacyInput.value || '';
    }
    if (privacyDeInput) {
      payload.privacyUrlDe = privacyDeInput.value || '';
    }
    if (privacyEnInput) {
      payload.privacyUrlEn = privacyEnInput.value || '';
    }

    setStatus('Speichert…', false);

    try {
      const response = await apiFetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': getCsrfToken()
        },
        body: JSON.stringify(payload)
      });

      if (!response.ok) {
        throw new Error('project-settings-save-failed');
      }

      const result = await response.json();
      const settings = result?.settings || {};
      const storageInput = form.querySelector('#cookieStorageKey');
      const bannerDeInput = form.querySelector('#cookieBannerTextDe');
      const bannerEnInput = form.querySelector('#cookieBannerTextEn');
      const vendorFlagsInput = form.querySelector('#cookieVendorFlags');
      const enabledInput = form.querySelector('#cookieConsentEnabled');
      const privacyInput = form.querySelector('#privacyUrl');
      const privacyDeInput = form.querySelector('#privacyUrlDe');
      const privacyEnInput = form.querySelector('#privacyUrlEn');
      if (storageInput && typeof settings.cookie_storage_key === 'string') {
        storageInput.value = settings.cookie_storage_key;
      }
      if (bannerDeInput && typeof settings.cookie_banner_text_de === 'string') {
        bannerDeInput.value = settings.cookie_banner_text_de;
      }
      if (bannerEnInput && typeof settings.cookie_banner_text_en === 'string') {
        bannerEnInput.value = settings.cookie_banner_text_en;
      }
      if (vendorFlagsInput && settings.cookie_vendor_flags !== undefined) {
        if (typeof settings.cookie_vendor_flags === 'string') {
          vendorFlagsInput.value = settings.cookie_vendor_flags;
        } else {
          try {
            vendorFlagsInput.value = JSON.stringify(settings.cookie_vendor_flags, null, 2);
          } catch (error) {
            vendorFlagsInput.value = '';
          }
        }
      }
      if (enabledInput && typeof settings.cookie_consent_enabled === 'boolean') {
        enabledInput.checked = settings.cookie_consent_enabled;
      }
      if (privacyInput && typeof settings.privacy_url === 'string') {
        privacyInput.value = settings.privacy_url;
      }
      if (privacyDeInput && typeof settings.privacy_url_de === 'string') {
        privacyDeInput.value = settings.privacy_url_de;
      }
      if (privacyEnInput && typeof settings.privacy_url_en === 'string') {
        privacyEnInput.value = settings.privacy_url_en;
      }
      const updatedAt = result?.settings?.updated_at || result?.settings?.updatedAt;
      if (updatedLabel) {
        updatedLabel.textContent = updatedAt ? `Zuletzt gespeichert: ${updatedAt}` : 'Einstellungen gespeichert';
      }
      setStatus('Einstellungen gespeichert.', false);
    } catch (error) {
      setStatus('Einstellungen konnten nicht gespeichert werden.', true);
    }
  });
};

const initPageNamespaceManager = () => {
  const manager = document.querySelector('[data-page-namespace-manager]');
  if (!manager) {
    return;
  }
  const namespaceSelect = manager.querySelector('[data-page-namespace-select]');
  if (!namespaceSelect) {
    return;
  }

  const feedback = manager.querySelector('[data-page-namespace-feedback]');
  const pages = parseDatasetJson(manager.dataset.pages, []);
  const pageSelectId = manager.dataset.pageSelectId || '';
  const pageSelectKey = manager.dataset.pageSelectKey || 'slug';
  const pageSelectionParam = manager.dataset.pageSelectionParam || '';
  const pageSelectionValueKey = manager.dataset.pageSelectionValueKey || 'slug';
  const successMessage = manager.dataset.successMessage || 'Seite verschoben.';
  const errorMessageDefault = manager.dataset.errorMessage || 'Namespace konnte nicht geändert werden.';
  const pageSelect = pageSelectId ? document.getElementById(pageSelectId) : null;
  const activeNamespaceSelect = document.getElementById('pageNamespaceSelect');

  const resolveActiveNamespace = () =>
    (activeNamespaceSelect?.value || activeNamespaceSelect?.dataset.pageNamespace || manager.dataset.activeNamespace || '').trim();

  const findPage = () => {
    if (!pageSelect) {
      return null;
    }
    const rawValue = pageSelect.value;
    if (!rawValue) {
      return null;
    }
    if (pageSelectKey === 'id') {
      const numeric = Number(rawValue);
      return pages.find(page => Number(page?.id) === numeric) || null;
    }
    return pages.find(page => String(page?.slug) === String(rawValue)) || null;
  };

  const setFeedback = (message, status = 'success') => {
    if (!feedback) {
      return;
    }
    feedback.classList.remove('uk-alert-danger', 'uk-alert-primary', 'uk-alert-success');
    if (!message) {
      feedback.hidden = true;
      feedback.textContent = '';
      return;
    }
    feedback.textContent = message;
    feedback.hidden = false;
    feedback.classList.add(status === 'success' ? 'uk-alert-success' : 'uk-alert-danger');
  };

  const updateNamespaceSelect = () => {
    const page = findPage();
    if (!page) {
      namespaceSelect.disabled = true;
      return;
    }
    namespaceSelect.disabled = false;
    const namespaceValue = (page.namespace || resolveActiveNamespace() || '').trim();
    if (namespaceValue) {
      namespaceSelect.value = namespaceValue;
    }
    namespaceSelect.dataset.currentNamespace = namespaceValue;
  };

  updateNamespaceSelect();

  if (pageSelect) {
    pageSelect.addEventListener('change', () => {
      setFeedback('');
      updateNamespaceSelect();
    });
  }

  namespaceSelect.addEventListener('change', async () => {
    const page = findPage();
    if (!page) {
      return;
    }
    const targetNamespace = namespaceSelect.value.trim();
    if (!targetNamespace) {
      return;
    }
    const currentNamespace = (page.namespace || resolveActiveNamespace() || '').trim();
    if (currentNamespace && targetNamespace === currentNamespace) {
      setFeedback('');
      return;
    }

    namespaceSelect.disabled = true;
    setFeedback('');

    const endpoint = `${withBase('/admin/pages/')}${encodeURIComponent(page.slug)}/namespace`;
    try {
      const response = await window.apiFetch(`${endpoint}?namespace=${encodeURIComponent(resolveActiveNamespace())}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        body: JSON.stringify({ namespace: targetNamespace })
      });

      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        const message = payload.error || errorMessageDefault;
        throw new Error(message);
      }

      page.namespace = targetNamespace;
      namespaceSelect.dataset.currentNamespace = targetNamespace;
      setFeedback(successMessage, 'success');

      const activeNamespace = resolveActiveNamespace();
      if (activeNamespace && targetNamespace !== activeNamespace) {
        const url = new URL(window.location.href);
        url.searchParams.set('namespace', targetNamespace);
        if (pageSelectionParam) {
          const value = page[pageSelectionValueKey] ?? page.slug;
          if (value !== undefined && value !== null && value !== '') {
            url.searchParams.set(pageSelectionParam, String(value));
          }
        }
        window.location.assign(url.toString());
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : errorMessageDefault;
      setFeedback(message, 'danger');
    } finally {
      namespaceSelect.disabled = false;
    }
  });
};

document.addEventListener('DOMContentLoaded', function () {
  const adminTabs = document.getElementById('adminTabs');
  const adminMenu = document.getElementById('adminMenu');
  const adminNav = document.getElementById('adminNav');
  const adminMenuToggle = document.getElementById('adminMenuToggle');
  const pageNamespaceSelect = document.getElementById('pageNamespaceSelect');
  const namespaceSelect = document.getElementById('namespaceSelect');
  const pageTabs = document.getElementById('pageTabs');

  if (window.domainType !== 'main') {
    adminTabs?.querySelector('[data-route="tenants"]')?.remove();
    adminMenu?.querySelector('a[href$="/admin/tenants"]')?.parentElement?.remove();
  }

  const resolveActivePageTab = () => pageTabs?.querySelector('li.uk-active')?.dataset.pageTab || '';
  if (pageNamespaceSelect) {
    const currentNamespace = pageNamespaceSelect.dataset.pageNamespace || pageNamespaceSelect.value || '';
    if (currentNamespace && pageNamespaceSelect.value !== currentNamespace) {
      pageNamespaceSelect.value = currentNamespace;
    }
    pageNamespaceSelect.addEventListener('change', () => {
      const selectedNamespace = pageNamespaceSelect.value || '';
      if (!selectedNamespace) {
        return;
      }
      const url = new URL(window.location.href);
      url.searchParams.set('namespace', selectedNamespace);
      const activeTab = resolveActivePageTab();
      if (activeTab) {
        url.searchParams.set('pageTab', activeTab);
      }
      window.location.assign(url.toString());
    });
  }
  if (namespaceSelect) {
    const currentNamespace = namespaceSelect.dataset.namespace || namespaceSelect.value || '';
    if (currentNamespace && namespaceSelect.value !== currentNamespace) {
      namespaceSelect.value = currentNamespace;
    }
    namespaceSelect.addEventListener('change', () => {
      const selectedNamespace = namespaceSelect.value || '';
      if (!selectedNamespace) {
        return;
      }
      const url = new URL(window.location.href);
      url.searchParams.set('namespace', selectedNamespace);
      const activeTab = resolveActivePageTab();
      if (activeTab) {
        url.searchParams.set('pageTab', activeTab);
      }
      window.location.assign(url.toString());
    });
  }

  initProjectSettings();
  initPageNamespaceManager();

  const adminRoutes = Array.from(adminTabs ? adminTabs.querySelectorAll('li') : [])
    .map(tab => tab.getAttribute('data-route') || '');
  const settingsInitial = window.quizSettings || {};
  const ragChatSecretPlaceholder = window.ragChatSecretPlaceholder || '__SECRET_PRESENT__';
  const ragChatTokenPlaceholder = window.ragChatTokenPlaceholder || '••••••••';
  const transRagChatSaved = window.transRagChatSaved || 'Einstellung gespeichert';
  const transCatalogsFetchError = window.transCatalogsFetchError || 'Kataloge konnten nicht geladen werden';
  const transCatalogsForbidden = window.transCatalogsForbidden || 'Keine Berechtigung zum Laden der Kataloge';
  const transRagChatSaveError = window.transRagChatSaveError || 'Fehler beim Speichern';
  const transRagChatTokenSaved = window.transRagChatTokenSaved || '';
  const transRagChatTokenMissing = window.transRagChatTokenMissing || '';
  const transCountdownInvalid = window.transCountdownInvalid || 'Zeitlimit muss 0 oder größer sein.';
  const pagesInitial = window.pagesContent || {};
  const profileForm = document.getElementById('profileForm');
  const profileSaveBtn = document.getElementById('profileSaveBtn');
  const welcomeMailBtn = document.getElementById('welcomeMailBtn');
  const checkoutContainer = document.getElementById('stripe-checkout');
  const planButtons = document.querySelectorAll('.plan-select');
  const emailInput = document.getElementById('subscription-email');
  const planSelect = document.getElementById('planSelect');
  const managementSection = document.querySelector('[data-admin-section="management"]');
  const domainStartPageTable = managementSection?.querySelector('#domainStartPageTable') || null;
  const initialDomainStartPageOptions = window.domainStartPageOptions || {};
  const domainStartPageOptions = { ...initialDomainStartPageOptions };
  window.domainStartPageOptions = domainStartPageOptions;
  const coreDomainStartPageOrder = ['help', 'events'];
  const marketingNewsletterSection = document.getElementById('marketingNewsletterConfigSection');
  const marketingNewsletterSlugInput = document.getElementById('marketingNewsletterSlug');
  const marketingNewsletterSlugOptions = document.getElementById('marketingNewsletterSlugOptions');
  const marketingNewsletterTable = document.getElementById('marketingNewsletterConfigTable');
  const marketingNewsletterTableBody = marketingNewsletterTable ? marketingNewsletterTable.querySelector('tbody') : null;
  const marketingNewsletterAddBtn = document.getElementById('marketingNewsletterAddRow');
  const marketingNewsletterSaveBtn = document.getElementById('marketingNewsletterSave');
  const marketingNewsletterResetBtn = document.getElementById('marketingNewsletterReset');
  const marketingNewsletterRaw = window.marketingNewsletterConfigs || {};
  const marketingNewsletterData = {};
  Object.entries(marketingNewsletterRaw).forEach(([slug, items]) => {
    const normalizedSlug = typeof slug === 'string' ? slug.trim().toLowerCase() : '';
    if (normalizedSlug === '') {
      return;
    }
    marketingNewsletterData[normalizedSlug] = Array.isArray(items)
      ? items.map(item => ({
          label: typeof item.label === 'string' ? item.label : '',
          url: typeof item.url === 'string' ? item.url : '',
          style: typeof item.style === 'string' && item.style !== '' ? item.style : 'primary'
        }))
      : [];
  });
  const marketingNewsletterSlugs = Array.from(new Set(
    (window.marketingNewsletterSlugs || [])
      .map(slug => (typeof slug === 'string' ? slug.trim().toLowerCase() : ''))
      .filter(slug => slug !== '')
  ));
  Object.keys(marketingNewsletterData).forEach(slug => {
    if (slug !== '' && !marketingNewsletterSlugs.includes(slug)) {
      marketingNewsletterSlugs.push(slug);
    }
  });
  const marketingNewsletterStyles = Array.isArray(window.marketingNewsletterStyles) && window.marketingNewsletterStyles.length
    ? window.marketingNewsletterStyles.slice()
    : ['primary', 'secondary', 'link'];
  const marketingNewsletterStyleLabels = window.marketingNewsletterStyleLabels || {};
  const transMarketingNewsletterSaved = window.transMarketingNewsletterSaved || 'Konfiguration gespeichert.';
  const transMarketingNewsletterError = window.transMarketingNewsletterError || 'Speichern fehlgeschlagen.';
  const transMarketingNewsletterInvalidSlug = window.transMarketingNewsletterInvalidSlug || 'Slug erforderlich';
  const transMarketingNewsletterRemove = window.transMarketingNewsletterRemove || 'Entfernen';
  const transMarketingNewsletterEmpty = window.transMarketingNewsletterEmpty || 'Keine Einträge vorhanden.';
  const resolveMarketingNewsletterNamespace = () => {
    const candidates = [
      marketingNewsletterSection?.dataset.marketingNamespace,
      window.marketingNewsletterNamespace,
      window.pageNamespace,
      window.defaultNamespace
    ];
    for (const candidate of candidates) {
      if (typeof candidate === 'string' && candidate.trim() !== '') {
        return candidate.trim();
      }
    }
    return '';
  };
  const buildMarketingNewsletterPath = () => {
    const namespace = resolveMarketingNewsletterNamespace();
    const params = new URLSearchParams();
    if (namespace) {
      params.set('namespace', namespace);
    }
    const query = params.toString();
    return `/admin/marketing-newsletter-configs${query ? `?${query}` : ''}`;
  };
  const labelFromSlug = slug => {
    if (typeof slug !== 'string' || slug === '') {
      return '';
    }
    const parts = slug.split('-').filter(Boolean);
    if (!parts.length) {
      return slug.charAt(0).toUpperCase() + slug.slice(1);
    }
    return parts
      .map(part => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ');
  };
  const splitStartPageKey = value => {
    if (typeof value !== 'string') {
      return null;
    }
    const separatorIndex = value.indexOf(':');
    if (separatorIndex <= 0 || separatorIndex === value.length - 1) {
      return null;
    }
    const namespace = value.slice(0, separatorIndex).trim();
    const slug = value.slice(separatorIndex + 1).trim();
    if (!namespace || !slug) {
      return null;
    }
    return { namespace, slug };
  };
  const formatStartPageLabel = key => {
    if (typeof key !== 'string' || key === '') {
      return '';
    }
    const parsed = splitStartPageKey(key);
    if (!parsed) {
      return labelFromSlug(key);
    }
    return `${parsed.namespace} · ${labelFromSlug(parsed.slug)}`;
  };
  const ensureDomainStartPageOption = (slug, label) => {
    if (typeof slug !== 'string' || slug === '') {
      return;
    }
    const normalizedLabel = typeof label === 'string' && label.trim() !== '' ? label : formatStartPageLabel(slug);
    domainStartPageOptions[slug] = normalizedLabel;
  };
  const mergeDomainStartPageOptions = options => {
    if (!options || typeof options !== 'object') {
      return;
    }
    Object.entries(options).forEach(([slug, label]) => {
      ensureDomainStartPageOption(slug, label);
    });
  };
  const getDomainStartPageOptionEntries = () => {
    const coreEntries = [];
    coreDomainStartPageOrder.forEach(slug => {
      if (Object.prototype.hasOwnProperty.call(domainStartPageOptions, slug)) {
        coreEntries.push([slug, domainStartPageOptions[slug]]);
      }
    });
    const rest = Object.entries(domainStartPageOptions).filter(
      ([slug]) => !coreDomainStartPageOrder.includes(slug)
    );
    rest.sort((a, b) => {
      return a[1].localeCompare(b[1], undefined, { sensitivity: 'base' });
    });
    return coreEntries.concat(rest);
  };
  mergeDomainStartPageOptions(initialDomainStartPageOptions);
  const domainStartPageTypeLabels = window.domainStartPageTypeLabels || {};
  const transDomainStartPageSaved = window.transDomainStartPageSaved || 'Startseite gespeichert';
  const transDomainStartPageError = window.transDomainStartPageError || 'Fehler beim Speichern';
  const transDomainStartPageInvalidEmail = window.transDomainStartPageInvalidEmail || transDomainStartPageError;
  const transDomainStartPageDeleted = window.transDomainStartPageDeleted || 'Eintrag gelöscht';
  const transDomainStartPageDeleteError = window.transDomainStartPageDeleteError || transDomainStartPageError;
  const transDomainStartPageDeleteConfirm = window.transDomainStartPageDeleteConfirm || 'Eintrag entfernen?';
  const transDomainSmtpTitle = window.transDomainSmtpTitle || 'SMTP-Einstellungen';
  const transDomainSmtpSaved = window.transDomainSmtpSaved || transDomainStartPageSaved;
  const transDomainSmtpError = window.transDomainSmtpError || transDomainStartPageError;
  const transDomainSmtpInvalid = window.transDomainSmtpInvalid || transDomainSmtpError;
  const transDomainSmtpDefault = window.transDomainSmtpDefault || 'Standard';
  const transDomainSmtpSummaryDsn = window.transDomainSmtpSummaryDsn || 'DSN';
  const transDomainSmtpPasswordSet = window.transDomainSmtpPasswordSet || 'Passwort gesetzt';
  const secretPlaceholder = window.domainStartPageSecretPlaceholder || '__SECRET_KEEP__';
  const transDomainContactTemplateEdit = window.transDomainContactTemplateEdit || 'Template bearbeiten';
  const transDomainContactTemplateSaved = window.transDomainContactTemplateSaved || 'Template gespeichert';
  const transDomainContactTemplateError = window.transDomainContactTemplateError || 'Fehler beim Speichern';
  const transDomainContactTemplateLoadError = window.transDomainContactTemplateLoadError || transDomainContactTemplateError;
  const transDomainContactTemplateInvalidDomain = window.transDomainContactTemplateInvalidDomain || transDomainContactTemplateError;
  const contactTemplateModalEl = document.getElementById('domainContactTemplateModal');
  const contactTemplateModal = contactTemplateModalEl && window.UIkit ? UIkit.modal(contactTemplateModalEl) : null;
  const contactTemplateForm = document.getElementById('domainContactTemplateForm');
  const contactTemplateInfo = document.getElementById('domainContactTemplateInfo');
  const contactTemplateFields = contactTemplateForm
    ? {
        domain: document.getElementById('domainContactTemplateDomain'),
        senderName: document.getElementById('domainContactTemplateSenderName'),
        recipientHtml: document.getElementById('domainContactTemplateRecipientHtml'),
        recipientText: document.getElementById('domainContactTemplateRecipientText'),
        senderHtml: document.getElementById('domainContactTemplateSenderHtml'),
        senderText: document.getElementById('domainContactTemplateSenderText'),
      }
    : null;
  const contactTemplateSubmit = contactTemplateForm?.querySelector('button[type="submit"]') || null;
  const domainSmtpModalEl = document.getElementById('domainSmtpModal');
  const domainSmtpModal = domainSmtpModalEl && window.UIkit ? UIkit.modal(domainSmtpModalEl) : null;
  const domainSmtpForm = document.getElementById('domainSmtpForm');
  const domainSmtpInfo = document.getElementById('domainSmtpInfo');
  const domainSmtpFields = domainSmtpForm
    ? {
        domain: document.getElementById('domainSmtpDomain'),
        host: document.getElementById('domainSmtpHost'),
        user: document.getElementById('domainSmtpUser'),
        port: document.getElementById('domainSmtpPort'),
        encryption: document.getElementById('domainSmtpEncryption'),
        pass: document.getElementById('domainSmtpPass'),
        clear: document.getElementById('domainSmtpClearPass'),
        dsn: document.getElementById('domainSmtpDsn'),
      }
    : null;
  const domainSmtpSubmit = domainSmtpForm?.querySelector('button[type="submit"]') || null;
  let mainDomainNormalized = '';
  let domainStartPageData = [];
  let domainStartPageUpdater = null;
  let reloadDomainStartPages = null;
  let currentSmtpItem = null;
  const trumbowygAvailable = () => !!(window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.trumbowyg === 'function');
  const ensureTemplateEditor = element => {
    if (!element || element.dataset.templateEditor !== 'html' || !trumbowygAvailable()) {
      return null;
    }
    if (!element.dataset.editorReady) {
      window.jQuery(element).trumbowyg({
        autogrow: true,
        semantic: false,
        removeformatPasted: true,
      });
      element.dataset.editorReady = '1';
    }
    return window.jQuery(element);
  };
  const sanitizeTemplateHtml = value => {
    if (typeof value !== 'string' || value === '') {
      return '';
    }
    if (window.DOMPurify && typeof window.DOMPurify.sanitize === 'function') {
      return window.DOMPurify.sanitize(value, {
        FORBID_TAGS: ['style', 'link', 'meta', 'html', 'head', 'body', 'base']
      });
    }
    return value
      .replace(/<style[\s\S]*?<\/style>/gi, '')
      .replace(/<\/?(?:html|head|body)[^>]*>/gi, '')
      .replace(/<link[^>]*>/gi, '')
      .replace(/<meta[^>]*>/gi, '');
  };
  const setTemplateFieldValue = (element, value) => {
    if (!element) return;
    if (element.dataset.templateEditor === 'html') {
      const editor = ensureTemplateEditor(element);
      if (editor) {
        const sanitized = sanitizeTemplateHtml(value);
        editor.trumbowyg('html', sanitized);
        return;
      }
    }
    element.value = typeof value === 'string' ? value : '';
  };
  const getTemplateFieldValue = element => {
    if (!element) return '';
    if (element.dataset.templateEditor === 'html') {
      const editor = ensureTemplateEditor(element);
      if (editor) {
        return editor.trumbowyg('html');
      }
    }
    return element.value || '';
  };
  const resetContactTemplateForm = () => {
    if (!contactTemplateFields) return;
    if (contactTemplateFields.senderName) contactTemplateFields.senderName.value = '';
    if (contactTemplateFields.recipientText) contactTemplateFields.recipientText.value = '';
    if (contactTemplateFields.senderText) contactTemplateFields.senderText.value = '';
    setTemplateFieldValue(contactTemplateFields.recipientHtml, '');
    setTemplateFieldValue(contactTemplateFields.senderHtml, '');
  };
  const toggleContactTemplateDisabled = disabled => {
    if (!contactTemplateForm) return;
    const elements = contactTemplateForm.querySelectorAll('input, textarea, button[type="submit"]');
    elements.forEach(el => {
      el.disabled = disabled;
    });
  };
  const loadContactTemplateData = normalized => {
    if (!normalized) {
      return Promise.reject(new Error(transDomainContactTemplateInvalidDomain));
    }
    toggleContactTemplateDisabled(true);
    return apiFetch(`/admin/domain-contact-template/${encodeURIComponent(normalized)}`)
      .then(res => {
        if (!res.ok) {
          return res
            .json()
            .catch(() => ({}))
            .then(data => {
              throw new Error(data.error || transDomainContactTemplateLoadError);
            });
        }
        return res.json();
      })
      .then(data => {
        if (!contactTemplateFields) {
          return;
        }
        setTemplateFieldValue(contactTemplateFields.recipientHtml, data?.recipient_html || '');
        setTemplateFieldValue(contactTemplateFields.senderHtml, data?.sender_html || '');
        if (contactTemplateFields.senderName) {
          contactTemplateFields.senderName.value = data?.sender_name || '';
        }
        if (contactTemplateFields.recipientText) {
          contactTemplateFields.recipientText.value = data?.recipient_text || '';
        }
        if (contactTemplateFields.senderText) {
          contactTemplateFields.senderText.value = data?.sender_text || '';
        }
      })
      .finally(() => {
        toggleContactTemplateDisabled(false);
      });
  };
  const openContactTemplateEditor = item => {
    if (!contactTemplateModal || !contactTemplateFields) {
      return;
    }
    const normalized = (item?.normalized || '').trim();
    if (!normalized) {
      notify(transDomainContactTemplateInvalidDomain, 'danger');
      return;
    }
    if (contactTemplateFields.domain) {
      contactTemplateFields.domain.value = normalized;
    }
    if (contactTemplateInfo) {
      contactTemplateInfo.textContent = item?.domain || normalized;
    }
    ensureTemplateEditor(contactTemplateFields?.recipientHtml);
    ensureTemplateEditor(contactTemplateFields?.senderHtml);
    resetContactTemplateForm();
    contactTemplateModal.show();
    loadContactTemplateData(normalized).catch(err => {
      notify(err.message || transDomainContactTemplateLoadError, 'danger');
      contactTemplateModal.hide();
    });
  };

  const normalizeSmtpPortValue = value => {
    if (value === null || value === undefined) {
      return '';
    }
    if (typeof value === 'number') {
      return Number.isFinite(value) ? value : '';
    }
    if (typeof value === 'string') {
      const trimmed = value.trim();
      if (trimmed === '') {
        return '';
      }
      const parsed = Number.parseInt(trimmed, 10);
      return Number.isNaN(parsed) ? trimmed : parsed;
    }

    return '';
  };

  const applyDomainConfig = (item, config) => {
    if (!item || !config) {
      return;
    }

    if (typeof config.start_page === 'string' && config.start_page !== '') {
      item.start_page = config.start_page;
    }
    item.email = typeof config.email === 'string' ? config.email : '';
    item.smtp_host = typeof config.smtp_host === 'string' ? config.smtp_host : '';
    item.smtp_user = typeof config.smtp_user === 'string' ? config.smtp_user : '';
    const portValue = normalizeSmtpPortValue(config.smtp_port ?? '');
    item.smtp_port = portValue === '' ? '' : portValue;
    item.smtp_encryption = typeof config.smtp_encryption === 'string' ? config.smtp_encryption : '';
    item.smtp_dsn = typeof config.smtp_dsn === 'string' ? config.smtp_dsn : '';
    item.has_smtp_pass = Boolean(config.has_smtp_pass);
  };

  const buildDomainPayload = (item, overrides = {}) => {
    if (!item) {
      return {};
    }

    const resolve = (key, fallback) => (
      Object.prototype.hasOwnProperty.call(overrides, key) ? overrides[key] : fallback
    );

    const portValue = resolve('smtp_port', item.smtp_port);

    return {
      domain: item.normalized,
      start_page: resolve('start_page', item.start_page),
      email: resolve('email', item.email || ''),
      smtp_host: resolve('smtp_host', item.smtp_host || ''),
      smtp_user: resolve('smtp_user', item.smtp_user || ''),
      smtp_port: portValue === '' || portValue === null ? '' : portValue,
      smtp_encryption: resolve('smtp_encryption', item.smtp_encryption || ''),
      smtp_dsn: resolve('smtp_dsn', item.smtp_dsn || ''),
      smtp_pass: resolve('smtp_pass', secretPlaceholder),
    };
  };

  const describeSmtpConfig = item => {
    if (!item) {
      return transDomainSmtpDefault;
    }
    if (typeof item.smtp_dsn === 'string' && item.smtp_dsn !== '') {
      try {
        const parsed = new URL(item.smtp_dsn);
        return `${transDomainSmtpSummaryDsn} (${parsed.protocol.replace(':', '')})`;
      } catch (e) {
        return transDomainSmtpSummaryDsn;
      }
    }
    if (typeof item.smtp_host === 'string' && item.smtp_host !== '') {
      let summary = item.smtp_host;
      if (item.smtp_port !== '' && item.smtp_port !== null && item.smtp_port !== undefined) {
        summary += `:${item.smtp_port}`;
      }
      if (typeof item.smtp_encryption === 'string' && item.smtp_encryption !== '' && item.smtp_encryption !== 'none') {
        summary += ` (${item.smtp_encryption.toUpperCase()})`;
      }
      return summary;
    }
    if (item.has_smtp_pass) {
      return transDomainSmtpPasswordSet;
    }

    return transDomainSmtpDefault;
  };

  const openSmtpEditor = item => {
    if (!domainSmtpForm || !domainSmtpFields) {
      return;
    }
    currentSmtpItem = item || null;
    if (domainSmtpFields.domain) {
      domainSmtpFields.domain.value = item?.normalized || '';
    }
    if (domainSmtpFields.host) {
      domainSmtpFields.host.value = item?.smtp_host || '';
    }
    if (domainSmtpFields.user) {
      domainSmtpFields.user.value = item?.smtp_user || '';
    }
    if (domainSmtpFields.port) {
      const port = item?.smtp_port ?? '';
      domainSmtpFields.port.value = port === '' || port === null ? '' : port;
    }
    if (domainSmtpFields.encryption) {
      domainSmtpFields.encryption.value = item?.smtp_encryption || '';
    }
    if (domainSmtpFields.pass) {
      domainSmtpFields.pass.value = '';
    }
    if (domainSmtpFields.clear) {
      domainSmtpFields.clear.checked = false;
    }
    if (domainSmtpFields.dsn) {
      domainSmtpFields.dsn.value = item?.smtp_dsn || '';
    }
    if (domainSmtpInfo) {
      domainSmtpInfo.textContent = item?.domain || '';
    }
    if (domainSmtpModal) {
      domainSmtpModal.show();
    }
  };

  if (contactTemplateForm && contactTemplateFields) {
    contactTemplateForm.addEventListener('submit', e => {
      e.preventDefault();
      const domainValue = (contactTemplateFields.domain?.value || '').trim();
      if (!domainValue) {
        notify(transDomainContactTemplateInvalidDomain, 'danger');
        return;
      }

      const payload = {
        domain: domainValue,
        sender_name: contactTemplateFields.senderName
          ? contactTemplateFields.senderName.value.trim()
          : '',
        recipient_html: getTemplateFieldValue(contactTemplateFields.recipientHtml),
        recipient_text: contactTemplateFields.recipientText?.value || '',
        sender_html: getTemplateFieldValue(contactTemplateFields.senderHtml),
        sender_text: contactTemplateFields.senderText?.value || '',
      };

      toggleContactTemplateDisabled(true);
      if (contactTemplateSubmit) {
        contactTemplateSubmit.disabled = true;
      }

      apiFetch('/admin/domain-contact-template', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
        .then(res => {
          if (!res.ok) {
            return res
              .json()
              .catch(() => ({}))
              .then(data => {
                const fallback = res.status === 422
                  ? transDomainContactTemplateInvalidDomain
                  : transDomainContactTemplateError;
                throw new Error(data.error || fallback);
              });
          }
          return res.json().catch(() => ({}));
        })
        .then(() => {
          notify(transDomainContactTemplateSaved, 'success');
          if (contactTemplateModal) {
            contactTemplateModal.hide();
          }
        })
        .catch(err => {
          notify(err.message || transDomainContactTemplateError, 'danger');
        })
        .finally(() => {
          toggleContactTemplateDisabled(false);
          if (contactTemplateSubmit) {
            contactTemplateSubmit.disabled = false;
          }
        });
    });
  }
  if (emailInput) {
    emailInput.addEventListener('input', () => {
      emailInput.classList.remove('uk-form-danger');
    });
  }
  if (planButtons.length || planSelect) {
      fetch(withBase('/admin/subscription/status'))
        .then(r => (r.ok ? r.json() : null))
        .then(data => {
        const currentPlan = data?.plan || '';
        planButtons.forEach(btn => {
          const btnPlan = btn.dataset.plan;
          if (!btnPlan) return;
          if (btnPlan === currentPlan) {
            btn.disabled = true;
          } else if (currentPlan) {
            btn.textContent = window.transUpgradeAction || 'Upgrade';
          }
        });
        if (planSelect) {
          planSelect.value = currentPlan;
        }
        })
        .catch(() => {});
  }

  document.addEventListener('click', e => {
    const el = e.target.closest('[data-action]');
    if (!el) return;
    const action = el.getAttribute('data-action');
    const sub = el.getAttribute('data-sub');
    const uid = el.getAttribute('data-uid');
    if (action === 'delete') {
      e.preventDefault();
      if (!confirm('Mandant wirklich löschen?')) return;
      el.classList.add('uk-disabled');
      apiFetch('/tenants', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uid })
      })
        .then(r => {
          if (!r.ok) return r.text().then(text => { throw new Error(text); });
          return apiFetch('/api/tenants/' + encodeURIComponent(sub), { method: 'DELETE' });
        })
        .then(() => {
          notify('Mandant entfernt', 'success');
          refreshTenantList();
        })
        .catch(() => notify('Fehler beim Löschen', 'danger'))
        .finally(() => {
          el.classList.remove('uk-disabled');
        });
    } else if (action === 'build-docker') {
      e.preventDefault();
      const original = el.innerHTML;
      el.disabled = true;
      el.innerHTML = '<div uk-spinner></div>';
      apiFetch('/api/docker/build', { method: 'POST' })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
          if (!ok) throw new Error(data.error || 'Fehler');
          notify(window.transImageReady, 'success');
        })
        .catch(err => notify(err.message || 'Fehler beim Erstellen', 'danger'))
        .finally(() => {
          el.disabled = false;
          el.innerHTML = original;
        });
    } else if (action === 'upgrade-docker') {
      e.preventDefault();
      el.classList.add('uk-disabled');
      const originalHtml = el.innerHTML;
      const text = (el.textContent || '').trim();
      el.innerHTML = text ? `<span class="uk-margin-small-right" uk-spinner></span>${text}` : '<span uk-spinner></span>';
      apiFetch('/api/tenants/' + encodeURIComponent(sub) + '/upgrade', { method: 'POST' })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
          if (!ok) throw new Error(data.error || 'Fehler');
          notify(window.transUpgradeDocker || 'Docker aktualisiert', 'success');
        })
        .catch(err => notify(err.message || 'Fehler beim Aktualisieren', 'danger'))
        .finally(() => {
          el.innerHTML = originalHtml;
          el.classList.remove('uk-disabled');
        });
    } else if (action === 'restart') {
      e.preventDefault();
      apiFetch('/api/tenants/' + encodeURIComponent(sub) + '/restart', { method: 'POST' })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
          if (!ok) throw new Error(data.error || 'Fehler');
          notify(data.status || 'Neu gestartet', 'success');
        })
        .catch(err => notify(err.message || 'Fehler beim Neustart', 'danger'));
    } else if (action === 'renew') {
      e.preventDefault();
      apiFetch('/api/tenants/' + encodeURIComponent(sub) + '/renew-ssl', { method: 'POST' })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
          if (!ok) throw new Error(data.error || 'Fehler');
          notify(data.status || 'Zertifikat wird erneuert', 'success');
        })
        .catch(err => notify(err.message || 'Fehler beim Erneuern', 'danger'));
    } else if (action === 'welcome') {
      e.preventDefault();
      apiFetch('/tenants/' + encodeURIComponent(sub) + '/welcome', { method: 'POST' })
        .then(r => {
          if (!r.ok) throw new Error('Fehler');
          notify('Willkommensmail gesendet', 'success');
        })
        .catch(() => notify('Willkommensmail nicht verfügbar', 'danger'));
    }
  });
  planButtons.forEach(btn => {
    btn.addEventListener('click', async () => {
      const plan = btn.dataset.plan;
      if (!plan) return;
      const payload = { plan, embedded: true };
      if (emailInput) {
        const email = emailInput.value.trim();
        if (email === '') {
          emailInput.classList.add('uk-form-danger');
          emailInput.focus();
          notify('Bitte E-Mail-Adresse eingeben', 'warning');
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
          let msg = 'Fehler beim Starten der Zahlung';
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
        notify('Fehler beim Starten der Zahlung', 'danger', 0);
      }
    });
  });

  const params = new URLSearchParams(window.location.search);
  const sessionId = params.get('session_id');
  if (sessionId) {
    fetch(withBase('/admin/subscription/checkout/' + encodeURIComponent(sessionId)))
      .then(() => {
        window.history.replaceState({}, document.title, window.location.pathname);
        window.location.reload();
      });
  }

  function slugify(text) {
    return text
      .toString()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/ß/g, 'ss')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
  }

  function getUsedIds() {
    const list = typeof catalogManager !== 'undefined' && catalogManager
      ? catalogManager.getData()
      : catalogs;
    return new Set(list.map(c => c.slug || c.sort_order));
  }

  function uniqueId(text) {
    let base = slugify(text);
    if (!base) return '';
    const used = getUsedIds();
    let id = base;
    let i = 2;
    while (used.has(id)) {
      id = base + '_' + i;
      i++;
    }
    return id;
  }

  function insertSoftHyphens(text){
    return text ? text.replace(/\/-/g, '\u00AD') : '';
  }

  function updatePuzzleFeedbackUI() {
    if (!puzzleIcon || !puzzleLabel) return;
    if (puzzleFeedback.trim().length > 0) {
      puzzleIcon.setAttribute('uk-icon', 'icon: check');
      puzzleLabel.textContent = 'Feedbacktext bearbeiten';
    } else {
      puzzleIcon.setAttribute('uk-icon', 'icon: pencil');
      puzzleLabel.textContent = 'Feedbacktext';
    }
    if (window.UIkit && UIkit.icon) {
      UIkit.icon(puzzleIcon, { icon: puzzleIcon.getAttribute('uk-icon').split(': ')[1] });
    }
  }

    function updateInviteTextUI() {
      if (!inviteLabel) return;
      if (inviteText.trim().length > 0) {
        inviteLabel.textContent = 'Einladungstext bearbeiten';
      } else {
        inviteLabel.textContent = 'Einladungstext eingeben';
      }
    }
  // --------- Konfiguration bearbeiten ---------
  // Ausgangswerte aus der bestehenden Konfiguration
  const cfgInitial = window.quizConfig || {};
  const cloneConfigValue = (value, seen = new Map()) => {
    if (typeof value !== 'object' || value === null) {
      return value;
    }
    if (seen.has(value)) {
      return seen.get(value);
    }
    if (Array.isArray(value)) {
      const arr = [];
      seen.set(value, arr);
      value.forEach((item, idx) => {
        arr[idx] = cloneConfigValue(item, seen);
      });
      return arr;
    }
    const obj = {};
    seen.set(value, obj);
    Object.keys(value).forEach(key => {
      obj[key] = cloneConfigValue(value[key], seen);
    });
    return obj;
  };
  const cloneConfig = config => {
    if (!config || typeof config !== 'object') {
      return {};
    }
    if (typeof structuredClone === 'function') {
      try {
        return structuredClone(config);
      } catch (err) {
        /* empty */
      }
    }
    return cloneConfigValue(config);
  };
  function replaceInitialConfig(newConfig) {
    const source = cloneConfig(newConfig);
    Object.keys(cfgInitial).forEach(key => {
      delete cfgInitial[key];
    });
    Object.assign(cfgInitial, source);
    return cloneConfig(cfgInitial);
  }
  const normalizeId = (value) => {
    if (value === null || value === undefined) {
      return '';
    }
    return String(value);
  };

  const cfgParams = new URLSearchParams(window.location.search);
  let currentEventUid = normalizeId(cfgParams.get('event') || '');
  const eventIndicators = document.querySelectorAll('[data-current-event-indicator]');
  const indicatorNodes = Array.from(eventIndicators);
  const eventSelectNodes = indicatorNodes
    .map(el => el.querySelector('[data-current-event-select]'))
    .filter(el => el);
  let currentEventName = '';
  let currentEventSlug = window.initialEventSlug || cfgInitial.slug || '';
  let availableEvents = [];
  if (!currentEventUid) {
    const seededUid = indicatorNodes.map(el => el.dataset.currentEventUid || '').find(uid => uid);
    currentEventUid = normalizeId(seededUid || cfgInitial.event_uid || '');
  }
  if (!cfgInitial.event_uid && currentEventUid) {
    cfgInitial.event_uid = currentEventUid;
  }
  if (!currentEventName) {
    const seededName = indicatorNodes
      .map(el => {
        const attrName = (el.dataset.currentEventName || '').trim();
        if (attrName) return attrName;
        const select = el.querySelector('[data-current-event-select]');
        if (select && select.options && select.selectedIndex >= 0) {
          const option = select.options[select.selectedIndex];
          if (option && option.value) {
            return (option.textContent || '').trim();
          }
        }
        return '';
      })
      .find(name => name);
    currentEventName = seededName || (cfgInitial.header || '');
  }

  eventSelectNodes.forEach(select => {
    select.addEventListener('change', () => {
      const uid = normalizeId(select.value || '');
      const option = select.options[select.selectedIndex] || null;
      const name = option ? (option.textContent || '') : '';
      if (uid === (currentEventUid || '')) {
        renderCurrentEventIndicator(currentEventName, currentEventUid, availableEvents.length > 0);
        return;
      }
      setCurrentEvent(uid, name);
    });
  });
  // Verweise auf die Formularfelder
  const cfgFields = {
    logoFile: document.getElementById('cfgLogoFile'),
    logoPreview: document.getElementById('cfgLogoPreview'),
    pageTitle: document.getElementById('cfgPageTitle'),
    backgroundColor: document.getElementById('cfgBackgroundColor'),
    buttonColor: document.getElementById('cfgButtonColor'),
    startTheme: document.getElementById('cfgStartTheme'),
    checkAnswerButton: document.getElementById('cfgCheckAnswerButton'),
    qrUser: document.getElementById('cfgQRUser'),
    randomNames: document.getElementById('cfgRandomNames'),
    randomNameStrategy: document.getElementById('cfgRandomNameStrategy'),
    randomNameLocale: document.getElementById('cfgRandomNameLocale'),
    randomNameDomains: Array.from(document.querySelectorAll('input[name="random_name_domains[]"]')),
    randomNameTones: Array.from(document.querySelectorAll('input[name="random_name_tones[]"]')),
    randomNameBuffer: document.getElementById('cfgRandomNameBuffer'),
    randomNamePreviewButton: document.getElementById('cfgRandomNamePreviewButton'),
    shuffleQuestions: document.getElementById('cfgShuffleQuestions'),
    teamRestrict: document.getElementById('cfgTeamRestrict'),
    competitionMode: document.getElementById('cfgCompetitionMode'),
    teamResults: document.getElementById('cfgTeamResults'),
    photoUpload: document.getElementById('cfgPhotoUpload'),
    countdownEnabled: document.getElementById('cfgCountdownEnabled'),
    countdown: document.getElementById('cfgCountdown'),
    puzzleEnabled: document.getElementById('cfgPuzzleEnabled'),
    puzzleWord: document.getElementById('cfgPuzzleWord'),
    puzzleWrap: document.getElementById('cfgPuzzleWordWrap'),
    registrationEnabled: document.getElementById('cfgRegistrationEnabled'),
    dashboardRefreshInterval: document.getElementById('cfgDashboardRefreshInterval'),
    dashboardFixedHeight: document.getElementById('dashboardFixedHeight'),
    dashboardTheme: document.getElementById('cfgDashboardTheme'),
    dashboardInfoText: document.getElementById('cfgDashboardInfoText'),
    dashboardMediaEmbed: document.getElementById('cfgDashboardMediaEmbed'),
    dashboardShareEnabled: document.getElementById('cfgDashboardShareEnabled'),
    dashboardSponsorEnabled: document.getElementById('cfgDashboardSponsorEnabled'),
    dashboardVisibilityStart: document.getElementById('cfgDashboardVisibilityStart'),
    dashboardVisibilityEnd: document.getElementById('cfgDashboardVisibilityEnd')
  };
  const randomNameOptionsFieldset = document.querySelector('[data-random-name-options]');
  const randomNameHintsContainer = document.querySelector('[data-random-name-hints]');
  const randomNameHintText = randomNameHintsContainer
    ? randomNameHintsContainer.querySelector('[data-random-name-hint-text]')
    : null;
  const randomNameHintMessages = randomNameHintsContainer
    ? {
      ai: randomNameHintsContainer.dataset.hintAi || '',
      lexicon: randomNameHintsContainer.dataset.hintLexicon || '',
      disabled: randomNameHintsContainer.dataset.hintDisabled || ''
    }
    : null;
  const randomNamePreviewContainer = document.querySelector('[data-random-name-preview]');
  const randomNamePreviewList = randomNamePreviewContainer
    ? randomNamePreviewContainer.querySelector('[data-random-name-preview-list]')
    : null;
  const randomNamePreviewStatus = randomNamePreviewContainer
    ? randomNamePreviewContainer.querySelector('[data-random-name-preview-status]')
    : null;
  const randomNamePreviewSpinner = randomNamePreviewContainer
    ? randomNamePreviewContainer.querySelector('[data-random-name-preview-spinner]')
    : null;
  const randomNamePreviewMessages = randomNamePreviewContainer
    ? {
      hint: randomNamePreviewContainer.dataset.previewHint || '',
      empty: randomNamePreviewContainer.dataset.previewEmpty || '',
      loading: randomNamePreviewContainer.dataset.previewLoading || '',
      error: randomNamePreviewContainer.dataset.previewError || '',
      none: randomNamePreviewContainer.dataset.previewNone || '',
      requiresEvent: randomNamePreviewContainer.dataset.previewRequiresEvent || ''
    }
    : null;
  const randomNameLogMessages = randomNamePreviewContainer
    ? {
      target: randomNamePreviewContainer.dataset.logTarget || '',
      attempt: randomNamePreviewContainer.dataset.logAttempt || '',
      received: randomNamePreviewContainer.dataset.logReceived || '',
      accepted: randomNamePreviewContainer.dataset.logAccepted || '',
      skippedDuplicateCache: randomNamePreviewContainer.dataset.logSkippedDuplicateCache || '',
      skippedDuplicateEvent: randomNamePreviewContainer.dataset.logSkippedDuplicateEvent || '',
      skippedInvalid: randomNamePreviewContainer.dataset.logSkippedInvalid || '',
      error: randomNamePreviewContainer.dataset.logError || '',
      persisted: randomNamePreviewContainer.dataset.logPersisted || '',
      statusCompleted: randomNamePreviewContainer.dataset.logStatusCompleted || '',
      statusPartial: randomNamePreviewContainer.dataset.logStatusPartial || '',
      statusFailed: randomNamePreviewContainer.dataset.logStatusFailed || '',
      statusUnchanged: randomNamePreviewContainer.dataset.logStatusUnchanged || '',
      statusSkipped: randomNamePreviewContainer.dataset.logStatusSkipped || '',
      statusDisabled: randomNamePreviewContainer.dataset.logStatusDisabled || '',
      statusMissingEvent: randomNamePreviewContainer.dataset.logStatusMissingEvent || '',
      namesPlaceholder: randomNamePreviewContainer.dataset.logNamesPlaceholder || ''
    }
    : null;
  const randomNameInventoryContainer = document.querySelector('[data-random-name-inventory]');
  const randomNameInventoryFields = randomNameInventoryContainer
    ? {
      ai: randomNameInventoryContainer.querySelector('[data-random-name-inventory-ai]'),
      lexicon: randomNameInventoryContainer.querySelector('[data-random-name-inventory-lexicon]')
    }
    : null;
  const randomNameInventoryMessages = randomNameInventoryContainer
    ? {
      loading: randomNameInventoryContainer.dataset.inventoryLoading || '',
      error: randomNameInventoryContainer.dataset.inventoryError || '',
      aiTotal: randomNameInventoryContainer.dataset.inventoryAiTotal || '',
      aiEmpty: randomNameInventoryContainer.dataset.inventoryAiEmpty || '',
      lexicon: randomNameInventoryContainer.dataset.inventoryLexicon || '',
      lexiconEmpty: randomNameInventoryContainer.dataset.inventoryLexiconEmpty || ''
    }
    : null;
  let randomNameInventoryRequestId = 0;
  const randomNameCacheContainer = document.querySelector('[data-random-name-cache]');
  const randomNameCacheList = randomNameCacheContainer
    ? randomNameCacheContainer.querySelector('[data-random-name-cache-list]')
    : null;
  const randomNameCacheStatus = randomNameCacheContainer
    ? randomNameCacheContainer.querySelector('[data-random-name-cache-status]')
    : null;
  const randomNameCacheMessages = randomNameCacheContainer
    ? {
      empty: randomNameCacheContainer.dataset.cacheEmpty || '',
      total: randomNameCacheContainer.dataset.cacheTotal || '',
      labelDomains: randomNameCacheContainer.dataset.cacheLabelDomains || '',
      labelTones: randomNameCacheContainer.dataset.cacheLabelTones || '',
      labelLocale: randomNameCacheContainer.dataset.cacheLabelLocale || '',
      none: randomNameCacheContainer.dataset.cacheNone || '',
      entryCount: randomNameCacheContainer.dataset.cacheEntryCount || '',
      entryEmpty: randomNameCacheContainer.dataset.cacheEntryEmpty || ''
    }
    : null;
  let randomNamePreviewContext = { eventUid: '', fingerprint: '' };

  const toggleRandomNamePreviewSpinner = (visible) => {
    if (randomNamePreviewSpinner) {
      randomNamePreviewSpinner.hidden = !visible;
    }
  };

  const clearRandomNamePreviewList = () => {
    if (randomNamePreviewList) {
      randomNamePreviewList.innerHTML = '';
      randomNamePreviewList.hidden = true;
    }
  };

  const formatTemplate = (template, values) => {
    if (typeof template !== 'string' || template === '') {
      return '';
    }

    const replacements = values && typeof values === 'object' ? values : {};
    return Object.keys(replacements).reduce((result, key) => {
      const value = replacements[key];
      const safeValue = value === undefined || value === null ? '' : String(value);
      return result.replace(new RegExp(`\\{${key}\\}`, 'g'), safeValue);
    }, template);
  };

  const formatRandomNameLogEntry = (entry) => {
    if (!randomNameLogMessages || !entry || typeof entry !== 'object') {
      return { message: '', level: 'info' };
    }

    const { code = '', level = 'info' } = entry;
    const context = typeof entry.context === 'object' && entry.context !== null
      ? entry.context
      : {};

    const count = Number.isFinite(Number(context.count)) ? Number(context.count) : 0;
    const attempt = Number.isFinite(Number(context.attempt)) ? Number(context.attempt) : null;
    const names = Array.isArray(context.names)
      ? context.names.map(value => String(value)).filter(value => value !== '')
      : [];
    const namesText = names.length
      ? names.join(', ')
      : (randomNameLogMessages.namesPlaceholder || '');
    const messageText = typeof context.message === 'string' ? context.message : '';

    const replacements = {
      count: String(count),
      attempt: attempt === null ? '' : String(attempt),
      names: namesText,
      message: messageText
    };

    let template = '';
    switch (code) {
      case 'target':
        template = randomNameLogMessages.target;
        break;
      case 'attempt':
        template = randomNameLogMessages.attempt;
        break;
      case 'received':
        template = randomNameLogMessages.received;
        break;
      case 'accepted':
        template = randomNameLogMessages.accepted;
        break;
      case 'skipped': {
        const reason = typeof context.reason === 'string' ? context.reason : 'invalid';
        if (reason === 'duplicate_cache') {
          template = randomNameLogMessages.skippedDuplicateCache;
        } else if (reason === 'duplicate_event') {
          template = randomNameLogMessages.skippedDuplicateEvent;
        } else {
          template = randomNameLogMessages.skippedInvalid;
        }
        break;
      }
      case 'persisted':
        template = randomNameLogMessages.persisted;
        break;
      case 'error':
        template = randomNameLogMessages.error;
        break;
      case 'status': {
        const status = typeof context.status === 'string' ? context.status : '';
        if (status === 'completed') {
          template = randomNameLogMessages.statusCompleted;
        } else if (status === 'partial') {
          template = randomNameLogMessages.statusPartial;
        } else if (status === 'failed') {
          template = randomNameLogMessages.statusFailed;
        } else if (status === 'skipped') {
          template = randomNameLogMessages.statusSkipped;
        } else if (status === 'disabled') {
          template = randomNameLogMessages.statusDisabled;
        } else if (status === 'missing-event') {
          template = randomNameLogMessages.statusMissingEvent;
        } else {
          template = randomNameLogMessages.statusUnchanged;
        }
        break;
      }
      default:
        template = '';
    }

    if (!template && messageText) {
      return { message: messageText, level }; // fallback to raw message
    }

    return {
      message: formatTemplate(template, replacements),
      level
    };
  };

  const renderRandomNameLog = (log) => {
    if (!randomNamePreviewList) {
      return;
    }

    const entries = Array.isArray(log?.entries) ? log.entries : [];
    clearRandomNamePreviewList();

    if (!entries.length) {
      return;
    }

    entries.forEach(entry => {
      const { message, level } = formatRandomNameLogEntry(entry);
      if (!message) {
        return;
      }

      const item = document.createElement('li');
      item.className = 'random-name-log-entry';
      item.textContent = message;
      if (typeof level === 'string' && level !== '') {
        item.dataset.logLevel = level;
      }
      randomNamePreviewList.appendChild(item);
    });

    randomNamePreviewList.hidden = false;
    if (randomNamePreviewStatus) {
      randomNamePreviewStatus.hidden = true;
    }
  };

  const clearRandomNameCacheList = () => {
    if (randomNameCacheList) {
      randomNameCacheList.innerHTML = '';
      randomNameCacheList.hidden = true;
    }
  };

  const formatRandomNameCacheEntryCount = (count) => {
    if (!randomNameCacheMessages) {
      return String(count);
    }
    const template = randomNameCacheMessages.entryCount || '';
    if (template.includes('{count}')) {
      return template.replace('{count}', String(count));
    }
    return template === '' ? String(count) : template;
  };

  const formatRandomNameCacheFilters = (filters) => {
    if (!randomNameCacheMessages) {
      return '';
    }
    const domains = Array.isArray(filters?.domains)
      ? filters.domains.map(value => String(value).trim()).filter(value => value !== '')
      : [];
    const tones = Array.isArray(filters?.tones)
      ? filters.tones.map(value => String(value).trim()).filter(value => value !== '')
      : [];
    const parts = [];

    if (domains.length) {
      const label = randomNameCacheMessages.labelDomains || '';
      parts.push(label ? `${label}: ${domains.join(', ')}` : domains.join(', '));
    }

    if (tones.length) {
      const label = randomNameCacheMessages.labelTones || '';
      parts.push(label ? `${label}: ${tones.join(', ')}` : tones.join(', '));
    }

    if (!parts.length) {
      return randomNameCacheMessages.none || '';
    }

    return parts.join(' • ');
  };

  const formatRandomNameCacheLocale = (locale) => {
    if (!randomNameCacheMessages) {
      return String(locale || '');
    }
    const label = randomNameCacheMessages.labelLocale || '';
    const value = typeof locale === 'string' && locale.trim() !== ''
      ? locale
      : (randomNameCacheMessages.none || '');
    return label ? `${label}: ${value}` : value;
  };

  const resetRandomNameCache = () => {
    clearRandomNameCacheList();
    if (randomNameCacheStatus && randomNameCacheMessages) {
      const message = randomNameCacheMessages.empty || '';
      randomNameCacheStatus.textContent = message;
      randomNameCacheStatus.hidden = message === '';
    }
  };

  const updateRandomNameCacheVisibility = (visible) => {
    if (!randomNameCacheContainer) {
      return;
    }
    randomNameCacheContainer.hidden = !visible;
    if (!visible) {
      resetRandomNameCache();
    }
  };

  const renderRandomNameCache = (cache) => {
    if (!randomNameCacheMessages || !randomNameCacheContainer) {
      return;
    }

    const entries = Array.isArray(cache?.entries) ? cache.entries : [];
    const totalCount = Number.isFinite(Number(cache?.total)) ? Number(cache.total) : 0;
    const hasEntries = entries.length > 0;

    updateRandomNameCacheVisibility(hasEntries);

    if (!hasEntries) {
      return;
    }

    if (randomNameCacheStatus) {
      if (totalCount > 0) {
        let message = randomNameCacheMessages.total || '';
        if (message.includes('{count}')) {
          message = message.replace('{count}', String(totalCount));
        }
        randomNameCacheStatus.textContent = message || '';
        randomNameCacheStatus.hidden = message === '';
      } else {
        const message = randomNameCacheMessages.empty || '';
        randomNameCacheStatus.textContent = message;
        randomNameCacheStatus.hidden = message === '';
      }
    }

    clearRandomNameCacheList();
    if (!randomNameCacheList) {
      return;
    }

    entries.forEach(entry => {
      const listItem = document.createElement('li');
      listItem.className = 'random-name-cache-entry';

      const header = document.createElement('div');
      header.className = 'uk-flex uk-flex-between uk-flex-middle';

      const filtersLabel = document.createElement('div');
      filtersLabel.className = 'uk-text-small';
      filtersLabel.textContent = formatRandomNameCacheFilters(entry?.filters || {});
      header.appendChild(filtersLabel);

      const countLabel = document.createElement('div');
      countLabel.className = 'uk-text-meta';
      const available = Number.isFinite(Number(entry?.available))
        ? Number(entry.available)
        : (Array.isArray(entry?.names) ? entry.names.length : 0);
      countLabel.textContent = formatRandomNameCacheEntryCount(available);
      header.appendChild(countLabel);

      listItem.appendChild(header);

      const localeText = formatRandomNameCacheLocale(entry?.filters?.locale || '');
      if (localeText) {
        const localeRow = document.createElement('div');
        localeRow.className = 'uk-text-meta uk-margin-small-top';
        localeRow.textContent = localeText;
        listItem.appendChild(localeRow);
      }

      const names = Array.isArray(entry?.names)
        ? entry.names.map(value => String(value))
        : [];

      if (names.length) {
        const namesList = document.createElement('ul');
        namesList.className = 'uk-list uk-list-collapse uk-margin-small-top';
        names.forEach(name => {
          const nameItem = document.createElement('li');
          nameItem.textContent = name;
          namesList.appendChild(nameItem);
        });
        listItem.appendChild(namesList);
      } else if (randomNameCacheMessages.entryEmpty) {
        const emptyMessage = document.createElement('div');
        emptyMessage.className = 'uk-text-meta uk-margin-small-top';
        emptyMessage.textContent = randomNameCacheMessages.entryEmpty;
        listItem.appendChild(emptyMessage);
      }

      randomNameCacheList.appendChild(listItem);
    });

    randomNameCacheList.hidden = false;
  };

  const setRandomNameInventoryMessage = (target, message) => {
    if (!target) {
      return;
    }

    const normalized = message || '';
    target.textContent = normalized;
    target.hidden = normalized === '';
  };

  const formatRandomNameInventoryMessage = (template, values) => {
    if (typeof template !== 'string' || template === '') {
      return '';
    }

    return template.replace(/\{(\w+)\}/g, (match, key) => {
      if (!Object.prototype.hasOwnProperty.call(values, key)) {
        return match;
      }

      const raw = values[key];
      if (raw === undefined || raw === null) {
        return '';
      }

      return String(raw);
    });
  };

  const showRandomNameInventory = (visible) => {
    if (!randomNameInventoryContainer) {
      return;
    }

    randomNameInventoryContainer.hidden = !visible;
  };

  const renderRandomNameInventoryLoading = () => {
    if (!randomNameInventoryMessages || !randomNameInventoryFields) {
      return;
    }

    const message = randomNameInventoryMessages.loading || '';
    setRandomNameInventoryMessage(randomNameInventoryFields.ai, message);
    setRandomNameInventoryMessage(randomNameInventoryFields.lexicon, message);
  };

  const renderRandomNameInventoryError = () => {
    if (!randomNameInventoryMessages || !randomNameInventoryFields) {
      return;
    }

    const message = randomNameInventoryMessages.error || '';
    setRandomNameInventoryMessage(randomNameInventoryFields.ai, message);
    setRandomNameInventoryMessage(randomNameInventoryFields.lexicon, message);
  };

  const renderRandomNameInventoryData = (payload) => {
    if (!randomNameInventoryMessages || !randomNameInventoryFields) {
      return;
    }

    renderRandomNameCache(payload?.ai?.cache || null);

    const cacheTotalRaw = payload?.ai?.cache?.total;
    const cacheTotal = Number.isFinite(Number(cacheTotalRaw)) ? Number(cacheTotalRaw) : 0;
    const aiMessage = cacheTotal > 0
      ? formatRandomNameInventoryMessage(randomNameInventoryMessages.aiTotal, { count: cacheTotal })
      : (randomNameInventoryMessages.aiEmpty || '');
    setRandomNameInventoryMessage(randomNameInventoryFields.ai, aiMessage);

    const lexicon = payload?.lexicon || {};
    const totalRaw = Number.isFinite(Number(lexicon.total)) ? Number(lexicon.total) : 0;
    const total = Math.max(0, Math.trunc(totalRaw));

    if (total <= 0) {
      setRandomNameInventoryMessage(
        randomNameInventoryFields.lexicon,
        randomNameInventoryMessages.lexiconEmpty || ''
      );
      return;
    }

    const availableRaw = Number.isFinite(Number(lexicon.available)) ? Number(lexicon.available) : null;
    const reservedRaw = Number.isFinite(Number(lexicon.reserved)) ? Number(lexicon.reserved) : null;
    const available = availableRaw !== null ? Math.max(0, Math.trunc(availableRaw)) : null;
    const reserved = reservedRaw !== null ? Math.max(0, Math.trunc(reservedRaw)) : null;
    const resolvedAvailable = available !== null
      ? available
      : (reserved !== null ? Math.max(0, total - reserved) : Math.max(0, total));
    const resolvedReserved = reserved !== null
      ? reserved
      : Math.max(0, total - resolvedAvailable);

    const message = formatRandomNameInventoryMessage(randomNameInventoryMessages.lexicon, {
      total,
      available: resolvedAvailable,
      reserved: resolvedReserved,
    });

    setRandomNameInventoryMessage(randomNameInventoryFields.lexicon, message);
  };

  const refreshRandomNameInventory = async () => {
    if (!randomNameInventoryContainer || !randomNameInventoryMessages) {
      return;
    }

    const eventUid = currentEventUid;
    if (!eventUid) {
      randomNameInventoryRequestId += 1;
      showRandomNameInventory(false);
      return;
    }

    showRandomNameInventory(true);
    renderRandomNameInventoryLoading();

    const requestId = ++randomNameInventoryRequestId;
    const params = new URLSearchParams();
    params.set('event_uid', eventUid);

    try {
      const response = await apiFetch(`/api/team-names/status?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
      });

      if (!response.ok) {
        throw new Error('random-name-inventory-status-failed');
      }

      const payload = await response.json();
      if (requestId !== randomNameInventoryRequestId) {
        return;
      }

      renderRandomNameInventoryData(payload);
    } catch (error) {
      if (requestId !== randomNameInventoryRequestId) {
        return;
      }

      renderRandomNameInventoryError();
    }
  };

  const setRandomNamePreviewStatus = (messageKey) => {
    if (!randomNamePreviewStatus || !randomNamePreviewMessages) {
      return;
    }
    const message = randomNamePreviewMessages[messageKey] || '';
    randomNamePreviewStatus.textContent = message;
    randomNamePreviewStatus.hidden = message === '';
  };

  const resetRandomNamePreview = (messageKey = 'hint') => {
    clearRandomNamePreviewList();
    toggleRandomNamePreviewSpinner(false);
    setRandomNamePreviewStatus(messageKey);
    randomNamePreviewContext = { eventUid: '', fingerprint: '' };
    resetRandomNameCache();
  };

  const computeRandomNamePreviewFingerprint = () => {
    const domains = Array.isArray(cfgFields.randomNameDomains)
      ? formUtils.readChecked(cfgFields.randomNameDomains).slice().sort()
      : [];
    const tones = Array.isArray(cfgFields.randomNameTones)
      ? formUtils.readChecked(cfgFields.randomNameTones).slice().sort()
      : [];
    const localeRaw = typeof cfgFields.randomNameLocale?.value === 'string'
      ? cfgFields.randomNameLocale.value.trim().toLowerCase()
      : '';
    return JSON.stringify({ domains, tones, locale: localeRaw });
  };

  const updateRandomNamePreviewState = (randomNamesEnabled, aiSelected) => {
    if (!randomNamePreviewContainer || !randomNamePreviewMessages) {
      return;
    }

    const shouldShow = randomNamesEnabled && aiSelected;
    randomNamePreviewContainer.hidden = !shouldShow;
    updateRandomNameCacheVisibility(shouldShow && !!currentEventUid);

    if (cfgFields.randomNamePreviewButton) {
      cfgFields.randomNamePreviewButton.disabled = !shouldShow || !currentEventUid;
    }

    if (!shouldShow) {
      resetRandomNamePreview('hint');
      return;
    }

    if (!currentEventUid) {
      resetRandomNamePreview('requiresEvent');
      return;
    }

    const fingerprint = computeRandomNamePreviewFingerprint();
    if (
      randomNamePreviewContext.eventUid !== currentEventUid ||
      randomNamePreviewContext.fingerprint !== fingerprint
    ) {
      setRandomNamePreviewStatus('hint');
      clearRandomNamePreviewList();
      toggleRandomNamePreviewSpinner(false);
      randomNamePreviewContext = { eventUid: '', fingerprint: '' };
    }
  };

  const invalidateRandomNamePreview = () => {
    if (!randomNamePreviewContainer || !randomNamePreviewMessages) {
      return;
    }
    resetRandomNamePreview(currentEventUid ? 'hint' : 'requiresEvent');
    updateRandomNameCacheVisibility(!!currentEventUid && !!randomNameCacheContainer && !randomNamePreviewContainer.hidden);
  };

  const resolveRandomNamePreviewCount = () => {
    const fallback = 1;
    if (!cfgFields.randomNameBuffer) {
      return fallback;
    }

    const rawValue = cfgFields.randomNameBuffer.value;
    const parsedValue = Number.parseInt(rawValue, 10);
    const min = Number.parseInt(cfgFields.randomNameBuffer.min, 10);
    const max = Number.parseInt(cfgFields.randomNameBuffer.max, 10);

    let normalized = Number.isNaN(parsedValue) ? fallback : parsedValue;
    if (!Number.isNaN(min)) {
      normalized = Math.max(normalized, min);
    }
    if (!Number.isNaN(max)) {
      normalized = Math.min(normalized, max);
    }

    return normalized;
  };

  const requestRandomNamePreview = async () => {
    if (
      !cfgFields.randomNamePreviewButton
      || !randomNamePreviewContainer
      || !randomNamePreviewMessages
    ) {
      return;
    }

    if (!currentEventUid) {
      resetRandomNamePreview('requiresEvent');
      return;
    }

    const domains = Array.isArray(cfgFields.randomNameDomains)
      ? formUtils.readChecked(cfgFields.randomNameDomains)
      : [];
    const tones = Array.isArray(cfgFields.randomNameTones)
      ? formUtils.readChecked(cfgFields.randomNameTones)
      : [];
    const localeRaw = typeof cfgFields.randomNameLocale?.value === 'string'
      ? cfgFields.randomNameLocale.value.trim()
      : '';
    const locale = localeRaw === '' ? null : localeRaw;

    const fingerprint = computeRandomNamePreviewFingerprint();

    toggleRandomNamePreviewSpinner(true);
    clearRandomNamePreviewList();
    setRandomNamePreviewStatus('loading');
    resetRandomNameCache();

    const targetCount = resolveRandomNamePreviewCount();

    const payload = {
      event_id: currentEventUid,
      count: targetCount,
      domains,
      tones
    };
    if (locale) {
      payload.locale = locale;
    }

    cfgFields.randomNamePreviewButton.disabled = true;

    try {
      const response = await apiFetch('/api/team-names/preview', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      if (!response.ok) {
        throw new Error('random-name-cache-fill-failed');
      }
      const data = await response.json();
      renderRandomNameCache(data?.cache);
      renderRandomNameLog(data?.log);
      randomNamePreviewContext = { eventUid: currentEventUid, fingerprint };

      const hasLogEntries = Array.isArray(data?.log?.entries) && data.log.entries.length > 0;
      if (!hasLogEntries) {
        setRandomNamePreviewStatus('none');
      }
    } catch (error) {
      randomNamePreviewContext = { eventUid: '', fingerprint: '' };
      setRandomNamePreviewStatus('error');
      resetRandomNameCache();
    } finally {
      toggleRandomNamePreviewSpinner(false);
      const randomNamesEnabled = !!cfgFields.randomNames && !!cfgFields.randomNames.checked;
      const strategy = typeof cfgFields.randomNameStrategy?.value === 'string'
        ? cfgFields.randomNameStrategy.value.toLowerCase()
        : '';
      cfgFields.randomNamePreviewButton.disabled = !randomNamesEnabled || strategy !== 'ai' || !currentEventUid;
      refreshRandomNameInventory();
    }
  };
  const syncRandomNameOptionsState = () => {
    if (!randomNameOptionsFieldset && !randomNameHintsContainer) {
      return;
    }
    const randomNamesEnabled = !!cfgFields.randomNames && !!cfgFields.randomNames.checked;
    const strategyRaw = cfgFields.randomNameStrategy?.value;
    const strategy = typeof strategyRaw === 'string' ? strategyRaw.toLowerCase() : '';
    const aiSelected = strategy === 'ai';
    const fieldsetEnabled = randomNamesEnabled && aiSelected;
    if (randomNameOptionsFieldset) {
      randomNameOptionsFieldset.disabled = !fieldsetEnabled;
      randomNameOptionsFieldset.classList.toggle('uk-disabled', !fieldsetEnabled);
    }
    if (randomNameHintsContainer && randomNameHintText && randomNameHintMessages) {
      let hintKey = 'disabled';
      if (randomNamesEnabled) {
        hintKey = aiSelected ? 'ai' : 'lexicon';
      }
      const message = randomNameHintMessages[hintKey] || '';
      randomNameHintText.textContent = message;
      randomNameHintsContainer.hidden = message === '';
    }
    updateRandomNamePreviewState(randomNamesEnabled, aiSelected);
  };
  const dashboardModulesList = document.querySelector('[data-dashboard-modules]');
  const dashboardModuleInputs = {
    public: document.getElementById('cfgDashboardModules'),
    sponsor: document.getElementById('cfgDashboardSponsorModules')
  };
  const dashboardVariantSwitch = document.querySelector('[data-dashboard-variant-switch]');
  const dashboardVariantButtons = dashboardVariantSwitch
    ? Array.from(dashboardVariantSwitch.querySelectorAll('[data-dashboard-variant]'))
    : [];
  let activeDashboardVariant = 'public';
  const dashboardModulesState = {
    public: [],
    sponsor: []
  };
  let dashboardSponsorModulesInherited = false;
  const dashboardShareInputs = {
    public: document.querySelector('[data-share-link="public"]'),
    sponsor: document.querySelector('[data-share-link="sponsor"]')
  };
  const DASHBOARD_LAYOUT_OPTIONS = ['auto', 'wide', 'full'];
  const DASHBOARD_RESULTS_SORT_OPTIONS = ['time', 'points', 'name'];
  const DASHBOARD_RESULTS_MAX_LIMIT = 50;
  const DASHBOARD_POINTS_LEADER_MIN_LIMIT = 1;
  const DASHBOARD_POINTS_LEADER_MAX_LIMIT = 10;
  const DASHBOARD_POINTS_LEADER_DEFAULT_LIMIT = 5;
  const DASHBOARD_RESULTS_DEFAULT_INTERVAL = 10;
  const DASHBOARD_RESULTS_PAGE_INTERVAL_MIN = 1;
  const DASHBOARD_RESULTS_PAGE_INTERVAL_MAX = 300;
  const DASHBOARD_DEFAULT_MODULES = [
    { id: 'header', enabled: true, layout: 'full' },
    { id: 'pointsLeader', enabled: true, layout: 'wide', options: { title: 'Platzierungen', limit: 5 } },
    {
      id: 'rankings',
      enabled: true,
      layout: 'wide',
      options: {
        limit: null,
        pageSize: 10,
        pageInterval: DASHBOARD_RESULTS_DEFAULT_INTERVAL,
        sort: 'time',
        title: 'Live-Rankings',
        showPlacement: false,
      },
    },
    {
      id: 'results',
      enabled: true,
      layout: 'full',
      options: {
        limit: null,
        pageSize: 10,
        pageInterval: DASHBOARD_RESULTS_DEFAULT_INTERVAL,
        sort: 'time',
        title: 'Ergebnisliste',
        showPlacement: false,
      }
    },
    { id: 'wrongAnswers', enabled: false, layout: 'auto', options: { title: 'Falsch beantwortete Fragen' } },
    { id: 'infoBanner', enabled: false, layout: 'auto', options: { title: 'Hinweise' } },
    { id: 'qrCodes', enabled: false, layout: 'auto', options: { catalogs: [], title: 'Katalog-QR-Codes' } },
    { id: 'media', enabled: false, layout: 'auto', options: { title: 'Highlights' } }
  ];
  const DASHBOARD_DEFAULT_MODULE_MAP = new Map(DASHBOARD_DEFAULT_MODULES.map(module => [module.id, module]));
  const normalizeDashboardResultsLimit = (value) => {
    if (value === null || value === undefined) {
      return null;
    }
    const normalized = String(value).trim();
    if (normalized === '' || normalized === '0') {
      return null;
    }
    const parsed = Number.parseInt(normalized, 10);
    if (Number.isNaN(parsed) || parsed <= 0) {
      return null;
    }
    return Math.min(parsed, DASHBOARD_RESULTS_MAX_LIMIT);
  };

  const normalizeDashboardResultsPageSize = (value, limit) => {
    if (value === null || value === undefined) {
      return null;
    }
    const normalized = String(value).trim();
    if (normalized === '' || normalized === '0') {
      return null;
    }
    const parsed = Number.parseInt(normalized, 10);
    if (Number.isNaN(parsed) || parsed <= 0) {
      return null;
    }
    let resolved = Math.min(parsed, DASHBOARD_RESULTS_MAX_LIMIT);
    const normalizedLimit = typeof limit === 'number' && Number.isFinite(limit) && limit > 0
      ? Math.floor(limit)
      : null;
    if (normalizedLimit !== null && resolved > normalizedLimit) {
      resolved = normalizedLimit;
    }
    return resolved;
  };

  const normalizeDashboardResultsPageInterval = (value) => {
    if (value === null || value === undefined) {
      return null;
    }
    const normalized = String(value).trim();
    if (normalized === '' || normalized === '0') {
      return null;
    }
    const parsed = Number.parseInt(normalized, 10);
    if (Number.isNaN(parsed) || parsed < DASHBOARD_RESULTS_PAGE_INTERVAL_MIN) {
      return null;
    }
    return Math.min(parsed, DASHBOARD_RESULTS_PAGE_INTERVAL_MAX);
  };

  const normalizeDashboardPointsLeaderLimit = (value) => {
    if (value === null || value === undefined) {
      return null;
    }
    const normalized = String(value).trim();
    if (normalized === '') {
      return null;
    }
    const parsed = Number.parseInt(normalized, 10);
    if (Number.isNaN(parsed) || parsed < DASHBOARD_POINTS_LEADER_MIN_LIMIT) {
      return null;
    }
    if (parsed > DASHBOARD_POINTS_LEADER_MAX_LIMIT) {
      return DASHBOARD_POINTS_LEADER_MAX_LIMIT;
    }
    return parsed;
  };

  function applyDashboardResultsOptions(item, moduleId, options = {}) {
    if (!item) {
      return;
    }
    const defaults = DASHBOARD_DEFAULT_MODULE_MAP.get(moduleId)?.options || {};
    const limitField = item.querySelector('[data-module-results-option="limit"]');
    const limitValue = normalizeDashboardResultsLimit(
      options && Object.prototype.hasOwnProperty.call(options, 'limit')
        ? options.limit
        : defaults.limit
    );
    if (limitField) {
      limitField.value = limitValue === null ? '' : String(limitValue);
    }
    const pageSizeField = item.querySelector('[data-module-results-option="pageSize"]');
    if (pageSizeField) {
      const pageSizeValue = normalizeDashboardResultsPageSize(
        options && Object.prototype.hasOwnProperty.call(options, 'pageSize')
          ? options.pageSize
          : defaults.pageSize,
        limitValue
      );
      pageSizeField.value = pageSizeValue === null ? '' : String(pageSizeValue);
    }
    const pageIntervalField = item.querySelector('[data-module-results-option="pageInterval"]');
    if (pageIntervalField) {
      const fallbackInterval = normalizeDashboardResultsPageInterval(defaults.pageInterval)
        ?? DASHBOARD_RESULTS_DEFAULT_INTERVAL;
      const intervalValue = normalizeDashboardResultsPageInterval(
        options && Object.prototype.hasOwnProperty.call(options, 'pageInterval')
          ? options.pageInterval
          : defaults.pageInterval
      );
      const resolvedInterval = intervalValue ?? fallbackInterval;
      pageIntervalField.value = resolvedInterval === null ? '' : String(resolvedInterval);
    }
    const sortField = item.querySelector('[data-module-results-option="sort"]');
    if (sortField) {
      const fallbackSort = defaults.sort || 'time';
      const rawSort = typeof options?.sort === 'string' ? options.sort.trim() : '';
      sortField.value = DASHBOARD_RESULTS_SORT_OPTIONS.includes(rawSort) ? rawSort : fallbackSort;
    }
    const titleField = item.querySelector('[data-module-results-option="title"]');
    if (titleField) {
      const fallbackTitle = defaults.title || (moduleId === 'rankings' ? 'Live-Rankings' : 'Ergebnisliste');
      const rawTitle = typeof options?.title === 'string' ? options.title.trim() : '';
      titleField.value = rawTitle !== '' ? rawTitle : fallbackTitle;
    }
    const placementField = item.querySelector('[data-module-results-option="showPlacement"]');
    if (placementField) {
      const fallbackPlacement = resolveBooleanOption(defaults.showPlacement, false);
      const rawPlacement = Object.prototype.hasOwnProperty.call(options || {}, 'showPlacement')
        ? options.showPlacement
        : defaults.showPlacement;
      placementField.checked = resolveBooleanOption(rawPlacement, fallbackPlacement);
    }
    syncDashboardResultsPageSizeState(item);
  }

  function applyDashboardPointsLeaderOptions(item, options = {}) {
    if (!item) {
      return;
    }
    const field = item.querySelector('[data-module-points-leader-limit]');
    if (!field) {
      return;
    }
    const defaults = DASHBOARD_DEFAULT_MODULE_MAP.get('pointsLeader')?.options || {};
    const fallback = normalizeDashboardPointsLeaderLimit(
      options && Object.prototype.hasOwnProperty.call(options, 'limit')
        ? options.limit
        : defaults.limit
    ) ?? normalizeDashboardPointsLeaderLimit(defaults.limit) ?? DASHBOARD_POINTS_LEADER_DEFAULT_LIMIT;
    field.value = String(fallback);
  }

  function syncDashboardResultsPageSizeState(item) {
    if (!item) {
      return;
    }
    const limitField = item.querySelector('[data-module-results-option="limit"]');
    const pageSizeField = item.querySelector('[data-module-results-option="pageSize"]');
    if (!pageSizeField) {
      return;
    }
    pageSizeField.disabled = false;
    const limitValue = normalizeDashboardResultsLimit(limitField?.value);
    const rawValue = typeof pageSizeField.value === 'string' ? pageSizeField.value.trim() : '';
    if (rawValue === '') {
      return;
    }
    const normalized = normalizeDashboardResultsPageSize(rawValue, limitValue);
    if (normalized === null) {
      if (limitValue !== null) {
        pageSizeField.value = String(limitValue);
      } else {
        pageSizeField.value = '';
      }
    } else if (String(normalized) !== rawValue) {
      pageSizeField.value = String(normalized);
    }
  }
  function applyDashboardModuleTitle(item, moduleId, options = {}) {
    if (!item) {
      return;
    }
    const field = item.querySelector('[data-module-title]');
    if (!field) {
      return;
    }
    const defaults = DASHBOARD_DEFAULT_MODULE_MAP.get(moduleId)?.options || {};
    const fallback = typeof defaults.title === 'string' && defaults.title.trim() !== ''
      ? defaults.title
      : (field.placeholder || '');
    const raw = typeof options?.title === 'string' ? options.title.trim() : '';
    field.value = raw !== '' ? options.title : fallback;
  }
  function readDashboardModuleTitle(item, moduleId) {
    const field = item.querySelector('[data-module-title]');
    if (!field) {
      return null;
    }
    const defaults = DASHBOARD_DEFAULT_MODULE_MAP.get(moduleId)?.options || {};
    const fallback = typeof defaults.title === 'string' ? defaults.title : '';
    const placeholder = typeof field.placeholder === 'string' ? field.placeholder : '';
    const base = fallback.trim() !== '' ? fallback : placeholder.trim();
    const raw = typeof field.value === 'string' ? field.value.trim() : '';
    const resolved = raw !== '' ? raw : base;
    return resolved !== '' ? resolved : null;
  }
  const DASHBOARD_QR_MODULE_ID = 'qrCodes';
  const dashboardQrModule = dashboardModulesList?.querySelector('[data-module-id="' + DASHBOARD_QR_MODULE_ID + '"]') || null;
  const dashboardQrCatalogContainer = dashboardQrModule?.querySelector('[data-module-catalogs]') || null;
  const dashboardQrEmptyNote = dashboardQrModule?.querySelector('[data-module-catalogs-empty]') || null;
  const dashboardQrMissingLabel = dashboardQrModule?.dataset.missingLabel || 'Catalog not found';
  let dashboardQrCatalogs = [];
  let dashboardQrFetchEpoch = 0;
  let dashboardPublicToken = cfgInitial.dashboardShareToken || '';
  let dashboardSponsorToken = cfgInitial.dashboardSponsorToken || '';
  const ragChatFields = {
    form: document.getElementById('ragChatForm'),
    url: document.getElementById('ragChatUrl'),
    driver: document.getElementById('ragChatDriver'),
    forceOpenAi: document.getElementById('ragChatForceOpenAi'),
    token: document.getElementById('ragChatToken'),
    tokenClear: document.getElementById('ragChatTokenClear'),
    tokenStatus: document.getElementById('ragChatTokenStatus'),
    model: document.getElementById('ragChatModel'),
    temperature: document.getElementById('ragChatTemperature'),
    topP: document.getElementById('ragChatTopP'),
    maxTokens: document.getElementById('ragChatMaxTokens'),
    presencePenalty: document.getElementById('ragChatPresencePenalty'),
    frequencyPenalty: document.getElementById('ragChatFrequencyPenalty')
  };

  function ragChatIsTruthy(value) {
    if (typeof value === 'string') {
      const normalized = value.trim().toLowerCase();
      return ['1', 'true', 'yes', 'on'].includes(normalized);
    }
    return value === true;
  }

  function updateRagChatTokenStatus() {
    if (!ragChatFields.tokenStatus) return;
    const hasToken = ragChatIsTruthy(settingsInitial.rag_chat_service_token_present);
    ragChatFields.tokenStatus.textContent = hasToken
      ? (transRagChatTokenSaved || '')
      : (transRagChatTokenMissing || '');
  }

  const normalizeDashboardCatalogList = (rawList) => {
    if (!Array.isArray(rawList)) {
      return [];
    }
    return rawList.map((item) => {
      const uid = item?.uid ? String(item.uid) : '';
      const slug = item?.slug ? String(item.slug) : '';
      const sortOrder = item?.sort_order !== undefined && item?.sort_order !== null
        ? String(item.sort_order)
        : '';
      const name = item?.name ? String(item.name) : (slug || sortOrder || uid);
      return { uid, slug, sortOrder, name };
    });
  };

  const getDashboardQrSelection = (modules) => {
    if (!Array.isArray(modules)) {
      return [];
    }
    const module = modules.find((entry) => entry && entry.id === DASHBOARD_QR_MODULE_ID);
    if (!module) {
      return [];
    }
    const raw = module.options?.catalogs;
    if (Array.isArray(raw)) {
      return raw
        .map((value) => String(value ?? '').trim())
        .filter((value) => value !== '');
    }
    if (typeof raw === 'string' && raw.trim() !== '') {
      return [raw.trim()];
    }
    return [];
  };

  function syncDashboardQrOptions(selectedIds = [], mark = false) {
    if (!dashboardQrCatalogContainer) {
      if (mark) {
        updateDashboardModules(true);
      }
      return;
    }
    const normalizedSelection = Array.isArray(selectedIds)
      ? Array.from(new Set(selectedIds.map((value) => String(value ?? '').trim()).filter((value) => value !== '')))
      : [];

    dashboardQrCatalogContainer.innerHTML = '';
    if (!dashboardQrCatalogs.length) {
      if (dashboardQrEmptyNote) {
        dashboardQrEmptyNote.hidden = false;
      }
      if (mark) {
        updateDashboardModules(true);
      } else {
        updateDashboardModules(false);
      }
      return;
    }

    if (dashboardQrEmptyNote) {
      dashboardQrEmptyNote.hidden = true;
    }

    const seen = new Set();
    dashboardQrCatalogs.forEach((catalog) => {
      const identifier = catalog.uid || catalog.slug || catalog.sortOrder || '';
      if (!identifier) {
        return;
      }
      const label = document.createElement('label');
      label.className = 'uk-display-block uk-margin-small-bottom';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.className = 'uk-checkbox';
      checkbox.value = identifier;
      checkbox.dataset.moduleCatalog = '1';
      if (catalog.uid) {
        checkbox.dataset.catalogUid = catalog.uid;
      }
      if (catalog.slug) {
        checkbox.dataset.catalogSlug = catalog.slug;
      }
      if (catalog.sortOrder) {
        checkbox.dataset.catalogSort = catalog.sortOrder;
      }
      if (
        normalizedSelection.includes(identifier)
        || (catalog.uid && normalizedSelection.includes(catalog.uid))
        || (catalog.slug && normalizedSelection.includes(catalog.slug))
        || (catalog.sortOrder && normalizedSelection.includes(String(catalog.sortOrder)))
      ) {
        checkbox.checked = true;
        seen.add(identifier);
        if (catalog.uid) seen.add(catalog.uid);
        if (catalog.slug) seen.add(catalog.slug);
        if (catalog.sortOrder) seen.add(String(catalog.sortOrder));
      }
      label.appendChild(checkbox);
      const span = document.createElement('span');
      span.className = 'uk-margin-small-left';
      span.textContent = catalog.name;
      label.appendChild(span);
      dashboardQrCatalogContainer.appendChild(label);
    });

    normalizedSelection.forEach((id) => {
      const normalizedId = String(id);
      if (seen.has(normalizedId)) {
        return;
      }
      const label = document.createElement('label');
      label.className = 'uk-display-block uk-margin-small-bottom uk-text-muted';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.className = 'uk-checkbox';
      checkbox.value = normalizedId;
      checkbox.dataset.moduleCatalog = '1';
      checkbox.checked = true;
      label.appendChild(checkbox);
      const span = document.createElement('span');
      span.className = 'uk-margin-small-left';
      span.textContent = `${dashboardQrMissingLabel} (${normalizedId})`;
      label.appendChild(span);
      dashboardQrCatalogContainer.appendChild(label);
    });

    if (mark) {
      updateDashboardModules(true);
    } else {
      updateDashboardModules(false);
    }
  }

  function loadDashboardQrCatalogOptions(selectedIds = [], mark = false) {
    if (!dashboardModulesList) {
      return Promise.resolve();
    }
    const targetSelection = Array.isArray(selectedIds) ? selectedIds : [];
    if (dashboardQrCatalogs.length > 0) {
      syncDashboardQrOptions(targetSelection, mark);
      return Promise.resolve();
    }
    const requestId = ++dashboardQrFetchEpoch;
    return apiFetch('/kataloge/catalogs.json', { headers: { 'Accept': 'application/json' } })
      .then((res) => {
        if (!res.ok) {
          throw new Error('catalogs');
        }
        return res.json();
      })
      .then((payload) => {
        if (requestId !== dashboardQrFetchEpoch) {
          return;
        }
        const list = Array.isArray(payload)
          ? payload
          : Array.isArray(payload?.items)
            ? payload.items
            : [];
        dashboardQrCatalogs = normalizeDashboardCatalogList(list);
        const domSelection = getDashboardQrSelection(readDashboardModules());
        const effectiveSelection = domSelection.length ? domSelection : targetSelection;
        syncDashboardQrOptions(effectiveSelection, mark);
      })
      .catch(() => {
        if (requestId !== dashboardQrFetchEpoch) {
          return;
        }
        dashboardQrCatalogs = [];
        const domSelection = getDashboardQrSelection(readDashboardModules());
        const effectiveSelection = domSelection.length ? domSelection : targetSelection;
        syncDashboardQrOptions(effectiveSelection, mark);
      });
  }

  function renderRagChatSettings() {
    if (!ragChatFields.form) return;

    if (ragChatFields.url) {
      ragChatFields.url.value = settingsInitial.rag_chat_service_url || '';
    }
    if (ragChatFields.driver) {
      ragChatFields.driver.value = settingsInitial.rag_chat_service_driver || '';
    }
    if (ragChatFields.forceOpenAi) {
      ragChatFields.forceOpenAi.checked = ragChatIsTruthy(settingsInitial.rag_chat_service_force_openai);
    }
    if (ragChatFields.model) {
      ragChatFields.model.value = settingsInitial.rag_chat_service_model || '';
    }
    if (ragChatFields.temperature) {
      ragChatFields.temperature.value = settingsInitial.rag_chat_service_temperature || '';
    }
    if (ragChatFields.topP) {
      ragChatFields.topP.value = settingsInitial.rag_chat_service_top_p || '';
    }
    if (ragChatFields.maxTokens) {
      ragChatFields.maxTokens.value = settingsInitial.rag_chat_service_max_tokens || '';
    }
    if (ragChatFields.presencePenalty) {
      ragChatFields.presencePenalty.value = settingsInitial.rag_chat_service_presence_penalty || '';
    }
    if (ragChatFields.frequencyPenalty) {
      ragChatFields.frequencyPenalty.value = settingsInitial.rag_chat_service_frequency_penalty || '';
    }

    if (ragChatFields.token) {
      ragChatFields.token.value = '';
      const hasToken = ragChatIsTruthy(settingsInitial.rag_chat_service_token_present);
      ragChatFields.token.placeholder = hasToken ? ragChatTokenPlaceholder : '';
    }
    if (ragChatFields.tokenClear) {
      ragChatFields.tokenClear.checked = false;
    }

    updateRagChatTokenStatus();
  }

  function collectRagChatPayload() {
    const payload = {
      rag_chat_service_url: ragChatFields.url?.value?.trim() || '',
      rag_chat_service_driver: ragChatFields.driver?.value?.trim() || '',
      rag_chat_service_force_openai: ragChatFields.forceOpenAi?.checked ? '1' : '0',
      rag_chat_service_model: ragChatFields.model?.value?.trim() || '',
      rag_chat_service_temperature: ragChatFields.temperature?.value?.trim() || '',
      rag_chat_service_top_p: ragChatFields.topP?.value?.trim() || '',
      rag_chat_service_max_tokens: ragChatFields.maxTokens?.value?.trim() || '',
      rag_chat_service_presence_penalty: ragChatFields.presencePenalty?.value?.trim() || '',
      rag_chat_service_frequency_penalty: ragChatFields.frequencyPenalty?.value?.trim() || ''
    };

    const tokenValue = ragChatFields.token?.value?.trim() || '';
    if (ragChatFields.tokenClear?.checked) {
      payload.rag_chat_service_token = '';
    } else if (tokenValue !== '') {
      payload.rag_chat_service_token = tokenValue;
    }

    return payload;
  }
  const puzzleFeedbackBtn = document.getElementById('puzzleFeedbackBtn');
  const puzzleIcon = document.getElementById('puzzleFeedbackIcon');
  const puzzleLabel = document.getElementById('puzzleFeedbackLabel');
  const puzzleTextarea = document.getElementById('puzzleFeedbackTextarea');
  const puzzleSaveBtn = document.getElementById('puzzleFeedbackSave');
  const puzzleModal = window.UIkit ? UIkit.modal('#puzzleFeedbackModal') : null;
  const inviteTextBtn = document.getElementById('inviteTextBtn');
  const inviteLabel = document.getElementById('inviteTextLabel');
  const inviteTextarea = document.getElementById('inviteTextTextarea');
  const inviteSaveBtn = document.getElementById('inviteTextSave');
  const inviteModal = window.UIkit ? UIkit.modal('#inviteTextModal') : null;
  const inviteToolbar = document.getElementById('inviteTextToolbar');
  const commentTextarea = document.getElementById('catalogCommentTextarea');
  const commentSaveBtn = document.getElementById('catalogCommentSave');
  const commentModal = window.UIkit ? UIkit.modal('#catalogCommentModal') : null;
  const commentToolbar = document.getElementById('catalogCommentToolbar');
  const catalogEditInput = document.getElementById('catalogEditInput');
  const catalogEditError = document.getElementById('catalogEditError');
  const resultsResetModalEl = document.getElementById('resultsResetModal');
  const resultsResetModal = resultsResetModalEl && window.UIkit ? UIkit.modal(resultsResetModalEl) : null;
  const resultsResetConfirm = document.getElementById('resultsResetConfirm');
  let puzzleFeedback = '';
  let inviteText = '';
  let currentCommentItem = null;

  function wrapSelection(textarea, before, after) {
    if (!textarea) return;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const val = textarea.value;
    textarea.value = val.slice(0, start) + before + val.slice(start, end) + after + val.slice(end);
    textarea.focus();
    textarea.selectionStart = start + before.length;
    textarea.selectionEnd = end + before.length;
  }

  function insertText(textarea, text) {
    if (!textarea) return;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const val = textarea.value;
    textarea.value = val.slice(0, start) + text + val.slice(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + text.length;
  }

  commentToolbar?.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-format]');
    if (!btn) return;
    const fmt = btn.dataset.format;
    switch (fmt) {
      case 'h2':
        wrapSelection(commentTextarea, '<h2>', '</h2>');
        break;
      case 'h3':
        wrapSelection(commentTextarea, '<h3>', '</h3>');
        break;
      case 'h4':
        wrapSelection(commentTextarea, '<h4>', '</h4>');
        break;
      case 'h5':
        wrapSelection(commentTextarea, '<h5>', '</h5>');
        break;
      case 'bold':
        wrapSelection(commentTextarea, '<strong>', '</strong>');
        break;
      case 'italic':
        wrapSelection(commentTextarea, '<em>', '</em>');
        break;
    }
  });

  inviteToolbar?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-format],[data-insert]');
    if (!btn) return;
    if (btn.dataset.insert) {
      insertText(inviteTextarea, btn.dataset.insert);
      return;
    }
    const fmt = btn.dataset.format;
    switch (fmt) {
      case 'h2':
        wrapSelection(inviteTextarea, '<h2>', '</h2>');
        break;
      case 'h3':
        wrapSelection(inviteTextarea, '<h3>', '</h3>');
        break;
      case 'h4':
        wrapSelection(inviteTextarea, '<h4>', '</h4>');
        break;
      case 'h5':
        wrapSelection(inviteTextarea, '<h5>', '</h5>');
        break;
      case 'bold':
        wrapSelection(inviteTextarea, '<strong>', '</strong>');
        break;
      case 'italic':
        wrapSelection(inviteTextarea, '<em>', '</em>');
        break;
    }
  });
  if (cfgFields.logoFile && cfgFields.logoPreview) {
    const bar = document.getElementById('cfgLogoProgress');
    if (window.UIkit && UIkit.upload) {
      UIkit.upload('.js-upload', {
        url: withBase('/logo.png'),
        name: 'file',
        multiple: false,
        error: function (e) {
          const msg = (e && e.xhr && e.xhr.responseText) ? e.xhr.responseText : 'Fehler beim Hochladen';
          notify(msg, 'danger');
        },
        loadStart: function (e) {
          bar.removeAttribute('hidden');
          bar.max = e.total;
          bar.value = e.loaded;
        },
        progress: function (e) {
          bar.max = e.total;
          bar.value = e.loaded;
        },
        loadEnd: function (e) {
          bar.max = e.total;
          bar.value = e.loaded;
        },
        completeAll: function () {
          setTimeout(function () {
            bar.setAttribute('hidden', 'hidden');
          }, 1000);
          const file = cfgFields.logoFile.files && cfgFields.logoFile.files[0];
          const ext = (() => {
            if (!file) return 'png';
            const name = file.name.toLowerCase();
            if (name.endsWith('.svg')) return 'svg';
            if (name.endsWith('.webp')) return 'webp';
            return 'png';
          })();
          cfgInitial.logoPath = currentEventUid
            ? `/logo-${currentEventUid}.${ext}`
            : `/logo.${ext}`;
          cfgFields.logoPreview.src = withBase(cfgInitial.logoPath) + '?' + Date.now();
          notify('Logo hochgeladen', 'success');
        }
      });
    }
  }
  function readDashboardModules() {
    if (!dashboardModulesList) {
      return [];
    }
    const modules = [];
    dashboardModulesList.querySelectorAll('[data-module-id]').forEach(item => {
      const id = item.dataset.moduleId || '';
      if (!id) {
        return;
      }
      const toggle = item.querySelector('[data-module-toggle]');
      const enabled = toggle ? toggle.checked : true;
      const entry = { id, enabled };
      const layoutField = item.querySelector('[data-module-layout]');
      const defaultLayout = DASHBOARD_DEFAULT_MODULE_MAP.get(id)?.layout
        || layoutField?.dataset.defaultLayout
        || 'auto';
      let layout = layoutField ? (layoutField.value || layoutField.dataset.defaultLayout || defaultLayout) : defaultLayout;
      layout = String(layout || '').trim();
      if (!DASHBOARD_LAYOUT_OPTIONS.includes(layout)) {
        layout = defaultLayout;
      }
      entry.layout = layout;
      if (id === 'rankings' || id === 'results') {
        const defaults = DASHBOARD_DEFAULT_MODULE_MAP.get(id)?.options || {};
        const limitField = item.querySelector('[data-module-results-option="limit"]');
        const pageSizeField = item.querySelector('[data-module-results-option="pageSize"]');
        const pageIntervalField = item.querySelector('[data-module-results-option="pageInterval"]');
        const sortField = item.querySelector('[data-module-results-option="sort"]');
        const titleField = item.querySelector('[data-module-results-option="title"]');
        const limitValue = limitField
          ? normalizeDashboardResultsLimit(limitField.value)
          : normalizeDashboardResultsLimit(defaults.limit);
        let pageSizeValue = pageSizeField
          ? normalizeDashboardResultsPageSize(pageSizeField.value, limitValue)
          : null;
        if (pageSizeValue === null) {
          pageSizeValue = normalizeDashboardResultsPageSize(defaults.pageSize, limitValue);
        }
        const defaultInterval = normalizeDashboardResultsPageInterval(defaults.pageInterval)
          ?? DASHBOARD_RESULTS_DEFAULT_INTERVAL;
        let pageIntervalValue = pageIntervalField
          ? normalizeDashboardResultsPageInterval(pageIntervalField.value)
          : null;
        if (pageIntervalValue === null) {
          pageIntervalValue = defaultInterval;
        }
        const rawSort = sortField ? String(sortField.value || '').trim() : '';
        const sortValue = DASHBOARD_RESULTS_SORT_OPTIONS.includes(rawSort)
          ? rawSort
          : (defaults.sort || 'time');
        let titleValue = titleField ? titleField.value.trim() : '';
        if (titleValue === '') {
          titleValue = defaults.title || (id === 'rankings' ? 'Live-Rankings' : 'Ergebnisliste');
        }
        const placementField = item.querySelector('[data-module-results-option="showPlacement"]');
        let placementValue = false;
        if (placementField) {
          placementValue = placementField.checked;
        } else if (Object.prototype.hasOwnProperty.call(defaults, 'showPlacement')) {
          placementValue = defaults.showPlacement === true;
        }
        const supportsPlacement = Object.prototype.hasOwnProperty.call(defaults, 'showPlacement');
        entry.options = {
          limit: limitValue,
          pageSize: pageSizeValue,
          pageInterval: pageIntervalValue,
          sort: sortValue,
          title: titleValue,
          ...(supportsPlacement ? { showPlacement: placementValue } : {}),
        };
      } else if (id === 'pointsLeader') {
        const defaults = DASHBOARD_DEFAULT_MODULE_MAP.get(id)?.options || {};
        const limitField = item.querySelector('[data-module-points-leader-limit]');
        const fallbackLimit = normalizeDashboardPointsLeaderLimit(defaults.limit)
          ?? DASHBOARD_POINTS_LEADER_DEFAULT_LIMIT;
        const limitValue = limitField
          ? normalizeDashboardPointsLeaderLimit(limitField.value)
          : null;
        const resolvedLimit = limitValue ?? fallbackLimit;
        entry.options = { limit: resolvedLimit };
      } else if (id === DASHBOARD_QR_MODULE_ID) {
        const catalogs = [];
        item.querySelectorAll('[data-module-catalog]').forEach(catalogEl => {
          if (!catalogEl.checked) {
            return;
          }
          const rawValue = catalogEl.value
            || catalogEl.dataset.catalogUid
            || catalogEl.dataset.catalogSlug
            || catalogEl.dataset.catalogSort
            || '';
          const normalized = String(rawValue).trim();
          if (normalized && !catalogs.includes(normalized)) {
            catalogs.push(normalized);
          }
        });
        entry.options = { catalogs };
      }
      const titleValue = readDashboardModuleTitle(item, id);
      if (titleValue !== null) {
        if (!entry.options) {
          entry.options = {};
        }
        entry.options.title = titleValue;
      }
      modules.push(entry);
    });
    return modules;
  }

  function cloneDashboardModules(modules) {
    if (!Array.isArray(modules)) {
      return [];
    }
    return modules.map(entry => {
      if (!entry || typeof entry !== 'object') {
        return {};
      }
      const clone = { ...entry };
      if (entry.options && typeof entry.options === 'object') {
        clone.options = { ...entry.options };
      }
      return clone;
    });
  }

  function getVariantModulesForRender(variant) {
    if (variant === 'sponsor' && dashboardSponsorModulesInherited) {
      return cloneDashboardModules(dashboardModulesState.public);
    }
    const state = dashboardModulesState[variant];
    return cloneDashboardModules(Array.isArray(state) ? state : []);
  }

  function storeDashboardModules(variant, modules, mark = false, options = {}) {
    const input = dashboardModuleInputs[variant];
    if (variant === 'sponsor' && options.inherited) {
      dashboardModulesState.sponsor = null;
      dashboardSponsorModulesInherited = true;
      if (input) {
        input.value = '';
      }
    } else {
      const normalized = cloneDashboardModules(Array.isArray(modules) ? modules : []);
      dashboardModulesState[variant] = normalized;
      if (variant === 'sponsor') {
        dashboardSponsorModulesInherited = false;
      }
      if (input) {
        try {
          input.value = JSON.stringify(normalized);
        } catch (err) {
          input.value = '[]';
        }
      }
    }
    if (mark) {
      queueCfgSave();
    }
  }

  function renderDashboardModulesList(modules) {
    if (!dashboardModulesList) {
      return;
    }
    const configured = Array.isArray(modules) && modules.length ? modules : DASHBOARD_DEFAULT_MODULES;
    const map = new Map();
    dashboardModulesList.querySelectorAll('[data-module-id]').forEach(item => {
      map.set(item.dataset.moduleId, item);
    });
    configured.forEach(module => {
      const item = map.get(module.id);
      if (!item) {
        return;
      }
      dashboardModulesList.appendChild(item);
      const toggle = item.querySelector('[data-module-toggle]');
      if (toggle) {
        toggle.checked = !!module.enabled;
      }
      const defaultLayout = DASHBOARD_DEFAULT_MODULE_MAP.get(module.id)?.layout
        || item.querySelector('[data-module-layout]')?.dataset.defaultLayout
        || 'auto';
      const layoutValue = DASHBOARD_LAYOUT_OPTIONS.includes(module.layout)
        ? module.layout
        : defaultLayout;
      const layoutField = item.querySelector('[data-module-layout]');
      if (layoutField) {
        layoutField.value = layoutValue;
      }
      if (module.id === 'rankings' || module.id === 'results') {
        applyDashboardResultsOptions(item, module.id, module.options || {});
      } else if (module.id === 'pointsLeader') {
        applyDashboardPointsLeaderOptions(item, module.options || {});
      }
      applyDashboardModuleTitle(item, module.id, module.options || {});
    });
    DASHBOARD_DEFAULT_MODULES.forEach(module => {
      if (configured.some(entry => entry.id === module.id)) {
        return;
      }
      const item = map.get(module.id);
      if (!item) {
        return;
      }
      dashboardModulesList.appendChild(item);
      const toggle = item.querySelector('[data-module-toggle]');
      if (toggle) {
        toggle.checked = !!module.enabled;
      }
      const defaultLayout = DASHBOARD_DEFAULT_MODULE_MAP.get(module.id)?.layout
        || item.querySelector('[data-module-layout]')?.dataset.defaultLayout
        || 'auto';
      const layoutField = item.querySelector('[data-module-layout]');
      if (layoutField) {
        layoutField.value = defaultLayout;
      }
      if (module.id === 'rankings' || module.id === 'results') {
        applyDashboardResultsOptions(item, module.id, module.options || {});
      } else if (module.id === 'pointsLeader') {
        applyDashboardPointsLeaderOptions(item, module.options || {});
      }
      applyDashboardModuleTitle(item, module.id, module.options || {});
    });
  }

  function applyDashboardModules(modules, variant = activeDashboardVariant, options = {}) {
    if (variant === 'sponsor' && options.inherited) {
      storeDashboardModules('sponsor', null, false, { inherited: true });
    } else {
      storeDashboardModules(variant, modules, false);
    }
    if (variant === activeDashboardVariant) {
      const renderModules = getVariantModulesForRender(variant);
      renderDashboardModulesList(renderModules);
      loadDashboardQrCatalogOptions(getDashboardQrSelection(renderModules));
    }
  }

  function updateDashboardModules(mark = false) {
    if (!dashboardModulesList) {
      if (mark) {
        queueCfgSave();
      }
      return;
    }
    const modules = readDashboardModules();
    storeDashboardModules(activeDashboardVariant, modules, mark);
  }

  function updateDashboardVariantButtons() {
    if (dashboardVariantButtons.length === 0) {
      return;
    }
    dashboardVariantButtons.forEach(btn => {
      const target = btn.dataset.dashboardVariant === 'sponsor' ? 'sponsor' : 'public';
      const isActive = target === activeDashboardVariant;
      btn.classList.toggle('uk-active', isActive);
      btn.classList.toggle('uk-button-primary', isActive);
    });
  }

  function setActiveDashboardVariant(nextVariant) {
    const normalized = nextVariant === 'sponsor' ? 'sponsor' : 'public';
    if (!dashboardModulesList) {
      activeDashboardVariant = normalized;
      updateDashboardVariantButtons();
      return;
    }
    if (normalized === activeDashboardVariant) {
      updateDashboardVariantButtons();
      return;
    }
    storeDashboardModules(activeDashboardVariant, readDashboardModules());
    activeDashboardVariant = normalized;
    updateDashboardVariantButtons();
    const renderModules = getVariantModulesForRender(activeDashboardVariant);
    renderDashboardModulesList(renderModules);
    loadDashboardQrCatalogOptions(getDashboardQrSelection(renderModules));
  }

  function buildDashboardShareLink(variant) {
    const slug = currentEventSlug || '';
    const token = variant === 'sponsor' ? dashboardSponsorToken : dashboardPublicToken;
    if (!slug || !token) {
      return '';
    }
    const path = `/event/${encodeURIComponent(slug)}/dashboard/${encodeURIComponent(token)}`;
    const url = new URL(withBase(path), window.location.origin);
    if (variant === 'sponsor') {
      url.searchParams.set('variant', 'sponsor');
    }
    return url.toString();
  }

  function updateDashboardShareLinks() {
    if (dashboardShareInputs.public) {
      dashboardShareInputs.public.value = buildDashboardShareLink('public');
    }
    if (dashboardShareInputs.sponsor) {
      dashboardShareInputs.sponsor.value = buildDashboardShareLink('sponsor');
    }
  }

  // Füllt das Formular mit den Werten aus einem Konfigurationsobjekt
  function renderCfg(data) {
    if (cfgFields.logoPreview) {
      cfgFields.logoPreview.src = data.logoPath ? data.logoPath + '?' + Date.now() : '';
    }
    cfgFields.pageTitle.value = data.pageTitle || '';
    cfgFields.backgroundColor.value = data.backgroundColor || '';
    cfgFields.buttonColor.value = data.buttonColor || '';
    if (cfgFields.startTheme) {
      const normalizedTheme = (data.startTheme || '').toLowerCase();
      cfgFields.startTheme.value = normalizedTheme === 'dark' ? 'dark' : 'light';
    }
    cfgFields.checkAnswerButton.checked = data.CheckAnswerButton !== 'no';
    cfgFields.qrUser.checked = !!data.QRUser;
    if (cfgFields.randomNames) {
      cfgFields.randomNames.checked = data.randomNames !== false;
    }
    if (cfgFields.randomNameStrategy) {
      const rawStrategy = data.randomNameStrategy ?? data.random_name_strategy ?? '';
      const normalizedStrategy = typeof rawStrategy === 'string' || typeof rawStrategy === 'number'
        ? String(rawStrategy).trim().toLowerCase()
        : '';
      cfgFields.randomNameStrategy.value = ['ai', 'lexicon'].includes(normalizedStrategy)
        ? normalizedStrategy
        : 'ai';
    }
    if (cfgFields.randomNameLocale) {
      const rawLocale = data.randomNameLocale ?? data.random_name_locale ?? '';
      if (rawLocale === null || rawLocale === undefined) {
        cfgFields.randomNameLocale.value = '';
      } else if (typeof rawLocale === 'string') {
        cfgFields.randomNameLocale.value = rawLocale.trim();
      } else if (typeof rawLocale === 'number') {
        cfgFields.randomNameLocale.value = String(rawLocale);
      } else {
        cfgFields.randomNameLocale.value = '';
      }
    }
    if (Array.isArray(cfgFields.randomNameDomains)) {
      const domains = Array.isArray(data.randomNameDomains) ? data.randomNameDomains : [];
      formUtils.checkBoxes(cfgFields.randomNameDomains, domains);
    }
    if (Array.isArray(cfgFields.randomNameTones)) {
      const tones = Array.isArray(data.randomNameTones) ? data.randomNameTones : [];
      formUtils.checkBoxes(cfgFields.randomNameTones, tones);
    }
    if (cfgFields.randomNameBuffer) {
      const rawBuffer = data.randomNameBuffer ?? '';
      if (rawBuffer === null || rawBuffer === undefined || rawBuffer === '') {
        cfgFields.randomNameBuffer.value = '';
      } else {
        const parsedBuffer = Number.parseInt(rawBuffer, 10);
        cfgFields.randomNameBuffer.value = Number.isNaN(parsedBuffer)
          ? ''
          : String(parsedBuffer);
      }
    }
    syncRandomNameOptionsState();
    refreshRandomNameInventory();
    if (cfgFields.shuffleQuestions) {
      cfgFields.shuffleQuestions.checked = data.shuffleQuestions !== false;
    }
    if (cfgFields.teamRestrict) {
      cfgFields.teamRestrict.checked = !!data.QRRestrict;
    }
    if (cfgFields.competitionMode) {
      cfgFields.competitionMode.checked = !!data.competitionMode;
    }
    if (cfgFields.teamResults) {
      cfgFields.teamResults.checked = data.teamResults !== false;
    }
    if (cfgFields.photoUpload) {
      cfgFields.photoUpload.checked = data.photoUpload !== false;
    }
    if (cfgFields.countdownEnabled) {
      const rawCountdownEnabled = data.countdownEnabled ?? data.countdown_enabled ?? false;
      cfgFields.countdownEnabled.checked = rawCountdownEnabled === true
        || rawCountdownEnabled === '1'
        || rawCountdownEnabled === 1
        || String(rawCountdownEnabled).toLowerCase() === 'true';
    }
    if (cfgFields.countdown) {
      const rawCountdown = data.countdown ?? data.defaultCountdown ?? '';
      if (rawCountdown === null || rawCountdown === undefined || rawCountdown === '') {
        cfgFields.countdown.value = '';
      } else {
        const parsedCountdown = Number.parseInt(rawCountdown, 10);
        cfgFields.countdown.value = Number.isNaN(parsedCountdown)
          ? ''
          : String(Math.max(parsedCountdown, 0));
      }
    }
    if (cfgFields.puzzleEnabled) {
      cfgFields.puzzleEnabled.checked = data.puzzleWordEnabled !== false;
    }
    if (cfgFields.puzzleWord) {
      cfgFields.puzzleWord.value = data.puzzleWord || '';
    }
    if (cfgFields.registrationEnabled) {
      cfgFields.registrationEnabled.checked = settingsInitial.registration_enabled === '1';
    }
    if (cfgFields.dashboardRefreshInterval) {
      const rawRefresh = data.dashboardRefreshInterval ?? 15;
      const parsedRefresh = Number.parseInt(rawRefresh, 10);
      if (Number.isNaN(parsedRefresh)) {
        cfgFields.dashboardRefreshInterval.value = '15';
      } else {
        const clamped = Math.min(Math.max(parsedRefresh, 5), 300);
        cfgFields.dashboardRefreshInterval.value = String(clamped);
      }
    }
    if (cfgFields.dashboardFixedHeight) {
      const rawHeight = typeof data.dashboardFixedHeight === 'string'
        ? data.dashboardFixedHeight
        : '';
      cfgFields.dashboardFixedHeight.value = rawHeight;
    }
    if (cfgFields.dashboardTheme) {
      const normalizedTheme = typeof data.dashboardTheme === 'string'
        ? data.dashboardTheme.trim().toLowerCase()
        : '';
      cfgFields.dashboardTheme.value = normalizedTheme === 'dark' ? 'dark' : 'light';
    }
    if (cfgFields.dashboardInfoText) {
      cfgFields.dashboardInfoText.value = data.dashboardInfoText || '';
    }
    if (cfgFields.dashboardMediaEmbed) {
      cfgFields.dashboardMediaEmbed.value = data.dashboardMediaEmbed || '';
    }
    if (cfgFields.dashboardShareEnabled) {
      const enabled = data.dashboardShareEnabled === true
        || data.dashboardShareEnabled === '1'
        || String(data.dashboardShareEnabled).toLowerCase() === 'true';
      cfgFields.dashboardShareEnabled.checked = enabled;
    }
    if (cfgFields.dashboardSponsorEnabled) {
      const enabledSponsor = data.dashboardSponsorEnabled === true
        || data.dashboardSponsorEnabled === '1'
        || String(data.dashboardSponsorEnabled).toLowerCase() === 'true';
      cfgFields.dashboardSponsorEnabled.checked = enabledSponsor;
    }
    if (cfgFields.dashboardVisibilityStart) {
      cfgFields.dashboardVisibilityStart.value = data.dashboardVisibilityStart || '';
    }
    if (cfgFields.dashboardVisibilityEnd) {
      cfgFields.dashboardVisibilityEnd.value = data.dashboardVisibilityEnd || '';
    }
    if (dashboardModulesList) {
      activeDashboardVariant = 'public';
      updateDashboardVariantButtons();
      const publicModules = Array.isArray(data.dashboardModules) ? data.dashboardModules : [];
      applyDashboardModules(publicModules, 'public');
      const sponsorModulesRaw = data.dashboardSponsorModules;
      const sponsorInherited = data.dashboardSponsorModulesInherited === true
        || !Array.isArray(sponsorModulesRaw);
      if (sponsorInherited) {
        applyDashboardModules([], 'sponsor', { inherited: true });
      } else {
        applyDashboardModules(sponsorModulesRaw, 'sponsor');
      }
    }
    dashboardPublicToken = data.dashboardShareToken || '';
    dashboardSponsorToken = data.dashboardSponsorToken || '';
    updateDashboardShareLinks();
    puzzleFeedback = data.puzzleFeedback || '';
    updatePuzzleFeedbackUI();
    inviteText = data.inviteText || '';
    updateInviteTextUI();
    if (cfgFields.puzzleWrap) {
      cfgFields.puzzleWrap.style.display = cfgFields.puzzleEnabled.checked ? '' : 'none';
    }
  }
  renderCfg(cfgInitial);
  renderRagChatSettings();

  if (domainStartPageTable) {
    const tbody = domainStartPageTable.querySelector('tbody');
    const columnCount = domainStartPageTable.querySelectorAll('thead th').length || 5;
    const messages = {
      loading: domainStartPageTable.dataset.loading || '',
      empty: domainStartPageTable.dataset.empty || '',
      error: domainStartPageTable.dataset.error || transDomainStartPageError
    };
    const domainNamespace = resolveNamespaceQuery();
    const domainStartPageEndpoint = withProjectNamespace('/admin/domain-start-pages', domainNamespace);
    const domainStartPageCertificateEndpoint = withProjectNamespace(
      '/admin/domain-start-pages/certificate',
      domainNamespace
    );
    const sslActionLabel = window.actionDomainIssueSsl || 'Request certificate';
    const sslSuccessMessage = window.transDomainSslIssued || transDomainStartPageSaved;
    const sslErrorMessage = window.transDomainSslError || transDomainStartPageError;
    const marketingDomainForm = managementSection?.querySelector('#marketingDomainForm') || null;
    const marketingDomainHost = managementSection?.querySelector('#marketingDomainHost') || null;
    const marketingDomainLabel = managementSection?.querySelector('#marketingDomainLabel') || null;
    const marketingDomainError = managementSection?.querySelector('#marketingDomainFormError') || null;
    const marketingMessages = {
      invalid: marketingDomainForm?.dataset.invalid || window.transMarketingDomainInvalid || transDomainContactTemplateInvalidDomain,
      error: marketingDomainForm?.dataset.error || window.transMarketingDomainError || transDomainStartPageError,
      success: marketingDomainForm?.dataset.success || window.transMarketingDomainCreated || transDomainStartPageSaved,
    };

    const marketingDomainPattern = /^(?=.{1,255}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i;

    const toggleMarketingFormDisabled = disabled => {
      if (!marketingDomainForm) {
        return;
      }
      Array.from(marketingDomainForm.elements || []).forEach(el => {
        if (el && typeof el === 'object' && 'disabled' in el) {
          el.disabled = disabled;
        }
      });
    };

    const setMarketingFormError = message => {
      if (!marketingDomainError) {
        return;
      }
      if (message) {
        marketingDomainError.textContent = message;
        marketingDomainError.hidden = false;
      } else {
        marketingDomainError.textContent = '';
        marketingDomainError.hidden = true;
      }
    };

    const validateMarketingDomain = value => {
      const trimmed = typeof value === 'string' ? value.trim() : '';
      return trimmed !== '' && marketingDomainPattern.test(trimmed);
    };

    if (marketingDomainHost) {
      marketingDomainHost.addEventListener('input', () => {
        marketingDomainHost.classList.remove('uk-form-danger');
        setMarketingFormError('');
      });
    }

    if (marketingDomainForm && marketingDomainHost) {
      marketingDomainForm.addEventListener('submit', event => {
        event.preventDefault();
        const hostValue = marketingDomainHost.value.trim();
        const labelValue = marketingDomainLabel ? marketingDomainLabel.value.trim() : '';

        if (!validateMarketingDomain(hostValue)) {
          marketingDomainHost.classList.add('uk-form-danger');
          setMarketingFormError(marketingMessages.invalid);
          notify(marketingMessages.invalid, 'warning');
          marketingDomainHost.focus();
          return;
        }

        toggleMarketingFormDisabled(true);
        setMarketingFormError('');

        apiFetch('/admin/marketing-domains', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            domain: hostValue,
            label: labelValue !== '' ? labelValue : null,
          })
        })
          .then(res => {
            return res
              .json()
              .catch(() => ({}))
              .then(data => {
                if (!res.ok) {
                  const message = data?.error || marketingMessages.error;
                  throw new Error(message);
                }
                return data;
              });
          })
          .then(() => {
            marketingDomainForm.reset();
            if (marketingDomainHost) {
              marketingDomainHost.classList.remove('uk-form-danger');
            }
            notify(marketingMessages.success, 'success');
            if (typeof reloadDomainStartPages === 'function') {
              reloadDomainStartPages();
            }
          })
          .catch(err => {
            const message = err?.message || marketingMessages.error;
            setMarketingFormError(message);
            notify(message, 'danger');
          })
          .finally(() => {
            toggleMarketingFormDisabled(false);
          });
      });
    }

    const marketingDomainReconcileButton = managementSection?.querySelector('#marketingDomainReconcile') || null;
    const marketingDomainReconcileMessages = {
      provisioned: window.transMarketingDomainReconcileProvisioned || 'Certificates checked: {count} domains.',
      resolved: window.transMarketingDomainReconcileResolved || 'Resolved marketing domains ({count}): {domains}',
      unresolved: window.transMarketingDomainReconcileUnresolved || 'Unresolved marketing domains ({count}): {domains}',
      error: window.transMarketingDomainReconcileError || transDomainStartPageError,
    };

    const normalizeDomainList = list => {
      if (!Array.isArray(list)) {
        return [];
      }
      return list
        .map(value => (value === null || value === undefined ? '' : String(value)).trim())
        .filter(value => value !== '');
    };

    const setReconcileButtonState = isBusy => {
      if (!marketingDomainReconcileButton) {
        return;
      }
      marketingDomainReconcileButton.disabled = isBusy;
      marketingDomainReconcileButton.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    };

    if (marketingDomainReconcileButton) {
      marketingDomainReconcileButton.addEventListener('click', () => {
        setReconcileButtonState(true);
        apiFetch('/admin/marketing-domains/reconcile', { method: 'POST' })
          .then(res => {
            return res
              .json()
              .catch(() => ({}))
              .then(data => {
                if (!res.ok) {
                  const message = data?.error || marketingDomainReconcileMessages.error;
                  throw new Error(message);
                }
                return data;
              });
          })
          .then(data => {
            const provisioned = normalizeDomainList(data?.provisioned);
            const resolved = normalizeDomainList(data?.resolved_marketing_domains);
            const unresolved = normalizeDomainList(data?.unresolved_marketing_domains);
            const provisionedMessage = formatTemplate(marketingDomainReconcileMessages.provisioned, {
              count: provisioned.length,
              domains: provisioned.join(', ')
            });
            const resolvedMessage = formatTemplate(marketingDomainReconcileMessages.resolved, {
              count: resolved.length,
              domains: resolved.join(', ')
            });
            const summaryMessage = [provisionedMessage, resolvedMessage].filter(Boolean).join(' ');
            if (summaryMessage) {
              notify(summaryMessage, 'success');
            }
            if (unresolved.length) {
              const unresolvedMessage = formatTemplate(marketingDomainReconcileMessages.unresolved, {
                count: unresolved.length,
                domains: unresolved.join(', ')
              });
              if (unresolvedMessage) {
                notify(unresolvedMessage, 'warning');
              }
            }
            if (typeof reloadDomainStartPages === 'function') {
              reloadDomainStartPages();
            }
          })
          .catch(err => {
            const message = err?.message || marketingDomainReconcileMessages.error;
            notify(message, 'danger');
          })
          .finally(() => {
            setReconcileButtonState(false);
          });
      });
    }

    const renderMessageRow = message => {
      if (!tbody) return;
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = columnCount;
      td.textContent = message;
      tr.appendChild(td);
      tbody.appendChild(tr);
    };

    const renderDomainTable = () => {
      if (!tbody) {
        return;
      }
      tbody.innerHTML = '';
      if (!domainStartPageData.length) {
        if (messages.empty) {
          renderMessageRow(messages.empty);
        }
        return;
      }

      domainStartPageData.forEach(item => {
        const tr = document.createElement('tr');

        const domainCell = document.createElement('td');
        domainCell.textContent = item.domain;
        tr.appendChild(domainCell);

        const selectCell = document.createElement('td');
        const select = document.createElement('select');
        select.className = 'uk-select';
        if (!Object.prototype.hasOwnProperty.call(domainStartPageOptions, item.start_page)) {
          ensureDomainStartPageOption(item.start_page, item.start_page);
        }
        getDomainStartPageOptionEntries().forEach(([value, label]) => {
          const option = document.createElement('option');
          option.value = value;
          option.textContent = label || value;
          select.appendChild(option);
        });
        select.value = item.start_page;
        selectCell.appendChild(select);
        tr.appendChild(selectCell);

        const emailCell = document.createElement('td');
        const emailInput = document.createElement('input');
        emailInput.type = 'email';
        emailInput.className = 'uk-input';
        emailInput.value = typeof item.email === 'string' ? item.email : '';
        emailCell.appendChild(emailInput);
        tr.appendChild(emailCell);

        const smtpCell = document.createElement('td');
        const smtpSummary = document.createElement('div');
        smtpSummary.className = 'uk-text-meta';
        smtpSummary.textContent = describeSmtpConfig(item);
        smtpCell.appendChild(smtpSummary);
        const smtpButton = document.createElement('button');
        smtpButton.type = 'button';
        smtpButton.className = 'uk-button uk-button-default uk-button-small uk-margin-small-top';
        smtpButton.textContent = transDomainSmtpTitle;
        smtpButton.addEventListener('click', () => openSmtpEditor(item));
        smtpCell.appendChild(smtpButton);
        tr.appendChild(smtpCell);

        const templateCell = document.createElement('td');
        templateCell.className = 'uk-table-shrink';
        const templateButton = document.createElement('button');
        templateButton.type = 'button';
        templateButton.className = 'uk-button uk-button-default uk-button-small';
        templateButton.textContent = transDomainContactTemplateEdit;
        templateButton.addEventListener('click', () => openContactTemplateEditor(item));
        templateCell.appendChild(templateButton);
        tr.appendChild(templateCell);

        const sslCell = document.createElement('td');
        sslCell.className = 'uk-table-shrink';
        const sslButton = document.createElement('button');
        sslButton.type = 'button';
        sslButton.className = 'uk-button uk-button-default uk-button-small';
        sslButton.textContent = sslActionLabel;
        sslButton.addEventListener('click', () => {
          sslButton.disabled = true;
          apiFetch(domainStartPageCertificateEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ domain: item.domain })
          })
            .then(res => {
              if (!res.ok) {
                return res.json().catch(() => ({})).then(data => {
                  throw new Error(data?.error || sslErrorMessage);
                });
              }
              return res.json();
            })
            .then(() => {
              notify(sslSuccessMessage, 'success');
            })
            .catch(err => {
              notify(err?.message || sslErrorMessage, 'danger');
            })
            .finally(() => {
              sslButton.disabled = false;
            });
        });
        sslCell.appendChild(sslButton);
        tr.appendChild(sslCell);

        const typeCell = document.createElement('td');
        typeCell.textContent = domainStartPageTypeLabels[item.type] || item.type;
        tr.appendChild(typeCell);

        const actionsCell = document.createElement('td');
        actionsCell.className = 'uk-table-shrink uk-text-center';
        if (item.type === 'custom') {
          const deleteButton = document.createElement('button');
          deleteButton.type = 'button';
          deleteButton.className = 'uk-button uk-button-danger uk-button-small';
          deleteButton.textContent = window.transDelete || 'Löschen';
          deleteButton.addEventListener('click', () => {
            if (!window.confirm(transDomainStartPageDeleteConfirm)) {
              return;
            }
            deleteButton.disabled = true;
            const targetDomain = item.normalized || item.domain;
            apiFetch(domainStartPageEndpoint, {
              method: 'DELETE',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ domain: targetDomain })
            })
              .then(res => {
                return res
                  .json()
                  .catch(() => ({}))
                  .then(data => {
                    if (!res.ok) {
                      throw new Error(data?.error || transDomainStartPageDeleteError);
                    }
                    return data;
                  });
              })
              .then(() => {
                notify(transDomainStartPageDeleted, 'success');
                if (typeof reloadDomainStartPages === 'function') {
                  reloadDomainStartPages();
                }
              })
              .catch(err => {
                notify(err?.message || transDomainStartPageDeleteError, 'danger');
              })
              .finally(() => {
                deleteButton.disabled = false;
              });
          });
          actionsCell.appendChild(deleteButton);
        }
        tr.appendChild(actionsCell);

        select.addEventListener('change', () => {
          const previous = item.start_page;
          const newValue = select.value;
          select.disabled = true;
          const payload = buildDomainPayload(item, {
            start_page: newValue,
            email: emailInput.value.trim(),
          });
          domainStartPageUpdater(payload)
            .then(data => {
              const config = data?.config || null;
              if (config) {
                applyDomainConfig(item, config);
              } else {
                item.start_page = newValue;
                item.email = emailInput.value.trim();
              }
              select.value = item.start_page;
              emailInput.value = item.email || '';
              smtpSummary.textContent = describeSmtpConfig(item);
              if (item.type === 'main') {
                settingsInitial.home_page = item.start_page;
              }
              notify(transDomainStartPageSaved, 'success');
            })
            .catch(err => {
              select.value = previous;
              notify(err.message || transDomainStartPageError, 'danger');
            })
            .finally(() => {
              select.disabled = false;
            });
        });

        emailInput.addEventListener('change', () => {
          const previous = typeof item.email === 'string' ? item.email : '';
          const newValue = emailInput.value.trim();
          if (newValue === previous) {
            emailInput.value = previous;
            return;
          }

          emailInput.disabled = true;
          const payload = buildDomainPayload(item, {
            email: newValue,
          });
          domainStartPageUpdater(payload)
            .then(data => {
              const config = data?.config || null;
              if (config) {
                applyDomainConfig(item, config);
              } else {
                item.email = newValue;
              }
              select.value = item.start_page;
              emailInput.value = item.email || '';
              smtpSummary.textContent = describeSmtpConfig(item);
              notify(transDomainStartPageSaved, 'success');
            })
            .catch(err => {
              emailInput.value = previous;
              notify(err.message || transDomainStartPageError, 'danger');
            })
            .finally(() => {
              emailInput.disabled = false;
            });
        });

        tbody.appendChild(tr);
      });
    };

    const toggleSmtpFormDisabled = disabled => {
      if (!domainSmtpForm) {
        return;
      }
      const elements = domainSmtpForm.querySelectorAll('input, select, button');
      elements.forEach(el => {
        if (el.classList.contains('uk-modal-close')) {
          return;
        }
        el.disabled = disabled;
      });
    };

    if (domainSmtpForm && domainSmtpFields) {
      domainSmtpForm.addEventListener('submit', event => {
        event.preventDefault();
        if (!currentSmtpItem) {
          notify(transDomainSmtpInvalid, 'danger');
          return;
        }

        const hostValue = domainSmtpFields.host?.value?.trim() || '';
        const userValue = domainSmtpFields.user?.value?.trim() || '';
        const encryptionValue = domainSmtpFields.encryption?.value || '';
        const dsnValue = domainSmtpFields.dsn?.value?.trim() || '';
        const portInput = domainSmtpFields.port?.value ?? '';
        const clearPass = !!domainSmtpFields.clear?.checked;
        const passInput = domainSmtpFields.pass?.value || '';

        let portPayload = '';
        if (typeof portInput === 'string') {
          const trimmed = portInput.trim();
          if (trimmed !== '') {
            const parsed = Number.parseInt(trimmed, 10);
            portPayload = Number.isNaN(parsed) ? trimmed : parsed;
          }
        } else if (typeof portInput === 'number') {
          portPayload = portInput;
        }

        let passPayload = secretPlaceholder;
        if (clearPass) {
          passPayload = '';
        } else if (passInput !== '') {
          passPayload = passInput;
        }

        const payload = buildDomainPayload(currentSmtpItem, {
          smtp_host: hostValue,
          smtp_user: userValue,
          smtp_port: portPayload,
          smtp_encryption: encryptionValue,
          smtp_dsn: dsnValue,
          smtp_pass: passPayload,
        });

        toggleSmtpFormDisabled(true);

        domainStartPageUpdater(payload)
          .then(data => {
            const config = data?.config || null;
            if (config) {
              applyDomainConfig(currentSmtpItem, config);
            } else {
              currentSmtpItem.smtp_host = hostValue;
              currentSmtpItem.smtp_user = userValue;
              currentSmtpItem.smtp_port = normalizeSmtpPortValue(portPayload);
              currentSmtpItem.smtp_encryption = encryptionValue;
              currentSmtpItem.smtp_dsn = dsnValue;
              if (clearPass) {
                currentSmtpItem.has_smtp_pass = false;
              } else if (passPayload !== secretPlaceholder) {
                currentSmtpItem.has_smtp_pass = passPayload !== '';
              }
            }
            renderDomainTable();
            notify(transDomainSmtpSaved, 'success');
            if (domainSmtpFields.pass) {
              domainSmtpFields.pass.value = '';
            }
            if (domainSmtpFields.clear) {
              domainSmtpFields.clear.checked = false;
            }
            if (domainSmtpModal) {
              domainSmtpModal.hide();
            }
          })
          .catch(err => {
            notify(err.message || transDomainSmtpError, 'danger');
          })
          .finally(() => {
            toggleSmtpFormDisabled(false);
          });
      });
    }

    if (domainSmtpModalEl && window.UIkit && UIkit.util) {
      UIkit.util.on(domainSmtpModalEl, 'hidden', () => {
        currentSmtpItem = null;
        if (domainSmtpFields?.pass) {
          domainSmtpFields.pass.value = '';
        }
        if (domainSmtpFields?.clear) {
          domainSmtpFields.clear.checked = false;
        }
      });
    }

    domainStartPageUpdater = payload => {
      const hasSmtpFields = payload && typeof payload === 'object'
        && (
          Object.prototype.hasOwnProperty.call(payload, 'smtp_host')
          || Object.prototype.hasOwnProperty.call(payload, 'smtp_user')
          || Object.prototype.hasOwnProperty.call(payload, 'smtp_port')
          || Object.prototype.hasOwnProperty.call(payload, 'smtp_encryption')
          || Object.prototype.hasOwnProperty.call(payload, 'smtp_dsn')
          || Object.prototype.hasOwnProperty.call(payload, 'smtp_pass')
        );
      return apiFetch(domainStartPageEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(res => {
        return res
          .json()
          .catch(() => ({}))
          .then(data => {
            if (!res.ok) {
              const fallback = res.status === 422
                ? (hasSmtpFields ? transDomainSmtpInvalid : transDomainStartPageInvalidEmail)
                : transDomainStartPageError;
              throw new Error(data.error || fallback);
            }
            mergeDomainStartPageOptions(data?.options || {});
            return data;
          });
      });
    };

    const loadDomainStartPages = () => {
      if (!tbody) {
        return;
      }
      tbody.innerHTML = '';
      if (messages.loading) {
        renderMessageRow(messages.loading);
      }

      apiFetch(domainStartPageEndpoint)
        .then(res => {
          if (!res.ok) {
            return res.json().catch(() => ({})).then(data => {
              throw new Error(data.error || messages.error);
            });
          }
          return res.json();
        })
        .then(data => {
          mergeDomainStartPageOptions(data?.options || {});
          domainStartPageData = Array.isArray(data?.domains) ? data.domains : [];
          domainStartPageData = domainStartPageData.map(item => ({
            ...item,
            email: typeof item.email === 'string' ? item.email : '',
            smtp_host: typeof item.smtp_host === 'string' ? item.smtp_host : '',
            smtp_user: typeof item.smtp_user === 'string' ? item.smtp_user : '',
            smtp_port: normalizeSmtpPortValue(item.smtp_port ?? ''),
            smtp_encryption: typeof item.smtp_encryption === 'string' ? item.smtp_encryption : '',
            smtp_dsn: typeof item.smtp_dsn === 'string' ? item.smtp_dsn : '',
            has_smtp_pass: Boolean(item.has_smtp_pass),
          }));
          mainDomainNormalized = typeof data?.main === 'string' ? data.main : '';
          const mainEntry = domainStartPageData.find(it => it.type === 'main');
          if (mainEntry) {
            settingsInitial.home_page = mainEntry.start_page;
          }
          tbody.innerHTML = '';
          renderDomainTable();
        })
        .catch(err => {
          tbody.innerHTML = '';
          renderMessageRow(err.message || messages.error);
        });
    };

    reloadDomainStartPages = loadDomainStartPages;
    loadDomainStartPages();
  }

  const domainChatContainer = document.querySelector('[data-domain-chat]');
  if (domainChatContainer) {
    const domainChatTranslations = window.domainChatTranslations || {};
    const domainSelect = domainChatContainer.querySelector('[data-domain-chat-select]');
    const uploadForm = domainChatContainer.querySelector('[data-domain-chat-form]');
    const uploadInput = domainChatContainer.querySelector('[data-domain-chat-upload]');
    const submitButton = domainChatContainer.querySelector('[data-domain-chat-submit]');
    const rebuildButton = domainChatContainer.querySelector('[data-domain-chat-rebuild]');
    const downloadButton = domainChatContainer.querySelector('[data-domain-chat-download]');
    const tableBody = domainChatContainer.querySelector('[data-domain-chat-body]');
    const statusBox = domainChatContainer.querySelector('[data-domain-chat-status]');
    const wikiContainer = domainChatContainer.querySelector('[data-domain-chat-wiki]');
    const wikiList = domainChatContainer.querySelector('[data-domain-chat-wiki-list]');
    const wikiMessage = domainChatContainer.querySelector('[data-domain-chat-wiki-message]');
    const wikiSaveButton = domainChatContainer.querySelector('[data-domain-chat-wiki-save]');
    const wikiDescription = domainChatContainer.querySelector('[data-domain-chat-wiki-description]');
    const maxUploadSize = Number(window.domainChatMaxSize || 0);
    let currentDomain = domainSelect?.value?.trim() || '';
    let wikiState = {
      enabled: false,
      available: false,
      articles: [],
      pageSlug: null,
    };

    const formatBytes = bytes => {
      const value = Number(bytes);
      if (!Number.isFinite(value) || value <= 0) {
        return '0 B';
      }
      const units = ['B', 'KB', 'MB', 'GB'];
      let result = value;
      let unitIndex = 0;
      while (result >= 1024 && unitIndex < units.length - 1) {
        result /= 1024;
        unitIndex += 1;
      }
      const decimals = result >= 10 ? 0 : 1;
      return `${result.toFixed(decimals)} ${units[unitIndex]}`;
    };

    const formatDate = value => {
      if (!value) {
        return '';
      }
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) {
        return value;
      }
      return date.toLocaleString();
    };

    const showStatus = (message, status = 'primary', details = '') => {
      if (!statusBox) {
        return;
      }
      statusBox.innerHTML = '';
      if (!message) {
        statusBox.hidden = true;
        return;
      }
      const paragraph = document.createElement('p');
      paragraph.textContent = message;
      paragraph.className = status === 'danger' ? 'uk-text-danger' : 'uk-text-success';
      statusBox.appendChild(paragraph);
      if (details) {
        const pre = document.createElement('pre');
        pre.className = 'uk-margin-small-top uk-text-small';
        pre.textContent = details;
        statusBox.appendChild(pre);
      }
      statusBox.hidden = false;
    };

    const setWikiMessage = (message, variant = 'warning') => {
      if (!wikiMessage) {
        return;
      }
      if (!message) {
        wikiMessage.textContent = '';
        wikiMessage.hidden = true;
        return;
      }
      let className = 'uk-alert uk-alert-warning uk-margin-small';
      if (variant === 'success') {
        className = 'uk-alert uk-alert-success uk-margin-small';
      } else if (variant === 'danger') {
        className = 'uk-alert uk-alert-danger uk-margin-small';
      }
      wikiMessage.className = className;
      wikiMessage.textContent = message;
      wikiMessage.hidden = false;
    };

    const updateWikiSaveState = () => {
      if (!wikiSaveButton) {
        return;
      }
      const canEdit = wikiState.enabled && wikiState.available && wikiState.articles.length > 0;
      if (!canEdit) {
        wikiSaveButton.disabled = true;
        wikiSaveButton.hidden = true;
        return;
      }
      const dirty = wikiState.articles.some(article => article.selected !== article.initialSelected);
      wikiSaveButton.disabled = !dirty;
      wikiSaveButton.hidden = false;
    };

    const renderWikiArticles = wiki => {
      if (!wikiContainer) {
        return;
      }
      if (!wiki) {
        wikiContainer.hidden = false;
        if (wikiDescription) {
          wikiDescription.hidden = true;
        }
        if (wikiList) {
          wikiList.innerHTML = '';
          wikiList.hidden = true;
        }
        if (wikiSaveButton) {
          wikiSaveButton.disabled = true;
          wikiSaveButton.hidden = true;
        }
        setWikiMessage(domainChatTranslations.loading || 'Loading…', 'warning');
        wikiState = {
          enabled: false,
          available: false,
          articles: [],
          pageSlug: null,
        };
        return;
      }

      wikiContainer.hidden = false;
      const enabled = wiki.enabled !== false;
      const available = enabled && wiki.available !== false;
      if (wikiDescription) {
        wikiDescription.hidden = !available;
      }
      const rawArticles = Array.isArray(wiki.articles) ? wiki.articles : [];

      wikiState = {
        enabled,
        available,
        articles: rawArticles.map(article => ({
          id: Number(article.id) || 0,
          title: typeof article.title === 'string' ? article.title : '',
          slug: typeof article.slug === 'string' ? article.slug : '',
          locale: typeof article.locale === 'string' ? article.locale : '',
          excerpt: typeof article.excerpt === 'string' ? article.excerpt : '',
          publishedAt: typeof article.publishedAt === 'string' ? article.publishedAt : '',
          isStartDocument: article.isStartDocument === true,
          selected: article.selected === true,
          initialSelected: article.selected === true,
        })),
        pageSlug: typeof wiki.pageSlug === 'string' ? wiki.pageSlug : null,
      };

      if (!enabled) {
        if (wikiList) {
          wikiList.innerHTML = '';
          wikiList.hidden = true;
        }
        if (wikiSaveButton) {
          wikiSaveButton.disabled = true;
          wikiSaveButton.hidden = true;
        }
        setWikiMessage(domainChatTranslations.wikiUnavailable || domainChatTranslations.error || '', 'warning');
        return;
      }

      if (!available) {
        if (wikiList) {
          wikiList.innerHTML = '';
          wikiList.hidden = true;
        }
        if (wikiSaveButton) {
          wikiSaveButton.disabled = true;
          wikiSaveButton.hidden = true;
        }
        setWikiMessage(domainChatTranslations.wikiUnavailable || '', 'warning');
        return;
      }

      if (wikiState.articles.length === 0) {
        if (wikiList) {
          wikiList.innerHTML = '';
          wikiList.hidden = true;
        }
        if (wikiSaveButton) {
          wikiSaveButton.disabled = true;
          wikiSaveButton.hidden = true;
        }
        setWikiMessage(domainChatTranslations.wikiEmpty || '', 'warning');
        return;
      }

      setWikiMessage('');
      if (wikiList) {
        wikiList.innerHTML = '';
        wikiList.hidden = false;
        wikiState.articles.forEach(article => {
          const item = document.createElement('li');
          const header = document.createElement('div');
          header.className = 'uk-flex uk-flex-middle uk-flex-wrap';

          const checkbox = document.createElement('input');
          checkbox.type = 'checkbox';
          checkbox.className = 'uk-checkbox uk-margin-small-right';
          checkbox.checked = article.selected;
          checkbox.dataset.articleId = String(article.id);
          checkbox.addEventListener('change', () => {
            article.selected = checkbox.checked;
            updateWikiSaveState();
          });
          header.appendChild(checkbox);

          const localeBadge = document.createElement('span');
          localeBadge.className = 'uk-label uk-label-light uk-margin-small-right';
          localeBadge.textContent = (article.locale || '').toUpperCase() || 'DE';
          header.appendChild(localeBadge);

          const titleSpan = document.createElement('span');
          titleSpan.className = 'uk-text-bold';
          titleSpan.textContent = article.title || article.slug || '';
          header.appendChild(titleSpan);

          item.appendChild(header);

          if (article.slug) {
            const slugLine = document.createElement('div');
            slugLine.className = 'uk-text-meta';
            slugLine.textContent = article.slug;
            item.appendChild(slugLine);
          }

          if (article.excerpt) {
            const excerptLine = document.createElement('div');
            excerptLine.className = 'uk-text-meta';
            excerptLine.textContent = article.excerpt;
            item.appendChild(excerptLine);
          }

          wikiList.appendChild(item);
        });
      }

      updateWikiSaveState();
    };

    const parseFileName = header => {
      if (!header) {
        return '';
      }
      const starMatch = header.match(/filename\*=UTF-8''([^;]+)/i);
      if (starMatch && starMatch[1]) {
        try {
          return decodeURIComponent(starMatch[1]);
        } catch (error) {
          return starMatch[1];
        }
      }
      const match = header.match(/filename="?([^";]+)"?/i);
      return match && match[1] ? match[1] : '';
    };

    const renderDocuments = documents => {
      if (!tableBody) {
        return;
      }
      tableBody.innerHTML = '';
      if (!documents.length) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 4;
        cell.textContent = domainChatTranslations.empty || 'Keine Dateien vorhanden';
        row.appendChild(cell);
        tableBody.appendChild(row);
        return;
      }

      documents.forEach(doc => {
        const row = document.createElement('tr');

        const nameCell = document.createElement('td');
        nameCell.textContent = doc.name || doc.filename || '';
        row.appendChild(nameCell);

        const sizeCell = document.createElement('td');
        sizeCell.className = 'uk-text-nowrap';
        sizeCell.textContent = formatBytes(doc.size || 0);
        row.appendChild(sizeCell);

        const updatedCell = document.createElement('td');
        updatedCell.className = 'uk-text-nowrap';
        updatedCell.textContent = formatDate(doc.updated_at || doc.uploaded_at || '');
        row.appendChild(updatedCell);

        const actionsCell = document.createElement('td');
        actionsCell.className = 'uk-text-nowrap';
        const deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.className = 'uk-button uk-button-link uk-text-danger';
        deleteButton.textContent = window.transDelete || 'Delete';
        deleteButton.addEventListener('click', () => {
          if (!doc.id) {
            return;
          }
          const confirmMessage = domainChatTranslations.confirmDelete || window.transDeletePageConfirm || 'Delete document?';
          if (!window.confirm(confirmMessage)) {
            return;
          }
          apiFetch(`/admin/domain-chat/documents/${encodeURIComponent(doc.id)}?domain=${encodeURIComponent(currentDomain)}`, {
            method: 'DELETE'
          })
            .then(res => res.json().catch(() => ({})).then(data => {
              if (!res.ok) {
                throw new Error(data.error || domainChatTranslations.error || 'Delete failed');
              }
              notify(domainChatTranslations.deleted || 'Gelöscht', 'success');
              return loadDocuments(currentDomain);
            }))
            .catch(err => {
              notify(err.message || domainChatTranslations.error || 'Delete failed', 'danger');
            });
        });
        actionsCell.appendChild(deleteButton);
        row.appendChild(actionsCell);

        tableBody.appendChild(row);
      });
    };

    const loadDocuments = domain => {
      if (domain === '') {
        if (wikiContainer) {
          wikiContainer.hidden = true;
        }
        if (tableBody) {
          tableBody.innerHTML = '';
        }

        return Promise.resolve();
      }

      if (wikiContainer) {
        wikiContainer.hidden = false;
        renderWikiArticles(null);
      }

      if (tableBody) {
        tableBody.innerHTML = '';
        const loadingRow = document.createElement('tr');
        const loadingCell = document.createElement('td');
        loadingCell.colSpan = 4;
        loadingCell.textContent = domainChatTranslations.loading || 'Lade …';
        loadingRow.appendChild(loadingCell);
        tableBody.appendChild(loadingRow);
      }

      return apiFetch(`/admin/domain-chat/documents?domain=${encodeURIComponent(domain)}`)
        .then(res => res.json().catch(() => ({})).then(data => {
          if (!res.ok) {
            throw new Error(data.error || domainChatTranslations.error || 'Request failed');
          }
          currentDomain = typeof data.domain === 'string' && data.domain !== '' ? data.domain : domain;
          const docs = Array.isArray(data.documents) ? data.documents : [];
          renderDocuments(docs);
          if (wikiContainer) {
            renderWikiArticles(data.wiki ?? { enabled: true, available: false, articles: [] });
          }
          showStatus('', 'primary');
        }))
        .catch(err => {
          if (tableBody) {
            tableBody.innerHTML = '';
            const errorRow = document.createElement('tr');
            const errorCell = document.createElement('td');
            errorCell.colSpan = 4;
            errorCell.textContent = err.message || domainChatTranslations.error || 'Fehler';
            errorRow.appendChild(errorCell);
            tableBody.appendChild(errorRow);
          }
          if (wikiContainer) {
            renderWikiArticles({ enabled: true, available: false, articles: [] });
            setWikiMessage(err.message || domainChatTranslations.error || 'Fehler', 'danger');
          }
          showStatus(err.message || domainChatTranslations.error || 'Fehler', 'danger');
        });
    };

    if (domainSelect) {
      domainSelect.addEventListener('change', () => {
        currentDomain = domainSelect.value.trim();
        loadDocuments(currentDomain);
      });
    }

    if (uploadForm) {
      uploadForm.addEventListener('submit', event => {
        event.preventDefault();
        const selectedDomain = domainSelect?.value?.trim() || currentDomain;
        const files = uploadInput?.files;
        const file = files && files.length ? files[0] : null;
        if (!selectedDomain || !file) {
          notify(domainChatTranslations.error || 'Keine Datei ausgewählt', 'danger');
          return;
        }
        if (maxUploadSize > 0 && file.size > maxUploadSize) {
          const sizeMb = (maxUploadSize / 1048576).toFixed(1);
          notify(domainChatTranslations.error || `Datei zu groß (max. ${sizeMb} MB)`, 'danger');
          return;
        }

        const formData = new FormData();
        formData.append('domain', selectedDomain);
        formData.append('document', file);

        if (submitButton) {
          submitButton.disabled = true;
        }
        if (rebuildButton) {
          rebuildButton.disabled = true;
        }
        if (downloadButton) {
          downloadButton.disabled = true;
        }

        apiFetch('/admin/domain-chat/documents', {
          method: 'POST',
          body: formData,
        })
          .then(res => res.json().catch(() => ({})).then(data => {
            if (!res.ok) {
              throw new Error(data.error || domainChatTranslations.error || 'Upload failed');
            }
            notify(domainChatTranslations.uploaded || 'Dokument gespeichert', 'success');
            if (uploadInput) {
              uploadInput.value = '';
            }
            return loadDocuments(selectedDomain);
          }))
          .catch(err => {
            notify(err.message || domainChatTranslations.error || 'Upload failed', 'danger');
          })
          .finally(() => {
            if (submitButton) {
              submitButton.disabled = false;
            }
            if (rebuildButton) {
              rebuildButton.disabled = false;
            }
            if (downloadButton) {
              downloadButton.disabled = false;
            }
          });
      });
    }

    if (wikiSaveButton) {
      wikiSaveButton.addEventListener('click', () => {
        const selectedDomain = domainSelect?.value?.trim() || currentDomain;
        if (!selectedDomain) {
          notify(domainChatTranslations.error || 'Keine Domain ausgewählt', 'danger');
          return;
        }
        if (!wikiState.enabled || !wikiState.available) {
          notify(domainChatTranslations.wikiUnavailable || domainChatTranslations.error || 'Aktion nicht verfügbar', 'danger');
          return;
        }

        const selectedIds = wikiState.articles
          .filter(article => article.selected)
          .map(article => article.id)
          .filter(id => Number.isInteger(id) && id > 0);

        wikiSaveButton.disabled = true;

        apiFetch('/admin/domain-chat/wiki-selection', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ domain: selectedDomain, articles: selectedIds }),
        })
          .then(res => res.json().catch(() => ({})).then(data => {
            if (!res.ok || data.success !== true) {
              const message = typeof data.error === 'string'
                ? data.error
                : (domainChatTranslations.wikiError || domainChatTranslations.error || 'Speichern fehlgeschlagen');
              throw new Error(message);
            }

            if (wikiContainer) {
              renderWikiArticles(data.wiki ?? { enabled: true, available: false, articles: [] });
            }

            const successMessage = domainChatTranslations.wikiSaved || 'Auswahl gespeichert';
            showStatus(successMessage, 'success');
            notify(successMessage, 'success');
          }))
          .catch(err => {
            const message = err.message || domainChatTranslations.wikiError || domainChatTranslations.error || 'Speichern fehlgeschlagen';
            notify(message, 'danger');
            setWikiMessage(message, 'danger');
          })
          .finally(() => {
            updateWikiSaveState();
          });
      });
    }

    if (rebuildButton) {
      rebuildButton.addEventListener('click', () => {
        const selectedDomain = domainSelect?.value?.trim() || currentDomain;
        if (!selectedDomain) {
          notify(domainChatTranslations.error || 'Keine Domain ausgewählt', 'danger');
          return;
        }
        rebuildButton.disabled = true;
        if (downloadButton) {
          downloadButton.disabled = true;
        }
        apiFetch('/admin/domain-chat/rebuild', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ domain: selectedDomain }),
        })
          .then(res => res.json().catch(() => ({})).then(data => {
            const success = data && data.success === true;
            const status = typeof data.status === 'string' ? data.status : '';
            if (status === 'throttled') {
              const retryAfter = typeof data.retry_after === 'number' ? data.retry_after : null;
              const suffix = retryAfter ? ` (${retryAfter}s)` : '';
              const message = (domainChatTranslations.rebuildThrottled || 'Bitte warten, bevor der Index neu aufgebaut wird.')
                + suffix;
              showStatus(message, 'warning');
              notify(message, 'warning');
              return null;
            }
            if (!res.ok || !success) {
              const errorText = typeof data.error === 'string' ? data.error.trim() : '';
              const stderrText = typeof data.stderr === 'string' ? data.stderr.trim() : '';
              const stdoutText = typeof data.stdout === 'string' ? data.stdout.trim() : '';
              const message = errorText
                || stderrText
                || stdoutText
                || domainChatTranslations.rebuildError
                || 'Rebuild failed';
              const details = (stderrText && stderrText !== message)
                ? stderrText
                : (stdoutText && stdoutText !== message ? stdoutText : '');
              const error = new Error(message);
              if (details) {
                error.details = details;
              }
              throw error;
            }
            if (data.queued === true || status === 'queued') {
              const message = domainChatTranslations.rebuildQueued || 'Index-Aufbau eingeplant';
              showStatus(message, 'success');
              notify(message, 'success');
              return null;
            }
            const message = data.cleared
              ? (domainChatTranslations.rebuildCleared || 'Index zurückgesetzt')
              : (domainChatTranslations.rebuild || 'Index aktualisiert');
            const stdout = typeof data.stdout === 'string' ? data.stdout.trim() : '';
            showStatus(message, 'success', stdout);
            return loadDocuments(selectedDomain);
          }))
          .catch(err => {
            const message = err.message || domainChatTranslations.rebuildError || 'Rebuild failed';
            const details = typeof err.details === 'string' ? err.details : '';
            showStatus(message, 'danger', details);
            notify(message, 'danger');
        })
          .finally(() => {
            rebuildButton.disabled = false;
            if (downloadButton) {
              downloadButton.disabled = false;
            }
          });
      });
    }

    if (downloadButton) {
      downloadButton.addEventListener('click', () => {
        const selectedDomain = domainSelect?.value?.trim() || currentDomain;
        if (!selectedDomain) {
          notify(domainChatTranslations.error || 'Keine Domain ausgewählt', 'danger');
          return;
        }

        downloadButton.disabled = true;

        apiFetch(`/admin/domain-chat/index?domain=${encodeURIComponent(selectedDomain)}`)
          .then(res => {
            if (!res.ok) {
              return res.json().catch(() => ({})).then(data => {
                const serverMessage = typeof data.error === 'string' ? data.error : '';
                const fallbackMissing = domainChatTranslations.downloadMissing || '';
                const fallbackGeneric = domainChatTranslations.downloadError
                  || domainChatTranslations.error
                  || 'Download failed';
                const message = serverMessage === 'index-not-found'
                  ? (fallbackMissing || fallbackGeneric)
                  : (serverMessage || fallbackGeneric);
                throw new Error(message);
              });
            }

            return res.blob().then(blob => ({
              blob,
              disposition: res.headers.get('Content-Disposition') || '',
            }));
          })
          .then(({ blob, disposition }) => {
            if (!(blob instanceof Blob)) {
              throw new Error(domainChatTranslations.downloadError
                || domainChatTranslations.error
                || 'Download failed');
            }

            const objectUrl = URL.createObjectURL(blob);
            const suggestedName = parseFileName(disposition) || (() => {
              const safeDomain = selectedDomain.replace(/[^a-z0-9._-]+/gi, '-');
              return `domain-index-${safeDomain}.json`;
            })();

            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = suggestedName;
            link.rel = 'noopener';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(objectUrl);
          })
          .catch(err => {
            notify(err.message || domainChatTranslations.downloadError || domainChatTranslations.error || 'Download failed', 'danger');
          })
          .finally(() => {
            downloadButton.disabled = false;
          });
      });
    }

    if (currentDomain !== '') {
      loadDocuments(currentDomain);
    } else if (wikiContainer) {
      wikiContainer.hidden = true;
    }
  }
  function collectCfgData() {
    const data = {
      pageTitle: cfgFields.pageTitle?.value || '',
      backgroundColor: cfgFields.backgroundColor?.value || '',
      buttonColor: cfgFields.buttonColor?.value || '',
      CheckAnswerButton: cfgFields.checkAnswerButton?.checked ? 'yes' : 'no',
      QRUser: cfgFields.qrUser?.checked ? '1' : '0'
    };
    if (cfgFields.startTheme) {
      const selectedTheme = (cfgFields.startTheme.value || '').toLowerCase();
      data.startTheme = selectedTheme === 'dark' ? 'dark' : 'light';
    }
    if (cfgFields.randomNames) data.randomNames = cfgFields.randomNames.checked;
    if (cfgFields.randomNameStrategy) {
      const rawStrategy = cfgFields.randomNameStrategy.value;
      const normalizedStrategy = typeof rawStrategy === 'string'
        ? rawStrategy.trim().toLowerCase()
        : '';
      data.randomNameStrategy = ['ai', 'lexicon'].includes(normalizedStrategy)
        ? normalizedStrategy
        : '';
    }
    if (cfgFields.randomNameLocale) {
      const rawLocale = typeof cfgFields.randomNameLocale.value === 'string'
        ? cfgFields.randomNameLocale.value.trim()
        : '';
      data.randomNameLocale = rawLocale;
    }
    if (Array.isArray(cfgFields.randomNameDomains)) {
      data.randomNameDomains = formUtils.readChecked(cfgFields.randomNameDomains);
    }
    if (Array.isArray(cfgFields.randomNameTones)) {
      data.randomNameTones = formUtils.readChecked(cfgFields.randomNameTones);
    }
    if (cfgFields.randomNameBuffer) {
      const rawBuffer = cfgFields.randomNameBuffer.value;
      const trimmedBuffer = rawBuffer == null ? '' : String(rawBuffer).trim();
      if (trimmedBuffer === '') {
        data.randomNameBuffer = '';
        cfgFields.randomNameBuffer.value = '';
      } else {
        const parsedBuffer = Number.parseInt(trimmedBuffer, 10);
        if (Number.isNaN(parsedBuffer)) {
          data.randomNameBuffer = '';
          cfgFields.randomNameBuffer.value = '';
        } else {
          const min = Number.parseInt(cfgFields.randomNameBuffer.min, 10);
          const max = Number.parseInt(cfgFields.randomNameBuffer.max, 10);
          let normalized = parsedBuffer;
          if (!Number.isNaN(min)) {
            normalized = Math.max(normalized, min);
          }
          if (!Number.isNaN(max)) {
            normalized = Math.min(normalized, max);
          }
          data.randomNameBuffer = String(normalized);
          cfgFields.randomNameBuffer.value = String(normalized);
        }
      }
    }
    if (cfgFields.shuffleQuestions) data.shuffleQuestions = cfgFields.shuffleQuestions.checked;
    if (cfgFields.teamRestrict) data.QRRestrict = cfgFields.teamRestrict.checked ? '1' : '0';
    if (cfgFields.competitionMode) data.competitionMode = cfgFields.competitionMode.checked;
    if (cfgFields.teamResults) data.teamResults = cfgFields.teamResults.checked;
    if (cfgFields.photoUpload) data.photoUpload = cfgFields.photoUpload.checked;
    if (cfgFields.countdownEnabled) {
      data.countdownEnabled = cfgFields.countdownEnabled.checked ? '1' : '0';
    }
    if (cfgFields.countdown) {
      const rawValue = cfgFields.countdown.value;
      const trimmed = rawValue == null ? '' : String(rawValue).trim();
      if (trimmed === '') {
        data.countdown = '';
        cfgFields.countdown.value = '';
      } else {
        const parsed = Number.parseInt(trimmed, 10);
        if (Number.isNaN(parsed)) {
          data.countdown = '';
          cfgFields.countdown.value = '';
        } else if (parsed < 0) {
          data.countdown = '0';
          cfgFields.countdown.value = '0';
          if (typeof notify === 'function') {
            notify(transCountdownInvalid, 'warning');
          }
        } else {
          data.countdown = String(parsed);
          cfgFields.countdown.value = String(parsed);
        }
      }
    }
    if (cfgFields.puzzleEnabled) {
      data.puzzleWordEnabled = cfgFields.puzzleEnabled.checked;
      data.puzzleWord = cfgFields.puzzleWord?.value || '';
    }
    if (cfgFields.dashboardRefreshInterval) {
      const rawRefresh = cfgFields.dashboardRefreshInterval.value;
      const parsedRefresh = Number.parseInt(rawRefresh, 10);
      if (Number.isNaN(parsedRefresh)) {
        data.dashboardRefreshInterval = '15';
        cfgFields.dashboardRefreshInterval.value = '15';
      } else {
        const clamped = Math.min(Math.max(parsedRefresh, 5), 300);
        data.dashboardRefreshInterval = String(clamped);
        cfgFields.dashboardRefreshInterval.value = String(clamped);
      }
    }
    if (cfgFields.dashboardFixedHeight) {
      const rawHeight = typeof cfgFields.dashboardFixedHeight.value === 'string'
        ? cfgFields.dashboardFixedHeight.value.trim()
        : '';
      data.dashboardFixedHeight = rawHeight;
      cfgFields.dashboardFixedHeight.value = rawHeight;
    }
    if (cfgFields.dashboardTheme) {
      const selectedTheme = (cfgFields.dashboardTheme.value || '').toLowerCase();
      data.dashboardTheme = selectedTheme === 'dark' ? 'dark' : 'light';
    }
    if (dashboardModulesList) {
      storeDashboardModules(activeDashboardVariant, readDashboardModules());
      data.dashboardModules = cloneDashboardModules(dashboardModulesState.public);
      data.dashboardSponsorModules = dashboardSponsorModulesInherited
        ? null
        : cloneDashboardModules(dashboardModulesState.sponsor || []);
      data.dashboardSponsorModulesInherited = dashboardSponsorModulesInherited;
    }
    if (cfgFields.dashboardInfoText) {
      data.dashboardInfoText = cfgFields.dashboardInfoText.value || '';
    }
    if (cfgFields.dashboardMediaEmbed) {
      data.dashboardMediaEmbed = cfgFields.dashboardMediaEmbed.value || '';
    }
    if (cfgFields.dashboardShareEnabled) {
      data.dashboardShareEnabled = cfgFields.dashboardShareEnabled.checked ? '1' : '0';
    }
    if (cfgFields.dashboardSponsorEnabled) {
      data.dashboardSponsorEnabled = cfgFields.dashboardSponsorEnabled.checked ? '1' : '0';
    }
    if (cfgFields.dashboardVisibilityStart) {
      data.dashboardVisibilityStart = cfgFields.dashboardVisibilityStart.value || '';
    }
    if (cfgFields.dashboardVisibilityEnd) {
      data.dashboardVisibilityEnd = cfgFields.dashboardVisibilityEnd.value || '';
    }
    if (puzzleFeedback) data.puzzleFeedback = puzzleFeedback;
    if (inviteText) data.inviteText = inviteText;
    return data;
  }

  let cfgSaveTimer;
  function queueCfgSave() {
    clearTimeout(cfgSaveTimer);
    cfgSaveTimer = setTimeout(saveCfg, 800);
  }

  function saveCfg() {
    const data = collectCfgData();
    const cfgPath = currentEventUid ? `/admin/event/${currentEventUid}` : '/config.json';
    const method = currentEventUid ? 'PATCH' : 'POST';
    apiFetch(cfgPath, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }).then(r => {
      if (r.ok) {
        Object.assign(cfgInitial, data);
        notify('Einstellung gespeichert', 'success');
      } else {
        notify('Fehler beim Speichern', 'danger');
      }
    }).catch(() => notify('Fehler beim Speichern', 'danger'));
  }

  document.querySelectorAll('[data-config-form]').forEach(form => {
    form.addEventListener('submit', event => event.preventDefault());
  });

  if (cfgFields.puzzleEnabled) {
    cfgFields.puzzleEnabled.addEventListener('change', () => {
      if (cfgFields.puzzleWrap) {
        cfgFields.puzzleWrap.style.display = cfgFields.puzzleEnabled.checked ? '' : 'none';
      }
      queueCfgSave();
    });
  }

  Object.entries(cfgFields).forEach(([key, el]) => {
    if (!el || ['logoFile', 'logoPreview', 'registrationEnabled', 'puzzleEnabled'].includes(key)) {
      return;
    }
    const targets = Array.isArray(el) ? el : [el];
    targets.forEach(target => {
      if (!target || typeof target.addEventListener !== 'function') {
        return;
      }
      target.addEventListener('change', queueCfgSave);
      target.addEventListener('input', queueCfgSave);
    });
  });
  cfgFields.randomNames?.addEventListener('change', () => {
    syncRandomNameOptionsState();
    invalidateRandomNamePreview();
  });
  cfgFields.randomNameStrategy?.addEventListener('change', () => {
    syncRandomNameOptionsState();
    invalidateRandomNamePreview();
  });
  if (Array.isArray(cfgFields.randomNameDomains)) {
    cfgFields.randomNameDomains.forEach(input => {
      if (input && typeof input.addEventListener === 'function') {
        input.addEventListener('change', () => invalidateRandomNamePreview());
      }
    });
  }
  if (Array.isArray(cfgFields.randomNameTones)) {
    cfgFields.randomNameTones.forEach(input => {
      if (input && typeof input.addEventListener === 'function') {
        input.addEventListener('change', () => invalidateRandomNamePreview());
      }
    });
  }
  if (cfgFields.randomNameLocale) {
    cfgFields.randomNameLocale.addEventListener('input', () => invalidateRandomNamePreview());
    cfgFields.randomNameLocale.addEventListener('change', () => invalidateRandomNamePreview());
  }
  cfgFields.randomNamePreviewButton?.addEventListener('click', () => {
    requestRandomNamePreview();
  });
  dashboardModulesList?.addEventListener('change', event => {
    if (event.target.matches('[data-module-results-option="limit"]')) {
      const moduleItem = event.target.closest('[data-module-id]');
      syncDashboardResultsPageSizeState(moduleItem);
    }
    if (event.target.matches('[data-module-points-leader-limit]')) {
      updateDashboardModules(true);
      return;
    }
    if (event.target.matches('[data-module-toggle], [data-module-catalog], [data-module-layout], [data-module-results-option], [data-module-title]')) {
      updateDashboardModules(true);
    }
  });
  dashboardModulesList?.addEventListener('input', event => {
    if (event.target.matches('[data-module-results-option="limit"]')) {
      const moduleItem = event.target.closest('[data-module-id]');
      syncDashboardResultsPageSizeState(moduleItem);
    }
    if (event.target.matches('[data-module-points-leader-limit]')) {
      updateDashboardModules(true);
      return;
    }
    if (event.target.matches('[data-module-results-option], [data-module-title]')) {
      updateDashboardModules(true);
    }
  });
  dashboardModulesList?.addEventListener('moved', () => {
    updateDashboardModules(true);
  });
  if (dashboardVariantSwitch) {
    dashboardVariantSwitch.addEventListener('click', event => {
      const toggle = event.target.closest('[data-dashboard-variant]');
      if (!toggle) {
        return;
      }
      event.preventDefault();
      const variant = toggle.dataset.dashboardVariant === 'sponsor' ? 'sponsor' : 'public';
      setActiveDashboardVariant(variant);
    });
  }
  document.querySelectorAll('[data-copy-link]').forEach(btn => {
    btn.addEventListener('click', () => {
      const variant = btn.dataset.copyLink === 'sponsor' ? 'sponsor' : 'public';
      const input = dashboardShareInputs[variant];
      if (!input || !input.value) {
        notify(transDashboardLinkMissing, 'warning');
        return;
      }
      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(input.value)
          .then(() => notify(transDashboardLinkCopied, 'success'))
          .catch(() => notify(transDashboardCopyFailed, 'danger'));
      } else {
        input.select();
        try {
          document.execCommand('copy');
          notify(transDashboardLinkCopied, 'success');
        } catch (err) {
          notify(transDashboardCopyFailed, 'danger');
        }
      }
    });
  });
  document.querySelectorAll('[data-rotate-token]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!currentEventUid) {
        notify(transDashboardNoEvent, 'warning');
        return;
      }
      const variant = btn.dataset.rotateToken === 'sponsor' ? 'sponsor' : 'public';
      btn.disabled = true;
      apiFetch(`/admin/event/${encodeURIComponent(currentEventUid)}/dashboard-token`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ variant })
      })
        .then(res => {
          if (!res.ok) {
            throw new Error('rotate-failed');
          }
          return res.json().catch(() => ({}));
        })
        .then(payload => {
          const token = payload?.token || '';
          if (variant === 'sponsor') {
            dashboardSponsorToken = token;
            cfgInitial.dashboardSponsorToken = token;
          } else {
            dashboardPublicToken = token;
            cfgInitial.dashboardShareToken = token;
          }
          updateDashboardShareLinks();
          notify(transDashboardTokenRotated, 'success');
        })
        .catch(() => {
          notify(transDashboardTokenRotateError, 'danger');
        })
        .finally(() => {
          btn.disabled = false;
        });
    });
  });
  puzzleFeedbackBtn?.addEventListener('click', () => {
    if (puzzleTextarea) {
      puzzleTextarea.value = puzzleFeedback;
    }
  });
  inviteTextBtn?.addEventListener('click', () => {
    if (inviteTextarea) {
      inviteTextarea.value = inviteText;
    }
  });
  puzzleSaveBtn?.addEventListener('click', () => {
    if (!puzzleTextarea) return;
    puzzleFeedback = puzzleTextarea.value;
    updatePuzzleFeedbackUI();
    puzzleModal.hide();
    cfgInitial.puzzleFeedback = puzzleFeedback;
    notify('Feedbacktext gespeichert', 'success');
    queueCfgSave();
  });

  inviteSaveBtn?.addEventListener('click', () => {
    if (!inviteTextarea) return;
    inviteText = inviteTextarea.value;
    updateInviteTextUI();
    inviteModal.hide();
    cfgInitial.inviteText = inviteText;
    notify('Einladungstext gespeichert', 'success');
    queueCfgSave();
  });

  commentSaveBtn?.addEventListener('click', async () => {
    if (!currentCommentItem || !commentTextarea) return;
    currentCommentItem.comment = commentTextarea.value;
    const list = catalogManager.getData();
    catalogManager.render(list);
    try {
      await saveCatalogs(list);
      commentModal.hide();
      currentCommentItem = null;
      notify('Kommentar gespeichert', 'success');
    } catch (err) {
      console.error(err);
      notify('Fehler beim Speichern', 'danger');
    }
  });


  cfgFields.registrationEnabled?.addEventListener('change', () => {
    settingsInitial.registration_enabled = cfgFields.registrationEnabled.checked ? '1' : '0';
    apiFetch('/settings.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ registration_enabled: settingsInitial.registration_enabled })
    }).then(r => {
      if (r.ok) {
        notify('Einstellung gespeichert', 'success');
      } else {
        notify('Fehler beim Speichern', 'danger');
      }
    }).catch(() => notify('Fehler beim Speichern', 'danger'));
  });
  function bindTeamPrintButtons(root = document) {
    if (!root || typeof root.querySelectorAll !== 'function') {
      return;
    }
    const buttons = root.querySelectorAll('.qr-print-btn');
    buttons.forEach(btn => {
      if (!btn || btn.dataset.summaryTeamBound === '1') {
        return;
      }
      btn.dataset.summaryTeamBound = '1';
      btn.addEventListener('click', e => {
        e.preventDefault();
        const rawTeam = btn.getAttribute('data-team') || btn.dataset.team || '';
        if (!rawTeam) {
          return;
        }
        const team = String(rawTeam);
        let link;
        if (currentEventUid) {
          const eventParam = encodeURIComponent(currentEventUid);
          link = window.baseUrl
            ? window.baseUrl + '/?event=' + eventParam + '&t=' + encodeURIComponent(team)
            : withBase('/?event=' + eventParam + '&t=' + encodeURIComponent(team));
        } else {
          link = window.baseUrl
            ? window.baseUrl + '/?t=' + encodeURIComponent(team)
            : withBase('/?t=' + encodeURIComponent(team));
        }
        let url = '/qr.pdf?t=' + encodeURIComponent(link) + '&rounded=1';
        if (currentEventUid) {
          url += '&event=' + encodeURIComponent(currentEventUid);
        }
        window.open(withBase(url), '_blank');
      });
    });
  }

  const summaryPrintBtn = document.getElementById('summaryPrintBtn');
  summaryPrintBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    window.print();
  });
  // sticker editor handled in sticker-editor.js
  const openInvitesBtn = document.getElementById('openInvitesBtn');
  openInvitesBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    if (currentEventUid) {
      const url = '/invites.pdf?event=' + encodeURIComponent(currentEventUid);
      window.open(withBase(url), '_blank');
    }
  });

  bindTeamPrintButtons();

  // --------- Fragen bearbeiten ---------
  const container = document.getElementById('questions');
  const addBtn = document.getElementById('addBtn');
  const catSelect = document.getElementById('catalogSelect');
  const catalogList = document.getElementById('catalogList');
  const newCatBtn = document.getElementById('newCatBtn');
  let catalogs = [];
  let catalogFile = '';
  let initial = [];
  let undoStack = [];

  registerCacheReset(() => {
    catalogs = [];
    catalogManager?.render([]);
    if (catSelect) {
      catSelect.innerHTML = '';
      catSelect.value = '';
    }
    resetQuestionEditorState();
  });

  const parseBoolean = value => {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value > 0;
    if (typeof value === 'string') {
      const normalized = value.trim().toLowerCase();
      return ['1', 'true', 'yes', 'on'].includes(normalized);
    }
    return false;
  };

  function isCountdownFeatureEnabled() {
    const raw = cfgInitial.countdownEnabled ?? cfgInitial.countdown_enabled ?? false;
    return parseBoolean(raw);
  }

  function getDefaultCountdownSeconds() {
    const raw = cfgInitial.countdown ?? cfgInitial.defaultCountdown ?? 0;
    const parsed = Number.parseInt(raw, 10);
    if (!Number.isNaN(parsed) && parsed > 0) {
      return parsed;
    }
    return null;
  }

  function parseCountdownValue(value) {
    if (value === null || value === undefined) return null;
    const trimmed = String(value).trim();
    if (trimmed === '') return null;
    const parsed = Number.parseInt(trimmed, 10);
    if (Number.isNaN(parsed) || parsed < 0) {
      return null;
    }
    return parsed;
  }

  // Zähler für eindeutige Namen von Eingabefeldern
  let cardIndex = 0;

  container?.addEventListener('input', () => saveQuestions());
  container?.addEventListener('change', () => saveQuestions());
  if (container && window.UIkit && UIkit.util) {
    UIkit.util.on(container, 'moved', () => saveQuestions());
  }

  const catalogPaginationEl = document.getElementById('catalogsPagination');

  const commentPreviewScratch = document.createElement('div');
  const COMMENT_PREVIEW_LIMIT = 140;

  function extractCommentPreview(raw) {
    if (!raw) {
      return { preview: '', full: '' };
    }
    commentPreviewScratch.innerHTML = raw;
    const text = (commentPreviewScratch.textContent || commentPreviewScratch.innerText || '')
      .replace(/\s+/g, ' ')
      .trim();
    commentPreviewScratch.textContent = '';
    if (!text) {
      return { preview: '', full: '' };
    }
    if (text.length <= COMMENT_PREVIEW_LIMIT) {
      return { preview: text, full: text };
    }
    const slice = text.slice(0, COMMENT_PREVIEW_LIMIT + 1);
    const lastSpace = slice.lastIndexOf(' ');
    const base = lastSpace > 0 ? slice.slice(0, lastSpace) : slice.slice(0, COMMENT_PREVIEW_LIMIT);
    const preview = `${base.trimEnd()} …`;
    return { preview, full: text };
  }

  function renderCatalogComment(item) {
    const { preview, full } = extractCommentPreview(item?.comment);
    if (!preview) {
      return '';
    }
    const span = document.createElement('span');
    span.classList.add('uk-text-truncate');
    span.textContent = preview;
    if (full && full !== preview) {
      span.title = full;
    }
    return span;
  }

  const catalogColumns = [
    { key: 'slug', label: 'Slug', className: 'uk-table-shrink', editable: true },
    { key: 'name', label: 'Name', className: 'uk-table-expand', editable: true },
    { key: 'description', label: 'Beschreibung', className: 'uk-table-expand', editable: true },
    { key: 'raetsel_buchstabe', label: 'Rätsel-Buchstabe', className: 'uk-table-shrink', editable: true },
    {
      key: 'comment',
      label: 'Kommentar',
      className: 'uk-table-expand',
      editable: true,
      ariaDesc: 'Kommentar bearbeiten',
      render: renderCatalogComment
    },
    {
      className: 'uk-table-shrink',
      render: item => {
        const wrapper = document.createElement('div');
        wrapper.className = 'uk-flex uk-flex-middle uk-flex-right';

        const delBtn = document.createElement('button');
        delBtn.className = 'uk-icon-button qr-action uk-text-danger';
        delBtn.setAttribute('uk-icon', 'trash');
        delBtn.setAttribute('aria-label', window.transDelete || 'Löschen');
        delBtn.setAttribute('uk-tooltip', 'title: ' + (window.transDelete || 'Löschen') + '; pos: left');
        delBtn.addEventListener('click', () => deleteCatalogById(item.id));

        wrapper.appendChild(delBtn);
        return wrapper;
      },
      renderCard: item => {
        const wrapper = document.createElement('div');
        wrapper.className = 'uk-flex uk-flex-middle uk-flex-right qr-action';

        const delBtn = document.createElement('button');
        delBtn.className = 'uk-icon-button qr-action uk-text-danger';
        delBtn.setAttribute('uk-icon', 'trash');
        delBtn.setAttribute('aria-label', window.transDelete || 'Löschen');
        delBtn.addEventListener('click', () => deleteCatalogById(item.id));

        wrapper.appendChild(delBtn);
        return wrapper;
      }
    }
  ];

  let catalogManager;
  let catalogEditor;
  if (catalogList) {
    catalogManager = new TableManager({
      tbody: catalogList,
      mobileCards: { container: document.getElementById('catalogCards') },
      sortable: true,
      columns: catalogColumns,
      onEdit: cell => {
        const key = cell?.dataset.key;
        if (key === 'comment') {
          const id = cell?.dataset.id;
          const list = catalogManager.getData();
          const cat = list.find(c => c.id === id);
          currentCommentItem = cat || null;
          if (commentTextarea) commentTextarea.value = cat?.comment || '';
          commentModal.show();
        } else {
          catalogEditError.hidden = true;
          catalogEditor.open(cell);
        }
      },
      onReorder: () => saveCatalogs(catalogManager.getData(), false, true)
    });
    catalogEditor = createCellEditor(catalogManager, {
      modalSelector: '#catalogEditModal',
      inputSelector: '#catalogEditInput',
      saveSelector: '#catalogEditSave',
      cancelSelector: '#catalogEditCancel',
      getTitle: key => catalogColumns.find(c => c.key === key)?.label || '',
      onSave: (list, item, key) => {
        const val = catalogEditInput.value.trim();
        if (key === 'slug') {
          item.slug = val;
        } else if (key === 'name') {
          item.name = val;
          if (item.new && !item.slug) {
            const idSlug = uniqueId(val);
            item.slug = idSlug;
          }
        } else if (key === 'description') {
          item.description = val;
        } else if (key === 'raetsel_buchstabe') {
          item.raetsel_buchstabe = val;
        }
        catalogManager.render(list);
        saveCatalogs(list, true);
      }
    });
    if (catalogPaginationEl) {
      catalogManager.bindPagination(catalogPaginationEl, 50);
    }
  }

  async function saveCatalogs(list = catalogManager?.getData() || [], show = false, reorder = false, retries = 1) {
    for (const item of list) {
      const currentId = item.slug?.trim() || '';
      const newFile = currentId ? currentId + '.json' : '';
      if (item.new) {
        let id = currentId;
        if (!id) {
          id = uniqueId(item.name || '');
        }
        if (!id) continue;
        try {
          await apiFetch('/kataloge/' + id + '.json', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: '[]'
          });
          item.new = false;
          item.file = id + '.json';
          item.slug = id;
        } catch (err) {
          console.error(err);
          notify('Fehler beim Erstellen', 'danger');
        }
      } else if (currentId && item.file && item.file !== newFile) {
        try {
          const res = await apiFetch('/kataloge/' + item.file, { headers: { 'Accept': 'application/json' } });
          const content = await res.text();
          await apiFetch('/kataloge/' + newFile, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: content });
          await apiFetch('/kataloge/' + item.file, { method: 'DELETE' });
          item.file = newFile;
        } catch (err) {
          console.error(err);
          notify('Fehler beim Umbenennen', 'danger');
        }
      }
      item.file = newFile;
    }

    const data = list
      .map((c, idx) => ({
        uid: c.id,
        sort_order: idx + 1,
        slug: c.slug,
        file: c.slug ? c.slug + '.json' : '',
        name: c.name,
        description: c.description,
        raetsel_buchstabe: c.raetsel_buchstabe,
        comment: c.comment
      }))
      .filter(c => c.slug);

    try {
      const r = await apiFetch('/kataloge/catalogs.json', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      if (!r.ok) throw new Error(r.statusText);
      catalogs = data.map(c => ({ ...c, id: c.uid }));
      catSelect.innerHTML = '';
      catalogs.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name || c.sort_order || c.slug;
        catSelect.appendChild(opt);
      });
      if (!catalogFile && catalogs.length > 0) {
        catSelect.value = catalogs[0].id;
        loadCatalog(catSelect.value);
      }
      if (show && !reorder) notify('Katalogliste gespeichert', 'success');
    } catch (err) {
      console.error(err);
      if (retries > 0) {
        notify('Fehler beim Speichern, versuche es erneut …', 'warning');
        setTimeout(() => saveCatalogs(list, show, reorder, retries - 1), 1000);
      } else {
        notify('Fehler beim Speichern', 'danger');
      }
    }
  }

  function loadCatalog(identifier) {
    const cat = catalogs.find(c => c.id === identifier || c.uid === identifier || (c.slug || c.sort_order) === identifier);
    if (!cat) return;
    catalogFile = cat.file;
    apiFetch('/kataloge/' + catalogFile, { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(data => {
        initial = data;
        renderAll(initial);
        undoStack = [JSON.parse(JSON.stringify(initial))];
      })
      .catch(() => {
        initial = [];
        renderAll(initial);
        undoStack = [JSON.parse(JSON.stringify(initial))];
      });
  }

  function applyCatalogList(list = []) {
    const timestamp = Date.now();
    catalogs = (Array.isArray(list) ? list : []).map((item, index) => {
      const baseId = item?.id ?? item?.uid ?? item?.slug ?? item?.sort_order;
      const id = baseId !== undefined && baseId !== null && baseId !== ''
        ? String(baseId)
        : String(timestamp + index);
      return { ...item, id };
    });
    if (catSelect) {
      catSelect.innerHTML = '';
      catalogs.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name || c.sort_order || c.slug;
        catSelect.appendChild(opt);
      });
    }
    catalogManager.render(catalogs);
    if (!catalogs.length) {
      if (catSelect) {
        catSelect.value = '';
      }
      resetQuestionEditorState();
      return;
    }
    if (catSelect) {
      const params = new URLSearchParams(window.location.search);
      const slug = params.get('katalog');
      const selected = catalogs.find(c => (c.slug || c.sort_order) === slug) || catalogs[0];
      if (selected) {
        catSelect.value = String(selected.id);
        loadCatalog(selected.id);
      }
    }
  }

  async function loadLegacyCatalogs() {
    const res = await apiFetch('/kataloge/catalogs.json', { headers: { 'Accept': 'application/json' } });
    if (!res.ok) {
      throw new Error(`Legacy catalogs request failed with status ${res.status}`);
    }
    const list = await res.json();
    applyCatalogList(list);
  }

  async function loadCatalogs() {
    catalogManager?.setColumnLoading('name', true);
    try {
      const res = await apiFetch('/admin/catalogs/data', { headers: { 'Accept': 'application/json' } });
      if (res.status === 404) {
        await loadLegacyCatalogs();
        return;
      }
      if (res.status === 401 || res.status === 403) {
        notify(transCatalogsForbidden, 'warning', 4000);
        return;
      }
      if (!res.ok) {
        throw new Error(`Admin catalogs request failed with status ${res.status}`);
      }
      const data = await res.json();
      if (data && typeof data === 'object' && data.useLegacy) {
        await loadLegacyCatalogs();
        return;
      }
      const list = data.items || data;
      applyCatalogList(list);
    } catch (err) {
      console.error(err);
      notify(transCatalogsFetchError, 'danger', 4000);
    } finally {
      catalogManager?.setColumnLoading('name', false);
    }
  }

  loadCatalogs();

  if (catSelect) {
    catSelect.addEventListener('change', () => loadCatalog(catSelect.value));
  }

  function deleteCatalogById(id) {
    const list = catalogManager.getData();
    const cat = list.find(c => c.id === id);
    if (!cat) return;
    if (cat.new || !cat.file) {
      catalogManager.render(list.filter(c => c.id !== id));
      return;
    }
    if (!confirm('Katalog wirklich löschen?')) return;
    apiFetch('/kataloge/' + cat.file, { method: 'DELETE' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        const updated = list.filter(c => c.id !== id);
        catalogManager.render(updated);
        catalogs = updated;
        const opt = catSelect.querySelector('option[value="' + id + '"]');
        opt?.remove();
        if (catalogs[0]) {
          if (catSelect.value === String(id)) {
            catSelect.value = catalogs[0].id;
            loadCatalog(catSelect.value);
          }
        } else {
          resetQuestionEditorState();
        }
        saveCatalogs(updated);
        notify('Katalog gelöscht', 'success');
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Löschen', 'danger');
      });
  }

  // Rendert alle Fragen im Editor neu
  function renderAll(data) {
    if (!container) {
      return;
    }
    container.innerHTML = '';
    cardIndex = 0;
    data.forEach((q, i) => container.appendChild(createCard(q, i)));
  }

  function resetQuestionEditorState() {
    catalogFile = '';
    initial = [];
    renderAll(initial);
    undoStack = [JSON.parse(JSON.stringify(initial))];
  }

  // Erstellt ein Bearbeitungsformular für eine Frage
  function createCard(q, index = -1) {
    const card = document.createElement('div');
    card.className = 'uk-card qr-card uk-card-body uk-margin question-card';
    if (index >= 0) {
      card.dataset.index = String(index);
    }
    const typeSelect = document.createElement('select');
    typeSelect.className = 'uk-select uk-margin-small-bottom type-select';
    const labelMap = {
      mc: 'Multiple Choice',
      assign: 'Zuordnen',
      sort: 'Sortieren',
      swipe: 'Swipe-Karten',
      photoText: 'Foto + Text',
      flip: 'Hätten Sie es gewusst?'
    };
    ['sort', 'assign', 'mc', 'swipe', 'photoText', 'flip'].forEach(t => {
      const opt = document.createElement('option');
      opt.value = t;
      opt.textContent = labelMap[t] || t;
      typeSelect.appendChild(opt);
    });
    typeSelect.value = q.type || 'mc';
    const typeInfo = document.createElement('div');
    typeInfo.className = 'uk-alert-primary uk-margin-small-bottom type-info';
    // Infotext passend zum gewählten Fragetyp anzeigen
    function updateInfo() {
      const map = {
        sort: 'Items in die richtige Reihenfolge bringen.',
        assign: 'Begriffe den passenden Definitionen zuordnen.',
        mc: 'Mehrfachauswahl (Multiple Choice, mehrere Antworten möglich).',
        swipe: 'Karten nach links oder rechts wischen.',
        photoText: 'Foto aufnehmen und passende Antwort eingeben.',
        flip: 'Frage mit umdrehbarer Antwortkarte.'
      };
      const base = map[typeSelect.value] || '';
      typeInfo.textContent = base + ' Für kleine Displays kannst du "/-" als verstecktes Worttrennzeichen nutzen.';
    }
    updateInfo();
    typeSelect.addEventListener('change', () => {
      renderFields();
      updateInfo();
      updatePointsState();
      updatePreview();
    });
    const prompt = document.createElement('textarea');
    prompt.className = 'uk-textarea uk-margin-small-bottom prompt';
    prompt.placeholder = 'Fragetext';
    prompt.value = q.prompt || '';
    const countdownEnabled = isCountdownFeatureEnabled();
    const defaultCountdown = getDefaultCountdownSeconds();
    const countdownId = `countdown-${cardIndex}`;
    const countdownGroup = document.createElement('div');
    countdownGroup.className = 'uk-margin-small-bottom';
    const countdownLabel = document.createElement('label');
    countdownLabel.className = 'uk-form-label';
    countdownLabel.setAttribute('for', countdownId);
    countdownLabel.textContent = 'Zeitlimit (Sekunden)';
    const countdownInput = document.createElement('input');
    countdownInput.className = 'uk-input countdown-input';
    countdownInput.type = 'number';
    countdownInput.min = '0';
    countdownInput.id = countdownId;
    const hasCountdown = Object.prototype.hasOwnProperty.call(q, 'countdown');
    if (hasCountdown && q.countdown !== null && q.countdown !== undefined) {
      countdownInput.value = String(q.countdown);
    }
    countdownInput.placeholder = defaultCountdown !== null
      ? `Standard: ${defaultCountdown}s`
      : 'z.\u00A0B. 45';
    countdownInput.disabled = !countdownEnabled;
    const countdownMeta = document.createElement('div');
    countdownMeta.className = 'uk-text-meta';
    const countdownDisabledHint = cfgFields.countdownEnabled
      ? 'In den Event-Einstellungen unter „Extras“ den Countdown aktivieren, um ein Zeitlimit festzulegen.'
      : 'Countdown aktivieren, um ein Zeitlimit festzulegen.';
    countdownMeta.textContent = countdownEnabled
      ? 'Leer für Standardwert, 0 deaktiviert den Timer.'
      : countdownDisabledHint;
    countdownGroup.appendChild(countdownLabel);
    countdownGroup.appendChild(countdownInput);
    countdownGroup.appendChild(countdownMeta);
    const pointsId = `points-${cardIndex}`;
    const pointsGroup = document.createElement('div');
    pointsGroup.className = 'uk-margin-small-bottom question-points-group';
    const pointsLabel = document.createElement('label');
    pointsLabel.className = 'uk-form-label';
    pointsLabel.setAttribute('for', pointsId);
    pointsLabel.textContent = 'Punkte (0–10000)';
    const pointsInput = document.createElement('input');
    pointsInput.className = 'uk-input points-input';
    pointsInput.type = 'number';
    pointsInput.id = pointsId;
    pointsInput.min = '0';
    pointsInput.max = '10000';
    pointsInput.step = '1';
    pointsInput.setAttribute('aria-label', 'Punkte pro Frage');
    const existingPoints = parseQuestionPoints(q.points);
    if (typeSelect.value === 'flip') {
      pointsInput.value = existingPoints !== null ? String(existingPoints) : '0';
    } else {
      pointsInput.value = existingPoints !== null ? String(existingPoints) : '1';
    }
    const pointsMeta = document.createElement('div');
    pointsMeta.className = 'uk-text-meta';
    pointsGroup.appendChild(pointsLabel);
    pointsGroup.appendChild(pointsInput);
    pointsGroup.appendChild(pointsMeta);
    let lastScorablePoints = existingPoints ?? 1;

    function updatePointsState() {
      const scorable = typeSelect.value !== 'flip';
      if (!scorable) {
        const parsed = parseQuestionPoints(pointsInput.value);
        if (parsed !== null) {
          lastScorablePoints = parsed;
        }
        pointsInput.value = '0';
        pointsInput.disabled = true;
        pointsMeta.textContent = 'Dieser Fragetyp vergibt keine Punkte.';
      } else {
        pointsInput.disabled = false;
        const parsed = parseQuestionPoints(pointsInput.value);
        const fallback = Number.isFinite(lastScorablePoints) ? lastScorablePoints : 1;
        const value = parsed === null ? fallback : parsed;
        const normalized = normalizeQuestionPoints(value, true);
        pointsInput.value = String(normalized);
        lastScorablePoints = normalized;
        pointsMeta.textContent = 'Punkte pro Frage (0–10000). Leer ergibt 1 Punkt.';
      }
    }

    pointsInput.addEventListener('input', () => {
      if (typeSelect.value !== 'flip') {
        const parsed = parseQuestionPoints(pointsInput.value);
        if (parsed !== null) {
          const normalized = normalizeQuestionPoints(parsed, true);
          if (String(normalized) !== pointsInput.value) {
            pointsInput.value = String(normalized);
          }
          lastScorablePoints = normalized;
        }
      }
      updatePreview();
    });

    pointsInput.addEventListener('blur', () => {
      if (typeSelect.value === 'flip') {
        return;
      }
      const parsed = parseQuestionPoints(pointsInput.value);
      const fallback = Number.isFinite(lastScorablePoints) ? lastScorablePoints : 1;
      const value = parsed === null ? fallback : parsed;
      const normalized = normalizeQuestionPoints(value, true);
      pointsInput.value = String(normalized);
      lastScorablePoints = normalized;
      updatePreview();
    });

    const fields = document.createElement('div');
    fields.className = 'fields';
    const removeBtn = document.createElement('button');
    removeBtn.className = 'uk-icon-button uk-button-danger uk-margin-small-top uk-align-right';
    removeBtn.setAttribute('uk-icon', 'trash');
    removeBtn.setAttribute('aria-label', 'Entfernen');
    removeBtn.onclick = () => {
      const idx = card.dataset.index;
      if (idx !== undefined) {
        undoStack.push(JSON.parse(JSON.stringify(initial)));
        apiFetch('/kataloge/' + catalogFile + '/' + idx, { method: 'DELETE' })
          .then(r => {
            if (!r.ok) throw new Error(r.statusText);
            initial.splice(Number(idx), 1);
            renderAll(initial);
            saveQuestions(initial, true);
          })
          .catch(err => {
            console.error(err);
            notify('Fehler beim Löschen', 'danger');
          });
      } else {
        card.remove();
        saveQuestions();
      }
    };

    // Hilfsfunktionen zum Anlegen der Eingabefelder
    function addItem(value = '') {
      const div = document.createElement('div');
      div.className = 'uk-flex uk-margin-small-bottom item-row';
      const input = document.createElement('input');
      input.className = 'uk-input item';
      input.type = 'text';
      input.value = value;
      input.setAttribute('aria-label', 'Item');
      const btn = document.createElement('button');
      btn.className = 'uk-icon-button uk-button-danger uk-button-small uk-margin-left';
      btn.setAttribute('uk-icon', 'trash');
      btn.setAttribute('aria-label', 'Entfernen');
      btn.onclick = () => { div.remove(); saveQuestions(); };
      div.appendChild(input);
      div.appendChild(btn);
      return div;
    }

    function addPair(term = '', def = '') {
      const row = document.createElement('div');
      row.className = 'uk-grid-small uk-margin-small-bottom term-row';
      row.setAttribute('uk-grid', '');
      const tInput = document.createElement('input');
      tInput.className = 'uk-input term';
      tInput.type = 'text';
      tInput.placeholder = 'Begriff';
      tInput.value = term;
      tInput.setAttribute('aria-label', 'Begriff');
      const dInput = document.createElement('input');
      dInput.className = 'uk-input definition';
      dInput.type = 'text';
      dInput.placeholder = 'Definition';
      dInput.value = def;
      dInput.setAttribute('aria-label', 'Definition');
      const rem = document.createElement('button');
      rem.className = 'uk-icon-button uk-button-danger uk-button-small';
      rem.setAttribute('uk-icon', 'trash');
      rem.setAttribute('aria-label', 'Entfernen');
      rem.onclick = () => { row.remove(); saveQuestions(); };
      const tDiv = document.createElement('div');
      tDiv.appendChild(tInput);
      const dDiv = document.createElement('div');
      dDiv.appendChild(dInput);
      const bDiv = document.createElement('div');
      bDiv.className = 'uk-width-auto';
      bDiv.appendChild(rem);
      row.appendChild(tDiv);
      row.appendChild(dDiv);
      row.appendChild(bDiv);
      return row;
    }

    function addOption(text = '', checked = false) {
      const row = document.createElement('div');
      row.className = 'uk-flex uk-margin-small-bottom option-row';
      const radio = document.createElement('input');
      radio.type = 'checkbox';
      radio.className = 'uk-checkbox answer';
      radio.name = 'ans' + cardIndex;
      radio.checked = checked;
      const input = document.createElement('input');
      input.className = 'uk-input option uk-margin-small-left';
      input.type = 'text';
      input.value = text;
      input.setAttribute('aria-label', 'Antworttext');
      const optId = 'opt-' + Math.random().toString(36).slice(2, 8);
      input.id = optId;
      radio.setAttribute('aria-labelledby', optId);
      const rem = document.createElement('button');
      rem.className = 'uk-icon-button uk-button-danger uk-button-small uk-margin-left';
      rem.setAttribute('uk-icon', 'trash');
      rem.setAttribute('aria-label', 'Entfernen');
      rem.onclick = () => { row.remove(); saveQuestions(); };
      row.appendChild(radio);
      row.appendChild(input);
      row.appendChild(rem);
      return row;
    }

    function addCard(text = '', correct = false) {
      const row = document.createElement('div');
      row.className = 'uk-flex uk-margin-small-bottom card-row';
      const input = document.createElement('input');
      input.className = 'uk-input card-text';
      input.type = 'text';
      input.value = text;
      input.placeholder = 'Kartentext';
      input.setAttribute('aria-label', 'Kartentext');
      const check = document.createElement('input');
      check.type = 'checkbox';
      check.className = 'uk-checkbox card-correct uk-margin-left';
      check.checked = correct;
      check.setAttribute('aria-label', 'Richtige Antwort (rechts)');
      const rem = document.createElement('button');
      rem.className = 'uk-icon-button uk-button-danger uk-button-small uk-margin-left';
      rem.setAttribute('uk-icon', 'trash');
      rem.setAttribute('aria-label', 'Entfernen');
      rem.onclick = () => { row.remove(); saveQuestions(); };
      row.appendChild(input);
      row.appendChild(rem);
      row.appendChild(check);
      return row;
    }

    // Zeigt je nach Fragetyp die passenden Eingabefelder an
    function renderFields() {
      fields.innerHTML = '';
      if (typeSelect.value === 'sort') {
        const list = document.createElement('div');
        (q.items || ['', '']).forEach(it => list.appendChild(addItem(it)));
        const add = document.createElement('button');
        add.className = 'uk-icon-button uk-button-primary uk-margin-small-top';
        add.setAttribute("uk-icon", "plus");
        add.setAttribute("aria-label", "Item hinzufügen");
        add.onclick = e => {
          e.preventDefault();
          list.appendChild(addItem(''));
        };
        fields.appendChild(list);
        fields.appendChild(add);
      } else if (typeSelect.value === 'assign') {
        const list = document.createElement('div');
        (q.terms || [{ term: '', definition: '' }]).forEach(p =>
          list.appendChild(addPair(p.term, p.definition))
        );
        const add = document.createElement('button');
        add.className = 'uk-icon-button uk-button-primary uk-margin-small-top';
        add.setAttribute("uk-icon", "plus");
        add.setAttribute("aria-label", "Begriff hinzufügen");
        add.onclick = e => {
          e.preventDefault();
          list.appendChild(addPair('', ''));
        };
        fields.appendChild(list);
        fields.appendChild(add);
      } else if (typeSelect.value === 'swipe') {
        const right = document.createElement('input');
        right.className = 'uk-input uk-margin-small-bottom right-label';
        right.type = 'text';
        right.placeholder = 'Label rechts (\u27A1, z.B. Ja)';
        right.style.borderColor = 'green';
        right.value = q.rightLabel || '';
        right.setAttribute('aria-label', 'Label f\u00fcr Swipe nach rechts');
        right.setAttribute('uk-tooltip', 'title: Text, der beim Wischen nach rechts angezeigt wird.; pos: right');

        const left = document.createElement('input');
        left.className = 'uk-input uk-margin-small-bottom left-label';
        left.type = 'text';
        left.placeholder = 'Label links (\u2B05, z.B. Nein)';
        left.style.borderColor = 'red';
        left.value = q.leftLabel || '';
        left.setAttribute('aria-label', 'Label f\u00fcr Swipe nach links');
        left.setAttribute('uk-tooltip', 'title: Text, der beim Wischen nach links angezeigt wird.; pos: right');

        fields.appendChild(right);
        fields.appendChild(left);
        const list = document.createElement('div');
        (q.cards || [{ text: '', correct: false }]).forEach(c =>
          list.appendChild(addCard(c.text, c.correct))
        );
        const add = document.createElement('button');
        add.className = 'uk-icon-button uk-button-primary uk-margin-small-top';
        add.setAttribute("uk-icon", "plus");
        add.setAttribute("aria-label", "Karte hinzufügen");
        add.onclick = e => { e.preventDefault(); list.appendChild(addCard('', false)); };
        fields.appendChild(list);
        fields.appendChild(add);
      } else if (typeSelect.value === 'flip') {
        const ans = document.createElement('textarea');
        ans.className = 'uk-textarea uk-margin-small-bottom flip-answer';
        ans.placeholder = 'Antwort';
        ans.value = q.answer || '';
        ans.setAttribute('aria-label', 'Antwort');
        fields.appendChild(ans);
      } else if (typeSelect.value === 'photoText') {
        const consent = document.createElement('label');
        consent.className = 'uk-margin-small-bottom';
        consent.innerHTML = '<input type="checkbox" class="uk-checkbox consent-box"> Datenschutz-Checkbox anzeigen';
        const chk = consent.querySelector('input');
        if (q.consent) chk.checked = true;
        fields.appendChild(consent);
      } else {
        const list = document.createElement('div');
        (q.options || ['', '']).forEach((opt, i) =>
          list.appendChild(addOption(opt, (q.answers || []).includes(i)))
        );
        const add = document.createElement('button');
        add.className = 'uk-icon-button uk-button-primary uk-margin-small-top';
        add.setAttribute("uk-icon", "plus");
        add.setAttribute("aria-label", "Option hinzufügen");
        add.onclick = e => {
          e.preventDefault();
          list.appendChild(addOption(''));
        };
        fields.appendChild(list);
        fields.appendChild(add);
      }
    }

    renderFields();
    updatePointsState();

    // Vorschau-Bereich anlegen
    const preview = document.createElement('div');
    preview.className = 'uk-card qr-card uk-card-body question-preview';

    const formCol = document.createElement('div');
    formCol.appendChild(typeSelect);
    formCol.appendChild(typeInfo);
    formCol.appendChild(prompt);
    formCol.appendChild(countdownGroup);
    formCol.appendChild(pointsGroup);
    formCol.appendChild(fields);
    formCol.appendChild(removeBtn);

    const previewCol = document.createElement('div');
    previewCol.appendChild(preview);

    const grid = document.createElement('div');
    grid.className = 'uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m';
    grid.setAttribute('uk-grid', '');
    grid.appendChild(formCol);
    grid.appendChild(previewCol);

    card.appendChild(grid);

    function updatePreview() {
      preview.innerHTML = '';
      const countdownValue = parseCountdownValue(countdownInput.value);
      const effectiveCountdown = countdownEnabled
        ? (countdownValue !== null ? countdownValue : defaultCountdown)
        : null;
      if (countdownEnabled) {
        if (effectiveCountdown !== null && effectiveCountdown > 0) {
          const timer = document.createElement('div');
          timer.className = 'question-timer uk-margin-small-bottom';
          const timerLabel = document.createElement('span');
          timerLabel.className = 'question-timer__label';
          timerLabel.textContent = 'Zeitlimit:';
          const timerValue = document.createElement('span');
          timerValue.className = 'question-timer__value';
          timerValue.textContent = `${effectiveCountdown}s`;
          timer.appendChild(timerLabel);
          timer.appendChild(timerValue);
          preview.appendChild(timer);
        } else if (countdownValue === 0) {
          const noTimer = document.createElement('div');
          noTimer.className = 'uk-text-meta uk-margin-small-bottom';
          noTimer.textContent = 'Kein Timer für diese Frage.';
          preview.appendChild(noTimer);
        }
      }
      const scorable = typeSelect.value !== 'flip';
      const pointsValue = getPointsValue(card, typeSelect.value);
      const pointsInfo = document.createElement('div');
      pointsInfo.className = 'uk-text-meta uk-margin-small-bottom';
      if (scorable) {
        pointsInfo.textContent = pointsValue === 1 ? '1 Punkt' : `${pointsValue} Punkte`;
      } else {
        pointsInfo.textContent = 'Keine Punktevergabe';
      }
      preview.appendChild(pointsInfo);
      const h = document.createElement('h4');
      h.textContent = insertSoftHyphens(prompt.value || 'Vorschau');
      preview.appendChild(h);
      if (typeSelect.value === 'sort') {
        const ul = document.createElement('ul');
        Array.from(fields.querySelectorAll('.item')).forEach(i => {
          const li = document.createElement('li');
          li.textContent = insertSoftHyphens(i.value);
          ul.appendChild(li);
        });
        preview.appendChild(ul);
      } else if (typeSelect.value === 'assign') {
        const ul = document.createElement('ul');
        Array.from(fields.querySelectorAll('.term-row')).forEach(r => {
          const term = r.querySelector('.term').value;
          const def = r.querySelector('.definition').value;
          const li = document.createElement('li');
          li.textContent = insertSoftHyphens(term) + ' – ' + insertSoftHyphens(def);
          ul.appendChild(li);
        });
        preview.appendChild(ul);
      } else if (typeSelect.value === 'swipe') {
        const container = document.createElement('div');
        container.style.position = 'relative';
        container.style.height = '200px';
        container.style.userSelect = 'none';
        container.style.touchAction = 'none';

        const leftLabel = fields.querySelector('.left-label')?.value || 'Nein';
        const rightLabel = fields.querySelector('.right-label')?.value || 'Ja';

        const leftStatic = document.createElement('div');
        leftStatic.textContent = '⬅ ' + insertSoftHyphens(leftLabel);
        leftStatic.style.position = 'absolute';
        leftStatic.style.left = '0';
        leftStatic.style.top = '50%';
        leftStatic.style.transform = 'translate(-50%, -50%) rotate(180deg)';
        leftStatic.style.writingMode = 'vertical-rl';
        leftStatic.style.pointerEvents = 'none';
        leftStatic.style.color = 'red';
        leftStatic.style.zIndex = '10';
        container.appendChild(leftStatic);

        const rightStatic = document.createElement('div');
        rightStatic.textContent = insertSoftHyphens(rightLabel) + ' ➡';
        rightStatic.style.position = 'absolute';
        rightStatic.style.right = '0';
        rightStatic.style.top = '50%';
        rightStatic.style.transform = 'translate(50%, -50%)';
        rightStatic.style.writingMode = 'vertical-rl';
        rightStatic.style.pointerEvents = 'none';
        rightStatic.style.color = 'green';
        rightStatic.style.zIndex = '10';
        container.appendChild(rightStatic);

        const label = document.createElement('div');
        label.style.position = 'absolute';
        label.style.top = '8px';
        label.style.left = '8px';
        label.style.fontWeight = 'bold';
        label.style.pointerEvents = 'none';
        container.appendChild(label);

        let cards = Array.from(fields.querySelectorAll('.card-row')).map(r => ({
          text: r.querySelector('.card-text').value
        }));

        let startX = 0, startY = 0, offsetX = 0, offsetY = 0, dragging = false;

        function render() {
          container.querySelectorAll('.swipe-card').forEach(el => el.remove());
          cards.forEach((c, i) => {
            const card = document.createElement('div');
            card.className = 'swipe-card';
            card.style.position = 'absolute';
            card.style.left = '2rem';
            card.style.right = '2rem';
            card.style.top = '0';
            card.style.bottom = '0';
            card.style.background = 'white';
            card.style.borderRadius = '8px';
            card.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
            card.style.display = 'flex';
            card.style.alignItems = 'center';
            card.style.justifyContent = 'center';
            card.style.padding = '1rem';
            card.style.transition = 'transform 0.3s';
            const off = (cards.length - i - 1) * 4;
            card.style.transform = `translate(0,-${off}px)`;
            card.style.zIndex = i;
            card.textContent = insertSoftHyphens(c.text);
            if (i === cards.length - 1) {
              card.addEventListener('pointerdown', start);
              card.addEventListener('pointermove', move);
              card.addEventListener('pointerup', end);
              card.addEventListener('pointercancel', end);
            }
            container.appendChild(card);
          });
        }

        function point(e) { return { x: e.clientX, y: e.clientY }; }

        function start(e) {
          if (!cards.length) return;
          const p = point(e);
          startX = p.x; startY = p.y;
          dragging = true;
          offsetX = 0; offsetY = 0;
        }

        function move(e) {
          if (!dragging) return;
          const p = point(e);
          offsetX = p.x - startX;
          offsetY = p.y - startY;
          const card = container.querySelector('.swipe-card:last-child');
          if (card) {
            const rot = offsetX / 10;
            card.style.transform = `translate(${offsetX}px,${offsetY}px) rotate(${rot}deg)`;
          }
          label.textContent = offsetX >= 0
            ? '➡ ' + insertSoftHyphens(rightLabel)
            : '⬅ ' + insertSoftHyphens(leftLabel);
          label.style.color = offsetX >= 0 ? 'green' : 'red';
          e.preventDefault();
        }

        function end() {
          if (!dragging) return;
          dragging = false;
          const cardEl = container.querySelector('.swipe-card:last-child');
          const threshold = 80;
          if (Math.abs(offsetX) > threshold) {
            if (cardEl) {
              cardEl.style.transform = `translate(${offsetX > 0 ? 1000 : -1000}px,${offsetY}px)`;
            }
            setTimeout(() => {
              cards.pop();
              offsetX = offsetY = 0;
              label.textContent = '';
              render();
            }, 300);
          } else {
            if (cardEl) {
              cardEl.style.transform = 'translate(0,0)';
            }
            offsetX = offsetY = 0;
            label.textContent = '';
          }
        }

        render();
        preview.appendChild(container);
      } else if (typeSelect.value === 'flip') {
        const p = document.createElement('p');
        const ans = fields.querySelector('.flip-answer');
        p.textContent = insertSoftHyphens(ans ? ans.value : 'Antwort');
        preview.appendChild(p);
      } else if (typeSelect.value === 'photoText') {
        const p = document.createElement('p');
        p.textContent = 'Foto-Upload und Textfeld';
        preview.appendChild(p);
      } else {
        const ul = document.createElement('ul');
        Array.from(fields.querySelectorAll('.option-row')).forEach(r => {
          const input = r.querySelector('.option');
          const check = r.querySelector('.answer').checked;
          const li = document.createElement('li');
          li.textContent = insertSoftHyphens(input.value) + (check ? ' ✓' : '');
          if (check) li.classList.add('uk-text-success');
          ul.appendChild(li);
        });
        preview.appendChild(ul);
      }
    }

    prompt.addEventListener('input', updatePreview);
    fields.addEventListener('input', updatePreview);
    countdownInput.addEventListener('input', updatePreview);
    countdownInput.addEventListener('change', updatePreview);
    typeSelect.addEventListener('change', updatePreview);
    updatePreview();

    cardIndex++;
    return card;
  }

  // Sammelt alle Eingaben aus den Karten in ein Array von Fragen
  function getCountdownValue(card) {
    const input = card.querySelector('.countdown-input');
    if (!input) return null;
    return parseCountdownValue(input.value);
  }

  function clampQuestionPoints(value) {
    if (!Number.isFinite(value)) {
      return 0;
    }
    if (value < 0) {
      return 0;
    }
    if (value > 10000) {
      return 10000;
    }
    return Math.round(value);
  }

  function parseQuestionPoints(raw) {
    if (typeof raw === 'number' && Number.isFinite(raw)) {
      return clampQuestionPoints(raw);
    }
    if (typeof raw === 'string') {
      const trimmed = raw.trim();
      if (trimmed === '') {
        return null;
      }
      const parsed = Number.parseInt(trimmed, 10);
      if (Number.isNaN(parsed)) {
        return null;
      }
      return clampQuestionPoints(parsed);
    }
    return null;
  }

  function normalizeQuestionPoints(raw, scorable) {
    if (!scorable) {
      return 0;
    }
    const parsed = parseQuestionPoints(raw);
    if (parsed === null) {
      return 1;
    }
    return parsed;
  }

  function getPointsValue(card, type) {
    const scorable = type !== 'flip';
    const input = card.querySelector('.points-input');
    if (!input) {
      return normalizeQuestionPoints(null, scorable);
    }
    return normalizeQuestionPoints(input.value, scorable);
  }

  function collect() {
    return Array.from(container.querySelectorAll('.question-card')).map(card => {
      const type = card.querySelector('.type-select').value;
      const prompt = card.querySelector('.prompt').value.trim();
      if (type === 'sort') {
        const items = Array.from(card.querySelectorAll('.item-row .item'))
          .map(i => i.value.trim())
          .filter(Boolean);
        const obj = { type, prompt, items };
        const countdown = getCountdownValue(card);
        if (countdown !== null) obj.countdown = countdown;
        obj.points = getPointsValue(card, type);
        return obj;
      } else if (type === 'assign') {
        const terms = Array.from(card.querySelectorAll('.term-row')).map(r => ({
          term: r.querySelector('.term').value.trim(),
          definition: r.querySelector('.definition').value.trim()
        })).filter(t => t.term || t.definition);
        const obj = { type, prompt, terms };
        const countdown = getCountdownValue(card);
        if (countdown !== null) obj.countdown = countdown;
        obj.points = getPointsValue(card, type);
        return obj;
      } else if (type === 'swipe') {
        const cards = Array.from(card.querySelectorAll('.card-row')).map(r => ({
          text: r.querySelector('.card-text').value.trim(),
          correct: r.querySelector('.card-correct').checked
        })).filter(c => c.text);
        const rightLabel = card.querySelector('.right-label').value.trim();
        const leftLabel = card.querySelector('.left-label').value.trim();
        const obj = { type, prompt, cards };
        if (rightLabel) obj.rightLabel = rightLabel;
        if (leftLabel) obj.leftLabel = leftLabel;
        const countdown = getCountdownValue(card);
        if (countdown !== null) obj.countdown = countdown;
        obj.points = getPointsValue(card, type);
        return obj;
      } else if (type === 'flip') {
        const answer = card.querySelector('.flip-answer').value.trim();
        const obj = { type, prompt, answer };
        const countdown = getCountdownValue(card);
        if (countdown !== null) obj.countdown = countdown;
        obj.points = getPointsValue(card, type);
        return obj;
      } else if (type === 'photoText') {
        const consent = card.querySelector('.consent-box').checked;
        const obj = { type, prompt, consent };
        const countdown = getCountdownValue(card);
        if (countdown !== null) obj.countdown = countdown;
        obj.points = getPointsValue(card, type);
        return obj;
      } else {
        const options = Array.from(card.querySelectorAll('.option-row .option'))
          .map(i => i.value.trim())
          .filter(Boolean);
        const checks = Array.from(card.querySelectorAll('.option-row .answer'));
        const answers = checks
          .map((c, i) => (c.checked ? i : -1))
          .filter(i => i >= 0);
        const obj = { type, prompt, options, answers };
        const countdown = getCountdownValue(card);
        if (countdown !== null) obj.countdown = countdown;
        obj.points = getPointsValue(card, type);
        return obj;
      }
    });
  }

  // Speichert die Fragen automatisch auf dem Server
  let saveTimer;
  function saveQuestions(list, skipHistory = false) {
    if (!catalogFile) return;
    const data = list || collect();
    if (!skipHistory) {
      undoStack.push(JSON.parse(JSON.stringify(initial)));
      if (undoStack.length > 50) undoStack.shift();
    }
    initial = data;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      apiFetch('/kataloge/' + catalogFile, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })
        .then(r => {
          if (!r.ok && r.status !== 400) {
            throw new Error(r.statusText);
          }
        })
        .catch(err => {
          console.error(err);
          notify('Fehler beim Speichern', 'danger');
        });
    }, 300);
  }

  function undo() {
    const prev = undoStack.pop();
    if (prev) {
      renderAll(prev);
      saveQuestions(prev, true);
    }
  }

  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
      e.preventDefault();
      undo();
    }
  });

  // Fügt eine neue leere Frage hinzu
  addBtn.addEventListener('click', function (e) {
    e.preventDefault();
    container.appendChild(
      createCard({ type: 'mc', prompt: '', points: 1, options: ['', ''], answers: [0] }, -1)
    );
  });

  newCatBtn.addEventListener('click', function (e) {
    e.preventDefault();
    const id = crypto.randomUUID();
    const item = { id, slug: '', file: '', name: '', description: '', raetsel_buchstabe: '', comment: '', new: true };
    const list = catalogManager.getData();
    list.push(item);
    catalogManager.render(list);
    saveCatalogs(list, true);
    const cell = document.querySelector(`[data-id="${id}"][data-key="name"]`);
    if (cell) {
      catalogEditError.hidden = true;
      catalogEditor.open(cell);
    }
  });


  const resultsResetBtn = document.getElementById('resultsResetBtn');
  const resultsDownloadBtn = document.getElementById('resultsDownloadBtn');
  const resultsPdfBtn = document.getElementById('resultsPdfBtn');

  resultsResetBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    resultsResetModal?.show();
  });

  resultsResetConfirm?.addEventListener('click', function () {
    apiFetch('/results', { method: 'DELETE' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify('Ergebnisse gelöscht', 'success');
        resultsResetModal?.hide();
        window.location.reload();
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Löschen', 'danger');
      });
  });

  resultsDownloadBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    apiFetch('/results/download')
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        return r.blob();
      })
      .then(blob => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const name = (window.quizConfig && window.quizConfig.header) ? window.quizConfig.header : 'results';
        a.download = name + '.csv';
        a.click();
        URL.revokeObjectURL(url);
      })
      .catch(err => {
        console.error(err);
      notify('Fehler beim Herunterladen', 'danger');
    });
  });

  resultsPdfBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    window.open(withBase('/results.pdf'), '_blank');
  });

  // --------- Veranstaltungen ---------
  const eventsListEl = document.getElementById('eventsList');
  const eventsCardsEl = document.getElementById('eventsCards');
  const eventsCardsEmptyEl = document.getElementById('eventsCardsEmpty');
  const eventAddBtn = document.getElementById('eventAddBtn');

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
    availableEvents = normalized;
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
        if (ev.uid === currentEventUid) {
          option.selected = true;
        }
        select.appendChild(option);
      });
      const hasCurrent = normalized.some(ev => ev.uid === currentEventUid);
      if (hasCurrent) {
        select.value = currentEventUid;
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
    const hasAnyEvents = typeof hasEvents === 'boolean' ? hasEvents : availableEvents.length > 0;
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
        const disable = !hasAnyEvents || availableEvents.length === 0;
        select.disabled = disable;
      }
      if (empty) {
        if (!hasAnyEvents || availableEvents.length === 0) {
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
      currentEventUid = '';
      currentEventName = '';
      currentEventSlug = '';
      cfgInitial.event_uid = '';
      window.quizConfig = {};
      updateActiveHeader('');
      renderCurrentEventIndicator('', '', false);
      updateEventButtons('');
      eventDependentSections.forEach(sec => { sec.hidden = true; });
      return;
    }
    const match = list.find(ev => ev.uid === currentEventUid);
    if (match) {
      currentEventName = match.name || currentEventName;
      currentEventSlug = match.slug || currentEventSlug;
      updateActiveHeader(currentEventName);
      renderCurrentEventIndicator(currentEventName, currentEventUid, availableEvents.length > 0);
      updateEventButtons(currentEventUid);
      eventDependentSections.forEach(sec => { sec.hidden = !currentEventUid; });
      updateDashboardShareLinks();
    } else {
      currentEventUid = '';
      currentEventName = '';
      currentEventSlug = '';
      cfgInitial.event_uid = '';
      window.quizConfig = {};
      updateDashboardShareLinks();
      updateActiveHeader('');
      renderCurrentEventIndicator('', '', availableEvents.length > 0);
      updateEventButtons('');
      eventDependentSections.forEach(sec => { sec.hidden = true; });
    }
  }

  renderCurrentEventIndicator(currentEventName, currentEventUid, true);
  updateActiveHeader(currentEventName);
  updateEventButtons(currentEventUid);
  eventDependentSections.forEach(sec => { sec.hidden = !currentEventUid; });
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

    apiFetch('/events.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify('Veranstaltungen gespeichert', 'success');
        syncCurrentEventState(selectable);
        highlightCurrentEvent();
      })
      .catch(() => notify('Fehler beim Speichern', 'danger'));
  }

  function highlightCurrentEvent() {
    const normalizedCurrent = normalizeId(currentEventUid);
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
    const normalizedCurrentUid = normalizeId(currentEventUid);

    if (normalizedUid === normalizedCurrentUid) {
      // Short-circuit when selecting the already active event to avoid redundant network calls
      highlightCurrentEvent();
      return Promise.resolve();
    }

    if (switchPending || lastSwitchFailed) {
      highlightCurrentEvent();
      return Promise.resolve();
    }
    const prevUid = currentEventUid;
    const prevName = currentEventName;
    const prevSlug = currentEventSlug;
    return switchEvent(normalizedUid, name)
      .then(cfg => {
        currentEventUid = normalizedUid;
        currentEventName = currentEventUid ? (name || currentEventName) : '';
        const matched = availableEvents.find(ev => normalizeId(ev.uid) === currentEventUid);
        currentEventSlug = matched?.slug || currentEventSlug;
        updateDashboardShareLinks();
        cfgInitial.event_uid = currentEventUid;
        Object.assign(cfgInitial, cfg);
        dashboardQrCatalogs = [];
        dashboardQrFetchEpoch += 1;
        window.quizConfig = cfg || {};
        updateActiveHeader(currentEventName);
        renderCurrentEventIndicator(currentEventName, currentEventUid);
        updateEventButtons(currentEventUid);
        eventDependentSections.forEach(sec => { sec.hidden = !currentEventUid; });
        const url = new URL(window.location);
        if (currentEventUid) url.searchParams.set('event', currentEventUid); else url.searchParams.delete('event');
        if (window.history && window.history.replaceState) {
          window.history.replaceState(null, '', url.toString());
        }
        highlightCurrentEvent();
        syncRandomNameOptionsState();
        invalidateRandomNamePreview();
      })
      .catch(err => {
        console.error(err);
        notify(err.message || 'Fehler beim Wechseln des Events', 'danger');
        currentEventUid = prevUid;
        currentEventName = prevName;
        updateActiveHeader(prevName);
        renderCurrentEventIndicator(prevName, prevUid);
        updateEventButtons(prevUid);
        eventDependentSections.forEach(sec => { sec.hidden = !prevUid; });
        const url = new URL(window.location);
        if (prevUid) url.searchParams.set('event', prevUid); else url.searchParams.delete('event');
        if (window.history && window.history.replaceState) {
          window.history.replaceState(null, '', url.toString());
        }
        currentEventSlug = prevSlug;
        updateDashboardShareLinks();
        highlightCurrentEvent();
      });
  }

  if (eventsListEl) {
    const labels = eventsListEl.dataset || {};
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
          input.checked = normalizedId === normalizeId(currentEventUid);
          input.addEventListener('change', () => {
            if (!input.checked) return;
            if (switchPending || lastSwitchFailed) {
              highlightCurrentEvent();
              return;
            }
            const twin = eventsCardsEl?.querySelector(`input[name="currentEventCard"][data-id="${normalizedId}"]`);
            if (twin) twin.checked = true;
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
          input.checked = normalizedId === normalizeId(currentEventUid);
          input.addEventListener('change', () => {
            if (!input.checked) return;
            if (switchPending || lastSwitchFailed) {
              highlightCurrentEvent();
              return;
            }
            const twin = eventsListEl.querySelector(`input[name="currentEventList"][data-id="${normalizedId}"]`);
            if (twin) twin.checked = true;
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
          delBtn.setAttribute('aria-label', window.transDelete || 'Löschen');
          delBtn.setAttribute('uk-tooltip', 'title: ' + (window.transDelete || 'Löschen') + '; pos: left');
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
          delBtn.setAttribute('aria-label', window.transDelete || 'Löschen');
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
        + `<button id="eventEditCancel" class="uk-button uk-button-default" type="button">${window.transCancel || 'Abbrechen'}</button>`
        + `<button id="eventEditSave" class="uk-button uk-button-primary" type="button">${window.transSave || 'Speichern'}</button>`
        + '</div>'
        + '</div>';
      document.body.appendChild(modal);
    }
    eventManager = new TableManager({
      tbody: eventsListEl,
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
      renderCurrentEventIndicator(currentEventName, currentEventUid, false);
      updateEventButtons(currentEventUid);
      eventDependentSections.forEach(sec => { sec.hidden = !currentEventUid; });
    }
    if (eventManager) {
      apiFetch('/events.json', { headers: { 'Accept': 'application/json' } })
        .then(r => {
          if (!r.ok) {
            if (r.status === 401 || r.status === 403) {
              notify('Bitte einloggen', 'warning');
            }
            throw new Error(`HTTP ${r.status}`);
          }
          return r.json();
        })
        .then(data => {
          const list = data.map(d => createEventItem(d));
          eventManager.render(list);
          highlightCurrentEvent();
          updateEventsCardsEmptyState();
          syncCurrentEventState(list);
          if (initialEmpty && list.length === 0) {
            notify('Keine Events gefunden', 'warning');
          }
        })
        .catch(err => {
          console.error(err);
          const message = err instanceof TypeError
            ? transEventsFetchError
            : (err.message && err.message.trim() ? err.message : transEventsFetchError);
          notify(message, 'warning');
          if (!eventsCardsEl || eventsCardsEl.children.length === 0) {
            updateEventsCardsEmptyState({ force: true, useError: true });
          }
        });
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
      + `<button id="teamDeleteCancel" class="uk-button uk-button-default uk-modal-close" type="button">${window.transCancel || 'Abbrechen'}</button>`
      + `<button id="teamDeleteConfirm" class="uk-button uk-button-danger" type="button">${window.transDelete || 'Löschen'}</button>`
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
      + `<button id="teamEditCancel" class="uk-button uk-button-default" type="button">${window.transCancel || 'Abbrechen'}</button>`
      + `<button id="teamEditSave" class="uk-button uk-button-primary" type="button">${window.transSave || 'Speichern'}</button>`
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
          delBtn.setAttribute('aria-label', window.transDelete || 'Löschen');
          delBtn.setAttribute('uk-tooltip', 'title: ' + (window.transDelete || 'Löschen') + '; pos: left');
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
          delBtn.setAttribute('aria-label', window.transDelete || 'Löschen');
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
          teamEditError.textContent = 'Name darf nicht leer sein';
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
    apiFetch('/teams.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(names)
    })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        if (show) notify('Liste gespeichert', 'success');
      })
      .catch(err => {
        console.error(err);
        if (retries > 0) {
          notify('Fehler beim Speichern, versuche es erneut …', 'warning');
          setTimeout(() => saveTeamList(list, show, retries - 1), 1000);
        } else {
          notify('Fehler beim Speichern', 'danger');
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
    const replacement = trimmed !== '' ? trimmed : '…';
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
    apiFetch('/teams.json', { method: 'DELETE' })
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
    window.open(withBase('/results.pdf?team=' + encodeURIComponent(teamName)), '_blank');
  }

  function loadTeamList() {
    if (!teamManager) return;
    teamManager.setColumnLoading('name', true);
    apiFetch('/teams.json', { headers: { 'Accept': 'application/json' } })
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


  // --------- Benutzer ---------
  const usersListEl = managementSection?.querySelector('#usersList') || null;
  const usersCardsEl = managementSection?.querySelector('#usersCards') || null;
  const userAddBtn = managementSection?.querySelector('#userAddBtn') || null;
  const userPassModal = managementSection && window.UIkit ? UIkit.modal('#userPassModal') : null;
  const userPassInput = managementSection?.querySelector('#userPassInput') || null;
  const userPassRepeat = managementSection?.querySelector('#userPassRepeat') || null;
  const userPassForm = managementSection?.querySelector('#userPassForm') || null;
  const usersPaginationEl = managementSection?.querySelector('#usersPagination') || null;
  const labelUsername = usersListEl?.dataset.labelUsername || 'Benutzername';
  const labelRole = usersListEl?.dataset.labelRole || 'Rolle';
  const labelNamespaces = usersListEl?.dataset.labelNamespaces || 'Namespaces';
  const labelActive = usersListEl?.dataset.labelActive || 'Aktiv';
  const rawAvailableNamespaces = Array.isArray(window.availableNamespaces)
    ? window.availableNamespaces
    : [];
  const normalizeNamespaceEntry = (entry) => {
    if (!entry) {
      return null;
    }
    if (typeof entry === 'string') {
      const namespace = entry.trim().toLowerCase();
      return namespace ? { namespace, label: null, is_active: true } : null;
    }
    if (typeof entry === 'object') {
      const namespace = String(entry.namespace || '').trim().toLowerCase();
      if (!namespace) {
        return null;
      }
      const label = entry.label ? String(entry.label).trim() : null;
      const isActive = entry.is_active !== false;
      return { namespace, label: label || null, is_active: isActive };
    }
    return null;
  };
  const normalizedAvailableEntries = rawAvailableNamespaces
    .map(normalizeNamespaceEntry)
    .filter(Boolean);
  if (normalizedAvailableEntries.length === 0) {
    normalizedAvailableEntries.push({
      namespace: window.defaultNamespace || 'default',
      label: null,
      is_active: true
    });
  }
  const availableNamespaces = normalizedAvailableEntries
    .filter(entry => entry.is_active !== false)
    .map(entry => entry.namespace);
  if (availableNamespaces.length === 0) {
    availableNamespaces.push(window.defaultNamespace || 'default');
  }
  const availableNamespaceLabels = new Map(
    normalizedAvailableEntries.map(entry => [entry.namespace, entry.label])
  );
  const defaultNamespace = window.defaultNamespace || 'default';
  const canEditNamespaces = window.currentUserRole === 'admin';
  const namespaceActiveLabel = window.transNamespaceActiveLabel || 'Aktiv';
  const namespaceDefaultLabel = window.transNamespaceDefaultLabel || 'Standard';
  const namespaceNoneLabel = window.transNamespaceNone || '-';
  const USERS_PER_PAGE = 50;
  const userSectionActive = usersListEl?.closest('li')?.classList.contains('uk-active');
  let currentUserId = null;
  let userManager;

  function normalizeUserNamespaces(item) {
    const existing = Array.isArray(item.namespaces) ? item.namespaces : [];
    const normalized = [];
    existing.forEach(entry => {
      const namespace = typeof entry === 'string' ? entry : entry?.namespace;
      if (!namespace) return;
      const value = String(namespace).trim().toLowerCase();
      if (!value || normalized.some(v => v.namespace === value)) return;
      normalized.push({
        namespace: value,
        is_default: Boolean(entry?.is_default)
      });
    });
    if (normalized.length === 0) {
      normalized.push({ namespace: defaultNamespace, is_default: true });
    }
    if (!normalized.some(entry => entry.is_default)) {
      normalized[0].is_default = true;
    }
    item.namespaces = normalized;
    return normalized;
  }

  function getDefaultNamespace(item) {
    const namespaces = normalizeUserNamespaces(item);
    const selected = namespaces.find(entry => entry.is_default);
    return selected?.namespace || namespaces[0]?.namespace || defaultNamespace;
  }

  function renderUsers(list = []) {
    const data = list.map(u => ({
      ...u,
      id: u.id ?? crypto.randomUUID(),
      role: u.role || (window.roles && window.roles[0]) || '',
      password: '',
      namespaces: Array.isArray(u.namespaces) ? u.namespaces : []
    }));
    data.forEach(item => normalizeUserNamespaces(item));
    userManager.render(data);
  }

  const getResponseErrorText = (payload) => {
    if (!payload) {
      return '';
    }
    if (typeof payload === 'string') {
      return payload.trim();
    }
    const candidate =
      payload.error ||
      payload.message ||
      payload.detail ||
      payload.title;
    if (typeof candidate === 'string') {
      return candidate.trim();
    }
    if (Array.isArray(payload.errors) && payload.errors.length) {
      const first = payload.errors.find(entry => typeof entry === 'string');
      if (first) {
        return first.trim();
      }
    }
    if (payload.errors && typeof payload.errors === 'object') {
      const firstKey = Object.keys(payload.errors)[0];
      const entry = payload.errors[firstKey];
      if (Array.isArray(entry) && entry.length && typeof entry[0] === 'string') {
        return entry[0].trim();
      }
      if (typeof entry === 'string') {
        return entry.trim();
      }
    }
    return '';
  };

  const getUserConflictMessage = (payload) => {
    const hint =
      payload?.conflict ||
      payload?.field ||
      payload?.code ||
      payload?.reason ||
      '';
    const normalized = String(hint || getResponseErrorText(payload)).toLowerCase();
    if (normalized.includes('email') || normalized.includes('e-mail') || normalized.includes('mail')) {
      return 'E-Mail bereits vergeben';
    }
    if (normalized.includes('rolle') || normalized.includes('role')) {
      return 'Rolle bereits vergeben';
    }
    if (normalized.includes('user') || normalized.includes('benutzer') || normalized.includes('name')) {
      return 'Benutzername bereits vergeben';
    }
    const fallback = getResponseErrorText(payload);
    return fallback || 'Benutzername bereits vergeben';
  };

  const readErrorPayload = async (response) => {
    if (!response || response.ok) {
      return null;
    }
    try {
      return await response.clone().json();
    } catch (error) {
      return null;
    }
  };

  function saveUsers(list = userManager?.getData() || []) {
    if (list.some(u => !u.username?.trim())) {
      notify('Benutzername darf nicht leer sein', 'warning');
      return;
    }
    const payload = list
      .map((u, index) => ({
        id: u.id && !isNaN(u.id) ? parseInt(u.id, 10) : undefined,
        username: u.username?.trim(),
        role: u.role,
        active: u.active !== false,
        password: u.password || '',
        namespaces: Array.isArray(u.namespaces) ? u.namespaces : [],
        position: index
      }))
      .filter(u => u.username);
    apiFetch('/users.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(async r => {
        const errorPayload = await readErrorPayload(r);
        if (r.status === 409) {
          notify(getUserConflictMessage(errorPayload), 'danger');
          return Promise.reject();
        }
        if (!r.ok) {
          const message = getResponseErrorText(errorPayload);
          if (r.status === 400 || r.status === 422) {
            notify(message || 'Benutzername nicht erlaubt', 'danger');
            return Promise.reject();
          }
          if (message) {
            notify(message, 'danger');
            return Promise.reject();
          }
          throw new Error(r.statusText);
        }
        return r.json();
      })
      .then(data => {
        renderUsers(data);
        notify('Liste gespeichert', 'success');
      })
      .catch(err => {
        if (err) {
          console.error(err);
          notify('Fehler beim Speichern', 'danger');
        }
      });
  }

  function removeUser(id) {
    const list = userManager.getData();
    const idx = list.findIndex(u => u.id === id);
    if (idx !== -1) {
      list.splice(idx, 1);
      userManager.render(list);
      saveUsers(list);
    }
  }

  function openPassModal(id) {
    currentUserId = id;
    if (userPassInput) userPassInput.value = '';
    if (userPassRepeat) userPassRepeat.value = '';
    userPassModal?.show();
  }

  function addUser() {
    const list = userManager.getData();
    const id = crypto.randomUUID();
    list.push({
      id,
      username: '',
      role: (window.roles && window.roles[0]) || '',
      active: true,
      password: '',
      namespaces: [{ namespace: defaultNamespace, is_default: true }]
    });
    userManager.render(list);
    const cell = usersListEl?.querySelector(`[data-id="${id}"][data-key="username"]`);
    if (cell) {
      openUserEditor(cell);
    }
  }

  const userNameModal = managementSection && window.UIkit ? UIkit.modal('#userNameModal') : null;
  const userNameForm = managementSection?.querySelector('#userNameForm') || null;
  const userNameInput = managementSection?.querySelector('#userNameInput') || null;

  function openUserEditor(cell) {
    const id = cell?.dataset.id;
    const key = cell?.dataset.key;
    const item = userManager.getData().find(u => u.id === id);
    if (!item || key !== 'username') return;
    currentUserId = id;
    userNameInput.value = item.username || '';
    userNameModal?.show();
  }

  userNameForm?.addEventListener('submit', e => {
    e.preventDefault();
    const list = userManager.getData();
    const item = list.find(u => u.id === currentUserId);
    if (!item) {
      userNameModal?.hide();
      return;
    }
    const value = userNameInput.value.trim();
    if (!value) {
      notify(window.transUsernameRequired || 'Benutzername darf nicht leer sein', 'warning');
      return;
    }
    item.username = value;
    userManager.render(list);
    saveUsers(list);
    userNameModal?.hide();
  });

  function normalizeNamespaceList(values = []) {
    const normalized = [];
    values.forEach(value => {
      if (!value) return;
      const normalizedValue = String(value).trim().toLowerCase();
      if (normalizedValue && !normalized.includes(normalizedValue)) {
        normalized.push(normalizedValue);
      }
    });
    return normalized;
  }

  function updateNamespaceSelection(item, selectedValues, defaultValue) {
    const normalizedValues = normalizeNamespaceList(selectedValues);
    if (normalizedValues.length === 0) {
      normalizedValues.push(defaultNamespace);
    }

    let activeDefault = defaultValue ? String(defaultValue).trim().toLowerCase() : '';
    if (!normalizedValues.includes(activeDefault)) {
      activeDefault = normalizedValues[0] || defaultNamespace;
    }

    item.namespaces = normalizedValues.map(namespace => ({
      namespace,
      is_default: namespace === activeDefault
    }));

    return {
      namespaces: normalizedValues,
      defaultNamespace: activeDefault
    };
  }

  function buildNamespaceCell(item) {
    normalizeUserNamespaces(item);
    const wrapper = document.createElement('div');
    wrapper.className = 'uk-flex uk-flex-column';

    const select = document.createElement('select');
    select.className = 'uk-select';
    select.multiple = true;
    select.setAttribute('aria-label', labelNamespaces);
    select.disabled = !canEditNamespaces;

    const selectedSet = new Set(normalizeUserNamespaces(item).map(entry => entry.namespace));
    const namespaceOptions = [...availableNamespaces];
    selectedSet.forEach(namespace => {
      if (!namespaceOptions.includes(namespace)) {
        namespaceOptions.push(namespace);
      }
    });
    const formatNamespaceLabel = (namespace) => {
      const label = availableNamespaceLabels.get(namespace);
      if (label) {
        return `${namespace} – ${label}`;
      }
      return namespace;
    };
    namespaceOptions.forEach(namespace => {
      const option = document.createElement('option');
      option.value = namespace;
      option.textContent = formatNamespaceLabel(namespace);
      option.selected = selectedSet.has(namespace);
      select.appendChild(option);
    });

    wrapper.appendChild(select);

    const summary = document.createElement('div');
    summary.className = 'uk-text-small uk-text-muted uk-margin-small-top';
    wrapper.appendChild(summary);

    const activeRow = document.createElement('div');
    activeRow.className = 'uk-text-meta uk-margin-small-top';
    wrapper.appendChild(activeRow);

    let defaultSelect = null;
    if (canEditNamespaces) {
      const defaultRow = document.createElement('div');
      defaultRow.className = 'uk-margin-small-top uk-flex uk-flex-middle';

      const label = document.createElement('span');
      label.className = 'uk-text-meta uk-margin-small-right';
      label.textContent = `${namespaceDefaultLabel}:`;
      defaultRow.appendChild(label);

      defaultSelect = document.createElement('select');
      defaultSelect.className = 'uk-select uk-form-small';
      defaultSelect.setAttribute('aria-label', namespaceDefaultLabel);
      defaultRow.appendChild(defaultSelect);
      wrapper.appendChild(defaultRow);
    }

    const syncDisplay = (commit = false) => {
      const selectedValues = Array.from(select.selectedOptions).map(option => option.value);
      const defaultValue = defaultSelect ? defaultSelect.value : getDefaultNamespace(item);
      const result = updateNamespaceSelection(item, selectedValues, defaultValue);

      if (selectedValues.length === 0) {
        Array.from(select.options).forEach(option => {
          option.selected = option.value === result.defaultNamespace;
        });
      }

      if (defaultSelect) {
        defaultSelect.innerHTML = '';
        result.namespaces.forEach(namespace => {
          const option = document.createElement('option');
          option.value = namespace;
          option.textContent = formatNamespaceLabel(namespace);
          defaultSelect.appendChild(option);
        });
        defaultSelect.value = result.defaultNamespace || '';
      }

      summary.textContent = result.namespaces.length
        ? result.namespaces.map(formatNamespaceLabel).join(', ')
        : namespaceNoneLabel;
      activeRow.textContent = `${namespaceActiveLabel}: ${result.defaultNamespace || namespaceNoneLabel}`;

      if (commit) {
        saveUsers(userManager.getData());
      }
    };

    select.addEventListener('change', () => syncDisplay(true));
    defaultSelect?.addEventListener('change', () => syncDisplay(true));

    syncDisplay(false);

    return wrapper;
  }

  if (usersListEl) {
    const roleTemplate = document.getElementById('userRoleSelect');
    const userColumns = [
      { key: 'username', label: labelUsername, editable: true },
      {
        key: 'role',
        label: labelRole,
        render: item => {
          let select;
          if (roleTemplate) {
            select = roleTemplate.content.firstElementChild.cloneNode(true);
          } else {
            select = document.createElement('select');
            (window.roles || []).forEach(r => {
              const opt = document.createElement('option');
              opt.value = r;
              opt.textContent = r;
              select.appendChild(opt);
            });
          }
          select.value = item.role || (window.roles && window.roles[0]) || '';
          select.addEventListener('change', () => {
            item.role = select.value;
            saveUsers(userManager.getData());
          });
          return select;
        }
      },
      {
        key: 'namespaces',
        label: labelNamespaces,
        render: item => buildNamespaceCell(item)
      },
      {
        key: 'active',
        label: labelActive,
        className: 'uk-table-shrink',
        render: item => {
          const cb = document.createElement('input');
          cb.type = 'checkbox';
          cb.checked = item.active !== false;
          cb.addEventListener('change', () => {
            item.active = cb.checked;
            saveUsers(userManager.getData());
          });
          return cb;
        }
      },
      {
        className: 'uk-table-shrink',
        render: item => {
          const wrapper = document.createElement('div');
          wrapper.className = 'uk-flex uk-flex-middle uk-flex-right';

          const passBtn = document.createElement('button');
          passBtn.type = 'button';
          passBtn.className = 'uk-icon-button qr-action';
          passBtn.setAttribute('uk-icon', 'key');
          passBtn.setAttribute('aria-label', window.transUserPass || 'Passwort setzen');
          passBtn.setAttribute('uk-tooltip', 'title: ' + (window.transUserPass || 'Passwort setzen') + '; pos: left');
          passBtn.addEventListener('click', () => openPassModal(item.id));
          wrapper.appendChild(passBtn);

          const delBtn = document.createElement('button');
          delBtn.type = 'button';
          delBtn.className = 'uk-icon-button qr-action uk-text-danger uk-margin-small-left';
          delBtn.setAttribute('uk-icon', 'trash');
          delBtn.setAttribute('aria-label', window.transDelete || 'Löschen');
          delBtn.setAttribute('uk-tooltip', 'title: ' + (window.transDelete || 'Löschen') + '; pos: left');
          delBtn.addEventListener('click', () => removeUser(item.id));
          wrapper.appendChild(delBtn);

          return wrapper;
        },
        renderCard: item => {
          const wrapper = document.createElement('div');
          wrapper.className = 'uk-flex uk-flex-middle qr-action';

          const passBtn = document.createElement('button');
          passBtn.className = 'uk-icon-button qr-action';
          passBtn.setAttribute('uk-icon', 'key');
          passBtn.setAttribute('aria-label', window.transUserPass || 'Passwort setzen');
          passBtn.addEventListener('click', () => openPassModal(item.id));
          wrapper.appendChild(passBtn);

          const delBtn = document.createElement('button');
          delBtn.className = 'uk-icon-button qr-action uk-text-danger uk-margin-small-left';
          delBtn.setAttribute('uk-icon', 'trash');
          delBtn.setAttribute('aria-label', window.transDelete || 'Löschen');
          delBtn.addEventListener('click', () => removeUser(item.id));
          wrapper.appendChild(delBtn);

          return wrapper;
        }
      }
    ];
    userManager = new TableManager({
      tbody: usersListEl,
      columns: userColumns,
      sortable: true,
      mobileCards: { container: usersCardsEl },
      onEdit: cell => openUserEditor(cell),
      onReorder: () => saveUsers()
    });
    if (usersPaginationEl) {
      userManager.bindPagination(usersPaginationEl, USERS_PER_PAGE);
    }
    if (userSectionActive) {
      userManager.setColumnLoading('username', true);
      apiFetch('/users.json', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
          renderUsers(data);
        })
        .catch(() => {})
        .finally(() => userManager.setColumnLoading('username', false));
    }
  }

  userAddBtn?.addEventListener('click', e => {
    e.preventDefault();
    addUser();
  });

  userPassForm?.addEventListener('submit', e => {
    e.preventDefault();
    if (!userPassInput || !userPassRepeat) return;
    const p1 = userPassInput.value;
    const p2 = userPassRepeat.value;
    if (p1 === '' || p2 === '') {
      notify('Passwort darf nicht leer sein', 'danger');
      return;
    }
    if (p1 !== p2) {
      notify('Passwörter stimmen nicht überein', 'danger');
      return;
    }
    const list = userManager.getData();
    const item = list.find(u => u.id === currentUserId);
    if (item) {
      item.password = p1;
      saveUsers(list);
    }
    userPassModal?.hide();
    userPassInput.value = '';
    userPassRepeat.value = '';
  });

  const importJsonBtn = managementSection?.querySelector('#importJsonBtn') || null;
  const exportJsonBtn = managementSection?.querySelector('#exportJsonBtn') || null;
  const saveDemoBtn = managementSection?.querySelector('#saveDemoBtn') || null;
  const backupTableBody = managementSection?.querySelector('#backupTableBody') || null;
  const tenantTableBody = document.getElementById('tenantTableBody');
  const tenantCards = document.getElementById('tenantCards');
  const tenantSyncBtn = document.getElementById('tenantSyncBtn');
  const tenantSyncBadge = document.getElementById('tenantSyncBadge');
  const tenantExportBtn = document.getElementById('tenantExportBtn');
  const tenantReportBtn = document.getElementById('tenantReportBtn');
  const tenantStatusFilter = document.getElementById('tenantStatusFilter');
  const tenantSearchInput = document.getElementById('tenantSearchInput');
  const TENANT_STATUS_VALUES = new Set([
    'active',
    'canceled',
    'simulated',
    'pending',
    'provisioning',
    'provisioned',
    'failed'
  ]);
  const normalizeTenantStatus = value => {
    if (typeof value !== 'string') {
      return '';
    }
    const normalized = value.trim().toLowerCase();
    return TENANT_STATUS_VALUES.has(normalized) ? normalized : '';
  };
  let tenantColumnBtn = document.getElementById('tenantColumnBtn');
  const tenantTable = tenantTableBody?.closest('table');
  const tenantTableHeadings = tenantTable?.querySelectorAll('thead th') || [];
  const tenantColumnDefs = [
    { key: 'plan', label: 'Abo', thIndex: 1 },
    { key: 'billing', label: 'Rechnungsinfo', thIndex: 2 },
    { key: 'created', label: 'Erstellt', thIndex: 3 }
  ];
  const tenantColumnDefaults = tenantColumnDefs.map(c => c.key);
  let tenantColumns = [...tenantColumnDefaults];
  let initialTenantListHtml = typeof window.initialTenantListHtml === 'string'
    ? window.initialTenantListHtml
    : '';
  let initialTenantHtmlApplied = false;
  let tenantSyncState = null;

  function normalizeTenantSyncState(raw) {
    if (!raw || typeof raw !== 'object') {
      return null;
    }
    const parseIntSafe = value => {
      if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
      }
      const num = parseInt(String(value ?? ''), 10);
      return Number.isNaN(num) ? 0 : num;
    };
    const parseBoolSafe = value => {
      if (typeof value === 'boolean') {
        return value;
      }
      if (typeof value === 'number') {
        return value !== 0;
      }
      if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();
        if (['1', 'true', 'yes', 'on'].includes(normalized)) {
          return true;
        }
        if (['0', 'false', 'no', 'off', ''].includes(normalized)) {
          return false;
        }
      }
      return Boolean(value);
    };
    return {
      last_run_at: typeof raw.last_run_at === 'string' && raw.last_run_at !== '' ? raw.last_run_at : null,
      next_allowed_at: typeof raw.next_allowed_at === 'string' && raw.next_allowed_at !== '' ? raw.next_allowed_at : null,
      cooldown_seconds: parseIntSafe(raw.cooldown_seconds),
      stale_after_seconds: parseIntSafe(raw.stale_after_seconds),
      is_stale: parseBoolSafe(raw.is_stale),
      is_throttled: parseBoolSafe(raw.is_throttled)
    };
  }

  function renderTenantSyncBadge() {
    if (!tenantSyncBadge) {
      return;
    }
    if (!tenantSyncState) {
      tenantSyncBadge.classList.add('uk-hidden');
      tenantSyncBadge.removeAttribute('title');
      tenantSyncBadge.removeAttribute('aria-label');
      return;
    }

    const parseDate = value => {
      if (typeof value !== 'string' || value === '') {
        return null;
      }
      const ms = Date.parse(value);
      return Number.isNaN(ms) ? null : ms;
    };

    const now = Date.now();
    const lastRunMs = tenantSyncState.last_run_at ? parseDate(tenantSyncState.last_run_at) : null;
    const nextAllowedMs = tenantSyncState.next_allowed_at ? parseDate(tenantSyncState.next_allowed_at) : null;
    const staleByAge = lastRunMs === null
      ? true
      : (tenantSyncState.stale_after_seconds > 0
        ? (now - lastRunMs) > tenantSyncState.stale_after_seconds * 1000
        : false);
    const isStale = Boolean(tenantSyncState.is_stale) || staleByAge;
    const computedThrottled = nextAllowedMs !== null ? nextAllowedMs > now : false;
    const isThrottled = nextAllowedMs !== null ? computedThrottled : Boolean(tenantSyncState.is_throttled);

    let text = window.transTenantSyncOk || 'Aktuell';
    let background = '#32d296';
    if (isStale) {
      text = window.transTenantSyncStale || 'Sync nötig';
      background = '#faa05a';
    } else if (isThrottled) {
      text = window.transTenantSyncCooling || 'Wartezeit';
      background = '#1e87f0';
    }

    tenantSyncBadge.textContent = text;
    tenantSyncBadge.style.backgroundColor = background;
    tenantSyncBadge.style.color = '#fff';
    tenantSyncBadge.classList.remove('uk-hidden');

    if (lastRunMs) {
      const formatted = new Date(lastRunMs).toLocaleString();
      const label = `${window.transTenantSyncLastRun || 'Letzter Sync'}: ${formatted}`;
      tenantSyncBadge.title = label;
      tenantSyncBadge.setAttribute('aria-label', label);
    } else {
      tenantSyncBadge.removeAttribute('title');
      tenantSyncBadge.removeAttribute('aria-label');
    }
  }

  function updateTenantSyncState(state) {
    tenantSyncState = normalizeTenantSyncState(state);
    if (typeof window !== 'undefined') {
      window.tenantSyncState = tenantSyncState;
    }
    renderTenantSyncBadge();
  }

  function extractTenantSyncState(doc) {
    if (!doc || typeof doc.getElementById !== 'function') {
      return null;
    }
    const meta = doc.getElementById('tenantSyncMeta');
    if (!meta) {
      return null;
    }
    const { dataset } = meta;
    const parseIntSafe = value => {
      const num = parseInt(String(value ?? ''), 10);
      return Number.isNaN(num) ? 0 : num;
    };
    return {
      last_run_at: dataset.lastRun || null,
      next_allowed_at: dataset.nextAllowed || null,
      cooldown_seconds: parseIntSafe(dataset.cooldown),
      stale_after_seconds: parseIntSafe(dataset.staleAfter),
      is_stale: dataset.isStale === '1',
      is_throttled: dataset.isThrottled === '1'
    };
  }

  function applyTenantListHtml(html) {
    if (typeof html !== 'string' || html.trim() === '') {
      return false;
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const newBody = doc.getElementById('tenantTableBody');
    const newCards = doc.getElementById('tenantCards');
    const metaState = extractTenantSyncState(doc);
    let applied = false;

    if (tenantTableBody) {
      if (newBody) {
        tenantTableBody.innerHTML = newBody.innerHTML;
        applied = true;
      } else {
        tenantTableBody.innerHTML = '';
      }
    }

    if (tenantCards) {
      if (newCards) {
        tenantCards.innerHTML = newCards.innerHTML;
        applied = true;
      } else {
        tenantCards.innerHTML = '';
      }
    }

    if (applied) {
      bindTenantColumnButton();
    }

    if (metaState) {
      updateTenantSyncState(metaState);
    }

    if (applied) {
      updateTenantColumnVisibility();
    }

    return applied;
  }

  if (initialTenantListHtml.trim() !== '') {
    initialTenantHtmlApplied = applyTenantListHtml(initialTenantListHtml);
    window.initialTenantListHtml = '';
    initialTenantListHtml = '';
  }

  function showTenantSpinner() {
    if (tenantTableBody) {
      const columnCount = tenantTableHeadings.length || tenantColumnDefs.length || 1;
      tenantTableBody.innerHTML = `<tr><td colspan="${columnCount}" class="uk-text-center uk-padding"><div uk-spinner></div></td></tr>`;
    }
    if (tenantCards) {
      tenantCards.innerHTML = '<div class="uk-text-center uk-padding"><div uk-spinner></div></div>';
    }
  }

  function refreshTenantList(showSpinner = true) {
    const statusValue = normalizeTenantStatus(tenantStatusFilter?.value || '');
    if (tenantStatusFilter && statusValue !== (tenantStatusFilter.value || '')) {
      tenantStatusFilter.value = '';
    }
    loadTenants(statusValue, tenantSearchInput?.value, showSpinner);
  }

  try {
    const stored = JSON.parse(getStored(STORAGE_KEYS.TENANT_COLUMNS));
    if (Array.isArray(stored)) {
      tenantColumns = tenantColumnDefaults.filter(k => stored.includes(k));
    }
  } catch (_) {}
  tenantColumnDefs.forEach(def => {
    tenantTableHeadings[def.thIndex]?.classList.add('col-' + def.key);
  });
  function updateTenantColumnVisibility() {
    tenantColumnDefs.forEach(def => {
      const visible = tenantColumns.includes(def.key);
      if (tenantTableHeadings[def.thIndex]) {
        tenantTableHeadings[def.thIndex].style.display = visible ? '' : 'none';
      }
      tenantTable?.querySelectorAll('.col-' + def.key).forEach(el => {
        el.style.display = visible ? '' : 'none';
      });
    });
  }
  function handleTenantColumnClick() {
    let modal = document.getElementById('tenantColumnModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'tenantColumnModal';
      modal.setAttribute('uk-modal', '');
      const options = tenantColumnDefs.map(def => {
        const checked = tenantColumns.includes(def.key) ? 'checked' : '';
        return `<label><input class="uk-checkbox" type="checkbox" data-col="${def.key}" ${checked}> ${def.label}</label>`;
      }).join('<br>');
      modal.innerHTML = `<div class="uk-modal-dialog uk-modal-body">
        <h2 class="uk-modal-title">Spalten auswählen</h2>
        <form>${options}</form>
        <p class="uk-text-right">
          <button class="uk-button uk-button-default uk-modal-close" type="button">Abbrechen</button>
          <button class="uk-button uk-button-primary" type="button" id="tenantColumnSave">Speichern</button>
        </p>
      </div>`;
      document.body.appendChild(modal);
      modal.querySelector('#tenantColumnSave').addEventListener('click', () => {
        const selected = Array.from(modal.querySelectorAll('input[type="checkbox"]'))
          .filter(cb => cb.checked)
          .map(cb => cb.dataset.col);
        tenantColumns = tenantColumnDefaults.filter(k => selected.includes(k));
        try { setStored(STORAGE_KEYS.TENANT_COLUMNS, JSON.stringify(tenantColumns)); } catch (_) {}
        updateTenantColumnVisibility();
        refreshTenantList();
        if (window.UIkit) UIkit.modal(modal).hide();
      });
    } else {
      modal.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.checked = tenantColumns.includes(cb.dataset.col);
      });
    }
    if (window.UIkit) UIkit.modal(modal).show();
  }

  function bindTenantColumnButton() {
    tenantColumnBtn = document.getElementById('tenantColumnBtn');
    if (!tenantColumnBtn) {
      return;
    }
    tenantColumnBtn.removeEventListener('click', handleTenantColumnClick);
    tenantColumnBtn.addEventListener('click', handleTenantColumnClick);
  }

  bindTenantColumnButton();
  updateTenantColumnVisibility();
  updateTenantSyncState(window.tenantSyncState || null);

  tenantStatusFilter?.addEventListener('change', () => {
    refreshTenantList();
  });

  tenantSearchInput?.addEventListener('input', () => {
    refreshTenantList();
  });

  function loadBackups() {
    if (!backupTableBody) return;
    apiFetch('/backups')
      .then(r => {
        if (!r.ok) {
          return r.json().then(data => {
            throw new Error(data.error || r.statusText);
          });
        }

        return r.text();
      })
      .then(html => {
        backupTableBody.innerHTML = html;
      })
      .catch(err => {
        backupTableBody.innerHTML = '<tr><td colspan="2">Fehler</td></tr>';
        notify(err.message || 'Fehlende Berechtigungen oder Ordner', 'danger');
      });
  }

  backupTableBody?.addEventListener('click', e => {
    const btn = e.target.closest('button[data-action][data-name]');
    if (!btn) return;
    const { action, name } = btn.dataset;
    if (!name) return;
    if (action === 'restore') {
      apiFetch('/backups/' + encodeURIComponent(name) + '/restore', { method: 'POST' })
        .then(r => {
          if (!r.ok) throw new Error(r.statusText);
          notify('Import abgeschlossen', 'success');
        })
        .catch(() => notify('Fehler beim Import', 'danger'));
    } else if (action === 'download') {
      apiFetch('/backups/' + encodeURIComponent(name) + '/download')
        .then(r => r.blob())
        .then(blob => {
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = name + '.zip';
          a.click();
          URL.revokeObjectURL(url);
        })
        .catch(() => notify('Fehler beim Download', 'danger'));
    } else if (action === 'delete') {
      apiFetch('/backups/' + encodeURIComponent(name), { method: 'DELETE' })
        .then(r => {
          if (r.ok) {
            loadBackups();
            return;
          }
          return r.json().then(data => {
            throw new Error(data.error || r.statusText);
          });
        })
        .catch(err => notify(err.message || 'Fehler beim Löschen', 'danger'));
    }
  });
  importJsonBtn?.addEventListener('click', e => {
    e.preventDefault();
    apiFetch('/restore-default', { method: 'POST' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify('Import abgeschlossen', 'success');
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Import', 'danger');
      });
  });

  saveDemoBtn?.addEventListener('click', e => {
    e.preventDefault();
    apiFetch('/export-default', { method: 'POST' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify('Demodaten gespeichert', 'success');
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Speichern', 'danger');
      });
  });


  exportJsonBtn?.addEventListener('click', e => {
    e.preventDefault();
    apiFetch('/export', { method: 'POST' })
      .then(r => {
        if (!r.ok) {
          return r.json().then(data => {
            throw new Error(data.error || r.statusText);
          });
        }
        notify('Export abgeschlossen', 'success');
        loadBackups();
      })
      .catch(err => {
        console.error(err);
        notify(err.message || 'Fehlende Berechtigungen oder Ordner', 'danger');
      });
  });

  tenantExportBtn?.addEventListener('click', e => {
    e.preventDefault();
    apiFetch('/tenants/export')
      .then(async r => {
        if (!r.ok) throw new Error('Fehler');
        const blob = await r.blob();
        const disposition = r.headers.get('Content-Disposition') || '';
        let filename = 'tenants.csv';
        const match = /filename="?([^";]+)"?/i.exec(disposition);
        if (match) {
          filename = match[1];
        }
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
      })
      .catch(() => notify('Fehler beim Export', 'danger'));
  });

  tenantReportBtn?.addEventListener('click', e => {
    e.preventDefault();
    apiFetch('/tenants/report')
      .then(async r => {
        if (!r.ok) throw new Error('Fehler');
        const contentType = r.headers.get('Content-Type') || '';
        const disposition = r.headers.get('Content-Disposition') || '';
        if (contentType.includes('pdf')) {
          const blob = await r.blob();
          const url = window.URL.createObjectURL(blob);
          window.open(url, '_blank');
          window.URL.revokeObjectURL(url);
          return;
        }
        if (contentType.includes('html')) {
          const text = await r.text();
          const w = window.open('', '_blank');
          if (w) {
            w.document.write(text);
            w.document.close();
          }
          return;
        }
        const blob = await r.blob();
        let filename = 'tenant-report.csv';
        const match = /filename="?([^";]+)"?/i.exec(disposition);
        if (match) {
          filename = match[1];
        }
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
      })
      .catch(() => notify('Fehler beim Bericht', 'danger'));
  });

  tenantSyncBtn?.addEventListener('click', e => {
    e.preventDefault();
    const original = tenantSyncBtn.innerHTML;
    tenantSyncBtn.disabled = true;
    tenantSyncBtn.innerHTML = '<div uk-spinner></div>';
    const params = new URLSearchParams();
    const normalizedStatus = normalizeTenantStatus(tenantStatusFilter?.value || '');
    const searchQueryRaw = typeof tenantSearchInput?.value === 'string' ? tenantSearchInput.value : '';
    const searchQuery = searchQueryRaw.trim();
    if (normalizedStatus) params.set('status', normalizedStatus);
    if (searchQuery) params.set('query', searchQuery);
    const syncUrl = '/tenants/sync' + (params.toString() ? ('?' + params.toString()) : '');
    let shouldRefresh = true;
    apiFetch(syncUrl, { method: 'POST' })
      .then(async r => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
          const error = new Error(typeof data?.error === 'string' ? data.error : 'sync-failed');
          error.data = data;
          throw error;
        }
        return data;
      })
      .then(data => {
        if (typeof data?.html === 'string' && applyTenantListHtml(data.html)) {
          shouldRefresh = false;
        }
        if (data?.sync) {
          updateTenantSyncState(data.sync);
        }
        if (data?.throttled) {
          notify(window.transTenantSyncThrottled || 'Sync läuft bereits – bitte später erneut versuchen', 'warning');
          return;
        }

        const importedRaw = data?.imported;
        const importedNumber = typeof importedRaw === 'number'
          ? importedRaw
          : (typeof importedRaw === 'string' && importedRaw.trim() !== '' ? Number(importedRaw) : NaN);
        const imported = Number.isFinite(importedNumber) ? importedNumber : 0;

        if (imported > 0) {
          notify(window.transTenantSyncSuccess || 'Mandanten eingelesen', 'success');
        } else {
          const template = window.transTenantSyncNoChanges || 'Keine neuen Mandanten gefunden ({count} importiert)';
          const message = template.replace('{count}', String(imported));
          notify(message, 'warning');
        }
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Synchronisieren', 'danger');
      })
      .finally(() => {
        if (shouldRefresh) {
          refreshTenantList();
        }
        tenantSyncBtn.disabled = false;
        tenantSyncBtn.innerHTML = original;
      });
  });

  function loadTenants(status = '', query = '', showSpinner = true) {
    if (!tenantTableBody) return;
    if (typeof window.domainType !== 'undefined' && window.domainType !== 'main') {
      notify('MAIN_DOMAIN falsch konfiguriert – Mandantenliste nicht geladen', 'warning');
      return;
    }
    if (showSpinner) {
      showTenantSpinner();
    }
    const params = new URLSearchParams();
    const normalizedStatus = normalizeTenantStatus(status || '');
    const normalizedQuery = typeof query === 'string' ? query : '';
    if (normalizedStatus) params.set('status', normalizedStatus);
    if (normalizedQuery) params.set('query', normalizedQuery);
    const url = '/tenants' + (params.toString() ? ('?' + params.toString()) : '');
    apiFetch(url, { headers: { 'Accept': 'text/html' } })
      .then(r => r.ok ? r.text() : Promise.reject(r))
      .then((html) => {
        if (!applyTenantListHtml(html) && tenantTableBody) {
          tenantTableBody.innerHTML = '';
        }
      })
      .catch(() => notify('Mandanten konnten nicht geladen werden', 'danger'));
  }

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
    const nameEl = document.getElementById('summaryEventName');
    const descEl = document.getElementById('summaryEventDesc');
    const qrImg = document.getElementById('summaryEventQr');
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
      apiFetch('/events.json', opts).then(r => r.json()).catch(() => [])
    ]).then(([cfg, events]) => {
      if (!isActive()) {
        return;
      }
      const nextConfig = (cfg && typeof cfg === 'object') ? cfg : {};
      populateEventSelectors(events);
      const selectableHasEvents = availableEvents.length > 0;
      const previousUid = currentEventUid;
      let ev = events.find(e => e.uid === currentEventUid) || null;
      if (!ev && previousUid) {
        const configClone = replaceInitialConfig({});
        currentEventUid = '';
        currentEventName = '';
        cfgInitial.event_uid = currentEventUid;
        window.quizConfig = configClone;
        ev = {};
      } else {
        const configClone = replaceInitialConfig(nextConfig);
        if (!ev) {
          currentEventUid = '';
          currentEventName = '';
          ev = {};
        } else {
          currentEventName = ev.name || currentEventName;
        }
        cfgInitial.event_uid = currentEventUid;
        window.quizConfig = configClone;
      }
      currentEventSlug = ev?.slug || (currentEventUid ? currentEventSlug : '');
      updateDashboardShareLinks();
      eventDependentSections.forEach(sec => { sec.hidden = !currentEventUid; });
      renderCurrentEventIndicator(currentEventName, currentEventUid, selectableHasEvents);
      updateEventButtons(currentEventUid);
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
      const allowedPlans = ['starter', 'standard', 'professional'];
      const allowedBilling = ['credit'];
      if (!allowedPlans.includes(data.plan)) delete data.plan;
      if (!allowedBilling.includes(data.billing_info)) delete data.billing_info;
      apiFetch('/admin/profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify('Profil gespeichert', 'success');
      }).catch(() => notify('Fehler beim Speichern', 'danger'));
    });

    welcomeMailBtn?.addEventListener('click', e => {
      e.preventDefault();
      apiFetch('/admin/profile/welcome', { method: 'POST' })
        .then(r => {
          if (!r.ok) throw new Error('failed');
          notify('Willkommensmail gesendet', 'success');
        })
        .catch(() => notify('Fehler beim Senden', 'danger'));
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
          .catch(() => notify('Fehler', 'danger'));
        return;
      }
      if (!plan) return;
      const payload = { plan, embedded: true };
      if (emailInput) {
        const email = emailInput.value.trim();
        if (email === '') {
          emailInput.classList.add('uk-form-danger');
          emailInput.focus();
          notify('Bitte E-Mail-Adresse eingeben', 'warning');
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
          let msg = 'Fehler beim Starten der Zahlung';
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
        notify('Fehler beim Starten der Zahlung', 'danger', 0);
      }
    });

  function updateHeading(el, name) {
    if (!el) return;
    el.textContent = name ? `${name} – ${el.dataset.title}` : el.dataset.title;
  }

  document.addEventListener('event:changed', e => {
    const detail = e.detail || {};
    const { uid, name, config, epoch, pending } = detail;
    if (typeof epoch === 'number' && !isCurrentEpoch(epoch)) {
      return;
    }
    if (pending) {
      currentEventUid = '';
      currentEventName = '';
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
    currentEventUid = uid || '';
    currentEventName = currentEventUid ? (name || currentEventName) : '';
    const nextConfig = (config && typeof config === 'object') ? config : {};
    const configClone = replaceInitialConfig(nextConfig);
    cfgInitial.event_uid = currentEventUid;
    window.quizConfig = configClone;
    renderCfg(configClone);
    updateActiveHeader(currentEventName);
    renderCurrentEventIndicator(currentEventName, currentEventUid, availableEvents.length > 0);
    updateEventButtons(currentEventUid);
    updateHeading(eventSettingsHeading, currentEventName);
    updateHeading(catalogsHeading, currentEventName);
    updateHeading(questionsHeading, currentEventName);
    eventDependentSections.forEach(sec => { sec.hidden = !currentEventUid; });
    highlightCurrentEvent();
    if (catSelect) loadCatalogs();
    if (teamListEl) loadTeamList();
    loadSummary();
  });

  // Page editors are handled in trumbowyg-pages.js

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
    const form = namespaceManager.querySelector('[data-namespace-form]');
    const input = namespaceManager.querySelector('[data-namespace-input]');
    const labelInput = namespaceManager.querySelector('[data-namespace-label-input]');
    const formError = namespaceManager.querySelector('[data-namespace-error]');
    const labelSave = namespaceManager.dataset.labelSave || 'Save';
    const labelDelete = namespaceManager.dataset.labelDelete || 'Delete';
    const labelDefault = namespaceManager.dataset.labelDefault || 'Default';
    const labelInactive = namespaceManager.dataset.labelInactive || 'Inactive';
    const namespacePattern = namespaceManager.dataset.namespacePattern || '^[a-z0-9][a-z0-9-]*$';
    const namespaceMaxLength = Number.parseInt(namespaceManager.dataset.namespaceMaxLength || '100', 10) || 100;
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

    const normalizeNamespace = value => String(value || '').trim().toLowerCase();
    const normalizeLabel = value => {
      const normalized = String(value ?? '').trim();
      return normalized === '' ? null : normalized;
    };
    const namespaceRegex = new RegExp(namespacePattern);
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
    const buildErrorElement = element => {
      if (!element?.parentElement) {
        return null;
      }
      let error = element.parentElement.querySelector('[data-namespace-error]');
      if (!error) {
        error = document.createElement('p');
        error.className = 'uk-text-danger uk-margin-small-top uk-hidden';
        error.dataset.namespaceError = '';
        element.parentElement.appendChild(error);
      }
      if (!error.id) {
        error.id = `namespace-error-${Math.random().toString(36).slice(2, 10)}`;
      }
      return error;
    };
    const showNamespaceError = (element, message) => {
      if (!element) {
        return;
      }
      const error = element === input ? formError || buildErrorElement(element) : buildErrorElement(element);
      if (error) {
        error.textContent = message;
        error.classList.remove('uk-hidden');
      }
      element.classList.add('uk-form-danger');
      element.setAttribute('aria-invalid', 'true');
      if (error?.id) {
        element.setAttribute('aria-describedby', error.id);
      }
    };
    const clearNamespaceError = element => {
      if (!element) {
        return;
      }
      const error = element.parentElement?.querySelector('[data-namespace-error]');
      if (error) {
        error.textContent = '';
        error.classList.add('uk-hidden');
      }
      element.classList.remove('uk-form-danger');
      element.removeAttribute('aria-invalid');
      element.removeAttribute('aria-describedby');
    };

    const renderMessageRow = message => {
      if (!tableBody) {
        return;
      }
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = columnCount;
      td.textContent = message;
      tr.appendChild(td);
      tableBody.appendChild(tr);
    };

    const parseJsonResponse = res => {
      return res
        .json()
        .catch(() => ({}))
        .then(data => {
          if (!res.ok) {
            throw new Error(data?.error || messages.error);
          }
          return data;
        });
    };

    const loadNamespaces = () => {
      if (!tableBody) {
        return;
      }
      tableBody.innerHTML = '';
      if (messages.loading) {
        renderMessageRow(messages.loading);
      }
      apiFetch(listUrl)
        .then(parseJsonResponse)
        .then(data => {
          const entries = Array.isArray(data?.namespaces) ? data.namespaces : [];
          tableBody.innerHTML = '';
          if (!entries.length) {
            renderMessageRow(messages.empty);
            return;
          }
          entries.forEach(item => {
            const namespaceValue = typeof item.namespace === 'string' ? item.namespace : '';
            const labelValue = typeof item.label === 'string' ? item.label : '';
            const isDefault = Boolean(item.is_default) || namespaceValue === defaultNamespace;
            const isActive = item.is_active !== false;
            const isInactive = !isActive;

            const tr = document.createElement('tr');
            if (isInactive) {
              tr.classList.add('uk-text-muted');
            }

            const nameCell = document.createElement('td');
            const nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.className = 'uk-input';
            nameInput.value = namespaceValue;
            nameInput.dataset.original = namespaceValue;
            nameInput.disabled = isDefault;
            nameInput.maxLength = namespaceMaxLength;
            nameCell.appendChild(nameInput);
            tr.appendChild(nameCell);

            const labelCell = document.createElement('td');
            const labelInput = document.createElement('input');
            labelInput.type = 'text';
            labelInput.className = 'uk-input';
            labelInput.value = labelValue;
            labelInput.dataset.original = normalizeLabel(labelValue) ?? '';
            labelInput.disabled = isDefault;
            labelCell.appendChild(labelInput);
            tr.appendChild(labelCell);

            const statusCell = document.createElement('td');
            statusCell.textContent = isDefault ? labelDefault : (isInactive ? labelInactive : '-');
            tr.appendChild(statusCell);

            const actionCell = document.createElement('td');
            actionCell.className = 'uk-text-center';

            const saveButton = document.createElement('button');
            saveButton.type = 'button';
            saveButton.className = 'uk-button uk-button-primary uk-button-small uk-margin-small-right';
            saveButton.textContent = labelSave;
            saveButton.disabled = isDefault;
            actionCell.appendChild(saveButton);

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'uk-button uk-button-danger uk-button-small';
            deleteButton.textContent = labelDelete;
            deleteButton.disabled = isDefault || isInactive;
            actionCell.appendChild(deleteButton);

            saveButton.addEventListener('click', () => {
              const nextValue = normalizeNamespace(nameInput.value);
              const nextLabel = normalizeLabel(labelInput.value);
              const validationMessage = getNamespaceError(nextValue);
              if (validationMessage) {
                showNamespaceError(nameInput, validationMessage);
                notify(validationMessage, 'warning');
                nameInput.focus();
                return;
              }
              clearNamespaceError(nameInput);
              if (nextValue === nameInput.dataset.original && (nextLabel ?? '') === labelInput.dataset.original) {
                return;
              }
              saveButton.disabled = true;
              nameInput.disabled = true;
              labelInput.disabled = true;
              apiFetch(buildUrl(updateUrlTemplate, namespaceValue), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ namespace: nextValue, label: nextLabel })
              })
                .then(parseJsonResponse)
                .then(() => {
                  notify(messages.updated, 'success');
                  loadNamespaces();
                })
                .catch(err => {
                  const message = err?.message || messages.error;
                  if (message === messages.defaultLocked || message === messages.inUse) {
                    notify(message, 'warning');
                  } else {
                    notify(message, 'danger');
                  }
                })
                .finally(() => {
                  saveButton.disabled = isDefault;
                  nameInput.disabled = isDefault;
                  labelInput.disabled = isDefault;
                });
            });

            deleteButton.addEventListener('click', () => {
              if (!window.confirm(messages.confirmDelete)) {
                return;
              }
              deleteButton.disabled = true;
              apiFetch(buildUrl(deleteUrlTemplate, namespaceValue), { method: 'DELETE' })
                .then(parseJsonResponse)
                .then(() => {
                  notify(messages.deleted, 'success');
                  loadNamespaces();
                })
                .catch(err => {
                  const message = err?.message || messages.error;
                  if (message === messages.defaultLocked || message === messages.inUse) {
                    notify(message, 'warning');
                  } else {
                    notify(message, 'danger');
                  }
                })
                .finally(() => {
                  deleteButton.disabled = isDefault || isInactive;
                });
            });

            tr.appendChild(actionCell);
            tableBody.appendChild(tr);

            nameInput.addEventListener('input', () => {
              clearNamespaceError(nameInput);
            });
            labelInput.addEventListener('input', () => {
              clearNamespaceError(labelInput);
            });
          });
        })
        .catch(err => {
          tableBody.innerHTML = '';
          const message = err?.message || messages.error;
          if (message === messages.error && messages.tableMissing) {
            renderMessageRow(messages.tableMissing);
            return;
          }
          renderMessageRow(message);
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
          showNamespaceError(input, validationMessage);
          notify(validationMessage, 'warning');
          input.focus();
          return;
        }
        clearNamespaceError(input);
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
            const message = err?.message || messages.error;
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
        clearNamespaceError(input);
      });
    }

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

  initProjectTree();
  loadBackups();
  const path = window.location.pathname.replace(basePath + '/admin', '');
  const currentRoute = path.replace(/^\/|\/$/g, '') || 'dashboard';
  if (currentRoute === 'tenants') {
    refreshTenantList(!initialTenantHtmlApplied);
  }
});
