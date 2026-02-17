-- Update calHelp page content to match refreshed marketing copy
UPDATE pages
SET content = $CALHELP$
<script type="application/json" data-page-modules>
{
  "headline": {
    "de": "Module – abgestimmt auf Ihren Kalibrierbetrieb",
    "en": "Modules – tailored to your calibration operation"
  },
  "subheadline": {
    "de": "Sechs Bausteine führen vom Readiness-Check bis zum laufenden Betrieb.",
    "en": "Six building blocks guide you from readiness through steady-state operations."
  },
  "modules": [
    {
      "id": "readiness",
      "icon": "search",
      "eyebrow": {
        "de": "Modul 01",
        "en": "Module 01"
      },
      "title": {
        "de": "Readiness & Scope",
        "en": "Readiness & Scope"
      },
      "pitch": {
        "de": "Wir erfassen Systemlandschaft, regulatorische Anforderungen und Zielbild – transparent für alle Stakeholder.",
        "en": "We map your system landscape, regulatory requirements and target picture – transparently for every stakeholder."
      },
      "deliverables": {
        "de": [
          "Inventar-Check & Systemkarte",
          "Stakeholder-Interviews",
          "Risikoliste mit Priorisierung"
        ],
        "en": [
          "Inventory review & system map",
          "Stakeholder interviews",
          "Prioritised risk register"
        ]
      },
      "kpi": {
        "de": "KPI: Projektstart in ≤ 10 Tagen nach Kick-off",
        "en": "KPI: Project start within 10 days after kick-off"
      },
      "link": {
        "url": "#process",
        "label": {
          "de": "Beispiel ansehen",
          "en": "View example"
        }
      }
    },
    {
      "id": "migration",
      "icon": "database",
      "eyebrow": {
        "de": "Modul 02",
        "en": "Module 02"
      },
      "title": {
        "de": "Datenmigration & Bereinigung",
        "en": "Data migration & cleansing"
      },
      "pitch": {
        "de": "Wir extrahieren Altdaten, bereinigen Dubletten und dokumentieren jedes Mapping nachvollziehbar.",
        "en": "We extract legacy data, remove duplicates and document every mapping in an auditable trail."
      },
      "deliverables": {
        "de": [
          "Extraktion inkl. Checksummen",
          "Feld-Mapping & Einheitenlogik",
          "Pilotmigration mit Report-Diffs"
        ],
        "en": [
          "Extraction incl. checksums",
          "Field mapping & unit logic",
          "Pilot migration with report diffs"
        ]
      },
      "kpi": {
        "de": "KPI: ≥ 99,5 % korrekte Daten im Pilot",
        "en": "KPI: ≥ 99.5% accurate data in the pilot"
      },
      "link": {
        "url": "https://calhelp.notion.site/met-cal-handbuch",
        "label": {
          "de": "Beispiel ansehen",
          "en": "View example"
        },
        "target": "_blank",
        "rel": "noopener"
      }
    },
    {
      "id": "process-design",
      "icon": "settings",
      "eyebrow": {
        "de": "Modul 03",
        "en": "Module 03"
      },
      "title": {
        "de": "Prozessdesign & Rollen",
        "en": "Process design & roles"
      },
      "pitch": {
        "de": "Wir modellieren Workflows für Labor, Service und Verwaltung – inklusive Freigaben und Eskalationen.",
        "en": "We model workflows for lab, service and administration – including approvals and escalations."
      },
      "deliverables": {
        "de": [
          "Soll-Prozessdiagramm",
          "Rollen- und Rechte-Matrix",
          "Abnahme-Checklisten"
        ],
        "en": [
          "Target process diagram",
          "Role & permission matrix",
          "Acceptance checklists"
        ]
      },
      "kpi": {
        "de": "KPI: 100 % Rollen mit definierten Verantwortlichkeiten",
        "en": "KPI: 100% of roles with defined responsibilities"
      },
      "link": {
        "url": "#benefits",
        "label": {
          "de": "Beispiel ansehen",
          "en": "View example"
        }
      }
    },
    {
      "id": "integrations",
      "icon": "link",
      "eyebrow": {
        "de": "Modul 04",
        "en": "Module 04"
      },
      "title": {
        "de": "Integrationen & Schnittstellen",
        "en": "Integrations & interfaces"
      },
      "pitch": {
        "de": "Wir binden MET/TEAM, ERP oder Asset-Tools an – mit getesteten APIs und Webhooks.",
        "en": "We connect MET/TEAM, ERP or asset tools – with verified APIs and webhooks."
      },
      "deliverables": {
        "de": [
          "API-Blueprint & Sequenzen",
          "SSO-/IdP-Anbindung",
          "Webhook-Testlauf"
        ],
        "en": [
          "API blueprint & sequences",
          "SSO / IdP onboarding",
          "Webhook test run"
        ]
      },
      "kpi": {
        "de": "KPI: Integrationslatenz < 2 Minuten",
        "en": "KPI: Integration latency < 2 minutes"
      },
      "link": {
        "url": "#help",
        "label": {
          "de": "Beispiel ansehen",
          "en": "View example"
        }
      }
    },
    {
      "id": "compliance",
      "icon": "shield",
      "eyebrow": {
        "de": "Modul 05",
        "en": "Module 05"
      },
      "title": {
        "de": "Audit & Compliance",
        "en": "Audit & compliance"
      },
      "pitch": {
        "de": "Wir liefern nachvollziehbare Nachweise – von Report-Diffs bis DSGVO-Check.",
        "en": "We provide traceable evidence – from report diffs to GDPR compliance checks."
      },
      "deliverables": {
        "de": [
          "Audit-Trail-Konzept",
          "Report-Differenzen & Protokolle",
          "DSGVO-Datenfluss-Dokumentation"
        ],
        "en": [
          "Audit trail concept",
          "Report diffs & protocols",
          "GDPR data-flow documentation"
        ]
      },
      "kpi": {
        "de": "KPI: Audit-ready innerhalb von 4 Wochen",
        "en": "KPI: Audit-ready within 4 weeks"
      },
      "link": {
        "url": "#proof",
        "label": {
          "de": "Beispiel ansehen",
          "en": "View example"
        }
      }
    },
    {
      "id": "enablement",
      "icon": "users",
      "eyebrow": {
        "de": "Modul 06",
        "en": "Module 06"
      },
      "title": {
        "de": "Enablement & Betrieb",
        "en": "Enablement & operations"
      },
      "pitch": {
        "de": "Wir befähigen Teams nachhaltig – mit Trainings, Hypercare und KPI-Monitoring.",
        "en": "We empower teams sustainably – with training, hypercare and KPI monitoring."
      },
      "deliverables": {
        "de": [
          "Schulungsplan & Materialien",
          "Hypercare-Desk (30 Tage)",
          "KPI-Dashboard & Review"
        ],
        "en": [
          "Training plan & materials",
          "Hypercare desk (30 days)",
          "KPI dashboard & review"
        ]
      },
      "kpi": {
        "de": "KPI: ≥ 90 % aktive Nutzer:innen nach 30 Tagen",
        "en": "KPI: ≥ 90% active users after 30 days"
      },
      "link": {
        "url": "#cta",
        "label": {
          "de": "Beispiel ansehen",
          "en": "View example"
        }
      }
    }
  ]
}
</script>

<section id="fit" class="uk-section calhelp-section" aria-labelledby="fit-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="fit-title" class="uk-heading-medium">Woran merken Sie, dass wir die Richtigen sind?</h2>
      <p class="uk-text-lead">Drei Signale zeigen, dass unser Einstieg passt.</p>
    </div>
    <div class="uk-grid-large uk-child-width-1-3@m uk-grid-match" data-uk-grid>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="fit-audit-title">
        <h3 id="fit-audit-title" class="uk-card-title">Sie wollen Ruhe vor Audits.</h3>
        <p>Wir liefern Nachweise, die bestehen – ohne Schleifen und Sonderläufe.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="fit-double-title">
        <h3 id="fit-double-title" class="uk-card-title">Sie wollen weniger Doppelerfassung.</h3>
        <p>Wir verbinden, was zusammengehört, damit Daten nur einmal gepflegt werden.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="fit-clarity-title">
        <h3 id="fit-clarity-title" class="uk-card-title">Sie wollen Klarheit im Alltag.</h3>
        <p>Wir machen Rollen sichtbar, Termine greifbar und Fortschritt belegbar.</p>
      </article>
    </div>
  </div>
