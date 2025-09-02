# Table Framework

Die Listenansichten im Admin-Bereich nutzen eine Kombination aus Twig-Makros und dem JavaScript-`TableManager`.
Die Makros `qr_table(headings, body_id, sortable=true)` und `qr_rowcards(list_id)` erzeugen die HTML-Struktur, während der
`TableManager` die Daten rendert, sortierbar macht und optional eine mobile Kartenansicht verwaltet.

## Twig-Makro einbinden

```twig
{% from 'components/table.twig' import qr_table, qr_rowcards %}
{{ qr_table([
  {'label': '', 'class': 'uk-table-shrink'},
  {'label': t('column_name'), 'class': 'uk-table-expand'}
], 'teamsList', true) }}
{{ qr_rowcards('teamsCards') }}
```

## TableManager konfigurieren

```js
const manager = new TableManager({
  tbody: document.getElementById('teamsList'),
  columns: [
    { key: 'name', className: 'uk-text-bold' },
    {
      render: t => { /* ... */ },          // eigene Zell-Renderfunktion
      renderCard: t => { /* ... */ },      // mobiles Rendering
      editable: true,
      ariaDesc: 'klicken zum Bearbeiten'
    }
  ],
  sortable: true,
  mobileCards: { container: document.getElementById('teamsCards') },
  onEdit: (cell, data) => { /* ... */ },
  onDelete: id => { /* ... */ },
  onReorder: list => { /* ... */ }
});
manager.render(data);
```

**Optionen**

- `tbody`: `tbody`-Element der Tabelle.
- `columns`: Felddefinitionen mit `key`, `className`, `render`, `renderCard`, `editable`, `ariaDesc`.
- `sortable`: Aktiviert Drag-and-Drop für Tabelle und Karten.
- `mobileCards`: Objekt mit `container` und optional eigener `render`-Funktion.
- `onEdit`, `onDelete`, `onReorder`: Callback-Funktionen für Aktionen.

Weitere Methoden:

- `render(list)`: Rendert die übergebene Datenliste.
- `bindPagination(el, perPage)`: Fügt Pagination hinzu.

## ARIA und Responsive

- `qr_table` erzeugt eine Desktop-Tabelle (`uk-visible@m`), `qr_rowcards` eine mobile Liste (`uk-hidden@m`).
- Zeilen erhalten `role="row"`, Zellen `role="gridcell"`.
- Editierbare Zellen bekommen per `aria-describedby` einen Screenreader-Hinweis.
- Das Attribut `uk-sortable` ermöglicht Drag-and-Drop auch in der mobilen Ansicht.
