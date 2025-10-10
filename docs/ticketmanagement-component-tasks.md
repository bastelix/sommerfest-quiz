# Aufgabenplan: Ticketmanagement-Komponente

Diese Aufgabenliste baut auf der Konzeptbeschreibung der Ticketmanagement-Komponente auf und strukturiert die Umsetzung in klar abgegrenzte Arbeitspakete. Sie dient als Ausgangspunkt für Roadmap-Planung, Ticketanlage (z. B. Jira, Linear) und Ressourcenzuteilung.

## 1. Grundlagen & Projektvorbereitung

- [ ] Feature-Flag `feature.ticketing_enabled` definieren und in Konfiguration verdrahten.
- [ ] Domain- und Modulordner im Backend vorbereiten (`src/App/Ticketing/` inkl. Service-, Controller-, Repository-Struktur).
- [ ] Gemeinsame UI-Terminologie mit Produkt/Support abstimmen (Statusnamen, Queue-Begriffe, CAPA-Vokabular) und Glossar anlegen.
- [ ] Security- und Datenschutzanforderungen (Audit-Log, Aufbewahrungsfristen, DSGVO) validieren und in Risikoliste dokumentieren.

## 2. Datenmodell & Migrationen

- [ ] Migrationen für Tabellen `tickets`, `ticket_events`, `ticket_comments`, `ticket_assignments`, `ticket_watchers`, `ticket_checklists`, `ticket_links`, `ticket_tags`, `ticket_attachments` anlegen.
- [ ] Trigger/Stored Procedures für `updated_at`, Ticketnummer-Sequenz und SLA-Zielzeiten implementieren.
- [ ] Seed-Skript für Default-Ticket-Typen, Prioritäten, Queues erstellen.
- [ ] Datenbank-Indizes für häufige Filter (`status`, `queue_id`, `priority`, `asset_id`) hinzufügen.

## 3. Backend-Services & Geschäftslogik

- [ ] `TicketService` (CRUD, Validierung, Pflichtfelder pro Ticket-Typ) implementieren.
- [ ] `TicketWorkflowService` (Statuswechsel, Transition-Guards, Checklisten-Pflichtfelder) implementieren.
- [ ] `TicketNotificationService` (E-Mail, Webhooks, Portal-Benachrichtigungen) implementieren.
- [ ] `TicketSlaService` (SLA-Berechnung, Eskalationsregeln, Countdown) implementieren.
- [ ] `TicketSearchService` (Filter, Pagination, Sortierung, Volltext) implementieren.
- [ ] `TicketIntegrationService` (Sync zu Fluke MET/TEAM & MET/CAL, generische Webhooks) implementieren.
- [ ] Background-Jobs für SLA-Monitoring und CAPA-Report-Exporte einplanen und Worker-Definition ergänzen.

## 4. API- & Controller-Schicht (Slim)

- [ ] Admin-Routen für Ticket-Listing, Detailansicht, Erstellung, Bearbeitung, Statuswechsel, Kommentare, Attachments, Watcher.
- [ ] Portal-Routen für Self-Service (Ticketliste und -erstellung) bereitstellen.
- [ ] Webhook-Endpoint für E-Mail-Parser implementieren (inkl. Sicherheitsprüfungen).
- [ ] Event-Routen/Webhooks für externe Integrationen abbilden.
- [ ] Middleware für Rollen- & Berechtigungsprüfung (ticket_viewer, ticket_agent, ticket_manager, portal_requester) ergänzen.

## 5. Frontend/Admin-UI (UIkit)

- [ ] Ticket-Tab ins Admin-Layout integrieren und Navigationseintrag „Tickets“ anlegen.
- [ ] Ticket-Board (Kanban + Listenansicht) mit Filterpanel, Quick-Edit und Bulk-Aktionen umsetzen.
- [ ] Ticket-Detailansicht (Metadaten, Aktivitäten, Checklisten, Anhänge, Historie, SLA-Indikator) gestalten.
- [ ] Modale Dialoge für Workflow-Aktionen (Schließen, Eskalieren, Reopen) implementieren.
- [ ] Konfigurationsoberflächen für Ticket-Typen, Queues, SLA-Kalender (inkl. UIkit Accordion) erstellen.

