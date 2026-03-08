# API v1 – Vollständige Referenz

> **Base-URL:** `/api/v1`
> **Authentifizierung:** Bearer-Token im `Authorization`-Header
> **Content-Type:** `application/json`

---

## Inhaltsverzeichnis

1. [Authentifizierung](#authentifizierung)
2. [Scopes](#scopes)
3. [Fehlerformat](#fehlerformat)
4. [Pages (CMS-Seiten)](#pages-cms-seiten)
5. [Menus (Navigationsmenüs)](#menus-navigationsmenüs)
6. [Menu Items (Menüeinträge)](#menu-items-menüeinträge)
7. [News (Neuigkeiten / Blog)](#news-neuigkeiten--blog)
8. [Interne Endpunkte (Session-basiert)](#interne-endpunkte-session-basiert)

---

## Authentifizierung

Alle `/api/v1`-Endpunkte werden über die `ApiTokenAuthMiddleware` geschützt. Jeder Request benötigt einen gültigen **Bearer-Token**:

```
Authorization: Bearer <token>
```

Tokens werden pro Namespace ausgestellt und über die Admin-Oberfläche (`/admin/namespaces`) verwaltet. Jeder Token ist an genau einen Namespace gebunden und besitzt eine Menge von **Scopes**, die den Zugriff auf Ressourcen steuern.

### Fehlerhafte Authentifizierung

| HTTP-Status | Fehler-Code      | Bedeutung                                      |
|-------------|------------------|-------------------------------------------------|
| `401`       | `missing_token`  | Kein oder leerer `Authorization`-Header          |
| `403`       | `invalid_token`  | Token ungültig oder abgelaufen                   |
| `403`       | `missing_scope`  | Token besitzt nicht den erforderlichen Scope     |
| `403`       | `namespace_mismatch` | Token-Namespace stimmt nicht mit URL-Namespace überein |

---

## Scopes

| Scope         | Beschreibung                                  | Verwendung                           |
|---------------|-----------------------------------------------|--------------------------------------|
| `cms:read`    | Seiten lesen (Liste, Tree)                    | Pages GET-Endpunkte                  |
| `cms:write`   | Seiten anlegen und aktualisieren              | Pages PUT-Endpunkt                   |
| `seo:write`   | SEO-Konfiguration einer Seite schreiben       | Page-Upsert mit `seo`-Payload       |
| `menu:read`   | Menüs und Menüeinträge lesen                  | Menus/Items GET-Endpunkte            |
| `menu:write`  | Menüs und Menüeinträge erstellen/ändern/löschen | Menus/Items POST/PATCH/DELETE        |
| `news:read`   | News-Artikel lesen                             | News GET-Endpunkte                   |
| `news:write`  | News-Artikel erstellen/ändern/löschen          | News POST/PATCH/DELETE               |

---

## Fehlerformat

Alle Fehlerantworten haben folgendes JSON-Format:

```json
{
  "error": "<error_code>",
  "message": "<optional, bei 500-Fehlern>",
  "details": ["<optional, bei Validierungsfehlern>"]
}
```

---

## Pages (CMS-Seiten)

### Alle Seiten eines Namespace auflisten

```
GET /api/v1/namespaces/{ns}/pages
```

**Scope:** `cms:read`

**Response `200`:**

```json
{
  "namespace": "mein-namespace",
  "pages": [
    {
      "id": 1,
      "namespace": "mein-namespace",
      "slug": "startseite",
      "title": "Startseite",
      "status": "published",
      "type": "page",
      "language": "de"
    }
  ]
}
```

---

### Seitenbaum abrufen

```
GET /api/v1/namespaces/{ns}/pages/tree
```

**Scope:** `cms:read`

**Response `200`:**

```json
{
  "namespace": "mein-namespace",
  "tree": [
    {
      "slug": "startseite",
      "title": "Startseite",
      "children": []
    }
  ]
}
```

---

### Seite erstellen oder aktualisieren (Upsert)

```
PUT /api/v1/namespaces/{ns}/pages/{slug}
```

**Scope:** `cms:write` (+ optional `seo:write`, `menu:write`)

**Request-Body:**

```json
{
  "blocks": [
    {
      "type": "hero",
      "data": { "headline": "Willkommen" }
    }
  ],
  "meta": {
    "layout": "default"
  },
  "title": "Meine Seite",
  "status": "published",
  "seo": {
    "slug": "meine-seite",
    "metaTitle": "Meine Seite – Titel",
    "metaDescription": "Beschreibung für Suchmaschinen",
    "canonicalUrl": "https://example.com/meine-seite",
    "robotsMeta": "index, follow",
    "ogTitle": "OG-Titel",
    "ogDescription": "OG-Beschreibung",
    "ogImage": "https://example.com/og.jpg",
    "schemaJson": "{}",
    "hreflang": "de",
    "domain": "example.com",
    "faviconPath": "/favicon.ico"
  },
  "menuAssignments": [
    {
      "slot": "header",
      "menuId": 1,
      "locale": "de",
      "isActive": true,
      "pageScoped": true
    }
  ]
}
```

| Feld              | Typ       | Pflicht | Beschreibung                                         |
|-------------------|-----------|---------|------------------------------------------------------|
| `blocks`          | `array`   | Ja      | Liste der Block-Objekte (validiert gegen Block-Contract-Schema) |
| `meta`            | `object`  | Nein    | Metadaten (z.B. Layout)                               |
| `title`           | `string`  | Nein    | Seitentitel (bei Neuanlage wird Slug als Fallback verwendet) |
| `status`          | `string`  | Nein    | `"draft"` oder `"published"`                          |
| `seo`             | `object`  | Nein    | SEO-Konfiguration (erfordert Scope `seo:write`)       |
| `menuAssignments` | `array`   | Nein    | Menü-Zuweisungen (erfordert Scope `menu:write`)       |

**Response `200`:**

```json
{
  "status": "ok",
  "namespace": "mein-namespace",
  "slug": "meine-seite",
  "pageId": 42
}
```

**Fehlercodes:**

| Status | Code                     | Beschreibung                          |
|--------|--------------------------|---------------------------------------|
| `400`  | `invalid_json`           | Body ist kein gültiges JSON           |
| `403`  | `missing_scope`          | Benötigter Scope fehlt (seo/menu)     |
| `422`  | `invalid_blocks`         | `blocks` ist kein Array               |
| `422`  | `block_contract_invalid` | Block-Contract-Validierung fehlgeschlagen |
| `422`  | `block_schema_invalid`   | Strikte Schema-Validierung fehlgeschlagen |
| `422`  | `invalid_status`         | Status ist nicht `draft`/`published`  |
| `422`  | `invalid_title`          | Titel hat ungültigen Typ              |
| `500`  | `encode_failed`          | JSON-Encoding fehlgeschlagen          |
| `500`  | `page_not_found_after_upsert` | Seite nach Speichern nicht gefunden |

---

## Menus (Navigationsmenüs)

### Alle Menüs auflisten

```
GET /api/v1/namespaces/{ns}/menus
```

**Scope:** `menu:read`

**Response `200`:**

```json
{
  "namespace": "mein-namespace",
  "menus": [
    {
      "id": 1,
      "namespace": "mein-namespace",
      "label": "Hauptmenü",
      "locale": "de",
      "isActive": true,
      "updatedAt": "2026-03-08T12:00:00+00:00"
    }
  ]
}
```

---

### Menü erstellen

```
POST /api/v1/namespaces/{ns}/menus
```

**Scope:** `menu:write`

**Request-Body:**

```json
{
  "label": "Footermenü",
  "locale": "de",
  "isActive": true
}
```

| Feld       | Typ      | Pflicht | Beschreibung                          |
|------------|----------|---------|---------------------------------------|
| `label`    | `string` | Ja      | Anzeigename des Menüs                 |
| `locale`   | `string` | Nein    | Sprachcode (z.B. `"de"`, `"en"`)     |
| `isActive` | `bool`   | Nein    | Aktiv-Status (Standard: `true`)       |

**Response `201`:**

```json
{
  "status": "created",
  "menu": {
    "id": 2,
    "namespace": "mein-namespace",
    "label": "Footermenü",
    "locale": "de",
    "isActive": true,
    "updatedAt": "2026-03-08T12:00:00+00:00"
  }
}
```

---

### Menü aktualisieren

```
PATCH /api/v1/namespaces/{ns}/menus/{menuId}
```

**Scope:** `menu:write`

**Request-Body:** Identisch mit POST (alle Felder erforderlich).

**Response `200`:**

```json
{
  "status": "updated",
  "menu": { "id": 1, "label": "Hauptmenü (neu)", "..." : "..." }
}
```

---

### Menü löschen

```
DELETE /api/v1/namespaces/{ns}/menus/{menuId}
```

**Scope:** `menu:write`

**Response `200`:**

```json
{ "status": "deleted" }
```

---

## Menu Items (Menüeinträge)

### Einträge eines Menüs auflisten

```
GET /api/v1/namespaces/{ns}/menus/{menuId}/items
```

**Scope:** `menu:read`

**Query-Parameter:**

| Parameter | Typ      | Beschreibung                    |
|-----------|----------|---------------------------------|
| `locale`  | `string` | Filtert nach Sprache (optional) |

**Response `200`:**

```json
{
  "namespace": "mein-namespace",
  "menuId": 1,
  "locale": "de",
  "items": [
    {
      "id": 10,
      "menuId": 1,
      "namespace": "mein-namespace",
      "parentId": null,
      "label": "Startseite",
      "href": "/",
      "icon": null,
      "layout": "default",
      "detailTitle": null,
      "detailText": null,
      "detailSubline": null,
      "position": 0,
      "locale": "de",
      "isExternal": false,
      "isActive": true,
      "isStartpage": true,
      "children": [
        {
          "id": 11,
          "label": "Unterseite",
          "href": "/unterseite",
          "children": []
        }
      ]
    }
  ]
}
```

Die Items werden als **Baumstruktur** zurückgegeben. Kinder-Einträge werden über `parentId` zugeordnet.

---

### Menüeintrag erstellen

```
POST /api/v1/namespaces/{ns}/menus/{menuId}/items
```

**Scope:** `menu:write`

**Request-Body:**

```json
{
  "label": "Kontakt",
  "href": "/kontakt",
  "icon": "mail",
  "parentId": null,
  "layout": "default",
  "detailTitle": "Kontaktseite",
  "detailText": "Nehmen Sie Kontakt auf",
  "detailSubline": "Wir freuen uns auf Ihre Nachricht",
  "position": 5,
  "isExternal": false,
  "locale": "de",
  "isActive": true,
  "isStartpage": false
}
```

| Feld            | Typ      | Pflicht | Beschreibung                              |
|-----------------|----------|---------|-------------------------------------------|
| `label`         | `string` | Ja      | Anzeigename                               |
| `href`          | `string` | Ja      | Ziel-URL oder Pfad                        |
| `icon`          | `string` | Nein    | Icon-Bezeichner                           |
| `parentId`      | `int`    | Nein    | ID des Eltern-Eintrags (für Verschachtelung) |
| `layout`        | `string` | Nein    | Layout-Variante (Standard: `"default"`)   |
| `detailTitle`   | `string` | Nein    | Detail-Titel (für Mega-Menüs)            |
| `detailText`    | `string` | Nein    | Detail-Text                               |
| `detailSubline` | `string` | Nein    | Detail-Unterzeile                         |
| `position`      | `int`    | Nein    | Sortierreihenfolge                        |
| `isExternal`    | `bool`   | Nein    | Externer Link (Standard: `false`)         |
| `locale`        | `string` | Nein    | Sprachcode                                |
| `isActive`      | `bool`   | Nein    | Aktiv-Status (Standard: `true`)           |
| `isStartpage`   | `bool`   | Nein    | Als Startseite markieren (Standard: `false`) |

**Response `201`:**

```json
{ "status": "created", "id": 12 }
```

---

### Menüeintrag aktualisieren

```
PATCH /api/v1/namespaces/{ns}/menus/{menuId}/items/{itemId}
```

**Scope:** `menu:write`

**Request-Body:** Identisch mit POST (alle Felder erforderlich).

**Response `200`:**

```json
{ "status": "updated", "id": 12 }
```

---

### Menüeintrag löschen

```
DELETE /api/v1/namespaces/{ns}/menus/{menuId}/items/{itemId}
```

**Scope:** `menu:write`

**Response `200`:**

```json
{ "status": "deleted" }
```

---

## News (Neuigkeiten / Blog)

### Alle News-Artikel auflisten

```
GET /api/v1/namespaces/{ns}/news
```

**Scope:** `news:read`

**Response `200`:**

```json
{
  "namespace": "mein-namespace",
  "news": [
    {
      "id": 1,
      "pageId": 42,
      "slug": "erstes-update",
      "title": "Erstes Update",
      "excerpt": "Kurzer Anreißer...",
      "content": "<p>Vollständiger HTML-Inhalt</p>",
      "imageUrl": "https://example.com/bild.jpg",
      "isPublished": true,
      "publishedAt": "2026-03-08T10:00:00+00:00",
      "createdAt": "2026-03-07T08:00:00+00:00",
      "updatedAt": "2026-03-08T10:00:00+00:00"
    }
  ]
}
```

---

### Einzelnen News-Artikel abrufen

```
GET /api/v1/namespaces/{ns}/news/{id}
```

**Scope:** `news:read`

**Response `200`:**

```json
{
  "namespace": "mein-namespace",
  "news": {
    "id": 1,
    "pageId": 42,
    "slug": "erstes-update",
    "title": "Erstes Update",
    "excerpt": "Kurzer Anreißer...",
    "content": "<p>Vollständiger HTML-Inhalt</p>",
    "imageUrl": "https://example.com/bild.jpg",
    "isPublished": true,
    "publishedAt": "2026-03-08T10:00:00+00:00",
    "createdAt": "2026-03-07T08:00:00+00:00",
    "updatedAt": "2026-03-08T10:00:00+00:00"
  }
}
```

**Fehlercodes:**

| Status | Code        | Beschreibung           |
|--------|-------------|------------------------|
| `400`  | `invalid_id`| ID ist keine gültige Zahl |
| `404`  | `not_found` | Artikel nicht gefunden  |

---

### News-Artikel erstellen

```
POST /api/v1/namespaces/{ns}/news
```

**Scope:** `news:write`

**Request-Body:**

```json
{
  "pageId": 42,
  "slug": "neuer-artikel",
  "title": "Neuer Artikel",
  "content": "<p>Inhalt des Artikels</p>",
  "excerpt": "Kurzer Anreißer",
  "imageUrl": "https://example.com/bild.jpg",
  "isPublished": true,
  "publishedAt": "2026-03-08T10:00:00+00:00"
}
```

| Feld          | Typ      | Pflicht | Beschreibung                              |
|---------------|----------|---------|-------------------------------------------|
| `pageId`      | `int`    | Ja      | ID der zugehörigen CMS-Seite              |
| `slug`        | `string` | Ja      | URL-freundlicher Bezeichner               |
| `title`       | `string` | Ja      | Titel des Artikels                        |
| `content`     | `string` | Ja      | Vollständiger Inhalt (HTML)               |
| `excerpt`     | `string` | Nein    | Kurzer Anreißertext                       |
| `imageUrl`    | `string` | Nein    | URL zum Titelbild                         |
| `isPublished` | `bool`   | Nein    | Veröffentlicht? (Standard: `false`)       |
| `publishedAt` | `string` | Nein    | Veröffentlichungsdatum (ISO 8601 / UTC)   |

**Response `201`:**

```json
{
  "status": "created",
  "news": { "id": 2, "slug": "neuer-artikel", "..." : "..." }
}
```

**Fehlercodes:**

| Status | Code                    | Beschreibung                          |
|--------|-------------------------|---------------------------------------|
| `400`  | `invalid_json`          | Body ist kein gültiges JSON           |
| `409`  | `conflict`              | Slug bereits vergeben                 |
| `422`  | `missing_required_fields` | Pflichtfelder fehlen                |
| `422`  | `validation_failed`     | Inhalt entspricht nicht den Regeln    |
| `422`  | `invalid_published_at`  | Datum nicht parsbar                   |

---

### News-Artikel aktualisieren

```
PATCH /api/v1/namespaces/{ns}/news/{id}
```

**Scope:** `news:write`

**Request-Body:** Alle Felder sind optional. Nicht übergebene Felder behalten ihren bisherigen Wert.

```json
{
  "title": "Aktualisierter Titel",
  "isPublished": true
}
```

**Response `200`:**

```json
{
  "status": "updated",
  "news": { "id": 1, "title": "Aktualisierter Titel", "..." : "..." }
}
```

**Fehlercodes:** Identisch mit POST, zusätzlich:

| Status | Code                      | Beschreibung                  |
|--------|---------------------------|-------------------------------|
| `404`  | `not_found`               | Artikel nicht gefunden        |
| `422`  | `content_cannot_be_empty` | Inhalt darf nicht leer werden |

---

### News-Artikel löschen

```
DELETE /api/v1/namespaces/{ns}/news/{id}
```

**Scope:** `news:write`

**Response `200`:**

```json
{ "status": "deleted" }
```

| Status | Code        | Beschreibung           |
|--------|-------------|------------------------|
| `404`  | `not_found` | Artikel nicht gefunden  |

---

## Interne Endpunkte (Session-basiert)

Die folgenden Endpunkte verwenden **keine** Bearer-Token-Authentifizierung, sondern Session/Cookie-basierte Authentifizierung. Sie dienen der internen Anwendung (Admin-UI, Quiz-Frontend).

### Quiz & Spieler

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/api/players`                        | Spieler-Daten abrufen (`event_uid`, `player_uid`) |
| `POST`   | `/api/players`                        | Spieler registrieren/aktualisieren          |
| `GET`    | `/api/quiz-progress`                  | Quiz-Fortschritt eines Spielers abrufen     |
| `POST`   | `/api/player-contact`                 | Kontaktdaten hinterlegen (Opt-in)           |
| `POST`   | `/api/player-contact/confirm`         | Kontakt-Opt-in bestätigen                   |
| `DELETE` | `/api/player-contact`                 | Kontaktdaten löschen                        |

### Team-Namen (KI-generiert)

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/api/team-names/batch`               | Batch-Vorschläge abrufen                    |
| `GET`    | `/api/team-names/status`              | Warmup-Status prüfen                        |
| `POST`   | `/api/team-names/preview`             | Vorschau generieren                         |
| `GET`    | `/api/team-names/history`             | Verlauf abrufen                             |
| `POST`   | `/api/team-names`                     | Team-Name speichern                         |
| `DELETE` | `/api/team-names/by-name`             | Team-Name nach Name löschen                 |
| `POST`   | `/api/team-names/{token}/confirm`     | Vorschlag bestätigen                        |
| `DELETE` | `/api/team-names/{token}`             | Vorschlag ablehnen                          |

### Konfiguration & Einstellungen

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/config.json`                        | Aktive Konfiguration laden                  |
| `POST`   | `/config.json`                        | Konfiguration speichern                     |
| `GET`    | `/events/{uid}/config.json`           | Event-spezifische Konfiguration             |
| `GET`    | `/settings.json`                      | Globale Einstellungen laden                 |
| `POST`   | `/settings.json`                      | Globale Einstellungen speichern             |

### Kataloge (Quiz-Stationen)

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/kataloge/{file}`                    | Fragenkatalog einer Station laden           |
| `POST`   | `/kataloge/{file}`                    | Fragenkatalog speichern                     |
| `PUT`    | `/kataloge/{file}`                    | Fragenkatalog komplett ersetzen             |
| `DELETE` | `/kataloge/{file}`                    | Fragenkatalog löschen                       |
| `DELETE` | `/kataloge/{file}/{index}`            | Einzelne Frage löschen                      |
| `GET`    | `/catalog/questions/{file}`           | Fragen eines Katalogs abrufen               |

### Events

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/events.json`                        | Alle Events auflisten                       |
| `POST`   | `/events.json`                        | Event erstellen/aktualisieren               |
| `GET`    | `/admin/event/{id}`                   | Event-Details (Admin)                       |
| `POST`   | `/admin/event/{id}/dashboard-token`   | Dashboard-Token generieren                  |

### Teams

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/teams.json`                         | Alle Teams laden                            |
| `POST`   | `/teams.json`                         | Team erstellen/aktualisieren                |
| `DELETE` | `/teams.json`                         | Team löschen                                |

### Benutzer & Authentifizierung

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/users.json`                         | Benutzer auflisten                          |
| `POST`   | `/users.json`                         | Benutzer erstellen/aktualisieren            |
| `POST`   | `/password`                           | Passwort ändern                             |
| `POST`   | `/password/reset/request`             | Passwort-Reset anfordern                    |
| `POST`   | `/password/reset/confirm`             | Passwort-Reset bestätigen                   |
| `POST`   | `/password/set`                       | Neues Passwort setzen (mit Token)           |
| `POST`   | `/login`                              | Anmelden                                    |
| `GET`    | `/logout`                             | Abmelden                                    |
| `POST`   | `/register`                           | Registrieren                                |
| `POST`   | `/auth/google`                        | Google-Login                                |
| `POST`   | `/auth/google/onboarding`             | Google-Onboarding                           |

### Tenants (Multi-Tenancy)

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/tenants`                            | Alle Tenants auflisten (Admin-UI)           |
| `GET`    | `/tenants.json`                       | Tenants als JSON                            |
| `POST`   | `/tenants`                            | Tenant erstellen                            |
| `GET`    | `/tenants/{subdomain}`                | Tenant-Details                              |
| `GET`    | `/tenants/export`                     | Tenant-Export                               |
| `GET`    | `/tenants/report`                     | Tenant-Report                               |
| `DELETE` | `/tenants`                            | Tenant löschen                              |
| `POST`   | `/tenants/sync`                       | Tenant synchronisieren                      |
| `POST`   | `/tenants/{subdomain}/welcome`        | Willkommens-Mail senden                     |
| `POST`   | `/api/tenants/{slug}/onboard`         | Tenant onboarden                            |
| `DELETE` | `/api/tenants/{slug}`                 | Tenant löschen (API)                        |

### Domains & SSL

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/admin/domains/api`                  | Domains auflisten                           |
| `POST`   | `/admin/domains/api`                  | Domain erstellen                            |
| `PATCH`  | `/admin/domains/api/{id}`             | Domain aktualisieren                        |
| `DELETE` | `/admin/domains/api/{id}`             | Domain löschen                              |
| `POST`   | `/admin/domains/api/{id}/renew-ssl`   | SSL-Zertifikat erneuern                     |
| `POST`   | `/api/admin/domains/{id}/provision-ssl` | SSL provisionieren                        |
| `POST`   | `/api/renew-ssl`                      | Globales SSL-Renewal                        |
| `POST`   | `/api/tenants/{slug}/renew-ssl`       | Tenant-spezifisches SSL-Renewal             |

### Namespaces (Admin)

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/admin/namespaces`                   | Namespace-Verwaltung (UI)                   |
| `GET`    | `/admin/namespaces/data`              | Namespaces als JSON                         |
| `POST`   | `/admin/namespaces`                   | Namespace erstellen                         |
| `PATCH`  | `/admin/namespaces/{namespace}`       | Namespace aktualisieren                     |
| `DELETE` | `/admin/namespaces/{namespace}`       | Namespace löschen                           |

### Kontaktformulare & Newsletter

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `POST`   | `/api/contact-form`                   | Kontaktformular absenden                    |
| `POST`   | `/api/newsletter-subscribe`           | Newsletter abonnieren                       |
| `POST`   | `/newsletter/unsubscribe`             | Newsletter abbestellen                      |

### Chat (RAG-basiert)

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `POST`   | `/calhelp/chat`                       | Chat-Anfrage (CalHelp)                      |
| `GET`    | `/admin/domain-chat/documents`        | Chat-Dokumente auflisten                    |
| `GET`    | `/admin/domain-chat/index`            | Chat-Index abrufen                          |
| `POST`   | `/admin/domain-chat/documents`        | Chat-Dokument hochladen                     |
| `DELETE` | `/admin/domain-chat/documents/{id}`   | Chat-Dokument löschen                       |
| `POST`   | `/admin/domain-chat/wiki-selection`   | Wiki-Selektion für Chat                     |
| `POST`   | `/admin/domain-chat/rebuild`          | Chat-Index neu aufbauen                     |

### Medien & Uploads

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/uploads/projects/{namespace}/{file}` | Projekt-Medien abrufen                     |
| `GET`    | `/uploads/{namespace}/{file}`         | Globale Medien abrufen                      |
| `GET`    | `/uploads/{file}`                     | Legacy-Upload abrufen                       |
| `POST`   | `/photos`                             | Foto hochladen (Beweisfoto)                 |
| `POST`   | `/photos/rotate`                      | Foto rotieren                               |
| `GET`    | `/photo/{team}/{file}`                | Beweisfoto abrufen                          |
| `POST`   | `/logo.png`                           | Logo hochladen (PNG)                        |
| `POST`   | `/logo.webp`                          | Logo hochladen (WebP)                       |
| `POST`   | `/logo.svg`                           | Logo hochladen (SVG)                        |
| `POST`   | `/qrlogo.png`                         | QR-Logo hochladen (PNG)                     |
| `POST`   | `/qrlogo.webp`                        | QR-Logo hochladen (WebP)                    |

### Backups & Import/Export

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/backups`                            | Backups auflisten                           |
| `GET`    | `/backups/{name}/download`            | Backup herunterladen                        |
| `POST`   | `/backups/{name}/restore`             | Backup wiederherstellen                     |
| `DELETE` | `/backups/{name}`                     | Backup löschen                              |
| `POST`   | `/import`                             | Daten importieren                           |
| `POST`   | `/import/{name}`                      | Benannten Import ausführen                  |
| `POST`   | `/export`                             | Daten exportieren                           |
| `POST`   | `/restore-default`                    | Standarddaten wiederherstellen              |
| `POST`   | `/export-default`                     | Standarddaten exportieren                   |

### Katalog-Design & Sticker

| Methode  | Pfad                                         | Beschreibung                          |
|----------|-----------------------------------------------|---------------------------------------|
| `GET`    | `/catalog/{slug}/design`                      | Katalog-Design laden                  |
| `POST`   | `/catalog/{slug}/design`                      | Katalog-Design speichern              |
| `GET`    | `/admin/sticker-settings`                     | Sticker-Einstellungen laden           |
| `POST`   | `/admin/sticker-settings`                     | Sticker-Einstellungen speichern       |
| `POST`   | `/admin/sticker-background`                   | Sticker-Hintergrund hochladen         |
| `GET`    | `/admin/reports/catalog-stickers.pdf`         | Sticker-Report als PDF                |

### Mail-Konfiguration

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/admin/mail-providers`               | Mail-Provider auflisten                     |
| `POST`   | `/admin/mail-providers`               | Mail-Provider konfigurieren                 |
| `POST`   | `/admin/mail-providers/test`          | Mail-Provider testen                        |
| `GET`    | `/settings/mail`                      | Mail-Einstellungen laden                    |
| `POST`   | `/settings/mail`                      | Mail-Einstellungen speichern                |
| `POST`   | `/settings/mail/test`                 | Test-Mail senden                            |

### Username-Blocklist

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/admin/username-blocklist`           | Blockliste anzeigen                         |
| `POST`   | `/admin/username-blocklist`           | Eintrag hinzufügen                          |
| `POST`   | `/admin/username-blocklist/import`    | Blockliste importieren                      |
| `DELETE` | `/admin/username-blocklist/{id}`      | Eintrag löschen                             |

### Prompt-Templates (KI)

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/admin/prompt-templates`             | Templates anzeigen (UI)                     |
| `GET`    | `/admin/prompt-templates/data`        | Templates als JSON                          |
| `PATCH`  | `/admin/prompt-templates/{id}`        | Template aktualisieren                      |

### Sonstiges

| Methode  | Pfad                                  | Beschreibung                                |
|----------|---------------------------------------|---------------------------------------------|
| `GET`    | `/qr.png`                             | QR-Code als PNG                             |
| `GET`    | `/qr.pdf`                             | QR-Code als PDF                             |
| `GET`    | `/qr/catalog`                         | Katalog-QR-Code                             |
| `GET`    | `/qr/team`                            | Team-QR-Code                                |
| `GET`    | `/qr/event`                           | Event-QR-Code                               |
| `GET`    | `/invites.pdf`                        | Einladungen als PDF                         |
| `GET`    | `/ranking`                            | Rangliste anzeigen                          |
| `GET`    | `/results-hub`                        | Ergebnis-Hub                                |
| `DELETE` | `/results`                            | Ergebnisse löschen                          |
| `POST`   | `/session/catalog`                    | Katalog-Session starten                     |
| `POST`   | `/nginx-reload`                       | Nginx-Konfiguration neu laden               |
| `POST`   | `/api/docker/build`                   | Docker-Build anstoßen                       |
| `POST`   | `/stripe/webhook`                     | Stripe-Webhook empfangen                    |

---

## Datenmodelle

### Fragentypen (Katalog-Stationen)

Jeder Fragenkatalog (`/kataloge/{file}`) enthält ein Array von Fragen. Folgende Typen werden unterstützt:

| Typ      | Beschreibung                                   | Pflichtfelder                               |
|----------|-------------------------------------------------|---------------------------------------------|
| `mc`     | Multiple-Choice-Frage                           | `prompt`, `options`, `answers`, `countdown` |
| `assign` | Zuordnungsaufgabe (Term ↔ Definition)           | `prompt`, `terms[].term`, `terms[].definition` |
| `sort`   | Sortieraufgabe                                  | `prompt`, `items`                           |
| `flip`   | Flip-Card (Eingabefeld)                         | `prompt`, `answer`                          |
| `swipe`  | Swipe-Karten (Richtig/Falsch)                   | `prompt`, `rightLabel`, `leftLabel`, `cards[].text`, `cards[].correct` |

### Event

```json
{
  "uid": "1",
  "name": "Sommerfest 2025",
  "start_date": "2025-07-04T18:00",
  "end_date": "2025-07-04T23:00",
  "description": "Willkommen beim Veranstaltungsquiz"
}
```

### Katalog (Station)

```json
{
  "uid": "340da4f1-d796-49c2-aeaf-932280de5167",
  "id": 1,
  "slug": "station_1",
  "file": "station_1.json",
  "name": "Station-1",
  "description": "Speicher und Geräte",
  "raetsel_buchstabe": "A",
  "comment": ""
}
```

### Profil

```json
{
  "uid": "main",
  "subdomain": "main",
  "plan": "",
  "billing_info": "",
  "imprint_name": "Example Org",
  "imprint_street": "Example Street 1",
  "imprint_zip": "12345",
  "imprint_city": "Example City",
  "imprint_email": "info@example.com",
  "created_at": ""
}
```
