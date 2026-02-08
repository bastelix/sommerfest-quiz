# Architecture Review – QuizRace / Sommerfest-Quiz

> Dieses Review bewertet die Ist-Architektur gegen die in `AGENTS.md`, `CONTRIBUTING.md`
> und `ROBOTS.md` definierten Regeln. Es identifiziert Abweichungen, fehlende Konzepte
> und unnötige Konstrukte, die eine stabile Weiterentwicklung gefährden.

---

## 1  AGENTS.md vs. Realität – Dokumentation stimmt nicht mit Code überein

### 1.1  `namespace_id UUID NOT NULL` existiert nicht

**AGENTS.md (Zeile 132)** verlangt:

```
namespace_id UUID NOT NULL
```

**Realität:** Kein einziges Migrationsfile (180 Dateien) enthält eine Spalte
`namespace_id`. Der Code nutzt durchgängig `namespace TEXT NOT NULL` als
String-Primary-Key. UUIDs kommen für Namespaces nirgends zum Einsatz.

**Betroffene Tabellen:** `namespaces`, `pages`, `namespace_profile`, `events`,
`user_namespaces`, `marketing_newsletter_configs`, `newsletter_campaigns`.

**Bewertung:** Die AGENTS.md beschreibt ein Datenmodell, das nie implementiert wurde.
Jeder Entwickler (Mensch oder AI), der sich an die Dokumentation hält, wird
inkompatiblen Code schreiben. Die AGENTS.md muss auf `namespace TEXT NOT NULL`
korrigiert werden, oder das Schema muss migriert werden.

### 1.2  „Jede Query ohne Namespace-Scope ist ein Architekturfehler" – massiv verletzt

Die AGENTS.md (Zeile 136) definiert als **verbindliche Regel:**

> Jede Query ohne Namespace-Scope gilt als Architekturfehler.

Folgende zentralen Services verletzen diese Regel systematisch:

| Service | Scoping | Befund |
|---------|---------|--------|
| `ConfigService` | nur `event_uid` | Kein `namespace`-Column in `config`-Tabelle. Namespace-Werte werden als `event_uid` gespeichert (Überladung). |
| `ResultService` | nur `event_uid` (optional!) | `event_uid`-Parameter ist optional, Default `''`. Ohne ihn: `DELETE FROM results` ohne Filter. `resolveCatalogUid()` hat null Scoping. |
| `CatalogService` | nur `event_uid` | Null Namespace-Bewusstsein. Kein `namespace`-Column in `catalogs`. |
| `TeamService` | nur `event_uid` | `getEventUidByName()` sucht global über alle Namespaces. |
| `PlayerService` | nur `event_uid` | Spielerdaten nicht nach Namespace isoliert. |

**Sicherheitsrisiko:** In einer Multi-Namespace-Umgebung kann ein Namespace potenziell
auf Daten anderer Namespaces zugreifen, wenn `event_uid` erraten oder manipuliert wird.

### 1.3  Drei Module existieren nicht als Architektur-Einheit

AGENTS.md definiert drei Module mit „harten Grenzen":
- **Events-Modul** – Quiz, Spiel, Auswertung
- **Inhalte-Modul** – Seiten, Design, SEO
- **Admin-Modul** – System, Domains, Abos

**Realität:** Es gibt keine physische Modulgrenze. Alle Services liegen in einem
flachen `src/Service/`-Verzeichnis (80+ Files). Controller sind nur nach
`Controller/`, `Controller/Admin/` und `Controller/Marketing/` aufgeteilt. Services
können frei aufeinander zugreifen – keine Package-Isolation, keine Interface-Grenzen
zwischen Modulen.

Die behauptete Modularität ist Dokumentation ohne Code-Gegenstück.

---

## 2  Fehlende Konzepte – wo Architektur nicht zu Ende gedacht wurde

### 2.1  Repository-Pattern: angefangen, nicht durchgezogen

**Soll (AGENTS.md Zeile 114):** Controller → Service → Repository (Datenzugriff)

**Ist:**
- 5 Repository-Klassen in `src/Repository/`
- 37 Service-Klassen mit direkten PDO-Queries (inline SQL)
- 3 weitere Repository-Klassen falsch platziert in `src/Service/` (`PageContentRepository`, `PageContentFileRepository`, `PageContentDatabaseRepository`)

**Verhältnis:** 37:5 – die überwältigende Mehrheit des Datenzugriffs umgeht das
Repository-Pattern. Services enthalten hunderte SQL-Statements direkt.

**Auswirkung:** Query-Logik ist über die gesamte Codebasis verstreut. Refactoring
von Tabellenstrukturen erfordert Änderungen in Dutzenden von Files statt an einer
zentralen Stelle.

### 2.2  Dependency Injection Container fehlt

**Soll (AGENTS.md Zeile 119-123):** Explizite Abhängigkeiten, keine versteckten Helper.

**Ist:** Kein DI-Container. Stattdessen manuelle Instanziierung von 30+ Services
pro Request in `src/routes.php` (3.742 Zeilen):

