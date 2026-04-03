# eQuiz-Modul – Migrationsprompt für eForms-Framework

> **Zweck:** Dieses Dokument beschreibt vollständig das bestehende Quiz-System aus edocs-cloud und dient als **Implementierungsbriefing** für die eForms-Code-Assistenz. Es enthält alle Details zu DB-Schema, Fragetypen, Spielablauf, Scoring, Konfiguration, API-Endpunkten und Frontend-Code.

---

## 1. Architektur-Überblick des Quellsystems

**Quell-Framework:** Slim 4 (PHP 8.2+), PostgreSQL 15, Twig 3, UIkit 3 + Vanilla JS  
**Muster:** Controller → Service → Repository → PDO

### Kerndateien Backend

| Datei | Funktion |
|-------|----------|
| `src/Controller/Api/V1/NamespaceQuizController.php` | REST-API (Events, Catalogs, Results, Teams) |
| `src/Controller/CatalogController.php` | Web-UI für Kataloge |
| `src/Controller/ResultController.php` | Ergebnisverwaltung |
| `src/Service/EventService.php` | Event-Lifecycle |
| `src/Service/CatalogService.php` | Katalog-CRUD + JSON-Dateifallback |
| `src/Service/ResultService.php` | Ergebnis-Tracking mit Per-Question-Metriken |
| `src/Service/TeamService.php` | Teamverwaltung |
| `src/Service/ConfigService.php` | Event-Konfiguration (Bool/JSON-Keys) |
| `src/Routes/api_v1.php` | Routendefinitionen |
| `migrations/20240910_base_schema.sql` | Kernschema |

### Kerndateien Frontend

| Datei | Zeilen | Funktion |
|-------|--------|----------|
| `public/js/quiz.js` | ~2650 | Hauptquiz-Engine: Fragerendering, Auswertung, Ergebnis-Submission |
| `public/js/catalog.js` | ~489 | Katalogauswahl + Player-Logik |
| `public/js/admin-catalog.js` | ~1679 | Admin-Frageneditor |
| `public/js/admin-events.js` | ~598 | Admin-Eventverwaltung |
| `public/js/admin-config.js` | ~4059 | Admin-Konfigurationseditor |
| `public/js/admin-teams.js` | ~426 | Admin-Teamverwaltung |
| `public/js/results.js` | ~569 | Ergebnisansicht |
| `public/js/results-data-service.js` | ~933 | Ergebnis-Datenservice |
| `public/js/event-dashboard.js` | ~1109 | Live-Dashboard/Leaderboard |
| `public/js/event-config.js` | ~1384 | Event-Konfigurationslogik |
| `public/js/storage.js` | ~165 | localStorage/sessionStorage-Abstraktion |
| `public/js/events.js` | ~492 | Event-Auswahl und -Verwaltung |

### Templates (Twig)

| Template | Funktion |
|----------|----------|
| `templates/event_catalogs.twig` | Katalogübersicht mit Card-Grid |
| `templates/results.twig` | Ergebnistabelle + Ranking |
| `templates/results-hub.twig` | Ergebnis-Hub |
| `templates/events_overview.twig` | Eventübersicht |
| `templates/admin/event_config.twig` | Admin-Konfiguration |
| `templates/admin/components/event_controls.twig` | Event-Steuerungselemente |
| `templates/admin/components/event_dashboard.twig` | Dashboard-Komponente |

---

## 2. Datenbank-Schema

Alle Tabellen verwenden den Prefix `equiz_`. Die `namespace`-Spalte aus dem Quellsystem wird **nicht** übernommen.

