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

## Testing

Die automatisierten Tests werden mit PHPUnit ausgeführt. Ist keine Umgebung
`POSTGRES_DSN` gesetzt, erstellt der Test-Harness eine gemeinsam genutzte
SQLite-Datenbank im Speicher und wendet alle Migrationen automatisch an. So
können die Tests ohne lokale PostgreSQL-Instanz gestartet werden.

Wer die Tests gegen PostgreSQL ausführen möchte, setzt `POSTGRES_DSN`,
`POSTGRES_USER` und `POSTGRES_PASSWORD` auf die gewünschten Verbindungsdaten.
Alternativ lässt sich über `Tests\TestCase::setDatabase()` eine eigene
`PDO`-Instanz einspeisen.

Tests starten mit:

```bash
vendor/bin/phpunit
```

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

Zum Start genügt:
```bash
cp sample.env .env
docker compose up --build -d
```
Die Datei setzt `COMPOSE_PROJECT_NAME=sommerfest-quiz`, damit Docker Compose vorhandene Container und Volumes bei späteren Deployments wiederverwendet.
Falls der Reverse Proxy das Docker-Netzwerk noch nicht kennt, lege es vorher an:
```bash
docker network create ${NETWORK:-webproxy}
```
Der Name des Netzwerks lässt sich über die Umgebungsvariable `NETWORK` anpassen.
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
`POSTGRES_USER`, `POSTGRES_PASSWORD` und `POSTGRES_DB` ausgewertet (zur
Kompatibilität wird auch `POSTGRES_PASS` noch unterstützt).

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

Mehrere Subdomains lassen sich als eigene Mandanten betreiben. Ein neuer
Mandant wird mit `scripts/create_tenant.sh` angelegt:

```bash
scripts/create_tenant.sh foo
```

Das Skript sendet einen API-Aufruf an `/tenants`, legt die Datei
`vhost.d/foo.$DOMAIN` an und lädt anschließend den Proxy neu. Zum Entfernen
eines Mandanten steht `scripts/delete_tenant.sh` bereit:

```bash
scripts/delete_tenant.sh foo
```

Beide Skripte lesen die Variable `DOMAIN` aus `.env` und nutzen sie
für die vhost-Konfiguration. Befindet sich im Projektverzeichnis eine `.env`,
lädt `scripts/onboard_tenant.sh` sie automatisch und übernimmt die dort
definierten Variablen.

Das Proxy-Setup legt zudem standardmäßig ein Docker-Netzwerk namens
`webproxy` an. Nach dem Aufruf von `scripts/create_tenant.sh` oder einem
`POST /tenants` muss das Onboarding angestoßen werden, damit der neue
Mandant diesem Netzwerk beitritt und ein Let's-Encrypt-Zertifikat erhält.
Starte dazu den Web-Assistenten unter `/onboarding` oder führe
`scripts/onboard_tenant.sh <subdomain>` aus. Stelle sicher, dass dein
Haupt-Stack dieses Netzwerk erstellt oder verwaltet. Den Namen kannst du
über die Umgebungsvariable `NETWORK` im Skript anpassen.

Das Skript `scripts/onboard_tenant.sh` steht weiterhin zur Verfügung, um
einen Container manuell zu starten oder neu aufzusetzen. Es schreibt unter
`tenants/<slug>/` eine eigene `docker-compose.yml`, legt dort ein
persistentes `data/`-Verzeichnis an und bindet es im Container unter
`/var/www/data` ein. So bleiben hochgeladene Logos oder Fotos auch bei
Upgrades erhalten. Zusätzlich fordert das Skript das SSL-Zertifikat an.
Welches Docker-Image dabei verwendet wird, lässt sich über die Variable `APP_IMAGE` in der `.env` steuern.
Dieses Tag sollte dem lokal gebauten Slim-Image entsprechen (`docker build -t <tag> .`),
da das Onboarding-Skript diese Variable nutzt.

Schlägt das Onboarding fehl, hilft ein Blick in das Log:

```bash
tail -n 50 logs/onboarding.log
```

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
Das Skript stoppt den Container und löscht das Verzeichnis `tenants/<slug>/`:

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

