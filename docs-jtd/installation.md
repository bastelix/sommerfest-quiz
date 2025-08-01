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

 Das mitgelieferte `docker-compose.yml` startet die Anwendung samt Reverse Proxy. Daten werden dauerhaft in einem Volume gesichert, Beweisfotos bleiben als JPEG im Ordner `data/photos` erhalten. Dabei richtet die Anwendung Fotos, sofern möglich, anhand ihrer EXIF-Daten aus. Die Domain (`MAIN_DOMAIN`), das Docker-Image (`APP_IMAGE`) und weitere Parameter lassen sich über die Datei `.env` anpassen.
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

> **Hinweis:** Der Reverse Proxy verwendet ein eigenes Docker-Netzwerk. 
> Standardmäßig heißt es `webproxy`. Existiert es nicht, kannst du es mit
> `docker network create ${NETWORK:-webproxy}` anlegen. 
> Über die Umgebungsvariable `NETWORK` legst du bei Bedarf einen anderen Namen fest.

## Docker-Abhängigkeit beim Mandanten-Onboarding {#tenant-onboarding-docker}

Die Skripte `create_tenant.sh` und `delete_tenant.sh` richten neue Subdomains ein und laden danach den Reverse Proxy neu. Sie gehen davon aus, dass ein nginx-Container über Docker Compose läuft. Ohne diesen Container schlägt der Reload-Befehl fehl.

Ist Docker nicht verfügbar oder wird der Proxy anderweitig betrieben, kann der Reload über die Umgebungsvariable `NGINX_RELOAD` deaktiviert werden. Setze sie auf `0`, um nur die Vhost-Dateien anzulegen. Der Proxy muss dann manuell neu geladen werden.
Alternativ kannst du den Reload durch einen separaten Webhook-Service ausführen lassen. Setze dafür `NGINX_RELOADER_URL` auf die Adresse dieses Webhooks. Der Container `nginx-reloader` lauscht beispielsweise auf `http://nginx-reloader:8080/reload` und erwartet das Token `NGINX_RELOAD_TOKEN` im Header `X-Token`. Passe bei Bedarf `NGINX_CONTAINER` an. Bei Nutzung dieses Webhooks sollte `NGINX_RELOAD` auf `0` gesetzt werden. Hinterlege das Token in deiner `.env`-Datei unter `NGINX_RELOAD_TOKEN`; die Anwendung und der Reloader-Container verwenden denselben Wert. Die mitgelieferte Beispielkonfiguration ist bereits entsprechend vorbereitet und aktiviert diese Variante standardmäßig.
Auch die Anwendung selbst stößt den Reload nun über diesen HTTP-Service an und benötigt kein Docker mehr im Container.

Möchtest du für jeden Mandanten einen eigenen Docker-Stack starten, übernimmt dies nun automatisch das Onboarding. Dabei wird unter `tenants/<slug>/` eine individuelle `docker-compose.yml` angelegt und die Instanz gestartet. `acme-companion` kümmert sich um das Zertifikat für `<slug>.quizrace.app>`.
Das Skript `scripts/onboard_tenant.sh` kann weiterhin genutzt werden, um bei Bedarf einen Container manuell zu initialisieren. Die erzeugte Compose-Datei startet den PHP-Webserver auf Port `8080` und setzt `VIRTUAL_PORT=8080`, sodass der Reverse Proxy die ACME-Challenge bedienen kann.

Damit das Skript im Container funktioniert, müssen dort Docker-CLI und Compose-Plugin verfügbar sein und das Socket des Host-Daemons eingebunden werden. Andernfalls führe das Skript direkt auf dem Host aus.

Zum Entfernen einer Instanz steht `scripts/offboard_tenant.sh` bereit. Es stoppt den Container, entfernt dessen Volumes und löscht das Unterverzeichnis `tenants/<slug>/`.
