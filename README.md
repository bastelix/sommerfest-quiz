# sommerfest-quiz

Dieses Repository enthält ein kleines clientseitiges Quizsystem. Im Verzeichnis `quiz-app` befindet sich die Anwendung, die ohne Build-Prozess direkt im Browser läuft.

Vor dem Start können die Bibliotheken lokal in `quiz-app/libs` abgelegt werden. Führe dazu im Unterordner den Befehl `./fetch_libs.sh` aus und öffne anschließend `quiz-app/index.html` direkt im Browser.

Siehe `quiz-app/README.md` für weitere Informationen zur Nutzung und zum Hinzufügen neuer Fragen.

## Deploy per GitHub Actions

Im Repository befindet sich ein Workflow `.github/workflows/deploy.yml`. Er lädt den Code, führt `quiz-app/fetch_libs.sh` aus und stellt das komplette `quiz-app/` Verzeichnis als Artefakt bereit. Dadurch kann ein fertiges Offline-Paket direkt aus dem Actions-Bereich heruntergeladen werden.

