-- Replace inline Twig markup on the calHelp page with placeholders handled by Twig partials.
UPDATE pages
SET content = replace(
  content,
  $$    <details class="calhelp-proof-details">
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
    </details>$$,
  $$    <div data-calhelp-assurance></div>
$$
)
WHERE slug = 'calhelp';

UPDATE pages
SET content = replace(
  content,
  $$<section id="cases" class="uk-section calhelp-section" aria-labelledby="cases-title">
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
</section>$$,
  $$<div data-calhelp-cases></div>
$$
)
WHERE slug = 'calhelp';
