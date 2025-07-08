---
layout: default
title: Admin & Nutzerverwaltung
nav_order: 3
parent: Nutzung & Bedienung
toc: true
---

# Admin-Bereich & Nutzerverwaltung

## Authentifizierung

Der Zugang zum Administrationsbereich erfolgt über `/login`. Nach einem erfolgreichen POST mit gültigen Daten wird eine Session gesetzt und der Browser zur Route `/admin` weitergeleitet. Die Middleware `AdminAuthMiddleware` schützt alle Admin-Routen und leitet bei fehlender Session zum Login um.

## Administrationsoberfläche

Unter `/admin` stehen folgende Tabs zur Verfügung:
1. **Veranstaltungen** – Veranstaltungen anlegen, bearbeiten oder entfernen.
2. **Veranstaltung konfigurieren** – Einstellungen wie Logo, Farben und Texte.
3. **Kataloge** – Fragenkataloge erstellen und verwalten.
4. **Fragen anpassen** – Fragen eines Katalogs hinzufügen, bearbeiten oder löschen.
5. **Teams/Personen** – Teilnehmerlisten pflegen, optional als Login-Beschränkung.
6. **Ergebnisse** – Spielstände einsehen und herunterladen.
7. **Statistik** – Einzelne Antworten analysieren und nach Teams filtern.
8. **Administration** – Passwort ändern und Backups verwalten.
Der Statistik-Tab listet jede Antwort mit Name, Versuch, Katalog, Frage, Antwort, Richtig-Status und optionalem Beweisfoto. Über ein Auswahlfeld lassen sich die Daten nach Teams oder Personen filtern.


## Weitere Funktionen

- **Administration:** Ein POST auf `/password` speichert ein neues Admin-Passwort in `config.json`. Über `/export` kann ein JSON-Backup erstellt und per `/import` wieder eingespielt werden. 
  Die Route `/backups` listet vorhandene Sicherungen.
- **Logo hochladen:** Das aktuelle Logo wird unter `/logo.png` oder `/logo.webp` bereitgestellt. Über einen POST auf diese URLs lässt sich eine neue Datei hochladen. Das Bild wird dabei im Ordner `data/` gespeichert, sodass PDFs es einbinden können.
- **Ergebnisse exportieren:** Alle Resultate können als CSV-Datei heruntergeladen werden.
Die API ermöglicht so die komplette Verwaltung eines Quizsystems und ist sowohl lokal als auch im Netzwerk schnell einsatzbereit.

