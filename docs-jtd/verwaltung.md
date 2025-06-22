---
layout: default
title: Admin & Nutzerverwaltung
nav_order: 6
toc: true
---

# Admin-Bereich & Nutzerverwaltung

## Authentifizierung

Der Zugang zum Administrationsbereich erfolgt über `/login`. Nach einem erfolgreichen POST mit gültigen Daten wird eine Session gesetzt und der Browser zur Route `/admin` weitergeleitet. Die Middleware `AdminAuthMiddleware` schützt alle Admin-Routen und leitet bei fehlender Session zum Login um.

## Administrationsoberfläche

Unter `/admin` stehen folgende Tabs zur Verfügung:
1. **Veranstaltung konfigurieren** – Einstellungen wie Logo, Farben und Texte.
2. **Kataloge** – Fragenkataloge erstellen und verwalten.
3. **Fragen anpassen** – Fragen eines Katalogs hinzufügen, bearbeiten oder löschen.
4. **Teams/Personen** – Teilnehmerlisten pflegen, optional als Login-Beschränkung.
5. **Ergebnisse** – Spielstände einsehen und herunterladen.
6. **Passwort ändern** – Administrationspasswort setzen.

## Weitere Funktionen

- **Passwort ändern:** Ein POST auf `/password` speichert ein neues Admin-Passwort in `config.json`.
- **Logo hochladen:** Das aktuelle Logo wird unter `/logo.png` oder `/logo.webp` bereitgestellt. Über einen POST auf diese URLs lässt sich eine neue Datei hochladen.
- **Ergebnisse exportieren:** Alle Resultate können als CSV-Datei heruntergeladen werden.

Die API ermöglicht so die komplette Verwaltung eines Quizsystems und ist sowohl lokal als auch im Netzwerk schnell einsatzbereit.

