document.addEventListener('DOMContentLoaded', () => {
  const resultsBtn = document.getElementById('show-results-btn');
  const puzzleBtn = document.getElementById('check-puzzle-btn');
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
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">' +
      '<h3 class="uk-modal-title uk-text-center">Rätselwort überprüfen</h3>' +
      '<input id="puzzle-input" class="uk-input" type="text" placeholder="Rätselwort eingeben">' +
      '<div id="puzzle-feedback" class="uk-margin-top uk-text-center"></div>' +
      '<button class="uk-button uk-button-primary uk-width-1-1 uk-margin-top">Überprüfen</button>' +
      '</div>';
    const input = modal.querySelector('#puzzle-input');
    const feedback = modal.querySelector('#puzzle-feedback');
    const btn = modal.querySelector('button');
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    UIkit.util.on(modal, 'shown', () => { input.focus(); });
    btn.addEventListener('click', () => {
      const expected = (window.quizConfig && window.quizConfig.puzzleWord) ? window.quizConfig.puzzleWord.toLowerCase() : '';
      const val = (input.value || '').trim().toLowerCase();
      if(val && val === expected){
        const custom = (window.quizConfig && window.quizConfig.puzzleFeedback) ? window.quizConfig.puzzleFeedback.trim() : '';
        feedback.textContent = custom || 'Herzlichen Glückwunsch, das Rätselwort ist korrekt!';
        feedback.className = 'uk-margin-top uk-text-center uk-text-success';
      }else{
        feedback.textContent = 'Das ist leider nicht korrekt. Versuch es noch einmal!';
        feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
      }
    });
    ui.show();
  }

  resultsBtn?.addEventListener('click', showResults);
  puzzleBtn?.addEventListener('click', showPuzzle);
});
