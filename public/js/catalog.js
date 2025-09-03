/* global UIkit, Html5Qrcode, generateUserName */
const basePath = window.basePath || '';
const withBase = p => basePath + p;
// Hilfsfunktion, um nur eine Front- und eine Rückkamera zu behalten
window.filterCameraOrientations = window.filterCameraOrientations || function(cams){
  if(!Array.isArray(cams)) return [];
  const frontRegex = /front|user|face/i;
  const backRegex = /back|rear|environment/i;
  let frontCam;
  let backCam;
  cams.forEach(c => {
    if(!frontCam && frontRegex.test(c.label)) frontCam = c;
    if(!backCam && backRegex.test(c.label)) backCam = c;
  });
  const result = [];
  if(backCam) result.push(backCam);
  if(frontCam && frontCam !== backCam) result.push(frontCam);
  if(result.length === 0 && cams.length) result.push(cams[0]);
  return result;
};
// Lädt die verfügbaren Fragenkataloge und startet nach Auswahl das Quiz
(function(){
  const eventUid = (window.quizConfig || {}).event_uid || '';
  const playerNameKey = eventUid ? `qr_player_name:${eventUid}` : 'quizUser';
  function setStored(key, value){
    if(key === 'quizUser') key = playerNameKey;
    try{
      sessionStorage.setItem(key, value);
      localStorage.setItem(key, value);
    }catch(e){ /* empty */ }
  }
  function getStored(key){
    if(key === 'quizUser') key = playerNameKey;
    return sessionStorage.getItem(key) || localStorage.getItem(key);
  }
  function sanitize(text){
    const el = document.createElement('div');
    el.textContent = text == null ? '' : String(text);
    return el.textContent;
  }
  function createModal(title){
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    const dialog = document.createElement('div');
    dialog.className = 'uk-modal-dialog uk-modal-body';
    const h3 = document.createElement('h3');
    h3.className = 'uk-modal-title uk-text-center';
    h3.textContent = title;
    dialog.appendChild(h3);
    modal.appendChild(dialog);
    document.body.appendChild(modal);
    const ui = UIkit.modal(modal);
    UIkit.util.on(modal, 'hidden', () => { modal.remove(); });
    return {modal, dialog, ui};
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
      block.className = 'modern-info-card uk-card qr-card uk-card-body uk-box-shadow-medium uk-margin';
      block.style.whiteSpace = 'pre-wrap';
      headerEl.appendChild(block);
    }
    if(text){
      block.textContent = sanitize(text);
      block.classList.remove('uk-hidden');
    }else{
      block.textContent = '';
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
          img.src = withBase(cfg.logoPath) + '?' + Date.now();
          img.removeAttribute('srcset');
          img.removeAttribute('sizes');
          img.classList.remove('uk-hidden');
        }else{
          img.src = '';
          img.removeAttribute('srcset');
          img.removeAttribute('sizes');
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
    if(cfg.colors){
      if(cfg.colors.primary){
        document.documentElement.style.setProperty('--primary-color', cfg.colors.primary);
      }
      if(cfg.colors.accent){
        document.documentElement.style.setProperty('--accent-color', cfg.colors.accent);
      }
    }
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
      const res = await fetch(withBase('/kataloge/catalogs.json'), { headers: { 'Accept': 'application/json' } });
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
      const res = await fetch(withBase('/kataloge/' + file), { headers: { 'Accept': 'application/json' } });
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
    container.textContent = '';
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary uk-button-large uk-align-right';
    btn.textContent = 'Los geht es!';
    const cfg = window.quizConfig || {};
    if(cfg.colors && cfg.colors.accent){
      btn.style.backgroundColor = cfg.colors.accent;
      btn.style.borderColor = cfg.colors.accent;
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
    const {dialog, ui} = createModal('Katalog bereits gespielt');
    const p1 = document.createElement('p');
    p1.className = 'uk-text-center';
    p1.textContent = 'Der Katalog ' + sanitize(name) + ' wurde bereits abgeschlossen.';
    dialog.appendChild(p1);
    if(remaining){
      const p2 = document.createElement('p');
      p2.className = 'uk-text-center';
      p2.textContent = 'Folgende Fragenkataloge fehlen noch: ' + sanitize(remaining);
      dialog.appendChild(p2);
    }
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary uk-width-1-1 uk-margin-top';
    btn.textContent = 'OK';
    dialog.appendChild(btn);
    btn.addEventListener('click', () => ui.hide());
    ui.show();
  }

  function showAllSolvedModal(){
    const {dialog, ui} = createModal('Alle Kataloge gespielt');
    const p = document.createElement('p');
    p.className = 'uk-text-center';
    p.textContent = 'Herzlichen Glückwunsch, alle Kataloge wurden erfolgreich gespielt!';
    dialog.appendChild(p);
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary uk-width-1-1 uk-margin-top';
    btn.textContent = 'Zur Auswertung wechseln';
    dialog.appendChild(btn);
    btn.addEventListener('click', () => { ui.hide(); window.location.href = '/summary'; });
    ui.show();
  }

  function showSelection(catalogs, solved){
    solved = solved || new Set();
    const container = document.getElementById('quiz');
    if(!container) return;
    container.textContent = '';
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
      card.className = 'uk-card qr-card uk-card-body uk-card-hover';
      card.style.cursor = 'pointer';
      card.addEventListener('click', () => {
        const localSolved = new Set(JSON.parse(sessionStorage.getItem('quizSolved') || '[]'));
        if((window.quizConfig || {}).competitionMode && localSolved.has(cat.uid)){
          const remaining = catalogs.filter(c => !localSolved.has(c.uid)).map(c => c.name || c.slug || c.sort_order).join(', ');
          showCatalogSolvedModal(cat.name || cat.slug || cat.sort_order, remaining);
          return;
        }
        let qs = '?katalog=' + (cat.slug || cat.sort_order);
        if(eventUid) qs += '&event=' + encodeURIComponent(eventUid);
        history.replaceState(null, '', qs);
        loadQuestions(
          cat.slug || cat.sort_order,
          cat.file,
          cat.raetsel_buchstabe,
          cat.uid,
          cat.name || cat.slug || cat.sort_order,
          cat.description || cat.beschreibung || '',
          cat.comment || cat.kommentar || ''
        );
      });
      const title = document.createElement('h3');
      title.textContent = cat.name || cat.slug || cat.sort_order;
      const desc = document.createElement('p');
      desc.textContent = cat.description || cat.beschreibung || '';
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
        if(!Array.isArray(allowed) || !allowed.length){
          const r = await fetch(withBase('/teams.json'), {headers:{'Accept':'application/json'}});
          if(r.ok){
            allowed = await r.json();
          } else {
            allowed = [];
          }
        }
        allowed = allowed.map(t => String(t).toLowerCase());
        sessionStorage.setItem('allowedTeams', JSON.stringify(allowed));
      }catch(e){
        allowed = [];
      }
    }
    const container = document.getElementById('quiz');
    if(!container) return;
    container.textContent = '';
    const div = document.createElement('div');
    div.className = 'uk-text-center login-buttons uk-grid-small';
    div.setAttribute('uk-grid', '');
    if(cfg.QRUser){
      const scanBtn = document.createElement('button');
      scanBtn.className = 'uk-button uk-button-primary uk-width-1-1';
      scanBtn.textContent = 'Name mit QR-Code scannen';
      if(cfg.colors && cfg.colors.accent){
        scanBtn.style.backgroundColor = cfg.colors.accent;
        scanBtn.style.borderColor = cfg.colors.accent;
        scanBtn.style.color = '#fff';
      }
      let bypass;
      if(!cfg.QRRestrict && !cfg.competitionMode){
        bypass = document.createElement('button');
        bypass.type = 'button';
        bypass.textContent = 'Kataloge anzeigen';
        bypass.className = 'uk-button uk-button-primary uk-width-1-1';
        if(cfg.colors && cfg.colors.accent){
          bypass.style.backgroundColor = cfg.colors.accent;
          bypass.style.borderColor = cfg.colors.accent;
          bypass.style.color = '#fff';
        }
        bypass.addEventListener('click', () => {
          setStored('quizUser', generateUserName());
          sessionStorage.removeItem('quizSolved');
          updateUserName();
          onDone();
        });
      }
      const {modal, dialog, ui} = createModal('Team-Check-in');
      modal.id = 'qr-modal';
      const qrDiv = document.createElement('div');
      qrDiv.id = 'login-qr';
      qrDiv.className = 'uk-margin';
      qrDiv.style.maxWidth = '320px';
      qrDiv.style.margin = '0 auto';
      qrDiv.style.width = '100%';
      const flipBtn = document.createElement('button');
      flipBtn.id = 'login-qr-flip';
      flipBtn.className = 'uk-button uk-button-default uk-width-1-1';
      flipBtn.textContent = 'Kamera wechseln';
      flipBtn.disabled = true;
      const stopBtn = document.createElement('button');
      stopBtn.id = 'login-qr-stop';
      stopBtn.className = 'uk-button uk-button-primary uk-width-1-1 uk-margin-top';
      stopBtn.textContent = 'Abbrechen';
      dialog.appendChild(qrDiv);
      dialog.appendChild(flipBtn);
      dialog.appendChild(stopBtn);
      let scanner;
      let opener;
      let cameras = [];
      let camIndex = 0;
      const stopScanner = () => {
        if(scanner){
          scanner.stop().then(()=>scanner.clear()).catch(()=>{});
          scanner = null;
        }
        flipBtn.disabled = true;
      };
      const startCamera = async () => {
        const camId = cameras[camIndex].id;
        flipBtn.disabled = true;
        try{
          await scanner.start(camId, { fps:10, qrbox:250 }, text => {
            const name = sanitize(text.trim());
            if(cfg.QRRestrict && allowed.indexOf(name.toLowerCase()) === -1){
              alert('Unbekanntes oder nicht berechtigtes Team/Person');
              return;
            }
            setStored('quizUser', name);
            sessionStorage.removeItem('quizSolved');
            updateUserName();
            stopScanner();
            ui.hide();
            onDone();
          });
        }catch(err){
          console.error('QR scanner start failed.', err);
          qrDiv.textContent = 'QR-Scanner konnte nicht gestartet werden.';
          showManualInput();
        }
        flipBtn.disabled = cameras.length < 2;
      };
      const startScanner = async () => {
        if(typeof Html5Qrcode === 'undefined'){
          qrDiv.textContent = 'QR-Scanner nicht verfügbar.';
          showManualInput();
          return;
        }
        scanner = new Html5Qrcode('login-qr');
        flipBtn.disabled = true;
        try{
          const cams = await Html5Qrcode.getCameras();
          if(!cams || !cams.length){
            qrDiv.textContent = 'Keine Kamera gefunden.';
            return;
          }
          cameras = filterCameraOrientations(cams);
          flipBtn.disabled = cameras.length < 2;
          camIndex = 0;
          const backIdx = cameras.findIndex(c => /back|rear|environment/i.test(c.label));
          if(backIdx >= 0) camIndex = backIdx;
          await startCamera();
        }catch(err){
          console.error('Camera list error.', err);
          qrDiv.textContent = 'Kamera konnte nicht initialisiert werden. Bitte erlaube den Kamerazugriff im Browser oder in den Geräteeinstellungen. Lade die Seite danach neu.';
          showManualInput();
        }
      };
      flipBtn.disabled = true;
      function showManualInput(){
        qrDiv.textContent = '';
        const input = document.createElement('input');
        input.id = 'manual-team-name';
        input.className = 'uk-input';
        input.type = 'text';
        input.placeholder = 'Teamname eingeben';
        const submit = document.createElement('button');
        submit.id = 'manual-team-submit';
        submit.className = 'uk-button uk-button-primary uk-width-1-1 uk-margin-top';
        submit.textContent = 'Weiter';
        qrDiv.appendChild(input);
        qrDiv.appendChild(submit);
        flipBtn.classList.add('uk-hidden');
        submit.addEventListener('click', () => {
          const name = sanitize((input.value || '').trim());
          if(!name) return;
          if(cfg.QRRestrict && allowed.indexOf(name.toLowerCase()) === -1){
            alert('Unbekanntes oder nicht berechtigtes Team/Person');
            return;
          }
          setStored('quizUser', name);
          stopScanner();
          ui.hide();
          onDone();
        });
        input.addEventListener('keydown', (ev) => {
          if(ev.key === 'Enter'){
            ev.preventDefault();
            submit.click();
          }
        });
        input.focus();
      }
      const trapFocus = (e) => {
        if(e.key === 'Tab'){
          e.preventDefault();
          stopBtn.focus();
        }
      };
      flipBtn.addEventListener('click', async () => {
        if(!scanner || cameras.length < 2) return;
        flipBtn.disabled = true;
        try{
          await scanner.stop();
          await scanner.clear();
          await new Promise(r => setTimeout(r, 100));
          scanner = new Html5Qrcode('login-qr');
        }catch(e){
          console.warn('Fehler beim Stoppen oder Clearen:', e);
        }
        camIndex = (camIndex + 1) % cameras.length;
        await startCamera();
      });
      scanBtn.addEventListener('click', async (e) => {
        opener = e.currentTarget;
        ui.show();
        await startScanner();
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
        ui.hide();
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
      if(autoScan){
        ui.show();
        await startScanner();
      }
    }else{
      if(!cfg.competitionMode || hasCatalog){
        const btn = document.createElement('button');
        btn.className = 'uk-button uk-button-primary uk-align-right';
        btn.textContent = 'Los geht es!';
        if(cfg.colors && cfg.colors.accent){
          btn.style.backgroundColor = cfg.colors.accent;
          btn.style.borderColor = cfg.colors.accent;
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
        const r = await fetch(withBase('/results.json'), {headers:{'Accept':'application/json'}});
        if(r.ok){
          const data = await r.json();
          const key = typeof playerNameKey !== 'undefined' ? playerNameKey : 'quizUser';
          const user = (sessionStorage.getItem(key) || '') || (typeof localStorage !== 'undefined' ? localStorage.getItem(key) : '');
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
    if(cfg.QRRestrict && !cfg.QRRemember){
      // Ohne Namensübernahme gespeicherte Daten löschen
      sessionStorage.removeItem(playerNameKey);
      sessionStorage.removeItem('quizSolved');
      // localStorage.removeItem(playerNameKey);
    }
    [playerNameKey, 'quizCatalog', 'quizSolved'].forEach(k => {
      const v = localStorage.getItem(k);
      if(v && !sessionStorage.getItem(k)){
        sessionStorage.setItem(k, v);
      }
    });
    updateUserName();
    applyConfig();
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
            }
            showCatalogSolvedModal(selected.name || selected.slug || selected.id, remaining);
            return;
          }
        loadQuestions(
          selected.slug || selected.id,
          selected.file,
          selected.raetsel_buchstabe,
          selected.uid,
          selected.name || selected.slug || selected.id,
          selected.description || selected.beschreibung || '',
          selected.comment || selected.kommentar || ''
        );
      }else{
        showSelection(catalogs, solvedNow);
      }
    };
    if((window.quizConfig || {}).QRUser){
      if(getStored('quizUser')){
        updateUserName();
        proceed();
      }else{
        showLogin(proceed, !!id);
      }
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
