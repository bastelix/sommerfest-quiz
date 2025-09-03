/* global UIkit */

import TableManager from './table-manager.js';

const basePath = window.basePath || '';
const withBase = path => basePath + path;
const escape = url => encodeURI(url);

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
  const ui = UIkit.modal(modal);
  UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
  ui.show();
}

window.apiFetch = (path, options = {}) => {
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
    headers
  };
  return fetch(withBase(path), opts).then(res => {
    if (res.status === 402) {
      showUpgradeModal();
      const err = new Error(window.transUpgradeText || 'upgrade-required');
      err.code = 'upgrade-required';
      throw err;
    }
    return res;
  });
};
window.notify = (msg, status = 'primary', timeout = 2000) => {
  if (typeof UIkit !== 'undefined' && UIkit.notification) {
    UIkit.notification({ message: msg, status, pos: 'top-center', timeout });
  } else {
    alert(msg);
  }
};

document.addEventListener('DOMContentLoaded', function () {
  const adminTabs = document.getElementById('adminTabs');
  const adminMenu = document.getElementById('adminMenu');
  const adminNav = document.getElementById('adminNav');
  const adminMenuToggle = document.getElementById('adminMenuToggle');

  if (window.domainType !== 'main') {
    adminTabs?.querySelector('[data-route="tenants"]')?.remove();
    adminMenu?.querySelector('a[href$="/admin/tenants"]')?.parentElement?.remove();
  }

  const adminRoutes = Array.from(adminTabs ? adminTabs.querySelectorAll('li') : [])
    .map(tab => tab.getAttribute('data-route') || '');
  const settingsInitial = window.quizSettings || {};
  const pagesInitial = window.pagesContent || {};
  const profileForm = document.getElementById('profileForm');
  const profileSaveBtn = document.getElementById('profileSaveBtn');
  const welcomeMailBtn = document.getElementById('welcomeMailBtn');
  const checkoutContainer = document.getElementById('stripe-checkout');
  const planButtons = document.querySelectorAll('.plan-select');
  const emailInput = document.getElementById('subscription-email');
  const planSelect = document.getElementById('planSelect');
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
          loadTenants(tenantStatusFilter?.value, tenantSearchInput?.value);
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
          notify(window.transImageReady || 'Image bereit', 'success');
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
    UIkit.icon(puzzleIcon, { icon: puzzleIcon.getAttribute('uk-icon').split(': ')[1] });
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
  // Verweise auf die Formularfelder
  const cfgFields = {
    logoFile: document.getElementById('cfgLogoFile'),
    logoPreview: document.getElementById('cfgLogoPreview'),
    pageTitle: document.getElementById('cfgPageTitle'),
    backgroundColor: document.getElementById('cfgBackgroundColor'),
    buttonColor: document.getElementById('cfgButtonColor'),
    checkAnswerButton: document.getElementById('cfgCheckAnswerButton'),
    qrUser: document.getElementById('cfgQRUser'),
    randomNames: document.getElementById('cfgRandomNames'),
    teamRestrict: document.getElementById('cfgTeamRestrict'),
    competitionMode: document.getElementById('cfgCompetitionMode'),
    teamResults: document.getElementById('cfgTeamResults'),
    photoUpload: document.getElementById('cfgPhotoUpload'),
    puzzleEnabled: document.getElementById('cfgPuzzleEnabled'),
    puzzleWord: document.getElementById('cfgPuzzleWord'),
    puzzleWrap: document.getElementById('cfgPuzzleWordWrap'),
    homePage: document.getElementById('cfgHomePage'),
    registrationEnabled: document.getElementById('cfgRegistrationEnabled')
  };
  const puzzleFeedbackBtn = document.getElementById('puzzleFeedbackBtn');
  const puzzleIcon = document.getElementById('puzzleFeedbackIcon');
  const puzzleLabel = document.getElementById('puzzleFeedbackLabel');
  const puzzleTextarea = document.getElementById('puzzleFeedbackTextarea');
  const puzzleSaveBtn = document.getElementById('puzzleFeedbackSave');
  const puzzleModal = UIkit.modal('#puzzleFeedbackModal');
  const inviteTextBtn = document.getElementById('inviteTextBtn');
  const inviteLabel = document.getElementById('inviteTextLabel');
  const inviteTextarea = document.getElementById('inviteTextTextarea');
  const inviteSaveBtn = document.getElementById('inviteTextSave');
  const inviteModal = UIkit.modal('#inviteTextModal');
  const inviteToolbar = document.getElementById('inviteTextToolbar');
  const commentTextarea = document.getElementById('catalogCommentTextarea');
  const commentSaveBtn = document.getElementById('catalogCommentSave');
  const commentModal = UIkit.modal('#catalogCommentModal');
  const commentToolbar = document.getElementById('catalogCommentToolbar');
  const resultsResetModal = UIkit.modal('#resultsResetModal');
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
        const ext = file && file.name.toLowerCase().endsWith('.webp') ? 'webp' : 'png';
        cfgInitial.logoPath = activeEventUid
          ? `/logo-${activeEventUid}.${ext}`
          : `/logo.${ext}`;
        cfgFields.logoPreview.src = withBase(cfgInitial.logoPath) + '?' + Date.now();
        notify('Logo hochgeladen', 'success');
      }
    });
  }
  // Füllt das Formular mit den Werten aus einem Konfigurationsobjekt
  function renderCfg(data) {
    if (cfgFields.logoPreview) {
      cfgFields.logoPreview.src = data.logoPath ? data.logoPath + '?' + Date.now() : '';
    }
    cfgFields.pageTitle.value = data.pageTitle || '';
    cfgFields.backgroundColor.value = data.backgroundColor || '';
    cfgFields.buttonColor.value = data.buttonColor || '';
    cfgFields.checkAnswerButton.checked = data.CheckAnswerButton !== 'no';
    cfgFields.qrUser.checked = !!data.QRUser;
    if (cfgFields.randomNames) {
      cfgFields.randomNames.checked = data.randomNames !== false;
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
    if (cfgFields.puzzleEnabled) {
      cfgFields.puzzleEnabled.checked = data.puzzleWordEnabled !== false;
    }
    if (cfgFields.puzzleWord) {
      cfgFields.puzzleWord.value = data.puzzleWord || '';
    }
    if (cfgFields.homePage) {
      cfgFields.homePage.value = settingsInitial.home_page || 'help';
    }
    if (cfgFields.registrationEnabled) {
      cfgFields.registrationEnabled.checked = settingsInitial.registration_enabled === '1';
    }
    puzzleFeedback = data.puzzleFeedback || '';
    updatePuzzleFeedbackUI();
    inviteText = data.inviteText || '';
    updateInviteTextUI();
    if (cfgFields.puzzleWrap) {
      cfgFields.puzzleWrap.style.display = cfgFields.puzzleEnabled.checked ? '' : 'none';
    }
  }
  renderCfg(cfgInitial);
  if (cfgFields.puzzleEnabled) {
    cfgFields.puzzleEnabled.addEventListener('change', () => {
      if (cfgFields.puzzleWrap) {
        cfgFields.puzzleWrap.style.display = cfgFields.puzzleEnabled.checked ? '' : 'none';
      }
    });
  }
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
  });

  inviteSaveBtn?.addEventListener('click', () => {
    if (!inviteTextarea) return;
    inviteText = inviteTextarea.value;
    updateInviteTextUI();
    inviteModal.hide();
    cfgInitial.inviteText = inviteText;
    notify('Einladungstext gespeichert', 'success');
  });

  commentSaveBtn?.addEventListener('click', () => {
    if (!currentCommentItem || !commentTextarea) return;
    currentCommentItem.comment = commentTextarea.value;
    catalogManager.render(catalogManager.getData());
    commentModal.hide();
    currentCommentItem = null;
  });

  cfgFields.homePage?.addEventListener('change', () => {
    settingsInitial.home_page = cfgFields.homePage.value;
    apiFetch('/settings.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ home_page: cfgFields.homePage.value })
    }).then(r => {
      if (r.ok) {
        notify('Einstellung gespeichert', 'success');
      } else {
        notify('Fehler beim Speichern', 'danger');
      }
    }).catch(() => notify('Fehler beim Speichern', 'danger'));
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
  [
    cfgFields.pageTitle,
    cfgFields.backgroundColor,
    cfgFields.buttonColor,
    // Former auto-save fields removed; form submission handled by backend.
  const summaryPrintBtn = document.getElementById('summaryPrintBtn');
  summaryPrintBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    window.print();
  });

  const openInvitesBtn = document.getElementById('openInvitesBtn');
  openInvitesBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    window.open(withBase('/invites.pdf'), '_blank');
  });

  document.querySelectorAll('.qr-print-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const team = btn.getAttribute('data-team');
      if (team) {
        window.open(withBase('/qr.pdf?t=' + encodeURIComponent(team) + '&logoText=QUIZ%0ARACE&rounded=1'), '_blank');
      }
    });
  });

  // --------- Fragen bearbeiten ---------
  const container = document.getElementById('questions');
  const addBtn = document.getElementById('addBtn');
  const saveBtn = document.getElementById('saveBtn');
  const resetBtn = document.getElementById('resetBtn');
  const catSelect = document.getElementById('catalogSelect');
  const catalogList = document.getElementById('catalogList');
  const newCatBtn = document.getElementById('newCatBtn');
  const catalogsSaveBtn = document.getElementById('catalogsSaveBtn');
  let catalogs = [];
  let catalogFile = '';
  let initial = [];

  const catalogColumns = [
    { key: 'slug', editable: true },
    { key: 'name', editable: true },
    { key: 'description', editable: true },
    { key: 'raetsel_buchstabe', editable: true },
    { key: 'comment', editable: true, ariaDesc: 'Kommentar bearbeiten' }
  ];

  const catalogManager = new TableManager({
    tbody: catalogList,
    mobileCards: { container: document.getElementById('catalogCards') },
    sortable: true,
    columns: catalogColumns,
    onEdit: editCatalogCell,
    onDelete: id => deleteCatalogById(id),
    onReorder: saveCatalogOrder
  });

  function editCatalogCell(cell) {
    const id = cell?.dataset.id;
    const key = cell?.dataset.key;
    if (!id || !key) return;
    const list = catalogManager.getData();
    const cat = list.find(c => c.id === id);
    if (!cat) return;
    if (key === 'comment') {
      currentCommentItem = cat;
      if (commentTextarea) commentTextarea.value = cat.comment || '';
      commentModal.show();
      return;
    }
    let val = prompt('', cat[key] || '');
    if (val === null) return;
    val = val.trim();
    if (key === 'slug') {
      cat.slug = val;
      cat.file = val ? val + '.json' : '';
    } else if (key === 'name') {
      cat.name = val;
      if (cat.new && !cat.slug) {
        const idSlug = uniqueId(val);
        cat.slug = idSlug;
        cat.file = idSlug ? idSlug + '.json' : '';
      }
    } else if (key === 'description') {
      cat.description = val;
    } else if (key === 'raetsel_buchstabe') {
      cat.raetsel_buchstabe = val;
    }
    catalogManager.render(list);
  }

  function saveCatalogOrder() {
    const list = catalogManager.getData();
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

    apiFetch('/kataloge/catalogs.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        catalogs = data.map(c => ({ ...c, id: c.uid }));
        catSelect.innerHTML = '';
        catalogs.forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.id;
          opt.textContent = c.name || c.sort_order || c.slug;
          catSelect.appendChild(opt);
        });
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Speichern', 'danger');
      });
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
      })
      .catch(() => {
        initial = [];
        renderAll(initial);
      });
  }

  apiFetch('/kataloge/catalogs.json', { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(list => {
      let needsRender = false;
      catalogs = list.map((c, i) => {
        if (!c.uid && !c.slug) {
          needsRender = true;
          return { ...c, id: Date.now() + i };
        }
        return { ...c, id: c.uid || c.slug };
      });
      catSelect.innerHTML = '';
      catalogs.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name || c.sort_order || c.slug;
        catSelect.appendChild(opt);
      });
      console.log(catalogs);
      catalogManager.render(catalogs);
      if (needsRender) {
        catalogManager.render(catalogs);
      }
      const params = new URLSearchParams(window.location.search);
      const slug = params.get('katalog');
      const selected = catalogs.find(c => (c.slug || c.sort_order) === slug) || catalogs[0];
      if (selected) {
        catSelect.value = selected.id;
        loadCatalog(selected.id);
      }
    })
    .catch(err => console.error(err));

  catSelect.addEventListener('change', () => loadCatalog(catSelect.value));

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
          catalogFile = '';
          initial = [];
          renderAll(initial);
        }
        notify('Katalog gelöscht', 'success');
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Löschen', 'danger');
      });
  }

  // Rendert alle Fragen im Editor neu
  function renderAll(data) {
    container.innerHTML = '';
    data.forEach((q, i) => container.appendChild(createCard(q, i)));
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
    });
    const prompt = document.createElement('textarea');
    prompt.className = 'uk-textarea uk-margin-small-bottom prompt';
    prompt.placeholder = 'Fragetext';
    prompt.value = q.prompt || '';
    const fields = document.createElement('div');
    fields.className = 'fields';
    const removeBtn = document.createElement('button');
    removeBtn.className = 'uk-icon-button uk-button-danger uk-margin-small-top uk-align-right';
    removeBtn.setAttribute('uk-icon', 'trash');
    removeBtn.setAttribute('aria-label', 'Entfernen');
    removeBtn.onclick = () => {
      const idx = card.dataset.index;
      if (idx !== undefined) {
        apiFetch('/kataloge/' + catalogFile + '/' + idx, { method: 'DELETE' })
          .then(r => {
            if (!r.ok) throw new Error(r.statusText);
            initial.splice(Number(idx), 1);
            renderAll(initial);
          })
          .catch(err => {
            console.error(err);
            notify('Fehler beim Löschen', 'danger');
          });
      } else {
        card.remove();
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
      btn.onclick = () => div.remove();
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
      rem.onclick = () => row.remove();
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
      rem.onclick = () => row.remove();
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
      rem.onclick = () => row.remove();
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

    // Vorschau-Bereich anlegen
    const preview = document.createElement('div');
    preview.className = 'uk-card qr-card uk-card-body question-preview';

    const formCol = document.createElement('div');
    formCol.appendChild(typeSelect);
    formCol.appendChild(typeInfo);
    formCol.appendChild(prompt);
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
    typeSelect.addEventListener('change', updatePreview);
    updatePreview();

    cardIndex++;
    return card;
  }

  // Sammelt alle Eingaben aus den Karten in ein Array von Fragen
  function collect() {
    return Array.from(container.querySelectorAll('.question-card')).map(card => {
      const type = card.querySelector('.type-select').value;
      const prompt = card.querySelector('.prompt').value.trim();
      if (type === 'sort') {
        const items = Array.from(card.querySelectorAll('.item-row .item'))
          .map(i => i.value.trim())
          .filter(Boolean);
        return { type, prompt, items };
      } else if (type === 'assign') {
        const terms = Array.from(card.querySelectorAll('.term-row')).map(r => ({
          term: r.querySelector('.term').value.trim(),
          definition: r.querySelector('.definition').value.trim()
        })).filter(t => t.term || t.definition);
        return { type, prompt, terms };
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
        return obj;
      } else if (type === 'flip') {
        const answer = card.querySelector('.flip-answer').value.trim();
        return { type, prompt, answer };
      } else if (type === 'photoText') {
        const consent = card.querySelector('.consent-box').checked;
        return { type, prompt, consent };
      } else {
        const options = Array.from(card.querySelectorAll('.option-row .option'))
          .map(i => i.value.trim())
          .filter(Boolean);
        const checks = Array.from(card.querySelectorAll('.option-row .answer'));
        const answers = checks
          .map((c, i) => (c.checked ? i : -1))
          .filter(i => i >= 0);
        return { type, prompt, options, answers };
      }
    });
  }

  // Speichert die eingegebenen Fragen
  saveBtn.addEventListener('click', function (e) {
    e.preventDefault();
    const data = collect();
    apiFetch('/kataloge/' + catalogFile, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    })
      .then(r => {
        if (r.ok) {
          notify('Fragen gespeichert', 'success');
        } else if (r.status === 400) {
          notify('Ungültige Daten', 'danger');
        } else {
          throw new Error(r.statusText);
        }
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Speichern', 'danger');
      });
  });

  // Setzt den Editor auf die Anfangswerte zurück
  resetBtn.addEventListener('click', function (e) {
    e.preventDefault();
    renderAll(initial);
  });

  // Fügt eine neue leere Frage hinzu
  addBtn.addEventListener('click', function (e) {
    e.preventDefault();
    container.appendChild(
      createCard({ type: 'mc', prompt: '', options: ['', ''], answers: [0] }, -1)
    );
  });

  newCatBtn.addEventListener('click', function (e) {
    e.preventDefault();
    const id = crypto.randomUUID();
    const item = { id, slug: '', file: '', name: '', description: '', raetsel_buchstabe: '', comment: '', new: true };
    const list = catalogManager.getData();
    list.push(item);
    catalogManager.render(list);
    const cell = document.querySelector(`[data-id="${id}"][data-key="name"]`);
    if (cell) editCatalogCell(cell);
  });

  catalogsSaveBtn?.addEventListener('click', async e => {
    e.preventDefault();
    const list = catalogManager.getData();
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

    apiFetch('/kataloge/catalogs.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        catalogs = data.map(c => ({ ...c, id: c.uid }));
        catSelect.innerHTML = '';
        catalogs.forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.id;
          opt.textContent = c.name || c.sort_order || c.slug;
          catSelect.appendChild(opt);
        });
        catalogManager.render(catalogs);
        if (!catalogFile && catalogs.length > 0) {
          catSelect.value = catalogs[0].id;
          loadCatalog(catSelect.value);
        }
        notify('Katalogliste gespeichert', 'success');
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Speichern', 'danger');
      });
  });


  const resultsResetBtn = document.getElementById('resultsResetBtn');
  const resultsDownloadBtn = document.getElementById('resultsDownloadBtn');
  const resultsPdfBtn = document.getElementById('resultsPdfBtn');

  resultsResetBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    resultsResetModal.show();
  });

  resultsResetConfirm?.addEventListener('click', function () {
    apiFetch('/results', { method: 'DELETE' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify('Ergebnisse gelöscht', 'success');
        resultsResetModal.hide();
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
  const eventAddBtn = document.getElementById('eventAddBtn');
  const eventSelect = document.getElementById('eventSelect');
  const eventSelectWrap = document.getElementById('eventSelectWrap');
  const eventOpenBtn = document.getElementById('eventOpenBtn');
  const langSelect = document.getElementById('langSelect');
  let activeEventUid = cfgInitial.event_uid || '';

  function collectEvents() {
    return Array.from(eventsListEl.querySelectorAll('.event-row')).map(row => {
      const publishedInput = row.querySelector('.event-published');
      const published = publishedInput ? publishedInput.checked : row.dataset.published === 'true';
      row.dataset.published = published.toString();
      return {
        uid: row.dataset.uid || crypto.randomUUID(),
        name: row.querySelector('.event-name').value.trim(),
        start_date: row.querySelector('.event-start').value.trim() || new Date().toISOString().slice(0, 16),
        end_date: row.querySelector('.event-end').value.trim() || new Date().toISOString().slice(0, 16),
        description: row.querySelector('.event-desc').value.trim(),
        published
      };
    }).filter(e => e.name);
  }

  function saveEvents() {
    const list = collectEvents();
    apiFetch('/events.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(list)
    })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify('Veranstaltungen gespeichert', 'success');
        populateEventSelect(list);
      })
      .catch(() => notify('Fehler beim Speichern', 'danger'));
    updateEventRowNumbers();
  }

  function updateEventRowNumbers() {
    Array.from(eventsListEl.querySelectorAll('.event-row')).forEach((row, idx) => {
      const cell = row.querySelector('.row-num .row-number');
      if (cell) cell.textContent = idx + 1;
    });
  }

  function createEventRow(ev = {}) {
    const row = document.createElement('tr');
    row.className = 'event-row';
    row.setAttribute('role', 'row');
    row.dataset.uid = ev.uid || crypto.randomUUID();
    row.dataset.published = ev.published ? 'true' : 'false';

    if (ev.uid === activeEventUid) {
      row.classList.add('active-event');
    }

    const labels = eventsListEl?.dataset || {};

    const handleCell = document.createElement('td');
    handleCell.setAttribute('role', 'cell');
    const handleLabel = document.createElement('span');
    handleLabel.className = 'cell-label uk-hidden@s';
    handleLabel.textContent = labels.labelActions || '';
    handleCell.appendChild(handleLabel);
    const handleSpan = document.createElement('span');
    handleSpan.className = 'uk-sortable-handle uk-icon';
    handleSpan.setAttribute('uk-icon', 'icon: table');
    handleCell.appendChild(handleSpan);

    const indexCell = document.createElement('td');
    indexCell.className = 'row-num';
    indexCell.setAttribute('role', 'cell');
    const indexLabel = document.createElement('span');
    indexLabel.className = 'cell-label uk-hidden@s';
    indexLabel.textContent = labels.labelNumber || '';
    const indexValue = document.createElement('span');
    indexValue.className = 'row-number';
    indexCell.appendChild(indexLabel);
    indexCell.appendChild(indexValue);

    const nameCell = document.createElement('td');
    nameCell.setAttribute('role', 'cell');
    const nameLabel = document.createElement('span');
    nameLabel.className = 'cell-label uk-hidden@s';
    nameLabel.textContent = labels.labelName || '';
    nameCell.appendChild(nameLabel);
    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.className = 'uk-input event-name';
    nameInput.placeholder = 'Name';
    nameInput.value = ev.name || '';
    nameCell.appendChild(nameInput);

    const startCell = document.createElement('td');
    startCell.setAttribute('role', 'cell');
    const startLabel = document.createElement('span');
    startLabel.className = 'cell-label uk-hidden@s';
    startLabel.textContent = labels.labelStart || '';
    startCell.appendChild(startLabel);
    const startWrapper = document.createElement('div');
    startWrapper.className = 'uk-inline';
    const startIcon = document.createElement('span');
    startIcon.className = 'uk-form-icon';
    startIcon.setAttribute('uk-icon', 'icon: calendar');
    const startInput = document.createElement('input');
    startInput.type = 'datetime-local';
    startInput.className = 'uk-input event-start';
    startInput.placeholder = 'TT.MM.JJJJ HH:MM';
    const now = new Date().toISOString().slice(0, 16);
    startInput.value = ev.start_date || now;
    startWrapper.appendChild(startIcon);
    startWrapper.appendChild(startInput);
    startCell.appendChild(startWrapper);

    const endCell = document.createElement('td');
    endCell.setAttribute('role', 'cell');
    const endLabel = document.createElement('span');
    endLabel.className = 'cell-label uk-hidden@s';
    endLabel.textContent = labels.labelEnd || '';
    endCell.appendChild(endLabel);
    const endWrapper = document.createElement('div');
    endWrapper.className = 'uk-inline';
    const endIcon = document.createElement('span');
    endIcon.className = 'uk-form-icon';
    endIcon.setAttribute('uk-icon', 'icon: calendar');
    const endInput = document.createElement('input');
    endInput.type = 'datetime-local';
    endInput.className = 'uk-input event-end';
    endInput.placeholder = 'TT.MM.JJJJ HH:MM';
    endInput.value = ev.end_date || now;
    endWrapper.appendChild(endIcon);
    endWrapper.appendChild(endInput);
    endCell.appendChild(endWrapper);

    const descCell = document.createElement('td');
    descCell.setAttribute('role', 'cell');
    const descLabel = document.createElement('span');
    descLabel.className = 'cell-label uk-hidden@s';
    descLabel.textContent = labels.labelDescription || '';
    descCell.appendChild(descLabel);
    const descInput = document.createElement('input');
    descInput.type = 'text';
    descInput.className = 'uk-input event-desc';
    descInput.placeholder = 'Beschreibung';
    descInput.value = ev.description || '';
    descCell.appendChild(descInput);

    const activateCell = document.createElement('td');
    activateCell.setAttribute('role', 'cell');
    const activateCellLabel = document.createElement('span');
    activateCellLabel.className = 'cell-label uk-hidden@s';
    activateCellLabel.textContent = labels.labelCurrent || '';
    activateCell.appendChild(activateCellLabel);
    const activateLabel = document.createElement('label');
    activateLabel.className = 'switch';
    activateLabel.setAttribute('uk-tooltip', `title: ${labels.tipSelectEvent || ''}; pos: top`);
    const activateInput = document.createElement('input');
    activateInput.type = 'radio';
    activateInput.name = 'currentEvent';
    activateInput.checked = ev.uid === activeEventUid;
    activateInput.setAttribute('aria-label', labels.tipSelectEvent || '');
    const activateSlider = document.createElement('span');
    activateSlider.className = 'slider';
    activateInput.addEventListener('change', () => {
      if (activateInput.checked) {
        setActiveEvent(row.dataset.uid, nameInput.value.trim());
        row.classList.add('active-event');
      } else {
        row.classList.remove('active-event');
      }
    });
    activateLabel.appendChild(activateInput);
    activateLabel.appendChild(activateSlider);
    activateCell.appendChild(activateLabel);

    const delCell = document.createElement('td');
    delCell.setAttribute('role', 'cell');
    const delLabel = document.createElement('span');
    delLabel.className = 'cell-label uk-hidden@s';
    delLabel.textContent = labels.labelActions || '';
    delCell.appendChild(delLabel);
    const del = document.createElement('button');
    del.className = 'uk-icon-button uk-button-danger';
    del.setAttribute('uk-icon', 'trash');
    del.setAttribute('aria-label', 'Löschen');
    del.addEventListener('click', () => {
      UIkit.modal
        .confirm('Veranstaltung wirklich löschen? Dabei werden auch alle angelegten Kataloge, Fragen und Teams entfernt.')
        .then(() => {
          row.remove();
          saveEvents();
        })
        .catch(() => {});
    });
    delCell.appendChild(del);

    row.appendChild(handleCell);
    row.appendChild(indexCell);
    row.appendChild(nameCell);
    row.appendChild(startCell);
    row.appendChild(endCell);
    row.appendChild(descCell);
    row.appendChild(activateCell);
    row.appendChild(delCell);
    return row;
  }

  function renderEvents(list) {
    if (!eventsListEl) return;
    eventsListEl.innerHTML = '';
    list.forEach(ev => eventsListEl.appendChild(createEventRow(ev)));
    updateEventRowNumbers();
  }

  function populateEventSelect(list) {
    if (!eventSelect) return;
    eventSelect.innerHTML = '';
    list.forEach(ev => {
      const opt = document.createElement('option');
      opt.value = ev.uid;
      opt.textContent = ev.name;
      if (ev.uid === activeEventUid) {
        opt.selected = true;
      }
      eventSelect.appendChild(opt);
    });
    updateEventSelectDisplay();
  }

  function updateActiveHeader(name, uid) {
    if (eventSelect) {
      const opt = Array.from(eventSelect.options).find(o => o.value === uid);
      if (opt) {
        eventSelect.value = opt.value;
      }
    }
    updateEventSelectDisplay();
    const top = document.getElementById('topbar-title');
    if (top) top.textContent = name || top.dataset.defaultTitle || '';
  }

  function updateEventSelectDisplay() {
    if (!eventSelectWrap || !eventSelect) return;
    const btnSpan = eventSelectWrap.querySelector('button > span:first-child');
    if (btnSpan) {
      const sel = eventSelect.options[eventSelect.selectedIndex];
      btnSpan.textContent = sel ? sel.textContent : '';
    }
    window.dispatchEvent(new Event('resize'));
  }

  function setActiveEvent(uid, name) {
    activeEventUid = uid;
    cfgInitial.event_uid = uid;
    updateActiveHeader(name, uid);
    apiFetch('/config.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event_uid: uid })
    }).then(() => {
      window.location.reload();
    }).catch(() => {});
  }

  if (eventsListEl && window.UIkit && UIkit.util) {
    UIkit.util.on(eventsListEl, 'moved', () => {
      saveEvents();
    });
  }

  if (eventsListEl || eventSelect) {
    apiFetch('/events.json', { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(data => {
        renderEvents(data);
        populateEventSelect(data);
      })
      .catch(() => {});
  }

  eventAddBtn?.addEventListener('click', e => {
    e.preventDefault();
    eventsListEl.appendChild(createEventRow());
    updateEventRowNumbers();
  });

  eventsListEl?.addEventListener('change', () => {
    saveEvents();
  });

  eventSelect?.addEventListener('change', () => {
    const uid = eventSelect.value;
    const name = eventSelect.options[eventSelect.selectedIndex]?.textContent || '';
    if (uid && uid !== activeEventUid) {
      setActiveEvent(uid, name);
    }
  });

  eventOpenBtn?.addEventListener('click', () => {
    const uid = eventSelect?.value;
    if (uid) {
      window.open(withBase('/?event=' + uid), '_blank');
    }
  });

  langSelect?.addEventListener('change', () => {
    const lang = langSelect.value;
    const url = new URL(window.location.href);
    url.searchParams.set('lang', lang);
    window.location.href = escape(url.toString());
  });

  // --------- Teams/Personen ---------
  const teamListEl = document.getElementById('teamsList');
  const teamCardsEl = document.getElementById('teamsCards');
  const teamAddBtn = document.getElementById('teamAddBtn');
  const teamRestrictTeams = document.getElementById('teamRestrict');
  const teamEditModal = UIkit.modal('#teamEditModal');
  const teamEditInput = document.getElementById('teamEditInput');
  const teamEditSave = document.getElementById('teamEditSave');
  const teamEditCancel = document.getElementById('teamEditCancel');
  const teamEditError = document.getElementById('teamEditError');
  const teamEditTitle = document.querySelector('#teamEditModal .uk-modal-title');
  const teamEditTitleBase = teamEditTitle?.textContent.trim();
  let currentTeamId = null;
  let currentTeamKey = null;
  const TEAMS_PER_PAGE = 50;
  const teamPaginationEl = document.createElement('ul');
  teamPaginationEl.id = 'teamsPagination';
  teamPaginationEl.className = 'uk-pagination uk-flex-center';
  teamAddBtn?.parentElement?.before(teamPaginationEl);

  const teamManager = new TableManager({
    tbody: teamListEl,
    mobileCards: { container: teamCardsEl },
    columns: [
      { key: 'name', className: 'team-name', editable: true },
      {
        className: 'uk-table-shrink',
        render: item => {
          const btn = document.createElement('button');
          btn.className = 'uk-icon-button qr-action';
          btn.setAttribute('uk-icon', 'file-text');
          btn.setAttribute('aria-label', window.transTeamPdf || 'PDF');
          btn.setAttribute('uk-tooltip', 'title: ' + (window.transTeamPdf || 'PDF') + '; pos: left');
          btn.addEventListener('click', () => openTeamPdf(item.name));
          return btn;
        },
        renderCard: item => {
          const btn = document.createElement('button');
          btn.className = 'uk-icon-button qr-action';
          btn.setAttribute('uk-icon', 'file-text');
          btn.setAttribute('aria-label', window.transTeamPdf || 'PDF');
          btn.addEventListener('click', () => openTeamPdf(item.name));
          return btn;
        }
      }
    ],
    sortable: true,
    onEdit: openTeamModal,
    onDelete: removeTeam,
    onReorder: () => reorderTeams(teamManager.getData())
  });
  teamManager.bindPagination(teamPaginationEl, TEAMS_PER_PAGE);

  function saveTeamList(list = teamManager.getData(), show = false) {
    const names = list.map(t => t.name);
    apiFetch('/teams.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(names)
    }).then(r => {
      if (!r.ok) throw new Error(r.statusText);
      if (show) notify('Liste gespeichert', 'success');
    }).catch(err => {
      if (show) {
        console.error(err);
        notify('Fehler beim Speichern', 'danger');
      }
    });
  }

  function openTeamModal(cell) {
    currentTeamId = cell?.dataset.id || null;
    currentTeamKey = cell?.dataset.key || null;
    const team = teamManager.getData().find(t => t.id === currentTeamId) || {};
    const name = currentTeamKey ? (team[currentTeamKey] || '') : '';
    teamEditInput.value = name;
    if (teamEditTitle) {
      teamEditTitle.textContent = name ? `${teamEditTitleBase}: ${name}` : teamEditTitleBase;
    }
    teamEditError.hidden = true;
    teamEditModal.show();
  }

  function reorderTeams(list) {
    saveTeamList(list);
  }

  function removeTeam(id) {
    const list = teamManager.getData();
    const idx = list.findIndex(t => t.id === id);
    if (idx !== -1) {
      list.splice(idx, 1);
      teamManager.render(list);
      saveTeamList(list);
    }
  }

  function openTeamPdf(teamName){
    window.open(withBase('/results.pdf?team=' + encodeURIComponent(teamName)), '_blank');
  }

  if(teamListEl){
    apiFetch('/teams.json', { headers: { 'Accept':'application/json' } })
      .then(r => r.json())
      .then(data => {
        const list = data.map(n => ({ id: crypto.randomUUID(), name: n }));
        teamManager.render(list);
      })
      .catch(()=>{});
    if (teamRestrictTeams) {
      teamRestrictTeams.checked = !!cfgInitial.QRRestrict;
    }
  }

  teamAddBtn?.addEventListener('click', e => {
    e.preventDefault();
    const id = crypto.randomUUID();
    const team = { id, name: '' };
    const list = teamManager.getData();
    list.push(team);
    if (teamManager.pagination) {
      teamManager.pagination.page = Math.max(1, Math.ceil(list.length / TEAMS_PER_PAGE));
    }
    teamManager.render(list);
    const cell = document.querySelector(`[data-id="${id}"][data-key="name"]`);
    if (cell) openTeamModal(cell);
  });

  teamEditSave?.addEventListener('click', () => {
    const val = teamEditInput.value.trim();
    if (!val) {
      teamEditError.textContent = 'Name darf nicht leer sein';
      teamEditError.hidden = false;
      return;
    }
    const list = teamManager.getData();
    const team = list.find(t => t.id === currentTeamId);
    if (team && currentTeamKey) team[currentTeamKey] = val;
    teamManager.render(list);
    saveTeamList(list);
    teamEditModal.hide();
  });

  teamEditCancel?.addEventListener('click', e => {
    e.preventDefault();
    teamEditModal.hide();
  });


  // --------- Benutzer ---------
  const usersListEl = document.getElementById('usersList');
  const userAddBtn = document.getElementById('userAddBtn');
  const userPassModal = UIkit.modal('#userPassModal');
  const userPassInput = document.getElementById('userPassInput');
  const userPassRepeat = document.getElementById('userPassRepeat');
  const userPassSave = document.getElementById('userPassSave');
  let currentUserRow = null;

  function collectUsers() {
    return Array.from(usersListEl.querySelectorAll('.user-row')).map(row => ({
      id: row.dataset.id ? parseInt(row.dataset.id, 10) : undefined,
      username: row.querySelector('.user-name').value.trim(),
      password: row.dataset.pass || '',
      role: row.querySelector('.user-role').value,
      active: row.querySelector('.user-active')?.checked
    })).filter(u => u.username);
  }

  function saveUsers() {
    const list = collectUsers();
    apiFetch('/users.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(list)
    })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify('Liste gespeichert', 'success');
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Speichern', 'danger');
      });
  }

  function createUserRow(u = {}) {
    const row = document.createElement('tr');
    row.className = 'user-row';
    if (u.id) row.dataset.id = u.id;

    const handleCell = document.createElement('td');
    const handleSpan = document.createElement('span');
    handleSpan.className = 'uk-sortable-handle uk-icon';
    handleSpan.setAttribute('uk-icon', 'icon: table');
    handleCell.appendChild(handleSpan);

    const nameCell = document.createElement('td');
    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.className = 'uk-input user-name';
    nameInput.placeholder = 'Benutzername';
    nameInput.value = u.username || '';
    nameCell.appendChild(nameInput);

    const activeCell = document.createElement('td');
    const activeCheckbox = document.createElement('input');
    activeCheckbox.type = 'checkbox';
    activeCheckbox.className = 'user-active';
    activeCheckbox.checked = u.active !== false;
    activeCell.appendChild(activeCheckbox);

    const passCell = document.createElement('td');
    const passBtn = document.createElement('button');
    passBtn.className = 'uk-button uk-button-default';
    passBtn.setAttribute('uk-icon', 'icon: key');
    passBtn.setAttribute('aria-label', 'Passwort setzen');
    passBtn.addEventListener('click', () => {
      currentUserRow = row;
      if (userPassInput) userPassInput.value = '';
      if (userPassRepeat) userPassRepeat.value = '';
      userPassModal.show();
    });
    passCell.appendChild(passBtn);
    row.dataset.pass = '';

    const roleCell = document.createElement('td');
    const roleSelect = document.createElement('select');
    roleSelect.className = 'uk-select user-role';
    const roles = window.roles || [];
    roles.forEach(r => {
      const opt = document.createElement('option');
      opt.value = r;
      opt.textContent = r;
      if ((u.role || roles[0]) === r) opt.selected = true;
      roleSelect.appendChild(opt);
    });
    roleCell.appendChild(roleSelect);

    const delCell = document.createElement('td');
    const delBtn = document.createElement('button');
    delBtn.className = 'uk-icon-button uk-button-danger';
    delBtn.setAttribute('uk-icon', 'trash');
    delBtn.setAttribute('aria-label', 'Löschen');
    delBtn.addEventListener('click', () => {
      row.remove();
      saveUsers();
    });
    delCell.appendChild(delBtn);

    row.appendChild(handleCell);
    row.appendChild(nameCell);
    row.appendChild(roleCell);
    row.appendChild(activeCell);
    row.appendChild(passCell);
    row.appendChild(delCell);
    return row;
  }

  function renderUsers(list) {
    if (!usersListEl) return;
    usersListEl.innerHTML = '';
    list.forEach(u => usersListEl.appendChild(createUserRow(u)));
  }

  if (usersListEl && window.UIkit && UIkit.util) {
    UIkit.util.on(usersListEl, 'moved', saveUsers);
  }

  if (usersListEl) {
    apiFetch('/users.json', { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(data => renderUsers(data))
      .catch(() => {});
  }

  userAddBtn?.addEventListener('click', e => {
    e.preventDefault();
    usersListEl.appendChild(createUserRow());
    saveUsers();
  });

  userPassSave?.addEventListener('click', () => {
    if (!userPassInput || !userPassRepeat || !currentUserRow) return;
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
    currentUserRow.dataset.pass = p1;
    userPassModal.hide();
    userPassInput.value = '';
    userPassRepeat.value = '';
    saveUsers();
  });

  usersListEl?.addEventListener('change', () => {
    saveUsers();
  });

  const importJsonBtn = document.getElementById('importJsonBtn');
  const exportJsonBtn = document.getElementById('exportJsonBtn');
  const saveDemoBtn = document.getElementById('saveDemoBtn');
  const backupTableBody = document.getElementById('backupTableBody');
  const tenantTableBody = document.getElementById('tenantTableBody');
  const tenantCards = document.getElementById('tenantCards');
  const tenantSyncBtn = document.getElementById('tenantSyncBtn');
  const tenantExportBtn = document.getElementById('tenantExportBtn');
  const tenantReportBtn = document.getElementById('tenantReportBtn');
  const tenantStatusFilter = document.getElementById('tenantStatusFilter');
  const tenantSearchInput = document.getElementById('tenantSearchInput');
  const tenantColumnBtn = document.getElementById('tenantColumnBtn');
  const tenantTable = tenantTableBody?.closest('table');
  const tenantTableHeadings = tenantTable?.querySelectorAll('thead th') || [];
  const tenantColumnDefs = [
    { key: 'plan', label: 'Abo', thIndex: 1 },
    { key: 'billing', label: 'Rechnungsinfo', thIndex: 2 },
    { key: 'created', label: 'Erstellt', thIndex: 3 }
  ];
  const tenantColumnDefaults = tenantColumnDefs.map(c => c.key);
  let tenantColumns = [...tenantColumnDefaults];
  try {
    const stored = JSON.parse(localStorage.getItem('tenantColumns'));
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
  tenantColumnBtn?.addEventListener('click', () => {
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
        try { localStorage.setItem('tenantColumns', JSON.stringify(tenantColumns)); } catch (_) {}
        updateTenantColumnVisibility();
        loadTenants(tenantStatusFilter?.value, tenantSearchInput?.value);
        UIkit.modal(modal).hide();
      });
    } else {
      modal.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.checked = tenantColumns.includes(cb.dataset.col);
      });
    }
    UIkit.modal(modal).show();
  });
  updateTenantColumnVisibility();

  const syncTenants = () => {
    tenantSyncBtn?.click();
  };

  tenantStatusFilter?.addEventListener('change', () => {
    loadTenants(tenantStatusFilter.value, tenantSearchInput?.value);
  });

  tenantSearchInput?.addEventListener('input', () => {
    loadTenants(tenantStatusFilter?.value, tenantSearchInput.value);
  });

  function loadBackups() {
    if (!backupTableBody) return;
    apiFetch('/backups')
      .then(r => r.text())
      .then(html => {
        backupTableBody.innerHTML = html;
      })
      .catch(() => {
        backupTableBody.innerHTML = '<tr><td colspan="2">Fehler</td></tr>';
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
        if (!r.ok) throw new Error(r.statusText);
        notify('Export abgeschlossen', 'success');
        loadBackups();
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Export', 'danger');
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
    apiFetch('/tenants/sync', { method: 'POST' })
      .then(r => r.json())
      .then(() => {
        notify('Mandanten eingelesen', 'success');
        loadTenants(tenantStatusFilter?.value, tenantSearchInput?.value);
      })
      .catch(() => notify('Fehler beim Synchronisieren', 'danger'))
      .finally(() => {
        tenantSyncBtn.disabled = false;
        tenantSyncBtn.innerHTML = original;
      });
  });

  function loadTenants(status = tenantStatusFilter?.value || '', query = tenantSearchInput?.value || '') {
    if (!tenantTableBody || window.domainType !== 'main') return;
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (query) params.set('query', query);
    const url = '/tenants' + (params.toString() ? ('?' + params.toString()) : '');
    apiFetch(url, { headers: { 'Accept': 'text/html' } })
      .then((r) => r.text())
      .then((html) => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newBody = doc.getElementById('tenantTableBody');
        const newCards = doc.getElementById('tenantCards');
        if (newBody) {
          tenantTableBody.innerHTML = newBody.innerHTML;
        }
        if (tenantCards && newCards) {
          tenantCards.innerHTML = newCards.innerHTML;
        }
        updateTenantColumnVisibility();
      })
      .catch(() => {});
  }

  // Zähler für eindeutige Namen von Eingabefeldern
  let cardIndex = 0;

  // --------- Hilfe-Seitenleiste ---------
  const helpBtn = document.getElementById('helpBtn');
  const helpSidebar = document.getElementById('helpSidebar');
  const helpContent = document.getElementById('helpContent');
  const qrDesignModal = document.getElementById('qrDesignModal');
  const qrLabelInput = document.getElementById('qrLabelInput');
  const qrPunchoutInput = document.getElementById('qrPunchoutInput');
  const qrRoundModeSelect = document.getElementById('qrRoundModeSelect');
  const qrColorInput = document.getElementById('qrColorInput');
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
    if (qrDesignModal) UIkit.modal(qrDesignModal).show();
  }

  [qrLabelInput, qrPunchoutInput, qrRoundModeSelect, qrColorInput, qrRoundedInput, qrLogoWidthInput].forEach(el => {
    el?.addEventListener('input', updateQrPreview);
    el?.addEventListener('change', updateQrPreview);
  });

  qrLogoFile?.addEventListener('change', () => {
    const file = qrLogoFile.files && qrLogoFile.files[0];
    if (!file) return;
    const ext = file.type === 'image/webp' ? 'webp' : 'png';
    const fd = new FormData();
    fd.append('file', file);
    apiFetch('/qrlogo.' + ext, { method: 'POST', body: fd })
      .then(() => apiFetch('/config.json', { headers: { 'Accept': 'application/json' } }))
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
        const params = new URLSearchParams();
        params.set('t', target);
        if (qrLogoPath) {
          params.set('logo_path', qrLogoPath);
        } else {
          const label = img.nextElementSibling?.textContent || '';
          const lns = label.split('\n');
          if (lns[0]) params.set('text1', lns[0]);
          if (lns[1]) params.set('text2', lns[1]);
        }
        if (colorVal) params.set('fg', colorVal.replace('#', ''));
        if (logoWidthVal) params.set('logo_width', logoWidthVal);
        params.set('round_mode', roundMode);
        params.set('rounded', rounded ? '1' : '0');
        params.set('logo_punchout', punchout);
        img.src = withBase(endpoint + '?' + params.toString());
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
      apiFetch('/config.json', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).catch(() => {});
      Object.assign(cfgInitial, data);
    } else if (currentQrImg) {
      currentQrImg.src = qrPreview.src;
      const data = { qrRounded: rounded };
      data[field] = colorVal;
      if (logoWidthVal) data.qrLogoWidth = parseInt(logoWidthVal, 10);
      apiFetch('/config.json', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).catch(() => {});
      Object.assign(cfgInitial, data);
    }
    if (qrDesignModal) UIkit.modal(qrDesignModal).hide();
  });

  function loadSummary() {
    const nameEl = document.getElementById('summaryEventName');
    const descEl = document.getElementById('summaryEventDesc');
    const qrImg = document.getElementById('summaryEventQr');
    const qrLabel = document.getElementById('summaryEventLabel');
    const catalogsEl = document.getElementById('summaryCatalogs');
    const teamsEl = document.getElementById('summaryTeams');
    if (!nameEl || !catalogsEl || !teamsEl) return;
    const opts = { headers: { 'Accept': 'application/json' } };
    Promise.all([
      apiFetch('/config.json', opts).then(r => r.json()).catch(() => ({})),
      apiFetch('/events.json', opts).then(r => r.json()).catch(() => []),
      apiFetch('/kataloge/catalogs.json', opts).then(r => r.json()).catch(() => []),
      apiFetch('/teams.json', opts).then(r => r.json()).catch(() => [])
    ]).then(([cfg, events, catalogs, teams]) => {
      Object.assign(cfgInitial, cfg);
      activeEventUid = cfgInitial.event_uid || activeEventUid;
      const ev = events.find(e => e.uid === activeEventUid) || events[0] || {};
      nameEl.textContent = ev.name || '';
      if (descEl) descEl.textContent = ev.description || '';
      const applyDesign = (params, colorKey) => {
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
      if (qrImg) {
        const link = window.baseUrl ? window.baseUrl : withBase('/?event=' + encodeURIComponent(ev.uid || ''));
        qrImg.dataset.endpoint = '/qr/event';
        qrImg.dataset.target = link;
        const params = new URLSearchParams();
        params.set('t', link);
        applyDesign(params, 'qrColorEvent');
        qrImg.src = withBase('/qr/event?' + params.toString());
      }
      if (qrLabel) qrLabel.textContent = ev.name || '';
      catalogsEl.innerHTML = '';
      catalogs.forEach(c => {
        const wrapper = document.createElement('div');
        wrapper.className = 'uk-width-1-1 uk-width-1-2@s';
        const card = document.createElement('div');
        card.className = 'export-card uk-card qr-card uk-card-body';
        let href = withBase('/?katalog=' + encodeURIComponent(c.slug));
        if (ev.uid) {
          href = withBase('/?event=' + encodeURIComponent(ev.uid) + '&katalog=' + encodeURIComponent(c.slug));
        }
        const linkEl = document.createElement('a');
        linkEl.href = href;
        linkEl.target = '_blank';
        linkEl.textContent = c.name || '';
        const h4 = document.createElement('h4');
        h4.className = 'uk-card-title';
        h4.appendChild(linkEl);
        const p = document.createElement('p');
        p.textContent = c.description || '';
        const img = document.createElement('img');
        const qrLink = (window.baseUrl ? window.baseUrl + href : href);
        img.dataset.endpoint = '/qr/catalog';
        img.dataset.target = qrLink;
        const cParams = new URLSearchParams();
        cParams.set('t', qrLink);
        applyDesign(cParams, 'qrColorCatalog');
        img.src = withBase('/qr/catalog?' + cParams.toString());
        img.alt = 'QR';
        img.width = 96;
        img.height = 96;
        const label = document.createElement('div');
        label.className = 'qr-label';
        label.textContent = c.name || '';
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
        card.appendChild(label);
        card.appendChild(designBtn);
        wrapper.appendChild(card);
        catalogsEl.appendChild(wrapper);
      });
      teamsEl.innerHTML = '';
      teams.forEach(t => {
        const wrapper = document.createElement('div');
        wrapper.className = 'uk-width-1-1 uk-width-1-2@s';
        const card = document.createElement('div');
        card.className = 'export-card uk-card qr-card uk-card-body uk-position-relative';
        const btn = document.createElement('button');
        btn.className = 'qr-print-btn uk-icon-button uk-position-top-right';
        btn.setAttribute('data-team', t);
        btn.setAttribute('uk-icon', 'icon: print');
        btn.setAttribute('aria-label', 'QR-Code drucken');
        const h4 = document.createElement('h4');
        h4.className = 'uk-card-title';
        h4.textContent = t;
        const img = document.createElement('img');
        img.dataset.endpoint = '/qr/team';
        img.dataset.target = t;
        const tParams = new URLSearchParams();
        tParams.set('t', t);
        applyDesign(tParams, 'qrColorTeam');
        img.src = withBase('/qr/team?' + tParams.toString());
        img.alt = 'QR';
        img.width = 96;
        img.height = 96;
        const label = document.createElement('div');
        label.className = 'qr-label';
        label.textContent = t;
        const designBtn = document.createElement('button');
        designBtn.className = 'uk-icon-button uk-position-top-left';
        designBtn.setAttribute('uk-icon', 'icon: paint-bucket');
        designBtn.type = 'button';
        designBtn.addEventListener('click', () => {
          openQrDesignModal(img, '/qr/team', t, t);
        });
        card.appendChild(btn);
        card.appendChild(h4);
        card.appendChild(img);
        card.appendChild(label);
        card.appendChild(designBtn);
        wrapper.appendChild(card);
        teamsEl.appendChild(wrapper);
      });
    });
  }

  function activeHelpText() {
    if (!adminTabs) return '';
    const active = adminTabs.querySelector('li.uk-active');
    return active ? active.getAttribute('data-help') || '' : '';
  }

  helpBtn?.addEventListener('click', () => {
    if (!helpSidebar || !helpContent) return;
    let text = activeHelpText();
    if (!text && window.location.pathname.endsWith('/admin/event/settings')) {
      text = window.transEventSettingsHelp || '';
    }
    helpContent.innerHTML = text;
    UIkit.offcanvas(helpSidebar).show();
  });

  adminMenuToggle?.addEventListener('click', e => {
    e.preventDefault();
    if (adminNav) UIkit.offcanvas(adminNav).show();
  });

  if (adminMenu && adminTabs) {
    const tabControl = UIkit.tab(adminTabs);
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
    if (initIdx >= 0) {
      tabControl.show(initIdx);
      if (initRoute === 'summary') {
        loadSummary();
      }
      if (initRoute === 'tenants') {
        syncTenants();
      }
    }
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
        syncTenants();
      }
    });
    if (summaryIdx >= 0) {
      adminTabs.children[summaryIdx]?.addEventListener('click', () => {
        loadSummary();
      });
    }
    if (tenantIdx >= 0) {
      adminTabs.children[tenantIdx]?.addEventListener('click', () => {
        syncTenants();
      });
    }
    adminMenu.querySelectorAll('[data-tab]').forEach(item => {
      item.addEventListener('click', e => {
        e.preventDefault();
        const idx = parseInt(item.getAttribute('data-tab'), 10);
        if (!isNaN(idx)) {
          tabControl.show(idx);
          const route = adminRoutes[idx];
          if (route && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', basePath + '/admin/' + route);
          }
          if (adminNav) UIkit.offcanvas(adminNav).hide();
          if (idx === summaryIdx) {
            loadSummary();
          }
          if (idx === tenantIdx) {
            syncTenants();
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

  // Page editors are handled in trumbowyg-pages.js

  loadBackups();
  const path = window.location.pathname.replace(basePath + '/admin/', '');
  const currentRoute = path === '' ? 'dashboard' : path.replace(/^\/?/, '');
  if (currentRoute === 'tenants') {
    syncTenants();
  }
});
