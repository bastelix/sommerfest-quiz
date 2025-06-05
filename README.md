# Sommerfest-Quiz

Dieses Projekt enthält ein vollständig clientseitiges Quiz, das ohne Serververbindung genutzt werden kann. Alle benötigten Dateien liegen im Repository.

## Inhalt des Repositories

- **index.html** – Startseite und Quiz.
- **admin.html** – Admin-Oberfläche zum Anpassen von Fragen und Design.
- **css/** – Stylesheets von UIkit.
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

## Konfigurationsoptionen

Die Datei `js/config.js` enthält alle zentralen Einstellungen:

- `logoPath` – Pfad zu einem eigenen Logo (optional).
- `header` – Überschrift auf der Startseite.
- `subheader` – Untertitel unterhalb der Überschrift.
- `backgroundColor` – Hintergrundfarbe der Seite.
- `buttonColor` – Farbe für Schaltflächen.
- `CheckAnswerButton` – Wenn auf `"no"` gesetzt, wird der Button **Antwort prüfen** ausgeblendet und nur **Weiter** angezeigt.

## Erstellung

Dieses Tool ist ein reiner Prototyp, der zu 100% mit Codex von OpenAI umgesetzt wurde. Die Arbeit diente ausschließlich der Erprobung des Coding-Assistenten und seiner Möglichkeiten. Sämtliche Dateien in diesem Repository – angefangen bei HTML und CSS über die Skripte bis hin zu dieser Dokumentation – wurden mithilfe des Assistenten generiert. Manuelle Eingriffe beschränkten sich auf minimale Korrekturen sowie die Begleitung des Generierungsprozesses. Die vorliegende Anwendung soll daher insbesondere demonstrieren, wie sich mithilfe von Codex ein funktionsfähiger Prototyp realisieren lässt.

## Lizenz

Der Code steht unter der MIT-Lizenz. Siehe die Datei `LICENSE` für Details.