</section>

<section id="benefits" class="uk-section uk-section-muted calhelp-section" aria-labelledby="benefits-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="benefits-title" class="uk-heading-medium">Was ändert sich für Sie – ganz konkret?</h2>
      <p class="uk-text-lead">Ergebnisse, die Ihr Team sofort spürt.</p>
    </div>
    <div class="uk-grid-large uk-child-width-1-3@m uk-grid-match" data-uk-grid>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="benefit-focus-title">
        <h3 id="benefit-focus-title" class="uk-card-title">Weniger Suchen, mehr Schaffen.</h3>
        <p>Ein zentraler Arbeitsstand ersetzt verstreute Ordner und Excel-Listen.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="benefit-approval-title">
        <h3 id="benefit-approval-title" class="uk-card-title">Ein Freigabeweg, den alle verstehen.</h3>
        <p>Verantwortliche sehen Fristen, Status und Nachweise auf einen Blick.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="benefit-cert-title">
        <h3 id="benefit-cert-title" class="uk-card-title">Zertifikate, die beim ersten Mal passen.</h3>
        <p>Vorlagen, Prüfpfade und Kommentare bleiben nachvollziehbar dokumentiert.</p>
      </article>
    </div>
  </div>
</section>

<section id="process" class="uk-section calhelp-section" aria-labelledby="process-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="process-title" class="uk-heading-medium">So kommen wir ins Laufen – ohne großes Projekt</h2>
      <p class="uk-text-lead">Wir sprechen, ordnen und setzen gemeinsam um – Schritt für Schritt belegbar.</p>
    </div>
    <ol class="uk-list calhelp-process-summary">
      <li><strong>Verstehen.</strong> In 30–45 Minuten klären wir Zielbild, Beteiligte und Stolpersteine.</li>
      <li><strong>Ordnen.</strong> Wir richten das Nötige zuerst: Daten dort, wo sie gebraucht werden.</li>
      <li><strong>Umsetzen.</strong> Wir zeigen den Weg zum ersten belastbaren Nachweis – und gehen ihn mit Ihnen.</li>
    </ol>
    <details class="calhelp-process-details">
      <summary class="calhelp-process-details__summary">Details für Technik &amp; IT</summary>
      <div class="calhelp-process" data-page-stepper>
      <ol class="calhelp-process__nav" aria-label="Migrationsprozess in fünf Schritten">
        <li class="calhelp-process__nav-item is-active">
          <button type="button"
                  class="calhelp-process__nav-button"
                  data-page-step-trigger="readiness"
                  aria-controls="process-step-readiness"
                  aria-current="step">
            <span class="calhelp-process__nav-index" aria-hidden="true">1</span>
            <span class="calhelp-process__nav-label">Readiness-Check</span>
          </button>
          <span class="calhelp-process__nav-connector" aria-hidden="true"></span>
        </li>
        <li class="calhelp-process__nav-item">
          <button type="button"
                  class="calhelp-process__nav-button"
                  data-page-step-trigger="mapping"
                  aria-controls="process-step-mapping">
            <span class="calhelp-process__nav-index" aria-hidden="true">2</span>
            <span class="calhelp-process__nav-label">Mapping &amp; Regeln</span>
          </button>
          <span class="calhelp-process__nav-connector" aria-hidden="true"></span>
        </li>
        <li class="calhelp-process__nav-item">
          <button type="button"
                  class="calhelp-process__nav-button"
                  data-page-step-trigger="pilot"
                  aria-controls="process-step-pilot">
            <span class="calhelp-process__nav-index" aria-hidden="true">3</span>
            <span class="calhelp-process__nav-label">Pilot &amp; Validierung</span>
          </button>
          <span class="calhelp-process__nav-connector" aria-hidden="true"></span>
        </li>
        <li class="calhelp-process__nav-item">
          <button type="button"
                  class="calhelp-process__nav-button"
                  data-page-step-trigger="cutover"
                  aria-controls="process-step-cutover">
            <span class="calhelp-process__nav-index" aria-hidden="true">4</span>
            <span class="calhelp-process__nav-label">Delta-Sync &amp; Cutover</span>
          </button>
          <span class="calhelp-process__nav-connector" aria-hidden="true"></span>
        </li>
        <li class="calhelp-process__nav-item">
          <button type="button"
                  class="calhelp-process__nav-button"
                  data-page-step-trigger="golive"
                  aria-controls="process-step-golive">
            <span class="calhelp-process__nav-index" aria-hidden="true">5</span>
            <span class="calhelp-process__nav-label">Go-Live &amp; Monitoring</span>
          </button>
        </li>
      </ol>
      <div class="calhelp-process__stages">
        <article id="process-step-readiness"
                 class="uk-card uk-card-primary uk-card-body calhelp-process__stage calhelp-process__stage--active calhelp-process__stage--panel-open"
                 data-page-step="readiness">
          <header class="calhelp-process__stage-header">
            <h3>Readiness-Check</h3>
            <p>Systeminventar, Datenumfang, Besonderheiten (z. B. Anhänge, benutzerdefinierte Felder).</p>
          </header>
          <button type="button"
                  class="calhelp-process__toggle"
                  data-page-step-toggle
                  aria-expanded="true"
                  aria-controls="process-panel-readiness">
            <span class="calhelp-process__toggle-label"
                  data-page-i18n
                  data-i18n-de="Leistungen &amp; Abnahme ausblenden"
                  data-i18n-en="Hide deliverables &amp; acceptance"
                  data-page-toggle-label-de-expanded="Leistungen &amp; Abnahme ausblenden"
                  data-page-toggle-label-de-collapsed="Leistungen &amp; Abnahme anzeigen"
                  data-page-toggle-label-en-expanded="Hide deliverables &amp; acceptance"
                  data-page-toggle-label-en-collapsed="Show deliverables &amp; acceptance">Leistungen &amp; Abnahme ausblenden</span>
            <span class="calhelp-process__toggle-icon" aria-hidden="true"></span>
          </button>
          <div id="process-panel-readiness"
               class="calhelp-process__panel is-open"
               data-page-step-panel>
            <h4>Was liefern wir</h4>
            <ul class="uk-list uk-list-bullet">
              <li>Vollständiges Systeminventar mit Datenumfang und Quellen.</li>
              <li>Dokumentation von Anhängen, Sonderfeldern und Compliance-Vorgaben.</li>
              <li>Abgestimmter Projektplan inklusive Rollen und Risikobewertung.</li>
            </ul>
            <p class="calhelp-process__criteria"><span>Abnahmekriterium:</span> Kick-off-Freigabe mit dokumentiertem Inventar und Risikoübersicht.</p>
          </div>
        </article>
        <article id="process-step-mapping"
                 class="uk-card uk-card-primary uk-card-body calhelp-process__stage"
                 data-page-step="mapping">
          <header class="calhelp-process__stage-header">
            <h3>Mapping &amp; Regeln</h3>
            <p>Felder, SI-Präfixe, Status/Workflows, Rollen. Transparent dokumentiert.</p>
          </header>
          <button type="button"
                  class="calhelp-process__toggle"
                  data-page-step-toggle
                  aria-expanded="true"
                  aria-controls="process-panel-mapping">
            <span class="calhelp-process__toggle-label"
                  data-page-i18n
                  data-i18n-de="Leistungen &amp; Abnahme ausblenden"
                  data-i18n-en="Hide deliverables &amp; acceptance"
                  data-page-toggle-label-de-expanded="Leistungen &amp; Abnahme ausblenden"
                  data-page-toggle-label-de-collapsed="Leistungen &amp; Abnahme anzeigen"
                  data-page-toggle-label-en-expanded="Hide deliverables &amp; acceptance"
                  data-page-toggle-label-en-collapsed="Show deliverables &amp; acceptance">Leistungen &amp; Abnahme ausblenden</span>
            <span class="calhelp-process__toggle-icon" aria-hidden="true"></span>
          </button>
          <div id="process-panel-mapping"
               class="calhelp-process__panel"
               data-page-step-panel>
            <h4>Was liefern wir</h4>
            <ul class="uk-list uk-list-bullet">
              <li>Mapping-Dokumentation für Felder, Einheiten und Statuslogiken.</li>
              <li>Rollen- und Workflow-Matrix mit Verantwortlichkeiten.</li>
              <li>Blueprint der Validierungs- und Prüfregeln.</li>
            </ul>
            <p class="calhelp-process__criteria"><span>Abnahmekriterium:</span> Fachlicher Review der Mapping-Dokumentation durch IT und Fachbereich.</p>
          </div>
        </article>
        <article id="process-step-pilot"
                 class="uk-card uk-card-primary uk-card-body calhelp-process__stage"
                 data-page-step="pilot">
          <header class="calhelp-process__stage-header">
            <h3>Pilot &amp; Validierung</h3>
            <p>Teilmenge (Golden Samples), Checksummen, Abweichungsbericht. Freigabe als Gate.</p>
          </header>
          <button type="button"
                  class="calhelp-process__toggle"
                  data-page-step-toggle
                  aria-expanded="true"
                  aria-controls="process-panel-pilot">
            <span class="calhelp-process__toggle-label"
                  data-page-i18n
                  data-i18n-de="Leistungen &amp; Abnahme ausblenden"
                  data-i18n-en="Hide deliverables &amp; acceptance"
                  data-page-toggle-label-de-expanded="Leistungen &amp; Abnahme ausblenden"
                  data-page-toggle-label-de-collapsed="Leistungen &amp; Abnahme anzeigen"
                  data-page-toggle-label-en-expanded="Hide deliverables &amp; acceptance"
                  data-page-toggle-label-en-collapsed="Show deliverables &amp; acceptance">Leistungen &amp; Abnahme ausblenden</span>
            <span class="calhelp-process__toggle-icon" aria-hidden="true"></span>
          </button>
          <div id="process-panel-pilot"
               class="calhelp-process__panel"
               data-page-step-panel>
            <h4>Was liefern wir</h4>
            <ul class="uk-list uk-list-bullet">
              <li>Golden-Sample-Datensätze im Zielsystem.</li>
              <li>Checksummen- und Diff-Reports mit Kommentaren.</li>
              <li>Abweichungsprotokoll inklusive Freigabeempfehlung.</li>
            </ul>
            <p class="calhelp-process__criteria"><span>Abnahmekriterium:</span> Pilotabnahme mit höchstens 1&nbsp;% tolerierten Abweichungen.</p>
          </div>
        </article>
        <article id="process-step-cutover"
                 class="uk-card uk-card-primary uk-card-body calhelp-process__stage"
                 data-page-step="cutover">
          <header class="calhelp-process__stage-header">
            <h3>Delta-Sync &amp; Cutover</h3>
            <p>Downtime-arm, sauber geplantes Übergabefenster, klarer Abnahmelauf.</p>
          </header>
          <button type="button"
                  class="calhelp-process__toggle"
                  data-page-step-toggle
                  aria-expanded="true"
                  aria-controls="process-panel-cutover">
            <span class="calhelp-process__toggle-label"
                  data-page-i18n
                  data-i18n-de="Leistungen &amp; Abnahme ausblenden"
                  data-i18n-en="Hide deliverables &amp; acceptance"
                  data-page-toggle-label-de-expanded="Leistungen &amp; Abnahme ausblenden"
                  data-page-toggle-label-de-collapsed="Leistungen &amp; Abnahme anzeigen"
                  data-page-toggle-label-en-expanded="Hide deliverables &amp; acceptance"
                  data-page-toggle-label-en-collapsed="Show deliverables &amp; acceptance">Leistungen &amp; Abnahme ausblenden</span>
            <span class="calhelp-process__toggle-icon" aria-hidden="true"></span>
          </button>
          <div id="process-panel-cutover"
               class="calhelp-process__panel"
               data-page-step-panel>
            <h4>Was liefern wir</h4>
            <ul class="uk-list uk-list-bullet">
              <li>Cutover-Playbook mit Zeitplan und Verantwortlichkeiten.</li>
              <li>Automatisierte Delta-Migration inklusive Validierung.</li>
              <li>Kommunikationspaket für Stakeholder und Hotline.</li>
            </ul>
            <p class="calhelp-process__criteria"><span>Abnahmekriterium:</span> Abschlussprotokoll ohne kritische Abweichungen.</p>
          </div>
        </article>
        <article id="process-step-golive"
                 class="uk-card uk-card-primary uk-card-body calhelp-process__stage"
                 data-page-step="golive">
          <header class="calhelp-process__stage-header">
            <h3>Go-Live &amp; Monitoring</h3>
            <p>KPIs, Protokolle, Hypercare-Phase. Stabil in den Betrieb überführt.</p>
          </header>
          <button type="button"
                  class="calhelp-process__toggle"
                  data-page-step-toggle
                  aria-expanded="true"
                  aria-controls="process-panel-golive">
            <span class="calhelp-process__toggle-label"
                  data-page-i18n
                  data-i18n-de="Leistungen &amp; Abnahme ausblenden"
                  data-i18n-en="Hide deliverables &amp; acceptance"
                  data-page-toggle-label-de-expanded="Leistungen &amp; Abnahme ausblenden"
                  data-page-toggle-label-de-collapsed="Leistungen &amp; Abnahme anzeigen"
                  data-page-toggle-label-en-expanded="Hide deliverables &amp; acceptance"
                  data-page-toggle-label-en-collapsed="Show deliverables &amp; acceptance">Leistungen &amp; Abnahme ausblenden</span>
            <span class="calhelp-process__toggle-icon" aria-hidden="true"></span>
          </button>
          <div id="process-panel-golive"
               class="calhelp-process__panel"
               data-page-step-panel>
            <h4>Was liefern wir</h4>
            <ul class="uk-list uk-list-bullet">
              <li>KPI-Dashboard und Monitoring-Checks.</li>
              <li>Hypercare- und Supportplan für die ersten Wochen.</li>
              <li>Wissensübergabe samt Trainingsunterlagen.</li>
            </ul>
            <p class="calhelp-process__criteria"><span>Abnahmekriterium:</span> Betriebsfreigabe nach abgeschlossener Hypercare-Phase.</p>
          </div>
        </article>
      </div>
      </div>
      <div class="calhelp-note uk-card uk-card-primary uk-card-body">
        <p class="uk-margin-remove">Abnahmekriterien sind vorab definiert (z. B. ≥ 99,5 % korrekte Migration, 0 kritische Abweichungen, Report-Abnahme mit Musterdaten).</p>
      </div>
    </details>
  </div>
