---
layout: default
title: Teams & Ergebnisse
nav_order: 2
parent: Nutzung & Bedienung
toc: true
---

# Teams, Login & Ergebnis-Export

## Teams und QR-Code-Login

In `data/teams.json` lassen sich Teilnehmernamen speichern. `GET /teams.json` ruft die Liste ab, `POST /teams.json` speichert sie. Ein optionales Häkchen "Nur Teams/Personen aus der Liste dürfen teilnehmen" aktiviert eine Zugangsbeschränkung via QR-Code. QR-Codes können direkt in der Oberfläche generiert werden.

## Ergebnisse

Alle Resultate werden in der Datenbank abgelegt. Die API bietet folgende Endpunkte:
- `GET /results.json` – liefert alle gespeicherten Ergebnisse.
- `POST /results` – fügt ein neues Ergebnis hinzu.
- `DELETE /results` – löscht alle Einträge.
- `GET /results/download` – erzeugt eine CSV-Datei mit allen Resultaten.
- `GET /question-results.json` – listet falsch beantwortete Fragen.

Diese Funktionen ermöglichen eine einfache Auswertung und Archivierung der Spielergebnisse.

