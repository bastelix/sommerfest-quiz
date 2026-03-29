# Verbesserungen & Roadmap

Systematische Code-Analyse mit konkreten Verbesserungsvorschlägen. Jeder Eintrag enthält einen Prompt, der direkt in Claude Code eingefügt werden kann.

---

## Priorität: Hoch

---

## [Sicherheit] CORS-Konfiguration – Origin-Validierung fehlt

**Problem:** Der `ResponseEmitter` (`src/Application/ResponseEmitter/ResponseEmitter.php`) spiegelt den `HTTP_ORIGIN`-Header ohne Validierung zurück und setzt gleichzeitig `Access-Control-Allow-Credentials: true`. Jede beliebige Origin kann dadurch authentifizierte Requests stellen.
**Impact:** 🔴 Hoch
**Aufwand:** S
**Betroffene Dateien:** `src/Application/ResponseEmitter/ResponseEmitter.php`

### Umsetzungs-Prompt

> Öffne `src/Application/ResponseEmitter/ResponseEmitter.php`. Aktuell wird `$_SERVER['HTTP_ORIGIN']` ungefiltert in den `Access-Control-Allow-Origin`-Header geschrieben, während `Access-Control-Allow-Credentials: true` gesetzt ist. Das ist eine CORS-Sicherheitslücke.
>
> Erstelle eine Whitelist erlaubter Origins. Lese die erlaubten Domains aus der Umgebungsvariable `CORS_ALLOWED_ORIGINS` (kommasepariert). Falls die Variable nicht gesetzt ist, erlaube nur die `DOMAIN` und `MAIN_DOMAIN` aus der `.env`. Prüfe den eingehenden Origin gegen diese Whitelist, bevor du ihn zurückspiegelst. Ist der Origin nicht erlaubt, setze keinen `Access-Control-Allow-Origin`-Header.
>
> Teste danach: Sende einen Request mit `Origin: https://evil.example.com` – der Response darf keinen `Access-Control-Allow-Origin`-Header enthalten.

---

## [Sicherheit] OAuth-Scopes für Tickets und Customer fehlen

**Problem:** `NamespaceTicketController` definiert `SCOPE_TICKET_READ` und `SCOPE_TICKET_WRITE`, aber diese Scopes fehlen in der erlaubten Scope-Liste des `OAuthController` (`src/Controller/Api/OAuthController.php`). Gleiches gilt für `customer:read`/`customer:write` aus `CustomerController`.
**Impact:** 🔴 Hoch
**Aufwand:** S
**Betroffene Dateien:** `src/Controller/Api/OAuthController.php`, `src/Controller/Api/V1/NamespaceTicketController.php`, `src/Controller/Api/V1/CustomerController.php`

### Umsetzungs-Prompt

> Öffne `src/Controller/Api/OAuthController.php`. Suche die Liste der erlaubten OAuth-Scopes (circa Zeile 19-28). Dort fehlen die Scopes `ticket:read`, `ticket:write`, `customer:read` und `customer:write`. Ergänze diese vier Scopes in der erlaubten Liste, analog zu den bestehenden Einträgen (z.B. `cms:read`, `news:write`).
>
> Prüfe danach, dass `NamespaceTicketController::SCOPE_TICKET_READ` und `SCOPE_TICKET_WRITE` mit den neuen Einträgen übereinstimmen. Aktualisiere auch die MCP-Connector-Doku `docs/mcp-connector-setup.md`, falls die Scopes dort noch nicht gelistet sind.
>
> Teste: Ein OAuth-Token-Request mit `scope=ticket:read ticket:write` darf keinen Fehler mehr werfen.

---

## [Performance] Fehlende Pagination in API- und MCP-List-Endpoints

