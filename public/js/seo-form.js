export function initSeoForm() {
  const form = document.querySelector('.seo-form');
  if (!form) return;

  const inputs = form.querySelectorAll('[data-maxlength]');

  inputs.forEach(input => {
    const max = parseInt(input.dataset.maxlength, 10);
    const counter = form.querySelector(`.char-count[data-for="${input.id}"]`);
    if (counter) {
      input.addEventListener('input', () => {
        const len = input.value.length;
        counter.textContent = `${len}/${max}`;
        counter.classList.toggle('uk-text-danger', len > max);
      });
      input.dispatchEvent(new Event('input'));
    }
  });

  const importBtn = form.querySelector('.import-seo-example');
  if (importBtn) {
    importBtn.addEventListener('click', () => {
      const example = {
        metaTitle: 'QuizRace – Gestalten Sie Ihr interaktives Team-Quiz für Events',
        metaDescription: 'QuizRace macht Ihr Event einzigartig: QR-Code-Stationen, Live-Ranking & Rätselspaß – datensicher, flexibel, ohne App. Jetzt kostenlos testen!',
        slug: '/',
        canonical: 'https://quizrace.app/',
        robots: 'index, follow',
        ogTitle: 'QuizRace – Gestalten Sie Ihr interaktives Team-Quiz für Events',
        ogDescription: 'Erstellen Sie Ihr eigenes Event-Quiz mit QR-Code-Stationen, Live-Ranking & Rätselspaß. DSGVO-konform, flexibel, ohne App. Jetzt kostenlos testen!',
        ogImage: 'https://quizrace.app/img/social-preview.jpg',
        schema: `{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "QuizRace",
  "url": "https://quizrace.app/",
  "description": "QuizRace ist das interaktive Event-Quiz mit QR-Code-Stationen, Live-Ranking & Rätselspaß – datensicher, flexibel, ohne App. Jetzt kostenlos testen!",
  "publisher": {
    "@type": "Organization",
    "name": "QuizRace",
    "logo": {
      "@type": "ImageObject",
      "url": "https://quizrace.app/img/logo.png"
    }
  },
  "sameAs": [
    "https://www.facebook.com/quizrace",
    "https://www.instagram.com/quizrace",
    "https://www.linkedin.com/company/quizrace"
  ]
}`,
        hreflang: '<link rel="alternate" href="https://quizrace.app/" hreflang="de" />'
      };
      Object.entries(example).forEach(([id, value]) => {
        const field = form.querySelector(`#${id}`);
        if (field) {
          field.value = value;
          field.dispatchEvent(new Event('input'));
        }
      });
    });
  }

  form.addEventListener('submit', e => {
    let valid = true;
    inputs.forEach(input => {
      const max = parseInt(input.dataset.maxlength, 10);
      if (max && input.value.length > max) {
        input.classList.add('uk-form-danger');
        valid = false;
      } else {
        input.classList.remove('uk-form-danger');
      }
    });
    form.querySelectorAll('[required]').forEach(field => {
      if (!field.value.trim()) {
        field.classList.add('uk-form-danger');
        valid = false;
      } else {
        field.classList.remove('uk-form-danger');
      }
    });
    if (!valid) {
      e.preventDefault();
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSeoForm);
} else {
  initSeoForm();
}
