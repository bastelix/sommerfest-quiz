# News-Modul

Das News-Modul ermöglicht das Erstellen und Verwalten von Nachrichtenartikeln innerhalb eines Namespace. News-Artikel sind an eine CMS-Seite gebunden und werden auf der öffentlichen Website als Blog/Neuigkeiten angezeigt.

---

## Datenmodell

| Feld | Typ | Beschreibung |
|---|---|---|
| `id` | int | Auto-Increment ID |
| `namespace` | string | Zugehöriger Namespace |
| `pageId` | int | Verknüpfte CMS-Seite |
| `slug` | string | URL-Slug (eindeutig pro Namespace) |
| `title` | string | Artikeltitel |
| `content` | text | HTML-Inhalt |
| `excerpt` | string | Anreißertext für Listings |
| `imageUrl` | string | Titelbild-URL |
| `isPublished` | bool | Veröffentlichungsstatus |
| `publishedAt` | datetime | Veröffentlichungszeitpunkt |
| `createdAt` | datetime | Erstellungszeitpunkt |
| `updatedAt` | datetime | Letzte Änderung |

**Beteiligte Dateien:**

- `src/Domain/LandingNews.php`
- `src/Service/LandingNewsService.php`
- `src/Controller/Admin/LandingNewsController.php`
- `src/Controller/Marketing/LandingNewsController.php`
- `src/Controller/Api/V1/NamespaceNewsController.php`
- `src/Service/Mcp/NewsTools.php`

---

## CRUD-Operationen

### Admin-Oberfläche

| Aktion | Route |
|---|---|
| Liste | `GET /admin/landing-news` |
| Erstellen | `GET /admin/landing-news/create` |
| Speichern | `POST /admin/landing-news` |
| Bearbeiten | `GET /admin/landing-news/{id}/edit` |
| Aktualisieren | `PUT /admin/landing-news/{id}` |
| Löschen | `DELETE /admin/landing-news/{id}` |

### API v1

| Method | Pfad | Scope | Beschreibung |
|---|---|---|---|
| `GET` | `/api/v1/namespaces/{ns}/news` | `news:read` | Alle Artikel |
| `GET` | `/api/v1/namespaces/{ns}/news/{id}` | `news:read` | Einzelner Artikel |
| `POST` | `/api/v1/namespaces/{ns}/news` | `news:write` | Erstellen |
| `PATCH` | `/api/v1/namespaces/{ns}/news/{id}` | `news:write` | Aktualisieren |
| `DELETE` | `/api/v1/namespaces/{ns}/news/{id}` | `news:write` | Löschen |

### MCP-Tools

Alle News-Operationen sind über `NewsTools` verfügbar (siehe [MCP-Tool-Referenz](mcp-reference.md#newstools)).

---

## Öffentliche Ansicht

| Route | Beschreibung |
|---|---|
| `/landing/news` | News-Übersicht |
| `/m/{pageSlug}/news` | News-Liste einer Seite |
| `/news/{newsSlug}` | Einzelner Artikel |

### RSS/Atom-Feeds

Der `FeedController` stellt automatisch RSS- und Atom-Feeds bereit:

- **RSS:** `/feed/rss`
- **Atom:** `/feed/atom`

---

## Seitenverknüpfung

Jeder News-Artikel ist über `pageId` an eine CMS-Seite gebunden. Dies ermöglicht:

- Filterung der News pro Seite
- Seitenspezifische News-Widgets
- Zuordnung zu Namespace-Bereichen