</section>

<section id="comparison" class="uk-section uk-section-muted calhelp-section" aria-labelledby="comparison-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="comparison-title"
          class="uk-heading-medium"
          data-page-i18n
          data-i18n-de="Alltag vorher vs. nachher"
          data-i18n-en="Everyday work before vs. after">Alltag vorher vs. nachher</h2>
      <p data-page-i18n
         data-i18n-de="Wie calHelp Abläufe verändert – drei Beispiele aus dem Betrieb."
         data-i18n-en="How calHelp changes operations – three real-world examples.">Wie calHelp Abläufe verändert – drei Beispiele aus dem Betrieb.</p>
    </div>
    <div class="calhelp-comparison" data-page-comparison>
      <article class="uk-card uk-card-primary uk-card-body calhelp-comparison__card"
               aria-labelledby="comparison-card-data-title"
               data-page-comparison-card="data"
               data-page-comparison-default="after">
        <header class="calhelp-comparison__header">
          <p id="comparison-card-data-title"
             class="calhelp-comparison__eyebrow"
             data-page-i18n
             data-i18n-de="Datenpflege"
             data-i18n-en="Data upkeep">Datenpflege</p>
          <div class="calhelp-comparison__toggle-group"
               role="group"
               data-page-i18n
               data-page-i18n-attr="aria-label"
               data-i18n-de="Zustand für Datenpflege wechseln"
               data-i18n-en="Switch data upkeep state"
               aria-label="Zustand für Datenpflege wechseln">
            <button type="button"
                    class="calhelp-comparison__toggle"
                    data-comparison-toggle="before"
                    aria-pressed="false"
                    aria-controls="comparison-card-data-before"
                    data-page-i18n
                    data-i18n-de="Vorher"
                    data-i18n-en="Before">Vorher</button>
            <button type="button"
                    class="calhelp-comparison__toggle"
                    data-comparison-toggle="after"
                    aria-pressed="true"
                    aria-controls="comparison-card-data-after"
                    data-page-i18n
                    data-i18n-de="Nachher"
                    data-i18n-en="After">Nachher</button>
          </div>
        </header>
        <div class="calhelp-comparison__body" aria-live="polite">
          <div id="comparison-card-data-before"
               class="calhelp-comparison__state"
               data-comparison-state="before"
               aria-hidden="true"
               hidden>
            <p data-page-i18n
               data-i18n-de="Stammdaten in Excel, lokale Ablagen, Absprachen per E-Mail. Jede Korrektur kostet Zeit und erzeugt neue Versionen."
               data-i18n-en="Master data lives in Excel and local folders, coordination happens via email. Every correction costs time and spawns another version.">Stammdaten in Excel, lokale Ablagen, Absprachen per E-Mail. Jede Korrektur kostet Zeit und erzeugt neue Versionen.</p>
            <dl class="calhelp-comparison__metrics">
              <div class="calhelp-comparison__metric">
                <dt data-page-i18n
                    data-i18n-de="Parallel gepflegte Quellen"
                    data-i18n-en="Sources maintained in parallel">Parallel gepflegte Quellen</dt>
                <dd data-page-i18n
                    data-i18n-de="3 Systeme"
                    data-i18n-en="3 systems">3 Systeme</dd>
              </div>
              <div class="calhelp-comparison__metric">
                <dt data-page-i18n
                    data-i18n-de="Aktualisierung"
                    data-i18n-en="Update cycle">Aktualisierung</dt>
                <dd data-page-i18n
                    data-i18n-de="&gt; 48&nbsp;h Rückstand"
                    data-i18n-en="&gt; 48&nbsp;h lag">&gt; 48&nbsp;h Rückstand</dd>
              </div>
            </dl>
          </div>
          <div id="comparison-card-data-after"
               class="calhelp-comparison__state is-active"
               data-comparison-state="after">
            <p data-page-i18n
               data-i18n-de="Zentrale Stammdaten mit Validierungsregeln, Änderungen mit Pflichtfeldern dokumentiert. Teams pflegen direkt im System."
               data-i18n-en="Central master data with validation rules, required fields document every change. Teams maintain records directly in the platform.">Zentrale Stammdaten mit Validierungsregeln, Änderungen mit Pflichtfeldern dokumentiert. Teams pflegen direkt im System.</p>
            <dl class="calhelp-comparison__metrics">
              <div class="calhelp-comparison__metric">
                <dt data-page-i18n
                    data-i18n-de="Suchzeit"
                    data-i18n-en="Search time">Suchzeit</dt>
                <dd data-page-i18n
                    data-i18n-de="−35&nbsp;%"
                    data-i18n-en="−35%">−35&nbsp;%</dd>
              </div>
              <div class="calhelp-comparison__metric">
                <dt data-page-i18n
                    data-i18n-de="Datenquelle"
                    data-i18n-en="Data source">Datenquelle</dt>
                <dd data-page-i18n
                    data-i18n-de="1 konsolidiertes System"
                    data-i18n-en="1 consolidated system">1 konsolidiertes System</dd>
              </div>
            </dl>
          </div>
        </div>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-comparison__card"
               aria-labelledby="comparison-card-approval-title"
               data-page-comparison-card="approval"
               data-page-comparison-default="after">
        <header class="calhelp-comparison__header">
          <p id="comparison-card-approval-title"
             class="calhelp-comparison__eyebrow"
             data-page-i18n
             data-i18n-de="Report-Freigabe"
             data-i18n-en="Report approval">Report-Freigabe</p>
          <div class="calhelp-comparison__toggle-group"
               role="group"
               data-page-i18n
               data-page-i18n-attr="aria-label"
               data-i18n-de="Zustand für Report-Freigabe wechseln"
               data-i18n-en="Switch report approval state"
               aria-label="Zustand für Report-Freigabe wechseln">
            <button type="button"
                    class="calhelp-comparison__toggle"
                    data-comparison-toggle="before"
                    aria-pressed="false"
                    aria-controls="comparison-card-approval-before"
                    data-page-i18n
                    data-i18n-de="Vorher"
                    data-i18n-en="Before">Vorher</button>
            <button type="button"
                    class="calhelp-comparison__toggle"
                    data-comparison-toggle="after"
                    aria-pressed="true"
                    aria-controls="comparison-card-approval-after"
                    data-page-i18n
                    data-i18n-de="Nachher"
                    data-i18n-en="After">Nachher</button>
          </div>
        </header>
        <div class="calhelp-comparison__body" aria-live="polite">
          <div id="comparison-card-approval-before"
               class="calhelp-comparison__state"
               data-comparison-state="before"
               aria-hidden="true"
               hidden>
            <p data-page-i18n
               data-i18n-de="Freigaben per E-Mail oder Excel, unklare Versionen, jedes Audit verlangt Nachfragen. Feedbackschleifen dauern Tage."
               data-i18n-en="Approvals travel via email or Excel, versions stay unclear and every audit triggers follow-up questions. Review loops take days.">Freigaben per E-Mail oder Excel, unklare Versionen, jedes Audit verlangt Nachfragen. Feedbackschleifen dauern Tage.</p>
            <dl class="calhelp-comparison__metrics">
              <div class="calhelp-comparison__metric">
                <dt data-page-i18n
                    data-i18n-de="Feedbackschleifen"
                    data-i18n-en="Review loops">Feedbackschleifen</dt>
                <dd data-page-i18n
                    data-i18n-de="Ø 3 Runden"
                    data-i18n-en="avg. 3 rounds">Ø 3 Runden</dd>
              </div>
              <div class="calhelp-comparison__metric">
                <dt data-page-i18n
                    data-i18n-de="Nachvollziehbarkeit"
                    data-i18n-en="Traceability">Nachvollziehbarkeit</dt>
                <dd data-page-i18n
                    data-i18n-de="Audit-Trail nur manuell"
                    data-i18n-en="Audit trail manual only">Audit-Trail nur manuell</dd>
              </div>
            </dl>
          </div>
          <div id="comparison-card-approval-after"
               class="calhelp-comparison__state is-active"
               data-comparison-state="after">
            <p data-page-i18n
               data-i18n-de="Geführte Freigaben mit Rollen, Versionskontrolle und Pflichtkommentaren. Signaturen landen automatisch im Audit-Trail."
               data-i18n-en="Guided approvals with roles, version control and required comments. Sign-offs land automatically in the audit trail.">Geführte Freigaben mit Rollen, Versionskontrolle und Pflichtkommentaren. Signaturen landen automatisch im Audit-Trail.</p>
            <dl class="calhelp-comparison__metrics">
              <div class="calhelp-comparison__metric">
                <dt data-page-i18n
                    data-i18n-de="Feedbackschleifen"
                    data-i18n-en="Review loops">Feedbackschleifen</dt>
                <dd data-page-i18n
                    data-i18n-de="−50&nbsp;%"
                    data-i18n-en="−50%">−50&nbsp;%</dd>
              </div>
              <div class="calhelp-comparison__metric">
                <dt data-page-i18n
                    data-i18n-de="Signatur-Log"
                    data-i18n-en="Signature log">Signatur-Log</dt>
                <dd data-page-i18n
                    data-i18n-de="100&nbsp;% automatisch"
                    data-i18n-en="100% automated">100&nbsp;% automatisch</dd>
              </div>
            </dl>
          </div>
        </div>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-comparison__card"
               aria-labelledby="comparison-card-audit-title"
               data-page-comparison-card="audit"
               data-page-comparison-default="after">
        <header class="calhelp-comparison__header">
          <p id="comparison-card-audit-title"
             class="calhelp-comparison__eyebrow"
             data-page-i18n
             data-i18n-de="Audit-Vorbereitung"
             data-i18n-en="Audit preparation">Audit-Vorbereitung</p>
          <div class="calhelp-comparison__toggle-group"
               role="group"
               data-page-i18n
               data-page-i18n-attr="aria-label"
               data-i18n-de="Zustand für Audit-Vorbereitung wechseln"
               data-i18n-en="Switch audit preparation state"
               aria-label="Zustand für Audit-Vorbereitung wechseln">
            <button type="button"
                    class="calhelp-comparison__toggle"
                    data-comparison-toggle="before"
                    aria-pressed="false"
                    aria-controls="comparison-card-audit-before"
                    data-page-i18n
                    data-i18n-de="Vorher"
                    data-i18n-en="Before">Vorher</button>
            <button type="button"
                    class="calhelp-comparison__toggle"
                    data-comparison-toggle="after"
                    aria-pressed="true"
                    aria-controls="comparison-card-audit-after"
                    data-page-i18n
                    data-i18n-de="Nachher"
                    data-i18n-en="After">Nachher</button>
          </div>
        </header>
        <div class="calhelp-comparison__body" aria-live="polite">
          <div id="comparison-card-audit-before"
               class="calhelp-comparison__state"
               data-comparison-state="before"
               aria-hidden="true"
               hidden>
            <p data-page-i18n
               data-i18n-de="Nachweise liegen in Ordnern, Checklisten werden händisch gepflegt. Vor Audits werden Dokumente gesucht und Medien abgeglichen."
               data-i18n-en="Evidence lives in folders, checklists stay manual. Before an audit the team hunts documents and reconciles media files.">Nachweise liegen in Ordnern, Checklisten werden händisch gepflegt. Vor Audits werden Dokumente gesucht und Medien abgeglichen.</p>
            <dl class="calhelp-comparison__metrics">
              <div class="calhelp-comparison__metric">
                <dt data-page-i18n
                    data-i18n-de="Vorbereitungszeit"
                    data-i18n-en="Preparation time">Vorbereitungszeit</dt>
                <dd data-page-i18n
                    data-i18n-de="3 Tage"
                    data-i18n-en="3 days">3 Tage</dd>
              </div>
              <div class="calhelp-comparison__metric">
                <dt data-page-i18n
                    data-i18n-de="Ablage"
                    data-i18n-en="Storage">Ablage</dt>
                <dd data-page-i18n
                    data-i18n-de="5+ verstreute Ordner"
                    data-i18n-en="5+ scattered folders">5+ verstreute Ordner</dd>
              </div>
            </dl>
          </div>
          <div id="comparison-card-audit-after"
               class="calhelp-comparison__state is-active"
               data-comparison-state="after">
            <p data-page-i18n
               data-i18n-de="Audit-Workspace mit Checkliste, Report-Diffs und Messmittelhistorie. Nachweise stehen sortiert bereit, inklusive Ansprechpartner:in."
               data-i18n-en="Audit workspace with checklist, report diffs and instrument history. Evidence is pre-sorted including the responsible contact.">Audit-Workspace mit Checkliste, Report-Diffs und Messmittelhistorie. Nachweise stehen sortiert bereit, inklusive Ansprechpartner:in.</p>
            <dl class="calhelp-comparison__metrics">
              <div class="calhelp-comparison__metric">
                <dt data-page-i18n
                    data-i18n-de="Aufwand"
                    data-i18n-en="Effort">Aufwand</dt>
                <dd data-page-i18n
                    data-i18n-de="−60&nbsp;%"
                    data-i18n-en="−60%">−60&nbsp;%</dd>
              </div>
              <div class="calhelp-comparison__metric">
                <dt data-page-i18n
                    data-i18n-de="Bereitstellung"
                    data-i18n-en="Preparation">Bereitstellung</dt>
                <dd data-page-i18n
                    data-i18n-de="Audit-Ordner in 1 Tag"
                    data-i18n-en="Audit binder in 1 day">Audit-Ordner in 1 Tag</dd>
              </div>
            </dl>
          </div>
        </div>
      </article>
    </div>
  </div>
