# Sommerfest-Quiz

![HTML Validity](https://img.shields.io/badge/HTML%20validity-passing-brightgreen)

Dieses Projekt stellt ein kleines Quiz dar, das nun mit [Slim](https://www.slimframework.com/) als leichtgewichtiger PHP‑Anwendung betrieben wird. Das komplette Frontend basiert auf [UIkit3](https://getuikit.com/), sodass sämtliche Seiten reine UIkit‑HTML‑Strukturen verwenden.

## Projektstruktur

- **public/** – Enthält `index.php`, alle UIkit3‑Assets sowie die JavaScript‑Dateien.
- **templates/** – UIkit3‑basierte HTML‑Dateien (`index.html`, `admin.html`, `faq.html`).
- **src/** – PHP‑Routen und sonstige Logik.
- **kataloge/** – Fragenkataloge im JSON‑Format.
- **config/** – Einstellungen der Anwendung.
- **vendor/** – Von Composer installierte Abhängigkeiten.

## Anwendung starten

Nach dem Klonen des Repositories werden die Abhängigkeiten installiert und ein lokaler PHP‑Server gestartet:

```bash
composer install
php -S localhost:8080 -t public
```

Anschließend ist die Anwendung unter `http://localhost:8080` erreichbar.

## Fragen bearbeiten und Design anpassen

Die Datei `admin.html` stellt eine Administrationsoberfläche bereit. Änderungen an den Einstellungen und Fragen werden über AJAX direkt an den Slim‑Server geschickt und dort gespeichert. Ein manuelles Kopieren der erzeugten Dateien ist nicht mehr nötig.

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

Dieses Quiz läuft vollständig im Browser und benötigt keine permanente Serververbindung. Ergebnisse werden ausschließlich lokal im `localStorage` gespeichert. Dabei erhält jede teilnehmende Person einen zufälligen Fantasienamen, sodass keinerlei personenbezogene Daten verarbeitet werden. Der integrierte PHP‑Server dient lediglich zum Ausliefern der Dateien und führt keine Protokollierung durch. Beim Export der Datei `statistical.log` werden lediglich die Pseudonyme samt Punktzahl ausgegeben. Unter diesen Voraussetzungen kann das Tool in der Regel DSGVO‑konform eingesetzt werden, eine rechtliche Prüfung für den konkreten Anwendungsfall bleibt jedoch empfohlen.

## Erstellung

Dieses Tool ist ein reiner Prototyp, der zu 100% mit Codex von OpenAI umgesetzt wurde. Die Arbeit diente ausschließlich der Erprobung des Coding-Assistenten und seiner Möglichkeiten. Sämtliche Dateien in diesem Repository – angefangen bei HTML und CSS über die Skripte bis hin zu dieser Dokumentation – wurden mithilfe des Assistenten generiert. Manuelle Eingriffe beschränkten sich auf minimale Korrekturen sowie die Begleitung des Generierungsprozesses. Die vorliegende Anwendung soll daher insbesondere demonstrieren, wie sich mithilfe von Codex ein funktionsfähiger Prototyp realisieren lässt.

## Build-Slim Workflow

Um das Slim-Skeleton bei Bedarf automatisiert in dieses Repository zu kopieren, existiert der Workflow [`build-slim.yml`](.github/workflows/build-slim.yml). Er installiert PHP samt Composer, führt `composer create-project slim/slim-skeleton temp-slim` aus und übernimmt die erzeugten Dateien per `rsync`. Anschließend wird das temporäre Verzeichnis wieder entfernt. So bleibt das Grundgerüst jederzeit reproduzierbar.

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
