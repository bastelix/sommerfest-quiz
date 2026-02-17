-- Remove calHelp modules section from stored page content
UPDATE pages
SET content = $CALHELP$
<section id="solutions" class="uk-section calhelp-section" aria-labelledby="solutions-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="solutions-title" class="uk-heading-medium">Erkennen Sie sich wieder?</h2>
      <p class="uk-text-lead">Drei Alltagssituationen zeigen, warum Teams zu calHelp wechseln – und was danach besser läuft.</p>
    </div>
    <div class="uk-grid-large uk-child-width-1-3@m uk-grid-match" data-uk-grid>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="solutions-double-title">
        <h3 id="solutions-double-title" class="uk-card-title">Doppelte Erfassung</h3>
        <p class="uk-text-small uk-text-muted">„Excel, E-Mails, Ordner?“</p>
        <p>Ein Arbeitsstand statt fünf Ablagen.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="solutions-audit-title">
        <h3 id="solutions-audit-title" class="uk-card-title">Audit-Druck</h3>
        <p class="uk-text-small uk-text-muted">„Nachweise jagen?“</p>
        <p>Checkliste, Diffs &amp; Historie an einem Ort.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="solutions-approval-title">
        <h3 id="solutions-approval-title" class="uk-card-title">Unklare Freigaben</h3>
        <p class="uk-text-small uk-text-muted">„Wer gibt was frei?“</p>
        <p>Geführte Workflows, klare Verantwortung.</p>
      </article>
    </div>
    <div class="uk-margin-large-top">
      <h3 class="uk-heading-bullet">So helfen wir</h3>
      <div class="uk-grid-large uk-child-width-1-3@m uk-grid-match" data-uk-grid>
        <article class="uk-card uk-card-default uk-card-body calhelp-card" aria-labelledby="solutions-organise-title">
          <h4 id="solutions-organise-title" class="uk-card-title">Ordnen</h4>
          <p>Wir schaffen Überblick – Daten &amp; Rollen an einem Ort.</p>
        </article>
        <article class="uk-card uk-card-default uk-card-body calhelp-card" aria-labelledby="solutions-simplify-title">
          <h4 id="solutions-simplify-title" class="uk-card-title">Vereinfachen</h4>
          <p>Weg mit Doppelarbeiten, hin zu klaren Wegen.</p>
        </article>
        <article class="uk-card uk-card-default uk-card-body calhelp-card" aria-labelledby="solutions-prove-title">
          <h4 id="solutions-prove-title" class="uk-card-title">Belegen</h4>
          <p>Nachweise, die beim ersten Mal bestehen.</p>
        </article>
      </div>
    </div>
  </div>
</section>

<section id="approach" class="uk-section calhelp-section" aria-labelledby="approach-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="approach-title" class="uk-heading-medium">Vorgehen – verständlich und belastbar</h2>
      <p class="uk-text-lead">Verstehen → Ordnen → Umsetzen. Schlanke Schritte, sichtbare Ergebnisse.</p>
    </div>
    <div class="uk-grid-large uk-child-width-1-3@m uk-grid-match" data-uk-grid>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="approach-understand-title">
        <h3 id="approach-understand-title" class="uk-card-title">Verstehen</h3>
        <p>In 30–45 Minuten klären wir Zielbild, Beteiligte und Stolpersteine.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="approach-structure-title">
        <h3 id="approach-structure-title" class="uk-card-title">Ordnen</h3>
        <p>Wir sortieren Daten, Rollen und Verantwortungen – zuerst dort, wo es sofort entlastet.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="approach-deliver-title">
        <h3 id="approach-deliver-title" class="uk-card-title">Umsetzen</h3>
        <p>Wir liefern den ersten belastbaren Nachweis und zeigen, wie der Alltag damit leichter läuft.</p>
      </article>
    </div>
    <p class="uk-text-small uk-margin-medium-top">Technische Checklisten und KPIs finden Sie im <a href="#knowledge">Trust-Center</a>.</p>
  </div>
</section>

