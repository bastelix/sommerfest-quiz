# Projektüberblick

**edocs.cloud** ist ein Multi-Tenant-Agentur-CMS auf Basis von Slim 4 und UIkit 3. Die Plattform kombiniert ein Quiz-/Event-System für Firmenfeiern mit einem vollständigen Content-Management-System.

---

## Was ist edocs.cloud?

edocs.cloud wurde ursprünglich als Quiz-App für Firmenveranstaltungen entwickelt und ist inzwischen zu einer umfassenden CMS-Plattform gewachsen. Die Anwendung ermöglicht es Agenturen und Unternehmen, eigenständige Web-Auftritte mit Quiz-Funktionalität, Wiki, News, Ticketsystem und individuellen Designs zu betreiben.

## Kernfunktionen

### Content Management
- **Block-basierte Seiten** – Hero, Text, Feature-List, Testimonial und weitere Block-Typen
- **Wiki-Modul** – Markdown-basierte Wissensdatenbank mit Versionierung pro Seite
- **News-Modul** – Blog/Neuigkeiten mit RSS/Atom-Feeds
- **Menü-System** – Hierarchische Navigationsmenüs mit Slot-Zuweisungen (Header, Footer)
- **Footer-Builder** – Flexibler Footer mit 6 Block-Typen und Layout-Optionen

### Quiz & Events
- **6 Fragetypen** – Sortieren, Zuordnen, Multiple Choice, Swipe-Karten, Foto mit Texteingabe, „Hätten Sie es gewusst?"
- **Team-basiert** – QR-Code-Login, KI-generierte Teamnamen
- **Echtzeit-Ergebnisse** – Ranglisten, PDF-Export, Statistiken
- **Rätselwort** – Optionales Buchstabenpuzzle über alle Stationen

### Plattform
- **Multi-Tenant** – Namespace-basierte Isolation mit Custom-Domain-Mapping
- **Design-Token-System** – Individuelle Gestaltung pro Namespace (Farben, Typografie, Komponenten)
- **MCP-Server** – 60+ Tools für AI-Agenten (Claude, etc.) über OAuth 2.0
- **REST API v1** – Vollständige API mit Bearer-Token-Auth und Scope-System
- **Ticketsystem** – Aufgaben- und Bug-Verwaltung mit Status-Workflow

### Technik
- **Slim 4 + Twig 3** – Leichtgewichtiges PHP-Backend
- **UIkit 3** – Responsives Frontend mit Dark Mode
- **PostgreSQL 15** – Datenhaltung mit Schema-Isolation
- **Docker** – Production-ready Stack mit nginx, ACME/SSL, Adminer
- **Stripe** – Billing mit Checkout, Subscriptions und Webhooks
- **GitHub Actions** – CI/CD mit automatischem Versioning und Changelog

## Spielregeln (Quiz-Modul)

1. Jedes Team meldet sich über einen QR-Code oder manuell mit einem Namen an.
2. Ein Fragenkatalog kann Sortier-, Zuordnungs- und Multiple-Choice-Aufgaben enthalten.
3. Die Punktezahl hängt von der Anzahl korrekt gelöster Aufgaben ab.
4. Optional kann zu jeder Frage ein Buchstabe für ein Rätselwort vergeben werden.
5. Nachdem alle Fragen beantwortet wurden, wird die Gesamtwertung angezeigt. Bei aktivem Wettkampfmodus sind Wiederholungen nicht möglich.

## Weiterführende Dokumentation

- [Architektur-Überblick](architecture.md) – Systemdesign und Request-Lifecycle
- [API-Referenz](api-v1-reference.md) – REST-Endpoints
- [MCP-Tool-Referenz](mcp-reference.md) – AI-Agent-Integration
- [Deployment](deployment.md) – Docker-Setup und Betrieb
