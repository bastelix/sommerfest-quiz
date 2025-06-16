document.addEventListener('DOMContentLoaded', () => {
  const resultsBtn = document.getElementById('show-results-btn');
  const puzzleBtn = document.getElementById('check-puzzle-btn');
  const photoBtn = document.getElementById('upload-photo-btn');
  const user = sessionStorage.getItem('quizUser') || '';

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
      btn.addEventListener('click', () => {
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
          input.disabled = true;
          btn.textContent = 'Schließen';
        }else{
          feedback.textContent = 'Das ist leider nicht korrekt. Versuch es erneut!';
          feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
          return;
        }
      });
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
      'Mit dem Upload eines Gruppenfotos bestätigen Sie, dass alle abgebildeten Teammitglieder der Verwendung des Fotos im Rahmen des Teamtages zustimmen. Das Hochladen ist freiwillig. Die Fotos werden ausschließlich für die Dokumentation des Teamtages verwendet und nach der Veranstaltung von der Onlineplattform gelöscht.'</p>' +
      '<select id="team-select" class="uk-select uk-margin-small-top">' +
      '<option value="">Team wählen</option>' +
      '</select>' +
      '<input id="photo-input" class="uk-input" type="file" accept="image/*" capture="environment">' +
      '<div id="photo-feedback" class="uk-margin-top uk-text-center"></div>' +
      '<button class="uk-button uk-button-primary uk-width-1-1 uk-margin-top" disabled>Hochladen</button>' +
      '</div>';
    const input = modal.querySelector('#photo-input');
    const feedback = modal.querySelector('#photo-feedback');
    const select = modal.querySelector('#team-select');
    const btn = modal.querySelector('button');
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    fetch('/teams.json').then(r => r.json()).then(list => {
      if(Array.isArray(list)){
        list.forEach(t => {
          const opt = document.createElement('option');
          opt.value = t;
          opt.textContent = t;
          select.appendChild(opt);
        });
      }
    }).catch(()=>{});

    function toggleBtn(){
      btn.disabled = !input.files.length || !select.value;
    }
    input.addEventListener('change', toggleBtn);
    select.addEventListener('change', toggleBtn);
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

  resultsBtn?.addEventListener('click', showResults);
  puzzleBtn?.addEventListener('click', showPuzzle);
  photoBtn?.addEventListener('click', showPhotoModal);
});
