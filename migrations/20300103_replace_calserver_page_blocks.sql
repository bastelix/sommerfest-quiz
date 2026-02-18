-- Replace calServer marketing page with CMS block-based content.
-- Updates page content (blocks JSON), namespace custom CSS, and design tokens.

-- ── 1. Update page content with new 10-block structure ──

UPDATE pages
SET content = $CONTENT${
  "meta": {
    "namespace": "calserver",
    "slug": "calserver",
    "title": "calServer – Kalibrier- & Prüfmittelmanagement",
    "schemaVersion": "block-contract-v1"
  },
  "blocks": [
    {
      "id": "hero",
      "type": "hero",
      "variant": "stat_tiles",
      "meta": {
        "sectionStyle": {
          "layout": "full",
          "intent": "hero",
          "background": {
            "mode": "color",
            "colorToken": "secondary"
          }
        }
      },
      "data": {
        "eyebrow": "Gehostet in Deutschland",
        "eyebrowAsTag": true,
        "headline": "Mehr Überblick, weniger Aufwand.",
        "subheadline": "Geräte, Termine und Dokumente – organisiert an einem Ort. calServer strukturiert die Kalibrier- und Inventarverwaltung für Ihr gesamtes Team.",
        "cta": {
          "primary": {
            "label": "Jetzt testen",
            "href": "#kontakt"
          },
          "secondary": {
            "label": "Demo buchen",
            "href": "#kontakt"
          }
        },
        "statTiles": [
          { "value": "1.668", "label": "umgesetzte Kund:innen-Wünsche" },
          { "value": "99,9 %", "label": "Systemverfügbarkeit" },
          { "value": "> 15", "label": "Jahre am Markt" },
          { "value": "12", "label": "integrierte Module" }
        ],
        "provenExpert": {
          "rating": "4,91 / 5",
          "recommendation": "100 % Empfehlung",
          "reviewCount": "63",
          "reviewSource": "ProvenExpert"
        }
      }
    },
    {
      "id": "trust-bar",
      "type": "stat_strip",
      "variant": "trust_bar",
      "meta": {
        "sectionStyle": {
          "layout": "normal",
          "intent": "plain"
        }
      },
      "data": {
        "items": [
          { "icon": "lock", "label": "DSGVO-konform" },
          { "icon": "location", "label": "Hosting in DE" },
          { "icon": "cog", "label": "REST-API & Webhooks" },
          { "icon": "check", "label": "ISO 27001" },
          { "icon": "world", "label": "Made in Germany" }
        ]
      }
    },
    {
      "id": "features",
      "type": "feature_list",
      "variant": "grid-bullets",
      "meta": {
        "anchor": "funktionen",
        "sectionStyle": {
          "layout": "normal",
          "intent": "content"
        }
      },
      "data": {
        "eyebrow": "Funktionen",
        "title": "Alles, was Ihr Labor braucht",
        "subtitle": "12 Module – von Inventar über Kalibrierung bis zum Kundenportal.",
        "columns": 3,
        "items": [
          {
            "id": "geraete",
            "icon": "desktop",
            "title": "Geräteverwaltung",
            "description": "Stammdaten, Dokumente und komplette Historie in einer Akte – ob 100 oder 100.000 Geräte."
          },
          {
            "id": "fristen",
            "icon": "clock",
            "title": "Fristen & Erinnerungen",
            "description": "Automatische Erinnerungslogik, Eskalationspfade und übersichtliche Planungs-Dashboards."
          },
          {
            "id": "dms",
            "icon": "file-text",
            "title": "DMS & Zertifikate",
            "description": "Revisionssichere Ablage mit Versionierung, Auto-Zuordnung und integriertem PDF-Viewer."
          },
          {
            "id": "messwerte",
            "icon": "bolt",
            "title": "Kalibrier- & Messwerte",
            "description": "Messwerte erfassen, importieren und DAkkS-taugliche Berichte per Klick erzeugen."
          },
          {
            "id": "auftraege",
            "icon": "cart",
            "title": "Aufträge & Abrechnung",
            "description": "Angebot → Auftrag → Rechnung in einem Flow, inklusive SLAs und Eskalation."
          },
          {
            "id": "metcal",
            "icon": "refresh",
            "title": "MET/CAL-Sync",
            "description": "Bidirektionale Synchronisation mit Fluke MET/TEAM & MET/CAL – Migration ohne Stillstand."
          }
        ]
      }
    },
    {
      "id": "product-screenshots",
      "type": "info_media",
      "variant": "switcher",
      "meta": {
        "anchor": "produkt",
        "sectionStyle": {
          "layout": "normal",
          "intent": "content"
        }
      },
      "data": {
        "eyebrow": "Produkt",
        "title": "calServer in Aktion",
        "subtitle": "Ausgewählte Ansichten aus der Anwendung.",
        "items": [
          {
            "id": "geraeteuebersicht",
            "title": "Geräteübersicht",
            "description": "Alle Prüfmittel auf einen Blick – mit Stammdaten, Status, Kalibrierhistorie und verknüpften Dokumenten. Filtern nach Standort, Fälligkeit oder Gerätetyp."
          },
          {
            "id": "kalibrier-dashboard",
            "title": "Kalibrier-Dashboard",
            "description": "Fälligkeiten, offene Aufgaben und KPIs im Überblick. Eskalationsstufen und Ampellogik zeigen sofort, wo Handlungsbedarf besteht."
          },
          {
            "id": "auftragssteuerung",
            "title": "Auftragssteuerung",
            "description": "Vom Angebot über den Auftrag bis zur Rechnung – ein durchgängiger Flow mit Status-Tracking, SLAs und automatischer Eskalation."
          },
          {
            "id": "dms-zertifikate",
            "title": "DMS & Zertifikate",
            "description": "Revisionssichere Ablage mit Versionierung und Auto-Zuordnung. DAkkS-konforme Kalibrierscheine direkt aus dem System erzeugen."
          },
          {
            "id": "self-service-portal",
            "title": "Self-Service-Portal",
            "description": "Kunden sehen ihre Geräte, den Bearbeitungsstand und Dokumente – ohne Anruf, ohne E-Mail. Entlastet das Team und steigert die Servicequalität."
          }
        ]
      }
    },
    {
      "id": "referenzen",
      "type": "audience_spotlight",
      "variant": "tabs",
      "meta": {
        "anchor": "referenzen",
        "sectionStyle": {
          "layout": "normal",
          "intent": "feature",
          "background": {
            "mode": "color",
            "colorToken": "muted"
          }
        }
      },
      "data": {
        "title": "Vertrauen aus der Praxis",
        "subtitle": "Ausgewählte Referenzen – vom Kalibrierlabor über Industriekonzerne bis zum öffentlichen Versorger.",
        "cases": [
          {
            "id": "thermo-fisher",
            "title": "Thermo Fisher Scientific",
            "badge": "Kalibrierlabor",
            "lead": "Globaler Life-Science-Konzern · EMEA-weites Deployment",
            "body": "<h5><strong>Projektnutzung</strong></h5><p>EMEA-weite Leihgeräte-Verwaltung und lückenlose Geräteakten über mehrere Standorte. Bidirektionale Synchronisation mit Fluke MET/TEAM für einen konsistenten Datenbestand.</p><h5><strong>Ergebnis</strong></h5><p>Zentraler Überblick über den gesamten EMEA-Gerätebestand, eliminierte Datensilos zwischen MET/TEAM und calServer, revisionssichere Nachverfolgung.</p>"
          },
          {
            "id": "zf",
            "title": "ZF",
            "badge": "Industrielabor",
            "lead": "Automobilzulieferer · Enterprise-Infrastruktur",
            "body": "<h5><strong>Projektnutzung</strong></h5><p>API-basierte Messwert-Erfassung auf Kubernetes-Infrastruktur mit SSO-Anbindung (Azure AD). Bidirektionale MET/TEAM-Synchronisation für nahtlosen Datenaustausch.</p><h5><strong>Ergebnis</strong></h5><p>Vollständige Integration in die bestehende Enterprise-IT-Landschaft, automatisierte Messwert-Pipelines und Single-Sign-On für alle Anwender.</p>"
          },
          {
            "id": "vde",
            "title": "VDE",
            "badge": "Qualitätsmanagement",
            "lead": "Verband der Elektrotechnik · Normungsinstitut",
            "body": "<h5><strong>Projektnutzung</strong></h5><p>Agile Auftragssteuerung mit integriertem Dokumentenmanagement. calServer als zentrales Intranet und Ticketing-Plattform für die QM-Abteilung.</p><h5><strong>Ergebnis</strong></h5><p>Transparente Auftragsprozesse, revisionssicheres DMS und ein zentraler Hub für alle QM-relevanten Workflows – jenseits der klassischen Kalibrierung.</p>"
          },
          {
            "id": "ifm",
            "title": "ifm electronic",
            "badge": "Kalibrierlabor",
            "lead": "Sensorhersteller · 2 Standorte",
            "body": "<h5><strong>Projektnutzung</strong></h5><p>Standortübergreifendes Ticket-Management für Störungen und CAPA-Prozesse. Bidirektionale Synchronisation mit MET/TEAM und MET/CAL.</p><h5><strong>Ergebnis</strong></h5><p>Einheitliche Störungsbearbeitung über beide Standorte, nachvollziehbare CAPA-Dokumentation und konsistenter Datenbestand mit dem Fluke-Ökosystem.</p>"
          },
          {
            "id": "berliner-stadtwerke",
            "title": "Berliner Stadtwerke",
            "badge": "Assetmanagement",
            "lead": "Kommunaler Energieversorger · Erneuerbare Energien",
            "body": "<h5><strong>Projektnutzung</strong></h5><p>Projekt- und Wartungsmanagement für dezentrale erneuerbare Energieanlagen (PV, Speicher). calServer als zentrale Plattform jenseits der klassischen Kalibrierung.</p><h5><strong>Ergebnis</strong></h5><p>Strukturierte Wartungsplanung und Projektdokumentation für verteilte Assets – Nachweis, dass calServer auch außerhalb der Kalibrierung funktioniert.</p>"
          },
          {
            "id": "ksw",
            "title": "KSW",
            "badge": "Kalibrierlabor",
            "lead": "Kalibrierdienstleister · End-to-End-Prozess",
            "body": "<h5><strong>Projektnutzung</strong></h5><p>Kompletter Workflow vom Wareneingang über die Laborbearbeitung bis zur automatisierten Rechnungsstellung – alles in calServer abgebildet.</p><h5><strong>Ergebnis</strong></h5><p>Durchgängig digitaler Auftragsprozess ohne Medienbrüche, automatisierte Abrechnung und vollständige Nachverfolgbarkeit aller Kalibrieraufträge.</p>"
          },
          {
            "id": "teramess",
            "title": "TERAMESS",
            "badge": "Kalibrierlabor",
            "lead": "Kalibrierdienstleister · DAkkS-akkreditiert",
            "body": "<h5><strong>Projektnutzung</strong></h5><p>DAkkS-konforme Kalibrierscheine direkt aus calServer in der Cloud erstellen. Audit-sichere Dokumentation über den gesamten Kalibrierprozess.</p><h5><strong>Ergebnis</strong></h5><p>Normkonforme Zertifikate auf Knopfdruck, durchgängig digitale Prozesskette und jederzeitige Audit-Bereitschaft – vollständig cloudbasiert.</p>"
          },
          {
            "id": "systems-engineering",
            "title": "Systems Engineering",
            "badge": "Kalibrierlabor",
            "lead": "Kalibrierdienstleister · Auftragssteuerung",
            "body": "<h5><strong>Projektnutzung</strong></h5><p>calServer als steuerndes Herzstück der gesamten Auftragsbearbeitung – von der Anfrage über die Kalibrierung bis zur Auslieferung und Dokumentation.</p><h5><strong>Ergebnis</strong></h5><p>Zentrale Steuerung aller Labortätigkeiten, deutlich reduzierter Verwaltungsaufwand und ein einheitlicher Prozess für alle Kalibrieraufträge.</p>"
          }
        ]
      }
    },
    {
      "id": "testimonials",
      "type": "testimonial",
      "variant": "slider",
      "meta": {
        "anchor": "stimmen",
        "sectionStyle": {
          "layout": "normal",
          "intent": "content"
        }
      },
      "data": {
        "title": "Was unsere Kunden sagen",
        "subtitle": "Verifiziert auf ProvenExpert – 4,91/5 bei 100 % Empfehlung.",
        "quotes": [
          {
            "quote": "Sehr professioneller und individueller Service. Besonders die breitbandige Unterstützung durch die Unabhängigkeit und Erfahrung hat uns sehr weitergeholfen. Klare Empfehlung.",
            "author": { "name": "Dave D.", "role": "" },
            "source": "ProvenExpert",
            "avatarInitials": "DD"
          },
          {
            "quote": "Es gibt keine Alternative als calHelp, denn die Erfahrung und Kompetenz hebt sich einfach ab zu allen anderen in dem Bereich.",
            "author": { "name": "Markus M.", "role": "ZF" },
            "source": "ProvenExpert",
            "avatarInitials": "MM"
          },
          {
            "quote": "Vom ersten Kontakt bis zur letzten Minute wurden unsere Belange zur vollsten Zufriedenheit erfüllt. Eine funktionsfähige Umgebung mit migrierten Daten wurde fristgerecht übergeben.",
            "author": { "name": "Migrationskunde", "role": "" },
            "source": "ProvenExpert",
            "avatarInitials": "MK"
          },
          {
            "quote": "Hervorragendes Seminar, Herr Buske ist sehr professionell. Klare Empfehlung für alle, die sich in der Kalibrierung mit der Metcal-Anwendung weiterentwickeln wollen.",
            "author": { "name": "Gwendolin N.", "role": "" },
            "source": "ProvenExpert",
            "avatarInitials": "GN"
          },
          {
            "quote": "Ein sehr gut strukturierter Kurs, der ein besseres Verständnis zur Funktionalität und Handhabung von MetCal nahegebracht hat. Vielen Dank an Herrn Buske.",
            "author": { "name": "Seminarteilnehmer", "role": "" },
            "source": "ProvenExpert",
            "avatarInitials": "ST"
          }
        ]
      }
    },
    {
      "id": "pricing",
      "type": "package_summary",
      "variant": "comparison-cards",
      "meta": {
        "anchor": "preise",
        "sectionStyle": {
          "layout": "normal",
          "intent": "feature",
          "background": {
            "mode": "color",
            "colorToken": "muted"
          }
        }
      },
      "data": {
        "title": "Transparent & flexibel",
        "subtitle": "Monatliche Laufzeit · 30 Tage Kündigungsfrist · DSGVO-konform",
        "plans": [
          {
            "id": "standard",
            "title": "Standard-Hosting",
            "description": "Cloud in Deutschland – schnell starten.",
            "features": [
              "Inventar-, Kalibrier- & Auftragsverwaltung",
              "Dokumentenmanagement (Basis)",
              "Tägliche Backups, SSL & Subdomain",
              "Rollen & Berechtigungen"
            ],
            "primaryCta": {
              "label": "Anfrage senden",
              "href": "#kontakt"
            }
          },
          {
            "id": "performance",
            "title": "Performance-Hosting",
            "badge": "Beliebt",
            "description": "Mehr Leistung & Spielraum.",
            "features": [
              "Skalierbare Ressourcen & Performance",
              "Alle Module, mehr Speicher",
              "Priorisiertes Monitoring",
              "Team-Workflows & Berechtigungen"
            ],
            "primaryCta": {
              "label": "Anfrage senden",
              "href": "#kontakt"
            }
          },
          {
            "id": "enterprise",
            "title": "Enterprise (On-Prem)",
            "description": "Volle Datenhoheit – Ihr Netzwerk.",
            "features": [
              "On-Prem im eigenen Netzwerk",
              "SSO (Azure / Google)",
              "Individuelle SLAs & Compliance",
              "MET/TEAM-Sync optional"
            ],
            "primaryCta": {
              "label": "Anfrage senden",
              "href": "#kontakt"
            }
          }
        ]
      }
    },
    {
      "id": "founder",
      "type": "rich_text",
      "variant": "prose",
      "meta": {
        "sectionStyle": {
          "layout": "normal",
          "intent": "content",
          "container": {
            "width": "normal",
            "spacing": "normal"
          }
        }
      },
      "data": {
        "body": "<div class=\"uk-card uk-card-default uk-card-body uk-border-rounded\"><div class=\"uk-grid uk-grid-medium uk-flex-middle\" data-uk-grid><div class=\"uk-width-expand@s\"><div class=\"uk-flex uk-flex-middle uk-margin-small-bottom\" style=\"gap:.75rem;\"><span class=\"uk-label\" style=\"background:#1a73e8; font-size:.72rem; letter-spacing:.06em; border-radius:4px; padding:.35rem .85rem; text-transform:uppercase;\">Gründer</span></div><h3 class=\"uk-margin-small-top uk-margin-remove-bottom\">René Buske</h3><p class=\"uk-text-small uk-text-muted uk-margin-small-top\">Über 20 Jahre in der Kalibriertechnik – Bundeswehr, 8 Jahre Fluke MET/CAL-Support beim deutschen Exklusivpartner, Gründer von calHelp (2009). Über 1.000 Seminarteilnehmer, Speaker beim Munich Calibration Day.</p><div class=\"uk-margin-small-top uk-flex uk-flex-wrap\" style=\"gap:.5rem;\"><a class=\"uk-button uk-button-default uk-button-small cs-link-btn\" href=\"https://de.linkedin.com/in/metcaltrainer\" target=\"_blank\" rel=\"noopener\" style=\"border-radius:100px;\"><span data-uk-icon=\"icon: linkedin; ratio: .75;\" class=\"uk-margin-xsmall-right\"></span>LinkedIn</a><a class=\"uk-button uk-button-default uk-button-small cs-link-btn\" href=\"https://www.provenexpert.com/de-de/calhelp/\" target=\"_blank\" rel=\"noopener\" style=\"border-radius:100px;\"><span data-uk-icon=\"icon: star; ratio: .75;\" class=\"uk-margin-xsmall-right\"></span>ProvenExpert</a></div></div><div class=\"uk-width-auto@s uk-visible@m uk-flex uk-flex-center\"><img src=\"/uploads/rene-buske-portrait.png\" alt=\"René Buske\" style=\"width:160px; height:160px; object-fit:cover; border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,.08);\" loading=\"lazy\"></div></div><hr class=\"uk-divider-small uk-margin-top\"><div class=\"uk-text-small uk-text-muted uk-margin-small-bottom\" style=\"font-weight:600;\">Weitere Projekte</div><div class=\"uk-grid uk-grid-small uk-child-width-1-2@s uk-child-width-1-4@m uk-grid-match\" data-uk-grid><div><a href=\"https://calhelp.de\" target=\"_blank\" rel=\"noopener\" class=\"uk-card uk-card-default uk-card-body uk-card-hover uk-border-rounded uk-display-block uk-link-reset\" style=\"padding:.65rem .85rem;\"><div class=\"uk-text-bold uk-text-small\">calHelp.de</div><div class=\"uk-text-meta\" style=\"font-size:.72rem;\">MET/CAL-Seminare &amp; Support</div></a></div><div><a href=\"https://kaaroo.com\" target=\"_blank\" rel=\"noopener\" class=\"uk-card uk-card-default uk-card-body uk-card-hover uk-border-rounded uk-display-block uk-link-reset\" style=\"padding:.65rem .85rem;\"><div class=\"uk-text-bold uk-text-small\">KaaRoo.com</div><div class=\"uk-text-meta\" style=\"font-size:.72rem;\">Digitalisierungs-Dachmarke</div></a></div><div><a href=\"https://webseite.online\" target=\"_blank\" rel=\"noopener\" class=\"uk-card uk-card-default uk-card-body uk-card-hover uk-border-rounded uk-display-block uk-link-reset\" style=\"padding:.65rem .85rem;\"><div class=\"uk-text-bold uk-text-small\">Webseite.Online</div><div class=\"uk-text-meta\" style=\"font-size:.72rem;\">Webentwicklung &amp; Hosting</div></a></div><div><a href=\"https://quizrace.app\" target=\"_blank\" rel=\"noopener\" class=\"uk-card uk-card-default uk-card-body uk-card-hover uk-border-rounded uk-display-block uk-link-reset\" style=\"padding:.65rem .85rem;\"><div class=\"uk-text-bold uk-text-small\">QuizRace.app</div><div class=\"uk-text-meta\" style=\"font-size:.72rem;\">Interaktive Quiz-Plattform</div></a></div></div></div>"
      }
    },
    {
      "id": "closing-cta",
      "type": "cta",
      "variant": "full_width",
      "meta": {
        "anchor": "kontakt",
        "sectionStyle": {
          "layout": "full",
          "intent": "highlight",
          "background": {
            "mode": "color",
            "colorToken": "secondary"
          }
        }
      },
      "data": {
        "title": "Bereit für weniger Aufwand?",
        "body": "Testen Sie calServer 30 Tage kostenlos – oder buchen Sie eine persönliche Demo.",
        "primary": {
          "label": "Kostenlos testen",
          "href": "https://develop.net-cal.com"
        },
        "secondary": {
          "label": "Demo buchen",
          "href": "https://calendly.com/calhelp/calserver-vorstellung"
        }
      }
    },
    {
      "id": "faq",
      "type": "faq",
      "variant": "accordion",
      "meta": {
        "anchor": "faq",
        "sectionStyle": {
          "layout": "normal",
          "intent": "plain"
        }
      },
      "data": {
        "title": "Häufige Fragen",
        "items": [
          {
            "id": "faq-speed",
            "question": "Wie schnell bin ich mit der Cloud-Version startklar?",
            "answer": "In der Regel innerhalb weniger Tage – wir begleiten den Kick-off persönlich."
          },
          {
            "id": "faq-switch",
            "question": "Kann ich zwischen Cloud und On-Premise wechseln?",
            "answer": "Ja, ein Wechsel ist jederzeit möglich. Wir unterstützen bei Migration und Datenübernahme."
          },
          {
            "id": "faq-imports",
            "question": "Welche Datenimporte sind möglich?",
            "answer": "Excel/CSV-Importe, API-Schnittstellen sowie individuelle Integrationen."
          },
          {
            "id": "faq-support",
            "question": "Wie funktioniert der Support?",
            "answer": "Support per E-Mail, Telefon oder Ticketsystem – je nach Paket sogar mit SLA."
          },
          {
            "id": "faq-testdata",
            "question": "Was passiert mit meinen Daten nach dem Test?",
            "answer": "Nach Testende entscheiden Sie: weiter nutzen, exportieren oder löschen lassen – ganz transparent."
          }
        ],
        "followUp": {
          "text": "Noch nicht fündig geworden?",
          "linkLabel": "Weitere Fragen → Kontakt",
          "href": "#contact-form"
        }
      }
    }
  ]
}$CONTENT$,
    content_source = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calserver' AND namespace = 'calserver';

