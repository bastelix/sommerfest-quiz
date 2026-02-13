# Harmonisierung: Menü-Editor ← Footer-Block-Editor UX-Patterns

## Analyse: Ist-Zustand

### Footer-Block-Editor (kundenfreundlicher)
| Merkmal | Umsetzung |
|---|---|
| **Darstellung** | Kompakte Block-Cards mit farbigen Typ-Icons (M/T/S/C/N/H), Titel und Meta-Zusammenfassung |
| **Bearbeitung** | Inline-Editing per Pencil-Button – Editbereich klappt unterhalb der Card auf |
| **Drag & Drop** | UIKit Sortable mit SVG-Dot-Grid-Handle, Cross-Column-Support |
| **Feedback** | `UIkit.notification()` Toast-Meldungen (non-blocking, top-right) |
| **Vorlagen** | Schnellvorlagen (Business, Minimal, Kontakt-Fokus) per Klick |
| **Layout-Wahl** | Visuelle SVG-Buttons für Layout-Varianten |
| **Preview** | Integrierte Live-Vorschau mit Toggle-Button |
| **Rich-Text** | TipTap-Editor für Text/HTML-Blocks |
| **Progressive Disclosure** | Nur relevante Felder je Block-Typ sichtbar |

### Menü-Editor – Tabelle (`marketing-menu-admin.js`)
| Merkmal | Umsetzung |
|---|---|
| **Darstellung** | Volle Tabelle mit 12 Spalten – alle Felder gleichzeitig sichtbar |
| **Bearbeitung** | Jede Zelle ist immer ein Input-Feld, kein Lese-/Bearbeitungsmodus |
| **Drag & Drop** | Eigene Implementierung mit UIKit-Icon-Button als Handle |
| **Feedback** | Persistenter Alert-Banner über der Tabelle (blockiert Sicht) |
| **Vorlagen** | Keine (nur KI-Generierung) |
| **Preview** | Keine (nur im Tree-Modus) |
| **Validierung** | Inline-Fehleranzeige pro Feld, umfassende Cross-Validierung |
| **Progressive Disclosure** | Keine – kognitive Überladung durch 12 sichtbare Spalten |

### Menü-Editor – Baum (`marketing-menu-tree.js`)
| Merkmal | Umsetzung |
|---|---|
| **Darstellung** | Hierarchischer Baum mit Einrückung per `--menu-depth` |
| **Bearbeitung** | Basis (Label, Href) sichtbar; Erweitert (Layout, Icon, Locale, SEO) hinter Zahnrad-Button |
| **Drag & Drop** | Per-Ebene Drag & Drop mit ARIA-Attributen |
| **Feedback** | Persistenter Alert-Banner |
| **Batch-Speicherung** | "Alle speichern" / "Abbrechen" statt Einzel-Speicherung |
| **Preview** | Sidebar-Preview-Panel |
| **Barrierefreiheit** | ARIA roles (tree, treeitem), aria-level, aria-expanded, tabindex |
| **Progressive Disclosure** | Teilweise vorhanden (Basic/Advanced split) |

---

## Harmonisierungs-Vorschläge

Die folgenden Änderungen übernehmen die bewährten UX-Patterns des Footer-Editors in den Menü-Tree-Editor, um ein konsistentes Benutzererlebnis zu schaffen.

### 1. Toast-Feedback statt persistenter Banner
**Was:** `setFeedback()` in `marketing-menu-tree.js` soll bei Erfolgs-/Fehlermeldungen `UIkit.notification()` nutzen statt den persistenten Alert-Banner.
**Warum:** Der Footer-Editor zeigt Feedback als kurze, nicht-blockierende Toast-Meldungen (top-right). Der persistente Banner im Menü-Editor verdeckt Inhalte und erfordert manuelles Scrollen.
**Dateien:** `public/js/marketing-menu-tree.js`

