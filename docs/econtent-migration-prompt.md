# eContent-Modul – Migrationsprompt für eForms-Framework

> **Zweck:** Dieses Dokument beschreibt die **fachliche Logik** des bestehenden Content/CMS-Systems aus edocs-cloud. Es dient als Implementierungsbriefing für die eForms-Code-Assistenz – mit voller gestalterischer Freiheit bei der Umsetzung.

---

## Gestaltungsprinzipien

- **100% gestalterische Freiheit.** Es gibt keine Vorgaben zu UI-Framework, CSS, Komponenten-Bibliothek oder Templatestruktur. Die Umsetzung soll sich nahtlos in das bestehende eForms-Designsystem einfügen.
- **Bestehende Komponenten wiederverwenden.** Tabellensysteme, Formulare, Modals, Admin-Layouts, Navigationsstrukturen und alle anderen bereits im eForms-Framework vorhandenen Bausteine sollen bevorzugt genutzt werden. Kein Rad neu erfinden.
- **Laravel-Konventionen folgen.** Das Zielsystem ist Laravel – Eloquent Models, Migrations, Blade Templates, Resource Controllers, Form Requests, etc. sind die natürliche Wahl.
- **Optimierter Gesamtcode.** Möglichst kompakt, möglichst viele vorhandene Komponenten, möglichst wenig Eigenentwicklung bei Infrastruktur-Themen (Tabellen, CRUD, Validierung).

---

## 1. Quellsystem-Referenz

Das Quellsystem basiert auf Slim 4 / PostgreSQL / Twig. Die folgenden Dateien dienen als **fachliche Referenz** für die Content-Logik – nicht als Vorlage für die Architektur:

### Backend – Domain

| Datei | Fachliche Funktion |
|-------|-------------------|
| `src/Domain/Page.php` | Seiten-Entität (Status, Typ, Hierarchie, Startseite) |
| `src/Domain/PageModule.php` | Seiten-Module (latest-news, pricing-table, timeline) |
| `src/Domain/PageSeoConfig.php` | SEO-Metadaten pro Seite |
| `src/Domain/CmsMenu.php` | Menü-Entität |
| `src/Domain/CmsMenuItem.php` | Menüpunkt mit Layout und Details |
| `src/Domain/CmsMenuAssignment.php` | Menü-Slot-Zuweisung (global oder pro Seite) |
| `src/Domain/CmsFooterBlock.php` | Footer-Block (Typ + Slot + Content) |

### Backend – Services

| Datei | Fachliche Funktion |
|-------|-------------------|
| `src/Service/PageService.php` | Seiten-CRUD, Baumstruktur, Slug-Validierung |
| `src/Service/PageModuleService.php` | Modul-Verwaltung (Typen, Positionen) |
| `src/Service/PageContentLoader.php` | Content-Laden aus mehreren Quellen (DB/Datei) |
| `src/Service/PageVariableService.php` | Platzhalter-Ersetzung (`[NAME]`, `[EMAIL]`, etc.) |
| `src/Service/PageBlockContractMigrator.php` | Block-Contract-Validierung und Migration |
| `src/Service/PagesDesignService.php` | Design-Einstellungen pro Seite |
| `src/Service/CmsMenuService.php` | Menü-CRUD |
| `src/Service/CmsMenuDefinitionService.php` | Menü-Struktur-Definitionen |
| `src/Service/CmsMenuResolverService.php` | Menü-Auflösung für Frontend-Rendering |
| `src/Service/CmsPageMenuService.php` | Seiten-Menü-Integration |
| `src/Service/CmsFooterBlockService.php` | Footer-Block-CRUD (Typen, Slots) |
| `src/Service/CmsLayoutDataService.php` | Layout-Daten für Frontend (Header, Footer, Nav) |
| `src/Service/CmsPageRouteResolver.php` | Slug-zu-Seite-Auflösung |
| `src/Service/MarketingSlugResolver.php` | Slug-Resolver mit Domain-Kontext |
| `src/Application/Seo/PageSeoConfigService.php` | SEO-Konfiguration CRUD + History |
| `src/Service/Seo/SitemapService.php` | Sitemap-Generierung |
| `src/Service/Seo/FeedService.php` | RSS/Atom-Feed-Generierung |
| `src/Service/Seo/LlmsTxtService.php` | LLMs.txt-Generierung |
| `src/Service/Seo/SchemaEnhancer.php` | JSON-LD Schema-Anreicherung |