<section id="results" class="uk-section uk-section-muted calhelp-section" aria-labelledby="results-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="results-title"
          class="uk-heading-medium"
          data-calhelp-i18n
          data-i18n-de="Vorher vs. Nachher – Wirkung auf einen Blick"
          data-i18n-en="Before vs. after – impact at a glance">Vorher vs. Nachher – Wirkung auf einen Blick</h2>
      <p data-calhelp-i18n
         data-i18n-de="Suchzeit −35 %, Freigabe-Runden halbiert, Audit-Ordner in 1 Tag."
         data-i18n-en="Search time −35%, approval loops halved, audit pack ready in 1 day.">Suchzeit −35 %, Freigabe-Runden halbiert, Audit-Ordner in 1 Tag.</p>
    </div>
    <div class="calhelp-comparison" data-calhelp-comparison>
      <article class="uk-card uk-card-primary uk-card-body calhelp-comparison__card"
               aria-labelledby="results-card-data-title"
               data-calhelp-comparison-card="data"
               data-calhelp-comparison-default="after">
        <header class="calhelp-comparison__header">
          <p id="results-card-data-title"
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
                    aria-controls="results-card-data-before"
                    data-calhelp-i18n
                    data-i18n-de="Vorher"
                    data-i18n-en="Before">Vorher</button>
            <button type="button"
                    class="calhelp-comparison__toggle"
                    data-comparison-toggle="after"
                    aria-pressed="true"
                    aria-controls="results-card-data-after"
                    data-calhelp-i18n
                    data-i18n-de="Nachher"
                    data-i18n-en="After">Nachher</button>
          </div>
        </header>
        <div class="calhelp-comparison__body" aria-live="polite">
          <div id="results-card-data-before"
               class="calhelp-comparison__state"
               data-comparison-state="before"
               aria-hidden="true"
               hidden>
            <p data-calhelp-i18n
               data-i18n-de="Stammdaten in Excel, lokale Ordner, Abstimmung per E-Mail – jeder korrigiert für sich."
               data-i18n-en="Master data in Excel, local folders, coordination via email – everyone fixes things alone.">Stammdaten in Excel, lokale Ordner, Abstimmung per E-Mail – jeder korrigiert für sich.</p>
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
          <div id="results-card-data-after"
               class="calhelp-comparison__state is-active"
               data-comparison-state="after">
            <p data-calhelp-i18n
               data-i18n-de="Ein gemeinsamer Datenstand mit Plausibilitätsregeln – Änderungen sind nachvollziehbar."
               data-i18n-en="One shared data baseline with simple rules – every change stays traceable.">Ein gemeinsamer Datenstand mit Plausibilitätsregeln – Änderungen sind nachvollziehbar.</p>
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
               aria-labelledby="results-card-approval-title"
               data-calhelp-comparison-card="approval"
               data-calhelp-comparison-default="after">
        <header class="calhelp-comparison__header">
          <p id="results-card-approval-title"
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
                    aria-controls="results-card-approval-before"
                    data-calhelp-i18n
                    data-i18n-de="Vorher"
                    data-i18n-en="Before">Vorher</button>
            <button type="button"
                    class="calhelp-comparison__toggle"
                    data-comparison-toggle="after"
                    aria-pressed="true"
                    aria-controls="results-card-approval-after"
                    data-calhelp-i18n
                    data-i18n-de="Nachher"
                    data-i18n-en="After">Nachher</button>
          </div>
        </header>
        <div class="calhelp-comparison__body" aria-live="polite">
          <div id="results-card-approval-before"
               class="calhelp-comparison__state"
               data-comparison-state="before"
               aria-hidden="true"
               hidden>
            <p data-calhelp-i18n
               data-i18n-de="Freigaben wandern per E-Mail, Versionen bleiben unklar, Rückfragen ziehen sich."
               data-i18n-en="Approvals travel via email, versions stay unclear and follow-ups drag on.">Freigaben wandern per E-Mail, Versionen bleiben unklar, Rückfragen ziehen sich.</p>
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
          <div id="results-card-approval-after"
               class="calhelp-comparison__state is-active"
               data-comparison-state="after">
            <p data-calhelp-i18n
               data-i18n-de="Geführte Freigaben mit Rollen, Kommentaren und Signaturen im Audit-Trail."
               data-i18n-en="Guided approvals with roles, comments and signatures captured in the audit trail.">Geführte Freigaben mit Rollen, Kommentaren und Signaturen im Audit-Trail.</p>
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
               aria-labelledby="results-card-audit-title"
               data-calhelp-comparison-card="audit"
               data-calhelp-comparison-default="after">
        <header class="calhelp-comparison__header">
          <p id="results-card-audit-title"
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
                    aria-controls="results-card-audit-before"
                    data-calhelp-i18n
                    data-i18n-de="Vorher"
                    data-i18n-en="Before">Vorher</button>
            <button type="button"
                    class="calhelp-comparison__toggle"
                    data-comparison-toggle="after"
                    aria-pressed="true"
                    aria-controls="results-card-audit-after"
                    data-calhelp-i18n
                    data-i18n-de="Nachher"
                    data-i18n-en="After">Nachher</button>
          </div>
        </header>
        <div class="calhelp-comparison__body" aria-live="polite">
          <div id="results-card-audit-before"
               class="calhelp-comparison__state"
               data-comparison-state="before"
               aria-hidden="true"
               hidden>
            <p data-calhelp-i18n
               data-i18n-de="Checklisten, PDFs und Kommentare liegen verstreut – der Audit-Ordner entsteht in letzter Minute."
               data-i18n-en="Checklists, PDFs and comments are scattered – the audit pack is built at the last minute.">Checklisten, PDFs und Kommentare liegen verstreut – der Audit-Ordner entsteht in letzter Minute.</p>
            <dl class="calhelp-comparison__metrics">
              <div class="calhelp-comparison__metric">
                <dt data-calhelp-i18n
                    data-i18n-de="Vorbereitung"
                    data-i18n-en="Preparation">Vorbereitung</dt>
                <dd data-calhelp-i18n
                    data-i18n-de="Mehrere Tage"
                    data-i18n-en="Several days">Mehrere Tage</dd>
              </div>
              <div class="calhelp-comparison__metric">
                <dt data-calhelp-i18n
                    data-i18n-de="Nachfragen"
                    data-i18n-en="Follow-up questions">Nachfragen</dt>
                <dd data-calhelp-i18n
                    data-i18n-de="Häufig"
                    data-i18n-en="Frequent">Häufig</dd>
              </div>
            </dl>
          </div>
          <div id="results-card-audit-after"
               class="calhelp-comparison__state is-active"
               data-comparison-state="after">
            <p data-calhelp-i18n
               data-i18n-de="Audit-Ordner entsteht aus dem System – Nachweise, Kommentare und Historie sind in Sekunden parat."
               data-i18n-en="The audit pack comes straight from the system – evidence, comments and history are ready in seconds.">Audit-Ordner entsteht aus dem System – Nachweise, Kommentare und Historie sind in Sekunden parat.</p>
            <dl class="calhelp-comparison__metrics">
              <div class="calhelp-comparison__metric">
                <dt data-calhelp-i18n
                    data-i18n-de="Vorbereitung"
                    data-i18n-en="Preparation">Vorbereitung</dt>
                <dd data-calhelp-i18n
                    data-i18n-de="1 Tag"
                    data-i18n-en="1 day">1 Tag</dd>
              </div>
              <div class="calhelp-comparison__metric">
                <dt data-calhelp-i18n
                    data-i18n-de="Nachfragen"
                    data-i18n-en="Follow-up questions">Nachfragen</dt>
                <dd data-calhelp-i18n
                    data-i18n-de="Selten"
                    data-i18n-en="Rare">Selten</dd>
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

