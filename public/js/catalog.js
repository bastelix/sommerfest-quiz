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

async function handleSelection(opt) {
  if (!opt) {
    return;
  }

  // Metadaten speichern
  const name = opt.textContent || opt.dataset.name || '';
  const desc = opt.dataset.desc || '';
  const comment = opt.dataset.comment || '';

  sessionStorage.setItem('quizCatalogName', name);
  if (desc) {
    sessionStorage.setItem('quizCatalogDesc', desc);
  } else {
    sessionStorage.removeItem('quizCatalogDesc');
  }
  if (comment) {
    sessionStorage.setItem('quizCatalogComment', comment);
  } else {
    sessionStorage.removeItem('quizCatalogComment');
  }

  localStorage.setItem('quizCatalogUid', opt.dataset.uid || '');
  localStorage.setItem('quizCatalogSortOrder', opt.dataset.sortOrder || '');

  // Katalogdaten laden
  const file = opt.dataset.file;
  try {
    if (file) {
      const base = window.basePath || '';
      const res = await fetch(base + file, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      window.quizQuestions = data;
      showCatalogIntro(data);
      return;
    }
  } catch (e) {
    console.error('Katalogdatei konnte nicht geladen werden', e);
  }
  showCatalogIntro([]);
}

function showCatalogIntro(qs) {
  const header = document.getElementById('quiz-header');
  if (header) {
    const title = sessionStorage.getItem('quizCatalogName') || '';
    const desc = sessionStorage.getItem('quizCatalogDesc') || '';
    const comment = sessionStorage.getItem('quizCatalogComment');

    let h1 = header.querySelector('h1');
    if (!h1) {
      h1 = document.createElement('h1');
      header.appendChild(h1);
    }
    h1.textContent = title;

    let sub = header.querySelector('p[data-role="subheader"]');
    if (!sub) {
      sub = document.createElement('p');
      sub.dataset.role = 'subheader';
      header.appendChild(sub);
    }
    sub.textContent = desc;

    let cBlock = header.querySelector('div[data-role="catalog-comment-block"]');
    if (comment) {
      if (!cBlock) {
        cBlock = document.createElement('div');
        cBlock.dataset.role = 'catalog-comment-block';
        header.appendChild(cBlock);
      }
      cBlock.textContent = comment;
    } else if (cBlock) {
      cBlock.remove();
    }
  }

  const quiz = document.getElementById('quiz');
  if (!quiz) {
    return;
  }

  quiz.innerHTML = '';

  const comment = sessionStorage.getItem('quizCatalogComment');
  if (comment) {
    const p = document.createElement('p');
    p.textContent = comment;
    quiz.appendChild(p);
  }

  const button = document.createElement('button');
  button.textContent = "Los geht's!";
  button.addEventListener('click', async () => {
    if (typeof window.startQuiz !== 'function') {
      await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = (window.basePath || '') + 'js/quiz.js';
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
      });
    }
    const questions = qs || window.quizQuestions || [];
    window.startQuiz(questions, false);
  });
  quiz.appendChild(button);
}

document.addEventListener('DOMContentLoaded', init);
if (document.readyState !== 'loading') {
  init();
}
