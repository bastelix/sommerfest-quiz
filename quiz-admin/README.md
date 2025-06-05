# Quiz Admin

Diese statische Quiz-App basiert auf Vue 3 und Tailwind CSS. Sie nutzt keine
Serverkomponenten und l\u00e4dt die Fragen aus der lokalen Datei `questions.json`.
Alle ben\u00f6tigten Bibliotheken k\u00f6nnen lokal im Ordner `libs/` liegen. Mit
`fetch_libs.sh` lassen sich die Dateien bei Bedarf herunterladen und die
ben\u00f6tigte `tailwind.min.css` wird dabei direkt erzeugt.

## Nutzung

1. Bei Bedarf `./fetch_libs.sh` ausf\u00fchren, um alle Bibliotheken lokal zu speichern.
2. `index.html` im Browser \u00f6ffnen.
3. Die App l\u00e4dt automatisch die Fragen und startet das Quiz.
4. Der Dark/Light-Mode kann \u00fcber den Schalter in der Topbar gewechselt werden.

Die Dateien unter `assets/` dienen als Platz f\u00fcr optionale Bilder oder Icons.
