/* global UIkit */
document.addEventListener('DOMContentLoaded', function () {
  function notify(msg, status = 'primary') {
    if (typeof UIkit !== 'undefined' && UIkit.notification) {
      UIkit.notification({ message: msg, status, pos: 'top-center', timeout: 2000 });
    } else {
      alert(msg);
    }
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

  function updatePuzzleFeedbackUI() {
    if (!puzzleIcon || !puzzleLabel) return;
    if (puzzleFeedback.trim().length > 0) {
      puzzleIcon.setAttribute('uk-icon', 'icon: check');
      puzzleLabel.textContent = 'Feedbacktext bearbeiten';
    } else {
      puzzleIcon.setAttribute('uk-icon', 'icon: pencil');
      puzzleLabel.textContent = 'Feedbacktext eingeben';
    }
    UIkit.icon(puzzleIcon, { icon: puzzleIcon.getAttribute('uk-icon').split(': ')[1] });
  }
  // --------- Konfiguration bearbeiten ---------
  // Ausgangswerte aus der bestehenden Konfiguration
  const cfgInitial = window.quizConfig || {};
  // Verweise auf die Formularfelder
  const cfgFields = {
    logoFile: document.getElementById('cfgLogoFile'),
    logoPreview: document.getElementById('cfgLogoPreview'),
    pageTitle: document.getElementById('cfgPageTitle'),
    header: document.getElementById('cfgHeader'),
    subheader: document.getElementById('cfgSubheader'),
    backgroundColor: document.getElementById('cfgBackgroundColor'),
    buttonColor: document.getElementById('cfgButtonColor'),
    checkAnswerButton: document.getElementById('cfgCheckAnswerButton'),
    qrUser: document.getElementById('cfgQRUser'),
    teamRestrict: document.getElementById('cfgTeamRestrict'),
    competitionMode: document.getElementById('cfgCompetitionMode'),
    teamResults: document.getElementById('cfgTeamResults'),
    photoUpload: document.getElementById('cfgPhotoUpload'),
    puzzleEnabled: document.getElementById('cfgPuzzleEnabled'),
    puzzleWord: document.getElementById('cfgPuzzleWord'),
    puzzleWrap: document.getElementById('cfgPuzzleWordWrap')
  };
  const puzzleFeedbackBtn = document.getElementById('puzzleFeedbackBtn');
  const puzzleIcon = document.getElementById('puzzleFeedbackIcon');
  const puzzleLabel = document.getElementById('puzzleFeedbackLabel');
  const puzzleTextarea = document.getElementById('puzzleFeedbackTextarea');
  const puzzleSaveBtn = document.getElementById('puzzleFeedbackSave');
  const puzzleModal = UIkit.modal('#puzzleFeedbackModal');
  const commentTextarea = document.getElementById('catalogCommentTextarea');
  const commentSaveBtn = document.getElementById('catalogCommentSave');
  const commentModal = UIkit.modal('#catalogCommentModal');
  const commentToolbar = document.getElementById('catalogCommentToolbar');
  const resultsResetModal = UIkit.modal('#resultsResetModal');
  const resultsResetConfirm = document.getElementById('resultsResetConfirm');
  let puzzleFeedback = '';
  let currentCommentInput = null;
  let logoUploaded = false;

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
  if (cfgFields.logoFile && cfgFields.logoPreview) {
    const bar = document.getElementById('cfgLogoProgress');
    UIkit.upload('.js-upload', {
      url: '/logo.png',
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
        cfgFields.logoPreview.src = '/logo.' + ext + '?' + Date.now();
        logoUploaded = true;
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
    cfgFields.header.value = data.header || '';
    cfgFields.subheader.value = data.subheader || '';
    cfgFields.backgroundColor.value = data.backgroundColor || '';
    cfgFields.buttonColor.value = data.buttonColor || '';
    cfgFields.checkAnswerButton.checked = data.CheckAnswerButton !== 'no';
    cfgFields.qrUser.checked = !!data.QRUser;
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
    puzzleFeedback = data.puzzleFeedback || '';
    updatePuzzleFeedbackUI();
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
  puzzleSaveBtn?.addEventListener('click', () => {
    if (!puzzleTextarea) return;
    puzzleFeedback = puzzleTextarea.value;
    updatePuzzleFeedbackUI();
    puzzleModal.hide();
    cfgInitial.puzzleFeedback = puzzleFeedback;
    fetch('/config.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(cfgInitial)
    }).then(r => {
      if (r.ok) {
        notify('Feedbacktext gespeichert', 'success');
      }
    }).catch(() => {});
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
  document.getElementById('cfgResetBtn').addEventListener('click', function (e) {
    e.preventDefault();
    renderCfg(cfgInitial);
  });
  document.getElementById('cfgSaveBtn').addEventListener('click', function (e) {
    e.preventDefault();
    const data = Object.assign({}, cfgInitial, {
      logoPath: (function () {
        if (cfgFields.logoPreview && cfgFields.logoPreview.src) {
          const m = cfgFields.logoPreview.src.match(/\/logo\.(png|webp)/);
          if (m) return '/logo.' + m[1];
        }
        return cfgInitial.logoPath;
      })(),
      pageTitle: cfgFields.pageTitle.value.trim(),
      header: cfgFields.header.value.trim(),
      subheader: cfgFields.subheader.value.trim(),
      backgroundColor: cfgFields.backgroundColor.value.trim(),
      buttonColor: cfgFields.buttonColor.value.trim(),
      CheckAnswerButton: cfgFields.checkAnswerButton.checked ? 'yes' : 'no',
      QRUser: cfgFields.qrUser.checked,
      QRRestrict: cfgFields.teamRestrict ? cfgFields.teamRestrict.checked : cfgInitial.QRRestrict,
      competitionMode: cfgFields.competitionMode ? cfgFields.competitionMode.checked : cfgInitial.competitionMode,
      teamResults: cfgFields.teamResults ? cfgFields.teamResults.checked : cfgInitial.teamResults,
      photoUpload: cfgFields.photoUpload ? cfgFields.photoUpload.checked : cfgInitial.photoUpload,
      puzzleWordEnabled: cfgFields.puzzleEnabled ? cfgFields.puzzleEnabled.checked : cfgInitial.puzzleWordEnabled,
      puzzleWord: cfgFields.puzzleWord ? cfgFields.puzzleWord.value.trim() : cfgInitial.puzzleWord,
      puzzleFeedback: puzzleFeedback
    });
    fetch('/config.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    })
      .then(r => {
        if (r.ok) {
          notify('Konfiguration gespeichert', 'success');
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
  });


  const summaryPrintBtn = document.getElementById('summaryPrintBtn');
  summaryPrintBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    window.print();
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
    fetch('/kataloge/' + catalogFile, { headers: { 'Accept': 'application/json' } })
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

  fetch('/kataloge/catalogs.json', { headers: { 'Accept': 'application/json' } })
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
    fetch('/kataloge/' + cat.file, { method: 'DELETE' })
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
    del.className = 'uk-button uk-button-danger';
    del.textContent = '×';
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
    fetch('/kataloge/catalogs.json', {
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
      swipe: 'Swipe-Karten'
    };
    ['sort', 'assign', 'mc', 'swipe'].forEach(t => {
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
        swipe: 'Karten nach links oder rechts wischen.'
      };
      typeInfo.textContent = map[typeSelect.value] || '';
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
    removeBtn.className = 'uk-button uk-button-danger uk-margin-small-top uk-align-right';
    removeBtn.textContent = 'Entfernen';
    removeBtn.onclick = () => {
      const idx = card.dataset.index;
      if (idx !== undefined) {
        fetch('/kataloge/' + catalogFile + '/' + idx, { method: 'DELETE' })
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
      btn.className = 'uk-button uk-button-danger uk-button-small uk-margin-left';
      btn.textContent = '×';
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
      rem.className = 'uk-button uk-button-danger uk-button-small';
      rem.textContent = '×';
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
      rem.className = 'uk-button uk-button-danger uk-button-small uk-margin-left';
      rem.textContent = '×';
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
      rem.className = 'uk-button uk-button-danger uk-button-small uk-margin-left';
      rem.textContent = '×';
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
        add.className = 'uk-button uk-button-small uk-margin-small-top';
        add.textContent = 'Item hinzufügen';
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
        add.className = 'uk-button uk-button-small uk-margin-small-top';
        add.textContent = 'Begriff hinzufügen';
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
        add.className = 'uk-button uk-button-small uk-margin-small-top';
        add.textContent = 'Karte hinzufügen';
        add.onclick = e => { e.preventDefault(); list.appendChild(addCard('', false)); };
        fields.appendChild(list);
        fields.appendChild(add);
      } else {
        const list = document.createElement('div');
        (q.options || ['', '']).forEach((opt, i) =>
          list.appendChild(addOption(opt, (q.answers || []).includes(i)))
        );
        const add = document.createElement('button');
        add.className = 'uk-button uk-button-small uk-margin-small-top';
        add.textContent = 'Option hinzufügen';
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
      h.textContent = prompt.value || 'Vorschau';
      preview.appendChild(h);
      if (typeSelect.value === 'sort') {
        const ul = document.createElement('ul');
        Array.from(fields.querySelectorAll('.item')).forEach(i => {
          const li = document.createElement('li');
          li.textContent = i.value;
          ul.appendChild(li);
        });
        preview.appendChild(ul);
      } else if (typeSelect.value === 'assign') {
        const ul = document.createElement('ul');
        Array.from(fields.querySelectorAll('.term-row')).forEach(r => {
          const term = r.querySelector('.term').value;
          const def = r.querySelector('.definition').value;
          const li = document.createElement('li');
          li.textContent = term + ' – ' + def;
          ul.appendChild(li);
        });
        preview.appendChild(ul);
      } else if (typeSelect.value === 'swipe') {
        const ul = document.createElement('ul');
        Array.from(fields.querySelectorAll('.card-row')).forEach(r => {
          const text = r.querySelector('.card-text').value;
          const check = r.querySelector('.card-correct').checked;
          const li = document.createElement('li');
          li.textContent = text + (check ? ' ✓' : '');
          ul.appendChild(li);
        });
        preview.appendChild(ul);
      } else {
        const ul = document.createElement('ul');
        Array.from(fields.querySelectorAll('.option-row')).forEach(r => {
          const input = r.querySelector('.option');
          const check = r.querySelector('.answer').checked;
          const li = document.createElement('li');
          li.textContent = input.value + (check ? ' ✓' : '');
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
    fetch('/kataloge/' + catalogFile, {
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
          await fetch('/kataloge/' + id + '.json', {
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
          const res = await fetch('/kataloge/' + row.dataset.initialFile, { headers: { 'Accept': 'application/json' } });
          const content = await res.text();
          await fetch('/kataloge/' + newFile, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: content });
          await fetch('/kataloge/' + row.dataset.initialFile, { method: 'DELETE' });
          row.dataset.initialFile = newFile;
        } catch (err) {
          console.error(err);
          notify('Fehler beim Umbenennen', 'danger');
        }
      }
    }

    const data = collectCatalogs();
    fetch('/kataloge/catalogs.json', {
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
        notify('Katalogliste gespeichert', 'success');
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Speichern', 'danger');
      });
  });


  const resultsResetBtn = document.getElementById('resultsResetBtn');
  const resultsDownloadBtn = document.getElementById('resultsDownloadBtn');

  resultsResetBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    resultsResetModal.show();
  });

  resultsResetConfirm?.addEventListener('click', function () {
    fetch('/results', { method: 'DELETE' })
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
    fetch('/results/download')
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
    fetch('/teams.json', {
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
    del.className = 'uk-button uk-button-danger';
    del.textContent = '×';
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
    fetch('/teams.json', { headers: { 'Accept':'application/json' } })
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
    fetch('/teams.json', {
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
      fetch('/config.json', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(cfgInitial)
      }).catch(()=>{});
    }
  });

  // --------- Passwort ändern ---------
  const passSaveBtn = document.getElementById('passSaveBtn');
  const importJsonBtn = document.getElementById('importJsonBtn');
  const exportJsonBtn = document.getElementById('exportJsonBtn');
  const newPass = document.getElementById('newPass');
  const newPassRepeat = document.getElementById('newPassRepeat');
  const backupTableBody = document.getElementById('backupTableBody');

  function loadBackups() {
    if (!backupTableBody) return;
    fetch('/backups')
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
            fetch('/import/' + encodeURIComponent(name), { method: 'POST' })
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
            fetch('/backups/' + encodeURIComponent(name) + '/download')
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
            fetch('/backups/' + encodeURIComponent(name), { method: 'DELETE' })
              .then(r => {
                if (!r.ok) throw new Error(r.statusText);
                loadBackups();
              })
              .catch(() => notify('Fehler beim Löschen', 'danger'));
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

  passSaveBtn?.addEventListener('click', e => {
    e.preventDefault();
    if (!newPass || !newPassRepeat) return;
    const p1 = newPass.value;
    const p2 = newPassRepeat.value;
    if (p1 === '' || p2 === '') {
      notify('Passwort darf nicht leer sein', 'danger');
      return;
    }
    if (p1 !== p2) {
      notify('Passwörter stimmen nicht überein', 'danger');
      return;
    }
    fetch('/password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ password: p1 })
    })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify('Passwort geändert', 'success');
        newPass.value = '';
        newPassRepeat.value = '';
      })
      .catch(err => {
        console.error(err);
      notify('Fehler beim Speichern', 'danger');
    });
  });

  importJsonBtn?.addEventListener('click', e => {
    e.preventDefault();
    fetch('/import', { method: 'POST' })
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
    fetch('/export', { method: 'POST' })
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

  // Zähler für eindeutige Namen von Eingabefeldern
  let catalogRowIndex = 0;
  let cardIndex = 0;

  // --------- Hilfe-Seitenleiste ---------
  const helpBtn = document.getElementById('helpBtn');
  const helpSidebar = document.getElementById('helpSidebar');
  const helpContent = document.getElementById('helpContent');
  const adminTabs = document.getElementById('adminTabs');

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

  loadBackups();
});
