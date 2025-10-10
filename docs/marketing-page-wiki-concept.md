# Konzept: Optionaler Wiki-/Dokumentationsbereich je Marketing-Page

## Zielsetzung

* Redakteur:innen können pro bestehender Marketing-Page zusätzliche Wissensartikel (FAQ, Dokumentation, Schritt-für-Schritt-Anleitungen) pflegen.
* Der Bereich ist **optional** und ergänzt die bestehende Seitenausspielung – der reguläre Page-Content bleibt unverändert.
* Endkund:innen sehen auf Marketing-Seiten einen zusätzlichen Navigationspunkt „Dokumentation“, sofern mindestens ein Wiki-Artikel veröffentlicht wurde.

## Nutzerrollen & Abläufe

| Rolle            | Bedürfnisse                                                                 | Lösung                                                                 |
|------------------|------------------------------------------------------------------------------|------------------------------------------------------------------------|
| Marketing-Team   | Einfach strukturierte Pflege von Artikeln ohne technische Kenntnisse.       | Blockbasierter Editor (Editor.js) + Vorschau wie im News-Modul.        |
| Support/Service  | Versionierbare Artikellisten, Download als Markdown für Offlinenutzung.      | Historie über Versions-Tabelle, Export-Button je Artikel.              |
| Entwicklung      | Einbettung in bestehende Deployment-Pipeline (Markdown-Dateien optional).    | Publisher schreibt `.md` in `content/pages/{locale}/{slug}/wiki/`.     |
| Besucher:innen   | Schnelle Navigation, Filterbarkeit und SEO-freundliche URLs.                 | Breadcrumbs, Suchindexierung, semantische URLs (`/page/wiki/{article}`).|

## Funktionsumfang

1. **Aktivierung pro Page**
   * Checkbox „Dokumentationsbereich aktivieren“ im Tab „Seitenbetreiber“ > „Module“.
   * Ohne Aktivierung bleiben alle Wiki-UI-Elemente im Frontend verborgen.

2. **Artikelverwaltung**
   * Liste aller Artikel mit Status (Entwurf, veröffentlicht, archiviert) und Locale.
   * Aktionen: Neu anlegen, bearbeiten, duplizieren, löschen, exportieren (.md).
   * Kategorien/Tags (optional) für Filterung im Frontend.

3. **Editor-Workflow**
   * Editor.js als Block-Editor (Text, Header, Liste, Tabelle, Code, Hinweisboxen, Bilder).
   * Tabstruktur: „Editor“, „Markdown“, „Vorschau“ analog zum News-Modul.
   * Beim Speichern: Editor.js-JSON → Markdown-Konvertierung → Speicherung in DB, optional Dateisystem.

4. **Frontend-Ausgabe**
   * Reiter oder Abschnitt „Dokumentation“ auf der Marketing-Seite.
   * Routing: `/pages/{slug}/wiki` (Übersicht) und `/pages/{slug}/wiki/{article-slug}` (Detail).
   * Breadcrumbs (`Marketing-Seite > Dokumentation > Artikel`).
   * Suche innerhalb der Artikel (clientseitig via Fuse.js oder serverseitig via LIKE/Fulltext).

## Datenmodell

Neue Tabellen (PostgreSQL):

### `marketing_page_wiki_settings`

| Feld           | Typ              | Beschreibung                                               |
|----------------|------------------|------------------------------------------------------------|
| `page_id` (PK) | UUID / bigint    | FK auf `marketing_pages.id`.                               |
| `is_active`    | BOOLEAN          | Aktiviert den Wiki-Bereich.                                |
| `menu_label`   | VARCHAR(64)      | Optionaler Name statt Standard „Dokumentation“.            |
| `updated_at`   | TIMESTAMP (TZ)   | Automatisches Update (Trigger).                            |

### `marketing_page_wiki_articles`

