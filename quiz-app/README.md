# Quiz App

Dieses kleine Quizsystem basiert auf Vue.js und Tailwind CSS und läuft komplett im Browser. Die notwendigen Bibliotheken befinden sich bereits im Verzeichnis `libs/`, sodass keine Verbindung zu externen CDNs erforderlich ist. Das Skript `fetch_libs.sh` dient lediglich dazu, diese Dateien bei Bedarf zu aktualisieren.


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

1. `index.html` im Browser öffnen (z. B. per Doppelklick).
2. Das Quiz lädt automatisch die Fragen aus `questions.json` über ein eingebettetes Skript.
3. Falls du die Bibliotheken aktualisieren möchtest, führe zuvor `./fetch_libs.sh` aus.
4. Möchtest du ein eigenständiges Paket verteilen, kannst du mit `./package.sh`
   eine ZIP-Datei erstellen, die alle Dateien der Anwendung enthält.

## Automatischer Build

Der Workflow `.github/workflows/deploy.yml` baut eine lauffähige Offline-Version und stellt sie als Download bereit. Bei jedem Push auf `main` oder über einen manuellen Start wird `fetch_libs.sh` ausgeführt und das Ergebnis als Artefakt hochgeladen.

Viel Spaß beim Erweitern des Quizzes!