```sql
-- Quiz-Events (Hauptentität)
CREATE TABLE equiz_events (
    uid TEXT PRIMARY KEY,        -- hex-encoded random UID
    name TEXT NOT NULL,
    description TEXT,
    slug TEXT,
    start_date TEXT,
    end_date TEXT,
    published BOOLEAN DEFAULT FALSE,
    sort_order INTEGER DEFAULT 0
);

-- Kataloge (Fragensammlungen pro Event)
CREATE TABLE equiz_catalogs (
    uid TEXT PRIMARY KEY,
    event_uid TEXT NOT NULL REFERENCES equiz_events(uid) ON DELETE CASCADE,
    slug TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    comment TEXT,
    file TEXT,
    sort_order INTEGER DEFAULT 0,
    design_path TEXT,
    raetsel_buchstabe TEXT,       -- Buchstabe für Rätselwort
    UNIQUE(event_uid, sort_order)
);

-- Fragen
CREATE TABLE equiz_questions (
    id SERIAL PRIMARY KEY,
    catalog_uid TEXT NOT NULL REFERENCES equiz_catalogs(uid) ON DELETE CASCADE,
    type TEXT NOT NULL,           -- 'mc', 'sort', 'assign', 'swipe', 'flip', 'photoText'
    prompt TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0,
    points INTEGER DEFAULT 1,    -- 0-10000, 0 für flip
    countdown INTEGER,           -- Sekunden, NULL = kein Limit
    options JSONB,               -- MC-Optionen
    answers JSONB,               -- Korrekte Antwort-Indizes
    terms JSONB,                 -- Zuordnungs-Paare [{term, definition}]
    items JSONB,                 -- Sortier-Items
    cards JSONB,                 -- Swipe-Karten [{text, correct}]
    right_label TEXT,            -- Swipe: rechtes Label
    left_label TEXT,             -- Swipe: linkes Label
    UNIQUE(catalog_uid, sort_order)
);

-- Ergebnisse
CREATE TABLE equiz_results (
    id SERIAL PRIMARY KEY,
    event_uid TEXT NOT NULL REFERENCES equiz_events(uid) ON DELETE CASCADE,
    name TEXT NOT NULL,
    catalog TEXT NOT NULL,
    attempt INTEGER DEFAULT 1,
    correct INTEGER DEFAULT 0,
    total INTEGER DEFAULT 0,
    points INTEGER DEFAULT 0,
    max_points INTEGER DEFAULT 0,
    time INTEGER,                -- Millisekunden
    started_at DOUBLE PRECISION,
    duration_sec DOUBLE PRECISION,
    expected_duration_sec DOUBLE PRECISION,
    duration_ratio DOUBLE PRECISION,
    photo TEXT,
    player_uid TEXT,
    answer_text TEXT,
    consent BOOLEAN
);
CREATE INDEX idx_equiz_results_catalog ON equiz_results(catalog);
CREATE INDEX idx_equiz_results_name ON equiz_results(name);

-- Per-Question Ergebnisse
CREATE TABLE equiz_question_results (
    id SERIAL PRIMARY KEY,
    question_id INTEGER,
    catalog TEXT NOT NULL,
    event_uid TEXT NOT NULL,
    correct INTEGER DEFAULT 0,
    points INTEGER DEFAULT 0,
    final_points INTEGER DEFAULT 0,
    time_left_sec DOUBLE PRECISION,
    efficiency DOUBLE PRECISION,
    is_correct BOOLEAN DEFAULT FALSE,
    scoring_version INTEGER DEFAULT 1,
    answers JSONB,
    photos JSONB,
    consent BOOLEAN
);

-- Teams
CREATE TABLE equiz_teams (
    uid TEXT PRIMARY KEY,
    event_uid TEXT NOT NULL REFERENCES equiz_events(uid) ON DELETE CASCADE,
    name TEXT NOT NULL UNIQUE,
    sort_order INTEGER DEFAULT 0,
    UNIQUE(event_uid, sort_order)
);

-- Konfiguration (1 Zeile pro Event)
CREATE TABLE equiz_config (
    id SERIAL PRIMARY KEY,
    event_uid TEXT NOT NULL REFERENCES equiz_events(uid) ON DELETE CASCADE,
    -- Boolean-Flags:
    "randomNames" BOOLEAN DEFAULT TRUE,
    "shuffleQuestions" BOOLEAN DEFAULT TRUE,
    "competitionMode" BOOLEAN DEFAULT FALSE,
    "teamResults" BOOLEAN DEFAULT FALSE,
    "photoUpload" BOOLEAN DEFAULT TRUE,
    "puzzleWordEnabled" BOOLEAN DEFAULT FALSE,
    "collectPlayerUid" BOOLEAN DEFAULT FALSE,
    "countdownEnabled" BOOLEAN DEFAULT FALSE,
    "QRUser" BOOLEAN DEFAULT FALSE,
    "QRRemember" BOOLEAN DEFAULT FALSE,
    "QRRestrict" BOOLEAN DEFAULT FALSE,
    "loginRequired" BOOLEAN DEFAULT FALSE,
    "dashboardShareEnabled" BOOLEAN DEFAULT FALSE,
    "dashboardSponsorEnabled" BOOLEAN DEFAULT FALSE,
    "playerContactEnabled" BOOLEAN DEFAULT FALSE,
    -- JSON-Felder:
    colors JSONB,
    "designTokens" JSONB,
    "dashboardModules" JSONB,
    "dashboardSponsorModules" JSONB,
    -- String-Felder:
    "startTheme" TEXT,
    "effectsProfile" TEXT,
    "sliderProfile" TEXT,
    "resultsViewMode" TEXT,
    "customCss" TEXT,
    countdown INTEGER,
    "logoPath" TEXT,
    "puzzleWord" TEXT,
    "puzzleFeedback" TEXT,
    "dashboardTheme" TEXT,
    "dashboardRefreshInterval" INTEGER,
    "dashboardInfoText" TEXT,
    "dashboardMediaEmbed" TEXT
);

-- Spieler
CREATE TABLE equiz_players (
    event_uid TEXT NOT NULL REFERENCES equiz_events(uid) ON DELETE CASCADE,
    player_name TEXT NOT NULL,
    player_uid TEXT NOT NULL,
    PRIMARY KEY (event_uid, player_uid)
);
```

