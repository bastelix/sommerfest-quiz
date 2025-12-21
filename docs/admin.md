# Administration

Die Administrationsoberfläche erreichen Sie über `/admin/dashboard` (kurz `/admin`) nach einem erfolgreichen Login. Alle Management-Rollen (z. B. `event-manager`, `team-manager`) werden nach dem Login automatisch zum Admin-Dashboard weitergeleitet. Die Navigation ist in folgende Kategorien gegliedert:

* **Startseite** – `/admin/dashboard`
* **Event-Management**
  * **Events** – `/admin/events`
  * **Event-Dashboards** – `/admin/event/dashboard`
  * **Event-Konfiguration** – `/admin/event/settings`
  * **Übersicht** – `/admin/summary`
  * **Kataloge** – `/admin/catalogs` (Administrator:innen & Katalog-Editor:innen)
  * **Fragen bearbeiten** – `/admin/questions`
  * **Teams/Personen** – `/admin/teams`
  * **Ergebnisse** – `/admin/results`
  * **Statistik** – `/admin/statistics`
* **Inhalte**
  * **Medien** – `/admin/media` (Administrator:innen & Katalog-Editor:innen)
* **KI-Chatbot** – `/admin/rag-chat` (Administrator:innen & Katalog-Editor:innen) – verwaltet die Verbindung zum KI-Backend und liefert Zugriff auf die projektbezogene KI-Dokumentation.
  * **Seiten** – `/admin/pages` (nur Administrator:innen)
* **Konto**
  * **Profil** – `/admin/profile`
  * **Abo** – `/admin/subscription`
* **Administration**
  * **Administration** – `/admin/management` (nur Administrator:innen)
  * **Logs** – `/admin/logs` (nur Administrator:innen)
  * **Mail-Provider** – `/admin/mail-providers` (nur Administrator:innen)
  * **Subdomains** – `/admin/tenants` (nur Administrator:innen, nur auf der Hauptdomain sichtbar)
Im Tab "Administration" lassen sich JSON-Sicherungen exportieren und bei Bedarf wiederherstellen. Der Statistik-Tab listet jede Antwort mit Name, Versuch, Katalog, Frage, Antwort, Richtig-Status und optionalem Beweisfoto. Über ein Auswahlfeld lassen sich die Daten nach Teams oder Personen filtern.

## Dashboard-Konfiguration

Der Abschnitt **Event-Konfiguration → Dashboard** bündelt alle Einstellungen für die öffentliche Ergebnisanzeige. Für die Module „Live-Rankings“ und „Ergebnisliste“ lassen sich die sichtbare Menge (`Anzahl der Einträge`) sowie der automatische Seitenumbruch (`Einträge pro Seite`) steuern. Zusätzlich legt das Feld **Automatischer Seitenwechsel (Sekunden)** fest, in welchem Intervall die Anzeige zwischen den Seiten wechselt. Ohne Eingabe bleibt der Standard von 10 Sekunden aktiv – so können Administrator:innen bei großen Gruppen das Tempo jederzeit an das Publikum anpassen.

Weitere Funktionen wie der QR-Code-Login mit Namensspeicherung oder der Wettkampfmodus lassen sich über die Event-Konfiguration in der Datenbank aktivieren.

## Domänen-Konfiguration

Die Anwendung unterscheidet über die Umgebungsvariable `MAIN_DOMAIN` zwischen der Hauptdomain und möglichen Mandanten-Domains. Sie legt fest, unter welcher Basisadresse der Quiz-Container erreichbar ist, etwa `quiz.example`. Typische Subdomains sind:

- `admin.` – Administrationsoberfläche
- `{tenant}.` – individuelle Mandanteninstanzen

Zusätzlich lassen sich weitere Marketing-Domains über die Umgebungsvariable `MARKETING_DOMAINS` (Komma- oder Zeilen-separiert) freischalten. Diese Domains liefern die Inhalte der Landing Pages aus, ohne eine Mandanten-Subdomain verwenden zu müssen. Damit der Reverse Proxy automatisch TLS-Zertifikate für diese Hosts anfordert, sollten die Domains kommagetrennt eingetragen werden; `docker-compose.yml` hängt die Liste an `VIRTUAL_HOST` an und übernimmt sie zugleich (ohne Regex-Einträge) in `LETSENCRYPT_HOST`.

Im Admin-Tab **Administration → Domains** kann die Schaltfläche **Domains prüfen** genutzt werden, um fehlende Zertifikate nachzuziehen und nicht auflösbare DNS-Einträge zu melden.
Wenn eine Marketing-Domain über die Admin-Oberfläche angelegt wird, schreibt der Server die Domain in die `MARKETING_DOMAINS`-Liste (persistiert in `.env`) und stößt danach einen Proxy-Reload über den konfigurierten Reload-Mechanismus an. Dadurch aktualisiert der Reverse Proxy seine Hostliste und der ACME-Companion kann direkt ein Zertifikat für die neue Domain anfordern.

