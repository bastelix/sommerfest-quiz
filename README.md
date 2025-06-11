# Sommerfest-Quiz

Ein webbasiertes Quiz auf Basis des Slim Frameworks und UIkit3. Fragenkataloge koennen per JSON-Dateien erweitert werden. Ergebnisse werden lokal im Browser gespeichert.

## Features

- Drei Fragetypen: Sortieren, Zuordnen und Multiple Choice
- Verwaltung von Fragenkatalogen ueber eine Admin-Oberflaeche
- Optionales QR-Code-Login
- Dunkelmodus
- Ergebnisse koennen als Statistik heruntergeladen werden

## Projektstruktur

- **public/** – Einstiegspunkt und JavaScript-Dateien
- **templates/** – Twig-Vorlagen fuer Startseite, FAQ und Admin-Oberflaeche
- **kataloge/** – JSON-Fragenkataloge
- **src/** – PHP-Code mit Routen und Controllern

## Anwendung starten

1. Abhaengigkeiten installieren

```bash
composer install
```

2. PHP-Server starten

```bash
php -S localhost:8080 -t public
```

Die Startseite ist anschliessend unter http://localhost:8080 erreichbar.

## Tests

Zum Ausfuehren der PHP-Tests wird PHPUnit verwendet:

```bash
vendor/bin/phpunit
```

Die HTML-Validierung laesst sich mit Python testen:

```bash
python3 tests/test_html_validity.py
```

## Composer-Abhaengigkeiten aktualisieren

Mit dem manuellen Workflow **Manual Composer Install** laesst sich `composer install` direkt auf GitHub ausfuehren. Hinterlege dazu ein Repository Secret **GH_PAT** mit einem Personal Access Token, das mindestens die Rechte `repo` und `workflow` besitzt. Der Workflow verwendet dieses Token beim Push und schreibt bei Bedarf eine aktualisierte `composer.lock` zurueck ins Repository. Gestartet wird er im Actions-Menue.

## Datenschutz

Alle Eingaben bleiben im Browser. Es findet keine serverseitige Speicherung statt.

