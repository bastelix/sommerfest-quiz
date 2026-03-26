# Häufige Fragen (FAQ)

## Allgemein

### Was ist edocs.cloud?
edocs.cloud ist ein Multi-Tenant-Agentur-CMS mit integrierter Quiz-Engine, Wiki, Ticketsystem, News-Modul und MCP-Server für AI-Agenten. Es basiert auf Slim 4 (PHP) und UIkit 3.

### Für wen ist edocs.cloud gedacht?
Für Agenturen und Unternehmen, die eigenständige Web-Auftritte mit Quiz-Funktionalität, Wissensdatenbank und individuellen Designs betreiben möchten. Jeder Mandant (Namespace) erhält einen isolierten Bereich mit eigener Domain.

---

## Quiz & Events

### Wie starte ich ein Quiz?
Als Admin: Event anlegen → Fragenkatalog erstellen → Teams anlegen → QR-Codes generieren. Teilnehmer scannen den QR-Code und werden automatisch zum passenden Katalog geleitet.

### Funktioniert das Quiz auf dem Smartphone?
Ja. Die Anwendung ist vollständig responsive (UIkit 3) und für Smartphone, Tablet und Desktop optimiert.

### Welche Fragetypen gibt es?
- **Sortieren** – Elemente in die richtige Reihenfolge bringen
- **Zuordnen** – Elemente korrekt zuordnen (Drag & Drop)
- **Multiple Choice** – Eine oder mehrere richtige Antworten
- **Swipe-Karten** – Wisch-Geste für richtig/falsch
- **Foto mit Texteingabe** – Bild anzeigen, Antwort eintippen
- **„Hätten Sie es gewusst?"** – Informationskarte mit Auflösung

### Wie bediene ich Drag & Drop?
Halte ein Element gedrückt und ziehe es an die gewünschte Stelle.

### Gibt es einen Dunkelmodus?
Ja. Der Wechsel erfolgt über `data-theme` auf dem `<body>`-Element und wird in `localStorage` gespeichert. Das System unterstützt auch die automatische Erkennung der Betriebssystem-Einstellung.

### Was passiert mit den Ergebnissen?
Ergebnisse werden in der Datenbank gespeichert und können als PDF, CSV oder JSON exportiert werden. Eine Rangliste zeigt die besten Platzierungen.

---

## CMS & Module

### Wie funktioniert das Wiki?
Jede CMS-Seite kann ein eigenes Wiki aktivieren. Artikel werden in Markdown verfasst, automatisch versioniert und über einen Status-Workflow (draft → published → archived) gesteuert. Siehe [Wiki-Modul](wiki.md).

### Wie funktioniert das Ticketsystem?
Tickets können an Wiki-Artikel oder CMS-Seiten referenziert werden und durchlaufen den Workflow: open → in_progress → resolved → closed. Siehe [Ticketsystem](tickets.md).

### Wie funktionieren News?
News-Artikel sind an CMS-Seiten gebunden und werden als Blog mit RSS/Atom-Feeds bereitgestellt. Siehe [News-Modul](news.md).

### Was sind Namespaces?
Ein Namespace ist die zentrale Multi-Tenant-Einheit. Jeder Namespace isoliert Inhalte, Design, Events und Konfiguration. Custom Domains werden pro Namespace zugeordnet – kein Subdomain-Routing. Siehe [Namespace-Management](namespace-management.md).

---

## API & MCP

### Gibt es eine API?
Ja. Die REST API v1 (`/api/v1`) bietet Endpoints für Pages, Menus, News, Events, Tickets und Design. Authentifizierung über Bearer-Token mit Scope-System. Siehe [API-Referenz](api-v1-reference.md).

### Was ist der MCP-Server?
Der MCP-Server (Model Context Protocol) stellt 60+ Tools bereit, über die AI-Agenten wie Claude alle CMS-Funktionen nutzen können. Authentifizierung über OAuth 2.0. Siehe [MCP-Tool-Referenz](mcp-reference.md).

### Wie verbinde ich Claude mit edocs.cloud?
Über die MCP-Connector-Funktion in Claude. Discovery über `/.well-known/oauth-authorization-server`, dann OAuth-Flow. Details: [MCP-Connector-Setup](mcp-connector-setup.md).

---

## Betrieb

### Wie starte ich edocs.cloud lokal?
```bash
cp sample.env .env
docker compose up --build -d
```
Das Entrypoint-Skript installiert Abhängigkeiten, legt die DB an und führt Migrationen aus. Details: [Deployment](deployment.md).

### Welche Datenbank wird verwendet?
PostgreSQL 15. SQLite wird nicht unterstützt. Tenant-Daten werden über Namespace-Spalten isoliert.

### Wie werden Migrationen ausgeführt?
```bash
php scripts/run_migrations.php
```
143+ SQL-Migrationen werden chronologisch ausgeführt. Im Docker-Container automatisch beim Start. Details: [Migrationen](migrationen.md).

---

## Entstehung

edocs.cloud ist das Ergebnis einer Zusammenarbeit zwischen menschlicher Erfahrung und künstlicher Intelligenz. Die Codebase wurde experimentell mit AI-Assistenten (OpenAI Codex, GitHub Copilot, Claude) entwickelt, wobei Ideen, Organisation und Praxiswissen von Menschen stammen.

Bei der Entwicklung standen im Fokus:

- **Barrierefreiheit** – Zugänglich für alle Nutzergruppen
- **Datenschutz** – DSGVO-konforme Datenverarbeitung
- **Performance** – Stabil auch bei vielen gleichzeitigen Teilnehmern
- **Geräteunabhängigkeit** – Smartphone, Tablet und Desktop
- **Offene Schnittstellen** – REST API und MCP-Server
