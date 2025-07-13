# Sommerfest-Quiz
[![Deploy](https://github.com/bastelix/sommerfest-quiz/actions/workflows/deploy.yml/badge.svg)](https://github.com/bastelix/sommerfest-quiz/actions/workflows/deploy.yml)
[![HTML Validity Test](https://github.com/bastelix/sommerfest-quiz/actions/workflows/html-validity.yml/badge.svg)](https://github.com/bastelix/sommerfest-quiz/actions/workflows/html-validity.yml)

**Dokumentation:** Die ausführliche Anleitung findest du auf GitHub Pages: <https://bastelix.github.io/sommerfest-quiz/>

Das **Sommerfest-Quiz** ist eine sofort einsetzbare Web-App, mit der Sie Besucherinnen und Besucher spielerisch an Events beteiligen. Dank Slim Framework und UIkit3 funktioniert alles ohne komplizierte Server-Setups direkt im Browser.

## Disclaimer / Hinweis

Die Sommerfeier 2025 Quiz-App ist das Ergebnis einer spannenden Zusammenarbeit zwischen menschlicher Erfahrung und künstlicher Intelligenz. Während Ideen, Organisation und jede Menge Praxiswissen von Menschen stammen, wurden alle Codezeilen experimentell komplett von OpenAI Codex geschrieben. Für die kreativen Konzepte und Inhalte kam ChatGPT 4.1 zum Einsatz, bei der Fehlersuche half GitHub Copilot und das Logo wurde von der KI Sora entworfen.

Diese App wurde im Rahmen einer Machbarkeitsstudie entwickelt, um das Potenzial moderner Codeassistenten in der Praxis zu erproben.
Im Mittelpunkt stand die Zugänglichkeit für alle Nutzergruppen – daher ist die Anwendung barrierefrei gestaltet und eignet sich auch für Menschen mit Einschränkungen. Datenschutz und Sicherheit werden konsequent beachtet, sodass alle Daten geschützt sind.
Die App zeichnet sich durch eine hohe Performance und Stabilität auch bei vielen gleichzeitigen Teilnehmenden aus. Das Bedienkonzept ist selbsterklärend, wodurch eine schnelle und intuitive Nutzung auf allen Endgeräten – ob Smartphone, Tablet oder Desktop – gewährleistet wird.
Zudem wurde auf eine ressourcenschonende Arbeitsweise und eine unkomplizierte Anbindung an andere Systeme Wert gelegt.

Mit dieser App zeigen wir, was heute schon möglich ist, wenn Menschen und verschiedene KI-Tools wie ChatGPT, Codex, Copilot und Sora gemeinsam an neuen digitalen Ideen tüfteln.

## Überblick

- **Flexibel einsetzbar**: Fragenkataloge im JSON-Format lassen sich bequem austauschen oder erweitern.
- **Fünf Fragetypen**: Sortieren, Zuordnen, Multiple Choice, Swipe-Karten und Foto mit Texteingabe bieten Abwechslung für jede Zielgruppe.
- **QR-Code-Login & Dunkelmodus**: Optionaler QR-Code-Login für schnelles Anmelden und ein zuschaltbares dunkles Design steigern den Komfort.
- **Persistente Speicherung**: Konfigurationen, Kataloge und Ergebnisse liegen in einer PostgreSQL-Datenbank.

## Highlights

- **Einfache Installation**: Nur Composer-Abhängigkeiten installieren und einen PHP-Server starten.
- **Intuitives UI**: Komplett auf UIkit3 basierendes Frontend mit flüssigen Animationen und responsive Design.
- **Stark anpassbar**: Farben, Logo und Texte lassen sich über `data/config.json` anpassen.
- **Backup per JSON**: Alle Daten lassen sich exportieren und wieder importieren.
- **Automatische Bildkompression**: Hochgeladene Fotos werden nun standardmäßig verkleinert und komprimiert.
- **Rätselwort und Foto-Einwilligung**: Optionales Puzzlewort-Spiel mit DSGVO-konformen Foto-Uploads.

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
- **src/** – PHP-Code mit Routen, Controllern und Services
- **docs/** – Zusätzliche Dokumentation, z.B. [Richtlinien zur Worttrennung](docs/frontend-word-break.md)

## Schnellstart

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
   # Anschließend Migrationen anwenden
   for f in migrations/*.sql; do psql -h localhost -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f "$f"; done
   ```

   Alternativ lassen sich Schema- und Datenimport direkt im Docker-Container ausführen:
   ```bash
   docker compose exec slim bash -c \
     'psql -h postgres -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f docs/schema.sql && \
      php scripts/import_to_pgsql.php'
   ```
   Für noch bequemere Einrichtung steht das Skript `scripts/run_psql_in_docker.sh`
   bereit. Es ruft denselben Befehl auf und übernimmt die Variablen aus `.env`:
   ```bash
   ./scripts/run_psql_in_docker.sh
   ```

4. Anschließend einmalig die vorhandenen JSON-Daten importieren:
   ```bash
  php scripts/import_to_pgsql.php
  ```
  Um Beispielbenutzer mit Rollen anzulegen, kann optional
  ```bash
  php scripts/seed_roles.php
  ```
  ausgeführt werden. Das Skript legt einen Benutzer pro Rolle an. Benutzername
  und Passwort entsprechen dabei jeweils dem Rollennamen:

  - `admin` – Administrator
  - `catalog-editor` – Fragenkataloge bearbeiten
  - `event-manager` – Veranstaltungen verwalten
  - `analyst` – Ergebnisse analysieren
  - `team-manager` – Teams verwalten

   Wird `POSTGRES_DSN` gesetzt und enthält das Verzeichnis `data/` bereits JSON-Dateien,
   legt das Entrypoint-Skript des Containers die Tabellen automatisch an und importiert
   die Daten beim Start.

## Docker Compose

Das mitgelieferte `docker-compose.yml` startet das Quiz samt Reverse Proxy.
Die Dateien im Ordner `data/` werden dabei in einem benannten Volume
`quizdata` gespeichert. So bleiben eingetragene Teams und Ergebnisse auch nach
`docker compose down` erhalten. Hochgeladene Beweisfotos landen im Verzeichnis
`data/photos` und werden dort immer als JPEG abgelegt. Durch das Volume bleiben
die Bilder ebenfalls dauerhaft erhalten. Die
ACME-Konfiguration des Let's-Encrypt-Begleiters landet im Ordner `acme/` und
wird dadurch ebenfalls persistiert. Zusätzlich läuft ein Adminer-Container,
der die PostgreSQL-Datenbank über die Subdomain `https://adminer.${DOMAIN}` bereitstellt. Er
nutzt intern den Hostnamen `postgres` und erfordert keine weiteren Einstellungen.
Um größere Uploads zu erlauben, kann die maximale
Request-Größe des Reverse Proxys über die Umgebungsvariable
`CLIENT_MAX_BODY_SIZE` angepasst werden. In der mitgelieferten
`docker-compose.yml` ist dieser Wert auf `10m` gesetzt.
Beim Einsatz von **jwilder/nginx-proxy** greift der Wert jedoch nur,
solange keine eigene Vhost-Konfiguration für die Domain existiert.
Soll ein höheres Limit dauerhaft gelten, empfiehlt es sich daher,
eine Datei im Verzeichnis `vhost.d/` anzulegen. Der Dateiname muss
exakt der bei `VIRTUAL_HOST` hinterlegten Domain entsprechen und kann
zum Beispiel folgenden Inhalt haben. Für die mitgelieferte
Beispiel-Domain `example.com` ist eine solche Datei bereits vorhanden:

```nginx
client_max_body_size 20m;
```

Nach dem Anlegen oder Anpassen der Datei sollte der Proxy neu gestartet
werden, damit die Einstellung aktiv wird (z.B. mit `docker compose
restart nginx-proxy`). Innerhalb des App-Containers können zudem die
Werte `upload_max_filesize` und `post_max_size` angepasst werden. Dafür
liegt im Verzeichnis `config/` bereits eine kleine `php.ini` bei, die in
`docker-compose.yml` eingebunden wird.
Die verwendete Domain wird aus der Datei `.env` gelesen (Variable `DOMAIN`).
Beim Start des Containers installiert ein Entrypoint-Skript automatisch alle
Composer-Abhängigkeiten, sofern das Verzeichnis `vendor/` noch nicht existiert.
Ein vorheriges `composer install` ist somit nicht mehr erforderlich,
solange die App innerhalb des Docker-Setups gestartet wird.

Ist in der `.env` die Variable `POSTGRES_DSN` gesetzt, legt das Entrypoint-
Skript beim Start automatisch die Datenbank anhand von `docs/schema.sql` an und
importiert die vorhandenen JSON-Daten. Neben `POSTGRES_DSN` werden dafür auch
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
ohne Docker betrieben wird. Ist `DOMAIN` dort gesetzt, wird für QR-Codes und
Exportlinks diese Adresse verwendet. Enthält die Variable kein Schema, wird
standardmäßig `https://` vorangestellt.

## Multi-Tenant Setup

Mehrere Subdomains lassen sich als eigene Mandanten betreiben. Ein neuer
Mandant wird mit `scripts/create_tenant.sh` angelegt:

```bash
scripts/create_tenant.sh foo
```

Das Skript sendet einen API-Aufruf an `/tenant`, legt die Datei
`vhost.d/foo.$DOMAIN` an und lädt anschließend den Proxy neu. Zum Entfernen
eines Mandanten steht `scripts/delete_tenant.sh` bereit:

```bash
scripts/delete_tenant.sh foo
```

Beide Skripte lesen die Variable `DOMAIN` aus `sample.env` und nutzen sie
für die vhost-Konfiguration.

Für den eigentlichen Quiz-Container lässt sich der Hostname über die
Umgebungsvariable `SLIM_VIRTUAL_HOST` steuern. Starte mehrere Instanzen
mit unterschiedlichen Werten, werden die Subdomains automatisch als
eigene Mandanten behandelt. Der eingesetzte Proxy erzeugt dank
`nginxproxy/acme-companion` für jede konfigurierte Domain ein
Let's-Encrypt-Zertifikat, sobald der Container gestartet wird.

## Anpassung

Alle wichtigen Einstellungen finden Sie in `data/config.json`. Ändern Sie hier Logo, Farben oder die Verwendung des QR-Code-Logins. Die Fragen selbst liegen in `data/kataloge/*.json` und können mit jedem Texteditor angepasst werden. Jede Katalogdefinition besitzt weiterhin ein `slug` für die URL. Fragen verknüpfen den Katalog nun über `catalog_uid`. Das bisherige `id` dient ausschließlich der Sortierung und wird automatisch vergeben.

QR-Codes können pro Eintrag über `qr_image` oder `qrcode_url` hinterlegt werden. Neben Data-URIs und lokalen Pfaden werden dabei nun auch HTTP- oder HTTPS-URLs unterstützt.

Die Übersichtsseiten erzeugen ihre QR-Codes jetzt lokal mit der Bibliothek *Endroid\\QrCode*. Katalog-Links erscheinen rot, Team-Links blau.

## Tests

PHP-Tests werden mit PHPUnit ausgeführt:
```bash
vendor/bin/phpunit
```

Zusätzlich prüfen Python-Skripte die Gültigkeit der HTML- und JSON-Dateien:
```bash
python3 tests/test_html_validity.py
python3 tests/test_json_validity.py
```

## Teams/Personen

- Neuer Tab "Teams/Personen" in der Administration.
- Liste mit Name und QR-Code, editierbar.
- QR-Code-Login auf bekannte Teams/Personen beschränkbar.
- Aktivierung/Deaktivierung der Beschränkung per Schalter.
- Zufallsnamen werden bei aktiver Beschränkung unterbunden.
- Die Uploadseite für Beweisfotos bietet jetzt ein Eingabefeld mit Vorschlagsliste für die Teamwahl.

## Datenschutz

Alle Ergebnisse werden in der Datenbank gespeichert. Über die Administrationsoberfläche lassen sie sich als CSV-Datei herunterladen. Jede Zeile enthält ein Pseudonym, den verwendeten Katalog, die Versuchnummer, die Punktzahl, den Zeitpunkt sowie optional die Rätselwort-Bestzeit und den Pfad eines Beweisfotos. Die exportierte Datei ist UTF‑8-kodiert und enthält eine BOM, damit Excel Sonderzeichen korrekt erkennt.

## Barrierefreiheit

Das Frontend bringt mehrere Funktionen mit, die die Nutzung erleichtern:

- Ausführliche ARIA-Beschriftungen auf Bedienelementen und Formularfeldern.
- Tastatursteuerung für Sortier- und Zuordnungsfragen samt versteckten Hinweisen.
- Fortschrittsbalken mit `aria-valuenow` und Live-Ansage der aktuellen Frage.
- Umschaltbarer Dunkel- und Hochkontrastmodus.


## Anwenderhandbuch

### Einleitung
Das Projekt *Sommerfest-Quiz* ist eine Web-Applikation zur Erstellung und Verwaltung von Quizfragen. Die Anwendung basiert auf dem Slim Framework und verwendet UIkit3 für das Frontend. Konfigurationen, Kataloge, Teams und Ergebnisse liegen in einer PostgreSQL-Datenbank und lassen sich über die Oberfläche als JSON-Dateien exportieren oder importieren.

### Installation und Start
1. Abhängigkeiten per Composer installieren:
   ```bash
   composer install
   ```
   Beim ersten Aufruf wird eine `composer.lock` erzeugt und alle benötigten Bibliotheken geladen.
2. Die Beispieldatei `sample.env` in `.env` kopieren und bei Bedarf anpassen:
   ```bash
   cp sample.env .env
   ```
3. Lokalen Server starten:
   ```bash
   php -S localhost:8080 -t public public/router.php
   ```
   Anschließend ist das Quiz unter `http://localhost:8080` erreichbar.

4. Optional: Tabellen in einer PostgreSQL-Datenbank anlegen und JSON-Daten importieren (siehe Abschnitt "Schnellstart" für ausführliche Befehle).

Für Docker-Betrieb steht ein `docker-compose.yml` bereit. Sämtliche Daten im Ordner `data/` werden in einem Volume namens `quizdata` gesichert, damit Ergebnisse erhalten bleiben.

### Konfigurationsdatei
Alle wesentlichen Einstellungen stehen in `data/config.json` und werden beim ersten Import in die Datenbank übernommen. Über die Administration können sie später angepasst werden:

```json
{
  "displayErrorDetails": true,
  "QRUser": true,
  "logoPath": "/logo.png",
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

Optional kann `baseUrl` gesetzt werden, um in QR-Codes vollständige Links mit Domain zu erzeugen. Wird dieser Wert nicht angegeben, ermittelt die Anwendung Schema und Host automatisch aus der aktuellen Anfrage. Der Parameter `competitionMode` blendet im Quiz alle Neustart-Schaltflächen aus, verhindert Wiederholungen bereits abgeschlossener Kataloge und unterbindet die Anzeige der Katalogübersicht. Ein Fragenkatalog kann dann nur über einen direkten QR-Code-Link gestartet werden. Im Wettkampfmodus führt ein Aufruf der Hauptseite ohne gültigen Katalog-Parameter automatisch zur Hilfe-Seite. Über `teamResults` lässt sich steuern, ob Teams nach Abschluss aller Kataloge ihre eigene Ergebnisübersicht angezeigt bekommen. `photoUpload` blendet die Buttons zum Hochladen von Beweisfotos ein oder aus. `puzzleWordEnabled` schaltet das Rätselwort-Spiel frei und `puzzleFeedback` definiert den Text, der nach korrekter Eingabe angezeigt wird. `inviteText` enthält ein optionales Anschreiben für teilnehmende Teams.

`ConfigService` liest und speichert diese Datei. Ein GET auf `/config.json` liefert den aktuellen Inhalt, ein POST auf dieselbe URL speichert geänderte Werte.

### Authentifizierung
Der Zugang zum Administrationsbereich erfolgt über `/login`. Benutzer und Rollen werden in der Tabelle `users` verwaltet. Nach erfolgreichem POST mit gültigen Zugangsdaten speichert das System die Benutzerinformationen inklusive Rolle in der Session und leitet Administratoren zur Route `/admin` weiter. Die Middleware `RoleAuthMiddleware` prüft die gespeicherte Rolle und leitet bei fehlenden Berechtigungen zum Login um.

### Administrationsoberfläche
Unter `/admin` stehen folgende Tabs zur Verfügung:
1. **Veranstaltung konfigurieren** – Einstellungen wie Logo, Farben und Texte.
2. **Kataloge** – Fragenkataloge erstellen und verwalten.
3. **Fragen anpassen** – Fragen eines Katalogs hinzufügen, bearbeiten oder löschen.
4. **Teams/Personen** – Teilnehmerlisten pflegen, optional als Login-Beschränkung.
5. **Ergebnisse** – Spielstände einsehen und herunterladen.
6. **Statistik** – Einzelne Antworten analysieren und nach Teams filtern.
7. **Administration** – Passwort ändern und Backups verwalten.

### Fragenkataloge
`data/kataloge/catalogs.json` listet verfügbare Kataloge mit `slug`, Name und optionaler QR-Code-Adresse. Die Reihenfolge wird durch das Feld `sort_order` bestimmt. Jede Frage speichert die zugehörige `catalog_uid`. Jeder Eintrag kann zusätzlich ein Feld `raetsel_buchstabe` enthalten, das den Buchstaben für das Rätselwort festlegt. Die API bietet hierzu folgende Endpunkte:
- `GET /kataloge/{file}` liefert den JSON-Katalog oder leitet im Browser auf `/?katalog=slug` um.
- `PUT /kataloge/{file}` legt eine neue Datei an.
- `POST /kataloge/{file}` überschreibt einen Katalog mit gesendeten Daten.
- `DELETE /kataloge/{file}` entfernt die Datei.
- `DELETE /kataloge/{file}/{index}` löscht eine Frage anhand des Index.

### Teams und QR-Code-Login
In `data/teams.json` können Teilnehmernamen gespeichert werden. `GET /teams.json` ruft die Liste ab, `POST /teams.json` speichert sie. Ein optionales Häkchen „Nur Teams/Personen aus der Liste dürfen teilnehmen“ aktiviert eine Zugangsbeschränkung via QR-Code. QR-Codes lassen sich direkt in der Oberfläche generieren.

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
Ein POST auf `/password` speichert ein neues Admin-Passwort in `config.json`.
Backups lassen sich über `/export` erstellen und per `/import` wiederherstellen.
`GET /backups` listet alle Sicherungen, einzelne Ordner können über
`/backups/{name}/download` heruntergeladen oder via `DELETE /backups/{name}`
entfernt werden.

### Logo hochladen
Das aktuelle Logo wird unter `/logo.png` oder `/logo.webp` bereitgestellt. Über einen POST auf diese URLs lässt sich eine neue PNG- oder WebP-Datei hochladen. Nach dem Upload wird der Pfad automatisch in `config.json` gespeichert. Die Datei landet im Verzeichnis `data/`, damit auch PDFs das Logo einbinden können.

### Sicherheit und Haftung
Die Software wird unter der MIT-Lizenz bereitgestellt und erfolgt ohne Gewähr. Die Urheber haften nicht für Schäden, die aus der Nutzung entstehen. Die integrierten Maßnahmen zur Barrierefreiheit verbessern die Zugänglichkeit, sie ersetzen jedoch keine individuelle Prüfung.

### Fazit
Die API ermöglicht die komplette Verwaltung eines Quizsystems:
- Konfiguration, Fragenkataloge, Teams, Ergebnisse und Fotoeinwilligungen werden in einer PostgreSQL-Datenbank verwaltet.
- Über das Admin-Frontend sind diese Bereiche komfortabel zugänglich.
- Ergebnisse lassen sich als Excel/CSV exportieren.
Dieses Handbuch fasst die Nutzung von der Grundkonfiguration über das Anlegen der Fragen und Teams bis zum Abruf der Resultate zusammen.
Durch den Einsatz von Slim Framework und standardisierten Endpunkten ist die Anwendung sowohl lokal als auch im Netzwerk schnell einsetzbar.


## Lizenz

Dieses Projekt steht unter der MIT-Lizenz. Weitere Informationen finden Sie in der Datei `LICENSE`.