Die `DomainMiddleware` prüft bei jeder Anfrage den Host gegen `MAIN_DOMAIN` und die Marketing-Liste und setzt entsprechend das Attribut `domainType` (`main`, `tenant` oder `marketing`). Ist `MAIN_DOMAIN` leer oder stimmt keine der konfigurierten Domains mit der aufgerufenen Domain überein, blockiert die Middleware den Zugriff mit `403 Invalid main domain configuration.`

### Beispiel für eine Fehlkonfiguration

Ist `MAIN_DOMAIN=quiz.example` gesetzt, die Anwendung wird aber über `quiz.local` aufgerufen, funktioniert der Login unter `admin.quiz.local` zwar. Nach der Weiterleitung auf `/admin` greift die `DomainMiddleware` jedoch ein und der Browser zeigt einen `403` an. Erst wenn `MAIN_DOMAIN` auf die tatsächlich genutzte Domain gesetzt wird, lässt sich das Dashboard erreichen.

## Aktive Events

Über zwei Mechanismen wird festgelegt, für welches Event der Server arbeitet:

* **Admin-Flag** – Administratoren können im Backend ein Event als aktiv markieren. `ConfigService::setActiveEventUid` schreibt die jeweilige `event_uid` in die Tabelle `active_event`. `ConfigService::getActiveEventUid` liest diesen Wert und stellt ihn dem Backend als Standard bereit.
* **Frontend-Auswahl** – Im Frontend wird das Event ausschließlich über den URL-Parameter `event` bestimmt. Der Wert landet in `window.quizConfig.event_uid` und steuert sämtliche clientseitige Funktionen.

Der gesetzte Admin-Flag dient nur dazu, eine Vorauswahl für das Backend zu liefern. Die Anwendung im Browser orientiert sich ausschließlich am URL-basierten Event und ignoriert den in `active_event` gespeicherten Wert.

## Teamnamen-Reservierungen

Der integrierte Teamnamen-Pool liefert auf Wunsch automatisch passende Vorschläge. Drei Konfigurationsfelder steuern die Filterung:

* `randomNameDomains` – JSON-Liste der gewünschten Themencluster (`nature`, `science`, `culture`, `sports`, `fantasy`, `geography`).
* `randomNameTones` – JSON-Liste der Tonalitäten (`playful`, `bold`, `elegant`, `serious`, `quirky`).
* `randomNameBuffer` – Zahl zwischen 0 und 99999. Gibt an, wie viele zusätzliche Namen pro Event vorab reserviert bleiben, damit die Auslastung im Dashboard sichtbar bleibt.
* `randomNameLocale` – Optionales Locale (z. B. `de-DE`), mit dem die KI-Antworten priorisiert werden. Bleibt das Feld leer, nutzt der Service die globale Voreinstellung.
* `randomNameStrategy` – Steuert die Quelle für Zufallsnamen. `ai` (Standard) nutzt den KI-Client, `lexicon` liefert ausschließlich Einträge aus dem gepflegten Wortschatz.

Bleiben die Listen leer, zieht der Dienst sämtliche verfügbaren Adjektiv- und Substantiv-Kombinationen heran. Die Werte werden im Admin-Frontend automatisch validiert und als Kleinbuchstaben gespeichert. Betriebsteams können die Felder bei Bedarf direkt in der `config`-Tabelle pflegen; `ConfigService` serialisiert die Listen als JSON (`random_name_domains` / `random_name_tones`) und normalisiert die Eingaben.

Der KI-Client nutzt dieselben Einstellungen wie der Marketing-Chatbot (`RAG_CHAT_SERVICE_URL`, `RAG_CHAT_SERVICE_TOKEN`, `RAG_CHAT_SERVICE_MODEL`, `RAG_CHAT_SERVICE_TIMEOUT`). Separate `TEAM_NAME_AI_*` Variablen sind nicht erforderlich. Standardmäßig wartet der Client bis zu 60 Sekunden auf eine Antwort, bevor der Request abgebrochen wird.

Der KI-Modus puffert bei jeder Reservierung die angegebene Anzahl zusätzlicher Vorschläge und speichert sie in der Tabelle `team_names`. Ein Batch-Request liefert maximal zehn neue Namen, weitere Reservierungen greifen anschließend auf den Cache zurück. Monitoring-Systeme sollten deshalb die Größe der Tabelle und den Anteil an Fallback-Namen beobachten. Ein dauerhaft hoher Fallback-Anteil deutet auf einen fehlerhaften Endpoint (`RAG_CHAT_SERVICE_URL`) oder auf leere KI-Antworten hin.

