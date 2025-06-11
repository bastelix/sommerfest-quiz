// Lädt die verfügbaren Fragenkataloge und startet nach Auswahl das Quiz
(function(){
  function applyConfig(){
    const cfg = window.quizConfig || {};
    const headerEl = document.getElementById('quiz-header');
    if(headerEl){
      headerEl.innerHTML = '';
      if(cfg.logoPath){
        const img = document.createElement('img');
        img.src = cfg.logoPath;
        img.alt = cfg.header || 'Logo';
        img.className = 'uk-margin-small-bottom';
        headerEl.appendChild(img);
      }
      if(cfg.header){
        const h = document.createElement('h1');
        h.textContent = cfg.header;
        h.className = 'uk-margin-remove-bottom';
        headerEl.appendChild(h);
      }
      if(cfg.subheader){
        const p = document.createElement('p');
        p.textContent = cfg.subheader;
        p.className = 'uk-text-lead';
        headerEl.appendChild(p);
      }
    }
    const styleEl = document.createElement('style');
    styleEl.textContent = `\n      body { background-color: ${cfg.backgroundColor || '#f8f8f8'}; }\n      .uk-button-primary { background-color: ${cfg.buttonColor || '#1e87f0'}; border-color: ${cfg.buttonColor || '#1e87f0'}; }\n    `;
    document.head.appendChild(styleEl);
  }
  async function loadCatalogList(){
    try{
      const res = await fetch('kataloge/catalogs.json');
      if(res.ok){
        return await res.json();
      }
    }catch(e){
      console.warn('Katalogliste konnte nicht geladen werden.', e);
    }
    const inline = document.getElementById('catalogs-data');
    if(inline){
      try{
        return JSON.parse(inline.textContent);
      }catch(err){
        console.error('Inline-Katalogliste ung\u00fcltig.', err);
      }
    }
    return [];
  }

  async function loadQuestions(id, file){
    try{
      const res = await fetch('kataloge/' + file);
      const data = await res.json();
      window.quizQuestions = data;
      if(window.startQuiz){
        window.startQuiz(data);
      }
      return;
    }catch(e){
      console.warn('Fragen konnten nicht geladen werden, versuche inline Daten', e);
    }
    const inline = document.getElementById(id + '-data');
    if(inline){
      try{
        const data = JSON.parse(inline.textContent);
        window.quizQuestions = data;
        if(window.startQuiz){
          window.startQuiz(data);
        }
      }catch(err){
        console.error('Inline-Daten ung\u00fcltig.', err);
      }
    }
  }

  function showSelection(catalogs){
    const container = document.getElementById('quiz');
    if(!container) return;
    container.innerHTML = '';
    const grid = document.createElement('div');
    grid.className = 'uk-child-width-expand@s uk-text-center';
    grid.setAttribute('uk-grid', '');
    catalogs.forEach(cat => {
      const cardWrap = document.createElement('div');
      const card = document.createElement('div');
      card.className = 'uk-card uk-card-default uk-card-body uk-card-hover';
      card.style.cursor = 'pointer';
      card.addEventListener('click', () => {
        history.replaceState(null, '', '?katalog=' + cat.id);
        loadQuestions(cat.id, cat.file);
      });
      const title = document.createElement('h3');
      title.textContent = cat.name || cat.id;
      const desc = document.createElement('p');
      desc.textContent = cat.description || '';
      card.appendChild(title);
      card.appendChild(desc);
      cardWrap.appendChild(card);
      grid.appendChild(cardWrap);
    });
    container.appendChild(grid);
  }

  function showLogin(onDone){
    const cfg = window.quizConfig || {};
    const container = document.getElementById('quiz');
    if(!container) return;
    container.innerHTML = '';
    const div = document.createElement('div');
    div.className = 'uk-text-center';
    if(cfg.QRUser){
      const scanBtn = document.createElement('button');
      scanBtn.className = 'uk-button uk-button-primary';
      scanBtn.textContent = 'QR-Code scannen';
      if(cfg.buttonColor){
        scanBtn.style.backgroundColor = cfg.buttonColor;
        scanBtn.style.borderColor = cfg.buttonColor;
        scanBtn.style.color = '#fff';
      }
      const bypass = document.createElement('a');
      bypass.href = '#';
      bypass.textContent = 'Kataloge anzeigen';
      bypass.className = 'uk-display-block uk-margin-top';
      bypass.addEventListener('click', (e)=>{
        e.preventDefault();
        sessionStorage.setItem('quizUser', generateUserName());
        onDone();
      });
      const modal = document.createElement('div');
      modal.id = 'qr-modal';
      modal.setAttribute('uk-modal', '');
      modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">'+
        '<button class="uk-modal-close-default" type="button" uk-close></button>'+
        '<div id="login-qr" class="uk-margin" style="width:250px"></div>'+
      '</div>';
      let scanner;
      const startScanner = () => {
        if(typeof Html5QrcodeScanner === 'undefined'){
          document.getElementById('login-qr').textContent = 'QR-Scanner nicht verf\u00fcgbar.';
          return;
        }
        try{
          scanner = new Html5QrcodeScanner('login-qr', {fps:10, qrbox:250});
          scanner.render(text=>{
            sessionStorage.setItem('quizUser', text.trim());
            scanner.clear().then(()=>{ UIkit.modal(modal).hide(); onDone(); });
          });
        }catch(err){
          console.error('QR scanner initialization failed.', err);
          document.getElementById('login-qr').textContent = 'QR-Scanner konnte nicht gestartet werden.';
        }
      };
      scanBtn.addEventListener('click', () => {
        UIkit.modal(modal).show();
        startScanner();
      });
      UIkit.util.on(modal, 'hidden', () => {
        if(scanner){
          scanner.clear().catch(()=>{});
          scanner = null;
        }
      });
      div.appendChild(scanBtn);
      div.appendChild(bypass);
      container.appendChild(modal);
    }else{
      const btn = document.createElement('button');
      btn.className = 'uk-button uk-button-primary';
      btn.textContent = 'Starten';
      if(cfg.buttonColor){
        btn.style.backgroundColor = cfg.buttonColor;
        btn.style.borderColor = cfg.buttonColor;
        btn.style.color = '#fff';
      }
      btn.addEventListener('click', () => {
        sessionStorage.setItem('quizUser', generateUserName());
        onDone();
      });
      div.appendChild(btn);
    }
    container.appendChild(div);
  }

  async function init(){
    applyConfig();
    const catalogs = await loadCatalogList();
    const params = new URLSearchParams(window.location.search);
    const id = params.get('katalog');
    const proceed = () => {
      const selected = catalogs.find(c => c.id === id);
      if(selected){
        loadQuestions(selected.id, selected.file);
      }else{
        showSelection(catalogs);
      }
    };
    if((window.quizConfig || {}).QRUser){
      showLogin(proceed);
    }else{
      if(!sessionStorage.getItem('quizUser')){
        sessionStorage.setItem('quizUser', generateUserName());
      }
      proceed();
    }
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  }else{
    init();
  }
})();
