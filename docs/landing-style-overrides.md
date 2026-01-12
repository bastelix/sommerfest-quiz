---
layout: default
title: "Landing-Seite: Styles"
---

## CSS-Variablen der Landing-Seite

Die Marketing-Seite nutzt eigene CSS-Variablen, um Farben für Text und Dropdown-Menüs festzulegen. Die Werte werden direkt im Template `templates/marketing/default.twig` innerhalb eines `:root`-Blocks gesetzt:

```twig
<style>
  :root {
    --topbar-text: #fff;
    --topbar-drop-bg: #0c86d0;
    --topbar-drop-border: rgba(255, 255, 255, 0.32);
  }
</style>
```

Diese Variablen steuern die Palette der Topbar sowie der Konfigurations-Dropdowns. Anpassungen an der Landing-Seite erfolgen über diese Variablen, damit alle Komponenten konsistent bleiben.