<section id="knowledge" class="uk-section calhelp-section" aria-labelledby="knowledge-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="knowledge-title" class="uk-heading-medium">Wissen &amp; Vertrauen</h2>
      <p class="uk-text-lead">Kurz erklärt hier, ausführlich im Trust-Center.</p>
    </div>
    <div class="uk-grid-large uk-child-width-1-3@m uk-grid-match" data-uk-grid>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="knowledge-safe-title">
        <h3 id="knowledge-safe-title" class="uk-card-title">Sicher gehostet.</h3>
        <p>Daten bleiben in deutschen Rechenzentren, Zugriffe sind rollenbasiert und revisionssicher.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="knowledge-trace-title">
        <h3 id="knowledge-trace-title" class="uk-card-title">Alles nachvollziehbar.</h3>
        <p>Freigaben, Kommentare und Versionen landen automatisch im Audit-Trail.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="knowledge-audit-title">
        <h3 id="knowledge-audit-title" class="uk-card-title">Auditbereit.</h3>
        <p>Nachweise &amp; Vorlagen stehen auf Abruf bereit – ohne zusätzliche Nachtschichten.</p>
      </article>
    </div>
    <div class="uk-margin-large-top">
      <div data-calhelp-assurance></div>
    </div>
    <div class="uk-margin-large-top">
      <div data-calhelp-proof-gallery></div>
    </div>
    <div class="calhelp-kpi uk-card uk-card-primary uk-card-body">
      <p class="uk-margin-remove">Alle technischen Details, KPIs und Checklisten finden Sie im <a href="https://calhelp.notion.site/Trust-Center" target="_blank" rel="noopener">Trust-Center</a>. Für Fragen stehen wir jederzeit bereit.</p>
    </div>
  </div>
</section>

