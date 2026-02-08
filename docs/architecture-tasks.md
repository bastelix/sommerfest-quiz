# Aufgabenliste – Architektur-Professionalisierung QuizRace

> Priorisierte Aufgabenliste zur Behebung der im Architecture Review identifizierten
> Unzulänglichkeiten. Jede Phase baut auf der vorherigen auf. Innerhalb einer Phase
> sind Aufgaben parallelisierbar, sofern nicht anders markiert.

---

## Phase 0 – Dokumentation an Realität anpassen (Grundlage für alles Weitere)

> **Ziel:** AGENTS.md und Leitdokumente spiegeln den tatsächlichen Code wider.
> Erst wenn die Doku stimmt, kann sie als Leitplanke für Weiterentwicklung dienen.

### 0.1 AGENTS.md: `namespace_id UUID` → `namespace TEXT` korrigieren

**Problem:** AGENTS.md Zeile 132 fordert `namespace_id UUID NOT NULL`. Kein einziges
der 180 Migrationsfiles nutzt UUIDs. Der Code verwendet durchgängig `namespace TEXT`.

**Betroffene Stelle:**
- `AGENTS.md` Zeile 132

**Aufgabe:**
- [ ] Zeile 132 ändern auf: `namespace TEXT NOT NULL`
- [ ] Kommentar ergänzen, dass `namespace` ein menschenlesbarer Slug ist (z. B. `mein-projekt`)

**Akzeptanzkriterium:** Jeder Entwickler, der die AGENTS.md liest, schreibt Code,
der mit dem realen Schema kompatibel ist.

---

### 0.2 AGENTS.md: Modulstruktur als Ziel kennzeichnen, nicht als Ist-Zustand

**Problem:** AGENTS.md beschreibt drei Module (Events, Inhalte, Admin) mit „harten Grenzen".
Im Code existiert ein flaches `src/Service/`-Verzeichnis ohne jede Modulgrenze.

**Aufgabe:**
- [ ] Abschnitt „Modulgrenzen" in „Ziel-Modulgrenzen" umbenennen
- [ ] Hinweis ergänzen: „Aktuell sind die Module nicht physisch getrennt. Die
      Verzeichnisstruktur `src/Events/`, `src/Content/`, `src/Admin/` ist Migrationsziel."
- [ ] Roadmap-Verweis auf Phase 4 dieser Aufgabenliste

**Akzeptanzkriterium:** Kein Entwickler geht fälschlicherweise von bestehender Modulisolation aus.

---

### 0.3 AGENTS.md: Progressive Enhancement ehrlich dokumentieren

**Problem:** AGENTS.md Zeile 159 fordert „HTML funktioniert ohne JavaScript".
Das ist faktisch unwahr – Quiz, Admin und Theme-Switching sind vollständig JS-abhängig.
In der gesamten Codebasis gibt es nur 1 `<noscript>`-Tag.

**Aufgabe:**
- [ ] Formulierung anpassen auf: „Progressive Enhancement ist anzustreben.
      Neue Features sollen ohne JavaScript grundlegende Funktionalität bieten,
      soweit technisch vertretbar."
- [ ] Dokumentieren, welche Bereiche zwingend JS erfordern (Quiz-Gameplay, Admin-UI)

**Akzeptanzkriterium:** Doku-Aussage und Realität stimmen überein.

---

### 0.4 AGENTS.md: Dependency-Injection-Regeln präzisieren

**Problem:** Zeilen 119-123 verbieten Service-Locator und statische Globals.
`Database::connectFromEnv()` ist genau das – ein statischer Service-Locator.
66+ Stellen im Code nutzen ihn direkt.

**Aufgabe:**
- [ ] Regel ergänzen: „`Database::connectFromEnv()` ist eine Legacy-Ausnahme.
      Neue Services MÜSSEN PDO per Constructor Injection erhalten."
- [ ] Markieren, dass die vollständige Beseitigung statischer DB-Zugriffe in Phase 3 erfolgt

**Akzeptanzkriterium:** Neuer Code nutzt Constructor Injection. Bestehende Ausnahmen sind dokumentiert.

---

## Phase 1 – Technische Schulden mit sofortiger Wirkung beseitigen

> **Ziel:** Low-Hanging Fruit entfernen, die jede weitere Arbeit erschweren.
> Keine Architekturänderung, nur Aufräumen.