-- ── 2. Ensure config row exists for calserver namespace ──

INSERT INTO config (event_uid)
SELECT 'calserver'
WHERE NOT EXISTS (SELECT 1 FROM config WHERE event_uid = 'calserver');

-- ── 3. Update namespace custom CSS ──

UPDATE config
SET custom_css = $CSS$/* ── calServer page namespace styles (2026-02) ── */

/* ── Typography ── */
[data-namespace="calserver"] {
  --font-family-heading: 'Plus Jakarta Sans', 'Poppins', system-ui, sans-serif;
  --font-family-body: 'Plus Jakarta Sans', 'Poppins', system-ui, sans-serif;
  scroll-behavior: smooth;
}
[data-namespace="calserver"] h1,
[data-namespace="calserver"] h2,
[data-namespace="calserver"] h3,
[data-namespace="calserver"] h4,
[data-namespace="calserver"] .uk-heading-medium,
[data-namespace="calserver"] .uk-heading-small {
  font-family: var(--font-family-heading);
}

/* ── Hero section (navy gradient) ── */
[data-namespace="calserver"] .section[data-section-intent="hero"] {
  background: linear-gradient(170deg, #0b1a2e 0%, #122240 100%) !important;
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .uk-heading-medium {
  color: #fff;
  font-weight: 800;
  font-size: clamp(2.4rem, 5vw, 3.4rem);
  line-height: 1.1;
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .uk-text-lead {
  color: rgba(255, 255, 255, 0.85);
}

/* Stat tiles inside hero */
[data-namespace="calserver"] .cs-stat-tile {
  background: rgba(255, 255, 255, 0.07) !important;
  border: 1px solid rgba(255, 255, 255, 0.10);
  text-align: center;
}
[data-namespace="calserver"] .cs-stat-num {
  font-size: 2.6rem;
  font-weight: 800;
  font-family: var(--font-family-heading);
  color: #58a6ff;
  line-height: 1.1;
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .cs-stat-tile .uk-text-muted {
  color: rgba(255, 255, 255, 0.65) !important;
}

/* Hero CTA buttons */
[data-namespace="calserver"] .section[data-section-intent="hero"] .uk-button-primary {
  background: #1a73e8 !important;
  color: #fff !important;
  border-color: #1a73e8 !important;
  border-radius: 100px;
  padding: 0 2rem;
  font-weight: 600;
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .uk-button-primary:hover {
  background: #1557b0 !important;
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .uk-button-default {
  color: rgba(255, 255, 255, 0.9);
  border-color: rgba(255, 255, 255, 0.25);
  border-radius: 100px;
  padding: 0 2rem;
  font-weight: 600;
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .uk-button-default:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}

/* ── ProvenExpert badge ── */
[data-namespace="calserver"] .cs-proven-expert {
  gap: 0.75rem;
}
[data-namespace="calserver"] .cs-proven-expert__stars {
  display: flex;
  gap: 2px;
}
[data-namespace="calserver"] .cs-star {
  width: 18px;
  height: 18px;
  fill: #eab308;
}
[data-namespace="calserver"] .cs-proven-expert__text .uk-text-small {
  color: rgba(255, 255, 255, 0.9);
}
[data-namespace="calserver"] .cs-proven-expert__text .uk-text-meta {
  color: rgba(255, 255, 255, 0.55);
}

/* ── Trust bar ── */
[data-namespace="calserver"] .section[data-block-variant="trust_bar"] {
  background: var(--global-muted-background, #f8f8f8);
  border-top: 1px solid rgba(0, 0, 0, 0.06);
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}
[data-namespace="calserver"] .section[data-block-variant="trust_bar"] .uk-subnav-divider > li {
  padding-left: 1rem;
}

/* ── Blue icon accent ── */
[data-namespace="calserver"] .cs-blue {
  color: #1a73e8 !important;
}

/* ── Feature cards (grid-bullets) ── */
[data-namespace="calserver"] .section[data-block-type="feature_list"] .uk-card {
  border-top: 3px solid #1a73e8;
  border-radius: 10px;
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}
[data-namespace="calserver"] .section[data-block-type="feature_list"] .uk-card:hover {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

/* ── Product screenshots (info_media:switcher) ── */
[data-namespace="calserver"] .section[data-block-type="info_media"] .uk-subnav > li > a {
  font-weight: 600;
}

/* ── Referenzen (audience_spotlight:tabs) ── */
[data-namespace="calserver"] .section[data-block-type="audience_spotlight"] .uk-card {
  border-radius: 10px;
}

/* ── Testimonial slider ── */
[data-namespace="calserver"] .cs-quote-deco {
  font-size: 4.5rem;
  line-height: 1;
  color: rgba(26, 115, 232, 0.12);
  font-family: Georgia, serif;
  pointer-events: none;
}
[data-namespace="calserver"] .cs-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1a73e8, #58a6ff);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
  letter-spacing: 0.02em;
}
[data-namespace="calserver"] .section[data-block-type="testimonial"] .uk-card {
  border-radius: 10px;
  position: relative;
  overflow: hidden;
}

/* ── Pricing cards ── */
[data-namespace="calserver"] .section[data-block-type="package_summary"] .uk-card {
  border-radius: 10px;
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}
[data-namespace="calserver"] .section[data-block-type="package_summary"] .uk-card:hover {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}
/* Popular plan ring */
[data-namespace="calserver"] .section[data-block-type="package_summary"] .uk-card:has(.uk-label) {
  box-shadow: 0 0 0 2px #1a73e8;
}
[data-namespace="calserver"] .section[data-block-type="package_summary"] .uk-label {
  background: #1a73e8;
  border-radius: 4px;
}
[data-namespace="calserver"] .section[data-block-type="package_summary"] .uk-button-primary {
  border-radius: 100px;
  background: #1a73e8;
  font-weight: 600;
}
[data-namespace="calserver"] .section[data-block-type="package_summary"] .uk-button-primary:hover {
  background: #1557b0;
}

/* ── Founder card ── */
[data-namespace="calserver"] .cs-link-btn {
  border-radius: 100px;
  font-size: 0.82rem;
}

/* ── CTA section (highlight intent – navy gradient) ── */
[data-namespace="calserver"] .section[data-section-intent="highlight"] {
  background: linear-gradient(170deg, #0b1a2e 0%, #122240 100%) !important;
}
[data-namespace="calserver"] .section[data-section-intent="highlight"] .uk-heading-medium {
  color: #fff;
  font-weight: 800;
}
[data-namespace="calserver"] .section[data-section-intent="highlight"] .uk-text-lead {
  color: rgba(255, 255, 255, 0.85);
}
[data-namespace="calserver"] .section[data-section-intent="highlight"] .uk-button-primary {
  background: #1a73e8 !important;
  color: #fff !important;
  border-color: #1a73e8 !important;
  border-radius: 100px;
  padding: 0 2rem;
  font-weight: 600;
}
[data-namespace="calserver"] .section[data-section-intent="highlight"] .uk-button-primary:hover {
  background: #1557b0 !important;
}
[data-namespace="calserver"] .section[data-section-intent="highlight"] .uk-button-default {
  color: rgba(255, 255, 255, 0.9);
  border-color: rgba(255, 255, 255, 0.25);
  border-radius: 100px;
  padding: 0 2rem;
  font-weight: 600;
}
[data-namespace="calserver"] .section[data-section-intent="highlight"] .uk-button-default:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}

/* ── General rounded buttons for calServer ── */
[data-namespace="calserver"] .section .uk-button {
  border-radius: 100px;
}

/* ── Eyebrow tag style (green hosted badge) ── */
[data-namespace="calserver"] .section[data-section-intent="hero"] .hero-eyebrow-tag {
  background: rgba(52, 211, 153, 0.15);
  color: #34d399;
  border-radius: 100px;
  font-size: 0.82rem;
  padding: 0.35rem 1rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: none;
  border: 1px solid rgba(52, 211, 153, 0.3);
}
[data-namespace="calserver"] .section[data-section-intent="hero"] .hero-eyebrow-tag::before {
  content: "";
  display: inline-block;
  width: 14px;
  height: 14px;
  margin-right: 6px;
  background: currentColor;
  -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath fill='currentColor' d='M16.707 5.293a1 1 0 0 1 0 1.414l-8 8a1 1 0 0 1-1.414 0l-4-4a1 1 0 1 1 1.414-1.414L8 12.586l7.293-7.293a1 1 0 0 1 1.414 0z'/%3E%3C/svg%3E") no-repeat center / contain;
  mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath fill='currentColor' d='M16.707 5.293a1 1 0 0 1 0 1.414l-8 8a1 1 0 0 1-1.414 0l-4-4a1 1 0 1 1 1.414-1.414L8 12.586l7.293-7.293a1 1 0 0 1 1.414 0z'/%3E%3C/svg%3E") no-repeat center / contain;
  vertical-align: -2px;
}

/* ── Section header label/eyebrow ── */
[data-namespace="calserver"] .section .uk-text-meta {
  color: #1a73e8;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  font-size: 0.8rem;
}

/* ── Logo blue border ── */
[data-namespace="calserver"] .cs-logo__image {
  border: 2px solid #1a73e8;
  border-radius: 8px;
  padding: 3px;
  display: inline-flex;
  align-items: center;
}

/* ── Navbar CTA button ── */
[data-namespace="calserver"] .cs-nav-cta {
  border-radius: 100px;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  padding: 0 1.2rem;
  margin-right: 0.5rem;
}

/* ── General card radius ── */
[data-namespace="calserver"] .uk-card {
  border-radius: 10px;
}

/* ── FAQ section ── */
[data-namespace="calserver"] .section[data-block-type="faq"] .uk-accordion-title {
  font-weight: 600;
}
$CSS$
WHERE event_uid = 'calserver';

-- ── 4. Update design tokens ──

UPDATE config
SET design_tokens = jsonb_set(
  jsonb_set(
    jsonb_set(
      COALESCE(design_tokens, '{}')::jsonb,
      '{brand,primary}', '"#1a73e8"'
    ),
    '{brand,secondary}', '"#0b1a2e"'
  ),
  '{brand,accent}', '"#58a6ff"'
)
WHERE event_uid = 'calserver';
