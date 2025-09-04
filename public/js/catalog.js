/* global UIkit */
const basePath = window.basePath || '';
const withBase = p => basePath + p;

(function () {
  const eventUid = (window.quizConfig || {}).event_uid || '';
  const csrfToken =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
    window.csrfToken || '';

  function setStored(key, value) {
    try {
      sessionStorage.setItem(key, value);
      localStorage.setItem(key, value);
    } catch (e) {
      // ignore storage errors
    }
  }

  function sanitize(text) {
    const el = document.createElement('div');
    el.textContent = text == null ? '' : String(text);
    return el.textContent;
  }

  function setSubHeader(text) {
    const headerEl = document.getElementById('quiz-header');
    if (!headerEl) return;
    let el = headerEl.querySelector('p[data-role="subheader"]');
    if (!el) {
      el = document.createElement('p');
      el.dataset.role = 'subheader';
      el.className = 'uk-text-lead';
      headerEl.appendChild(el);
    }
    el.textContent = text || '';
  }

  function setComment(text) {
    const headerEl = document.getElementById('quiz-header');
    if (!headerEl) return;
    let block = headerEl.querySelector('div[data-role="catalog-comment-block"]');
    if (!block) {
      block = document.createElement('div');
      block.dataset.role = 'catalog-comment-block';
      block.className = 'modern-info-card uk-card qr-card uk-card-body uk-box-shadow-medium uk-margin';
      block.style.whiteSpace = 'pre-wrap';
      headerEl.appendChild(block);
    }
    if (text) {
      block.textContent = sanitize(text);
      block.classList.remove('uk-hidden');
    } else {
      block.textContent = '';
      block.classList.add('uk-hidden');
    }
  }

  function withEvent(url) {
    const sep = url.includes('?') ? '&' : '?';
    return url + sep + 'event=' + encodeURIComponent(eventUid);
  }

  async function loadQuestions(slug, sort_order, file, letter, uid, name, desc, comment) {
    const catalogKey = uid ?? slug ?? sort_order;
    setStored('quizCatalog', catalogKey);
    sessionStorage.setItem('quizCatalogName', name || slug || uid || sort_order);
    if (desc !== undefined) {
      sessionStorage.setItem('quizCatalogDesc', desc);
    } else {
      sessionStorage.removeItem('quizCatalogDesc');
    }
    if (comment !== undefined) {
      sessionStorage.setItem('quizCatalogComment', comment);
    } else {
      sessionStorage.removeItem('quizCatalogComment');
    }

    const headerEl = document.getElementById('quiz-header');
    if (headerEl) {
      let title = headerEl.querySelector('h1');
      if (!title) {
        title = document.createElement('h1');
        title.className = 'uk-margin-remove-bottom';
        headerEl.appendChild(title);
      }
      title.textContent = name || slug || uid || sort_order;
    }
    setSubHeader(desc || '');
    setComment(comment || '');

    let loaded = false;
    try {
      const headers = { Accept: 'application/json' };
      if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
      }
      const res = await fetch(
        withBase(withEvent('/catalog/questions/' + file)),
        { headers, credentials: 'same-origin' }
      );
      const data = await res.json();
      window.quizQuestions = data;
      loaded = true;
      showCatalogIntro(data);
      return;
    } catch (e) {
      console.error('Fragen konnten nicht geladen werden, versuche inline Daten', e);
    } finally {
      if (!loaded) {
        const inlineId = slug ?? uid ?? sort_order;
        const inline = inlineId ? document.getElementById(inlineId + '-data') : null;
        if (inline) {
          try {
            const data = JSON.parse(inline.textContent);
            window.quizQuestions = data;
            loaded = true;
            showCatalogIntro(data);
          } catch (err) {
            console.error('Inline-Daten ungültig.', err);
          }
        }
        if (!loaded) {
          alert('Fragen konnten nicht geladen werden.');
          showCatalogIntro([]);
        }
      }
    }
  }

  function showCatalogIntro(data) {
    const container = document.getElementById('quiz');
    if (!container) return;
    container.textContent = '';
    const desc = sessionStorage.getItem('quizCatalogDesc');
    if (desc) {
      const p = document.createElement('p');
      p.textContent = desc;
      container.appendChild(p);
    }
    const comment = sessionStorage.getItem('quizCatalogComment');
    if (comment) {
      const p = document.createElement('p');
      p.textContent = comment;
      container.appendChild(p);
    }
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary uk-button-large uk-align-right';
    btn.textContent = 'Los geht\'s!';
    const cfg = window.quizConfig || {};
    if (cfg.colors && cfg.colors.accent) {
      btn.style.backgroundColor = cfg.colors.accent;
      btn.style.borderColor = cfg.colors.accent;
      btn.style.color = '#fff';
    }
    btn.addEventListener('click', async () => {
      const runQuiz = () => {
        if (typeof window.startQuiz === 'function') {
          window.startQuiz(data, true);
          return true;
        }
        return false;
      };
      if (runQuiz()) return;
      try {
        await new Promise((resolve, reject) => {
          const s = document.createElement('script');
          s.src = withBase('/js/quiz.js');
          s.defer = true;
          s.onload = resolve;
          s.onerror = reject;
          document.head.appendChild(s);
        });
        if (!runQuiz()) {
          console.warn('startQuiz is still undefined after loading quiz.js');
          alert('Quiz kann nicht gestartet werden.');
        }
      } catch (e) {
        console.warn('quiz.js could not be loaded', e);
        alert('Quiz kann nicht gestartet werden.');
      }
    });
    container.appendChild(btn);
  }

  function handleSelection(option) {
    if (!option) return;
    loadQuestions(
      option.dataset.slug,
      option.dataset.sortOrder,
      option.dataset.file,
      option.dataset.letter,
      option.dataset.uid,
      option.textContent,
      option.dataset.desc,
      option.dataset.comment
    );
  }

  function init() {
    const select = document.getElementById('catalog-select');
    if (!select) return;

    const params = new URLSearchParams(window.location.search);
    const id = (params.get('slug') || params.get('katalog') || '').toLowerCase();

    if (select.options.length === 1 && !id) handleSelection(select.options[0]);

    select.addEventListener('change', () => {
      const opt = select.selectedOptions[0];
      handleSelection(opt);
    });

    if (id) {
      const opt = Array.from(select.options).find(o => {
        const value = (o.value || '').toLowerCase();
        const slug = (o.dataset.slug || '').toLowerCase();
        return value === id || slug === id;
      });
      if (opt) {
        select.value = opt.value;
        // Trigger selection manually so setComment() and showCatalogIntro() run
        handleSelection(opt);
      }
    } else {
      const opt = select.selectedOptions[0];
      if (opt) {
        // Run on initial load so showCatalogIntro() displays the catalog intro
        handleSelection(opt);
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
