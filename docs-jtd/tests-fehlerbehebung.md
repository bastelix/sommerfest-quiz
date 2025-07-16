---
layout: default
title: Tests & Fehlerbehebung
nav_order: 2
parent: Entwicklung & Updates
---

# Tests & Fehlerbehebung

## Fehler bei Datenvergleich

### Beispiele

- `Failed asserting that '[]' matches JSON string ...`
- `Failed asserting that actual size 0 matches expected size 1`

### Ursache

- Erwartete Testdaten fehlen oder werden nicht wie erwartet erzeugt oder gelesen.
- Die Datenbank ist nicht korrekt vorbereitet.

### Lösung

- Prüfe das Setup und Teardown deiner Tests.
- Stelle sicher, dass initiale Testdaten und Migrationen ausgeführt werden.
