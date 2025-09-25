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
    domain: 'seoDomain',
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

  const normalizeDomainValue = domain => {
    if (!domain) return '';
    const trimmed = String(domain).trim().toLowerCase();
    if (trimmed === '') return '';
    return trimmed
      .replace(/^https?:\/\//, '')
      .split('/')[0]
      .replace(/:\d+$/, '');
  };

  const ensureMetaDomains = (pageId, domain) => {
    if (!pageId || !domain) return;
    const meta = pageMeta[pageId] ?? { slug: '', title: '', domains: [] };
    if (!Array.isArray(meta.domains)) {
      meta.domains = [];
    }
    if (!meta.domains.includes(domain)) {
      meta.domains.unshift(domain);
    }
    pageMeta[pageId] = meta;
  };

  const getActivePageId = () => {
    if (pageSelect && pageSelect.value) return pageSelect.value;
    if (hiddenPageId && hiddenPageId.value) return String(hiddenPageId.value);
    const keys = Object.keys(pageConfigs);
    return keys.length > 0 ? keys[0] : '';
  };

  const determinePrimaryDomain = pageId => {
    const config = pageConfigs[pageId] || {};
    const meta = pageMeta[pageId] || {};
    const rawDomain = config.domain
      || (Array.isArray(meta.domains) && meta.domains.length > 0 ? meta.domains[0] : '')
      || window.location.hostname;
    const normalized = normalizeDomainValue(rawDomain);
    return normalized || normalizeDomainValue(window.location.hostname);
  };

  const buildBaseUrl = domain => {
    if (!domain) return window.location.origin;
    return `https://${domain}`.replace(/\/+$/, '');
  };

  const exampleGenerators = {
    landing: ctx => ({
      metaTitle: 'QuizRace – Gestalten Sie Ihr interaktives Team-Quiz für Events',
      metaDescription:
        'QuizRace macht Ihr Event einzigartig: QR-Code-Stationen, Live-Ranking & Rätselspaß – datensicher, flexibel, ohne App. Jetzt kostenlos testen!',
      slug: '/',
      canonical: `${ctx.baseUrl}/`,
      robots: 'index, follow',
      ogTitle: 'QuizRace – Gestalten Sie Ihr interaktives Team-Quiz für Events',
      ogDescription:
        'Erstellen Sie Ihr eigenes Event-Quiz mit QR-Code-Stationen, Live-Ranking & Rätselspaß. DSGVO-konform, flexibel, ohne App. Jetzt kostenlos testen!',
      ogImage: `${ctx.baseUrl}/img/social-preview.jpg`,
      schema: `{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "QuizRace",
  "url": "${ctx.baseUrl}/",
  "description": "QuizRace ist das interaktive Event-Quiz mit QR-Code-Stationen, Live-Ranking & Rätselspaß – datensicher, flexibel, ohne App. Jetzt kostenlos testen!",
  "publisher": {
    "@type": "Organization",
    "name": "QuizRace",
    "logo": {
      "@type": "ImageObject",
      "url": "${ctx.baseUrl}/img/logo.png"
    }
  },
  "sameAs": [
    "https://www.facebook.com/quizrace",
    "https://www.instagram.com/quizrace",
    "https://www.linkedin.com/company/quizrace"
  ]
}`,
      hreflang: `<link rel="alternate" href="${ctx.baseUrl}/" hreflang="de" />`,
      domain: ctx.domain
    }),
    calserver: ctx => ({
      metaTitle: 'calServer – Digitale Termin- und Ressourcenplanung für Schulen',
      metaDescription:
        'calServer organisiert Unterricht, Räume und Vertretungen mit einem zentralen Kalender – DSGVO-konform, cloudbasiert und mit persönlichem Support.',
      slug: '/',
      canonical: `${ctx.baseUrl}/`,
      robots: 'index, follow',
      ogTitle: 'calServer – Mehr Zeit für Schule statt Terminchaos',
      ogDescription:
        'Mit calServer verwalten Schulen Ressourcen, Teams und Veranstaltungen intuitiv. Sichere Infrastruktur, persönlicher Support und flexible Workflows.',
      ogImage: `${ctx.baseUrl}/img/social-preview.jpg`,
      schema: `{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "calServer",
  "url": "${ctx.baseUrl}/",
  "description": "calServer digitalisiert Terminplanung und Ressourcenverwaltung für Schulen – zuverlässig, sicher und datenschutzkonform."
}`,
      hreflang: `<link rel="alternate" href="${ctx.baseUrl}/" hreflang="de" />`,
      domain: ctx.domain
    })
  };

  const buildGenericExample = ctx => {
    const rawSlug = ctx.configSlug && ctx.configSlug !== '/' ? ctx.configSlug : ctx.slugValue;
    const cleanedSlug = rawSlug ? rawSlug.replace(/^\/+/, '') : '';
    const slugField = cleanedSlug ? `/${cleanedSlug}` : '/';
    const canonical = slugField === '/' ? `${ctx.baseUrl}/` : `${ctx.baseUrl}${slugField}`;
    const title = ctx.title || (cleanedSlug ? cleanedSlug : ctx.domain || 'Landing Page');
    return {
      metaTitle: `${title} – ${ctx.domain}`,
      metaDescription: `${title} auf ${ctx.domain}.`,
      slug: slugField,
      canonical,
      robots: 'index, follow',
      ogTitle: title,
      ogDescription: `${title} auf ${ctx.domain}.`,
      ogImage: `${ctx.baseUrl}/img/social-preview.jpg`,
      schema: `{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": "${title}",
  "url": "${canonical}"
}`,
      hreflang: `<link rel="alternate" href="${canonical}" hreflang="de" />`,
      domain: ctx.domain
    };
  };

  const resolveExampleKey = (meta, domain) => {
    const slug = meta.slug || '';
    if (slug && exampleGenerators[slug]) {
      return slug;
    }

    const normalizedDomain = domain || '';
    if (/calserver/.test(normalizedDomain)) {
      return 'calserver';
    }
    if (/quizrace|quiz-race/.test(normalizedDomain)) {
      return 'landing';
    }

    return slug;
  };

  const buildExample = pageId => {
    const meta = pageMeta[pageId] || {};
    const domain = determinePrimaryDomain(pageId);
    const baseUrl = buildBaseUrl(domain);
    const key = resolveExampleKey(meta, domain);
    const generator = (key && exampleGenerators[key]) || exampleGenerators[meta.slug] || buildGenericExample;
    return generator({
      domain,
      baseUrl,
      slugValue: meta.slug || '',
      configSlug: pageConfigs[pageId]?.slug || '',
      title: meta.title || ''
    });
  };

  const applyConfig = config => {
    if (!config) return;
    const pageIdRaw = config.pageId ?? config.id;
    const pageId = pageIdRaw != null ? String(pageIdRaw) : '';
    if (hiddenPageId && pageId) {
      hiddenPageId.value = pageId;
    }
    const meta = pageId ? pageMeta[pageId] : null;
    Object.entries(fieldMap).forEach(([key, elementId]) => {
      const field = form.querySelector(`#${elementId}`);
      if (!field) return;
      let value = config[key];
      if (value == null) {
        value = '';
      }
      if (key === 'domain') {
        if (!value && meta && Array.isArray(meta.domains) && meta.domains.length > 0) {
          [value] = meta.domains;
        }
      }
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
        const domains = Array.isArray(entry.domains)
          ? entry.domains.map(value => String(value)).filter(value => value !== '')
          : [];
        pageConfigs[id] = {
          pageId: config.pageId ?? Number(id),
          metaTitle: config.metaTitle ?? '',
          metaDescription: config.metaDescription ?? '',
          slug: config.slug ?? '',
          domain: normalizeDomainValue(config.domain ?? domains[0] ?? ''),
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
          title: entry.title || '',
          domains: []
        };
        domains.forEach(domain => ensureMetaDomains(id, normalizeDomainValue(domain)));
        if (pageConfigs[id].domain) {
          ensureMetaDomains(id, normalizeDomainValue(pageConfigs[id].domain));
        }
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
      const activeId = getActivePageId();
      if (!activeId) return;
      const example = buildExample(activeId);
      Object.entries(example).forEach(([key, value]) => {
        const targetId = fieldMap[key] ?? key;
        const field = form.querySelector(`#${targetId}`);
        if (field) {
          field.value = value;
          field.dispatchEvent(new Event('input'));
        }
      });
      const normalized = normalizeDomainValue(example.domain ?? '');
      if (pageConfigs[activeId]) {
        pageConfigs[activeId].domain = normalized;
      }
      if (normalized) {
        ensureMetaDomains(activeId, normalized);
      }
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
          const normalizedDomain = normalizeDomainValue(data.config.domain ?? '');
          pageConfigs[id] = {
            pageId: data.config.pageId ?? data.page.id,
            metaTitle: data.config.metaTitle ?? '',
            metaDescription: data.config.metaDescription ?? '',
            slug: data.config.slug ?? '',
            domain: normalizedDomain,
            canonicalUrl: data.config.canonicalUrl ?? '',
            robotsMeta: data.config.robotsMeta ?? '',
            ogTitle: data.config.ogTitle ?? '',
            ogDescription: data.config.ogDescription ?? '',
            ogImage: data.config.ogImage ?? '',
            schemaJson: data.config.schemaJson ?? '',
            hreflang: data.config.hreflang ?? ''
          };
          const existingMeta = pageMeta[id] ?? { slug: '', title: '', domains: [] };
          const domains = Array.isArray(existingMeta.domains)
            ? existingMeta.domains.slice()
            : [];
          if (normalizedDomain && !domains.includes(normalizedDomain)) {
            domains.unshift(normalizedDomain);
          }
          pageMeta[id] = {
            slug: data.page.slug || existingMeta.slug || '',
            title: data.page.title || existingMeta.title || '',
            domains
          };
          if (normalizedDomain) {
            ensureMetaDomains(id, normalizedDomain);
          }

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
                domains: pageMeta[pageId]?.domains || [],
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