### 1.1 73 redundante `require_once` aus routes.php entfernen

**Problem:** PSR-4 Autoloading ist korrekt konfiguriert. 15 Controller funktionieren
bereits ohne `require_once`. Die 73 Statements (Zeilen 173-245) sind Legacy-Artefakte.

**Betroffene Stelle:**
- `src/routes.php` Zeilen 173-245

**Aufgabe:**
- [ ] Alle `require_once __DIR__ . '/Controller/...'` Zeilen entfernen
- [ ] Sicherstellen, dass Composer-Autoloader in `public/index.php` vor routes.php geladen wird (ist er)
- [ ] PHPUnit + manuelle Prüfung der Routen

**Akzeptanzkriterium:** `composer test` grün. Keine `require_once` für Klassen in `src/`.

---

### 1.2 PostgreSQL-Version angleichen (Test = Produktion)

**Problem:**
- `docker-compose.yml`: PostgreSQL **15**
- `docker-compose.test.yml`: PostgreSQL **16**
- `helm/values.yaml`: PostgreSQL **15**

**Aufgabe:**
- [ ] `docker-compose.test.yml` auf PostgreSQL 15 setzen (an Produktion angleichen)
- [ ] ODER Produktion auf 16 hochziehen (mit Migrationsplan)
- [ ] Entscheidung dokumentieren

**Akzeptanzkriterium:** Gleiche PostgreSQL-Major-Version in Test und Produktion.

---

### 1.3 Kubernetes Health-Probes ergänzen

**Problem:** `helm/sommerfest-quiz/templates/deployment.yaml` hat keine Liveness-/Readiness-Probes.
Ein hängender PHP-Prozess würde nicht automatisch neu gestartet.

**Betroffene Stelle:**
- `helm/sommerfest-quiz/templates/deployment.yaml`

**Aufgabe:**
- [ ] `livenessProbe` ergänzen: HTTP GET `/healthz` (Route existiert bereits in routes.php)
- [ ] `readinessProbe` ergänzen: HTTP GET `/healthz`
- [ ] `startupProbe` ergänzen für Migrationslaufzeit

**Akzeptanzkriterium:** `helm template` validiert. Probe-Konfiguration zeigt auf `/healthz`.

---

### 1.4 `db-init-job.yaml` Schema-Referenz korrigieren

**Problem:** `helm/sommerfest-quiz/templates/db-init-job.yaml` referenziert `docs/schema.sql`,
eine Datei die nicht eindeutig für diesen Zweck existiert.

**Aufgabe:**
- [ ] Prüfen, ob `docs/schema.sql` existiert und korrekt ist
- [ ] Alternativ auf den Migrator umstellen: `php scripts/run_migrations.php`
- [ ] Job-Template anpassen

**Akzeptanzkriterium:** Helm-Job initialisiert die DB zuverlässig mit dem aktuellen Schema.

---

## Phase 2 – Namespace-Scoping nachrüsten (Sicherheitskritisch)

> **Ziel:** Die AGENTS.md-Kardinalregel „Jede Query ohne Namespace-Scope ist ein
> Architekturfehler" tatsächlich durchsetzen. Verhindert Cross-Namespace-Datenzugriff.

### 2.1 Migration: `namespace`-Spalte zu Event-gebundenen Tabellen hinzufügen

**Betroffene Tabellen (kein `namespace`-Column vorhanden):**

| Tabelle | Aktueller Scope | Abhängigkeit |
|---------|-----------------|--------------|
| `config` | `event_uid` | events.namespace |
| `catalogs` | `event_uid` | events.namespace |
| `questions` | `catalog_uid` | catalogs → events.namespace |
| `results` | `event_uid` | events.namespace |
| `question_results` | `event_uid` | results → events.namespace |
| `teams` | `event_uid` | events.namespace |
| `players` | `event_uid` | events.namespace |
| `photo_consents` | `event_uid` | events.namespace |
| `summary_photos` | `event_uid` | events.namespace |
| `active_event` | `event_uid` | events.namespace |

**Aufgabe:**
- [ ] Forward-Migration schreiben: `ALTER TABLE ... ADD COLUMN namespace TEXT`
- [ ] Datenmigration: `UPDATE <table> SET namespace = (SELECT namespace FROM events WHERE ...)`
- [ ] `NOT NULL`-Constraint setzen nach Datenmigration
- [ ] Index auf `namespace`-Column erstellen
- [ ] SQLite-Schema (`sqlite-schema.sql`) synchron aktualisieren
- [ ] Rollback-Hinweis dokumentieren