### Backend – Controller

| Datei | Fachliche Funktion |
|-------|-------------------|
| `src/Controller/Admin/PageController.php` | Admin-Seiteneditor (Block-basiert) |
| `src/Controller/Admin/PageModuleController.php` | Admin-Modul-API |
| `src/Controller/Admin/PagesDesignController.php` | Admin-Design-Editor |
| `src/Controller/Admin/NavigationController.php` | Admin-Navigation |
| `src/Controller/Admin/MarketingMenuController.php` | Admin-Menü-CRUD |
| `src/Controller/Admin/MarketingMenuItemController.php` | Admin-Menüpunkt-CRUD |
| `src/Controller/Admin/MarketingMenuDefinitionController.php` | Admin-Menüdefinitionen |
| `src/Controller/Admin/MarketingMenuAssignmentController.php` | Admin-Menü-Zuweisungen |
| `src/Controller/Admin/MarketingFooterBlockController.php` | Admin-Footer-CRUD |
| `src/Controller/Api/V1/NamespacePageController.php` | REST-API (Pages-CRUD) |
| `src/Controller/Marketing/PageController.php` | Frontend-Seiten-Rendering |

### Frontend (Logik-Referenz)

| Datei | Fachliche Funktion |
|-------|-------------------|
| `public/js/components/block-contract.js` | Block-Contract-Validierung (Client) |
| `public/js/components/block-contract.schema.json` | Block-Schema-Definition (21 Typen) |
| `public/js/components/block-content-editor.js` | Block-basierter visueller Seiteneditor |
| `public/js/components/page-renderer.js` | Block-Rendering im Frontend |
| `public/js/components/block-renderer-matrix.js` | Renderer-Matrix für Block-Typen |
| `public/js/components/block-renderer-matrix-data.js` | Renderer-Daten |
| `public/js/tiptap-pages.js` | TipTap-Texteditor-Integration |
| `public/js/admin-page-tree.js` | Admin-Seitenbaum |
| `public/js/marketing-footer-blocks.js` | Footer-Block-Editor |
| `public/js/marketing-menu-admin.js` | Menü-Admin-Hauptlogik |
| `public/js/marketing-menu-tree.js` | Menü-Baumansicht |
| `public/js/marketing-menu-common.js` | Menü-Hilfsfunktionen |
| `public/js/marketing-menu-cards.js` | Menü-Karten-Darstellung |
| `public/js/marketing-menu-href-suggest.js` | Menü-Link-Vorschläge |
| `public/js/marketing-menu-overview.js` | Menü-Übersicht |
| `public/js/marketing-menu-standards.js` | Menü-Standards |

---

## 2. Datenbank-Schema

Die `namespace`-Spalte aus dem Quellsystem wird **nicht** übernommen. Mandantentrennung erfolgt über das eForms-Tenant-System. Die Tabellenstruktur in Laravel-Migrations überführen (Eloquent Models mit passenden Relationships).

