-- Database schema for Sommerfest Quiz
-- Mirrors the JSON structure stored under data/

-- Configuration settings (one row expected)
CREATE TABLE config (
    id INTEGER PRIMARY KEY,
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
CREATE TABLE teams (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
);
CREATE UNIQUE INDEX idx_team_name ON teams(name);

-- Quiz results
CREATE TABLE results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
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
CREATE TABLE catalogs (
    uid TEXT PRIMARY KEY,
    id TEXT UNIQUE NOT NULL,
    file TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    qrcode_url TEXT,
    raetsel_buchstabe TEXT
);

-- Questions belonging to catalogs
CREATE TABLE questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    catalog_id TEXT NOT NULL,
    type TEXT NOT NULL,
    prompt TEXT NOT NULL,
    options JSON,
    answers JSON,
    terms JSON,
    items JSON,
    FOREIGN KEY (catalog_id) REFERENCES catalogs(id)
);
CREATE INDEX idx_questions_catalog ON questions(catalog_id);
