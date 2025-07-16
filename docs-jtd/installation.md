---
layout: default
title: Installation & Schnellstart
nav_order: 1
parent: Einstieg & Setup
toc: true
---

# Installation & Schnellstart

1. **Abhängigkeiten installieren**
   ```bash
   composer install
   ```
   Beim ersten Aufruf wird eine `composer.lock` angelegt und alle benötigten Pakete werden geladen. Wer die Anwendung ohne Docker betreibt, muss diesen Schritt manuell ausführen.

2. **Server starten** (lokal für Tests)
   ```bash
   php -S localhost:8080 -t public public/router.php
   ```
   Danach ist das Quiz unter <http://localhost:8080> erreichbar.

3. **Optional: PostgreSQL-Datenbank vorbereiten**
   ```bash
   export POSTGRES_DSN="pgsql:host=localhost;dbname=quiz"
   export POSTGRES_USER=quiz
   export POSTGRES_PASSWORD=***
   psql -h localhost -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f docs/schema.sql
   ```
   Alternativ lassen sich Schema- und Datenimport auch im Docker-Container ausführen:
   ```bash
   docker compose exec slim sh -c \
     'psql -h postgres -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f docs/schema.sql && php scripts/import_to_pgsql.php'
   ```

4. **JSON-Daten importieren**
   ```bash
   php scripts/import_to_pgsql.php
   ```

 Das mitgelieferte `docker-compose.yml` startet die Anwendung samt Reverse Proxy. Daten werden dauerhaft in einem Volume gesichert, Beweisfotos bleiben als JPEG im Ordner `data/photos` erhalten. Dabei richtet die Anwendung Fotos, sofern möglich, anhand ihrer EXIF-Daten aus. Die Domain und weitere Parameter lassen sich über die Datei `.env` anpassen.
**Wichtig:** Damit Fotos automatisch gedreht werden können, muss die PHP-Erweiterung `exif` installiert und aktiviert sein. Prüfen lässt sich das mit:
```bash
php -m | grep exif
```
Fehlt ein EXIF-Orientierungseintrag, kann beim Hochladen über den Parameter
`rotate` ein Winkel (0, 90, 180 oder 270) angegeben werden. Ist kein Parameter
gesetzt, versucht die Anwendung, das externe Programm `convert` aus ImageMagick
(Option `-auto-orient`) aufzurufen. Für diesen Fallback sollte ImageMagick
installiert sein.
Im Docker-Container wird ImageMagick bereits installiert.