</section>

<script type="application/json" data-page-usecases>
{
  "heading": {
    "de": "Drei Situationen, in denen Kund:innen zu uns kommen",
    "en": "Three situations that bring teams to us"
  },
  "intro": {
    "de": "Kurz und konkret: Wo wir starten, wie es sich anfühlt, was bleibt.",
    "en": "Short and concrete: where we start, how it feels, what remains."
  },
  "usecases": [
    {
      "id": "ksw",
      "title": {
        "de": "KSW",
        "en": "KSW"
      },
      "tagline": {
        "de": "Vom Flickenteppich zur Linie",
        "en": "From patchwork to one line"
      },
      "story": {
        "de": "Angebote, Lager, Messwerte, Rechnungen – ein Fluss statt vieler Inseln.",
        "en": "Quotes, stock, measurements and invoicing become one flow instead of scattered islands."
      },
      "result": {
        "de": "Ergebnis: kürzere Durchlaufzeiten, weniger Rückfragen.",
        "en": "Result: shorter lead times and fewer follow-up questions."
      }
    },
    {
      "id": "ifm",
      "title": {
        "de": "i.f.m.",
        "en": "i.f.m."
      },
      "tagline": {
        "de": "Ein Verbund, ein Arbeitsstand",
        "en": "One network, one shared status"
      },
      "story": {
        "de": "Mehrere Labore arbeiten nach demselben Takt.",
        "en": "Several labs work to the same rhythm."
      },
      "result": {
        "de": "Ergebnis: planbare Auslastung, konsistente Berichte, Entspannung vor Audits.",
        "en": "Result: predictable utilisation, consistent reports and calmer audits."
      }
    },
    {
      "id": "berliner-stadtwerke",
      "title": {
        "de": "Berliner Stadtwerke",
        "en": "Berliner Stadtwerke"
      },
      "tagline": {
        "de": "Projekte & Wartungen im Griff",
        "en": "Projects and maintenance under control"
      },
      "story": {
        "de": "Anlagen, Termine, Nachweise zentral geführt.",
        "en": "Assets, schedules and evidence live in one place."
      },
      "result": {
        "de": "Ergebnis: klare Zuständigkeiten, schnellere Reaktion, sauber dokumentiert.",
        "en": "Result: clear ownership, faster responses and clean documentation."
      }
    }
  ]
}
</script>
<div data-page-usecases></div>

