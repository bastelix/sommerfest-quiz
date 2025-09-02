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

Weitere Funktionen wie der QR-Code-Login mit Namensspeicherung oder der Wettkampfmodus lassen sich in der Datei `data/config.json` aktivieren.

## Statische Seiten bearbeiten

Im Tab **Seiten** können Administratoren die HTML-Dateien `landing`, `impressum`, `datenschutz` und `faq` anpassen. Über das Untermenü wird die gewünschte Seite ausgewählt und im **Trumbowyg**-Editor bearbeitet. Zusätzlich stehen eigene UIkit-Blöcke zur Verfügung, etwa ein Hero-Abschnitt oder eine Card. Mit **Speichern** werden die Änderungen im Ordner `content/` abgelegt. Die Schaltfläche *Vorschau* zeigt den aktuellen Stand direkt im Modal an. Alternativ kann der Editor weiterhin über `/admin/pages/{slug}` aufgerufen werden.

Wird die dunkle Hero-Vorlage (`uk-section-primary uk-light`) genutzt, sollte anschließend ein Abschnitt mit einer Hintergrundklasse wie `section--alt` eingefügt werden, damit der Seitenhintergrund wieder aufgehellt wird.

## Template-Makros

Wiederverwendbare Tabellen für die Administrations-Ansichten befinden sich in `templates/components/table.twig`. Die Makros werden in einer Twig-Vorlage eingebunden mit:

```twig
{% from 'components/table.twig' import qr_table, qr_rowcards %}
```

`qr_table(headings, body_id, sortable=true)` rendert die Desktop-Tabelle. Die Überschriften werden als Array mit `label` und optional `class` übergeben. Beispiel:

```twig
{{ qr_table([
  {'label': '', 'class': 'uk-table-shrink uk-text-center'},
  {'label': t('column_name'), 'class': 'uk-table-expand'},
  {'label': '', 'class': 'uk-table-shrink uk-text-center'}
], 'teamsList') }}
```

Für die mobile Darstellung steht `qr_rowcards(list_id)` zur Verfügung:

```twig
{{ qr_rowcards('teamsCards') }}
```

Weitere Admin-Views können die Makros entsprechend einbinden und anpassen.