```php
// routes.php – jeder Request erzeugt alle Services
$configService = new ConfigService($pdo);
$eventService = new EventService($pdo, $configService, $tenantService, $sub);
$catalogService = new CatalogService($pdo, $configService, ...);
$resultService = new ResultService($pdo);
// ... 30+ weitere
```

Zusätzlich instanziieren einzelne Route-Handler nochmals eigene Services:

```php
// Zeile ~903, ~1240, ~1285 etc.
$configService = new ConfigService($pdo);  // Duplikate
$pageService = new PageService($pdo);      // Duplikate
```

**Probleme:**
- Alle Services werden eager erzeugt, egal welche Route aufgerufen wird
- Zwei DB-Connections pro Request (base + schema-spezifisch)
- Migrationen werden bei jedem Request geprüft (`MigrationRuntime::ensureUpToDate`)
- Kein Lazy-Loading möglich
- Neue Dependency = routes.php muss angepasst werden

### 2.3  Database-Klasse ist ein statischer Service-Locator

`src/Infrastructure/Database.php` nutzt statische Properties (`$factory`, `$connectHook`)
als globalen, mutablen State. Das widerspricht AGENTS.md Zeile 119-120:

> Kein Service Locator. Keine statischen Globals.

Controller wie `AdminController`, `LoginController`, `HomeController` rufen
`Database::connectFromEnv()` direkt auf, statt die PDO-Instanz injiziert zu
bekommen. Damit umgehen sie die Schema-Auswahl der Middleware und verbinden
sich potentiell mit dem falschen Schema.

### 2.4  Autoloading vorhanden, aber nicht genutzt

`composer.json` konfiguriert PSR-4 Autoloading (`"App\\": "src/"`), und
`public/index.php` lädt den Composer-Autoloader. Dennoch enthält `routes.php`
**73 redundante `require_once`-Statements** (Zeilen 173-245).

15 Controller funktionieren bereits ohne `require_once` rein über Autoloading.
Die 73 Statements sind Legacy-Artefakte und erzeugen unnötige Verwirrung darüber,
ob Autoloading zuverlässig funktioniert.

### 2.5  Kein Rollback-Support für Migrationen

AGENTS.md (Zeile 145) fordert vom AI-Assistenten: „Rollback-Hinweis".
Aber die `Migrator`-Klasse unterstützt keine Down-Migrationen. Alle 180
Migrationsdateien sind One-Way-SQL. Es existiert kein Mechanismus, um
Schemaänderungen rückgängig zu machen.

---

## 3  Überflüssiges und Unnötiges

### 3.1  routes.php als Gott-Datei (3.742 Zeilen)

Diese eine Datei ist:
- Router-Konfiguration
- DI-Container-Ersatz (Service-Instanziierung)
- Middleware-Definition (Inline-Closures)
- Request-Lifecycle-Manager
- Domain-Type-Resolver

Bei 3.742 Zeilen ist sie die komplexeste Datei der gesamten Codebasis und ein
Single Point of Failure für jede Änderung.

### 3.2  73 redundante require_once (siehe 2.4)

Rein technisch überflüssig, da PSR-4 Autoloading konfiguriert und aktiv ist.

### 3.3  Doppelte Service-Instanziierung in Route-Handlern

Mindestens `ConfigService`, `PageService` und weitere werden in der Middleware
instanziiert UND in einzelnen Route-Handlern nochmals `new`-instanziiert. Dies
erzeugt redundante Objekte und potentiell inkonsistenten State.

### 3.4  Frontend: admin.js (11.535 Zeilen) und admin.twig (153 KB)

Beide Dateien sind monolithisch und untragbar:
- `public/js/admin.js`: 11.535 Zeilen in einer Datei
- `templates/admin.twig`: ~153 KB Template-Code

Dies ist weder wartbar noch testbar. Änderungen an einem Admin-Feature
riskieren Seiteneffekte in allen anderen.

### 3.5  block-content-editor.js (202 KB) und block-renderer-matrix-data.js (99 KB)

Zwei einzelne JS-Dateien mit zusammen 301 KB. Ohne Build-Tool werden diese bei
jedem Page-Load vollständig geladen. `block-renderer-matrix-data.js` enthält
hardcodierte Block-Definitionen, die über eine API geladen werden könnten.

### 3.6  6 separate marketing-menu-*.js Dateien

```
marketing-menu-admin.js        (1.718 Zeilen)
marketing-menu-overview.js     (880 Zeilen)
marketing-menu-assignments.js  (1.352 Zeilen)
marketing-menu-standards.js    (880 Zeilen)
marketing-menu-overrides-list.js   (880 Zeilen)
marketing-menu-overrides-detail.js (880 Zeilen)
marketing-menu-tree.js         (1.352 Zeilen)
```

7 Dateien für ein Feature (Menü-Verwaltung), ohne gemeinsames Modul oder
Abstraktion. Code-Duplikation zwischen den Dateien ist wahrscheinlich.

