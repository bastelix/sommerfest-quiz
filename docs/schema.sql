-- Combined base schema for QuizRace (optimiert, robust und idempotent)

-- ENUM f\xC3\xBCr Rollen
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
    CREATE TYPE user_role AS ENUM (
      'admin','catalog-editor','event-manager','analyst','team-manager','service-account'
    );
  END IF;
END$$;

-- Events
CREATE TABLE IF NOT EXISTS events (
    uid TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    start_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    description TEXT
);

-- Config
CREATE TABLE IF NOT EXISTS config (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
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
    competitionMode BOOLEAN,
    teamResults BOOLEAN,
    photoUpload BOOLEAN,
    puzzleWordEnabled BOOLEAN,
    puzzleWord TEXT,
    puzzleFeedback TEXT,
    inviteText TEXT,
    preview_password_hash TEXT,
    qrremember BOOLEAN DEFAULT FALSE,
    event_uid TEXT,
    CONSTRAINT fk_config_event FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);

-- Teams
CREATE TABLE IF NOT EXISTS teams (
    sort_order INTEGER NOT NULL,
    name TEXT NOT NULL,
    uid TEXT PRIMARY KEY,
    event_uid TEXT,
    CONSTRAINT fk_teams_event FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE,
    CONSTRAINT uq_teams_sort_order UNIQUE (event_uid, sort_order)
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_team_name ON teams(name);

-- Results
CREATE TABLE IF NOT EXISTS results (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
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
    CONSTRAINT fk_results_event FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_results_catalog ON results(catalog);
CREATE INDEX IF NOT EXISTS idx_results_name ON results(name);

-- Per-question results
CREATE TABLE IF NOT EXISTS question_results (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name TEXT NOT NULL,
    catalog TEXT NOT NULL,
    question_id INTEGER NOT NULL,
    attempt INTEGER NOT NULL,
    correct INTEGER NOT NULL,
    answer_text TEXT,
    photo TEXT,
    consent BOOLEAN,
    event_uid TEXT,
    CONSTRAINT fk_qresults_event FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
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
    CONSTRAINT fk_catalogs_event FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE constraint_name = 'catalogs_unique_sort_order'
      AND table_name = 'catalogs'
  ) THEN
    ALTER TABLE catalogs
      ADD CONSTRAINT catalogs_unique_sort_order
      UNIQUE(event_uid, sort_order) DEFERRABLE INITIALLY DEFERRED;
  END IF;
END$$;

-- Questions
CREATE TABLE IF NOT EXISTS questions (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    catalog_uid TEXT NOT NULL,
    sort_order INTEGER,
    type TEXT NOT NULL,
    prompt TEXT NOT NULL,
    options JSONB DEFAULT '{}'::JSONB,
    answers JSONB DEFAULT '[]'::JSONB,
    terms JSONB DEFAULT '{}'::JSONB,
    items JSONB DEFAULT '{}'::JSONB,
    cards JSONB DEFAULT '[]'::JSONB,
    right_label TEXT,
    left_label TEXT,
    FOREIGN KEY (catalog_uid) REFERENCES catalogs(uid) ON DELETE CASCADE,
    CONSTRAINT uq_questions_catalog_sort UNIQUE (catalog_uid, sort_order)
);
CREATE INDEX IF NOT EXISTS idx_questions_catalog ON questions(catalog_uid);

-- Photo consents
CREATE TABLE IF NOT EXISTS photo_consents (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    team TEXT NOT NULL,
    time INTEGER NOT NULL,
    event_uid TEXT,
    CONSTRAINT fk_photo_consents_event FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_photo_consents_team ON photo_consents(team);

-- Summary photos
CREATE TABLE IF NOT EXISTS summary_photos (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name TEXT NOT NULL,
    path TEXT NOT NULL,
    time INTEGER NOT NULL,
    event_uid TEXT,
    CONSTRAINT fk_summary_photos_event FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_summary_photos_name ON summary_photos(name);

-- User accounts
CREATE TABLE IF NOT EXISTS users (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    email TEXT UNIQUE,
    role user_role NOT NULL DEFAULT 'catalog-editor'
);

-- Tenant definitions
CREATE TABLE IF NOT EXISTS tenants (
    uid TEXT PRIMARY KEY,
    subdomain TEXT UNIQUE NOT NULL,
    plan TEXT,
    billing_info TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Currently active event
CREATE TABLE IF NOT EXISTS active_event (
    event_uid TEXT PRIMARY KEY,
    CONSTRAINT fk_active_event FOREIGN KEY (event_uid) REFERENCES events(uid) ON DELETE CASCADE
);
