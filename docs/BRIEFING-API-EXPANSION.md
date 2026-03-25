# Briefing: API v1 Erweiterung – Vollständige Seitenerstellung via KI-Agent

> **Ziel:** Die bestehende REST-API so erweitern, dass ein KI-Agent per Prompt komplette Marketing-Seiten erstellen kann – inklusive Navigation, Content-Blöcken, Footer, Page-Modulen, SEO und Design-Tokens.

---

## 1. Systemübersicht

### Tech-Stack

| Komponente     | Technologie                                    |
|----------------|------------------------------------------------|
| Backend        | PHP 8.2+, Slim Framework 4                     |
| Templates      | Twig                                           |
| Datenbank      | PostgreSQL                                     |
| Frontend       | Vanilla JS, UIkit 3, CSS Custom Properties     |
| Architektur    | MVC (Controller → Service → Repository/Domain) |

### Multi-Tenancy

Das gesamte System ist **Namespace-basiert**. Jede Entität (Seite, Menü, Footer-Block, Design-Token) gehört zu einem `namespace`-String (z.B. `edocs`, `calhelp`, `aurora`). Der Default-Namespace ist `edocs`.

### Authentifizierung

- Bearer-Token via `ApiTokenAuthMiddleware`
- Token-Tabelle: `namespace_api_tokens` mit `scopes` (JSONB-Array)
- Jeder API-Request wird gegen den Token-Namespace geprüft: Der `{ns}`-Parameter in der URL muss mit dem Namespace des Tokens übereinstimmen
- Scopes steuern den Zugriff granular (z.B. `cms:read`, `cms:write`, `seo:write`)

### Schlüsseldateien

| Zweck                     | Pfad                                                    |
|---------------------------|---------------------------------------------------------|
| API-Routen                | `src/Routes/api_v1.php`                                 |
| Auth-Middleware            | `src/Application/Middleware/ApiTokenAuthMiddleware.php`  |
| Page-Controller (Vorlage) | `src/Controller/Api/V1/NamespacePageController.php`     |
| Menu-Controller (Vorlage) | `src/Controller/Api/V1/NamespaceMenuController.php`     |
| News-Controller (Vorlage) | `src/Controller/Api/V1/NamespaceNewsController.php`     |

---

## 2. Bestehende API v1

Basis-URL: `/api/v1/namespaces/{ns}/...`

URL-Parameter `{ns}` ist ein Namespace-Slug: `[a-z0-9\-]+`

### Pages

| Methode | Pfad             | Scope        | Beschreibung                          |
|---------|------------------|--------------|---------------------------------------|
| GET     | `/pages`         | `cms:read`   | Liste aller Seiten (nur Metadaten)    |
| GET     | `/pages/tree`    | `cms:read`   | Seitenbaum-Hierarchie                 |
| PUT     | `/pages/{slug}`  | `cms:write`  | Seite erstellen oder aktualisieren    |

**PUT `/pages/{slug}` – Payload:**
```json
{
  "blocks": [ /* Block-Contract-Objekte */ ],
  "meta": { /* Seiten-Metadaten */ },
  "title": "Seitentitel",
  "status": "draft" | "published",
  "seo": { /* SEO-Konfiguration, erfordert scope seo:write */ },
  "menuAssignments": [ /* Menu-Slot-Zuweisungen, erfordert scope menu:write */ ]
}
```

### Menus

| Methode | Pfad                         | Scope         | Beschreibung              |
|---------|------------------------------|---------------|---------------------------|
| GET     | `/menus`                     | `menu:read`   | Alle Menüs listen         |
| POST    | `/menus`                     | `menu:write`  | Neues Menü erstellen      |
| PATCH   | `/menus/{menuId}`            | `menu:write`  | Menü aktualisieren        |
| DELETE  | `/menus/{menuId}`            | `menu:write`  | Menü löschen              |
| GET     | `/menus/{menuId}/items`      | `menu:read`   | Menü-Einträge listen      |
| POST    | `/menus/{menuId}/items`      | `menu:write`  | Menü-Eintrag erstellen    |
| PATCH   | `/menus/{menuId}/items/{id}` | `menu:write`  | Menü-Eintrag aktualisieren|
| DELETE  | `/menus/{menuId}/items/{id}` | `menu:write`  | Menü-Eintrag löschen      |

### News