Für den eigentlichen Quiz-Container lässt sich der Hostname über die
Umgebungsvariable `SLIM_VIRTUAL_HOST` steuern. Starte mehrere Instanzen
mit unterschiedlichen Werten, werden die Subdomains automatisch als
eigene Mandanten behandelt. Der eingesetzte Proxy erzeugt dank
`nginxproxy/acme-companion` für jede konfigurierte Domain ein
Let's-Encrypt-Zertifikat, sobald der Container gestartet wird.

Weitere nützliche Variablen in `.env` sind:

- `LETSENCRYPT_EMAIL` – Kontaktadresse für die automatische Zertifikatserstellung.
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

Die Übersichtsseiten erzeugen ihre QR-Codes jetzt lokal mit der Bibliothek *Endroid\\QrCode*. Katalog-Links erscheinen rot, Team-Links blau.

### Rich-Text-Editor

Zum Bearbeiten der statischen Seiten wird **Trumbowyg** eingesetzt. Die Bibliothek
kann per CDN eingebunden werden:

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trumbowyg@2/dist/ui/trumbowyg.min.css">
<script src="https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/trumbowyg@2/dist/trumbowyg.min.js"></script>
```

Die Initialisierung erfolgt im Skript `public/js/trumbowyg-pages.js`. Dort sind
auch eigene UIkit-Vorlagen wie ein Hero-Block oder eine Card hinterlegt. Beim
Speichern wird das generierte HTML in das Feld `content` übertragen. Eine
Vorschau lässt sich direkt im Modal aufrufen.

## Tests

Alle Tests lassen sich komfortabel über Composer starten:
```bash
composer test
```

Dabei führt der Befehl zunächst PHPUnit aus und ruft anschließend die beiden
Python-Skripte zur Prüfung der HTML- bzw. JSON-Dateien auf.

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
  "ogImagePath": "/img/social-preview.jpg",
  "pageTitle": "Modernes Quiz mit UIkit",
  "backgroundColor": "#ffffff",
  "buttonColor": "#1e87f0",
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
  "postgres_pass": "***"
}
```

Der Parameter `displayErrorDetails` kann auch über die Umgebungsvariable
`DISPLAY_ERROR_DETAILS` gesetzt werden.

Optional kann `baseUrl` gesetzt werden, um in QR-Codes vollständige Links mit Domain zu erzeugen. `QRRemember` speichert gescannte Namen und erspart das erneute Einscannen. Der Parameter `competitionMode` blendet im Quiz alle Neustart-Schaltflächen aus, verhindert Wiederholungen bereits abgeschlossener Kataloge und unterbindet die Anzeige der Katalogübersicht. Ein Fragenkatalog kann dann nur über einen direkten QR-Code-Link gestartet werden. Im Wettkampfmodus führt ein Aufruf der Hauptseite ohne gültigen Katalog-Parameter automatisch zur Hilfe-Seite. Über `teamResults` lässt sich steuern, ob Teams nach Abschluss aller Kataloge ihre eigene Ergebnisübersicht angezeigt bekommen. `photoUpload` blendet die Buttons zum Hochladen von Beweisfotos ein oder aus. `puzzleWordEnabled` schaltet das Rätselwort-Spiel frei und `puzzleFeedback` definiert den Text, der nach korrekter Eingabe angezeigt wird. `inviteText` enthält ein optionales Anschreiben für teilnehmende Teams.

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
Für Funktionen wie das Zurücksetzen von Passwörtern nutzt die Anwendung SMTP. Die Verbindung wird über folgende Umgebungsvariablen gesteuert:

- `SMTP_HOST` – Hostname des Servers
- `SMTP_USER` – Benutzername
- `SMTP_PASS` – Passwort
- `SMTP_PORT` – Port (z. B. 587)
- `SMTP_ENCRYPTION` – Verschlüsselung (`none`, `tls` oder `ssl`)
- `SMTP_FROM` – Absenderadresse
- `SMTP_FROM_NAME` – Name des Absenders

Diese Variablen können in `.env` gesetzt werden.

### Passwort zurücksetzen
Die API unterstützt ein zweistufiges Verfahren zum Zurücksetzen vergessener Passwörter:

