# sommerfest-quiz

Dieses Repository enth\u00e4lt ein kleines clientseitiges Quizsystem. Neben dem urspr\u00fcnglichen Ordner `quiz-app` befindet sich jetzt das Beispiel `quiz-admin`, das ein moderneres Layout mit Vue&nbsp;3 und Tailwind per CDN demonstriert.

Die JavaScript-Dateien f\u00fcr `quiz-app` liegen bereits im Ordner `quiz-app/libs`. Das Skript `fetch_libs.sh` l\u00e4dt sie bei Bedarf erneut herunter und erzeugt die ben\u00f6tigte `tailwind.min.css` lokal \u00fcber `npx tailwindcss`. Anschlie\u00dfend kannst du `quiz-app/index.html` direkt im Browser \u00f6ffnen.

Im Verzeichnis `quiz-admin` befindet sich eine weitere Variante, die die Fragen aus `questions.json` l\u00e4dt und ein Card-basiertes Layout nutzt. Auch diese Seite kann ohne Build-Prozess direkt ge\u00f6ffnet werden.

Siehe die jeweiligen README-Dateien f\u00fcr weitere Informationen.

## Offline-Paket erstellen

Mit `quiz-app/package.sh` l\u00e4sst sich ein ZIP-Archiv erzeugen, das alle Dateien inklusive der JavaScript-Bibliotheken enth\u00e4lt. So kann das Quiz bequem ohne Internetverbindung verteilt werden.

## Deploy per GitHub Actions

Im Repository befindet sich ein Workflow `.github/workflows/deploy.yml`. Er lu00e4dt den Code, richtet Node ein, fu00fchrt `quiz-app/fetch_libs.sh` aus und stellt das komplette `quiz-app/` Verzeichnis als Artefakt bereit. Dadurch kann ein fertiges Offline-Paket direkt aus dem Actions-Bereich heruntergeladen werden.