**Akzeptanzkriterium:** Alle Event-Tabellen enthalten `namespace NOT NULL`.
Bestehende Daten sind korrekt zugeordnet.

---

### 2.2 Services: Namespace-Filter in alle Queries einbauen

**Betroffene Services und Methoden (92+ unscoped Queries):**

| Service | Datei | Kritische Methoden |
|---------|-------|--------------------|
| `ConfigService` | `src/Service/ConfigService.php` | `getJson()` Z.168, `getConfig()` Z.214, `saveConfig()` Z.653, `getActiveEventUid()` Z.852, `getDashboardTokens()` Z.415 |
| `ResultService` | `src/Service/ResultService.php` | `getAll()` Z.41, `add()` Z.361, `clear()` Z.756 (DELETE ohne Filter!), `clearTeams()` Z.794, `markPuzzle()` Z.816 |
| `CatalogService` | `src/Service/CatalogService.php` | `fetchPagedCatalogs()` Z.126, `countCatalogs()` Z.164, `uidBySlug()` Z.210, `createCatalog()` Z.234 |
| `TeamService` | `src/Service/TeamService.php` | `getAll()` Z.46, `getEventUidByName()` Z.80 (global!), `addIfMissing()` Z.97, `saveAll()` Z.144 |
| `PlayerService` | `src/Service/PlayerService.php` | `save()` Z.67, `find()` Z.112, alle Player-Queries |

**Aufgabe (pro Service):**
- [ ] Constructor um `string $namespace`-Parameter erweitern
- [ ] Alle SELECT-Queries um `AND namespace = ?` erweitern
- [ ] Alle INSERT-Statements um `namespace`-Column erweitern
- [ ] Alle DELETE/UPDATE um `AND namespace = ?` erweitern
- [ ] Tests schreiben: Query mit falschem Namespace gibt leere Ergebnisse
- [ ] `ResultService::clear()` absichern: Verbot von DELETE ohne namespace-Scope

**Akzeptanzkriterium:** Kein SQL-Statement in diesen Services ohne Namespace-Filter.
PHPStan + Tests grün.

---

### 2.3 PHPStan Custom Rule: Unscoped Queries erkennen

**Aufgabe:**
- [ ] Custom PHPStan-Rule schreiben, die Services ohne Namespace-Parameter im Konstruktor flaggt
- [ ] Alternativ: Grep-basierter CI-Check der prüft, ob jedes `FROM <business-table>` ein `namespace` enthält
- [ ] In `phpstan.neon.dist` oder CI-Workflow integrieren

**Akzeptanzkriterium:** CI schlägt fehl, wenn eine neue Query ohne Namespace-Filter hinzugefügt wird.

---

## Phase 3 – Strukturelle Verbesserung Backend

> **Ziel:** routes.php aufbrechen, DI-Container einführen, Repository-Pattern
> konsequent umsetzen. Macht den Code wartbar und testbar.

### 3.1 Leichtgewichtigen DI-Container einführen

**Problem:** 30+ Services werden bei JEDEM Request eager instanziiert in routes.php.
Doppelte Instanziierungen an 18+ Stellen. Kein Lazy-Loading.

**Aufgabe:**
- [ ] PHP-DI (`php-di/php-di`) oder Slims eingebauten Container evaluieren
- [ ] Container-Definitions-Datei erstellen: `src/container.php`
- [ ] Alle Service-Definitionen als Lazy-Factories registrieren
- [ ] Controller-Instantiierung über Container statt manuelles `new`
- [ ] `Database::connectFromEnv()`-Aufrufe in Container-Factory kapseln
- [ ] 66+ direkte `Database::connectFromEnv()`-Aufrufe aus Controllers/Services entfernen:
  - `HomeController.php:34`, `LoginController.php:30,66`, `RegisterController.php:29,43`
  - `AdminController.php:56,344,350`, `OnboardingController.php:41`
  - `ResultController.php:285`, `Admin/PageController.php:59`
  - `CmsMenuResolverService.php:32`, `PageAiJobRepository.php:26`
  - `PageModuleService.php:20`, `TenantService.php:71`
  - `AuthorizationMiddleware.php:74`
  - und ~50 weitere

