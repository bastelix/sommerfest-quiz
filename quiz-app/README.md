# Quiz App

Dieses kleine Quizsystem basiert auf Vue.js und Tailwind CSS und läuft komplett im Browser. Die benötigten Bibliotheken können lokal hinterlegt werden, sodass keine Verbindung zu externen CDNs notwendig ist.
Führe im Ordner **quiz-app** das Skript `fetch_libs.sh` aus, um die JavaScript- und CSS-Dateien in das Verzeichnis `libs/` herunterzuladen. Danach kann `index.html` direkt im Browser geöffnet werden und funktioniert auch ohne Webserver.


## Fragen bearbeiten

Die Fragen befinden sich in der Datei `questions.json`. Sie besteht aus einem Array von Frageobjekten. Mögliche Typen sind `match`, `choice` und `sort`.


Neue Fragen können hinzugefügt werden, indem ein weiteres Objekt im gleichen Format ergänzt wird. Die Reihenfolge der Objekte entspricht der Reihenfolge im Quiz.

## Projektstruktur

```
quiz-app/
  |-- index.html        # Hauptseite mit Vue.js App
  |-- questions.json    # Fragen im JSON-Format
  |-- assets/           # optionale Dateien (Bilder, Sounds)
  |-- README.md         # diese Datei
```

## Nutzung

1. Abhängigkeiten mit `./fetch_libs.sh` herunterladen (einmalig).
2. `index.html` im Browser öffnen (z. B. per Doppelklick).
3. Das Quiz lädt automatisch die Fragen aus `questions.json` über ein eingebettetes Skript.

## Automatischer Build

Der Workflow `.github/workflows/deploy.yml` baut eine lauffähige Offline-Version und stellt sie als Download bereit. Bei jedem Push auf `main` oder über einen manuellen Start wird `fetch_libs.sh` ausgeführt und das Ergebnis als Artefakt hochgeladen.
=======

Viel Spaß beim Erweitern des Quizzes!
