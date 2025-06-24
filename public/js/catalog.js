/* global UIkit, Html5Qrcode, generateUserName */
// Lädt die verfügbaren Fragenkataloge und startet nach Auswahl das Quiz
(function(){
  function setStored(key, value){
    try{
      sessionStorage.setItem(key, value);
      localStorage.setItem(key, value);
    }catch(e){}
  }
  function getStored(key){
    return sessionStorage.getItem(key) || localStorage.getItem(key);
  }
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

  function setComment(text){
    const headerEl = document.getElementById('quiz-header');
    if(!headerEl) return;
    let block = headerEl.querySelector('div[data-role="catalog-comment-block"]');
    if(!block){
      block = document.createElement('div');
      block.dataset.role = 'catalog-comment-block';
      block.className = 'modern-info-card uk-card uk-card-default uk-card-body uk-box-shadow-medium uk-margin';
      block.style.whiteSpace = 'pre-wrap';
      headerEl.appendChild(block);
    }
    if(text){
      if(text.indexOf('<') !== -1){
        block.innerHTML = text;
      }else{
        block.textContent = text;
      }
      block.classList.remove('uk-hidden');
    }else{
      block.innerHTML = '';
      block.classList.add('uk-hidden');
    }
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
      setComment('');
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
    const user = getStored('quizUser');
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

  async function loadQuestions(id, file, letter, uid, name, desc, comment){
    setStored('quizCatalog', uid || id);
    sessionStorage.setItem('quizCatalogName', name || id);
    if(desc !== undefined){
      sessionStorage.setItem('quizCatalogDesc', desc);
    } else {
      sessionStorage.removeItem('quizCatalogDesc');
    }
    if(comment !== undefined){
      sessionStorage.setItem('quizCatalogComment', comment);
    } else {
      sessionStorage.removeItem('quizCatalogComment');
    }
    const headerEl = document.getElementById('quiz-header');
    if(headerEl){
      let title = headerEl.querySelector('h1');
      if(!title){
        title = document.createElement('h1');
        title.className = 'uk-margin-remove-bottom';
        headerEl.appendChild(title);
      }
      title.textContent = name || id;
    }
    setSubHeader(desc || '');
    setComment(comment || '');
    if(letter){
      const cfg = window.quizConfig || {};
      if(cfg.puzzleWordEnabled && letter){
        sessionStorage.setItem('quizLetter', letter);
      }else{
        sessionStorage.removeItem('quizLetter');
      }
    }
    try{
      const res = await fetch('/kataloge/' + file, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      window.quizQuestions = data;
      showCatalogIntro(data);
      return;
    }catch(e){
      console.warn('Fragen konnten nicht geladen werden, versuche inline Daten', e);
    }
    const inline = document.getElementById(id + '-data');
    if(inline){
      try{
        const data = JSON.parse(inline.textContent);
        window.quizQuestions = data;
        showCatalogIntro(data);
      }catch(err){
        console.error('Inline-Daten ungültig.', err);
      }
    }
  }

  function showCatalogIntro(data){
    const container = document.getElementById('quiz');
    if(!container) return;
    container.innerHTML = '';
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary uk-button-large uk-align-right';
    btn.textContent = 'Los geht es!';
    const cfg = window.quizConfig || {};
    if(cfg.buttonColor){
      btn.style.backgroundColor = cfg.buttonColor;
      btn.style.borderColor = cfg.buttonColor;
      btn.style.color = '#fff';
    }
    btn.addEventListener('click', () => {
      if(window.startQuiz){
        window.startQuiz(data, true);
      }
    });
    container.appendChild(btn);
  }

  function showCatalogSolvedModal(name, remaining){
    const msg = 'Der Katalog ' + name + ' wurde bereits abgeschlossen.' +
      (remaining ? '<br>Folgende Fragenkataloge fehlen noch: ' + remaining : '');
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">' +
      '<h3 class="uk-modal-title uk-text-center">Katalog bereits gespielt</h3>' +
      '<p class="uk-text-center">' + msg + '</p>' +
      '<button class="uk-button uk-button-primary uk-width-1-1 uk-margin-top">OK</button>' +
      '</div>';
    const btn = modal.querySelector('button');
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    btn.addEventListener('click', () => ui.hide());
    ui.show();
  }

  function showAllSolvedModal(){
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">' +
      '<h3 class="uk-modal-title uk-text-center">Alle Kataloge gespielt</h3>' +
      '<p class="uk-text-center">Herzlichen Glückwunsch, alle Kataloge wurden erfolgreich gespielt!</p>' +
      '<button class="uk-button uk-button-primary uk-width-1-1 uk-margin-top">Zur Auswertung wechseln</button>' +
      '</div>';
    const btn = modal.querySelector('button');
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    btn.addEventListener('click', () => { ui.hide(); window.location.href = "/summary"; });
    ui.show();
  }

  function showSelection(catalogs, solved){
    solved = solved || new Set();
    const container = document.getElementById('quiz');
    if(!container) return;
    container.innerHTML = '';
    const cfg = window.quizConfig || {};
    if(catalogs.length && solved.size === catalogs.length){
      showAllSolvedModal();
      return;
    }
    const params = new URLSearchParams(window.location.search);
    if(cfg.competitionMode && !params.get('katalog')){
      const p = document.createElement('p');
      p.textContent = 'Bitte QR-Code verwenden, um einen Fragenkatalog zu starten.';
      p.className = 'uk-text-center';
      container.appendChild(p);
      return;
    }


    const grid = document.createElement('div');
    grid.className = 'uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@m uk-grid-small uk-text-center';
    grid.setAttribute('uk-grid', '');
    catalogs.forEach(cat => {
      const cardWrap = document.createElement('div');
      const card = document.createElement('div');
      card.className = 'uk-card uk-card-default uk-card-body uk-card-hover';
      card.style.cursor = 'pointer';
      card.addEventListener('click', () => {
        const localSolved = new Set(JSON.parse(sessionStorage.getItem('quizSolved') || '[]'));
        if((window.quizConfig || {}).competitionMode && localSolved.has(cat.uid)){
          const remaining = catalogs.filter(c => !localSolved.has(c.uid)).map(c => c.name || c.slug || c.sort_order).join(', ');
          showCatalogSolvedModal(cat.name || cat.slug || cat.sort_order, remaining);
          return;
        }
        history.replaceState(null, '', '?katalog=' + (cat.slug || cat.sort_order));
        loadQuestions(cat.slug || cat.sort_order, cat.file, cat.raetsel_buchstabe, cat.uid, cat.name || cat.slug || cat.sort_order, cat.description || '', cat.comment || '');
      });
      const title = document.createElement('h3');
      title.textContent = cat.name || cat.slug || cat.sort_order;
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
    const params = new URLSearchParams(window.location.search);
    const hasCatalog = !!params.get('katalog');
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
    div.className = 'uk-text-center login-buttons uk-grid-small';
    div.setAttribute('uk-grid', '');
    if(cfg.QRUser){
      const scanBtn = document.createElement('button');
      scanBtn.className = 'uk-button uk-button-primary uk-width-1-1';
      scanBtn.textContent = 'Name mit QR-Code scannen';
      if(cfg.buttonColor){
        scanBtn.style.backgroundColor = cfg.buttonColor;
        scanBtn.style.borderColor = cfg.buttonColor;
        scanBtn.style.color = '#fff';
      }
      let bypass;
      if(!cfg.QRRestrict && !cfg.competitionMode){
        bypass = document.createElement('button');
        bypass.type = 'button';
        bypass.textContent = 'Kataloge anzeigen';
        bypass.className = 'uk-button uk-button-primary uk-width-1-1';
        if(cfg.buttonColor){
          bypass.style.backgroundColor = cfg.buttonColor;
          bypass.style.borderColor = cfg.buttonColor;
          bypass.style.color = '#fff';
        }
        bypass.addEventListener('click', () => {
          setStored('quizUser', generateUserName());
          sessionStorage.removeItem('quizSolved');
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
              setStored('quizUser', name);
              sessionStorage.removeItem('quizSolved');
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
      const scanWrap = document.createElement('div');
      scanWrap.className = bypass ? 'uk-width-1-2@s' : 'uk-width-1-1';
      scanWrap.appendChild(scanBtn);
      div.appendChild(scanWrap);
      if(bypass){
        const bypassWrap = document.createElement('div');
        bypassWrap.className = 'uk-width-1-2@s';
        bypassWrap.appendChild(bypass);
        div.appendChild(bypassWrap);
      }
      container.appendChild(modal);
      if(autoScan){
        UIkit.modal(modal).show();
        startScanner();
      }
    }else{
      if(!cfg.competitionMode || hasCatalog){
        const btn = document.createElement('button');
        btn.className = 'uk-button uk-button-primary uk-align-right';
        btn.textContent = 'Los geht es!';
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
          setStored('quizUser', generateUserName());
          sessionStorage.removeItem('quizSolved');
          updateUserName();
          onDone();
        });
        div.appendChild(btn);
      } else {
        const p = document.createElement('p');
        p.textContent = 'Bitte QR-Code verwenden, um das Quiz zu starten.';
        div.appendChild(p);
      }
    }
    container.appendChild(div);
  }

  async function buildSolvedSet(cfg){
    let solved = new Set(JSON.parse(sessionStorage.getItem('quizSolved') || '[]'));
    if(cfg.competitionMode){
      try{
        const r = await fetch('/results.json', {headers:{'Accept':'application/json'}});
        if(r.ok){
          const data = await r.json();
          const user = (sessionStorage.getItem('quizUser') || '') || (typeof localStorage !== 'undefined' ? localStorage.getItem('quizUser') : '');
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
    sessionStorage.setItem('quizSolved', JSON.stringify([...solved]));
    return solved;
  }

  async function init(){
    const cfg = window.quizConfig || {};
    if(cfg.QRRestrict){
      sessionStorage.removeItem('quizUser');
      sessionStorage.removeItem('quizSolved');
      localStorage.removeItem('quizUser');
    }
    ['quizUser','quizCatalog'].forEach(k => {
      const v = localStorage.getItem(k);
      if(v && !sessionStorage.getItem(k)){
        sessionStorage.setItem(k, v);
      }
    });
    applyConfig();
    updateUserName();
    const catalogs = await loadCatalogList();
    const params = new URLSearchParams(window.location.search);
    const id = params.get('katalog');
    const proceed = async () => {
      const solvedNow = await buildSolvedSet(cfg);
      const selected = catalogs.find(c => (c.slug || c.sort_order) === id);
      if(selected){
          if(cfg.competitionMode && solvedNow.has(selected.uid)){
            const remaining = catalogs.filter(c => !solvedNow.has(c.uid)).map(c => c.name || c.slug || c.sort_order).join(', ');
            if(catalogs.length && solvedNow.size === catalogs.length){
              showAllSolvedModal();
              return;
            } else {
              showCatalogSolvedModal(selected.name || selected.slug || selected.id, remaining);
              showSelection(catalogs, solvedNow);
              return;
            }
          }
        loadQuestions(selected.slug || selected.id, selected.file, selected.raetsel_buchstabe, selected.uid, selected.name || selected.slug || selected.id, selected.description || '', selected.comment || '');
      }else{
        showSelection(catalogs, solvedNow);
      }
    };
    if((window.quizConfig || {}).QRUser){
      showLogin(proceed, !!id);
    }else{
      if(!getStored('quizUser')){
          if(!cfg.QRRestrict){
            setStored('quizUser', generateUserName());
            sessionStorage.removeItem('quizSolved');
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