---

## 3. Fragetypen

| Typ | Beschreibung | Datenfelder | Bewertbar |
|-----|-------------|-------------|-----------|
| `mc` | Multiple Choice | `prompt`, `options[]`, `answers[]` (Indizes) | Ja |
| `sort` | Sortierung/Reihenfolge | `prompt`, `items[]` (korrekte Reihenfolge) | Ja |
| `assign` | Zuordnung (Drag & Drop) | `prompt`, `terms[{term, definition}]` | Ja |
| `swipe` | Wisch-Karten (Tinder-Stil) | `prompt`, `cards[{text, correct}]`, `leftLabel`, `rightLabel` | Ja |
| `flip` | Lernkarte (Umdrehen) | `prompt`, `answer` | Nein (0 Punkte) |
| `photoText` | Foto + Text Eingabe | `prompt`, `consent` | Ja (manuell) |

---

## 4. Spielablauf

```
1. EVENT-AUSWAHL
   → Benutzer wählt ein Event (oder wird via URL direkt weitergeleitet)

2. KATALOG-AUSWAHL (event_catalogs.twig)
   → Kartenraster mit allen Katalogen des Events
   → Bereits gelöste Kataloge werden markiert (competitionMode)
   → Klick öffnet den Quiz-Player

3. QUIZ-PLAYER (quiz.js)
   a) TEAMNAME-EINGABE
      - promptTeamName(): Modal mit Zufallsname-Vorschlag (Format: "Gast-xxxxx")
      - Einfache Zufallsnamen ohne Server-Reservierung
      - Alternativ: QR-Code-Scanner für Namenszuweisung (QRUser-Modus)
      - Wiederkehrende Spieler werden erkannt ("Ah - dich kenne ich")

   b) STARTBILDSCHIRM
      - Logo + Event-Name + Beschreibung
      - "Los geht's!"-Button

   c) FRAGEN-DURCHLAUF
      - Fragen werden gemischt (shuffleQuestions, Fisher-Yates)
      - Fortschrittsbalken (nur bewertbare Fragen zählen)
      - Pro Frage:
        * Countdown-Timer (optional, konfigurierbar pro Frage oder global)
        * Punktevorschau bei aktivem Timer
        * Typ-spezifisches Rendering (mc/sort/assign/swipe/flip/photoText)
        * Feedback nach Antwort (korrekt/falsch + Farbmarkierung)
        * Automatischer Weiter bei Timeout (1.5s Verzögerung)
      - Antworten werden im answers[]-Array gespeichert mit:
        * isCorrect, timeLeftSec, ggf. photo/text/consent

   d) ZUSAMMENFASSUNG
      - "Danke für die Teilnahme [Name]!"
      - Punkte/Richtige anzeigen
      - Confetti-Animation bei Volltreffer
      - Rätselwort-Buchstabe anzeigen (puzzleWordEnabled)
      - Verbleibende Stationen anzeigen (competitionMode)
      - Beweisfoto-Upload (photoUpload)
      - Rätselwort-Prüfung (puzzleWordEnabled)
      - Ergebnis per POST /results an Server senden

   e) ERGEBNIS-DATEN (POST /results)
      {
        name, catalog, correct, total, points, maxPoints,
        wrong: [Frage-Nummern], answers: [{isCorrect, timeLeftSec, ...}],
        event_uid, startedAt, player_uid, puzzleTime
      }
```

