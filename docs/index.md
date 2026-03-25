---
hide:
  - navigation
  - toc
---

# Willkommen zur edocs Dokumentation

Das **edocs** ist eine Web-App für Veranstaltungen wie Sommerfeste und Firmenevents.
Die Anwendung basiert auf dem Slim Framework und nutzt UIkit3 für ein responsives Design.
Fragenkataloge, Teilnehmer und Ergebnisse werden in einer PostgreSQL-Datenbank gespeichert
und können per JSON importiert oder exportiert werden.

---

<div class="grid cards" markdown>

-   :material-book-open-variant:{ .lg .middle } **Handbuch**

    ---

    Spielablauf, Spielregeln und häufige Fragen rund um das edocs.

    [:octicons-arrow-right-24: Zum Handbuch](about.md)

-   :material-shield-account:{ .lg .middle } **Administration**

    ---

    Rollen, Berechtigungen, Mandantenverwaltung und Migrationen.

    [:octicons-arrow-right-24: Zur Administration](admin.md)

-   :material-api:{ .lg .middle } **Technik**

    ---

    REST-API-Referenz, Architektur-Entscheidungen und ADRs.

    [:octicons-arrow-right-24: Zur API-Referenz](api-v1-reference.md)

-   :material-palette-swatch:{ .lg .middle } **Entwicklung**

    ---

    Design-Tokens, Namespace-System, CSS-Audit und Block-Editor.

    [:octicons-arrow-right-24: Zur Entwicklung](marketing-design-tokens.md)

</div>

---

## Projekt-Steckbrief

| Eigenschaft | Details |
|-------------|---------|
| **Typ** | Multi-Tenant-Webanwendung (PHP / Slim Framework) |
| **Einsatzgebiet** | Quiz-Rallyes für Firmenfeiern und Veranstaltungen |
| **Fragetypen** | Sortieren, Zuordnen, Multiple Choice, Swipe-Karten, Foto mit Texteingabe, „Hätten Sie es gewusst?" |
| **Frontend** | UIkit3, responsives Design, Dark Mode |
| **Datenbank** | PostgreSQL mit Schema-Isolation pro Mandant |
| **Deployment** | Docker / Docker Compose |
| **API** | REST-API mit JSON Import/Export |
| **Authentifizierung** | QR-Code-Login, Session-basiert |

## Highlights

- **Flexibel einsetzbar** – Kataloge im JSON-Format lassen sich einfach austauschen.
- **Sechs Fragetypen** – Sortieren, Zuordnen, Multiple Choice, Swipe-Karten, Foto mit Texteingabe und „Hätten Sie es gewusst?"-Karten.
- **QR-Code-Login & Dunkelmodus** – Komfortables Spielen auf allen Geräten.
- **Multi-Tenant** – Mehrere Organisationen gleichzeitig bedienbar, jeder Mandant mit eigener Subdomain und eigenem DB-Schema.
- **Persistente Speicherung** – PostgreSQL mit automatischen Migrationen.

---

## Lizenz

Diese Anwendung steht unter einer proprietären Lizenz.
Alle Rechte liegen bei René Buske.
Weitere Informationen unter [Lizenz](lizenz.md).
