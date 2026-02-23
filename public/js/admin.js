/* global UIkit */

import './marketing-menu-admin.js';
import './marketing-menu-overview.js';
import './marketing-menu-standards.js';
import TableManager from './table-manager.js';
import { createCellEditor } from './edit-helpers.js';
import {
  setCurrentEvent as switchEvent,
  switchPending,
  lastSwitchFailed,
  resetSwitchState,
  registerCacheReset,
  isCurrentEpoch
} from './event-switcher.js';
import { applyLazyImage } from './lazy-images.js';

import { normalizeBasePath, basePath, withBase, resolveApiEndpoint, escape, transEventsFetchError, transDashboardLinkCopied, transDashboardLinkMissing, transDashboardCopyFailed, transDashboardTokenRotated, transDashboardTokenRotateError, transDashboardNoEvent, parseBooleanOption, resolveBooleanOption, formUtils, isAllowed, getCsrfToken, parseDatasetJson } from './admin-utils.js';
import { initPageTypeDefaultsForm, normalizeTreeNamespace, normalizeTreePosition, MAX_TREE_DEPTH, flattenTreeNodes, sortTree, buildTreeFromFlatPages, showUpgradeModal, apiFetch, updateMenuBadge, updateStatusBadge, openPageStatusModal, openMenuAssignModal, openPageRenameModal, openPageDeleteModal } from './admin-page-tree.js';
import { initTeams } from './admin-teams.js';
import { initUsers } from './admin-users.js';
import { initEvents } from './admin-events.js';
import { initCatalog } from './admin-catalog.js';
import { initHelp } from './admin-help.js';

const resolveEventNamespace = () => {
  const indicator = document.querySelector('[data-event-namespace]');
  if (indicator && indicator.dataset.eventNamespace) {
    return indicator.dataset.eventNamespace;
  }
  const params = new URLSearchParams(window.location.search);
  return params.get('namespace') || '';
};

const appendNamespaceParam = (url) => {
  const ns = resolveEventNamespace();
  if (!ns) return url;
  const separator = url.includes('?') ? '&' : '?';
  return url + separator + 'namespace=' + encodeURIComponent(ns);
};
window.notify = (msg, status = 'primary', timeout = 2000) => {
  if (typeof UIkit !== 'undefined' && UIkit.notification) {
    UIkit.notification({ message: msg, status, pos: 'top-center', timeout });
  } else {
    alert(msg);
  }
};

import { getNamespaceSelects, initProjectTree, initProjectSettings, initPageNamespaceManager } from './admin-project.js';