| Methode | Pfad          | Scope         | Beschreibung            |
|---------|---------------|---------------|-------------------------|
| GET     | `/news`       | `news:read`   | Alle News listen        |
| GET     | `/news/{id}`  | `news:read`   | Einzelne News lesen     |
| POST    | `/news`       | `news:write`  | News erstellen          |
| PATCH   | `/news/{id}`  | `news:write`  | News aktualisieren      |
| DELETE  | `/news/{id}`  | `news:write`  | News löschen            |

---

## 3. Block-System

Das Block-System ist der Kern des Page Builders. Jede Seite besteht aus einem Array von Block-Objekten.

### Block-Struktur

```typescript
interface Block {
  id: string;          // Eindeutige Block-ID (z.B. UUID)
  type: BlockType;     // Einer der 15 Block-Typen
  variant: string;     // Variante des Block-Typs
  data: object;        // Typenspezifische Daten
  tokens?: Tokens;     // Optionale Styling-Tokens
  meta?: BlockMeta;    // Optionale Metadaten (Anker, Section-Style)
}
```

### Block-Typen und Varianten

| Typ                  | Varianten                                                                                      |
|----------------------|------------------------------------------------------------------------------------------------|
| `hero`               | `centered_cta`, `media_right`, `media_left`, `media_video`, `minimal`, `stat_tiles`            |
| `feature_list`       | `stacked_cards`, `icon_grid`, `detailed-cards`, `grid-bullets`, `text-columns`, `card-stack`, `slider`, `clustered-tabs` |
| `content_slider`     | `words`, `images`                                                                              |
| `process_steps`      | `timeline_horizontal`, `timeline_vertical`, `numbered-vertical`, `numbered-horizontal`         |
| `testimonial`        | `single_quote`, `quote_wall`, `slider`                                                         |
| `rich_text`          | `prose`                                                                                        |
| `info_media`         | (varianten-abhängig)                                                                           |
| `cta`                | (varianten-abhängig)                                                                           |
| `stat_strip`         | (varianten-abhängig)                                                                           |
| `audience_spotlight` | (varianten-abhängig)                                                                           |
| `package_summary`    | (varianten-abhängig)                                                                           |
| `faq`                | (varianten-abhängig)                                                                           |
| `event_highlight`    | (varianten-abhängig)                                                                           |
| `system_module`      | (varianten-abhängig)                                                                           |
| `case_showcase`      | (varianten-abhängig)                                                                           |

### Tokens (pro Block)

```typescript
interface Tokens {
  background?: 'primary' | 'secondary' | 'muted' | 'accent' | 'surface';
  spacing?: 'small' | 'normal' | 'large';
  width?: 'narrow' | 'normal' | 'wide';
  columns?: 'single' | 'two' | 'three' | 'four';
  accent?: 'brandA' | 'brandB' | 'brandC';
}
```

### Block-Meta

```typescript
interface BlockMeta {
  anchor?: string;           // HTML-Anker-ID
  sectionStyle?: {
    layout: 'normal' | 'full' | 'card';
    intent?: 'content' | 'feature' | 'highlight' | 'hero' | 'plain';
    background?: {
      mode: 'none' | 'color' | 'image';
      colorToken?: string;
      imageId?: string;
      attachment?: 'scroll' | 'fixed';
      overlay?: number;
    };
  };
}
```

### Validierung

Blöcke werden zweistufig validiert:

1. **Strukturvalidierung:** `PageBlockContractMigrator` prüft die grundlegende Datenstruktur
2. **Schema-Validierung:** `BlockContractSchemaValidator` prüft gegen das JSON-Schema

Referenzdateien:
- TypeScript-Typdefinitionen: `public/js/components/block-contract.d.ts`
- JSON-Schema: `public/js/components/block-contract.schema.json`
- Renderer-Matrix (alle Varianten): `public/js/components/block-renderer-matrix-data.js`

---

## 4. Layout-Komponenten

Eine vollständig gerenderte Marketing-Seite besteht aus diesen Schichten (zusammengesetzt durch `CmsLayoutDataService.loadLayoutData()`):

### 4.1 Header / Navigation

- Menü-Slot: `header` (Hauptnavigation)
- Logo-Konfiguration: Text- oder Bildmodus
- Header-Config: Toggles für Sprache, Theme, Kontrast
- Datenquelle: `CmsMenuResolverService` löst Menü-Zuweisungen auf

