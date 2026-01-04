# Altes Marketing-System vs. neues Page-Editor-System

Diese Notiz fasst die verbindliche Trennung zwischen dem historischen Marketing-Setup und dem aktuellen Page-Editor-basierten CMS zusammen.

## A) Altes statisches Marketing-System (Legacy)

**Pfad-/Routing-Logik**

- Templates: `templates/marketing/*.twig`
- Slug- bzw. Domain-Aussteuerung steckt direkt in den Templates.

**Inhalte & Eigenschaften**

- Statische Landingpages
- Fest verdrahtete Menüs
- Fest verdrahtete SEO-Texte

**Status & Einschränkung**

- Als *Legacy* eingestuft
- **Soll nicht mehr für neue Pages genutzt werden**

## B) Neues CMS / Page-Editor-System ✅

**Pfad-/Routing-Logik**

- Admin-Oberfläche: `/admin/pages/content?namespace=…`

**Inhalte & Eigenschaften**

- Seiten aus dem Page-Editor
- Block-JSON als Content-Quelle
- Namespace-Design, Namespace-Menüs und Namespace-SEO

**Status & Vorgaben**

- Zielsystem für alle neuen Seiten
- Marketing-Templates dürfen hier nicht vorkommen

## Grundregel

- Alles unter `pages/` gehört ausschließlich zum neuen Page-Editor-System (B).
- Alles unter `templates/marketing/` darf für das neue System niemals greifen.
