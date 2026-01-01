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
  * **Logins** – `/admin/logins` (nur Administrator:innen)
  * **Administration** – `/admin/management` (nur Administrator:innen)
  * **Logs** – `/admin/logs` (nur Administrator:innen)
  * **Mail-Provider** – `/admin/mail-providers` (nur Administrator:innen)
  * **Subdomains** – `/admin/tenants` (nur Administrator:innen, nur auf der Hauptdomain sichtbar)
The **Logins** tab combines registration settings with the user management list. The **Administration** tab provides
JSON backup exports and restores. The statistics tab lists every answer with name, attempt, catalog, question, answer,
correctness, and an optional evidence photo, and can be filtered by team or person.

## Container-Metriken abrufen

Über den geschützten Endpunkt `/admin/system/metrics` stehen CPU-, Speicher- und OOM-Zähler des laufenden Containers als JSON zur Verfügung. Die API liest die cgroup-Dateien direkt aus (`/sys/fs/cgroup`) und liefert je nach Version (v1/v2) unterschiedliche Felder:

```
{
  "timestamp": "2024-06-01T12:00:00+00:00",
  "cgroupVersion": 2,
  "memory": { "currentBytes": 1234567, "maxBytes": 2345678 },
  "cpu": { "usageMicros": 1234, "percent": 12.34, "sampleWindowSeconds": 1.0 },
  "oom": { "events": 0, "kills": 0 }
}
```

* `percent` basiert auf dem CPU-Usage-Differenzwert seit der letzten Abfrage und dem gemessenen Zeitfenster `sampleWindowSeconds`.
  Bei der ersten Abfrage wird `percent` mit `0` ausgeliefert, weil noch kein Vergleichswert existiert.
* Fehlt das cgroup-Dateisystem oder notwendige Dateien, antwortet der Server mit HTTP 503 und einem `error`-Feld.

Im Event-Dashboard steht das Modul **Container-Metriken** bereit. Dort lässt sich die Sichtbarkeit, das Layout, ein eigenes Aktualisierungsintervall sowie optionale Obergrenzen (Memory in MB, CPU in %) konfigurieren. Wird kein Memory-Limit gesetzt, verwendet das Modul den vom Kernel gemeldeten cgroup-Maximalwert (sofern vorhanden).

## Dashboard-Konfiguration

Der Abschnitt **Event-Konfiguration → Dashboard** bündelt alle Einstellungen für die öffentliche Ergebnisanzeige. Für die Module „Live-Rankings“ und „Ergebnisliste“ lassen sich die sichtbare Menge (`Anzahl der Einträge`) sowie der automatische Seitenumbruch (`Einträge pro Seite`) steuern. Zusätzlich legt das Feld **Automatischer Seitenwechsel (Sekunden)** fest, in welchem Intervall die Anzeige zwischen den Seiten wechselt. Ohne Eingabe bleibt der Standard von 10 Sekunden aktiv – so können Administrator:innen bei großen Gruppen das Tempo jederzeit an das Publikum anpassen.

Weitere Funktionen wie der QR-Code-Login mit Namensspeicherung oder der Wettkampfmodus lassen sich über die Event-Konfiguration in der Datenbank aktivieren.

## Domänen-Konfiguration

Die Anwendung unterscheidet über die Umgebungsvariable `MAIN_DOMAIN` zwischen der Hauptdomain und möglichen Mandanten-Domains. Sie legt fest, unter welcher Basisadresse der Quiz-Container erreichbar ist, etwa `quiz.example`. Typische Subdomains sind:

- `admin.` – Administrationsoberfläche
- `{tenant}.` – individuelle Mandanteninstanzen

Marketing-Domains werden ausschließlich im Admin-Bereich gepflegt (Tabelle `domains`). Bei jeder Aktivierung wird automatisch die passende Zone über die Public-Suffix-Liste abgeleitet und in `certificate_zones` registriert. `.env` enthält keine Marketing-Domain-Listen mehr; secrets wie DNS-API-Tokens werden weiterhin dort verwaltet.

So stellst du die TLS-Versorgung für Marketing-Hosts sicher:

1. Lege die Domains im Tab **Administration → Domains** an und aktiviere sie. Kontrolliere per SQL, dass jede Zone in `certificate_zones` auftaucht (`SELECT * FROM certificate_zones ORDER BY zone;`).
2. Nach dem Aktivieren stößt die Admin-API automatisch `scripts/wildcard_maintenance.sh` an, sofern `ACME_SH_BIN`, `ACME_WILDCARD_PROVIDER` und `NGINX_WILDCARD_CERT_DIR` gesetzt sind. Ohne diese Variablen bleiben die Einträge auf `pending` und der Systemd-Timer übernimmt die Abarbeitung.
3. `bin/generate-nginx-zones` und `bin/provision-wildcard-certificates` können weiterhin manuell oder per Timer laufen, um `<zone>` und `*.zone` in die Zertifikatsanforderung zu bringen und nginx neu zu laden.
4. Müssen einzelne Hosts sofort per Companion ausgeliefert werden, setze sie zusätzlich in `.env` als `SLIM_LETSENCRYPT_HOST` (nur konkrete Hosts, keine Regex). Der Entrypoint übernimmt diese Werte vor dem Filterprozess in `LETSENCRYPT_HOST`.

Im Admin-Tab **Administration → Domains** kann die Schaltfläche **Domains prüfen** genutzt werden, um fehlende Zertifikate nachzuziehen und nicht auflösbare DNS-Einträge zu melden.

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