```sql
-- Pages (Hauptentität)
CREATE TABLE econtent_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    type VARCHAR(50) NULL,
    parent_id BIGINT UNSIGNED NULL REFERENCES econtent_pages(id) ON DELETE SET NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft|published|archived
    language VARCHAR(10) NULL,
    content_source VARCHAR(255) NULL,
    base_slug VARCHAR(255) NULL,
    startpage_domain VARCHAR(255) NULL,
    is_startpage BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- Page Modules (Komponenten vor/nach Content)
CREATE TABLE econtent_page_modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id BIGINT UNSIGNED NOT NULL REFERENCES econtent_pages(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL,       -- latest-news|pricing-table|timeline
    config JSON NULL,
    position VARCHAR(50) NOT NULL    -- before-content|after-content
);
CREATE INDEX econtent_page_modules_page_position_idx ON econtent_page_modules(page_id, position, id);

-- Page SEO Config (1:1 zu Pages)
CREATE TABLE econtent_page_seo_config (
    page_id BIGINT UNSIGNED PRIMARY KEY REFERENCES econtent_pages(id) ON DELETE CASCADE,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    slug VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NULL,
    canonical_url VARCHAR(2048) NULL,
    robots_meta VARCHAR(255) NULL,
    og_title VARCHAR(255) NULL,
    og_description TEXT NULL,
    og_image VARCHAR(2048) NULL,
    schema_json JSON NULL,
    hreflang VARCHAR(50) NULL,
    favicon_path VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE UNIQUE INDEX econtent_page_seo_config_domain_slug ON econtent_page_seo_config(COALESCE(domain, ''), slug);

-- Page SEO Config History (Änderungsprotokoll)
CREATE TABLE econtent_page_seo_config_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id BIGINT UNSIGNED NOT NULL REFERENCES econtent_pages(id) ON DELETE CASCADE,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    slug VARCHAR(255) NULL,
    domain VARCHAR(255) NULL,
    canonical_url VARCHAR(2048) NULL,
    robots_meta VARCHAR(255) NULL,
    og_title VARCHAR(255) NULL,
    og_description TEXT NULL,
    og_image VARCHAR(2048) NULL,
    schema_json JSON NULL,
    hreflang VARCHAR(50) NULL,
    favicon_path VARCHAR(255) NULL,
    created_at TIMESTAMP NULL
);

-- Menus
CREATE TABLE econtent_menus (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(255) NOT NULL,
    locale VARCHAR(10) NOT NULL DEFAULT 'de',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMP NULL
);

-- Menu Items (hierarchisch, mit Layout-Optionen)
CREATE TABLE econtent_menu_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_id BIGINT UNSIGNED NOT NULL REFERENCES econtent_menus(id) ON DELETE CASCADE,
    parent_id BIGINT UNSIGNED NULL REFERENCES econtent_menu_items(id) ON DELETE CASCADE,
    label VARCHAR(255) NOT NULL,
    href VARCHAR(2048) NOT NULL,
    icon VARCHAR(100) NULL,
    position INTEGER NOT NULL DEFAULT 0,
    is_external BOOLEAN NOT NULL DEFAULT FALSE,
    locale VARCHAR(10) NOT NULL DEFAULT 'de',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    layout VARCHAR(50) NOT NULL DEFAULT 'link',
    detail_title VARCHAR(255) NULL,
    detail_text TEXT NULL,
    detail_subline VARCHAR(255) NULL,
    is_startpage BOOLEAN NOT NULL DEFAULT FALSE,
    updated_at TIMESTAMP NULL
);
CREATE INDEX econtent_menu_items_menu_idx ON econtent_menu_items(menu_id, locale, position, id);
CREATE INDEX econtent_menu_items_parent_idx ON econtent_menu_items(parent_id);

-- Menu Assignments (Menü → Seite/Slot-Zuweisung)
CREATE TABLE econtent_menu_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_id BIGINT UNSIGNED NOT NULL REFERENCES econtent_menus(id) ON DELETE CASCADE,
    page_id BIGINT UNSIGNED NULL REFERENCES econtent_pages(id) ON DELETE CASCADE,
    slot VARCHAR(50) NOT NULL,
    locale VARCHAR(10) NOT NULL DEFAULT 'de',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMP NULL
);
CREATE UNIQUE INDEX econtent_menu_assignments_page_unique ON econtent_menu_assignments(page_id, slot, locale) WHERE page_id IS NOT NULL;
CREATE UNIQUE INDEX econtent_menu_assignments_global_unique ON econtent_menu_assignments(slot, locale) WHERE page_id IS NULL;

-- Footer Blocks
CREATE TABLE econtent_footer_blocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot VARCHAR(20) NOT NULL CHECK (slot IN ('footer_1', 'footer_2', 'footer_3')),
    type VARCHAR(20) NOT NULL CHECK (type IN ('menu', 'text', 'social', 'contact', 'newsletter', 'html')),
    content JSON NOT NULL DEFAULT '{}',
    position INTEGER NOT NULL DEFAULT 0,
    locale VARCHAR(10) NOT NULL DEFAULT 'de',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMP NULL
);
CREATE INDEX econtent_footer_blocks_slot_idx ON econtent_footer_blocks(slot, locale, position, id) WHERE is_active = TRUE;
```

---

## 3. Domänenmodell

