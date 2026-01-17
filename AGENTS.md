Purpose

Dieses Dokument definiert verbindliche Leitplanken für die Entwicklung des Systems.

Die AGENTS.md dient Human-first als:
	•	Onboarding-Dokument für Entwickler
	•	Architektur- und Entscheidungsreferenz
	•	Verbindlicher Rahmen für AI-Code-Assistenten

Bei Unklarheiten gilt:

Nachfragen ist verpflichtend. Annahmen ohne Rückfrage sind unzulässig.

⸻

System Model – Überblick

Das System ist als modularer Monolith konzipiert und besteht aus drei fachlich klar getrennten Modulen:
	•	Events-Modul – Quiz-, Spiel- und Auswertungslogik
	•	Inhalte-Modul – Namespace-basierter Page Designer und Content-Verwaltung
	•	Admin-Modul – System-, Domain-, Abo- und Namespace-Verwaltung

Zentrale Konzepte
	•	Tenant (Instanz / Agentur)
	•	Technische Isolationseinheit
	•	Entspricht genau einem Datenbankschema
	•	Träger von Abos, Limits und globalen Einstellungen
	•	Namespace (Arbeitsraum / Projekt)
	•	Logische Nutzungseinheit innerhalb eines Tenants
	•	Kapselt Inhalte, Events, Design, Domains und Zugriffe
	•	Benutzer können mehreren Namespaces zugewiesen sein
	•	Abo
	•	Gilt auf Tenant-Ebene
	•	Definiert Limits (Namespaces, Benutzer, Features)

⸻

Modulgrenzen (Hard Rules)

Events-Modul

Verantwortlich für:
	•	Veranstaltungen (Events)
	•	Spielrunden / Sessions
	•	Teilnehmer (anonym, Gerät, Pseudonym, Teams)
	•	Fragenkataloge und Antworten
	•	Auswertung, Scoring, Rankings
	•	QR-Codes und Einstiegspunkte

Regeln:
	•	Das Events-Modul ist fachlich unabhängig vom Inhalte-Modul
	•	Keine direkte Abhängigkeit zu Seiten, SEO oder Design
	•	Namespace dient als Tenant-Grenze

⸻

Inhalte-Modul

Verantwortlich für:
	•	Seiten (Landingpages, Info-Seiten)
	•	Design / Themes / Tokens
	•	SEO (Meta, OG, Sitemap)
	•	Wiki- und Wissensartikel
	•	Medien / Assets
	•	Navigation und Footer
	•	Domain-Zuordnung
	•	Sprache / Locale

Regeln:
	•	Alle Inhalte sind immer Namespace-gebunden
	•	Keine Spiel- oder Auswertungslogik im Inhalte-Modul

⸻

Admin-Modul

Verantwortlich für:
	•	Domain- und DNS-Zuordnung
	•	Namespace-Lifecycle
	•	Globale Defaults
	•	Feature-Flags
	•	Systemrollen und Rechte
	•	Limits und Abo-Logik

Harte Grenze:

Das Admin-Modul enthält keinerlei fachliche Logik aus Events oder Inhalte.

⸻

Backend-Architektur

Technologiestack
	•	PHP 8.2
	•	SlimPHP als HTTP-Router
	•	PostgreSQL

Architekturprinzipien
	•	Modularer Monolith
	•	Explizite Abhängigkeiten
	•	Keine Framework-Magie

Slim-Nutzung
	•	Slim ist ausschließlich zuständig für Routing und HTTP-Schicht
	•	Quer­schnittslogik erfolgt über Middleware:
	•	Authentifizierung
	•	Namespace-Auflösung
	•	Locale
	•	Feature-Flags

Code-Struktur
	•	Controller: HTTP-spezifisch, keine Fachlogik
	•	Services: Fachlogik eines Moduls
	•	Repositories: Datenzugriff

⸻

Dependency Rules (Hard Rules)
	•	Kein Service Locator
	•	Keine statischen Globals
	•	Keine versteckten Helper
	•	Jede Abhängigkeit ist im Konstruktor sichtbar

⸻

Datenbank-Regeln

Tenant- & Namespace-Modell
	•	Ein Tenant = ein Datenbankschema
	•	Alle fachlichen Tabellen enthalten:

namespace_id UUID NOT NULL

Verbindliche Regel

Jede Query ohne Namespace-Scope gilt als Architekturfehler.

⸻

Migrationen (Pflicht)
	•	Jede Schemaänderung erfolgt ausschließlich über Migrationen
	•	Keine manuellen Änderungen in produktiven Datenbanken

AI-Code-Assistent MUSS IMMER liefern:
	1.	Forward-Migration
	2.	Rollback-Hinweis
	3.	Hinweis auf betroffene Tabellen
	4.	Hinweis auf Namespace- und Abo-Auswirkungen

⸻

Frontend-Regeln

CSS & UI
	•	UIKit 3 ist das primäre CSS-System
	•	Weitere CSS-Frameworks sind später möglich, jedoch nur explizit und isoliert

JavaScript
	•	Progressive Enhancement
	•	HTML funktioniert ohne JavaScript

HTML
	•	Semantisches HTML ist verpflichtend
	•	Keine unklassierten Wrapper-Divs
	•	UIKit-Klassen sind konsistent einzusetzen

Design Tokens
	•	CSS-Variablen als Design Tokens
	•	Namespace-spezifische Theme-Overrides erlaubt
	•	Keine Hardcoded Styles

⸻

AI-Assistant Contract

Der AI-Code-Assistent ist Teil des Entwicklungssystems und unterliegt folgenden Regeln:
	•	Bei Unklarheiten ist nachzufragen
	•	Annahmen müssen explizit gekennzeichnet sein
	•	Code wird schrittweise und überprüfbar geliefert
	•	Architektur- und Modulgrenzen sind strikt einzuhalten
	•	Migrationen sind immer mitzuliefern

⸻

Änderungsdisziplin
	•	Änderungen an dieser AGENTS.md erfolgen bewusst und versioniert
	•	Abweichungen müssen begründet werden

Diese AGENTS.md ist eine Leitplanke – kein Vorschlag.
