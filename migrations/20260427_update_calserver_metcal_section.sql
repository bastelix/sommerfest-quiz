-- Simplify MET/CAL section on calServer landing page
UPDATE pages
SET content = REPLACE(content, $$    <section id="metcal" class="uk-section uk-section-large uk-section-primary uk-light calserver-metcal" aria-labelledby="metcal-heading">
      <div class="calserver-metcal__inner">
        <div class="uk-container">
          <span class="calserver-metcal__eyebrow">FLUKE MET/CAL · MET/TRACK</span>
          <h2 class="calserver-metcal__title" id="metcal-heading">Ein System. Klare Prozesse.</h2>
          <p class="calserver-metcal__lead">Migration und Hybridbetrieb mit FLUKE MET/CAL werden planbar: Wir orchestrieren den Wechsel von MET/TRACK in den calServer, binden METTEAM sinnvoll ein und sichern auditfähige Nachweise ohne Unterbrechung.</p>
          <div class="calserver-metcal__grid" role="list">
            <article class="calserver-metcal__card" role="listitem">
              <div class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: refresh"></div>
              <h3>Migration ohne Stillstand</h3>
              <p><strong>Assessment bis Nachprüfung</strong> – klare Timeline, Dry-Run und Cut-over-Regeln.</p>
              <ul>
                <li>Datenmapping für Kunden, Geräte, Historien und Dokumente</li>
                <li>Delta-Sync &amp; Freeze-Fenster für den Go-Live</li>
                <li>Abnahmebericht mit KPIs und Korrekturschleifen</li>
              </ul>
            </article>
            <article class="calserver-metcal__card calserver-metcal__card--dark" role="listitem">
              <div class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: link"></div>
              <h3>Hybrid &amp; METTEAM eingebunden</h3>
              <p><strong>Synchronisation in beide Richtungen</strong> – MET/CAL läuft weiter, calServer erledigt Verwaltung und Berichte.</p>
              <ul>
                <li>Klare Feldregeln mit Freigabe der letzten Änderung</li>
                <li>Änderungsprotokoll, Abweichungslisten und erneuter Abgleich bei Konflikten</li>
                <li>Pro Gerät aktivierbar – Daten und Historie sind sofort vorhanden</li>
              </ul>
            </article>
            <article class="calserver-metcal__card calserver-metcal__card--outline" role="listitem">
              <div class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: file-text"></div>
              <h3>Auditfähige Zertifikate</h3>
              <p><strong>DAkkS-taugliche Berichte</strong> – Guardband, Messunsicherheit und Konformitätsangaben sind vorbereitet.</p>
              <ul>
                <li>Vorlagen in Deutsch und Englisch, inklusive QR-/Barcode und Versionierung</li>
                <li>Standardtexte steuern Rückführbarkeit und Konformität</li>
                <li>REST-API, SSO (Azure/Google) und Hosting in Deutschland</li>
              </ul>
            </article>
          </div>
          <div class="calserver-metcal__cta" role="group" aria-label="Weiterführende Aktionen">
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
          <p class="calserver-metcal__note">immer weiss: Hybridbetrieb? Kein Problem. Wir kombinieren MET/CAL, METTEAM und calServer so, dass Datenqualität, Audit-Trails und Rollenmodelle zusammenpassen.</p>
        </div>
      </div>
    </section>$$, $$    <section id="metcal" class="uk-section uk-section-large uk-section-primary uk-light calserver-metcal" aria-labelledby="metcal-heading">
      <div class="calserver-metcal__inner">
        <div class="uk-container">
          <div class="uk-grid-large uk-flex-middle uk-child-width-1-2@m" data-uk-grid>
            <div>
              <span class="calserver-metcal__eyebrow">FLUKE MET/CAL</span>
              <h2 class="calserver-metcal__title" id="metcal-heading">FLUKE MET/CAL einbinden – ohne Stillstand</h2>
              <p class="calserver-metcal__lead">Wir holen Ihre MET/CAL-Abläufe in eine klare Ordnung. MET/CAL läuft weiter, calServer zeigt den Stand, steuert den Ablauf und liefert belastbare Nachweise.</p>
              <p>Ergebnis: <strong>weniger Doppelarbeit, mehr Überblick, ruhige Audits.</strong></p>
            </div>
            <div class="uk-flex uk-flex-center uk-flex-middle uk-margin-top uk-margin-remove-top@m">
              <div class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: link; ratio: 1.6"></div>
            </div>
          </div>

          <div class="uk-margin-large-top">
            <h3 class="uk-heading-bullet">Was Sie davon haben</h3>
            <div class="calserver-metcal__grid" role="list">
              <article class="calserver-metcal__card" role="listitem">
                <h3>
                  <span class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: eye"></span>
                  Ein Blick, alles klar.
                </h3>
                <p>Aufträge, Freigaben und Historien liegen an einem Ort – jede:r sieht sofort, was läuft.</p>
              </article>
              <article class="calserver-metcal__card calserver-metcal__card--dark" role="listitem">
                <h3>
                  <span class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: copy"></span>
                  Keine Doppelpflege.
                </h3>
                <p>Systeme sprechen miteinander – Informationen werden nur einmal erfasst.</p>
              </article>
              <article class="calserver-metcal__card calserver-metcal__card--outline" role="listitem">
                <h3>
                  <span class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: check"></span>
                  Nachweise, die bestehen.
                </h3>
                <p>Berichte kommen beim ersten Mal durch – auditfest und jederzeit abrufbar.</p>
              </article>
            </div>
          </div>

          <div class="uk-margin-large-top">
            <h3 class="uk-heading-bullet">So läuft der Umstieg</h3>
            <ol class="calserver-metcal__steps uk-margin-remove-top" aria-label="So läuft der Umstieg">
              <li><strong>Kurz prüfen.</strong> Wir verstehen Ihr Setup und priorisieren.</li>
              <li><strong>Klein testen.</strong> Ein realer Probelauf zeigt, was noch fehlt.</li>
              <li><strong>Sanft live gehen.</strong> Kurzer Übergabemoment – weiterarbeiten wie gewohnt.</li>
            </ol>
            <p class="calserver-metcal__note"><em>Alles dokumentiert. Keine Überraschungen.</em></p>
          </div>

          <div class="uk-grid-large uk-child-width-1-2@m uk-margin-large-top" data-uk-grid>
            <div>
              <h3 class="uk-heading-bullet">Wenn Systeme bleiben sollen (Hybrid)</h3>
              <ul class="uk-list uk-list-bullet uk-margin-small-top">
                <li><strong>MET/CAL bleibt produktiv.</strong> Messwerte dort, Übersicht und Nachweise in calServer.</li>
                <li><strong>MET/TEAM kann angebunden bleiben.</strong> Aufträge rein, Ergebnisse raus – ohne Kopieren.</li>
                <li><strong>Änderungen nachvollziehbar.</strong> Wer, was, wann – lückenlos festgehalten.</li>
              </ul>
            </div>
            <div>
              <h3 class="uk-heading-bullet">Berichte &amp; Nachweise (ohne Drama)</h3>
              <ul class="uk-list uk-list-bullet uk-margin-small-top">
                <li><strong>Klarer Prüfmaßstab.</strong> Was gilt, ist sichtbar und einheitlich.</li>
                <li><strong>Vorlagen mit Versionsstand.</strong> Auf Wunsch zweisprachig, mit QR/Barcode.</li>
                <li><strong>Freigabe mit Probelauf.</strong> Erst prüfen, dann freigeben – fertig.</li>
              </ul>
            </div>
          </div>

          <div class="uk-margin-large-top">
            <h3 class="uk-heading-bullet">Kurz beantwortet</h3>
            <ul class="uk-accordion calserver-metcal__faq" data-uk-accordion>
              <li>
                <a class="uk-accordion-title" href="#">Müssen wir MET/CAL aufgeben?</a>
                <div class="uk-accordion-content">
                  <p>Nein. MET/CAL bleibt im Einsatz.</p>
                </div>
              </li>
              <li>
                <a class="uk-accordion-title" href="#">Gibt es Ausfallzeiten?</a>
                <div class="uk-accordion-content">
                  <p>Nur einen kurzen Moment für die Übergabe.</p>
                </div>
              </li>
              <li>
                <a class="uk-accordion-title" href="#">Wie sicher ist das?</a>
                <div class="uk-accordion-content">
                  <p>Betrieb in Deutschland oder On-Prem; Anmeldung mit Firmenkonto möglich.</p>
                </div>
              </li>
              <li>
                <a class="uk-accordion-title" href="#">Und unsere alten Daten?</a>
                <div class="uk-accordion-content">
                  <p>Sie werden geordnet übernommen und bleiben nutzbar.</p>
                </div>
              </li>
            </ul>
          </div>

          <div class="uk-margin-large-top">
            <h3 class="uk-heading-bullet">Nächster Schritt</h3>
            <p class="uk-margin-small-top">In 15 Minuten klären wir, wo Sie stehen – und was sich sofort lohnt.</p>
            <div class="calserver-metcal__cta" role="group" aria-label="Nächste Schritte">
              <a class="uk-button uk-button-primary" href="{{ basePath }}/kontakt" data-analytics-event="nav_internal" data-analytics-context="metcal_cta" data-analytics-from="#calserver" data-analytics-to="#kontakt">
                <span class="uk-margin-small-right" data-uk-icon="icon: receiver"></span>Gespräch starten
              </a>
              <a class="uk-button uk-button-default" href="{{ basePath }}/fluke-metcal" data-analytics-event="nav_internal" data-analytics-context="metcal_teaser" data-analytics-from="#calserver" data-analytics-to="#metcal">
                <span class="uk-margin-small-right" data-uk-icon="icon: arrow-right"></span>Mehr zu MET/CAL</a>
            </div>
          </div>
        </div>
      </div>
    </section>$$)
WHERE slug = 'calserver';