**Problem:** Alle List-Endpoints (API v1 und MCP-Tools) liefern alle Datensätze ohne Limit zurück. Bei großen Namespaces mit vielen Seiten/Tickets/News führt das zu hohem Speicherverbrauch und langen Antwortzeiten.
**Impact:** 🔴 Hoch
**Aufwand:** M
**Betroffene Dateien:** `src/Controller/Api/V1/NamespacePageController.php`, `src/Controller/Api/V1/NamespaceTicketController.php`, `src/Controller/Api/V1/NamespaceNewsController.php`, `src/Controller/Api/V1/NamespaceQuizController.php`, `src/Service/Mcp/PageTools.php`, `src/Service/Mcp/TicketTools.php`, `src/Service/Mcp/NewsTools.php`

### Umsetzungs-Prompt

> Implementiere Pagination für alle API-v1-List-Endpoints und MCP-List-Tools. Beginne mit `src/Controller/Api/V1/NamespacePageController.php` Methode `list()`.
>
> 1. Akzeptiere Query-Parameter `offset` (int, default 0) und `limit` (int, default 50, max 200).
> 2. Reiche diese an `PageService::getAllForNamespace()` weiter – ergänze die Methode in `src/Service/PageService.php` um `LIMIT` und `OFFSET` in der SQL-Query.
> 3. Gib im Response zusätzlich `"pagination": { "offset": 0, "limit": 50, "total": 123 }` zurück.
> 4. Wende dasselbe Muster auf `NamespaceTicketController::list()`, `NamespaceNewsController::list()` und `NamespaceQuizController::listEvents()` an.
> 5. Ergänze in den MCP-Tools (`PageTools`, `TicketTools`, `NewsTools`) die Parameter `offset` und `limit` in den `definitions()` und leite sie an die Services weiter.
>
> Teste: `GET /api/v1/namespaces/test/pages?limit=2&offset=0` liefert maximal 2 Seiten und ein `pagination`-Objekt.

---

## [MCP-Härtung] Fehlende Try-Catch-Blöcke in MCP-Tools

**Problem:** MCP-Tools wie `NewsTools` und `TicketTools` werfen Exceptions direkt (`InvalidArgumentException`, `RuntimeException`), statt sie in strukturierte Fehlerantworten zu wrappen. Die API-Controller machen es richtig (try-catch mit JSON-Error-Response), die MCP-Tools nicht.
**Impact:** 🔴 Hoch
**Aufwand:** M
**Betroffene Dateien:** `src/Service/Mcp/NewsTools.php`, `src/Service/Mcp/TicketTools.php`, `src/Service/Mcp/QuizTools.php`, `src/Service/Mcp/WikiTools.php`, `src/Service/Mcp/McpToolRegistry.php`

### Umsetzungs-Prompt

> Prüfe `src/Service/Mcp/McpToolRegistry.php` Methode `callTool()`. Stelle sicher, dass dort ein globaler try-catch existiert, der alle Exceptions fängt und als strukturierte MCP-Fehlerantwort zurückgibt (Format: `{ "isError": true, "content": [{ "type": "text", "text": "Fehlermeldung" }] }`).
>
> Prüfe dann in `src/Service/Mcp/NewsTools.php` die Methode `createNews()` (ca. Zeile 145-166): Dort wird `new DateTimeImmutable($args['publishedAt'])` ohne try-catch aufgerufen. Ungültige Datumsstrings werfen eine Exception. Wrappe den Aufruf in try-catch und gib eine verständliche Fehlermeldung zurück.
>
> Wende dasselbe Muster auf alle Handler-Methoden in `TicketTools.php`, `QuizTools.php` und `WikiTools.php` an, die Dates parsen oder externe Services aufrufen.
>
> Teste: Rufe `create_news` mit `publishedAt: "kein-datum"` auf – die Antwort muss ein strukturierter Fehler sein, kein Stack-Trace.

---

## [MCP-Härtung] Duplizierter resolveNamespace()-Code in allen Tool-Klassen