Für automatisierte Setups steht neben `POST /api/team-names` jetzt `GET /api/team-names/batch` bereit. Die Anfrage akzeptiert `event_uid` (oder `event_id`) sowie `count` (maximal 10). Die Antwort enthält `event_id` und eine Liste `reservations`. Jedes Element folgt der Struktur:

```json
{
  "name": "Beispiel Team",
  "token": "<16 Byte hex>",
  "expires_at": "2025-01-01T12:00:00+00:00",
  "lexicon_version": 2,
  "total": 480,
  "remaining": 475,
  "fallback": false
}
```

Die Felder `total` und `remaining` berücksichtigen automatisch den eingestellten Filter. Fällt kein passender Name mehr ab, liefert der Service einen Eintrag mit `fallback: true` sowie einem generischen `Gast-XXXXX`-Namen. Tokens bleiben 10 Minuten gültig, bevor sie automatisch freigegeben werden.

## Statische Seiten bearbeiten

Im Tab **Seiten** können Administratoren die HTML-Dateien `landing`, `impressum`, `datenschutz` und `faq` anpassen. Über das Untermenü wird die gewünschte Seite ausgewählt und im **Trumbowyg**-Editor bearbeitet. Zusätzlich stehen eigene UIkit-Blöcke zur Verfügung, etwa ein Hero-Abschnitt oder eine Card. Mit **Speichern** werden die Änderungen im Ordner `content/` abgelegt. Die Schaltfläche *Vorschau* zeigt den aktuellen Stand direkt im Modal an. Alternativ kann der Editor weiterhin über `/admin/pages/{slug}` aufgerufen werden.

Wird die dunkle Hero-Vorlage (`uk-section-primary uk-light`) genutzt, sollte anschließend ein Abschnitt mit einer Hintergrundklasse wie `section--alt` eingefügt werden, damit der Seitenhintergrund wieder aufgehellt wird.

The landing page template now renders its hero, innovation highlights, and section dividers from the database content or page modules instead of hardcoded Twig sections. Use the **Seiten → landing** editor to manage the main HTML content and configure optional page modules for the `before-content` and `after-content` positions if you want to insert reusable blocks around the content. Header and footer markup remain part of the Twig template.

## Bild-Uploads

Alle Bilder werden über den `ImageUploadService` verarbeitet. Globale Dateien landen im Verzeichnis `data/uploads`, eventbezogene Bilder unter `data/events/<event_uid>/images`.

| Typ                 | Beispielpfad                              | Qualität |
|---------------------|------------------------------------------|----------|
| Logo                | `/events/<uid>/images/logo.{png,webp,svg}` | 80 (`QUALITY_LOGO`)
| Sticker-Hintergrund | `/events/<uid>/images/sticker-bg.png`    | 90 (`QUALITY_STICKER`)
| Foto                | `/uploads/photo.jpg`                     | 70 (`QUALITY_PHOTO`)

Die Qualität entspricht den Konstanten in `ImageUploadService` und sorgt für einheitlich komprimierte Dateien.

Globale Dateien lassen sich direkt über `/uploads/<dateiname>` abrufen, beispielsweise damit Vorschaubilder im Medienmanager korrekt geladen werden können.

Über `/admin/media` steht eine Medienbibliothek bereit, die globale und eventbezogene Uploads kapselt. Die Oberfläche listet alle Dateien mit Größe und Änderungsdatum auf, erlaubt eine Volltextsuche sowie das seitenweise Durchblättern (20 Einträge pro Seite). Uploads werden serverseitig auf 5 MB und die Formate PNG/JPG/WEBP/SVG begrenzt; fehlerhafte Anfragen liefern strukturierte Antworten mit einem `error`-Feld und den gültigen `limits`. Die gleichen Informationen stehen nach erfolgreichen Aktionen zur Verfügung, etwa nach dem Hochladen (`message: uploaded`) oder beim Umbenennen (`message: renamed`).

Alle XHR-Endpunkte (`/admin/media/files`, `/admin/media/upload`, `/admin/media/rename`, `/admin/media/delete`) sind per CSRF-Token und `RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR)` abgesichert. Die JSON-Antworten enthalten neben der Dateiliste zusätzliche Metadaten (`mime`, `size`, `modified`) sowie die Upload-Limits, damit Clients die Vorgaben unmittelbar in der Oberfläche anzeigen können. Pfad-Traversal wird serverseitig verhindert, indem Dateinamen strikt normalisiert und nur bekannte Verzeichnisse zugelassen werden.

Im Vorschaubereich blendet der Medienmanager bei ausgewählter Datei ein schreibgeschütztes URL-Feld ein. Der Wert entspricht der aufgelösten Download-Adresse (inklusive Basis-Pfad) und lässt sich über die Schaltfläche **URL kopieren** in die Zwischenablage übernehmen. Bei Browsern ohne Clipboard-API bleibt das Feld fokussierbar, die Anwendung informiert über einen Hinweis-Toast über das manuelle Kopieren.
