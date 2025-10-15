-- Switch the MET/CAL checklist download from PDF to Markdown and adjust inline copy.
UPDATE pages
SET content = REPLACE(
    content,
    $$          <div class="calserver-metcal__cta" role="group" aria-label="Weiterführende Aktionen">
            <a class="uk-button uk-button-primary" href="{{ basePath }}/fluke-metcal" data-analytics-event="nav_internal" data-analytics-context="metcal_teaser" data-analytics-from="#calserver" data-analytics-to="#metcal">
              <span class="uk-margin-small-right" data-uk-icon="icon: arrow-right"></span>Zur Landingpage MET/CAL</a>
            <a class="uk-button uk-button-default" href="{{ basePath }}/downloads/metcal-migrations-checkliste.pdf" data-analytics-event="download_checkliste" data-analytics-context="calserver_page" data-analytics-file="metcal-migrations-checkliste.pdf">
              <span class="uk-margin-small-right" data-uk-icon="icon: cloud-download"></span>Migrations-Checkliste</a>
          </div>
          <div class="calserver-metcal__checklist uk-margin-large-top" aria-labelledby="metcal-checklist-heading">
            <h3 class="uk-heading-bullet uk-margin-remove-bottom" id="metcal-checklist-heading">Migrations-Checkliste im Überblick</h3>
            <p class="uk-margin-small-top">Sieben Schritte von Discovery bis Nachbetreuung. Die vollständige Checkliste steht weiterhin als PDF bereit.</p>
            <ol class="uk-margin-medium-top" aria-label="Sieben Schritte der MET/CAL Migration">
              <li>
                <h4 class="uk-margin-small-bottom">Discovery &amp; Scope</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Aktuelle MET/CAL- und MET/TEAM-Versionen sowie Integrationen erfassen.</li>
                  <li>Stakeholder dokumentieren: Kalibrierleitung, IT-Betrieb, Qualitätsmanagement, Partner.</li>
                  <li>Erfolgskriterien definieren (Verfügbarkeit, Reporting, regulatorische Anforderungen).</li>
                </ul>
              </li>
              <li>
                <h4 class="uk-margin-small-bottom">Datenbewertung</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Beispieldaten für Verfahren, Geräte, Zertifikate und Anhänge exportieren.</li>
                  <li>Feldzuordnungen von MET/CAL zu calServer inklusive Custom-Feldern dokumentieren.</li>
                  <li>Datenqualität prüfen (fehlende Kalibrierungen, verwaiste Assets, inkonsistente Toleranzen).</li>
                </ul>
              </li>
              <li>
                <h4 class="uk-margin-small-bottom">Infrastruktur vorbereiten</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Hosting-Modell festlegen (calServer Cloud oder dedizierter Tenant).</li>
                  <li>Authentifizierung und Berechtigungen (Azure AD, Google Workspace, lokale Konten) klären.</li>
                  <li>Backup-, Aufbewahrungs- und Verschlüsselungsvorgaben mit IT-Security abstimmen.</li>
                </ul>
              </li>
              <li>
                <h4 class="uk-margin-small-bottom">Migrationsplan</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Freeze-Fenster und Kommunikationsplan für Stakeholder abstimmen.</li>
                  <li>Synchronisationsregeln für Hybridbetrieb vorbereiten.</li>
                  <li>Validierungsskripte für Zertifikate, Asset-Status und Guardband-Berechnungen definieren.</li>
                </ul>
              </li>
              <li>
                <h4 class="uk-margin-small-bottom">Dry Run</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Sandbox-Daten importieren und End-to-End-Prozesse testen.</li>
                  <li>Generierte Zertifikate auf Layout, Sprache und Compliance prüfen.</li>
                  <li>Abweichungen dokumentieren und Maßnahmen priorisieren.</li>
                </ul>
              </li>
              <li>
                <h4 class="uk-margin-small-bottom">Go-Live</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Finalen Datenimport durchführen und Integrationen aktivieren.</li>
                  <li>Smoke-Tests mit Pilotanwendern (Kalender, Tickets, Reporting) abschließen.</li>
                  <li>Rollback-Kriterien dokumentieren und Sicherungen verifizieren.</li>
                </ul>
              </li>
              <li>
                <h4 class="uk-margin-small-bottom">Nachbetreuung</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Systemmetriken überwachen (Sync-Queues, Laufzeiten, Speicher).</li>
                  <li>Feedback aus Qualität und Betrieb sammeln und Maßnahmen priorisieren.</li>
                  <li>SOPs und Schulungsmaterialien auf calServer-Prozesse aktualisieren.</li>
                </ul>
              </li>
            </ol>
          </div>
          <p class="calserver-metcal__note">immer weiss: Hybridbetrieb? Kein Problem. Wir kombinieren MET/CAL, METTEAM und calServer so, dass Datenqualität, Audit-Trails und Rollenmodelle zusammenpassen.</p>$$,
    $$          <div class="calserver-metcal__cta" role="group" aria-label="Weiterführende Aktionen">
            <a class="uk-button uk-button-primary" href="{{ basePath }}/fluke-metcal" data-analytics-event="nav_internal" data-analytics-context="metcal_teaser" data-analytics-from="#calserver" data-analytics-to="#metcal">
              <span class="uk-margin-small-right" data-uk-icon="icon: arrow-right"></span>Zur Landingpage MET/CAL</a>
            <a class="uk-button uk-button-default" href="{{ basePath }}/downloads/metcal-migrations-checkliste.md" data-analytics-event="download_checkliste" data-analytics-context="calserver_page" data-analytics-file="metcal-migrations-checkliste.md">
              <span class="uk-margin-small-right" data-uk-icon="icon: cloud-download"></span>Migrations-Checkliste</a>
          </div>
          <div class="calserver-metcal__checklist uk-margin-large-top" aria-labelledby="metcal-checklist-heading">
            <h3 class="uk-heading-bullet uk-margin-remove-bottom" id="metcal-checklist-heading">Migrations-Checkliste im Überblick</h3>
            <p class="uk-margin-small-top">Sieben Schritte von Discovery bis Nachbetreuung. Die vollständige Checkliste steht weiterhin als Markdown-Datei bereit.</p>
            <ol class="uk-margin-medium-top" aria-label="Sieben Schritte der MET/CAL Migration">
              <li>
                <h4 class="uk-margin-small-bottom">Discovery &amp; Scope</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Aktuelle MET/CAL- und MET/TEAM-Versionen sowie Integrationen erfassen.</li>
                  <li>Stakeholder dokumentieren: Kalibrierleitung, IT-Betrieb, Qualitätsmanagement, Partner.</li>
                  <li>Erfolgskriterien definieren (Verfügbarkeit, Reporting, regulatorische Anforderungen).</li>
                </ul>
              </li>
              <li>
                <h4 class="uk-margin-small-bottom">Datenbewertung</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Beispieldaten für Verfahren, Geräte, Zertifikate und Anhänge exportieren.</li>
                  <li>Feldzuordnungen von MET/CAL zu calServer inklusive Custom-Feldern dokumentieren.</li>
                  <li>Datenqualität prüfen (fehlende Kalibrierungen, verwaiste Assets, inkonsistente Toleranzen).</li>
                </ul>
              </li>
              <li>
                <h4 class="uk-margin-small-bottom">Infrastruktur vorbereiten</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Hosting-Modell festlegen (calServer Cloud oder dedizierter Tenant).</li>
                  <li>Authentifizierung und Berechtigungen (Azure AD, Google Workspace, lokale Konten) klären.</li>
                  <li>Backup-, Aufbewahrungs- und Verschlüsselungsvorgaben mit IT-Security abstimmen.</li>
                </ul>
              </li>
              <li>
                <h4 class="uk-margin-small-bottom">Migrationsplan</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Freeze-Fenster und Kommunikationsplan für Stakeholder abstimmen.</li>
                  <li>Synchronisationsregeln für Hybridbetrieb vorbereiten.</li>
                  <li>Validierungsskripte für Zertifikate, Asset-Status und Guardband-Berechnungen definieren.</li>
                </ul>
              </li>
              <li>
                <h4 class="uk-margin-small-bottom">Dry Run</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Sandbox-Daten importieren und End-to-End-Prozesse testen.</li>
                  <li>Generierte Zertifikate auf Layout, Sprache und Compliance prüfen.</li>
                  <li>Abweichungen dokumentieren und Maßnahmen priorisieren.</li>
                </ul>
              </li>
              <li>
                <h4 class="uk-margin-small-bottom">Go-Live</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Finalen Datenimport durchführen und Integrationen aktivieren.</li>
                  <li>Smoke-Tests mit Pilotanwendern (Kalender, Tickets, Reporting) abschließen.</li>
                  <li>Rollback-Kriterien dokumentieren und Sicherungen verifizieren.</li>
                </ul>
              </li>
              <li>
                <h4 class="uk-margin-small-bottom">Nachbetreuung</h4>
                <ul class="uk-list uk-list-bullet uk-margin-remove-top">
                  <li>Systemmetriken überwachen (Sync-Queues, Laufzeiten, Speicher).</li>
                  <li>Feedback aus Qualität und Betrieb sammeln und Maßnahmen priorisieren.</li>
                  <li>SOPs und Schulungsmaterialien auf calServer-Prozesse aktualisieren.</li>
                </ul>
              </li>
            </ol>
          </div>
          <p class="calserver-metcal__note">immer weiss: Hybridbetrieb? Kein Problem. Wir kombinieren MET/CAL, METTEAM und calServer so, dass Datenqualität, Audit-Trails und Rollenmodelle zusammenpassen.</p>$$
)
WHERE slug = 'calserver';

