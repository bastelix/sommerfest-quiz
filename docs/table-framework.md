# Table Framework

Die Listenansichten nutzen den Twig-Makro `qr_rowcards` und den JavaScript-`TableManager` zur Darstellung als Karten.

## Makro

`qr_rowcards(list_id, sortable=true, list_class='uk-hidden@m uk-list', tag='ul')` erzeugt einen leeren Container für Karten.

{% raw %}
```twig
{% from 'components/table.twig' import qr_rowcards %}
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
  columns: [
    { key: 'name', className: 'uk-text-bold', label: 'Team' },
    { key: 'points', className: 'uk-text-right', label: 'Punkte' }
  ],
  mobileCards: { container: document.getElementById('teamsCards') },
  sortable: true
});

manager.render(teams);
```

### Optionen

- `columns`: Spaltendefinitionen (`key`, `className`, `render`, `renderCard`, `editable`, `ariaDesc`).
- `mobileCards`: Objekt mit `container` und optional eigener `render`-Funktion.
- `sortable`: Aktiviert Drag-and-Drop für Karten.
- `onEdit`, `onDelete`, `onReorder`: Callback-Funktionen für Aktionen.

### Mobile Aktionsmenüs

Enthält eine Karte mehrere Elemente mit der Klasse `qr-action`, fasst der `TableManager` diese automatisch in einem aufklappbaren Menü zusammen.

### Barrierefreiheit

- `qr_rowcards` erzeugt eine Liste für mobile Kartenansichten.
- TableManager setzt `role="row"` und `role="gridcell"` für Karten und Zellen.
- Sortier-Handle und Lösch-Buttons erhalten ein `aria-label`.
- `uk-sortable` ermöglicht Drag-and-Drop innerhalb der Karten.