Der KI-Client nutzt dieselben Einstellungen wie der Marketing-Chatbot (`RAG_CHAT_SERVICE_URL`, `RAG_CHAT_SERVICE_TOKEN`, `RAG_CHAT_SERVICE_MODEL`, `RAG_CHAT_SERVICE_TIMEOUT`). Separate `TEAM_NAME_AI_*` Variablen sind nicht erforderlich. Standardmäßig wartet der Client bis zu 60 Sekunden auf eine Antwort, bevor der Request abgebrochen wird; Nginx-Timeouts für KI-Endpunkte (z. B. `proxy_read_timeout` / `proxy_send_timeout` auf `/admin/pages/ai-generate`) sollten entsprechend höher angesetzt werden.

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

### Marketing-Landing-Namespaces

Marketing-Seiten orientieren sich an der Namespace-Auflösung aus `NamespaceResolver`. Die Reihenfolge der Kandidaten ist: Request-Attribute (`legalPageNamespace`, `pageNamespace`, `namespace`), Route-Argumente (`namespace`, `tenantNamespace`, `tenant`, `subdomain`), Event-/Tenant-Kontext, danach der `default`-Namespace.

Wenn eine Marketing-Seite in dem aufgelösten Namespace fehlt, wird automatisch auf die Seite im `default`-Namespace zurückgegriffen. So können tenant-spezifische Landing-Pages einzelne Seiten überschreiben, ohne dass globale Standardinhalte fehlen.

Fehlt zusätzlich eine Twig-Datei für den Slug unter `templates/marketing/<slug>.twig`, rendert der Controller die Fallback-Vorlage `templates/marketing/default.twig`. Dadurch lassen sich neue Marketing-Seiten auch ohne eigene Twig-Struktur ausliefern, solange Inhalte und Navigation gepflegt sind.

Die Seed-Daten in `src/Infrastructure/Migrations/sqlite-schema.sql` legen initiale Marketing-Seiten immer im `default`-Namespace an. Der Ziel-Namespace für die Laufzeit wird daher durch die Auflösung im `NamespaceResolver` bestimmt (z. B. über Query-Parameter `namespace`, Route-Argumente oder Tenant-Subdomain). Eigene Projekt-Namespaces werden anschließend über die Admin-Oberfläche gepflegt oder per Datenbank-Import ergänzt.

### Manuelle Tests für statische Seiten

Diese Checks decken den Editor vollständig ab und sollten nach jedem Update oder Import durchgeführt werden:

1. **CRUD auf landing/impressum/datenschutz/faq**
   * Öffne `/admin/pages` und lege für jede der vier Seiten einen Entwurf mit eindeutiger Überschrift an.
   * Speichere die Änderungen, lade die Admin-Seite neu und prüfe, ob die Inhalte wieder erscheinen.
   * Lösche einen Entwurf (z. B. `faq`), bestätige den Hinweisdialog, kontrolliere die ausgeblendeten Formularfelder und stelle den Eintrag über **Seite erstellen** wieder her.
2. **Quiz-Link-Validierung**
   * Klicke im Toolbar-Button **Quiz-Link**, warte auf die geladenen Katalog-Slugs und füge einen Link ein.
   * Prüfe im Quelltext (`href`), dass der Slug URL-kodiert wird (`/?katalog=<slug>`) und keine leeren Slugs gespeichert werden.
3. **Medien mit srcset**
   * Füge ein `<img>` mit `srcset` hinzu, das sowohl gültige (`320w`, `2x`) als auch absichtlich fehlerhafte Deskriptoren enthält.
   * Speichere den Inhalt, öffne die Vorschau und verifiziere, dass ungültige `srcset`-Attribute entfernt wurden, gültige Angaben aber bestehen bleiben.
4. **Theme-Wechsel**
   * Wechsle über **Hell/Dunkel/High-Contrast** die Darstellung und beobachte die `data-theme`-Attribute auf `<body>`, `.page-editor` und `#preview-content`.
   * Bestätige, dass das gespeicherte Theme auch nach einem Neuladen aktiv bleibt (LocalStorage wird gesetzt).
5. **Vorschau**
   * Öffne die Vorschau aus der Toolbar, prüfe die Darstellung von Buttons, Cards und Medien sowie die Namespace-spezifischen Styles (Landing-Assets werden mitgeladen).
   * Bei Landing-Seiten müssen Hero- und Alternativabschnitte sichtbar sein; für `impressum`, `datenschutz` und `faq` gilt die Standard-Hintergrundfarbe ohne Landing-Styles.

### Editor-Konfiguration

Das Admin-Frontend setzt ausschließlich auf den Tiptap-Editor; ein alternativer Fallback ist nicht mehr vorgesehen.

### Marketing-Menüs exportieren und importieren

Im Tab **Seiten → Navigation** lassen sich komplette Marketing-Menüs als JSON sichern oder in andere Umgebungen übernehmen. Der Export-Button lädt die aktuelle Menüstruktur (inklusive Namespace und Locale) für die ausgewählte Seite herunter. Über **Menü importieren** wird eine JSON-Datei ausgewählt; der Server validiert erlaubte Felder und ersetzt die bestehenden Menüeinträge der Seite in einem Schritt. Die Startseite wird unabhängig von den Menüeinträgen direkt an der Page gespeichert und nicht mehr über das Menü-JSON verwaltet.

### Namespace as the standard project identifier

Namespaces are the canonical project identifier in the admin UI. Any legacy "project" labels should be treated as
aliases for the namespace: settings, content, and UI selections are stored and resolved by namespace only. If your
data already uses namespaces exclusively, no migration steps are required.

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