**Problem:** Alle 9 MCP-Tool-Klassen implementieren identischen `resolveNamespace()`-Code und identische Input-Validierungspatterns. Das sind ~45 Stellen mit duplizierter Logik.
**Impact:** 🟡 Mittel
**Aufwand:** S
**Betroffene Dateien:** `src/Service/Mcp/PageTools.php`, `src/Service/Mcp/MenuTools.php`, `src/Service/Mcp/NewsTools.php`, `src/Service/Mcp/FooterTools.php`, `src/Service/Mcp/QuizTools.php`, `src/Service/Mcp/StylesheetTools.php`, `src/Service/Mcp/WikiTools.php`, `src/Service/Mcp/TicketTools.php`, `src/Service/Mcp/BackupTools.php`

### Umsetzungs-Prompt

> Erstelle einen neuen Trait `src/Service/Mcp/McpToolTrait.php` mit folgenden Methoden:
>
> 1. `resolveNamespace(array $args): string` – die gemeinsame Namespace-Auflösung
> 2. `requireString(array $args, string $key): string` – wirft Exception wenn leer
> 3. `requireInt(array $args, string $key): int` – castet und prüft > 0
> 4. `optionalString(array $args, string $key, string $default = ''): string`
> 5. `optionalInt(array $args, string $key, int $default = 0): int`
> 6. `optionalBool(array $args, string $key, ?bool $default = null): ?bool`
>
> Nutze den Trait in allen 9 Tool-Klassen und ersetze die duplizierten Methoden. Die `$defaultNamespace`-Property bleibt als Constructor-Parameter.
>
> Teste: Alle bestehenden MCP-Tool-Aufrufe müssen weiterhin funktionieren. PHPStan darf keine neuen Fehler melden.

---

## Priorität: Mittel

---

## [Performance] Fehlende Datenbank-Indizes auf Namespace-Spalten

**Problem:** Die `pages`- und `events`-Tabellen haben zwar Unique-Constraints auf `(namespace, slug)`, aber keinen einzelnen Index auf `namespace` für List-Queries. `PageService::getAllForNamespace()` und `EventService::getAll()` filtern nach `namespace` und profitieren von einem dedizierten Index.
**Impact:** 🟡 Mittel
**Aufwand:** S
**Betroffene Dateien:** `migrations/` (neue Migration)

### Umsetzungs-Prompt

> Erstelle eine neue Migration `migrations/YYYYMMDD_add_namespace_indexes.sql` (mit aktuellem Datum). Füge folgende Indizes hinzu:
>
> ```sql
> CREATE INDEX IF NOT EXISTS idx_pages_namespace ON pages (namespace);
> CREATE INDEX IF NOT EXISTS idx_events_namespace ON events (namespace);
> CREATE INDEX IF NOT EXISTS idx_landing_news_namespace ON landing_news (namespace);
> CREATE INDEX IF NOT EXISTS idx_tickets_namespace ON tickets (namespace);
> CREATE INDEX IF NOT EXISTS idx_cms_footer_blocks_namespace ON cms_footer_blocks (namespace);
> CREATE INDEX IF NOT EXISTS idx_cms_menus_namespace ON cms_menus (namespace);
> ```
>
> Teste: `php scripts/run_migrations.php` muss fehlerfrei durchlaufen. Prüfe mit `EXPLAIN ANALYZE SELECT * FROM pages WHERE namespace = 'test'` dass der Index verwendet wird.

---

## [API-Design] Inkonsistente Fehler-Responses über API-Controller hinweg

**Problem:** API-Controller verwenden unterschiedliche Error-Formate. Manche geben `{ "error": "code" }` zurück, andere `{ "error": "code", "message": "..." }`, wieder andere `{ "error": "code", "details": [...] }`. Es fehlt ein standardisiertes Error-Format.
**Impact:** 🟡 Mittel
**Aufwand:** M
**Betroffene Dateien:** `src/Controller/Api/V1/NamespacePageController.php`, `src/Controller/Api/V1/NamespaceTicketController.php`, `src/Controller/Api/V1/NamespaceNewsController.php`, `src/Controller/Api/V1/NamespaceQuizController.php`

### Umsetzungs-Prompt

