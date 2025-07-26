---
layout: default
title: Einstieg & Setup
nav_order: 2
has_children: true
---

# Einstieg & Setup

Kurze Übersicht über Installation und grundlegende Einstellungen.

## Schritte zur Einrichtung

1. **Domain konfigurieren**
   <progress value="1" max="4"></progress>
   
   Richte die gewünschte (Sub-)Domain ein und lasse sie auf den Server zeigen.

2. **Container einrichten und starten**
   <progress value="2" max="4"></progress>

   Klone das Repository, kopiere die Beispielkonfiguration und starte die Docker-Umgebung:
   ```bash
   cp .env.template .env
   docker compose up --build -d
   ```

3. **SSL-Zertifikat abrufen**
   <progress value="3" max="4"></progress>

   Sobald der Container unter der Domain erreichbar ist, erstellt der integrierte Proxy automatisch ein Let's‑Encrypt‑Zertifikat.

4. **Datenbank einrichten**
   <progress value="4" max="4"></progress>

   Führe die Migrationen aus und importiere die Beispiel‑Daten:
   ```bash
   ./scripts/run_psql_in_docker.sh
   php scripts/import_to_pgsql.php
   ```

Nach erfolgreicher Einrichtung erreichst du das Backend unter [`/login`](https://deine-domain/login). Die Plattform ist aktuell noch im Aufbau.