| Feld              | Typ              | Beschreibung                                                         |
|-------------------|------------------|----------------------------------------------------------------------|
| `id` (PK)         | UUID / bigint    | Primärschlüssel.                                                     |
| `page_id`         | UUID / bigint    | FK auf `marketing_pages`.                                            |
| `slug`            | VARCHAR          | SEO-Slug pro Page & Locale (`UNIQUE (page_id, locale, slug)`).        |
| `locale`          | VARCHAR(5)       | z. B. `de`, `en`.                                                     |
| `title`           | VARCHAR(160)     | Artikelüberschrift.                                                  |
| `excerpt`         | VARCHAR(300)     | Kurzbeschreibung für Listenansichten.                                |
| `editor_json`     | JSONB            | Rohdaten des Editors.                                                |
| `content_md`      | TEXT             | Generiertes Markdown.                                                |
| `content_html`    | TEXT             | Sanitized HTML (CommonMark → HTMLPurifier).                          |
| `status`          | ENUM             | `draft`, `published`, `archived`.                                    |
| `sort_index`      | INTEGER          | Reihenfolge im Listing.                                              |
| `published_at`    | TIMESTAMP (TZ)   | Veröffentlichung.                                                     |
| `updated_at`      | TIMESTAMP (TZ)   | Automatisches Update.                                                |

### `marketing_page_wiki_versions`

Speichert Snapshots je Artikel (ähnlich News-Versionen).

| Feld           | Typ            | Beschreibung                                         |
|----------------|----------------|------------------------------------------------------|
| `id`           | UUID / bigint  | Primärschlüssel.                                     |
| `article_id`   | UUID / bigint  | FK auf `marketing_page_wiki_articles`.               |
| `editor_json`  | JSONB          | Rohdaten zum Zeitpunkt der Versionierung.            |
| `content_md`   | TEXT           | Markdown-Snapshot.                                   |
| `created_at`   | TIMESTAMP (TZ) | Zeitpunkt der Version.                               |
| `created_by`   | UUID           | FK auf Admin-User (optional).                        |

## Backend-Architektur

* **Service-Layer**
  * `WikiSettingsService`: Aktivierung, Menülabel, Feature-Flag je Page verwalten.
  * `WikiArticleService`: CRUD, Sortierung, Statuswechsel, Markdown-Konvertierung, Export.
  * `WikiPublisher`: Schreibt veröffentlichte Artikel als `.md` (optional) und invalidiert Caches.
  * `EditorJsToMarkdown`: Wiederverwendung aus Marketing/News, um JSON → Markdown zu konvertieren.

* **Controller/Routen (Slim)**
  * `GET /admin/pages/{pageId}/wiki`: Einstellungen + Artikelliste.
  * `POST /admin/pages/{pageId}/wiki/settings`: Aktivierung, Menülabel.
  * `POST /admin/pages/{pageId}/wiki/articles`: Artikel erstellen/aktualisieren.
  * `POST /admin/pages/{pageId}/wiki/articles/{id}/status`: Statuswechsel (publish/archive).
  * `GET /admin/pages/{pageId}/wiki/articles/{id}`: Daten für Editor laden.
  * `GET /admin/pages/{pageId}/wiki/articles/{id}/download`: Markdown-Export.
  * Upload-Route `/admin/uploads` weiterverwenden für Bilder innerhalb des Editors.

* **Frontend (Public)**
  * `GET /pages/{slug}/wiki` rendert Liste veröffentlichter Artikel (nach Locale gefiltert).
  * `GET /pages/{slug}/wiki/{articleSlug}` rendert Details, generiert SEO-Metadaten und JSON-LD.
  * Middleware prüft, ob Wiki aktiv ist, sonst 404.

## Admin-UI (UIkit)

* **Tab-Erweiterung**: Im bestehenden Page-Detail erscheinen neue Tabs „Wiki“ und „Einstellungen“ sobald das Feature aktiv ist. Tab „Wiki“ enthält Artikel-Listing + Editor-Modal.
* **Artikel-Modal**: UIkit-Modal mit Editor.js, Tabs für Markdown und Vorschau. Buttons „Speichern“, „Speichern & veröffentlichen“, „Als Markdown herunterladen“.
* **Listenansicht**: Tabelle mit Locale, Titel, Status, Sortier-Drag&Drop (UIkit `uk-sortable`).
* **Feedback**: Notifications für Speichern/Veröffentlichen, unsaved changes warning.

