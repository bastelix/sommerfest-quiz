# Sommerfest-Quiz

Das **Sommerfest-Quiz** ist eine sofort einsetzbare Web-App, mit der Sie Besucherinnen und Besucher spielerisch an Events beteiligen. Dank Slim Framework und UIkit3 funktioniert alles ohne komplizierte Server-Setups direkt im Browser.

## Überblick

- **Flexibel einsetzbar**: Fragenkataloge im JSON-Format lassen sich bequem austauschen oder erweitern.
- **Drei Fragetypen**: Sortieren, Zuordnen und Multiple Choice bieten Abwechslung für jede Zielgruppe.
- **Volle Kontrolle**: Ein Admin-Bereich erlaubt das Anpassen von Fragen und Farben sowie das Herunterladen der aktualisierten Konfiguration.
- **QR-Code-Login & Dunkelmodus**: Optionaler QR-Code-Login für schnelles Anmelden und ein zuschaltbares dunkles Design steigern den Komfort.
- **Datensparsam**: Alle Ergebnisse verbleiben ausschließlich im Browser und können als Statistikdatei exportiert werden.

## Highlights

- **Einfache Installation**: Nur Composer-Abhängigkeiten installieren und einen PHP-Server starten.
- **Intuitives UI**: Komplett auf UIkit3 basierendes Frontend mit flüssigen Animationen und responsive Design.
- **Stark anpassbar**: Farben, Logo und Texte können jederzeit über `public/js/config.js` oder die Admin-Oberfläche geändert werden.
- **Vollständig im Browser**: Das Quiz benötigt keine Serverpersistenz und funktioniert auch offline, sobald die Seite geladen ist.

## Projektstruktur

- **public/** – Einstiegspunkt `index.php`, alle UIkit-Assets sowie JavaScript-Dateien
- **templates/** – Twig-Vorlagen für Startseite, FAQ und Admin-Bereich
- **kataloge/** – Fragenkataloge im JSON-Format
- **src/** – PHP-Code mit Routen, Controllern und Services

## Schnellstart

1. Abhängigkeiten installieren:
   ```bash
   composer install
   ```
2. Server starten (z.B. für lokale Tests):
   ```bash
   php -S localhost:8080 -t public public/router.php
   ```
   Anschließend ist das Quiz unter <http://localhost:8080> aufrufbar.

## Anpassung

Alle wichtigen Einstellungen finden Sie in `public/js/config.js`. Ändern Sie hier Logo, Farben oder die Verwendung des QR-Code-Logins. Die Fragen selbst liegen in `kataloge/*.json` und können mit jedem Texteditor angepasst werden. Über die Admin-Seite lassen sich neue Versionen dieser Dateien herunterladen.

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

## Datenschutz

Das Quiz verzichtet vollständig auf serverseitige Speicherung. Alle Eingaben bleiben im Browser der Teilnehmenden und können bei Bedarf als `statistical.log` heruntergeladen werden.

## Barrierefreiheit

Bei einer Projektprüfung wurden die Vorlagen auf bessere Zugänglichkeit hin optimiert. Unter anderem wurden aussagekräftigere ARIA-Labels vergeben, damit Screenreader alle Bedienelemente korrekt ankündigen.

## Lizenz

Dieses Projekt steht unter der MIT-Lizenz. Weitere Informationen finden Sie in der Datei `LICENSE`.