<section id="proof" class="uk-section calhelp-section" aria-labelledby="proof-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="proof-title" class="uk-heading-medium">Was Vertrauen schafft</h2>
      <p class="uk-text-lead">Drei Zusagen, auf die Sie sich verlassen können.</p>
    </div>
    <div class="uk-grid-large uk-child-width-1-3@m uk-grid-match" data-uk-grid>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="proof-trace-title">
        <h3 id="proof-trace-title" class="uk-card-title">Nachweisbar.</h3>
        <p>Freigaben und Änderungen sind protokolliert – lückenlos und exportierbar.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="proof-check-title">
        <h3 id="proof-check-title" class="uk-card-title">Prüfbar.</h3>
        <p>Berichte lassen sich reproduzieren, auf Wunsch zweisprachig und mit Golden Samples geprüft.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="proof-safe-title">
        <h3 id="proof-safe-title" class="uk-card-title">Sicher.</h3>
        <p>Betrieb in Deutschland oder On-Prem, Zugriffe rollenbasiert, Backups verschlüsselt.</p>
      </article>
    </div>
    <details class="calhelp-proof-details">
      <summary class="calhelp-proof-details__summary">Details für Technik &amp; IT</summary>
      {% set assurancePanels = [
      {
        id: 'cloud-de',
        title: 'Cloud DE',
        icon: 'cloud',
        facts: [
          {
            icon: 'cloud',
            badge: { icon: 'location', label: 'Software hosted in Germany' },
            text: 'ISO 27001- und BSI C5-geprüfte Rechenzentren mit redundanten Standorten in Frankfurt & Berlin.'
          },
          {
            icon: 'server',
            text: '24/7-Monitoring mit Alarmierung & Incident-Response in &le; 15 Minuten.'
          },
          {
            icon: 'lock',
            text: 'Backups verschlüsselt (AES-256) gespeichert und täglich auf Integrität geprüft.'
          }
        ],
        hint: 'Zugriff kann auf Wunsch per IP-Allowlist oder mandantenbezogener VPN-Anbindung zusätzlich abgesichert werden.'
      },
      {
        id: 'on-prem',
        title: 'On-Prem',
        icon: 'database',
        facts: [
          {
            icon: 'bolt',
            badge: { icon: 'bolt', label: 'Setup in &le; 4 Stunden' },
            text: 'Installationspaket für Windows Server & Linux inkl. automatischer Preflight-Checks.'
          },
          {
            icon: 'database',
            text: 'PostgreSQL oder SQL Server, optional im Cluster mit Failover-Testprotokoll.'
          },
          {
            icon: 'refresh',
            text: 'Signierte Update-Pakete mit Rollback-Plan und Dokumentation der Änderungen.'
          }
        ],
        hint: 'Konfigurations-Playbooks (Ansible/PowerShell) erleichtern das Patch-Management und erlauben reproduzierbare Deployments.'
      },
      {
        id: 'dsgvo',
        title: 'DSGVO',
        icon: 'shield',
        facts: [
          {
            icon: 'file-text',
            badge: { icon: 'file-text', label: 'AV-Vertrag & TOMs' },
            text: 'Aktualisierte ADV inklusive Technischer & Organisatorischer Maßnahmen, unterschriftsreif in Deutsch/Englisch.'
          },
          {
            icon: 'history',
            text: 'Protokollierte Datenflüsse und Verarbeitungstätigkeiten für Auskunfts- & Löschbegehren.'
          },
          {
            icon: 'trash',
            text: 'Konfigurierbare Aufbewahrungsfristen mit revisionssicherer Löschbestätigung.'
          }
        ],
        hint: 'Auf Wunsch liefern wir ein Muster für Datenschutz-Folgenabschätzungen und Schnittstellen für Betroffenenanfragen (REST).'
      },
      {
        id: 'rollen-protokolle',
        title: 'Rollen &amp; Protokolle',
        icon: 'users',
        facts: [
          {
            icon: 'users',
            badge: { icon: 'users', label: '5+ Rollen vorkonfiguriert' },
            text: 'Feingranulare Berechtigungen für Labor, QS, IT, Service und externe Partner.'
          },
          {
            icon: 'file-text',
            text: 'Objektbezogener Audit-Trail (wer/was/wann) mit Export nach CSV oder SIEM.'
          },
          {
            icon: 'key',
            text: 'SSO-Anbindung via SAML/OIDC inklusive SCIM-Provisioning & Gruppen-Mapping.'
          }
        ],
        hint: 'Webhook- und Syslog-Forwarder ermöglichen das Streaming der Audit-Logs an bestehende SIEM-Lösungen.'
      }
    ] %}
      <div class="calhelp-assurance" aria-labelledby="proof-accordion-title">
        <h3 id="proof-accordion-title" class="calhelp-assurance__title">Trust Center – Details für IT &amp; Qualitätsmanagement</h3>
        <p class="calhelp-assurance__intro">Vier Schwerpunktbereiche zeigen, wie calHelp Cloud, On-Premises und Compliance sicher umsetzt – inklusive konkreter IT-Hinweise.</p>
        <ul class="uk-accordion calhelp-assurance__accordion" uk-accordion="multiple: true">
          {% for panel in assurancePanels %}
            <li class="calhelp-assurance__item">
              <a class="uk-accordion-title" href="#proof-{{ panel.id }}">
                <span class="calhelp-assurance__title-icon" aria-hidden="true" data-uk-icon="icon: {{ panel.icon }}"></span>
                <span>{{ panel.title }}</span>
              </a>
              <div class="uk-accordion-content" id="proof-{{ panel.id }}">
                <ul class="calhelp-assurance__facts" role="list">
                  {% for fact in panel.facts %}
                    <li class="calhelp-assurance__fact">
                      <span class="calhelp-assurance__fact-icon" aria-hidden="true" data-uk-icon="icon: {{ fact.icon|default('check') }}"></span>
                      <div class="calhelp-assurance__fact-body">
                        {% if fact.badge is defined %}
                          <span class="calhelp-assurance__badge">
                            <span class="calhelp-assurance__badge-icon" aria-hidden="true" data-uk-icon="icon: {{ fact.badge.icon|default('check') }}"></span>
                            <span>{{ fact.badge.label }}</span>
                          </span>
                        {% endif %}
                        <p class="calhelp-assurance__fact-text">{{ fact.text }}</p>
                      </div>
                    </li>
                  {% endfor %}
                </ul>
                <p class="calhelp-assurance__hint">
                  <span class="calhelp-assurance__hint-icon" aria-hidden="true" data-uk-icon="icon: info"></span>
                  <span class="calhelp-assurance__hint-text"><strong>IT-Hinweis:</strong> {{ panel.hint }}</span>
                </p>
              </div>
            </li>
          {% endfor %}
        </ul>
      </div>
    </details>
    <div data-page-proof-gallery></div>
    <div class="calhelp-kpi uk-card uk-card-primary uk-card-body">
      <p class="uk-margin-remove">Weniger Schleifen, mehr Ergebnisse – begleitet von 15+ Jahren Projekterfahrung.</p>
    </div>
  </div>
