# Sommerfest-Quiz

![HTML Validity](https://img.shields.io/badge/HTML%20validity-passing-brightgreen)

Dieses Projekt enthält ein vollständig clientseitiges Quiz, das ohne Serververbindung genutzt werden kann. Alle benötigten Dateien liegen im Repository. Einfach runterladen, anpassen - Fertig!

## Inhalt des Repositories

- **index.html** – Startseite und Quiz.
- **admin.html** – Admin-Oberfläche zum Anpassen von Fragen und Design.
- **faq.html** – Hilfeseite mit häufig gestellten Fragen.
- **css/** – Stylesheets von UIkit.
- **css/dark.css** – Zusätzliche Styles für den Dunkelmodus.
- **js/** – JavaScript-Dateien von UIkit und die Skripte für das Quiz.
- **js/config.js** – Einstellungen für Logo, Farben und Texte.
- **kataloge/** – Enthält einzelne Fragenkataloge als JSON-Dateien.
- **kataloge/catalogs.json** – Indexdatei mit allen verfügbaren Katalogen.
- **server.js** – Ein kleiner Node.js-Server zum lokalen Hosten der Dateien.

## Quiz starten

Das Quiz kann direkt durch Öffnen von `index.html` in einem Browser verwendet werden. Optional lässt es sich mit Node.js lokal bereitstellen:

```bash
node server.js
```

Anschließend ist die Anwendung unter `http://localhost:3000` erreichbar.

## Fragen bearbeiten und Design anpassen

Die Datei `admin.html` stellt eine einfache Administrationsoberfläche bereit. Dort können Fragenkataloge bearbeitet und das Layout angepasst werden. Beim Klick auf **Speichern** wird eine neue `config.js` sowie für den aktuell geöffneten Katalog eine JSON-Datei heruntergeladen. Die Dateien müssen anschließend manuell in den Ordner `js/` bzw. `kataloge/` kopiert werden, um die vorhandenen Versionen zu ersetzen.

## Eigene Fragenkataloge

Alle Fragen liegen als einzelne JSON-Dateien im Unterordner `kataloge/`. Die Datei `catalogs.json` listet die verfügbaren Kataloge samt Anzeigenamen und Beschreibung. Um einen neuen Katalog anzulegen, wird eine weitere JSON-Datei in diesem Ordner gespeichert und `catalogs.json` um einen entsprechenden Eintrag ergänzt.

Beim Aufruf von `index.html` erscheint zunächst eine Übersicht der vorhandenen Kataloge. Nach Auswahl wird der passende Fragenkatalog geladen und das Quiz gestartet. Alternativ kann ein Katalog direkt über den URL‑Parameter `?katalog=<id>` geöffnet werden, z.&nbsp;B. `index.html?katalog=fragen_it`.

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
- `QRUser` – Bei `true` startet das Quiz mit einem QR-Code-Login. Der Inhalt des Codes wird als Nutzername verwendet.

## QR-Code-Login

Ist die Option `QRUser` aktiviert, erscheint vor dem Quiz ein einfacher QR-Scanner. 
Der eingelesene Text dient als eindeutiger Benutzername für diese Sitzung. 
Zur Erzeugung des Codes kann jedes gängige Tool verwendet werden (z.B. Online-Generatoren oder Apps).

## Datenschutz und DSGVO

Dieses Quiz läuft vollständig im Browser und benötigt keine Serververbindung. Ergebnisse werden ausschließlich lokal im `localStorage` gespeichert. Dabei erhält jede teilnehmende Person einen zufälligen Fantasienamen, sodass keinerlei personenbezogene Daten verarbeitet werden. Der optionale Node.js-Server dient nur zum Ausliefern der statischen Dateien und führt keine Protokollierung durch. Beim Export der Datei `statistical.log` werden lediglich die Pseudonyme samt Punktzahl ausgegeben. Unter diesen Voraussetzungen kann das Tool in der Regel DSGVO-konform eingesetzt werden, eine rechtliche Prüfung für den konkreten Anwendungsfall bleibt jedoch empfohlen.

## Erstellung

Dieses Tool ist ein reiner Prototyp, der zu 100% mit Codex von OpenAI umgesetzt wurde. Die Arbeit diente ausschließlich der Erprobung des Coding-Assistenten und seiner Möglichkeiten. Sämtliche Dateien in diesem Repository – angefangen bei HTML und CSS über die Skripte bis hin zu dieser Dokumentation – wurden mithilfe des Assistenten generiert. Manuelle Eingriffe beschränkten sich auf minimale Korrekturen sowie die Begleitung des Generierungsprozesses. Die vorliegende Anwendung soll daher insbesondere demonstrieren, wie sich mithilfe von Codex ein funktionsfähiger Prototyp realisieren lässt.

## Lizenz

<details>
<summary>Der Code steht unter der MIT-Lizenz. Siehe die Datei <code>LICENSE</code> für Details.</summary>
Der Quellcode befindet sich auf GitHub: <https://github.com/bastelix/sommerfest-quiz>
Die Erstellung der Anwendung erfolgte mithilfe von etwa 60 Anweisungen, und das komplette Archiv ist kleiner als 1 MB.


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