---

## 5. Scoring-System

```javascript
// Basis-Punkte pro Frage: 0-10000 (default: 1, flip: 0)
// Bei aktivem Countdown: Zeitbasierter Multiplikator
const SCORE_ALPHA = 0.5;  // Exponent für Zeitkurve (Wurzelfunktion)
const SCORE_FLOOR = 0.1;  // Mindest-Multiplikator (10% der Basispunkte)

function computePreviewPoints(basePoints, totalSeconds, remainingSeconds) {
    const ratio = remainingSeconds / totalSeconds;
    const multiplier = Math.max(Math.pow(ratio, SCORE_ALPHA), SCORE_FLOOR);
    return Math.round(basePoints * multiplier);
}
```

---

## 6. Konfigurationsoptionen

### Spielmodus

| Key | Beschreibung |
|-----|-------------|
| `competitionMode` | Team-Wettbewerb (jeder Katalog nur einmal lösbar) |
| `teamResults` | Team-Leaderboard am Ende |
| `shuffleQuestions` | Fragenreihenfolge mischen |
| `randomNames` | Zufallsnamen vorschlagen (Format: `Gast-xxxxx`) |
| `countdownEnabled` | Zeitlimit pro Frage aktivieren |
| `countdown` | Standard-Countdown in Sekunden |

### Spieler-Identifikation

| Key | Beschreibung |
|-----|-------------|
| `QRUser` | Name per QR-Code scannen |
| `QRRemember` | QR-gescannten Namen merken |
| `QRRestrict` | Nur QR-Registrierung erlaubt |
| `collectPlayerUid` | Individuelle Spieler-IDs tracken |
| `loginRequired` | Authentifizierung erforderlich |
| `playerContactEnabled` | Kontaktdaten-Sammlung |

### Features

| Key | Beschreibung |
|-----|-------------|
| `photoUpload` | Beweisfoto-Upload nach Quiz |
| `puzzleWordEnabled` | Rätselwort-Feature (Buchstabe pro Katalog) |
| `puzzleWord` | Das zu erratende Rätselwort |
| `puzzleFeedback` | Feedback-Text bei korrektem Rätselwort |

### Erscheinungsbild

| Key | Beschreibung |
|-----|-------------|
| `colors` | JSON mit `{primary, accent}` Farbschema |
| `designTokens` | Design-System-Tokens |
| `logoPath` | Pfad zum Event-Logo |
| `customCss` | Benutzerdefiniertes CSS |
| `startTheme` | Start-Theme |
| `effectsProfile` | Effekte-Profil |
| `sliderProfile` | Slider-Profil |

### Dashboard/Leaderboard

| Key | Beschreibung |
|-----|-------------|
| `dashboardShareEnabled` | Öffentlicher Leaderboard-Link |
| `dashboardSponsorEnabled` | Sponsor-Branding |
| `dashboardTheme` | Dark/Light |
| `dashboardRefreshInterval` | Aktualisierungsintervall |
| `dashboardModules` | Leaderboard-Module (JSON) |
| `dashboardSponsorModules` | Sponsor-Module (JSON) |
| `dashboardInfoText` | Info-Text |
| `dashboardMediaEmbed` | Media-Embed |

### Teamnamen (vereinfacht)

- `randomNames` aktiviert Zufallsnamen (Format: `Gast-xxxxx`)
- Kein AI-Generierungssystem, kein Reservierungssystem
- Einfache clientseitige Generierung: `Gast-${Math.random().toString(36).slice(2, 7)}`

---

## 7. API-Endpunkte

