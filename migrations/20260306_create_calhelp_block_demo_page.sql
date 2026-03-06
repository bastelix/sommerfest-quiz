-- CalHelp demo page showcasing all block types (draft)

INSERT INTO namespaces (namespace, label, is_active)
VALUES ('calhelp', 'calHelp', TRUE)
ON CONFLICT (namespace) DO NOTHING;

INSERT INTO pages (namespace, slug, title, content, type, status, language, is_startpage, content_source)
VALUES (
  'calhelp',
  'blocks-demo',
  'CalHelp – Blocks Demo (Draft)',
  $CONTENT${
  "id": "blocks-demo",
  "meta": {
    "source": "migration",
    "notes": "Draft demo page: every block type filled with CalHelp-themed sample content.",
    "reviewed": false
  },
  "blocks": [
    {
      "id": "hero-1",
      "type": "hero",
      "variant": "centered_cta",
      "data": {
        "eyebrow": "CalHelp",
        "headline": "Kalibrier-Workflows, die wirklich funktionieren.",
        "subheadline": "Von MET/CAL bis QA: CalHelp bringt Struktur in Inventar, Fristen und Dokumente – ohne Overkill.",
        "cta": {
          "primary": { "label": "Demo anfragen", "href": "#kontakt" },
          "secondary": { "label": "Funktionen ansehen", "href": "#funktionen" }
        }
      },
      "tokens": { "background": "default", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "proof-1",
      "type": "proof",
      "variant": "metric-callout",
      "data": {
        "title": "Proof / Metrics",
        "metrics": [
          { "id": "p1", "value": "99,9%", "label": "Uptime" },
          { "id": "p2", "value": ">15", "label": "Jahre Erfahrung" },
          { "id": "p3", "value": "1.000+", "label": "Trainings-Teilnehmende" }
        ]
      },
      "tokens": { "background": "muted", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "stat-1",
      "type": "stat_strip",
      "variant": "cards",
      "data": {
        "metrics": [
          { "id": "s1", "value": "Automatisch", "label": "Fristen & Eskalationen" },
          { "id": "s2", "value": "Sauber", "label": "Doku & Versionierung" },
          { "id": "s3", "value": "Planbar", "label": "Team-Workflows" }
        ],
        "marquee": ["DSGVO", "Hosting DE", "API-ready", "Audit-Logs"]
      },
      "tokens": { "background": "default", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "rich-1",
      "type": "rich_text",
      "variant": "prose",
      "data": {
        "body": "<h2 id=\"funktionen\">Warum CalHelp?</h2><p>Diese Seite ist eine <strong>Demo</strong> und zeigt alle Blocktypen einmal sinnvoll befüllt.</p><ul><li>Kalibrier- & Inventar-Workflows</li><li>Dokumente, Zertifikate, Historie</li><li>Integrationen (z.B. MET/CAL)</li></ul>",
        "alignment": "start"
      },
      "tokens": { "background": "default", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "features-1",
      "type": "feature_list",
      "variant": "detailed-cards",
      "data": {
        "eyebrow": "Features",
        "title": "Alles an einem Platz",
        "lead": "Die wichtigsten Bausteine für Kalibrierteams – pragmatisch statt fancy.",
        "items": [
          { "id": "f1", "title": "Geräteakten", "description": "Stammdaten, Dokumente, Historie – strukturiert und auffindbar." },
          { "id": "f2", "title": "Fristen & Erinnerungen", "description": "Automatische Erinnerungen, Eskalationen und Planungsübersicht." },
          { "id": "f3", "title": "Zertifikate & DMS", "description": "Versionierung, PDFs, Zuordnung – ohne Dateichaos." }
        ]
      },
      "tokens": { "background": "muted", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "steps-1",
      "type": "process_steps",
      "variant": "timeline",
      "data": {
        "title": "So läuft’s ab",
        "summary": "Ein typischer CalHelp-Flow – vom Ist-Stand bis zum produktiven Betrieb.",
        "steps": [
          { "id": "st1", "title": "Analyse", "description": "Prozesse, Datenquellen und Fristenlogik aufnehmen." },
          { "id": "st2", "title": "Setup", "description": "Namespaces, Rollen, Vorlagen, Strukturen." },
          { "id": "st3", "title": "Go-Live", "description": "Migration/Sync, Schulung, Feinschliff." }
        ]
      },
      "tokens": { "background": "default", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "info-1",
      "type": "info_media",
      "variant": "image-right",
      "data": {
        "body": "<h3>Info + Media</h3><p>Dieser Block ist ideal für kurze Erklärungen neben einem Screenshot oder Diagramm.</p>",
        "media": { "imageId": "demo-image", "alt": "Demo" }
      },
      "tokens": { "background": "surface", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "audience-1",
      "type": "audience_spotlight",
      "variant": "tabs",
      "data": {
        "title": "Für wen ist das?",
        "tabs": [
          { "id": "a1", "label": "Kalibrierlabor", "body": "Fristen, Zertifikate, Geräteakten." },
          { "id": "a2", "label": "Industrie", "body": "Asset-Management, Compliance, Audits." },
          { "id": "a3", "label": "Service", "body": "Aufträge, SLAs, Dokumente." }
        ]
      },
      "tokens": { "background": "default", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "faq-1",
      "type": "faq",
      "variant": "accordion",
      "data": {
        "title": "FAQ",
        "items": [
          { "id": "q1", "question": "Ist das nur für MET/CAL?", "answer": "Nein. Integrationen sind optional." },
          { "id": "q2", "question": "On-Prem möglich?", "answer": "Je nach Setup / Compliance-Anforderungen." }
        ]
      },
      "tokens": { "background": "muted", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "testimonial-1",
      "type": "testimonial",
      "variant": "slider",
      "data": {
        "title": "Stimmen",
        "subtitle": "Beispielzitate (Demo)",
        "quotes": [
          { "id": "t1", "quote": "Endlich sind Fristen und Doku nicht mehr ‘irgendwo’.", "author": { "name": "QM", "role": "Labor" } },
          { "id": "t2", "quote": "Die Struktur spart uns jeden Monat Stunden.", "author": { "name": "Leitung", "role": "Kalibrierteam" } }
        ]
      },
      "tokens": { "background": "default", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "package-1",
      "type": "package_summary",
      "variant": "comparison-cards",
      "data": {
        "title": "Pakete (Demo)",
        "subtitle": "Beispielhafte Darstellung – Inhalte sind Platzhalter.",
        "plans": [
          { "id": "starter", "title": "Starter", "description": "Klein anfangen.", "features": ["Basis-Setup", "Standard-Workflows"], "primaryCta": { "label": "Anfragen", "href": "#kontakt" } },
          { "id": "pro", "badge": "Beliebt", "title": "Pro", "description": "Mehr Automatisierung.", "features": ["Erinnerungen", "DMS"], "primaryCta": { "label": "Anfragen", "href": "#kontakt" } },
          { "id": "enterprise", "title": "Enterprise", "description": "On-Prem / SSO.", "features": ["SSO", "SLA"], "primaryCta": { "label": "Sprechen", "href": "#kontakt" } }
        ]
      },
      "tokens": { "background": "surface", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "content-slider-1",
      "type": "content_slider",
      "variant": "words",
      "data": {
        "title": "Content Slider",
        "items": [
          { "id": "cs1", "headline": "Planbar", "body": "Fristen & Kalender im Blick." },
          { "id": "cs2", "headline": "Nachvollziehbar", "body": "Historie & Audit-Trail." }
        ]
      },
      "tokens": { "background": "default", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "case-1",
      "type": "case_showcase",
      "variant": "tabs",
      "data": {
        "title": "Case Showcase",
        "cases": [
          { "id": "c1", "label": "Migration", "body": "Datenübernahme ohne Stillstand." },
          { "id": "c2", "label": "Audit", "body": "Doku und Nachweise auf Knopfdruck." }
        ]
      },
      "tokens": { "background": "muted", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "system-module-1",
      "type": "system_module",
      "variant": "switcher",
      "data": {
        "title": "System Module",
        "items": [
          { "id": "sm1", "label": "Inventar", "body": "Assets, Standorte, Status." },
          { "id": "sm2", "label": "Kalibrierung", "body": "Messwerte, Zertifikate, Fristen." }
        ]
      },
      "tokens": { "background": "default", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "event-1",
      "type": "event_highlight",
      "variant": "card",
      "data": {
        "title": "Event Highlight",
        "items": [
          { "id": "e1", "title": "Training", "date": "2026-04-01", "body": "MET/CAL Deep Dive (Demo)." }
        ]
      },
      "tokens": { "background": "surface", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "news-1",
      "type": "latest_news",
      "variant": "cards",
      "data": {
        "title": "Latest News",
        "items": [
          { "id": "n1", "title": "Release", "body": "Neue Demo-API v1 verfügbar.", "href": "/m/calhelp" }
        ]
      },
      "tokens": { "background": "default", "spacing": "normal", "width": "normal" }
    },

    {
      "id": "cta-1",
      "type": "cta",
      "variant": "full_width",
      "data": {
        "title": "Kontakt",
        "body": "Wenn du willst, mache ich aus dieser Demo eine echte Landingpage.",
        "primary": { "label": "Kontakt aufnehmen", "href": "mailto:info@calhelp.de" },
        "secondary": { "label": "Zurück nach oben", "href": "#top" }
      },
      "tokens": { "background": "primary", "spacing": "normal", "width": "normal" }
    }
  ]
}$CONTENT$,
  'marketing',
  'draft',
  'de',
  FALSE,
  'migration:blocks-demo'
)
ON CONFLICT (namespace, slug) DO UPDATE SET
  title = EXCLUDED.title,
  content = EXCLUDED.content,
  type = EXCLUDED.type,
  status = EXCLUDED.status,
  language = EXCLUDED.language,
  is_startpage = EXCLUDED.is_startpage,
  content_source = EXCLUDED.content_source,
  updated_at = CURRENT_TIMESTAMP;
