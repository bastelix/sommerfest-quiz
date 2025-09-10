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