### Page (Hauptentität)

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `id` | BIGINT PK | Auto-Increment ID |
| `slug` | VARCHAR(255) UNIQUE | URL-Pfad (z.B. `ueber-uns`) |
| `title` | VARCHAR(255) | Seitentitel |
| `content` | LONGTEXT | Block-basierter JSON-Content |
| `type` | VARCHAR(50) | Seitentyp (optional) |
| `parent_id` | FK nullable | Elternseite (Baumstruktur) |
| `sort_order` | INTEGER | Sortierung innerhalb der Ebene |
| `status` | VARCHAR(20) | `draft` / `published` / `archived` |
| `language` | VARCHAR(10) | Sprache (z.B. `de`, `en`) |
| `content_source` | VARCHAR(255) | Content-Quelle (DB oder Dateipfad) |
| `base_slug` | VARCHAR(255) | Basis-Slug für mehrsprachige Varianten |
| `startpage_domain` | VARCHAR(255) | Domain für Startseiten-Zuordnung |
| `is_startpage` | BOOLEAN | Startseite einer Domain |

### PageModule

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `id` | BIGINT PK | Auto-Increment ID |
| `page_id` | FK | Zugehörige Seite |
| `type` | VARCHAR(50) | `latest-news`, `pricing-table`, `timeline` |
| `config` | JSON | Modul-Konfiguration |
| `position` | VARCHAR(50) | `before-content` oder `after-content` |

### PageSeoConfig (1:1 zu Page)

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `page_id` | FK PK | Zugehörige Seite |
| `meta_title` | VARCHAR(255) | SEO-Titel |
| `meta_description` | TEXT | SEO-Beschreibung |
| `slug` | VARCHAR(255) | SEO-Slug (kann von Page-Slug abweichen) |
| `domain` | VARCHAR(255) | Zugehörige Domain |
| `canonical_url` | VARCHAR(2048) | Canonical-URL |
| `robots_meta` | VARCHAR(255) | Robots-Anweisungen |
| `og_title` | VARCHAR(255) | Open-Graph-Titel |
| `og_description` | TEXT | Open-Graph-Beschreibung |
| `og_image` | VARCHAR(2048) | Open-Graph-Bild |
| `schema_json` | JSON | JSON-LD Schema-Markup |
| `hreflang` | VARCHAR(50) | Hreflang-Tag |
| `favicon_path` | VARCHAR(255) | Favicon-Pfad |

### CmsMenu

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `id` | BIGINT PK | Auto-Increment ID |
| `label` | VARCHAR(255) | Menü-Bezeichnung |
| `locale` | VARCHAR(10) | Sprache (default: `de`) |
| `is_active` | BOOLEAN | Aktiv/Inaktiv |

### CmsMenuItem (hierarchisch)

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `id` | BIGINT PK | Auto-Increment ID |
| `menu_id` | FK | Zugehöriges Menü |
| `parent_id` | FK nullable | Elternelement (Verschachtelung) |
| `label` | VARCHAR(255) | Anzeigename |
| `href` | VARCHAR(2048) | Link-Ziel |
| `icon` | VARCHAR(100) | Icon-Klasse (optional) |
| `position` | INTEGER | Sortierung |
| `is_external` | BOOLEAN | Externer Link |
| `locale` | VARCHAR(10) | Sprache |
| `is_active` | BOOLEAN | Aktiv/Inaktiv |
| `layout` | VARCHAR(50) | Layout-Typ (default: `link`) |
| `detail_title` | VARCHAR(255) | Detail-Titel (für Mega-Menü) |
| `detail_text` | TEXT | Detail-Text |
| `detail_subline` | VARCHAR(255) | Detail-Subline |
| `is_startpage` | BOOLEAN | Startseiten-Link |

### CmsMenuAssignment

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `id` | BIGINT PK | Auto-Increment ID |
| `menu_id` | FK | Zugehöriges Menü |
| `page_id` | FK nullable | Zugehörige Seite (NULL = global) |
| `slot` | VARCHAR(50) | Navigations-Slot |
| `locale` | VARCHAR(10) | Sprache |
| `is_active` | BOOLEAN | Aktiv/Inaktiv |

### CmsFooterBlock

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `id` | BIGINT PK | Auto-Increment ID |
| `slot` | VARCHAR(20) | `footer_1`, `footer_2`, `footer_3` |
| `type` | VARCHAR(20) | `menu`, `text`, `social`, `contact`, `newsletter`, `html` |
| `content` | JSON | Block-Inhalt |
| `position` | INTEGER | Sortierung innerhalb des Slots |
| `locale` | VARCHAR(10) | Sprache |
| `is_active` | BOOLEAN | Aktiv/Inaktiv |

---

