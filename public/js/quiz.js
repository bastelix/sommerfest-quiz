/* global UIkit, Html5Qrcode, generateUserName */
// Hauptskript des Quizzes. Dieses File erzeugt dynamisch alle Fragen,
// wertet Antworten aus und speichert das Ergebnis im Browser.
// Der Code wird ausgeführt, sobald das DOM geladen ist.
// Utility zum Generieren zufälliger Nutzernamen
(function(){
  const melodicNames = [
    'Sonnenklang', 'Mondmelodie', 'Sturmserenade', 'Himmelsklang', 'Seewindlied', 'Sternenchor',
    'Fliederduft', 'Traumtänzer', 'Herbstleuchten', 'Sommernacht', 'Funkelpfad', 'Lichtklang',
    'Wolkenflug', 'Morgenröte', 'Nebelmut', 'Blütenzauber', 'Schattenklang', 'Seelenruh',
    'Friedenshauch', 'Kristallschein', 'Sternquelle', 'Friedentropfen', 'Kometflug', 'Sommersanft',
    'Lichtersanft', 'Birkenflug', 'Frostkraft', 'Herbstkraft', 'Feuerkraft', 'Birkenquelle',
    'Fernenregen', 'Sternsonne', 'Abendrauschen', 'Talerfunken', 'Fernenmond', 'Meeresfunken',
    'Winterstille', 'Liedlicht', 'Seelenfeuer', 'Sturmfeuer', 'Fernenstern', 'Auenkraft',
    'Flügelrauschen', 'Fichtenglut', 'Sonnenregen', 'Melodieruf', 'Meereswelle', 'Flusssegen',
    'Tanzregen', 'Frostecho', 'Dufttraum', 'Silberstreif', 'Regentau', 'Sonnenwelle',
    'Sternmond', 'Abendmorgen', 'Abendschimmer', 'Winterlicht', 'Blütenkristall', 'Zauberseele',
    'Sonnenherz', 'Brunnenwind', 'Zauberflug', 'Herbstwelle', 'Duftsegen', 'Sonnenlicht',
    'Friedenstille', 'Sturmhauch', 'Feuerstreif', 'Frostlied', 'Wolkenkraft', 'Sommerlicht',
    'Goldwelle', 'Windtraum', 'Fliederwind', 'Liedklang', 'Sturmsegen', 'Silbertanz',
    'Fichtenruf', 'Seelenstreif', 'Flügeltropfen', 'Aromasegen', 'Fernenflug', 'Kometglanz',
    'Kristallmut', 'Silberfeuer', 'Traumstern', 'Fliedertöne', 'Liedtanz', 'Wiesenstille',
    'Wandersanft', 'Eichenglanz', 'Friedensegen', 'Frühlingswelle', 'Fliederfunken', 'Leuchtkraft',
    'Herbstklang', 'Blütensegen', 'Sturmklang', 'Brunnenglanz', 'Wolkenfeder', 'Duftstille',
    'Silbertropfen', 'Glanzlicht', 'Flügellicht', 'Glanzwind', 'Herbstfeuer', 'Flügelkristall',
    'Sonnenkristall', 'Morgensegen', 'Schattentöne', 'Brunnenreigen', 'Herbstreigen', 'Sternzeit',
    'Seelenzauber', 'Auenregen', 'Fichtenwind', 'Eichenflug', 'Schattensonne', 'Birkensegen',
    'Feuertraum', 'Seelenkraft', 'Duftpfad', 'Silberruf', 'Traumklänge', 'Sturmreigen',
    'Regenfeder', 'Tanzkraft', 'Lichtregen', 'Frühlingsreigen', 'Windzeit', 'Nebelseele',
    'Aromapfad', 'Meerestau', 'Klangherz', 'Sonnenfeuer', 'Eichenglut', 'Windpfad',
    'Fliedertropfen', 'Glückmut', 'Kometstrahl', 'Meereswind', 'Brunnentau', 'Wolkenmorgen',
    'Talerklänge', 'Elfenruf', 'Fichtensonne', 'Sternklang', 'Elfenlicht', 'Goldflug',
    'Liedzauber', 'Flusstraum', 'Sonnenzeit', 'Liedquelle', 'Klanglicht', 'Goldecho',
    'Duftzauber', 'Sternkristall', 'Frostflug', 'Friedenlicht', 'Winterregen', 'Sommerreigen',
    'Traumreigen', 'Seelenherz', 'Sternflug', 'Regenrauschen', 'Sternsegen', 'Glücktraum',
    'Regenglanz', 'Wolkenmut', 'Sonnenglut', 'Flügelmorgen', 'Brunnenpfad', 'Drachenstern',
    'Glückwelle', 'Fernenfeder', 'Glitzerlicht', 'Wiesenflug', 'Kristallmond', 'Regenlicht',
    'Blütenwind', 'Zaubersegen', 'Kometlicht', 'Brunnenlicht', 'Seelenflug', 'Kristallzauber',
    'Brunnentraum', 'Blütenzeit', 'Blütenherz', 'Melodiestille', 'Nebelflug', 'Aromatau',
    'Lichtzauber', 'Kometstille', 'Lichterwelle', 'Mondglanz', 'Schattentropfen', 'Elfenquelle',
    'Sturmstrahl', 'Traumkristall', 'Fliederstern', 'Glückhauch', 'Traumherz', 'Winterflug',
    'Tanztraum', 'Birkenlicht', 'Duftkraft', 'Lichterrauschen', 'Wiesenstrahl', 'Sterntöne',
    'Morgenherz', 'Glanzmorgen', 'Klangtanz', 'Talerecho', 'Klangwelle', 'Frühlingsmond',
    'Meeresreigen', 'Lichtglanz', 'Wintersegen', 'Feuerschimmer'
  ];

  window.generateUserName = function(){
    const used = JSON.parse(localStorage.getItem('usedNames') || '[]');
    const available = melodicNames.filter(n => !used.includes(n));
    let name;
    if(available.length){
      name = available[Math.floor(Math.random() * available.length)];
      used.push(name);
      localStorage.setItem('usedNames', JSON.stringify(used));
    }else{
      name = 'Gast-' + Math.random().toString(36).substr(2,5);
    }
    return name;
  };
})();

