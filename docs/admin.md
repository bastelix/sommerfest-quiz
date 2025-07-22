# Administration

Die Administrationsoberfl\u00e4che erreichen Sie \u00fcber `/admin` nach einem erfolgreichen Login. Folgende Bereiche stehen zur Verf\u00fcgung:

1. **Veranstaltungen** – Veranstaltungen anlegen, bearbeiten oder entfernen. Jede Zeile enthält Name, Beginn, Ende und Beschreibung.
2. **Veranstaltung konfigurieren** – Farben, Logos und Texte anpassen.
3. **Kataloge** – Fragenkataloge anlegen und verwalten.
4. **Fragen anpassen** – Bestehende Fragen ändern oder neue hinzufügen.
5. **Teams/Personen** – Teilnehmerlisten pflegen und optional den Zugang einschränken.
6. **Ergebnisse** – Spielstände einsehen und als CSV herunterladen.
7. **Statistik** – Einzelne Antworten analysieren und nach Teams filtern.
8. **Seiten** – Statische Inhalte wie Landing-Page, Impressum, Lizenz und Datenschutz bearbeiten.
9. **Administration** – Benutzer und Backups verwalten.
Im Tab "Administration" lassen sich JSON-Sicherungen exportieren und bei Bedarf wiederherstellen.
Der Statistik-Tab listet jede Antwort mit Name, Versuch, Katalog, Frage, Antwort, Richtig-Status und optionalem Beweisfoto. Über ein Auswahlfeld lassen sich die Daten nach Teams oder Personen filtern.

Weitere Funktionen wie der QR-Code-Login oder der Wettkampfmodus lassen sich in der Datei `data/config.json` aktivieren.

## Statische Seiten bearbeiten

Im Tab **Seiten** können Administratoren die HTML-Dateien `landing`, `impressum`, `lizenz` und `datenschutz` anpassen. Über das Untermenü wird die gewünschte Seite ausgewählt und im **Trumbowyg**-Editor bearbeitet. Zusätzlich stehen eigene UIkit-Blöcke zur Verfügung, etwa ein Hero-Abschnitt oder eine Card. Mit **Speichern** werden die Änderungen im Ordner `content/` abgelegt. Die Schaltfläche *Vorschau* zeigt den aktuellen Stand direkt im Modal an. Alternativ kann der Editor weiterhin über `/admin/pages/{slug}` aufgerufen werden.
