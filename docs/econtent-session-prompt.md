# Session-Prompt: eContent-Migrationsprompt schreiben

## Aufgabe

Erstelle die Datei `docs/econtent-migration-prompt.md` im Repo `/home/user/edocs-cloud`.
Branch: `claude/migrate-content-eforms-IPlvQ` (bereits ausgecheckt).
Commit-Message: `docs(econtent): add migration prompt for content module to eforms`
Nach dem Commit: `git push -u origin claude/migrate-content-eforms-IPlvQ`

## Kontext

Die Datei `docs/equiz-migration-prompt.md` existiert bereits als **Vorlage** für Struktur, Ton und Detailgrad. Das neue Dokument beschreibt das **Content/CMS-Modul** (Pages, Block-Editor, Menüs, Footer) von edocs-cloud als Implementierungs-Briefing für das eForms-Laravel-Projekt. Sprache: **Deutsch**. Selbe Struktur wie equiz-Spec.

## Entscheidungen

- **Modulname:** eContent
- **Tabellen-Prefix:** `econtent_`
- **URL-Prefix:** `/api/econtent/`
- **Scope:** Kern + Navigation (pages, page_modules, page_seo_config, marketing_menus/items/assignments, marketing_footer_blocks, Block-Contract, Page-Editor, Menü-Editor, Footer-Editor)
- **Ausgenommen:** Wiki, Landing-News, PageAI, MarketingMenuAI, PageSeoAI, Newsletter, MarketingChat, RagChat
- **Tenant-Modell:** Offen lassen - nur Anforderungen beschreiben, technische Umsetzung überlässt die Spec eForms
- **Namespace-Spalte:** Wird NICHT übernommen, Mandantentrennung via eForms-Tenant-System

## Struktur (10 Abschnitte)

```
Zweck + Gestaltungsprinzipien
1. Quellsystem-Referenz
2. Datenbank-Schema
3. Domänenmodell
4. Block-Contract + Page-Editor + Menü-Editor + Footer-Editor
5. Konfigurationsoptionen
6. API-Endpunkte
7. Entfernungen (Namespace-Bindung)
8. Tenant-Anforderungen
9. Testplan
Zusammenfassung
```

## Gesammelte Daten

### Block-Typen (aus block-contract.schema.json)

hero, cta, faq, feature_list, info_media, rich_text, testimonial, proof, process_steps, contact_form, content_slider, event_highlight, latest_news, logo-row, metric-callout, package_summary, stat_strip, subscription_plans, system_module, audience_spotlight, case_showcase

### PageModule-Typen + Positionen (aus PageModuleService.php)

- ALLOWED_TYPES: `latest-news`, `pricing-table`, `timeline`
- ALLOWED_POSITIONS: `before-content`, `after-content`

### FooterBlock-Typen + Slots (aus CmsFooterBlockService.php)

- ALLOWED_TYPES: `menu`, `text`, `social`, `contact`, `newsletter`, `html`
- ALLOWED_SLOTS: `footer_1`, `footer_2`, `footer_3`

### Page-Status (aus Domain/Page.php)

- STATUS_DRAFT = 'draft'
- STATUS_PUBLISHED = 'published'
- STATUS_ARCHIVED = 'archived'

### Page-Felder (aus Domain/Page.php)

id, namespace*, slug, title, content, type, parentId, sortOrder, status, language, contentSource, baseSlug, startpageDomain, isStartpage
(*namespace wird NICHT übernommen)

### PageSeoConfig-Felder (aus Domain/PageSeoConfig.php)

pageId, metaTitle, metaDescription, slug, domain, canonicalUrl, robotsMeta, ogTitle, ogDescription, ogImage, schemaJson, hreflang, faviconPath

### CmsMenu-Felder

id, namespace*, label, locale, isActive, updatedAt

### CmsMenuItem-Felder

id, menuId, parentId, namespace*, label, href, icon, position, isExternal, locale, isActive, layout, detailTitle, detailText, detailSubline, isStartpage, updatedAt

### CmsMenuAssignment-Felder

id, menuId, pageId (nullable), namespace*, slot, locale, isActive, updatedAt

### CmsFooterBlock-Felder

id, namespace*, slot, type, content (JSONB), position, locale, isActive, updatedAt

### PageVariableService - Platzhalter

`[NAME]`, `[STREET]`, `[ZIP]`, `[CITY]`, `[EMAIL]` → werden mit Profil-/Tenant-Daten ersetzt

### Quellsystem-Referenz (verifizierte Dateipfade)

**Backend Domain:**
- src/Domain/Page.php, PageModule.php, PageSeoConfig.php, CmsMenu.php, CmsMenuItem.php, CmsMenuAssignment.php, CmsFooterBlock.php

**Services:**
- src/Service/PageService.php, PageModuleService.php, PageContentLoader.php, PageContentRepository.php, PageVariableService.php, PageBlockContractMigrator.php, PagesDesignService.php
- src/Service/CmsMenuService.php, CmsMenuDefinitionService.php, CmsMenuResolverService.php, CmsPageMenuService.php, CmsFooterBlockService.php, CmsLayoutDataService.php, CmsPageRouteResolver.php, MarketingSlugResolver.php
- src/Application/Seo/PageSeoConfigService.php, src/Service/Seo/SitemapService.php, FeedService.php, LlmsTxtService.php, SchemaEnhancer.php

