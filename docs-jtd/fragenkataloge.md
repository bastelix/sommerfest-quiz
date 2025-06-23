---
layout: default
title: Fragenkataloge
nav_order: 1
parent: Nutzung & Bedienung
toc: true
---

# Kataloge & Fragetypen

`data/kataloge/catalogs.json` listet alle verfügbaren Kataloge mit `slug`, Name und optionaler QR-Code-Adresse. Die Reihenfolge bestimmt das Feld `sort_order`. Jede Frage speichert die zugehörige `catalog_uid`.

Ein Eintrag kann zudem ein Feld `raetsel_buchstabe` enthalten, das den Buchstaben für das Rätselwort festlegt.

## API-Endpunkte

- `GET /kataloge/{file}` liefert den JSON-Katalog oder leitet im Browser auf `/?katalog=slug` um.
- `PUT /kataloge/{file}` legt eine neue Datei an.
- `POST /kataloge/{file}` überschreibt einen vorhandenen Katalog.
- `DELETE /kataloge/{file}` entfernt die Datei.
- `DELETE /kataloge/{file}/{index}` löscht eine Frage anhand des Index.

Die Fragetypen umfassen Sortieren, Zuordnen und Multiple Choice. Dank des Puzzlewort-Features kann nach jeder Runde ein Buchstabe angezeigt werden, bis sich das Lösungswort ergibt.