### 4.2 Content-Blöcke

- Die eigentlichen Seitenblöcke (Block-System, siehe Abschnitt 3)
- Gespeichert als JSON in `pages.content`

### 4.3 Page-Module

- Positionierbare Module vor oder nach dem Content
- Tabelle: `page_modules`
- Service: `PageModuleService` (`src/Service/PageModuleService.php`)
- Erlaubte Typen: `latest-news`
- Erlaubte Positionen: `before-content`, `after-content`
- Config: JSON-Objekt pro Modul (z.B. Anzahl der News, Kategorie-Filter)
- Fallback-Mechanismus: Wenn ein Namespace keine Module hat, werden die des Default-Namespace geerbt

### 4.4 Footer

3-Spalten-System mit typisierten Blöcken:

| Slot       | Beschreibung     |
|------------|------------------|
| `footer_1` | Linke Spalte     |
| `footer_2` | Mittlere Spalte  |
| `footer_3` | Rechte Spalte    |

**Footer-Block-Typen:**

| Typ          | Content-Felder                                                    |
|--------------|-------------------------------------------------------------------|
| `menu`       | `menuId` (int) – referenziert ein Menü, Items werden aufgelöst    |
| `text`       | `headline` (string), `body` (string/HTML)                         |
| `social`     | `links` (Array mit `platform`, `url`, `label`)                    |
| `contact`    | `email`, `phone`, `address` etc.                                  |
| `newsletter` | `headline`, `placeholder`, `buttonLabel`, `action`                |
| `html`       | `html` (string) – freies HTML                                     |

**Footer-Layout-Optionen:** `equal`, `brand-left`, `cta-right`, `centered`

Datenquellen:
- Tabelle: `marketing_footer_blocks`
- Service: `CmsFooterBlockService` (`src/Service/CmsFooterBlockService.php`)
- Admin-Controller (Logik-Vorlage): `src/Controller/Admin/MarketingFooterBlockController.php`

Zusätzliche Footer-Navigation über Menü-Slots:
- `footer` – Footer-Navigation
- `legal` – Legal-Navigation (Impressum, Datenschutz etc.)
- Footer-Spalten via Slots: `footer_col_1`, `footer_col_2`, `footer_col_3`, `footer_col_4`

### 4.5 Design-Tokens

Namespace-spezifische CSS Custom Properties für visuelles Theming.

**Token-Gruppen:**

```json
{
  "brand": {
    "primary": "#1e87f0",
    "accent": "#f97316",
    "secondary": "#f97316"
  },
  "layout": {
    "profile": "standard"       // narrow | standard | wide
  },
  "typography": {
    "preset": "modern"          // modern | classic | tech
  },
  "components": {
    "cardStyle": "rounded",     // rounded | square | pill
    "buttonStyle": "filled"     // filled | outline | ghost
  }
}
```

Datenquellen:
- Service: `DesignTokenService` (`src/Service/DesignTokenService.php`)
- Admin-Controller (Logik-Vorlage): `src/Controller/Admin/PagesDesignController.php`
- CSS-Output: `public/css/namespace-tokens.css` und `public/css/{namespace}/namespace-tokens.css`
- Design-Presets: Verfügbar über `DesignTokenService::listAvailablePresets()`

---

## 5. Fehlende API-Endpunkte

Die folgenden Endpunkte müssen implementiert werden, damit ein KI-Agent per API-Calls vollständige Seiten erstellen und verwalten kann.

### 5.1 Page Detail GET (Priorität: Hoch)

**Warum:** Der bestehende List-Endpunkt gibt nur Metadaten zurück. Ein KI-Agent muss den aktuellen Inhalt einer Seite lesen können, um Änderungen darauf aufzubauen.

```
GET /api/v1/namespaces/{ns}/pages/{slug}
Scope: cms:read
```

**Response:**
```json
{
  "id": 42,
  "namespace": "edocs",
  "slug": "landing",
  "title": "Landing Page",
  "status": "published",
  "type": "marketing",
  "language": "de",
  "blocks": [ /* Block-Contract-Array */ ],
  "meta": { /* Seiten-Meta */ },
  "seo": { /* SEO-Konfiguration falls vorhanden */ }
}
```