function setStored(key, value){
  try{
    sessionStorage.setItem(key, value);
    localStorage.setItem(key, value);
  }catch(e){}
}
function getStored(key){
  return sessionStorage.getItem(key) || localStorage.getItem(key);
}

function formatPuzzleTime(ts){
  const d = new Date(ts * 1000);
  const pad = n => n.toString().padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

async function fetchLatestPuzzleEntry(name, catalog){
  try{
    const list = await fetch('/results.json').then(r => r.json());
    if(Array.isArray(list)){
      return list.slice().reverse().find(e => e.name === name && e.catalog === catalog && e.puzzleTime);
    }
  }catch(e){
    return null;
  }
  return null;
}

window.formatPuzzleTime = formatPuzzleTime;
window.fetchLatestPuzzleEntry = fetchLatestPuzzleEntry;

function runQuiz(questions, skipIntro){
  // Konfiguration laden und einstellen, ob der "Antwort prüfen"-Button
  // eingeblendet werden soll
  const cfg = window.quizConfig || {};
  const showCheck = cfg.CheckAnswerButton !== 'no';
  if(cfg.backgroundColor){
    document.body.style.backgroundColor = cfg.backgroundColor;
  }

  const container = document.getElementById('quiz');
  const progress = document.getElementById('progress');
  const announcer = document.getElementById('question-announcer');
  // Vorhandene Inhalte entfernen (z.B. Katalogauswahl)
  if (container) container.innerHTML = '';


  // Hilfsfunktion zum Mischen von Arrays (Fisher-Yates)
  function shuffleArray(arr){
    const a = arr.slice();
    for(let i = a.length - 1; i > 0; i--){
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  // Fragen mischen, damit die Reihenfolge bei jedem Aufruf variiert
  const shuffled = shuffleArray(questions);
  const questionCount = shuffled.length;

  let current = skipIntro ? 1 : 0;
  // Zu jedem Eintrag im Array ein DOM-Element erzeugen
  const elements = [createStart()].concat(shuffled.map((q, idx) => createQuestion(q, idx)));
  // Speichert true/false für jede beantwortete Frage
  const results = new Array(questionCount).fill(false);
  const summaryEl = createSummary(); // Abschlussseite
  elements.push(summaryEl);
  let summaryShown = false;

  ['quizUser','quizCatalog'].forEach(k => {
    const v = localStorage.getItem(k);
    if(v && !sessionStorage.getItem(k)){
      sessionStorage.setItem(k, v);
    }
  });

  if(!getStored('quizUser')){
    if(!cfg.QRRestrict){
      setStored('quizUser', generateUserName());
    }
  }

  // konfigurierbare Farben dynamisch in ein Style-Tag schreiben
  const styleEl = document.createElement('style');
  styleEl.textContent = `\n    body { background-color: ${cfg.backgroundColor || '#ffffff'}; }\n    .uk-button-primary { background-color: ${cfg.buttonColor || '#1e87f0'}; border-color: ${cfg.buttonColor || '#1e87f0'}; }\n  `;
  document.head.appendChild(styleEl);

  const headerEl = document.getElementById('quiz-header');

  if(skipIntro && headerEl){
    headerEl.innerHTML = '';
    headerEl.classList.add('uk-hidden');
  }

  elements.forEach((el, i) => {
    if (i !== current) el.classList.add('uk-hidden');
    container.appendChild(el);
    if (typeof UIkit !== 'undefined' && UIkit.scrollspy) {
      UIkit.scrollspy(el);
    }
  });
  progress.max = questionCount;
  showQuestion(current);


  // Zeigt das Element mit dem angegebenen Index an und aktualisiert den Fortschrittsbalken
  function showQuestion(i){
    elements.forEach((el, idx) => el.classList.toggle('uk-hidden', idx !== i));
    if(i === 0){
      progress.classList.add('uk-hidden');
      progress.setAttribute('aria-valuenow', 0);
      if (announcer) announcer.textContent = '';
    } else if(i <= questionCount && i > 0){
      // Fragen anzeigen und Fortschritt aktualisieren
      progress.classList.remove('uk-hidden');
      progress.value = i;
      progress.setAttribute('aria-valuenow', i);
      if (announcer) announcer.textContent = `Frage ${i} von ${questionCount}`;
    } else if(i === questionCount + 1){
      // Nach der letzten Frage Zusammenfassung anzeigen
      progress.value = questionCount;
      progress.setAttribute('aria-valuenow', questionCount);
      if (announcer) announcer.textContent = `Frage ${questionCount} von ${questionCount}`;
      progress.classList.add('uk-hidden');
      updateSummary();
    }
  }

  // Blendet die nächste Frage ein
  function next(){
    if(current === 0 && headerEl){
      headerEl.innerHTML = '';
      headerEl.classList.add('uk-hidden');
    }
    if(current < questionCount + 1){
      current++;
      showQuestion(current);
    }
  }

  // Wendet die konfigurierte Buttonfarbe an
  function styleButton(btn){
    if(cfg.buttonColor){
      btn.style.backgroundColor = cfg.buttonColor;
      btn.style.borderColor = cfg.buttonColor;
      btn.style.color = '#fff';
    }
  }

  // Ermittelt das Ergebnis und schreibt es in localStorage
  function updateSummary(){
    if(summaryShown) return;
    summaryShown = true;
    const score = results.filter(r => r).length;
    let user = getStored('quizUser');
    if(!user && !cfg.QRRestrict){
      user = generateUserName();
      setStored('quizUser', user);
    }
    const p = summaryEl.querySelector('p');
    if(p) p.textContent = `${user} hat ${score} von ${questionCount} Punkten erreicht.`;
    const heading = summaryEl.querySelector('h3');
    if(heading) heading.textContent = `🎉 Danke für die Teilnahme ${user}!`;
    const letter = cfg.puzzleWordEnabled ? sessionStorage.getItem('quizLetter') : null;
    const letterEl = summaryEl.querySelector('#quiz-letter');
    if(letterEl){
      if(letter){
        letterEl.textContent = letter;
        letterEl.style.display = 'block';
      }else{
        letterEl.style.display = 'none';
      }
    }
    if(score === questionCount && typeof window.startConfetti === 'function'){
      window.startConfetti();
    }
    const catalog = getStored('quizCatalog') || 'unknown';
    const wrong = results.map((r,i)=> r ? null : i+1).filter(v=>v!==null);
    const data = { name: user, catalog, correct: score, total: questionCount, wrong };
    const puzzleSolved = sessionStorage.getItem('puzzleSolved') === 'true';
    const puzzleTs = sessionStorage.getItem('puzzleTime');
    if(puzzleSolved && puzzleTs){
      data.puzzleTime = parseInt(puzzleTs, 10) || Math.floor(Date.now()/1000);
    }
    fetch('/results', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }).catch(()=>{});
    const solved = JSON.parse(sessionStorage.getItem('quizSolved') || '[]');
    if(solved.indexOf(catalog) === -1){
      solved.push(catalog);
      sessionStorage.setItem('quizSolved', JSON.stringify(solved));
    }

    if(cfg.teamResults){
      let total = null;
      const dataEl = document.getElementById('catalogs-data');
      if(dataEl){
        try{
          const list = JSON.parse(dataEl.textContent);
          total = Array.isArray(list) ? list.length : null;
        }catch(e){
          total = null;
        }
      }
      if(total !== null && solved.length === total){
        const link = document.createElement('a');
        link.href = '/summary';
        link.className = 'uk-button uk-button-primary uk-margin-top';
        link.textContent = 'Ergebnisübersicht';
        styleButton(link);
        summaryEl.appendChild(link);
      }
    }

    if(cfg.puzzleWordEnabled){
      const attemptKey = 'puzzleAttempt-' + catalog;
      const puzzleSolved = sessionStorage.getItem('puzzleSolved') === 'true';
      const puzzleInfo = summaryEl.querySelector('#puzzle-info');
      if(puzzleSolved && puzzleInfo){
        const ts = parseInt(sessionStorage.getItem('puzzleTime') || '0', 10);
        if(ts){
          puzzleInfo.textContent = `Rätselwort gelöst: ${formatPuzzleTime(ts)}`;
        }
      }
      if(!puzzleSolved && !sessionStorage.getItem(attemptKey)){
        const puzzleBtn = document.createElement('button');
        puzzleBtn.className = 'uk-button uk-button-primary uk-margin-top';
        puzzleBtn.textContent = 'Rätselwort überprüfen';
        styleButton(puzzleBtn);
        puzzleBtn.addEventListener('click', () => showPuzzleCheck(puzzleBtn, attemptKey));
        summaryEl.appendChild(puzzleBtn);
      }
    }

    const remainingEl = summaryEl.querySelector('#quiz-remaining');
    if(remainingEl){
      try{
        const dataEl = document.getElementById('catalogs-data');
        const catalogs = dataEl ? JSON.parse(dataEl.textContent) : [];
        const solvedSet = new Set(JSON.parse(sessionStorage.getItem('quizSolved') || '[]'));
        const names = catalogs.filter(c => !solvedSet.has(c.uid || c.slug || c.sort_order))
          .map(c => c.name || c.slug || c.sort_order);
        if(names.length){
          remainingEl.textContent = 'Auf zur nächsten Station. Es fehlen noch: ' + names.join(', ');
        } else {
          remainingEl.textContent = '';
        }
      }catch(e){
        remainingEl.textContent = '';
      }
    }

    if(cfg.photoUpload !== false){
      const photoBtn = document.createElement('button');
      photoBtn.className = 'uk-button uk-button-primary uk-margin-top';
      photoBtn.textContent = 'Beweisfoto einreichen';
      styleButton(photoBtn);
      photoBtn.addEventListener('click', () => {
        const name = getStored('quizUser') || '';
        const catalogName = getStored('quizCatalog') || 'unknown';
        showPhotoModal(name, catalogName);
      });
      summaryEl.appendChild(photoBtn);
    }
  }

  // Wählt basierend auf dem Fragetyp die passende Erzeugerfunktion aus
  function createQuestion(q, idx){
    if(q.type === 'sort') return createSortQuestion(q, idx);
    if(q.type === 'assign') return createAssignQuestion(q, idx);
    if(q.type === 'mc') return createMcQuestion(q, idx);
    if(q.type === 'swipe') return createSwipeQuestion(q, idx);
    return document.createElement('div');
  }

  // Erstellt das DOM für eine Sortierfrage
  function createSortQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const h = document.createElement('h4');
    h.textContent = q.prompt;
    div.appendChild(h);
    const instr = document.createElement('p');
    instr.id = 'sort-desc-' + idx;
    instr.className = 'uk-hidden-visually';
    instr.textContent = 'Mit Pfeil nach oben oder unten verschiebst du den aktuellen Eintrag.'; // oder die andere Formulierung
    div.appendChild(instr);
    const ul = document.createElement('ul');
    ul.className = 'uk-list uk-list-divider sortable-list uk-margin';
    ul.setAttribute('aria-dropeffect', 'move');
    ul.setAttribute('aria-label', 'Sortierbare Liste');
    ul.setAttribute('aria-describedby', instr.id);
    const displayItems = shuffleArray(q.items);
    displayItems.forEach(text => {
      const li = document.createElement('li');
      li.draggable = true;
      li.setAttribute('role','listitem');
      li.tabIndex = 0;
      li.setAttribute('aria-grabbed','false');
      li.textContent = text;
      ul.appendChild(li);
    });
    div.appendChild(ul);
    const feedback = document.createElement('div');
    feedback.className = 'uk-margin-top';
    feedback.setAttribute('role', 'alert');
    feedback.setAttribute('aria-live', 'polite');
    const footer = document.createElement('div');
    footer.className = 'uk-margin-top uk-flex uk-flex-between';
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary';
    btn.textContent = 'Antwort prüfen';
    styleButton(btn);
    btn.addEventListener('click', () => checkSort(ul, q.items, feedback, idx));
    if(!showCheck) btn.classList.add('uk-hidden');
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button';
    nextBtn.textContent = 'Weiter';
    styleButton(nextBtn);
    nextBtn.addEventListener('click', () => {
      checkSort(ul, q.items, feedback, idx);
      next();
    });
    footer.appendChild(btn);
    footer.appendChild(nextBtn);
    div.appendChild(feedback);
    div.appendChild(footer);
    // Drag-&-Drop-Handler aktivieren
    setupSortHandlers(ul);
    return div;
  }

  // Drag-&-Drop sowie Tastaturnavigation für Sortierlisten
  function setupSortHandlers(ul){
    if (typeof Sortable !== 'undefined') {
      Sortable.create(ul, { animation: 150 });
    }
    ul.querySelectorAll('li').forEach(li => {
      li.addEventListener('keydown', e => {
        if(e.key === 'ArrowUp' && li.previousElementSibling){
          li.parentNode.insertBefore(li, li.previousElementSibling);
          li.focus();
        }else if(e.key === 'ArrowDown' && li.nextElementSibling){
          li.parentNode.insertBefore(li.nextElementSibling, li);
          li.focus();
        }
      });
    });
  }

  // Prüft die Reihenfolge der Sortierfrage
  function checkSort(ul, right, feedback, idx){
    const currentOrder = Array.from(ul.querySelectorAll('li')).map(li => li.textContent.trim());
    const correct = JSON.stringify(currentOrder) === JSON.stringify(right);
    results[idx] = correct;
    feedback.innerHTML =
      correct
        ? '<div class="uk-alert-success" uk-alert><span class="uk-hidden-visually">Richtige Antwort</span> ✅ Richtig sortiert!</div>'
        : '<div class="uk-alert-danger" uk-alert><span class="uk-hidden-visually">Falsche Antwort</span> ❌ Leider falsch, versuche es nochmal!</div>';
  }

  // Erstellt das DOM für eine Zuordnungsfrage
  // Links werden die Begriffe gelistet, rechts die Dropzones für die Definitionen
  function createAssignQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const h = document.createElement('h4');
    h.textContent = q.prompt;
    div.appendChild(h);
    const assignDesc = document.createElement('p');
    assignDesc.id = 'assign-desc-' + idx;
    assignDesc.className = 'uk-hidden-visually';
    assignDesc.textContent = 'Mit Pfeil nach oben oder unten kannst du Begriffe innerhalb der Liste verschieben.';
    div.appendChild(assignDesc);

    const grid = document.createElement('div');
    grid.className = 'uk-grid-small uk-child-width-1-2';
    grid.setAttribute('uk-grid','');
    div.appendChild(grid);

    const left = document.createElement('div');
    const termList = document.createElement('ul');
    termList.className = 'uk-list uk-list-striped terms';
    termList.setAttribute('aria-describedby', assignDesc.id);
    const leftTerms = shuffleArray(q.terms);
    div._initialLeftTerms = leftTerms.slice();
    leftTerms.forEach(t => {
      const li = document.createElement('li');
      li.draggable = true;
      li.setAttribute('role','listitem');
      li.tabIndex = 0;
      li.setAttribute('aria-grabbed','false');
      li.dataset.term = t.term;
      li.textContent = t.term;
      termList.appendChild(li);
    });
    left.appendChild(termList);
    grid.appendChild(left);

    const rightCol = document.createElement('div');
    const rightTerms = shuffleArray(q.terms);
    rightTerms.forEach(t => {
      const dz = document.createElement('div');
      dz.className = 'dropzone';
      dz.setAttribute('role','listitem');
      dz.tabIndex = 0;
      dz.setAttribute('aria-dropeffect', 'move');
      dz.dataset.term = t.term;
      dz.dataset.definition = t.definition;
      dz.setAttribute('aria-label', 'Dropzone f\u00fcr ' + t.definition);
      dz.setAttribute('aria-dropeffect', 'move');
      dz.textContent = t.definition;
      rightCol.appendChild(dz);
    });
    grid.appendChild(rightCol);

    const feedback = document.createElement('div');
    feedback.className = 'uk-margin-top';
    feedback.setAttribute('role', 'alert');
    feedback.setAttribute('aria-live', 'polite');
    const footer = document.createElement('div');
    footer.className = 'uk-margin-top uk-flex uk-flex-between';
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary';
    btn.textContent = 'Antwort prüfen';
    styleButton(btn);
    btn.addEventListener('click', () => checkAssign(div, feedback, idx));
    if(!showCheck) btn.classList.add('uk-hidden');
    const resetBtn = document.createElement('button');
    resetBtn.className = 'uk-button';
    resetBtn.textContent = 'Zurücksetzen';
    styleButton(resetBtn);
    resetBtn.addEventListener('click', () => resetAssign(div, feedback));
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button';
    nextBtn.textContent = 'Weiter';
    styleButton(nextBtn);
    nextBtn.addEventListener('click', () => {
      checkAssign(div, feedback, idx);
      next();
    });
    footer.appendChild(btn);
    footer.appendChild(resetBtn);
    footer.appendChild(nextBtn);
    div.appendChild(feedback);
    div.appendChild(footer);

    setupAssignHandlers(div);
    return div;
  }

  // Initialisiert Drag-&-Drop und Tastatursteuerung für die Zuordnungsfrage
  function setupAssignHandlers(div){
    const list = div.querySelector('.terms');
    const group = 'assign-' + Math.random().toString(36).substr(2,5);

    if (typeof Sortable !== 'undefined') {
      Sortable.create(list, {
        group: { name: group, pull: true, put: false },
        sort: false,
        animation: 150,
        onStart: evt => evt.item.setAttribute('aria-grabbed','true'),
        onEnd: evt => evt.item.setAttribute('aria-grabbed','false')
      });

      div.querySelectorAll('.dropzone').forEach(zone => {
        Sortable.create(zone, {
          group: { name: group, pull: false, put: true },
          sort: false,
          animation: 150,
            onAdd: evt => {
            const item = evt.item;
            zone.textContent = zone.dataset.definition + ' \u2013 ' + item.textContent;
            zone.dataset.dropped = item.dataset.term;
            item.remove();
          }
        });
      });
    }

    div._selectedTerm = null;
    div.querySelectorAll('.terms li').forEach(li => {
      li.addEventListener('keydown', e => {
        if(e.key === 'Enter' || e.key === ' '){
          div._selectedTerm = li;
          li.setAttribute('aria-grabbed','true');
          e.preventDefault();
        }
      });
    });
    div.querySelectorAll('.dropzone').forEach(zone => {
      zone.addEventListener('keydown', e => {
        if((e.key === 'Enter' || e.key === ' ') && div._selectedTerm){
          zone.textContent = zone.dataset.definition + ' \u2013 ' + div._selectedTerm.textContent;
          zone.dataset.dropped = div._selectedTerm.dataset.term;
          div._selectedTerm.style.visibility = 'hidden';
          div._selectedTerm.setAttribute('aria-grabbed','false');
          div._selectedTerm = null;
          e.preventDefault();
        }
      });
    });
  }

  // Überprüft, ob alle Begriffe korrekt zugeordnet wurden
  function checkAssign(div, feedback, idx){
    let allCorrect = true;
    div.querySelectorAll('.dropzone').forEach(zone => {
      const parts = zone.textContent.split(' \u2013 ');
      const dropped = parts.length > 1 ? parts[1].trim() : '';
      if(zone.dataset.term !== dropped) allCorrect = false;
    });
    results[idx] = allCorrect;
    feedback.innerHTML = allCorrect
      ? '<div class="uk-alert-success" uk-alert><span class="uk-hidden-visually">Richtige Antwort</span> ✅ Alles richtig zugeordnet!</div>'
      : '<div class="uk-alert-danger" uk-alert><span class="uk-hidden-visually">Falsche Antwort</span> ❌ Nicht alle Zuordnungen sind korrekt.</div>';
  }

  // Setzt die Zuordnungsfrage auf den Ausgangszustand zurück
  function resetAssign(div, feedback){
    const termList = div.querySelector('.terms');
    termList.innerHTML = '';
    div._initialLeftTerms.forEach(t => {
      const li = document.createElement('li');
      li.draggable = true;
      li.setAttribute('role','listitem');
      li.tabIndex = 0;
      li.setAttribute('aria-grabbed','false');
      li.dataset.term = t.term;
      li.textContent = t.term;
      termList.appendChild(li);
    });
    termList.querySelectorAll('li').forEach(li => {
      li.addEventListener('keydown', e => {
        if(e.key === 'Enter' || e.key === ' '){
          div._selectedTerm = li;
          li.setAttribute('aria-grabbed','true');
          e.preventDefault();
        }
      });
    });
    div.querySelectorAll('.dropzone').forEach(zone => {
      zone.textContent = zone.dataset.definition;
      delete zone.dataset.dropped;
    });
    feedback.textContent = '';
    div._selectedTerm = null;
  }

  // Prüft die Auswahl bei einer Multiple-Choice-Frage
  function checkMc(div, correctIndices, feedback, idx){
    const selected = Array.from(div.querySelectorAll('input[name="mc' + idx + '"]:checked'))
      .map(el => parseInt(el.value, 10))
      .sort((a, b) => a - b);
    const sortedCorrect = correctIndices.slice().sort((a, b) => a - b);
    const correct =
      selected.length === sortedCorrect.length &&
      selected.every((v, i) => v === sortedCorrect[i]);
    results[idx] = correct;
    feedback.innerHTML =
      correct
        ? '<div class="uk-alert-success" uk-alert><span class="uk-hidden-visually">Richtige Antwort</span> ✅ Korrekt!</div>'
        : '<div class="uk-alert-danger" uk-alert><span class="uk-hidden-visually">Falsche Antwort</span> ❌ Das ist nicht korrekt.</div>';
  }

  // Erstellt das DOM für eine Multiple-Choice-Frage
  function createMcQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const h = document.createElement('h4');
    h.textContent = q.prompt;
    div.appendChild(h);

    const options = document.createElement('div');

    // Optionen zufällig anordnen
    const order = shuffleArray(q.options.map((_,i) => i));
    // Korrekte Antworten auf die neue Reihenfolge abbilden
    const correctIndices = (q.answers || [q.answer || 0]).map(a => order.indexOf(a));

    order.forEach((orig,i) => {
      const label = document.createElement('label');
      label.className = 'mc-option';
      const input = document.createElement('input');
      input.className = 'uk-checkbox';
      input.type = 'checkbox';
      input.name = 'mc' + idx;
      input.value = i;
      label.appendChild(input);
      label.append(' ' + q.options[orig]);
      options.appendChild(label);
    });

    const feedback = document.createElement('div');
    feedback.setAttribute('role', 'alert');
    feedback.setAttribute('aria-live', 'polite');
    feedback.className = 'uk-margin-top';

    const footer = document.createElement('div');
    footer.className = 'uk-margin-top uk-flex uk-flex-between';
    const checkBtn = document.createElement('button');
    checkBtn.className = 'uk-button uk-button-primary';
    checkBtn.textContent = 'Antwort prüfen';
    styleButton(checkBtn);
    checkBtn.addEventListener('click', () => {
      checkMc(div, correctIndices, feedback, idx);
    });
    if(!showCheck) checkBtn.classList.add('uk-hidden');
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button';
    nextBtn.textContent = 'Weiter';
    styleButton(nextBtn);
    nextBtn.addEventListener('click', () => {
      checkMc(div, correctIndices, feedback, idx);
      next();
    });
    footer.appendChild(checkBtn);
    footer.appendChild(nextBtn);

    div.appendChild(options);
    div.appendChild(feedback);
    div.appendChild(footer);

    return div;
  }

  function createSwipeQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const h = document.createElement('h4');
    h.textContent = q.prompt;
    div.appendChild(h);

    const container = document.createElement('div');
    container.className = 'swipe-container';
    container.style.position = 'relative';
    container.style.height = '250px';
    container.style.userSelect = 'none';
    container.style.touchAction = 'none';
    div.appendChild(container);

    const controls = document.createElement('div');
    controls.className = 'uk-margin-top uk-flex uk-flex-center';
    const leftBtn = document.createElement('button');
    leftBtn.className = 'uk-button uk-margin-right';
    leftBtn.innerHTML = '\u2B05 ' + (q.leftLabel || 'Nein');
    styleButton(leftBtn);
    leftBtn.addEventListener('click', () => manualSwipe('left'));
    leftBtn.addEventListener('keydown', e => {
      if(e.key === 'Enter' || e.key === ' '){
        e.preventDefault();
        manualSwipe('left');
      }
    });

    const rightBtn = document.createElement('button');
    rightBtn.className = 'uk-button';
    rightBtn.innerHTML = (q.rightLabel || 'Ja') + ' \u27A1';
    styleButton(rightBtn);
    rightBtn.addEventListener('click', () => manualSwipe('right'));
    rightBtn.addEventListener('keydown', e => {
      if(e.key === 'Enter' || e.key === ' '){
        e.preventDefault();
        manualSwipe('right');
      }
    });
    controls.appendChild(leftBtn);
    controls.appendChild(rightBtn);

    const leftStatic = document.createElement('div');
    leftStatic.textContent = '\u2B05 ' + (q.leftLabel || 'Falsch');
    leftStatic.style.position = 'absolute';
    leftStatic.style.left = '0';
    leftStatic.style.top = '50%';
    leftStatic.style.transform = 'translate(-50%, -50%) rotate(180deg)';
    leftStatic.style.writingMode = 'vertical-rl';
    leftStatic.style.pointerEvents = 'none';
    leftStatic.style.color = 'red';
    leftStatic.style.zIndex = '10';
    container.appendChild(leftStatic);

    const rightStatic = document.createElement('div');
    rightStatic.textContent = (q.rightLabel || 'Richtig') + ' \u27A1';
    rightStatic.style.position = 'absolute';
    rightStatic.style.right = '0';
    rightStatic.style.top = '50%';
    rightStatic.style.transform = 'translate(50%, -50%)';
    rightStatic.style.writingMode = 'vertical-rl';
    rightStatic.style.pointerEvents = 'none';
    rightStatic.style.color = 'green';
    rightStatic.style.zIndex = '10';
    container.appendChild(rightStatic);

    const label = document.createElement('div');
    label.style.position = 'absolute';
    label.style.top = '8px';
    label.style.left = '8px';
    label.style.fontWeight = 'bold';
    label.style.pointerEvents = 'none';
    container.appendChild(label);

    let cards = (q.cards || []).map(c => ({...c}));
    const resultsLocal = [];
    let startX=0,startY=0,offsetX=0,offsetY=0,dragging=false;

    function render(){
      container.querySelectorAll('.swipe-card').forEach(el => el.remove());
      cards.forEach((c,i) => {
        const card = document.createElement('div');
        card.className = 'swipe-card';
        card.style.position = 'absolute';
        card.style.left = '2rem';
        card.style.right = '2rem';
        card.style.top = '0';
        card.style.bottom = '0';
        card.style.background = 'white';
        card.style.borderRadius = '8px';
        card.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
        card.style.display = 'flex';
        card.style.alignItems = 'center';
        card.style.justifyContent = 'center';
        card.style.padding = '1rem';
        card.style.transition = 'transform 0.3s';
        const off = (cards.length - i - 1) * 4;
        card.style.transform = `translate(0,-${off}px)`;
        card.style.zIndex = i;
        card.textContent = c.text;
        if(i === cards.length - 1){
          card.addEventListener('pointerdown', start);
          card.addEventListener('pointermove', move);
          card.addEventListener('pointerup', end);
          card.addEventListener('pointercancel', end);
        }
        container.appendChild(card);
      });
    }

    function point(e){ return { x: e.clientX, y: e.clientY }; }

    function start(e){
      if(!cards.length) return;
      const p = point(e);
      startX = p.x; startY = p.y;
      dragging = true;
      offsetX = 0; offsetY = 0;
    }

    function move(e){
      if(!dragging) return;
      const p = point(e);
      offsetX = p.x - startX;
      offsetY = p.y - startY;
      const card = container.querySelector('.swipe-card:last-child');
      if(card){
        const rot = offsetX / 10;
        card.style.transform = `translate(${offsetX}px,${offsetY}px) rotate(${rot}deg)`;
      }
      label.textContent = offsetX >= 0
        ? '\u27A1 ' + (q.rightLabel || 'Ja')
        : '\u2B05 ' + (q.leftLabel || 'Nein');
      label.style.color = offsetX >= 0 ? 'green' : 'red';
      e.preventDefault();
    }

    function end(){
      if(!dragging) return;
      dragging = false;
      const cardEl = container.querySelector('.swipe-card:last-child');
      const card = cards[cards.length-1];
      const threshold = 80;
      if(Math.abs(offsetX) > threshold){
        const dir = offsetX > 0 ? 'right' : 'left';
        const labelText = offsetX > 0 ? (q.rightLabel || 'Ja') : (q.leftLabel || 'Nein');
        const correct = (dir === 'right') === !!card.correct;
        resultsLocal.push({text: card.text, label: labelText, correct});
        if(cardEl){
          cardEl.style.transform = `translate(${offsetX > 0 ? 1000 : -1000}px,${offsetY}px)`;
        }
        setTimeout(() => {
          cards.pop();
          offsetX = offsetY = 0;
          label.textContent = '';
          render();
          if(!cards.length){
            results[idx] = resultsLocal.every(r => r.correct);
            next();
          }
        },300);
      } else {
        if(cardEl){
          cardEl.style.transform = 'translate(0,0)';
        }
        offsetX = offsetY = 0;
        label.textContent = '';
      }
    }

    function manualSwipe(dir){
      if(!cards.length) return;
      const cardEl = container.querySelector('.swipe-card:last-child');
      const card = cards[cards.length-1];
      const labelText = dir === 'right' ? (q.rightLabel || 'Ja') : (q.leftLabel || 'Nein');
      const correct = (dir === 'right') === !!card.correct;
      resultsLocal.push({text: card.text, label: labelText, correct});
      if(cardEl){
        cardEl.style.transform = `translate(${dir === 'right' ? 1000 : -1000}px,0)`;
      }
      setTimeout(() => {
        cards.pop();
        offsetX = offsetY = 0;
        label.textContent = '';
        render();
        if(!cards.length){
          results[idx] = resultsLocal.every(r => r.correct);
          next();
        }
      },300);
    }

    div.appendChild(controls);
    render();
    return div;
  }

  // Startbildschirm mit Statistik und Startknopf
  function createStart(){
    const div = document.createElement('div');
    div.className = 'question uk-text-center';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const stats = document.createElement('div');
    stats.className = 'uk-margin';

    if(cfg.QRUser){
      const scanBtn = document.createElement('button');
      scanBtn.className = 'uk-button uk-button-primary uk-button-large';
      scanBtn.textContent = 'Name mit QR-Code scannen';
      styleButton(scanBtn);
      const modal = document.createElement('div');
      modal.id = 'quiz-qr-modal';
      modal.setAttribute('uk-modal', '');
      modal.setAttribute('aria-modal', 'true');
      modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">'+
        '<h3 class="uk-modal-title uk-text-center">Who I AM</h3>'+
        '<div id="qr-reader" class="uk-margin" style="max-width:320px;margin:0 auto;width:100%"></div>'+
        '<button id="qr-reader-stop" class="uk-button uk-button-primary uk-width-1-1 uk-margin-top">Abbrechen</button>'+
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
          document.getElementById('qr-reader').textContent = 'QR-Scanner nicht verfügbar.';
          return;
        }
        scanner = new Html5Qrcode('qr-reader');
        Html5Qrcode.getCameras().then(cams => {
          if(!cams || !cams.length){
            document.getElementById('qr-reader').textContent = 'Keine Kamera gefunden.';
            return;
          }
          let cam = cams[0].id;
          const back = cams.find(c => /back|rear|environment/i.test(c.label));
          if(back) cam = back.id;
          scanner.start(cam, { fps: 10, qrbox: 250 }, text => {
            setStored('quizUser', text.trim());
            stopScanner();
            UIkit.modal(modal).hide();
            next();
          }).catch(err => {
            console.error('QR scanner start failed.', err);
            document.getElementById('qr-reader').textContent = 'QR-Scanner konnte nicht gestartet werden.';
          });
        }).catch(err => {
          console.error('Camera list error.', err);
          document.getElementById('qr-reader').textContent = 'Kamera konnte nicht initialisiert werden.';
        });
      };
      const stopBtn = modal.querySelector('#qr-reader-stop');
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
      document.body.appendChild(modal);
      return div;
    }

    const startBtn = document.createElement('button');
    startBtn.className = 'uk-button uk-button-primary uk-button-large uk-align-right';
    startBtn.textContent = 'Los geht es!';
    styleButton(startBtn);
    // Zeigt bisherige Ergebnisse als kleine Slideshow an
    stats.textContent = 'Noch keine Ergebnisse vorhanden.';
    startBtn.addEventListener('click', () => {
      if(cfg.QRRestrict){
        alert('Nur Registrierung per QR-Code erlaubt');
        return;
      }
      const user = generateUserName();
      setStored('quizUser', user);
      next();
    });
    div.appendChild(startBtn);
    div.appendChild(stats);
    return div;
  }

  // Abschlussbildschirm nach dem Quiz
  function createSummary(){
    const div = document.createElement('div');
    div.className = 'question uk-text-center';
    div.setAttribute('uk-scrollspy', 'cls: uk-animation-slide-bottom-small; target: > *; delay: 100');
    const h = document.createElement('h3');
    h.textContent = '🎉 Danke für die Teilnahme!';
    const p = document.createElement('p');
    const letter = document.createElement('div');
    letter.id = 'quiz-letter';
    letter.className = 'quiz-letter uk-margin-top';
    letter.style.display = 'none';
    const puzzleInfo = document.createElement('p');
    puzzleInfo.id = 'puzzle-info';
    puzzleInfo.className = 'uk-margin-top';
    const remainingInfo = document.createElement('p');
    remainingInfo.id = 'quiz-remaining';
    remainingInfo.className = 'uk-margin-top';
    div.appendChild(h);
    div.appendChild(p);
    div.appendChild(letter);
    div.appendChild(puzzleInfo);
    div.appendChild(remainingInfo);
    if(!cfg.competitionMode){
      const restart = document.createElement('a');
      restart.href = '/';
      restart.textContent = 'Neu starten';
      restart.className = 'uk-button uk-button-primary uk-margin-top';
      styleButton(restart);
      restart.addEventListener('click', () => {
        sessionStorage.removeItem('quizUser');
        sessionStorage.removeItem('quizSolved');
        localStorage.removeItem('quizUser');
        const topbar = document.getElementById('topbar-title');
        if(topbar){
          topbar.textContent = topbar.dataset.defaultTitle || '';
        }
      });
      div.appendChild(restart);
    }
    return div;
  }


  function showPuzzleCheck(btnEl, attemptKey){
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
    function handleCheck(){
      const valRaw = (input.value || '').trim();
      const ts = Math.floor(Date.now()/1000);
      const user = getStored('quizUser') || '';
      const catalog = getStored('quizCatalog') || 'unknown';
      fetch('/results?debug=1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: user, catalog, puzzleTime: ts, puzzleAnswer: valRaw })
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
            const infoEl = summaryEl.querySelector('#puzzle-info');
            if(infoEl){
              infoEl.textContent = `Rätselwort gelöst: ${formatPuzzleTime(ts)}`;
            }
            return;
          }
        }
        feedback.textContent = 'Das ist leider nicht korrekt. Viel Glück beim nächsten Versuch!';
        feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
      })
      .catch(() => {
        feedback.textContent = 'Fehler bei der Überprüfung.';
        feedback.className = 'uk-margin-top uk-text-center uk-text-danger';
      })
      .finally(() => {
        input.disabled = true;
        if(attemptKey) sessionStorage.setItem(attemptKey, 'true');
        if(btnEl){
          btnEl.disabled = true;
          btnEl.style.display = 'none';
        }

        btn.textContent = 'Schließen';
        btn.removeEventListener('click', handleCheck);
        btn.addEventListener('click', () => ui.hide());
      });
    }

    btn.addEventListener('click', handleCheck);
    ui.show();
  }

  function showPhotoModal(name, catalog){
    const modal = document.createElement('div');
    modal.setAttribute('uk-modal', '');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML =
      '<div class="uk-modal-dialog uk-modal-body">' +
        '<div class="uk-card uk-card-default uk-card-body uk-padding-small uk-width-1-1">' +
          '<p class="uk-text-small">Hinweis zum Hochladen von Gruppenfotos:<br>' +
            'Mit dem Upload eines Fotos bestätigen Sie, dass alle abgebildeten Personen der Verwendung des Fotos im Rahmen der Veranstaltung zustimmen. Das Hochladen ist freiwillig. Die Fotos werden ausschließlich für die Dokumentation der Veranstaltung verwendet und nach der Veranstaltung von der Onlineplattform gelöscht.' +
          '</p>' +
          '<div class="uk-margin-small-bottom">' +
            '<label class="uk-form-label" for="photo-input">Beweisfoto auswählen</label>' +
            '<div class="stacked-upload" uk-form-custom="target: true">' +
              '<input id="photo-input" type="file" accept="image/*" capture="environment" aria-label="Datei auswählen">' +
              '<input class="uk-input uk-width-1-1" type="text" placeholder="Keine Datei ausgewählt" disabled>' +
              '<button class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top" type="button" tabindex="-1">Durchsuchen</button>' +
            '</div>' +
          '</div>' +
          '<label class="uk-form-label uk-margin-small-bottom">' +
            '<input type="checkbox" id="consent-checkbox" class="uk-checkbox uk-margin-small-right">' +
            'Einverständnis aller abgebildeten Personen wurde eingeholt ' +
          '</label>' +
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
      btn.disabled = !input.files.length || !consent.checked;
    }
    input.addEventListener('change', toggleBtn);
    consent.addEventListener('change', toggleBtn);

    btn.addEventListener('click', () => {
      const file = input.files && input.files[0];
      if(!file || !consent.checked) return;
      const fd = new FormData();
      fd.append('photo', file);
      fd.append('name', name);
      fd.append('catalog', catalog);
      fd.append('team', name);

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
        .then(() => {
          feedback.textContent = 'Foto gespeichert';
          feedback.className = 'uk-margin-top uk-text-center uk-text-success';
          btn.disabled = true;
          input.disabled = true;
          consent.disabled = true;
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
}

function startQuiz(qs, skipIntro){
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', () => runQuiz(qs, skipIntro));
  } else {
    runQuiz(qs, skipIntro);
  }
}

window.startQuiz = startQuiz;
if(window.quizQuestions){
  startQuiz(window.quizQuestions, false);
}
