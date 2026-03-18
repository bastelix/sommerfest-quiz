# MCP-Connector einrichten (Claude.ai)

> Anleitung zum Verbinden von Claude.ai mit dem QuizRace MCP-Server.

---

## Voraussetzungen

- Ein laufender QuizRace-Server mit HTTPS (z. B. `https://mein-quiz.example.com`)
- Der MCP-Endpunkt ist unter `https://<deine-domain>/mcp` erreichbar
- OAuth 2.0 ist aktiv (automatisch, wenn der Server läuft)

---

## Connector in Claude.ai hinzufuegen

1. **Claude.ai** oeffnen → Einstellungen → **Connectors** → **Benutzerdefinierten Connector hinzufuegen**
2. Felder ausfuellen:

| Feld                          | Wert                                       | Hinweis                                                    |
|-------------------------------|--------------------------------------------|------------------------------------------------------------|
| **Name**                      | `quizrace` (oder beliebig)                 | Anzeigename in Claude.ai                                   |
| **Remote MCP Server URL**     | `https://<deine-domain>/mcp`               | z. B. `https://quiz.example.com/mcp`                       |
| **OAuth Client ID** (optional)| *leer lassen*                              | Claude registriert sich automatisch via Dynamic Client Registration (RFC 7591) |
| **OAuth-Client-Geheimnis** (optional) | *leer lassen*                       | Wird automatisch bei der Registrierung erzeugt              |

3. **Hinzufuegen** klicken.

---

## Was passiert nach dem Hinzufuegen?

1. Claude.ai ruft `https://<deine-domain>/.well-known/oauth-authorization-server` ab, um die OAuth-Endpunkte zu entdecken.
2. Claude registriert sich automatisch als OAuth-Client ueber `POST /oauth/register`.
3. Du wirst zur **Autorisierungsseite** (`/oauth/authorize`) weitergeleitet, wo du den Zugriff genehmigst und den Namespace auswaehlst.
4. Nach der Genehmigung erhaelt Claude ein Access-Token und kann die MCP-Tools nutzen.

---

## Verfuegbare Scopes

Beim Autorisieren werden folgende Berechtigungen angefragt:

| Scope        | Berechtigung                                  |
|--------------|-----------------------------------------------|
| `cms:read`   | Seiten lesen (Liste, Baum)                    |
| `cms:write`  | Seiten erstellen und aktualisieren            |
| `seo:write`  | SEO-Konfiguration schreiben                   |
| `menu:read`  | Menues und Menuepunkte lesen                  |
| `menu:write` | Menues und Menuepunkte erstellen/aendern/loeschen |
| `news:read`  | News-Artikel lesen                            |
| `news:write` | News-Artikel erstellen/aendern/loeschen       |

---

## Verfuegbare MCP-Tools

Nach erfolgreicher Verbindung stehen Claude folgende Tools zur Verfuegung:

### Namespaces

| Tool               | Beschreibung                                    |
|--------------------|-------------------------------------------------|
| `list_namespaces`  | Alle verfuegbaren Namespaces auflisten          |

### Seiten (CMS)

| Tool             | Beschreibung                                      |
|------------------|---------------------------------------------------|
| `list_pages`     | Alle Seiten eines Namespace auflisten             |
| `get_page_tree`  | Seitenbaum (Hierarchie) abrufen                   |
| `upsert_page`    | Seite erstellen oder aktualisieren                |

### Menues

| Tool               | Beschreibung                                   |
|--------------------|-------------------------------------------------|
| `list_menus`       | Alle Menues eines Namespace auflisten          |
| `create_menu`      | Neues Menue erstellen                          |
| `update_menu`      | Bestehendes Menue aktualisieren                |
| `delete_menu`      | Menue loeschen                                 |
| `list_menu_items`  | Menuepunkte als Baumstruktur abrufen           |
| `create_menu_item` | Neuen Menuepunkt erstellen                     |
| `update_menu_item` | Menuepunkt aktualisieren                       |
| `delete_menu_item` | Menuepunkt loeschen                            |

### News

| Tool          | Beschreibung                                        |
|---------------|-----------------------------------------------------|
| `list_news`   | Alle News-Artikel eines Namespace auflisten         |
| `get_news`    | Einzelnen News-Artikel abrufen                      |
| `create_news` | Neuen News-Artikel erstellen                        |
| `update_news` | News-Artikel aktualisieren                          |
| `delete_news` | News-Artikel loeschen                               |

### Namespace-Parameter

Alle Tools akzeptieren einen optionalen `namespace`-Parameter. Wird er nicht angegeben, wird der Namespace des OAuth-Tokens verwendet (der bei der Autorisierung gewaehlte Namespace).

```
"Zeige mir alle Seiten im Namespace calhelp"
→ Claude ruft list_pages({ namespace: "calhelp" }) auf

"Welche Namespaces gibt es?"
→ Claude ruft list_namespaces() auf
```

---

## Fehlerbehebung

| Problem                          | Loesung                                                        |
|----------------------------------|----------------------------------------------------------------|
| "Verbindung fehlgeschlagen"      | Pruefen, ob `https://<domain>/mcp` erreichbar ist (HTTPS!)    |
| "Autorisierung fehlgeschlagen"   | Server-Logs pruefen, OAuth-Endpunkte testen                   |
| "Tool nicht gefunden"            | Scope pruefen — fehlt z. B. `cms:read`, sind Page-Tools nicht nutzbar |
| "missing_scope"-Fehler           | Connector entfernen und neu hinzufuegen, dabei alle Scopes genehmigen |

---

## Beispiel: Connector fuer lokale Entwicklung

Fuer lokale Entwicklung mit HTTPS (z. B. via Caddy oder mkcert):

| Feld                      | Wert                              |
|---------------------------|-----------------------------------|
| **Name**                  | `quizrace-dev`                    |
| **Remote MCP Server URL** | `https://localhost:8443/mcp`      |
| **OAuth Client ID**       | *leer lassen*                     |
| **OAuth-Client-Geheimnis**| *leer lassen*                     |