document.addEventListener('DOMContentLoaded', function () {
  const adminTabs = document.getElementById('adminTabs');
  const adminMenu = document.getElementById('adminMenu');
  const adminNav = document.getElementById('adminNav');
  const adminMenuToggle = document.getElementById('adminMenuToggle');
  const pageNamespaceSelect = document.getElementById('pageNamespaceSelect');
  const namespaceSelects = getNamespaceSelects();
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
  namespaceSelects.forEach((namespaceSelect) => {
    const currentNamespace = namespaceSelect.dataset.namespace || namespaceSelect.value || '';
    if (currentNamespace && namespaceSelect.value !== currentNamespace) {
      namespaceSelect.value = currentNamespace;
    }
    if (namespaceSelect.dataset.namespaceListenerAttached === '1') {
      return;
    }

    namespaceSelect.dataset.namespaceListenerAttached = '1';

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
  });

  initProjectSettings();
  initPageNamespaceManager();

  const adminRoutes = Array.from(adminTabs ? adminTabs.querySelectorAll('li') : [])
    .map(tab => tab.getAttribute('data-route') || '');
  const settingsInitial = window.quizSettings || {};
  const ragChatSecretPlaceholder = window.ragChatSecretPlaceholder || '__SECRET_PRESENT__';
  const ragChatTokenPlaceholder = window.ragChatTokenPlaceholder || '••••••••';
  const transRagChatSaved = window.transRagChatSaved || 'Setting saved';
  const transCatalogsFetchError = window.transCatalogsFetchError || 'Could not load catalogues';
  const transCatalogsForbidden = window.transCatalogsForbidden || 'No permission to load catalogues';
  const transRagChatSaveError = window.transRagChatSaveError || 'Save failed';
  const transRagChatTokenSaved = window.transRagChatTokenSaved || '';
  const transRagChatTokenMissing = window.transRagChatTokenMissing || '';
  const transCountdownInvalid = window.transCountdownInvalid || 'Time limit must be 0 or greater.';
  const pagesInitial = window.pagesContent || {};
  const profileForm = document.getElementById('profileForm');
  const profileSaveBtn = document.getElementById('profileSaveBtn');
  const welcomeMailBtn = document.getElementById('welcomeMailBtn');
  const checkoutContainer = document.getElementById('stripe-checkout');
  const planButtons = document.querySelectorAll('.plan-select');
  const emailInput = document.getElementById('subscription-email');
  const planSelect = document.getElementById('planSelect');
  let currentActivePlan = '';
  const domainTableRoot = document.querySelector('#domainTable');
  const managementSection = document.querySelector('[data-admin-section="management"]')
    || domainTableRoot?.closest('[data-admin-section]')
    || domainTableRoot?.closest('.uk-container')
    || null;
  const domainTable = managementSection?.querySelector('#domainTable') || domainTableRoot || null;
  const marketingNewsletterSection = document.getElementById('marketingNewsletterConfigSection');
  const marketingNewsletterSlugInput = document.getElementById('marketingNewsletterSlug');
  const marketingNewsletterSlugOptions = document.getElementById('marketingNewsletterSlugOptions');
  const marketingNewsletterTable = document.getElementById('marketingNewsletterConfigTable');
  let marketingNewsletterTableBody = marketingNewsletterTable ? marketingNewsletterTable.querySelector('tbody') : null;
  if (marketingNewsletterTable && !marketingNewsletterTableBody) {
    marketingNewsletterTableBody = document.createElement('tbody');
    marketingNewsletterTable.appendChild(marketingNewsletterTableBody);
  }
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
  const transMarketingNewsletterSaved = window.transMarketingNewsletterSaved || 'Configuration saved.';
  const transMarketingNewsletterError = window.transMarketingNewsletterError || 'Save failed.';
  const transMarketingNewsletterInvalidSlug = window.transMarketingNewsletterInvalidSlug || 'Slug required';
  const transMarketingNewsletterRemove = window.transMarketingNewsletterRemove || 'Remove';
  const transMarketingNewsletterEmpty = window.transMarketingNewsletterEmpty || (window.transNoEntries || 'No entries available.');
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
  const transDomainSmtpSaved = window.transDomainSmtpSaved || 'SMTP settings saved.';
  const transDomainSmtpError = window.transDomainSmtpError || 'SMTP settings could not be saved.';
  const transDomainSmtpInvalid = window.transDomainSmtpInvalid || transDomainSmtpError;
  const transDomainSmtpDefault = window.transDomainSmtpDefault || 'Default';
  const transDomainSmtpSummaryDsn = window.transDomainSmtpSummaryDsn || 'DSN';
  const transDomainSmtpPasswordSet = window.transDomainSmtpPasswordSet || 'Password stored';
  const secretPlaceholder = window.domainStartPageSecretPlaceholder || '__SECRET_KEEP__';
  const transDomainContactTemplateEdit = window.transDomainContactTemplateEdit || 'Edit template';
  const transDomainContactTemplateSaved = window.transDomainContactTemplateSaved || 'Template saved';
  const transDomainContactTemplateError = window.transDomainContactTemplateError || 'Save failed';
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
  let currentSmtpItem = null;
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
    const normalizedValue = element.dataset.templateEditor === 'html'
      ? sanitizeTemplateHtml(value)
      : value;
    element.value = typeof normalizedValue === 'string' ? normalizedValue : '';
  };
  const getTemplateFieldValue = element => {
    if (!element) return '';
    const value = element.value || '';
    return element.dataset.templateEditor === 'html'
      ? sanitizeTemplateHtml(value)
      : value;
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
        currentActivePlan = currentPlan;
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
        document.querySelectorAll('[data-plan-card]').forEach(card => {
          card.classList.toggle('pricing-plan-card--active', card.dataset.planCard === currentPlan);
        });
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
      if (!confirm(window.transConfirmTenantDelete || 'Really delete tenant?')) return;
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
          notify(window.transTenantRemoved || 'Tenant removed', 'success');
          refreshTenantList();
        })
        .catch(() => notify(window.transErrorDeleteFailed || 'Delete failed', 'danger'))
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
          if (!ok) throw new Error(data.error || 'Error');
          notify(window.transImageReady, 'success');
        })
        .catch(err => notify(err.message || (window.transErrorBuildFailed || 'Build failed'), 'danger'))
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
          if (!ok) throw new Error(data.error || 'Error');
          notify(window.transUpgradeDocker || 'Docker updated', 'success');
        })
        .catch(err => notify(err.message || (window.transErrorUpgradeFailed || 'Upgrade failed'), 'danger'))
        .finally(() => {
          el.innerHTML = originalHtml;
          el.classList.remove('uk-disabled');
        });
    } else if (action === 'restart') {
      e.preventDefault();
      apiFetch('/api/tenants/' + encodeURIComponent(sub) + '/restart', { method: 'POST' })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
          if (!ok) throw new Error(data.error || 'Error');
          notify(data.status || (window.transRestarted || 'Restarted'), 'success');
        })
        .catch(err => notify(err.message || (window.transErrorRestartFailed || 'Restart failed'), 'danger'));
    } else if (action === 'renew') {
      e.preventDefault();
      apiFetch('/api/tenants/' + encodeURIComponent(sub) + '/renew-ssl', { method: 'POST' })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
          if (!ok) throw new Error(data.error || 'Error');
          notify(data.status || (window.transCertRenewing || 'Certificate is being renewed'), 'success');
        })
        .catch(err => notify(err.message || (window.transErrorRenewFailed || 'Renewal failed'), 'danger'));
    } else if (action === 'welcome') {
      e.preventDefault();
      apiFetch('/tenants/' + encodeURIComponent(sub) + '/welcome', { method: 'POST' })
        .then(r => {
          if (!r.ok) throw new Error('Error');
          notify(window.transWelcomeMailSent || 'Welcome email sent', 'success');
        })
        .catch(() => notify(window.transWelcomeMailUnavailable || 'Welcome email not available', 'danger'));
    }
  });
  planButtons.forEach(btn => {
    btn.addEventListener('click', async () => {
      const plan = btn.dataset.plan;
      if (!plan) return;

      // Existing subscriber: use toggle endpoint for plan change
      if (currentActivePlan && currentActivePlan !== plan) {
        btn.disabled = true;
        try {
          const res = await apiFetch('/admin/subscription/toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan })
          });
          if (!res.ok) throw new Error(window.transErrorPlanChange || 'Plan change failed');
          currentActivePlan = plan;
          planButtons.forEach(b => {
            b.disabled = (b.dataset.plan === plan);
            b.textContent = (b.dataset.plan === plan)
              ? (b.dataset.originalText || plan)
              : (window.transUpgradeAction || 'Upgrade');
          });
          document.querySelectorAll('[data-plan-card]').forEach(card => {
            card.classList.toggle('pricing-plan-card--active', card.dataset.planCard === plan);
          });
          notify(window.transUpgradeAction || 'Plan updated', 'success');
          if (typeof window.loadSubscription === 'function') {
            window.loadSubscription();
          }
        } catch (e) {
          console.error(e);
          notify(e.message || (window.transErrorPlanChange || 'Plan change failed'), 'danger');
          btn.disabled = false;
        }
        return;
      }

      // New subscriber: create Stripe Checkout session
      const payload = { plan, embedded: true };
      if (emailInput) {
        const email = emailInput.value.trim();
        if (email === '') {
          emailInput.classList.add('uk-form-danger');
          emailInput.focus();
          notify(window.transEmailRequired || 'Please enter an email address', 'warning');
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
          let msg = window.transErrorPaymentStart || 'Error starting payment';
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
        notify(window.transErrorPaymentStart || 'Error starting payment', 'danger', 0);
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

  function updatePuzzleFeedbackUI() {
    if (!puzzleIcon || !puzzleLabel) return;
    if (puzzleFeedback.trim().length > 0) {
      puzzleIcon.setAttribute('uk-icon', 'icon: check');
      puzzleLabel.textContent = window.transEditFeedbackText || 'Edit feedback text';
    } else {
      puzzleIcon.setAttribute('uk-icon', 'icon: pencil');
      puzzleLabel.textContent = window.transFeedbackText || 'Feedback text';
    }
    if (window.UIkit && UIkit.icon) {
      UIkit.icon(puzzleIcon, { icon: puzzleIcon.getAttribute('uk-icon').split(': ')[1] });
    }
  }

    function updateInviteTextUI() {
      if (!inviteLabel) return;
      if (inviteText.trim().length > 0) {
        inviteLabel.textContent = window.transEditInvitationText || 'Edit invitation text';
      } else {
        inviteLabel.textContent = window.transEnterInvitationText || 'Enter invitation text';
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

  // eventSelectNodes change listeners are now set up inside initEvents()

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
    playerContactEnabled: document.getElementById('cfgPlayerContactEnabled'),
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
  const DASHBOARD_CONTAINER_REFRESH_DEFAULT = 30;
  const DASHBOARD_CONTAINER_REFRESH_MIN = 5;
  const DASHBOARD_CONTAINER_REFRESH_MAX = 300;
  const DASHBOARD_CONTAINER_CPU_MAX = 400;
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
    { id: 'media', enabled: false, layout: 'auto', options: { title: 'Highlights' } },
    { id: 'containerMetrics', enabled: false, layout: 'auto', options: { title: 'Container-Metriken', refreshInterval: 30, maxMemoryMb: null, cpuMaxPercent: 100 } }
  ];
  const DASHBOARD_DEFAULT_MODULE_MAP = new Map(DASHBOARD_DEFAULT_MODULES.map(module => [module.id, module]));
  const DASHBOARD_RESULTS_TARGET_MODULE_IDS = ['rankings', 'results', 'rankingQr'];
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

  const buildDashboardResultsTarget = (cfg) => {
    if (typeof window.buildResultsUrl !== 'function') {
      return '';
    }
    return window.buildResultsUrl(cfg || {}, currentEventUid, '', { basePath });
  };

  const updateDashboardResultsTargets = (cfg) => {
    if (!dashboardModulesList) {
      return;
    }
    const targetUrl = buildDashboardResultsTarget(cfg);
    DASHBOARD_RESULTS_TARGET_MODULE_IDS.forEach((moduleId) => {
      const item = dashboardModulesList.querySelector(`[data-module-id="${moduleId}"]`);
      if (!item) {
        return;
      }
      if (targetUrl) {
        item.dataset.resultsTarget = targetUrl;
      } else {
        delete item.dataset.resultsTarget;
      }
    });
  };

  const normalizeContainerRefreshInterval = (value, fallback = DASHBOARD_CONTAINER_REFRESH_DEFAULT) => {
    const candidate = Number.parseInt(String(value ?? fallback).trim(), 10);
    if (Number.isNaN(candidate)) {
      return fallback;
    }
    if (candidate < DASHBOARD_CONTAINER_REFRESH_MIN) {
      return DASHBOARD_CONTAINER_REFRESH_MIN;
    }
    if (candidate > DASHBOARD_CONTAINER_REFRESH_MAX) {
      return DASHBOARD_CONTAINER_REFRESH_MAX;
    }
    return candidate;
  };

  const normalizeContainerMaxMemoryMb = (value) => {
    if (value === null || value === undefined) {
      return null;
    }
    const normalized = String(value).trim();
    if (normalized === '') {
      return null;
    }
    const parsed = Number.parseInt(normalized, 10);
    if (Number.isNaN(parsed) || parsed <= 0) {
      return null;
    }
    return parsed;
  };

  const normalizeContainerCpuMaxPercent = (value, fallback = 100) => {
    const parsed = Number.parseInt(String(value ?? fallback).trim(), 10);
    if (Number.isNaN(parsed) || parsed <= 0) {
      return fallback;
    }
    if (parsed > DASHBOARD_CONTAINER_CPU_MAX) {
      return DASHBOARD_CONTAINER_CPU_MAX;
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

  function applyContainerMetricsOptions(item, options = {}) {
    if (!item) {
      return;
    }
    const defaults = DASHBOARD_DEFAULT_MODULE_MAP.get('containerMetrics')?.options || {};
    const refreshField = item.querySelector('[data-module-container-refresh]');
    if (refreshField) {
      const baseRefresh = normalizeContainerRefreshInterval(defaults.refreshInterval, DASHBOARD_CONTAINER_REFRESH_DEFAULT);
      const resolvedRefresh = normalizeContainerRefreshInterval(
        Object.prototype.hasOwnProperty.call(options, 'refreshInterval') ? options.refreshInterval : baseRefresh,
        baseRefresh
      );
      refreshField.value = String(resolvedRefresh);
    }
    const memoryField = item.querySelector('[data-module-container-memory]');
    if (memoryField) {
      const memoryValue = normalizeContainerMaxMemoryMb(options.maxMemoryMb ?? defaults.maxMemoryMb ?? null);
      memoryField.value = memoryValue === null ? '' : String(memoryValue);
    }
    const cpuField = item.querySelector('[data-module-container-cpu-max]');
    if (cpuField) {
      const baseCpu = normalizeContainerCpuMaxPercent(defaults.cpuMaxPercent, 100);
      const resolvedCpu = normalizeContainerCpuMaxPercent(
        Object.prototype.hasOwnProperty.call(options, 'cpuMaxPercent') ? options.cpuMaxPercent : baseCpu,
        baseCpu
      );
      cpuField.value = String(resolvedCpu);
    }
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
    maxCompletionTokens: document.getElementById('ragChatMaxCompletionTokens'),
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
    if (ragChatFields.maxCompletionTokens) {
      ragChatFields.maxCompletionTokens.value = settingsInitial.rag_chat_service_max_completion_tokens || '';
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
      rag_chat_service_max_completion_tokens: ragChatFields.maxCompletionTokens?.value?.trim() || '',
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
  const commentState = { currentCommentItem: null };
  let catalogApi;

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
          const msg = (e && e.xhr && e.xhr.responseText) ? e.xhr.responseText : (window.transErrorUploadFailed || 'Upload failed');
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
      } else if (id === 'containerMetrics') {
        const defaults = DASHBOARD_DEFAULT_MODULE_MAP.get(id)?.options || {};
        const refreshField = item.querySelector('[data-module-container-refresh]');
        const memoryField = item.querySelector('[data-module-container-memory]');
        const cpuField = item.querySelector('[data-module-container-cpu-max]');
        const baseRefresh = normalizeContainerRefreshInterval(defaults.refreshInterval, DASHBOARD_CONTAINER_REFRESH_DEFAULT);
        const refreshValue = refreshField
          ? normalizeContainerRefreshInterval(refreshField.value, baseRefresh)
          : baseRefresh;
        const maxMemoryValue = memoryField
          ? normalizeContainerMaxMemoryMb(memoryField.value)
          : normalizeContainerMaxMemoryMb(defaults.maxMemoryMb ?? null);
        const cpuMaxValue = cpuField
          ? normalizeContainerCpuMaxPercent(cpuField.value, defaults.cpuMaxPercent ?? 100)
          : normalizeContainerCpuMaxPercent(defaults.cpuMaxPercent ?? 100, 100);
        entry.options = {
          refreshInterval: refreshValue,
          maxMemoryMb: maxMemoryValue,
          cpuMaxPercent: cpuMaxValue,
        };
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
      } else if (module.id === 'containerMetrics') {
        applyContainerMetricsOptions(item, module.options || {});
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
      } else if (module.id === 'containerMetrics') {
        applyContainerMetricsOptions(item, module.options || {});
      }
      applyDashboardModuleTitle(item, module.id, module.options || {});
    });
    updateDashboardResultsTargets(cfgInitial);
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
    const slug = currentEventSlug || currentEventUid || '';
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
    const hasCfgFields = Object.values(cfgFields).some(field => {
      if (Array.isArray(field)) {
        return field.length > 0;
      }
      return !!field;
    });
    if (!hasCfgFields) {
      return;
    }
    if (cfgFields.logoPreview) {
      cfgFields.logoPreview.src = data.logoPath ? data.logoPath + '?' + Date.now() : '';
    }
    if (cfgFields.pageTitle) {
      cfgFields.pageTitle.value = data.pageTitle || '';
    }
    if (cfgFields.backgroundColor) {
      cfgFields.backgroundColor.value = data.backgroundColor || '';
    }
    if (cfgFields.buttonColor) {
      cfgFields.buttonColor.value = data.buttonColor || '';
    }
    if (cfgFields.startTheme) {
      const normalizedTheme = (data.startTheme || '').toLowerCase();
      cfgFields.startTheme.value = normalizedTheme === 'dark' ? 'dark' : 'light';
    }
    if (cfgFields.checkAnswerButton) {
      cfgFields.checkAnswerButton.checked = data.CheckAnswerButton !== 'no';
    }
    if (cfgFields.qrUser) {
      cfgFields.qrUser.checked = !!data.QRUser;
    }
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
    if (cfgFields.playerContactEnabled) {
      cfgFields.playerContactEnabled.checked = !!data.playerContactEnabled;
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
    updateDashboardResultsTargets(data);
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

  if (domainTable) {
    const domainTableBody = domainTable.querySelector('#domainTableBody') || domainTable.querySelector('tbody');
    const initDomainDropdowns = root => {
      if (!root || typeof UIkit === 'undefined' || typeof UIkit.dropdown !== 'function') {
        return;
      }
      root.querySelectorAll('[uk-dropdown]').forEach(el => {
        UIkit.dropdown(el);
      });
    };
    window.initDomainDropdowns = initDomainDropdowns;
    initDomainDropdowns(domainTableBody);
    const columnCount = Number.parseInt(domainTable.dataset.columnCount || '4', 10)
      || domainTable.querySelectorAll('thead th').length
      || 4;
    const messages = {
      loading: domainTable.dataset.loading || '',
      empty: domainTable.dataset.empty || '',
      error: domainTable.dataset.error || window.transDomainError || 'Domain load failed.'
    };
    const domainEndpoint = resolveApiEndpoint(domainTable?.dataset?.api || '/admin/domains/api');
    const renewSslEndpoint = resolveApiEndpoint('/api/renew-ssl');
    const resolveDomainElement = (id) => managementSection?.querySelector(`#${id}`) || document.getElementById(id);
    const domainForm = resolveDomainElement('domainForm');
    const domainLegend = resolveDomainElement('domainLegend');
    const domainFormError = resolveDomainElement('domainFormError');
    const domainIdInput = resolveDomainElement('domainId');
    const domainHostInput = resolveDomainElement('domainHost');
    const domainLabelInput = resolveDomainElement('domainLabel');
    const domainNamespaceSelect = resolveDomainElement('domainNamespace');
    const domainActiveInput = resolveDomainElement('domainActive');
    const domainFormCancel = resolveDomainElement('domainFormCancel');
    const transDomainSaved = window.transDomainSaved || 'Domain saved.';
    const transDomainError = window.transDomainError || 'Domain save failed.';
    const transDomainInvalid = window.transDomainInvalid || transDomainError;
    const transDomainDeleted = window.transDomainDeleted || 'Domain deleted.';
    const transDomainDeleteConfirm = window.transDomainDeleteConfirm || 'Remove this domain?';
    const transDomainStatusActive = window.transNamespaceActiveLabel || 'Active';
    const transDomainStatusInactive = window.transNamespaceInactiveLabel || 'Inactive';
    const namespaceEntries = Array.isArray(window.availableNamespaces) ? window.availableNamespaces : [];
    const namespaceLabels = namespaceEntries.reduce((acc, entry) => {
      const key = typeof entry?.namespace === 'string' ? entry.namespace : '';
      if (key) {
        acc[key] = entry?.label || key;
      }
      return acc;
    }, {});
    const domainPattern = /^(?=.{1,255}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i;
    const renewSslButton = managementSection?.querySelector('[data-renew-ssl-main]') || null;
    const transRenewSuccess = renewSslButton?.dataset.success
      || window.transDomainSslIssued
      || 'Certificate request queued.';
    const transRenewError = renewSslButton?.dataset.error
      || window.transDomainSslError
      || 'Certificate request failed.';
    const transProvisionLabel = window.transProvisionSsl || 'Provision SSL Certificates';
    const transProvisionConfirm = window.transProvisionSslConfirm
      || 'Trigger SSL provisioning for marketing domains in this namespace?';
    const transProvisionStarted = window.transProvisionSslStarted
      || 'SSL provisioning started. Certificates are issued asynchronously.';
    const transProvisionError = window.transProvisionSslError
      || window.transDomainError
      || 'SSL provisioning failed.';
    const initialDomainData = (() => {
      if (Array.isArray(window.domains)) {
        return window.domains;
      }
      const datasetDomains = domainTable?.dataset?.initialDomains;
      if (datasetDomains) {
        try {
          const parsedDomains = JSON.parse(datasetDomains);
          if (Array.isArray(parsedDomains)) {
            return parsedDomains;
          }
        } catch (err) {
          console.warn('Failed to parse initial domain data:', err);
        }
      }
      return [];
    })();
    let domainData = initialDomainData.slice();

    if (renewSslButton) {
      renewSslButton.addEventListener('click', () => {
        const originalHtml = renewSslButton.innerHTML;
        renewSslButton.disabled = true;
        renewSslButton.innerHTML = `<span class="uk-margin-small-right" uk-spinner></span>${renewSslButton.textContent}`;

        apiFetch(renewSslEndpoint, { method: 'POST' })
          .then(res => res.json().catch(() => ({})).then(data => {
            if (!res.ok) {
              throw new Error(data?.error || transRenewError);
            }
            return data;
          }))
          .then(data => {
            notify(data?.status || transRenewSuccess, 'success');
          })
          .catch(err => {
            notify(err?.message || transRenewError, 'danger');
          })
          .finally(() => {
            renewSslButton.disabled = false;
            renewSslButton.innerHTML = originalHtml;
          });
      });
    }

    const requiredDomainSelectors = {
      '#domainForm': domainForm,
      '#domainHost': domainHostInput,
      '#domainLabel': domainLabelInput,
      '#domainNamespace': domainNamespaceSelect,
      '#domainActive': domainActiveInput,
      '#domainFormCancel': domainFormCancel,
    };
    const missingDomainSelectors = Object.entries(requiredDomainSelectors)
      .filter(([, element]) => !element)
      .map(([selector]) => selector);

    if (missingDomainSelectors.length) {
      console.warn('Domain management form not fully initialized. Missing element(s):', missingDomainSelectors.join(', '));
    } else {
      const setLegend = label => {
        if (domainLegend) {
          const addLabel = domainLegend.dataset.addLabel || domainLegend.textContent || '';
          domainLegend.textContent = label || addLabel;
        }
      };

      const setFormError = message => {
        if (!domainFormError) {
          return;
        }
        if (message) {
          domainFormError.textContent = message;
          domainFormError.hidden = false;
        } else {
          domainFormError.textContent = '';
          domainFormError.hidden = true;
        }
      };

      const resetForm = () => {
        if (domainIdInput) {
          domainIdInput.value = '';
        }
        if (domainHostInput) {
          domainHostInput.value = '';
          domainHostInput.classList.remove('uk-form-danger');
        }
        if (domainLabelInput) {
          domainLabelInput.value = '';
        }
        if (domainNamespaceSelect) {
          domainNamespaceSelect.value = '';
        }
        if (domainActiveInput) {
          domainActiveInput.checked = true;
        }
        if (domainFormCancel) {
          domainFormCancel.hidden = true;
        }
        setLegend(domainLegend?.dataset.addLabel || '');
        setFormError('');
      };

      const applyForm = domain => {
        if (!domain) {
          resetForm();
          return;
        }
        if (domainIdInput) {
          domainIdInput.value = String(domain.id || '');
        }
        if (domainHostInput) {
          domainHostInput.value = domain.host || domain.normalized_host || '';
          domainHostInput.classList.remove('uk-form-danger');
        }
        if (domainLabelInput) {
          domainLabelInput.value = domain.label || '';
        }
        if (domainNamespaceSelect) {
          domainNamespaceSelect.value = domain.namespace || '';
        }
        if (domainActiveInput) {
          domainActiveInput.checked = Boolean(domain.is_active);
        }
        if (domainFormCancel) {
          domainFormCancel.hidden = false;
        }
        setLegend(domainLegend?.dataset.editLabel || '');
        if (domainHostInput) {
          domainHostInput.focus();
        }
        setFormError('');
      };

      const renderDomainMessage = message => {
        if (!domainTableBody) return;
        domainTableBody.innerHTML = '';
        if (!message) return;
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = columnCount;
        td.textContent = message;
        tr.appendChild(td);
        domainTableBody.appendChild(tr);
      };

      const createTextCell = value => {
        const span = document.createElement('span');
        span.textContent = value || '';
        return span;
      };

      const formatDomainLabel = item => item.host || item.normalized_host || '';

      const setActionLoading = (link, loading) => {
        if (!link) {
          return () => {};
        }
        if (!link.dataset.originalLabel) {
          link.dataset.originalLabel = link.textContent || '';
        }
        if (!link.dataset.originalHtml) {
          link.dataset.originalHtml = link.innerHTML;
        }

        if (loading) {
          const label = link.dataset.originalLabel || '';
          link.classList.add('uk-disabled');
          link.innerHTML = `<span uk-spinner class="uk-margin-small-right"></span>${label}`;
          return () => setActionLoading(link, false);
        }

        link.classList.remove('uk-disabled');
        if (link.dataset.originalHtml) {
          link.innerHTML = link.dataset.originalHtml;
        }

        return () => {};
      };

      const renewDomainCertificate = (domain, trigger) => {
        if (!domain?.id) {
          return;
        }

        const reset = setActionLoading(trigger, true);
        const url = `${domainEndpoint}/${domain.id}/renew-ssl`;
        const hostLabel = formatDomainLabel(domain);
        const successMessage = hostLabel
          ? `${hostLabel}: ${transRenewSuccess}`
          : transRenewSuccess;

        apiFetch(url, { method: 'POST' })
          .then(res => res.json().catch(() => ({})).then(data => {
            if (!res.ok) {
              throw new Error(data?.error || transRenewError);
            }
            return data;
          }))
          .then(data => {
            const message = data?.status || successMessage;
            notify(message, 'success');
          })
          .catch(err => {
            notify(err?.message || transRenewError, 'danger');
          })
          .finally(() => {
            reset();
          });
      };

      const provisionDomainCertificate = (domain, trigger) => {
        if (!domain?.id) {
          return;
        }

        if (!window.confirm(transProvisionConfirm)) {
          return;
        }

        const reset = setActionLoading(trigger, true);
        const url = resolveApiEndpoint(`/api/admin/domains/${encodeURIComponent(domain.id)}/provision-ssl`);
        const hostLabel = formatDomainLabel(domain);
        const successMessage = hostLabel
          ? `${hostLabel}: ${transProvisionStarted}`
          : transProvisionStarted;

        apiFetch(url, { method: 'POST' })
          .then(res => res.json().catch(() => ({})).then(data => {
            if (!res.ok) {
              throw new Error(data?.error || transProvisionError);
            }
            return data;
          }))
          .then(data => {
            notify(data?.status || successMessage, 'primary');
          })
          .catch(err => {
            notify(err?.message || transProvisionError, 'danger');
          })
          .finally(() => {
            reset();
          });
      };

      const deleteDomain = (domain, trigger) => {
        if (!domain?.id) {
          return;
        }

        if (!window.confirm(transDomainDeleteConfirm)) {
          return;
        }

        const reset = setActionLoading(trigger, true);
        apiFetch(`${domainEndpoint}/${domain.id}`, { method: 'DELETE' })
          .then(res => {
            if (!res.ok) {
              return res.json().catch(() => ({})).then(data => {
                throw new Error(data?.error || transDomainError);
              });
            }
            return res.json();
          })
          .then(() => {
            notify(transDomainDeleted, 'success');
            loadDomains();
          })
          .catch(err => {
            notify(err?.message || transDomainError, 'danger');
          })
          .finally(() => {
            reset();
          });
      };

      const createActionLink = ({ label, icon, className = '', action, domainId = null, onClick }) => {
        const link = document.createElement('a');
        link.href = '#';
        link.className = `uk-flex uk-flex-middle ${className}`.trim();
        if (action) {
          link.dataset.domainAction = action;
        }
        if (domainId !== null && domainId !== undefined) {
          link.dataset.domainId = String(domainId);
        }
        if (icon) {
          const iconEl = document.createElement('span');
          iconEl.setAttribute('uk-icon', icon);
          iconEl.className = 'uk-margin-small-right';
          link.appendChild(iconEl);
        }
        const textEl = document.createElement('span');
        textEl.textContent = label;
        link.appendChild(textEl);

        if (typeof onClick === 'function') {
          link.addEventListener('click', event => {
            event.preventDefault();
            onClick(link, event);
          });
        }

        return link;
      };

      const domainColumns = [
        { key: 'host', render: item => createTextCell(item.host || item.normalized_host || '') },
        { key: 'label', render: item => createTextCell(item.label || '') },
        {
          key: 'namespace',
          render: item => {
            const label = item.namespace && namespaceLabels[item.namespace]
              ? namespaceLabels[item.namespace]
              : (item.namespace || window.transNamespaceNone || '');
            return createTextCell(label);
          }
        },
        { key: 'status', render: item => createTextCell(item.is_active ? transDomainStatusActive : transDomainStatusInactive) },
        {
          key: 'actions',
          className: 'uk-table-shrink uk-text-center',
          render: item => {
            const wrapper = document.createElement('div');
            wrapper.className = 'uk-inline';

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'uk-icon-button';
            toggle.setAttribute('uk-icon', 'more-vertical');
            wrapper.appendChild(toggle);

            const dropdown = document.createElement('div');
            dropdown.setAttribute('uk-dropdown', 'mode: click; pos: bottom-right; container: body');

            const list = document.createElement('ul');
            list.className = 'uk-nav uk-dropdown-nav';

            const header = document.createElement('li');
            header.className = 'uk-nav-header';
            header.textContent = window.transActions || (window.transActionsLabel ?? 'Aktionen');
            list.appendChild(header);

            const editItem = document.createElement('li');
            editItem.appendChild(createActionLink({
              label: window.transEdit || 'Edit',
              icon: 'pencil',
              action: 'edit',
              domainId: item.id,
              onClick: () => applyForm(item),
            }));
            list.appendChild(editItem);

            const provisionItem = document.createElement('li');
            provisionItem.appendChild(createActionLink({
              label: transProvisionLabel,
              icon: 'play',
              action: 'provision-ssl',
              domainId: item.id,
              onClick: link => provisionDomainCertificate(item, link),
            }));
            list.appendChild(provisionItem);

            const renewItem = document.createElement('li');
            renewItem.appendChild(createActionLink({
              label: window.transRenewSsl || window.transDomainRenew || window.transDomainRenewSsl || 'Renew SSL',
              icon: 'lock',
              action: 'renew-ssl',
              domainId: item.id,
              onClick: link => renewDomainCertificate(item, link),
            }));
            list.appendChild(renewItem);

            const divider = document.createElement('li');
            divider.className = 'uk-nav-divider';
            list.appendChild(divider);

            const deleteItem = document.createElement('li');
            deleteItem.appendChild(createActionLink({
              label: window.transDelete || 'Delete',
              icon: 'trash',
              className: 'uk-text-danger',
              action: 'delete',
              domainId: item.id,
              onClick: link => deleteDomain(item, link),
            }));
            list.appendChild(deleteItem);

            dropdown.appendChild(list);
            wrapper.appendChild(dropdown);

            return wrapper;
          }
        }
      ];

      const findDomainById = domainId => {
        if (domainId === undefined || domainId === null) {
          return null;
        }
        const matches = entry => String(entry?.id ?? '') === String(domainId);
        return domainData.find(matches) || initialDomainData.find(matches) || null;
      };

      const handleDomainAction = event => {
        if (!domainTable) {
          return;
        }

        const composedPath = typeof event.composedPath === 'function'
          ? event.composedPath()
          : null;
        const composedElement = Array.isArray(composedPath)
          ? composedPath.find(node => node instanceof Element)
          : null;
        const normalizedTarget = composedElement
          || (event.target instanceof Element
            ? event.target
            : event.target?.parentElement);

        if (!normalizedTarget) {
          return;
        }

        const actionTarget = normalizedTarget.closest('[data-domain-action]');
        if (!actionTarget) {
          return;
        }

        const action = actionTarget.dataset.domainAction;
        const domainId = actionTarget.dataset.domainId;
        const domain = findDomainById(domainId);

        if (!action || !domain) {
          return;
        }
        if (!domainTable.contains(actionTarget)) {
          // Fallback for dropdowns rendered outside of the table (e.g., UIkit container: body).
          // Accept actions that carry a domain id even when they are teleported to <body>.
          const relatedDropdown = actionTarget.closest('.uk-dropdown,[uk-dropdown]');
          const scopedToTable = actionTarget.closest('[data-domain-table]') === domainTable;
          const hasDomainContext = Boolean(domainId);
          if (!relatedDropdown && !scopedToTable && !hasDomainContext) {
            return;
          }
        }

        event.preventDefault();

        if (action === 'edit') {
          applyForm(domain);
          return;
        }

        if (action === 'provision-ssl') {
          provisionDomainCertificate(domain, actionTarget);
          return;
        }

        if (action === 'renew-ssl') {
          renewDomainCertificate(domain, actionTarget);
          return;
        }

        if (action === 'delete') {
          deleteDomain(domain, actionTarget);
        }
      };

      document.addEventListener('click', handleDomainAction);

      const domainTableManager = domainTableBody
        ? new TableManager({
          tbody: domainTableBody,
          columns: domainColumns,
          tableClasses: ['uk-table', 'uk-table-divider', 'uk-table-small', 'uk-table-hover'],
          tableWrapperClasses: ['uk-overflow-auto']
        })
        : null;

      const renderDomains = (list = domainData) => {
        if (!domainTableManager || !domainTableBody) {
          renderDomainMessage(messages.empty);
          return;
        }
        domainTableManager.render(list);
        initDomainDropdowns(domainTableBody);
        if (!domainTableManager.getViewData().length) {
          renderDomainMessage(messages.empty);
        }
      };

      const loadDomains = () => {
        if (!domainTableBody) {
          return;
        }
        renderDomainMessage(messages.loading);

        apiFetch(domainEndpoint)
          .then(res => {
            if (!res.ok) {
              return res.json().catch(() => ({})).then(data => {
                throw new Error(data.error || messages.error);
              });
            }
            return res.json();
          })
          .then(data => {
            domainData = Array.isArray(data?.domains) ? data.domains : [];
            renderDomains(domainData);
          })
          .catch(err => {
            renderDomainMessage(err.message || messages.error);
          });
      };

      domainHostInput.addEventListener('input', () => {
        domainHostInput.classList.remove('uk-form-danger');
        setFormError('');
      });

      domainForm.addEventListener('submit', event => {
        event.preventDefault();
        const normalizeHostInput = (value) => {
          let normalized = value.trim();
          normalized = normalized.replace(/^https?:\/\//i, '');
          normalized = normalized.replace(/\/$/, '');
          return normalized;
        };

        const hostValue = domainHostInput.value.trim();
        const normalizedHost = normalizeHostInput(hostValue);
        if (!domainPattern.test(normalizedHost)) {
          domainHostInput.classList.add('uk-form-danger');
          setFormError(transDomainInvalid);
          notify(transDomainInvalid, 'warning');
          domainHostInput.focus();
          return;
        }
        domainHostInput.value = normalizedHost;

        const payload = {
          host: normalizedHost,
          label: domainLabelInput ? domainLabelInput.value.trim() || null : null,
          namespace: domainNamespaceSelect ? domainNamespaceSelect.value.trim() || null : null,
          is_active: domainActiveInput ? domainActiveInput.checked : true,
        };

        const id = domainIdInput?.value ? Number(domainIdInput.value) : null;
        const endpoint = id ? `${domainEndpoint}/${id}` : domainEndpoint;
        const method = id ? 'PATCH' : 'POST';

        setFormError('');
        apiFetch(endpoint, {
          method,
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        })
          .then(res => {
            return res.json().catch(() => ({})).then(data => {
              if (!res.ok) {
                throw new Error(data?.error || transDomainError);
              }
              return data;
            });
          })
          .then(() => {
            notify(transDomainSaved, 'success');
            resetForm();
            loadDomains();
          })
          .catch(err => {
            const message = err?.message || transDomainError;
            setFormError(message);
            notify(message, 'danger');
          });
      });

      domainFormCancel.addEventListener('click', () => {
        resetForm();
      });

      resetForm();
      loadDomains();
    }
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
        cell.textContent = domainChatTranslations.empty || 'No files available';
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
          apiFetch(appendNamespaceParam(`/admin/domain-chat/documents/${encodeURIComponent(doc.id)}?domain=${encodeURIComponent(currentDomain)}`), {
            method: 'DELETE'
          })
            .then(res => res.json().catch(() => ({})).then(data => {
              if (!res.ok) {
                throw new Error(data.error || domainChatTranslations.error || 'Delete failed');
              }
              notify(domainChatTranslations.deleted || 'Deleted', 'success');
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
        loadingCell.textContent = domainChatTranslations.loading || 'Loading\u2026';
        loadingRow.appendChild(loadingCell);
        tableBody.appendChild(loadingRow);
      }

      return apiFetch(appendNamespaceParam(`/admin/domain-chat/documents?domain=${encodeURIComponent(domain)}`))
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
            errorCell.textContent = err.message || domainChatTranslations.error || 'Error';
            errorRow.appendChild(errorCell);
            tableBody.appendChild(errorRow);
          }
          if (wikiContainer) {
            renderWikiArticles({ enabled: true, available: false, articles: [] });
            setWikiMessage(err.message || domainChatTranslations.error || 'Error', 'danger');
          }
          showStatus(err.message || domainChatTranslations.error || 'Error', 'danger');
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
          notify(domainChatTranslations.error || 'No file selected', 'danger');
          return;
        }
        if (maxUploadSize > 0 && file.size > maxUploadSize) {
          const sizeMb = (maxUploadSize / 1048576).toFixed(1);
          notify(domainChatTranslations.error || `File too large (max. ${sizeMb} MB)`, 'danger');
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

        apiFetch(appendNamespaceParam('/admin/domain-chat/documents'), {
          method: 'POST',
          body: formData,
        })
          .then(res => res.json().catch(() => ({})).then(data => {
            if (!res.ok) {
              throw new Error(data.error || domainChatTranslations.error || 'Upload failed');
            }
            notify(domainChatTranslations.uploaded || 'Document saved', 'success');
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
          notify(domainChatTranslations.error || 'No domain selected', 'danger');
          return;
        }
        if (!wikiState.enabled || !wikiState.available) {
          notify(domainChatTranslations.wikiUnavailable || domainChatTranslations.error || 'Action not available', 'danger');
          return;
        }

        const selectedIds = wikiState.articles
          .filter(article => article.selected)
          .map(article => article.id)
          .filter(id => Number.isInteger(id) && id > 0);

        wikiSaveButton.disabled = true;

        apiFetch(appendNamespaceParam('/admin/domain-chat/wiki-selection'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ domain: selectedDomain, articles: selectedIds }),
        })
          .then(res => res.json().catch(() => ({})).then(data => {
            if (!res.ok || data.success !== true) {
              const message = typeof data.error === 'string'
                ? data.error
                : (domainChatTranslations.wikiError || domainChatTranslations.error || 'Save failed');
              throw new Error(message);
            }

            if (wikiContainer) {
              renderWikiArticles(data.wiki ?? { enabled: true, available: false, articles: [] });
            }

            const successMessage = domainChatTranslations.wikiSaved || 'Selection saved';
            showStatus(successMessage, 'success');
            notify(successMessage, 'success');
          }))
          .catch(err => {
            const message = err.message || domainChatTranslations.wikiError || domainChatTranslations.error || 'Save failed';
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
          notify(domainChatTranslations.error || 'No domain selected', 'danger');
          return;
        }
        rebuildButton.disabled = true;
        if (downloadButton) {
          downloadButton.disabled = true;
        }
        apiFetch(appendNamespaceParam('/admin/domain-chat/rebuild'), {
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
              const message = (domainChatTranslations.rebuildThrottled || 'Please wait before rebuilding the index.')
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
              const message = domainChatTranslations.rebuildQueued || 'Index rebuild scheduled';
              showStatus(message, 'success');
              notify(message, 'success');
              return null;
            }
            const message = data.cleared
              ? (domainChatTranslations.rebuildCleared || 'Index reset')
              : (domainChatTranslations.rebuild || 'Index updated');
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
          notify(domainChatTranslations.error || 'No domain selected', 'danger');
          return;
        }

        downloadButton.disabled = true;

        apiFetch(appendNamespaceParam(`/admin/domain-chat/index?domain=${encodeURIComponent(selectedDomain)}`))
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
    if (cfgFields.playerContactEnabled) data.playerContactEnabled = cfgFields.playerContactEnabled.checked;
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
        notify(window.transSettingSaved || 'Setting saved', 'success');
      } else {
        notify(window.transErrorSaveFailed || 'Save failed', 'danger');
      }
    }).catch(() => notify(window.transErrorSaveFailed || 'Save failed', 'danger'));
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
    notify(window.transFeedbackTextSaved || 'Feedback text saved', 'success');
    queueCfgSave();
  });

  inviteSaveBtn?.addEventListener('click', () => {
    if (!inviteTextarea) return;
    inviteText = inviteTextarea.value;
    updateInviteTextUI();
    inviteModal.hide();
    cfgInitial.inviteText = inviteText;
    notify(window.transInvitationTextSaved || 'Invitation text saved', 'success');
    queueCfgSave();
  });

  commentSaveBtn?.addEventListener('click', async () => {
    if (!commentState.currentCommentItem || !commentTextarea) return;
    commentState.currentCommentItem.comment = commentTextarea.value;
    const mgr = catalogApi?.catalogManager;
    if (!mgr) return;
    const list = mgr.getData();
    mgr.render(list);
    try {
      await catalogApi.saveCatalogs(list);
      commentModal.hide();
      commentState.currentCommentItem = null;
      notify(window.transCommentSaved || 'Comment saved', 'success');
    } catch (err) {
      console.error(err);
      notify(window.transErrorSaveFailed || 'Save failed', 'danger');
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
        notify(window.transSettingSaved || 'Setting saved', 'success');
      } else {
        notify(window.transErrorSaveFailed || 'Save failed', 'danger');
      }
    }).catch(() => notify(window.transErrorSaveFailed || 'Save failed', 'danger'));
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
  // --------- Fragen bearbeiten (extracted to admin-catalog.js) ---------
  catalogApi = initCatalog({
    apiFetch,
    notify,
    withBase,
    getCurrentEventUid: () => currentEventUid,
    cfgInitial,
    cfgFields,
    registerCacheReset,
    TableManager,
    createCellEditor,
    appendNamespaceParam,
    transCatalogsFetchError,
    transCatalogsForbidden,
    commentTextarea,
    commentModal,
    catalogEditInput,
    catalogEditError,
    resultsResetModal,
    resultsResetConfirm,
    commentState,
  });

  // --------- Veranstaltungen (extracted to admin-events.js) ---------
  const eventsState = {
    get currentEventUid() { return currentEventUid; },
    set currentEventUid(v) { currentEventUid = v; },
    get currentEventName() { return currentEventName; },
    set currentEventName(v) { currentEventName = v; },
    get currentEventSlug() { return currentEventSlug; },
    set currentEventSlug(v) { currentEventSlug = v; },
    get availableEvents() { return availableEvents; },
    set availableEvents(v) { availableEvents = v; },
    get dashboardQrCatalogs() { return dashboardQrCatalogs; },
    set dashboardQrCatalogs(v) { dashboardQrCatalogs = v; },
    get dashboardQrFetchEpoch() { return dashboardQrFetchEpoch; },
    set dashboardQrFetchEpoch(v) { dashboardQrFetchEpoch = v; },
  };
  const eventsApi = initEvents({
    apiFetch,
    escape,
    transEventsFetchError,
    normalizeId,
    cfgInitial,
    eventIndicators,
    indicatorNodes,
    eventSelectNodes,
    switchEvent,
    switchPending: () => switchPending,
    lastSwitchFailed: () => lastSwitchFailed,
    resetSwitchState,
    TableManager,
    createCellEditor,
    resolveEventNamespace,
    appendNamespaceParam,
    notify,
    updateDashboardShareLinks,
    syncRandomNameOptionsState,
    invalidateRandomNamePreview,
    state: eventsState,
  });
  const {
    populateEventSelectors,
    renderCurrentEventIndicator,
    updateEventButtons,
    syncCurrentEventState,
    updateEventsCardsEmptyState,
    highlightCurrentEvent,
    setCurrentEvent,
    updateActiveHeader,
    eventDependentSections,
    eventSettingsHeading,
    catalogsHeading,
    questionsHeading,
  } = eventsApi;

  // --------- Teams/Personen ---------
  const teamsApi = initTeams({
    apiFetch,
    notify,
    withBase,
    getCurrentEventUid: () => currentEventUid,
    cfgInitial,
    registerCacheReset,
    TableManager,
    createCellEditor
  });



  // --------- Benutzer / Mandanten (extracted to admin-users.js) ---------
  const usersCtx = initUsers({
    apiFetch,
    notify: window.notify,
    managementSection,
    adminTabs,
    adminRoutes,
    TableManager,
    registerCacheReset,
    getStored,
    setStored,
    STORAGE_KEYS
  });
  const { refreshTenantList, loadBackups, initBackupDropdowns, backupTableBody } = usersCtx;

  // --------- Hilfe-Seitenleiste (extracted to admin-help.js) ---------
  const helpState = {
    get currentEventUid() { return currentEventUid; },
    set currentEventUid(v) { currentEventUid = v; },
    get currentEventName() { return currentEventName; },
    set currentEventName(v) { currentEventName = v; },
    get currentEventSlug() { return currentEventSlug; },
    set currentEventSlug(v) { currentEventSlug = v; },
    get availableEvents() { return availableEvents; },
  };
  const { loadSummary } = initHelp({
    apiFetch,
    withBase,
    basePath,
    isAllowed,
    escape,
    registerCacheReset,
    isCurrentEpoch,
    TableManager,
    createCellEditor,
    appendNamespaceParam,
    replaceInitialConfig,
    updateDashboardShareLinks,
    renderCfg,
    loadCatalogs: catalogApi.loadCatalogs,
    applyCatalogList: catalogApi.applyCatalogList,
    bindTeamPrintButtons,
    collectRagChatPayload,
    renderRagChatSettings,
    buildMarketingNewsletterPath,
    labelFromSlug,
    notify,
    cfgInitial,
    settingsInitial,
    ragChatSecretPlaceholder,
    transRagChatSaved,
    transRagChatSaveError,
    adminTabs,
    adminMenu,
    adminNav,
    adminMenuToggle,
    adminRoutes,
    ragChatFields,
    catSelect: catalogApi.catSelect,
    teamsApi,
    eventsApi,
    refreshTenantList,
    profileForm,
    profileSaveBtn,
    welcomeMailBtn,
    planSelect,
    emailInput,
    checkoutContainer,
    marketingNewsletterSection,
    marketingNewsletterSlugInput,
    marketingNewsletterTableBody,
    marketingNewsletterTable,
    marketingNewsletterSlugOptions,
    marketingNewsletterAddBtn,
    marketingNewsletterSaveBtn,
    marketingNewsletterResetBtn,
    marketingNewsletterData,
    marketingNewsletterSlugs,
    marketingNewsletterStyles,
    marketingNewsletterStyleLabels,
    transMarketingNewsletterSaved,
    transMarketingNewsletterError,
    transMarketingNewsletterInvalidSlug,
    transMarketingNewsletterRemove,
    transMarketingNewsletterEmpty,
    state: helpState,
  });

  initPageTypeDefaultsForm();
  initProjectTree();
  initBackupDropdowns(backupTableBody);
  loadBackups();
  const path = window.location.pathname.replace(basePath + '/admin', '');
  const currentRoute = path.replace(/^\/|\/$/g, '') || 'dashboard';
  if (currentRoute === 'tenants') {
    refreshTenantList(!usersCtx.initialTenantHtmlApplied);
  }
});
