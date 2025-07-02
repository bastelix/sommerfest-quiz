/* global UIkit */
function getStored(key){
  return sessionStorage.getItem(key) || localStorage.getItem(key);
}
function setStored(key, value){
  try{
    sessionStorage.setItem(key, value);
    localStorage.setItem(key, value);
  }catch(e){}
}

function insertSoftHyphens(text){
  return text ? text.replace(/\/-/g, '\u00AD') : '';
}
document.addEventListener('DOMContentLoaded', () => {
  const resultsBtn = document.getElementById('show-results-btn');
  const puzzleBtn = document.getElementById('check-puzzle-btn');
  const photoBtn = document.getElementById('upload-photo-btn');
  const resultsEnabled = !(window.quizConfig && window.quizConfig.teamResults === false);
  if (resultsBtn && !resultsEnabled) {
    resultsBtn.remove();
  }
  const photoEnabled = !(window.quizConfig && window.quizConfig.photoUpload === false);
  if (photoBtn && !photoEnabled) {
    photoBtn.remove();
  }
  const puzzleInfo = document.getElementById('puzzle-solved-text');
  const user = getStored('quizUser') || '';

  let catalogMap = null;
  function fetchCatalogMap() {
    if (catalogMap) return Promise.resolve(catalogMap);
    return fetch('/kataloge/catalogs.json', { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(list => {
        const map = {};
        if (Array.isArray(list)) {
          list.forEach(c => {
            const name = c.name || '';
            if (c.uid) map[c.uid] = name;
            if (c.sort_order) map[c.sort_order] = name;
            if (c.slug) map[c.slug] = name;
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
      const list = await fetch('/results.json').then(r => r.json());
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
    let solved = sessionStorage.getItem('puzzleSolved') === 'true';
    let ts = parseInt(sessionStorage.getItem('puzzleTime') || '0', 10);
    if(!solved){
      const name = getStored('quizUser') || '';
      if(name){
        const t = await fetchPuzzleTimeFromResults(name);
        if(t){
          solved = true;
          ts = t;
          sessionStorage.setItem('puzzleSolved', 'true');
          sessionStorage.setItem('puzzleTime', String(t));
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
    card.className = 'uk-card uk-card-muted uk-card-body question-preview';
    const title = document.createElement('h5');
    const cat = q.catalogName || catMap[q.catalog] || q.catalog;
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
    modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">' +
      '<h3 class="uk-modal-title uk-text-center">Ergebnisübersicht</h3>' +
      '<p class="uk-text-center">' + user + '</p>' +
      '<div id="team-results" class="uk-overflow-auto"></div>' +
      '<button class="uk-button uk-button-primary uk-width-1-1 uk-margin-top">Schließen</button>' +
      '</div>';
    const tbodyContainer = modal.querySelector('#team-results');
    const closeBtn = modal.querySelector('button');
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    closeBtn.addEventListener('click', () => ui.hide());

    Promise.all([
      fetchCatalogMap(),
      fetch('/results.json').then(r => r.json()),
      fetch('/question-results.json').then(r => r.json())
    ])
      .then(([catMap, rows, qrows]) => {
        const filtered = rows.filter(row => row.name === user);
        const map = new Map();
        filtered.forEach(r => {
          const name = r.catalogName || catMap[r.catalog] || r.catalog;
          map.set(name, `${r.correct}/${r.total}`);
        });
        const table = document.createElement('table');
        table.className = 'uk-table uk-table-divider';
        table.innerHTML = '<thead><tr><th>Katalog</th><th>Ergebnis</th></tr></thead>';
        const tb = document.createElement('tbody');
        if(map.size === 0){
          const tr = document.createElement('tr');
          const td = document.createElement('td');
          td.colSpan = 2;
          td.textContent = 'Keine Daten';
          tr.appendChild(td);
          tb.appendChild(tr);
        }else{
          map.forEach((res, cat) => {
            const tr = document.createElement('tr');
            const td1 = document.createElement('td');
            td1.textContent = cat;
            const td2 = document.createElement('td');
            td2.textContent = res;
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
          tbodyContainer?.appendChild(h);
          wrong.forEach(w => {
            const card = renderQuestionPreview(w, catMap);
            tbodyContainer?.appendChild(card);
          });
        }
      })
      .catch(() => {
        if(tbodyContainer) tbodyContainer.textContent = 'Fehler beim Laden';
      });

    ui.show();
  }

  function showPuzzle(){
    const solvedBefore = sessionStorage.getItem('puzzleSolved') === 'true';
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">' +
      '<h3 class="uk-modal-title uk-text-center">Rätselwort überprüfen</h3>' +
      (solvedBefore ? '' : '<input id="puzzle-input" class="uk-input" type="text" placeholder="Rätselwort eingeben">') +
      '<div id="puzzle-feedback" class="uk-margin-top uk-text-center"></div>' +
      (solvedBefore ? '<button class="uk-button uk-button-primary uk-width-1-1 uk-margin-top">Schließen</button>' : '<button class="uk-button uk-button-primary uk-width-1-1 uk-margin-top">Überprüfen</button>') +
      '</div>';
    const input = modal.querySelector('#puzzle-input');
    const feedback = modal.querySelector('#puzzle-feedback');
    const btn = modal.querySelector('button');
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    if(!solvedBefore) UIkit.util.on(modal, 'shown', () => { input.focus(); });
    function handleCheck(){
        const valRaw = (input.value || '').trim();
        const ts = Math.floor(Date.now()/1000);
        const userName = getStored('quizUser') || '';
        const catalog = getStored('quizCatalog') || 'unknown';
        fetch('/results?debug=1', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name: userName, catalog, puzzleTime: ts, puzzleAnswer: valRaw })
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
              setTimeout(() => { feedback.textContent = ''; }, 3000);
            }
            if(data.success){
              feedback.textContent = 'Herzlichen Glückwunsch, das Rätselwort ist korrekt!';
              feedback.className = 'uk-margin-top uk-text-center uk-text-success';
              sessionStorage.setItem('puzzleSolved', 'true');
              sessionStorage.setItem('puzzleTime', String(ts));
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
    modal.innerHTML =
      '<div class="uk-modal-dialog uk-modal-body">' +
        '<div class="uk-card uk-card-default uk-card-body uk-padding-small uk-width-1-1">' +
          '<p class="uk-text-small">Hinweis zum Hochladen von Gruppenfotos:<br>' +
            'Ich bestätige, dass alle auf dem Foto abgebildeten Personen vor der Aufnahme darüber informiert wurden, dass das Gruppenfoto zu Dokumentationszwecken erstellt und ggf. veröffentlicht wird. Alle Anwesenden hatten Gelegenheit, der Aufnahme zu widersprechen, indem sie den Aufnahmebereich verlassen oder dies ausdrücklich mitteilen konnten.' +
          '</p>' +
          '<div class="uk-margin-small-bottom">' +
            '<label class="uk-form-label" for="photo-input">Beweisfoto auswählen</label>' +
            '<div class="stacked-upload" uk-form-custom="target: true">' +
              '<input id="photo-input" type="file" accept="image/*" capture="environment" aria-label="Datei auswählen">' +
              '<input class="uk-input uk-width-1-1" type="text" placeholder="Keine Datei ausgewählt" disabled>' +
              '<button class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top" type="button" tabindex="-1">Durchsuchen</button>' +
            '</div>' +
          '</div>' +
          (requireConsent ?
            '<label class="uk-form-label uk-margin-small-bottom">' +
              '<input type="checkbox" id="consent-checkbox" class="uk-checkbox uk-margin-small-right">' +
              'Einverständnis aller abgebildeten Personen wurde eingeholt ' +
            '</label>' : '') +
          '<div id="photo-feedback" class="uk-margin-small uk-text-center"></div>' +
          '<button id="upload-btn" class="uk-button uk-button-primary uk-width-1-1" disabled>Hochladen</button>' +
        '</div>' +
      '</div>';

    const input = modal.querySelector('#photo-input');
    const feedback = modal.querySelector('#photo-feedback');
    const consent = modal.querySelector('#consent-checkbox');
    const btn = modal.querySelector('#upload-btn');
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });

    function toggleBtn(){
      btn.disabled = !input.files.length || (requireConsent && !consent.checked);
    }
    input.addEventListener('change', toggleBtn);
    if(consent) consent.addEventListener('change', toggleBtn);
    btn.addEventListener('click', () => {
      const file = input.files && input.files[0];
      if(!file || (requireConsent && !consent.checked)) return;
      const fd = new FormData();
      fd.append('photo', file);
      fd.append('name', user);
      fd.append('catalog', 'summary');
      fd.append('team', user);

      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<div uk-spinner></div>';

      fetch('/photos', { method: 'POST', body: fd })
        .then(async r => {
          if (!r.ok) {
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
          btn.innerHTML = originalHtml;
        });
    });
    ui.show();
  }

  if (resultsBtn) { resultsBtn.addEventListener('click', showResults); }
  if (puzzleBtn) { puzzleBtn.addEventListener('click', showPuzzle); }
  if (photoBtn && photoEnabled) { photoBtn.addEventListener('click', showPhotoModal); }

  updatePuzzleInfo();
});
