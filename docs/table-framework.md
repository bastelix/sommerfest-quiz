# Table Framework

Die Listenansichten im Admin-Bereich nutzen Twig-Makros und den JavaScript-`TableManager`.

## Makros

Die Makros erzeugen das HTML-Grundgerüst:

- `qr_table(headings, body_id, sortable=true)`
- `qr_rowcards(list_id)`

{% raw %}
```twig
{% from 'components/table.twig' import qr_table, qr_rowcards %}
{{ qr_table([
  {'label': 'Team', 'class': 'uk-table-expand'},
  {'label': 'Punkte', 'class': 'uk-table-shrink uk-text-right'}
], 'teamsBody', true) }}
{{ qr_rowcards('teamsCards') }}
```
{% endraw %}

## TableManager

```js
import TableManager from '/js/table-manager.js';

const teams = [
  { id: 1, name: 'Team Alpha', points: 0 },
  { id: 2, name: 'Team Beta', points: 0 }
];

const manager = new TableManager({
  tbody: document.getElementById('teamsBody'),
  columns: [
    { key: 'name', className: 'uk-text-bold' },
    { key: 'points', className: 'uk-text-right' }
  ],
  sortable: true,
  mobileCards: { container: document.getElementById('teamsCards') }
});

manager.render(teams);
```

### Optionen

- `tbody`: `tbody`-Element der Tabelle.
- `columns`: Spaltendefinitionen (`key`, `className`, `render`, `renderCard`, `editable`, `ariaDesc`).
- `sortable`: Aktiviert Drag-and-Drop für Tabelle und Karten.
- `mobileCards`: Objekt mit `container` und optional eigener `render`-Funktion.
- `onEdit`, `onDelete`, `onReorder`: Callback-Funktionen für Aktionen.

### Methoden

- `render(list)`: Rendert die übergebene Datenliste.
- `addRow(item)`: Fügt eine Zeile hinzu.
- `bindPagination(el, perPage)`: Fügt Pagination hinzu.

## Barrierefreiheit

- `qr_table` erzeugt eine Desktop-Tabelle (`uk-visible@m`), `qr_rowcards` eine mobile Liste (`uk-hidden@m`).
- TableManager setzt `role="row"` und `role="gridcell"` für Zeilen und Zellen.
- Sortier-Handle und Lösch-Buttons erhalten ein `aria-label`.
- Editierbare Zellen bekommen `tabindex="0"` und über `aria-describedby` einen Screenreader-Hinweis.
- `uk-sortable` ermöglicht Drag-and-Drop in beiden Ansichten.