---

## 4  Architektur-Widersprüche

### 4.1  „Progressive Enhancement" vs. JavaScript-Abhängigkeit

AGENTS.md (Zeile 159-160): „HTML funktioniert ohne JavaScript."

**Realität:**
- Quiz-Interface benötigt JavaScript vollständig
- Admin-Interface benötigt JavaScript vollständig
- Theme-Switching funktioniert nur mit JavaScript
- Nur 1 `<noscript>`-Tag in der gesamten Codebasis (in calserver.twig)
- Kein einziges Form-basiertes Fallback für AJAX-Aktionen

Progressive Enhancement ist dokumentiert, aber nicht implementiert.

### 4.2  „Keine Framework-Magie" vs. statische Database-Klasse

AGENTS.md (Zeile 101): „Keine Framework-Magie."

Die `Database`-Klasse mit statischem Factory-Pattern, globalem State und
Connect-Hooks ist effektiv ein versteckter Service-Locator – genau die Art
von Magie, die vermieden werden soll.

### 4.3  „Controller: keine Fachlogik" vs. direkte DB-Aufrufe

AGENTS.md (Zeile 112): „Controller: HTTP-spezifisch, keine Fachlogik."

Mehrere Controller rufen `Database::connectFromEnv()` direkt auf und führen
datenbanknahe Operationen durch, statt Services zu nutzen.

### 4.4  ROBOTS.md „Prefer dependency injection" vs. statische Database-Aufrufe

ROBOTS.md (Zeile 29-30): „Prefer dependency injection and constructor arguments
over accessing globals or superglobals inside services."

`Database::connectFromEnv()` ist ein statischer Aufruf, der Environment-Variablen
liest – das Gegenteil von Constructor Injection.

---

## 5  Test-Abdeckung – Lücken

| Metrik | Wert |
|--------|------|
| PHP-Source-Dateien | 296 |
| PHP-Test-Dateien | 151 |
| Controller mit Tests | 44 von 89 (49%) |
| Controller ohne Tests | 45 (51%) |
| Test-zu-Code-Verhältnis | 44% |

**Ungetestete kritische Controller (Auswahl):**
- `DashboardController`, `EventController`, `EventListController`
- `NamespaceController`, `OnboardingController`
- Alle `MarketingMenu*Controller` (6 Stück)
- `PageController` (diverse Varianten)
- `LandingpageController`, `LandingNewsController`

**E2E-Tests:** Nur 1 Playwright-Spec (`page-editor.spec.js`). Kein E2E-Test
für den Quiz-Flow selbst.

---

## 6  PostgreSQL-Version-Diskrepanz

- `docker-compose.yml`: PostgreSQL **15**
- `docker-compose.test.yml`: PostgreSQL **16**
- `helm/values.yaml`: PostgreSQL **15**

Tests laufen gegen eine andere DB-Version als Produktion.

---

## 7  Helm-Chart: Fehlende Health-Probes

Das Kubernetes-Deployment (`helm/sommerfest-quiz/templates/deployment.yaml`) hat
**keine liveness- oder readiness-Probes** für den Application-Container. Ein
hängender PHP-Prozess würde nicht automatisch neu gestartet.

Der `db-init-job.yaml` referenziert `docs/schema.sql` – eine Datei, die im
Repository nicht eindeutig für diesen Zweck existiert.

---

## 8  Priorisierte Empfehlungen

### Sofort (Stabilität)

1. **AGENTS.md korrigieren:** `namespace_id UUID` → `namespace TEXT` (Doku an Code
   anpassen, nicht umgekehrt)
2. **Namespace-Scoping nachrüsten** in `config`, `results`, `question_results`,
   `teams`, `catalogs`, `questions` (erfordert Migrations + Service-Änderungen)
3. **73 require_once entfernen** aus routes.php

### Kurzfristig (Wartbarkeit)

4. **routes.php aufbrechen** in modulare Route-Files (events.routes.php,
   admin.routes.php, marketing.routes.php)
5. **Leichtgewichtigen DI-Container** einführen (z.B. PHP-DI oder Slim's
   eingebauter Container) für Lazy-Instantiation
6. **Repository-Pattern konsequent** für alle DB-Zugriffe umsetzen
7. **admin.js und admin.twig** in modulare Komponenten aufteilen

### Mittelfristig (Architektur)

8. **Modul-Grenzen** als physische Verzeichnisstruktur umsetzen
   (`src/Events/`, `src/Content/`, `src/Admin/`)
9. **Frontend Build-Tool** evaluieren (Vite) für Code-Splitting
10. **Test-Abdeckung** für die 45 ungetesteten Controller erhöhen
11. **PostgreSQL-Version** in Test und Produktion angleichen
12. **Kubernetes Health-Probes** hinzufügen

---

*Review erstellt: 2026-02-08*
*Basis: AGENTS.md, CONTRIBUTING.md, ROBOTS.md, Quellcode-Analyse aller Module*
