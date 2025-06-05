document.addEventListener('DOMContentLoaded', function(){
  const cfg = window.quizConfig || {};
  if(cfg.backgroundColor){
    document.body.style.backgroundColor = cfg.backgroundColor;
  }

  const container = document.getElementById('quiz');
  const progress = document.getElementById('progress');
  const questions = window.quizQuestions || [];

  // shuffle the questions so the order differs on every page load
  const shuffled = questions.slice();
  const questionCount = shuffled.length;
  for(let i = shuffled.length - 1; i > 0; i--){
    const j = Math.floor(Math.random() * (i + 1));
    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
  }

  let current = 0;
  const elements = shuffled.map((q, idx) => createQuestion(q, idx));
  const results = new Array(questionCount).fill(false);
  const startEl = createStart();
  const summaryEl = createSummary();
  elements.unshift(startEl);
  elements.push(summaryEl);
  let summaryShown = false;

  // apply configurable styles
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
      const h = document.createElement('h2');
      h.textContent = cfg.header;
      h.className = 'uk-card-title uk-margin-remove-bottom';
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
  });
  progress.max = questionCount;
  progress.value = 0;
  progress.classList.add('uk-hidden');

  function showQuestion(i){
    elements.forEach((el, idx) => el.classList.toggle('uk-hidden', idx !== i));
    if(i === 0){
      progress.classList.add('uk-hidden');
      progress.value = 0;
    } else if(i <= questionCount){
      progress.classList.remove('uk-hidden');
      progress.value = i;
    } else {
      progress.value = questionCount;
      progress.classList.add('uk-hidden');
      updateSummary();
    }
  }

  function next(){
    if(current < questionCount){
      current++;
      showQuestion(current);
    } else if(current === questionCount){
      current++;
      showQuestion(current);
    }
  }

  function styleButton(btn){
    if(cfg.buttonColor){
      btn.style.backgroundColor = cfg.buttonColor;
      btn.style.borderColor = cfg.buttonColor;
      btn.style.color = '#fff';
    }
  }

  function updateSummary(){
    if(summaryShown) return;
    summaryShown = true;
    const score = results.filter(r => r).length;
    const p = summaryEl.querySelector('p');
    if(p) p.textContent = `Du hast ${score} von ${questionCount} richtig.`;

    const user = sessionStorage.getItem('quizUser') || ('user-' + Math.random().toString(36).substr(2,8));
    let log = localStorage.getItem('statistical.log') || '';
    log += `${user} ${score}/${questionCount}\n`;
    localStorage.setItem('statistical.log', log);
  }

  function createQuestion(q, idx){
    if(q.type === 'sort') return createSortQuestion(q, idx);
    if(q.type === 'assign') return createAssignQuestion(q, idx);
    if(q.type === 'mc') return createMcQuestion(q, idx);
    return document.createElement('div');
  }

  function createSortQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question';
    const h = document.createElement('h4');
    h.textContent = q.prompt;
    div.appendChild(h);
    const ul = document.createElement('ul');
    ul.className = 'uk-list uk-list-divider sortable-list uk-margin';
    q.items.forEach(text => {
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
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button';
    nextBtn.textContent = 'Weiter';
    styleButton(nextBtn);
    nextBtn.addEventListener('click', next);
    footer.appendChild(btn);
    footer.appendChild(nextBtn);
    div.appendChild(feedback);
    div.appendChild(footer);
    setupSortHandlers(ul);
    return div;
  }

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

  function checkSort(ul, right, feedback, idx){
    const currentOrder = Array.from(ul.querySelectorAll('li')).map(li => li.textContent.trim());
    const correct = JSON.stringify(currentOrder) === JSON.stringify(right);
    results[idx] = correct;
    feedback.innerHTML =
      correct
        ? '<div class="uk-alert-success" uk-alert>✅ Richtig sortiert!</div>'
        : '<div class="uk-alert-danger" uk-alert>❌ Leider falsch, versuche es nochmal!</div>';
  }

  function createAssignQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question';
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
    q.terms.forEach(t => {
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
    q.terms.forEach(t => {
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
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button';
    nextBtn.textContent = 'Weiter';
    styleButton(nextBtn);
    nextBtn.addEventListener('click', next);
    footer.appendChild(btn);
    footer.appendChild(nextBtn);
    div.appendChild(feedback);
    div.appendChild(footer);

    setupAssignHandlers(div);
    return div;
  }

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

  function createMcQuestion(q, idx){
    const div = document.createElement('div');
    div.className = 'question';
    const h = document.createElement('h4');
    h.textContent = q.prompt;
    div.appendChild(h);

    const options = document.createElement('div');

    q.options.forEach((opt,i) => {
      const label = document.createElement('label');
      const input = document.createElement('input');
      input.className = 'uk-radio';
      input.type = 'radio';
      input.name = 'mc' + idx;
      input.value = i;
      label.appendChild(input);
      label.append(' ' + opt);
      options.appendChild(label);
      options.appendChild(document.createElement('br'));
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
      const v = div.querySelector('input[name="mc' + idx + '"]:checked');
      const correct = v && parseInt(v.value,10) === q.answer;
      results[idx] = correct;
      feedback.innerHTML =
        correct
          ? '<div class="uk-alert-success" uk-alert>✅ Korrekt!</div>'
          : '<div class="uk-alert-danger" uk-alert>❌ Das ist nicht korrekt.</div>';
    });
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button';
    nextBtn.textContent = 'Weiter';
    styleButton(nextBtn);
    nextBtn.addEventListener('click', next);
    footer.appendChild(checkBtn);
    footer.appendChild(nextBtn);

    div.appendChild(options);
    div.appendChild(feedback);
    div.appendChild(footer);

    return div;
  }

  function createStart(){
    const div = document.createElement('div');
    div.className = 'question uk-text-center';
    const h = document.createElement('h1');
    h.textContent = 'Quiz Start';
    h.className = 'uk-margin';
    const stats = document.createElement('div');
    stats.className = 'uk-margin';
    const startBtn = document.createElement('button');
    startBtn.className = 'uk-button uk-button-primary uk-button-large';
    startBtn.textContent = 'UND LOS';
    styleButton(startBtn);
    const log = localStorage.getItem('statistical.log');
    if(log){
      const list = document.createElement('ul');
      list.className = 'uk-list uk-list-divider uk-width-medium uk-margin-auto';
      log.trim().split('\n').filter(Boolean).forEach(l => {
        const [user, score] = l.split(' ');
        const li = document.createElement('li');
        li.textContent = `${user}: ${score}`;
        list.appendChild(li);
      });
      const h3 = document.createElement('h3');
      h3.textContent = 'Bisherige Ergebnisse';
      stats.appendChild(h3);
      stats.appendChild(list);
    } else {
      stats.textContent = 'Noch keine Ergebnisse vorhanden.';
    }
    startBtn.addEventListener('click', () => {
      const user = 'user-' + Math.random().toString(36).substr(2,8);
      sessionStorage.setItem('quizUser', user);
      next();
    });
    div.appendChild(h);
    div.appendChild(stats);
    div.appendChild(startBtn);
    return div;
  }

  function createSummary(){
    const div = document.createElement('div');
    div.className = 'question uk-text-center';
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
