# sommerfest-quiz

Dieses Repository enthält ein kleines clientseitiges Quizsystem. Im Verzeichnis `quiz-app` befindet sich die Anwendung, die ohne Build-Prozess direkt im Browser läuft.

Die JavaScript- und CSS-Dateien liegen bereits im Ordner `quiz-app/libs`. Das
Skript `fetch_libs.sh` muss nur ausgeführt werden, wenn du diese Bibliotheken
aktualisieren möchtest. Anschließend kannst du `quiz-app/index.html` direkt im
Browser öffnen.

Siehe `quiz-app/README.md` für weitere Informationen zur Nutzung und zum Hinzufügen neuer Fragen.

## Offline-Paket erstellen

Mit `quiz-app/package.sh` lässt sich ein ZIP-Archiv erzeugen, das alle Dateien
inklusive der JavaScript-Bibliotheken enthält. So kann das Quiz bequem ohne
Internetverbindung verteilt werden.

## Deploy per GitHub Actions

Im Repository befindet sich ein Workflow `.github/workflows/deploy.yml`. Er lädt den Code, führt `quiz-app/fetch_libs.sh` aus und stellt das komplette `quiz-app/` Verzeichnis als Artefakt bereit. Dadurch kann ein fertiges Offline-Paket direkt aus dem Actions-Bereich heruntergeladen werden.

