# Administration

Die Administrationsoberfläche erreichen Sie über `/admin/dashboard` (kurz `/admin`) nach einem erfolgreichen Login. Alle Management-Rollen (z. B. `event-manager`, `team-manager`) werden nach dem Login automatisch zum Admin-Dashboard weitergeleitet. Die Navigation ist in folgende Kategorien gegliedert:

* **Event**
  * **Startseite** – `/admin/dashboard`
  * **Events** – `/admin/events`
  * **Event-Konfiguration** – `/admin/event/settings`
  * **Übersicht** – `/admin/summary`
* **Inhalte**
  * **Kataloge** – `/admin/catalogs`
  * **Fragen bearbeiten** – `/admin/questions`
  * **KI-Chatbot** – `/admin/rag-chat` (Administrator:innen & Katalog-Editor:innen) – verwaltet die Verbindung zum KI-Backend und liefert Zugriff auf die KI-Dokumentation je Domain.
  * **Seiten** – `/admin/pages` (nur Administratoren)
* **Teams**
  * **Teams/Personen** – `/admin/teams`
* **Auswertung**
  * **Ergebnisse** – `/admin/results`
  * **Statistik** – `/admin/statistics`
* **Konto**
  * **Profil** – `/admin/profile`
  * **Abo** – `/admin/subscription`
* **Administration**
  * **Administration** – `/admin/management` (nur Administratoren)
  * **Subdomains** – `/admin/tenants` (nur Administratoren)
Im Tab "Administration" lassen sich JSON-Sicherungen exportieren und bei Bedarf wiederherstellen. Der Statistik-Tab listet jede Antwort mit Name, Versuch, Katalog, Frage, Antwort, Richtig-Status und optionalem Beweisfoto. Über ein Auswahlfeld lassen sich die Daten nach Teams oder Personen filtern.

Weitere Funktionen wie der QR-Code-Login mit Namensspeicherung oder der Wettkampfmodus lassen sich über die Event-Konfiguration in der Datenbank aktivieren.

## Domänen-Konfiguration

Die Anwendung unterscheidet über die Umgebungsvariable `MAIN_DOMAIN` zwischen der Hauptdomain und möglichen Mandanten-Domains. Sie legt fest, unter welcher Basisadresse der Quiz-Container erreichbar ist, etwa `quiz.example`. Typische Subdomains sind:

- `admin.` – Administrationsoberfläche
- `{tenant}.` – individuelle Mandanteninstanzen

Zusätzlich lassen sich weitere Marketing-Domains über die Umgebungsvariable `MARKETING_DOMAINS` (Komma- oder Zeilen-separiert) freischalten. Diese Domains liefern die Inhalte der Landing Pages aus, ohne eine Mandanten-Subdomain verwenden zu müssen. Damit der Reverse Proxy automatisch TLS-Zertifikate für diese Hosts anfordert, sollten die Domains kommagetrennt eingetragen werden; die Liste wird dann direkt an `VIRTUAL_HOST`/`LETSENCRYPT_HOST` des Slim-Containers durchgereicht.

Die `DomainMiddleware` prüft bei jeder Anfrage den Host gegen `MAIN_DOMAIN` und die Marketing-Liste und setzt entsprechend das Attribut `domainType` (`main`, `tenant` oder `marketing`). Ist `MAIN_DOMAIN` leer oder stimmt keine der konfigurierten Domains mit der aufgerufenen Domain überein, blockiert die Middleware den Zugriff mit `403 Invalid main domain configuration.`

### Beispiel für eine Fehlkonfiguration

Ist `MAIN_DOMAIN=quiz.example` gesetzt, die Anwendung wird aber über `quiz.local` aufgerufen, funktioniert der Login unter `admin.quiz.local` zwar. Nach der Weiterleitung auf `/admin` greift die `DomainMiddleware` jedoch ein und der Browser zeigt einen `403` an. Erst wenn `MAIN_DOMAIN` auf die tatsächlich genutzte Domain gesetzt wird, lässt sich das Dashboard erreichen.

## Aktive Events

Über zwei Mechanismen wird festgelegt, für welches Event der Server arbeitet:

* **Admin-Flag** – Administratoren können im Backend ein Event als aktiv markieren. `ConfigService::setActiveEventUid` schreibt die jeweilige `event_uid` in die Tabelle `active_event`. `ConfigService::getActiveEventUid` liest diesen Wert und stellt ihn dem Backend als Standard bereit.
* **Frontend-Auswahl** – Im Frontend wird das Event ausschließlich über den URL-Parameter `event` bestimmt. Der Wert landet in `window.quizConfig.event_uid` und steuert sämtliche clientseitige Funktionen.

Der gesetzte Admin-Flag dient nur dazu, eine Vorauswahl für das Backend zu liefern. Die Anwendung im Browser orientiert sich ausschließlich am URL-basierten Event und ignoriert den in `active_event` gespeicherten Wert.

## Statische Seiten bearbeiten

Im Tab **Seiten** können Administratoren die HTML-Dateien `landing`, `impressum`, `datenschutz` und `faq` anpassen. Über das Untermenü wird die gewünschte Seite ausgewählt und im **Trumbowyg**-Editor bearbeitet. Zusätzlich stehen eigene UIkit-Blöcke zur Verfügung, etwa ein Hero-Abschnitt oder eine Card. Mit **Speichern** werden die Änderungen im Ordner `content/` abgelegt. Die Schaltfläche *Vorschau* zeigt den aktuellen Stand direkt im Modal an. Alternativ kann der Editor weiterhin über `/admin/pages/{slug}` aufgerufen werden.

Wird die dunkle Hero-Vorlage (`uk-section-primary uk-light`) genutzt, sollte anschließend ein Abschnitt mit einer Hintergrundklasse wie `section--alt` eingefügt werden, damit der Seitenhintergrund wieder aufgehellt wird.

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
