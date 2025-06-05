# sommerfest-quiz

Dieses Repository enth\u00e4lt ein kleines clientseitiges Quizsystem. Neben dem urspr\u00fcnglichen Ordner `quiz-app` gibt es das Beispiel `quiz-admin`. Es nutzt ebenfalls Vue&nbsp;3 und Tailwind, bindet die Bibliotheken jedoch lokal ein.

Die JavaScript-Dateien f\u00fcr `quiz-app` liegen bereits im Ordner `quiz-app/libs`. Das Skript `fetch_libs.sh` l\u00e4dt sie bei Bedarf erneut herunter und erzeugt die ben\u00f6tigte `tailwind.min.css` lokal \u00fcber `npx tailwindcss`. Anschlie\u00dfend kannst du `quiz-app/index.html` direkt im Browser \u00f6ffnen.

Im Verzeichnis `quiz-admin` befindet sich eine weitere Variante, die die Fragen aus `questions.json` l\u00e4dt und ein Card-basiertes Layout nutzt. Die ben\u00f6tigten Bibliotheken liegen im Ordner `libs/`, sodass die Seite nach einem Lauf von `fetch_libs.sh` komplett offline funktioniert.

Siehe die jeweiligen README-Dateien f\u00fcr weitere Informationen.

## Offline-Paket erstellen

Mit `quiz-app/package.sh` l\u00e4sst sich ein ZIP-Archiv erzeugen, das alle Dateien inklusive der JavaScript-Bibliotheken enth\u00e4lt. So kann das Quiz bequem ohne Internetverbindung verteilt werden.