</section>

<section id="help" class="uk-section uk-section-muted calhelp-section" aria-labelledby="help-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="help-title" class="uk-heading-medium">Wie wir helfen</h2>
      <p class="uk-text-lead">Wir starten klein, liefern Belege und halten Wissen fest.</p>
    </div>
    <div class="uk-grid-large uk-child-width-1-3@m uk-grid-match" data-uk-grid>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="help-start-title">
        <h3 id="help-start-title" class="uk-card-title">Wir starten klein.</h3>
        <p>Der dringendste Knoten löst sich zuerst – sichtbar für Fachbereich und IT.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="help-proof-title">
        <h3 id="help-proof-title" class="uk-card-title">Wir belegen Ergebnisse.</h3>
        <p>Einfache Checks zeigen Fortschritt: Abnahmen, KPIs und dokumentierte Nachweise.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="help-doc-title">
        <h3 id="help-doc-title" class="uk-card-title">Wir dokumentieren.</h3>
        <p>Playbooks, Vorlagen und Mitschriften sorgen dafür, dass Wiederholungen schneller gehen.</p>
      </article>
    </div>
  </div>
</section>

<section id="cases" class="uk-section calhelp-section" aria-labelledby="cases-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="cases-title" class="uk-heading-medium">{{ 'calhelp_cases_title'|trans }}</h2>
      <p class="uk-text-lead">{{ 'calhelp_cases_subtitle'|trans }}</p>
    </div>
    {% set caseStories = [
      {
        id: 'lab-story',
        icon: 'server',
        labelKey: 'calhelp_cases_lab_label',
        quoteKey: 'calhelp_cases_lab_quote',
        sentencesKeys: [
          'calhelp_cases_lab_sentence_1',
          'calhelp_cases_lab_sentence_2',
          'calhelp_cases_lab_sentence_3',
          'calhelp_cases_lab_sentence_4',
          'calhelp_cases_lab_sentence_5'
        ]
      },
      {
        id: 'service-story',
        icon: 'users',
        labelKey: 'calhelp_cases_service_label',
        quoteKey: 'calhelp_cases_service_quote',
        sentencesKeys: [
          'calhelp_cases_service_sentence_1',
          'calhelp_cases_service_sentence_2',
          'calhelp_cases_service_sentence_3',
          'calhelp_cases_service_sentence_4',
          'calhelp_cases_service_sentence_5'
        ]
      },
      {
        id: 'manufacturing-story',
        icon: 'world',
        labelKey: 'calhelp_cases_manufacturing_label',
        quoteKey: 'calhelp_cases_manufacturing_quote',
        sentencesKeys: [
          'calhelp_cases_manufacturing_sentence_1',
          'calhelp_cases_manufacturing_sentence_2',
          'calhelp_cases_manufacturing_sentence_3',
          'calhelp_cases_manufacturing_sentence_4',
          'calhelp_cases_manufacturing_sentence_5'
        ]
      }
    ] %}
    <div class="calhelp-cases" role="list">
      {% for case in caseStories %}
        {% set toneClass = cycle(['calhelp-case-strip--primary', 'calhelp-case-strip--muted'], loop.index0) %}
        <article class="calhelp-case-strip {{ toneClass }}" role="listitem" id="{{ case.id }}">
          <header class="calhelp-case-strip__header">
            {% if case.logo is defined %}
              <figure class="calhelp-case-strip__media" aria-hidden="true">
                <img src="{{ case.logo }}" alt="" loading="lazy">
              </figure>
            {% elseif case.icon is defined %}
              <span class="calhelp-case-strip__icon" aria-hidden="true" data-uk-icon="icon: {{ case.icon }}"></span>
            {% endif %}
            <div class="calhelp-case-strip__meta">
              <p class="calhelp-case-strip__label">{{ case.labelKey|trans }}</p>
              <p class="calhelp-case-strip__quote">{{ case.quoteKey|trans }}</p>
            </div>
          </header>
          <div class="calhelp-case-strip__body">
            <ol class="calhelp-case-strip__story">
              {% for sentenceKey in case.sentencesKeys %}
                <li>{{ sentenceKey|trans }}</li>
              {% endfor %}
            </ol>
          </div>
        </article>
      {% endfor %}
    </div>
  </div>
