# MET/CAL Migration: Checkliste

## Überblick
Sieben Schritte von der Discovery-Phase bis zur Nachbetreuung – als Ergänzung zum calServer Landingpage-Teaser und zur Markdown-Version.

### 1. Discovery & Scope
- Aktuelle MET/CAL- und MET/TEAM-Versionen sowie Integrationen erfassen.
- Stakeholder dokumentieren: Kalibrierleitung, IT-Betrieb, Qualitätsmanagement, externe Partner.
- Erfolgskriterien definieren (Verfügbarkeit, Reporting, regulatorische Anforderungen).

### 2. Datenbewertung
- Beispieldaten für Verfahren, Geräte, Zertifikate und Anhänge exportieren.
- Feldzuordnungen von MET/CAL zu calServer inklusive Custom-Feldern dokumentieren.
- Datenqualität prüfen (fehlende Kalibrierungen, verwaiste Assets, inkonsistente Toleranzen).

### 3. Infrastruktur vorbereiten
- Hosting-Modell festlegen (calServer Cloud oder dedizierter Tenant).
- Authentifizierung und Berechtigungen (Azure AD, Google Workspace, lokale Konten) klären.
- Backup-, Aufbewahrungs- und Verschlüsselungsvorgaben mit IT-Security abstimmen.

### 4. Migrationsplan
- Freeze-Fenster und Kommunikationsplan für Stakeholder abstimmen.
- Synchronisationsregeln für Hybridbetrieb vorbereiten.
- Validierungsskripte für Zertifikate, Asset-Status und Guardband-Berechnungen definieren.

### 5. Dry Run
- Sandbox-Daten importieren und End-to-End-Prozesse testen.
- Generierte Zertifikate auf Layout, Sprache und Compliance prüfen.
- Abweichungen dokumentieren und Maßnahmen priorisieren.

### 6. Go-Live
- Finalen Datenimport durchführen und Integrationen aktivieren.
- Smoke-Tests mit Pilotanwender:innen (Kalender, Tickets, Reporting) abschließen.
- Rollback-Kriterien dokumentieren und Sicherungen verifizieren.

### 7. Nachbetreuung
- Systemmetriken überwachen (Sync-Queues, Laufzeiten, Speicher).
- Feedback aus Qualität und Betrieb sammeln und Maßnahmen priorisieren.
- SOPs und Schulungsmaterialien auf calServer-Prozesse aktualisieren.

---

## MET/CAL migration checklist (English)
Seven stages from discovery to post go-live follow-up. Mirrors the inline content on the calServer landing page and the Markdown download.

### 1. Discovery & Scope
- Confirm current MET/CAL and MET/TEAM versions and active integrations.
- Document stakeholders: calibration lead, IT operations, quality management, external partners.
- Define success criteria (uptime targets, reporting goals, regulatory requirements).

### 2. Data assessment
- Export representative procedures, instruments, certificates, and attachments.
- Map MET/CAL fields to calServer attributes, including custom fields and units.
- Identify data quality gaps (missing calibrations, orphaned assets, inconsistent tolerances).

### 3. Infrastructure preparation
- Decide on hosting (managed calServer cloud or dedicated tenant).
- Review authentication setup (Azure AD, Google Workspace, local accounts).
- Align backup, retention, and encryption controls with IT security policies.

### 4. Migration plan
- Schedule the freeze window and stakeholder communications.
- Configure sync rules for hybrid operations if MET/CAL stays active.
- Prepare validation scripts for certificates, asset statuses, and guardband calculations.

### 5. Dry run
- Import a sandbox dataset and validate end-to-end workflows.
- Review generated certificates for formatting, language, and compliance notes.
- Capture remediation actions for data mismatches or missing attachments.

### 6. Go-live
- Run the final data load and enable integrations (API, email ingestion, document storage).
- Complete smoke tests with pilot users covering scheduling, tickets, and reporting.
- Document rollback criteria and confirm backups before broader access.

### 7. Post go-live follow-up
- Monitor system health (sync queues, job runtimes, storage consumption).
- Gather feedback from quality and operations teams and prioritise quick wins.
- Update SOPs and training materials to reflect calServer workflows.
