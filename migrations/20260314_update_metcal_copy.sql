-- Simplify MET/CAL hybrid copy for calServer teaser
UPDATE pages
SET content = REPLACE(content, $$            <article class="calserver-metcal__card calserver-metcal__card--dark" role="listitem">
              <div class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: link"></div>
              <h3>Hybrid &amp; METTEAM eingebunden</h3>
              <p><strong>Bidirektionaler Sync</strong> – MET/CAL bleibt produktiv, calServer übernimmt Verwaltung und Reporting.</p>
              <ul>
                <li>Eigentümerschaft pro Feld &amp; Last-Write-Wins mit Review</li>
                <li>Änderungsjournal, Delta-Listen und Re-Sync bei Konflikten</li>
                <li>Aktivierung pro Gerät – sofortige Datenübernahme inklusive Historie</li>
              </ul>
            </article>
            <article class="calserver-metcal__card calserver-metcal__card--outline" role="listitem">
              <div class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: file-text"></div>
              <h3>Auditfähige Zertifikate</h3>
              <p><strong>DAkkS-ready Reports</strong> – Guardband, MU und Konformitätslegenden inklusive.</p>
              <ul>
                <li>Zweisprachige Vorlagen, QR-/Barcode und Versionierung</li>
                <li>Rückführbarkeit &amp; Konformität per Standardtext konfigurierbar</li>
                <li>REST-API, SSO (Azure/Google) und Hosting in Deutschland</li>
              </ul>
            </article>$$, $$            <article class="calserver-metcal__card calserver-metcal__card--dark" role="listitem">
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
            </article>$$)
WHERE slug = 'fluke-metcal';

UPDATE pages
SET content = REPLACE(content, $$        <article class="calserver-metcal__card calserver-metcal__card--dark" role="listitem">
          <div class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: link"></div>
          <h3>Hybrid &amp; METTEAM connected</h3>
          <p><strong>Bidirectional sync</strong> – MET/CAL stays productive, calServer handles management and reporting.</p>
          <ul>
            <li>Field ownership &amp; last-write-wins with review</li>
            <li>Change journal, delta lists and re-sync on conflicts</li>
            <li>Activation per instrument – immediately imports history</li>
          </ul>
        </article>
        <article class="calserver-metcal__card calserver-metcal__card--outline" role="listitem">
          <div class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: file-text"></div>
          <h3>Audit-ready certificates</h3>
          <p><strong>DAkkS-ready reports</strong> – guardbanding, MU and conformity legends included.</p>
          <ul>
            <li>Bilingual templates, QR/barcode and versioning</li>
            <li>Traceability &amp; conformity controlled via standard texts</li>
            <li>REST API, SSO (Azure/Google) and hosting in Germany</li>
          </ul>
        </article>$$, $$        <article class="calserver-metcal__card calserver-metcal__card--dark" role="listitem">
          <div class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: link"></div>
          <h3>Hybrid &amp; METTEAM connected</h3>
          <p><strong>Two-way sync</strong> – MET/CAL keeps running while calServer handles admin and reporting.</p>
          <ul>
            <li>Clear field rules with approval of the latest change</li>
            <li>Change log, difference lists and another sync when conflicts show up</li>
            <li>Enable per instrument – history and data appear right away</li>
          </ul>
        </article>
        <article class="calserver-metcal__card calserver-metcal__card--outline" role="listitem">
          <div class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: file-text"></div>
          <h3>Audit-ready certificates</h3>
          <p><strong>DAkkS-ready reports</strong> – guardband, measurement uncertainty and conformity notes included.</p>
          <ul>
            <li>Templates in German and English plus QR/barcode and versioning</li>
            <li>Standard texts cover traceability and conformity</li>
            <li>REST API, SSO (Azure/Google) and hosting in Germany</li>
          </ul>
        </article>$$)
WHERE slug = 'fluke-metcal';
