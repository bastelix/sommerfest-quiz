# Richtlinien zur Worttrennung im Frontend

Damit **lange Wörter im HTML** bei einem Zeilenumbruch automatisch korrekt getrennt werden, können folgende CSS- und HTML-Regeln verwendet werden.

## 1. CSS: Automatisches Umbrechen langer Wörter

### a) `word-break`

```css
word-break: break-all;
```

* Trennt Wörter an beliebiger Stelle, wenn nötig.

### b) `overflow-wrap` / `word-wrap`

```css
overflow-wrap: break-word;
/* oder: word-wrap: break-word; */
```

* Bricht lange Wörter nur um, wenn erforderlich.
* **Empfohlen**: `overflow-wrap: break-word;` für natürliches Verhalten.

## 2. HTML: Optionale Trennstellen setzen

### a) Weiche Trennstellen mit `&shy;`

```html
Super&shy;kalifragilistik&shy;expialigetisch
```

Beim Zeilenumbruch wird dann an dieser Stelle getrennt.

## 3. Beispiel

**CSS-Lösung (empfohlen):**

```html
<div class="text">
  EinSuperExtremLangewortOhneLeerzeichenDasGetrenntWerdenSoll
</div>
```

```css
.text {
  overflow-wrap: break-word;
}
```

## 4. Spezialfall: Silbentrennung

Für sprachspezifische Trennung kann das CSS-Attribut `hyphens` genutzt werden:

```css
.text {
  hyphens: auto;
  overflow-wrap: break-word;
}
```

Im HTML sollte das Attribut `lang` gesetzt werden:

```html
<div class="text" lang="de">
  Silbentrennungsbeispiel
</div>
```

## Zusammenfassung

* Allgemein:
  ```css
  overflow-wrap: break-word;
  ```
* Echte Silbentrennung:
  ```css
  hyphens: auto;
  overflow-wrap: break-word;
  ```
* Manuelle Trennstellen mit `&shy;` im HTML.