## 4. Block-Contract + Editoren

### Block-Typen (21 Typen)

Das Content-Feld einer Seite enthält ein JSON-Array von Blöcken. Jeder Block hat einen `type` und typ-spezifische Daten. Die Schema-Definition liegt in `block-contract.schema.json`.

| Typ | Beschreibung |
|-----|-------------|
| `hero` | Hero-Banner mit Bild, Titel, Untertitel, CTA |
| `cta` | Call-to-Action-Block |
| `faq` | FAQ-Akkordeon |
| `feature_list` | Feature-Liste mit Icons |
| `info_media` | Info-Block mit Medien (Bild/Video) |
| `rich_text` | Freitext (HTML/Markdown) |
| `testimonial` | Kundenstimmen/Zitate |
| `proof` | Social-Proof / Vertrauenselemente |
| `process_steps` | Prozess-/Schrittanleitung |
| `contact_form` | Kontaktformular |
| `content_slider` | Content-Karussell |
| `event_highlight` | Event-Hervorhebung |
| `latest_news` | Neueste Nachrichten |
| `logo-row` | Logo-Reihe (Partner/Kunden) |
| `metric-callout` | Kennzahlen-Hervorhebung |
| `package_summary` | Paket-/Preisübersicht |
| `stat_strip` | Statistik-Leiste |
| `subscription_plans` | Abo-Pläne/Preistabelle |
| `system_module` | System-Modul-Einbettung |
| `audience_spotlight` | Zielgruppen-Highlight |
| `case_showcase` | Fallstudien/Referenzen |

### Page-Editor

Der Seiteneditor ist block-basiert und ermöglicht:

- **Blöcke hinzufügen/entfernen/sortieren** per Drag & Drop
- **Block-spezifische Formulare** für jeden der 21 Typen
- **Live-Vorschau** der gerenderten Blöcke
- **TipTap-Integration** für Rich-Text-Blöcke
- **Block-Contract-Validierung** (Schema-Prüfung vor dem Speichern)
- **Content-Source-Auswahl** (DB oder Datei)

### Menü-Editor

Der Menü-Editor unterstützt:

- **Menü-CRUD** mit Label und Locale
- **Hierarchische Menüpunkte** (Drag & Drop Baumansicht)
- **Layout-Typen** pro Menüpunkt (link, Mega-Menü mit Details)
- **Link-Vorschläge** (interne Seiten, externe URLs)
- **Slot-Zuweisungen** (Menü → Seite oder global)
- **Mehrsprachigkeit** (locale pro Menü und Item)

### Footer-Editor

Der Footer-Editor verwaltet:

- **3 Footer-Slots** (`footer_1`, `footer_2`, `footer_3`)
- **6 Block-Typen** (`menu`, `text`, `social`, `contact`, `newsletter`, `html`)
- **Sortierung** innerhalb jedes Slots
- **JSON-Content** pro Block (typ-spezifische Struktur)
- **Locale-Unterstützung** für mehrsprachige Footer

### Platzhalter-System (PageVariableService)

Im Content können folgende Platzhalter verwendet werden, die beim Rendern mit Profil-/Tenant-Daten ersetzt werden:

| Platzhalter | Beschreibung |
|-------------|-------------|
| `[NAME]` | Firmenname / Tenant-Name |
| `[STREET]` | Straße |
| `[ZIP]` | Postleitzahl |
| `[CITY]` | Stadt |
| `[EMAIL]` | E-Mail-Adresse |

---

## 5. Konfigurationsoptionen

### Page-Status

| Status | Beschreibung |
|--------|-------------|
| `draft` | Entwurf – nicht öffentlich sichtbar |
| `published` | Veröffentlicht – öffentlich sichtbar |
| `archived` | Archiviert – nicht mehr aktiv |

### PageModule-Typen

| Typ | Beschreibung |
|-----|-------------|
| `latest-news` | Neueste Nachrichten/Beiträge |
| `pricing-table` | Preistabelle |
| `timeline` | Zeitstrahl/Chronologie |

### PageModule-Positionen

| Position | Beschreibung |
|----------|-------------|
| `before-content` | Vor dem Seiten-Content |
| `after-content` | Nach dem Seiten-Content |

### Footer-Block-Typen

