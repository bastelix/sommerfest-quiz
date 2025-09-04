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
