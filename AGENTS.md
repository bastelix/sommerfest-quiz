Mission & Evolution Goal

* Betreibe QuizRace als modularen Monolithen für Events, Inhalte und Administration mit klar getrennten Verantwortlichkeiten.
* Entwickle das System langfristig evolutiv: kleine, nachvollziehbare Änderungen statt großflächiger Umbauten.
* Priorisiere Stabilität, Wartbarkeit und sichere Weiterentwicklung vor Feature-Geschwindigkeit.
* Erweiterungen sollen den bestehenden Modulen folgen und keine neuen Kreuzabhängigkeiten erzeugen.

Core Architectural Principles

* Halte die Modulgrenzen strikt ein:
  * Events-Modul: Events, Sessions, Teilnehmer, Fragen, Antworten, Scoring, Rankings, QR-Einstiegspunkte.
  * Inhalte-Modul: Seiten, Design/Tokens, SEO, Medien, Navigation, Domain-Zuordnung, Locale.
  * Admin-Modul: Domains/DNS, Namespace-Lifecycle, globale Defaults, Feature-Flags, Rollen/Rechte, Limits/Abo-Logik.
* Implementiere Fachlogik ausschließlich in Services; Controller sind HTTP-spezifisch und enthalten keine Fachlogik.
* Nutze Repositories für Datenzugriff; Services orchestrieren Fachlogik.
* Setze auf explizite Abhängigkeiten: keine Service-Locator, keine statischen Globals, keine versteckten Helper.
* Verwende Slim ausschließlich für Routing und HTTP; Querschnittslogik läuft über Middleware.
* Bevorzuge klare, getestete, wartbare Lösungen vor cleveren Abkürzungen.

Agent Operating Rules

* Frage nach, wenn Anforderungen unklar, widersprüchlich oder unvollständig sind.
* Markiere Annahmen explizit, wenn Nachfragen nicht möglich sind.
* Ändere keine Architekturregeln ohne begründete Entscheidung und dokumentierte Motivation.
* Schreibe Code, Kommentare, Commit Messages und Dokumentation in Englisch.
* Verwende Dependency Injection; greife auf Superglobals nur in Controllern und Middleware zu.
* Vermeide eval, Short-Tags, var_dump/print_r im produktiven Code.
* Nutze keine neuen Frontend-Dependencies, die einen Build-Schritt erfordern.

System Boundaries & Entry Points

* Verändere Events-, Inhalte- und Admin-Logik nur innerhalb ihrer jeweiligen Module.
* Behandle Templates unter templates/pages/render.twig und templates/marketing/* als Marketing-Flächen mit Namespace-Design.
* Lasse Event-/Spiel-/Auswertungs-Seiten (z. B. results, ranking, summary, dashboard) unabhängig vom Marketing-Design.
* Bewahre die Namespace-Fallback-Logik für SEO und Seitenmodule (default namespace als Fallback).
* Bewahre die Datenisolation pro Tenant: ein Tenant ist ein Datenbankschema, Namespace ist die logische Einheit.
* Jede fachliche Tabelle enthält namespace_id UUID NOT NULL; jede Query muss einen Namespace-Scope haben.

Change Discipline

* Implementiere Schemaänderungen ausschließlich über neue Migrationen; modifiziere bestehende Migrationen nie.
* Liefere bei Schemaänderungen:
  * Forward-Migration
  * Rollback-Hinweis
  * Betroffene Tabellen
  * Auswirkungen auf Namespace- und Abo-Logik
* Halte Änderungen klein und nachvollziehbar; refactoriere nur mit klarer Begründung.
* Dokumentiere neue Konfigurationen in README.md und sample.env sowie in relevanten Compose-Dateien.
* Aktualisiere Tests und Fixtures, wenn Datenformate oder DOM-Erwartungen geändert werden.

Quality Gates

* Halte die Test-Suite grün und führe die Standard-Checks aus:
  * vendor/bin/phpcs
  * vendor/bin/phpstan analyse -c phpstan.neon.dist
  * vendor/bin/phpunit
  * python3 tests/test_html_validity.py
  * python3 tests/test_json_validity.py
  * node tests/test_competition_mode.js
  * node tests/test_results_rankings.js
  * node tests/test_random_name_prompt.js
  * node tests/test_onboarding_plan.js
  * node tests/test_onboarding_flow.js
  * node tests/test_login_free_catalog.js
  * node tests/test_catalog_smoke.js
  * node tests/test_catalog_autostart_path.js
  * node tests/test_shuffle_questions.js
  * node tests/test_team_name_suggestion.js
  * node tests/test_catalog_prevent_repeat.js
  * node tests/test_event_summary_switch.js
  * node tests/test_sticker_editor_save_events.js
  * node tests/test_media_filters.js
  * node tests/test_media_preview.js
* Nutze composer test für die vollständige Pipeline, wenn verfügbar.
* Stelle sicher, dass neue Features deterministische Tests haben und optionale Integrationen ohne Konfiguration sauber degradieren.

Long-Term Maintenance Rules

* Reduziere technische Schulden aktiv, aber nur mit klarer Nutzen-/Risiko-Abwägung.
* Depreziere APIs und Konfigurationen mit klarer Übergangsstrategie und dokumentierter Frist.
* Dokumentiere strukturelle Änderungen an Modulen, Datenmodellen und Schnittstellen nachvollziehbar.
* Verwende ImageUploadService-Qualitätskonstanten für Uploads; keine abweichenden Hardcodes.
