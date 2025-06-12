document.addEventListener('DOMContentLoaded', function () {
  function notify(msg, status = 'primary') {
    if (typeof UIkit !== 'undefined' && UIkit.notification) {
      UIkit.notification({ message: msg, status, pos: 'top-center', timeout: 2000 });
    } else {
      alert(msg);
    }
  }
  // --------- Konfiguration bearbeiten ---------
  // Ausgangswerte aus der bestehenden Konfiguration
  const cfgInitial = window.quizConfig || {};
  // Verweise auf die Formularfelder
  const cfgFields = {
    logoPath: document.getElementById('cfgLogoPath'),
    pageTitle: document.getElementById('cfgPageTitle'),
    header: document.getElementById('cfgHeader'),
    subheader: document.getElementById('cfgSubheader'),
    backgroundColor: document.getElementById('cfgBackgroundColor'),
    buttonColor: document.getElementById('cfgButtonColor'),
    checkAnswerButton: document.getElementById('cfgCheckAnswerButton'),
    qrUser: document.getElementById('cfgQRUser')
  };
  // Füllt das Formular mit den Werten aus einem Konfigurationsobjekt
  function renderCfg(data) {
    cfgFields.logoPath.value = data.logoPath || '';
    cfgFields.pageTitle.value = data.pageTitle || '';
    cfgFields.header.value = data.header || '';
    cfgFields.subheader.value = data.subheader || '';
    cfgFields.backgroundColor.value = data.backgroundColor || '';
    cfgFields.buttonColor.value = data.buttonColor || '';
    cfgFields.checkAnswerButton.value = data.CheckAnswerButton || 'yes';
    cfgFields.qrUser.value = String(data.QRUser) || 'false';
  }
  renderCfg(cfgInitial);
  document.getElementById('cfgResetBtn').addEventListener('click', function (e) {
    e.preventDefault();
    renderCfg(cfgInitial);
  });
  document.getElementById('cfgSaveBtn').addEventListener('click', function (e) {
    e.preventDefault();
    const data = {
      logoPath: cfgFields.logoPath.value.trim(),
      pageTitle: cfgFields.pageTitle.value.trim(),
      header: cfgFields.header.value.trim(),
      subheader: cfgFields.subheader.value.trim(),
      backgroundColor: cfgFields.backgroundColor.value.trim(),
      buttonColor: cfgFields.buttonColor.value.trim(),
      CheckAnswerButton: cfgFields.checkAnswerButton.value,
      QRUser: cfgFields.qrUser.value === 'true'
    };
    fetch('/config.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    })
      .then(r => {
        if (r.ok) {
          notify('Konfiguration gespeichert', 'success');
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

  // --------- Fragen bearbeiten ---------
  const container = document.getElementById('questions');
  const addBtn = document.getElementById('addBtn');
  const saveBtn = document.getElementById('saveBtn');
  const resetBtn = document.getElementById('resetBtn');
  const catSelect = document.getElementById('catalogSelect');
  const catalogList = document.getElementById('catalogList');
  const newCatBtn = document.getElementById('newCatBtn');
  let catalogs = [];
  let catalogFile = '';
  let initial = [];

  function loadCatalog(id) {
    const cat = catalogs.find(c => c.id === id);
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
        opt.value = c.id;
        opt.textContent = c.name || c.id;
        catSelect.appendChild(opt);
      });
      renderCatalogs(catalogs);
      const params = new URLSearchParams(window.location.search);
      const id = params.get('katalog') || (catalogs[0] && catalogs[0].id);
      if (id) {
        catSelect.value = id;
        loadCatalog(id);
      }
    });

  catSelect.addEventListener('change', () => loadCatalog(catSelect.value));

  function deleteCatalog(cat) {
    if (!confirm('Katalog wirklich löschen?')) return;
    fetch('/kataloge/' + cat.file, { method: 'DELETE' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        catalogs = catalogs.filter(c => c.id !== cat.id);
        const opt = catSelect.querySelector('option[value="' + cat.id + '"]');
        opt?.remove();
        if (catalogs[0]) {
          if (catSelect.value === cat.id) {
            catSelect.value = catalogs[0].id;
            loadCatalog(catalogs[0].id);
          }
        } else {
          catalogFile = '';
          initial = [];
          renderAll(initial);
        }
        renderCatalogs(catalogs);
        notify('Katalog gelöscht', 'success');
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Löschen', 'danger');
      });
  }

  function renderCatalogs(list) {
    if (!catalogList) return;
    catalogList.innerHTML = '';
    list.forEach(cat => {
      const row = document.createElement('div');
      row.className = 'uk-flex uk-flex-middle uk-margin';
      const info = document.createElement('div');
      info.className = 'uk-width-expand';
      const title = document.createElement('strong');
      title.textContent = cat.name || cat.id;
      const desc = document.createElement('div');
      desc.textContent = cat.description || '';
      info.appendChild(title);
      info.appendChild(desc);
      const qr = document.createElement('img');
      qr.className = 'uk-margin-left';
      qr.width = 64;
      qr.height = 64;
      qr.src = qrSrc(window.location.origin + '/kataloge/' + cat.file);
      const del = document.createElement('button');
      del.className = 'uk-button uk-button-danger uk-margin-left';
      del.textContent = 'Löschen';
      del.addEventListener('click', () => deleteCatalog(cat));
      row.appendChild(info);
      row.appendChild(qr);
      row.appendChild(del);
      catalogList.appendChild(row);
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
    card.className = 'uk-card uk-card-default uk-card-body uk-margin question-card';
    if (index >= 0) {
      card.dataset.index = String(index);
    }
    const typeSelect = document.createElement('select');
    typeSelect.className = 'uk-select uk-margin-small-bottom type-select';
    ['sort', 'assign', 'mc'].forEach(t => {
      const opt = document.createElement('option');
      opt.value = t;
      opt.textContent = t;
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
        mc: 'Mehrfachauswahl (Multiple Choice, mehrere Antworten möglich).'
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
      const dInput = document.createElement('input');
      dInput.className = 'uk-input definition';
      dInput.type = 'text';
      dInput.placeholder = 'Definition';
      dInput.value = def;
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

    card.appendChild(typeSelect);
    card.appendChild(typeInfo);
    card.appendChild(prompt);
    card.appendChild(fields);
    card.appendChild(removeBtn);
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
    const id = prompt('ID für neuen Katalog?');
    if (!id) return;
    const file = id + '.json';
    fetch('/kataloge/' + file, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: '[]' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        const cat = { id, file };
        catalogs.push(cat);
        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = id;
        catSelect.appendChild(opt);
        catSelect.value = id;
        loadCatalog(id);
        renderCatalogs(catalogs);
        notify('Katalog erstellt', 'success');
      })
      .catch(err => {
        console.error(err);
        notify('Fehler beim Erstellen', 'danger');
      });
  });


  const resultsResetBtn = document.getElementById('resultsResetBtn');
  const resultsDownloadBtn = document.getElementById('resultsDownloadBtn');

  resultsResetBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    if (!confirm('Alle Ergebnisse löschen?')) return;
    fetch('/results', { method: 'DELETE' })
      .then(r => {
        if (!r.ok) throw new Error(r.statusText);
        notify('Ergebnisse gelöscht', 'success');
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
        a.download = 'results.xlsx';
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
  const teamRestrict = document.getElementById('teamRestrict');

  function qrSrc(text){
    return 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' + encodeURIComponent(text);
  }

  function createTeamRow(name = ''){
    const div = document.createElement('div');
    div.className = 'uk-flex uk-flex-middle uk-margin-small';
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'uk-input uk-width-expand';
    input.value = name;
  const img = document.createElement('img');
  img.className = 'uk-margin-left';
  img.width = 64;
  img.height = 64;
  const dl = document.createElement('button');
  dl.className = 'uk-button uk-button-default uk-margin-left';
  dl.innerHTML = '<span uk-icon="download"></span>';
  dl.setAttribute('aria-label', 'QR-Code herunterladen');
  function triggerDownload(text) {
    const a = document.createElement('a');
    a.href = qrSrc(text);
    a.download = text + '.png';
    document.body.appendChild(a);
    a.click();
    a.remove();
  }
  function update() {
    const val = input.value.trim();
    if (val) {
      img.src = qrSrc(val);
      dl.disabled = false;
      dl.onclick = e => { e.preventDefault(); triggerDownload(val); };
    } else {
      img.src = '';
      dl.disabled = true;
      dl.onclick = null;
    }
  }
  input.addEventListener('input', update);
  update();
  const del = document.createElement('button');
  del.className = 'uk-button uk-button-danger uk-margin-left';
  del.textContent = '×';
  del.onclick = () => div.remove();
  div.appendChild(input);
  div.appendChild(img);
  div.appendChild(dl);
  div.appendChild(del);
    return div;
  }

  function renderTeams(list){
    teamListEl.innerHTML = '';
    list.forEach(n => teamListEl.appendChild(createTeamRow(n)));
  }

  if(teamListEl){
    fetch('/teams.json', { headers: { 'Accept':'application/json' } })
      .then(r => r.json())
      .then(data => { renderTeams(data); })
      .catch(()=>{});
    teamRestrict.checked = !!cfgInitial.QRRestrict;
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
    cfgInitial.QRRestrict = teamRestrict.checked;
    fetch('/config.json', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(cfgInitial)
    }).catch(()=>{});
  });

  // Zähler für eindeutige Namen von Eingabefeldern
  let cardIndex = 0;
});
