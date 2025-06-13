# Sommerfest-Quiz

Das **Sommerfest-Quiz** ist eine sofort einsetzbare Web-App, mit der Sie Besucherinnen und Besucher spielerisch an Events beteiligen. Dank Slim Framework und UIkit3 funktioniert alles ohne komplizierte Server-Setups direkt im Browser.

## Überblick

- **Flexibel einsetzbar**: Fragenkataloge im JSON-Format lassen sich bequem austauschen oder erweitern.
- **Drei Fragetypen**: Sortieren, Zuordnen und Multiple Choice bieten Abwechslung für jede Zielgruppe.
- **QR-Code-Login & Dunkelmodus**: Optionaler QR-Code-Login für schnelles Anmelden und ein zuschaltbares dunkles Design steigern den Komfort.
- **Datensparsam**: Alle Ergebnisse verbleiben ausschließlich im Browser und können als Statistikdatei exportiert werden.

## Highlights

- **Einfache Installation**: Nur Composer-Abhängigkeiten installieren und einen PHP-Server starten.
- **Intuitives UI**: Komplett auf UIkit3 basierendes Frontend mit flüssigen Animationen und responsive Design.
- **Stark anpassbar**: Farben, Logo und Texte lassen sich über `data/config.json` anpassen.
- **Vollständig im Browser**: Das Quiz benötigt keine Serverpersistenz und funktioniert auch offline, sobald die Seite geladen ist.

## Projektstruktur

