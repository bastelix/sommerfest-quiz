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

  async function load() {
    const ym = fmtYm(state.ym);
    const r = await fetch(withBase(`/admin/dashboard.json?month=${ym}`));
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

    let html = '<table class="uk-table uk-table-divider uk-table-small uk-text-center">';
    html += '<thead><tr><th>Mo</th><th>Di</th><th>Mi</th><th>Do</th><th>Fr</th><th>Sa</th><th>So</th></tr></thead><tbody><tr>';
    for (let i = 0; i < startWeekday; i++) html += '<td></td>';

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
      html += `<td class="${isToday ? 'uk-background-muted uk-text-bold' : ''}"><div>${day}</div>`;
      if (hasEvents) {
        html += '<div class="uk-margin-small-top">' +
          byDate[key].map(ev => {
            const s = new Date(ev.start).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
            const eTime = new Date(ev.end).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
            const label = `${s}–${eTime} ${short(ev.title)}`;
            return `<a class="uk-badge uk-display-block uk-margin-xsmall-top" href="${withBase('/admin/events/' + ev.id)}" title="${ev.title}">${label}</a>`;
          }).join('') +
          '</div>';
      }
      html += '</td>';
      const wd = (startWeekday + day) % 7;
      if (wd === 0 && day < days) html += '</tr><tr>';
    }

    const cellsUsed = startWeekday + days;
    const tail = (7 - (cellsUsed % 7)) % 7;
    for (let i = 0; i < tail; i++) html += '<td></td>';

    html += '</tr></tbody></table>';
    cal.innerHTML = html;
  }

  function short(t){ return t.length > 14 ? t.slice(0,12) + '…' : t; }

  function renderUpcoming() {
    const ul = document.getElementById('upcoming-list');
    const empty = document.getElementById('today-empty');
    if (!ul || !empty) return;
    const todayEvents = state.events.filter(e => new Date(e.start).toISOString().slice(0,10) === state.today);
    empty.style.display = todayEvents.length ? 'none' : '';
    ul.innerHTML = [...todayEvents, ...state.upcoming].slice(0,5).map(e => {
      const dt = new Date(e.start);
      const when = dt.toLocaleString('de-DE', { weekday:'short', day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
      return `<li class="uk-flex uk-flex-between uk-flex-middle"><div><div class="uk-text-bold">${e.title}</div><div class="uk-text-meta">${when}</div></div><div><a class="uk-button uk-button-text" href="${withBase('/admin/events/' + e.id)}">Öffnen →</a></div></li>`;
    }).join('');
  }

  function renderBadges() {
    const s = state.stats || {};
    const d = document.getElementById('badge-draft');
    const sch = document.getElementById('badge-scheduled');
    const r = document.getElementById('badge-running');
    const f = document.getElementById('badge-finished');
    if (d) d.textContent = `${s.draftCount || 0} Entwürfe`;
    if (sch) sch.textContent = `${s.scheduledCount || 0} geplant`;
    if (r) r.textContent = `${s.runningCount || 0} live`;
    if (f) f.textContent = `${s.finishedCount || 0} abgeschlossen`;
  }

  function renderSubscription() {
    const el = document.getElementById('subscription');
    if (!el || !state.subscription) return;
    const sub = state.subscription;
    const labels = {
      plan: el.dataset.labelPlan || 'Plan',
      events: el.dataset.labelEvents || 'Events',
      catalogs: el.dataset.labelCatalogs || 'Catalogs',
      questions: el.dataset.labelQuestions || 'Questions'
    };
    const planKey = sub.plan || '';
    const planName = el.dataset['plan' + capitalize(planKey)] || planKey || '-';
    const limits = sub.limits || {};
    const usage = sub.usage || {};
    const items = [
      { label: labels.events, used: usage.events, max: limits.maxEvents },
      { label: labels.catalogs, used: usage.catalogs, max: limits.maxCatalogsPerEvent },
      { label: labels.questions, used: usage.questions, max: limits.maxQuestionsPerCatalog }
    ];
    el.innerHTML = `<div><strong>${labels.plan}: ${planName}</strong></div>` +
      items.map(it => {
        const maxText = it.max === null || it.max === undefined ? '∞' : it.max;
        const pct = typeof it.max === 'number' ? Math.min(100, Math.round((it.used / it.max) * 100)) : 0;
        return `<div class="uk-margin-small-top"><div class="uk-flex uk-flex-between"><span>${it.label}</span><span>${it.used}${it.max !== null && it.max !== undefined ? ' / ' + maxText : ''}</span></div>${it.max !== null && it.max !== undefined ? `<progress class="uk-progress" value="${pct}" max="100"></progress>` : ''}</div>`;
      }).join('');
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
