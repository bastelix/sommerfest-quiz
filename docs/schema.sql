-- Database schema for Sommerfest Quiz
-- Mirrors the JSON structure stored under data/

-- Configuration settings (one row expected)
CREATE TABLE IF NOT EXISTS config (
    id SERIAL PRIMARY KEY,
    displayErrorDetails BOOLEAN,
    QRUser BOOLEAN,
    logoPath TEXT,
    pageTitle TEXT,
    header TEXT,
    subheader TEXT,
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
    puzzleFeedback TEXT
);

-- Teams list (names only)
CREATE TABLE IF NOT EXISTS teams (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL
);
CREATE UNIQUE INDEX idx_team_name ON teams(name);

-- Quiz results
CREATE TABLE IF NOT EXISTS results (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    catalog TEXT NOT NULL,
    attempt INTEGER NOT NULL,
    correct INTEGER NOT NULL,
    total INTEGER NOT NULL,
    time INTEGER NOT NULL,
    puzzleTime INTEGER,
    photo TEXT
);
CREATE INDEX idx_results_catalog ON results(catalog);
CREATE INDEX idx_results_name ON results(name);

-- Catalog definitions
CREATE TABLE IF NOT EXISTS catalogs (
    uid TEXT PRIMARY KEY,
    id TEXT UNIQUE NOT NULL,
    file TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    qrcode_url TEXT,
    raetsel_buchstabe TEXT,
    comment TEXT
);

-- Questions belonging to catalogs
CREATE TABLE IF NOT EXISTS questions (
    id SERIAL PRIMARY KEY,
    catalog_id TEXT NOT NULL,
    type TEXT NOT NULL,
    prompt TEXT NOT NULL,
    options JSONB,
    answers JSONB,
    terms JSONB,
    items JSONB,
    FOREIGN KEY (catalog_id) REFERENCES catalogs(id)
);
CREATE INDEX idx_questions_catalog ON questions(catalog_id);

-- Photo consents for uploaded evidence
CREATE TABLE IF NOT EXISTS photo_consents (
    id SERIAL PRIMARY KEY,
    team TEXT NOT NULL,
    time INTEGER NOT NULL
);
CREATE INDEX idx_photo_consents_team ON photo_consents(team);