- **public/** – Einstiegspunkt `index.php`, alle UIkit-Assets sowie JavaScript-Dateien
- **templates/** – Twig-Vorlagen für Startseite und FAQ
- **data/kataloge/** – Fragenkataloge im JSON-Format
- **src/** – PHP-Code mit Routen, Controllern und Services

## Schnellstart

1. Abhängigkeiten installieren:
   ```bash
   composer install
   ```
   Beim ersten Aufruf legt Composer eine `composer.lock` an und lädt alle
   benötigten Pakete herunter. Die Datei wird bewusst nicht versioniert,
   sodass stets die neuesten kompatiblen Abhängigkeiten installiert werden.
   Das Docker-Setup installiert dabei automatisch die PHP-Erweiterung *gd*,
   welche für die Bibliothek `setasign/fpdf` benötigt wird.
2. Server starten (z.B. für lokale Tests):
   ```bash
   php -S localhost:8080 -t public public/router.php
   ```
  Anschließend ist das Quiz unter <http://localhost:8080> aufrufbar.

## Docker Compose

Das mitgelieferte `docker-compose.yml` startet das Quiz samt Reverse Proxy.
Die Dateien im Ordner `data/` werden dabei in einem benannten Volume
`quizdata` gespeichert. So bleiben eingetragene Teams und Ergebnisse auch nach
`docker-compose down` erhalten. Die ACME-Konfiguration des Let's-Encrypt-
Begleiters landet im Ordner `acme/` und wird dadurch ebenfalls 
persistiert.

## Anpassung

Alle wichtigen Einstellungen finden Sie in `data/config.json`. Ändern Sie hier Logo, Farben oder die Verwendung des QR-Code-Logins. Die Fragen selbst liegen in `data/kataloge/*.json` und können mit jedem Texteditor angepasst werden. Jede Katalogdefinition enthält ein `id`, das dem Dateinamen ohne Endung entspricht. Bei neuen Katalogen generiert die Verwaltung dieses `id` nun automatisch aus dem eingegebenen Namen.

QR-Codes können pro Eintrag über `qr_image` oder `qrcode_url` hinterlegt werden. Neben Data-URIs und lokalen Pfaden werden dabei nun auch HTTP- oder HTTPS-URLs unterstützt.
Der PDF-Export platziert nun außerdem einen QR-Code für den Schnellzugriff auf das Quiz am Dokumentanfang.

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

## Datenschutz

Ergebnisse können nun serverseitig in einer `results.xlsx` abgelegt werden. Die Ablage erfolgt anonymisiert und entspricht den Vorgaben der DSGVO. Jede Zeile enthält ein Pseudonym, den verwendeten Katalog, die Versuchnummer und die Punktzahl. Alternativ lassen sich die Daten weiterhin lokal als `statistical.log` exportieren.

## Barrierefreiheit

Bei einer Projektprüfung wurden die Vorlagen auf bessere Zugänglichkeit hin optimiert. Unter anderem wurden aussagekräftigere ARIA-Labels vergeben, damit Screenreader alle Bedienelemente korrekt ankündigen.

## Anwenderhandbuch

### Einleitung
Das Projekt *Sommerfest-Quiz* ist eine Web-Applikation zur Erstellung und Verwaltung von Quizfragen. Die Anwendung basiert auf dem Slim Framework und verwendet UIkit3 für das Frontend. Konfigurationen und Fragen werden in JSON-Dateien gespeichert; Ergebnisse können sowohl im Browser als auch serverseitig abgelegt werden.

### Installation und Start
1. Abhängigkeiten per Composer installieren:
   ```bash
   composer install
   ```
   Beim ersten Aufruf wird eine `composer.lock` erzeugt und alle benötigten Bibliotheken geladen.
2. Lokalen Server starten:
   ```bash
   php -S localhost:8080 -t public public/router.php
   ```
   Anschließend ist das Quiz unter `http://localhost:8080` erreichbar.

Für Docker-Betrieb steht ein `docker-compose.yml` bereit. Sämtliche Daten im Ordner `data/` werden in einem Volume namens `quizdata` gesichert, damit Ergebnisse erhalten bleiben.

### Konfigurationsdatei
Alle wesentlichen Einstellungen finden sich in `data/config.json`. Hier lassen sich Logo, Titel, Hintergrundfarbe und weitere Optionen definieren:

```json
{
  "displayErrorDetails": true,
  "QRUser": true,
  "logoPath": "",
  "pageTitle": "Modernes Quiz mit UIkit",
  ...
}
```

`ConfigService` liest und speichert diese Datei. Ein GET auf `/config.json` liefert den aktuellen Inhalt, ein POST auf dieselbe URL speichert geänderte Werte.

### Authentifizierung
Der Zugang zum Administrationsbereich erfolgt über `/login`. Nach erfolgreichem POST mit gültigen Daten wird eine Session gesetzt und der Browser zur Route `/admin` umgeleitet. Die Middleware `AdminAuthMiddleware` schützt alle Admin-Routen und leitet bei fehlender Session zum Login weiter.

### Administrationsoberfläche
Unter `/admin` stehen folgende Tabs zur Verfügung:
1. **Veranstaltung konfigurieren** – Einstellungen wie Logo, Farben und Texte.
2. **Kataloge** – Fragenkataloge erstellen und verwalten.
3. **Teams/Personen** – Teilnehmerlisten pflegen, optional als Login-Beschränkung.
4. **Fragen anpassen** – Fragen eines Katalogs hinzufügen, bearbeiten oder löschen.
5. **Ergebnisse** – Spielstände einsehen und herunterladen.
6. **Passwort ändern** – Administrationspasswort setzen.

### Fragenkataloge
`data/kataloge/catalogs.json` listet verfügbare Kataloge mit `id`, Name und optionaler QR-Code-Adresse. Die API bietet hierzu folgende Endpunkte:
- `GET /kataloge/{file}` liefert den JSON-Katalog oder leitet im Browser auf `/?katalog=id` um.
- `PUT /kataloge/{file}` legt eine neue Datei an.
- `POST /kataloge/{file}` überschreibt einen Katalog mit gesendeten Daten.
- `DELETE /kataloge/{file}` entfernt die Datei.
- `DELETE /kataloge/{file}/{index}` löscht eine Frage anhand des Index.

### Teams und QR-Code-Login
In `data/teams.json` können Teilnehmernamen gespeichert werden. `GET /teams.json` ruft die Liste ab, `POST /teams.json` speichert sie. Ein optionales Häkchen „Nur Teams/Personen aus der Liste dürfen teilnehmen“ aktiviert eine Zugangsbeschränkung via QR-Code. QR-Codes lassen sich direkt in der Oberfläche generieren.

### Ergebnisse
Die Ergebnisse werden in `data/results.json` gespeichert. Wichtige Endpunkte:
- `GET /results.json` – liefert alle gespeicherten Ergebnisse.
- `POST /results` – fügt ein neues Ergebnis hinzu.
- `DELETE /results` – löscht alle Einträge.
- `GET /results/download` – erzeugt eine XLSX-Datei (oder CSV) mit allen Resultaten.

### PDF-Export
`GET /export.pdf` erzeugt eine PDF-Datei mit QR-Codes zu jedem Katalog und optional einer Teamliste. Der `PdfExportService` baut diese Datei auf und unterstützt auch externe QR-Code-Grafiken.

### Passwort ändern
Ein POST auf `/password` speichert ein neues Admin-Passwort in `config.json`.

### Sicherheit und Haftung
Die Software wird unter der MIT-Lizenz bereitgestellt und erfolgt ohne Gewähr. Die Urheber haften nicht für Schäden, die aus der Nutzung entstehen.

### Fazit
Die API ermöglicht die komplette Verwaltung eines Quizsystems:
- Konfiguration, Fragenkataloge, Teams und Ergebnisse werden als JSON-Dateien gepflegt.
- Über das Admin-Frontend sind diese Bereiche komfortabel zugänglich.
- Ergebnisse lassen sich als Excel/CSV exportieren oder als PDF drucken.
Dieses Handbuch fasst die Nutzung von der Grundkonfiguration über das Anlegen der Fragen und Teams bis zum Abruf der Resultate zusammen.
Durch den Einsatz von Slim Framework und standardisierten Endpunkten ist die Anwendung sowohl lokal als auch im Netzwerk schnell einsetzbar.


## Lizenz

Dieses Projekt steht unter der MIT-Lizenz. Weitere Informationen finden Sie in der Datei `LICENSE`.

