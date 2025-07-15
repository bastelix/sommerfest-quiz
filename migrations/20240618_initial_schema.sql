-- Initial database schema for Sommerfest Quiz
-- Mirrors docs/schema.sql to ensure tests have required tables

-- Configuration settings
CREATE TABLE IF NOT EXISTS config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    displayErrorDetails BOOLEAN,
    QRUser BOOLEAN,
    QRRemember BOOLEAN,
    logoPath TEXT,
    pageTitle TEXT,
    backgroundColor TEXT,
    buttonColor TEXT,
    CheckAnswerButton TEXT,
    adminUser TEXT,
    adminPass TEXT,
    QRRestrict BOOLEAN,
    competitionMode BOOLEAN,
    teamResults BOOLEAN,
    photoUpload BOOLEAN,
    puzzleWordEnabled BOOLEAN,
    puzzleWord TEXT,
    puzzleFeedback TEXT,
    inviteText TEXT,
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
    event_uid TEXT REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_results_catalog ON results(catalog);
CREATE INDEX IF NOT EXISTS idx_results_name ON results(name);

-- Per-question answer log
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
    event_uid TEXT REFERENCES events(uid) ON DELETE CASCADE
);
CREATE UNIQUE INDEX IF NOT EXISTS catalogs_sort_order_unique ON catalogs(sort_order);

-- Questions belonging to catalogs
CREATE TABLE IF NOT EXISTS questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    catalog_uid TEXT NOT NULL,
    sort_order INTEGER,
    type TEXT NOT NULL,
    prompt TEXT NOT NULL,
    options TEXT,
    answers TEXT,
    terms TEXT,
    items TEXT,
    FOREIGN KEY (catalog_uid) REFERENCES catalogs(uid) ON DELETE CASCADE,
    UNIQUE (catalog_uid, sort_order)
);
CREATE INDEX IF NOT EXISTS idx_questions_catalog ON questions(catalog_uid);

-- Photo consents
CREATE TABLE IF NOT EXISTS photo_consents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    team TEXT NOT NULL,
    time INTEGER NOT NULL,
    event_uid TEXT REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_photo_consents_team ON photo_consents(team);

-- Multi-tenant support
CREATE TABLE IF NOT EXISTS tenants (
    uid TEXT PRIMARY KEY,
    subdomain TEXT UNIQUE NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- User accounts
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'catalog-editor'
);