**Akzeptanzkriterium:**
- Kein `new XyzService($pdo)` in routes.php oder Controllern
- Kein `Database::connectFromEnv()` außerhalb der Container-Factory
- Services werden erst bei Nutzung instanziiert (Lazy)
- `composer test` grün

---

### 3.2 routes.php in modulare Route-Dateien aufbrechen

**Problem:** 3.742 Zeilen in einer Datei. Enthält Routing, DI-Ersatz, Middleware
und Domain-Logik. 307 Routen.

**Zielstruktur:**
```
src/
├── routes.php                  → ~100 Zeilen, lädt nur Module
├── Routes/
│   ├── public.php              → Home, FAQ, Help, Legal (50+ Routen)
│   ├── auth.php                → Login, Register, Password (4 Routen)
│   ├── onboarding.php          → Onboarding, Stripe Checkout (11 Routen)
│   ├── admin.php               → Admin-Dashboard, Settings (151 Routen)
│   ├── api.php                 → /api/* Endpunkte (22 Routen)
│   ├── marketing.php           → Marketing-Seiten, Landing (15+ Routen)
│   ├── events.php              → Catalog, Teams, Results (25+ Routen)
│   ├── assets.php              → QR, Logo, Photos, Media (25+ Routen)
│   └── cms-catchall.php        → CMS-Page-Resolver (1 Route)
├── Middleware/
│   ├── TenantResolutionMiddleware.php     → Schema-Auflösung (aus routes.php Z.336-353)
│   ├── NamespaceQueryMiddleware.php       → (aus routes.php Z.252-263)
│   └── MarketingNamespaceMiddleware.php   → (aus routes.php Z.265-296)
```

**Aufgabe:**
- [ ] Inline-Middleware-Closures in eigene Middleware-Klassen extrahieren
- [ ] Route-Definitionen nach Modul in separate Dateien aufteilen
- [ ] `$app->group()` für gemeinsame Middleware nutzen (z. B. alle `/admin/*` Routen)
- [ ] routes.php als Orchestrator belassen, der nur Route-Files inkludiert
- [ ] Service-Instantiierung aus routes.php in Container verlagern (Abhängigkeit: 3.1)

**Akzeptanzkriterium:**
- routes.php < 150 Zeilen
- Jede Route-Datei < 500 Zeilen
- Keine Inline-Closure-Middleware mehr
- Alle Routen funktionieren wie zuvor

---

### 3.3 Repository-Pattern konsequent umsetzen

**Problem:** 5 Repositories vs. 37 Services mit direktem SQL. 3 Repositories
falsch platziert in `src/Service/`.

**Aufgabe:**
- [ ] Repository-Interface definieren (optional, aber empfohlen für Testbarkeit)
- [ ] Bestehende Repositories in `src/Service/` verschieben:
  - `PageContentRepository.php` → `src/Repository/PageContentRepository.php`
  - `PageContentFileRepository.php` → `src/Repository/PageContentFileRepository.php`
  - `PageContentDatabaseRepository.php` → `src/Repository/PageContentDatabaseRepository.php`
- [ ] Schrittweise neue Repositories extrahieren (priorisiert nach Häufigkeit):
  1. `ConfigRepository` (aus ConfigService – 20+ Queries)
  2. `ResultRepository` (aus ResultService – 15+ Queries)
  3. `CatalogRepository` (aus CatalogService – 10+ Queries)
  4. `PlayerRepository` (aus PlayerService – 10+ Queries)
  5. `TeamRepository` (aus TeamService – 8+ Queries)
  6. `EventRepository` (aus EventService – 8+ Queries)
  7. `PageRepository` (aus PageService – 15+ Queries)
  8. `DomainRepository` (aus DomainService)
- [ ] Services delegieren Datenzugriff an Repository, enthalten nur Fachlogik
- [ ] Bestehende Tests anpassen

**Akzeptanzkriterium:**
- Kein SQL in Service-Klassen
- Alle Repositories in `src/Repository/`
- Services nutzen Repositories per Constructor Injection
- `composer test` grün

---

### 3.4 Doppelte Service-Instanziierungen eliminieren

**Problem (abhängig von 3.1):** PageService wird 8x instanziiert, PageSeoConfigService 8x,
LandingNewsService 3x, SettingsService 4x.

