/* global UIkit, STORAGE_KEYS, getStored, setStored, clearStored */

function insertSoftHyphens(text){
  return text ? text.replace(/\/-/g, '\u00AD') : '';
}

function safeUserName(name){
  return typeof name === 'string' && /^[\w\s.-]{1,100}$/.test(name) ? name : '';
}

function formatPointsDisplay(points, maxPoints){
  const normalizedPoints = Number.isFinite(points) ? points : Number.parseInt(points, 10);
  if(!Number.isFinite(normalizedPoints)){
    return '';
  }
  const normalizedMax = Number.isFinite(maxPoints) ? maxPoints : Number.parseInt(maxPoints, 10);
  if(Number.isFinite(normalizedMax) && normalizedMax > 0){
    return `${normalizedPoints}/${normalizedMax}`;
  }
  return String(normalizedPoints);
}
document.addEventListener('DOMContentLoaded', () => {
  const eventUid = (window.quizConfig || {}).event_uid || '';
  const cfg = window.quizConfig || {};
  const playerUidKey = STORAGE_KEYS.PLAYER_UID;
  const resultsBtn = document.getElementById('show-results-btn');
  const puzzleBtn = document.getElementById('check-puzzle-btn');
  const photoBtn = document.getElementById('upload-photo-btn');
  const basePath = window.basePath || '';
  const withBase = path => basePath + path;
  const resultsEnabled = !(window.quizConfig && window.quizConfig.teamResults === false);
  if (resultsBtn && !resultsEnabled) {
    resultsBtn.remove();
  }
  const photoEnabled = !(window.quizConfig && window.quizConfig.photoUpload === false);
  if (photoBtn && !photoEnabled) {
    photoBtn.remove();
  }
  const puzzleInfo = document.getElementById('puzzle-solved-text');
  const user = safeUserName(getStored(STORAGE_KEYS.PLAYER_NAME) || '');

  let catalogMap = null;
  function fetchCatalogMap() {
    if (catalogMap) return Promise.resolve(catalogMap);
    return fetch(withBase('/kataloge/catalogs.json'), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(list => {
        const map = {};
        if (Array.isArray(list)) {
          list.forEach(c => {
            const entry = { name: c.name || '', slug: c.slug || '' };
            if (c.uid) map[c.uid] = entry;
            if (c.sort_order) map[c.sort_order] = entry;
            if (c.slug) map[c.slug] = entry;
          });
        }
        catalogMap = map;
        return map;
      })
      .catch(() => {
        catalogMap = {};
        return catalogMap;
      });
  }

  const formatTs = window.formatPuzzleTime || function(ts){
    const d = new Date(ts * 1000);
    const pad = n => n.toString().padStart(2,'0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };

  async function fetchPuzzleTimeFromResults(name){
    try{
      const list = await fetch(withBase('/results.json')).then(r => r.json());
      if(Array.isArray(list)){
        for(let i=list.length-1; i>=0; i--){
          const e = list[i];
          if(e.name === name && e.puzzleTime){
            return parseInt(e.puzzleTime, 10);
          }
        }
      }
    }catch(e){
      return null;
    }
    return null;
  }

  async function updatePuzzleInfo(){
    let solved = getStored(STORAGE_KEYS.PUZZLE_SOLVED) === 'true';
    let ts = parseInt(getStored(STORAGE_KEYS.PUZZLE_TIME) || '0', 10);
    if(!solved){
      const name = getStored(STORAGE_KEYS.PLAYER_NAME) || '';
      if(name){
        const t = await fetchPuzzleTimeFromResults(name);
        if(t){
          solved = true;
          ts = t;
          setStored(STORAGE_KEYS.PUZZLE_SOLVED, 'true');
          setStored(STORAGE_KEYS.PUZZLE_TIME, String(t));
        }
      }
    }
    if(solved){
      if (puzzleBtn) puzzleBtn.remove();
      if(ts && puzzleInfo){
        puzzleInfo.textContent = `Rätselwort gelöst: ${formatTs(ts)}`;
      }
    }else{
      if(puzzleInfo) puzzleInfo.textContent = '';
    }
  }

  function renderQuestionPreview(q, catMap){
    const card = document.createElement('div');
    card.className = 'uk-card qr-card uk-card-body question-preview';
    const title = document.createElement('h5');
    const info = catMap[q.catalog];
    const cat = q.catalogName || (info ? info.name : q.catalog);
    title.textContent = insertSoftHyphens(cat);
    card.appendChild(title);

    const h = document.createElement('h4');
    h.textContent = insertSoftHyphens(q.prompt || '');
    card.appendChild(h);

    const type = q.type || 'mc';
    if(type === 'sort' && Array.isArray(q.items)){
      const ul = document.createElement('ul');
      q.items.forEach(it => {
        const li = document.createElement('li');
        li.textContent = insertSoftHyphens(it);
        ul.appendChild(li);
      });
      card.appendChild(ul);
    }else if(type === 'assign' && Array.isArray(q.terms)){
      const ul = document.createElement('ul');
      q.terms.forEach(p => {
        const li = document.createElement('li');
        li.textContent = insertSoftHyphens(p.term || '') + ' – ' + insertSoftHyphens(p.definition || '');
        ul.appendChild(li);
      });
      card.appendChild(ul);
    }else if(type === 'swipe' && Array.isArray(q.cards)){
      const ul = document.createElement('ul');
      q.cards.forEach(c => {
        const li = document.createElement('li');
        li.textContent = insertSoftHyphens(c.text) + (c.correct ? ' ✓' : '');
        ul.appendChild(li);
      });
      card.appendChild(ul);
    }else{
      const ul = document.createElement('ul');
      if(Array.isArray(q.options)){
        const answers = Array.isArray(q.answers) ? q.answers : [];
        q.options.forEach((opt, i) => {
          const li = document.createElement('li');
          const correct = answers.includes(i);
          li.textContent = insertSoftHyphens(opt) + (correct ? ' ✓' : '');
          if(correct) li.classList.add('uk-text-success');
          ul.appendChild(li);
        });
      }
      card.appendChild(ul);
    }

    return card;
  }

  function showResults(){
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    const dialog = document.createElement('div');
    dialog.className = 'uk-modal-dialog uk-modal-body';
    const title = document.createElement('h3');
    title.className = 'uk-modal-title uk-text-center';
    title.textContent = 'Ergebnisübersicht';
    const userP = document.createElement('p');
    userP.className = 'uk-text-center';
    userP.textContent = user;
    const tbodyContainer = document.createElement('div');
    tbodyContainer.id = 'team-results';
    tbodyContainer.className = 'uk-overflow-auto';
    const closeBtn = document.createElement('button');
    closeBtn.className = 'uk-button uk-button-primary uk-width-1-1 uk-margin-top';
    closeBtn.textContent = 'Schließen';
    dialog.append(title, userP, tbodyContainer, closeBtn);
    modal.appendChild(dialog);
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    closeBtn.addEventListener('click', () => ui.hide());

    Promise.all([
      fetchCatalogMap(),
      fetch(withBase('/results.json')).then(r => r.json()),
      fetch(withBase('/question-results.json')).then(r => r.json())
    ])
      .then(([catMap, rows, qrows]) => {
        const filtered = rows.filter(row => row.name === user);
        const map = new Map();
        filtered.forEach(r => {
          const info = catMap[r.catalog] || { name: r.catalog, slug: r.catalog };
          const name = r.catalogName || info.name;
          const pointsText = formatPointsDisplay(r.points, r.max_points);
          const correctText = `${r.correct}/${r.total}`;
          const summaryText = pointsText ? `${correctText} · ${pointsText}` : correctText;
          map.set(name, { res: summaryText, slug: info.slug });
        });
        const table = document.createElement('table');
        table.className = 'uk-table uk-table-divider';
        const thead = document.createElement('thead');
        const trh = document.createElement('tr');
        const th1 = document.createElement('th');
        th1.textContent = 'Katalog';
        const th2 = document.createElement('th');
        th2.textContent = 'Ergebnis';
        trh.append(th1, th2);
        thead.appendChild(trh);
        table.appendChild(thead);
        const tb = document.createElement('tbody');
        if(map.size === 0){
          const tr = document.createElement('tr');
          const td = document.createElement('td');
          td.colSpan = 2;
          td.textContent = 'Keine Daten';
          tr.appendChild(td);
          tb.appendChild(tr);
        }else{
          map.forEach((info, cat) => {
            const tr = document.createElement('tr');
            const td1 = document.createElement('td');
            const a = document.createElement('a');
            if (info.slug) {
              let href = '/?katalog=' + encodeURIComponent(info.slug);
              if(eventUid) href += '&event=' + encodeURIComponent(eventUid);
              a.href = href;
              a.target = '_blank';
            } else {
              a.href = '#';
            }
            a.textContent = cat;
            td1.appendChild(a);
            const td2 = document.createElement('td');
            td2.textContent = info.res;
            tr.appendChild(td1);
            tr.appendChild(td2);
            tb.appendChild(tr);
          });
        }
        table.appendChild(tb);
        if(tbodyContainer) tbodyContainer.appendChild(table);

        const wrong = qrows.filter(row => row.name === user && !row.correct);
        if (wrong.length) {
          const h = document.createElement('h4');
          h.textContent = 'Falsch beantwortete Fragen';
          tbodyContainer.appendChild(h);
          wrong.forEach(w => {
            const card = renderQuestionPreview(w, catMap);
            tbodyContainer.appendChild(card);
          });
        }
      })
      .catch(() => {
        if(tbodyContainer) tbodyContainer.textContent = 'Fehler beim Laden';
      });

    ui.show();
  }

  function showPuzzle(){
    const solvedBefore = getStored(STORAGE_KEYS.PUZZLE_SOLVED) === 'true';
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    const dialog = document.createElement('div');
    dialog.className = 'uk-modal-dialog uk-modal-body';
    const title = document.createElement('h3');
    title.className = 'uk-modal-title uk-text-center';
    title.textContent = 'Rätselwort überprüfen';
    let input = null;
    if(!solvedBefore){
      input = document.createElement('input');
      input.id = 'puzzle-input';
      input.className = 'uk-input';
      input.type = 'text';
      input.placeholder = 'Rätselwort eingeben';
    }
    const feedback = document.createElement('div');
    feedback.id = 'puzzle-feedback';
    feedback.className = 'uk-margin-top uk-text-center';
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary uk-width-1-1 uk-margin-top';
    btn.textContent = solvedBefore ? 'Schließen' : 'Überprüfen';
    dialog.append(title);
    if(input) dialog.appendChild(input);
    dialog.append(feedback, btn);
    modal.appendChild(dialog);
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    if(!solvedBefore && input) UIkit.util.on(modal, 'shown', () => { input.focus(); });
    function handleCheck(){
          const valRaw = (input.value || '').trim();
          const ts = Math.floor(Date.now()/1000);
          const userName = getStored(STORAGE_KEYS.PLAYER_NAME) || '';
          const catalog = getStored('quizCatalog') || 'unknown';
          const data = { name: userName, catalog, puzzleTime: ts, puzzleAnswer: valRaw };
          if(cfg.collectPlayerUid){
            const uid = getStored(playerUidKey);
            if(uid) data.player_uid = uid;
          }
          let debugTimer = null;
          fetch(withBase('/results?debug=1'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
          })
        .then(async r => {
          if(!r.ok){
            throw new Error('HTTP ' + r.status);
          }
          try{
            return await r.json();
          }catch(e){
            return null;
          }
        })
        .then(data => {
          if(data){
          if(data.normalizedAnswer !== undefined && data.normalizedExpected !== undefined){
              feedback.textContent = `Debug: ${data.normalizedAnswer} vs ${data.normalizedExpected}`;
              debugTimer = setTimeout(() => { feedback.textContent = ''; }, 3000);
            }
          if(data.success){
              if(debugTimer) clearTimeout(debugTimer);
              const cfg = window.quizConfig || {};
              const msg = (data.feedback && data.feedback.trim())
                ? data.feedback
                : (cfg.puzzleFeedback && cfg.puzzleFeedback.trim())
                  ? cfg.puzzleFeedback
                  : 'Herzlichen Glückwunsch, das Rätselwort ist korrekt!';
              feedback.textContent = msg;
              feedback.className = 'uk-margin-top uk-text-center uk-text-success';
              setStored(STORAGE_KEYS.PUZZLE_SOLVED, 'true');
              setStored(STORAGE_KEYS.PUZZLE_TIME, String(ts));
              updatePuzzleInfo();
              input.disabled = true;
              btn.textContent = 'Schließen';
              btn.removeEventListener('click', handleCheck);
              btn.addEventListener('click', () => ui.hide());
              return;
            }
          }
          feedback.textContent = 'Das ist leider nicht korrekt. Viel Glück beim nächsten Versuch!';
          feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
        })
        .catch(() => {
          feedback.textContent = 'Fehler bei der Überprüfung.';
          feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
        });
    }
    if(solvedBefore){
      feedback.textContent = 'Du hast das Rätselwort bereits gelöst.';
      feedback.className = 'uk-margin-top uk-text-center uk-text-success';
      btn.addEventListener('click', () => ui.hide());
    }else{
      btn.addEventListener('click', handleCheck);
    }
    ui.show();
  }

  function showPhotoModal(cb, requireConsent = true){
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    const dialog = document.createElement('div');
    dialog.className = 'uk-modal-dialog uk-modal-body';
    const card = document.createElement('div');
    card.className = 'uk-card qr-card uk-card-body uk-padding-small uk-width-1-1';

    const p = document.createElement('p');
    p.className = 'uk-text-small';
    p.append(
      'Hinweis zum Hochladen von Gruppenfotos:',
      document.createElement('br'),
      'Ich bestätige, dass alle auf dem Foto abgebildeten Personen vor der Aufnahme darüber informiert wurden, dass das Gruppenfoto zu Dokumentationszwecken erstellt und ggf. veröffentlicht wird. Alle Anwesenden hatten Gelegenheit, der Aufnahme zu widersprechen, indem sie den Aufnahmebereich verlassen oder dies ausdrücklich mitteilen konnten.'
    );

    const fileDiv = document.createElement('div');
    fileDiv.className = 'uk-margin-small-bottom';
    const label = document.createElement('label');
    label.className = 'uk-form-label';
    label.setAttribute('for', 'photo-input');
    label.textContent = 'Beweisfoto auswählen';
    const uploadDiv = document.createElement('div');
    uploadDiv.className = 'stacked-upload';
    uploadDiv.setAttribute('uk-form-custom', 'target: true');
    const input = document.createElement('input');
    input.id = 'photo-input';
    input.type = 'file';
    input.accept = 'image/*';
    input.setAttribute('capture', 'environment');
    input.setAttribute('aria-label', 'Datei auswählen');
    const textInput = document.createElement('input');
    textInput.className = 'uk-input uk-width-1-1';
    textInput.type = 'text';
    textInput.placeholder = 'Keine Datei ausgewählt';
    textInput.disabled = true;
    const browseBtn = document.createElement('button');
    browseBtn.className = 'uk-button uk-button-default uk-width-1-1 uk-margin-small-top';
    browseBtn.type = 'button';
    browseBtn.tabIndex = -1;
    browseBtn.textContent = 'Durchsuchen';
    uploadDiv.append(input, textInput, browseBtn);
    fileDiv.append(label, uploadDiv);

    card.append(p, fileDiv);

    let consent = null;
    if (requireConsent) {
      const consentLabel = document.createElement('label');
      consentLabel.className = 'uk-form-label uk-margin-small-bottom';
      consent = document.createElement('input');
      consent.type = 'checkbox';
      consent.id = 'consent-checkbox';
      consent.className = 'uk-checkbox uk-margin-small-right';
      consentLabel.append(consent, 'Einverständnis aller abgebildeten Personen wurde eingeholt ');
      card.appendChild(consentLabel);
    }

    const feedback = document.createElement('div');
    feedback.id = 'photo-feedback';
    feedback.className = 'uk-margin-small uk-text-center';
    const btn = document.createElement('button');
    btn.id = 'upload-btn';
    btn.className = 'uk-button uk-button-primary uk-width-1-1';
    btn.disabled = true;
    btn.textContent = 'Hochladen';
    card.append(feedback, btn);

    dialog.appendChild(card);
    modal.appendChild(dialog);
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });

    function toggleBtn(){
      btn.disabled = !input.files.length || (requireConsent && consent && !consent.checked);
    }
    input.addEventListener('change', toggleBtn);
    if(consent) consent.addEventListener('change', toggleBtn);
    btn.addEventListener('click', () => {
      const file = input.files && input.files[0];
      if(!file || (requireConsent && consent && !consent.checked)) return;
      const fd = new FormData();
      fd.append('photo', file);
        fd.append('name', user);
        fd.append('catalog', 'summary');
        fd.append('team', user);
        if(cfg.collectPlayerUid){
          const uid = getStored(playerUidKey);
          if(uid) fd.append('player_uid', uid);
        }

      const originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = '';
      const spinner = document.createElement('div');
      spinner.setAttribute('uk-spinner', '');
      btn.appendChild(spinner);

      fetch(withBase('/photos'), { method: 'POST', body: fd })
        .then(async r => {
          if (!r.ok) {
            throw new Error(await r.text());
          }
          const ct = r.headers.get('Content-Type') || '';
          if (!ct.includes('application/json')) {
            throw new Error(await r.text());
          }
          return r.json();
        })
        .then(data => {
          feedback.textContent = 'Foto gespeichert';
          feedback.className = 'uk-margin-top uk-text-center uk-text-success';
          btn.disabled = true;
          input.disabled = true;
          if(consent) consent.disabled = true;
          setTimeout(() => {
            ui.hide();
            if (typeof UIkit !== 'undefined' && UIkit.notification) {
              UIkit.notification({
                message: 'Bild erfolgreich gespeichert',
                status: 'success',
                pos: 'top-center',
                timeout: 2000
              });
            } else {
              alert('Bild erfolgreich gespeichert');
            }
          }, 1000);
          if(typeof cb === 'function') cb(data.path);
        })
        .catch(e => {
          feedback.textContent = e.message || 'Fehler beim Hochladen';
          feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
        })
        .finally(() => {
          btn.textContent = originalText;
        });
    });
    ui.show();
  }

  if (resultsBtn) { resultsBtn.addEventListener('click', showResults); }
  if (puzzleBtn) { puzzleBtn.addEventListener('click', showPuzzle); }
  if (photoBtn && photoEnabled) { photoBtn.addEventListener('click', showPhotoModal); }

  updatePuzzleInfo();
});