Alle Endpunkte ohne Namespace-Prefix, unter `/api/equiz/`.

```
# Events
GET    /api/equiz/events                       → Liste aller Events
GET    /api/equiz/events/{uid}                 → Event-Details
POST   /api/equiz/events                       → Event erstellen
PATCH  /api/equiz/events/{uid}                 → Event aktualisieren

# Kataloge
GET    /api/equiz/events/{uid}/catalogs        → Alle Kataloge eines Events
GET    /api/equiz/events/{uid}/catalogs/{slug} → Katalog mit Fragen
PUT    /api/equiz/events/{uid}/catalogs/{slug} → Katalog erstellen/aktualisieren

# Ergebnisse
GET    /api/equiz/events/{uid}/results         → Alle Ergebnisse
POST   /api/equiz/events/{uid}/results         → Ergebnis einreichen
DELETE /api/equiz/events/{uid}/results         → Alle Ergebnisse löschen

# Teams
GET    /api/equiz/events/{uid}/teams           → Teamliste
PUT    /api/equiz/events/{uid}/teams           → Teamliste ersetzen

# Spieler
POST   /api/equiz/players                      → Spieler registrieren
GET    /api/equiz/quiz-progress                → Gelöste Kataloge abfragen

# Fotos
POST   /api/equiz/photos                       → Beweisfoto hochladen

# Rätselwort
POST   /api/equiz/results?debug=1              → Rätselwort prüfen (puzzleAnswer-Feld)

# Konfiguration
GET    /api/equiz/events/{uid}/config          → Event-Konfiguration lesen
PUT    /api/equiz/events/{uid}/config          → Event-Konfiguration speichern
```

---

## 8. Frontend-Architektur

**UI-Framework:** UIkit 3 (CSS + JS-Komponenten)  
**Kein Build-System:** Vanilla JS mit `<script>` / `<script type="module">`  
**Drag & Drop:** SortableJS für Sort- und Assign-Fragen

### Schlüssel-Patterns

- `window.quizConfig` – Globale Konfiguration (vom Server injiziert)
- `window.quizQuestions` – Fragen-Array (vom Server injiziert)
- `window.startQuiz(questions, skipIntro)` – Einstiegspunkt
- `STORAGE_KEYS` – Abstraktion für localStorage/sessionStorage
- Modale Dialoge via `UIkit.modal()` (dynamisch erzeugt)
- CSS-Variablen für Theming (`--color-bg`, `--accent-color`)

### Admin-Editor (admin-catalog.js)

- `initCatalog(ctx)` – Modularer Editor mit Dependency Injection
- TableManager für Katalogliste
- Inline-Frageneditor mit Typ-Wechsel
- Drag & Drop Sortierung der Fragen
- Undo-Stack für Fragenänderungen
- Slug-Generierung und Validierung

---

## 9. Entfernungen (Namespace-Bindung)

Folgendes wird **NICHT** in das eQuiz-Modul übernommen:

- `namespace`-Spalte in Events und allen Queries
- `ApiTokenAuthMiddleware` mit Namespace-Scope-Prüfung
- `NamespaceResolver` und `NamespaceAccessService`
- `requireNamespaceMatch()` in Controllern
- `EventService::belongsToNamespace()` / `getByUidInNamespace()`
- `QuotaService`-Integration (Namespace-basierte Limits)
- URL-Prefix `/api/v1/namespaces/{ns}/`
- Multi-Tenant Datenisolierung
- `TeamNameClient` mit reserve/confirm/release (AI-basierte Namensgenerierung)
- `TeamNameService`, `TeamNameWarmupDispatcher`
- `randomNameDomains`, `randomNameTones` Konfigurationsfelder

---

## 10. Modulstruktur-Empfehlung für eForms

