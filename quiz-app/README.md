# Quiz App

Dieses kleine Quizsystem basiert auf Vue.js und Tailwind CSS und läuft komplett im Browser. Einfach den Ordner **quiz-app** in einem lokalen Webserver öffnen (z. B. `python -m http.server`) und `index.html` aufrufen.

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

1. Einen lokalen Webserver im Verzeichnis `quiz-app` starten:
   ```
   python3 -m http.server
   ```
2. Im Browser `http://localhost:8000/index.html` aufrufen.
3. Das Quiz lädt automatisch die Fragen aus `questions.json`.

Viel Spaß beim Erweitern des Quizzes!
