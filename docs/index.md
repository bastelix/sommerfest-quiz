---
hide:
  - navigation
  - toc
---

# Willkommen zur edocs.cloud Dokumentation

**edocs.cloud** ist ein Multi-Tenant-Agentur-CMS auf Basis von Slim 4 und UIkit 3. Es kombiniert ein Quiz-/Event-System mit einem vollständigen CMS inkl. Wiki, Ticketsystem, News, Design-Token-System und MCP-Integration für AI-Agenten.

---

<div class="grid cards" markdown>

-   :material-sitemap:{ .lg .middle } **Architektur**

    ---

    Namespace-Konzept, Page-Modell, Custom Domains, Request-Lifecycle und Tech-Stack.

    [:octicons-arrow-right-24: Zum Architektur-Überblick](architecture.md)

-   :material-api:{ .lg .middle } **API & MCP**

    ---

    REST-API v1 Referenz und MCP-Tool-Dokumentation für AI-Agenten.

    [:octicons-arrow-right-24: Zur API-Referenz](api-v1-reference.md) · [:octicons-arrow-right-24: MCP-Tools](mcp-reference.md)

-   :material-view-module:{ .lg .middle } **Module**

    ---

    Wiki, Ticketsystem, Quiz/Events, News, Menü-System und Footer-Builder.

    [:octicons-arrow-right-24: Zu den Modulen](wiki.md)

-   :material-palette-swatch:{ .lg .middle } **Design-System**

    ---

    Design-Tokens, Presets, Custom CSS und Komponentenstile.

    [:octicons-arrow-right-24: Zum Design-System](design-system.md)

-   :material-shield-account:{ .lg .middle } **Administration**

    ---

    Rollen, Berechtigungen, Namespace-Management und Migrationen.

    [:octicons-arrow-right-24: Zur Administration](admin.md)

-   :material-rocket-launch:{ .lg .middle } **Betrieb**

    ---

    Docker-Setup, Deployment, Domain-Mapping, Umgebungsvariablen.

    [:octicons-arrow-right-24: Zum Deployment](deployment.md)

</div>

---

## Projekt-Steckbrief

| Eigenschaft | Details |
|---|---|
| **Typ** | Multi-Tenant-Agentur-CMS (PHP / Slim 4) |
| **Module** | Quiz/Events, Wiki, Tickets, News, Menüs, Footer-Builder |
| **Frontend** | UIkit 3, Responsive Design, Dark Mode |
| **Datenbank** | PostgreSQL 15 |
| **AI-Integration** | MCP-Server (OAuth 2.0), RAG-Chat-Service |
| **Billing** | Stripe (Checkout, Subscriptions, Webhooks) |
| **Deployment** | Docker / Docker Compose, GitHub Actions CI/CD |
| **API** | REST v1 mit Bearer-Token-Auth, 7 Scopes |
| **Dokumentation** | MkDocs Material, GitHub Pages |

## Highlights

- **Multi-Tenant** – Namespace-Isolation mit Custom-Domain-Mapping
- **MCP-Server** – 60+ Tools für AI-Agenten (Seiten, Menüs, Design, Wiki, Tickets, etc.)
- **Design-Tokens** – Visuelles System mit Presets, Custom CSS und Live-Mapping
- **Wiki mit Versionierung** – Integrierte Wissensdatenbank pro Seite
- **Ticketsystem** – Bug- und Aufgabenverwaltung mit Status-Workflow
- **Quiz-Engine** – 6 Fragetypen, KI-Teamnamen, Echtzeit-Ergebnisse

---

## Lizenz

Diese Anwendung steht unter einer proprietären Lizenz.
Alle Rechte liegen bei René Buske.
Weitere Informationen unter [Lizenz](lizenz.md).
