---
layout: default
title: Admin & Nutzerverwaltung
nav_order: 3
parent: Nutzung & Bedienung
toc: true
---

# Admin-Bereich & Nutzerverwaltung

## Authentifizierung

Der Zugang zum Administrationsbereich erfolgt über `/login`. Nach einem erfolgreichen POST mit gültigen Daten wird eine Session gesetzt und der Browser zur Route `/admin/events` (bzw. `/admin`) weitergeleitet. Die Middleware `AdminAuthMiddleware` schützt alle Admin-Routen und leitet bei fehlender Session zum Login um.

## Administrationsoberfläche

Unter `/admin/events` stehen folgende Tabs per URL bereit:
1. **Events** – `/admin/events`.
2. **Event Configuration** – `/admin/event/settings`.
3. **Catalogs** – `/admin/catalogs`.
4. **Edit Questions** – `/admin/questions`.
5. **Teams/People** – `/admin/teams`.
6. **Summary** – `/admin/summary`.
7. **Results** – `/admin/results`.
8. **Statistics** – `/admin/statistics`.
9. **Pages** – `/admin/pages` (nur Administratoren).
10. **Administration** – `/admin/management` (nur Administratoren).
Der Statistik-Tab listet jede Antwort mit Name, Versuch, Katalog, Frage, Antwort, Richtig-Status und optionalem Beweisfoto. Über ein Auswahlfeld lassen sich die Daten nach Teams oder Personen filtern.


## Weitere Funktionen

- **Administration:** Über `/export` kann ein JSON-Backup erstellt werden. Eine Wiederherstellung ist per `/import` oder `/backups/{name}/restore` möglich.
  Die Route `/backups` listet vorhandene Sicherungen.
- **Logo hochladen:** Das aktuelle Logo wird unter `/logo.png` oder `/logo.webp` bereitgestellt. Über einen POST auf diese URLs lässt sich eine neue Datei hochladen. Das Bild wird dabei im Ordner `data/` gespeichert, sodass PDFs es einbinden können.
- **Ergebnisse exportieren:** Alle Resultate können als CSV-Datei heruntergeladen werden.
Die API ermöglicht so die komplette Verwaltung eines Quizsystems und ist sowohl lokal als auch im Netzwerk schnell einsatzbereit.