| Typ | Beschreibung |
|-----|-------------|
| `menu` | Menü-Links |
| `text` | Freitext-Block |
| `social` | Social-Media-Links |
| `contact` | Kontaktdaten |
| `newsletter` | Newsletter-Anmeldung |
| `html` | Freies HTML |

### Footer-Slots

| Slot | Beschreibung |
|------|-------------|
| `footer_1` | Erster Footer-Bereich |
| `footer_2` | Zweiter Footer-Bereich |
| `footer_3` | Dritter Footer-Bereich |

### SEO-Features

- **Meta-Tags** (Title, Description, Robots)
- **Open Graph** (Title, Description, Image)
- **Canonical URL** für Duplicate-Content-Vermeidung
- **Hreflang** für mehrsprachige Seiten
- **JSON-LD Schema** (strukturierte Daten)
- **Favicon** pro Domain
- **SEO-History** (Änderungsprotokoll aller SEO-Anpassungen)
- **Sitemap** automatische Generierung (`/sitemap.xml`)
- **RSS/Atom-Feed** (`/feed.xml`)
- **Robots.txt** dynamische Generierung
- **LLMs.txt** für AI-Crawler

---

## 6. API-Endpunkte

Alle Endpunkte ohne Namespace-Prefix. Routing-Struktur nach Laravel-Konventionen (Resource Controller, Route Groups, etc.).

```
# Pages
GET    /api/econtent/pages                        → Liste aller Seiten
GET    /api/econtent/pages/{id}                   → Seiten-Details mit Content
POST   /api/econtent/pages                        → Seite erstellen
PATCH  /api/econtent/pages/{id}                   → Seite aktualisieren
DELETE /api/econtent/pages/{id}                    → Seite löschen
POST   /api/econtent/pages/{id}/move              → Seite verschieben (Baumstruktur)
POST   /api/econtent/pages/{id}/status            → Status ändern (draft/published/archived)

# Page-Modules
GET    /api/econtent/pages/{id}/modules           → Module einer Seite
POST   /api/econtent/pages/{id}/modules           → Modul hinzufügen
PATCH  /api/econtent/pages/{id}/modules/{mid}     → Modul aktualisieren
DELETE /api/econtent/pages/{id}/modules/{mid}      → Modul entfernen

# SEO
GET    /api/econtent/pages/{id}/seo               → SEO-Config lesen
PUT    /api/econtent/pages/{id}/seo               → SEO-Config speichern

# Menus
GET    /api/econtent/menus                        → Alle Menüs
POST   /api/econtent/menus                        → Menü erstellen
PATCH  /api/econtent/menus/{id}                   → Menü aktualisieren
DELETE /api/econtent/menus/{id}                    → Menü löschen
GET    /api/econtent/menus/{id}/items             → Menüpunkte (Baum)
PUT    /api/econtent/menus/{id}/items             → Menüpunkte ersetzen (Bulk)
POST   /api/econtent/menus/{id}/assignments       → Menü-Zuweisung erstellen
DELETE /api/econtent/menus/{id}/assignments/{aid}  → Menü-Zuweisung entfernen

# Footer
GET    /api/econtent/footer-blocks                → Footer-Blocks (Filter: slot, locale)
PUT    /api/econtent/footer-blocks                → Footer-Blocks speichern (Bulk)

# Public (Frontend-Rendering)
GET    /{slug}                                    → Seite rendern (Slug-Auflösung)
GET    /sitemap.xml                               → Sitemap
GET    /robots.txt                                → Robots.txt
GET    /feed.xml                                  → RSS/Atom-Feed
```

---

## 7. Entfernungen (Namespace-Bindung)

Folgendes wird **NICHT** übernommen:

- `namespace`-Spalte in allen Tabellen (pages, menus, menu_items, menu_assignments, footer_blocks)
- `NamespaceResolver` und `NamespaceAccessService`
- `NamespaceAppearanceService`, `NamespaceSubscriptionService`
- `NamespaceRenderContextService`, `NamespaceValidator`
- `NamespaceBackupService`, `NamespaceContext`
- `NamespaceQueryMiddleware`, `MarketingNamespaceMiddleware`
- `CmsPageRouteResolver` Namespace-Fallback-Kette
- `PageService::DEFAULT_NAMESPACE`, `getAllForNamespace()`, `normalizeNamespaceInput()`, `assertValidNamespace()`
- `ApiTokenAuthMiddleware` mit Namespace-Scope-Prüfung
- URL-Prefix `/api/v1/namespaces/{ns}/pages`
- `QuotaService` mit Namespace-basierten Limits
- Wiki-System (`marketing_page_wiki_*` Tabellen + Services/Controller)
- Landing-News (`landing_news` Tabelle + Services/Controller)
- PageAI / MarketingMenuAI / PageSeoAI (AI-gestützte Generierung)
- Marketing-Newsletter
- MarketingChat / RagChat

