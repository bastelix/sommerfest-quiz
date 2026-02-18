-- Create the calServer CMS page directly in the calserver namespace.
-- This migration is idempotent and does not depend on a prior copy from
-- the default namespace.

-- 1. Ensure the calserver namespace exists
INSERT INTO namespaces (namespace, label, is_active)
VALUES ('calserver', 'calServer', TRUE)
ON CONFLICT (namespace) DO NOTHING;

-- 2. Insert (or update) the page in the calserver namespace
INSERT INTO pages (namespace, slug, title, content, type, status, language, is_startpage)
VALUES (
    'calserver',
    'calserver',
    'calServer – Kalibrier- und Inventarverwaltung für Teams',
    $CONTENT${
  "id": "calserver",
  "locale": "de",
  "title": "calServer",
  "meta": {
    "rebuiltFromMigration": true,
    "reviewed": false
  },
  "blocks": [
    {
      "id": "hero",
      "type": "hero",
      "variant": "centered_cta",
      "data": {
        "eyebrow": "Software gehostet in Deutschland",
        "headline": "Mehr Überblick, weniger Aufwand.",
        "subheadline": "Geräte, Termine und Dokumente – organisiert an einem Ort. calServer strukturiert die Kalibrier- und Inventarverwaltung für das ganze Team.",
        "cta": {
          "primary": { "label": "Jetzt testen", "href": "#kontakt" },
          "secondary": { "label": "Demo buchen", "href": "#kontakt" }
        }
      },
      "meta": {
        "sectionStyle": {
          "intent": "hero",
          "layout": "full",
          "background": { "mode": "color", "colorToken": "primary" },
          "container": { "width": "wide", "spacing": "generous" }
        }
      }
    },
    {
      "id": "proven-expert",
      "type": "rich_text",
      "variant": "prose",
      "data": {
        "body": "<div class=\"cs-proven-badge uk-text-center\"><span class=\"uk-text-warning\">&#9733;&#9733;&#9733;&#9733;&#9733;</span> <strong>4,91 / 5</strong> &middot; 100&nbsp;% Empfehlung &middot; 63 Bewertungen auf <a href=\"https://www.provenexpert.com/de-de/calhelp/\" target=\"_blank\" rel=\"noopener\">ProvenExpert</a></div>",
        "alignment": "center"
      },
      "meta": {
        "sectionStyle": {
          "container": { "spacing": "compact" }
        }
      }
    },
    {
      "id": "stats",
      "type": "stat_strip",
      "variant": "cards",
      "data": {
        "metrics": [
          { "id": "m-wishes", "value": "1.668", "label": "umgesetzte Kund:innen-Wünsche" },
          { "id": "m-uptime", "value": "99,9 %", "label": "Systemverfügbarkeit" },
          { "id": "m-years", "value": "> 15", "label": "Jahre am Markt" },
          { "id": "m-modules", "value": "12", "label": "integrierte Module" }
        ],
        "marquee": [
          "DSGVO-konform",
          "Hosting in Deutschland",
          "REST-API & Webhooks",
          "ISO 27001 Infrastruktur",
          "Software Made in Germany"
        ]
      },
      "meta": {
        "sectionStyle": {
          "container": { "spacing": "compact" }
        }
      }
    },
    {
      "id": "features",
      "type": "feature_list",
      "variant": "detailed-cards",
      "data": {
        "eyebrow": "Funktionen",
        "title": "Alles, was Ihr Labor braucht",
        "lead": "12 Module – von Inventar über Kalibrierung bis zum Kundenportal.",
        "items": [
          {
            "id": "feat-geraete",
            "title": "Geräteverwaltung",
            "description": "Stammdaten, Dokumente und Historie in einer Akte – ob 100 oder 100.000 Geräte."
          },
          {
            "id": "feat-fristen",
            "title": "Fristen & Erinnerungen",
            "description": "Automatische Erinnerungslogik, Eskalationspfade und Planungs-Dashboards."
          },
          {
            "id": "feat-dms",
            "title": "DMS & Zertifikate",
            "description": "Revisionssichere Ablage mit Versionierung, Auto-Zuordnung und PDF-Viewer."
          },
          {
            "id": "feat-kalibrier",
            "title": "Kalibrier- & Messwerte",
            "description": "Messwerte erfassen, importieren und DAkkS-taugliche Berichte erzeugen."
          },
          {
            "id": "feat-auftraege",
            "title": "Aufträge & Abrechnung",
            "description": "Angebot → Auftrag → Rechnung in einem Flow, mit SLAs und Eskalation."
          },
          {
            "id": "feat-metcal",
            "title": "MET/CAL-Synchronisation",
            "description": "Bidirektionale Sync mit Fluke MET/TEAM & MET/CAL – Migration ohne Stillstand."
          }
        ]
      },
      "meta": {
        "anchor": "funktionen",
        "sectionStyle": {
          "container": { "width": "wide" }
        }
      },
      "tokens": {
        "columns": "three"
      }
    },
    {
      "id": "references",
      "type": "feature_list",
      "variant": "detailed-cards",
      "data": {
        "eyebrow": "Referenzen",
        "title": "Vertrauen aus der Praxis",
        "lead": "Vom Kalibrierlabor über Industriekonzerne bis zum öffentlichen Versorger.",
        "items": [
          {
            "id": "ref-thermo",
            "title": "Thermo Fisher Scientific",
            "description": "Kalibrierlabor · EMEA-weite Leihverwaltung & Geräteakten mit MET/TEAM-Sync"
          },
          {
            "id": "ref-zf",
            "title": "ZF",
            "description": "Industrielabor · API-Messwerte auf Kubernetes mit SSO & MET/TEAM-Sync"
          },
          {
            "id": "ref-vde",
            "title": "VDE",
            "description": "Qualitätsmanagement · Agile Auftragssteuerung & revisionssicheres Intranet"
          },
          {
            "id": "ref-berlin",
            "title": "Berliner Stadtwerke",
            "description": "Assetmanagement · Projekte & Wartung für erneuerbare Energieanlagen"
          },
          {
            "id": "ref-ifm",
            "title": "ifm",
            "description": "Kalibrierlabor · Störungsbearbeitung & CAPA über zwei Standorte"
          },
          {
            "id": "ref-ksw",
            "title": "KSW",
            "description": "Kalibrierlabor · End-to-End: Wareneingang → Labor → Rechnung"
          },
          {
            "id": "ref-teramess",
            "title": "TERAMESS",
            "description": "Kalibrierlabor · DAkkS-konforme Zertifikate in der Cloud"
          },
          {
            "id": "ref-systems",
            "title": "Systems Engineering",
            "description": "Kalibrierlabor · Auftragsbearbeitung als steuerndes Herz"
          }
        ]
      },
      "meta": {
        "anchor": "referenzen",
        "sectionStyle": {
          "background": { "mode": "color", "colorToken": "muted" },
          "container": { "width": "wide" }
        }
      },
      "tokens": {
        "columns": "four"
      }
    },
    {
      "id": "testimonials",
      "type": "testimonial",
      "variant": "quote_wall",
      "data": {
        "quote": "Sehr professioneller und individueller Service. Besonders die breitbandige Unterstützung durch die Unabhängigkeit und Erfahrung hat uns sehr weitergeholfen.",
        "author": { "name": "Dave D.", "role": "ProvenExpert" }
      },
      "meta": {
        "anchor": "stimmen",
        "sectionStyle": {
          "container": { "width": "wide" }
        }
      }
    },
    {
      "id": "pricing",
      "type": "package_summary",
      "variant": "comparison-cards",
      "data": {
        "title": "Transparent & flexibel",
        "subtitle": "Monatliche Laufzeit, 30 Tage Kündigungsfrist, DSGVO-konform.",
        "plans": [
          {
            "id": "standard",
            "title": "Standard-Hosting",
            "description": "Cloud in Deutschland – für Teams, die schnell starten wollen.",
            "features": [
              "Inventar-, Kalibrier- & Auftragsverwaltung",
              "Dokumentenmanagement (Basis)",
              "Tägliche Backups, SSL & Subdomain",
              "Rollen & Berechtigungen"
            ],
            "primaryCta": { "label": "Anfrage senden", "href": "#kontakt" }
          },
          {
            "id": "performance",
            "badge": "Beliebt",
            "title": "Performance-Hosting",
            "description": "Mehr Leistung & Spielraum für wachsende Anforderungen.",
            "features": [
              "Skalierbare Ressourcen & Performance",
              "Alle Module, mehr Speicher",
              "Priorisiertes Monitoring",
              "Team-Workflows & Berechtigungen"
            ],
            "primaryCta": { "label": "Anfrage senden", "href": "#kontakt" }
          },
          {
            "id": "enterprise",
            "title": "Enterprise (On-Prem)",
            "description": "Volle Datenhoheit in Ihrer eigenen Infrastruktur.",
            "features": [
              "On-Prem-Betrieb im eigenen Netzwerk",
              "SSO (Azure/Google), erweiterte Integrationen",
              "Individuelle SLAs & Compliance",
              "MET/TEAM-Synchronisation optional"
            ],
            "primaryCta": { "label": "Anfrage senden", "href": "#kontakt" }
          }
        ],
        "disclaimer": "Vollständige AGB, SLA und AVV auf Anfrage oder im Kundenportal einsehbar."
      },
      "meta": {
        "anchor": "preise",
        "sectionStyle": {
          "background": { "mode": "color", "colorToken": "surface" },
          "container": { "width": "wide" }
        }
      }
    },
    {
      "id": "founder",
      "type": "rich_text",
      "variant": "prose",
      "data": {
        "body": "<div class=\"cs-founder-block\" style=\"display:grid;grid-template-columns:auto 1fr;gap:2rem;align-items:center;max-width:720px;\"><div style=\"width:80px;height:80px;border-radius:50%;background:var(--accent-primary,#1f6feb);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.5rem;flex-shrink:0;\">RB</div><div><p class=\"uk-text-meta\" style=\"margin-bottom:0.5rem;\">Über den Gründer</p><h3 class=\"uk-h4\" style=\"margin:0 0 0.75rem;\">René Buske</h3><p style=\"margin:0 0 0.5rem;\">Über 20 Jahre in der Kalibriertechnik – vom Labortechniker bei der Bundeswehr über 8 Jahre Fluke MET/CAL-Support beim deutschen Exklusivpartner bis zum Gründer von calHelp (2009). Über 1.000 Seminarteilnehmer, Speaker beim Munich Calibration Day.</p><p class=\"uk-text-small\" style=\"margin:0;\"><a href=\"https://de.linkedin.com/in/metcaltrainer\" target=\"_blank\" rel=\"noopener\">LinkedIn-Profil →</a> · <a href=\"https://www.provenexpert.com/de-de/calhelp/\" target=\"_blank\" rel=\"noopener\">ProvenExpert →</a></p></div></div>"
      }
    },
    {
      "id": "cta-final",
      "type": "cta",
      "variant": "full_width",
      "data": {
        "title": "Bereit für weniger Aufwand?",
        "body": "Testen Sie calServer 30 Tage kostenlos – oder buchen Sie eine persönliche Demo.",
        "primary": { "label": "Jetzt kostenlos testen", "href": "https://develop.net-cal.com" },
        "secondary": { "label": "Demo buchen", "href": "https://calendly.com/calhelp/calserver-vorstellung" }
      },
      "meta": {
        "anchor": "kontakt",
        "sectionStyle": {
          "intent": "highlight",
          "layout": "full",
          "background": { "mode": "color", "colorToken": "primary" },
          "container": { "width": "normal", "spacing": "generous" }
        }
      }
    }
  ]
}$CONTENT$,
    'marketing',
    'published',
    'de',
    TRUE
)
ON CONFLICT (namespace, slug) DO UPDATE SET
    title = EXCLUDED.title,
    content = EXCLUDED.content,
    type = EXCLUDED.type,
    status = EXCLUDED.status,
    language = EXCLUDED.language,
    is_startpage = EXCLUDED.is_startpage,
    updated_at = CURRENT_TIMESTAMP;