**Implementierungshinweise:**
- `PageService::findByKey($ns, $slug)` liefert das `Page`-Objekt
- `Page::getContent()` gibt den JSON-String zurück → decodieren
- SEO-Daten via `PageSeoConfigService::findByPageId()`

### 5.2 Footer Block API (Priorität: Hoch)

**Warum:** Footer-Blöcke können aktuell nur über die Admin-Oberfläche verwaltet werden. Ein KI-Agent braucht API-Zugang, um Footer-Inhalte programmatisch zu erstellen.

Neue Scopes: `footer:read`, `footer:write`

```
GET    /api/v1/namespaces/{ns}/footer-blocks?slot=...&locale=...
POST   /api/v1/namespaces/{ns}/footer-blocks
PUT    /api/v1/namespaces/{ns}/footer-blocks/{id}
DELETE /api/v1/namespaces/{ns}/footer-blocks/{id}
POST   /api/v1/namespaces/{ns}/footer-blocks/reorder
PUT    /api/v1/namespaces/{ns}/footer-layout
```

**POST/PUT Payload:**
```json
{
  "slot": "footer_1",
  "type": "text",
  "content": { "headline": "Über uns", "body": "<p>Beschreibungstext</p>" },
  "position": 0,
  "locale": "de",
  "isActive": true
}
```

**POST `.../reorder` Payload:**
```json
{
  "slot": "footer_1",
  "locale": "de",
  "orderedIds": [5, 3, 8]
}
```

**PUT `.../footer-layout` Payload:**
```json
{
  "layout": "brand-left"
}
```

**Implementierungshinweise:**
- Neuer Controller: `NamespaceFooterBlockController` in `src/Controller/Api/V1/`
- Bestehende Service-Logik in `CmsFooterBlockService` vollständig nutzbar
- Erlaubte Block-Typen: `menu`, `text`, `social`, `contact`, `newsletter`, `html`
- Erlaubte Slots: `footer_1`, `footer_2`, `footer_3`
- **Wichtig:** Namespace-Prüfung muss hinzugefügt werden (der bestehende Admin-Controller prüft das nicht explizit)
- Footer-Layout wird via `ProjectSettingsService` gespeichert

### 5.3 Design Token API (Priorität: Mittel)

**Warum:** Design-Tokens bestimmen das visuelle Erscheinungsbild des gesamten Namespace. Ein KI-Agent muss das Branding anpassen können.

Neue Scopes: `design:read`, `design:write`

```
GET    /api/v1/namespaces/{ns}/design-tokens
PUT    /api/v1/namespaces/{ns}/design-tokens
POST   /api/v1/namespaces/{ns}/design-tokens/reset
GET    /api/v1/namespaces/{ns}/design-tokens/defaults
GET    /api/v1/namespaces/{ns}/design-tokens/presets
POST   /api/v1/namespaces/{ns}/design-tokens/import
```

**PUT Payload:**
```json
{
  "brand": { "primary": "#2563eb", "accent": "#f59e0b" },
  "layout": { "profile": "wide" },
  "typography": { "preset": "tech" },
  "components": { "cardStyle": "pill", "buttonStyle": "outline" }
}
```

**POST `.../import` Payload:**
```json
{
  "preset": "aurora"
}
```

**Implementierungshinweise:**
- Neuer Controller: `NamespaceDesignTokenController` in `src/Controller/Api/V1/`
- `DesignTokenService` bietet alle nötigen Methoden: `getTokensForNamespace()`, `persistTokens()`, `resetToDefaults()`, `importDesign()`, `listAvailablePresets()`, `getDefaults()`
- Token-Validierung (Hex-Farben, erlaubte Werte) ist im Service bereits eingebaut
- Nach `persistTokens()` und `importDesign()` wird automatisch das CSS neu generiert

### 5.4 Page Module API (Priorität: Mittel)