```
eQuiz/
├── Controller/
│   ├── QuizApiController.php          → REST-API für Events, Catalogs, Results, Teams
│   ├── QuizPlayerController.php       → Frontend-Endpunkte (Players, Progress, Photos)
│   ├── QuizAdminController.php        → Admin-UI (Eventverwaltung, Kataloge, Konfiguration)
│   └── QuizDashboardController.php    → Live-Dashboard/Leaderboard
├── Service/
│   ├── QuizEventService.php           → Event-Lifecycle
│   ├── QuizCatalogService.php         → Katalog-CRUD + Fragen
│   ├── QuizResultService.php          → Ergebnis-Tracking + Scoring
│   ├── QuizTeamService.php            → Teamverwaltung
│   └── QuizConfigService.php          → Event-Konfiguration
├── Migration/
│   └── create_equiz_tables.sql
├── Resources/
│   ├── js/
│   │   ├── quiz.js                    → Quiz-Engine (1:1 übernehmen)
│   │   ├── catalog.js                 → Katalogauswahl
│   │   ├── admin-catalog.js           → Admin-Frageneditor
│   │   ├── admin-events.js            → Admin-Eventverwaltung
│   │   ├── admin-config.js            → Konfigurationseditor
│   │   ├── admin-teams.js             → Teamverwaltung
│   │   ├── results.js                 → Ergebnisansicht
│   │   ├── results-data-service.js    → Ergebnis-Datenservice
│   │   ├── event-dashboard.js         → Dashboard
│   │   ├── event-config.js            → Konfigurationslogik
│   │   └── storage.js                 → Storage-Abstraktion
│   ├── css/
│   │   ├── quiz.css                   → Quiz-Player Styles
│   │   └── admin.css                  → Admin Styles
│   └── views/
│       ├── quiz/player.twig           → Quiz-Player Template
│       ├── quiz/catalogs.twig         → Katalogauswahl
│       ├── quiz/results.twig          → Ergebnisse
│       ├── quiz/summary.twig          → Spieler-Zusammenfassung
│       ├── quiz/dashboard.twig        → Live-Dashboard
│       └── admin/
│           ├── events.twig            → Event-Verwaltung
│           ├── catalogs.twig          → Katalog-/Fragen-Editor
│           ├── config.twig            → Konfiguration
│           ├── results.twig           → Ergebnis-Verwaltung
│           └── teams.twig             → Team-Verwaltung
└── routes.php                         → Alle eQuiz-Routen
```

---

## 11. Testplan

1. **Event erstellen** – Admin: neues Event mit Name, Beschreibung, Datumsbereich
2. **Katalog anlegen** – Admin: Katalog mit Slug und Fragen aller 6 Typen erstellen
3. **Konfiguration setzen** – Admin: competitionMode, shuffleQuestions, countdownEnabled konfigurieren
4. **Quiz spielen** – Frontend: Teamname eingeben → Fragen durchspielen → Ergebnis absenden
5. **Ergebnisse prüfen** – Admin: Ergebnistabelle mit korrekten Punkten und Zeitmetriken
6. **Dashboard** – Live-Leaderboard mit automatischer Aktualisierung
7. **Competition-Modus** – Katalog als gelöst markiert, kein erneutes Spielen möglich
8. **Rätselwort** – Buchstaben sammeln und Rätselwort prüfen
9. **Foto-Upload** – Beweisfoto einreichen und in Ergebnissen sichtbar
10. **Teams** – Teamliste verwalten, Teamnamen-Vorschläge funktionsfähig

---

## Zusammenfassung

Dieses Dokument beschreibt vollständig das bestehende Quiz-System aus edocs-cloud. Die Kernprinzipien für die Migration:

1. **Alle 6 Fragetypen** (mc, sort, assign, swipe, flip, photoText) mit identischer Spiellogik übernehmen
2. **Frontend-Code** (quiz.js, admin-catalog.js) als Basis nutzen – die dynamische DOM-Erzeugung und UIkit-Integration bleiben gleich
3. **Namespace-Bindung entfernen** – alle `namespace`-Parameter, Multi-Tenant-Middleware und Quotas weglassen
4. **Tabellen-Prefix `equiz_`** verwenden für saubere Trennung im eForms-Schema
5. **API-Pfade** unter `/api/equiz/` ohne Namespace-Segment
6. **Konfigurationssystem** vollständig übernehmen (Bool-Keys, JSON-Felder, Dashboard-Settings)
7. **Scoring-Algorithmus** mit Countdown-Multiplikator exakt reproduzieren
8. **Teamnamen** nur als einfache Zufallsnamen (`Gast-xxxxx`), ohne AI-Generierung und Reservierungssystem