</section>

<section id="conversation" class="uk-section calhelp-section" aria-labelledby="conversation-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="conversation-title" class="uk-heading-medium">Gespräch starten – in drei kurzen Schritten</h2>
      <p class="uk-text-lead">Wir hören zu, bevor wir zeigen. So läuft unser Kennenlernen.</p>
    </div>
    <div class="uk-grid-large" data-uk-grid>
      <div class="uk-width-1-2@m">
        <ol class="calhelp-demo-steps uk-card uk-card-primary uk-card-body" aria-label="Ablauf des Erstgesprächs">
          <li>Anlass schildern: Labor, Service oder Verwaltung – wir hören zu und fragen nach.</li>
          <li>Lage klären: Datenstand, Prioritäten und Zeitfenster werden gemeinsam sortiert.</li>
          <li>Nächsten Schritt vereinbaren: Check, Workshop oder Protokoll – passend zu Ihrer Situation.</li>
        </ol>
      </div>
      <div class="uk-width-1-2@m">
        <div class="uk-card uk-card-primary uk-card-body calhelp-card calhelp-demo-card">
          <h3 class="uk-card-title">Was Sie erwartet</h3>
          <ul class="uk-list uk-list-divider calhelp-cta-list">
            <li>30–45 Minuten fokussiertes Gespräch mit einer klaren Agenda.</li>
            <li>Kurzprotokoll mit empfohlenem Fahrplan und Verantwortlichkeiten.</li>
            <li>Optional: Zugang zum Handbuch, wenn Sie direkt eintauchen möchten.</li>
          </ul>
          <p class="uk-text-small uk-margin-top">Wir dokumentieren, damit der nächste Schritt für alle nachvollziehbar ist.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="about" class="uk-section uk-section-muted calhelp-section" aria-labelledby="about-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="about-title" class="uk-heading-medium">Über calHelp</h2>
      <p class="uk-text-lead">Wissen führt. Software liefert. – der Ansatz von René Buske.</p>
    </div>
    <div class="uk-grid-large" data-uk-grid>
      <div class="uk-width-2-3@m">
        <p>calHelp ist die Dachmarke von René Buske. Aus jahrelanger Projektarbeit im Kalibrierumfeld ist ein klarer Ansatz entstanden: <strong>Wissen führt. Software liefert.</strong> Wir migrieren Altdaten sauber, binden bestehende Systeme an (z. B. MET/TEAM) und stabilisieren Abläufe – <strong>konsistent, nachvollziehbar, auditfähig</strong>.</p>
      </div>
      <div class="uk-width-1-3@m">
        <ul class="uk-list calhelp-values uk-card uk-card-primary uk-card-body" aria-label="Werte von calHelp">
          <li><strong>Präzision:</strong> Entscheidungen auf Datenbasis.</li>
          <li><strong>Transparenz:</strong> Dokumentierte Regeln, prüfbare Schritte.</li>
          <li><strong>Verlässlichkeit:</strong> Saubere Übergabe, stabiler Betrieb.</li>
        </ul>
        <p class="uk-text-small">Kontakt: Kurzes Kennenlernen (15–20 Min) – wir klären Ihr Zielbild und empfehlen den passenden Einstieg.</p>
      </div>
    </div>
  </div>
</section>

