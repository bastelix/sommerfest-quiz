-- Combined base schema for Sommerfest Quiz
-- Generated to replace individual migrations

-- Configuration settings
CREATE TABLE IF NOT EXISTS config (
    id SERIAL PRIMARY KEY,
    displayErrorDetails BOOLEAN,
    QRUser BOOLEAN,
    logoPath TEXT,
    pageTitle TEXT,
    backgroundColor TEXT,
    buttonColor TEXT,
    CheckAnswerButton TEXT,
    QRRestrict BOOLEAN,
    competitionMode BOOLEAN,
    teamResults BOOLEAN,
    photoUpload BOOLEAN,
    puzzleWordEnabled BOOLEAN,
    puzzleWord TEXT,
    puzzleFeedback TEXT,
    inviteText TEXT,
    qrremember BOOLEAN DEFAULT FALSE,
    event_uid TEXT REFERENCES events(uid) ON DELETE CASCADE
);

-- Event definitions
CREATE TABLE IF NOT EXISTS events (
    uid TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    start_date TEXT DEFAULT CURRENT_TIMESTAMP,
    end_date TEXT DEFAULT CURRENT_TIMESTAMP,
    description TEXT
);

-- Teams list
CREATE TABLE IF NOT EXISTS teams (
    sort_order INTEGER UNIQUE NOT NULL,
    name TEXT NOT NULL,
    uid TEXT PRIMARY KEY,
    event_uid TEXT REFERENCES events(uid) ON DELETE CASCADE
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_team_name ON teams(name);

-- Quiz results
CREATE TABLE IF NOT EXISTS results (
    id SERIAL PRIMARY KEY,
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
    event_uid TEXT REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_results_catalog ON results(catalog);
CREATE INDEX IF NOT EXISTS idx_results_name ON results(name);

-- Per-question answer log
CREATE TABLE IF NOT EXISTS question_results (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    catalog TEXT NOT NULL,
    question_id INTEGER NOT NULL,
    attempt INTEGER NOT NULL,
    correct INTEGER NOT NULL,
    answer_text TEXT,
    photo TEXT,
    consent BOOLEAN,
    event_uid TEXT REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_qresults_catalog ON question_results(catalog);
CREATE INDEX IF NOT EXISTS idx_qresults_name ON question_results(name);
CREATE INDEX IF NOT EXISTS idx_qresults_question ON question_results(question_id);

-- Catalog definitions
CREATE TABLE IF NOT EXISTS catalogs (
    uid TEXT PRIMARY KEY,
    sort_order INTEGER NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    file TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    qrcode_url TEXT,
    raetsel_buchstabe TEXT,
    comment TEXT,
    design_path TEXT,
    event_uid TEXT REFERENCES events(uid) ON DELETE CASCADE
);
ALTER TABLE catalogs
    ADD CONSTRAINT catalogs_unique_sort_order
    UNIQUE(event_uid, sort_order) DEFERRABLE INITIALLY DEFERRED;

-- Questions belonging to catalogs
CREATE TABLE IF NOT EXISTS questions (
    id SERIAL PRIMARY KEY,
    catalog_uid TEXT NOT NULL,
    sort_order INTEGER,
    type TEXT NOT NULL,
    prompt TEXT NOT NULL,
    options JSONB,
    answers JSONB,
    terms JSONB,
    items JSONB,
    FOREIGN KEY (catalog_uid) REFERENCES catalogs(uid) ON DELETE CASCADE,
    UNIQUE (catalog_uid, sort_order)
);
CREATE INDEX IF NOT EXISTS idx_questions_catalog ON questions(catalog_uid);

-- Photo consents
CREATE TABLE IF NOT EXISTS photo_consents (
    id SERIAL PRIMARY KEY,
    team TEXT NOT NULL,
    time INTEGER NOT NULL,
    event_uid TEXT REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_photo_consents_team ON photo_consents(team);

-- Summary photos uploaded after quiz completion
CREATE TABLE IF NOT EXISTS summary_photos (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    path TEXT NOT NULL,
    time INTEGER NOT NULL,
    event_uid TEXT REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_summary_photos_name ON summary_photos(name);

-- User accounts
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'catalog-editor',
    CONSTRAINT users_role_check CHECK (role IN ('admin','catalog-editor','event-manager','analyst','team-manager','service-account'))
);

-- Tenant definitions
CREATE TABLE IF NOT EXISTS tenants (
    uid TEXT PRIMARY KEY,
    subdomain TEXT UNIQUE NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Currently active event
CREATE TABLE IF NOT EXISTS active_event (
    event_uid TEXT PRIMARY KEY REFERENCES events(uid) ON DELETE CASCADE
);
