# SEO & SEF

edocs.cloud implementiert Suchmaschinenoptimierung (SEO) und Search-Engine-Friendly (SEF) URLs auf mehreren Ebenen.

---

## Implementierte Features

### SEO-Konfiguration pro Seite

Jede CMS-Seite hat eine eigene SEO-Konfiguration (`PageSeoConfig`):

| Feld | Beschreibung |
|---|---|
| `metaTitle` | Seitentitel für Suchmaschinen |
| `metaDescription` | Meta-Beschreibung |
| `canonicalUrl` | Canonical URL |
| `robotsMeta` | Robots-Direktive (index/follow) |
| `ogTitle` | OpenGraph-Titel |
| `ogDescription` | OpenGraph-Beschreibung |
| `ogImage` | OpenGraph-Bild |
| `schemaJson` | Strukturierte Daten (JSON-LD) |
| `hreflang` | Sprachzuordnung |
| `domain` | Domain-Zuordnung |
| `faviconPath` | Favicon-Pfad |

Quelle: `src/Domain/PageSeoConfig.php`, `src/Service/PageSeoConfigService.php`

### Automatische Generierung

Der `PageSeoAiGenerator` (`src/Service/Marketing/PageSeoAiGenerator.php`) kann SEO-Metadaten KI-gestützt generieren.

### Sitemaps

Der `SitemapController` (`src/Controller/Marketing/SitemapController.php`) generiert XML-Sitemaps pro Namespace/Domain.

### RSS/Atom-Feeds

Der `FeedController` stellt automatisch Feeds bereit:

- **RSS:** `/feed/rss`
- **Atom:** `/feed/atom`

### robots.txt

Dynamisch generiert pro Domain über `RobotsTxtController`.

### llms.txt

Für AI-Crawler wird eine `llms.txt` über `LlmsTxtController` bereitgestellt:

- **Standard:** `/llms.txt`
- **Vollständig:** `/llms-full.txt`

### SEF-URLs

- Seiten: `/m/{slug}` oder `/{slug}` (Custom Domain)
- Wiki: `/m/{slug}/wiki/{articleSlug}`
- News: `/news/{newsSlug}`
- Keine Query-Parameter für Content-Seiten

---

## API-Integration

SEO-Daten können über die API geschrieben werden:

```
PUT /api/v1/namespaces/{ns}/pages/{slug}
```

Mit `seo`-Objekt im Body (erfordert Scope `seo:write`).

MCP-Tool: `upsert_page` mit `seo`-Parameter.

---

## Schema.org / Structured Data

Der `SchemaEnhancer` (`src/Service/Seo/SchemaEnhancer.php`) ergänzt Seiten automatisch mit strukturierten Daten.

---

## Beteiligte Dateien

| Datei | Aufgabe |
|---|---|
| `src/Domain/PageSeoConfig.php` | SEO-Datenmodell |
| `src/Service/PageSeoConfigService.php` | SEO-CRUD |
| `src/Service/Marketing/PageSeoAiGenerator.php` | KI-SEO-Generierung |
| `src/Service/Seo/SitemapService.php` | Sitemap-Generierung |
| `src/Service/Seo/FeedService.php` | RSS/Atom-Feeds |
| `src/Service/Seo/SchemaEnhancer.php` | Structured Data |
| `src/Service/Seo/LlmsTxtService.php` | llms.txt |
| `src/Controller/Marketing/SitemapController.php` | Sitemap-Endpoint |
| `src/Controller/Marketing/FeedController.php` | Feed-Endpoints |
| `src/Controller/Marketing/RobotsTxtController.php` | robots.txt |
| `src/Controller/Marketing/LlmsTxtController.php` | llms.txt |