UPDATE pages
SET content = REPLACE(
    content,
    $$          <div class="calserver-metcal__cta" role="group" aria-label="Follow-up actions">
        <a class="uk-button uk-button-primary" href="{{ basePath }}/fluke-metcal" data-analytics-event="nav_internal" data-analytics-context="metcal_teaser" data-analytics-from="#calserver" data-analytics-to="#metcal">
          <span class="uk-margin-small-right" data-uk-icon="icon: arrow-right"></span>Open MET/CAL landing page</a>
        <a class="uk-button uk-button-default" href="{{ basePath }}/downloads/metcal-migrations-checkliste.pdf" data-analytics-event="download_checkliste" data-analytics-context="calserver_page" data-analytics-file="metcal-migrations-checkliste.pdf">
          <span class="uk-margin-small-right" data-uk-icon="icon: cloud-download"></span>Migration checklist</a>
      </div>
      <div class="calserver-metcal__checklist uk-margin-large-top" aria-labelledby="metcal-checklist-heading-en">
        <h3 class="uk-heading-bullet uk-margin-remove-bottom" id="metcal-checklist-heading-en">Migration checklist highlights</h3>
        <p class="uk-margin-small-top">Seven stages from discovery to post go-live follow-up. The full checklist remains available as a PDF download.</p>
        <ol class="uk-margin-medium-top" aria-label="Seven MET/CAL migration stages">
          <li>
            <h4 class="uk-margin-small-bottom">Discovery &amp; Scope</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Confirm current MET/CAL and MET/TEAM versions and active integrations.</li>
              <li>Document stakeholders: calibration lead, IT operations, quality management, external partners.</li>
              <li>Define success criteria (uptime targets, reporting goals, regulatory requirements).</li>
            </ul>
          </li>
          <li>
            <h4 class="uk-margin-small-bottom">Data assessment</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Export representative procedures, instruments, certificates, and attachments.</li>
              <li>Map MET/CAL fields to calServer attributes, including custom fields and units.</li>
              <li>Identify data quality gaps (missing calibrations, orphaned assets, inconsistent tolerances).</li>
            </ul>
          </li>
          <li>
            <h4 class="uk-margin-small-bottom">Infrastructure preparation</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Decide on hosting (managed calServer cloud or dedicated tenant).</li>
              <li>Review authentication setup (Azure AD, Google Workspace, local accounts).</li>
              <li>Align backup, retention, and encryption controls with IT security policies.</li>
            </ul>
          </li>
          <li>
            <h4 class="uk-margin-small-bottom">Migration plan</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Schedule the freeze window and stakeholder communications.</li>
              <li>Configure sync rules for hybrid operations if MET/CAL stays active.</li>
              <li>Prepare validation scripts for certificates, asset statuses, and guardband calculations.</li>
            </ul>
          </li>
          <li>
            <h4 class="uk-margin-small-bottom">Dry run</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Import a sandbox dataset and validate end-to-end workflows.</li>
              <li>Review generated certificates for formatting, language, and compliance notes.</li>
              <li>Capture remediation actions for data mismatches or missing attachments.</li>
            </ul>
          </li>
          <li>
            <h4 class="uk-margin-small-bottom">Go-live</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Run the final data load and enable integrations (API, email ingestion, document storage).</li>
              <li>Complete smoke tests with pilot users covering scheduling, tickets, and reporting.</li>
              <li>Document rollback criteria and confirm backups before broader access.</li>
            </ul>
          </li>
          <li>
            <h4 class="uk-margin-small-bottom">Post go-live follow-up</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Monitor system health (sync queues, job runtimes, storage consumption).</li>
              <li>Gather feedback from quality and operations teams and prioritise quick wins.</li>
              <li>Update SOPs and training materials to reflect calServer workflows.</li>
            </ul>
          </li>
        </ol>
      </div>
      <p class="calserver-metcal__note">Hybrid operations? No problem. We align MET/CAL, METTEAM and calServer so data quality, audit trails and role models match.</p>$$,
    $$          <div class="calserver-metcal__cta" role="group" aria-label="Follow-up actions">
        <a class="uk-button uk-button-primary" href="{{ basePath }}/fluke-metcal" data-analytics-event="nav_internal" data-analytics-context="metcal_teaser" data-analytics-from="#calserver" data-analytics-to="#metcal">
          <span class="uk-margin-small-right" data-uk-icon="icon: arrow-right"></span>Open MET/CAL landing page</a>
        <a class="uk-button uk-button-default" href="{{ basePath }}/downloads/metcal-migrations-checkliste.md" data-analytics-event="download_checkliste" data-analytics-context="calserver_page" data-analytics-file="metcal-migrations-checkliste.md">
          <span class="uk-margin-small-right" data-uk-icon="icon: cloud-download"></span>Migration checklist</a>
      </div>
      <div class="calserver-metcal__checklist uk-margin-large-top" aria-labelledby="metcal-checklist-heading-en">
        <h3 class="uk-heading-bullet uk-margin-remove-bottom" id="metcal-checklist-heading-en">Migration checklist highlights</h3>
        <p class="uk-margin-small-top">Seven stages from discovery to post go-live follow-up. The full checklist remains available as a Markdown download.</p>
        <ol class="uk-margin-medium-top" aria-label="Seven MET/CAL migration stages">
          <li>
            <h4 class="uk-margin-small-bottom">Discovery &amp; Scope</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Confirm current MET/CAL and MET/TEAM versions and active integrations.</li>
              <li>Document stakeholders: calibration lead, IT operations, quality management, external partners.</li>
              <li>Define success criteria (uptime targets, reporting goals, regulatory requirements).</li>
            </ul>
          </li>
          <li>
            <h4 class="uk-margin-small-bottom">Data assessment</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Export representative procedures, instruments, certificates, and attachments.</li>
              <li>Map MET/CAL fields to calServer attributes, including custom fields and units.</li>
              <li>Identify data quality gaps (missing calibrations, orphaned assets, inconsistent tolerances).</li>
            </ul>
          </li>
          <li>
            <h4 class="uk-margin-small-bottom">Infrastructure preparation</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Decide on hosting (managed calServer cloud or dedicated tenant).</li>
              <li>Review authentication setup (Azure AD, Google Workspace, local accounts).</li>
              <li>Align backup, retention, and encryption controls with IT security policies.</li>
            </ul>
          </li>
          <li>
            <h4 class="uk-margin-small-bottom">Migration plan</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Schedule the freeze window and stakeholder communications.</li>
              <li>Configure sync rules for hybrid operations if MET/CAL stays active.</li>
              <li>Prepare validation scripts for certificates, asset statuses, and guardband calculations.</li>
            </ul>
          </li>
          <li>
            <h4 class="uk-margin-small-bottom">Dry run</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Import a sandbox dataset and validate end-to-end workflows.</li>
              <li>Review generated certificates for formatting, language, and compliance notes.</li>
              <li>Capture remediation actions for data mismatches or missing attachments.</li>
            </ul>
          </li>
          <li>
            <h4 class="uk-margin-small-bottom">Go-live</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Run the final data load and enable integrations (API, email ingestion, document storage).</li>
              <li>Complete smoke tests with pilot users covering scheduling, tickets, and reporting.</li>
              <li>Document rollback criteria and confirm backups before broader access.</li>
            </ul>
          </li>
          <li>
            <h4 class="uk-margin-small-bottom">Post go-live follow-up</h4>
            <ul class="uk-list uk-list-bullet uk-margin-remove-top">
              <li>Monitor system health (sync queues, job runtimes, storage consumption).</li>
              <li>Gather feedback from quality and operations teams and prioritise quick wins.</li>
              <li>Update SOPs and training materials to reflect calServer workflows.</li>
            </ul>
          </li>
        </ol>
      </div>
      <p class="calserver-metcal__note">Hybrid operations? No problem. We align MET/CAL, METTEAM and calServer so data quality, audit trails and role models match.</p>$$
)
WHERE slug = 'calserver';
