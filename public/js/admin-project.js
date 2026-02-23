/* global UIkit */

import { withBase, getCsrfToken, parseDatasetJson } from './admin-utils.js';

// ---------------------------------------------------------------------------
// Dependencies that live in admin-page-tree.js (extracted concurrently).
// Until admin-page-tree.js is wired up we fall back to window globals so the
// module works in both the legacy bundle and the new modular setup.
// ---------------------------------------------------------------------------
const MAX_TREE_DEPTH = 50;

const getPageTreeModalFn = (name) => {
  try {
    // Prefer explicit window globals set by admin-page-tree.js
    if (typeof window[name] === 'function') {
      return window[name];
    }
  } catch (_) { /* ignore */ }
  return () => {};
};

const openPageRenameModal = (...args) => getPageTreeModalFn('openPageRenameModal')(...args);
const openPageDeleteModal = (...args) => getPageTreeModalFn('openPageDeleteModal')(...args);
const openPageStatusModal = (...args) => getPageTreeModalFn('openPageStatusModal')(...args);
const openMenuAssignModal = (...args) => getPageTreeModalFn('openMenuAssignModal')(...args);

// ---------------------------------------------------------------------------
// apiFetch – defined on window by admin.js, consumed here as a convenience
// reference so we don't need a circular import.
// ---------------------------------------------------------------------------
const apiFetch = (...args) => window.apiFetch(...args);

// ===========================================================================
// Project page-tree list (renders page tree inside the project overview)
// ===========================================================================

