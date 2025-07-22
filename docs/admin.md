# Administration

Die Administrationsoberfl\u00e4che erreichen Sie \u00fcber `/admin/events` (kurz `/admin`) nach einem erfolgreichen Login. Jeder Tab besitzt eine eigene Route:

1. **Events** – erreichbar über `/admin/events`.
2. **Event Configuration** – `/admin/event/settings`.
3. **Catalogs** – `/admin/catalogs`.
4. **Edit Questions** – `/admin/questions`.
5. **Teams/People** – `/admin/teams`.
6. **Summary** – `/admin/summary`.
7. **Results** – `/admin/results`.
8. **Statistics** – `/admin/statistics`.
9. **Pages** – `/admin/pages` (nur Administratoren).
10. **Administration** – `/admin/management` (nur Administratoren).
Im Tab "Administration" lassen sich JSON-Sicherungen exportieren und bei Bedarf wiederherstellen.
Der Statistik-Tab listet jede Antwort mit Name, Versuch, Katalog, Frage, Antwort, Richtig-Status und optionalem Beweisfoto. Über ein Auswahlfeld lassen sich die Daten nach Teams oder Personen filtern.

Weitere Funktionen wie der QR-Code-Login oder der Wettkampfmodus lassen sich in der Datei `data/config.json` aktivieren.

## Statische Seiten bearbeiten

Im Tab **Seiten** können Administratoren die HTML-Dateien `landing`, `impressum`, `lizenz` und `datenschutz` anpassen. Über das Untermenü wird die gewünschte Seite ausgewählt und im **Trumbowyg**-Editor bearbeitet. Zusätzlich stehen eigene UIkit-Blöcke zur Verfügung, etwa ein Hero-Abschnitt oder eine Card. Mit **Speichern** werden die Änderungen im Ordner `content/` abgelegt. Die Schaltfläche *Vorschau* zeigt den aktuellen Stand direkt im Modal an. Alternativ kann der Editor weiterhin über `/admin/pages/{slug}` aufgerufen werden.
