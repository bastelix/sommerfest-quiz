# Administration

Die Administrationsoberfläche erreichen Sie über `/admin/dashboard` (kurz `/admin`) nach einem erfolgreichen Login. Die Navigation ist in folgende Kategorien gegliedert:

* **Event**
  * **Startseite** – `/admin/dashboard`
  * **Events** – `/admin/events`
  * **Event-Konfiguration** – `/admin/event/settings`
* **Inhalte**
  * **Kataloge** – `/admin/catalogs`
  * **Fragen bearbeiten** – `/admin/questions`
  * **Seiten** – `/admin/pages` (nur Administratoren)
* **Teams**
  * **Teams/Personen** – `/admin/teams`
* **Auswertung**
  * **Übersicht** – `/admin/summary`
  * **Ergebnisse** – `/admin/results`
  * **Statistik** – `/admin/statistics`
* **Konto**
  * **Profil** – `/admin/profile`
  * **Abo** – `/admin/subscription`
* **Administration**
  * **Administration** – `/admin/management` (nur Administratoren)
  * **Subdomains** – `/admin/tenants` (nur Administratoren)
Im Tab "Administration" lassen sich JSON-Sicherungen exportieren und bei Bedarf wiederherstellen. Der Statistik-Tab listet jede Antwort mit Name, Versuch, Katalog, Frage, Antwort, Richtig-Status und optionalem Beweisfoto. Über ein Auswahlfeld lassen sich die Daten nach Teams oder Personen filtern.

Weitere Funktionen wie der QR-Code-Login oder der Wettkampfmodus lassen sich in der Datei `data/config.json` aktivieren.

## Statische Seiten bearbeiten

Im Tab **Seiten** können Administratoren die HTML-Dateien `landing`, `impressum`, `datenschutz` und `faq` anpassen. Über das Untermenü wird die gewünschte Seite ausgewählt und im **Trumbowyg**-Editor bearbeitet. Zusätzlich stehen eigene UIkit-Blöcke zur Verfügung, etwa ein Hero-Abschnitt oder eine Card. Mit **Speichern** werden die Änderungen im Ordner `content/` abgelegt. Die Schaltfläche *Vorschau* zeigt den aktuellen Stand direkt im Modal an. Alternativ kann der Editor weiterhin über `/admin/pages/{slug}` aufgerufen werden.