**Controller:**
- src/Controller/Admin/PageController.php, PageModuleController.php, PagesDesignController.php, NavigationController.php
- src/Controller/Admin/MarketingMenuController.php, MarketingMenuItemController.php, MarketingMenuDefinitionController.php, MarketingMenuAssignmentController.php, MarketingFooterBlockController.php
- src/Controller/Admin/DomainPageController.php, ProjectPagesController.php
- src/Controller/Api/V1/NamespacePageController.php, src/Controller/Marketing/PageController.php

**Frontend (alle verifiziert):**
- public/js/components/block-contract.js, block-contract.schema.json, block-content-editor.js, page-renderer.js, block-renderer-matrix.js, block-renderer-matrix-data.js
- public/js/tiptap-pages.js, admin-page-tree.js, marketing-footer-blocks.js
- public/js/marketing-menu-admin.js, marketing-menu-tree.js, marketing-menu-common.js, marketing-menu-cards.js, marketing-menu-href-suggest.js, marketing-menu-overview.js, marketing-menu-standards.js
- public/css/marketing.css, marketing-cards.css

### Datenbank-Schema (Quellmigrationen zusammengefasst, OHNE namespace)

```sql
-- Pages
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

-- Page Modules
CREATE TABLE econtent_page_modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id BIGINT UNSIGNED NOT NULL REFERENCES econtent_pages(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL,       -- latest-news|pricing-table|timeline
    config JSON NULL,
    position VARCHAR(50) NOT NULL    -- before-content|after-content
);
CREATE INDEX econtent_page_modules_page_position_idx ON econtent_page_modules(page_id, position, id);

-- Page SEO Config
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

-- Page SEO Config History
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

-- Menu Items
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

-- Menu Assignments
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

### API-Endpunkte

```
# Pages
GET    /api/econtent/pages
GET    /api/econtent/pages/{id}
POST   /api/econtent/pages
PATCH  /api/econtent/pages/{id}
DELETE /api/econtent/pages/{id}
POST   /api/econtent/pages/{id}/move
POST   /api/econtent/pages/{id}/status

# Page-Modules
GET    /api/econtent/pages/{id}/modules
POST   /api/econtent/pages/{id}/modules
PATCH  /api/econtent/pages/{id}/modules/{mid}
DELETE /api/econtent/pages/{id}/modules/{mid}

# SEO
GET    /api/econtent/pages/{id}/seo
PUT    /api/econtent/pages/{id}/seo

# Menus
GET    /api/econtent/menus
POST   /api/econtent/menus
PATCH  /api/econtent/menus/{id}
DELETE /api/econtent/menus/{id}
GET    /api/econtent/menus/{id}/items
PUT    /api/econtent/menus/{id}/items
POST   /api/econtent/menus/{id}/assignments
DELETE /api/econtent/menus/{id}/assignments/{aid}

# Footer
GET    /api/econtent/footer-blocks?slot=footer_1&locale=de
PUT    /api/econtent/footer-blocks

# Public
GET    /{slug}
GET    /sitemap.xml
GET    /robots.txt
GET    /feed.xml
```

### Entfernungen (NICHT übernehmen)

- namespace-Spalte in allen Tabellen
- NamespaceResolver, NamespaceAccessService, NamespaceAppearanceService, NamespaceSubscriptionService, NamespaceRenderContextService, NamespaceValidator, NamespaceBackupService, NamespaceContext, NamespaceQueryMiddleware, MarketingNamespaceMiddleware
- CmsPageRouteResolver Namespace-Fallback-Kette
- PageService::DEFAULT_NAMESPACE, getAllForNamespace(), normalizeNamespaceInput(), assertValidNamespace()
- API-Prefix /api/v1/namespaces/{ns}/pages
- ApiTokenAuthMiddleware mit Namespace-Scope
- QuotaService mit Namespace-Limits
- Wiki (marketing_page_wiki_* Tabellen + Services/Controller)
- Landing-News (landing_news + Services/Controller)
- PageAI / MarketingMenuAI / PageSeoAI
- Marketing-Newsletter
- MarketingChat / RagChat

### Tenant-Anforderungen (offen, nur Anforderungen)

- Domains → Tenant-Routing (Host → Tenant, kein Match → 404)
- Startseite pro Domain (is_startpage + startpage_domain)
- Abo-Kopplung (max Pages, max Domains, PageModule-Verfügbarkeit)
- Kein namespace-Column, Mandantentrennung via eForms-Tenant-System
- Admin-Auth pro Tenant

## Anweisungen

1. Lies `docs/equiz-migration-prompt.md` als Strukturvorlage
2. Schreibe `docs/econtent-migration-prompt.md` mit allen oben genannten Daten
3. Verwende **exakt dieselbe Struktur** wie die equiz-Spec (Überschriften, Tabellen-Format, Code-Blöcke)
4. Die Datei ist lang (~600-700 Zeilen) - schreibe sie ggf. in Teilen (erst Write, dann Edit)
5. Commit + Push auf Branch `claude/migrate-content-eforms-IPlvQ`

**WICHTIG:** Schreibe die Datei in **mehreren Schritten** falls nötig (erst Abschnitt 0-4, dann 5-9 per Edit anfügen), um Timeouts zu vermeiden.