const buildProjectPageTreeList = (nodes, level = 0, availableMenus = [], menuAssignmentMap = {}) => {
  const list = document.createElement('ul');
  list.className = 'uk-list uk-list-collapse';
  if (level > 0) {
    list.classList.add('uk-margin-small-left');
  }
  if (level > MAX_TREE_DEPTH) {
    return list;
  }

  nodes.forEach(node => {
    const item = document.createElement('li');
    const row = document.createElement('div');
    row.className = 'uk-flex uk-flex-between uk-flex-middle uk-flex-wrap';
    if (node.id) {
      row.setAttribute('data-page-row', node.id);
    }

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
    const menuAssignment = node.id ? menuAssignmentMap[node.id] : null;
    if (menuAssignment && menuAssignment.menuLabel) {
      const menuBadge = document.createElement('span');
      menuBadge.className = 'uk-label uk-label-success uk-margin-small-left';
      menuBadge.textContent = menuAssignment.menuLabel;
      menuBadge.setAttribute('data-menu-badge', node.id);
      menuBadge.title = (window.transTopMenu || 'Top menu') + ': ' + menuAssignment.menuLabel;
      meta.appendChild(menuBadge);
    }
    if (node.status) {
      const statusBadge = document.createElement('span');
      statusBadge.className = 'uk-label uk-margin-small-left';
      statusBadge.setAttribute('data-status-badge', node.id);
      if (node.status === 'published') {
        statusBadge.classList.add('uk-label-success');
        statusBadge.textContent = window.transStatusPublished || 'Published';
      } else if (node.status === 'archived') {
        statusBadge.classList.add('uk-label-warning');
        statusBadge.textContent = window.transStatusArchived || 'Archived';
      } else {
        statusBadge.textContent = window.transStatusDraft || 'Draft';
      }
      meta.appendChild(statusBadge);
    }
    if (meta.childElementCount > 0) {
      row.appendChild(meta);
    }

    if (node.slug && node.editUrl) {
      const hasChildren = Array.isArray(node.children) && node.children.length > 0;
      const actions = document.createElement('div');
      actions.className = 'uk-inline page-tree-actions';

      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'uk-icon-button page-tree-action-toggle';
      toggle.setAttribute('uk-icon', 'icon: more-vertical; ratio: 0.9');
      toggle.setAttribute('aria-label', window.transActions || 'Actions');

      const dropdown = document.createElement('div');
      dropdown.setAttribute('uk-dropdown', 'mode: click; pos: bottom-right; container: body');

      const nav = document.createElement('ul');
      nav.className = 'uk-nav uk-dropdown-nav';

      const renameLi = document.createElement('li');
      const renameLink = document.createElement('a');
      renameLink.href = '#';
      renameLink.innerHTML = '<span uk-icon="icon: pencil; ratio: 0.8" class="uk-margin-small-right"></span>' + (window.transRenamePageAction || 'Rename');
      renameLink.addEventListener('click', (e) => {
        e.preventDefault();
        UIkit.dropdown(dropdown).hide(false);
        openPageRenameModal(node.slug, node.namespace, node.title || node.slug);
      });
      renameLi.appendChild(renameLink);
      nav.appendChild(renameLi);

      if (availableMenus.length > 0) {
        const menuLi = document.createElement('li');
        const menuLink = document.createElement('a');
        menuLink.href = '#';
        menuLink.innerHTML = '<span uk-icon="icon: menu; ratio: 0.8" class="uk-margin-small-right"></span>' + (window.transAssignMenu || 'Assign menu');
        menuLink.addEventListener('click', (e) => {
          e.preventDefault();
          UIkit.dropdown(dropdown).hide(false);
          openMenuAssignModal(node, availableMenus, menuAssignmentMap);
        });
        menuLi.appendChild(menuLink);
        nav.appendChild(menuLi);
      }

      const statusLi = document.createElement('li');
      const statusLink = document.createElement('a');
      statusLink.href = '#';
      statusLink.innerHTML = '<span uk-icon="icon: bolt; ratio: 0.8" class="uk-margin-small-right"></span>' + (window.transChangeStatusAction || 'Change status');
      statusLink.addEventListener('click', (e) => {
        e.preventDefault();
        UIkit.dropdown(dropdown).hide(false);
        openPageStatusModal(node);
      });
      statusLi.appendChild(statusLink);
      nav.appendChild(statusLi);

      const divider = document.createElement('li');
      divider.className = 'uk-nav-divider';
      nav.appendChild(divider);

      const deleteLi = document.createElement('li');
      const deleteLink = document.createElement('a');
      deleteLink.href = '#';
      deleteLink.className = 'uk-text-danger';
      deleteLink.innerHTML = '<span uk-icon="icon: trash; ratio: 0.8" class="uk-margin-small-right"></span>' + (window.transDelete || 'Delete');
      deleteLink.addEventListener('click', (e) => {
        e.preventDefault();
        UIkit.dropdown(dropdown).hide(false);
        openPageDeleteModal(node.slug, node.namespace, node.title || node.slug, hasChildren);
      });
      deleteLi.appendChild(deleteLink);
      nav.appendChild(deleteLi);

      dropdown.appendChild(nav);
      actions.appendChild(toggle);
      actions.appendChild(dropdown);
      row.appendChild(actions);

      if (window.UIkit && UIkit.icon) {
        UIkit.icon(toggle);
      }
    }

    item.appendChild(row);
    if (Array.isArray(node.children) && node.children.length) {
      item.appendChild(buildProjectPageTreeList(node.children, level + 1, availableMenus, menuAssignmentMap));
    }
    list.appendChild(item);
  });

  return list;
};

// ===========================================================================
// Helper: empty state
// ===========================================================================

const createProjectEmptyState = message => {
  const empty = document.createElement('div');
  empty.className = 'uk-text-meta';
  empty.textContent = message;
  return empty;
};

// ===========================================================================
// Helper: build admin URL with namespace
// ===========================================================================

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

// ===========================================================================
// Helper: empty state with action links
// ===========================================================================