**Betroffene Stellen in `routes.php`:**
- `PageService`: Z.627, 1240, 1263, 1285, 1307, 1329, 1351, 1380
- `PageSeoConfigService`: Z.631, 1241, 1264, 1286, 1308, 1330, 1352, 1386
- `SettingsService`: Z.1807, 2815, 2921, 3018

**Aufgabe:**
- [ ] Wird durch DI-Container (3.1) automatisch gelöst
- [ ] Verifizieren, dass nach Container-Einführung keine manuellen Instanziierungen bleiben

**Akzeptanzkriterium:** Jeder Service wird genau einmal (lazy) instanziiert.

---

## Phase 4 – Modulare Architektur (Zielarchitektur aus AGENTS.md)

> **Ziel:** Die drei Module (Events, Inhalte, Admin) als physische
> Verzeichnisstruktur umsetzen. Modul-Grenzen durch Namespace-Isolation
> im PHP-Code erzwingen.

### 4.1 Modulverzeichnisse erstellen und Klassen zuordnen

**Zielstruktur:**
```
src/
├── Events/
│   ├── Controller/       → EventController, CatalogController, ResultController, ...
│   ├── Service/          → EventService, CatalogService, ResultService, TeamService, PlayerService
│   ├── Repository/       → EventRepository, CatalogRepository, ResultRepository, ...
│   └── Domain/           → Event (Entity), Catalog (Entity), Result (Entity)
├── Content/
│   ├── Controller/       → PageController, LandingpageController, WikiController, ...
│   ├── Service/          → PageService, DesignTokenService, CmsMenuService, ...
│   ├── Repository/       → PageRepository, PageModuleRepository, ...
│   └── Domain/           → Page (Entity), Module (Entity)
├── Admin/
│   ├── Controller/       → AdminController, SettingsController, DomainController, ...
│   ├── Service/          → TenantService, NamespaceService, UserService, ...
│   └── Repository/       → TenantRepository, NamespaceRepository, ...
├── Shared/
│   ├── Infrastructure/   → Database, Migrator, MailService
│   ├── Middleware/        → Auth, CSRF, RateLimit, LanguageMiddleware
│   └── Support/          → UsernameGuard, DomainNameHelper
```

**Aufgabe:**
- [ ] Verzeichnisstruktur anlegen
- [ ] Klassen-Zuordnung dokumentieren (welche Klasse in welches Modul)
- [ ] Schrittweise Migration: Ein Modul nach dem anderen verschieben
- [ ] PSR-4-Mapping in `composer.json` anpassen
- [ ] Imports in allen nutzenden Dateien aktualisieren
- [ ] Sicherstellen: Kein zirkulärer Import zwischen Events ↔ Content

**Akzeptanzkriterium:**
- Jedes Modul hat eigenes Verzeichnis
- Kein Service importiert direkt aus einem anderen Modul ohne Shared-Interface
- `composer test` + `phpstan` grün

---

### 4.2 Modul-Grenz-Enforcement in CI

**Aufgabe:**
- [ ] CI-Check: `Events/` darf nicht von `Content/` importieren (und umgekehrt)
- [ ] Erlaubte Imports: nur aus `Shared/` und eigenem Modul
- [ ] PHPStan-Rule oder deptrac-Konfiguration

**Akzeptanzkriterium:** CI schlägt fehl bei unerlaubten Cross-Modul-Imports.

---

## Phase 5 – Frontend-Professionalisierung

> **Ziel:** Monolithische JS/Twig-Dateien aufbrechen, Code-Duplikation eliminieren,
> Grundlage für Testbarkeit schaffen.

### 5.1 Marketing-Menu: Shared Utility extrahieren

**Problem:** 7 Dateien mit ~40-50% Code-Duplikation. `resolveCsrfToken()` 18x dupliziert,
`apiFetch()` 6x, `resolveNamespace()` 6x, `setFeedback()` 7x.

**Betroffene Dateien:**
```
public/js/marketing-menu-admin.js        (1.718 Zeilen)
public/js/marketing-menu-overview.js     (499 Zeilen)
public/js/marketing-menu-assignments.js  (517 Zeilen)
public/js/marketing-menu-standards.js    (352 Zeilen)
public/js/marketing-menu-overrides-list.js   (111 Zeilen)
public/js/marketing-menu-overrides-detail.js (369 Zeilen)
public/js/marketing-menu-tree.js         (1.352 Zeilen)
```

