# QuizRace
[![Deploy](https://github.com/bastelix/sommerfest-quiz/actions/workflows/deploy.yml/badge.svg)](https://github.com/bastelix/sommerfest-quiz/actions/workflows/deploy.yml)
[![HTML Validity Test](https://github.com/bastelix/sommerfest-quiz/actions/workflows/html-validity.yml/badge.svg)](https://github.com/bastelix/sommerfest-quiz/actions/workflows/html-validity.yml)

## Dokumentation

Die ausführliche Anleitung findest du auf GitHub Pages: <https://bastelix.github.io/sommerfest-quiz/>

### Dokumentation lokal bauen

1. Abhängigkeiten installieren:
   ```bash
   bundle install
   ```
2. Dokumentation anzeigen:
   ```bash
   bundle exec jekyll serve
   ```
   Danach ist sie unter <http://localhost:4000> erreichbar.

### Deployment

Änderungen an der Dokumentation werden automatisch über GitHub Pages veröffentlicht, sobald sie auf den `main`-Branch gepusht werden.

#### Asynchrones Warm-up der Teamnamen

Beim Import von Konfigurationen – beispielsweise während eines Deployments – löst `ConfigService::saveConfig()` nun keinen direkten Aufruf von `TeamNameService::warmUpAiSuggestions()` mehr aus. Stattdessen plant der `TeamNameWarmupDispatcher` einen Hintergrundprozess ein, der nach dem Senden der HTTP-Antwort ausgeführt wird. Über `App\runBackgroundProcess()` wird das Skript `scripts/team_name_warmup.php` mit Event-ID, Filterlisten, Locale und gewünschter Puffergröße gestartet. Das Skript erzeugt die benötigten Services, berücksichtigt das Schema über die Umgebungsvariable `APP_TENANT_SCHEMA` und füllt den AI-Cache ohne die Request-Laufzeit zu verlängern. Das Verhalten stellt sicher, dass neue Filtereinstellungen beim Deployment automatisch vorbereitet werden, ohne den Administrations-Workflow zu blockieren.

### Health endpoint

The application exposes a lightweight health probe at `/healthz`. It responds with HTTP 200 when the
app is ready and includes a small JSON payload with version metadata. If a PostgreSQL DSN is
configured, the endpoint performs a quick database ping and reports the status in the `db` field.
The Docker Compose setup for the `slim` service uses `curl -f http://localhost:8080/healthz` as its
health check.

Das **QuizRace** ist eine sofort einsetzbare Web-App, mit der Sie Besucherinnen und Besucher spielerisch an Events beteiligen. Dank Slim Framework und UIkit3 funktioniert alles ohne komplizierte Server-Setups direkt im Browser.

## Disclaimer / Hinweis

Die Sommerfeier 2025 Quiz-App ist das Ergebnis einer spannenden Zusammenarbeit zwischen menschlicher Erfahrung und künstlicher Intelligenz. Während Ideen, Organisation und jede Menge Praxiswissen von Menschen stammen, wurden alle Codezeilen experimentell komplett von OpenAI Codex geschrieben. Für die kreativen Konzepte und Inhalte kam ChatGPT 4.1 zum Einsatz, bei der Fehlersuche half GitHub Copilot und das Logo wurde von der KI Sora entworfen.

Diese App wurde im Rahmen einer Machbarkeitsstudie entwickelt, um das Potenzial moderner Codeassistenten in der Praxis zu erproben.
Im Mittelpunkt stand die Zugänglichkeit für alle Nutzergruppen – daher ist die Anwendung barrierefrei gestaltet und eignet sich auch für Menschen mit Einschränkungen. Datenschutz und Sicherheit werden konsequent beachtet, sodass alle Daten geschützt sind.
Die App zeichnet sich durch eine hohe Performance und Stabilität auch bei vielen gleichzeitigen Teilnehmenden aus. Das Bedienkonzept ist selbsterklärend, wodurch eine schnelle und intuitive Nutzung auf allen Endgeräten – ob Smartphone, Tablet oder Desktop – gewährleistet wird.
Zudem wurde auf eine ressourcenschonende Arbeitsweise und eine unkomplizierte Anbindung an andere Systeme Wert gelegt.

Mit dieser App zeigen wir, was heute schon möglich ist, wenn Menschen und verschiedene KI-Tools wie ChatGPT, Codex, Copilot und Sora gemeinsam an neuen digitalen Ideen tüfteln.

## Überblick

- **Flexibel einsetzbar**: Fragenkataloge im JSON-Format lassen sich bequem austauschen oder erweitern.
- **Sechs Fragetypen**: Sortieren, Zuordnen, Multiple Choice, Swipe-Karten, Foto mit Texteingabe und "Hätten Sie es gewusst?"-Karten bieten Abwechslung f\u00fcr jede Zielgruppe.
- **QR-Code-Login & Dunkelmodus**: Optionaler QR-Code-Login für schnelles Anmelden und ein dunkles Design, das sich automatisch der Systemeinstellung anpasst oder manuell umgeschaltet werden kann, steigern den Komfort.
- **Persistente Speicherung**: Konfigurationen, Kataloge und Ergebnisse liegen in einer PostgreSQL-Datenbank.
- **Mandantenverwaltung**: Tenant-Daten werden über PostgreSQL-Schemata isoliert; SQLite wird nicht unterstützt.

## Highlights

- **Einfache Installation**: Nur Composer-Abhängigkeiten installieren und einen PHP-Server starten.
- **Intuitives UI**: Komplett auf UIkit3 basierendes Frontend mit flüssigen Animationen und responsive Design.
- **Stark anpassbar**: Farben, Logo und Texte werden in der Datenbank gespeichert und können über die Administrationsoberfläche angepasst werden.
- **Backup per JSON**: Alle Daten lassen sich exportieren und wieder importieren.
- **Automatische Bildkompression**: Hochgeladene Fotos werden nun standardmäßig verkleinert und komprimiert.
- **Rätselwort und Foto-Einwilligung**: Optionales Puzzlewort-Spiel mit DSGVO-konformen Foto-Uploads.
- **Kostenloser Testzeitraum**: Neue Stripe-Abonnements starten mit einer 7-tägigen Testphase.

## Fokus der Entwicklung

Bei der Erstellung dieser Anwendung standen besonders folgende Punkte im Mittelpunkt:

- **Barrierefreiheit**: Die App ist für alle zugänglich, auch für Menschen mit Einschränkungen.
- **Datenschutz**: Die Daten sind sicher und werden vertraulich behandelt.
- **Schnelle und stabile Nutzung**: Auch bei vielen Teilnehmenden läuft die App zuverlässig.
- **Einfache Bedienung**: Die Nutzung ist leicht und selbsterklärend.
- **Geräteunabhängigkeit**: Funktioniert auf allen Geräten: Handy, Tablet oder PC – freie Wahl.
- **Nachhaltigkeit**: Die Umsetzung ist ressourcenschonend gestaltet.
- **Offene Schnittstellen**: Die App lässt sich problemlos mit anderen Systemen verbinden.

Dieses Projekt zeigt, wie Mensch und KI zusammen ganz neue digitale Möglichkeiten schaffen können.

## Projektstruktur

- **public/** – Einstiegspunkt `index.php`, alle UIkit-Assets sowie JavaScript-Dateien
  - Mockups für die calHelp-Proof-Gallery liegen unter `public/img/calhelp/`
- **templates/** – Twig-Vorlagen für Startseite und FAQ
- **data/kataloge/** – Fragenkataloge im JSON-Format
- **tenants**-Tabelle – Profildaten für die Main-Umgebung
- **src/** – PHP-Code mit Routen, Controllern und Services
- **docs/** – Zusätzliche Dokumentation, z.B. [Richtlinien zur Worttrennung](docs/frontend-word-break.md)
-
## Theme-Strategie

Der Wechsel zwischen hellem und dunklem Design erfolgt über das `data-theme`-Attribut auf dem `<body>`-Element. CSS-Regeln greifen auf `[data-theme="dark"]`, während JavaScript den Wert (`light` oder `dark`) setzt und in `localStorage` speichert. Klassen wie `dark-mode` werden nicht mehr verwendet.

## Schnellstart

Stelle sicher, dass PHP 8.2 oder höher installiert ist:

```bash
php -v
```

1. Abhängigkeiten installieren:
  ```bash
  composer install
  ```
  Beim ersten Aufruf legt Composer eine `composer.lock` an und lädt alle
  benötigten Pakete herunter. Die Datei wird bewusst nicht versioniert,
  sodass stets die neuesten kompatiblen Abhängigkeiten installiert werden.
  Wer die Anwendung ohne Docker betreibt, muss diesen Schritt manuell
  ausführen. Fehlt das Verzeichnis `vendor/`, zeigt die App eine
  entsprechende Fehlermeldung an.
  Das Docker-Setup installiert dabei automatisch die PHP-Erweiterungen
  *gd* und *pdo_pgsql*. Ersteres benötigt die Bibliothek
  `setasign/fpdf`, letzteres stellt die Verbindung zu PostgreSQL her.
  Wer die Anwendung ohne Docker betreibt, muss *pdo_pgsql* manuell
  aktivieren, damit die Datenbankanbindung funktioniert.
   Wurden neue Pakete in `composer.json` eingetragen, sollte anschließend
   `composer update --lock` ausgeführt werden, um die `composer.lock`
   zu aktualisieren. Andernfalls bricht der Docker-Build mit Hinweis auf
   eine veraltete Lock-Datei ab.
   Eine Aktualisierung der `composer.lock` kann alternativ
   über den GitHub-Workflow **Manual Composer Install** erfolgen
   (siehe `.github/workflows/composer-install.yml`).
   Dieser lässt sich im Reiter **Actions** über den Button
   „Run workflow“ manuell starten und committet bei Bedarf die
   aktualisierte Datei.
2. Server starten (z.B. für lokale Tests):
   ```bash
   php -S localhost:8080 -t public public/router.php
   ```
 Anschließend ist das Quiz unter <http://localhost:8080> aufrufbar.

3. Optional: Tabellen in einer PostgreSQL-Datenbank anlegen:
   ```bash
   # Datenbankparameter setzen (Beispielwerte)
   export POSTGRES_DSN="pgsql:host=localhost;dbname=quiz"
   export POSTGRES_USER=quiz
   export POSTGRES_PASSWORD=***
   export POSTGRES_DB=quiz

  # Schema importieren
  psql -h localhost -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f docs/schema.sql
   ```

   Alternativ lassen sich Schema- und Datenimport direkt im Docker-Container ausführen:
   ```bash
   docker compose exec slim sh -c \
     'psql -h postgres -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f docs/schema.sql && \
      php scripts/import_to_pgsql.php'
   ```
   Für noch bequemere Einrichtung steht das Skript `scripts/run_psql_in_docker.sh`
   bereit. Es ruft denselben Befehl auf und übernimmt die Variablen aus `.env`:
   ```bash
   ./scripts/run_psql_in_docker.sh
   ```

4. Wer Docker nicht nutzt, sollte im Anschluss die Migrationen ausführen,
   um notwendige Tabellen wie `tenants` anzulegen und spätere Änderungen zu
   übernehmen:
   ```bash
   php scripts/run_migrations.php
   ```
   > Hinweis: Der Webserver führt Migrationen nicht mehr bei jedem Request aus.
   > Setze bei Bedarf `RUN_MIGRATIONS_ON_REQUEST=true`, um das Verhalten
   > temporär für lokale Tests zu aktivieren.
5. Anschließend einmalig die vorhandenen JSON-Daten importieren:
   ```bash
  php scripts/import_to_pgsql.php
  ```
  Beispielbenutzer lassen sich mithilfe des Skripts
  ```bash
  php scripts/seed_roles.php
  ```
  anlegen. Es erstellt einen Benutzer pro Rolle, wobei Benutzername und Passwort
  jeweils dem Rollennamen entsprechen:

  - `admin` – Administrator
  - `catalog-editor` – Fragenkataloge bearbeiten
  - `event-manager` – Veranstaltungen verwalten
  - `analyst` – Ergebnisse analysieren
  - `team-manager` – Teams verwalten

  Der Erstimport legt nur die erforderlichen Rollen an.
  Ein Admin-Benutzer wird bereits beim ersten Start mit
  zufälligem Passwort angelegt. Dieses Passwort wird
  mandantenspezifisch unter
  `/var/www/data/<TENANT_ID>/admin_password.txt`
  gespeichert und sollte im Onboarding durch ein eigenes
  Passwort ersetzt werden. Jede Instanz benötigt eine eigene
  Umgebungsvariable `TENANT_ID`.

   Wird `POSTGRES_DSN` gesetzt und enthält das Verzeichnis `data/` bereits JSON-Dateien,
   legt das Entrypoint-Skript des Containers die Tabellen automatisch an und importiert
   die Daten beim Start. Direkt danach werden alle Migrationen ausgeführt,
   sodass neue Spalten sofort verfügbar sind.

### Vordefinierte Blocklisten laden

Für die Moderation von Nutzernamen stehen geprüfte Blocklisten im Verzeichnis
`resources/blocklists/` bereit. Jede CSV-Datei enthält mindestens die Spalte `term`
und verweist über `source` auf die geprüfte Herkunft der Begriffe.

| Preset      | Kategorie             | Datei                                 | Quellenbeispiele |
|-------------|-----------------------|---------------------------------------|------------------|
| `nsfw`      | NSFW                  | `resources/blocklists/nsfw.csv`       | Wikipedia-Artikel zu großen Porno-Plattformen |
| `ns_symbols`| §86a/NS-Bezug         | `resources/blocklists/ns_symbols.csv` | Bundeszentrale/ADL-Einordnungen rechtsextremer Codes |
| `slur`      | Beleidigung/Slur      | `resources/blocklists/slur.csv`       | Deutsche Welle, Zentralrat Deutscher Sinti und Roma, Amnesty u. a. |
| `general`   | Allgemein             | `resources/blocklists/general.csv`    | Microsoft, Google und Salesforce Richtlinien zu reservierten Namen |
| `admin`     | Admin                 | `resources/blocklists/admin.csv`      | GitHub, GitLab und Discord Vorgaben zu Admin-Rollen |

Die CSV-Dateien werden aus dem globalen Index `data/username_blocklist/presets.json` erzeugt. Ergänzungen
oder Änderungen sollten dort erfolgen und anschließend per Build-Schritt übernommen werden:

```bash
php scripts/build_blocklist_presets.php
```

Die Import-Buttons in der Admin-Oberfläche lesen **direkt** aus den Dateien unter
`resources/blocklists/`. Stelle daher sicher, dass die CSVs nach Änderungen am Index neu erstellt werden.

Importiere die gewünschten Listen beim Datenimport mit dem neuen Preset-Parameter.
Der folgende Befehl übernimmt alle Beispiele in einem Rutsch:

```bash
php scripts/import_to_pgsql.php \
  --preset nsfw \
  --preset ns_symbols \
  --preset slur \
  --preset general \
  --preset admin
```

Datenschutz-Hinweis: Die Dateien enthalten ausschließlich generische, nicht-personenbezogene Begriffe,
die zur Moderation dienen. Bewahre sie dennoch vertraulich auf, da die enthaltenen Ausdrücke sensibel
oder beleidigend sein können. Eigene Ergänzungen über das Admin-Interface bleiben von den Presets unberührt
und können jederzeit entfernt werden.
## Admin Tools

### Nutzername-Sperrlisten importieren

Für größere Aktualisierungen der Sperrliste steht das Skript `scripts/import_username_blocklists.php` bereit. Es verarbeitet beliebig viele CSV- oder JSON-Dateien, entfernt doppelte Einträge pro Kategorie und schreibt die Daten über den `UsernameBlocklistService` in die Datenbank. Die Verbindung erfolgt wie bei den anderen Admin-Skripten über `POSTGRES_DSN`, `POSTGRES_USER` und `POSTGRES_PASSWORD`.

```bash
php scripts/import_username_blocklists.php data/username_blocklist/sample.csv data/username_blocklist/sample.json
```

Jede Zeile bzw. jedes JSON-Objekt muss die Felder `term` (mindestens drei Zeichen) und `category` enthalten. Die Kategorie wird gegen die internen Werte validiert (`NSFW`, `§86a/NS-Bezug`, `Beleidigung/Slur`, `Allgemein`, `Admin`). Umlaute und Groß-/Kleinschreibung werden automatisch ausgeglichen, bevor die Einträge in Kleinbuchstaben gespeichert werden.

- [CSV-Beispieldatei](data/username_blocklist/sample.csv)
- [JSON-Beispieldatei](data/username_blocklist/sample.json)

## Testing

Die automatisierten Tests werden mit PHPUnit ausgeführt und benötigen eine
laufende PostgreSQL-Instanz. Der Test-Harness liest dazu `.env.test` aus und
verwendet die dort hinterlegten Variablen `POSTGRES_DSN`, `POSTGRES_USER` und
`POSTGRES_PASSWORD`. Alternativ lassen sich die Variablen direkt in der
Umgebung setzen. Das DSN kann sowohl das `pgsql:`-Format als auch
`postgres://` verwenden.

Wer eine eigene Verbindung bereitstellen möchte, kann über
`Tests\TestCase::setDatabase()` eine vorbereitete `PDO`-Instanz einspeisen.

Tests starten mit:

```bash
vendor/bin/phpunit
```

Für eine lokale PostgreSQL-Instanz liefert `docker-compose.test.yml` einen
kleinen Datenbank-Container mit den in `.env.test` hinterlegten Zugangsdaten.

### KI-gestützte Teamnamen und RAG-Endpoint

Der Dienst für Teamnamen kann optional KI-Vorschläge abrufen. Dafür muss der Chat-Endpunkt des RAG-Backends
konfiguriert sein. Der Teamnamen-Service verwendet dieselben Umgebungsvariablen wie der Marketing-Chatbot – separate
`TEAM_NAME_AI_*` Einstellungen sind nicht mehr erforderlich:

| Variable | Beschreibung |
|----------|--------------|
| `RAG_CHAT_SERVICE_URL` | HTTP-Endpoint für Chat-Completions (z. B. der bereits für den RAG-Chatbot genutzte `/v1/chat/completions`-Pfad). |
| `RAG_CHAT_SERVICE_TOKEN` | Optionaler API-Token für den Aufruf des Endpoints. |
| `RAG_CHAT_SERVICE_MODEL` | Bevorzugtes Modell, das in der System-Prompt vermerkt wird. |
| `RAG_CHAT_SERVICE_TIMEOUT` | Maximale Wartezeit in Sekunden pro Chat-Anfrage, Standard sind 60 s. Nginx-Proxy-Timeouts für KI-Endpunkte (z. B. `proxy_read_timeout` / `proxy_send_timeout` auf `/admin/pages/ai-generate`) sollten entsprechend erhöht werden. |

Ein Pufferwert in der
Event-Konfiguration (`randomNameBuffer`) bestimmt, wie viele zusätzliche Vorschläge pro Event vorreserviert bleiben
und liegt zwischen 0 und 99 999. Über `randomNameStrategy` wird festgelegt, ob der KI-Modus (`ai`, Standard) oder
das reine Lexikon (`lexicon`) verwendet wird. `randomNameLocale` überschreibt das Locale pro Event und ergänzt damit
den globalen Standard (`de`).

Admins können KI-Vorschläge direkt in der Event-Konfiguration testen: Sobald der KI-Modus aktiv ist, blendet der
Random-Name-Abschnitt eine Vorschau-Schaltfläche ein. Diese ruft `/api/team-names/preview` auf und zeigt eine Liste der
ersten Treffer an, ohne Reservierungen zu verbrauchen. Werden Domains oder Tonalitäten angepasst, leert die Anwendung
automatisch bestehende Reservierungen und baut den KI-Puffer für das Event neu auf, damit die nächsten Spieler sofort
zur aktualisierten Auswahl passen.

Zur Laufzeit protokolliert die Anwendung Fehlversuche beim Reservieren im `team_names`-Table. Für das Monitoring
empfiehlt es sich, die Auslastung dieser Tabelle sowie den Anteil an Fallback-Namen zu beobachten, um Probleme mit
dem KI-Endpunkt frühzeitig zu erkennen.

## Docker Compose

Das mitgelieferte `docker-compose.yml` startet das Quiz samt Reverse Proxy. Der integrierte PHP-Webserver hört auf Port `8080`, der im Docker-Image freigegeben ist. Ein kleiner Zusatzcontainer (`nginx-reloader`) ermöglicht einen geschützten Reload des Proxys per Webhook. Dieser Container enthält nun das Docker-CLI, um den Proxy direkt neu laden zu können. Alternativ kann jede beliebige URL über die Variable `NGINX_RELOADER_URL` hinterlegt werden. Wird dieser Webhook genutzt, sollte `NGINX_RELOAD` auf `0` stehen, damit keine Docker-Befehle ausgeführt werden. Das dafür notwendige Token wird über die Datei `.env` als `NGINX_RELOAD_TOKEN` definiert und sowohl an die Anwendung als auch an den Reloader-Container weitergereicht. Die mitgelieferte Beispielkonfiguration ist bereits entsprechend vorbereitet und nutzt standardmäßig `http://nginx-reloader:8080/reload` bei deaktiviertem `NGINX_RELOAD`.
Sollte der automatische Reload scheitern, bricht `scripts/create_tenant.sh` mit einer Fehlermeldung ab. In diesem Fall lässt sich der Proxy manuell neu laden:

```bash
docker compose exec nginx nginx -s reload
```

Zur Fehlersuche helfen die Logs des Proxy- oder Reloader-Containers:

```bash
docker compose logs nginx
docker compose logs nginx-reloader
```

Anschließend kann das Skript erneut aufgerufen werden.
Zertifikate und Konfigurationen werden komplett in benannten Volumes
gespeichert. Dadurch bleiben alle Daten auch nach `docker compose down`
erhalten und es sind keine manuellen Ordner erforderlich. Zusätzlich läuft ein
Adminer-Container,
der die PostgreSQL-Datenbank über die Subdomain `https://adminer.${DOMAIN}` bereitstellt. Er
nutzt intern den Hostnamen `postgres` und erfordert keine weiteren Einstellungen.
Um größere Uploads zu erlauben, wird die maximale
Request-Größe des Reverse Proxys über eine Datei in `vhost.d/` konfiguriert.
Kopiere das Beispiel `vhost.d/example.com` und passe den Wert
`client_max_body_size` an deine Domain an. Nach dem Ändern genügt
`docker compose restart docker-gen`, damit nginx die Einstellung übernimmt.
Die optionale Variable `CLIENT_MAX_BODY_SIZE` in `.env` liefert dabei nur
einen Standardwert für Skripte wie `scripts/create_tenant.sh`.

### Static upload MIME types

The development router in `public/router.php` whitelists static file extensions
to mirror the production web server configuration. Ensure nginx or Apache serve
the same list from `/uploads/` with matching MIME types so media downloads do
not fall back to the PHP handler. The current extensions are:

```
avif, css, gif, html, ico, jpeg, jpg, js, json, map, mp3, mp4, ogg, pdf,
png, svg, ttf, txt, webm, webp, woff, woff2
```

Keep this list in sync if additional media formats are introduced.

Zum Start genügt:
```bash
cp sample.env .env
docker compose up --build -d
```
Die Datei setzt `COMPOSE_PROJECT_NAME=sommerfest-quiz`, damit Docker Compose vorhandene Container und Volumes bei späteren Deployments wiederverwendet.
Standardmäßig legt Docker Compose das benötigte Netzwerk automatisch an. Soll
ein bereits vorhandenes Proxy-Netz genutzt werden, setze in deiner `.env`
`NETWORK_EXTERNAL=true` und lege das Netz manuell an:
```bash
docker network create ${NETWORK:-webproxy}
```
Den Namen kannst du weiterhin über die Umgebungsvariable `NETWORK`
konfigurieren.
Beenden lässt sich der Stack mit:
```bash
docker compose down
```
Die Volumes bleiben dabei erhalten.
Beim Einsatz des integrierten Proxy-Stacks (nginx, docker-gen und acme-companion) greift der Wert nur, solange keine eigene Vhost-Konfiguration vorliegt.
Soll ein höheres Limit dauerhaft gelten, lege im Verzeichnis `vhost.d/` eine Datei an.
Nach dem Anpassen genügt ein Neustart des Containers `docker-gen` (z.B. `docker compose restart docker-gen`), damit nginx die Einstellung übernimmt.

Werte `upload_max_filesize` und `post_max_size` angepasst werden. Dafür
liegt im Verzeichnis `config/` eine kleine `php.ini` bereit. Sie wird beim
Bauen des Docker-Images nach
`/usr/local/etc/php/conf.d/custom.ini` kopiert und automatisch geladen.
Das `docker-compose.yml` bindet dieselbe Datei als Volume ein, sodass
Änderungen ohne erneutes Bauen wirksam werden.
Die verwendete Domain wird aus der Datei `.env` gelesen (Variablen `DOMAIN` oder `MAIN_DOMAIN`).
Beim Start des Containers installiert ein Entrypoint-Skript automatisch alle
Composer-Abhängigkeiten, sofern das Verzeichnis `vendor/` noch nicht existiert.
Ein vorheriges `composer install` ist somit nicht mehr erforderlich,
solange die App innerhalb des Docker-Setups gestartet wird.

Ist in der `.env` die Variable `POSTGRES_DSN` gesetzt, legt das Entrypoint-
Skript beim Start automatisch die Datenbank anhand von `docs/schema.sql` an und
importiert die vorhandenen JSON-Daten. Danach werden die Migrationen einmalig
ausgeführt. Neben `POSTGRES_DSN` werden dafür auch
`POSTGRES_USER`, `POSTGRES_PASSWORD` und `POSTGRES_DB` ausgewertet. Das
veraltete `POSTGRES_PASS` wird bei Bedarf mit Warnung auf `POSTGRES_PASSWORD`
gespiegelt.

### Bildgrößen anpassen

Damit hochgeladene Dateien nicht unnötig groß werden, ist die Bibliothek [Intervention Image](https://image.intervention.io/) fest eingebunden.
Die Controller verkleinern Bilder automatisch auf eine
maximale Kantenlänge von 1500&nbsp;Pixeln (Beweisfotos) beziehungsweise
512&nbsp;Pixeln (Logo) und speichern sie mit 70–80&nbsp;% Qualität
im JPEG-Format. Fotos werden nach Möglichkeit anhand ihrer EXIF-Daten
gedreht, sofern die PHP-Installation diese Funktion unterstützt.

**Wichtig:** Die automatische Drehung funktioniert nur, wenn die PHP-Erweiterung `exif` installiert und aktiviert ist. Den Status prüfst du mit:
```bash
php -m | grep exif
```

Die Anwendung lädt beim Start eine vorhandene `.env`-Datei ein, auch wenn sie
ohne Docker betrieben wird. Ist `DOMAIN` oder `MAIN_DOMAIN` dort gesetzt,
werden für QR-Codes und Exportlinks diese Adressen verwendet. Enthält die
Variable kein Schema, wird
standardmäßig `https://` vorangestellt.

## Multi-Tenant Setup

Mehrere Subdomains lassen sich als eigene Mandanten betreiben. Das Verzeichnis
für die Compose-Dateien der Mandanten lässt sich über die Variable
`TENANTS_DIR` steuern (Standard: `tenants/`). Ein neuer Mandant wird mit
`scripts/create_tenant.sh` angelegt:

```bash
scripts/create_tenant.sh foo
```

Setzt du in `.env` zusätzlich `TENANT_SINGLE_CONTAINER=1`, arbeitet das Skript
mandantenfähig innerhalb des bestehenden `slim`-Containers. Standardmäßig
werden nur die Apex-Domains in `VIRTUAL_HOST` und `LETSENCRYPT_HOST` eingetragen,
damit HTTP-01-Challenges für die explizit konfigurierten Hosts funktionieren.
Aktivierst du hingegen `ENABLE_WILDCARD_SSL=1`, fügt das Entrypoint-Skript
einen Regex-Host für alle Subdomains hinzu und ergänzt einen Wildcard-Eintrag in
`LETSENCRYPT_HOST`. Das erfordert eine DNS-01-Challenge, um ein gültiges
Zertifikat zu erhalten.

Für den Wildcard-Fall wird ein Zertifikat von Let's Encrypt erwartet, das
`*.${MAIN_DOMAIN}` (oder – falls `MAIN_DOMAIN` nicht gesetzt ist – `*.${DOMAIN}`)
abdeckt und als `certs/<domain>.crt` sowie `certs/<domain>.key` im Projekt liegt.
Der `acme-companion` kann ein solches Zertifikat über die DNS-Challenge beziehen
und unter gleichem Namen in das `certs/`-Volume schreiben. Die neue Hilfsroutine
`scripts/provision_wildcard.sh` stößt diesen Prozess automatisiert an: Sie
startet bei Bedarf den `acme-companion`, ruft `acme.sh` mit dem in `.env`
konfigurierten DNS-Plugin auf und legt das Zertifikat im `certs/`-Verzeichnis
ab. `scripts/create_tenant.sh` ruft die Routine automatisch auf, wenn das
Wildcard-Zertifikat noch fehlt.

Das Entrypoint-Skript ergänzt in diesem Modus automatisch einen Regex-Host der
Form `~^([a-z0-9-]+\.)?${MAIN_DOMAIN}$` (oder `${DOMAIN}`) in `VIRTUAL_HOST`.
`LETSENCRYPT_HOST` enthält ausschließlich echte Domains beziehungsweise
Wildcard-Einträge (`*.example.test`), sodass der `acme-companion` keine
ungültigen CSRs erzeugt. Bestehende Einträge – beispielsweise Marketing-Domains
– bleiben erhalten und werden passend ergänzt, damit der Reverse Proxy
Zertifikate für alle Mandanten-Slugs ausstellen kann.

Konfiguriere für die automatische Ausstellung folgende Variablen in `.env`:

* `ACME_WILDCARD_PROVIDER` – Name des `acme.sh`-DNS-Plugins (unterstützt: `dns_cf`, `dns_hetzner`).
* `ACME_WILDCARD_ACCOUNT_EMAIL` (optional) – Account-Adresse; fällt ansonsten
  auf `LETSENCRYPT_EMAIL` zurück. Stelle sicher, dass sie gesetzt ist (z. B.
  identisch zu `LETSENCRYPT_EMAIL`), damit `acme.sh` ohne ZeroSSL-Nachfrage
  läuft; die Slim-Container-Umgebung übernimmt diesen Wert inzwischen
  automatisch.
* `ACME_WILDCARD_SERVICE` (optional) – Compose-Service des Companions
  (`acme-companion`).
* `ACME_WILDCARD_SERVER` und `ACME_WILDCARD_USE_STAGING` für abweichende
  ACME-Endpunkte.
* `ACME_WILDCARD_ENV_*` – Zugangsdaten für das gewählte DNS-Plugin, etwa
  `ACME_WILDCARD_ENV_CF_Token` und `ACME_WILDCARD_ENV_CF_Account_ID` für
  Cloudflare. Beachte bei Anbietern mit gemischter Groß-/Kleinschreibung wie
  Hetzner, dass du entweder den exakten Namen (`ACME_WILDCARD_ENV_HETZNER_Token`)
  oder dessen komplett großgeschriebene Variante (`ACME_WILDCARD_ENV_HETZNER_TOKEN`)
  verwenden kannst; das Skript setzt beide Formen auf die benötigte
  `HETZNER_Token`-Umgebungsvariable des Plugins um.

Über zusätzliche Variablen lässt sich der eingesetzte ACME-Anbieter anpassen.
Standardmäßig nutzt der Companion Let's Encrypt, kann aber durch folgende
Parameter in `.env` umkonfiguriert werden:

* `ACME_DEFAULT_CA` – Voreinstellung des Companions (`letsencrypt`,
  `letsencrypt-staging`, `zerossl`, `buypass`).
* `ACME_CA_URI` – Überschriebene Directory-URL des bevorzugten ACME-Servers.
* `ACME_CA_URI_ALTERNATE` – Alternative Directory-URL für den automatischen
  Fallback.
* `ACME_EAB_KID` und `ACME_EAB_HMAC_KEY` – Zugangsdaten für CAs, die ein
  External Account Binding voraussetzen.

Ist das Zertifikat vorhanden, muss `scripts/create_tenant.sh` den
`slim`-Container nicht mehr neu starten; neue Mandanten werden sofort nach dem
Proxy-Reload erreichbar.

Das Skript sendet einen API-Aufruf an `/tenants`, legt die Datei
`vhost.d/foo.$DOMAIN` an und lädt anschließend den Proxy neu. Zum Entfernen
eines Mandanten steht `scripts/delete_tenant.sh` bereit:

```bash
scripts/delete_tenant.sh foo
```

Beide Skripte lesen die Variable `DOMAIN` aus `.env` und nutzen sie
für die vhost-Konfiguration. Befindet sich im Projektverzeichnis eine `.env`,
lädt `scripts/onboard_tenant.sh` sie automatisch und übernimmt die dort
definierten Variablen. Sowohl `scripts/create_tenant.sh` als auch
`scripts/onboard_tenant.sh` räumen bei Fehlern die bereits erzeugten
Ressourcen wieder auf: vhost-Eintrag, `docker-compose.yml`, `data/`-Ordner
und gestartete Container werden über `scripts/offboard_tenant.sh`
beziehungsweise als Fallback per Dateilöschung entfernt.

Das Proxy-Setup legt zudem standardmäßig ein Docker-Netzwerk namens
`webproxy` an. Nach dem Aufruf von `scripts/create_tenant.sh` oder einem
`POST /tenants` muss das Onboarding angestoßen werden, damit der neue
Mandant diesem Netzwerk beitritt und ein Let's-Encrypt-Zertifikat erhält.
Starte dazu den Web-Assistenten unter `/onboarding` oder führe
`scripts/onboard_tenant.sh <subdomain>` aus. Stelle sicher, dass dein
Haupt-Stack dieses Netzwerk erstellt oder verwaltet. Den Namen kannst du
über die Umgebungsvariable `NETWORK` im Skript anpassen.

Das Skript `scripts/onboard_tenant.sh` steht weiterhin zur Verfügung, um
einen Container manuell zu starten oder neu aufzusetzen. Es legt im durch
`TENANTS_DIR` festgelegten Verzeichnis (`Standard: tenants/`) den Ordner
`<slug>/` an, erstellt dort eine eigene `docker-compose.yml` und ein
persistentes `data/`-Verzeichnis, das im Container unter `/var/www/data`
eingebunden wird. So bleiben hochgeladene Logos oder Fotos auch bei
Upgrades erhalten. Zusätzlich fordert das Skript das SSL-Zertifikat an.
Welches Docker-Image dabei verwendet wird, lässt sich über die Variable `APP_IMAGE` in der `.env` steuern.
Dieses Tag sollte dem lokal gebauten Slim-Image entsprechen (`docker build -t <tag> .`),
da das Onboarding-Skript diese Variable nutzt.

Schlägt das Onboarding fehl, hilft ein Blick in das Log:

```bash
tail -n 50 logs/onboarding.log
```


## Wildcard-Domain für die Anwendung einrichten

Damit die Anwendung jede Subdomain deines Mandanten verarbeitet (z. B.
`team1.example.test`, `marketing.example.test`), richte die Wildcard-Domain
innerhalb des Stacks wie folgt ein:

1. **DNS vorbereiten** – Leite einen Wildcard-A/AAAA-Record (`*.example.test`)
   auf die IP deines Reverse Proxys. Der Apex (`example.test`) sollte ebenfalls
   auf dieselbe IP zeigen.
2. **Umgebungsvariablen setzen** – Trage in `.env` mindestens `DOMAIN` oder
   `MAIN_DOMAIN` sowie `ENABLE_WILDCARD_SSL=1` ein. Hinterlege das gewünschte
   ACME-DNS-Plugin (z. B. `dns_cf`) per `ACME_WILDCARD_PROVIDER`, den Companion
   über `ACME_WILDCARD_SERVICE=acme-companion` und die Zugangsdaten via
   `ACME_WILDCARD_ENV_*` (siehe oben). Für Marketing-Subdomains kannst du
   Regex-/Wildcard-Hosts bereits in `VIRTUAL_HOST`/`LETSENCRYPT_HOST`
   ergänzen, damit der Companion die DNS-01-Challenges akzeptiert.
3. **Wildcard-Zertifikat beziehen** – Starte den Stack mit Compose
   (`docker compose up -d slim acme-companion`) und führe anschließend
   `scripts/provision_wildcard.sh --domain "$MAIN_DOMAIN"` aus (alternativ:
   `bin/provision-wildcard-certificates` kopiert vorhandene Marketing-Zonen
   auf das Companion-Volume). Das Skript kümmert sich um die DNS-01-Challenge
   per `acme.sh` und legt `.crt`/`.key` unter `certs/<domain>.*` sowie
   `/etc/ssl/wildcards/<zone>/` ab.
4. **Reverse Proxy aktualisieren** – Das Entrypoint-Skript ergänzt nach dem
   Zertifikat ein Regex-Host-Muster (`~^([a-z0-9-]+\.)?<domain>$`) in
   `VIRTUAL_HOST` und lädt nginx neu. Kontrolliere mit `docker logs slim`
   bzw. `docker logs acme-companion`, dass der Reload erfolgreich war und die
   Zertifikate im Volume sichtbar sind.
5. **Mandanten testen** – Lege einen neuen Mandanten mit
   `scripts/create_tenant.sh <slug>` an oder onboarde einen bestehenden via
   `/onboarding`. Rufe anschließend eine beliebige Subdomain deines Mandanten
   per HTTPS auf. Das Zertifikat sollte für `*.example.test` ausgestellt sein.

Kommt es bei Schritt 3 zu Fehlern (häufig: DNS-Provider-API), hilft ein Blick
in die Protokolle des Companions (`docker logs acme-companion`). Solange kein
gültiges Wildcard-Zertifikat vorliegt, blockiert `scripts/create_tenant.sh` das
Onboarding und weist auf den fehlgeschlagenen Zertifikatsabruf hin.

### Synchronisation der Mandantenliste

Die Mandantenübersicht im Admin-Backend lädt bestehende Einträge nur noch
über die Tabelle selbst. Ein automatischer Sync beim Öffnen des Tabs findet
nicht mehr statt; der Abgleich kann weiterhin manuell über den Sync-Button
oder automatisiert durch einen Hintergrundjob ausgelöst werden. Der Button
trägt ein Status-Badge, das zwischen „Aktuell“, „Wartezeit“ (Cooldown läuft)
und „Sync nötig“ unterscheidet.

`TenantService::importMissing()` speichert den Zeitpunkt des letzten Syncs
und erzwingt eine Wartezeit von fünf Minuten, bevor erneut Mandanten
eingelesen werden. Wiederholte Aufrufe innerhalb dieser Frist werden
throttled beantwortet, damit keine parallel laufenden Scans entstehen.

Das Skript legt dabei eine Compose-Datei an, die analog zum Hauptcontainer
einen PHP-Webserver auf Port `8080` startet und `VIRTUAL_PORT=8080` setzt.
Nur so kann der `acme-companion` die HTTP-Challenge beantworten und das
Zertifikat erstellen.

Um diese Container auch aus dem `slim`-Service heraus starten zu können,
bringt das Image nun neben dem Docker-CLI auch das Compose-Plugin mit und
benötigt Zugriff auf den Docker-Daemon. Binde dafür
`/var/run/docker.sock` ein oder führe `scripts/onboard_tenant.sh`
alternativ direkt auf dem Host aus, wenn Docker im Container nicht
verfügbar ist.

Zum Entfernen einer isolierten Instanz nutzt du `scripts/offboard_tenant.sh`.
Das Skript stoppt den Container und löscht das Verzeichnis
`${TENANTS_DIR}/<slug>/`:

```bash
scripts/offboard_tenant.sh foo
```

Der Datenbanknutzer, der über `POSTGRES_USER` definiert ist, muss
neue Schemas und Tabellen anlegen dürfen. Bei PostgreSQL reicht etwa
folgender Befehl aus:

```sql
GRANT CREATE ON DATABASE quiz TO quiz;
```

Anschließend kann `CREATE SCHEMA` im Hintergrund ausgeführt werden.

### `.env`-Blöcke

Die wichtigsten Umgebungsvariablen werden in der `.env` gebündelt. Der
Minimal-Block ist zwingend nötig und besteht aus

- `COMPOSE_PROJECT_NAME`
- `DOMAIN`/`MAIN_DOMAIN`
- `SLIM_VIRTUAL_HOST`
- `POSTGRES_DSN`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_DB`
- `LETSENCRYPT_EMAIL`

Weitere Werte sind optional. Der TLS-Block bündelt ACME-/nginx-Einstellungen
(`ACME_*`, `NGINX_WILDCARD_*`, `SLIM_LETSENCRYPT_HOST`, `ENABLE_WILDCARD_SSL`)
und kann leer bleiben, solange keine Wildcard-Automatisierung benötigt wird.
Alle Beispiele sind in `sample.env` ausführlich kommentiert; veraltete Felder
wie `SLIM_VIRTUAL_HOSTS`, `SLIM_LETSENCRYPT_HOSTS` oder `MARKETING_DOMAINS`
lösen nur noch Warnungen aus und werden automatisch auf die neuen Variablen
umgesetzt.

Für den eigentlichen Quiz-Container lässt sich der Hostname über die
Umgebungsvariable `SLIM_VIRTUAL_HOST` steuern. Starte mehrere Instanzen
mit unterschiedlichen Werten, werden die Subdomains automatisch als
eigene Mandanten behandelt.

Marketing-Domains werden ausschließlich in der Anwendung hinterlegt
(`domains`-Tabelle) und beim Anlegen automatisch einer Zone zugeordnet.
Die Zone-Liste wird in `certificate_zones` persistiert und dient als
einzige Quelle für TLS-Konfiguration:

- `bin/generate-nginx-zones` erzeugt für jede aktive Zone eine statische
  Datei unter `/etc/nginx/wildcards/<zone>.conf` (HTTP→HTTPS-Redirect, Proxy
  auf den Slim-Container) und entfernt veraltete Einträge, bevor nginx
  einmalig per `nginx -s reload` neu geladen wird.
- `bin/provision-wildcard-certificates` nutzt `acme.sh` mit DNS-01, stellt
  `<zone>` und `*.zone` aus und installiert die Zertifikate unter
  `/etc/ssl/wildcards/<zone>/`. Die Tabelle `certificate_zones` zeichnet den
  Status der Ausstellung mit Zeitstempel auf; nginx wird nur nach einer
  tatsächlichen Änderung der Zertifikate neu geladen.

Der vollständige Ablauf für Marketing-Hosts:

1. Domains im Admin-Bereich aktiv schalten (**Administration → Domains**) und prüfen, dass die abgeleiteten Zonen in `certificate_zones` landen (`SELECT * FROM certificate_zones ORDER BY zone;`).
2. Nach dem Aktivieren einer Domain stößt die Admin-API automatisch `scripts/wildcard_maintenance.sh` an, sofern die ACME-Variablen (`ACME_SH_BIN`, `ACME_WILDCARD_PROVIDER`, `NGINX_WILDCARD_CERT_DIR`) gesetzt sind. Andernfalls bleiben die Zonen auf `pending` und der stündliche Timer (siehe unten) übernimmt.
3. `bin/generate-nginx-zones` und `bin/provision-wildcard-certificates` lassen sich weiterhin manuell ausführen (oder per Systemd-Timer aktivieren), damit `<zone>` und `*.zone` in die Wildcard-Anforderung einfließen und nginx die neuen Zonen kennt.
4. Falls einzelne Marketing-Hosts sofort per Companion bedient werden sollen, in `.env` `SLIM_LETSENCRYPT_HOST` setzen (nur konkrete Hosts, keine Regex). Der Entrypoint übernimmt sie vor dem Filterprozess nach `LETSENCRYPT_HOST`.

### Automatisierung der Wildcard-Zertifikate

Ein stündlicher Systemd-Timer kümmert sich um die statische nginx-Zonenliste
und die Zertifikats-Erneuerung. Die Unit-Dateien liegen unter
`resources/systemd/`:

1. Kopiere `resources/systemd/wildcard-maintenance.service` und
   `resources/systemd/wildcard-maintenance.timer` nach `/etc/systemd/system/`.
2. Passe `WorkingDirectory` und `EnvironmentFile` an deinen Projektpfad an (z. B.
   `/opt/quizrace`) und hinterlege die nötigen Variablen (`ACME_SH_BIN`,
   `ACME_WILDCARD_PROVIDER`, `NGINX_WILDCARD_CERT_DIR`, optional `ACME_SH_HOME`).
3. Aktiviere den Timer: `systemctl daemon-reload && systemctl enable --now
   wildcard-maintenance.timer` (oder richte einen äquivalenten Cronjob ein).
4. Prüfe die Ausführung und Fehlerlogs frühzeitig über
   `logs/wildcard-maintenance.log` oder `journalctl -u
   wildcard-maintenance.service`.

Der Timer ruft `scripts/wildcard_maintenance.sh` auf. Das Skript läuft im
Projektwurzelverzeichnis, validiert die benötigten Umgebungsvariablen, führt
`bin/generate-nginx-zones` und `bin/provision-wildcard-certificates`
nacheinander aus und protokolliert alles in `logs/wildcard-maintenance.log`.
Cron-Alternative: `0 * * * * cd /opt/quizrace && /usr/bin/env
ACME_SH_BIN=/usr/bin/acme.sh ACME_WILDCARD_PROVIDER=dns_cf
NGINX_WILDCARD_CERT_DIR=/etc/ssl/wildcards ./scripts/wildcard_maintenance.sh
>> /var/log/wildcard-maintenance.log 2>&1`

Marketing-Domains müssen im Admin-Bereich aktiv geschaltet sein, damit die
Tabelle `certificate_zones` gefüllt wird. Unter **Administration → Domains**
stellt der **Aktiv**-Schalter sicher, dass jede Domain einer Zone zugeordnet
und für die Zertifikats-Jobs eingeplant wird. Der Status der Anforderung
(`pending`/`issued`) lässt sich per SQL kontrollieren:

```sql
SELECT domain, zone, status, last_issued_at
FROM certificate_zones
ORDER BY zone;
```

Die gleiche Admin-Ansicht bietet den Button **Domains prüfen**, der fehlende
Zertifikate nachzieht und DNS-Fehler meldet. Anschließend sollten die Logs des
Maintenance-Skripts auf Fehlermeldungen kontrolliert werden.

`SLIM_LETSENCRYPT_HOST` ist weiterhin verfügbar, sollte aber nur für
konkrete Hosts (keine Regex-Ausdrücke) genutzt werden. Marketing-Domain-
Listen in `.env` entfallen vollständig – die Anwendung ist die zentrale
Quelle für alle Domains und Zonen.

Weitere nützliche Variablen in `.env` sind:

- `LETSENCRYPT_EMAIL` – Kontaktadresse für die automatische Zertifikatserstellung.
- `SLIM_LETSENCRYPT_HOST` – zusätzliche Zertifikats-Domains für den Slim-Container
  (nur konkrete Hostnamen, keine Regex-Ausdrücke).
- `MAIN_DOMAIN` – zentrale Domain des Quiz-Containers (z.B. `quizrace.app`).
- `APP_IMAGE` – Docker-Image, das für neue Mandanten verwendet wird.
  Es sollte den Tag des lokal gebauten Slim-Images (`docker build -t <tag> .`) nutzen,
  da das Onboarding-Skript auf diese Variable zurückgreift.
- `NETWORK` – Name des Docker-Netzwerks des Reverse Proxy (Standard `webproxy`).
- `BASE_PATH` – optionaler Basis-Pfad, falls die Anwendung nicht im Root der Domain liegt.
- `SERVICE_USER` – Benutzername für den automatischen Login des Onboarding-Assistenten.
- `SERVICE_PASS` – Passwort dieses Service-Benutzers.
- Sind beide gesetzt, führt `scripts/create_tenant.sh` das Onboarding
  nach dem Anlegen eines Mandanten automatisch per `POST /api/tenants/<subdomain>/onboard` aus.
- **Wichtig:** Der Onboarding-Assistent setzt einen Account mit mindestens der
  Rolle `service-account` voraus, um alle Schritte (inklusive `POST /restore-default`)
  automatisiert auszuführen. Ein Beispiel zum Anlegen:

```bash
curl -X POST http://$DOMAIN/users.json \
  -H 'Content-Type: application/json' \
  -d '[{"username":"robot","password":"secret","role":"service-account","active":true}]'
```
- `NGINX_RELOAD_TOKEN` – Token für den Webhook `http://nginx-reloader:8080/reload`.
- `NGINX_CONTAINER` – Name des Proxy-Containers (Standard `nginx`).
- `NGINX_RELOAD` – auf `0` setzen, wenn ein externer Webhook den Reload übernimmt.
- `NGINX_RELOADER_URL` – URL eines externen Webhooks für den Proxy-Reload.
- `DISPLAY_ERROR_DETAILS` – auf `1` setzen, um detaillierte Fehlermeldungen anzuzeigen.

Bei der Mandanten-Erstellung fragt der Onboarding-Assistent nach einem Admin-Passwort.
Bleibt das Feld leer, erzeugt die Anwendung automatisch ein sicheres Passwort und zeigt es nach der Einrichtung an. Dieses Passwort ersetzt das zuvor generierte Standardpasswort des Admin-Benutzers.

## Anpassung

Alle wichtigen Einstellungen werden in der Datenbank gespeichert und lassen sich über die Administrationsoberfläche ändern. Die Fragen selbst liegen in `data/kataloge/*.json` und können mit jedem Texteditor angepasst werden. Jede Katalogdefinition besitzt weiterhin ein `slug` für die URL. Fragen verknüpfen den Katalog nun über `catalog_uid`. Das bisherige `id` dient ausschließlich der Sortierung und wird automatisch vergeben.

QR-Codes können pro Eintrag über `qr_image` hinterlegt werden, etwa als Data-URI oder lokaler Pfad.

Die Übersichtsseiten erzeugen ihre QR-Codes jetzt lokal mit der Bibliothek *chillerlan\\php-qrcode*. Katalog-Links erscheinen rot, Team-Links blau. Die Generator-Optionen überlassen der Bibliothek die Wahl der passenden QR-Version, sodass auch lange URLs ohne zusätzliche Konfiguration zuverlässig kodiert werden.

### Rich-Text-Editor

Zum Bearbeiten der statischen Seiten kommt **TipTap Core** mit dem
**StarterKit** zum Einsatz. Die Initialisierung sowie die Anbindung an die
Formulare übernimmt das Modul `public/js/tiptap-pages.js`, das außerdem vor dem
Speichern DOMPurify-Sanitizing und eine `srcset`-Prüfung ausführt. Eine
Vorschau lässt sich direkt im Modal aufrufen.

## Tests

Alle Tests lassen sich komfortabel über Composer starten:
```bash
composer test
```

Der Befehl führt zunächst PHPUnit aus, ruft anschließend die beiden
Python-Skripte zur Prüfung der HTML- bzw. JSON-Dateien auf und startet danach
folgende JavaScript-Tests mit Node.js:

```
node tests/test_competition_mode.js
node tests/test_results_rankings.js
node tests/test_random_name_prompt.js
node tests/test_onboarding_plan.js
node tests/test_onboarding_flow.js
node tests/test_login_free_catalog.js
node tests/test_catalog_smoke.js
node tests/test_catalog_autostart_path.js
node tests/test_shuffle_questions.js
node tests/test_team_name_suggestion.js
node tests/test_catalog_prevent_repeat.js
node tests/test_event_summary_switch.js
node tests/test_sticker_editor_save_events.js
node tests/test_media_filters.js
node tests/test_media_preview.js
```

Für die JavaScript-Tests wird Node.js **20 LTS** benötigt. Zusätzliche
Abhängigkeiten sind nicht erforderlich; falls du npm- oder pnpm-Workflows
nutzt, reicht ein minimaler `package.json`-Stub wie `{ "type": "commonjs" }`,
damit die Skripte ohne weitere Pakete lauffähig bleiben.

## Teams/Personen

- Neuer Tab "Teams/Personen" in der Administration.
- Liste mit Name und QR-Code, editierbar.
- QR-Code-Login auf bekannte Teams/Personen beschränkbar.
- Aktivierung/Deaktivierung der Beschränkung per Schalter.
- Manuelle Namenseingabe wird bei aktiver Beschränkung unterbunden.
- Die Uploadseite für Beweisfotos bietet jetzt ein Eingabefeld mit Vorschlagsliste für die Teamwahl.

## Datenschutz

Alle Ergebnisse werden in der Datenbank gespeichert. Über die Administrationsoberfläche lassen sie sich als CSV-Datei herunterladen. Jede Zeile enthält ein Pseudonym, den verwendeten Katalog, die Versuchnummer, die Punktzahl, den Zeitpunkt sowie optional die Rätselwort-Bestzeit und den Pfad eines Beweisfotos. Die exportierte Datei ist UTF‑8-kodiert und enthält eine BOM, damit Excel Sonderzeichen korrekt erkennt.

## Barrierefreiheit

Das Frontend bringt mehrere Funktionen mit, die die Nutzung erleichtern:

- Ausführliche ARIA-Beschriftungen auf Bedienelementen und Formularfeldern.
- Tastatursteuerung für Sortier- und Zuordnungsfragen samt versteckten Hinweisen.
- Fortschrittsbalken mit `aria-valuenow` und Live-Ansage der aktuellen Frage.
- Automatische Dark-Mode-Erkennung sowie umschaltbarer Dunkel- und Hochkontrastmodus.


## Anwenderhandbuch

### Einleitung
Das Projekt *QuizRace* ist eine Web-Applikation zur Erstellung und Verwaltung von Quizfragen. Die Anwendung basiert auf dem Slim Framework und verwendet UIkit3 für das Frontend. Konfigurationen, Kataloge, Teams und Ergebnisse liegen in einer PostgreSQL-Datenbank und lassen sich über die Oberfläche als JSON-Dateien exportieren oder importieren.

### Installation und Start
1. Abhängigkeiten per Composer installieren:
   ```bash
   composer install
   ```
   Beim ersten Aufruf wird eine `composer.lock` erzeugt und alle benötigten Bibliotheken geladen.
2. Die Beispieldatei `sample.env` in `.env` kopieren und bei Bedarf anpassen. Sie enthält `COMPOSE_PROJECT_NAME=sommerfest-quiz`, wodurch Docker Compose bei späteren Deployments bestehende Container und Volumes wiederverwendet:
   ```bash
   cp sample.env .env
   ```
3. Lokalen Server starten:
   ```bash
   php -S localhost:8080 -t public public/router.php
   ```
   Anschließend ist das Quiz unter `http://localhost:8080` erreichbar.

4. Optional: Tabellen in einer PostgreSQL-Datenbank anlegen und JSON-Daten importieren (siehe Abschnitt "Schnellstart" für ausführliche Befehle).

Für Docker-Betrieb steht ein `docker-compose.yml` bereit. Zertifikate und weitere Konfigurationen werden in Volumes gesichert, sodass keine lokalen Ordner benötigt werden.

### Konfiguration
Alle wesentlichen Einstellungen werden in der Datenbank gespeichert und können über die Administration angepasst werden:

```json
{
  "displayErrorDetails": true,
  "QRUser": true,
  "QRRemember": false,
  "logoPath": "/logo.png",
  "ogImagePath": "/uploads/seo/social-preview.jpg",
  "pageTitle": "Modernes Quiz mit UIkit",
  "backgroundColor": "#ffffff",
  "buttonColor": "#1e87f0",
  "startTheme": "light",
  "CheckAnswerButton": "no",
  "QRRestrict": false,
  "competitionMode": false,
  "teamResults": true,
  "photoUpload": true,
  "puzzleWordEnabled": true,
  "puzzleWord": "",
  "puzzleFeedback": "",
  "inviteText": "",
  "postgres_dsn": "pgsql:host=postgres;dbname=quiz",
  "postgres_user": "quiz",
  "postgres_password": "***"
}
```

Hinweis: Platzhaltergrafiken für Landingpage, calServer und SEO werden nicht mitgeliefert. Legen Sie bei Bedarf eigene Dateien im Bereich "Global" des Media-Managers unter `/uploads/...` an und passen die Pfade (z. B. `ogImagePath`) entsprechend an.

Der Parameter `displayErrorDetails` kann auch über die Umgebungsvariable
`DISPLAY_ERROR_DETAILS` gesetzt werden.

Optional kann `baseUrl` gesetzt werden, um in QR-Codes vollständige Links mit Domain zu erzeugen. Die Option `startTheme` bestimmt, ob die Teilnehmeroberfläche standardmäßig hell (`light`) oder dunkel (`dark`) geladen wird. `QRRemember` speichert gescannte Namen und erspart das erneute Einscannen. Der Parameter `competitionMode` blendet im Quiz alle Neustart-Schaltflächen aus, ersetzt die Teamnamen-Option „Zurücksetzen“ durch „Name ändern“, verhindert Wiederholungen bereits abgeschlossener Kataloge und unterbindet die Anzeige der Katalogübersicht. Die Startseite prüft dabei serverseitig, ob für Spieler und Katalog bereits ein Ergebnis vorliegt, und blockiert bei Wiederholungen den Start. Ein Fragenkatalog kann dann nur über einen direkten QR-Code-Link gestartet werden. Im Wettkampfmodus führt ein Aufruf der Hauptseite ohne gültigen Katalog-Parameter automatisch zur Hilfe-Seite. Über `teamResults` lässt sich steuern, ob Teams nach Abschluss aller Kataloge ihre eigene Ergebnisübersicht angezeigt bekommen. `photoUpload` blendet die Buttons zum Hochladen von Beweisfotos ein oder aus. `puzzleWordEnabled` schaltet das Rätselwort-Spiel frei und `puzzleFeedback` definiert den Text, der nach korrekter Eingabe angezeigt wird. `inviteText` enthält ein optionales Anschreiben für teilnehmende Teams.

`ConfigService` verwaltet diese Werte in der Datenbank. Jeder Event besitzt dabei eine eigene Konfiguration.
Welcher Event aktuell bearbeitet wird, steht in der Tabelle `active_event`. Ein GET auf `/config.json`
liefert die Einstellungen des aktiven Events, ein POST auf dieselbe URL speichert die Änderungen.
Über den URL-Parameter `event` kann im Frontend ein beliebiger Event zur Ansicht gewählt werden,
ohne ihn als aktiv zu setzen. Die Backend-Logik bleibt davon unberührt.
Mit `lang` lässt sich zusätzlich die Sprache der Oberfläche festlegen (`en` oder `de`).
Die Übersetzungen befinden sich in `resources/lang/` sowie `public/js/i18n/`.

### Authentifizierung
Der Zugang zum Administrationsbereich erfolgt über `/login`. Benutzer und Rollen werden in der Tabelle `users` verwaltet. Nach erfolgreichem POST mit gültigen Zugangsdaten speichert das System die Benutzerinformationen inklusive Rolle in der Session und leitet Administratoren zur Route `/admin` weiter. Die Middleware `RoleAuthMiddleware` prüft die gespeicherte Rolle und leitet bei fehlenden Berechtigungen zum Login um.

### E-Mail-Versand
Für Funktionen wie das Zurücksetzen von Passwörtern nutzt die Anwendung Symfony Mailer. Die bevorzugte Konfiguration erfolgt im Adminbereich unter **Administration → Mail-Anbieter**. Dort lassen sich pro Mandant Provider auswählen, SMTP-Zugangsdaten und optionale API-Schlüssel hinterlegen sowie per „Verbindung testen“ prüfen. Die Daten werden in der Tabelle `mail_providers` gespeichert und mit `MAIL_PROVIDER_SECRET` (Fallback `PASSWORD_RESET_SECRET`) verschlüsselt. Ist kein Geheimnis gesetzt, weist die Oberfläche darauf hin und verweigert das Speichern.

Über den Bereich **Administration → Domains** können weiterhin domainspezifische SMTP-Overrides gepflegt werden (z. B. ein dedizierter Mailer-DSN oder eigene Zugangsdaten). Diese Einstellungen haben Vorrang vor der globalen Provider-Konfiguration, sobald für eine Domain ein Override hinterlegt ist.

Die bisherigen `.env`-Variablen dienen als Fallback, solange noch keine Provider-Konfiguration hinterlegt ist oder einzelne Felder leer bleiben:

1. **Klassisches SMTP** über folgende Variablen:
   - `SMTP_HOST` – Hostname des Servers
   - `SMTP_USER` – Benutzername
   - `SMTP_PASS` – Passwort
   - `SMTP_PORT` – Port (z. B. 587)
   - `SMTP_ENCRYPTION` – Verschlüsselung (`none`, `tls` oder `ssl`)
2. **Direkter Mailer-DSN** über `MAILER_DSN`, falls ein Provider mit eigener API oder speziellen Parametern verwendet wird. In diesem Fall werden die Werte aus `SMTP_HOST` bis `SMTP_ENCRYPTION` ignoriert.

Unabhängig von der Variante legen `SMTP_FROM` (Absenderadresse) und `SMTP_FROM_NAME` (Absendername) den sichtbaren Absender fest. Diese Werte lassen sich entweder im Admin-Formular pflegen oder weiterhin in `.env` setzen.

Beispiele für DSNs:

- Brevo SMTP: `smtp://support%40quizrace.app:DEIN_STARKES_PASSWORT@smtp.brevo.com:587?encryption=tls`
- Mailgun API: `mailgun+https://API_KEY:DEINE-DOMAIN@default?region=eu`
- Brevo API: `brevo+api://DEIN_API_KEY@default`
- Mailchimp (Mandrill) API: `mailchimp+https://DEIN_MANDRILL_KEY@default`

### Cloudflare Turnstile

Die Marketing-Kontaktformulare lassen sich zusätzlich mit einem Cloudflare-Turnstile-Captcha schützen. Dafür müssen in der `.env` beide Schlüssel hinterlegt werden:

- `TURNSTILE_SITE_KEY` – öffentlicher Schlüssel für das Frontend-Widget
- `TURNSTILE_SECRET_KEY` – geheimer Schlüssel für die Server-Validierung

Sind beide Werte gesetzt, blendet die Landingpage automatisch das Turnstile-Widget ein und der `ContactController` validiert eingehende Token serverseitig. Ohne Konfiguration bleibt das Formular wie bisher frei zugänglich.

### Wiki-Feature-Flag

Der optionale Wiki-Bereich der Marketing-Seiten lässt sich global über die Umgebungsvariable `FEATURE_WIKI_ENABLED` steuern. Der Standardwert ist `true`. Setzen Sie den Wert auf `false`, um alle öffentlichen und administrativen Wiki-Routen vollständig zu deaktivieren, ohne einzelne Seiteneinstellungen anpassen zu müssen.

### Passwort zurücksetzen
Die API unterstützt ein zweistufiges Verfahren zum Zurücksetzen vergessener Passwörter:

1. `POST /password/reset/request` nimmt einen Benutzernamen oder eine E‑Mail-Adresse entgegen und verschickt einen Link mit einem Reset-Token.
2. `POST /password/reset/confirm` setzt nach Validierung des Tokens das neue Passwort.

Für den Versand der E-Mails muss entweder ein vollständiger `MAILER_DSN` gesetzt oder – beim SMTP-Fallback – `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`, `SMTP_PORT` und `SMTP_ENCRYPTION` konfiguriert sein. Über `SMTP_FROM` und `SMTP_FROM_NAME` lässt sich der Absender zentral festlegen. Das Token ist aus Sicherheitsgründen nur eine Stunde gültig.

### Administrationsoberfläche
Unter `/admin` stehen folgende Tabs zur Verfügung:
1. **Veranstaltung konfigurieren** – Einstellungen wie Logo, Farben und Texte.
2. **Übersicht** – Ergebnisse tabellarisch einsehen.
3. **Kataloge** – Fragenkataloge erstellen und verwalten.
4. **Fragen anpassen** – Fragen eines Katalogs hinzufügen, bearbeiten oder löschen.
5. **Teams/Personen** – Teilnehmerlisten pflegen, optional als Login-Beschränkung.
6. **Ergebnisse** – Spielstände einsehen und herunterladen.
7. **Statistik** – Einzelne Antworten analysieren und nach Teams filtern.
8. **News-Artikel** – News-Beiträge einzelnen Landingpages zuordnen, inklusive Veröffentlichungstermin und Sichtbarkeitsstatus.
9. **Administration** – Benutzer und Backups verwalten.

### Fragenkataloge
`data/kataloge/catalogs.json` listet verfügbare Kataloge mit `slug`, Name und optionaler QR-Code-Adresse. Die Reihenfolge wird durch das Feld `sort_order` bestimmt. Jede Frage speichert die zugehörige `catalog_uid`. Jeder Eintrag kann zusätzlich ein Feld `raetsel_buchstabe` enthalten, das den Buchstaben für das Rätselwort festlegt. Die API bietet hierzu folgende Endpunkte:
- `GET /kataloge/{file}` liefert den JSON-Katalog oder leitet im Browser auf `/?katalog=slug` um.
- `PUT /kataloge/{file}` legt eine neue Datei an.
- `POST /kataloge/{file}` überschreibt einen Katalog mit gesendeten Daten.
- `DELETE /kataloge/{file}` entfernt die Datei.
- `DELETE /kataloge/{file}/{index}` löscht eine Frage anhand des Index.

### Teams und QR-Code-Login
In `data/teams.json` können Teilnehmernamen gespeichert werden. `GET /teams.json` ruft die Liste ab, `POST /teams.json` speichert sie. Ein optionales Häkchen „Nur Teams/Personen aus der Liste dürfen teilnehmen“ aktiviert eine Zugangsbeschränkung via QR-Code. QR-Codes lassen sich direkt in der Oberfläche generieren.

#### Voraussetzungen für QR-Code-Endpunkte

Damit die QR-Code-Endpunkte unter `/qr/...` funktionieren, müssen folgende Bedingungen erfüllt sein:

- Bei Apache ist `mod_rewrite` zu aktivieren oder bei Nginx entsprechende Rewrite-Regeln einzurichten.
- Die PHP-Erweiterung `gd` muss aktiv sein, um die QR-Codes zu rendern.

Ob die Konfiguration korrekt ist, lässt sich mit `/qr/team?t=Beispiel` testen.

### Spielerprofil
Auf `/profile` legen Teilnehmende ihren Anzeigenamen fest. Ist die Option für Zufallsnamen aktiv und noch kein Name hinterlegt, leitet die Startseite automatisch auf diese Profilseite weiter. Nach dem Speichern werden Name und eine zufällige Kennung lokal gespeichert und über `/api/players` an den Server gemeldet.

### Ergebnisse
Alle Resultate werden in der Datenbank abgelegt. Die API bietet folgende Endpunkte:
- `GET /results.json` – liefert alle gespeicherten Ergebnisse.
- `POST /results` – fügt ein neues Ergebnis hinzu.
- `DELETE /results` – löscht alle Einträge.
- `GET /results/download` – erzeugt eine CSV-Datei mit allen Resultaten.
- `GET /question-results.json` – listet falsch beantwortete Fragen.

Die Ergebnisübersicht zeigt drei Ranglisten. Der Titel „Ranking-Champions"
ordnet Teams nach der Anzahl gelöster Fragen. Bei Gleichstand entscheiden
zunächst die erreichten Punkte und anschließend die kleinste insgesamt
benötigte Spielzeit. Dadurch erscheinen Teams auch dann in der Liste, wenn
nicht alle Fragenkataloge vollständig gelöst wurden.

### Statistik
Im Statistik-Tab lassen sich alle gegebenen Antworten detailliert auswerten. Die Tabelle zeigt Name, Versuch, Katalog,
Frage, Antwort, ob sie korrekt war, und ein optionales Beweisfoto. Über ein Dropdown lässt sich die Ansicht auf einzelne
Teams oder Personen beschränken.


### Administration
Backups lassen sich über `/export` erstellen und per `/import` oder
`/backups/{name}/restore` wiederherstellen. `GET /backups` listet alle
Sicherungen, einzelne Ordner können über `/backups/{name}/download`
heruntergeladen oder via `DELETE /backups/{name}` entfernt werden.
Damit das funktioniert, muss der Ordner `backup/` vom Serverprozess
beschreibbar sein.

Neben Mandanten-Zertifikaten kann das SSL-Zertifikat der Admin-Domain über einen POST auf `/api/renew-ssl` erneuert werden. Der Aufruf startet den Hauptcontainer neu.

### Logo hochladen
Das aktuelle Logo wird unter `/logo.png`, `/logo.webp` oder `/logo.svg` bereitgestellt. Über einen POST auf diese URLs lässt sich eine neue PNG-, WebP- oder SVG-Datei hochladen. Nach dem Upload wird der Pfad automatisch in der Datenbank gespeichert. Die Datei landet im Verzeichnis `data/`, damit auch PDFs das Logo einbinden können.

### Sicherheit und Haftung
Die Software wird ohne Gewähr bereitgestellt. Alle Rechte liegen bei René Buske. Eine Haftung für Schäden, die aus der Nutzung entstehen, ist ausgeschlossen. Die integrierten Maßnahmen zur Barrierefreiheit verbessern die Zugänglichkeit, sie ersetzen jedoch keine individuelle Prüfung.

### Fazit
Die API ermöglicht die komplette Verwaltung eines Quizsystems:
- Konfiguration, Fragenkataloge, Teams, Ergebnisse und Fotoeinwilligungen werden in einer PostgreSQL-Datenbank verwaltet.
- Über das Admin-Frontend sind diese Bereiche komfortabel zugänglich.
- Ergebnisse lassen sich als Excel/CSV exportieren.
Dieses Handbuch fasst die Nutzung von der Grundkonfiguration über das Anlegen der Fragen und Teams bis zum Abruf der Resultate zusammen.
Durch den Einsatz von Slim Framework und standardisierten Endpunkten ist die Anwendung sowohl lokal als auch im Netzwerk schnell einsetzbar.


## Lizenz
Dieses Projekt steht unter einer proprietären Lizenz. Alle Rechte gehören René Buske. Eine kommerzielle Nutzung ist erlaubt. Weitere Informationen finden Sie in der Datei `LICENSE`.
