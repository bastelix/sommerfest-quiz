---
layout: default
title: Migrationen
---

## Datenbank-Migrationen

Datenbankänderungen werden als SQL-Dateien im Verzeichnis `migrations/` verwaltet.
Um alle offenen Migrationen anzuwenden, steht das Skript `scripts/run_migrations.php` bereit:

```bash
php scripts/run_migrations.php
```

Das Skript führt neue Migrationen in chronologischer Reihenfolge aus und protokolliert sie in der Tabelle `migrations`.
Standardmäßig führt der Webserver die Migrationen nicht mehr automatisch bei
eingehenden Requests aus. Für lokale Experimente kann die Umgebungsvariable
`RUN_MIGRATIONS_ON_REQUEST` auf einen wahrheitswertigen Wert gesetzt werden,
um das Verhalten kurzfristig wieder einzuschalten.