**Aufgabe:**
- [ ] Neue Datei `public/js/marketing-menu-common.js` erstellen (~200 Zeilen):
  - `resolveCsrfToken()`
  - `apiFetch(path, options)`
  - `resolveNamespace()`
  - `setFeedback(message, status)` / `hideFeedback()`
  - `normalizeBasePath(candidate)`
  - `appendQueryParam(path, key, value)` / `withNamespace(path)`
- [ ] Alle 7 Dateien refactoren: Duplizierte Funktionen durch Imports ersetzen
- [ ] Sicherstellen, dass das Laden ohne Build-Tool funktioniert (Script-Tags oder ES-Module)

**Akzeptanzkriterium:** Keine duplizierten Utility-Funktionen. ~500 Zeilen weniger.

---

### 5.2 admin.js in Feature-Module aufteilen

**Problem:** 11.535 Zeilen in einer Datei.

**Identifizierte Split-Points:**
- Dashboard-Rendering (Z.400-1000) → `admin-dashboard.js`
- Newsletter-Konfiguration (Z.10800-11090) → `admin-newsletter.js`
- Namespace-Management (Z.11094+) → `admin-namespaces.js`
- RAG-Chat-Settings (Z.10744-10803) → `admin-rag-settings.js`

**Aufgabe:**
- [ ] Feature-Module als separate Dateien extrahieren
- [ ] Shared State über `window.adminConfig` oder Event-Bus teilen
- [ ] `admin.js` als Orchestrator belassen, der Module initialisiert
- [ ] Lazy-Loading: Module nur laden, wenn Tab aktiv

**Akzeptanzkriterium:** admin.js < 3.000 Zeilen. Module isoliert testbar.

---

### 5.3 admin.twig in Twig-Partials aufbrechen

**Problem:** ~2.622 Zeilen in einer Template-Datei. 10+ Feature-Sections.

**Aufgabe:**
- [ ] Twig-Includes erstellen:
  - `templates/admin/dashboard.twig`
  - `templates/admin/events.twig`
  - `templates/admin/catalogs.twig`
  - `templates/admin/media.twig`
  - `templates/admin/teams.twig`
  - `templates/admin/results.twig`
  - `templates/admin/pages.twig`
  - `templates/admin/statistics.twig`
- [ ] `admin.twig` als Layout-Shell belassen mit `{% include %}` Blöcken
- [ ] Python HTML-Validity-Tests anpassen

**Akzeptanzkriterium:** admin.twig < 300 Zeilen. Partials einzeln editierbar.

---

### 5.4 block-content-editor.js aufteilen

**Problem:** 5.727 Zeilen. Enthält Block-Konfiguration, Validierung und UI.

**Aufgabe:**
- [ ] `public/js/components/block-types-config.js` extrahieren (~200 Zeilen):
  - `BLOCK_TYPE_LABELS`, `VARIANT_LABELS`, `SECTION_LAYOUT_OPTIONS`
  - `LEGACY_APPEARANCE_ALIASES`, `LEGACY_TOKEN_ALIASES`
- [ ] `public/js/components/block-validator.js` extrahieren (~800 Zeilen):
  - `sanitizeBlock()`, Validierungslogik
- [ ] `block-content-editor.js` behält UI/State-Management (~4.700 Zeilen)

**Akzeptanzkriterium:** Konfiguration und Validierung sind separat testbar.

---

## Phase 6 – Test-Abdeckung erhöhen

> **Ziel:** Die 51% ungetesteten Controller absichern. Fokus auf geschäftskritische
> und sicherheitsrelevante Bereiche.

### 6.1 Tests für ungetestete Event-Controller

**Ungetestet:**
- `DashboardController`
- `EventController`
- `EventListController`
- `EventConfigController`

**Aufgabe:**
- [ ] Controller-Tests für CRUD-Operationen schreiben
- [ ] Namespace-Isolation in Tests verifizieren (nach Phase 2)
- [ ] Edge Cases: leere Events, ungültige event_uid

---

### 6.2 Tests für ungetestete Admin-Controller

**Ungetestet (Auswahl der wichtigsten):**
- `NamespaceController`
- `OnboardingController` / `OnboardingSessionController`
- Alle 6 `MarketingMenu*Controller`
- `LandingpageController` / `LandingNewsController`
- `PageController` (diverse Varianten)
- `BackupController`
- `SystemMetricsController`

