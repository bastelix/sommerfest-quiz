# CMS Design Routing: Ursachen und Fix-Plan

## Executive Summary

* Das CMS ist vorhanden, aber die Render-Pfade bleiben im Marketing-System gefangen.
* `/cms/pages/{slug}` und `/m/{slug}` instanziieren den `CmsPageController` ohne Design- oder Namespace-Kontext.
* Gespeicherte Namespace-Designs gelangen weder in das Frontend noch in die CMS-Preview.

## Technische Bruchstellen

1. **Fehlender Design-Context im Controller**
   * `CmsPageController` wird "roh" erstellt, ohne `PagesDesignService` oder Theme-Resolver.
   * Folge: `data-theme`, CSS-Tokens, Menüs und SEO werden nicht gesetzt.

2. **Getrennter Render-Pfad für Preview**
   * Admin-Preview rendert clientseitig korrekt, CMS-Preview/Frontend serverseitig neutral.

3. **Route-Resolver liefert Marketing-Controller**
   * `CmsPageRouteResolver` kann weiterhin Marketing statt CMS liefern, sodass das Namespace-Design ignoriert wird.

## Empfohlener Fix

1. **Design beim Rendern laden**
   * Namespace aus Request oder Resolver bestimmen und per `PagesDesignService` das Design beziehen.
   * Design explizit in das Twig-Rendering injizieren (`namespace`, `design`, Menü, SEO).

2. **Layout auf CMS-Design umstellen**
   * `templates/layout.twig`: `data-theme` und ähnliche Attribute direkt aus dem geladenen Namespace-Design ableiten.

3. **Preview und Frontend angleichen**
   * Preview-Routen auf denselben `CmsPageController` zeigen lassen (z. B. `/cms/preview/{pageId}`) statt separater Marketing-Pfade.

4. **Resolver straffen**
   * Für CMS-Seiten nur noch den CMS-Controller zurückgeben; Marketing bleibt auf explizite Pfade beschränkt (`/landing/*`, `/m/*`).

## Erfolgsindikatoren

* `/admin/pages/design` ändert sichtbar das Frontend-Theme.
* Preview und Frontend rendern identisch.
* `<html data-theme>` spiegelt das Namespace-Design wider.
* Menüs und SEO stammen aus den CMS-Konfigurationen, nicht aus Marketing-Defaults.
