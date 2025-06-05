document.addEventListener('DOMContentLoaded', function(){
  const container = document.getElementById('quiz');
  const progress = document.getElementById('progress');
  const cfg = window.quizConfig || {};
  const questions = window.quizQuestions || [];

  // shuffle the questions so the order differs on every page load
  const shuffled = questions.slice();
  for(let i = shuffled.length - 1; i > 0; i--){
    const j = Math.floor(Math.random() * (i + 1));
    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
  }

  let current = 0;
  const elements = shuffled.map((q, idx) => createQuestion(q, idx));

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
  progress.max = elements.length;
  progress.value = 1;

  function showQuestion(i){
    elements.forEach((el, idx) => el.classList.toggle('uk-hidden', idx !== i));
    progress.value = i + 1;
  }

  function next(e){
    if(current < elements.length - 1){
      current++;
      showQuestion(current);
    }else{
      e.target.disabled = true;
      document.body.insertAdjacentHTML('beforeend','<div class="uk-alert-success uk-margin-top">🎉 Danke fürs Mitmachen!</div>');
    }
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
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary';
    btn.textContent = 'Antwort prüfen';
    const feedback = document.createElement('div');
    feedback.className = 'uk-margin-top';
    btn.addEventListener('click', () => checkSort(ul, q.items, feedback));
    div.appendChild(btn);
    div.appendChild(feedback);
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button uk-button-default uk-margin-top';
    nextBtn.textContent = 'Weiter';
    nextBtn.addEventListener('click', next);
    div.appendChild(nextBtn);
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

  function checkSort(ul, right, feedback){
    const currentOrder = Array.from(ul.querySelectorAll('li')).map(li => li.textContent.trim());
    feedback.innerHTML =
      JSON.stringify(currentOrder) === JSON.stringify(right)
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

    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary uk-margin-small-top';
    btn.textContent = 'Antwort prüfen';
    const feedback = document.createElement('div');
    feedback.className = 'uk-margin-top';
    btn.addEventListener('click', () => checkAssign(div, feedback));
    div.appendChild(btn);
    div.appendChild(feedback);
    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button uk-button-default uk-margin-top';
    nextBtn.textContent = 'Weiter';
    nextBtn.addEventListener('click', next);
    div.appendChild(nextBtn);

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

  function checkAssign(div, feedback){
    let allCorrect = true;
    div.querySelectorAll('.dropzone').forEach(zone => {
      if(zone.dataset.term !== zone.dataset.dropped) allCorrect = false;
    });
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

    const form = document.createElement('form');
    form.id = 'mcForm' + idx;

    q.options.forEach((opt,i) => {
      const label = document.createElement('label');
      const input = document.createElement('input');
      input.className = 'uk-radio';
      input.type = 'radio';
      input.name = 'mc' + idx;
      input.value = i;
      label.appendChild(input);
      label.append(' ' + opt);
      form.appendChild(label);
      form.appendChild(document.createElement('br'));
    });

    const submit = document.createElement('button');
    submit.className = 'uk-button uk-button-primary uk-margin-top';
    submit.type = 'submit';
    submit.textContent = 'Antwort prüfen';
    form.appendChild(submit);

    const feedback = document.createElement('div');
    feedback.className = 'uk-margin-top';
    form.addEventListener('submit', e => {
      e.preventDefault();
      const v = div.querySelector('input[name="mc' + idx + '"]:checked');
      feedback.innerHTML =
        v && parseInt(v.value,10) === q.answer
          ? '<div class="uk-alert-success" uk-alert>✅ Korrekt! Die besitzende OE hat Schreibrechte.</div>'
          : '<div class="uk-alert-danger" uk-alert>❌ Das ist nicht korrekt.</div>';
    });

    div.appendChild(form);
    div.appendChild(feedback);

    const nextBtn = document.createElement('button');
    nextBtn.className = 'uk-button uk-button-primary uk-margin-top';
    nextBtn.textContent = 'Fertig';
    nextBtn.addEventListener('click', next);
    div.appendChild(nextBtn);

    return div;
  }
});