## Dateisystem (optional)

```
content/pages/{locale}/{slug}/wiki/
  ├── index.json          # Cache der Artikel-Metadaten (Titel, Slug, Excerpt, updated_at)
  ├── {article-slug}.md   # Markdown inklusive Front-Matter
```

Front-Matter-Beispiel:

```md
---
title: "Kalibrierschein FAQ"
slug: "faq"
locale: "de"
published_at: "2025-03-01T12:00:00+01:00"
updated_at: "2025-03-02T09:15:00+01:00"
status: "published"
---
# Häufige Fragen
...
```

## Integrationen & Abhängigkeiten

* Wiederverwendung bestehender Markdown-Preview-Pipeline (`League\CommonMark` + HTMLPurifier) aus News.
* Shared Upload-Service für Bilder, der bereits S3/Filesystem-Abstraktion unterstützt.
* Optionales Feature-Flag (z. B. `feature.wiki_enabled`) zur stufenweisen Einführung.

## Migration & Rollout

1. **Migrationen**: Neue Tabellen + Trigger (`updated_at`). Default `is_active = false`.
2. **Backfill**: Kein initialer Content erforderlich; ggf. Skript, das bestehende FAQ-Abschnitte von Seiten in erste Artikel umwandelt.
3. **Feature Toggle**: Aktivierung pro Stage, Schulung des Marketing-Teams.
4. **Monitoring**: Log-Einträge bei Dateischreibfehlern, Sentry-Alerts für 5xx auf neuen Routen.

## Tests & Qualitätssicherung

* **Unit-Tests**: Markdown-Konverter, Service-Methoden (Statuswechsel, Sortierung, Export).
* **Integrationstests**: Admin-Controller (CRUD, Rechte, Validierung), Public-Controller (404 bei inaktivem Wiki, korrekte Locale-Filterung).
* **UI-Tests**: Cypress/Playwright-Flows für Artikel-Erstellung und Frontend-Darstellung.
* **Performance**: Sicherstellen, dass zusätzliche Queries lazy geladen werden und Caching für Listings genutzt wird (z. B. HTTP-Cache, Redis-Key `wiki:{pageId}:{locale}`).

## Security & Berechtigungen

* Nur Admin-Rolle darf Wiki verwalten; Rechtemodell analog News.
* CSRF-Schutz bei allen POST-Routen.
* Sanitizing des gerenderten HTML (Preview + Public-Ausgabe).
* Rate-Limiting & Audit-Log optional bei häufigen Änderungen.

## Offene Fragen

* Sollen Artikel mehrere Locale-Versionen parallel anzeigen (Fallback, Copy-Paste)?
* Werden strukturierte Daten (FAQPage JSON-LD) benötigt? → Falls ja, bei Artikel mit Q&A-Blöcken automatisch generieren.
* Soll die Suche seitenübergreifend (alle Pages) oder pro Page laufen? Aktuell vorgesehen: pro Page.
* Brauchen wir API-Export (REST/GraphQL) für Dritt-Systeme? Könnte später ergänzt werden.

## Aufwandsschätzung (High-Level)

| Paket                                 | Aufwand (Personentage) |
|---------------------------------------|------------------------|
| Migrationen & Services                | 2,0                    |
| Admin-UI-Implementierung              | 2,5                    |
| Public-Frontend & Routing             | 1,5                    |
| Tests (Unit + Integration + UI)       | 1,5                    |
| Dokumentation & Rollout               | 0,5                    |
| **Summe**                             | **8,0**                |

## Ergebnis

