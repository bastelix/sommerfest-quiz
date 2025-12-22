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
    contact_email TEXT NULL,
    consent_granted_at TEXT NULL,
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
    startTheme TEXT,
    CheckAnswerButton TEXT,
    QRRestrict BOOLEAN,
    randomNames BOOLEAN DEFAULT TRUE,
    random_name_domains TEXT NOT NULL DEFAULT '[]',
    random_name_tones TEXT NOT NULL DEFAULT '[]',
    random_name_buffer INTEGER DEFAULT 0,
    random_name_locale TEXT,
    random_name_strategy TEXT DEFAULT 'ai',
    competitionMode BOOLEAN,
    teamResults BOOLEAN,
    photoUpload BOOLEAN,
    puzzleWordEnabled BOOLEAN,
    puzzleWord TEXT,
    puzzleFeedback TEXT,
    collectPlayerUid BOOLEAN,
    countdownEnabled BOOLEAN,
    countdown INTEGER,
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
    dashboard_share_token TEXT,
    dashboard_sponsor_token TEXT,
    dashboard_modules TEXT,
    dashboard_theme TEXT,
    dashboard_refresh_interval INTEGER,
    dashboard_fixed_height TEXT,
    dashboard_share_enabled BOOLEAN DEFAULT FALSE,
    dashboard_sponsor_enabled BOOLEAN DEFAULT FALSE,
    dashboard_info_text TEXT,
    dashboard_media_embed TEXT,
    dashboard_visibility_start TEXT,
    dashboard_visibility_end TEXT,
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
    smtp_host TEXT,
    smtp_user TEXT,
    smtp_pass TEXT,
    smtp_port INTEGER,
    smtp_encryption TEXT,
    smtp_dsn TEXT,
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

