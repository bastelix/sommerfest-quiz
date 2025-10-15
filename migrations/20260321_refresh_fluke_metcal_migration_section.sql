INSERT INTO pages (slug, title, content)
VALUES (
    'fluke-metcal',
    'FLUKE MET/CAL Integration',
    $$<!--
kb_id: metcal-integration-v1
kb_version: 1.1
kb_tags: ["MET/CAL","MET/TRACK","METTEAM","Migration","Zertifikat","Guardband","DAkkS"]
kb_synonyms: ["METCAL","MET TRACK","MET/TRACK","MET TEAM","METTEAM","Guardband","Reports"]
updated_at: 2025-03-21
summary: Diese Seite zeigt Migration von MET/TRACK nach calServer, Hybridbetrieb mit METTEAM sowie Reporting & Guardband – inklusive neuem Migrationsangebot mit klaren Deliverables.
-->
<div data-metcal-sticky-sentinel></div>

<section id="szenarien" class="uk-section metcal-section metcal-section--light" aria-labelledby="metcal-szenarien-heading">
  <div class="uk-container">
    <span class="metcal-eyebrow">Szenarien</span>
    <h2 class="metcal-section__title" id="metcal-szenarien-heading">Drei Wege, ein Ziel: MET/CAL und calServer harmonisieren.</h2>
    <p class="metcal-section__lead">Ob kompletter Wechsel, Hybridbetrieb oder Schritt-für-Schritt-Ausbau – wir strukturieren Prozesse, Datenmodelle und Zuständigkeiten so, dass dein Team ohne Doppelpflege starten kann.</p>
    <div class="metcal-card-grid" role="list">
      <article class="metcal-card" role="listitem" aria-labelledby="metcal-scenario-full">
        <h3 class="metcal-card__title" id="metcal-scenario-full">Vollumstieg</h3>
        <p class="metcal-card__text">Für Teams, die konsequent konsolidieren: strukturierte Migration, Rollenmodelle, Berichte und Zertifikate – alles im calServer.</p>
        <ul class="metcal-card__list">
          <li>Clean-up der Stammdaten &amp; Pflichtfelder</li>
          <li>Import geprüfter Historien &amp; Dokumente</li>
          <li>Auditbericht zum Abschluss</li>
        </ul>
      </article>
      <article class="metcal-card metcal-card--accent" role="listitem" aria-labelledby="metcal-scenario-hybrid">
        <h3 class="metcal-card__title" id="metcal-scenario-hybrid">Hybrid</h3>
        <p class="metcal-card__text">MET/CAL bleibt produktiv, calServer übernimmt Verwaltung, Reporting und Zertifikate – ohne doppelte Pflege.</p>
        <ul class="metcal-card__list">
          <li>Sync-Regeln für führende Felder</li>
          <li>Delta-Listen &amp; Konfliktjournal</li>
          <li>Automatischer Zertifikatsversand</li>
        </ul>
      </article>
      <article class="metcal-card" role="listitem" aria-labelledby="metcal-scenario-scale">
        <h3 class="metcal-card__title" id="metcal-scenario-scale">Start klein → skalieren</h3>
        <p class="metcal-card__text">Inventar &amp; Fälligkeiten als Einstieg, später Messdaten, Zertifikate und SSO ergänzen – API-gestützt, ohne Sackgasse.</p>
        <ul class="metcal-card__list">
          <li>Module modular aktivieren</li>
          <li>Playbooks für Roll-out &amp; Schulung</li>
          <li>Messdaten-Pipeline als Option</li>
        </ul>
      </article>
    </div>
  </div>
</section>

<section id="migration" class="uk-section metcal-section" aria-labelledby="metcal-migration-heading">
  <div class="uk-container">
    <span class="metcal-eyebrow">Migration</span>
    <h2 class="metcal-section__title" id="metcal-migration-heading">So läuft der Umstieg von MET/TRACK nach calServer.</h2>
    <p class="metcal-section__lead">Wir planen, testen und dokumentieren den Wechsel – inklusive Freeze-Fenster, Delta-Sync und Abnahmebericht.</p>
    <div class="metcal-migration-offer" role="note" aria-labelledby="metcal-migration-offer-title">
      <h3 class="metcal-migration-offer__title" id="metcal-migration-offer-title">Migration ohne Stillstand</h3>
      <ul class="metcal-migration-offer__list" role="list">
        <li class="metcal-migration-offer__item" role="listitem">Assessment bis Nachprüfung – klare Timeline, Dry-Run und Cut-over-Regeln.</li>
        <li class="metcal-migration-offer__item" role="listitem">Datenmapping für Kunden, Geräte, Historien und Dokumente.</li>
        <li class="metcal-migration-offer__item" role="listitem">Delta-Sync &amp; Freeze-Fenster für den Go-Live.</li>
        <li class="metcal-migration-offer__item" role="listitem">Abnahmebericht mit KPIs und Korrekturschleifen.</li>
      </ul>
    </div>
    <ol class="metcal-timeline" role="list">
      <li class="metcal-timeline__item" aria-label="Schritt 1 Assessment">
        <span class="metcal-timeline__badge">1</span>
        <div class="metcal-timeline__body">
          <h3>Assessment</h3>
          <p>Scope, Datenqualität, Pflichtfelder und Mapping-Regeln werden gemeinsam definiert – inklusive Verantwortlichkeiten.</p>
        </div>
      </li>
      <li class="metcal-timeline__item" aria-label="Schritt 2 Mapping">
        <span class="metcal-timeline__badge">2</span>
        <div class="metcal-timeline__body">
          <h3>Mapping</h3>
          <p>Felder, Einheiten, Präfixe sowie Historien und Dokumentreferenzen werden in calServer-Strukturen überführt.</p>
        </div>
      </li>
      <li class="metcal-timeline__item" aria-label="Schritt 3 Dry-Run">
        <span class="metcal-timeline__badge">3</span>
        <div class="metcal-timeline__body">
          <h3>Dry-Run</h3>
          <p>Testimport mit Stichproben, Validierungen und Delta-Regeln – transparent dokumentiert, damit Risiken früh sichtbar werden.</p>
        </div>
      </li>
      <li class="metcal-timeline__item" aria-label="Schritt 4 Cut-over">
        <span class="metcal-timeline__badge">4</span>
        <div class="metcal-timeline__body">
          <h3>Cut-over &amp; Hybrid</h3>
          <p>Freeze-Fenster, Delta-Sync und Go-Live – begleitet von Checklisten, Rollback-Optionen und Kommunikationsplan.</p>
        </div>
      </li>
      <li class="metcal-timeline__item" aria-label="Schritt 5 Nachprüfung">
        <span class="metcal-timeline__badge">5</span>
        <div class="metcal-timeline__body">
          <h3>Nachprüfung</h3>
          <p>Abnahmebericht mit KPIs, Abweichungen und Korrekturen – inklusive Lessons Learned für zukünftige Importe.</p>
        </div>
      </li>
    </ol>
    <div class="metcal-migration-transfer">
      <h3 class="metcal-subheading">Was wird übertragen?</h3>
      <ul class="metcal-transfer-list">
        <li><strong>Stammdaten:</strong> Kunden, Standorte, Geräteakten, Kategorien.</li>
        <li><strong>Historie:</strong> Kalibrierungen, Reparaturen, Prüfungen inklusive Messwertbezug.</li>
        <li><strong>Fälligkeiten:</strong> Intervalle, Eskalationsregeln und Erinnerungen.</li>
        <li><strong>Dokumente:</strong> Zertifikate, Anleitungen, Prüfprotokolle mit Versionierung.</li>
        <li><strong>Optional:</strong> Rollen, Vorlagen, Nutzer:innengruppen und Seriennummernkreise.</li>
      </ul>
    </div>
  </div>
</section>

<section id="metteam" class="uk-section metcal-section metcal-section--accent" aria-labelledby="metcal-metteam-heading">
  <div class="uk-container">
    <span class="metcal-eyebrow">METTEAM</span>
    <h2 class="metcal-section__title" id="metcal-metteam-heading">Hybridbetrieb mit METTEAM – sauber geregelt.</h2>
    <p class="metcal-section__lead">Bidirektionale Synchronisation, klare Eigentümerschaften und vollständige Protokollierung halten MET/CAL, METTEAM und calServer im Gleichklang.</p>
    <div class="metcal-feature-grid" role="list">
      <article class="metcal-feature" role="listitem">
        <h3>Sync-Regeln pro Feld</h3>
        <p>Feldweise Festlegung, wer führend ist (MET/CAL, METTEAM oder calServer). Konflikte werden als Review-Aufgabe markiert.</p>
      </article>
      <article class="metcal-feature" role="listitem">
        <h3>Last-Write-Wins mit Journal</h3>
        <p>Änderungen werden versioniert, inklusive Delta-Listen, Autor:in und Zeitstempel – nachvollziehbar für Audits.</p>
      </article>
      <article class="metcal-feature" role="listitem">
        <h3>Aktivierung pro Gerät</h3>
        <p>Hybridmodus per Toggle aktivieren: vorhandene Daten werden direkt übernommen, Beenden inklusive Aufräumdialog.</p>
      </article>
    </div>
  </div>
</section>

<section id="zertifikate" class="uk-section metcal-section" aria-labelledby="metcal-cert-heading">
  <div class="uk-container">
    <span class="metcal-eyebrow">Zertifikate</span>
    <h2 class="metcal-section__title" id="metcal-cert-heading">DAkkS-taugliche Nachweise mit Guardband und MU.</h2>
    <p class="metcal-section__lead">Konfigurierbare Standardtexte, Guardband-Logik und Mehrsprachigkeit sorgen für auditfähige Zertifikate.</p>
    <ul class="metcal-feature-list">
      <li><strong>Konformitätsaussagen:</strong> Guardband-Methoden mit klarer Ampel (Pass, Undetermined, Fail).</li>
      <li><strong>Mehrsprachigkeit:</strong> Labels und Fließtexte in DE/EN, inklusive variabler Platzhalter.</li>
      <li><strong>Optionen:</strong> QR-/Barcode, Logo, Versionskennzeichnung, Protokoll-Verknüpfung und digitale Signatur.</li>
      <li><strong>Export:</strong> PDF/A, Massenexport und Versand per Portal oder API.</li>
    </ul>
  </div>
</section>

<section id="berichte" class="uk-section metcal-section metcal-section--light" aria-labelledby="metcal-reports-heading">
  <div class="uk-container">
    <span class="metcal-eyebrow">Berichte &amp; Monitoring</span>
    <h2 class="metcal-section__title" id="metcal-reports-heading">Berichte im Griff – nachvollziehbar und versandfertig.</h2>
    <p class="metcal-section__lead">Templates, Guardband und Automatisierung für reproduzierbare Nachweise, die Auditfragen vorwegnehmen.</p>
    <div class="metcal-report-grid" role="list">
      <article class="metcal-report-card" role="listitem">
        <h3>Vorlagen &amp; Guardband</h3>
        <p>Standardisierte Layouts mit Konformitätslogik, Messwert-Tabellen und zweisprachigen Textbausteinen.</p>
        <ul>
          <li>Guardband- und MU-Varianten je Produktlinie</li>
          <li>Seriennummern, QR/Barcode und Signaturfelder</li>
        </ul>
      </article>
      <article class="metcal-report-card" role="listitem">
        <h3>Review &amp; Freigabe</h3>
        <p>Vier-Augen-Freigabe mit Kommentar-Log, Golden Samples und Diff-Ansicht pro Änderung.</p>
        <ul>
          <li>Abweichungsberichte als PDF/A</li>
          <li>Checklisten für Audit-Fragen</li>
        </ul>
      </article>
      <article class="metcal-report-card" role="listitem">
        <h3>Verteilung &amp; Archiv</h3>
        <p>Versand via Portal, API oder E-Mail, inklusive Portalablage, Downloadhistorie und Ablaufsteuerung.</p>
        <ul>
          <li>Rollenbasierte Sichtbarkeit</li>
          <li>Automatisierte Erinnerungen</li>
        </ul>
      </article>
    </div>
  </div>
</section>

<section id="sicherheit" class="uk-section metcal-section metcal-section--light" aria-labelledby="metcal-security-heading">
  <div class="uk-container">
    <span class="metcal-eyebrow">Sicherheit &amp; Betrieb</span>
    <h2 class="metcal-section__title" id="metcal-security-heading">SSO, DSGVO und Betrieb nach Plan.</h2>
    <div class="metcal-security-grid" role="list">
      <article class="metcal-security" role="listitem">
        <h3>SSO &amp; Rollen</h3>
        <p>Azure Entra ID und Google via SAML 2.0 – Gruppensync, Rollenmapping und automatische Provisionierung.</p>
      </article>
      <article class="metcal-security" role="listitem">
        <h3>DSGVO &amp; Hosting</h3>
        <p>Hetzner Rechenzentrum in Deutschland, tägliche Backups (7-Tage-Ring) und vollständige Audit-Trails.</p>
      </article>
      <article class="metcal-security" role="listitem">
        <h3>REST-API</h3>
        <p>Stabile Endpunkte für ERP, DMS, Ticketsysteme – inklusive Webhooks und API-Keys mit Scope.</p>
      </article>
      <article class="metcal-security" role="listitem">
        <h3>Betrieb &amp; Support</h3>
        <p>Monitoring, Update-Pfade, Rollback-Plan und dedizierte Ansprechpartner:innen.</p>
      </article>
    </div>
  </div>
</section>

<section id="pakete" class="uk-section metcal-section" aria-labelledby="metcal-packages-heading">
  <div class="uk-container">
    <span class="metcal-eyebrow">Pakete</span>
    <h2 class="metcal-section__title" id="metcal-packages-heading">Pakete mit klaren Outcomes.</h2>
    <div class="metcal-packages" role="list">
      <article class="metcal-package" role="listitem">
        <h3>Startklar</h3>
        <p class="metcal-package__subtitle">Quick-Start für Inventar &amp; Fälligkeiten.</p>
        <ul class="metcal-package__list">
          <li>Setup &amp; Basisrollen</li>
          <li>1 Reportvorlage</li>
          <li>Schulung (½ Tag)</li>
        </ul>
        <p class="metcal-package__outcome"><strong>Outcome:</strong> produktiver Start.</p>
      </article>
      <article class="metcal-package metcal-package--highlight" role="listitem">
        <h3>Migration Sprint</h3>
        <p class="metcal-package__subtitle">MET/TRACK → calServer mit Dry-Run &amp; Cut-over.</p>
        <ul class="metcal-package__list">
          <li>Daten-Assessment &amp; Mapping</li>
          <li>Dry-Run &amp; Delta-Regeln</li>
          <li>Go-Live-Begleitung &amp; Abnahme</li>
        </ul>
        <p class="metcal-package__outcome"><strong>Outcome:</strong> geprüfte Datenbasis.</p>
      </article>
      <article class="metcal-package" role="listitem">
        <h3>Integration Sprint</h3>
        <p class="metcal-package__subtitle">METTEAM &amp; Hybridbetrieb stabilisieren.</p>
        <ul class="metcal-package__list">
          <li>Treiber- &amp; Sync-Regeln</li>
          <li>Konfliktlogik &amp; Journal</li>
          <li>Tests &amp; Übergabe</li>
        </ul>
        <p class="metcal-package__outcome"><strong>Outcome:</strong> Hybrid ohne Doppelpflege.</p>
      </article>
      <article class="metcal-package" role="listitem">
        <h3>Care &amp; Compliance</h3>
        <p class="metcal-package__subtitle">Laufende Betreuung &amp; Auditvorbereitung.</p>
        <ul class="metcal-package__list">
          <li>Report-Updates</li>
          <li>Backup-Checks</li>
          <li>Audit-Vorbereitung</li>
        </ul>
        <p class="metcal-package__outcome"><strong>Outcome:</strong> dauerhaft auditfähig.</p>
      </article>
    </div>
  </div>
</section>

<section id="faq" class="uk-section metcal-section metcal-section--light" aria-labelledby="metcal-faq-heading">
  <div class="uk-container">
    <span class="metcal-eyebrow">FAQ</span>
    <h2 class="metcal-section__title" id="metcal-faq-heading">Häufige Fragen.</h2>
    <div class="metcal-faq" data-uk-accordion>
      <div class="metcal-faq__item" data-faq-id="faq_1">
        <a class="metcal-faq__toggle" href="#">Müssen wir MET/CAL ablösen?</a>
        <div class="metcal-faq__content">Nein. MET/CAL kann bleiben; calServer übernimmt Verwaltung, Reports und Nachweise – auch im Hybridbetrieb.</div>
      </div>
      <div class="metcal-faq__item" data-faq-id="faq_2">
        <a class="metcal-faq__toggle" href="#">Wie vermeiden wir doppelte Pflege?</a>
        <div class="metcal-faq__content">Durch klar definierte führende Felder, Delta-Sync und Änderungsjournal mit Review.</div>
      </div>
      <div class="metcal-faq__item" data-faq-id="faq_3">
        <a class="metcal-faq__toggle" href="#">Sind Zertifikate DAkkS-tauglich?</a>
        <div class="metcal-faq__content">Ja. Standardtexte sind normgerecht konfigurierbar; Guardband, MU und Konformitätslegenden sind integriert.</div>
      </div>
      <div class="metcal-faq__item" data-faq-id="faq_4">
        <a class="metcal-faq__toggle" href="#">Wie lange dauert die Migration?</a>
        <div class="metcal-faq__content">Abhängig von Datenqualität und Umfang. Der Dry-Run zeigt realistische Aufwände und Risiken.</div>
      </div>
      <div class="metcal-faq__item" data-faq-id="faq_5">
        <a class="metcal-faq__toggle" href="#">Unterstützt ihr SSO &amp; DSGVO?</a>
        <div class="metcal-faq__content">Ja. Azure/Google SSO via SAML 2.0, Hosting in Deutschland, Backups und Audit-Trails inklusive.</div>
      </div>
      <div class="metcal-faq__item" data-faq-id="faq_6">
        <a class="metcal-faq__toggle" href="#">Können wir klein starten?</a>
        <div class="metcal-faq__content">Ja. Inventar &amp; Fälligkeiten als Einstieg, später Messdaten, Zertifikate und SSO ergänzen.</div>
      </div>
      <div class="metcal-faq__item" data-faq-id="faq_7">
        <a class="metcal-faq__toggle" href="#">Was kostet der Hybridbetrieb?</a>
        <div class="metcal-faq__content">Hängt von Integrationsgrad und Treiberumfang ab; wir kalkulieren transparent im Integration Sprint.</div>
      </div>
      <div class="metcal-faq__item" data-faq-id="faq_8">
        <a class="metcal-faq__toggle" href="#">Wie sichern wir die Datenqualität?</a>
        <div class="metcal-faq__content">Mapping-Regeln, Validierungen, Stichproben und Abnahmebericht mit Korrekturschleife.</div>
      </div>
    </div>
  </div>
</section>

<div class="metcal-sticky-cta" data-metcal-sticky-cta>
  <div class="metcal-sticky-cta__inner">
    <div class="metcal-sticky-cta__copy">
      <strong>Bereit für den Wechsel?</strong>
      <span>Wir planen Migration, Hybridbetrieb und Berichte gemeinsam – ohne Stillstand.</span>
    </div>
    <div class="metcal-sticky-cta__actions" role="group" aria-label="Schnellzugriff">
      <a class="uk-button uk-button-primary"
         href="#berichte"
         data-analytics-event="click_cta_reports"
         data-analytics-context="sticky_metcal"
         data-analytics-page="/fluke-metcal"
         data-analytics-target="#berichte">
        <span class="uk-margin-small-right" data-uk-icon="icon: file-text"></span>Berichte ansehen
      </a>
      <a class="uk-button uk-button-default"
         href="{{ basePath }}/kontakt"
         data-analytics-event="click_cta_contact"
         data-analytics-context="sticky_metcal"
         data-analytics-target="/kontakt">
        <span class="uk-margin-small-right" data-uk-icon="icon: receiver"></span>Kontakt aufnehmen
      </a>
    </div>
  </div>
</div>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {"@type": "Question", "name": "Müssen wir MET/CAL ablösen?", "acceptedAnswer": {"@type": "Answer", "text": "Nein. MET/CAL kann bleiben; calServer übernimmt Verwaltung, Reports und Nachweise – auch im Hybridbetrieb."}},
    {"@type": "Question", "name": "Wie vermeiden wir doppelte Pflege?", "acceptedAnswer": {"@type": "Answer", "text": "Durch klar definierte führende Felder, Delta-Sync und Änderungsjournal mit Review."}},
    {"@type": "Question", "name": "Sind Zertifikate DAkkS-tauglich?", "acceptedAnswer": {"@type": "Answer", "text": "Ja. Standardtexte sind normgerecht konfigurierbar; Guardband, MU und Konformitätslegenden sind integriert."}},
    {"@type": "Question", "name": "Wie lange dauert die Migration?", "acceptedAnswer": {"@type": "Answer", "text": "Abhängig von Datenqualität und Umfang. Der Dry-Run zeigt realistische Aufwände und Risiken."}},
    {"@type": "Question", "name": "Unterstützt ihr SSO & DSGVO?", "acceptedAnswer": {"@type": "Answer", "text": "Ja. Azure/Google SSO via SAML 2.0, Hosting in Deutschland, Backups und Audit-Trails inklusive."}},
    {"@type": "Question", "name": "Können wir klein starten?", "acceptedAnswer": {"@type": "Answer", "text": "Ja. Inventar & Fälligkeiten als Einstieg, später Messdaten, Zertifikate und SSO ergänzen."}},
    {"@type": "Question", "name": "Was kostet der Hybridbetrieb?", "acceptedAnswer": {"@type": "Answer", "text": "Hängt von Integrationsgrad und Treiberumfang ab; wir kalkulieren transparent im Integration Sprint."}},
    {"@type": "Question", "name": "Wie sichern wir die Datenqualität?", "acceptedAnswer": {"@type": "Answer", "text": "Mapping-Regeln, Validierungen, Stichproben und Abnahmebericht mit Korrekturschleife."}}
  ]
}
</script>
$$
)
ON CONFLICT (slug) DO UPDATE
SET title = excluded.title,
    content = excluded.content;
