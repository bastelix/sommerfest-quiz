INSERT INTO pages (slug, title, content)
VALUES (
    'calhelp',
    'calHelp',
    $$
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
          "url": "#services",
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

  <section id="benefits" class="uk-section uk-section-muted calhelp-section" aria-labelledby="benefits-title">
    <div class="uk-container">
      <div class="calhelp-section__header">
        <h2 id="benefits-title" class="uk-heading-medium">Warum jetzt handeln?</h2>
        <p class="uk-text-lead">Drei starke Gründe, calHelp jetzt zu starten – strukturiert, auditfest, stabil.</p>
      </div>
      <div class="uk-grid-large uk-child-width-1-3@m uk-grid-match" data-uk-grid>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="benefit-migration-title">
          <h3 id="benefit-migration-title" class="uk-card-title">Nahtlos umsteigen</h3>
          <p>Historien aus Altsystemen verlustarm übernehmen, bestehende Tools weiter nutzen. Ohne Doppelerfassung, ohne Datenbruch.</p>
        </article>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="benefit-audit-title">
          <h3 id="benefit-audit-title" class="uk-card-title">Auditfest arbeiten</h3>
          <p>DAkkS-konforme Reports, nachvollziehbare Konformitätslogik und klare Freigaben – Prüfungen bestehen statt diskutieren.</p>
        </article>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="benefit-operations-title">
          <h3 id="benefit-operations-title" class="uk-card-title">Einfach betreiben</h3>
          <p>In Deutschland gehostet oder On-Prem – mit SSO, Rollen und API. Stabil im Alltag, skalierbar im Wachstum.</p>
        </article>
      </div>
    </div>
  </section>

  <section id="process" class="uk-section calhelp-section" aria-labelledby="process-title">
    <div class="uk-container">
      <div class="calhelp-section__header">
        <h2 id="process-title" class="uk-heading-medium">Von Altdaten zu stabilen Abläufen in 5 Schritten</h2>
        <p class="uk-text-lead">Jede Phase ist klar dokumentiert – inklusive Abnahmen, KPIs und Verantwortlichkeiten.</p>
      </div>
      <div class="calhelp-process" data-calhelp-stepper>
        <ol class="calhelp-process__nav" aria-label="Migrationsprozess in fünf Schritten">
          <li class="calhelp-process__nav-item is-active">
            <button type="button"
                    class="calhelp-process__nav-button"
                    data-calhelp-step-trigger="readiness"
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
                    data-calhelp-step-trigger="mapping"
                    aria-controls="process-step-mapping">
              <span class="calhelp-process__nav-index" aria-hidden="true">2</span>
              <span class="calhelp-process__nav-label">Mapping &amp; Regeln</span>
            </button>
            <span class="calhelp-process__nav-connector" aria-hidden="true"></span>
          </li>
          <li class="calhelp-process__nav-item">
            <button type="button"
                    class="calhelp-process__nav-button"
                    data-calhelp-step-trigger="pilot"
                    aria-controls="process-step-pilot">
              <span class="calhelp-process__nav-index" aria-hidden="true">3</span>
              <span class="calhelp-process__nav-label">Pilot &amp; Validierung</span>
            </button>
            <span class="calhelp-process__nav-connector" aria-hidden="true"></span>
          </li>
          <li class="calhelp-process__nav-item">
            <button type="button"
                    class="calhelp-process__nav-button"
                    data-calhelp-step-trigger="cutover"
                    aria-controls="process-step-cutover">
              <span class="calhelp-process__nav-index" aria-hidden="true">4</span>
              <span class="calhelp-process__nav-label">Delta-Sync &amp; Cutover</span>
            </button>
            <span class="calhelp-process__nav-connector" aria-hidden="true"></span>
          </li>
          <li class="calhelp-process__nav-item">
            <button type="button"
                    class="calhelp-process__nav-button"
                    data-calhelp-step-trigger="golive"
                    aria-controls="process-step-golive">
              <span class="calhelp-process__nav-index" aria-hidden="true">5</span>
              <span class="calhelp-process__nav-label">Go-Live &amp; Monitoring</span>
            </button>
          </li>
        </ol>
        <div class="calhelp-process__stages">
          <article id="process-step-readiness"
                   class="uk-card uk-card-primary uk-card-body calhelp-process__stage calhelp-process__stage--active calhelp-process__stage--panel-open"
                   data-calhelp-step="readiness">
            <header class="calhelp-process__stage-header">
              <h3>Readiness-Check</h3>
              <p>Systeminventar, Datenumfang, Besonderheiten (z. B. Anhänge, benutzerdefinierte Felder).</p>
            </header>
            <button type="button"
                    class="calhelp-process__toggle"
                    data-calhelp-step-toggle
                    aria-expanded="true"
                    aria-controls="process-panel-readiness">
              <span class="calhelp-process__toggle-label"
                    data-calhelp-i18n
                    data-i18n-de="Leistungen &amp; Abnahme ausblenden"
                    data-i18n-en="Hide deliverables &amp; acceptance"
                    data-calhelp-toggle-label-de-expanded="Leistungen &amp; Abnahme ausblenden"
                    data-calhelp-toggle-label-de-collapsed="Leistungen &amp; Abnahme anzeigen"
                    data-calhelp-toggle-label-en-expanded="Hide deliverables &amp; acceptance"
                    data-calhelp-toggle-label-en-collapsed="Show deliverables &amp; acceptance">Leistungen &amp; Abnahme ausblenden</span>
              <span class="calhelp-process__toggle-icon" aria-hidden="true"></span>
            </button>
            <div id="process-panel-readiness"
                 class="calhelp-process__panel is-open"
                 data-calhelp-step-panel>
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
                   data-calhelp-step="mapping">
            <header class="calhelp-process__stage-header">
              <h3>Mapping &amp; Regeln</h3>
              <p>Felder, SI-Präfixe, Status/Workflows, Rollen. Transparent dokumentiert.</p>
            </header>
            <button type="button"
                    class="calhelp-process__toggle"
                    data-calhelp-step-toggle
                    aria-expanded="true"
                    aria-controls="process-panel-mapping">
              <span class="calhelp-process__toggle-label"
                    data-calhelp-i18n
                    data-i18n-de="Leistungen &amp; Abnahme ausblenden"
                    data-i18n-en="Hide deliverables &amp; acceptance"
                    data-calhelp-toggle-label-de-expanded="Leistungen &amp; Abnahme ausblenden"
                    data-calhelp-toggle-label-de-collapsed="Leistungen &amp; Abnahme anzeigen"
                    data-calhelp-toggle-label-en-expanded="Hide deliverables &amp; acceptance"
                    data-calhelp-toggle-label-en-collapsed="Show deliverables &amp; acceptance">Leistungen &amp; Abnahme ausblenden</span>
              <span class="calhelp-process__toggle-icon" aria-hidden="true"></span>
            </button>
            <div id="process-panel-mapping"
                 class="calhelp-process__panel"
                 data-calhelp-step-panel>
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
                   data-calhelp-step="pilot">
            <header class="calhelp-process__stage-header">
              <h3>Pilot &amp; Validierung</h3>
              <p>Teilmenge (Golden Samples), Checksummen, Abweichungsbericht. Freigabe als Gate.</p>
            </header>
            <button type="button"
                    class="calhelp-process__toggle"
                    data-calhelp-step-toggle
                    aria-expanded="true"
                    aria-controls="process-panel-pilot">
              <span class="calhelp-process__toggle-label"
                    data-calhelp-i18n
                    data-i18n-de="Leistungen &amp; Abnahme ausblenden"
                    data-i18n-en="Hide deliverables &amp; acceptance"
                    data-calhelp-toggle-label-de-expanded="Leistungen &amp; Abnahme ausblenden"
                    data-calhelp-toggle-label-de-collapsed="Leistungen &amp; Abnahme anzeigen"
                    data-calhelp-toggle-label-en-expanded="Hide deliverables &amp; acceptance"
                    data-calhelp-toggle-label-en-collapsed="Show deliverables &amp; acceptance">Leistungen &amp; Abnahme ausblenden</span>
              <span class="calhelp-process__toggle-icon" aria-hidden="true"></span>
            </button>
            <div id="process-panel-pilot"
                 class="calhelp-process__panel"
                 data-calhelp-step-panel>
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
                   data-calhelp-step="cutover">
            <header class="calhelp-process__stage-header">
              <h3>Delta-Sync &amp; Cutover</h3>
              <p>Downtime-arm, sauber geplantes Übergabefenster, klarer Abnahmelauf.</p>
            </header>
            <button type="button"
                    class="calhelp-process__toggle"
                    data-calhelp-step-toggle
                    aria-expanded="true"
                    aria-controls="process-panel-cutover">
              <span class="calhelp-process__toggle-label"
                    data-calhelp-i18n
                    data-i18n-de="Leistungen &amp; Abnahme ausblenden"
                    data-i18n-en="Hide deliverables &amp; acceptance"
                    data-calhelp-toggle-label-de-expanded="Leistungen &amp; Abnahme ausblenden"
                    data-calhelp-toggle-label-de-collapsed="Leistungen &amp; Abnahme anzeigen"
                    data-calhelp-toggle-label-en-expanded="Hide deliverables &amp; acceptance"
                    data-calhelp-toggle-label-en-collapsed="Show deliverables &amp; acceptance">Leistungen &amp; Abnahme ausblenden</span>
              <span class="calhelp-process__toggle-icon" aria-hidden="true"></span>
            </button>
            <div id="process-panel-cutover"
                 class="calhelp-process__panel"
                 data-calhelp-step-panel>
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
                   data-calhelp-step="golive">
            <header class="calhelp-process__stage-header">
              <h3>Go-Live &amp; Monitoring</h3>
              <p>KPIs, Protokolle, Hypercare-Phase. Stabil in den Betrieb überführt.</p>
            </header>
            <button type="button"
                    class="calhelp-process__toggle"
                    data-calhelp-step-toggle
                    aria-expanded="true"
                    aria-controls="process-panel-golive">
              <span class="calhelp-process__toggle-label"
                    data-calhelp-i18n
                    data-i18n-de="Leistungen &amp; Abnahme ausblenden"
                    data-i18n-en="Hide deliverables &amp; acceptance"
                    data-calhelp-toggle-label-de-expanded="Leistungen &amp; Abnahme ausblenden"
                    data-calhelp-toggle-label-de-collapsed="Leistungen &amp; Abnahme anzeigen"
                    data-calhelp-toggle-label-en-expanded="Hide deliverables &amp; acceptance"
                    data-calhelp-toggle-label-en-collapsed="Show deliverables &amp; acceptance">Leistungen &amp; Abnahme ausblenden</span>
              <span class="calhelp-process__toggle-icon" aria-hidden="true"></span>
            </button>
            <div id="process-panel-golive"
                 class="calhelp-process__panel"
                 data-calhelp-step-panel>
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
    </div>
  </section>

  <section id="comparison" class="uk-section uk-section-muted calhelp-section" aria-labelledby="comparison-title">
    <div class="uk-container">
      <div class="calhelp-section__header">
        <h2 id="comparison-title"
            class="uk-heading-medium"
            data-calhelp-i18n
            data-i18n-de="Alltag vorher vs. nachher"
            data-i18n-en="Everyday work before vs. after">Alltag vorher vs. nachher</h2>
        <p data-calhelp-i18n
           data-i18n-de="Wie calHelp Abläufe verändert – drei Beispiele aus dem Betrieb."
           data-i18n-en="How calHelp changes operations – three real-world examples.">Wie calHelp Abläufe verändert – drei Beispiele aus dem Betrieb.</p>
      </div>
      <div class="calhelp-comparison" data-calhelp-comparison>
        <article class="uk-card uk-card-primary uk-card-body calhelp-comparison__card"
                 aria-labelledby="comparison-card-data-title"
                 data-calhelp-comparison-card="data"
                 data-calhelp-comparison-default="after">
          <header class="calhelp-comparison__header">
            <p id="comparison-card-data-title"
               class="calhelp-comparison__eyebrow"
               data-calhelp-i18n
               data-i18n-de="Datenpflege"
               data-i18n-en="Data upkeep">Datenpflege</p>
            <div class="calhelp-comparison__toggle-group"
                 role="group"
                 data-calhelp-i18n
                 data-calhelp-i18n-attr="aria-label"
                 data-i18n-de="Zustand für Datenpflege wechseln"
                 data-i18n-en="Switch data upkeep state"
                 aria-label="Zustand für Datenpflege wechseln">
              <button type="button"
                      class="calhelp-comparison__toggle"
                      data-comparison-toggle="before"
                      aria-pressed="false"
                      aria-controls="comparison-card-data-before"
                      data-calhelp-i18n
                      data-i18n-de="Vorher"
                      data-i18n-en="Before">Vorher</button>
              <button type="button"
                      class="calhelp-comparison__toggle"
                      data-comparison-toggle="after"
                      aria-pressed="true"
                      aria-controls="comparison-card-data-after"
                      data-calhelp-i18n
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
              <p data-calhelp-i18n
                 data-i18n-de="Stammdaten in Excel, lokale Ablagen, Absprachen per E-Mail. Jede Korrektur kostet Zeit und erzeugt neue Versionen."
                 data-i18n-en="Master data lives in Excel and local folders, coordination happens via email. Every correction costs time and spawns another version.">Stammdaten in Excel, lokale Ablagen, Absprachen per E-Mail. Jede Korrektur kostet Zeit und erzeugt neue Versionen.</p>
              <dl class="calhelp-comparison__metrics">
                <div class="calhelp-comparison__metric">
                  <dt data-calhelp-i18n
                      data-i18n-de="Parallel gepflegte Quellen"
                      data-i18n-en="Sources maintained in parallel">Parallel gepflegte Quellen</dt>
                  <dd data-calhelp-i18n
                      data-i18n-de="3 Systeme"
                      data-i18n-en="3 systems">3 Systeme</dd>
                </div>
                <div class="calhelp-comparison__metric">
                  <dt data-calhelp-i18n
                      data-i18n-de="Aktualisierung"
                      data-i18n-en="Update cycle">Aktualisierung</dt>
                  <dd data-calhelp-i18n
                      data-i18n-de="&gt; 48&nbsp;h Rückstand"
                      data-i18n-en="&gt; 48&nbsp;h lag">&gt; 48&nbsp;h Rückstand</dd>
                </div>
              </dl>
            </div>
            <div id="comparison-card-data-after"
                 class="calhelp-comparison__state is-active"
                 data-comparison-state="after">
              <p data-calhelp-i18n
                 data-i18n-de="Zentrale Stammdaten mit Validierungsregeln, Änderungen mit Pflichtfeldern dokumentiert. Teams pflegen direkt im System."
                 data-i18n-en="Central master data with validation rules, required fields document every change. Teams maintain records directly in the platform.">Zentrale Stammdaten mit Validierungsregeln, Änderungen mit Pflichtfeldern dokumentiert. Teams pflegen direkt im System.</p>
              <dl class="calhelp-comparison__metrics">
                <div class="calhelp-comparison__metric">
                  <dt data-calhelp-i18n
                      data-i18n-de="Suchzeit"
                      data-i18n-en="Search time">Suchzeit</dt>
                  <dd data-calhelp-i18n
                      data-i18n-de="−35&nbsp;%"
                      data-i18n-en="−35%">−35&nbsp;%</dd>
                </div>
                <div class="calhelp-comparison__metric">
                  <dt data-calhelp-i18n
                      data-i18n-de="Datenquelle"
                      data-i18n-en="Data source">Datenquelle</dt>
                  <dd data-calhelp-i18n
                      data-i18n-de="1 konsolidiertes System"
                      data-i18n-en="1 consolidated system">1 konsolidiertes System</dd>
                </div>
              </dl>
            </div>
          </div>
        </article>
        <article class="uk-card uk-card-primary uk-card-body calhelp-comparison__card"
                 aria-labelledby="comparison-card-approval-title"
                 data-calhelp-comparison-card="approval"
                 data-calhelp-comparison-default="after">
          <header class="calhelp-comparison__header">
            <p id="comparison-card-approval-title"
               class="calhelp-comparison__eyebrow"
               data-calhelp-i18n
               data-i18n-de="Report-Freigabe"
               data-i18n-en="Report approval">Report-Freigabe</p>
            <div class="calhelp-comparison__toggle-group"
                 role="group"
                 data-calhelp-i18n
                 data-calhelp-i18n-attr="aria-label"
                 data-i18n-de="Zustand für Report-Freigabe wechseln"
                 data-i18n-en="Switch report approval state"
                 aria-label="Zustand für Report-Freigabe wechseln">
              <button type="button"
                      class="calhelp-comparison__toggle"
                      data-comparison-toggle="before"
                      aria-pressed="false"
                      aria-controls="comparison-card-approval-before"
                      data-calhelp-i18n
                      data-i18n-de="Vorher"
                      data-i18n-en="Before">Vorher</button>
              <button type="button"
                      class="calhelp-comparison__toggle"
                      data-comparison-toggle="after"
                      aria-pressed="true"
                      aria-controls="comparison-card-approval-after"
                      data-calhelp-i18n
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
              <p data-calhelp-i18n
                 data-i18n-de="Freigaben per E-Mail oder Excel, unklare Versionen, jedes Audit verlangt Nachfragen. Feedbackschleifen dauern Tage."
                 data-i18n-en="Approvals travel via email or Excel, versions stay unclear and every audit triggers follow-up questions. Review loops take days.">Freigaben per E-Mail oder Excel, unklare Versionen, jedes Audit verlangt Nachfragen. Feedbackschleifen dauern Tage.</p>
              <dl class="calhelp-comparison__metrics">
                <div class="calhelp-comparison__metric">
                  <dt data-calhelp-i18n
                      data-i18n-de="Feedbackschleifen"
                      data-i18n-en="Review loops">Feedbackschleifen</dt>
                  <dd data-calhelp-i18n
                      data-i18n-de="Ø 3 Runden"
                      data-i18n-en="avg. 3 rounds">Ø 3 Runden</dd>
                </div>
                <div class="calhelp-comparison__metric">
                  <dt data-calhelp-i18n
                      data-i18n-de="Nachvollziehbarkeit"
                      data-i18n-en="Traceability">Nachvollziehbarkeit</dt>
                  <dd data-calhelp-i18n
                      data-i18n-de="Audit-Trail nur manuell"
                      data-i18n-en="Audit trail manual only">Audit-Trail nur manuell</dd>
                </div>
              </dl>
            </div>
            <div id="comparison-card-approval-after"
                 class="calhelp-comparison__state is-active"
                 data-comparison-state="after">
              <p data-calhelp-i18n
                 data-i18n-de="Geführte Freigaben mit Rollen, Versionskontrolle und Pflichtkommentaren. Signaturen landen automatisch im Audit-Trail."
                 data-i18n-en="Guided approvals with roles, version control and required comments. Sign-offs land automatically in the audit trail.">Geführte Freigaben mit Rollen, Versionskontrolle und Pflichtkommentaren. Signaturen landen automatisch im Audit-Trail.</p>
              <dl class="calhelp-comparison__metrics">
                <div class="calhelp-comparison__metric">
                  <dt data-calhelp-i18n
                      data-i18n-de="Feedbackschleifen"
                      data-i18n-en="Review loops">Feedbackschleifen</dt>
                  <dd data-calhelp-i18n
                      data-i18n-de="−50&nbsp;%"
                      data-i18n-en="−50%">−50&nbsp;%</dd>
                </div>
                <div class="calhelp-comparison__metric">
                  <dt data-calhelp-i18n
                      data-i18n-de="Signatur-Log"
                      data-i18n-en="Signature log">Signatur-Log</dt>
                  <dd data-calhelp-i18n
                      data-i18n-de="100&nbsp;% automatisch"
                      data-i18n-en="100% automated">100&nbsp;% automatisch</dd>
                </div>
              </dl>
            </div>
          </div>
        </article>
        <article class="uk-card uk-card-primary uk-card-body calhelp-comparison__card"
                 aria-labelledby="comparison-card-audit-title"
                 data-calhelp-comparison-card="audit"
                 data-calhelp-comparison-default="after">
          <header class="calhelp-comparison__header">
            <p id="comparison-card-audit-title"
               class="calhelp-comparison__eyebrow"
               data-calhelp-i18n
               data-i18n-de="Audit-Vorbereitung"
               data-i18n-en="Audit preparation">Audit-Vorbereitung</p>
            <div class="calhelp-comparison__toggle-group"
                 role="group"
                 data-calhelp-i18n
                 data-calhelp-i18n-attr="aria-label"
                 data-i18n-de="Zustand für Audit-Vorbereitung wechseln"
                 data-i18n-en="Switch audit preparation state"
                 aria-label="Zustand für Audit-Vorbereitung wechseln">
              <button type="button"
                      class="calhelp-comparison__toggle"
                      data-comparison-toggle="before"
                      aria-pressed="false"
                      aria-controls="comparison-card-audit-before"
                      data-calhelp-i18n
                      data-i18n-de="Vorher"
                      data-i18n-en="Before">Vorher</button>
              <button type="button"
                      class="calhelp-comparison__toggle"
                      data-comparison-toggle="after"
                      aria-pressed="true"
                      aria-controls="comparison-card-audit-after"
                      data-calhelp-i18n
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
              <p data-calhelp-i18n
                 data-i18n-de="Nachweise liegen in Ordnern, Checklisten werden händisch gepflegt. Vor Audits werden Dokumente gesucht und Medien abgeglichen."
                 data-i18n-en="Evidence lives in folders, checklists stay manual. Before an audit the team hunts documents and reconciles media files.">Nachweise liegen in Ordnern, Checklisten werden händisch gepflegt. Vor Audits werden Dokumente gesucht und Medien abgeglichen.</p>
              <dl class="calhelp-comparison__metrics">
                <div class="calhelp-comparison__metric">
                  <dt data-calhelp-i18n
                      data-i18n-de="Vorbereitungszeit"
                      data-i18n-en="Preparation time">Vorbereitungszeit</dt>
                  <dd data-calhelp-i18n
                      data-i18n-de="3 Tage"
                      data-i18n-en="3 days">3 Tage</dd>
                </div>
                <div class="calhelp-comparison__metric">
                  <dt data-calhelp-i18n
                      data-i18n-de="Ablage"
                      data-i18n-en="Storage">Ablage</dt>
                  <dd data-calhelp-i18n
                      data-i18n-de="5+ verstreute Ordner"
                      data-i18n-en="5+ scattered folders">5+ verstreute Ordner</dd>
                </div>
              </dl>
            </div>
            <div id="comparison-card-audit-after"
                 class="calhelp-comparison__state is-active"
                 data-comparison-state="after">
              <p data-calhelp-i18n
                 data-i18n-de="Audit-Workspace mit Checkliste, Report-Diffs und Messmittelhistorie. Nachweise stehen sortiert bereit, inklusive Ansprechpartner:in."
                 data-i18n-en="Audit workspace with checklist, report diffs and instrument history. Evidence is pre-sorted including the responsible contact.">Audit-Workspace mit Checkliste, Report-Diffs und Messmittelhistorie. Nachweise stehen sortiert bereit, inklusive Ansprechpartner:in.</p>
              <dl class="calhelp-comparison__metrics">
                <div class="calhelp-comparison__metric">
                  <dt data-calhelp-i18n
                      data-i18n-de="Aufwand"
                      data-i18n-en="Effort">Aufwand</dt>
                  <dd data-calhelp-i18n
                      data-i18n-de="−60&nbsp;%"
                      data-i18n-en="−60%">−60&nbsp;%</dd>
                </div>
                <div class="calhelp-comparison__metric">
                  <dt data-calhelp-i18n
                      data-i18n-de="Bereitstellung"
                      data-i18n-en="Preparation">Bereitstellung</dt>
                  <dd data-calhelp-i18n
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
    "usecases": [
      {
        "id": "lab",
        "title": {
          "de": "Kalibrierlabor",
          "en": "Calibration lab"
        },
        "statement": {
          "de": "\u201eWir m\u00fcssen Zertifikate schneller und nachvollziehbar erzeugen.\u201c",
          "en": "\u201cWe need to issue certificates faster with full traceability.\u201d"
        },
        "tasks": {
          "de": [
            "Pr\u00fcfmittel-Stammdaten konsolidieren",
            "Kalibrierauftr\u00e4ge priorisieren",
            "Zertifikatsvorlagen versionieren",
            "Messmittel-Historie protokollieren"
          ],
          "en": [
            "Consolidate calibration master data",
            "Prioritise calibration orders",
            "Version certificate templates",
            "Track instrument history"
          ]
        },
        "results": {
          "de": [
            { "label": "Zertifikatslaufzeit", "kpi": "\u2264 2 Minuten" },
            { "label": "R\u00fcckverfolgbare Pr\u00fcfwege", "kpi": "100 %" },
            { "label": "Auditfertige Reports", "kpi": "24/7" }
          ],
          "en": [
            { "label": "Certificate lead time", "kpi": "\u2264 2 minutes" },
            { "label": "Traceable calibration paths", "kpi": "100%" },
            { "label": "Audit-ready reports", "kpi": "24/7" }
          ]
        },
        "snippet": {
          "url": "https://calhelp.notion.site/kalibrierlabor-snippet"
        }
      },
      {
        "id": "inspection",
        "title": {
          "de": "Pr\u00fcfstelle",
          "en": "Test laboratory"
        },
        "statement": {
          "de": "\u201eWir m\u00fcssen Au\u00dfentermine l\u00fcckenlos dokumentieren.\u201c",
          "en": "\u201cWe have to document on-site inspections without gaps.\u201d"
        },
        "tasks": {
          "de": [
            "Checklisten f\u00fcr Vor-Ort-Pr\u00fcfungen",
            "Beweissicherung per Foto",
            "Messwerte offline erfassen",
            "Abweichungen dokumentieren"
          ],
          "en": [
            "Provide on-site inspection checklists",
            "Capture evidence photos",
            "Record measurements offline",
            "Document deviations"
          ]
        },
        "results": {
          "de": [
            { "label": "Vor-Ort-Zeit pro Auftrag", "kpi": "\u2212 18 %" },
            { "label": "Abschlussquote Erstpr\u00fcfung", "kpi": "98 %" },
            { "label": "Digitale Nachweise", "kpi": "100 %" }
          ],
          "en": [
            { "label": "Field visit per job", "kpi": "\u2212 18%" },
            { "label": "First-time completion rate", "kpi": "98%" },
            { "label": "Digital evidence", "kpi": "100%" }
          ]
        },
        "snippet": {
          "url": "https://calhelp.notion.site/pruefstelle-snippet"
        }
      },
      {
        "id": "service",
        "title": {
          "de": "Instandhaltung/Service",
          "en": "Maintenance/Service"
        },
        "statement": {
          "de": "\u201eWir wollen Wartungen planen, Nachweise sichern und R\u00fcckfragen reduzieren.\u201c",
          "en": "\u201cWe want predictable maintenance, reliable evidence and fewer call-backs.\u201d"
        },
        "tasks": {
          "de": [
            "Wartungspl\u00e4ne generieren",
            "Ersatzteile disponieren",
            "Serviceeins\u00e4tze tracken",
            "SLAs \u00fcberwachen"
          ],
          "en": [
            "Generate maintenance plans",
            "Plan spare parts",
            "Track service visits",
            "Monitor SLAs"
          ]
        },
        "results": {
          "de": [
            { "label": "Reaktionszeit St\u00f6rung", "kpi": "< 4 h" },
            { "label": "Planerf\u00fcllung Wartung", "kpi": "95 %" },
            { "label": "Ticketdurchlaufzeit", "kpi": "\u2212 22 %" }
          ],
          "en": [
            { "label": "Incident response time", "kpi": "< 4 h" },
            { "label": "Maintenance plan adherence", "kpi": "95%" },
            { "label": "Ticket cycle time", "kpi": "\u2212 22%" }
          ]
        },
        "snippet": {
          "url": "https://calhelp.notion.site/instandhaltung-snippet"
        }
      },
      {
        "id": "public",
        "title": {
          "de": "\u00d6ffentliche Verwaltung",
          "en": "Public administration"
        },
        "statement": {
          "de": "\u201eWir brauchen konsistente Prozesse, belastbare Nachweise und DSGVO-Konformit\u00e4t.\u201c",
          "en": "\u201cWe need consistent processes, solid evidence and GDPR compliance.\u201d"
        },
        "tasks": {
          "de": [
            "Rollen & Freigaben definieren",
            "Verfahrensdokumentation pflegen",
            "Aufbewahrungsfristen steuern",
            "Vergaberegeln nachvollziehen"
          ],
          "en": [
            "Define roles and approvals",
            "Maintain procedure documentation",
            "Control retention periods",
            "Track procurement rules"
          ]
        },
        "results": {
          "de": [
            { "label": "Revisionssichere Vorg\u00e4nge", "kpi": "100 %" },
            { "label": "Bearbeitungszeit Bescheide", "kpi": "\u2212 15 %" },
            { "label": "DSGVO-konforme Abl\u00e4ufe", "kpi": "100 %" }
          ],
          "en": [
            { "label": "Audit-proof cases", "kpi": "100%" },
            { "label": "Decision turnaround", "kpi": "\u2212 15%" },
            { "label": "GDPR-compliant workflows", "kpi": "100%" }
          ]
        },
        "snippet": {
          "url": "https://calhelp.notion.site/oeffentliche-verwaltung-snippet"
        }
      },
      {
        "id": "inventory",
        "title": {
          "de": "Inventarverwaltung",
          "en": "Asset management"
        },
        "statement": {
          "de": "\u201eWir wollen Best\u00e4nde transparent halten und Verf\u00fcgbarkeit im Blick behalten.\u201c",
          "en": "\u201cWe need transparent inventories and actionable availability data.\u201d"
        },
        "tasks": {
          "de": [
            "Asset-Lebenszyklen abbilden",
            "Inventur per QR-Code",
            "Statuswechsel automatisieren",
            "Nutzungsberichte bereitstellen"
          ],
          "en": [
            "Map asset life cycles",
            "Run inventory with QR codes",
            "Automate status transitions",
            "Provide utilisation reports"
          ]
        },
        "results": {
          "de": [
            { "label": "Inventur-Durchlauf", "kpi": "\u2264 48 h" },
            { "label": "Datenkonsistenz", "kpi": "99,5 %" },
            { "label": "Auslastungsreporting", "kpi": "W\u00f6chentlich" }
          ],
          "en": [
            { "label": "Inventory cycle", "kpi": "\u2264 48 h" },
            { "label": "Data consistency", "kpi": "99.5%" },
            { "label": "Utilisation reporting", "kpi": "Weekly" }
          ]
        },
        "snippet": {
          "url": "https://calhelp.notion.site/inventar-snippet"
        }
      }
    ]
  }
  </script>
  <div data-page-usecases></div>

  <section id="proof" class="uk-section calhelp-section" aria-labelledby="proof-title">
    <div class="uk-container">
      <div class="calhelp-section__header">
        <h2 id="proof-title" class="uk-heading-medium">Beweis &amp; Sicherheit</h2>
        <p class="uk-text-lead">Referenzen, Datenschutz und Qualitätsnachweise auf einen Blick.</p>
      </div>
      <div class="uk-grid-large uk-child-width-1-3@m uk-grid-match" data-uk-grid>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="proof-ref-title">
          <h3 id="proof-ref-title" class="uk-card-title">Referenzen</h3>
          <p>Produktiv eingesetzte Migrationen von MET/TRACK, fortlaufende MET/TEAM-Anbindung.</p>
        </article>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="proof-security-title">
          <h3 id="proof-security-title" class="uk-card-title">Sicherheit &amp; DSGVO</h3>
          <p>Hosting in DE (oder On-Prem), rollenbasierte Zugriffe, Protokollierung, nachvollziehbare Lösch-/Aufbewahrungsregeln.</p>
        </article>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="proof-quality-title">
          <h3 id="proof-quality-title" class="uk-card-title">Qualitätscheck</h3>
          <p>Musterzertifikate, visuelle Report-Diffs, dokumentierte Feld-Mappings.</p>
        </article>
      </div>
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
      <div data-calhelp-proof-gallery></div>
      <div class="calhelp-kpi uk-card uk-card-primary uk-card-body">
        <p class="uk-margin-remove">15+ Jahre Projekterfahrung · 1.600+ umgesetzte Kund:innen-Wünsche · 99,9 % Betriebszeit (aktuell)</p>
      </div>
    </div>
  </section>

  <section id="services" class="uk-section uk-section-muted calhelp-section" aria-labelledby="services-title">
    <div class="uk-container">
      <div class="calhelp-section__header">
        <h2 id="services-title" class="uk-heading-medium">Produktisierte Services – verständlich &amp; kaufbar</h2>
        <p class="uk-text-lead">Vom ersten Check bis zum stabilen Betrieb – modular buchbar.</p>
      </div>
      <div class="uk-grid-large uk-child-width-1-3@m uk-grid-match" data-uk-grid>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="service-s-title">
          <h3 id="service-s-title" class="uk-card-title">Paket S – Migration-Check (Fixpreis)</h3>
          <p>Analyse, Feld-Mapping-Skizze, Risikoabschätzung, Zeitplan. Ergebnis: Entscheidungsgrundlage &amp; Angebot.</p>
        </article>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="service-m-title">
          <h3 id="service-m-title" class="uk-card-title">Paket M – Pilot &amp; Cutover-Plan</h3>
          <p>Teilmenge migrieren, Validierung, Abweichungsbericht, Go-/No-Go-Empfehlung. Ergebnis: belastbarer Cutover-Plan.</p>
        </article>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="service-l-title">
          <h3 id="service-l-title" class="uk-card-title">Paket L – Vollmigration &amp; Hypercare</h3>
          <p>Vollübernahme, Delta-Sync, Go-Live-Begleitung (30 Tage), Monitoring mit KPIs. Ergebnis: stabiler Betrieb.</p>
        </article>
      </div>
      <aside class="calhelp-addons uk-card uk-card-primary uk-card-body" aria-label="Add-ons">
        <h3>Add-ons</h3>
        <ul class="uk-list uk-list-bullet">
          <li>DAkkS-Report-Bundle (zweisprachig)</li>
          <li>SSO-Starter (EntraID/Google)</li>
          <li>API-Starter (Integrationsrezepte)</li>
        </ul>
      </aside>
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

  <section id="demo" class="uk-section calhelp-section" aria-labelledby="demo-title">
    <div class="uk-container">
      <div class="calhelp-section__header">
        <h2 id="demo-title" class="uk-heading-medium">Demo – Micro-Onboarding statt Formular</h2>
        <p class="uk-text-lead">In 60–90 Sekunden zur passenden Demo: ein kurzer Frage-Flow, damit wir Ihr Szenario vorbereiten können.</p>
      </div>
      <div class="uk-grid-large" data-uk-grid>
        <div class="uk-width-1-2@m">
          <ol class="calhelp-demo-steps uk-card uk-card-primary uk-card-body" aria-label="Fragen für den Demo-Flow">
            <li>Wofür möchten Sie das System nutzen? (Labor | Instandhaltung | Verwaltung | Sonstiges)</li>
            <li>Datenbasis? (MET/TRACK | MET/TEAM | CSV/Excel | unklar)</li>
            <li>Umfang? (&lt;1.000 | 1.000–10.000 | &gt;10.000 | unklar)</li>
            <li>Zeitfenster? (ASAP | 1–3 Mon | 3–6 Mon | Evaluierung offen)</li>
            <li>Abschluss (Kontaktfelder + freiwilliger Newsletter-Opt-in)</li>
          </ol>
        </div>
        <div class="uk-width-1-2@m">
          <div class="uk-card uk-card-primary uk-card-body calhelp-card">
            <h3 class="uk-card-title">Abschluss-Screen</h3>
            <p>Zwei Optionen führen zum nächsten Schritt – individuell vorbereitet.</p>
            <ul class="uk-list uk-list-divider calhelp-cta-list">
              <li>Demo-Termin wählen</li>
              <li>MET/CAL-Handbuch öffnen</li>
            </ul>
            <p class="uk-text-small uk-margin-top">Abläufe sind nachvollziehbar: wer, was, wann.</p>
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
      <div class="uk-grid-large uk-child-width-1-2@m" data-uk-grid>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="news-changelog-title">
          <h3 id="news-changelog-title" class="uk-card-title">Changelog kompakt</h3>
          <p class="uk-text-meta">Zuletzt aktualisiert am 04.10.2025</p>
          <ul class="uk-list uk-list-bullet">
            <li>Migration: Delta-Sync für MET/TRACK erweitert.</li>
            <li>Reports: Konformitätslogik mit Guardband-Optionen ergänzt.</li>
            <li>Integrationen: MET/TEAM-Connector mit zusätzlichen Webhooks.</li>
          </ul>
        </article>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="news-recipe-title">
          <h3 id="news-recipe-title" class="uk-card-title">Praxisrezept in 3 Schritten</h3>
          <p class="uk-text-meta">Zuletzt aktualisiert am 27.09.2025</p>
          <p><strong>Thema:</strong> Konformitätslegende sauber integrieren.</p>
          <ol class="uk-list uk-list-decimal">
            <li>Legende zentral in calHelp pflegen.</li>
            <li>Template-Varianten für Kund:innen definieren.</li>
            <li>Report-Diffs mit Golden Samples gegenprüfen.</li>
          </ol>
        </article>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="news-usecase-title">
          <h3 id="news-usecase-title" class="uk-card-title">Use-Case-Spotlight</h3>
          <p class="uk-text-meta">Zuletzt aktualisiert am 18.09.2025</p>
          <p><strong>Ausgangslage:</strong> Stark gewachsene Kalibrierabteilung mit Inseltools.</p>
          <p><strong>Vorgehen:</strong> Migration aus MET/TRACK, Schnittstelle zu MET/TEAM, SSO.</p>
          <p><strong>Ergebnis:</strong> Auditberichte in 30 % weniger Zeit, klare Verantwortlichkeiten.</p>
          <p><strong>Learnings:</strong> Frühzeitig Rollenmodell definieren, Dokumentation als laufenden Prozess etablieren.</p>
          <p><strong>Nächste Schritte:</strong> Automatisierte Erinnerungen für Prüfmittel und Lieferant:innen.</p>
        </article>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="news-standards-title">
          <h3 id="news-standards-title" class="uk-card-title">Standards verständlich</h3>
          <p class="uk-text-meta">Zuletzt aktualisiert am 12.09.2025</p>
          <p><strong>Thema:</strong> Guardband &amp; MU in 5 Minuten erklärt.</p>
          <p>Beispiel: Messwert 10,0 mm mit MU 0,3 mm. Guardband reduziert die Toleranzgrenze auf 9,7–10,3 mm. calHelp dokumentiert automatisch, wie Entscheidung und Unsicherheit zusammenhängen.</p>
        </article>
        <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="news-roadmap-title">
          <h3 id="news-roadmap-title" class="uk-card-title">Roadmap-Ausblick</h3>
          <p class="uk-text-meta">Zuletzt aktualisiert am 05.09.2025</p>
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
        <a class="uk-button uk-button-default" href="#demo">Zum Demo-Flow</a>
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
        <p class="uk-text-lead">Starten Sie mit einem Migration-Check oder testen Sie unseren Demo-Flow. Wir melden uns mit einer passgenauen Empfehlung.</p>
      </div>
      <div class="calhelp-cta__actions" role="group" aria-label="Abschluss-CTAs">
        <a class="uk-button uk-button-primary" href="#services">Migration prüfen lassen</a>
        <a class="uk-button uk-button-default" href="#demo">Demo anfragen</a>
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
        <p><strong>Seitentitel:</strong> Umstieg auf ein zentrales Kalibrier-System – konsistent, nachvollziehbar, auditfähig</p>
        <p><strong>Beschreibung:</strong> calHelp migriert Altdaten, bindet MET/TEAM an und stabilisiert Abläufe – konsistent, nachvollziehbar, auditfähig.</p>
        <p><strong>Open-Graph-Hinweis:</strong> „Ein System. Klare Prozesse.“</p>
      </div>
    </div>
  </section>
    $$
)
ON CONFLICT (slug) DO UPDATE
SET title = EXCLUDED.title,
    content = EXCLUDED.content,
    updated_at = CURRENT_TIMESTAMP;
