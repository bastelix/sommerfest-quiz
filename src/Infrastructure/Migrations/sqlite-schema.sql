-- SQLite schema for tests
-- This script sets up the database schema in a SQLite-compatible way

CREATE TABLE IF NOT EXISTS migrations (
    version TEXT PRIMARY KEY
);

-- Events
CREATE TABLE IF NOT EXISTS events (
    uid TEXT PRIMARY KEY,
    slug TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    start_date TEXT DEFAULT CURRENT_TIMESTAMP,
    end_date TEXT DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    published BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order INTEGER NOT NULL DEFAULT 0
);

-- Players
CREATE TABLE IF NOT EXISTS players (
    event_uid TEXT NOT NULL,
    player_name TEXT NOT NULL,
    player_uid TEXT NOT NULL,
    PRIMARY KEY (event_uid, player_uid)
);

-- Config
CREATE TABLE IF NOT EXISTS config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    displayErrorDetails BOOLEAN,
    QRUser BOOLEAN,
    logoPath TEXT,
    pageTitle TEXT,
    backgroundColor TEXT,
    buttonColor TEXT,
    CheckAnswerButton TEXT,
    QRRestrict BOOLEAN,
    randomNames BOOLEAN DEFAULT TRUE,
    competitionMode BOOLEAN,
    teamResults BOOLEAN,
    photoUpload BOOLEAN,
    puzzleWordEnabled BOOLEAN,
    puzzleWord TEXT,
    puzzleFeedback TEXT,
    collectPlayerUid BOOLEAN,
    inviteText TEXT,
    qrremember BOOLEAN DEFAULT FALSE,
    event_uid TEXT,
    qrLabelLine1 TEXT,
    qrLabelLine2 TEXT,
    qrLogoPath TEXT,
    qrLogoWidth INTEGER,
    qrRoundMode TEXT,
    qrLogoPunchout BOOLEAN,
    qrRounded BOOLEAN,
    qrColorTeam TEXT,
    qrColorCatalog TEXT,
    qrColorEvent TEXT,
    stickerTemplate TEXT,
    stickerPrintHeader BOOLEAN,
    stickerPrintSubheader BOOLEAN,
    stickerPrintCatalog BOOLEAN,
    stickerPrintDesc BOOLEAN,
    stickerQrColor TEXT,
    stickerQrSizePct NUMERIC(6,2),
    stickerDescTop REAL,
    stickerDescLeft REAL,
    stickerQrTop REAL,
    stickerQrLeft REAL,
    stickerHeaderFontSize INTEGER,
    stickerSubheaderFontSize INTEGER,
    stickerCatalogFontSize INTEGER,
    stickerDescFontSize INTEGER,
    stickerTextColor TEXT,
    stickerDescWidth REAL,
    stickerDescHeight REAL,
    stickerBgPath TEXT,
    FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);

-- Settings
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT
);
INSERT OR IGNORE INTO settings(key, value) VALUES('home_page', 'help');
INSERT OR IGNORE INTO settings(key, value) VALUES('registration_enabled', '0');

