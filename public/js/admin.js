/* global UIkit */

const basePath = window.basePath || '';
const withBase = path => basePath + path;
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
    'X-CSRF-Token': token,
    ...(options.headers || {})
  };
  return fetch(withBase(path), {
    credentials: 'same-origin',
    ...options,
    headers
  }).then(res => {
    if (res.status === 402) {
      showUpgradeModal();
      throw new Error('upgrade-required');
    }
    return res;
  });
};
window.notify = (msg, status = 'primary') => {
  if (typeof UIkit !== 'undefined' && UIkit.notification) {
    UIkit.notification({ message: msg, status, pos: 'top-center', timeout: 2000 });
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
  const checkoutContainer = document.getElementById('stripe-checkout');
  const planButtons = document.querySelectorAll('.plan-select');
  const emailInput = document.getElementById('subscription-email');
  planButtons.forEach(btn => {
    btn.addEventListener('click', async () => {
      const plan = btn.dataset.plan;
      if (!plan) return;
      const payload = { plan, embedded: true };
      if (emailInput) {
        payload.email = emailInput.value;
      }
      try {
        const res = await apiFetch('/admin/subscription/checkout', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        if (!res.ok) {
          notify('Fehler beim Starten der Zahlung', 'danger');
          return;
        }
        const data = await res.json();
        if ([data.client_secret, data.publishable_key, window.Stripe, checkoutContainer].every(Boolean)) {
          const stripe = Stripe(data.publishable_key);
          const checkout = await stripe.initEmbeddedCheckout({ clientSecret: data.client_secret });
          checkout.mount('#stripe-checkout');
          return;
        }
        if (data.url) {
          window.location.href = data.url;
        }
      } catch (e) {
        console.error(e);
        notify('Fehler beim Starten der Zahlung', 'danger');
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
    const set = new Set(catalogs.map(c => c.slug || c.sort_order));
    document
      .querySelectorAll('.catalog-row .cat-id')
      .forEach(el => set.add(el.value.trim()));
    return set;
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
    if (!inviteIcon || !inviteLabel) return;
    if (inviteText.trim().length > 0) {
      inviteIcon.setAttribute('uk-icon', 'icon: check');
      inviteLabel.textContent = 'Einladungstext bearbeiten';
    } else {
      inviteIcon.setAttribute('uk-icon', 'icon: pencil');
      inviteLabel.textContent = 'Einladungstext eingeben';
    }
    UIkit.icon(inviteIcon, { icon: inviteIcon.getAttribute('uk-icon').split(': ')[1] });
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
    qrRemember: document.getElementById('cfgQRRemember'),
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
  const inviteIcon = document.getElementById('inviteTextIcon');
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
  let currentCommentInput = null;
  let cfgSaveTimer = null;

  function collectConfigData() {
    return Object.assign({}, cfgInitial, {
      logoPath: (function () {
        if (cfgFields.logoPreview && cfgFields.logoPreview.src) {
          const m = cfgFields.logoPreview.src.match(/\/logo(?:-[\w-]+)?\.(png|webp)/);
          if (m) return m[0];
        }
        return cfgInitial.logoPath;
      })(),
      pageTitle: cfgFields.pageTitle.value.trim(),
      backgroundColor: cfgFields.backgroundColor.value.trim(),
      buttonColor: cfgFields.buttonColor.value.trim(),
      CheckAnswerButton: cfgFields.checkAnswerButton.checked ? 'yes' : 'no',
      QRUser: cfgFields.qrUser.checked,
      QRRemember: cfgFields.qrRemember ? cfgFields.qrRemember.checked : cfgInitial.QRRemember,
      QRRestrict: cfgFields.teamRestrict ? cfgFields.teamRestrict.checked : cfgInitial.QRRestrict,
      randomNames: cfgFields.randomNames ? cfgFields.randomNames.checked : cfgInitial.randomNames,
      competitionMode: cfgFields.competitionMode ? cfgFields.competitionMode.checked : cfgInitial.competitionMode,
      teamResults: cfgFields.teamResults ? cfgFields.teamResults.checked : cfgInitial.teamResults,
      photoUpload: cfgFields.photoUpload ? cfgFields.photoUpload.checked : cfgInitial.photoUpload,
      puzzleWordEnabled: cfgFields.puzzleEnabled ? cfgFields.puzzleEnabled.checked : cfgInitial.puzzleWordEnabled,
      puzzleWord: cfgFields.puzzleWord ? cfgFields.puzzleWord.value.trim() : cfgInitial.puzzleWord,
      puzzleFeedback: puzzleFeedback,
      inviteText: inviteText
    });
  }

  function saveConfig(show = true) {
    const data = collectConfigData();
    apiFetch('/config.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    })
      .then(r => {
        if (r.ok) {
          if (show) notify('Konfiguration gespeichert', 'success');
          Object.assign(cfgInitial, data);
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
  }

  function scheduleConfigSave() {
    clearTimeout(cfgSaveTimer);
    cfgSaveTimer = setTimeout(saveConfig, 1000);
  }

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
        cfgInitial.logoPath = '/logo-' + activeEventUid + '.' + ext;
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
    if (cfgFields.qrRemember) {
      cfgFields.qrRemember.checked = !!data.QRRemember;
    }
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
    saveConfig(false);
  });

  inviteSaveBtn?.addEventListener('click', () => {
    if (!inviteTextarea) return;
    inviteText = inviteTextarea.value;
    updateInviteTextUI();
    inviteModal.hide();
    cfgInitial.inviteText = inviteText;
    notify('Einladungstext gespeichert', 'success');
    saveConfig(false);
  });

  commentSaveBtn?.addEventListener('click', () => {
    if(!currentCommentInput || !commentTextarea) return;
    currentCommentInput.value = commentTextarea.value;
    const btn = currentCommentInput.previousSibling;
    if(btn && btn.textContent !== undefined){
      btn.textContent = commentTextarea.value.trim() ? 'Kommentar bearbeiten' : 'Kommentar eingeben';
    }
    commentModal.hide();
    currentCommentInput = null;
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
    cfgFields.checkAnswerButton,
    cfgFields.qrUser,
    cfgFields.qrRemember,
    cfgFields.randomNames,
    cfgFields.teamRestrict,
    cfgFields.competitionMode,
    cfgFields.teamResults,
    cfgFields.photoUpload,
    cfgFields.puzzleEnabled,
    cfgFields.puzzleWord
  ].forEach(field => {
    const ev = field?.type === 'checkbox' ? 'change' : 'input';
    field?.addEventListener(ev, scheduleConfigSave);
  });


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

  if (catalogList && window.UIkit && UIkit.util) {
    UIkit.util.on(catalogList, 'moved', saveCatalogOrder);
  }

  function loadCatalog(identifier) {
    const cat = catalogs.find(c => c.uid === identifier || (c.slug || c.sort_order) === identifier);
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
      catalogs = list;
      catSelect.innerHTML = '';
      catalogs.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.uid || c.slug || c.sort_order;
        opt.textContent = c.name || c.sort_order || c.slug;
        catSelect.appendChild(opt);
      });
      renderCatalogs(catalogs);
      const params = new URLSearchParams(window.location.search);
      const slug = params.get('katalog');
      const selected = catalogs.find(c => (c.slug || c.sort_order) === slug) || catalogs[0];
      if (selected) {
        catSelect.value = selected.uid || selected.slug || selected.sort_order;
        loadCatalog(selected.uid || selected.slug || selected.sort_order);
      }
    });

  catSelect.addEventListener('change', () => loadCatalog(catSelect.value));

  function deleteCatalog(cat, row) {
    if (row.dataset.new === 'true' || !cat.file) {
      row.remove();
      return;
    }
    if (!confirm('Katalog wirklich löschen?')) return;
    apiFetch('/kataloge/' + cat.file, { method: 'DELETE' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        catalogs = catalogs.filter(c => c.uid !== cat.uid);
        const opt = catSelect.querySelector('option[value="' + cat.uid + '"]');
        opt?.remove();
        row.remove();
        if (catalogs[0]) {
          if (catSelect.value === cat.uid) {
            catSelect.value = catalogs[0].uid || catalogs[0].slug || catalogs[0].sort_order;
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

  function createCatalogRow(cat) {
    const row = document.createElement('tr');
    row.className = 'catalog-row';
    if (cat.new) row.dataset.new = 'true';
    row.dataset.sortOrder = cat.sort_order !== undefined ? String(cat.sort_order) : '';
    row.dataset.slug = cat.slug || '';
    row.dataset.file = cat.file || '';
    row.dataset.initialFile = cat.file || '';
    row.dataset.uid = cat.uid || crypto.randomUUID();

    const rowId = 'cat-' + catalogRowIndex++;

    const idCell = document.createElement('td');
    const handleCell = document.createElement('td');
    const handleSpan = document.createElement('span');
    handleSpan.className = 'uk-sortable-handle uk-icon';
    handleSpan.setAttribute('uk-icon', 'icon: table');
    handleCell.appendChild(handleSpan);

    const idInput = document.createElement('input');
    idInput.type = 'text';
    idInput.className = 'uk-input cat-id';
    idInput.placeholder = 'Slug';
    idInput.id = rowId + '-id';
    idInput.value = cat.slug || '';
    idCell.appendChild(idInput);

    const nameCell = document.createElement('td');
    const name = document.createElement('input');
    name.type = 'text';
    name.className = 'uk-input cat-name';
    name.placeholder = 'Name';
    name.id = rowId + '-name';
    name.value = cat.name || '';
    name.addEventListener('input', () => {
      if (row.dataset.new === 'true' && idInput.value.trim() === '') {
        idInput.value = uniqueId(name.value);
        update();
      }
    });
    nameCell.appendChild(name);

    const descCell = document.createElement('td');
    const desc = document.createElement('input');
    desc.type = 'text';
    desc.className = 'uk-input cat-desc';
    desc.placeholder = 'Beschreibung';
    desc.id = rowId + '-desc';
    desc.value = cat.description || '';
    descCell.appendChild(desc);

    const letterCell = document.createElement('td');
    const letter = document.createElement('input');
    letter.type = 'text';
    letter.className = 'uk-input cat-letter';
    letter.placeholder = 'Buchstabe';
    letter.id = rowId + '-letter';
    letter.value = cat.raetsel_buchstabe || '';
    letter.maxLength = 1;
    letterCell.appendChild(letter);

    const commentCell = document.createElement('td');
    const commentBtn = document.createElement('button');
    commentBtn.className = 'uk-button uk-button-default';
    const commentField = document.createElement('input');
    commentField.type = 'hidden';
    commentField.className = 'cat-comment';
    commentField.value = cat.comment || '';
    function updateCommentBtn(){
      commentBtn.textContent = commentField.value.trim() ? 'Kommentar bearbeiten' : 'Kommentar eingeben';
    }
    updateCommentBtn();
    commentBtn.addEventListener('click', () => {
      currentCommentInput = commentField;
      if(commentTextarea) commentTextarea.value = commentField.value;
      commentModal.show();
    });
    commentCell.appendChild(commentBtn);
    commentCell.appendChild(commentField);

    const delCell = document.createElement('td');
    const del = document.createElement('button');
    del.className = 'uk-icon-button uk-button-danger';
    del.setAttribute('uk-icon', 'trash');
    del.setAttribute('aria-label', 'Löschen');
    del.addEventListener('click', () => deleteCatalog(cat, row));
    delCell.appendChild(del);

    function update() {
      const slug = idInput.value.trim();
      row.dataset.slug = slug;
      row.dataset.file = slug ? slug + '.json' : '';
    }
    idInput.addEventListener('input', update);
    update();

    row.appendChild(handleCell);
    row.appendChild(idCell);
    row.appendChild(nameCell);
    row.appendChild(descCell);
    row.appendChild(letterCell);
    row.appendChild(commentCell);
    row.appendChild(delCell);

    return row;
  }

  function renderCatalogs(list) {
    if (!catalogList) return;
    catalogList.innerHTML = '';
    list
      .slice()
      .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
      .forEach(cat => catalogList.appendChild(createCatalogRow(cat)));
  }

  function collectCatalogs() {
    return Array.from(catalogList.querySelectorAll('.catalog-row'))
      .map((row, idx) => {
        const slug = row.querySelector('.cat-id').value.trim();
        const file = slug ? slug + '.json' : '';
        row.dataset.sortOrder = String(idx + 1);
        return {
          uid: row.dataset.uid,
          sort_order: idx + 1,
          slug,
          file,
          name: row.querySelector('.cat-name').value.trim(),
          description: row.querySelector('.cat-desc').value.trim(),
          raetsel_buchstabe: row.querySelector('.cat-letter').value.trim(),
          comment: row.querySelector('.cat-comment').value.trim()
        };
      })
      .filter(c => c.slug);
  }

  function saveCatalogOrder() {
    const data = collectCatalogs();
    apiFetch('/kataloge/catalogs.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }).catch(() => {});
  }

  // Rendert alle Fragen im Editor neu
  function renderAll(data) {
    container.innerHTML = '';
    data.forEach((q, i) => container.appendChild(createCard(q, i)));
  }

  // Erstellt ein Bearbeitungsformular für eine Frage
  function createCard(q, index = -1) {
    const card = document.createElement('div');
    card.className = 'uk-card uk-card-default uk-card-body uk-margin question-card';
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
    preview.className = 'uk-card uk-card-muted uk-card-body question-preview';

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
        const ul = document.createElement('ul');
        Array.from(fields.querySelectorAll('.card-row')).forEach(r => {
          const text = r.querySelector('.card-text').value;
          const check = r.querySelector('.card-correct').checked;
          const li = document.createElement('li');
          li.textContent = insertSoftHyphens(text) + (check ? ' ✓' : '');
          ul.appendChild(li);
        });
        preview.appendChild(ul);
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
    catalogList.appendChild(createCatalogRow({ id: '', slug: '', file: '', name: '', description: '', raetsel_buchstabe: '', new: true }));
  });

  catalogsSaveBtn?.addEventListener('click', async e => {
    e.preventDefault();
    const rows = Array.from(catalogList.querySelectorAll('.catalog-row'));
    for (const row of rows) {
      const currentId = row.querySelector('.cat-id').value.trim();
      const newFile = currentId ? currentId + '.json' : '';
      if (row.dataset.new === 'true') {
        let id = currentId;
        if (!id) {
          const nameEl = row.querySelector('.cat-name');
          if (nameEl) {
            id = uniqueId(nameEl.value);
            row.querySelector('.cat-id').value = id;
          }
        }
        if (!id) continue;
        try {
          await apiFetch('/kataloge/' + id + '.json', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: '[]'
          });
          row.dataset.new = '';
          row.dataset.initialFile = id + '.json';
        } catch (err) {
          console.error(err);
          notify('Fehler beim Erstellen', 'danger');
        }
      } else if (currentId && row.dataset.initialFile && row.dataset.initialFile !== newFile) {
        try {
          const res = await apiFetch('/kataloge/' + row.dataset.initialFile, { headers: { 'Accept': 'application/json' } });
          const content = await res.text();
          await apiFetch('/kataloge/' + newFile, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: content });
          await apiFetch('/kataloge/' + row.dataset.initialFile, { method: 'DELETE' });
          row.dataset.initialFile = newFile;
        } catch (err) {
          console.error(err);
          notify('Fehler beim Umbenennen', 'danger');
        }
      }
    }

    const data = collectCatalogs();
    apiFetch('/kataloge/catalogs.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        catalogs = data;
        catSelect.innerHTML = '';
        catalogs.forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.uid || c.slug || c.sort_order;
          opt.textContent = c.name || c.sort_order || c.slug;
          catSelect.appendChild(opt);
        });
        if (!catalogFile && catalogs.length > 0) {
          catSelect.value = catalogs[0].uid || catalogs[0].slug || catalogs[0].sort_order;
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
      const cell = row.querySelector('.row-num');
      if (cell) cell.textContent = idx + 1;
    });
  }

  function createEventRow(ev = {}) {
    const row = document.createElement('tr');
    row.className = 'event-row';
    row.dataset.uid = ev.uid || crypto.randomUUID();
    row.dataset.published = ev.published ? 'true' : 'false';

    if (ev.uid === activeEventUid) {
      row.classList.add('active-event');
    }

    const handleCell = document.createElement('td');
    const handleSpan = document.createElement('span');
    handleSpan.className = 'uk-sortable-handle uk-icon';
    handleSpan.setAttribute('uk-icon', 'icon: table');
    handleCell.appendChild(handleSpan);

    const indexCell = document.createElement('td');
    indexCell.className = 'row-num';
    indexCell.textContent = '';

    const nameCell = document.createElement('td');
    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.className = 'uk-input event-name';
    nameInput.placeholder = 'Name';
    nameInput.value = ev.name || '';
    nameCell.appendChild(nameInput);

    const startCell = document.createElement('td');
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
    const descInput = document.createElement('input');
    descInput.type = 'text';
    descInput.className = 'uk-input event-desc';
    descInput.placeholder = 'Beschreibung';
    descInput.value = ev.description || '';
    descCell.appendChild(descInput);

    const activateCell = document.createElement('td');
    const activateLabel = document.createElement('label');
    activateLabel.className = 'switch';
    activateLabel.setAttribute('uk-tooltip', 'title: Aktivieren; pos: top');
    const activateInput = document.createElement('input');
    activateInput.type = 'checkbox';
    activateInput.checked = ev.uid === activeEventUid;
    activateInput.setAttribute('aria-label', 'Aktivieren');
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
    window.location.href = url.toString();
  });

  // --------- Teams/Personen ---------
  const teamListEl = document.getElementById('teamsList');
  const teamAddBtn = document.getElementById('teamAddBtn');
  const teamSaveBtn = document.getElementById('teamsSaveBtn');
  const teamRestrictTeams = document.getElementById('teamRestrict');

  function collectTeams() {
    return Array.from(teamListEl.querySelectorAll('.team-row input.team-name'))
      .map(i => i.value.trim())
      .filter(Boolean);
  }

  function saveTeamOrder() {
    const names = collectTeams();
    apiFetch('/teams.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(names)
    }).catch(() => {});
  }


  function createTeamRow(name = ''){
    const row = document.createElement('tr');
    row.className = 'team-row';

    const handleCell = document.createElement('td');
    const handleSpan = document.createElement('span');
    handleSpan.className = 'uk-sortable-handle uk-icon';
    handleSpan.setAttribute('uk-icon', 'icon: table');
    handleCell.appendChild(handleSpan);

    const nameCell = document.createElement('td');
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'uk-input team-name';
    input.value = name;
    input.setAttribute('aria-label', 'Name');
    nameCell.appendChild(input);

    const delCell = document.createElement('td');
    const del = document.createElement('button');
    del.className = 'uk-icon-button uk-button-danger';
    del.setAttribute('uk-icon', 'trash');
    del.setAttribute('aria-label', 'Löschen');
    del.onclick = () => row.remove();
    delCell.appendChild(del);

    row.appendChild(handleCell);
    row.appendChild(nameCell);
    row.appendChild(delCell);
    return row;
  }

  function renderTeams(list){
    teamListEl.innerHTML = '';
    list.forEach(n => teamListEl.appendChild(createTeamRow(n)));
  }

  if (teamListEl && window.UIkit && UIkit.util) {
    UIkit.util.on(teamListEl, 'moved', saveTeamOrder);
  }

  if(teamListEl){
    apiFetch('/teams.json', { headers: { 'Accept':'application/json' } })
      .then(r => r.json())
      .then(data => { renderTeams(data); })
      .catch(()=>{});
    if (teamRestrictTeams) {
      teamRestrictTeams.checked = !!cfgInitial.QRRestrict;
    }
  }

  teamAddBtn?.addEventListener('click', e => {
    e.preventDefault();
    teamListEl.appendChild(createTeamRow(''));
  });

  teamSaveBtn?.addEventListener('click', e => {
    e.preventDefault();
    const names = Array.from(teamListEl.querySelectorAll('input.uk-input'))
      .map(i => i.value.trim())
      .filter(Boolean);
    apiFetch('/teams.json', {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify(names)
    }).then(r => {
      if(!r.ok) throw new Error(r.statusText);
      notify('Liste gespeichert','success');
    }).catch(err => {
      console.error(err);
      notify('Fehler beim Speichern','danger');
    });
    if (teamRestrictTeams) {
      cfgInitial.QRRestrict = teamRestrictTeams.checked;
      apiFetch('/config.json', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(cfgInitial)
      }).catch(()=>{});
    }
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
  const backupTableBody = document.getElementById('backupTableBody');
  const tenantTableBody = document.getElementById('tenantTableBody');
  const tenantSyncBtn = document.getElementById('tenantSyncBtn');

  function loadBackups() {
    if (!backupTableBody) return;
    apiFetch('/backups')
      .then(r => r.json())
      .then(list => {
        backupTableBody.innerHTML = '';
        if (list.length === 0) {
          const row = document.createElement('tr');
          const cell = document.createElement('td');
          cell.colSpan = 2;
          cell.textContent = 'Keine Backups';
          row.appendChild(cell);
          backupTableBody.appendChild(row);
          return;
        }
        list.forEach(name => {
          const tr = document.createElement('tr');
          const nameTd = document.createElement('td');
          nameTd.textContent = name;
          const actionTd = document.createElement('td');
          const imp = document.createElement('button');
          imp.className = 'uk-button uk-button-primary uk-margin-small-right';
          imp.textContent = 'Wiederherstellen';
          imp.addEventListener('click', () => {
            apiFetch('/backups/' + encodeURIComponent(name) + '/restore', { method: 'POST' })
              .then(r => {
                if (!r.ok) throw new Error(r.statusText);
                notify('Import abgeschlossen', 'success');
              })
              .catch(() => notify('Fehler beim Import', 'danger'));
          });
          const dl = document.createElement('button');
          dl.className = 'uk-button uk-button-default uk-margin-small-right';
          dl.textContent = 'Download';
          dl.addEventListener('click', () => {
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
          });
          const del = document.createElement('button');
          del.className = 'uk-button uk-button-danger';
          del.textContent = 'Löschen';
          del.addEventListener('click', () => {
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
          });
          actionTd.appendChild(imp);
          actionTd.appendChild(dl);
          actionTd.appendChild(del);
          tr.appendChild(nameTd);
          tr.appendChild(actionTd);
          backupTableBody.appendChild(tr);
        });
      })
      .catch(() => {});
  }
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

  tenantSyncBtn?.addEventListener('click', e => {
    e.preventDefault();
    const original = tenantSyncBtn.innerHTML;
    tenantSyncBtn.disabled = true;
    tenantSyncBtn.innerHTML = '<div uk-spinner></div>';
    apiFetch('/tenants/sync', { method: 'POST' })
      .then(r => r.json())
      .then(() => {
        notify('Mandanten eingelesen', 'success');
        loadTenants();
      })
      .catch(() => notify('Fehler beim Synchronisieren', 'danger'))
      .finally(() => {
        tenantSyncBtn.disabled = false;
        tenantSyncBtn.innerHTML = original;
      });
  });

  function loadTenants() {
    if (!tenantTableBody || window.domainType !== 'main') return;
    const mainDomain = window.mainDomain || '';
    apiFetch('/tenants.json', { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(list => {
        tenantTableBody.innerHTML = '';
        list.forEach(t => {
          const tr = document.createElement('tr');
          const subTd = document.createElement('td');
          const sub = t.subdomain || '';
          if (sub && mainDomain) {
            const a = document.createElement('a');
            a.href = 'https://' + sub + '.' + mainDomain;
            a.target = '_blank';
            a.rel = 'noopener';
            a.textContent = sub;
            subTd.appendChild(a);
          } else {
            subTd.textContent = sub;
          }
          const planTd = document.createElement('td');
          planTd.textContent = t.plan || '';
          const billingTd = document.createElement('td');
          billingTd.textContent = t.billing_info || '';
          const createdTd = document.createElement('td');
          createdTd.textContent = (t.created_at || '').replace('T', ' ').replace(/\..*/, '');
          const actionTd = document.createElement('td');
          const delBtn = document.createElement('button');
          delBtn.className = 'uk-button uk-button-danger uk-button-small';
          delBtn.textContent = 'Mandant löschen';
          delBtn.addEventListener('click', () => {
            if (!confirm('Mandant wirklich löschen?')) return;
            const originalHtml = delBtn.innerHTML;
            delBtn.disabled = true;
            delBtn.innerHTML = '<div uk-spinner></div>';
            apiFetch('/tenants', {
              method: 'DELETE',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ uid: t.uid })
            })
              .then(r => {
                if (!r.ok) return r.text().then(text => { throw new Error(text); });
                return apiFetch('/api/tenants/' + encodeURIComponent(t.subdomain), { method: 'DELETE' });
              })
              .then(() => {
                notify('Mandant entfernt', 'success');
                loadTenants();
              })
              .catch(() => notify('Fehler beim Löschen', 'danger'))
              .finally(() => {
                delBtn.disabled = false;
                delBtn.innerHTML = originalHtml;
              });
          });
          const renewBtn = document.createElement('button');
          renewBtn.className = 'uk-button uk-button-default uk-button-small uk-margin-small-right';
          renewBtn.textContent = 'SSL erneuern';
          renewBtn.addEventListener('click', () => {
            apiFetch('/api/tenants/' + encodeURIComponent(t.subdomain) + '/renew-ssl', { method: 'POST' })
              .then(r => r.json().then(data => ({ ok: r.ok, data })))
              .then(({ ok, data }) => {
                if (!ok) throw new Error(data.error || 'Fehler');
                notify(data.status || 'Zertifikat wird erneuert', 'success');
              })
              .catch(err => notify(err.message || 'Fehler beim Erneuern', 'danger'));
          });
          const welcomeBtn = document.createElement('button');
          welcomeBtn.className = 'uk-button uk-button-default uk-button-small uk-margin-small-right';
          welcomeBtn.textContent = 'Willkommensmail';
          welcomeBtn.addEventListener('click', () => {
            apiFetch('/tenants/' + encodeURIComponent(t.subdomain) + '/welcome')
              .then(r => {
                if (!r.ok) throw new Error('Fehler');
                return r.text();
              })
              .then(html => {
                const w = window.open('', '_blank');
                if (w) {
                  w.document.write(html);
                  w.document.close();
                }
              })
              .catch(() => notify('Willkommensmail nicht verfügbar', 'danger'));
          });
          actionTd.appendChild(welcomeBtn);
          actionTd.appendChild(renewBtn);
          actionTd.appendChild(delBtn);
          tr.appendChild(subTd);
          tr.appendChild(planTd);
          tr.appendChild(billingTd);
          tr.appendChild(createdTd);
          tr.appendChild(actionTd);
          tenantTableBody.appendChild(tr);
        });
      })
      .catch(() => {});
  }

  // Zähler für eindeutige Namen von Eingabefeldern
  let catalogRowIndex = 0;
  let cardIndex = 0;

  // --------- Hilfe-Seitenleiste ---------
  const helpBtn = document.getElementById('helpBtn');
  const helpSidebar = document.getElementById('helpSidebar');
  const helpContent = document.getElementById('helpContent');

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
      if (qrImg) {
        const link = window.baseUrl ? window.baseUrl : withBase('/?event=' + encodeURIComponent(ev.uid || ''));
        qrImg.src = withBase('/qr.png?t=' + encodeURIComponent(link) + '&text1=QUIZ&text2=RACE&rounded=1');
      }
      if (qrLabel) qrLabel.textContent = ev.name || '';
      catalogsEl.innerHTML = '';
      catalogs.forEach(c => {
        const wrapper = document.createElement('div');
        wrapper.className = 'uk-width-1-1 uk-width-1-2@s';
        const card = document.createElement('div');
        card.className = 'export-card uk-card uk-card-default uk-card-body';
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
                img.src = withBase('/qr/catalog?t=' + encodeURIComponent(qrLink));
        img.alt = 'QR';
        img.width = 96;
        img.height = 96;
        card.appendChild(h4);
        card.appendChild(p);
        card.appendChild(img);
        wrapper.appendChild(card);
        catalogsEl.appendChild(wrapper);
      });
      teamsEl.innerHTML = '';
      teams.forEach(t => {
        const wrapper = document.createElement('div');
        wrapper.className = 'uk-width-1-1 uk-width-1-2@s';
        const card = document.createElement('div');
        card.className = 'export-card uk-card uk-card-default uk-card-body uk-position-relative';
        const btn = document.createElement('button');
        btn.className = 'qr-print-btn uk-icon-button uk-position-top-right';
        btn.setAttribute('data-team', t);
        btn.setAttribute('uk-icon', 'icon: print');
        btn.setAttribute('aria-label', 'QR-Code drucken');
        const h4 = document.createElement('h4');
        h4.className = 'uk-card-title';
        h4.textContent = t;
        const img = document.createElement('img');
        img.src = withBase('/qr/team?t=' + encodeURIComponent(t));
        img.alt = 'QR';
        img.width = 96;
        img.height = 96;
        card.appendChild(btn);
        card.appendChild(h4);
        card.appendChild(img);
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
    helpContent.textContent = activeHelpText();
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
    const initIdx = adminRoutes.indexOf(initRoute);
    if (initIdx >= 0) {
      tabControl.show(initIdx);
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
          loadTenants();
        }
      });
      const summaryIdx = adminRoutes.indexOf('summary');
      const tenantIdx = adminRoutes.indexOf('tenants');
    if (summaryIdx >= 0) {
      adminTabs.children[summaryIdx]?.addEventListener('click', () => {
        loadSummary();
      });
    }
    if (tenantIdx >= 0) {
      adminTabs.children[tenantIdx]?.addEventListener('click', () => {
        loadTenants();
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
            loadTenants();
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

  // Page editors are handled in trumbowyg-pages.js

  loadBackups();
  if (adminRoutes.indexOf('tenants') >= 0) {
    loadTenants();
  }
});