> Erstelle einen Trait oder eine Methode in einem gemeinsamen Base-Controller `src/Controller/Api/V1/AbstractApiController.php` mit der Methode `errorResponse(Response $response, int $status, string $code, string $message = '', array $details = []): Response`.
>
> Das einheitliche Format soll sein:
> ```json
> { "error": "error_code", "message": "Human-readable message", "details": [] }
> ```
>
> Ersetze alle individuellen Error-Response-Patterns in den 4 API-v1-Controllern durch Aufrufe dieser Methode. Achte darauf, dass bestehende Error-Codes (`invalid_json`, `missing_scope`, `not_found`, etc.) beibehalten werden.
>
> Teste: Alle bestehenden API-Tests müssen weiterhin bestehen. Error-Responses müssen konsistent die drei Felder enthalten.

---

## [Performance] Fehlender Transaction-Support bei Page-Upsert

**Problem:** `NamespacePageController::upsert()` führt mehrere separate DB-Operationen durch (Seite speichern, SEO-Config speichern, Menu-Assignments erstellen) ohne Transaktions-Klammer. Schlägt Schritt 3 fehl, ist die Seite bereits gespeichert.
**Impact:** 🟡 Mittel
**Aufwand:** S
**Betroffene Dateien:** `src/Controller/Api/V1/NamespacePageController.php`

### Umsetzungs-Prompt

> Öffne `src/Controller/Api/V1/NamespacePageController.php`, Methode `upsert()`. Wrappe die gesamte Speicherlogik (Page upsert + SEO config + Menu assignments) in eine DB-Transaktion:
>
> ```php
> $pdo->beginTransaction();
> try {
>     // ... bestehende Logik ...
>     $pdo->commit();
> } catch (\Throwable $e) {
>     $pdo->rollBack();
>     throw $e;
> }
> ```
>
> Die PDO-Instanz sollte über den Constructor oder Container verfügbar sein. Falls nicht, injiziere sie.
>
> Teste: Erstelle eine Seite mit ungültigem `menuAssignments`-Payload. Die Seite darf NICHT gespeichert werden, wenn die Assignments fehlschlagen.

---

## [MCP-Härtung] Fehlende Filter- und Sortieroptionen in MCP-List-Tools

**Problem:** MCP-List-Tools wie `list_pages` und `list_news` unterstützen keine Filterung (z.B. nach Status, Sprache) und keine Sortierung. `list_tickets` hat Filter, aber `list_pages` nicht.
**Impact:** 🟡 Mittel
**Aufwand:** M
**Betroffene Dateien:** `src/Service/Mcp/PageTools.php`, `src/Service/Mcp/NewsTools.php`

### Umsetzungs-Prompt

> Erweitere `src/Service/Mcp/PageTools.php` Tool `list_pages`:
>
> 1. Füge die optionalen Parameter `status` (draft/published), `language`, `type` und `parentId` zur `inputSchema` hinzu.
> 2. Leite die Filter an `PageService::getAllForNamespace()` weiter – ergänze die Service-Methode um optionale WHERE-Klauseln.
> 3. Füge einen `sortBy`-Parameter hinzu (Werte: `title`, `slug`, `updatedAt`; Default: `title`).
>
> Wende dasselbe Muster auf `NewsTools::listNews()` an (Filter: `isPublished`, Sort: `publishedAt`, `title`).
>
> Teste: `list_pages({ status: "published" })` liefert nur veröffentlichte Seiten.

---

## [Code-Qualität] Services mit direkten PDO-Queries statt Repository-Pattern

**Problem:** 37 Service-Klassen enthalten direkte SQL-Queries via PDO, während nur 5 Repository-Klassen existieren. Das Repository-Pattern wurde begonnen, aber nicht durchgezogen (Verhältnis 37:5).
**Impact:** 🟡 Mittel
**Aufwand:** XL
**Betroffene Dateien:** `src/Service/PageService.php`, `src/Service/LandingNewsService.php`, `src/Service/CmsMenuDefinitionService.php`, `src/Service/TicketService.php` u.v.m.

### Umsetzungs-Prompt