<section id="news" class="uk-section calhelp-section" aria-labelledby="news-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="news-title" class="uk-heading-medium">Aktuelles &amp; Fachbeiträge</h2>
      <p class="uk-text-lead">Kurz, nützlich, selten: Updates zu Migration, Reports &amp; Best Practices.</p>
    </div>
    <div class="calhelp-news-grid" role="list">
      <article class="uk-card uk-card-primary uk-card-body calhelp-card calhelp-news-card calhelp-news-card--changelog" aria-labelledby="news-changelog-title" role="listitem">
        <header class="calhelp-news-card__header">
          <span class="calhelp-news-card__icon" aria-hidden="true" data-uk-icon="icon: refresh"></span>
          <div>
            <h3 id="news-changelog-title" class="uk-card-title">Vorher/Nachher: So wird ein Audit zur Formsache</h3>
            <p class="uk-text-meta">Zuletzt aktualisiert am 04.10.2025</p>
          </div>
        </header>
        <ul class="uk-list uk-list-bullet">
          <li>Alt: Drei Systeme, fünf Ordner, lange Suche. Neu: Ein Audit-Workspace in einem Tag.</li>
          <li>Alt: Guardband manuell erklärt. Neu: Klarer Prüfmaßstab direkt im Zertifikat.</li>
          <li>Alt: Schnittstellen im E-Mail-Thread. Neu: Abgenommene Webhooks mit Protokoll.</li>
        </ul>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card calhelp-news-card calhelp-news-card--praxis" aria-labelledby="news-recipe-title" role="listitem">
        <header class="calhelp-news-card__header">
          <span class="calhelp-news-card__icon" aria-hidden="true" data-uk-icon="icon: file-text"></span>
          <div>
            <h3 id="news-recipe-title" class="uk-card-title">In 15 Minuten zur sauberen Freigabe</h3>
            <p class="uk-text-meta">Zuletzt aktualisiert am 27.09.2025</p>
          </div>
        </header>
        <p class="calhelp-news-card__intro"><strong>Thema:</strong> Konformitätslegende sauber integrieren.</p>
        <ol class="calhelp-news-steps" aria-label="Konformitätslegende integrieren">
          <li class="calhelp-news-step">
            <span class="calhelp-news-step__icon" aria-hidden="true" data-uk-icon="icon: file-text"></span>
            <div class="calhelp-news-step__body">
              <span class="calhelp-news-step__label">Schritt&nbsp;1</span>
              <p class="calhelp-news-step__text">Legende zentral in calHelp pflegen.</p>
            </div>
          </li>
          <li class="calhelp-news-step">
            <span class="calhelp-news-step__icon" aria-hidden="true" data-uk-icon="icon: cog"></span>
            <div class="calhelp-news-step__body">
              <span class="calhelp-news-step__label">Schritt&nbsp;2</span>
              <p class="calhelp-news-step__text">Template-Varianten für Kund:innen definieren.</p>
            </div>
          </li>
          <li class="calhelp-news-step">
            <span class="calhelp-news-step__icon" aria-hidden="true" data-uk-icon="icon: check"></span>
            <div class="calhelp-news-step__body">
              <span class="calhelp-news-step__label">Schritt&nbsp;3</span>
              <p class="calhelp-news-step__text">Report-Diffs mit Golden Samples gegenprüfen.</p>
            </div>
          </li>
        </ol>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card calhelp-news-card calhelp-news-card--usecase" aria-labelledby="news-usecase-title" role="listitem">
        <header class="calhelp-news-card__header">
          <span class="calhelp-news-card__icon" aria-hidden="true" data-uk-icon="icon: users"></span>
          <div>
            <h3 id="news-usecase-title" class="uk-card-title">Use-Case-Spotlight</h3>
            <p class="uk-text-meta">Zuletzt aktualisiert am 18.09.2025</p>
          </div>
        </header>
        <div class="calhelp-news-card__body">
          <p><strong>Ausgangslage:</strong> Stark gewachsene Kalibrierabteilung mit Inseltools.</p>
          <p><strong>Vorgehen:</strong> Migration aus MET/TRACK, Schnittstelle zu MET/TEAM, SSO.</p>
          <p><strong>Ergebnis:</strong> Auditberichte in 30&nbsp;% weniger Zeit, klare Verantwortlichkeiten.</p>
          <p><strong>Learnings:</strong> Frühzeitig Rollenmodell definieren, Dokumentation als laufenden Prozess etablieren.</p>
          <p><strong>Nächste Schritte:</strong> Automatisierte Erinnerungen für Prüfmittel und Lieferant:innen.</p>
        </div>
        <ul class="calhelp-news-kpis" role="list" aria-label="Use-Case KPIs">
          <li class="calhelp-news-kpi">
            <span class="calhelp-news-kpi__icon" aria-hidden="true" data-uk-icon="icon: calendar"></span>
            <span class="calhelp-news-kpi__value">30&nbsp;%</span>
            <span class="calhelp-news-kpi__label">schnellere Auditberichte</span>
          </li>
          <li class="calhelp-news-kpi">
            <span class="calhelp-news-kpi__icon" aria-hidden="true" data-uk-icon="icon: lock"></span>
            <span class="calhelp-news-kpi__value">0</span>
            <span class="calhelp-news-kpi__label">kritische Abweichungen beim Cutover</span>
          </li>
          <li class="calhelp-news-kpi">
            <span class="calhelp-news-kpi__icon" aria-hidden="true" data-uk-icon="icon: commenting"></span>
            <span class="calhelp-news-kpi__value">100&nbsp;%</span>
            <span class="calhelp-news-kpi__label">Team onboarding in zwei Wochen</span>
          </li>
        </ul>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card calhelp-news-card calhelp-news-card--insight" aria-labelledby="news-standards-title" role="listitem">
        <header class="calhelp-news-card__header">
          <span class="calhelp-news-card__icon" aria-hidden="true" data-uk-icon="icon: info"></span>
          <div>
            <h3 id="news-standards-title" class="uk-card-title">Woran Sie gute Zertifikate erkennen</h3>
            <p class="uk-text-meta">Zuletzt aktualisiert am 12.09.2025</p>
          </div>
        </header>
        <p class="calhelp-news-card__intro"><strong>Thema:</strong> Guardband &amp; Messunsicherheit auf einen Blick.</p>
        <p>Checkliste: klare Toleranz, dokumentierte MU, nachvollziehbare Entscheidung. calHelp zeigt, wie diese Bausteine im Zertifikat zusammenspielen.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card calhelp-news-card calhelp-news-card--roadmap" aria-labelledby="news-roadmap-title" role="listitem">
        <header class="calhelp-news-card__header">
          <span class="calhelp-news-card__icon" aria-hidden="true" data-uk-icon="icon: calendar"></span>
          <div>
            <h3 id="news-roadmap-title" class="uk-card-title">Roadmap-Ausblick</h3>
            <p class="uk-text-meta">Zuletzt aktualisiert am 05.09.2025</p>
          </div>
        </header>
        <ul class="uk-list uk-list-bullet">
          <li>Q1: Templates für Prüfaufträge &amp; Zertifikate.</li>
          <li>Q2: SSO-Starter für EntraID und Google.</li>
          <li>Q3: API-Rezepte für ERP- und MES-Anbindungen.</li>
        </ul>
      </article>
    </div>
    <aside class="calhelp-newsletter uk-card uk-card-primary uk-card-body uk-light" aria-label="Newsletter-Box">
      <h3 class="uk-card-title">Newsletter</h3>
      <p>„Kurz, nützlich, selten: Updates zu Migration, Reports &amp; Best Practices.“ (Double-Opt-In, freiwillig.)</p>
      <a class="uk-button uk-button-default" href="#conversation">Gespräch vereinbaren</a>
    </aside>
    <section class="calhelp-editorial-calendar uk-card uk-card-primary uk-card-body" aria-labelledby="calendar-title">
      <h3 id="calendar-title">Redaktionskalender – 6 Wochen Ausblick</h3>
      <ol class="uk-list uk-list-decimal">
        <li>Woche 1: „Die 5 größten Stolperstellen bei MET/TRACK-Migrationen“ (Praxisbeitrag)</li>
        <li>Woche 2: Changelog kompakt (Reports &amp; Konformitätslogik)</li>
        <li>Woche 3: Use-Case-Spotlight (anonymisiert)</li>
        <li>Woche 4: „Guardband in 5 Minuten – verständlich erklärt“</li>
        <li>Woche 5: Praxisrezept „Validierung mit Golden Samples“</li>
        <li>Woche 6: Roadmap-Ausblick + Mini-Q&amp;A (aus Newsletter-Fragen)</li>
      </ol>
    </section>
  </div>
</section>

<section id="faq" class="uk-section uk-section-muted calhelp-section" aria-labelledby="faq-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="faq-title" class="uk-heading-medium">FAQ – die typischen Fragen</h2>
    </div>
    <dl class="calhelp-faq" aria-label="Häufig gestellte Fragen">
      <div class="uk-card uk-card-primary uk-card-body calhelp-faq__item">
        <dt>Bleibt MET/TEAM nutzbar?</dt>
        <dd>Ja. Bestehende Lösungen können angebunden bleiben (Fernsteuerung/Befüllen). Eine Ablösung ist optional und schrittweise.</dd>
      </div>
      <div class="uk-card uk-card-primary uk-card-body calhelp-faq__item">
        <dt>Was wird übernommen?</dt>
        <dd>Geräte, Historien, Zertifikate/PDFs, Kund:innen/Standorte, benutzerdefinierte Felder – soweit technisch verfügbar. Alles mit Mapping-Report und Abweichungsprotokoll.</dd>
      </div>
      <div class="uk-card uk-card-primary uk-card-body calhelp-faq__item">
        <dt>Wie sicher ist der Betrieb?</dt>
        <dd>Hosting in Deutschland oder On-Prem, Rollen/Rechte, Protokollierung. DSGVO-konform – inkl. transparentem Datenschutztext.</dd>
      </div>
      <div class="uk-card uk-card-primary uk-card-body calhelp-faq__item">
        <dt>Wie lange dauert der Umstieg?</dt>
        <dd>Abhängig von Datenumfang und Komplexität. Der Pilot liefert einen belastbaren Zeitplan für den Produktivlauf.</dd>
      </div>
    </dl>
  </div>
</section>

<section id="cta" class="uk-section calhelp-section calhelp-cta" aria-labelledby="cta-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="cta-title" class="uk-heading-medium">Der nächste Schritt ist klein – die Wirkung groß.</h2>
      <p class="uk-text-lead">Starten Sie mit einem Gespräch oder lassen Sie uns Ihre Lage klären. Wir liefern eine greifbare Empfehlung.</p>
    </div>
    <div class="calhelp-cta__actions" role="group" aria-label="Abschluss-CTAs">
      <a class="uk-button uk-button-primary" href="#conversation">Gespräch starten</a>
      <a class="uk-button uk-button-default" href="#help">Lage klären</a>
    </div>
    <div class="calhelp-note uk-card uk-card-primary uk-card-body">
      <p class="uk-margin-remove">Wir speichern nur, was für Rückmeldung und Terminfindung nötig ist. Details: <a href="{{ basePath }}/datenschutz">Datenschutz</a>.</p>
    </div>
  </div>
</section>

<section id="seo" class="uk-section uk-section-muted calhelp-section" aria-labelledby="seo-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="seo-title" class="uk-heading-medium">SEO &amp; Snippets</h2>
    </div>
    <div class="calhelp-seo-box uk-card uk-card-primary uk-card-body">
      <p><strong>Seitentitel:</strong> Ein System. Klare Prozesse – Kalibrierdaten und Nachweise im Griff</p>
      <p><strong>Beschreibung:</strong> Wir bringen Kalibrierdaten, Dokumente und Abläufe an einen Ort. Nachweise sind nachvollziehbar, Audits werden zur Formsache.</p>
      <p><strong>Open-Graph-Hinweis:</strong> „Gespräch starten – wir ordnen, vereinfachen, belegen.“</p>
    </div>
  </div>
</section>
$CALHELP$
WHERE slug = 'calhelp';