-- Prompt templates
CREATE TABLE IF NOT EXISTS prompt_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    prompt TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
INSERT OR IGNORE INTO prompt_templates(name, prompt) VALUES
('Standard', 'Erstelle deutsches UIkit-HTML für eine Marketing-Landingpage. Der HTML-Code wird in den Content-Block von
templates/marketing/landing.twig eingefügt (Header/Footer existieren). Orientiere dich am Landing-Layout:
- Verwende mehrere <section>-Blöcke mit uk-section und verschachtelten uk-container.
- Liefere eine Hero-Section mit Headline, Lead-Text und primärem Call-to-Action.
- Füge Sektionen mit den IDs innovations, how-it-works, scenarios, pricing, faq, contact-us hinzu (wenn relevant).
- Nutze uk-grid, uk-card, uk-list, uk-accordion und uk-button.
- Verwende ausschließlich UIkit-Klassen (Klassen beginnen mit "uk-").
- Nutze die Farb-Tokens. Mappe sie auf UIkit-Utilities oder CSS-Variablen auf einem Wrapper:
  Primary => --qr-landing-primary, Background => --qr-landing-bg, Accent => --qr-landing-accent.
- Schreibe prägnant, nutzenorientiert und auf Deutsch.
- Kein <html>, <head>, <body>, <script> oder <style>.
- Gib nur HTML zurück, kein Markdown.

Eingaben:
Slug: {{slug}}
Title: {{title}}
Theme: {{theme}}
Color scheme: {{colorScheme}}
Color tokens: Primary={{primaryColor}}, Background={{backgroundColor}}, Accent={{accentColor}}
Problem to address: {{problem}}'),
('Kompakt', 'Erstelle eine kompakte deutsche UIkit-Landingpage mit klaren Nutzenargumenten. Die HTML-Ausgabe wird in
templates/marketing/landing.twig eingesetzt. Anforderungen:
- Maximal 4 bis 5 <section>-Blöcke mit uk-section und uk-container.
- Eine kurze Hero-Section mit Headline, Subline und primärem Button.
- Eine Nutzenliste, eine Ablaufsektion und eine FAQ-Sektion (wenn relevant).
- Verwende nur UIkit-Klassen.
- Verwende die Farb-Tokens in einem Wrapper mit CSS-Variablen:
  Primary => --qr-landing-primary, Background => --qr-landing-bg, Accent => --qr-landing-accent.
- Texte kurz, prägnant, auf Deutsch.
- Keine <html>, <head>, <body>, <script> oder <style>.
- Ausgabe nur HTML.

Eingaben:
Slug: {{slug}}
Title: {{title}}
Theme: {{theme}}
Color scheme: {{colorScheme}}
Color tokens: Primary={{primaryColor}}, Background={{backgroundColor}}, Accent={{accentColor}}
Problem to address: {{problem}}'),
('Storytelling', 'Erstelle deutsches UIkit-HTML für eine Marketing-Landingpage, die eine kurze Story erzählt. Die HTML-Ausgabe
wird in templates/marketing/landing.twig eingefügt. Anforderungen:
- Nutze <section>-Blöcke mit uk-section und uk-container.
- Hero-Section mit Story-Hook, Lead-Text und primärem CTA.
- Eine "Warum"-Sektion, eine "So funktioniert es"-Sektion und eine "Ergebnisse"-Sektion (wenn relevant).
- Eine FAQ- oder Kontaktsektion als Abschluss.
- Verwende uk-grid, uk-card, uk-list, uk-accordion, uk-button.
- Verwende nur UIkit-Klassen ("uk-").
- Verwende die Farb-Tokens auf einem Wrapper:
  Primary => --qr-landing-primary, Background => --qr-landing-bg, Accent => --qr-landing-accent.
- Texte klar, benefit-orientiert, Deutsch.
- Keine <html>, <head>, <body>, <script> oder <style>.
- Ausgabe nur HTML.

Eingaben:
Slug: {{slug}}
Title: {{title}}
Theme: {{theme}}
Color scheme: {{colorScheme}}
Color tokens: Primary={{primaryColor}}, Background={{backgroundColor}}, Accent={{accentColor}}
Problem to address: {{problem}}');

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
    points INTEGER NOT NULL DEFAULT 0,
    answer_text TEXT,
    consent BOOLEAN,
    total INTEGER NOT NULL,
    max_points INTEGER NOT NULL DEFAULT 0,
    time INTEGER NOT NULL,
    started_at INTEGER,
    duration_sec INTEGER,
    puzzleTime INTEGER,
    photo TEXT,
    player_uid TEXT,
    expected_duration_sec INTEGER,
    duration_ratio REAL,
    event_uid TEXT,
    FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_results_catalog ON results(catalog);
CREATE INDEX IF NOT EXISTS idx_results_name ON results(name);
CREATE INDEX IF NOT EXISTS idx_results_event_player_uid ON results(event_uid, player_uid);

-- Question results
CREATE TABLE IF NOT EXISTS question_results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    catalog TEXT NOT NULL,
    question_id INTEGER NOT NULL,
    attempt INTEGER NOT NULL,
    correct INTEGER NOT NULL,
    points INTEGER NOT NULL DEFAULT 0,
    time_left_sec INTEGER,
    final_points INTEGER NOT NULL DEFAULT 0,
    efficiency REAL NOT NULL DEFAULT 0,
    is_correct INTEGER,
    scoring_version INTEGER NOT NULL DEFAULT 1,
    answer_text TEXT,
    photo TEXT,
    consent BOOLEAN,
    player_uid TEXT,
    event_uid TEXT,
    FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_qresults_catalog ON question_results(catalog);
CREATE INDEX IF NOT EXISTS idx_qresults_name ON question_results(name);
CREATE INDEX IF NOT EXISTS idx_qresults_question ON question_results(question_id);
CREATE INDEX IF NOT EXISTS idx_qresults_event_player_uid ON question_results(event_uid, player_uid);

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
    points INTEGER NOT NULL DEFAULT 1,
    options TEXT DEFAULT '{}',
    answers TEXT DEFAULT '[]',
    terms TEXT DEFAULT '{}',
    items TEXT DEFAULT '{}',
    cards TEXT DEFAULT '[]',
    right_label TEXT,
    left_label TEXT,
    countdown INTEGER,
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

CREATE TABLE IF NOT EXISTS user_namespaces (
    user_id INTEGER NOT NULL,
    namespace TEXT NOT NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, namespace),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_user_namespaces_user ON user_namespaces (user_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_user_namespaces_default
    ON user_namespaces (user_id)
    WHERE is_default;
INSERT OR IGNORE INTO user_namespaces (user_id, namespace, is_default)
SELECT id, 'default', TRUE
FROM users;

CREATE TABLE IF NOT EXISTS username_blocklist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    term TEXT NOT NULL,
    category TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT username_blocklist_category_check CHECK (
        category IN ('NSFW', '§86a/NS-Bezug', 'Beleidigung/Slur', 'Allgemein', 'Admin')
    )
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_username_blocklist_term_category
    ON username_blocklist(term, category);
CREATE INDEX IF NOT EXISTS idx_username_blocklist_category
    ON username_blocklist(category);

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
    onboarding_state TEXT NOT NULL DEFAULT 'pending',
    plan_started_at TEXT,
    plan_expires_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS namespace_profile (
    namespace TEXT PRIMARY KEY,
    imprint_name TEXT,
    imprint_street TEXT,
    imprint_zip TEXT,
    imprint_city TEXT,
    imprint_email TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS namespaces (
    namespace TEXT PRIMARY KEY,
    label TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
INSERT OR IGNORE INTO namespaces (namespace, is_active) VALUES ('default', 1);

CREATE TABLE IF NOT EXISTS project_settings (
    namespace TEXT PRIMARY KEY,
    cookie_consent_enabled INTEGER NOT NULL DEFAULT 0,
    cookie_storage_key TEXT,
    cookie_banner_text TEXT,
    cookie_banner_text_de TEXT,
    cookie_banner_text_en TEXT,
    cookie_vendor_flags TEXT,
    privacy_url TEXT,
    privacy_url_de TEXT,
    privacy_url_en TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
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
    namespace TEXT NOT NULL DEFAULT 'default',
    slug TEXT NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    type TEXT,
    parent_id INTEGER REFERENCES pages(id) ON DELETE SET NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    status TEXT,
    language TEXT,
    content_source TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(namespace, slug)
);

CREATE TABLE IF NOT EXISTS page_modules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    type TEXT NOT NULL,
    config TEXT,
    position TEXT NOT NULL,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_page_modules_page_position
    ON page_modules(page_id, position, id);

-- Landing page news
CREATE TABLE IF NOT EXISTS landing_news (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    slug TEXT NOT NULL,
    title TEXT NOT NULL,
    excerpt TEXT,
    content TEXT NOT NULL,
    published_at TEXT,
    is_published INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS marketing_newsletter_configs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    namespace TEXT NOT NULL DEFAULT 'default',
    slug TEXT NOT NULL,
    position INTEGER NOT NULL DEFAULT 0,
    label TEXT NOT NULL,
    url TEXT NOT NULL,
    style TEXT NOT NULL DEFAULT 'primary'
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_marketing_newsletter_configs_unique
    ON marketing_newsletter_configs(namespace, slug, position);

CREATE INDEX IF NOT EXISTS idx_marketing_newsletter_configs_slug
    ON marketing_newsletter_configs(namespace, slug);

INSERT INTO marketing_newsletter_configs (namespace, slug, position, label, url, style) VALUES
    ('default', 'landing', 0, 'QuizRace entdecken', '/landing', 'primary'),
    ('default', 'landing', 1, 'Kontakt aufnehmen', '/landing#contact-us', 'secondary'),
    ('default', 'calserver', 0, 'Mehr über calServer', '/calserver', 'primary'),
    ('default', 'calserver', 1, 'Demo anfragen', '/calserver#contact', 'secondary'),
    ('default', 'calserver-maintenance', 0, 'Service anfragen', '/calserver-maintenance', 'primary'),
    ('default', 'calserver-maintenance', 1, 'Direkt Kontakt aufnehmen', '/calserver-maintenance#contact', 'secondary'),
    ('default', 'calserver-accessibility', 0, 'Barrierefreiheit entdecken', '/calserver-accessibility', 'primary'),
    ('default', 'calserver-accessibility', 1, 'Beratung vereinbaren', '/calserver#contact', 'secondary'),
    ('default', 'calhelp', 0, 'CALhelp kennenlernen', '/calhelp', 'primary'),
    ('default', 'calhelp', 1, 'Beratung anfordern', '/calhelp#contact-info', 'secondary'),
    ('default', 'future-is-green', 0, 'Future is Green entdecken', '/future-is-green', 'primary'),
    ('default', 'future-is-green', 1, 'Kontakt aufnehmen', '#contact', 'secondary'),
    ('default', 'fluke-metcal', 0, 'Met/Track kennenlernen', '/fluke-metcal', 'primary'),
    ('default', 'fluke-metcal', 1, 'Kontakt aufnehmen', '/calserver#contact-us', 'secondary')
ON CONFLICT(namespace, slug, position) DO UPDATE SET
    label = excluded.label,
    url = excluded.url,
    style = excluded.style;

CREATE TABLE IF NOT EXISTS marketing_page_menu_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    namespace TEXT NOT NULL DEFAULT 'default',
    label TEXT NOT NULL,
    href TEXT NOT NULL,
    icon TEXT,
    position INTEGER NOT NULL DEFAULT 0,
    is_external INTEGER NOT NULL DEFAULT 0,
    locale TEXT NOT NULL DEFAULT 'de',
    is_active INTEGER NOT NULL DEFAULT 1,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS marketing_page_menu_items_page_locale_idx
    ON marketing_page_menu_items(page_id, namespace, locale, position, id);

-- Marketing page wiki
CREATE TABLE IF NOT EXISTS marketing_page_wiki_settings (
    page_id INTEGER PRIMARY KEY,
    is_active INTEGER NOT NULL DEFAULT 0,
    menu_label TEXT,
    menu_labels TEXT NOT NULL DEFAULT '{}',
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS marketing_page_wiki_articles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL,
    slug TEXT NOT NULL,
    locale TEXT NOT NULL DEFAULT 'de',
    title TEXT NOT NULL,
    excerpt TEXT,
    editor_json TEXT,
    content_md TEXT NOT NULL,
    content_html TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    sort_index INTEGER NOT NULL DEFAULT 0,
    published_at TEXT,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(page_id, locale, slug)
);

CREATE INDEX IF NOT EXISTS marketing_page_wiki_articles_page_locale_status_idx
    ON marketing_page_wiki_articles(page_id, locale, status, sort_index, published_at);

CREATE TABLE IF NOT EXISTS marketing_page_wiki_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL,
    editor_json TEXT,
    content_md TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    created_by TEXT,
    FOREIGN KEY (article_id) REFERENCES marketing_page_wiki_articles(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS marketing_page_wiki_versions_article_idx
    ON marketing_page_wiki_versions(article_id, created_at);

CREATE TABLE IF NOT EXISTS domain_chat_wiki_articles (
    domain TEXT NOT NULL,
    article_id INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(domain, article_id),
    FOREIGN KEY (article_id) REFERENCES marketing_page_wiki_articles(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS domain_chat_wiki_articles_domain_idx
    ON domain_chat_wiki_articles(domain);

CREATE UNIQUE INDEX IF NOT EXISTS landing_news_page_slug_idx
    ON landing_news(page_id, slug);
CREATE INDEX IF NOT EXISTS landing_news_page_published_idx
    ON landing_news(page_id, is_published, published_at DESC, id DESC);
CREATE INDEX IF NOT EXISTS landing_news_published_idx
    ON landing_news(is_published, published_at DESC, id DESC);

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

INSERT OR IGNORE INTO pages (namespace, slug, title, content) VALUES (
    'default',
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

    <section id="metcal" class="uk-section uk-section-large uk-section-primary uk-light calserver-metcal" aria-labelledby="metcal-heading">
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
                <li>Endlich korrekte Anzeigen der erweiterten Messunsicherheit durch inteligente Feldformeln in der Software</li>
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
    </section>

    <section id="features"
 class="uk-section uk-section-default uk-section-large">
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
                    <video class="calserver-module-figure__video"
                           width="1200"
                           height="675"
                           autoplay
                           muted
                           loop
                           playsinline
                           preload="auto"
                           poster="{{ basePath }}/uploads/calserver-module-device-management.webp"
                           aria-label="Screenshot der calServer-Geräteverwaltung mit Geräteakte, Historie und Messwerten">
                      <source src="{{ basePath }}/uploads/calserver-module-device-management.mp4" type="video/mp4">
                      Ihr Browser unterstützt keine HTML5-Videos.
                      <a href="{{ basePath }}/uploads/calserver-module-device-management.mp4" target="_blank" rel="noopener">
                        Video herunterladen
                      </a>.
                    </video>
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
                    <video class="calserver-module-figure__video"
                           width="1200"
                           height="675"
                           autoplay
                           muted
                           loop
                           playsinline
                           preload="auto"
                           poster="{{ basePath }}/uploads/calserver-module-calendar-resources.webp"
                           aria-label="Screenshot des calServer-Kalenders mit Ressourcen- und Terminplanung">
                      <source src="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4" type="video/mp4">
                      Ihr Browser unterstützt keine HTML5-Videos.
                      <a href="{{ basePath }}/uploads/calserver-module-calendar-resources.mp4" target="_blank" rel="noopener">
                        Video herunterladen
                      </a>.
                    </video>
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
                    <video class="calserver-module-figure__video"
                           width="1200"
                           height="675"
                           autoplay
                           muted
                           loop
                           playsinline
                           preload="auto"
                           poster="{{ basePath }}/uploads/calserver-module-order-ticketing.webp"
                           aria-label="Screenshot der calServer-Auftrags- und Ticketverwaltung mit Workflow-Status">
                      <source src="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4" type="video/mp4">
                      Ihr Browser unterstützt keine HTML5-Videos.
                      <a href="{{ basePath }}/uploads/calserver-module-order-ticketing.mp4" target="_blank" rel="noopener">
                        Video herunterladen
                      </a>.
                    </video>
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
                    <video class="calserver-module-figure__video"
                           width="1200"
                           height="675"
                           autoplay
                           muted
                           loop
                           playsinline
                           preload="auto"
                           poster="{{ basePath }}/uploads/calserver-module-self-service.webp"
                           aria-label="Screenshot des calServer-Self-Service-Portals mit Kundenansicht und Zertifikaten">
                      <source src="{{ basePath }}/uploads/calserver-module-self-service.mp4" type="video/mp4">
                      Ihr Browser unterstützt keine HTML5-Videos.
                      <a href="{{ basePath }}/uploads/calserver-module-self-service.mp4" target="_blank" rel="noopener">
                        Video herunterladen
                      </a>.
                    </video>
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
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-top" data-uk-grid>
              <div class="usecase-story uk-first-column uk-scrollspy-inview">
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
                  <a class="uk-button uk-button-text uk-margin-top"
                    href="{{ basePath }}/uploads/calserver-usecase-ifm-highlights.pdf"
                    target="_blank"
                    rel="noopener">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card usecase-card--visual uk-margin-top">
                  <div class="usecase-visual">
                    <img class="uk-border-rounded usecase-visual__image"
                       src="{{ basePath }}/uploads/calserver-usecase-ifm-ticketmanagement.webp"
                       width="960"
                       height="540"
                       loading="lazy"
                       decoding="async"
                       alt="Screenshot des calServer-Ticketboards im ifm-Use-Case mit CAPA-Bewertung">
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-top" data-uk-grid>
              <div class="usecase-story uk-first-column uk-scrollspy-inview">
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
                  <a class="uk-button uk-button-text uk-margin-top"
                    href="{{ basePath }}/uploads/calserver-usecase-ksw-highlights.pdf"
                    target="_blank"
                    rel="noopener">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card usecase-card--visual uk-margin-top">
                  <div class="usecase-visual">
                    <img class="uk-border-rounded usecase-visual__image"
                       src="{{ basePath }}/uploads/calserver-usecase-ksw-prozesskette.webp"
                       width="960"
                       height="540"
                       loading="lazy"
                       decoding="async"
                       alt="Screenshot des calServer-Prozessflusses von Wareneingang bis Abrechnung im KSW-Use-Case">
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-top" data-uk-grid>
              <div class="usecase-story uk-first-column uk-scrollspy-inview">
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
                  <a class="uk-button uk-button-text uk-margin-top"
                    href="{{ basePath }}/uploads/calserver-usecase-systems-highlights.pdf"
                    target="_blank"
                    rel="noopener">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card usecase-card--visual uk-margin-top">
                  <div class="usecase-visual">
                    <img class="uk-border-rounded usecase-visual__image"
                       src="{{ basePath }}/uploads/calserver-usecase-systems-reporting.webp"
                       width="960"
                       height="540"
                       loading="lazy"
                       decoding="async"
                       alt="Screenshot der calServer-Auftragssteuerung und Reporting-Widgets im Systems-Engineering-Use-Case">
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-top" data-uk-grid>
              <div class="usecase-story uk-first-column uk-scrollspy-inview">
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
                  <a class="uk-button uk-button-text uk-margin-top"
                    href="{{ basePath }}/uploads/calserver-usecase-teramess-highlights.pdf"
                    target="_blank"
                    rel="noopener">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card usecase-card--visual uk-margin-top">
                  <div class="usecase-visual">
                    <img class="uk-border-rounded usecase-visual__image"
                       src="{{ basePath }}/uploads/calserver-usecase-teramess-dakks.webp"
                       width="960"
                       height="540"
                       loading="lazy"
                       decoding="async"
                       alt="Screenshot der calServer-Zertifikatsverwaltung mit DAkkS-Historie im TERAMESS-Use-Case">
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-top" data-uk-grid>
              <div class="usecase-story uk-first-column uk-scrollspy-inview">
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
                  <a class="uk-button uk-button-text uk-margin-top"
                    href="{{ basePath }}/uploads/calserver-usecase-thermo-highlights.pdf"
                    target="_blank"
                    rel="noopener">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card usecase-card--visual uk-margin-top">
                  <div class="usecase-visual">
                    <img class="uk-border-rounded usecase-visual__image"
                       src="{{ basePath }}/uploads/calserver-usecase-thermo-leihverwaltung.webp"
                       width="960"
                       height="540"
                       loading="lazy"
                       decoding="async"
                       alt="Screenshot der calServer-Leihgeräte- und Geräteaktenübersicht im Thermo-Fisher-Use-Case">
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-top" data-uk-grid>
              <div class="usecase-story uk-first-column uk-scrollspy-inview">
                <span class="pill pill--badge uk-margin-small-bottom">Industrielabor</span>
                <h3 class="uk-h2">ZF – API-Messwerte auf Kubernetes mit SSO</h3>
                <p class="uk-text-lead">calServer verbindet skalierbare Messwert-APIs auf Kubernetes mit SSO und Geräteakten-Management.</p>
                <p>Messwerte fließen über Microservices automatisiert ein; Geräte, Zertifikate und Auswertungen bleiben im Zugriff der berechtigten Teams. Single Sign-On vereinfacht den Zugang, die bidirektionale Synchronisation mit Fluke MET/TEAM und MET/CAL stellt durchgehend konsistente Kalibrierdaten sicher.</p>
                <ul class="uk-list uk-list-bullet muted">
                  <li>API-Ingestion von Messwerten (Microservices/Kubernetes)</li>
                  <li>SSO (EntraID/Active Directory) für nahtlosen Zugriff</li>
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
                  <a class="uk-button uk-button-text uk-margin-top"
                    href="{{ basePath }}/uploads/calserver-usecase-zf-highlights.pdf"
                    target="_blank"
                    rel="noopener">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card usecase-card--visual uk-margin-top">
                  <div class="usecase-visual">
                    <img class="uk-border-rounded usecase-visual__image"
                       src="{{ basePath }}/uploads/calserver-usecase-zf-messwerte-sso.webp"
                       width="960"
                       height="540"
                       loading="lazy"
                       decoding="async"
                       alt="Screenshot der calServer-Messwert-APIs und SSO-Konfiguration im ZF-Use-Case">
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-top" data-uk-grid>
              <div class="usecase-story uk-first-column uk-scrollspy-inview">
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
                  <a class="uk-button uk-button-text uk-margin-top"
                    href="{{ basePath }}/uploads/calserver-usecase-berlin-highlights.pdf"
                    target="_blank"
                    rel="noopener">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card usecase-card--visual uk-margin-top">
                  <div class="usecase-visual">
                    <img class="uk-border-rounded usecase-visual__image"
                       src="{{ basePath }}/uploads/calserver-usecase-berlin-wartung.webp"
                       width="960"
                       height="540"
                       loading="lazy"
                       decoding="async"
                       alt="Screenshot der calServer-Wartungs- und Projektplanung für Berliner Stadtwerke">
                  </div>
                </div>
              </div>
            </div>
          </li>
          <li>
            <div class="uk-grid-large uk-child-width-1-2@m uk-flex-top" data-uk-grid>
              <div class="usecase-story uk-first-column uk-scrollspy-inview">
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
                  <a class="uk-button uk-button-text uk-margin-top"
                    href="{{ basePath }}/uploads/calserver-usecase-vde-highlights.pdf"
                    target="_blank"
                    rel="noopener">
                    <span class="uk-margin-small-right" data-uk-icon="icon: file-pdf"></span>HIGHLIGHTS ALS PDF
                  </a>
                </div>
                <div class="uk-card uk-card-default uk-card-body uk-card-hover usecase-card usecase-card--visual uk-margin-top">
                  <div class="usecase-visual">
                    <img class="uk-border-rounded usecase-visual__image"
                       src="{{ basePath }}/uploads/calserver-usecase-vde-auftragsboard.webp"
                       width="960"
                       height="540"
                       loading="lazy"
                       decoding="async"
                       alt="Screenshot des calServer-Auftragsboards mit DMS-Integration im VDE-Use-Case">
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
        <p class="muted uk-margin-small-top">Wählen Sie, wie calServer betrieben wird: als sichere Cloud-Lösung oder in Ihrer eigenen Umgebung.</p>
        <ul class="uk-subnav uk-subnav-pill uk-margin" data-uk-switcher>
          <li><a href="#">Cloud</a></li>
          <li><a href="#">On-Premise</a></li>
        </ul>
        <ul class="uk-switcher uk-margin"
            data-uk-scrollspy="cls: uk-animation-slide-bottom-small; target: .anim; delay: 75; repeat: true">
          <li>
            <p class="uk-text-lead uk-margin-remove-top uk-margin-medium-bottom">Die Cloud-Variante betreiben wir vollständig für Sie: Updates, Monitoring und Sicherheit bleiben bei uns, damit Ihr Team sich sofort auf die Arbeit mit calServer konzentrieren kann.</p>
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
            <p class="uk-text-lead uk-margin-remove-top uk-margin-medium-bottom">Mit der On-Premise-Variante läuft calServer in Ihrer Infrastruktur: Sie behalten volle Datenhoheit, wir begleiten Installation, Updates und binden bestehende Systeme nahtlos an.</p>
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
        <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap calserver-pricing__header">
          <h2 class="uk-heading-line"><span>Abomodelle</span></h2>
          <span class="muted">Monatliche Laufzeit, transparente Bedingungen, DSGVO-konform.</span>
        </div>
        <div class="uk-child-width-1-1 uk-child-width-1-3@m uk-grid-large uk-grid-match"
             data-uk-grid
             data-uk-scrollspy="cls: uk-animation-slide-bottom-small; target: .anim; delay: 75; repeat: true">
          <div class="anim">
            <div class="uk-card uk-card-default uk-card-body uk-text-center uk-card-hover shadow-soft calserver-pricing-card">
              <div class="uk-position-relative">
                <span class="pill pill--badge uk-position-absolute uk-position-top-right uk-margin-small">Cloud in DE</span>
                <h3>Standard-Hosting</h3>
              </div>
              <p class="muted">Für Teams, die schnell und zuverlässig starten wollen.</p>
              <ul class="uk-list uk-list-bullet uk-text-left muted">
                <li>Inventar-, Kalibrier- &amp; Auftragsverwaltung</li>
                <li>Dokumentenmanagement (Basis-Kontingent)</li>
                <li>Tägliche Backups, SSL &amp; Subdomain</li>
                <li>Basis-Updateservice (Security &amp; regelmäßige Features)</li>
                <li>Rollen &amp; Berechtigungen, Audit-fähige Historie</li>
              </ul>
              <div class="uk-margin-top uk-text-small muted">
                <p class="uk-margin-remove">Monatliche Abrechnung · Kündigungsfrist 30 Tage</p>
                <p class="uk-margin-remove">Erweiterungen (z. B. Speicher, SSO) zubuchbar</p>
              </div>
              <div class="uk-margin-top">
                <a class="uk-button uk-button-primary uk-width-1-1" href="#contact-form">Anfrage senden</a>
                <button class="uk-button uk-button-text uk-width-1-1 uk-margin-small-top" type="button" data-uk-toggle="target: #modal-standard-hosting">Leistungsdetails</button>
              </div>
            </div>
          </div>
          <div class="anim">
            <div class="uk-card uk-card-primary uk-card-primary--highlight uk-card-body uk-text-center uk-card-hover shadow-soft uk-position-relative calserver-pricing-card">
              <span class="pill pill--badge uk-position-absolute uk-position-top-right uk-margin-small">Beliebt</span>
              <h3>Performance-Hosting</h3>
              <p>Mehr Leistung und Spielraum für wachsende Anforderungen.</p>
              <ul class="uk-list uk-list-bullet uk-text-left">
                <li>Erhöhte Performance &amp; skalierbare Ressourcen</li>
                <li>Mehr Speicher, keine Moduleinschränkungen</li>
                <li>Priorisiertes Monitoring &amp; Stabilität</li>
                <li>Tägliche Backups, SSL, Subdomain</li>
                <li>Rollen &amp; Berechtigungen, Team-Workflows</li>
              </ul>
              <div class="uk-margin-top uk-text-small">
                <p class="uk-margin-remove">Monatliche Abrechnung · Kündigungsfrist 30 Tage</p>
                <p class="uk-margin-remove">Upgrade/Downgrade zwischen Plänen möglich</p>
              </div>
              <div class="uk-margin-top">
                <a class="uk-button uk-button-primary uk-width-1-1" href="#contact-form">Anfrage senden</a>
                <button class="uk-button uk-button-text uk-width-1-1 uk-margin-small-top" type="button" data-uk-toggle="target: #modal-performance-hosting">Leistungsdetails</button>
              </div>
            </div>
          </div>
          <div class="anim">
            <div class="uk-card uk-card-default uk-card-body uk-text-center uk-card-hover shadow-soft calserver-pricing-card">
              <div class="uk-position-relative">
                <span class="pill pill--badge uk-position-absolute uk-position-top-right uk-margin-small">Max. Kontrolle</span>
                <h3>Enterprise (On-Prem)</h3>
              </div>
              <p class="muted">Volle Datenhoheit und individuelle Compliance.</p>
              <ul class="uk-list uk-list-bullet uk-text-left muted">
                <li>On-Prem-Betrieb in Ihrer Infrastruktur</li>
                <li>SSO (Azure/Google), erweiterte Integrationen</li>
                <li>Erweiterte Compliance &amp; individuelle SLAs</li>
                <li>Optionale Synchronisationen (z. B. METBASE/METTEAM)</li>
                <li>Change-/Release-Management nach Vorgabe</li>
              </ul>
              <div class="uk-margin-top uk-text-small muted">
                <p class="uk-margin-remove">Monatliche Abrechnung · Kündigungsfrist 30 Tage</p>
                <p class="uk-margin-remove">Rollout &amp; Betrieb nach gemeinsamem Migrationsplan</p>
              </div>
              <div class="uk-margin-top">
                <a class="uk-button uk-button-primary uk-width-1-1" href="#contact-form">Anfrage senden</a>
                <button class="uk-button uk-button-text uk-width-1-1 uk-margin-small-top" type="button" data-uk-toggle="target: #modal-enterprise-hosting">Leistungsdetails</button>
              </div>
            </div>
          </div>
        </div>
        <p class="muted uk-text-small uk-margin-top">Vollständige AGB, SLA und AVV auf Anfrage oder im Kundenportal einsehbar.</p>
      </div>
    </section>

    <div id="modal-standard-hosting" data-uk-modal>
      <div class="uk-modal-dialog uk-modal-body">
        <h2 class="uk-modal-title">Standard-Hosting – Leistungsdetails</h2>
        <ul class="uk-list uk-list-bullet">
          <li>Funktionsumfang: Inventar, Kalibrierung, Aufträge, DMS (Basis)</li>
          <li>Backups täglich, Datenstandort: Deutschland</li>
          <li>Updates: Sicherheitsfixes laufend, Feature-Releases regelmäßig</li>
          <li>Optional: Speichererweiterung, SSO-Anbindung, Integrationen</li>
        </ul>
        <button class="uk-button uk-button-primary uk-modal-close" type="button">Schließen</button>
      </div>
    </div>

    <div id="modal-performance-hosting" data-uk-modal>
      <div class="uk-modal-dialog uk-modal-body">
        <h2 class="uk-modal-title">Performance-Hosting – Leistungsdetails</h2>
        <ul class="uk-list uk-list-bullet">
          <li>Skalierbare CPU/RAM-Kontingente &amp; priorisierte Ressourcen</li>
          <li>Großzügige Speicheroptionen, keine Modullimits</li>
          <li>Optional: SSO (Azure/Google), Integrationen (z. B. ERP/CRM)</li>
          <li>Monitoring &amp; Benachrichtigungen nach Best-Practice</li>
        </ul>
        <button class="uk-button uk-button-primary uk-modal-close" type="button">Schließen</button>
      </div>
    </div>

    <div id="modal-enterprise-hosting" data-uk-modal>
      <div class="uk-modal-dialog uk-modal-body">
        <h2 class="uk-modal-title">Enterprise (On-Prem) – Leistungsdetails</h2>
        <ul class="uk-list uk-list-bullet">
          <li>Einrichtung, Betrieb &amp; Wartung gemäß Projektvertrag</li>
          <li>Individuelle SLAs/AVV, Compliance nach Ihren Vorgaben</li>
          <li>Integration in vorhandene IdPs &amp; Systeme</li>
          <li>Optional: Managed-Service, erweiterte Überwachung, Reporting</li>
        </ul>
        <button class="uk-button uk-button-primary uk-modal-close" type="button">Schließen</button>
      </div>
    </div>

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
                <a id="trial" class="uk-button uk-button-default" href="#contact-form">Jetzt testen</a>
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
                          <form id="contact-form"
                                class="uk-form-stacked uk-width-large uk-margin-auto"
                                data-contact-endpoint="{{ basePath }}/calserver/contact">
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
INSERT OR REPLACE INTO pages (slug, title, content)
VALUES (
    'fluke-metcal',
    'FLUKE MET/CAL Integration',
    $$<!--
kb_id: metcal-integration-v1
kb_version: 1.3
kb_tags: ["MET/CAL","MET/TRACK","METTEAM","Migration","Zertifikat","Guardband","DAkkS"]
kb_synonyms: ["METCAL","MET TRACK","MET/TRACK","MET TEAM","METTEAM","Guardband","Reports"]
updated_at: 2025-05-20
summary: Diese Seite zeigt Migration von MET/TRACK nach calServer, Hybridbetrieb mit METTEAM sowie Reporting & Guardband – ohne Paket-Section auf der Landing Page.
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

<section id="metteam" class="uk-section uk-section-large uk-section-primary uk-light calserver-metcal metcal-section metcal-metteam" aria-labelledby="metcal-metteam-heading">
  <div class="calserver-metcal__inner">
    <div class="uk-container">
      <div class="metcal-metteam__header">
        <span class="calserver-metcal__eyebrow">FLUKE MET/CAL · MET/TRACK</span>
        <h2 class="calserver-metcal__title" id="metcal-metteam-heading">Ein System. Klare Prozesse.</h2>
        <p class="calserver-metcal__lead">Bidirektionale Synchronisation, klare Eigentümerschaften und vollständige Protokollierung halten MET/CAL, METTEAM und calServer im Gleichklang.</p>
      </div>
      <div class="calserver-metcal__grid metcal-metteam__grid" role="list">
        <article class="calserver-metcal__card" role="listitem">
          <h3>
            <span class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: settings"></span>
            Sync-Regeln pro Feld
          </h3>
          <p>Feldweise Festlegung, wer führend ist (MET/CAL, METTEAM oder calServer). Konflikte werden als Review-Aufgabe markiert.</p>
        </article>
        <article class="calserver-metcal__card calserver-metcal__card--dark" role="listitem">
          <h3>
            <span class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: history"></span>
            Journal statt Doppelpflege
          </h3>
          <p>Änderungen werden versioniert, inklusive Delta-Listen, Autor:in und Zeitstempel – nachvollziehbar für Audits.</p>
        </article>
        <article class="calserver-metcal__card calserver-metcal__card--outline" role="listitem">
          <h3>
            <span class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: bolt"></span>
            Aktivierung pro Gerät
          </h3>
          <p>Hybridmodus per Toggle aktivieren: vorhandene Daten werden direkt übernommen, Beenden inklusive Aufräumdialog.</p>
        </article>
      </div>
      <p class="metcal-metteam__note calserver-metcal__note"><strong>METTEAM bleibt produktiv.</strong> Aufträge laufen weiter – calServer sorgt für Überblick, Freigabe und Nachweise.</p>
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
);


INSERT OR IGNORE INTO pages (namespace, slug, title, content) VALUES (
    'default',
    'calhelp',
    'calHelp',
    '
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
      <div class="calhelp-process__stages" data-calhelp-slider>
        <div class="calhelp-process__track" data-calhelp-slider-track>
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

<script type="application/json" data-calhelp-usecases>
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
<div data-calhelp-usecases></div>

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
    <div data-calhelp-assurance></div>
    <div data-calhelp-proof-gallery></div>
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
            <a class="uk-button uk-button-default uk-width-1-1" href="#help">Lage klären</a>
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
'
);

INSERT OR IGNORE INTO pages (namespace, slug, title, content) VALUES (
    'default',
    'calserver-accessibility',
    'calServer Accessibility',
    '<section class="legal-page" aria-labelledby="calserver-accessibility-title">
  <div class="uk-section uk-section-default">
    <div class="uk-container uk-container-small">
      <h1 id="calserver-accessibility-title">Accessibility statement for calServer</h1>
      <p>This accessibility statement applies to the calServer marketing website operated by René Buske (calhelp.de). It covers the content delivered at <a href="https://calhelp.de/calserver" rel="noopener">https://calhelp.de/calserver</a> and associated subpages.</p>
      <p>We strive to comply with the Web Content Accessibility Guidelines (WCAG) 2.1 at level AA. Based on an internal self-evaluation performed on 10 March 2025 we consider the site to be partially compliant.</p>
      <h2 id="non-accessible-content">Non-accessible content</h2>
      <p>The following content and functions are not fully accessible. Each item references the affected WCAG 2.1 success criteria.</p>
      <ul>
        <li><strong>Looping module preview videos</strong> – The autoplaying module videos on the landing page do not provide controls to pause or stop the animation and lack audio descriptions or transcripts. This violates success criteria 1.2.5 (Audio Description – Prerecorded) and 2.2.2 (Pause, Stop, Hide). We are preparing captioned recordings with accessible controls for Q2 2025.</li>
        <li><strong>Animated client logo marquee</strong> – The continuously moving client logo marquee cannot yet be paused and does not respect the prefers-reduced-motion setting. This violates success criteria 2.2.2 (Pause, Stop, Hide) and 2.3.3 (Animation from Interactions). We will add a pause button and motion preference detection in an upcoming release.</li>
        <li><strong>Downloadable PDF case studies</strong> – The linked PDF summaries of customer projects have not yet been tagged for assistive technologies and may contain missing alternate text. This violates success criteria 1.1.1 (Non-text Content) and 1.3.1 (Info and Relationships). Tagged replacements are scheduled for Q3 2025.</li>
      </ul>
      <h2 id="third-party-content">Third-party content</h2>
      <p>The scheduling links to Calendly and the Cloudflare Turnstile widget used in the contact form are third-party services. We have no full control over their accessibility but relay encountered issues to the providers.</p>
      <h2 id="feedback">Feedback and contact</h2>
      <p>If you discover barriers that prevent you from using calServer, please let us know. We respond within five working days.</p>
      <p>
        Email: <a class="js-email-link" data-user="office" data-domain="calhelp.de" href="#">office [at] calhelp.de</a><br>
        Phone: <a href="tel:+4933203609080">+49&nbsp;33203&nbsp;609080</a>
      </p>
      <h2 id="enforcement">Enforcement procedure</h2>
      <p>If you do not receive a satisfactory response to your feedback you can contact the Commissioner for Digital Accessibility of the State of Brandenburg (Ministry of Social Affairs, Health, Integration and Consumer Protection). Further information is available at <a href="https://msgiv.brandenburg.de/msgiv/de/themen/teilhabe-und-inklusion/barrierefreiheit-in-der-informationstechnik/" target="_blank" rel="noopener">msgiv.brandenburg.de</a>.</p>
      <h2 id="statement-date">Preparation of this statement</h2>
      <p>This statement was created on 10 March 2025 based on self-evaluation, manual audits with assistive technologies and automated tests (axe-core, WAVE).</p>
    </div>
  </div>
</section>'
);

INSERT OR IGNORE INTO pages (namespace, slug, title, content) VALUES (
    'default',
    'calserver-accessibility-en',
    'calServer Accessibility',
    '<section class="legal-page" aria-labelledby="calserver-accessibility-title">
  <div class="uk-section uk-section-default">
    <div class="uk-container uk-container-small">
      <h1 id="calserver-accessibility-title">Accessibility statement for calServer</h1>
      <p>This is the English reference version of the calServer accessibility statement. It applies to the marketing site operated by René Buske (calhelp.de) and mirrors the German declaration.</p>
      <p>The website aims to comply with WCAG 2.1 level AA and is currently assessed as partially compliant (self-evaluation on 10 March 2025).</p>
      <h2 id="non-accessible-content">Non-accessible content</h2>
      <ul>
        <li><strong>Looping module preview videos</strong> – No player controls, captions or audio descriptions are available yet. Affects WCAG 1.2.5 and 2.2.2. Fix planned for Q2 2025.</li>
        <li><strong>Animated client logo marquee</strong> – Motion cannot be paused and ignores system motion preferences. Affects WCAG 2.2.2 and 2.3.3. Fix planned for Q2 2025.</li>
        <li><strong>Downloadable PDF case studies</strong> – Missing tagging and alternate text in PDFs. Affects WCAG 1.1.1 and 1.3.1. Fix planned for Q3 2025.</li>
      </ul>
      <h2 id="third-party-content">Third-party content</h2>
      <p>Calendly scheduling links and the Cloudflare Turnstile CAPTCHA are external services outside our direct control.</p>
      <h2 id="feedback">Feedback and contact</h2>
      <p>Contact us at <a class="js-email-link" data-user="office" data-domain="calhelp.de" href="#">office [at] calhelp.de</a> or call <a href="tel:+4933203609080">+49&nbsp;33203&nbsp;609080</a>.</p>
      <h2 id="enforcement">Enforcement procedure</h2>
      <p>Complaints can be escalated to the Commissioner for Digital Accessibility of the State of Brandenburg (Ministry of Social Affairs, Health, Integration and Consumer Protection): <a href="https://msgiv.brandenburg.de/msgiv/de/themen/teilhabe-und-inklusion/barrierefreiheit-in-der-informationstechnik/" target="_blank" rel="noopener">msgiv.brandenburg.de</a>.</p>
      <h2 id="statement-date">Preparation of this statement</h2>
      <p>Last review: 10 March 2025.</p>
    </div>
  </div>
</section>'
);
