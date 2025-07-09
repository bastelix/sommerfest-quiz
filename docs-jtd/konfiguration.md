---
layout: default
title: Einstellungen & Anpassungen
nav_order: 2
parent: Einstieg & Setup
toc: true
---

# Einstellungen & Anpassungen

Alle wesentlichen Optionen stehen in `data/config.json` und werden beim ersten Import in die Datenbank übernommen. Änderungen lassen sich später über die Administration speichern.

```json
{
  "displayErrorDetails": true,
  "QRUser": true,
  "logoPath": "/logo.png",
  "pageTitle": "Modernes Quiz mit UIkit",
  "backgroundColor": "#ffffff",
  "buttonColor": "#1e87f0",
  "CheckAnswerButton": "no",
  "QRRestrict": false,
  "competitionMode": false,
  "teamResults": true,
  "photoUpload": true,
  "puzzleWordEnabled": true,
  "puzzleWord": "",
  "puzzleFeedback": "",
  "postgres_dsn": "pgsql:host=postgres;dbname=quiz",
  "postgres_user": "quiz",
  "postgres_pass": "***"
}
```

Das hochgeladene Logo wird in `data/` gespeichert und über `logoPath` referenziert, typischerweise als `/logo.png`.

Optional kann `baseUrl` gesetzt werden, um in QR-Codes komplette Links zu erzeugen. Der Parameter `competitionMode` verhindert Wiederholungen bereits gelöster Kataloge. Über `teamResults` wird gesteuert, ob Teams ihre Ergebnisse einsehen dürfen, und `photoUpload` aktiviert den Upload von Beweisfotos. `puzzleWordEnabled` schaltet das Rätselwort frei und `puzzleFeedback` definiert den Erfolgshinweis nach der Lösung.

Konfigurationswerte können per GET auf `/config.json` abgerufen und per POST aktualisiert werden.