1. `POST /password/reset/request` nimmt einen Benutzernamen oder eine E‑Mail-Adresse entgegen und verschickt einen Link mit einem Reset-Token.
2. `POST /password/reset/confirm` setzt nach Validierung des Tokens das neue Passwort.

Für den Versand der E-Mails müssen `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`, `SMTP_PORT` und `SMTP_ENCRYPTION` konfiguriert sein. Über `SMTP_FROM` und `SMTP_FROM_NAME` lässt sich der Absender zentral festlegen. Das Token ist aus Sicherheitsgründen nur eine Stunde gültig.

### Administrationsoberfläche
Unter `/admin` stehen folgende Tabs zur Verfügung:
1. **Veranstaltung konfigurieren** – Einstellungen wie Logo, Farben und Texte.
2. **Kataloge** – Fragenkataloge erstellen und verwalten.
3. **Fragen anpassen** – Fragen eines Katalogs hinzufügen, bearbeiten oder löschen.
4. **Teams/Personen** – Teilnehmerlisten pflegen, optional als Login-Beschränkung.
5. **Ergebnisse** – Spielstände einsehen und herunterladen.
6. **Statistik** – Einzelne Antworten analysieren und nach Teams filtern.
7. **Administration** – Benutzer und Backups verwalten.

### Fragenkataloge
`data/kataloge/catalogs.json` listet verfügbare Kataloge mit `slug`, Name und optionaler QR-Code-Adresse. Die Reihenfolge wird durch das Feld `sort_order` bestimmt. Jede Frage speichert die zugehörige `catalog_uid`. Jeder Eintrag kann zusätzlich ein Feld `raetsel_buchstabe` enthalten, das den Buchstaben für das Rätselwort festlegt. Die API bietet hierzu folgende Endpunkte:
- `GET /kataloge/{file}` liefert den JSON-Katalog oder leitet im Browser auf `/?katalog=slug` um.
- `PUT /kataloge/{file}` legt eine neue Datei an.
- `POST /kataloge/{file}` überschreibt einen Katalog mit gesendeten Daten.
- `DELETE /kataloge/{file}` entfernt die Datei.
- `DELETE /kataloge/{file}/{index}` löscht eine Frage anhand des Index.

### Teams und QR-Code-Login
In `data/teams.json` können Teilnehmernamen gespeichert werden. `GET /teams.json` ruft die Liste ab, `POST /teams.json` speichert sie. Ein optionales Häkchen „Nur Teams/Personen aus der Liste dürfen teilnehmen“ aktiviert eine Zugangsbeschränkung via QR-Code. QR-Codes lassen sich direkt in der Oberfläche generieren.

### Spielerprofil
Auf `/profile` legen Teilnehmende ihren Anzeigenamen fest. Ist die Option für Zufallsnamen aktiv und noch kein Name hinterlegt, leitet die Startseite automatisch auf diese Profilseite weiter. Nach dem Speichern werden Name und eine zufällige Kennung lokal gespeichert und über `/api/players` an den Server gemeldet.

### Ergebnisse
Alle Resultate werden in der Datenbank abgelegt. Die API bietet folgende Endpunkte:
- `GET /results.json` – liefert alle gespeicherten Ergebnisse.
- `POST /results` – fügt ein neues Ergebnis hinzu.
- `DELETE /results` – löscht alle Einträge.
- `GET /results/download` – erzeugt eine CSV-Datei mit allen Resultaten.
- `GET /question-results.json` – listet falsch beantwortete Fragen.

Die Ergebnisübersicht zeigt drei Ranglisten. Der Titel „Katalogmeister" basiert
auf dem Zeitpunkt, an dem ein Team seinen letzten noch offenen Fragenkatalog
abgeschlossen hat. Wer hier die früheste Zeit erreicht, führt die Liste an.
Um überhaupt in dieser Liste zu erscheinen, müssen alle Kataloge aus
`data/kataloge/catalogs.json` vollständig gelöst sein.

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
Das aktuelle Logo wird unter `/logo.png` oder `/logo.webp` bereitgestellt. Über einen POST auf diese URLs lässt sich eine neue PNG- oder WebP-Datei hochladen. Nach dem Upload wird der Pfad automatisch in der Datenbank gespeichert. Die Datei landet im Verzeichnis `data/`, damit auch PDFs das Logo einbinden können.

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

