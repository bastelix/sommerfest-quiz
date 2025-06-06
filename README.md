# Sommerfest-Quiz

Dieses Projekt enthält ein vollständig clientseitiges Quiz, das ohne Serververbindung genutzt werden kann. Alle benötigten Dateien liegen im Repository.

## Inhalt des Repositories

- **index.html** – Startseite und Quiz.
- **admin.html** – Admin-Oberfläche zum Anpassen von Fragen und Design.
- **faq.html** – Hilfeseite mit häufig gestellten Fragen.
- **css/** – Stylesheets von UIkit.
- **css/dark.css** – Zusätzliche Styles für den Dunkelmodus.
- **js/** – JavaScript-Dateien von UIkit und die Skripte für das Quiz.
- **js/config.js** – Einstellungen für Logo, Farben und Texte.
- **js/questions.js** – Enthält alle Quizfragen.
- **server.js** – Ein kleiner Node.js-Server zum lokalen Hosten der Dateien.

## Quiz starten

Das Quiz kann direkt durch Öffnen von `index.html` in einem Browser verwendet werden. Optional lässt es sich mit Node.js lokal bereitstellen:

```bash
node server.js
```

Anschließend ist die Anwendung unter `http://localhost:3000` erreichbar.

## Fragen bearbeiten und Design anpassen

Die Datei `admin.html` stellt eine einfache Administrationsoberfläche bereit. Dort können Fragen hinzugefügt oder angepasst und Farben, Logo sowie Überschriften geändert werden. Beim Klick auf **Speichern** wird jeweils eine neue `config.js` bzw. `questions.js` heruntergeladen. Diese heruntergeladene Datei muss danach manuell in den Ordner `js/` kopiert und dort die vorhandene Datei ersetzen.

## Ablauf des Quiz

Nach dem Start erscheint eine Übersicht bisheriger Ergebnisse (sofern welche im Browser gespeichert sind). Anschließend startet das Quiz. Es gibt drei Fragetypen:

1. **Sortieren** – Elemente per Drag & Drop in die richtige Reihenfolge bringen.
2. **Zuordnen** – Begriffe den passenden Definitionen zuordnen.
3. **Multiple Choice** – Eine oder mehrere Antworten auswählen.

Jede Frage besitzt einen Button **Antwort prüfen** sowie **Weiter**, um zur nächsten Frage zu gelangen. Am Ende wird die erreichte Punktzahl angezeigt.

## Ergebnisse und Statistik

Die erzielten Punkte werden anonym im `localStorage` des Browsers gespeichert. Beim Start bekommt jede Person einen zufällig erzeugten Fantasienamen zugewiesen, der in der Ergebnisliste und auf der Auswertungsseite angezeigt wird. Auf der Startseite wird eine Liste der bisherigen Ergebnisse angezeigt, die als kleine Slideshow rotiert. Über den Button **Statistik herunterladen** lassen sich diese Daten als Datei `statistical.log` exportieren.

## FAQ

Eine Hilfeseite mit häufig gestellten Fragen befindet sich in `faq.html`. Von der Startseite lässt sie sich über das Fragezeichen-Symbol oben rechts aufrufen.

## Dunkelmodus

Das Quiz verfügt über einen optionalen Dunkelmodus. Über den Schalter links oben kann zwischen hellem und dunklem Design gewechselt werden. Die Auswahl wird im Browser gespeichert. Die dazugehörigen Styles stehen in `css/dark.css`.

## Konfigurationsoptionen

Die Datei `js/config.js` enthält alle zentralen Einstellungen:

- `logoPath` – Pfad zu einem eigenen Logo (optional).
- `header` – Überschrift auf der Startseite.
- `subheader` – Untertitel unterhalb der Überschrift.
- `backgroundColor` – Hintergrundfarbe der Seite.
- `buttonColor` – Farbe für Schaltflächen.
- `CheckAnswerButton` – Wenn auf `"no"` gesetzt, wird der Button **Antwort prüfen** ausgeblendet und nur **Weiter** angezeigt.

## Datenschutz und DSGVO

Dieses Quiz läuft vollständig im Browser und benötigt keine Serververbindung. Ergebnisse werden ausschließlich lokal im `localStorage` gespeichert. Dabei erhält jede teilnehmende Person einen zufälligen Fantasienamen, sodass keinerlei personenbezogene Daten verarbeitet werden. Der optionale Node.js-Server dient nur zum Ausliefern der statischen Dateien und führt keine Protokollierung durch. Beim Export der Datei `statistical.log` werden lediglich die Pseudonyme samt Punktzahl ausgegeben. Unter diesen Voraussetzungen kann das Tool in der Regel DSGVO-konform eingesetzt werden, eine rechtliche Prüfung für den konkreten Anwendungsfall bleibt jedoch empfohlen.

## Erstellung

Dieses Tool ist ein reiner Prototyp, der zu 100% mit Codex von OpenAI umgesetzt wurde. Die Arbeit diente ausschließlich der Erprobung des Coding-Assistenten und seiner Möglichkeiten. Sämtliche Dateien in diesem Repository – angefangen bei HTML und CSS über die Skripte bis hin zu dieser Dokumentation – wurden mithilfe des Assistenten generiert. Manuelle Eingriffe beschränkten sich auf minimale Korrekturen sowie die Begleitung des Generierungsprozesses. Die vorliegende Anwendung soll daher insbesondere demonstrieren, wie sich mithilfe von Codex ein funktionsfähiger Prototyp realisieren lässt.

## Lizenz

<details>
<summary>Der Code steht unter der MIT-Lizenz. Siehe die Datei <code>LICENSE</code> für Details.</summary>

```text
MIT License

Copyright (c) 2025 calhelp

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

</details>