const createProjectEmptyStateWithActions = namespace => {
  const wrapper = document.createElement('div');
  wrapper.className = 'uk-alert uk-alert-primary uk-margin-small';

  const title = document.createElement('div');
  title.className = 'uk-text-bold';
  title.textContent = window.transNoContentYet || 'No content yet.';
  wrapper.appendChild(title);

  const hint = document.createElement('div');
  hint.className = 'uk-text-meta uk-margin-small-top';
  hint.textContent = window.transCreateContentNow || 'Create content now:';
  wrapper.appendChild(hint);

  const list = document.createElement('ul');
  list.className = 'uk-list uk-list-collapse uk-margin-small-top';
  const actions = [
    {
      label: window.transCreateContentPages || 'Create content (Pages)',
      url: buildProjectAdminUrl('/admin/pages/content', namespace)
    },
    {
      label: window.transCreateContentWiki || 'Create content (Wiki)',
      url: buildProjectAdminUrl('/admin/pages/wiki', namespace)
    },
    {
      label: window.transCreateContentNews || 'Create content (News)',
      url: buildProjectAdminUrl('/admin/landing-news/create', namespace)
    },
    {
      label: window.transCreateContentNewsletter || 'Create content (Newsletter)',
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

// ===========================================================================
// Helper: status label
// ===========================================================================

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

// ===========================================================================
// Helper: create link element
// ===========================================================================

const createProjectLink = (label, url, className = '') => {
  const link = document.createElement('a');
  link.textContent = label;
  link.href = url;
  if (className) {
    link.className = className;
  }
  return link;
};

// ===========================================================================
// Wiki list builder
// ===========================================================================

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
            article.status === 'published' ? (window.transPublished || 'Published') : article.status,
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

// ===========================================================================
// News list builder
// ===========================================================================

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
          const labelText = news.isPublished ? (window.transPublished || 'Published') : (window.transDraft || 'Draft');
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

// ===========================================================================
// Slug list builder
// ===========================================================================

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

// ===========================================================================
// Media list builder
// ===========================================================================

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
    missingHeading.textContent = window.transMissingMedia || 'Missing media';
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
    wrapper.appendChild(createProjectEmptyState(window.transNoMediaReferences || 'No media references.'));
  }

  return wrapper;
};

// ===========================================================================
// Append a labelled block to a container
// ===========================================================================

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

// ===========================================================================
// Content-empty check
// ===========================================================================

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

// ===========================================================================
// KPI calculation
// ===========================================================================

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
    const element = container.querySelector(`[data-project-kpi="${key}"]`);
    if (element) {
      element.textContent = String(value);
    }
  });
};

// ===========================================================================
// Namespace heading & badge helpers
// ===========================================================================

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

// ===========================================================================
// Render the full project tree
// ===========================================================================

const renderProjectTree = (container, namespaces, emptyMessage) => {
  container.innerHTML = '';
  if (!namespaces.length) {
    container.appendChild(createProjectEmptyState(emptyMessage));
    updateProjectKpis([]);
    return;
  }

  const { flattenNodes, buildTree, normalizeNamespace } = window.pageTreeUtils || {};

  namespaces.forEach(section => {
    const wrapper = document.createElement('div');
    wrapper.className = 'project-tree-section uk-margin';

    wrapper.appendChild(buildProjectNamespaceHeading(section));

    if (isProjectContentEmpty(section)) {
      wrapper.appendChild(createProjectEmptyStateWithActions(section.namespace || ''));
    }

    const namespace = typeof normalizeNamespace === 'function'
      ? normalizeNamespace(section.namespace)
      : (section.namespace || 'default');
    const pages = Array.isArray(section.pages) ? section.pages : [];
    const flatPages = typeof flattenNodes === 'function' ? flattenNodes(pages, namespace) : pages;
    const pageTree = typeof buildTree === 'function' ? buildTree(flatPages) : pages;
    const sectionMenus = Array.isArray(section.availableMenus) ? section.availableMenus : [];
    const sectionMenuMap = section.menuAssignmentMap && typeof section.menuAssignmentMap === 'object'
      ? section.menuAssignmentMap
      : {};
    appendProjectBlock(
      wrapper,
      'Pages',
      pageTree.length ? buildProjectPageTreeList(pageTree, 0, sectionMenus, sectionMenuMap) : createProjectEmptyState(window.transNoPages || 'No pages available.')
    );

    const wikiEntries = Array.isArray(section.wiki) ? section.wiki : [];
    appendProjectBlock(
      wrapper,
      window.transKpiWikiArticles || 'Wiki articles',
      wikiEntries.length ? buildProjectWikiList(wikiEntries) : createProjectEmptyState(window.transNoWikiArticles || 'No wiki articles available.')
    );

    const newsEntries = Array.isArray(section.landingNews) ? section.landingNews : [];
    appendProjectBlock(
      wrapper,
      window.transKpiNewsArticles || 'News articles',
      newsEntries.length ? buildProjectNewsList(newsEntries) : createProjectEmptyState(window.transNoNewsArticles || 'No news articles available.')
    );

    const newsletterSlugs = Array.isArray(section.newsletterSlugs) ? section.newsletterSlugs : [];
    appendProjectBlock(
      wrapper,
      window.transKpiNewsletterSlugs || 'Newsletter slugs',
      newsletterSlugs.length ? buildProjectSlugList(newsletterSlugs) : createProjectEmptyState(window.transNoNewsletterSlugs || 'No newsletter slugs available.')
    );

    appendProjectBlock(
      wrapper,
      window.transKpiMediaRefs || 'Media refs',
      buildProjectMediaList(section.mediaReferences || {})
    );

    container.appendChild(wrapper);
  });
};

