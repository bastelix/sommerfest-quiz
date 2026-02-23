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
    if (!currentCommentItem || !commentTextarea) return;
    currentCommentItem.comment = commentTextarea.value;
    const list = catalogManager.getData();
    catalogManager.render(list);
    try {
      await saveCatalogs(list);
      commentModal.hide();
      currentCommentItem = null;
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
    { key: 'description', label: window.transDescription || 'Description', className: 'uk-table-expand', editable: true },
    { key: 'raetsel_buchstabe', label: window.transPuzzleLetter || 'Puzzle letter', className: 'uk-table-shrink', editable: true },
    {
      key: 'comment',
      label: window.transComment || 'Comment',
      className: 'uk-table-expand',
      editable: true,
      ariaDesc: window.transEditComment || 'Edit comment',
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
        delBtn.setAttribute('aria-label', window.transDelete || 'Delete');
        delBtn.setAttribute('uk-tooltip', 'title: ' + (window.transDelete || 'Delete') + '; pos: left');
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
        delBtn.setAttribute('aria-label', window.transDelete || 'Delete');
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
          notify(window.transErrorCreateFailed || 'Creation failed', 'danger');
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
          notify(window.transErrorRenameFailed || 'Rename failed', 'danger');
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
      if (show && !reorder) notify(window.transCatalogListSaved || 'Catalogue list saved', 'success');
    } catch (err) {
      console.error(err);
      if (retries > 0) {
        notify(window.transErrorSaveRetry || 'Save failed, please try again\u2026', 'warning');
        setTimeout(() => saveCatalogs(list, show, reorder, retries - 1), 1000);
      } else {
        notify(window.transErrorSaveFailed || 'Save failed', 'danger');
      }
    }
  }

  const appendEventParam = (url) => {
    if (!currentEventUid) return url;
    const separator = url.includes('?') ? '&' : '?';
    return url + separator + 'event=' + encodeURIComponent(currentEventUid);
  };

  function loadCatalog(identifier) {
    const cat = catalogs.find(c => c.id === identifier || c.uid === identifier || (c.slug || c.sort_order) === identifier);
    if (!cat) return;
    catalogFile = cat.file;
    apiFetch(appendEventParam(appendNamespaceParam('/kataloge/' + catalogFile)), { headers: { 'Accept': 'application/json' } })
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
    if (!catalogManager) return;
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
    const res = await apiFetch(appendEventParam(appendNamespaceParam('/kataloge/catalogs.json')), { headers: { 'Accept': 'application/json' } });
    if (!res.ok) {
      throw new Error(`Legacy catalogs request failed with status ${res.status}`);
    }
    const list = await res.json();
    applyCatalogList(list);
  }

  async function loadCatalogs() {
    if (!currentEventUid) {
      applyCatalogList([]);
      return;
    }
    catalogManager?.setColumnLoading('name', true);
    try {
      const res = await apiFetch(appendEventParam(appendNamespaceParam('/admin/catalogs/data')), { headers: { 'Accept': 'application/json' } });
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

  if (catalogList || catSelect) {
    loadCatalogs();

    if (catSelect) {
      catSelect.addEventListener('change', () => loadCatalog(catSelect.value));
    }
  }

  function deleteCatalogById(id) {
    const list = catalogManager.getData();
    const cat = list.find(c => c.id === id);
    if (!cat) return;
    if (cat.new || !cat.file) {
      catalogManager.render(list.filter(c => c.id !== id));
      return;
    }
    if (!confirm(window.transConfirmCatalogDelete || 'Really delete catalogue?')) return;
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
        notify(window.transCatalogDeleted || 'Catalogue deleted', 'success');
      })
      .catch(err => {
        console.error(err);
        notify(window.transErrorDeleteFailed || 'Delete failed', 'danger');
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

  // Erstellt ein Bearbeitungsformular für eine Frage (Block-Card-Pattern)
  function createCard(q, index = -1) {
    const card = document.createElement('div');
    card.className = 'question-block-card question-card';
    if (index >= 0) {
      card.dataset.index = String(index);
    }

    const TYPES = ['sort', 'assign', 'mc', 'swipe', 'photoText', 'flip'];
    const labelMap = {
      mc: window.transQuizTypeMc || 'Multiple Choice',
      assign: window.transQuizTypeAssign || 'Assign',
      sort: window.transQuizTypeSort || 'Sort',
      swipe: window.transQuizTypeSwipe || 'Swipe',
      photoText: window.transQuizTypePhotoText || 'Foto+Text',
      flip: window.transQuizTypeFlip || 'Wusstest du?'
    };
    const abbrMap = { mc: 'MC', assign: 'A', sort: 'S', swipe: 'T', photoText: 'P', flip: 'F' };
    const colorMap = { sort: '#1e87f0', assign: '#32d296', mc: '#f0506e', swipe: '#faa05a', flip: '#7c5cbf', photoText: '#6c757d' };
    const infoMap = {
      sort: window.transQuizInfoSort || 'Put items in the correct order.',
      assign: window.transQuizInfoAssign || 'Match terms to their definitions.',
      mc: window.transQuizInfoMc || 'Multiple choice (several answers possible).',
      swipe: window.transQuizInfoSwipe || 'Swipe cards left or right.',
      photoText: window.transQuizInfoPhotoText || 'Take a photo and enter the matching answer.',
      flip: window.transQuizInfoFlip || 'Question with a flippable answer card.'
    };

    // Hidden select preserved for collect() compatibility
    const typeSelect = document.createElement('select');
    typeSelect.className = 'type-select';
    typeSelect.style.display = 'none';
    TYPES.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t;
      opt.textContent = labelMap[t] || t;
      typeSelect.appendChild(opt);
    });
    typeSelect.value = q.type || 'mc';

    // ── Summary row ──────────────────────────────────────────────────────
    const summary = document.createElement('div');
    summary.className = 'card-row__summary';

    const dragHandle = document.createElement('div');
    dragHandle.className = 'card-row__drag';
    dragHandle.dataset.dragHandle = 'true';
    dragHandle.setAttribute('aria-hidden', 'true');
    dragHandle.setAttribute('uk-icon', 'table');
    summary.appendChild(dragHandle);

    const quizColorMap = { sort: 'badge-blue', assign: 'badge-green', mc: 'badge-red', swipe: 'badge-orange', flip: 'badge-purple', photoText: 'badge-gray' };
    const typeBadge = document.createElement('div');
    typeBadge.className = 'card-row__badge ' + (quizColorMap[q.type || 'mc'] || 'badge-muted');
    typeBadge.textContent = abbrMap[q.type || 'mc'] || '?';
    typeBadge.title = labelMap[q.type || 'mc'] || '';
    summary.appendChild(typeBadge);

    const numberBadge = document.createElement('span');
    numberBadge.className = 'question-block-card__number';
    numberBadge.textContent = index >= 0 ? String(index + 1) : '#';
    summary.appendChild(numberBadge);

    const infoEl = document.createElement('div');
    infoEl.className = 'card-row__info';
    const titleEl = document.createElement('div');
    titleEl.className = 'card-row__title';
    titleEl.textContent = q.prompt || (window.transNewQuestion || 'New question');
    const metaEl = document.createElement('div');
    metaEl.className = 'card-row__meta';
    infoEl.appendChild(titleEl);
    infoEl.appendChild(metaEl);
    summary.appendChild(infoEl);

    const actions = document.createElement('div');
    actions.className = 'card-row__actions';
    const editBtn = document.createElement('button');
    editBtn.setAttribute('uk-icon', 'pencil');
    editBtn.setAttribute('aria-label', window.transEditQuestion || 'Edit');
    editBtn.setAttribute('type', 'button');
    editBtn.className = 'btn-edit';
    const dupBtn = document.createElement('button');
    dupBtn.setAttribute('uk-icon', 'copy');
    dupBtn.setAttribute('aria-label', window.transDuplicate || 'Duplicate');
    dupBtn.setAttribute('type', 'button');
    dupBtn.className = 'btn-duplicate';
    const deleteBtn = document.createElement('button');
    deleteBtn.setAttribute('uk-icon', 'trash');
    deleteBtn.setAttribute('aria-label', window.transRemove || 'Remove');
    deleteBtn.setAttribute('type', 'button');
    deleteBtn.className = 'btn-delete';
    actions.appendChild(editBtn);
    actions.appendChild(dupBtn);
    actions.appendChild(deleteBtn);
    summary.appendChild(actions);
    card.appendChild(summary);

    // ── Edit area ────────────────────────────────────────────────────────
    const editArea = document.createElement('div');
    editArea.className = 'question-block-card__edit-area';
    if (index >= 0) editArea.classList.add('is-collapsed'); // existing cards start collapsed

    // Type selector grid
    const typeGrid = document.createElement('div');
    typeGrid.className = 'question-type-grid uk-margin-small-bottom';
    TYPES.forEach(t => {
      const opt = document.createElement('div');
      opt.className = 'question-type-option' + (typeSelect.value === t ? ' is-active' : '');
      opt.setAttribute('role', 'button');
      opt.setAttribute('tabindex', '0');
      opt.setAttribute('aria-label', labelMap[t] || t);
      const badge = document.createElement('div');
      badge.className = 'question-type-option__badge';
      badge.style.background = colorMap[t] || '#999';
      badge.textContent = abbrMap[t] || t;
      const lbl = document.createElement('span');
      lbl.textContent = labelMap[t] || t;
      opt.appendChild(badge);
      opt.appendChild(lbl);
      opt.addEventListener('click', () => {
        typeSelect.value = t;
        typeGrid.querySelectorAll('.question-type-option').forEach(o => o.classList.remove('is-active'));
        opt.classList.add('is-active');
        typeBadge.className = 'card-row__badge ' + (quizColorMap[t] || 'badge-muted');
        typeBadge.textContent = abbrMap[t] || '?';
        typeBadge.title = labelMap[t] || '';
        updateInfo();
        renderFields();
        updatePointsState();
        updatePreview();
        updateSummary();
      });
      opt.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); opt.click(); } });
      typeGrid.appendChild(opt);
    });
    editArea.appendChild(typeSelect);
    editArea.appendChild(typeGrid);

    const typeInfo = document.createElement('div');
    typeInfo.className = 'uk-alert-primary uk-margin-small-bottom type-info';
    editArea.appendChild(typeInfo);

    const prompt = document.createElement('textarea');
    prompt.className = 'uk-textarea uk-margin-small-bottom prompt';
    prompt.placeholder = window.transQuestionText || 'Question text';
    prompt.value = q.prompt || '';
    editArea.appendChild(prompt);

    const countdownEnabled = isCountdownFeatureEnabled();
    const defaultCountdown = getDefaultCountdownSeconds();
    const countdownId = `countdown-${cardIndex}`;
    const countdownGroup = document.createElement('div');
    countdownGroup.className = 'uk-margin-small-bottom';
    const countdownLabel = document.createElement('label');
    countdownLabel.className = 'uk-form-label';
    countdownLabel.setAttribute('for', countdownId);
    countdownLabel.textContent = window.transCountdownLabel || 'Time limit (seconds)';
    const countdownInput = document.createElement('input');
    countdownInput.className = 'uk-input countdown-input';
    countdownInput.type = 'number';
    countdownInput.min = '0';
    countdownInput.id = countdownId;
    const hasCountdown = Object.prototype.hasOwnProperty.call(q, 'countdown');
    if (hasCountdown && q.countdown !== null && q.countdown !== undefined) {
      countdownInput.value = String(q.countdown);
    }
    countdownInput.placeholder = defaultCountdown !== null ? (window.transCountdownDefault || 'Default: %ss').replace('%s', defaultCountdown) : (window.transCountdownPlaceholder || 'e.g. 45');
    countdownInput.disabled = !countdownEnabled;
    const countdownMeta = document.createElement('div');
    countdownMeta.className = 'uk-text-meta';
    const countdownDisabledHint = cfgFields.countdownEnabled
      ? (window.transCountdownEnableHintExtras || 'Enable countdown under "Extras" in the event settings to set a time limit.')
      : (window.transCountdownEnableHint || 'Enable countdown to set a time limit.');
    countdownMeta.textContent = countdownEnabled
      ? (window.transCountdownTimerHint || 'Leave empty for default, 0 disables the timer.')
      : countdownDisabledHint;
    countdownGroup.appendChild(countdownLabel);
    countdownGroup.appendChild(countdownInput);
    countdownGroup.appendChild(countdownMeta);
    editArea.appendChild(countdownGroup);

    const pointsId = `points-${cardIndex}`;
    const pointsGroup = document.createElement('div');
    pointsGroup.className = 'uk-margin-small-bottom question-points-group';
    const pointsLabel = document.createElement('label');
    pointsLabel.className = 'uk-form-label';
    pointsLabel.setAttribute('for', pointsId);
    pointsLabel.textContent = window.transPointsRange || 'Points (0\u201310000)';
    const pointsInput = document.createElement('input');
    pointsInput.className = 'uk-input points-input';
    pointsInput.type = 'number';
    pointsInput.id = pointsId;
    pointsInput.min = '0';
    pointsInput.max = '10000';
    pointsInput.step = '1';
    pointsInput.setAttribute('aria-label', window.transPointsPerQuestion || 'Points per question');
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
    editArea.appendChild(pointsGroup);

    const fields = document.createElement('div');
    fields.className = 'fields';
    editArea.appendChild(fields);

    const previewLabel = document.createElement('div');
    previewLabel.className = 'question-block-card__preview-label';
    previewLabel.textContent = window.transPreview || 'Vorschau';
    editArea.appendChild(previewLabel);

    const preview = document.createElement('div');
    preview.className = 'uk-card qr-card uk-card-body question-preview';
    editArea.appendChild(preview);

    const collapseLink = document.createElement('button');
    collapseLink.type = 'button';
    collapseLink.className = 'question-block-card__collapse-btn';
    collapseLink.textContent = window.transCollapse || 'Einklappen';
    collapseLink.addEventListener('click', () => {
      editArea.classList.add('is-collapsed');
      editBtn.classList.remove('is-active');
    });
    editArea.appendChild(collapseLink);

    card.appendChild(editArea);

    // ── Edit toggle ───────────────────────────────────────────────────────
    function toggleEdit() {
      const opening = editArea.classList.contains('is-collapsed');
      editArea.classList.toggle('is-collapsed');
      editBtn.classList.toggle('is-active', !editArea.classList.contains('is-collapsed'));
      if (opening) updatePreview();
    }
    editBtn.addEventListener('click', toggleEdit);
    // Double-click on summary row also toggles edit
    summary.addEventListener('dblclick', e => {
      if (e.target.closest('.question-block-card__actions')) return;
      toggleEdit();
    });

    deleteBtn.addEventListener('click', () => {
      if (!confirm(window.transConfirmQuestionDelete || 'Frage wirklich löschen?')) return;
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
            notify(window.transErrorDeleteFailed || 'Delete failed', 'danger');
          });
      } else {
        card.remove();
        saveQuestions();
      }
    });

    dupBtn.addEventListener('click', () => {
      const data = collectSingle(card);
      if (!data) return;
      const clone = createCard(data, -1);
      card.after(clone);
      saveQuestions();
    });

    // ── updateInfo ────────────────────────────────────────────────────────
    function updateInfo() {
      typeInfo.textContent = (infoMap[typeSelect.value] || '') + ' ' + (window.transQuizInfoSoftHyphen || 'For small displays you can use "/-" as a hidden soft hyphen.');
    }
    updateInfo();

    // ── updateSummary ─────────────────────────────────────────────────────
    function updateSummary() {
      const t = typeSelect.value;
      typeBadge.className = 'question-block-card__icon question-block-card__icon--' + t;
      typeBadge.textContent = abbrMap[t] || '?';
      titleEl.textContent = prompt.value.trim() || (window.transNewQuestion || 'New question');
      const pts = getPointsValue(card, t);
      const ptsLabel = t === 'flip'
        ? (window.transNoScoring || 'No scoring')
        : (pts === 1 ? (window.transOnePoint || '1 point') : `${pts} ${window.transPoints || 'points'}`);
      let itemCount = '';
      if (t === 'sort') {
        const n = fields.querySelectorAll('.item-row').length;
        itemCount = n > 0 ? ` \u00b7 ${n} ${window.transEntriesAbbr || 'entries'}` : '';
      } else if (t === 'assign') {
        const n = fields.querySelectorAll('.term-row').length;
        itemCount = n > 0 ? ` \u00b7 ${n} ${window.transPairs || 'pairs'}` : '';
      } else if (t === 'mc') {
        const n = fields.querySelectorAll('.option-row').length;
        itemCount = n > 0 ? ` \u00b7 ${n} ${window.transOptionsAbbr || 'opts.'}` : '';
      } else if (t === 'swipe') {
        const n = fields.querySelectorAll('.card-row').length;
        itemCount = n > 0 ? ` \u00b7 ${n} ${window.transCards || 'cards'}` : '';
      }
      metaEl.textContent = (labelMap[t] || t) + ' \u00b7 ' + ptsLabel + itemCount;
    }
    updateSummary();

    // ── updatePointsState ─────────────────────────────────────────────────
    function updatePointsState() {
      const scorable = typeSelect.value !== 'flip';
      if (!scorable) {
        const parsed = parseQuestionPoints(pointsInput.value);
        if (parsed !== null) { lastScorablePoints = parsed; }
        pointsInput.value = '0';
        pointsInput.disabled = true;
        pointsMeta.textContent = window.transFlipNoScoring || 'This question type does not award points.';
      } else {
        pointsInput.disabled = false;
        const parsed = parseQuestionPoints(pointsInput.value);
        const fallback = Number.isFinite(lastScorablePoints) ? lastScorablePoints : 1;
        const value = parsed === null ? fallback : parsed;
        const normalized = normalizeQuestionPoints(value, true);
        pointsInput.value = String(normalized);
        lastScorablePoints = normalized;
        pointsMeta.textContent = window.transPointsHint || 'Points per question (0\u201310000). Empty defaults to 1 point.';
      }
    }

    pointsInput.addEventListener('input', () => {
      if (typeSelect.value !== 'flip') {
        const parsed = parseQuestionPoints(pointsInput.value);
        if (parsed !== null) {
          const normalized = normalizeQuestionPoints(parsed, true);
          if (String(normalized) !== pointsInput.value) { pointsInput.value = String(normalized); }
          lastScorablePoints = normalized;
        }
      }
      updatePreview();
      updateSummary();
    });

    pointsInput.addEventListener('blur', () => {
      if (typeSelect.value === 'flip') { return; }
      const parsed = parseQuestionPoints(pointsInput.value);
      const fallback = Number.isFinite(lastScorablePoints) ? lastScorablePoints : 1;
      const value = parsed === null ? fallback : parsed;
      const normalized = normalizeQuestionPoints(value, true);
      pointsInput.value = String(normalized);
      lastScorablePoints = normalized;
      updatePreview();
      updateSummary();
    });

    prompt.addEventListener('input', () => { updateSummary(); updatePreview(); });

    // ── Helper functions for type-specific fields ─────────────────────────
    function addItem(value = '') {
      const div = document.createElement('div');
      div.className = 'uk-flex uk-margin-small-bottom item-row';
      const input = document.createElement('input');
      input.className = 'uk-input item';
      input.type = 'text';
      input.value = value;
      input.setAttribute('aria-label', window.transItem || 'Item');
      const btn = document.createElement('button');
      btn.className = 'uk-icon-button uk-button-danger uk-button-small uk-margin-left';
      btn.setAttribute('uk-icon', 'trash');
      btn.setAttribute('aria-label', window.transRemove || 'Remove');
      btn.type = 'button';
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
      tInput.placeholder = window.transTerm || 'Term';
      tInput.value = term;
      tInput.setAttribute('aria-label', window.transTerm || 'Term');
      const dInput = document.createElement('input');
      dInput.className = 'uk-input definition';
      dInput.type = 'text';
      dInput.placeholder = window.transDefinition || 'Definition';
      dInput.value = def;
      dInput.setAttribute('aria-label', window.transDefinition || 'Definition');
      const rem = document.createElement('button');
      rem.className = 'uk-icon-button uk-button-danger uk-button-small';
      rem.setAttribute('uk-icon', 'trash');
      rem.setAttribute('aria-label', window.transRemove || 'Remove');
      rem.type = 'button';
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
      row.className = 'uk-flex uk-flex-middle uk-margin-small-bottom option-row';
      const cbId = 'cb-' + Math.random().toString(36).slice(2, 8);
      const cbLabel = document.createElement('label');
      cbLabel.className = 'uk-flex uk-flex-middle uk-margin-small-right';
      cbLabel.setAttribute('for', cbId);
      cbLabel.style.gap = '4px';
      cbLabel.style.whiteSpace = 'nowrap';
      const radio = document.createElement('input');
      radio.type = 'checkbox';
      radio.className = 'uk-checkbox answer';
      radio.name = 'ans' + cardIndex;
      radio.checked = checked;
      radio.id = cbId;
      const cbText = document.createElement('span');
      cbText.className = 'uk-text-meta';
      cbText.style.fontSize = '0.75rem';
      cbText.textContent = window.transCorrect || 'Correct';
      cbLabel.appendChild(radio);
      cbLabel.appendChild(cbText);
      const input = document.createElement('input');
      input.className = 'uk-input option uk-margin-small-left';
      input.type = 'text';
      input.value = text;
      input.setAttribute('aria-label', window.transAnswerText || 'Answer text');
      const rem = document.createElement('button');
      rem.className = 'uk-icon-button uk-button-danger uk-button-small uk-margin-left';
      rem.setAttribute('uk-icon', 'trash');
      rem.setAttribute('aria-label', window.transRemove || 'Remove');
      rem.type = 'button';
      rem.onclick = () => { row.remove(); saveQuestions(); };
      row.appendChild(cbLabel);
      row.appendChild(input);
      row.appendChild(rem);
      return row;
    }

    function addCard(text = '', correct = false) {
      const row = document.createElement('div');
      row.className = 'swipe-card-row card-row';
      const input = document.createElement('input');
      input.className = 'uk-input card-text';
      input.type = 'text';
      input.value = text;
      input.placeholder = window.transCardText || 'Card text';
      input.setAttribute('aria-label', window.transCardText || 'Card text');
      const checkId = 'cc-' + Math.random().toString(36).slice(2, 8);
      const checkLabel = document.createElement('label');
      checkLabel.className = 'swipe-card-row__label';
      checkLabel.setAttribute('for', checkId);
      checkLabel.setAttribute('title', window.transSwipeRightCorrect || '\u2192 Swipe right = correct answer');
      const check = document.createElement('input');
      check.type = 'checkbox';
      check.className = 'uk-checkbox card-correct';
      check.checked = correct;
      check.id = checkId;
      const checkSpan = document.createElement('span');
      checkSpan.textContent = '\u2192';
      checkLabel.appendChild(checkSpan);
      checkLabel.appendChild(check);
      const rem = document.createElement('button');
      rem.className = 'uk-icon-button uk-button-danger uk-button-small';
      rem.setAttribute('uk-icon', 'trash');
      rem.setAttribute('aria-label', window.transRemove || 'Remove');
      rem.type = 'button';
      rem.onclick = () => { row.remove(); saveQuestions(); };
      row.appendChild(input);
      row.appendChild(checkLabel);
      row.appendChild(rem);
      return row;
    }

    // ── renderFields ──────────────────────────────────────────────────────
    function renderFields() {
      fields.innerHTML = '';
      if (typeSelect.value === 'sort') {
        const list = document.createElement('div');
        (q.items || ['', '']).forEach(it => list.appendChild(addItem(it)));
        const add = document.createElement('button');
        add.className = 'uk-icon-button uk-button-primary uk-margin-small-top';
        add.setAttribute('uk-icon', 'plus');
        add.setAttribute('aria-label', window.transAddItem || 'Add item');
        add.type = 'button';
        add.onclick = e => { e.preventDefault(); list.appendChild(addItem('')); };
        const hint = document.createElement('p');
        hint.className = 'uk-text-meta uk-margin-small-top';
        hint.textContent = window.transSortHint || 'Enter items in the correct order \u2013 they will be shuffled for the player.';
        fields.appendChild(list);
        fields.appendChild(add);
        fields.appendChild(hint);
      } else if (typeSelect.value === 'assign') {
        const header = document.createElement('div');
        header.className = 'assign-column-header';
        const hBegriff = document.createElement('span'); hBegriff.textContent = window.transTerm || 'Term';
        const hDef = document.createElement('span'); hDef.textContent = window.transDefinition || 'Definition';
        const hDel = document.createElement('span');
        header.appendChild(hBegriff); header.appendChild(hDef); header.appendChild(hDel);
        const list = document.createElement('div');
        (q.terms || [{ term: '', definition: '' }]).forEach(p => list.appendChild(addPair(p.term, p.definition)));
        const add = document.createElement('button');
        add.className = 'uk-icon-button uk-button-primary uk-margin-small-top';
        add.setAttribute('uk-icon', 'plus');
        add.setAttribute('aria-label', window.transAddTerm || 'Add term');
        add.type = 'button';
        add.onclick = e => { e.preventDefault(); list.appendChild(addPair('', '')); };
        fields.appendChild(header);
        fields.appendChild(list);
        fields.appendChild(add);
      } else if (typeSelect.value === 'swipe') {
        const right = document.createElement('input');
        right.className = 'uk-input uk-margin-small-bottom right-label';
        right.type = 'text';
        right.placeholder = window.transSwipeRightPlaceholder || 'Label right (\u27A1, e.g. Yes)';
        right.style.borderColor = 'green';
        right.value = q.rightLabel || '';
        right.setAttribute('aria-label', window.transSwipeRightLabel || 'Label for swipe right');
        right.setAttribute('uk-tooltip', 'title: ' + (window.transSwipeRightTooltip || 'Text shown when swiping right.') + '; pos: right');
        const left = document.createElement('input');
        left.className = 'uk-input uk-margin-small-bottom left-label';
        left.type = 'text';
        left.placeholder = window.transSwipeLeftPlaceholder || 'Label left (\u2B05, e.g. No)';
        left.style.borderColor = 'red';
        left.value = q.leftLabel || '';
        left.setAttribute('aria-label', window.transSwipeLeftLabel || 'Label for swipe left');
        left.setAttribute('uk-tooltip', 'title: ' + (window.transSwipeLeftTooltip || 'Text shown when swiping left.') + '; pos: right');
        fields.appendChild(right);
        fields.appendChild(left);
        const header = document.createElement('div');
        header.className = 'swipe-card-header';
        const hText = document.createElement('span'); hText.textContent = window.transCardText || 'Card text';
        const hCorrect = document.createElement('span');
        hCorrect.textContent = window.transSwipeCorrect || '\u2192 Correct';
        hCorrect.setAttribute('title', window.transSwipeRightCorrect || '\u2192 Swipe right = correct answer');
        const hDel = document.createElement('span');
        header.appendChild(hText); header.appendChild(hCorrect); header.appendChild(hDel);
        const list = document.createElement('div');
        (q.cards || [{ text: '', correct: false }]).forEach(c => list.appendChild(addCard(c.text, c.correct)));
        const add = document.createElement('button');
        add.className = 'uk-icon-button uk-button-primary uk-margin-small-top';
        add.setAttribute('uk-icon', 'plus');
        add.setAttribute('aria-label', window.transAddCard || 'Add card');
        add.type = 'button';
        add.onclick = e => { e.preventDefault(); list.appendChild(addCard('', false)); };
        fields.appendChild(header);
        fields.appendChild(list);
        fields.appendChild(add);
      } else if (typeSelect.value === 'flip') {
        const ans = document.createElement('textarea');
        ans.className = 'uk-textarea uk-margin-small-bottom flip-answer';
        ans.placeholder = window.transAnswer || 'Answer';
        ans.value = q.answer || '';
        ans.setAttribute('aria-label', window.transAnswer || 'Answer');
        fields.appendChild(ans);
      } else if (typeSelect.value === 'photoText') {
        const consent = document.createElement('label');
        consent.className = 'uk-margin-small-bottom';
        consent.innerHTML = '<input type="checkbox" class="uk-checkbox consent-box"> ' + (window.transShowPrivacyCheckbox || 'Show privacy checkbox');
        const chk = consent.querySelector('input');
        if (q.consent) chk.checked = true;
        fields.appendChild(consent);
      } else {
        // mc
        const list = document.createElement('div');
        (q.options || ['', '']).forEach((opt, i) => list.appendChild(addOption(opt, (q.answers || []).includes(i))));
        const add = document.createElement('button');
        add.className = 'uk-icon-button uk-button-primary uk-margin-small-top';
        add.setAttribute('uk-icon', 'plus');
        add.setAttribute('aria-label', window.transAddOption || 'Add option');
        add.type = 'button';
        add.onclick = e => { e.preventDefault(); list.appendChild(addOption('')); };
        fields.appendChild(list);
        fields.appendChild(add);
      }
    }

    renderFields();
    updatePointsState();

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
          timerLabel.textContent = (window.transTimeLimit || 'Time limit') + ':';
          const timerValue = document.createElement('span');
          timerValue.className = 'question-timer__value';
          timerValue.textContent = `${effectiveCountdown}s`;
          timer.appendChild(timerLabel);
          timer.appendChild(timerValue);
          preview.appendChild(timer);
        } else if (countdownValue === 0) {
          const noTimer = document.createElement('div');
          noTimer.className = 'uk-text-meta uk-margin-small-bottom';
          noTimer.textContent = window.transNoTimerForQuestion || 'No timer for this question.';
          preview.appendChild(noTimer);
        }
      }
      const scorable = typeSelect.value !== 'flip';
      const pointsValue = getPointsValue(card, typeSelect.value);
      const pointsInfo = document.createElement('div');
      pointsInfo.className = 'uk-text-meta uk-margin-small-bottom';
      if (scorable) {
        pointsInfo.textContent = pointsValue === 1 ? (window.transOnePoint || '1 point') : `${pointsValue} ${window.transPoints || 'points'}`;
      } else {
        pointsInfo.textContent = window.transNoScoring || 'No scoring';
      }
      preview.appendChild(pointsInfo);
      const h = document.createElement('h4');
      h.textContent = insertSoftHyphens(prompt.value || (window.transPreview || 'Preview'));
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
        const flipContainer = document.createElement('div');
        flipContainer.style.perspective = '600px';
        flipContainer.style.height = '120px';
        const flipCard = document.createElement('div');
        flipCard.style.width = '100%';
        flipCard.style.height = '100%';
        flipCard.style.cursor = 'pointer';
        flipCard.style.position = 'relative';
        const flipFront = document.createElement('div');
        flipFront.style.cssText = 'display:flex;align-items:center;justify-content:center;padding:1rem;height:100%;background:#f8f9fa;border-radius:8px;box-sizing:border-box;';
        flipFront.textContent = insertSoftHyphens(prompt.value || (window.transPreviewQuestion || 'Question'));
        const flipBack = document.createElement('div');
        flipBack.style.cssText = 'display:none;align-items:center;justify-content:center;padding:1rem;height:100%;background:var(--brand-primary,#1e87f0);color:#fff;border-radius:8px;box-sizing:border-box;';
        const ans = fields.querySelector('.flip-answer');
        flipBack.textContent = insertSoftHyphens(ans ? ans.value : (window.transPreviewAnswer || 'Answer'));
        flipCard.appendChild(flipFront);
        flipCard.appendChild(flipBack);
        flipContainer.appendChild(flipCard);
        let flipped = false;
        flipCard.addEventListener('click', () => {
          flipped = !flipped;
          flipFront.style.display = flipped ? 'none' : 'flex';
          flipBack.style.display = flipped ? 'flex' : 'none';
        });
        const flipHintPrev = document.createElement('p');
        flipHintPrev.className = 'uk-text-meta';
        flipHintPrev.style.fontSize = '0.8rem';
        flipHintPrev.textContent = window.transPreviewClickToReveal || 'Click to reveal';
        preview.appendChild(flipContainer);
        preview.appendChild(flipHintPrev);
      } else if (typeSelect.value === 'photoText') {
        const photoMock = document.createElement('div');
        photoMock.style.display = 'flex';
        photoMock.style.flexDirection = 'column';
        photoMock.style.gap = '0.5rem';
        const photoBtn = document.createElement('button');
        photoBtn.type = 'button';
        photoBtn.className = 'uk-button uk-button-default';
        photoBtn.disabled = true;
        photoBtn.textContent = window.transPreviewTakePhoto || '\uD83D\uDCF7 Take photo';
        const textInput = document.createElement('input');
        textInput.type = 'text';
        textInput.className = 'uk-input';
        textInput.disabled = true;
        textInput.placeholder = window.transAnswerInputPlaceholder || 'Enter answer \u2026';
        photoMock.appendChild(photoBtn);
        photoMock.appendChild(textInput);
        preview.appendChild(photoMock);
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

    fields.addEventListener('input', () => { updatePreview(); updateSummary(); });
    countdownInput.addEventListener('input', updatePreview);
    countdownInput.addEventListener('change', updatePreview);
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

  function collectSingle(card) {
    const type = card.querySelector('.type-select').value;
    const prompt = card.querySelector('.prompt').value.trim();
    const obj = { type, prompt };
    const countdown = getCountdownValue(card);
    if (countdown !== null) obj.countdown = countdown;
    obj.points = getPointsValue(card, type);
    if (type === 'sort') {
      obj.items = Array.from(card.querySelectorAll('.item-row .item')).map(i => i.value.trim()).filter(Boolean);
    } else if (type === 'assign') {
      obj.terms = Array.from(card.querySelectorAll('.term-row')).map(r => ({
        term: r.querySelector('.term').value.trim(),
        definition: r.querySelector('.definition').value.trim()
      })).filter(t => t.term || t.definition);
    } else if (type === 'swipe') {
      obj.cards = Array.from(card.querySelectorAll('.card-row')).map(r => ({
        text: r.querySelector('.card-text').value.trim(),
        correct: r.querySelector('.card-correct').checked
      })).filter(c => c.text);
      const rl = card.querySelector('.right-label');
      const ll = card.querySelector('.left-label');
      if (rl && rl.value.trim()) obj.rightLabel = rl.value.trim();
      if (ll && ll.value.trim()) obj.leftLabel = ll.value.trim();
    } else if (type === 'flip') {
      const ans = card.querySelector('.flip-answer');
      obj.answer = ans ? ans.value.trim() : '';
    } else if (type === 'photoText') {
      const chk = card.querySelector('.consent-box');
      obj.consent = chk ? chk.checked : false;
    } else {
      obj.options = Array.from(card.querySelectorAll('.option-row .option')).map(i => i.value.trim()).filter(Boolean);
      const checks = Array.from(card.querySelectorAll('.option-row .answer'));
      obj.answers = checks.map((c, i) => (c.checked ? i : -1)).filter(i => i >= 0);
    }
    return obj;
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
          notify(window.transErrorSaveFailed || 'Save failed', 'danger');
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
  if (addBtn && container) {
    addBtn.addEventListener('click', function (e) {
      e.preventDefault();
      container.appendChild(
        createCard({ type: 'mc', prompt: '', points: 1, options: ['', ''], answers: [0] }, -1)
      );
    });
  }

  if (newCatBtn) {
    newCatBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (!catalogManager) return;
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
  }


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
        notify(window.transResultsCleared || 'Results cleared', 'success');
        resultsResetModal?.hide();
        window.location.reload();
      })
      .catch(err => {
        console.error(err);
        notify(window.transErrorDeleteFailed || 'Delete failed', 'danger');
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
      notify(window.transErrorDownloadFailed || 'Download failed', 'danger');
    });
  });

  resultsPdfBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    window.open(withBase('/results.pdf'), '_blank');
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
    const resultsCodeEl = document.getElementById('summaryResultsUrl');
    const resultsLinkEl = document.getElementById('summaryResultsLink');
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
      apiFetch(appendNamespaceParam('/events.json'), opts).then(r => r.json()).catch(() => [])
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
      if (resultsCodeEl && resultsLinkEl && typeof window.buildResultsUrl === 'function') {
        const targetUrl = window.buildResultsUrl(nextConfig, ev.uid || '', '', {
          baseUrl: window.baseUrl || '',
          basePath,
          forceResults: true
        });
        if (targetUrl) {
          resultsCodeEl.textContent = targetUrl;
          resultsLinkEl.href = targetUrl;
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
      const allowedPlans = ['free', 'starter', 'standard'];
      const allowedBilling = ['credit'];
      if (!allowedPlans.includes(data.plan)) delete data.plan;
      if (!allowedBilling.includes(data.billing_info)) delete data.billing_info;
      apiFetch('/admin/profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify(window.transProfileSaved || 'Profile saved', 'success');
      }).catch(() => notify(window.transErrorSaveFailed || 'Save failed', 'danger'));
    });

    welcomeMailBtn?.addEventListener('click', e => {
      e.preventDefault();
      apiFetch('/admin/profile/welcome', { method: 'POST' })
        .then(r => {
          if (!r.ok) throw new Error('failed');
          notify(window.transWelcomeMailSent || 'Welcome email sent', 'success');
        })
        .catch(() => notify(window.transErrorSendFailed || 'Send failed', 'danger'));
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
          .catch(() => notify(window.transErrorGeneric || 'Error', 'danger'));
        return;
      }
      if (!plan) return;
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
    if (catSelect) {
      if (currentEventUid) {
        loadCatalogs();
      } else {
        applyCatalogList([]);
      }
    }
    if (teamsApi.teamListEl) teamsApi.loadTeamList();
    loadSummary();
  });

  // Page editors are handled in tiptap-pages.js

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
    const cards = document.getElementById('namespaceCards');
    const cardsEmpty = document.getElementById('namespaceCardsEmpty');
    const form = namespaceManager.querySelector('[data-namespace-form]');
    const input = namespaceManager.querySelector('[data-namespace-input]');
    const labelInput = namespaceManager.querySelector('[data-namespace-label-input]');
    const formError = namespaceManager.querySelector('[data-namespace-error]');
    const editModalEl = document.getElementById('namespaceEditModal');
    const editModal = editModalEl ? UIkit.modal(editModalEl) : null;
    const editTitle = editModalEl?.querySelector('[data-namespace-edit-title]') || null;
    const editLabel = editModalEl?.querySelector('[data-namespace-edit-label]') || null;
    const editInput = document.getElementById('namespaceEditInput');
    const editError = editModalEl?.querySelector('[data-namespace-edit-error]') || null;
    const editSave = document.getElementById('namespaceEditSave');
    const editCancel = document.getElementById('namespaceEditCancel');
    const labelDefault = namespaceManager.dataset.labelDefault || 'Default';
    const labelInactive = namespaceManager.dataset.labelInactive || 'Inactive';
    const namespacePattern = namespaceManager.dataset.namespacePattern || '^[a-z0-9][a-z0-9-]*$';
    const namespaceMaxLength = Number.parseInt(namespaceManager.dataset.namespaceMaxLength || '100', 10) || 100;
    const columnNamespace = namespaceManager.dataset.columnNamespace || 'Namespace';
    const columnLabel = namespaceManager.dataset.columnLabel || 'Label';
    const columnStatus = namespaceManager.dataset.columnStatus || 'Status';
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

    const namespaceColumns = [
      { key: 'namespace', label: columnNamespace, editable: true, ariaDesc: columnNamespace },
      { key: 'label', label: columnLabel, editable: true, ariaDesc: columnLabel, render: item => item.label || '-' },
      {
        key: 'status',
        label: columnStatus,
        render: item => {
          if (item.is_default) {
            return labelDefault;
          }
          if (item.is_active === false) {
            return labelInactive;
          }
          return '-';
        }
      }
    ];

    const namespaceTable = new TableManager({
      tbody: tableBody,
      columns: namespaceColumns,
      mobileCards: cards ? { container: cards } : null,
      onEdit: cell => openNamespaceEditor(cell),
      onDelete: id => handleNamespaceDelete(id)
    });

    const namespaceEditState = { id: null, key: null };

    const normalizeNamespace = value => String(value || '').trim().toLowerCase();
    const normalizeLabel = value => {
      const normalized = String(value ?? '').trim();
      return normalized === '' ? null : normalized;
    };
    const namespaceRegex = new RegExp(namespacePattern);
    const resolveErrorMessage = (error) => {
      const candidate = error?.message || '';
      if (typeof candidate === 'string' && candidate.trim().startsWith('{')) {
        try {
          const parsed = JSON.parse(candidate);
          if (parsed && typeof parsed.error === 'string') {
            return parsed.error;
          }
        } catch (_) {}
      }

      return candidate || messages.error;
    };

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

    const showFormError = (element, message) => {
      if (!element) return;
      const target = element === input ? formError : editError;
      if (target) {
        target.textContent = message;
        target.classList.remove('uk-hidden');
      }
      element.classList.add('uk-form-danger');
      element.setAttribute('aria-invalid', 'true');
    };

    const clearFormError = element => {
      if (!element) return;
      const target = element === input ? formError : editError;
      if (target) {
        target.textContent = '';
        target.classList.add('uk-hidden');
      }
      element.classList.remove('uk-form-danger');
      element.removeAttribute('aria-invalid');
    };

    const renderNamespaceMessage = message => {
      if (tableBody) {
        tableBody.innerHTML = '';
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = columnCount;
        td.textContent = message;
        tr.appendChild(td);
        tableBody.appendChild(tr);
      }
      if (cardsEmpty) {
        cardsEmpty.hidden = false;
        if (message) {
          cardsEmpty.textContent = message;
        }
      }
    };

    const clearNamespaceMessage = () => {
      if (cardsEmpty) {
        cardsEmpty.hidden = true;
        cardsEmpty.textContent = cardsEmpty.dataset.errorText || '';
      }
    };

    const parseJsonResponse = res => res
      .json()
      .catch(() => ({}))
      .then(data => {
        if (!res.ok) {
          throw new Error(data?.error || messages.error);
        }
        return data;
      });

    const mapNamespaces = entries => entries
      .map(item => {
        const namespaceValue = normalizeNamespace(item.namespace);
        return {
          id: namespaceValue,
          namespace: namespaceValue,
          label: typeof item.label === 'string' ? item.label : '',
          is_default: Boolean(item.is_default) || namespaceValue === defaultNamespace,
          is_active: item.is_active !== false
        };
      })
      .filter(item => item.namespace);

    const loadNamespaces = () => {
      namespaceTable.setColumnLoading('namespace', true);
      renderNamespaceMessage(messages.loading);
      apiFetch(listUrl)
        .then(parseJsonResponse)
        .then(data => {
          const entries = Array.isArray(data?.namespaces) ? data.namespaces : [];
          const normalized = mapNamespaces(entries);
          if (!normalized.length) {
            namespaceTable.render([]);
            renderNamespaceMessage(messages.empty);
            return;
          }
          clearNamespaceMessage();
          namespaceTable.render(normalized);
        })
        .catch(err => {
          const message = resolveErrorMessage(err);
          const finalMessage = message === messages.error && messages.tableMissing
            ? messages.tableMissing
            : message;
          namespaceTable.render([]);
          renderNamespaceMessage(finalMessage);
        })
        .finally(() => {
          namespaceTable.setColumnLoading('namespace', false);
        });
    };

    const persistNamespaceUpdate = (originalId, next) => {
      const payload = {
        namespace: normalizeNamespace(next.namespace),
        label: normalizeLabel(next.label)
      };
      return apiFetch(buildUrl(updateUrlTemplate, originalId), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(parseJsonResponse)
        .then(() => {
          notify(messages.updated, 'success');
          loadNamespaces();
        })
        .catch(err => {
          const message = resolveErrorMessage(err);
          if (message === messages.defaultLocked || message === messages.inUse) {
            notify(message, 'warning');
          } else {
            notify(message, 'danger');
          }
          throw err;
        });
    };

    const openNamespaceEditor = (cell) => {
      const key = cell?.dataset.key;
      const id = cell?.dataset.id;
      const item = namespaceTable.getData().find(entry => entry.id === id);
      if (!item || !key) {
        return;
      }
      if (!editModal || !editInput) {
        return;
      }
      if (item.is_default) {
        notify(messages.defaultLocked, 'warning');
        return;
      }
      namespaceEditState.id = id;
      namespaceEditState.key = key;
      clearFormError(editInput);
      if (editTitle) {
        editTitle.textContent = key === 'label' ? columnLabel : columnNamespace;
      }
      if (editLabel) {
        editLabel.textContent = key === 'label' ? columnLabel : columnNamespace;
      }
      editInput.maxLength = key === 'namespace' ? namespaceMaxLength : 255;
      editInput.value = item[key] || '';
      editModal.show();
    };

    const resetEditState = () => {
      namespaceEditState.id = null;
      namespaceEditState.key = null;
      clearFormError(editInput);
      if (editInput) {
        editInput.value = '';
      }
    };

    const saveNamespaceEdit = () => {
      const { id, key } = namespaceEditState;
      if (!id || !key || !editInput) {
        return;
      }
      const nextValue = key === 'namespace'
        ? normalizeNamespace(editInput.value)
        : editInput.value.trim();

      if (key === 'namespace') {
        const validationMessage = getNamespaceError(nextValue);
        if (validationMessage) {
          showFormError(editInput, validationMessage);
          notify(validationMessage, 'warning');
          return;
        }
      }

      const current = namespaceTable.getData().find(entry => entry.id === id);
      if (!current) {
        return;
      }

      const updated = { ...current, [key]: key === 'label' ? normalizeLabel(nextValue) : nextValue };
      persistNamespaceUpdate(current.id, updated)
        .then(() => {
          resetEditState();
          if (editModal) {
            editModal.hide();
          }
        })
        .catch(() => {});
    };

    const handleNamespaceDelete = id => {
      const item = namespaceTable.getData().find(entry => entry.id === id);
      if (!item) return;
      if (item.is_default) {
        notify(messages.defaultLocked, 'warning');
        return;
      }
      if (!window.confirm(messages.confirmDelete)) {
        return;
      }
      apiFetch(buildUrl(deleteUrlTemplate, item.id), { method: 'DELETE' })
        .then(parseJsonResponse)
        .then(() => {
          notify(messages.deleted, 'success');
          loadNamespaces();
        })
        .catch(err => {
          const message = resolveErrorMessage(err);
          if (message === messages.defaultLocked || message === messages.inUse) {
            notify(message, 'warning');
          } else {
            notify(message, 'danger');
          }
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
          showFormError(input, validationMessage);
          notify(validationMessage, 'warning');
          input.focus();
          return;
        }
        clearFormError(input);
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
            const message = resolveErrorMessage(err);
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
        clearFormError(input);
      });
    }

    editSave?.addEventListener('click', saveNamespaceEdit);
    editCancel?.addEventListener('click', () => {
      resetEditState();
      clearFormError(editInput);
    });

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