-- Domain start pages
CREATE TABLE IF NOT EXISTS domain_start_pages (
    domain TEXT PRIMARY KEY,
    start_page TEXT NOT NULL,
    email TEXT,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Domain contact templates
CREATE TABLE IF NOT EXISTS domain_contact_templates (
    domain TEXT PRIMARY KEY,
    sender_name TEXT,
    recipient_html TEXT,
    recipient_text TEXT,
    sender_html TEXT,
    sender_text TEXT,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Teams
CREATE TABLE IF NOT EXISTS teams (
    sort_order INTEGER NOT NULL,
    name TEXT NOT NULL,
    uid TEXT PRIMARY KEY,
    event_uid TEXT,
    FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE,
    UNIQUE(event_uid, sort_order)
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_team_name ON teams(name);

-- Results
CREATE TABLE IF NOT EXISTS results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    catalog TEXT NOT NULL,
    attempt INTEGER NOT NULL,
    correct INTEGER NOT NULL,
    answer_text TEXT,
    consent BOOLEAN,
    total INTEGER NOT NULL,
    time INTEGER NOT NULL,
    puzzleTime INTEGER,
    photo TEXT,
    event_uid TEXT,
    FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_results_catalog ON results(catalog);
CREATE INDEX IF NOT EXISTS idx_results_name ON results(name);

-- Question results
CREATE TABLE IF NOT EXISTS question_results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    catalog TEXT NOT NULL,
    question_id INTEGER NOT NULL,
    attempt INTEGER NOT NULL,
    correct INTEGER NOT NULL,
    answer_text TEXT,
    photo TEXT,
    consent BOOLEAN,
    event_uid TEXT,
    FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_qresults_catalog ON question_results(catalog);
CREATE INDEX IF NOT EXISTS idx_qresults_name ON question_results(name);
CREATE INDEX IF NOT EXISTS idx_qresults_question ON question_results(question_id);

-- Catalogs
CREATE TABLE IF NOT EXISTS catalogs (
    uid TEXT PRIMARY KEY,
    sort_order INTEGER NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    file TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    raetsel_buchstabe TEXT,
    comment TEXT,
    design_path TEXT,
    event_uid TEXT,
    FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE,
    UNIQUE(event_uid, sort_order)
);

-- Questions
CREATE TABLE IF NOT EXISTS questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    catalog_uid TEXT NOT NULL,
    sort_order INTEGER,
    type TEXT NOT NULL,
    prompt TEXT NOT NULL,
    options TEXT DEFAULT '{}',
    answers TEXT DEFAULT '[]',
    terms TEXT DEFAULT '{}',
    items TEXT DEFAULT '{}',
    cards TEXT DEFAULT '[]',
    right_label TEXT,
    left_label TEXT,
    FOREIGN KEY (catalog_uid) REFERENCES catalogs(uid) ON DELETE CASCADE,
    UNIQUE(catalog_uid, sort_order)
);
CREATE INDEX IF NOT EXISTS idx_questions_catalog ON questions(catalog_uid);

-- Photo consents
CREATE TABLE IF NOT EXISTS photo_consents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    team TEXT NOT NULL,
    time INTEGER NOT NULL,
    event_uid TEXT,
    FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_photo_consents_team ON photo_consents(team);

-- Summary photos
CREATE TABLE IF NOT EXISTS summary_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    path TEXT NOT NULL,
    time INTEGER NOT NULL,
    event_uid TEXT,
    FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_summary_photos_name ON summary_photos(name);

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    email TEXT UNIQUE,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    role TEXT NOT NULL DEFAULT 'catalog-editor',
    position INTEGER NOT NULL DEFAULT 0
);

-- User sessions
CREATE TABLE IF NOT EXISTS user_sessions (
    user_id INTEGER NOT NULL,
    session_id TEXT PRIMARY KEY,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Password resets
CREATE TABLE IF NOT EXISTS password_resets (
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tenants
CREATE TABLE IF NOT EXISTS tenants (
    uid TEXT PRIMARY KEY,
    subdomain TEXT UNIQUE NOT NULL,
    plan TEXT,
    billing_info TEXT,
    stripe_customer_id TEXT,
    stripe_subscription_id TEXT,
    stripe_price_id TEXT,
    stripe_status TEXT,
    stripe_current_period_end TEXT,
    stripe_cancel_at_period_end BOOLEAN,
    imprint_name TEXT,
    imprint_street TEXT,
    imprint_zip TEXT,
    imprint_city TEXT,
    imprint_email TEXT,
    custom_limits TEXT,
    plan_started_at TEXT,
    plan_expires_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Active event
CREATE TABLE IF NOT EXISTS active_event (
    event_uid TEXT PRIMARY KEY,
    FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);

-- Audit log
CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT NOT NULL,
    context TEXT DEFAULT '{}' NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);

-- Email confirmations
CREATE TABLE IF NOT EXISTS email_confirmations (
    email TEXT NOT NULL,
    token TEXT NOT NULL,
    confirmed INTEGER NOT NULL DEFAULT 0,
    expires_at TEXT NOT NULL,
    UNIQUE(token),
    UNIQUE(email)
);

-- Invitations
CREATE TABLE IF NOT EXISTS invitations (
    email TEXT NOT NULL,
    token TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    UNIQUE(token),
    UNIQUE(email)
);

-- Pages
CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT UNIQUE NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Page SEO config
CREATE TABLE IF NOT EXISTS page_seo_config (
    page_id INTEGER PRIMARY KEY REFERENCES pages(id) ON DELETE CASCADE,
    domain TEXT,
    meta_title TEXT,
    meta_description TEXT,
    slug TEXT NOT NULL,
    canonical_url TEXT,
    robots_meta TEXT,
    og_title TEXT,
    og_description TEXT,
    og_image TEXT,
    favicon_path TEXT,
    schema_json TEXT,
    hreflang TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_page_seo_config_domain_slug
    ON page_seo_config(COALESCE(domain, ''), slug);

CREATE TABLE IF NOT EXISTS page_seo_config_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    domain TEXT,
    meta_title TEXT,
    meta_description TEXT,
    slug TEXT,
    canonical_url TEXT,
    robots_meta TEXT,
    og_title TEXT,
    og_description TEXT,
    og_image TEXT,
    favicon_path TEXT,
    schema_json TEXT,
    hreflang TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO pages (slug, title, content) VALUES (
    'calserver',
    'calServer',
    '<section class="calserver-highlight calserver-section-glow" aria-label="calServer in Zahlen">
      <div class="uk-container">
        <div class="calserver-stats-strip" role="list">
          <div class="calserver-stats-strip__item" role="listitem">
            <div class="calserver-stats-strip__header">
              <span class="calserver-stats-strip__value"
                    data-counter-target="1668"
                    data-counter-duration="1600"
                    data-counter-start="0">
                1.668
              </span>
              <div class="calserver-stats-strip__title-group">
                <span class="calserver-stats-strip__title">umgesetzte Kund:innen-Wünsche</span>
                <span class="calserver-stats-strip__label">Stand: 23.09.2025</span>
              </div>
              <span class="calserver-stats-strip__tooltip"
                    data-uk-icon="icon: info"
                    data-uk-tooltip="title: „Priorisierte Kundenanforderungen, ausgeliefert und abgenommen.“; pos: bottom"
                    aria-label="Priorisierte Kundenanforderungen, ausgeliefert und abgenommen."
                    tabindex="0"></span>
            </div>
            <p class="calserver-stats-strip__benefit">Durch Community driven Engineering kundennahe Entwicklung.</p>
          </div>
          <div class="calserver-stats-strip__item" role="listitem">
            <div class="calserver-stats-strip__header">
              <span class="calserver-stats-strip__value"
                    data-counter-target="99.9"
                    data-counter-duration="1800"
                    data-counter-decimals="1"
                    data-counter-suffix=" %"
                    data-counter-start="0">
                99,9 %
              </span>
              <div class="calserver-stats-strip__title-group">
                <span class="calserver-stats-strip__title">Systemverfügbarkeit</span>
                <span class="calserver-stats-strip__label">Stand: 23.09.2025</span>
              </div>
              <span class="calserver-stats-strip__tooltip"
                    data-uk-icon="icon: info"
                    data-uk-tooltip="title: „Zeitanteil, in dem der Service erreichbar war.“; pos: bottom"
                    aria-label="Zeitanteil, in dem der Service erreichbar war."
                    tabindex="0"></span>
            </div>
            <p class="calserver-stats-strip__benefit">Sichere, planbare Abläufe im Alltag.</p>
          </div>
          <div class="calserver-stats-strip__item" role="listitem">
            <div class="calserver-stats-strip__header">
              <span class="calserver-stats-strip__value"
                    data-counter-target="15"
                    data-counter-duration="1400"
                    data-counter-prefix="&gt; "
                    data-counter-start="0">
                &gt; 15
              </span>
              <div class="calserver-stats-strip__title-group">
                <span class="calserver-stats-strip__title">Jahre am Markt</span>
                <span class="calserver-stats-strip__label">Stand: 23.09.2025</span>
              </div>
              <span class="calserver-stats-strip__tooltip"
                    data-uk-icon="icon: info"
                    data-uk-tooltip="title: „calServer wird seit über 15 Jahren produktiv eingesetzt.“; pos: bottom"
                    aria-label="calServer wird seit über 15 Jahren produktiv eingesetzt."
                    tabindex="0"></span>
            </div>
            <p class="calserver-stats-strip__benefit">Erfahrung, Stabilität und gereifte Prozesse.</p>
          </div>
        </div>

        <div class="calserver-logo-marquee"
             aria-label="calServer Leistungsversprechen">
          <div class="calserver-logo-marquee__track" role="list">
            <div class="calserver-logo-marquee__item" role="listitem">
              Hosting in Deutschland
            </div>
            <div class="calserver-logo-marquee__item" role="listitem">
              DSGVO-konform
            </div>
            <div class="calserver-logo-marquee__item" role="listitem">
              Software Made in Germany
            </div>
            <div class="calserver-logo-marquee__item" role="listitem">
              REST-API &amp; Webhooks
            </div>
            <div class="calserver-logo-marquee__item" role="listitem">
              Autom. Backups und weitere keys
            </div>

            <div class="calserver-logo-marquee__item" role="listitem" aria-hidden="true">
              Hosting in Deutschland
            </div>
            <div class="calserver-logo-marquee__item" role="listitem" aria-hidden="true">
              DSGVO-konform
            </div>
            <div class="calserver-logo-marquee__item" role="listitem" aria-hidden="true">
              Software Made in Germany
            </div>
            <div class="calserver-logo-marquee__item" role="listitem" aria-hidden="true">
              REST-API &amp; Webhooks
            </div>
            <div class="calserver-logo-marquee__item" role="listitem" aria-hidden="true">
              Autom. Backups und weitere keys
            </div>
          </div>
        </div>
      </div>
    </section>
    <div class="section-divider"></div>

    <section id="trust" class="uk-section uk-section-muted">
      <div class="uk-container">
        <div class="uk-grid-large uk-flex-middle" data-uk-grid>
          <div class="uk-width-1-1 uk-width-2-3@m">
            <h2 class="uk-heading-line uk-margin-remove-bottom"><span>So fühlt sich calServer im Alltag an</span></h2>
          </div>
          <div class="uk-width-1-1 uk-width-1-3@m">
            <p class="trust-story__lead">
              Vom ersten Login bis zur entspannten Audit-Vorbereitung: calServer nimmt dir Schritt für Schritt den Druck aus der Kalibrier- und Inventarverwaltung.
            </p>
          </div>
        </div>

        
        <ul class="trust-story trust-story--timeline" role="list" aria-label="So begleitet calServer dein Team">
                                              <li class="trust-story__step" role="listitem" tabindex="0" aria-labelledby="trust-step-devices" aria-describedby="trust-step-devices-description">
              <div class="trust-story__marker" aria-hidden="true">
                <span class="trust-story__connector trust-story__connector--before"></span>
                <span class="trust-story__badge" data-step-index="1">
                                      <span class="trust-story__badge-icon" aria-hidden="true" data-uk-icon="icon: database"></span>
                                    <span class="trust-story__sr">Schritt 1</span>
                </span>
                <span class="trust-story__connector trust-story__connector--after"></span>
              </div>
              <div class="trust-story__content">
                <h3 id="trust-step-devices" class="trust-story__title">Alle Geräte auf einen Blick</h3>
                <p id="trust-step-devices-description" class="trust-story__text">
                  Importiere Bestandslisten oder starte direkt im Browser. calServer sammelt Stammdaten, Dokumente und Verantwortlichkeiten an einem Ort, damit nichts verloren geht.
                </p>
              </div>
            </li>
                                              <li class="trust-story__step" role="listitem" tabindex="0" aria-labelledby="trust-step-deadlines" aria-describedby="trust-step-deadlines-description">
              <div class="trust-story__marker" aria-hidden="true">
                <span class="trust-story__connector trust-story__connector--before"></span>
                <span class="trust-story__badge" data-step-index="2">
                                      <span class="trust-story__badge-icon" aria-hidden="true" data-uk-icon="icon: bell"></span>
                                    <span class="trust-story__sr">Schritt 2</span>
                </span>
                <span class="trust-story__connector trust-story__connector--after"></span>
              </div>
              <div class="trust-story__content">
                <h3 id="trust-step-deadlines" class="trust-story__title">Fristen melden sich von selbst</h3>
                <p id="trust-step-deadlines-description" class="trust-story__text">
                  Erinnerungen, Eskalationspfade und mobile Checklisten halten dein Team auf Kurs. Jede Person sieht sofort, welche Prüfaufträge heute wichtig sind.
                </p>
              </div>
            </li>
                                              <li class="trust-story__step" role="listitem" tabindex="0" aria-labelledby="trust-step-audit" aria-describedby="trust-step-audit-description">
              <div class="trust-story__marker" aria-hidden="true">
                <span class="trust-story__connector trust-story__connector--before"></span>
                <span class="trust-story__badge" data-step-index="3">
                                      <span class="trust-story__badge-icon" aria-hidden="true" data-uk-icon="icon: shield"></span>
                                    <span class="trust-story__sr">Schritt 3</span>
                </span>
                <span class="trust-story__connector trust-story__connector--after"></span>
              </div>
              <div class="trust-story__content">
                <h3 id="trust-step-audit" class="trust-story__title">Auditbereit – jederzeit</h3>
                <p id="trust-step-audit-description" class="trust-story__text">
                  Nachweise, Zertifikate und Gerätehistorien liegen revisionssicher bereit. Mit Hosting in Deutschland und täglichen Backups bist du auf Kontrollen vorbereitet.
                </p>
              </div>
            </li>
                  </ul>

        <div class="trust-story__closing uk-grid uk-grid-large uk-flex-middle" data-uk-grid>
          <div class="uk-width-1-1 uk-width-expand@m">
            <h3 class="trust-story__closing-title">Beruhigende Sicherheit für dein Team</h3>
            <p class="trust-story__closing-text">
              DSGVO-konform, zuverlässig betreut und flexibel erweiterbar – calServer wächst mit deinen Abläufen und sorgt dafür, dass Termine, Rollen und Geräte harmonisch zusammenspielen.
            </p>
          </div>
          <div class="uk-width-1-1 uk-width-auto@m">
            <div class="trust-story__cta-group">
              <a class="uk-button uk-button-primary cta-main" href="#trial">
                <span class="uk-margin-small-right" data-uk-icon="icon: play"></span>Jetzt testen
              </a>
              <a class="btn btn-transparent" href="https://calendly.com/calhelp/calserver-vorstellung" target="_blank" rel="noopener">
                <span class="uk-margin-small-right" data-uk-icon="icon: calendar"></span>Demo buchen
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="features" class="uk-section uk-section-default uk-section-large">
      <div class="uk-container">
        <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
          <h2 class="uk-heading-line"><span>Funktionen, die den Alltag erleichtern</span></h2>
          <span class="muted">Scroll &amp; entdecke die wichtigsten Bereiche</span>
        </div>
        <div class="uk-margin-large-top">
          <div class="uk-text-center uk-margin-medium-bottom">
            <h3 class="uk-heading-bullet">Funktionen im Fokus</h3>
            <p class="muted">Wähle die Bereiche, die dein Team täglich nutzt – und spring direkt zur passenden
              Funktionskarte.</p>
          </div>
          <nav class="feature-nav" aria-label="Funktionsnavigation">
            <ul id="feature-nav" class="feature-nav__list">
              <li><a class="feature-nav__pill" href="#" data-target="feature-inventare-intervalle">Inventare &amp; Intervalle</a></li>
              <li><a class="feature-nav__pill" href="#" data-target="feature-kalibrierscheine">Kalibrierscheine digitalisieren</a></li>
              <li><a class="feature-nav__pill" href="#" data-target="feature-email-abrufe">E-Mail-Abrufe &amp; Erinnerungen</a></li>
              <li><a class="feature-nav__pill" href="#" data-target="feature-geraeteverwaltung">Geräteverwaltung</a></li>
              <li><a class="feature-nav__pill" href="#" data-target="feature-kalibrier-reparatur">Kalibrier- &amp; Reparaturverwaltung</a></li>
              <li><a class="feature-nav__pill" href="#" data-target="feature-auftragsbearbeitung">Auftragsbearbeitung</a></li>
              <li><a class="feature-nav__pill" href="#" data-target="feature-leihverwaltung">Leihverwaltung</a></li>
              <li><a class="feature-nav__pill" href="#" data-target="feature-dokumentationen">Dokumentationen &amp; Wiki</a></li>
              <li><a class="feature-nav__pill" href="#" data-target="feature-dms">Dateimanagement (DMS)</a></li>
              <li><a class="feature-nav__pill" href="#" data-target="feature-meldungen">Meldungen &amp; Tickets</a></li>
              <li><a class="feature-nav__pill" href="#" data-target="feature-cloud">Moderne Cloud-Basis</a></li>
            </ul>
          </nav>
        </div>
        <div id="feature-slider"
             class="uk-position-relative uk-visible-toggle feature-slider"
             tabindex="-1"
             data-uk-slider="center: true; autoplay: true; autoplay-interval: 4200; finite: false">
          <div class="uk-slider-container">
            <ul class="uk-slider-items uk-child-width-1-1@s uk-child-width-1-2@m uk-child-width-1-3@l feature-slider__list"
                data-uk-scrollspy="cls: uk-animation-slide-bottom-small; target: .feature-slider__item; delay: 75; repeat: true">
              <li class="feature-slider__item" id="feature-inventare-intervalle">
                <article class="feature-card">
                  <h3 class="uk-card-title feature-card__title">Inventare &amp; Intervalle</h3>
                  <p>Fälligkeiten kommen zu dir – Erinnerungen, Abrufe und Planungsübersichten sorgen für Ruhe.</p>
                  <ul class="uk-list uk-list-bullet">
                    <li>Automatische Erinnerungslogik</li>
                    <li>Klare Statusfarben</li>
                    <li>Planungs-Dashboards</li>
                  </ul>
                </article>
              </li>
              <li class="feature-slider__item" id="feature-kalibrierscheine">
                <article class="feature-card">
                  <h3 class="uk-card-title feature-card__title">Kalibrierscheine digitalisieren</h3>
                  <p>Einfach hochladen, alles Weitere erledigt die Zuordnung mit Versionierung &amp; Vorschau.</p>
                  <ul class="uk-list uk-list-bullet">
                    <li>Intelligente Dateinamen-Erkennung</li>
                    <li>Versionen &amp; Freigaben</li>
                    <li>Teilen per Link</li>
                  </ul>
                </article>
              </li>
              <li class="feature-slider__item" id="feature-email-abrufe">
                <article class="feature-card">
                  <h3 class="uk-card-title feature-card__title">E-Mail-Abrufe &amp; Erinnerungen</h3>
                  <p>Persönlich und planbar – Serien mit System statt Einzelmails.</p>
                  <ul class="uk-list uk-list-bullet">
                    <li>Vorlagen &amp; Platzhalter</li>
                    <li>Zeitpläne je Zielgruppe</li>
                    <li>Versandprotokoll</li>
                  </ul>
                </article>
              </li>
              <li class="feature-slider__item" id="feature-geraeteverwaltung">
                <article class="feature-card">
                  <h3 class="uk-card-title feature-card__title">Geräteverwaltung</h3>
                  <p>Ob 100 oder 100.000 Geräte – Suche, Filter und Gruppen bleiben schnell.</p>
                  <ul class="uk-list uk-list-bullet">
                    <li>Schnellsuche &amp; Filter</li>
                    <li>Sets &amp; Zubehör</li>
                    <li>Exporte (CSV/PDF)</li>
                  </ul>
                </article>
              </li>
              <li class="feature-slider__item" id="feature-kalibrier-reparatur">
                <article class="feature-card">
                  <h3 class="uk-card-title feature-card__title">Kalibrier- &amp; Reparaturverwaltung</h3>
                  <p>Von Messwerten bis Bericht – ohne Medienbrüche, mit revisionssicheren Freigaben.</p>
                  <ul class="uk-list uk-list-bullet">
                    <li>Messwerte erfassen/importieren</li>
                    <li>Bearbeitbare Übersichten</li>
                    <li>Berichte direkt erzeugen</li>
                  </ul>
                </article>
              </li>
              <li class="feature-slider__item" id="feature-auftragsbearbeitung">
                <article class="feature-card">
                  <h3 class="uk-card-title feature-card__title">Auftragsbearbeitung</h3>
                  <p>Ein Flow für alles: Angebot → Auftrag → Rechnung – mit eigenem Briefpapier.</p>
                  <ul class="uk-list uk-list-bullet">
                    <li>Sammel- &amp; Teilrechnungen</li>
                    <li>Preislisten &amp; Nummernkreise</li>
                    <li>Automatische Status</li>
                  </ul>
                </article>
              </li>
              <li class="feature-slider__item" id="feature-leihverwaltung">
                <article class="feature-card">
                  <h3 class="uk-card-title feature-card__title">Leihverwaltung</h3>
                  <p>Reservieren statt Telefonkette – Kalender auf, Gerät rein, fertig.</p>
                  <ul class="uk-list uk-list-bullet">
                    <li>Drag-and-Drop Kalender</li>
                    <li>Zubehör-Sets</li>
                    <li>Rückgabe-Erinnerungen</li>
                  </ul>
                </article>
              </li>
              <li class="feature-slider__item" id="feature-dokumentationen">
                <article class="feature-card">
                  <h3 class="uk-card-title feature-card__title">Dokumentationen &amp; Wiki</h3>
                  <p>Wissen bleibt im Team – gepflegt, versioniert und durchsuchbar.</p>
                  <ul class="uk-list uk-list-bullet">
                    <li>Editor mit Inhaltsverzeichnis</li>
                    <li>Versionen &amp; Berechtigungen</li>
                    <li>Interne Verlinkungen</li>
                  </ul>
                </article>
              </li>
              <li class="feature-slider__item" id="feature-dms">
                <article class="feature-card">
                  <h3 class="uk-card-title feature-card__title">Dateimanagement (DMS)</h3>
                  <p>„Nur speichern, nicht sortieren“ – Zuordnung läuft im Hintergrund.</p>
                  <ul class="uk-list uk-list-bullet">
                    <li>Auto-Zuordnung</li>
                    <li>Versionierung</li>
                    <li>PDF-Viewer</li>
                  </ul>
                </article>
              </li>
              <li class="feature-slider__item" id="feature-meldungen">
                <article class="feature-card">
                  <h3 class="uk-card-title feature-card__title">Meldungen &amp; Tickets</h3>
                  <p>Alles an einem Ort: Anliegen, Verlauf, Benachrichtigungen.</p>
                  <ul class="uk-list uk-list-bullet">
                    <li>Zentrales Ticketing</li>
                    <li>Teilnehmerwechsel</li>
                    <li>Benachrichtigungsketten</li>
                  </ul>
                </article>
              </li>
              <li class="feature-slider__item" id="feature-cloud">
                <article class="feature-card">
                  <h3 class="uk-card-title feature-card__title">Moderne Cloud-Basis</h3>
                  <p>Schnell, stabil und updatefreundlich – ohne großen Admin-Aufwand.</p>
                  <ul class="uk-list uk-list-bullet">
                    <li>Skalierbare Umgebung</li>
                    <li>Regelmäßige Updates</li>
                    <li>Tägliche Backups</li>
                  </ul>
                </article>
              </li>
            </ul>
          </div>
          <a class="uk-position-center-left uk-position-small uk-hidden-hover"
             href="#"
             data-uk-slidenav-previous
             data-uk-slider-item="previous"
             aria-label="Vorherige Funktion"></a>
          <a class="uk-position-center-right uk-position-small uk-hidden-hover"
             href="#"
             data-uk-slidenav-next
             data-uk-slider-item="next"
             aria-label="Nächste Funktion"></a>
        </div>
        <script>
          document.addEventListener(''DOMContentLoaded'', () => {
            const sliderElement = document.getElementById(''feature-slider'');
            const navElement = document.getElementById(''feature-nav'');
            if (!sliderElement || !navElement || typeof UIkit === ''undefined'') {
              return;
            }

            const slider = UIkit.slider(sliderElement);
            const pills = Array.from(navElement.querySelectorAll(''li''));
            const slides = Array.from(sliderElement.querySelectorAll(''.feature-slider__item''));
            const itemsContainer = sliderElement.querySelector(''.uk-slider-items'');
            if (!itemsContainer) {
              return;
            }

            const indexById = new Map(slides.map((slide, index) => [slide.id, index]));

            const applyFocusToCenter = () => {
              const slideElements = Array.from(itemsContainer.children);
              if (!slideElements.length) {
                return;
              }

              const viewportElement = sliderElement.querySelector(''.uk-slider-container'') || sliderElement;
              const viewportRect = viewportElement.getBoundingClientRect();
              const midpoint = viewportRect.left + viewportRect.width / 2;

              let nearest = null;
              let minDistance = Number.POSITIVE_INFINITY;

              slideElements.forEach((item) => {
                const card = item.querySelector(''.feature-card'');
                if (!card) {
                  return;
                }

                const rect = item.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const distance = Math.abs(centerX - midpoint);

                if (distance < minDistance) {
                  minDistance = distance;
                  nearest = item;
                }
              });

              slideElements.forEach((item) => {
                item.classList.remove(''is-center'');
                const card = item.querySelector(''.feature-card'');
                if (card) {
                  card.classList.remove(''feature-card--focus'');
                }
              });

              if (nearest) {
                nearest.classList.add(''is-center'');
                const focusCard = nearest.querySelector(''.feature-card'');
                if (focusCard) {
                  focusCard.classList.add(''feature-card--focus'');
                }
              }
            };

            const setActive = (index) => {
              pills.forEach((li, i) => {
                const isActive = i === index;
                li.classList.toggle(''uk-active'', isActive);

                const link = li.querySelector(''.feature-nav__pill'');
                if (!link) {
                  return;
                }

                link.classList.toggle(''is-active'', isActive);
                if (isActive) {
                  link.setAttribute(''aria-current'', ''true'');
                } else {
                  link.removeAttribute(''aria-current'');
                }
              });
            };

            const setCurrent = (index) => {
              slides.forEach((slide, i) => slide.classList.toggle(''uk-current'', i === index));
            };

            pills.forEach((li) => {
              const link = li.querySelector(''a[data-target]'');
              if (!link) {
                return;
              }

              const targetId = link.dataset.target;
              const targetIndex = indexById.get(targetId);
              if (typeof targetIndex === ''undefined'') {
                return;
              }

              link.addEventListener(''click'', (event) => {
                event.preventDefault();
                slider.show(targetIndex);
                setActive(targetIndex);
                setCurrent(targetIndex);
                applyFocusToCenter();
              });
            });

            UIkit.util.on(sliderElement, ''itemshown'', () => {
              setActive(slider.index);
              setCurrent(slider.index);
              applyFocusToCenter();
            });

            const initialIndex = typeof slider.index === ''number'' ? slider.index : 0;
            setActive(initialIndex);
            setCurrent(initialIndex);
            applyFocusToCenter();
            window.addEventListener(''resize'', applyFocusToCenter);
          });
        </script>
      </div>
    </section>

    <hr class="uk-divider-icon">

            <section id="modules" class="uk-section uk-section-muted">
      <div class="uk-container">
        <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
          <h2 class="uk-heading-line"><span>Module, die den Unterschied machen</span></h2>
          <span class="muted">Individuell kombinierbar – ohne versteckte Kosten</span>
        </div>
        <div class="calserver-modules-grid" data-uk-scrollspy="cls: uk-animation-slide-bottom-small; target: > *; delay: 100; repeat: true">
          <div>
            <ul class="uk-tab calserver-modules-nav"
                data-uk-switcher="connect: #calserver-modules-switcher; animation: uk-animation-fade">
                              <li>
                  <a class="calserver-modules-nav__link" href="#module-device-management">
                    <span class="calserver-modules-nav__title">Geräteverwaltung & Historie</span>
                    <span class="calserver-modules-nav__desc">Geräteakten, Anhänge und Historie in einer Oberfläche – inklusive Messwerten.</span>
                  </a>
                </li>
                              <li>
                  <a class="calserver-modules-nav__link" href="#module-calendar-resources">
                    <span class="calserver-modules-nav__title">Kalender & Ressourcen</span>
                    <span class="calserver-modules-nav__desc">Planung von Terminen, Leihgeräten und Personal in einer Ansicht.</span>
                  </a>
                </li>
                              <li>
                  <a class="calserver-modules-nav__link" href="#module-order-ticketing">
                    <span class="calserver-modules-nav__title">Auftrags- & Ticketverwaltung</span>
                    <span class="calserver-modules-nav__desc">Vom Auftrag bis zur Rechnung – mit klaren Status, Workflows und Dokumenten.</span>
                  </a>
                </li>
                              <li>
                  <a class="calserver-modules-nav__link" href="#module-self-service">
                    <span class="calserver-modules-nav__title">Self-Service & Extranet</span>
                    <span class="calserver-modules-nav__desc">Stellen Sie Kunden & Partnern Geräteinfos, Zertifikate und Formulare bereit.</span>
                  </a>
                </li>
                          </ul>
          </div>
          <div>
            <ul id="calserver-modules-switcher" class="uk-switcher calserver-modules-switcher">
                              <li>
                  <figure id="module-device-management" class="calserver-module-figure">
                    <img src="{{ basePath }}/uploads/calserver/modules/module-placeholder.svg"
                         width="1200"
                         height="675"
                         loading="lazy"
                         decoding="async"
                         alt="Platzhaltergrafik für die calServer-Geräteverwaltung – finaler Screenshot folgt">
                    <figcaption>
                      <h3 class="uk-h3">Geräteverwaltung & Historie</h3>
                      <p class="muted">Geräteakten, Anhänge und Historie in einer Oberfläche – inklusive Messwerten.</p>
                                              <ul class="uk-list uk-list-bullet muted">
                                                      <li>Geräte- & Standortverwaltung</li>
                                                      <li>Versionierte Dokumente & Bilder</li>
                                                      <li>Messwerte direkt verknüpfen</li>
                                                  </ul>
                                          </figcaption>
                  </figure>
                </li>
                              <li>
                  <figure id="module-calendar-resources" class="calserver-module-figure">
                    <img src="{{ basePath }}/uploads/calserver/modules/module-placeholder.svg"
                         width="1200"
                         height="675"
                         loading="lazy"
                         decoding="async"
                         alt="Platzhaltergrafik für den calServer-Kalender – finaler Screenshot folgt">
                    <figcaption>
                      <h3 class="uk-h3">Kalender & Ressourcen</h3>
                      <p class="muted">Planung von Terminen, Leihgeräten und Personal in einer Ansicht.</p>
                                              <ul class="uk-list uk-list-bullet muted">
                                                      <li>Gantt & Kalender</li>
                                                      <li>Verfügbarkeits-Check in Echtzeit</li>
                                                      <li>Outlook/iCal-Integration</li>
                                                  </ul>
                                          </figcaption>
                  </figure>
                </li>
                              <li>
                  <figure id="module-order-ticketing" class="calserver-module-figure">
                    <img src="{{ basePath }}/uploads/calserver/modules/module-placeholder.svg"
                         width="1200"
                         height="675"
                         loading="lazy"
                         decoding="async"
                         alt="Platzhaltergrafik für die calServer-Auftragsverwaltung – finaler Screenshot folgt">
                    <figcaption>
                      <h3 class="uk-h3">Auftrags- & Ticketverwaltung</h3>
                      <p class="muted">Vom Auftrag bis zur Rechnung – mit klaren Status, Workflows und Dokumenten.</p>
                                              <ul class="uk-list uk-list-bullet muted">
                                                      <li>Service & Ticketsystem</li>
                                                      <li>Angebote, Aufträge, Rechnungen</li>
                                                      <li>Eskalationen & SLAs</li>
                                                  </ul>
                                          </figcaption>
                  </figure>
                </li>
                              <li>
                  <figure id="module-self-service" class="calserver-module-figure">
                    <img src="{{ basePath }}/uploads/calserver/modules/module-placeholder.svg"
                         width="1200"
                         height="675"
                         loading="lazy"
                         decoding="async"
                         alt="Platzhaltergrafik für das calServer-Kundenportal – finaler Screenshot folgt">
                    <figcaption>
                      <h3 class="uk-h3">Self-Service & Extranet</h3>
                      <p class="muted">Stellen Sie Kunden & Partnern Geräteinfos, Zertifikate und Formulare bereit.</p>
                                              <ul class="uk-list uk-list-bullet muted">
                                                      <li>Kundenportale</li>
                                                      <li>Dokumente & Zertifikate</li>
                                                      <li>Individuelle Rechte</li>
                                                  </ul>
                                          </figcaption>
                  </figure>
                </li>
                          </ul>
          </div>
        </div>
      </div>
    </section>

    <section id="usecases" class="uk-section uk-section-default">
      <div class="uk-container">
        <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
          <h2 class="uk-heading-line"><span>Anwendungsfälle aus der Praxis</span></h2>
          <span class="muted">Vom Labor bis zum Außendienst</span>
        </div>
        <ul class="uk-subnav uk-subnav-pill uk-margin-large-top usecase-tabs" data-uk-switcher="animation: uk-animation-fade">
          <li><a href="#">ifm electronic (2 Standorte)</a></li>
          <li><a href="#">KSW Kalibrierservice GmbH</a></li>
          <li><a href="#">Systems Engineering GmbH</a></li>
          <li><a href="#">TERAMESS (DAkkS-Labor)</a></li>
          <li><a href="#">Thermo Fisher Scientific (EMEA)</a></li>
          <li><a href="#">ZF Friedrichshafen</a></li>
          <li><a href="#">Berliner Stadtwerke</a></li>
          <li><a href="#">VDE Deutschland</a></li>
        </ul>
        <ul class="uk-switcher uk-margin-large-top"
            data-uk-scrollspy="cls: uk-animation-slide-bottom-small; target: .usecase-story, .usecase-highlight; delay: 100; repeat: true">
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-middle" data-uk-grid>
              <div class="usecase-story">
                <span class="pill pill--badge uk-margin-small-bottom">Kalibrierlabor</span>
                <h3 class="uk-h2">ifm – Störungsbearbeitung &amp; Verbesserungen über zwei Standorte</h3>
                <p class="uk-text-lead">calServer steuert die interne Kalibrier- und Störungsbearbeitung über zwei Standorte hinweg.</p>
                <p>Fehlermeldungen werden im Ticketmanagement strukturiert aufgenommen, priorisiert und nachverfolgt; gleichzeitig dient es der Chancen-/Risiken-Betrachtung für kontinuierliche Verbesserungen. Die bidirektionale Synchronisation mit Fluke MET/TEAM und MET/CAL hält Geräte- und Kalibrierdaten standortübergreifend konsistent.</p>
                <ul class="uk-list uk-list-bullet muted">
                  <li>Ticketmanagement für Störungen &amp; CAPA-nahe Auswertungen</li>
                  <li>Standortübergreifende Geräteakten &amp; Zertifikate im DMS</li>
                  <li>Bidirektionale MET/TEAM- &amp; MET/CAL-Synchronisation</li>
                </ul>
              </div>
              <div class="usecase-highlight">
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card">
                  <h4 class="uk-heading-bullet">Key Facts</h4>
                  <ul class="uk-list uk-list-divider">
                    <li>Zwei Standorte, konsistente Datenbasis</li>
                    <li>Tickets + Chancen-/Risiken-Bewertung</li>
                    <li>Bidirektionale Synchronisation mit Fluke MET/TEAM &amp; MET/CAL</li>
                  </ul>
                                    <a class="uk-button uk-button-text uk-margin-top" href="#">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="usecase-visual uk-margin-top">
                  <div class="uk-height-medium uk-background-muted uk-border-rounded uk-flex uk-flex-middle uk-flex-center">
                    <span class="muted">Ticketmanagement &amp; Verbesserungen</span>
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-middle" data-uk-grid>
              <div class="usecase-story">
                <span class="pill pill--badge uk-margin-small-bottom">Kalibrierlabor</span>
                <h3 class="uk-h2">KSW – End-to-End vom Wareneingang bis zur Rechnung</h3>
                <p class="uk-text-lead">calServer bildet den gesamten Ablauf von Wareneingang über Labor bis zur Abrechnung ab.</p>
                <p>Laborbegleitscheine, Geräteakten und Zertifikate liegen zentral vor; Auftragsbearbeitung, Status und Kommunikation greifen ineinander — revisionssicher, schnell, transparent.</p>
                <ul class="uk-list uk-list-bullet muted">
                  <li>Auftragsbearbeitung mit SLAs, Eskalation &amp; Serienmails</li>
                  <li>DMS für Zertifikate, Berichte und Historie</li>
                  <li>Durchgängige Übergabe an Abrechnung &amp; Reporting</li>
                </ul>
              </div>
              <div class="usecase-highlight">
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card">
                  <h4 class="uk-heading-bullet">Key Facts</h4>
                  <ul class="uk-list uk-list-divider">
                    <li>Wareneingang → Labor → Rechnung</li>
                    <li>Auftragsbearbeitung als Taktgeber</li>
                    <li>DMS &amp; Reporting integriert</li>
                  </ul>
                                    <a class="uk-button uk-button-text uk-margin-top" href="#">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="usecase-visual uk-margin-top">
                  <div class="uk-height-medium uk-background-muted uk-border-rounded uk-flex uk-flex-middle uk-flex-center">
                    <span class="muted">Wareneingang bis Abrechnung</span>
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-middle" data-uk-grid>
              <div class="usecase-story">
                <span class="pill pill--badge uk-margin-small-bottom">Kalibrierlabor</span>
                <h3 class="uk-h2">Systems Engineering – Auftragsbearbeitung als Herzstück</h3>
                <p class="uk-text-lead">calServer macht die Auftragsbearbeitung zum steuernden Zentrum des Labors.</p>
                <p>Interne Aufgaben werden klar priorisiert, Reportings kommen direkt aus dem System und bleiben dank Versionierung nachvollziehbar — für verlässliche Termine und klare Zuständigkeiten.</p>
                <ul class="uk-list uk-list-bullet muted">
                  <li>Aufgaben-/Rollensteuerung mit Status &amp; Checklisten</li>
                  <li>Eigene Reports aus Auftrags- und Gerätedaten</li>
                  <li>DMS &amp; Historie für Audit- und Nachweispflichten</li>
                </ul>
              </div>
              <div class="usecase-highlight">
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card">
                  <h4 class="uk-heading-bullet">Key Facts</h4>
                  <ul class="uk-list uk-list-divider">
                    <li>Klarer Status- &amp; Rollenfluss</li>
                    <li>Reports direkt aus den Prozessdaten</li>
                    <li>Versionierung &amp; Nachvollziehbarkeit (DMS)</li>
                  </ul>
                                    <a class="uk-button uk-button-text uk-margin-top" href="#">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="usecase-visual uk-margin-top">
                  <div class="uk-height-medium uk-background-muted uk-border-rounded uk-flex uk-flex-middle uk-flex-center">
                    <span class="muted">Auftragssteuerung &amp; Reporting</span>
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-middle" data-uk-grid>
              <div class="usecase-story">
                <span class="pill pill--badge uk-margin-small-bottom">Kalibrierlabor</span>
                <h3 class="uk-h2">TERAMESS – DAkkS-konforme Zertifikate in der Cloud</h3>
                <p class="uk-text-lead">calServer CLOUD bündelt DAkkS-konforme Zertifikate, Geräteakten und Prüfberichte.</p>
                <p>Zertifikate werden revisionssicher abgelegt; Wiederhol- und Folgemessungen bleiben transparent nachvollziehbar. Kommunikation und Dokumente laufen in klaren, prüffesten Bahnen.</p>
                <ul class="uk-list uk-list-bullet muted">
                  <li>Revisionssichere Ablage mit Versionierung</li>
                  <li>Strukturierte Prüf- &amp; Messhistorie</li>
                  <li>Auswertungen &amp; Serienexports für Kund:innenkommunikation</li>
                </ul>
              </div>
              <div class="usecase-highlight">
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card">
                  <h4 class="uk-heading-bullet">Key Facts</h4>
                  <ul class="uk-list uk-list-divider">
                    <li>Revisionssicheres DMS in der Cloud</li>
                    <li>DAkkS-konforme Prüfhistorie</li>
                    <li>Serienexports &amp; Auswertungen</li>
                  </ul>
                                    <a class="uk-button uk-button-text uk-margin-top" href="#">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="usecase-visual uk-margin-top">
                  <div class="uk-height-medium uk-background-muted uk-border-rounded uk-flex uk-flex-middle uk-flex-center">
                    <span class="muted">DAkkS-Zertifikate &amp; Historie</span>
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-middle" data-uk-grid>
              <div class="usecase-story">
                <span class="pill pill--badge uk-margin-small-bottom">Kalibrierlabor</span>
                <h3 class="uk-h2">Thermo Fisher Scientific – EMEA Labore</h3>
                <p class="uk-text-lead">calServer orchestriert EMEA-weit Leihgeräte, Geräteakten und ISO-/DAkkS-konforme Zertifikate auf einer Plattform.</p>
                <p>Mehrere Labore arbeiten in einem konsistenten Workflow: Leihverwaltung, Prüffristen und Zertifikate werden zentral gesteuert, während Aufträge und Rückgaben standortübergreifend nachvollziehbar bleiben. Die bidirektionale Synchronisation mit Fluke MET/TEAM und MET/CAL hält Stammdaten, Kalibrierläufe und Ergebnisse automatisch aktuell.</p>
                <ul class="uk-list uk-list-bullet muted">
                  <li>Zentrale Leihverwaltung mit Termin- &amp; Rückgabe-Erinnerungen</li>
                  <li>Revisionssicheres DMS für Zertifikate &amp; Historie</li>
                  <li>Bidirektionale MET/TEAM- &amp; MET/CAL-Synchronisation</li>
                </ul>
              </div>
              <div class="usecase-highlight">
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card">
                  <h4 class="uk-heading-bullet">Key Facts</h4>
                  <ul class="uk-list uk-list-divider">
                    <li>EMEA-weit einheitliche Leihverwaltung &amp; Geräteakten</li>
                    <li>Revisionssicheres DMS für Zertifikate &amp; Historie</li>
                    <li>Bidirektionale Synchronisation mit Fluke MET/TEAM &amp; MET/CAL</li>
                  </ul>
                                    <a class="uk-button uk-button-text uk-margin-top" href="#">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="usecase-visual uk-margin-top">
                  <div class="uk-height-medium uk-background-muted uk-border-rounded uk-flex uk-flex-middle uk-flex-center">
                    <span class="muted">Leihverwaltung &amp; Geräteakten</span>
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-middle" data-uk-grid>
              <div class="usecase-story">
                <span class="pill pill--badge uk-margin-small-bottom">Industrielabor</span>
                <h3 class="uk-h2">ZF – API-Messwerte auf Kubernetes mit SSO</h3>
                <p class="uk-text-lead">calServer verbindet skalierbare Messwert-APIs auf Kubernetes mit SSO und Geräteakten-Management.</p>
                <p>Messwerte fließen über Microservices automatisiert ein; Geräte, Zertifikate und Auswertungen bleiben im Zugriff der berechtigten Teams. Single Sign-On vereinfacht den Zugang, die bidirektionale Synchronisation mit Fluke MET/TEAM und MET/CAL stellt durchgehend konsistente Kalibrierdaten sicher.</p>
                <ul class="uk-list uk-list-bullet muted">
                  <li>API-Ingestion von Messwerten (Microservices/Kubernetes)</li>
                  <li>SSO (z. B. EntraID/Google) für nahtlosen Zugriff</li>
                  <li>Bidirektionale MET/TEAM- &amp; MET/CAL-Synchronisation</li>
                </ul>
              </div>
              <div class="usecase-highlight">
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card">
                  <h4 class="uk-heading-bullet">Key Facts</h4>
                  <ul class="uk-list uk-list-divider">
                    <li>Microservices auf Kubernetes</li>
                    <li>SSO für schnellen, sicheren Zugang</li>
                    <li>Bidirektionale Synchronisation mit Fluke MET/TEAM &amp; MET/CAL</li>
                  </ul>
                                    <a class="uk-button uk-button-text uk-margin-top" href="#">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="usecase-visual uk-margin-top">
                  <div class="uk-height-medium uk-background-muted uk-border-rounded uk-flex uk-flex-middle uk-flex-center">
                    <span class="muted">Messwerte &amp; SSO-Workflows</span>
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-middle" data-uk-grid>
              <div class="usecase-story">
                <span class="pill pill--badge uk-margin-small-bottom">Assetmanagement</span>
                <h3 class="uk-h2">Berliner Stadtwerke – Projekte &amp; Wartung für erneuerbare Anlagen</h3>
                <p class="uk-text-lead">calServer steuert Projekte, Wartungspläne und Einsätze für regenerative Energieanlagen der Stadt Berlin.</p>
                <p>Vom Maßnahmenplan bis zur Einsatzplanung: Teams behalten Verfügbarkeit, Leistung und Kosten im Blick. Checklisten, Offline-Fähigkeit und Eskalationslogik sichern die fristgerechte Abarbeitung. (Ohne MET/CAL/MET/TEAM – Fokus auf Projekt- &amp; Wartungssteuerung.)</p>
                <ul class="uk-list uk-list-bullet muted">
                  <li>Projekt- &amp; Maßnahmensteuerung inkl. Einsatzplanung</li>
                  <li>Geplante/ungeplante Wartung mit Checklisten &amp; Offline-Modus</li>
                  <li>Dashboards für Verfügbarkeit, Leistung &amp; Kosten/Nutzen</li>
                </ul>
              </div>
              <div class="usecase-highlight">
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card">
                  <h4 class="uk-heading-bullet">Key Facts</h4>
                  <ul class="uk-list uk-list-divider">
                    <li>Stadtweite EE-Anlagen im Überblick</li>
                    <li>Geplante &amp; ungeplante Wartung aus einem System</li>
                    <li>Dashboards für Verfügbarkeit &amp; Performance</li>
                  </ul>
                                    <a class="uk-button uk-button-text uk-margin-top" href="#">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="usecase-visual uk-margin-top">
                  <div class="uk-height-medium uk-background-muted uk-border-rounded uk-flex uk-flex-middle uk-flex-center">
                    <span class="muted">Wartungs- &amp; Projektplanung</span>
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-middle" data-uk-grid>
              <div class="usecase-story">
                <span class="pill pill--badge uk-margin-small-bottom">Qualitätsmanagement</span>
                <h3 class="uk-h2">VDE – Agile Auftragssteuerung &amp; Intranet</h3>
                <p class="uk-text-lead">calServer bündelt agile Auftragssteuerung und Dokumentenflüsse für Prüf- und Zertifizierungsprozesse.</p>
                <p>Teams planen, priorisieren und dokumentieren Vorgänge auf einem anpassbaren Board; Freigaben sind versioniert, nachvollziehbar und auditfest. Rollen und Vorlagen verteilen Informationen zielgerichtet. Die bidirektionale Synchronisation mit Fluke MET/TEAM und MET/CAL sorgt für konsistente Mess- und Auftragsdaten.</p>
                <ul class="uk-list uk-list-bullet muted">
                  <li>Agiles Auftragsboard mit SLAs, Eskalationen und Vorlagen</li>
                  <li>Revisionssichere DMS-Ablage inkl. Freigabe-Workflow</li>
                  <li>Bidirektionale MET/TEAM- &amp; MET/CAL-Synchronisation</li>
                </ul>
              </div>
              <div class="usecase-highlight">
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card">
                  <h4 class="uk-heading-bullet">Key Facts</h4>
                  <ul class="uk-list uk-list-divider">
                    <li>Agiles Auftragsboard (SLAs, Eskalationslogik)</li>
                    <li>Audit-Trails &amp; versionierte Freigaben</li>
                    <li>Bidirektionale Synchronisation mit Fluke MET/TEAM &amp; MET/CAL</li>
                  </ul>
                                    <a class="uk-button uk-button-text uk-margin-top" href="#">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="usecase-visual uk-margin-top">
                  <div class="uk-height-medium uk-background-muted uk-border-rounded uk-flex uk-flex-middle uk-flex-center">
                    <span class="muted">Agiles Auftragsboard &amp; DMS</span>
                  </div>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </section>

    <section id="modes" class="uk-section uk-section-primary uk-light calserver-highlight calserver-section-glow">
      <div class="uk-container">
        <h2 class="uk-heading-line uk-light"><span>Betriebsarten, die zu Ihnen passen</span></h2>
        <ul class="uk-subnav uk-subnav-pill uk-margin" data-uk-switcher>
          <li><a href="#">Cloud</a></li>
          <li><a href="#">On-Premise</a></li>
        </ul>
        <ul class="uk-switcher uk-margin"
            data-uk-scrollspy="cls: uk-animation-slide-bottom-small; target: .anim; delay: 75; repeat: true">
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-grid-match" data-uk-grid>
              <div class="anim">
                <div class="uk-card uk-card-default uk-card-body uk-card-hover calserver-highlight-card">
                  <h3 class="uk-card-title">Sofort startklar</h3>
                  <ul class="uk-list uk-list-bullet muted">
                    <li>Bereitstellung in wenigen Tagen</li>
                    <li>Automatisierte Updates &amp; Monitoring</li>
                    <li>Backup-Strategie inklusive</li>
                  </ul>
                </div>
              </div>
              <div class="anim">
                <div class="uk-card uk-card-default uk-card-body uk-card-hover calserver-highlight-card">
                  <h3 class="uk-card-title">Sicher &amp; skalierbar</h3>
                  <ul class="uk-list uk-list-bullet muted">
                    <li>Rechenzentrum in Deutschland</li>
                    <li>ISO 27001 zertifizierte Infrastruktur</li>
                    <li>Flexible Nutzer:innenzahlen</li>
                  </ul>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-grid-match" data-uk-grid>
              <div class="anim">
                <div class="uk-card uk-card-default uk-card-body uk-card-hover calserver-highlight-card">
                  <h3 class="uk-card-title">Volle Kontrolle</h3>
                  <ul class="uk-list uk-list-bullet muted">
                    <li>Betrieb im eigenen Netzwerk</li>
                    <li>Unterstützung bei Installation &amp; Updates</li>
                    <li>Integration in bestehende Systeme</li>
                  </ul>
                </div>
              </div>
              <div class="anim">
                <div class="uk-card uk-card-default uk-card-body uk-card-hover calserver-highlight-card">
                  <h3 class="uk-card-title">Individuelle Sicherheit</h3>
                  <ul class="uk-list uk-list-bullet muted">
                    <li>Anbindung an Ihr Identity-Management</li>
                    <li>Flexible Backup- und Wartungsfenster</li>
                    <li>Support per SLA vereinbar</li>
                  </ul>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </section>

    <section id="pricing" class="uk-section uk-section-default">
      <div class="uk-container">
        <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
          <h2 class="uk-heading-line"><span>Preise, die Klarheit schaffen</span></h2>
          <span class="muted">Ehrlich, transparent, ohne versteckte Kosten.</span>
        </div>
        <div class="uk-child-width-1-1 uk-child-width-1-3@m uk-grid-large uk-grid-match"
             data-uk-grid
             data-uk-scrollspy="cls: uk-animation-slide-bottom-small; target: .anim; delay: 75; repeat: true">
          <div class="anim">
            <div class="uk-card uk-card-default uk-card-body uk-text-center uk-card-hover shadow-soft">
              <h3>Standard</h3>
              <p class="muted">Die solide Basis für kleine Teams.</p>
              <p class="uk-text-large uk-text-bold">ab 250 €/Monat</p>
              <ul class="uk-list uk-list-bullet uk-text-left muted">
                <li>Bis 2 Mandanten</li>
                <li>Alle Kernmodule inklusive</li>
                <li>E-Mail-Support innerhalb von 24 h</li>
              </ul>
              <a class="uk-button uk-button-default" href="#offer">Unverbindliches Angebot</a>
            </div>
          </div>
          <div class="anim">
            <div class="uk-card uk-card-primary uk-card-primary--highlight uk-card-body uk-text-center uk-card-hover shadow-soft uk-position-relative">
              <span class="pill pill--badge uk-position-absolute uk-position-top-right uk-margin-small">Empfohlen</span>
              <h3>Performance</h3>
              <p>Für größere Teams mit mehr Tempo.</p>
              <p class="uk-text-large uk-text-bold">auf Anfrage</p>
              <ul class="uk-list uk-list-bullet uk-text-left">
                <li>Unbegrenzte Nutzer:innen</li>
                <li>Erweiterte Automatisierungen</li>
                <li>Priorisierter Support &amp; SLA</li>
              </ul>
              <a class="uk-button uk-button-default" href="#offer">Beratung anfragen</a>
            </div>
          </div>
          <div class="anim">
            <div class="uk-card uk-card-default uk-card-body uk-text-center uk-card-hover shadow-soft">
              <h3>Enterprise</h3>
              <p class="muted">Individuell zugeschnitten auf Ihre Prozesse.</p>
              <p class="uk-text-large uk-text-bold">individuell</p>
              <ul class="uk-list uk-list-bullet uk-text-left muted">
                <li>Eigene Mandanten-Strukturen</li>
                <li>On-Premise oder Hybrid</li>
                <li>Integrationen &amp; Projektbegleitung</li>
              </ul>
              <a class="uk-button uk-button-default" href="#offer">Angebot anfordern</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="faq" class="uk-section uk-section-muted">
      <div class="uk-container">
        <h2 class="uk-heading-line"><span>Häufige Fragen</span></h2>
        <ul data-uk-accordion>
          <li>
            <a class="uk-accordion-title" href="#">Wie schnell bin ich mit der Cloud-Version startklar?</a>
            <div class="uk-accordion-content">
              <p>In der Regel innerhalb weniger Tage – wir begleiten den Kick-off persönlich.</p>
            </div>
          </li>
          <li>
            <a class="uk-accordion-title" href="#">Kann ich zwischen Cloud und On-Premise wechseln?</a>
            <div class="uk-accordion-content">
              <p>Ja, ein Wechsel ist jederzeit möglich. Wir unterstützen bei Migration und Datenübernahme.</p>
            </div>
          </li>
          <li>
            <a class="uk-accordion-title" href="#">Welche Datenimporte sind möglich?</a>
            <div class="uk-accordion-content">
              <p>Excel/CSV-Importe, API-Schnittstellen sowie individuelle Integrationen.</p>
            </div>
          </li>
          <li>
            <a class="uk-accordion-title" href="#">Wie funktioniert der Support?</a>
            <div class="uk-accordion-content">
              <p>Support per E-Mail, Telefon oder Ticketsystem – je nach Paket sogar mit SLA.</p>
            </div>
          </li>
          <li>
            <a class="uk-accordion-title" href="#">Was passiert mit meinen Daten nach dem Test?</a>
            <div class="uk-accordion-content">
              <p>Nach Testende entscheiden Sie: weiter nutzen, exportieren oder löschen lassen – ganz transparent.</p>
            </div>
          </li>
        </ul>
        <div class="uk-margin-top">
          <span class="muted">Noch nicht fündig geworden?</span>
          <a class="uk-margin-small-left" href="#contact-form">Weitere Fragen → Kontakt</a>
        </div>
      </div>
    </section>

    <section id="demo" class="uk-section uk-section-primary uk-light calserver-highlight calserver-section-glow">
      <div class="uk-container">
        <div class="calserver-highlight__intro uk-text-center">
          <h2 class="uk-heading-small uk-margin-remove-bottom">Bereit, calServer live zu erleben?</h2>
          <p class="uk-text-lead uk-margin-small-top">Jetzt testen oder Demo buchen – wir zeigen, wie Ihre Prozesse in calServer aussehen.</p>
        </div>
        <div class="uk-grid-large uk-child-width-1-1 uk-child-width-1-2@m uk-grid-match"
             data-uk-grid
             data-uk-scrollspy="cls: uk-animation-slide-bottom-small; target: .anim; delay: 75; repeat: true">
          <div class="anim">
            <div class="uk-card uk-card-default uk-card-body uk-card-hover calserver-highlight-card">
              <div>
                <h3 class="uk-card-title">Live-Demo buchen</h3>
                <p class="muted">Wir führen Sie durch calServer, beantworten Fragen und zeigen passende Workflows.</p>
                <ul class="uk-list uk-list-bullet muted">
                  <li>Individuelle Präsentation mit Branchenfokus</li>
                  <li>Direkter Austausch mit unseren Expert:innen</li>
                  <li>Konkrete Empfehlungen für Ihren Einsatz</li>
                </ul>
              </div>
              <div class="calserver-highlight-card__actions">
                <a class="uk-button uk-button-primary"
                   href="https://calendly.com/calhelp/calserver-vorstellung"
                   target="_blank"
                   rel="noopener">Demo buchen</a>
                <span class="muted">ca. 45&nbsp;Minuten individuelles Gespräch</span>
              </div>
            </div>
          </div>
          <div class="anim">
            <div class="uk-card uk-card-default uk-card-body uk-card-hover calserver-highlight-card">
              <div>
                <h3 class="uk-card-title">Testzugang sichern</h3>
                <p class="muted">Starten Sie mit einer eigenen Umgebung und prüfen Sie calServer mit Ihren Prozessen.</p>
                <ul class="uk-list uk-list-bullet muted">
                  <li>Einrichtung nach kurzer Abstimmung</li>
                  <li>Best Practices für den schnellen Einstieg</li>
                  <li>Support-Team an Ihrer Seite</li>
                </ul>
              </div>
              <div class="calserver-highlight-card__actions">
                <a id="trial" class="uk-button uk-button-default" href="#offer">Jetzt testen</a>
                <span class="muted">Zugang &amp; Demo-Umgebung inklusive</span>
              </div>
            </div>
          </div>
        </div>
        <p class="muted uk-text-small uk-text-center uk-margin-medium-top">* Demo-Zugang und Testumgebung nach kurzer Abstimmung.</p>
      </div>
    </section>

    <section id="contact-us" class="uk-section">
      <div class="uk-container">
        <h2 class="uk-heading-medium uk-text-center" data-uk-scrollspy="cls: uk-animation-slide-top-small">Noch Fragen? Wir sind für Sie da.</h2>
        <p class="uk-text-lead uk-text-center" data-uk-scrollspy="cls: uk-animation-fade; delay: 150">Ob Testzugang, Angebot oder individuelle Beratung – wir melden uns garantiert persönlich zurück.</p>
        <div class="uk-grid uk-child-width-1-2@m uk-grid-large uk-flex-top" data-uk-grid data-uk-scrollspy="target: > div; cls: uk-animation-slide-right-small; delay: 150">
          <div>
                          <form id="contact-form" class="uk-form-stacked uk-width-large uk-margin-auto">
                <div class="uk-margin">
                  <label class="uk-form-label" for="form-name">Ihr Name</label>
                  <input class="uk-input" id="form-name" name="name" type="text" required>
                </div>
                <div class="uk-margin">
                  <label class="uk-form-label" for="form-email">E-Mail</label>
                  <input class="uk-input" id="form-email" name="email" type="email" required>
                </div>
                <div class="uk-margin">
                  <label class="uk-form-label" for="form-msg">Nachricht</label>
                  <textarea class="uk-textarea" id="form-msg" name="message" rows="5" required></textarea>
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
            <div class="uk-grid uk-child-width-1-1 uk-grid-small">
              <div><div class="uk-form-label" aria-hidden="true">&nbsp;</div></div>
              <div>
                <div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
                  <p class="uk-margin-small-bottom uk-text-large">E-Mail</p>
                  <a
                    class="uk-text-lead uk-link-reset js-email-link"
                    data-user="office"
                    data-domain="calhelp.de"
                    href="#"
                  >office [at] calhelp.de</a>
                </div>
              </div>
              <div>
                <div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
                  <p class="uk-margin-small-bottom uk-text-large">Telefon</p>
                  <a href="tel:+4933203609080" class="uk-text-lead uk-link-reset">+49 33203 609080</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div id="contact-modal" data-uk-modal>
      <div class="uk-modal-dialog uk-modal-body">
        <p id="contact-modal-message" aria-live="polite"></p>
        <button class="uk-button uk-button-primary uk-modal-close" type="button">OK</button>
      </div>
    </div>
'
);