**Aufgabe:**
- [ ] Pro Controller mindestens Happy-Path + Auth-Check + Fehlerfälle testen
- [ ] Admin-Rechte-Prüfung testen (RoleAuthMiddleware)

---

### 6.3 E2E-Tests mit Playwright erweitern

**Problem:** Nur 1 Playwright-Spec (`page-editor.spec.js`). Kein E2E-Test
für den Quiz-Flow.

**Aufgabe:**
- [ ] Quiz-Flow E2E-Test: Event erstellen → QR-Code scannen → Quiz spielen → Ergebnis
- [ ] Admin-Login E2E-Test
- [ ] Onboarding-Flow E2E-Test
- [ ] In CI integrieren

---

### 6.4 Migrator: Rollback-Support evaluieren

**Problem:** 180 Migrationen ohne Rollback-Möglichkeit.

**Aufgabe:**
- [ ] Evaluieren: Separate `down_YYYYMMDD_*.sql`-Dateien neben Forward-Migrationen?
- [ ] Mindestens: Rollback-Hinweise als SQL-Kommentare in jede neue Migration
- [ ] `Migrator`-Klasse um optionale `rollback()`-Methode erweitern

---

## Phase 7 – AGENTS.md als lebendiges Dokument etablieren

> **Ziel:** AGENTS.md wird zum Single Source of Truth, der mit dem Code synchron bleibt.

### 7.1 Architektur-Decision-Records (ADRs) einführen

**Aufgabe:**
- [ ] Verzeichnis `docs/adr/` anlegen
- [ ] ADR-Template erstellen (Status, Kontext, Entscheidung, Konsequenzen)
- [ ] Rückwirkende ADRs für bestehende Entscheidungen:
  - ADR-001: Warum Text-Slugs statt UUIDs für Namespaces
  - ADR-002: Warum kein DI-Container (historisch)
  - ADR-003: Warum kein Frontend-Build-Tool
- [ ] Verweis von AGENTS.md auf ADR-Verzeichnis

---

### 7.2 CI-Check: AGENTS.md-Konsistenz

**Aufgabe:**
- [ ] Bei Migrations-Änderungen: CI prüft, ob AGENTS.md-Datenmodell noch stimmt
- [ ] Bei neuen Services: CI prüft, ob Namespace-Injection vorhanden
- [ ] Automatisierte Checks soweit möglich, Rest als Reviewer-Checkliste

---

## Abhängigkeitsmatrix

```
Phase 0 (Doku)        → keine Abhängigkeit, sofort machbar
Phase 1 (Aufräumen)   → keine Abhängigkeit, sofort machbar
Phase 2 (Namespace)   → nach Phase 0.1 (korrigierte Doku als Referenz)
Phase 3 (Backend)     → nach Phase 2 (Services haben dann namespace-Parameter)
  3.1 (DI-Container)  → eigenständig
  3.2 (routes split)  → nach 3.1
  3.3 (Repositories)  → nach 3.1
  3.4 (Duplikate)     → nach 3.1 (automatisch gelöst)
Phase 4 (Module)      → nach Phase 3 (saubere Struktur als Basis)
Phase 5 (Frontend)    → unabhängig, parallel zu Phase 2-4 machbar
Phase 6 (Tests)       → nach Phase 2 (Namespace-Tests) und Phase 3 (testbare Struktur)
Phase 7 (Governance)  → nach Phase 0, kontinuierlich
```

---

## Zusammenfassung

| Phase | Aufgaben | Aufwand (geschätzt) | Risiko |
|-------|----------|---------------------|--------|
| 0 – Dokumentation | 4 | Gering | Kein Risiko |
| 1 – Quick Wins | 4 | Gering | Geringes Risiko |
| 2 – Namespace-Scoping | 3 | Hoch | Mittleres Risiko (Datenmigration) |
| 3 – Backend-Struktur | 4 | Hoch | Mittleres Risiko (Refactoring) |
| 4 – Modularchitektur | 2 | Sehr hoch | Hohes Risiko (Breaking Changes) |
| 5 – Frontend | 4 | Mittel | Geringes Risiko |
| 6 – Tests | 4 | Mittel | Kein Risiko |
| 7 – Governance | 2 | Gering | Kein Risiko |
| **Gesamt** | **27 Aufgaben** | | |

---

*Erstellt: 2026-02-08*
*Basis: Architecture Review (`docs/architecture-review.md`), AGENTS.md, CONTRIBUTING.md, ROBOTS.md*
