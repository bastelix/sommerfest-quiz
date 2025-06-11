// Lädt die verfügbaren Fragenkataloge und startet nach Auswahl das Quiz
(function(){
  async function loadCatalogList(){
    try{
      const res = await fetch('kataloge/catalogs.json');
      return await res.json();
    }catch(e){
      console.error('Katalogliste konnte nicht geladen werden.', e);
      return [];
    }
  }

  async function loadQuestions(file){
    try{
      const res = await fetch('kataloge/' + file);
      const data = await res.json();
      window.quizQuestions = data;
      if(window.startQuiz){
        window.startQuiz(data);
      }
    }catch(e){
      console.error('Fragen konnten nicht geladen werden.', e);
    }
  }

  function showSelection(catalogs){
    const container = document.getElementById('quiz');
    if(!container) return;
    container.innerHTML = '';
    const grid = document.createElement('div');
    grid.className = 'uk-child-width-1-2@s uk-grid-small uk-grid-match';
    catalogs.forEach(cat => {
      const cardWrap = document.createElement('div');
      const card = document.createElement('div');
      card.className = 'uk-card uk-card-default uk-card-body';
      const title = document.createElement('h3');
      title.textContent = cat.name || cat.id;
      const desc = document.createElement('p');
      desc.textContent = cat.description || '';
      const btn = document.createElement('button');
      btn.className = 'uk-button uk-button-primary';
      btn.textContent = 'Starten';
      btn.addEventListener('click', () => {
        history.replaceState(null, '', '?katalog=' + cat.id);
        loadQuestions(cat.file);
      });
      card.appendChild(title);
      card.appendChild(desc);
      card.appendChild(btn);
      cardWrap.appendChild(card);
      grid.appendChild(cardWrap);
    });
    container.appendChild(grid);
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const catalogs = await loadCatalogList();
    const params = new URLSearchParams(window.location.search);
    const id = params.get('katalog');
    const selected = catalogs.find(c => c.id === id);
    if(selected){
      loadQuestions(selected.file);
    }else{
      showSelection(catalogs);
    }
  });
})();