// ===========================================================================
// Namespace resolution helpers
// ===========================================================================

const resolveProjectNamespace = (container) => {
  const candidate = container?.dataset.namespace || '';
  return candidate.trim();
};

const resolveNamespaceQuery = () => {
  const params = new URLSearchParams(window.location.search);
  return (params.get('namespace') || '').trim();
};

const getNamespaceSelects = () => {
  const elements = Array.from(document.querySelectorAll('[data-namespace-select]'));
  if (elements.length) {
    return elements;
  }
  const legacy = document.getElementById('namespaceSelect');
  return legacy ? [legacy] : [];
};

const getPrimaryNamespaceSelect = () => {
  const [firstNamespaceSelect] = getNamespaceSelects();
  return firstNamespaceSelect
    || document.getElementById('projectNamespaceSelect')
    || document.getElementById('pageNamespaceSelect');
};

const withProjectNamespace = (endpoint, namespace) => {
  if (!namespace) {
    return endpoint;
  }
  const separator = endpoint.includes('?') ? '&' : '?';
  return `${endpoint}${separator}namespace=${encodeURIComponent(namespace)}`;
};

// ===========================================================================
// initProjectTree – bootstraps the project tree view
// ===========================================================================

const initProjectTree = () => {
  const container = document.querySelector('[data-project-tree]');
  if (!container) {
    return;
  }
  const loading = container.querySelector('[data-project-tree-loading]');
  const emptyMessage = container.dataset.empty || (window.transNoNamespaceData || 'No namespace data available.');
  const errorMessage = container.dataset.error || (window.transNamespaceLoadError || 'Namespace overview could not be loaded.');
  const initialPayload = parseDatasetJson(container.dataset.initialPayload, null);
  const endpoint = container.dataset.endpoint || '/admin/projects/tree';
  const namespaceSelect = getPrimaryNamespaceSelect();
  const selectedNamespace = namespaceSelect?.value || '';
  const activeNamespace = (selectedNamespace || resolveProjectNamespace(container) || resolveNamespaceQuery()).trim();
  const endpointWithNamespace = withProjectNamespace(endpoint, activeNamespace);
  const resolveNamespaces = namespaces => {
    if (!Array.isArray(namespaces)) {
      return [];
    }
    if (!activeNamespace) {
      return namespaces;
    }
    const filtered = namespaces.filter(section => (section.namespace || '').trim() === activeNamespace);
    return filtered.length > 0 ? filtered : namespaces;
  };
  const renderNamespaces = namespaces => {
    renderProjectTree(container, namespaces, emptyMessage);
    updateProjectKpis(namespaces);
  };

  if (loading) {
    loading.textContent = loading.textContent || (window.transNamespaceLoading || 'Loading namespace overview\u2026');
  }

  if (Array.isArray(initialPayload)) {
    renderProjectTree(container, initialPayload, emptyMessage);
    updateProjectKpis(initialPayload);
  }

  apiFetch(endpointWithNamespace)
    .then(response => {
      if (!response.ok) {
        throw new Error('project-tree-request-failed');
      }
      return response.json();
    })
    .then(payload => {
      const namespaces = resolveNamespaces(payload?.namespaces);
      if (namespaces.length === 0 && activeNamespace) {
        return apiFetch(endpoint)
          .then(secondResponse => {
            if (!secondResponse.ok) {
              throw new Error('project-tree-fallback-request-failed');
            }
            return secondResponse.json();
          })
          .then(fallbackPayload => {
            const fallbackNamespaces = resolveNamespaces(fallbackPayload?.namespaces);
            renderNamespaces(fallbackNamespaces.length ? fallbackNamespaces : namespaces);
          });
      }
      renderNamespaces(namespaces);
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

// ===========================================================================
// initProjectSettings – project settings form handler
// ===========================================================================

const initProjectSettings = () => {
  const form = document.querySelector('[data-project-settings-form]');
  if (!form) {
    return;
  }

  const wrapper = form.closest('[data-project-settings]');
  const status = wrapper ? wrapper.querySelector('[data-project-settings-status]') : null;
  const updatedLabel = wrapper ? wrapper.querySelector('[data-project-settings-updated]') : null;
  const endpoint = wrapper?.dataset.endpoint || '/admin/projects/settings';
  const namespaceSelect = getPrimaryNamespaceSelect();

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
      setStatus(window.transNamespaceMissing || 'Namespace missing.', true);
      return;
    }

    const payload = new FormData();
    payload.append('namespace', namespace);
    const consentInput = form.querySelector('#cookieConsentEnabled');
    const storageInput = form.querySelector('#cookieStorageKey');
    const bannerDeInput = form.querySelector('#cookieBannerTextDe');
    const bannerEnInput = form.querySelector('#cookieBannerTextEn');
    const vendorFlagsInput = form.querySelector('#cookieVendorFlags');
    const privacyInput = form.querySelector('#privacyUrl');
    const privacyDeInput = form.querySelector('#privacyUrlDe');
    const privacyEnInput = form.querySelector('#privacyUrlEn');
    const showLanguageInput = form.querySelector('#showLanguageToggle');
    const showThemeInput = form.querySelector('#showThemeToggle');
    const showContrastInput = form.querySelector('#showContrastToggle');
    const logoModeInput = form.querySelector('input[name="header_logo_mode"]:checked');
    const logoAltInput = form.querySelector('#headerLogoAlt');
    const logoLabelInput = form.querySelector('#headerLogoLabel');
    const logoPathInput = form.querySelector('input[name="header_logo_path"]');
    const logoFileInput = form.querySelector('#headerLogoFile');

    if (consentInput) {
      payload.append('cookieConsentEnabled', consentInput.checked ? '1' : '0');
    }
    if (storageInput) {
      payload.append('cookieStorageKey', storageInput.value || '');
    }
    if (bannerDeInput) {
      payload.append('cookieBannerTextDe', bannerDeInput.value || '');
    }
    if (bannerEnInput) {
      payload.append('cookieBannerTextEn', bannerEnInput.value || '');
    }
    if (vendorFlagsInput) {
      payload.append('cookieVendorFlags', vendorFlagsInput.value || '');
    }
    if (privacyInput) {
      payload.append('privacyUrl', privacyInput.value || '');
    }
    if (privacyDeInput) {
      payload.append('privacyUrlDe', privacyDeInput.value || '');
    }
    if (privacyEnInput) {
      payload.append('privacyUrlEn', privacyEnInput.value || '');
    }
    if (showLanguageInput) {
      payload.append('showLanguageToggle', showLanguageInput.checked ? '1' : '0');
    }
    if (showThemeInput) {
      payload.append('showThemeToggle', showThemeInput.checked ? '1' : '0');
    }
    if (showContrastInput) {
      payload.append('showContrastToggle', showContrastInput.checked ? '1' : '0');
    }
    if (logoModeInput) {
      payload.append('headerLogoMode', logoModeInput.value);
    }
    if (logoAltInput) {
      payload.append('headerLogoAlt', logoAltInput.value || '');
    }
    if (logoLabelInput) {
      payload.append('headerLogoLabel', logoLabelInput.value || '');
    }
    if (logoPathInput) {
      payload.append('headerLogoPath', logoPathInput.value || '');
    }
    if (logoFileInput && logoFileInput.files && logoFileInput.files[0]) {
      payload.append('headerLogoFile', logoFileInput.files[0]);
    }

    setStatus(window.transSaving || 'Saving\u2026', false);

    try {
      const response = await apiFetch(endpoint, {
        method: 'POST',
        headers: {
          'X-CSRF-Token': getCsrfToken()
        },
        body: payload
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
      const showLanguageInput = form.querySelector('#showLanguageToggle');
      const showThemeInput = form.querySelector('#showThemeToggle');
      const showContrastInput = form.querySelector('#showContrastToggle');
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
      if (showLanguageInput && typeof settings.show_language_toggle === 'boolean') {
        showLanguageInput.checked = settings.show_language_toggle;
      }
      if (showThemeInput && typeof settings.show_theme_toggle === 'boolean') {
        showThemeInput.checked = settings.show_theme_toggle;
      }
      if (showContrastInput && typeof settings.show_contrast_toggle === 'boolean') {
        showContrastInput.checked = settings.show_contrast_toggle;
      }
      if (logoAltInput && typeof settings.header_logo_alt === 'string') {
        logoAltInput.value = settings.header_logo_alt;
      }
      if (logoLabelInput && typeof settings.header_logo_label === 'string') {
        logoLabelInput.value = settings.header_logo_label;
      }
      if (logoPathInput && typeof settings.header_logo_path === 'string') {
        logoPathInput.value = settings.header_logo_path;
        const logoPathLabel = form.querySelector('[data-current-logo-path]');
        const logoPathValue = form.querySelector('[data-current-logo-path-value]');
        if (logoPathValue) {
          logoPathValue.textContent = settings.header_logo_path;
        }
        if (logoPathLabel) {
          logoPathLabel.hidden = !settings.header_logo_path;
        }
      }
      if (settings.header_logo_mode) {
        const modeValue = settings.header_logo_mode === 'image' ? 'image' : 'text';
        const modeInputs = form.querySelectorAll('input[name="header_logo_mode"]');
        modeInputs.forEach(input => {
          if (input instanceof HTMLInputElement) {
            input.checked = input.value === modeValue;
          }
        });
      }
      const updatedAt = result?.settings?.updated_at || result?.settings?.updatedAt;
      if (updatedLabel) {
        updatedLabel.textContent = updatedAt ? `${window.transLastSaved || 'Last saved'}: ${updatedAt}` : (window.transSettingsSaved || 'Settings saved');
      }
      setStatus(window.transSettingsSaved || 'Settings saved.', false);
    } catch (error) {
      setStatus(window.transSettingsSaveError || 'Settings could not be saved.', true);
    }
  });
};

// ===========================================================================
// initPageNamespaceManager – namespace assignment for pages
// ===========================================================================

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
  const successMessage = manager.dataset.successMessage || (window.transPageMoved || 'Page moved.');
  const errorMessageDefault = manager.dataset.errorMessage || (window.transNamespaceChangeError || 'Namespace could not be changed.');
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

// ===========================================================================
// Named exports
// ===========================================================================

export {
  buildProjectPageTreeList,
  createProjectEmptyState,
  buildProjectAdminUrl,
  createProjectEmptyStateWithActions,
  createProjectStatusLabel,
  createProjectLink,
  buildProjectWikiList,
  buildProjectNewsList,
  buildProjectSlugList,
  buildProjectMediaList,
  appendProjectBlock,
  isProjectContentEmpty,
  countProjectPages,
  buildProjectKpis,
  updateProjectKpis,
  createProjectNamespaceBadge,
  buildProjectNamespaceHeading,
  renderProjectTree,
  resolveProjectNamespace,
  resolveNamespaceQuery,
  getNamespaceSelects,
  getPrimaryNamespaceSelect,
  withProjectNamespace,
  initProjectTree,
  initProjectSettings,
  initPageNamespaceManager
};