<div data-calhelp-cases></div>

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
    <ul class="uk-accordion calhelp-faq" aria-label="Häufig gestellte Fragen" uk-accordion="multiple: true">
      <li class="calhelp-faq__item">
        <a class="uk-accordion-title" href="#">Bleibt MET/TEAM nutzbar?</a>
        <div class="uk-accordion-content">
          <p>Ja. Bestehende Lösungen können angebunden bleiben (Fernsteuerung/Befüllen). Eine Ablösung ist optional und kann schrittweise erfolgen.</p>
        </div>
      </li>
      <li class="calhelp-faq__item">
        <a class="uk-accordion-title" href="#">Was wird übernommen?</a>
        <div class="uk-accordion-content">
          <p>Geräte, Historien, Zertifikate/PDFs, Kund:innen/Standorte, benutzerdefinierte Felder – soweit technisch verfügbar. Alles mit Mapping-Report und Abweichungsprotokoll.</p>
        </div>
      </li>
      <li class="calhelp-faq__item">
        <a class="uk-accordion-title" href="#">Wie sicher ist der Betrieb?</a>
        <div class="uk-accordion-content">
          <p>Hosting in Deutschland oder On-Prem, Rollen/Rechte, Protokollierung. DSGVO-konform – inkl. transparentem Datenschutzhinweis.</p>
        </div>
      </li>
      <li class="calhelp-faq__item">
        <a class="uk-accordion-title" href="#">Wie lange dauert der Umstieg?</a>
        <div class="uk-accordion-content">
          <p>Abhängig von Datenumfang und Komplexität. Der Pilot liefert einen belastbaren Zeitplan für den Produktivlauf.</p>
        </div>
      </li>
    </ul>
    <div class="calhelp-faq__footer">
      <span class="calhelp-faq__footer-hint">Noch nicht fündig geworden?</span>
      <a class="calhelp-faq__footer-link" href="#conversation">Weitere Fragen → Gespräch</a>
    </div>
  </div>
</section>

<section id="cta" class="uk-section calhelp-section calhelp-cta" aria-labelledby="cta-title">
  <div class="uk-container">
    <div class="calhelp-section__header">
      <h2 id="cta-title" class="uk-heading-medium">Der nächste Schritt ist klein – die Wirkung groß.</h2>
      <p class="uk-text-lead">Schicken Sie uns ein kurzes Briefing. Wir melden uns persönlich und schlagen den passenden Einstieg vor.</p>
    </div>
    <div class="uk-grid uk-child-width-1-2@m uk-grid-large uk-flex-top" uk-grid>
      <div>
        <form id="contact-form"
              class="uk-form-stacked uk-width-large uk-margin-auto"
              data-contact-endpoint="{{ basePath }}/calhelp/contact">
          <div class="uk-margin">
            <label class="uk-form-label" for="form-name">Ihr Name</label>
            <input class="uk-input" id="form-name" name="name" type="text" required>
          </div>
          <div class="uk-margin">
            <label class="uk-form-label" for="form-email">E-Mail</label>
            <input class="uk-input" id="form-email" name="email" type="email" required>
          </div>
          <div class="uk-margin">
            <label class="uk-form-label" for="form-msg">Worum geht es?</label>
            <textarea class="uk-textarea" id="form-msg" name="message" rows="5" required></textarea>
          </div>
          <div class="uk-margin turnstile-field" data-turnstile-container>
            <div class="turnstile-widget">{{ turnstile_widget }}</div>
            <p class="uk-text-small turnstile-hint" data-turnstile-hint hidden>Bitte bestätigen Sie, dass Sie kein Roboter sind.</p>
          </div>
          <div class="uk-margin">
            <label><input class="uk-checkbox" name="privacy" type="checkbox" required> Ich stimme der Speicherung meiner Daten zur Bearbeitung zu.</label>
          </div>
          <input type="text" name="company" autocomplete="off" tabindex="-1" class="uk-hidden" aria-hidden="true">
          <input type="hidden" name="csrf_token" value="{{ csrf_token }}">
          <button class="btn btn-black uk-button uk-button-secondary uk-button-large uk-width-1-1" type="submit">Senden</button>
        </form>
      </div>
      <div>
        <div class="uk-card uk-card-default uk-card-body uk-text-left calhelp-cta__panel">
          <p class="uk-text-large uk-margin-remove">Direkt einsteigen?</p>
          <p class="uk-margin-small-top">Sie bevorzugen ein Gespräch oder möchten ein konkretes Szenario prüfen? Nutzen Sie die Schnellzugriffe.</p>
          <div class="calhelp-cta__actions uk-margin-medium-top" role="group" aria-label="Abschluss-CTAs">
            <a class="uk-button uk-button-primary uk-width-1-1" href="#conversation">Gespräch starten</a>
            <a class="uk-button uk-button-default uk-width-1-1" href="#approach">Lage klären</a>
          </div>
        </div>
        <div class="calhelp-note uk-card uk-card-primary uk-card-body uk-margin-top">
          <p class="uk-margin-remove">Wir speichern nur, was für Rückmeldung und Terminfindung nötig ist. Details: <a href="{{ basePath }}/datenschutz">Datenschutz</a>.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<div id="contact-modal" uk-modal>
  <div class="uk-modal-dialog uk-modal-body">
    <p id="contact-modal-message" aria-live="polite"></p>
    <button class="uk-button uk-button-primary uk-modal-close" type="button">OK</button>
  </div>
</div>

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
