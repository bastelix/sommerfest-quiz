function init() {
  // Container sicherstellen
  let quizContainer = document.getElementById('quiz');
  if (!quizContainer) {
    const mount = document.querySelector('main') || document.body;
    quizContainer = document.createElement('div');
    quizContainer.id = 'quiz';
    mount.appendChild(quizContainer);
  }

  // URL-Parameter lesen (unterstützt mehrere Varianten)
  const params = new URLSearchParams(window.location.search);
  const id = (
    params.get('slug') ||
    params.get('katalog') ||
    params.get('catalog') ||
    params.get('k') ||
    ''
  ).toLowerCase();

  // Select suchen (ID oder data-role)
  const select = document.getElementById('catalog-select') ||
                 document.querySelector('[data-role="catalog-select"]');

  // --- Fall A: Kein <select> vorhanden ---
  if (!select) {
    if (id) {
      // 1) Versuche Inline-Daten <script id="abc-data">...</script>
      const inline = document.getElementById(id + '-data');
      if (inline) {
        try {
          const data = JSON.parse(inline.textContent);
          // Optional: Name/Desc/Comment aus data oder Metas setzen
          sessionStorage.setItem('quizCatalogName', id.toUpperCase());
          sessionStorage.removeItem('quizCatalogDesc');
          sessionStorage.removeItem('quizCatalogComment');
          showCatalogIntro(data);
          return;
        } catch (e) {
          console.warn('Inline-Daten ungültig für slug=', id, e);
        }
      }

      // 2) Kein Inline: Zeige zumindest Intro, damit der Button erscheint
      sessionStorage.setItem('quizCatalogName', id.toUpperCase());
      sessionStorage.removeItem('quizCatalogDesc');
      sessionStorage.removeItem('quizCatalogComment');
      showCatalogIntro([]); // Button sichtbar; quiz.js wird bei Klick nachgeladen
      UIkit?.notification?.({ message: 'Katalog nicht gefunden (slug: ' + id + ').', status: 'warning' });
      return;
    }

    // Weder select noch slug → nichts zu tun
    UIkit?.notification?.({ message: 'Kein Katalog wählbar.', status: 'warning' });
    return;
  }

  // --- Fall B: <select> existiert ---
  // Direktwahl per slug, falls vorhanden
  if (id) {
    const match = Array.from(select.options).find(o => {
      const value = (o.value || '').toLowerCase();
      const slug  = (o.dataset.slug || '').toLowerCase();
      return value === id || slug === id;
    });
    if (match) {
      select.value = match.value;
      // Dropdown ausblenden
      select.style.display = 'none';
      const selectLabel = document.querySelector('label[for="catalog-select"]');
      if (selectLabel) selectLabel.style.display = 'none';
      handleSelection(match);
      return;
    } else {
      console.warn('Ungültiger Katalog-Parameter:', id);
      UIkit?.notification?.({ message: 'Katalog nicht gefunden (slug: ' + id + ').', status: 'warning' });
      // Fallback: Übersicht/erste Option anzeigen
    }
  }

  // Fallbacks, wenn kein slug oder kein Match
  select.style.display = '';
  const selectLabel = document.querySelector('label[for="catalog-select"]');
  if (selectLabel) selectLabel.style.display = '';

  if (select.options.length === 1 && !id) {
    handleSelection(select.options[0]);
  } else {
    const opt = select.selectedOptions[0];
    if (opt) handleSelection(opt);
  }

  select.addEventListener('change', () => {
    const opt = select.selectedOptions[0];
    handleSelection(opt);
  });
}

async function handleSelection(option) {
  if (!option) return;

  const basePath = window.basePath || '';
  const withBase = p => basePath + p;

  const name = option.textContent || '';
  const desc = option.dataset.desc || '';
  const comment = option.dataset.comment || '';
  const uid = option.dataset.uid || option.dataset.slug || option.value || '';

  try {
    sessionStorage.setItem('quizCatalog', uid);
    if (name) sessionStorage.setItem('quizCatalogName', name);
    else sessionStorage.removeItem('quizCatalogName');
    if (desc) sessionStorage.setItem('quizCatalogDesc', desc);
    else sessionStorage.removeItem('quizCatalogDesc');
    if (comment) sessionStorage.setItem('quizCatalogComment', comment);
    else sessionStorage.removeItem('quizCatalogComment');
  } catch (e) {
    /* storage optional */
  }

  let data = [];
  const file = option.dataset.file;
  if (file) {
    try {
      const res = await fetch(withBase('/data/' + file));
      data = await res.json();
    } catch (e) {
      console.warn('Katalog konnte nicht geladen werden:', e);
    }
  }

  showCatalogIntro(data);
}

function showCatalogIntro(data) {
  const quizContainer = document.getElementById('quiz');
  if (quizContainer) quizContainer.innerHTML = '';

  const header = document.getElementById('quiz-header');
  const name = sessionStorage.getItem('quizCatalogName') || '';
  const desc = sessionStorage.getItem('quizCatalogDesc');
  const comment = sessionStorage.getItem('quizCatalogComment');
  const basePath = window.basePath || '';
  const withBase = p => basePath + p;

  if (header) {
    header.innerHTML = '';
    if (name) {
      const h1 = document.createElement('h1');
      h1.textContent = name;
      header.appendChild(h1);
    }
    if (desc) {
      const sub = document.createElement('p');
      sub.dataset.role = 'subheader';
      sub.textContent = desc;
      header.appendChild(sub);
    }
    if (comment) {
      const block = document.createElement('div');
      block.dataset.role = 'catalog-comment-block';
      block.textContent = comment;
      header.appendChild(block);
    }
  }

  if (quizContainer) {
    if (comment) {
      const p = document.createElement('p');
      p.textContent = comment;
      quizContainer.appendChild(p);
    }
    const btn = document.createElement('button');
    btn.className = 'uk-button uk-button-primary';
    btn.textContent = "Los geht's!";
    btn.addEventListener('click', () => {
      window.quizQuestions = data;
      if (typeof startQuiz === 'function') {
        startQuiz(data, false);
      } else {
        const script = document.createElement('script');
        script.src = withBase('/js/quiz.js');
        document.head.appendChild(script);
      }
    });
    quizContainer.appendChild(btn);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