> Dies ist ein großes Refactoring. Beginne mit den am häufigsten genutzten Services:
>
> 1. Erstelle `src/Repository/PageRepository.php` und extrahiere alle SQL-Queries aus `src/Service/PageService.php` dorthin. Der Service ruft dann nur noch Repository-Methoden auf.
> 2. Erstelle `src/Repository/TicketRepository.php` analog für `src/Service/TicketService.php`.
> 3. Erstelle `src/Repository/NewsRepository.php` analog für `src/Service/LandingNewsService.php`.
>
> Muster: Jede Repository-Methode nimmt primitive Parameter entgegen und gibt Arrays oder Domain-Objekte zurück. Kein Business-Logic im Repository.
>
> Teste: Alle bestehenden Tests müssen bestehen. PHPStan Level 4 darf keine neuen Fehler zeigen.

---

## [DX] Fehlende PHPStan-Konfiguration und CS-Fixer

**Problem:** PHPStan läuft auf Level 4. Es fehlt ein automatischer Code-Style-Fixer (php-cs-fixer) und PHPStan könnte auf Level 5+ angehoben werden.
**Impact:** 🟡 Mittel
**Aufwand:** M
**Betroffene Dateien:** `phpstan.neon.dist`, `composer.json`, `.php-cs-fixer.dist.php` (neu)

### Umsetzungs-Prompt

> 1. Erstelle `.php-cs-fixer.dist.php` mit einer PSR-12-Konfiguration für das `src/`-Verzeichnis.
> 2. Füge `friendsofphp/php-cs-fixer` als Dev-Dependency zu `composer.json` hinzu.
> 3. Ergänze in `composer.json` unter `scripts`: `"cs-fix": "php-cs-fixer fix"` und `"cs-check": "php-cs-fixer fix --dry-run --diff"`.
> 4. Hebe in `phpstan.neon.dist` das Level von 4 auf 5 an. Behebe die neu auftretenden Fehler oder füge sie als `ignoreErrors` hinzu.
> 5. Ergänze den `cs-check`-Schritt im GitHub Actions Workflow `tests.yml`.
>
> Teste: `composer cs-check` meldet Style-Verstöße. `composer cs-fix` behebt sie. `vendor/bin/phpstan analyse` auf Level 5 zeigt keine unbehandelten Fehler.

---

## Priorität: Niedrig

---

## [Modernisierung] Veraltetes `POSTGRES_PASS`-Alias

**Problem:** Das Entrypoint-Skript unterstützt noch `POSTGRES_PASS` als Alias für `POSTGRES_PASSWORD` mit Deprecation-Warnung. Dieses Legacy-Mapping sollte entfernt werden.
**Impact:** 🟢 Niedrig
**Aufwand:** S
**Betroffene Dateien:** `docker-entrypoint.sh`, `sample.env`

### Umsetzungs-Prompt

> Öffne `docker-entrypoint.sh`. Suche nach `POSTGRES_PASS` und entferne das Mapping auf `POSTGRES_PASSWORD`. Entferne auch eventuelle Kommentare dazu. Stelle sicher, dass `sample.env` nur `POSTGRES_PASSWORD` dokumentiert und kein `POSTGRES_PASS` mehr enthält.
>
> Teste: Docker-Container startet fehlerfrei mit `POSTGRES_PASSWORD`. Mit `POSTGRES_PASS` soll der Container einen klaren Fehler werfen.

---

## [Fehlende Features] REST-API für Footer-Blöcke fehlt

**Problem:** Footer-Blöcke können nur über MCP-Tools und die Admin-UI verwaltet werden. Es gibt keinen REST-API-v1-Controller für Footer-Operationen, obwohl die Scopes `footer:read`/`footer:write` im OAuth definiert sind.
**Impact:** 🟢 Niedrig
**Aufwand:** M
**Betroffene Dateien:** `src/Controller/Api/V1/` (neue Datei), `src/Routes/api_v1.php`

### Umsetzungs-Prompt