Der neue Wiki-/Dokumentationsbereich erweitert Marketing-Pages modular, ohne den bestehenden Content-Workflow zu ersetzen. Redakteur:innen erhalten ein vertrautes UI (Editor.js + Tabs), das konsistente Markdown-Dateien produziert und sich in News- und Static-Site-Prozesse integriert. Besucher:innen finden strukturierte Zusatzinformationen, während die technische Umsetzung mit bestehenden Patterns (Services, Publisher, Filesystem) harmoniert.

## Codeaufgaben

### Datenbank & Domain-Logik

1. Migrationen für `marketing_page_wiki_settings`, `marketing_page_wiki_articles` und `marketing_page_wiki_versions` inklusive Trigger für `updated_at`-Spalten anlegen.
2. Doctrine/Atlas-Mapper (je nach Projektstandard) für die neuen Tabellen erstellen und in das bestehende Repository-Layer integrieren.
3. Services implementieren:
   * `WikiSettingsService` mit Methoden `enableForPage`, `disableForPage`, `updateMenuLabel`, `isActive`.
   * `WikiArticleService` mit CRUD-Operationen, Statuswechsel, Sortierung (Drag&Drop-Index) und Markdown-/HTML-Konvertierung.
   * `WikiPublisher`, der veröffentlichte Artikel als Markdown-Dateien schreibt (Feature-Flag beachten) und Caches invalidiert.
4. Unit-Tests für Services inklusive Markdown-Konverter und Sortierlogik schreiben.

### Admin-Backend (Slim)

5. Routen und Controller-Actions für die Wiki-Verwaltung ergänzen (`GET/POST /admin/pages/{pageId}/wiki`, Artikel-CRUD, Statuswechsel, Export).
6. Request-Validierungen und Berechtigungsprüfungen (nur Admin-Rolle) implementieren.
7. Integrationstests für Controller (Happy Path, Fehlerfälle, Berechtigungen) hinzufügen.

### Admin-UI

8. Im bestehenden Marketing-Page-Detail zwei neue Tabs „Wiki“ und „Einstellungen“ implementieren, sichtbar wenn das Feature aktiviert ist.
9. Artikel-Listing mit UIkit-Tabelle und Drag&Drop-Sortierung bauen, inklusive Aktionen (Bearbeiten, Duplizieren, Löschen, Exportieren).
10. Editor-Modal mit Editor.js, Markdown-Tab und Vorschau-Tab einbinden; Autosave/Unsaved-Changes-Indikatoren ergänzen.
11. UI-Tests (Cypress/Playwright) für das Erstellen, Bearbeiten und Veröffentlichen eines Artikels schreiben.

### Öffentliches Frontend

12. Routing für `/pages/{slug}/wiki` (Übersicht) und `/pages/{slug}/wiki/{articleSlug}` (Detail) hinzufügen; Middleware prüft `is_active` und Locale.
13. Twig-Templates für Übersicht, Breadcrumbs, Artikeldetail inkl. SEO-Metadaten und optional JSON-LD erzeugen.
14. Clientseitige Suche (z. B. Fuse.js) oder serverseitige Filterung integrieren; Konfigurierbar per Feature-Flag.
15. Integrationstests für Public-Routen (404 bei inaktivem Wiki, richtige Locale-Filterung) ergänzen.

### Filesystem & Infrastruktur

16. Optionale Publisher-Logik, die Markdown-Dateien unter `content/pages/{locale}/{slug}/wiki/` ablegt, implementieren und mit Feature-Flag absichern.
17. Monitoring-Hooks (Logging, Sentry-Kontext) für Fehlerszenarien beim Schreiben/Löschen der Dateien ergänzen.
18. Dokumentation im Entwicklerhandbuch/README zu neuen Env-Variablen, Feature-Flags und Deploy-Schritten aktualisieren.

### Rollout

19. Feature-Flag `feature.wiki_enabled` einführen und Standardwerte in allen Environments setzen.
20. Release-Checkliste erstellen (Migration, Backfill-Skript optional, Schulungsunterlagen verlinken).
