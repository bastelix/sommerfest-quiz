// Lädt die verfügbaren Fragenkataloge und startet nach Auswahl das Quiz
(function(){
  function setSubHeader(text){
    const headerEl = document.getElementById('quiz-header');
    if(!headerEl) return;
    let el = headerEl.querySelector('p[data-role="subheader"]');
    if(!el){
      el = document.createElement('p');
      el.dataset.role = 'subheader';
      el.className = 'uk-text-lead';
      headerEl.appendChild(el);
    }
    el.textContent = text || '';
  }

  function applyConfig(){
    const cfg = window.quizConfig || {};
    const headerEl = document.getElementById('quiz-header');
    if(headerEl){
      const img = headerEl.querySelector('img');
      let title = headerEl.querySelector('h1');
      if(img){
        if(cfg.logoPath){
          img.src = cfg.logoPath;
          img.classList.remove('uk-hidden');
        }else{
          img.src = '';
          img.classList.add('uk-hidden');
        }
      }
      if(!title){
        title = document.createElement('h1');
        title.className = 'uk-margin-remove-bottom';
        headerEl.appendChild(title);
      }
      title.textContent = cfg.header || '';
      setSubHeader(cfg.subheader || '');
      // Benutzername wird nach erfolgreichem Login ergänzt
    }
    const styleEl = document.createElement('style');
    styleEl.textContent = `\n      body { background-color: ${cfg.backgroundColor || '#ffffff'}; }\n      .uk-button-primary { background-color: ${cfg.buttonColor || '#1e87f0'}; border-color: ${cfg.buttonColor || '#1e87f0'}; }\n    `;
    document.head.appendChild(styleEl);
  }

  function updateUserName(){
    const headerEl = document.getElementById('quiz-header');
    if(!headerEl) return;
    let nameEl = document.getElementById('quiz-user-name');
    const user = sessionStorage.getItem('quizUser');
    if(!nameEl){
      if(!user) return;
      nameEl = document.createElement('p');
      nameEl.id = 'quiz-user-name';
      nameEl.className = 'uk-text-lead';
      headerEl.appendChild(nameEl);
    }
    const topbar = document.getElementById('topbar-title');
    if(user){
      nameEl.textContent = user;
      nameEl.classList.remove('uk-hidden');
      if(topbar){
        topbar.textContent = '';
        topbar.appendChild(document.createTextNode('Jetzt spielt:'));
        topbar.appendChild(document.createElement('br'));
        topbar.appendChild(document.createTextNode(user));
      }
    }else{
      nameEl.textContent = '';
      nameEl.classList.add('uk-hidden');
      if(topbar){
        topbar.textContent = topbar.dataset.defaultTitle || '';
      }
    }
  }
  async function loadCatalogList(){
    try{
      const res = await fetch('/kataloge/catalogs.json', { headers: { 'Accept': 'application/json' } });
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
        console.error('Inline-Katalogliste ungültig.', err);
      }
    }
    return [];
  }

  async function loadQuestions(id, file){
    sessionStorage.setItem('quizCatalog', id);
    try{
      const res = await fetch('/kataloge/' + file, { headers: { 'Accept': 'application/json' } });
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
        console.error('Inline-Daten ungültig.', err);
      }
    }
  }

  function showSelection(catalogs, solved){
    solved = solved || new Set();
    const container = document.getElementById('quiz');
    if(!container) return;
    container.innerHTML = '';
    const grid = document.createElement('div');
    grid.className = 'uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@m uk-grid-small uk-text-center';
    grid.setAttribute('uk-grid', '');
    catalogs.forEach(cat => {
      const cardWrap = document.createElement('div');
      const card = document.createElement('div');
      card.className = 'uk-card uk-card-default uk-card-body uk-card-hover';
      card.style.cursor = 'pointer';
      card.addEventListener('click', () => {
        if((window.quizConfig || {}).competitionMode && solved.has(cat.id)){
          const remaining = catalogs.filter(c => !solved.has(c.id)).map(c => c.name || c.id).join(', ');
          alert('Der Katalog ' + (cat.name || cat.id) + ' wurde von eurem Team bereits abgeschlossen.' + (remaining ? '\nFolgende Fragenkataloge fehlen euch noch: ' + remaining : ''));
          return;
        }
        history.replaceState(null, '', '?katalog=' + cat.id);
        setSubHeader(cat.description || '');
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

  async function showLogin(onDone, autoScan){
    const cfg = window.quizConfig || {};
    let allowed = [];
    if(cfg.QRRestrict){
      try{
        allowed = JSON.parse(sessionStorage.getItem('allowedTeams') || 'null');
        if(!allowed){
          const r = await fetch('/teams.json', {headers:{'Accept':'application/json'}});
          if(r.ok){
            allowed = await r.json();
            sessionStorage.setItem('allowedTeams', JSON.stringify(allowed));
          } else {
            allowed = [];
          }
        }
      }catch(e){
        allowed = [];
      }
    }
    const container = document.getElementById('quiz');
    if(!container) return;
    container.innerHTML = '';
    const div = document.createElement('div');
    div.className = 'uk-text-center';
    if(cfg.QRUser){
      const scanBtn = document.createElement('button');
      scanBtn.className = 'uk-button uk-button-primary';
      scanBtn.textContent = 'Name mit QR-Code scannen';
      if(cfg.buttonColor){
        scanBtn.style.backgroundColor = cfg.buttonColor;
        scanBtn.style.borderColor = cfg.buttonColor;
        scanBtn.style.color = '#fff';
      }
      let bypass;
      if(!cfg.QRRestrict){
        bypass = document.createElement('a');
        bypass.href = '#';
        bypass.textContent = 'Kataloge anzeigen';
        bypass.className = 'uk-display-block uk-margin-top';
        bypass.addEventListener('click', (e)=>{
          e.preventDefault();
          sessionStorage.setItem('quizUser', generateUserName());
          updateUserName();
          onDone();
        });
      }
      const modal = document.createElement('div');
      modal.id = 'qr-modal';
      modal.setAttribute('uk-modal', '');
      modal.setAttribute('aria-modal', 'true');
      modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">'+
        '<h3 class="uk-modal-title uk-text-center">Who I AM</h3>'+
        '<div id="login-qr" class="uk-margin" style="max-width:320px;margin:0 auto;width:100%"></div>'+
        '<button id="login-qr-stop" class="uk-button uk-button-primary uk-width-1-1 uk-margin-top">Abbrechen</button>'+
      '</div>';
      let scanner;
      let opener;
      const stopScanner = () => {
        if(scanner){
          scanner.stop().then(()=>scanner.clear()).catch(()=>{});
          scanner = null;
        }
      };
      const startScanner = () => {
        if(typeof Html5Qrcode === 'undefined'){
          document.getElementById('login-qr').textContent = 'QR-Scanner nicht verfügbar.';
          return;
        }
        scanner = new Html5Qrcode('login-qr');
        Html5Qrcode.getCameras().then(cams => {
          if(!cams || !cams.length){
            document.getElementById('login-qr').textContent = 'Keine Kamera gefunden.';
            return;
          }
          let cam = cams[0].id;
          const back = cams.find(c => /back|rear|environment/i.test(c.label));
          if(back) cam = back.id;
          scanner.start(cam, { fps:10, qrbox:250 }, text => {
            const name = text.trim();
            if(cfg.QRRestrict && allowed.indexOf(name) === -1){
              alert('Unbekanntes oder nicht berechtigtes Team/Person');
              return;
            }
            sessionStorage.setItem('quizUser', name);
            updateUserName();
            stopScanner();
            UIkit.modal(modal).hide();
            onDone();
          }).catch(err => {
            console.error('QR scanner start failed.', err);
            document.getElementById('login-qr').textContent = 'QR-Scanner konnte nicht gestartet werden.';
          });
        }).catch(err => {
          console.error('Camera list error.', err);
          document.getElementById('login-qr').textContent = 'Kamera konnte nicht initialisiert werden.';
        });
      };
      const stopBtn = modal.querySelector('#login-qr-stop');
      const trapFocus = (e) => {
        if(e.key === 'Tab'){
          e.preventDefault();
          stopBtn.focus();
        }
      };
      scanBtn.addEventListener('click', (e) => {
        opener = e.currentTarget;
        UIkit.modal(modal).show();
        startScanner();
      });
      UIkit.util.on(modal, 'shown', () => {
        stopBtn.focus();
        modal.addEventListener('keydown', trapFocus);
      });
      UIkit.util.on(modal, 'hidden', () => {
        stopScanner();
        modal.removeEventListener('keydown', trapFocus);
        if(opener){
          opener.focus();
        }
      });
      stopBtn.addEventListener('click', () => {
        UIkit.modal(modal).hide();
      });
      div.appendChild(scanBtn);
      if(bypass) div.appendChild(bypass);
      container.appendChild(modal);
      if(autoScan){
        UIkit.modal(modal).show();
        startScanner();
      }
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
        if(cfg.QRRestrict){
          alert('Nur Registrierung per QR-Code erlaubt');
          return;
        }
        sessionStorage.setItem('quizUser', generateUserName());
        updateUserName();
        onDone();
      });
      div.appendChild(btn);
    }
    container.appendChild(div);
  }

  async function init(){
    const cfg = window.quizConfig || {};
    if(cfg.QRRestrict){
      sessionStorage.removeItem('quizUser');
    }
    applyConfig();
    updateUserName();
    const catalogs = await loadCatalogList();
    let solved = new Set();
    if(cfg.competitionMode){
      try{
        const r = await fetch('/results.json', {headers:{'Accept':'application/json'}});
        if(r.ok){
          const data = await r.json();
          const user = sessionStorage.getItem('quizUser');
          data.forEach(entry => {
            if(entry.name === user){
              solved.add(entry.catalog);
            }
          });
        }
      }catch(e){
        console.warn('results not loaded', e);
      }
    }
    const params = new URLSearchParams(window.location.search);
    const id = params.get('katalog');
    const proceed = () => {
      const selected = catalogs.find(c => c.id === id);
      if(selected){
        loadQuestions(selected.id, selected.file);
      }else{
        showSelection(catalogs, solved);
      }
    };
    if((window.quizConfig || {}).QRUser){
      showLogin(proceed, !!id);
    }else{
      if(!sessionStorage.getItem('quizUser')){
        if(!cfg.QRRestrict){
          sessionStorage.setItem('quizUser', generateUserName());
        }
      }
      updateUserName();
      proceed();
    }
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  }else{
    init();
  }
})();
