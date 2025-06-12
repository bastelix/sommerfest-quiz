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
- **Stark anpassbar**: Farben, Logo und Texte lassen sich über `config/config.json` anpassen.
- **Vollständig im Browser**: Das Quiz benötigt keine Serverpersistenz und funktioniert auch offline, sobald die Seite geladen ist.

## Projektstruktur

- **public/** – Einstiegspunkt `index.php`, alle UIkit-Assets sowie JavaScript-Dateien
- **templates/** – Twig-Vorlagen für Startseite und FAQ
- **kataloge/** – Fragenkataloge im JSON-Format
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

## Anpassung

Alle wichtigen Einstellungen finden Sie in `config/config.json`. Ändern Sie hier Logo, Farben oder die Verwendung des QR-Code-Logins. Die Fragen selbst liegen in `kataloge/*.json` und können mit jedem Texteditor angepasst werden.

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

## Lizenz

Dieses Projekt steht unter der MIT-Lizenz. Weitere Informationen finden Sie in der Datei `LICENSE`.

