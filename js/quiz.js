// Hauptskript des Quizzes. Dieses File erzeugt dynamisch alle Fragen,
// wertet Antworten aus und speichert das Ergebnis im Browser.
// Der Code wird ausgeführt, sobald das DOM geladen ist.
document.addEventListener('DOMContentLoaded', function(){
  // Konfiguration laden und einstellen, ob der "Antwort prüfen"-Button
  // eingeblendet werden soll
  const cfg = window.quizConfig || {};
  const showCheck = cfg.CheckAnswerButton !== 'no';
  if(cfg.backgroundColor){
    document.body.style.backgroundColor = cfg.backgroundColor;
  }

  const container = document.getElementById('quiz');
  const progress = document.getElementById('progress');
  const questions = window.quizQuestions || [];

  // Liste wohlklingender Namen für die Teilnehmer
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

  // Erzeugt einen eindeutigen Namen und merkt bereits vergebene
  function generateUserName(){
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
  }

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
  const startEl = createStart();     // Begrüßungsbildschirm
  const summaryEl = createSummary(); // Abschlussseite
  // Start- und End-Element einfügen
  elements.unshift(startEl);
  elements.push(summaryEl);
  let summaryShown = false;

  // konfigurierbare Farben dynamisch in ein Style-Tag schreiben
  const styleEl = document.createElement('style');
  styleEl.textContent = `\n    body { background-color: ${cfg.backgroundColor || '#f8f8f8'}; }\n    .uk-button-primary { background-color: ${cfg.buttonColor || '#1e87f0'}; border-color: ${cfg.buttonColor || '#1e87f0'}; }\n  `;
  document.head.appendChild(styleEl);

  // build header from config
  const headerEl = document.getElementById('quiz-header');
  if(headerEl){
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

  elements.forEach((el, i) => {
    if (i !== 0) el.classList.add('uk-hidden');
    container.appendChild(el);
    if (typeof UIkit !== 'undefined' && UIkit.scrollspy) {
      UIkit.scrollspy(el);
    }
  });
  progress.max = questionCount;
  progress.value = 0;
  progress.classList.add('uk-hidden');

  // Zeigt das Element mit dem angegebenen Index an und aktualisiert den Fortschrittsbalken
  function showQuestion(i){
    elements.forEach((el, idx) => el.classList.toggle('uk-hidden', idx !== i));
    if(i === 0){
      // Vor dem Quiz: kein Fortschrittsbalken
      progress.classList.add('uk-hidden');
      progress.value = 0;
    } else if(i <= questionCount){
      // Während des Quiz Balken aktualisieren
      progress.classList.remove('uk-hidden');
      progress.value = i;
    } else {
      // Nach der letzten Frage Zusammenfassung anzeigen
      progress.value = questionCount;
      progress.classList.add('uk-hidden');
      updateSummary();
    }
  }

  // Blendet die nächste Frage ein
  function next(){
    if(current < questionCount){
      current++;
      showQuestion(current);
    } else if(current === questionCount){
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
    const user = sessionStorage.getItem('quizUser') || generateUserName();
    const p = summaryEl.querySelector('p');
    if(p) p.textContent = `Du hast ${score} von ${questionCount} richtig.`;
    const heading = summaryEl.querySelector('h3');
    if(heading) heading.textContent = `🎉 Danke fürs Mitmachen ${user}!`;
    if(score === questionCount && typeof window.startConfetti === 'function'){
      window.startConfetti();
    }
    let log = localStorage.getItem('statistical.log') || '';
    log += `${user} ${score}/${questionCount}\n`;
    localStorage.setItem('statistical.log', log);
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
    const ul = document.createElement('ul');
    ul.className = 'uk-list uk-list-divider sortable-list uk-margin';
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
    let draggedSortItem;
    ul.querySelectorAll('li').forEach(li => {
      li.addEventListener('dragstart', () => {
        draggedSortItem = li;
        li.setAttribute('aria-grabbed','true');
      });
      li.addEventListener('dragend', () => li.setAttribute('aria-grabbed','false'));
      li.addEventListener('dragover', e => e.preventDefault());
      li.addEventListener('drop', function(){
        if(draggedSortItem !== this){
          this.parentNode.insertBefore(draggedSortItem, this.nextSibling);
        }
      });
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

    const grid = document.createElement('div');
    grid.className = 'uk-grid-small uk-child-width-1-2';
    grid.setAttribute('uk-grid','');
    div.appendChild(grid);

    const left = document.createElement('div');
    const termList = document.createElement('ul');
    termList.className = 'uk-list uk-list-striped terms';
    const leftTerms = shuffleArray(q.terms);
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
      dz.setAttribute('aria-label', t.definition);
      dz.textContent = t.definition;
      rightCol.appendChild(dz);
    });
    grid.appendChild(rightCol);

    const feedback = document.createElement('div');
    feedback.className = 'uk-margin-top';
    const footer = document.createElement('div');
    footer.className = 'uk-margin-top uk-flex uk-flex-between';
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary';
    btn.textContent = 'Antwort prüfen';
    styleButton(btn);
    btn.addEventListener('click', () => checkAssign(div, feedback, idx));
    if(!showCheck) btn.classList.add('uk-hidden');
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button';
    nextBtn.textContent = 'Weiter';
    styleButton(nextBtn);
    nextBtn.addEventListener('click', () => {
      checkAssign(div, feedback, idx);
      next();
    });
    footer.appendChild(btn);
    footer.appendChild(nextBtn);
    div.appendChild(feedback);
    div.appendChild(footer);

    setupAssignHandlers(div);
    return div;
  }

  // Initialisiert Drag-&-Drop und Tastatursteuerung für die Zuordnungsfrage
  function setupAssignHandlers(div){
    let draggedTerm = null;
    let selectedTerm = null;
    div.querySelectorAll('.terms li').forEach(li => {
      li.addEventListener('dragstart', () => {
        draggedTerm = li;
        li.setAttribute('aria-grabbed','true');
      });
      li.addEventListener('dragend', () => li.setAttribute('aria-grabbed','false'));
      li.addEventListener('keydown', e => {
        if(e.key === 'Enter' || e.key === ' '){
          selectedTerm = li;
          li.setAttribute('aria-grabbed','true');
          e.preventDefault();
        }
      });
    });
    div.querySelectorAll('.dropzone').forEach(zone => {
      zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.classList.add('over');
      });
      zone.addEventListener('dragleave', () => zone.classList.remove('over'));
      zone.addEventListener('drop', () => {
        zone.classList.remove('over');
        if(draggedTerm){
          zone.innerHTML = draggedTerm.textContent;
          zone.dataset.dropped = draggedTerm.dataset.term;
          draggedTerm.style.visibility = "hidden";
          draggedTerm.setAttribute('aria-grabbed','false');
          draggedTerm = null;
        }
      });
      zone.addEventListener('keydown', e => {
        if((e.key === 'Enter' || e.key === ' ') && selectedTerm){
          zone.innerHTML = selectedTerm.textContent;
          zone.dataset.dropped = selectedTerm.dataset.term;
          selectedTerm.style.visibility = "hidden";
          selectedTerm.setAttribute('aria-grabbed','false');
          selectedTerm = null;
          e.preventDefault();
        }
      });
    });
  }

  // Überprüft, ob alle Begriffe korrekt zugeordnet wurden
  function checkAssign(div, feedback, idx){
    let allCorrect = true;
    div.querySelectorAll('.dropzone').forEach(zone => {
      if(zone.dataset.term !== zone.dataset.dropped) allCorrect = false;
    });
    results[idx] = allCorrect;
    feedback.innerHTML = allCorrect
      ? '<div class="uk-alert-success" uk-alert>✅ Alles richtig zugeordnet!</div>'
      : '<div class="uk-alert-danger" uk-alert>❌ Nicht alle Zuordnungen sind korrekt.</div>';
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
    const startBtn = document.createElement('button');
    startBtn.className = 'uk-button uk-button-primary uk-button-large';
    startBtn.textContent = 'UND LOS';
    styleButton(startBtn);
    // Zeigt bisherige Ergebnisse als kleine Slideshow an
    function renderLog(text){
      stats.innerHTML = '';
      if(text){
        const lines = text.trim().split('\n').filter(Boolean);
        if(lines.length){
          const h3 = document.createElement('h3');
          h3.textContent = 'Bisherige Ergebnisse';
          stats.appendChild(h3);
          const container = document.createElement('div');
          container.id = 'results-slideshow';
          lines.forEach((l, idx) => {
            const [user, score] = l.split(' ');
            const slide = document.createElement('div');
            slide.className = 'result-slide uk-text-large';
            slide.textContent = `${user}: ${score}`;
            if(idx !== 0) slide.style.display = 'none';
            container.appendChild(slide);
          });
          if(lines.length > 1){
            let current = 0;
            setInterval(() => {
              const slides = container.children;
              slides[current].style.display = 'none';
              current = (current + 1) % slides.length;
              slides[current].style.display = '';
            }, 3000);
          }
          stats.appendChild(container);
        } else {
          stats.textContent = 'Noch keine Ergebnisse vorhanden.';
        }
      } else {
        stats.textContent = 'Noch keine Ergebnisse vorhanden.';
      }
    }

    const log = localStorage.getItem('statistical.log');
    renderLog(log);

    const downloadBtn = document.createElement('button');
    downloadBtn.className = 'uk-button uk-button-default uk-button-small uk-margin-top';
    downloadBtn.textContent = 'Statistik herunterladen';
    downloadBtn.addEventListener('click', () => {
      const text = localStorage.getItem('statistical.log') || '';
      const blob = new Blob([text], { type: 'text/plain' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'statistical.log';
      a.click();
      URL.revokeObjectURL(url);
    });
    startBtn.addEventListener('click', () => {
      const user = generateUserName();
      sessionStorage.setItem('quizUser', user);
      next();
    });
    div.appendChild(startBtn);
    div.appendChild(stats);
    div.appendChild(downloadBtn);
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
    restart.href = 'index.html';
    restart.textContent = 'Neu starten';
    restart.className = 'uk-button uk-button-primary uk-margin-top';
    styleButton(restart);
    div.appendChild(h);
    div.appendChild(p);
    div.appendChild(restart);
    return div;
  }
});