> Erstelle `src/Controller/Api/V1/NamespaceFooterController.php` mit folgenden Endpoints:
>
> - `GET /api/v1/namespaces/{ns}/footer/blocks` – Blöcke auflisten (Scope: `footer:read`)
> - `POST /api/v1/namespaces/{ns}/footer/blocks` – Block erstellen (Scope: `footer:write`)
> - `PATCH /api/v1/namespaces/{ns}/footer/blocks/{id}` – Block aktualisieren (Scope: `footer:write`)
> - `DELETE /api/v1/namespaces/{ns}/footer/blocks/{id}` – Block löschen (Scope: `footer:write`)
> - `GET /api/v1/namespaces/{ns}/footer/layout` – Layout abrufen (Scope: `footer:read`)
> - `PUT /api/v1/namespaces/{ns}/footer/layout` – Layout aktualisieren (Scope: `footer:write`)
>
> Nutze den bestehenden `CmsFooterBlockService`. Registriere die Routes in `src/Routes/api_v1.php` analog zu den bestehenden Endpoints.
>
> Teste: `GET /api/v1/namespaces/test/footer/blocks` mit gültigem Token und `footer:read`-Scope liefert die Footer-Blöcke.

---

## [Fehlende Features] Batch-Operationen in MCP-Tools

**Problem:** Alle MCP-Tools arbeiten auf Einzelobjekten. Für Workflows wie „erstelle 5 Seiten" oder „lösche alle Entwürfe" sind 5+ separate Tool-Calls nötig.
**Impact:** 🟢 Niedrig
**Aufwand:** L
**Betroffene Dateien:** `src/Service/Mcp/PageTools.php`, `src/Service/Mcp/TicketTools.php`

### Umsetzungs-Prompt

> Erweitere `src/Service/Mcp/PageTools.php` um ein neues Tool `batch_upsert_pages`:
>
> - Input: `{ "namespace": "...", "pages": [{ "slug": "...", "blocks": [...], ... }] }`
> - Verarbeite jede Seite in einer Transaktion. Bei Fehler: Rollback und Fehlermeldung für die betroffene Seite.
> - Rückgabe: `{ "results": [{ "slug": "...", "status": "ok" }, { "slug": "...", "status": "error", "message": "..." }] }`
>
> Ergänze analog `batch_transition_tickets` in `TicketTools.php` (mehrere Tickets gleichzeitig den Status ändern).
>
> Registriere die neuen Tools in der jeweiligen `definitions()`-Methode. Teste: `batch_upsert_pages` mit 3 Seiten erstellt alle 3 in einer Transaktion.

---

## [Quick Win] Fehlende `CHANGELOG.md`-Verlinkung in der Doku

**Problem:** Das Changelog wird automatisch via git-cliff generiert, ist aber nicht in der MkDocs-Navigation verlinkt.
**Impact:** 🟢 Niedrig
**Aufwand:** S
**Betroffene Dateien:** `mkdocs.yml`, `CHANGELOG.md`

### Umsetzungs-Prompt

> Prüfe ob `CHANGELOG.md` im Root existiert. Falls ja, kopiere es nach `docs/changelog.md` (oder erstelle einen Symlink) und füge es in `mkdocs.yml` unter „Entwicklung" ein:
>
> ```yaml
>   - Entwicklung:
>     - Verbesserungen & Roadmap: improvements.md
>     - Changelog: changelog.md
> ```
>
> Falls `CHANGELOG.md` nicht existiert, erstelle einen initialen Eintrag mit dem aktuellen Doku-Update.

---

## Zusammenfassung

| Kategorie | Anzahl | Priorität |
|---|---|---|
| Sicherheit | 2 | 🔴 Hoch |
| Performance | 3 | 🔴/🟡 Hoch/Mittel |
| MCP-Härtung | 3 | 🔴/🟡 Hoch/Mittel |
| API-Design | 1 | 🟡 Mittel |
| Code-Qualität | 1 | 🟡 Mittel |
| DX | 1 | 🟡 Mittel |
| Modernisierung | 1 | 🟢 Niedrig |
| Fehlende Features | 3 | 🟢 Niedrig |
