(function() {
  const basePath = window.basePath || '';
  const withBase = path => basePath + path;
  const state = {
    period: null,
    today: null,
    events: [],
    upcoming: [],
    stats: {},
    subscription: {},
    ym: new Date()
  };

  function fmtYm(d){ return d.toISOString().slice(0,7); }
  function firstOfMonth(d){ return new Date(d.getFullYear(), d.getMonth(), 1); }
  function lastOfMonth(d){ return new Date(d.getFullYear(), d.getMonth() + 1, 0); }

  function resolveActiveNamespace() {
    const params = new URLSearchParams(window.location.search);
    const queryNamespace = (params.get('namespace') || '').trim();
    if (queryNamespace) {
      return queryNamespace;
    }

    const select = document.querySelector('[data-namespace-select], #projectNamespaceSelect, #pageNamespaceSelect, #namespaceSelect');
    const candidate = (select?.value || '').trim();

    return candidate;
  }

  async function load() {
    const ym = fmtYm(state.ym);
    const namespace = resolveActiveNamespace();
    const searchParams = new URLSearchParams({ month: ym });
    if (namespace) {
      searchParams.set('namespace', namespace);
    }

    const r = await fetch(withBase(`/admin/dashboard.json?${searchParams.toString()}`));
    const data = await r.json();
    Object.assign(state, data);
    renderCalendar();
    renderUpcoming();
    renderBadges();
    renderSubscription();
  }

  function renderCalendar() {
    const cal = document.getElementById('calendar');
    if (!cal) return;
    const monthStart = firstOfMonth(state.ym);
    const monthEnd = lastOfMonth(state.ym);
    const startWeekday = (monthStart.getDay() + 6) % 7;
    const days = monthEnd.getDate();

    const h3 = cal.parentElement.querySelector('h3');
    h3.textContent = monthStart.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });

    cal.textContent = '';
    const table = document.createElement('table');
    table.className = 'uk-table uk-table-small uk-text-center calendar-table';
    const thead = document.createElement('thead');
    const trHead = document.createElement('tr');
    ['Mo','Di','Mi','Do','Fr','Sa','So'].forEach(d => {
      const th = document.createElement('th');
      th.textContent = d;
      trHead.appendChild(th);
    });
    thead.appendChild(trHead);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    let tr = document.createElement('tr');
    for (let i = 0; i < startWeekday; i++) tr.appendChild(document.createElement('td'));

    const byDate = {};
    state.events.forEach(e => {
      const start = new Date(e.start);
      const end = new Date(e.end);
      for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        const key = d.toISOString().slice(0, 10);
        (byDate[key] ||= []).push(e);
      }
    });

    for (let day = 1; day <= days; day++) {
      const date = new Date(state.ym.getFullYear(), state.ym.getMonth(), day);
      const key = date.toISOString().slice(0, 10);
      const isToday = key === state.today;
      const hasEvents = !!byDate[key];
      const td = document.createElement('td');
      if (isToday) td.className = 'uk-background-muted uk-text-bold';
      const dayDiv = document.createElement('div');
      dayDiv.textContent = day;
      td.appendChild(dayDiv);
      if (hasEvents) {
        const eventsDiv = document.createElement('div');
        eventsDiv.className = 'cal-events';
        byDate[key].forEach(ev => {
          const a = document.createElement('a');
          a.className = 'cal-event';
          a.href = withBase('/admin/events/' + ev.id);
          const data = window.safeEventData ? window.safeEventData(ev) : { rangeLabel: '' };
          a.setAttribute('title', data.rangeLabel);
          eventsDiv.appendChild(a);
        });
        td.appendChild(eventsDiv);
      }
      tr.appendChild(td);
      const wd = (startWeekday + day) % 7;
      if (wd === 0 && day < days) {
        tbody.appendChild(tr);
        tr = document.createElement('tr');
      }
    }

    const cellsUsed = startWeekday + days;
    const tail = (7 - (cellsUsed % 7)) % 7;
    for (let i = 0; i < tail; i++) tr.appendChild(document.createElement('td'));
    tbody.appendChild(tr);
    table.appendChild(tbody);
    cal.appendChild(table);
  }


  function renderUpcoming() {
    const ul = document.getElementById('upcoming-list');
    const empty = document.getElementById('today-empty');
    if (!ul || !empty) return;
    const todayEvents = state.events.filter(e => {
      const start = new Date(e.start).toISOString().slice(0, 10);
      const end = new Date(e.end).toISOString().slice(0, 10);
      return start <= state.today && end >= state.today;
    });
    empty.style.display = todayEvents.length ? 'none' : '';
    ul.textContent = '';
    [...todayEvents, ...state.upcoming].slice(0,5).forEach(e => {
      const data = window.safeEventData ? window.safeEventData(e) : { title: e.title, when: '' };
      const li = document.createElement('li');
      li.className = 'uk-flex uk-flex-between uk-flex-middle';

      const left = document.createElement('div');
      const titleDiv = document.createElement('div');
      titleDiv.className = 'uk-text-bold';
      titleDiv.textContent = data.title;
      const metaDiv = document.createElement('div');
      metaDiv.className = 'uk-text-meta';
      metaDiv.textContent = data.when;
      left.appendChild(titleDiv);
      left.appendChild(metaDiv);

      const right = document.createElement('div');
      const link = document.createElement('a');
      link.className = 'uk-button uk-button-text';
      link.href = withBase('/admin/events/' + e.id);
      link.textContent = 'Öffnen →';
      right.appendChild(link);

      li.appendChild(left);
      li.appendChild(right);
      ul.appendChild(li);
    });
  }

  function renderBadges() {
    const s = state.stats || {};
    const e = document.getElementById('badge-pages');
    const w = document.getElementById('badge-wiki');
    const n = document.getElementById('badge-news');
    const nl = document.getElementById('badge-newsletter');
    const m = document.getElementById('badge-media');
    if (e) e.textContent = `${s.pages || 0} Seiten`;
    if (w) w.textContent = `${s.wiki || 0} Wiki-Artikel`;
    if (n) n.textContent = `${s.news || 0} News-Einträge`;
    if (nl) nl.textContent = `${s.newsletter || 0} Newsletter-Slugs`;
    if (m) m.textContent = `${s.media || 0} Medien-Referenzen`;
  }

  function renderSubscription() {
    const els = document.querySelectorAll('[data-subscription]');
    if (!els.length || !state.subscription) return;
    const sub = state.subscription;
    els.forEach(el => {
      const labels = {
        plan: el.dataset.labelPlan || 'Plan',
        events: el.dataset.labelEvents || 'Events',
        catalogs: el.dataset.labelCatalogs || 'Catalogs',
        questions: el.dataset.labelQuestions || 'Questions',
        of: el.dataset.labelOf || '/'
      };
      const planKey = sub.plan || '';
      const planName = el.dataset['plan' + capitalize(planKey)] || planKey || '-';
      const limits = sub.limits || {};
      const usage = sub.usage || {};
      el.textContent = '';
      const planDiv = document.createElement('div');
      const strong = document.createElement('strong');
      strong.textContent = `${labels.plan}: ${planName}`;
      planDiv.appendChild(strong);
      el.appendChild(planDiv);

      const items = [
        { label: labels.events, used: usage.events, max: limits.maxEvents },
        { label: labels.catalogs, used: usage.catalogs, max: limits.maxCatalogsPerEvent },
        { label: labels.questions, used: usage.questions, max: limits.maxQuestionsPerCatalog }
      ];
      items.forEach(it => {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'uk-margin-small-top';
        const topDiv = document.createElement('div');
        topDiv.className = 'uk-flex uk-flex-between';
        const spanLabel = document.createElement('span');
        spanLabel.textContent = it.label;
        const spanUsed = document.createElement('span');
        const maxText = it.max === null || it.max === undefined ? '∞' : it.max;
        spanUsed.textContent = `${it.used}${it.max !== null && it.max !== undefined ? ' ' + labels.of + ' ' + maxText : ''}`;
        topDiv.appendChild(spanLabel);
        topDiv.appendChild(spanUsed);
        itemDiv.appendChild(topDiv);

        if (it.max !== null && it.max !== undefined) {
          const pct = typeof it.max === 'number' ? Math.min(100, Math.round((it.used / it.max) * 100)) : 0;
          const progress = document.createElement('progress');
          progress.className = 'uk-progress';
          progress.value = pct;
          progress.max = 100;
          itemDiv.appendChild(progress);
        }
        el.appendChild(itemDiv);
      });
    });
  }

  function capitalize(s){ return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }

  document.getElementById('cal-prev')?.addEventListener('click', () => {
    state.ym.setMonth(state.ym.getMonth() - 1);
    load();
  });
  document.getElementById('cal-next')?.addEventListener('click', () => {
    state.ym.setMonth(state.ym.getMonth() + 1);
    load();
  });

  load();
})();
