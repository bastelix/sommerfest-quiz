document.addEventListener('DOMContentLoaded', () => {
  const resultsBtn = document.getElementById('show-results-btn');
  const puzzleBtn = document.getElementById('check-puzzle-btn');
  const photoBtn = document.getElementById('upload-photo-btn');
  const puzzleInfo = document.getElementById('puzzle-solved-text');
  const user = sessionStorage.getItem('quizUser') || '';

  const formatTs = window.formatPuzzleTime || function(ts){
    const d = new Date(ts * 1000);
    const pad = n => n.toString().padStart(2,'0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };
  const fetchEntry = window.fetchLatestPuzzleEntry || async function(name, catalog){
    try{
      const list = await fetch('/results.json').then(r => r.json());
      if(Array.isArray(list)){
        return list.slice().reverse().find(e => e.name === name && e.catalog === catalog && e.puzzleTime);
      }
    }catch(e){ return null; }
    return null;
  };

  function updatePuzzleInfo(){
    const solved = sessionStorage.getItem('puzzleSolved') === 'true';
    const catalog = sessionStorage.getItem('quizCatalog') || 'unknown';
    if(solved){
      if (puzzleBtn) puzzleBtn.remove();
      fetchEntry(user, catalog).then(entry => {
        if(entry && entry.puzzleTime){
          puzzleInfo.textContent = `Rätselwort gelöst: ${formatTs(entry.puzzleTime)}`;
        }
      }).catch(()=>{});
    }else{
      if(puzzleInfo) puzzleInfo.textContent = '';
    }
  }

  function showResults(){
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">' +
      '<h3 class="uk-modal-title uk-text-center">Ergebnisübersicht</h3>' +
      '<div id="team-results" class="uk-overflow-auto"></div>' +
      '<button class="uk-button uk-button-primary uk-width-1-1 uk-margin-top">Schließen</button>' +
      '</div>';
    const tbodyContainer = modal.querySelector('#team-results');
    const closeBtn = modal.querySelector('button');
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    closeBtn.addEventListener('click', () => ui.hide());

    fetch('/results.json')
      .then(r => r.json())
      .then(rows => {
        const filtered = rows.filter(row => row.name === user);
        const map = new Map();
        filtered.forEach(r => {
          map.set(r.catalog, `${r.correct}/${r.total}`);
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
    const expected = (window.quizConfig && window.quizConfig.puzzleWord) ? window.quizConfig.puzzleWord : '';
    const custom = (window.quizConfig && window.quizConfig.puzzleFeedback) ? window.quizConfig.puzzleFeedback.trim() : '';
    if(solvedBefore){
      feedback.innerHTML = 'Du hast das Rätselwort bereits gelöst:<br><strong>' + expected + '</strong><br>' + (custom || 'Herzlichen Glückwunsch, das Rätselwort ist korrekt!');
      feedback.className = 'uk-margin-top uk-text-center uk-text-success';
      btn.addEventListener('click', () => ui.hide());
    }else{
      function handleCheck(){
        const val = (input.value || '').trim().toLowerCase();
        if(val && val === expected.toLowerCase()){
          feedback.textContent = custom || 'Herzlichen Glückwunsch, das Rätselwort ist korrekt!';
          feedback.className = 'uk-margin-top uk-text-center uk-text-success';
          sessionStorage.setItem('puzzleSolved', 'true');
          const userName = sessionStorage.getItem('quizUser') || '';
          const catalog = sessionStorage.getItem('quizCatalog') || 'unknown';
          fetch('/results', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: userName, catalog, puzzleTime: Math.floor(Date.now()/1000) })
          }).catch(()=>{});
          updatePuzzleInfo();
          input.disabled = true;
          btn.textContent = 'Schließen';
          btn.removeEventListener('click', handleCheck);
          btn.addEventListener('click', () => ui.hide());
        }else{
          feedback.textContent = 'Das ist leider nicht korrekt. Viel Glück beim nächsten Versuch!';
          feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
          return;
        }
      }
      btn.addEventListener('click', handleCheck);
    }
    ui.show();
  }

  function showPhotoModal(){
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">' +
      '<h3 class="uk-modal-title uk-text-center">Beweisfoto einreichen</h3>' +
      '<p class="uk-text-small">Hinweis zum Hochladen von Gruppenfotos:<br>' +
      'Mit dem Upload eines Gruppenfotos bestätigen Sie, dass alle abgebildeten Teammitglieder der Verwendung des Fotos im Rahmen des Teamtages zustimmen. Das Hochladen ist freiwillig. Die Fotos werden ausschließlich für die Dokumentation des Teamtages verwendet und nach der Veranstaltung von der Onlineplattform gelöscht.' +
      '</p>' +
      '<input id="team-select" class="uk-input uk-margin-small-top" list="team-list" placeholder="Team wählen">' +
      '<datalist id="team-list"></datalist>' +
      '<input id="photo-input" class="uk-input" type="file" accept="image/*" capture="environment">' +
      '<div id="photo-feedback" class="uk-margin-top uk-text-center"></div>' +
      '<button class="uk-button uk-button-primary uk-width-1-1 uk-margin-top" disabled>Hochladen</button>' +
      '</div>';
    const input = modal.querySelector('#photo-input');
    const feedback = modal.querySelector('#photo-feedback');
    const select = modal.querySelector('#team-select');
    const list = modal.querySelector('#team-list');
    const btn = modal.querySelector('button');
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    fetch('/teams.json').then(r => r.json()).then(data => {
      if(Array.isArray(data)){
        data.forEach(t => {
          const opt = document.createElement('option');
          opt.value = t;
          list.appendChild(opt);
        });
      }
    }).catch(()=>{});

    function toggleBtn(){
      btn.disabled = !input.files.length || !select.value.trim();
    }
    input.addEventListener('change', toggleBtn);
    select.addEventListener('change', toggleBtn);
    select.addEventListener('input', toggleBtn);
    btn.addEventListener('click', () => {
      const file = input.files && input.files[0];
      if(!file || !select.value) return;
      const fd = new FormData();
      fd.append('photo', file);
      fd.append('name', user);
      fd.append('catalog', 'summary');
      fd.append('team', select.value);
      fetch('/photos', { method: 'POST', body: fd })
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(() => {
          feedback.textContent = 'Foto gespeichert';
          feedback.className = 'uk-margin-top uk-text-center uk-text-success';
          btn.disabled = true;
          input.disabled = true;
          select.disabled = true;
        })
        .catch(() => {
          feedback.textContent = 'Fehler beim Hochladen';
          feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
        });
    });
    ui.show();
  }

  if (resultsBtn) { resultsBtn.addEventListener('click', showResults); }
  if (puzzleBtn) { puzzleBtn.addEventListener('click', showPuzzle); }
  if (photoBtn) { photoBtn.addEventListener('click', showPhotoModal); }

  updatePuzzleInfo();
});
