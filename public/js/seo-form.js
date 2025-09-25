/* global apiFetch, notify */

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

  const hiddenPageId = form.querySelector('input[name="pageId"]');
  const pageSelect = form.querySelector('#seoPageSelect');

  const fieldMap = {
    metaTitle: 'metaTitle',
    metaDescription: 'metaDescription',
    slug: 'slug',
    canonicalUrl: 'canonical',
    robotsMeta: 'robots',
    ogTitle: 'ogTitle',
    ogDescription: 'ogDescription',
    ogImage: 'ogImage',
    schemaJson: 'schema',
    hreflang: 'hreflang'
  };

  const pageConfigs = {};
  const pageMeta = {};

  const applyConfig = config => {
    if (!config) return;
    const pageId = config.pageId ?? config.id;
    if (hiddenPageId && pageId) {
      hiddenPageId.value = pageId;
    }
    Object.entries(fieldMap).forEach(([key, elementId]) => {
      const field = form.querySelector(`#${elementId}`);
      if (!field) return;
      const value = config[key] ?? '';
      if (field.value !== value) {
        field.value = value;
      }
      field.dispatchEvent(new Event('input'));
    });
  };

  if (pageSelect) {
    try {
      const configs = JSON.parse(pageSelect.dataset.configs || '[]');
      configs.forEach(entry => {
        if (!entry || typeof entry !== 'object') return;
        const id = String(entry.id ?? entry.config?.pageId ?? '');
        if (!id) return;
        const config = entry.config || {};
        pageConfigs[id] = {
          pageId: config.pageId ?? Number(id),
          metaTitle: config.metaTitle ?? '',
          metaDescription: config.metaDescription ?? '',
          slug: config.slug ?? '',
          canonicalUrl: config.canonicalUrl ?? '',
          robotsMeta: config.robotsMeta ?? '',
          ogTitle: config.ogTitle ?? '',
          ogDescription: config.ogDescription ?? '',
          ogImage: config.ogImage ?? '',
          schemaJson: config.schemaJson ?? '',
          hreflang: config.hreflang ?? ''
        };
        pageMeta[id] = {
          slug: entry.slug || '',
          title: entry.title || ''
        };
      });
    } catch (error) {
      // ignore malformed JSON
    }

    const initialId = pageSelect.dataset.selected || pageSelect.value;
    if (initialId && pageConfigs[initialId]) {
      applyConfig(pageConfigs[initialId]);
    }

    pageSelect.addEventListener('change', () => {
      const selectedId = pageSelect.value;
      if (hiddenPageId) {
        hiddenPageId.value = selectedId;
      }
      pageSelect.dataset.selected = selectedId;
      if (pageConfigs[selectedId]) {
        applyConfig(pageConfigs[selectedId]);
      } else {
        applyConfig({ pageId: selectedId });
      }
    });
  }

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
    e.preventDefault();
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
    if (!valid) return;
    const body = new URLSearchParams(new FormData(form));
    apiFetch('/admin/landingpage/seo', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        Accept: 'application/json'
      },
      body
    })
      .then(r => {
        if (!r.ok) throw new Error('save-failed');
        return r.json().catch(() => ({}));
      })
      .then(data => {
        if (data && data.config && data.page) {
          const id = String(data.page.id);
          pageConfigs[id] = {
            pageId: data.config.pageId ?? data.page.id,
            metaTitle: data.config.metaTitle ?? '',
            metaDescription: data.config.metaDescription ?? '',
            slug: data.config.slug ?? '',
            canonicalUrl: data.config.canonicalUrl ?? '',
            robotsMeta: data.config.robotsMeta ?? '',
            ogTitle: data.config.ogTitle ?? '',
            ogDescription: data.config.ogDescription ?? '',
            ogImage: data.config.ogImage ?? '',
            schemaJson: data.config.schemaJson ?? '',
            hreflang: data.config.hreflang ?? ''
          };
          pageMeta[id] = {
            slug: data.page.slug || '',
            title: data.page.title || ''
          };

          if (pageSelect) {
            let option = pageSelect.querySelector(`option[value="${CSS.escape(id)}"]`);
            if (!option) {
              option = document.createElement('option');
              option.value = id;
              pageSelect.append(option);
            }
            option.dataset.slug = pageMeta[id].slug;
            option.dataset.title = pageMeta[id].title;
            option.textContent = `${pageMeta[id].title} (${pageMeta[id].slug})`;
            pageSelect.value = id;
            pageSelect.dataset.selected = id;
            pageSelect.dataset.configs = JSON.stringify(
              Object.entries(pageConfigs).map(([pageId, config]) => ({
                id: Number(pageId),
                slug: pageMeta[pageId]?.slug || '',
                title: pageMeta[pageId]?.title || '',
                config
              }))
            );
          }

          applyConfig(pageConfigs[id]);
        }
        notify('Einstellungen gespeichert', 'success');
      })
      .catch(() => notify('Fehler beim Speichern', 'danger'));
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSeoForm);
} else {
  initSeoForm();
}