### 2. Layout-Badge auf Tree-Nodes
**Was:** Farbige Layout-Badges (Link/Dropdown/Mega/Spalte) analog zu den Block-Typ-Icons des Footer-Editors auf jeder Tree-Node anzeigen.
**Warum:** Der Footer-Editor zeigt den Block-Typ sofort visuell erkennbar. Im Tree-Editor ist das Layout erst nach Klick auf "Erweitert" sichtbar.
**Dateien:** `public/js/marketing-menu-tree.js`

### 3. Inline-Summary für Tree-Nodes
**Was:** Jede Tree-Node zeigt im geschlossenen Zustand eine kompakte Zusammenfassung: Label + Href (gekürzt) + Layout-Badge + Status-Indikatoren (Aktiv/Extern/Startseite).
**Warum:** Analog zum `block-card__summary` im Footer-Editor. Der Nutzer sieht den Zustand jedes Eintrags auf einen Blick, ohne Felder aufklappen zu müssen.
**Dateien:** `public/js/marketing-menu-tree.js`

### 4. Kompaktere Input-Darstellung mit Lese-/Bearbeitungsmodus
**Was:** Label und Href im Tree-Editor standardmäßig als Text anzeigen (Lese-Modus). Erst bei Klick auf den Node oder Pencil-Button werden Input-Felder sichtbar (Bearbeitungsmodus).
**Warum:** Footer-Cards zeigen Titel/Meta als Text und schalten erst auf Klick in den Bearbeitungsmodus um. Das reduziert visuelle Komplexität.
**Dateien:** `public/js/marketing-menu-tree.js`

### 5. Drag-Handle als SVG-Dot-Grid
**Was:** Den UIKit-`table`-Icon-Button durch ein SVG-Dot-Grid (wie im Footer-Editor) ersetzen.
**Warum:** Konsistente visuelle Sprache. Das Dot-Grid ist ein universell erkanntes Drag-Affordance-Pattern.
**Dateien:** `public/js/marketing-menu-tree.js`

### 6. Schnellvorlagen für Menü-Strukturen
**Was:** Preset-Buttons analog zu den Footer-Schnellvorlagen: z.B. "Standard-Navigation" (Home, Über uns, Kontakt), "E-Commerce" (Shop, Warenkorb, Konto), "Minimal" (Home, Impressum).
**Warum:** Der Footer-Editor ermöglicht mit einem Klick eine sinnvolle Grundstruktur. Der Menü-Editor hat nur die KI-Generierung, aber keine schnellen manuellen Templates.
**Dateien:** `public/js/marketing-menu-tree.js`, `templates/admin/navigation/menus.twig`

### 7. Status-Indikatoren vereinheitlichen
**Was:** Aktiv/Inaktiv-Darstellung im Tree-Editor wie im Footer-Editor (opacity 0.5 für inaktive Einträge, farbige Badges für Status).
**Warum:** Im Footer-Editor wird `block-card--inactive` mit `opacity: 0.5` dargestellt. Im Tree-Editor wird nur das Icon gewechselt (eye/ban), der visuelle Unterschied ist subtiler.
**Dateien:** `public/js/marketing-menu-tree.js`

---

## Umsetzungsreihenfolge

| Schritt | Änderung | Aufwand |
|---|---|---|
| 1 | Toast-Feedback (notification statt Banner) | Klein |
| 2 | Layout-Badges auf Tree-Nodes | Klein |
| 3 | Inline-Summary (Label + Href-Kurzform + Badges) | Mittel |
| 4 | SVG-Dot-Grid Drag-Handle | Klein |
| 5 | Status-Indikatoren (opacity + Badges) | Klein |
| 6 | Lese-/Bearbeitungsmodus für Inputs | Mittel |
| 7 | Schnellvorlagen/Presets | Mittel |

Alle Änderungen betreffen primär `marketing-menu-tree.js` und das zugehörige Template. Die API-Schicht und das Backend bleiben unverändert.
