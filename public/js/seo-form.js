/* global apiFetch, notify, UIkit */

export function initSeoForm() {
  const form = document.querySelector('.seo-form');
  if (!form) return;

  const saveUrl = form.getAttribute('action') || form.dataset.saveUrl || '';
  const deriveAiImportUrl = url => {
    if (!url) return '';
    try {
      const parsed = new URL(url, window.location.origin);
      parsed.pathname = parsed.pathname.replace(/\/seo\/?$/, '/seo/ai-import');
      return parsed.toString();
    } catch (error) {
      return url.replace(/\/seo\/?$/, '/seo/ai-import');
    }
  };
  const aiImportUrl = form.dataset.aiUrl
    || form.dataset.aiImportUrl
    || deriveAiImportUrl(saveUrl);

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
    hreflang: 'hreflang',
    faviconPath: 'faviconPath'
  };

  const pageConfigs = {};
  const pageMeta = {};

  const aiMessages = {
    pending: window.transSeoAiPending || 'KI erstellt SEO-Vorschläge…',
    success: window.transSeoAiSuccess || 'KI-Vorschläge übernommen',
    error: window.transSeoAiError || 'Die KI konnte keine SEO-Daten liefern.'
  };

  const aiButton = form.querySelector('.import-seo-ai');
  const aiSpinner = form.querySelector('[data-seo-ai-spinner]');
  let aiAbortController = null;

  const setAiLoading = state => {
    if (aiButton) {
      aiButton.disabled = state;
    }
    if (aiSpinner) {
      aiSpinner.hidden = !state;
    }
  };

  const buildOptionLabel = (slug, title) => {
    const trimmedTitle = (title || '').trim();
    if (trimmedTitle) {
      return `${trimmedTitle} (${slug})`;
    }
    const fallback = (slug || '')
      .split('-')
      .filter(Boolean)
      .map(part => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ');
    return `${fallback || slug} (${slug})`;
  };

  const faviconInput = form.querySelector('#faviconPath');
  const mediaButton = form.querySelector('#faviconSelectButton');
  const mediaModalEl = document.getElementById('seoMediaModal');
  const mediaListEl = mediaModalEl ? mediaModalEl.querySelector('[data-role="media-list"]') : null;
  const mediaEmptyEl = mediaModalEl ? mediaModalEl.querySelector('[data-role="media-empty"]') : null;
  const mediaSearchInput = mediaModalEl ? mediaModalEl.querySelector('[data-role="media-search"]') : null;
  const mediaModal = mediaModalEl && typeof UIkit !== 'undefined' ? UIkit.modal(mediaModalEl) : null;
  let mediaFiles = [];
  let mediaLoaded = false;
  let mediaLoading = false;
  let mediaSearchTimer;

  const renderMediaList = files => {
    if (!mediaListEl) return;
    mediaListEl.innerHTML = '';
    if (!Array.isArray(files) || files.length === 0) {
      if (mediaEmptyEl) mediaEmptyEl.hidden = false;
      return;
    }
    if (mediaEmptyEl) mediaEmptyEl.hidden = true;
    files.forEach(file => {
      const li = document.createElement('li');
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'uk-button uk-button-text uk-text-left uk-width-1-1';
      const path = file.path || file.url || '';
      btn.dataset.path = path;
      const displayName = file.name || path;
      btn.textContent = path ? `${displayName} (${path})` : displayName;
      li.append(btn);
      mediaListEl.append(li);
    });
  };

  const setMediaLoadingState = state => {
    if (!mediaListEl) return;
    if (state) {
      mediaListEl.innerHTML = '<li class="uk-text-center">Lade Medien...</li>';
      if (mediaEmptyEl) mediaEmptyEl.hidden = true;
    }
  };

  const loadMedia = async (search = '') => {
    if (!mediaListEl || mediaLoading) return;
    mediaLoading = true;
    setMediaLoadingState(true);
    try {
      const query = search ? `&search=${encodeURIComponent(search)}` : '';
      const response = await apiFetch(`/admin/media/files?scope=global&perPage=100${query}`, {
        headers: { Accept: 'application/json' }
      });
      if (!response.ok) throw new Error('media-load-failed');
      const payload = await response.json().catch(() => ({}));
      mediaFiles = Array.isArray(payload.files) ? payload.files : [];
      mediaLoaded = true;
      renderMediaList(mediaFiles);
    } catch (error) {
      mediaFiles = [];
      renderMediaList(mediaFiles);
      notify('Medien konnten nicht geladen werden', 'danger');
      console.error(error);
    } finally {
      mediaLoading = false;
    }
  };

  if (mediaListEl) {
    mediaListEl.addEventListener('click', event => {
      const target = event.target.closest('button[data-path]');
      if (!target) return;
      const path = target.dataset.path || '';
      if (path && faviconInput) {
        faviconInput.value = path;
        faviconInput.dispatchEvent(new Event('input'));
      }
      if (mediaModal) {
        mediaModal.hide();
      }
    });
  }

  if (mediaSearchInput) {
    mediaSearchInput.addEventListener('input', () => {
      if (mediaSearchTimer) {
        clearTimeout(mediaSearchTimer);
      }
      const value = mediaSearchInput.value || '';
      mediaSearchTimer = setTimeout(() => {
        loadMedia(value.trim());
      }, 250);
    });
  }

  if (mediaButton) {
    mediaButton.addEventListener('click', async () => {
      if (mediaModal && mediaListEl) {
        if (!mediaLoaded) {
          await loadMedia('');
        } else {
          renderMediaList(mediaFiles);
        }
        if (mediaSearchInput) {
          mediaSearchInput.value = '';
        }
        mediaModal.show();
        return;
      }

      try {
        const response = await apiFetch('/admin/media/files?scope=global&perPage=100', {
          headers: { Accept: 'application/json' }
        });
        if (!response.ok) throw new Error('media-load-failed');
        const payload = await response.json().catch(() => ({}));
        const files = Array.isArray(payload.files) ? payload.files : [];
        const paths = files.map(item => item.path || item.url || '').filter(Boolean);
        const suggestion = paths[0] || '';
        const entered = window.prompt('Pfad des Favicons eingeben', suggestion);
        if (entered && faviconInput) {
          faviconInput.value = entered.trim();
          faviconInput.dispatchEvent(new Event('input'));
        }
      } catch (error) {
        notify('Medien konnten nicht geladen werden', 'danger');
        console.error(error);
      }
    });
  }

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
      ogImage: `${ctx.baseUrl}/uploads/seo/social-preview.jpg`,
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
      "url": "${ctx.baseUrl}/uploads/branding/logo.png"
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
    'future-is-green': ctx => ({
      metaTitle: 'Future is Green – Urbane Logistik für lebenswerte Städte',
      metaDescription:
        'Future is Green orchestriert Mikro-Hubs, lokale Händler:innen und E-Cargobikes für emissionsfreie Same-Day-Lieferungen in deiner Stadt.',
      slug: '/future-is-green',
      canonical: `${ctx.baseUrl}/future-is-green`,
      robots: 'index, follow',
      ogTitle: 'Future is Green – Nachhaltige City-Logistik',
      ogDescription:
        'Mit Mikro-Hubs, E-Cargobikes und digitalem Routing reduziert Future is Green Verkehr, Lärm und CO₂ in Innenstädten.',
      ogImage: `${ctx.baseUrl}/uploads/seo/social-preview.jpg`,
      schema: `{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Future is Green",
  "url": "${ctx.baseUrl}/future-is-green",
  "description": "Future is Green verbindet Mikro-Hubs, lokale Händler:innen und E-Cargobikes zu einer emissionsfreien City-Logistik.",
  "areaServed": "Stadtquartiere, urbane Regionen",
  "offers": {
    "@type": "Service",
    "name": "Urbane Logistik",
    "areaServed": "Innenstadt",
    "description": "Letzte Meile mit Cargo-Bikes, Same-Day-Lieferfenster und transparente CO₂-Bilanzen für Kommunen und Händler:innen."
  },
  "sameAs": [
    "https://www.linkedin.com/company/future-is-green",
    "https://www.instagram.com/futureisgreen.city"
  ]
 }`,
      hreflang: `<link rel="alternate" href="${ctx.baseUrl}/future-is-green" hreflang="de" />`,
      domain: ctx.domain
    }),
    calserver: ctx => ({
      metaTitle: 'calServer – Kalibrier- & Prüfmittelmanagement',
      metaDescription:
        'calServer digitalisiert Kalibrier- und Prüfmittelverwaltung: Geräteakten, Terminplanung und Workflows in einer Plattform – Hosting & Support in Deutschland.',
      slug: '/calserver',
      canonical: `${ctx.baseUrl}/calserver`,
      robots: 'index, follow',
      ogTitle: 'calServer – Plattform für Kalibrier- & Prüfmittelteams',
      ogDescription:
        'Überwachen Sie Prüfmittel, Kalibrierfristen, Serviceaufträge und Dokumentation zentral. calServer liefert Workflows, Erinnerungen und Hosting in Deutschland.',
      ogImage: `${ctx.baseUrl}/uploads/calserver-module-device-management.webp`,
      schema: `{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "calServer",
  "applicationCategory": "BusinessApplication",
  "operatingSystem": "Web",
  "url": "${ctx.baseUrl}/calserver",
  "description": "calServer digitalisiert Kalibrier- und Prüfmittelverwaltung für Teams – inklusive Geräteakten, Terminplanung, Workflows und Dokumentation.",
  "provider": {
    "@type": "Organization",
    "name": "calServer",
    "url": "${ctx.baseUrl}/calserver"
  },
  "sameAs": [
    "https://calserver.de",
    "https://calserver.com"
  ]
}`,
      hreflang: `<link rel="alternate" href="${ctx.baseUrl}/calserver" hreflang="de" />\n<link rel="alternate" href="${ctx.baseUrl}/calserver?lang=en" hreflang="en" />`,
      domain: ctx.domain
    }),
    'fluke-metcal': ctx => ({
      metaTitle: 'FLUKE MET/CAL mit calServer – sicher migrieren, sauber integrieren',
      metaDescription:
        'Vom MET/TRACK-Altsystem ohne Stillstand in den calServer: Hybridbetrieb mit METTEAM, Guardband-Reports sowie Hosting & SSO in Deutschland.',
      slug: '/fluke-metcal',
      canonical: `${ctx.baseUrl}/fluke-metcal`,
      robots: 'index, follow',
      ogTitle: 'FLUKE MET/CAL mit calServer – Migration ohne Stillstand',
      ogDescription:
        'calServer orchestriert MET/CAL, MET/TRACK und METTEAM: Freeze-Fenster, Delta-Sync und auditfeste Guardband-Reports halten den Betrieb am Laufen.',
      ogImage: `${ctx.baseUrl}/uploads/calserver-module-device-management.webp`,
      schema: `{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "name": "FLUKE MET/CAL Migration mit calServer",
  "url": "${ctx.baseUrl}/fluke-metcal",
  "description": "Sicherer MET/TRACK-Umstieg in den calServer – mit METTEAM-Hybridbetrieb, Guardband-Reports und dokumentierten Prozessen.",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Wie gelingt der Umstieg ohne Stillstand?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "calServer plant Freeze-Fenster, Delta-Sync und Dry-Runs, damit MET/CAL produktiv bleibt, während Daten übernommen werden."
      }
    },
    {
      "@type": "Question",
      "name": "Wie bleibt METTEAM eingebunden?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Bidirektionale Synchronisation, Guardband-Logik und klare Rollenmodelle sichern einen durchgängigen Hybridbetrieb."
      }
    },
    {
      "@type": "Question",
      "name": "Sind Zertifikate auditfest?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Guardband-Reports, Änderungsjournal und Hosting in Deutschland liefern DAkkS-taugliche Nachweise für Audits."
      }
    }
  ]
 }`,
      hreflang: `<link rel="alternate" href="${ctx.baseUrl}/fluke-metcal" hreflang="de" />\n<link rel="alternate" href="${ctx.baseUrl}/fluke-metcal?lang=en" hreflang="en" />`,
      domain: ctx.domain
    }),
    'calserver-maintenance': ctx => ({
      metaTitle: 'calHelp – Wartungsarbeiten',
      metaDescription:
        'Wir führen geplante Wartungsarbeiten für dein Webprojekt durch. Erfahre hier Status-Updates, den Wartungsfahrplan und erreichbare Support-Kanäle.',
      slug: '/calserver-maintenance',
      canonical: `${ctx.baseUrl}/calserver-maintenance`,
      robots: 'noindex, follow',
      ogTitle: 'calHelp – Wartung & Statusinformationen',
      ogDescription:
        'Geplante Wartung von calHelp mit Live-Status, Fahrplan und Support-Kontakten. Melde dich für Updates an oder kontaktiere unser Team.',
      ogImage: `${ctx.baseUrl}/uploads/calserver-module-device-management.webp`,
      schema: `{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": "calHelp Maintenance",
  "url": "${ctx.baseUrl}/calserver-maintenance",
  "description": "Aktueller Wartungsstatus, Wartungsfahrplan und Support-Kontakt von calHelp für dein Webprojekt.",
  "inLanguage": "de",
  "isPartOf": {
    "@type": "WebSite",
    "name": "calHelp",
    "url": "${ctx.baseUrl}/"
  }
 }`,
      hreflang: `<link rel="alternate" href="${ctx.baseUrl}/calserver-maintenance" hreflang="de" />\n<link rel="alternate" href="${ctx.baseUrl}/calserver-maintenance?lang=en" hreflang="en" />`,
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
      ogImage: `${ctx.baseUrl}/uploads/seo/social-preview.jpg`,
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
          faviconPath: config.faviconPath ?? '',
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

  document.addEventListener('marketing-page:created', event => {
    const detail = event.detail || {};
    const pageIdRaw = detail.id;
    const slug = (detail.slug || '').trim();
    if (pageIdRaw == null || slug === '') {
      return;
    }
    const id = String(pageIdRaw);
    const title = detail.title || '';

    if (pageSelect) {
      const hasOption = Array.from(pageSelect.options).some(option => option.value === id);
      if (!hasOption) {
        const option = document.createElement('option');
        option.value = id;
        option.dataset.slug = slug;
        option.dataset.title = title;
        option.textContent = buildOptionLabel(slug, title);
        pageSelect.append(option);
      }
    }

    pageConfigs[id] = {
      pageId: detail.id,
      metaTitle: '',
      metaDescription: '',
      slug,
      domain: '',
      canonicalUrl: '',
      robotsMeta: '',
      ogTitle: '',
      ogDescription: '',
      ogImage: '',
      faviconPath: '',
      schemaJson: '',
      hreflang: ''
    };

    pageMeta[id] = {
      slug,
      title: title || '',
      domains: []
    };
  });

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

  if (aiButton) {
    aiButton.addEventListener('click', async () => {
      const activeId = getActivePageId();
      if (!activeId) return;
      if (!aiImportUrl) {
        notify(aiMessages.error, 'danger');
        return;
      }

      if (aiAbortController) {
        aiAbortController.abort();
      }
      aiAbortController = new AbortController();
      setAiLoading(true);

      const slug = pageMeta[activeId]?.slug || pageConfigs[activeId]?.slug || '';
      const title = pageMeta[activeId]?.title || '';
      const domain = determinePrimaryDomain(activeId);

      try {
        const response = await apiFetch(aiImportUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json'
          },
          body: JSON.stringify({
            pageId: Number(activeId),
            slug,
            title,
            domain
          }),
          signal: aiAbortController.signal
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload || !payload.config) {
          notify(payload?.error || aiMessages.error, 'danger');
          return;
        }

        const config = payload.config;
        const merged = {
          ...pageConfigs[activeId],
          ...config,
          pageId: Number(activeId)
        };

        const normalizedDomain = normalizeDomainValue(merged.domain || domain);
        if (normalizedDomain) {
          merged.domain = normalizedDomain;
          ensureMetaDomains(activeId, normalizedDomain);
        }

        pageConfigs[activeId] = merged;
        applyConfig(merged);
        notify(aiMessages.success, 'success');
      } catch (error) {
        if (error?.name !== 'AbortError') {
          notify(aiMessages.error, 'danger');
        }
      } finally {
        setAiLoading(false);
        aiAbortController = null;
      }
    });
  }

  form.addEventListener('submit', e => {
    e.preventDefault();
    if (!saveUrl) {
      notify('Fehler beim Speichern', 'danger');
      return;
    }
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
    apiFetch(saveUrl, {
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
            faviconPath: data.config.faviconPath ?? '',
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