---

## 8. Tenant-Anforderungen

Die technische Umsetzung der Mandantentrennung wird dem eForms-Framework überlassen. Folgende **fachliche Anforderungen** müssen abgedeckt werden:

| Anforderung | Beschreibung |
|-------------|-------------|
| **Domain-Routing** | Host → Tenant-Auflösung; kein Treffer → 404 |
| **Startseite pro Domain** | Jede Domain hat eine eigene Startseite (`is_startpage` + `startpage_domain`) |
| **Abo-Kopplung** | Begrenzung von max. Seiten, max. Domains und verfügbaren PageModule-Typen je nach Abo |
| **Mandantentrennung** | Kein `namespace`-Column, Isolation über eForms-Tenant-System |
| **Admin-Auth** | Authentifizierung und Autorisierung pro Tenant |

---

## 9. Testplan

1. **Seite erstellen** – Admin: neue Seite mit Titel, Slug, Block-Content (mindestens 3 verschiedene Block-Typen)
2. **Seitenbaum** – Admin: Unterseiten anlegen, Sortierung ändern, Seiten verschieben
3. **Status-Workflow** – Admin: Seite als Draft → Published → Archived umschalten
4. **Block-Editor** – Admin: Blöcke hinzufügen, bearbeiten, sortieren, entfernen; Block-Contract-Validierung
5. **SEO-Konfiguration** – Admin: Meta-Tags, Open Graph, Schema-JSON, Canonical URL setzen
6. **Menü erstellen** – Admin: Menü mit hierarchischen Einträgen (2+ Ebenen) anlegen
7. **Menü-Zuweisungen** – Admin: Menü einem Slot zuweisen (global und seitenspezifisch)
8. **Footer-Editor** – Admin: Footer-Blocks in allen 3 Slots mit verschiedenen Typen konfigurieren
9. **Frontend-Rendering** – Seite über Slug aufrufen, Blöcke korrekt gerendert, Menü + Footer sichtbar
10. **Platzhalter** – Content mit `[NAME]`, `[EMAIL]` etc. korrekt durch Tenant-Daten ersetzt
11. **SEO-Output** – Sitemap, Robots.txt, Feed.xml korrekt generiert
12. **Page-Modules** – Module (latest-news, pricing-table, timeline) vor/nach Content korrekt eingebunden

---

## Zusammenfassung

Dieses Dokument beschreibt die **fachliche Logik** des Content/CMS-Systems. Die Kernprinzipien:

1. **Block-basierter Seiteneditor** mit 21 Block-Typen und Schema-Validierung (Block-Contract)
2. **Hierarchische Seitenstruktur** mit Baumansicht, Sortierung und Status-Workflow (draft/published/archived)
3. **Menü-System** mit hierarchischen Einträgen, Layout-Optionen, Slot-Zuweisungen (global/pro Seite)
4. **Footer-System** mit 3 Slots, 6 Block-Typen und Locale-Unterstützung
5. **SEO-Modul** mit Meta-Tags, Open Graph, JSON-LD, Sitemap, Feed, Robots.txt, LLMs.txt
6. **Bestehende eForms-Komponenten maximal wiederverwenden** – Tabellen, Formulare, Admin-Layouts, Modals
7. **Namespace-Bindung entfernen** – alle `namespace`-Parameter, Multi-Tenant-Middleware und Quotas weglassen
8. **Tabellen-Prefix `econtent_`** verwenden, Eloquent Models + Laravel Migrations
9. **API-Pfade** unter `/api/econtent/` ohne Namespace-Segment
10. **Platzhalter-System** (`[NAME]`, `[STREET]`, etc.) für dynamische Tenant-Daten im Content
11. **Tenant-Anforderungen offen** – fachliche Anforderungen definiert, technische Umsetzung durch eForms
12. **Volle gestalterische Freiheit** – Design, Layouts und UI-Komponenten nach eigenem Ermessen im eForms-Designsystem umsetzen