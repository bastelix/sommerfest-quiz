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

function runQuiz(questions){
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

  let current = 0;
  // Zu jedem Eintrag im Array ein DOM-Element erzeugen
  const elements = shuffled.map((q, idx) => createQuestion(q, idx));
  // Speichert true/false für jede beantwortete Frage
  const results = new Array(questionCount).fill(false);
  const summaryEl = createSummary(); // Abschlussseite
  elements.push(summaryEl);
  let summaryShown = false;

  if(!sessionStorage.getItem('quizUser')){
    if(!cfg.QRRestrict){
      sessionStorage.setItem('quizUser', generateUserName());
    }
  }

  // konfigurierbare Farben dynamisch in ein Style-Tag schreiben
  const styleEl = document.createElement('style');
  styleEl.textContent = `\n    body { background-color: ${cfg.backgroundColor || '#ffffff'}; }\n    .uk-button-primary { background-color: ${cfg.buttonColor || '#1e87f0'}; border-color: ${cfg.buttonColor || '#1e87f0'}; }\n  `;
  document.head.appendChild(styleEl);

  // hide header once the quiz starts
  const headerEl = document.getElementById('quiz-header');
  if (headerEl) {
    headerEl.innerHTML = '';
    headerEl.classList.add('uk-hidden');
  }
  const disclaimerEl = document.getElementById('front-disclaimer');
  if (disclaimerEl) {
    disclaimerEl.classList.remove('uk-hidden');
  }

  elements.forEach((el, i) => {
    if (i !== 0) el.classList.add('uk-hidden');
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
    } else if(i < questionCount){
      // Fragen anzeigen und Fortschritt aktualisieren
      progress.classList.remove('uk-hidden');
      progress.value = i;
      progress.setAttribute('aria-valuenow', i);
      if (announcer) announcer.textContent = `Frage ${i} von ${questionCount}`;
    } else if(i === questionCount){
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
    if(current < questionCount){
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
    if (disclaimerEl) {
      disclaimerEl.classList.remove('uk-hidden');
    }
    const score = results.filter(r => r).length;
    let user = sessionStorage.getItem('quizUser');
    if(!user && !cfg.QRRestrict){
      user = generateUserName();
    }
    const p = summaryEl.querySelector('p');
    if(p) p.textContent = `${user} hat ${score} von ${questionCount} Punkten erreicht.`;
    const heading = summaryEl.querySelector('h3');
    if(heading) heading.textContent = `🎉 Danke fürs Mitmachen ${user}!`;
    if(score === questionCount && typeof window.startConfetti === 'function'){
      window.startConfetti();
    }
    const catalog = sessionStorage.getItem('quizCatalog') || 'unknown';
    fetch('/results', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: user, catalog, correct: score, total: questionCount })
    }).catch(()=>{});
  }

  // Wählt basierend auf dem Fragetyp die passende Erzeugerfunktion aus
  function createQuestion(q, idx){
    if(q.type === 'sort') return createSortQuestion(q, idx);
    if(q.type === 'assign') return createAssignQuestion(q, idx);
    if(q.type === 'mc') return createMcQuestion(q, idx);
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
    const sortDesc = document.createElement('p');
    sortDesc.id = 'sort-desc-' + idx;
    sortDesc.className = 'uk-hidden-visually';
    sortDesc.textContent = 'Mit Pfeil nach oben oder unten verschiebst du den aktuellen Eintrag.';
    div.appendChild(sortDesc);
    const ul = document.createElement('ul');
    ul.className = 'uk-list uk-list-divider sortable-list uk-margin';
    ul.setAttribute('aria-describedby', sortDesc.id);
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
        ? '<div class="uk-alert-success" uk-alert>✅ Richtig sortiert!</div>'
        : '<div class="uk-alert-danger" uk-alert>❌ Leider falsch, versuche es nochmal!</div>';
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
      ? '<div class="uk-alert-success" uk-alert>✅ Alles richtig zugeordnet!</div>'
      : '<div class="uk-alert-danger" uk-alert>❌ Nicht alle Zuordnungen sind korrekt.</div>';
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
        ? '<div class="uk-alert-success" uk-alert>✅ Korrekt!</div>'
        : '<div class="uk-alert-danger" uk-alert>❌ Das ist nicht korrekt.</div>';
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
      modal.innerHTML = '<div class="uk-modal-dialog uk-modal-body">'+
        '<h3 class="uk-modal-title uk-text-center">Who I AM</h3>'+
        '<div id="qr-reader" class="uk-margin" style="max-width:320px;margin:0 auto;width:100%"></div>'+
        '<button id="qr-reader-stop" class="uk-button uk-button-primary uk-width-1-1 uk-margin-top">Abbrechen</button>'+
      '</div>';
      let scanner;
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
            sessionStorage.setItem('quizUser', text.trim());
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
      scanBtn.addEventListener('click', () => {
        UIkit.modal(modal).show();
        startScanner();
      });
      UIkit.util.on(modal, 'hidden', stopScanner);
      modal.querySelector('#qr-reader-stop').addEventListener('click', () => {
        UIkit.modal(modal).hide();
      });
      div.appendChild(scanBtn);
      document.body.appendChild(modal);
      return div;
    }

    const startBtn = document.createElement('button');
    startBtn.className = 'uk-button uk-button-primary uk-button-large';
    startBtn.textContent = 'UND LOS';
    styleButton(startBtn);
    // Zeigt bisherige Ergebnisse als kleine Slideshow an
    stats.textContent = 'Noch keine Ergebnisse vorhanden.';
    startBtn.addEventListener('click', () => {
      if(cfg.QRRestrict){
        alert('Nur Registrierung per QR-Code erlaubt');
        return;
      }
      const user = generateUserName();
      sessionStorage.setItem('quizUser', user);
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
    h.textContent = '🎉 Danke fürs Mitmachen!';
    const p = document.createElement('p');
    const restart = document.createElement('a');
    restart.href = '/';
    restart.textContent = 'Neu starten';
    restart.className = 'uk-button uk-button-primary uk-margin-top';
    styleButton(restart);
    div.appendChild(h);
    div.appendChild(p);
    div.appendChild(restart);
    return div;
  }
}

function startQuiz(qs){
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', () => runQuiz(qs));
  } else {
    runQuiz(qs);
  }
}

window.startQuiz = startQuiz;
if(window.quizQuestions){
  startQuiz(window.quizQuestions);
}
