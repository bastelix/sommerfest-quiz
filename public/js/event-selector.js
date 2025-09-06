export default function initEventSelector({
  select,
  wrap = null,
  openBtn = null,
  titleEl = null,
  activeUid = '',
  fetchFn = window.fetch,
  withBase = p => p,
  notifyFn = () => {},
  onChange = null
} = {}) {
  let currentUid = activeUid;

  function updateDisplay() {
    if (!wrap || !select) return;
    const btnSpan = wrap.querySelector('button > span:first-child');
    if (btnSpan) {
      const sel = select.options[select.selectedIndex];
      btnSpan.textContent = sel ? sel.textContent : '';
    }
    window.dispatchEvent(new Event('resize'));
  }

  function populate(list = [], active = currentUid) {
    if (!select) return;
    currentUid = active;
    select.innerHTML = '';
    if (!Array.isArray(list) || list.length === 0) {
      if (wrap) wrap.hidden = true;
      if (titleEl) titleEl.hidden = false;
      return;
    }
    list.forEach(ev => {
      const opt = document.createElement('option');
      opt.value = ev.uid;
      opt.textContent = ev.name;
      if (ev.uid === currentUid) opt.selected = true;
      select.appendChild(opt);
    });
    if (wrap) wrap.hidden = false;
    if (titleEl) titleEl.hidden = true;
    updateDisplay();
  }

  function persist(uid) {
    fetchFn('/config.json', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event_uid: uid })
    })
      .then(r => {
        if (!r.ok) throw new Error('failed');
        window.location.reload();
      })
      .catch(() => notifyFn('Fehler beim Speichern', 'danger'));
  }

  function setActive(uid, name = '') {
    if (!uid || uid === currentUid) return;
    currentUid = uid;
    if (select) select.value = uid;
    updateDisplay();
    if (typeof onChange === 'function') onChange(uid, name);
    persist(uid);
  }

  select?.addEventListener('change', () => {
    const uid = select.value;
    const name = select.options[select.selectedIndex]?.textContent || '';
    setActive(uid, name);
  });

  openBtn?.addEventListener('click', () => {
    const uid = select?.value;
    if (uid) {
      window.open(withBase('/?event=' + encodeURIComponent(uid)), '_blank');
    }
  });

  return { populate, updateDisplay, setActive };
}