**Warum:** Page-Module (z.B. „Letzte News") ergänzen den Content und müssen vom KI-Agenten konfiguriert werden können.

Neue Scopes: `module:read`, `module:write`

```
GET    /api/v1/namespaces/{ns}/pages/{slug}/modules
POST   /api/v1/namespaces/{ns}/pages/{slug}/modules
PUT    /api/v1/namespaces/{ns}/pages/{slug}/modules/{id}
DELETE /api/v1/namespaces/{ns}/pages/{slug}/modules/{id}
```

**POST/PUT Payload:**
```json
{
  "type": "latest-news",
  "position": "after-content",
  "config": {
    "limit": 3,
    "category": "events"
  }
}
```

**Implementierungshinweise:**
- Neuer Controller: `NamespacePageModuleController` in `src/Controller/Api/V1/`
- `PageModuleService` bietet CRUD-Methoden: `create()`, `update()`, `delete()`, `getModulesForPage()`, `findById()`
- Erlaubte Typen: `latest-news`
- Erlaubte Positionen: `before-content`, `after-content`
- `{slug}` muss erst über `PageService::findByKey()` in eine `pageId` aufgelöst werden

### 5.5 Layout Assembly Endpoint (Priorität: Hoch)

**Warum:** Ein KI-Agent muss verstehen, wie eine Seite aktuell aussieht – mit allen Komponenten zusammen. Dieser read-only Endpunkt liefert die vollständige Layout-Struktur.

```
GET /api/v1/namespaces/{ns}/pages/{slug}/layout
Scope: cms:read
```

**Response:**
```json
{
  "page": {
    "id": 42,
    "slug": "landing",
    "title": "Landing Page",
    "status": "published",
    "blocks": [ /* ... */ ],
    "meta": { /* ... */ }
  },
  "seo": { /* SEO-Konfiguration */ },
  "header": {
    "navigation": [ /* Aufgelöste Menü-Items */ ],
    "config": { "show_language": true, "show_theme_toggle": true, "show_contrast_toggle": true },
    "logo": { "mode": "text", "label": "edocs", "src": null, "alt": "edocs" }
  },
  "footer": {
    "blocks": {
      "footer_1": [ /* Footer-Block-Objekte */ ],
      "footer_2": [ /* ... */ ],
      "footer_3": [ /* ... */ ]
    },
    "layout": "equal",
    "navigation": [ /* Footer-Menü-Items */ ],
    "legal": [ /* Legal-Menü-Items */ ]
  },
  "modules": {
    "before-content": [ /* Module */ ],
    "after-content": [ /* Module */ ]
  },
  "designTokens": { /* Aktuelle Design-Tokens */ }
}
```

**Implementierungshinweise:**
- Kern-Logik: `CmsLayoutDataService::loadLayoutData()` liefert Header, Footer, Navigation
- Ergänzen um: Page-Content, SEO, Design-Tokens, Page-Module
- Seite muss über `PageService::findByKey()` geladen werden
- Design-Tokens über `DesignTokenService::getTokensForNamespace()`
- Page-Module über `PageModuleService::getModulesByPosition()`

### 5.6 Composite Endpoint (Priorität: Niedrig, optional)

**Warum:** Reduziert die Anzahl der API-Calls für den KI-Agenten erheblich, wenn eine komplette Seite in einem Schritt erstellt werden soll.

```
POST /api/v1/namespaces/{ns}/pages/{slug}/compose
Scope: cms:write (+ weitere je nach Payload)
```

**Payload:**
```json
{
  "blocks": [ /* ... */ ],
  "meta": { /* ... */ },
  "title": "Neue Seite",
  "status": "published",
  "seo": { /* ... */ },
  "menuAssignments": [ /* ... */ ],
  "footerBlocks": [
    { "slot": "footer_1", "type": "text", "content": { /* ... */ }, "position": 0, "locale": "de" }
  ],
  "modules": [
    { "type": "latest-news", "position": "after-content", "config": { "limit": 3 } }
  ],
  "designTokens": {
    "brand": { "primary": "#2563eb" }
  }
}
```

**Implementierungshinweise:**
- Baut auf dem bestehenden `upsert`-Endpunkt auf
- Alle Operationen in einer Datenbank-Transaktion
- Scope-Prüfung pro Bereich (SEO → `seo:write`, Menu → `menu:write`, Footer → `footer:write`, Design → `design:write`, Module → `module:write`)
- Partial-Updates: Nur die übergebenen Bereiche werden verarbeitet

---

## 6. Implementierungskonventionen

### Controller-Pattern

Alle API-Controller folgen demselben Muster (vgl. `NamespacePageController`, `NamespaceMenuController`):

```php
final class NamespaceXxxController
{
    public const SCOPE_XXX_READ = 'xxx:read';
    public const SCOPE_XXX_WRITE = 'xxx:write';

    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?XxxService $service = null,
    ) {}

    public function list(Request $request, Response $response, array $args): Response
    {
        // 1. Namespace aus URL und Token extrahieren
        $ns = (string) ($args['ns'] ?? '');
        $tokenNs = (string) $request->getAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_NAMESPACE);

        // 2. Namespace-Match prüfen
        if ($tokenNs === '' || $ns === '' || $ns !== $tokenNs) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        // 3. PDO auflösen (nullable Constructor → RequestDatabase-Fallback)
        $pdo = $this->pdo ?? RequestDatabase::resolve($request);

        // 4. Service instanziieren
        $service = $this->service ?? new XxxService($pdo);

        // 5. Geschäftslogik ausführen und JSON zurückgeben
        return $this->json($response, ['data' => $result]);
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
```

### Routen-Registrierung

Neue Endpunkte werden in `src/Routes/api_v1.php` registriert:

```php
$group->get('/namespaces/{ns:[a-z0-9\-]+}/new-resource', function (
    Request $request, Response $response, array $args
): Response {
    return (new NamespaceXxxController())->list($request, $response, $args);
})->add(new ApiTokenAuthMiddleware(null, null, NamespaceXxxController::SCOPE_XXX_READ));
```

### Fehlerformat

```json
{
  "error": "error_code_snake_case",
  "details": [ /* Optional: Array mit Detail-Informationen */ ],
  "scope": "xxx:write"  // Optional: Fehlender Scope bei 403
}
```

HTTP-Statuscodes:
- `200` – Erfolg
- `400` – Ungültiges JSON
- `403` – Namespace-Mismatch oder fehlender Scope
- `404` – Ressource nicht gefunden
- `422` – Validierungsfehler (Block-Schema, ungültige Werte)
- `500` – Interner Fehler

### Namespace-Isolation

**Jeder API-Endpunkt MUSS** sicherstellen:
1. Token-Namespace stimmt mit URL-Namespace überein
2. Geladene Ressourcen gehören zum angegebenen Namespace
3. Bei Schreiboperationen wird nur im eigenen Namespace geschrieben

### Scope-Prüfung

- Haupt-Scope wird über `ApiTokenAuthMiddleware` als dritter Parameter erzwungen
- Zusätzliche Scopes (z.B. `seo:write` im Page-Upsert) werden im Controller via `hasScope()` geprüft:

```php
private function hasScope(Request $request, string $scope): bool
{
    $scopes = $request->getAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_SCOPES);
    return is_array($scopes) && in_array($scope, $scopes, true);
}
```

---

## 7. Implementierungsreihenfolge

| Prio | Endpunkt                            | Begründung                                              |
|------|-------------------------------------|---------------------------------------------------------|
| 1    | `GET /pages/{slug}` (Detail)        | Grundvoraussetzung: Seiten lesen bevor man sie ändert   |
| 2    | Footer Block API (CRUD + Reorder)   | Für vollständige Seitenerstellung unverzichtbar          |
| 3    | Design Token API (Read/Write)       | Visuelles Branding steuern                               |
| 4    | Page Module API (CRUD)              | Module wie „Letzte News" konfigurieren                   |
| 5    | Layout Assembly Endpoint            | Gesamtansicht einer Seite für Verifikation               |
| 6    | Composite Endpoint                  | Convenience – reduziert API-Calls, kann auch später kommen|

---

## 8. Referenzdateien

### API & Controller

| Datei                                                     | Beschreibung                               |
|-----------------------------------------------------------|--------------------------------------------|
| `src/Routes/api_v1.php`                                   | Zentrale Routen-Registrierung              |
| `src/Application/Middleware/ApiTokenAuthMiddleware.php`    | Token-Auth mit Scope-Prüfung              |
| `src/Controller/Api/V1/NamespacePageController.php`       | Page-API (Vorlage für neue Controller)     |
| `src/Controller/Api/V1/NamespaceMenuController.php`       | Menu-API (Vorlage für Namespace-Match)     |
| `src/Controller/Api/V1/NamespaceNewsController.php`       | News-API (Vorlage für CRUD-Pattern)        |

### Services (Geschäftslogik)

| Datei                                          | Beschreibung                                  |
|------------------------------------------------|-----------------------------------------------|
| `src/Service/PageService.php`                  | Seiten-CRUD, Baum-Hierarchie                  |
| `src/Service/CmsFooterBlockService.php`        | Footer-Block-CRUD (direkt nutzbar für API)    |
| `src/Service/CmsLayoutDataService.php`         | Layout-Assembly (Header + Footer + Navigation)|
| `src/Service/DesignTokenService.php`           | Design-Token Read/Write/Import/Presets         |
| `src/Service/PageModuleService.php`            | Page-Module-CRUD                              |
| `src/Service/CmsMenuDefinitionService.php`     | Menü-Definitionen und Zuweisungen             |
| `src/Service/CmsMenuResolverService.php`       | Menü-Auflösung für Rendering                  |
| `src/Service/ProjectSettingsService.php`       | Footer-Layout und Header-Config               |
| `src/Service/PageBlockContractMigrator.php`    | Block-Struktur-Validierung                    |
| `src/Service/BlockContractSchemaValidator.php`  | Block-JSON-Schema-Validierung                 |
| `src/Application/Seo/PageSeoConfigService.php` | SEO-Metadaten-Verwaltung                      |

### Admin-Controller (Logik-Vorlagen)

| Datei                                                      | Beschreibung                                |
|------------------------------------------------------------|---------------------------------------------|
| `src/Controller/Admin/MarketingFooterBlockController.php`  | Footer-Block-Verwaltung (Admin)             |
| `src/Controller/Admin/PageModuleController.php`            | Page-Module-Verwaltung (Admin)              |
| `src/Controller/Admin/PagesDesignController.php`           | Design-Token-Verwaltung (Admin)             |

### Block-System

| Datei                                              | Beschreibung                               |
|----------------------------------------------------|--------------------------------------------|
| `public/js/components/block-contract.d.ts`         | TypeScript-Typdefinitionen aller Blöcke    |
| `public/js/components/block-contract.schema.json`  | JSON-Schema für Block-Validierung          |
| `public/js/components/block-renderer-matrix-data.js`| Renderer-Matrix mit allen Varianten       |
| `public/js/components/block-renderer-matrix.js`    | Block-Rendering-Logik                      |

### Domain-Objekte

| Datei                                | Beschreibung                    |
|--------------------------------------|---------------------------------|
| `src/Domain/Page.php`               | Seiten-Entity                   |
| `src/Domain/CmsFooterBlock.php`     | Footer-Block-Entity             |
| `src/Domain/PageModule.php`         | Page-Module-Entity              |
| `src/Domain/PageSeoConfig.php`      | SEO-Config-Entity               |

---

## 9. Workflow: So erstellt ein KI-Agent eine komplette Seite

Beispiel-Ablauf mit den neuen Endpunkten:

```
1. Design-Tokens setzen (optional, wenn Branding gewünscht)
   PUT /api/v1/namespaces/{ns}/design-tokens

2. Menü erstellen und Items hinzufügen
   POST /api/v1/namespaces/{ns}/menus
   POST /api/v1/namespaces/{ns}/menus/{menuId}/items  (mehrfach)

3. Seite mit Blöcken und Menü-Zuweisung erstellen
   PUT /api/v1/namespaces/{ns}/pages/{slug}
   Body: { blocks, meta, title, status, seo, menuAssignments }

4. Footer-Blöcke erstellen
   POST /api/v1/namespaces/{ns}/footer-blocks  (mehrfach für verschiedene Slots)

5. Footer-Layout setzen
   PUT /api/v1/namespaces/{ns}/footer-layout

6. Page-Module hinzufügen (optional)
   POST /api/v1/namespaces/{ns}/pages/{slug}/modules

7. Ergebnis verifizieren
   GET /api/v1/namespaces/{ns}/pages/{slug}/layout
```

---

## 10. Wichtige Hinweise

- **Conventional Commits:** Alle Commit-Messages müssen der Conventional-Commits-Spezifikation folgen (siehe `CLAUDE.md`)
- **Branch-Naming:** `feature/...` für neue Features, `fix/...` für Bugfixes (siehe `CLAUDE.md`)
- **Tests:** Neue Endpunkte sollten mit PHPUnit-Tests abgedeckt werden (Test-Verzeichnis: `tests/`)
- **Kein Breaking Change:** Die bestehende API darf nicht verändert werden – nur Erweiterungen
- **Rückwärtskompatibilität:** Footer-Blöcke und Design-Tokens, die über die Admin-UI erstellt wurden, müssen über die API vollständig lesbar und editierbar sein
