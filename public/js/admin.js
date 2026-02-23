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
import { initConfig } from './admin-config.js';
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

  // --------- Konfiguration bearbeiten (extracted to admin-config.js) ---------
  const configApi = initConfig({
    apiFetch,
    notify,
    withBase,
    escape,
    basePath,
    resolveApiEndpoint,
    resolveBooleanOption,
    formUtils,
    TableManager,
    createCellEditor,
    registerCacheReset,
    resolveEventNamespace,
    appendNamespaceParam,
    transDashboardLinkCopied,
    transDashboardLinkMissing,
    transDashboardCopyFailed,
    transDashboardTokenRotated,
    transDashboardTokenRotateError,
    transDashboardNoEvent,
    settingsInitial,
    ragChatTokenPlaceholder,
    transRagChatTokenSaved,
    transRagChatTokenMissing,
    transCountdownInvalid,
    managementSection,
    domainTable,
  });
  const {
    cfgInitial,
    cfgFields,
    renderCfg,
    replaceInitialConfig,
    collectCfgData,
    updateDashboardShareLinks,
    syncRandomNameOptionsState,
    invalidateRandomNamePreview,
    bindTeamPrintButtons,
    collectRagChatPayload,
    renderRagChatSettings,
    normalizeId,
    commentTextarea,
    commentModal,
    catalogEditInput,
    catalogEditError,
    commentState,
    resultsResetModal,
    resultsResetConfirm,
    ragChatFields,
    eventIndicators,
    indicatorNodes,
    eventSelectNodes,
  } = configApi;
  let catalogApi;

  // --------- Fragen bearbeiten (extracted to admin-catalog.js) ---------
  catalogApi = initCatalog({
    apiFetch,
    notify,
    withBase,
    getCurrentEventUid: () => configApi.currentEventUid,
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
  configApi.catalogApi = catalogApi;

  // --------- Veranstaltungen (extracted to admin-events.js) ---------
  const eventsState = {
    get currentEventUid() { return configApi.currentEventUid; },
    set currentEventUid(v) { configApi.currentEventUid = v; },
    get currentEventName() { return configApi.currentEventName; },
    set currentEventName(v) { configApi.currentEventName = v; },
    get currentEventSlug() { return configApi.currentEventSlug; },
    set currentEventSlug(v) { configApi.currentEventSlug = v; },
    get availableEvents() { return configApi.availableEvents; },
    set availableEvents(v) { configApi.availableEvents = v; },
    get dashboardQrCatalogs() { return configApi.dashboardQrCatalogs; },
    set dashboardQrCatalogs(v) { configApi.dashboardQrCatalogs = v; },
    get dashboardQrFetchEpoch() { return configApi.dashboardQrFetchEpoch; },
    set dashboardQrFetchEpoch(v) { configApi.dashboardQrFetchEpoch = v; },
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
    getCurrentEventUid: () => configApi.currentEventUid,
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
    get currentEventUid() { return configApi.currentEventUid; },
    set currentEventUid(v) { configApi.currentEventUid = v; },
    get currentEventName() { return configApi.currentEventName; },
    set currentEventName(v) { configApi.currentEventName = v; },
    get currentEventSlug() { return configApi.currentEventSlug; },
    set currentEventSlug(v) { configApi.currentEventSlug = v; },
    get availableEvents() { return configApi.availableEvents; },
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