## 6. Techniker:innen-Oberfläche

- [ ] Mobile-optimierte Ansicht mit Checklistenbearbeitung, Zeiterfassung und Offline-Modus entwickeln.
- [ ] Kalenderintegration für Einsatzplanung (Read/Write) anbinden.
- [ ] Synchronisationslogik für Offline-Notizen und Upload-Warteschlange implementieren.

## 7. Portal/Self-Service

- [ ] Ticketformular mit Guided Questions, Pflichtfeld-Validierungen, QR-Code-Flow für Gerätesticker.
- [ ] Öffentliche Kommentar-/Statusanzeige inkl. Benachrichtigungslogik bauen.
- [ ] Downloadfunktion für Maßnahmenberichte (PDF/HTML) bereitstellen.

## 8. Kommunikation & Kollaboration

- [ ] Kommentar-Stream mit internen/externen Kanälen, @mentions und Stakeholder-Verwaltung implementieren.
- [ ] Automatische Protokollierung aller Events (Audit-Trail) sicherstellen.
- [ ] Integration mit bestehender Dokumentation (Wiki-Artikel-Vorschläge) hinzufügen.

## 9. Reporting & Analytics

- [ ] Dashboards für Durchlaufzeiten, SLA-Erfüllung, Ticketaufkommen implementieren.
- [ ] CAPA-Auswertungen (Risiko-/Wirksamkeitsbewertung) inklusive Exportfunktion bereitstellen.
- [ ] Data-Warehouse-Schnittstelle/Kafka-Events für Ticketänderungen definieren.

## 10. Integrationen & Automatisierung

- [ ] API-Clients für Fluke MET/TEAM & MET/CAL einbinden und Synchronisationsjobs planen.
- [ ] Webhook-/Event-System für externe Tools (Teams, Slack) aufsetzen.
- [ ] Regel-Engine („wenn Gerät X + Priorität Hoch → Queue Y + Prüfliste Z“) implementieren.

## 11. Sicherheit & Compliance

- [ ] Rollen-/Rechtemodell technisch enforced (inkl. Portal-Zugänge, CSRF, Rate-Limiting).
- [ ] Attachment-Upload mit Virenprüfung und Content-Type-Validierung ausstatten.
- [ ] DSGVO-konforme Lösch-/Anonymisierungsprozesse definieren und automatisieren.
- [ ] Audit-Log-Integration überprüfen und Sentry-Alerts konfigurieren.

## 12. Tests & Qualitätssicherung

- [ ] Unit-Tests für Services (Workflow, SLA, Notifications, Regel-Engine).
- [ ] Integrationstests für Controller-Flows, IMAP/Webhook-Verarbeitung und Portalzugriff erstellen.
- [ ] End-to-End-Tests (Playwright/Cypress) für Ticketanlage, Statuswechsel, Portal-Interaktion bauen.
- [ ] Performance-Tests für Listen, SLA-Jobs und Massen-Uploads durchführen.
- [ ] Security-Tests (Berechtigungen, CSRF, Uploads, Rate-Limiting) automatisieren.

## 13. Rollout & Enablement

- [ ] Feature-Toggle-Plan erstellen (Pilotmandanten, Staffeln). 
- [ ] Schulungsmaterialien für Service-Teams und Portalnutzer:innen vorbereiten.
- [ ] Monitoring-Dashboards für SLA-Verstöße und Job-Fehler aufsetzen.
- [ ] Feedback-Kanäle (Support, Produktboard) definieren und Prozesse für kontinuierliche Verbesserungen etablieren.
