# quizrace – Funktionsübersicht

quizrace ist eine vollständige SaaS-Plattform für interaktive Quiz-Events – von der Erstellung einzelner Fragenkataloge bis zum mandantenfähigen Betrieb mit eigenem Branding. Ob Firmen-Sommerfest, Teambuilding-Tag, Schulprojekt oder öffentliche Veranstaltung: quizrace liefert eine reibungslose Spielerfahrung auf jedem Gerät und gibt Veranstaltenden alle Werkzeuge an die Hand, um das Quiz vollständig ohne technisches Vorwissen durchzuführen. Das System ist vollständig selbst gehostet, DSGVO-konform und über Docker innerhalb weniger Minuten einsatzbereit.

---

## Inhaltsverzeichnis

1. [Quiz erstellen & verwalten](#1-quiz-erstellen--verwalten)
2. [Spielerlebnis & Gameplay](#2-spielerlebnis--gameplay)
3. [QR-Code-System & Einladungen](#3-qr-code-system--einladungen)
4. [Design & Branding](#4-design--branding)
5. [Teams & Teilnehmer](#5-teams--teilnehmer)
6. [Ergebnisse & Auswertung](#6-ergebnisse--auswertung)
7. [Live-Dashboard](#7-live-dashboard)
8. [CMS & Seiteninhalte](#8-cms--seiteninhalte)
9. [Medien-Bibliothek](#9-medien-bibliothek)
10. [Administration & Betrieb](#10-administration--betrieb)
11. [Technische Highlights](#11-technische-highlights)
12. [Einsatzszenarien](#12-einsatzszenarien)

---

## 1. Quiz erstellen & verwalten

### Fragenkataloge
- Unbegrenzt viele Kataloge pro Event; Kataloge sind sortierbar und einzeln freischaltbar
- Jeder Katalog hat Name, Beschreibung, optionalen Rätselbuchstaben für das Puzzle-Wort und eine eigene Design-Datei
- Fragen können per Drag & Drop neu angeordnet werden
- Fragen-Shuffle: Reihenfolge der Fragen wird pro Spielsitzung automatisch durchgemischt (optional)
- JSON-basierte Datenspeicherung ermöglicht einfachen Import/Export von Katalogen

### 6 Fragetypen

| Typ | Beschreibung |
|-----|-------------|
| **Multiple Choice** | Eine oder mehrere korrekte Antworten aus vorgegebenen Optionen |
| **Flip Cards** | Karten werden umgedreht, um Begriffe oder Bilder zu enthüllen |
| **Matching / Zuordnung** | Begriffe oder Aussagen einander zuordnen (Drag & Drop) |
| **Multi-Select** | Mehrere richtige Antworten müssen aus einer Liste ausgewählt werden |
| **Texteingabe** | Freitextantwort, die gegen eine Musterlösung geprüft wird |
| **Sortier- / Swipe-Karten** | Karten per Swipe in die richtige Kategorie einordnen |

### Multi-Event-Betrieb
- Beliebig viele Events parallel verwalten (Name, Startdatum, Enddatum, Beschreibung)
- Events können als Entwurf (draft) oder veröffentlicht (published) geschaltet werden
- Separate Konfiguration je Event: Login-Pflicht, Team-Modus, Foto-Upload, Wettkampfmodus u. v. m.

---

## 2. Spielerlebnis & Gameplay

### Spielmodi
- **Einzelspieler**: Jede Person spielt auf eigenem Gerät in eigenem Tempo
- **Team-Modus**: Ergebnisse werden Mannschaften zugerechnet; Team-Rangliste im Live-Dashboard
- **Wettkampfmodus**: Server-synchronisierter Ablauf – alle starten und antworten gleichzeitig

### Zeitdruck & Spannung
- **Countdown-Timer**: Jede Frage hat eine konfigurierbare Zeitbegrenzung; ablaufende Zeit wird visuell angezeigt
- **Zeitbasierte Punkte**: Neben Rohpunkten fließt Antwortgeschwindigkeit in einen Effizienz-Score ein
- **Puzzle-Wort**: Nach Abschluss des Quiz erscheint ein optionales Lösungswort, das aus Rätselbuchstaben der einzelnen Kataloge zusammengesetzt wird – ideal für mehrstufige Event-Spiele

### Einloggen & Beitritt
- **QR-Code-Login**: Teilnehmende scannen ihren persönlichen QR-Code mit der Kamera – kein Tippen nötig
- **Namenseingabe**: Alternativ freie Namenseingabe oder Auswahl aus zufällig generierten Namen
- **Zufallsnamen-Generator**: KI-gestützte, konfigurierbare Namenvorschläge (Locale, Domäne, Tonalität)
- **Passwortgeschützter Bereich**: Login-Pflicht und Whitelist-Funktion für geschlossene Veranstaltungen

### Foto-Upload
- Teilnehmende können während oder nach dem Quiz ein Foto hochladen
- DSGVO-konforme Einwilligungsabfrage vor dem Upload, wird protokolliert
- Fotos erscheinen in der Ergebnisliste und im Live-Dashboard

---

## 3. QR-Code-System & Einladungen

### QR-Code-Generierung
- QR-Codes für Kataloge, Teams und Events auf Knopfdruck erstellen
- Export als **PNG** oder **SVG** in frei wählbarer Größe (64–2048 px)
- Vollständig anpassbares Erscheinungsbild:
  - Vordergrund- und Hintergrundfarbe
  - Logo-Einbettung mit optionalem Punchout (weißer Freiraum um das Logo)
  - Abgerundete Ecken (Quiet-Zone-Rounding)
  - Separate Farbgebung für Team-, Katalog- und Event-QR-Codes

### Sticker & Print-Layout
- Konfigurierbares Drucklayout mit pixelgenauer Positionierung aller Elemente:
  - QR-Code-Position, Größe in Prozent
  - Beschreibungstext mit Position, Breite, Höhe, Schriftfarbe und -größe
  - Kopf- und Unterzeile, Katalogtitel
  - Eigenes Hintergrundbild pro Sticker-Template
- Schriftgrößen für Header, Subheader, Katalog und Beschreibung unabhängig einstellbar

### PDF-Einladungen
- Einladungs-PDFs mit QR-Code pro Team in einem Klick erstellen
- Freitext-Einladungsschreiben mit Platzhaltern (`[team]`, `[event_name]`, …)
- **Bulk-PDF**: Alle Team-Einladungen in einer einzigen PDF-Datei für den Druck

---

## 4. Design & Branding

### Namespace-basiertes Design-Token-System
Jeder Mandant (Namespace) verfügt über ein eigenes, vollständig unabhängiges Set an Design-Tokens, die als CSS-Variablen bereitgestellt werden. Änderungen wirken sich sofort auf alle Seiten und die Quiz-Oberfläche aus.

**Token-Kategorien:**

| Kategorie | Optionen |
|-----------|---------|
| `brand.primary / accent / secondary` | Beliebige Hex-Farbe |
| `layout.profile` | `narrow` · `standard` · `wide` |
| `typography.preset` | `modern` · `classic` · `tech` |
| `components.cardStyle` | `rounded` · `square` · `pill` |
| `components.buttonStyle` | `filled` · `outline` · `ghost` |

### Vorgefertigte Design-Presets
Sofort einsatzbereit, jederzeit anpassbar:

| Preset | Charakter |
|--------|-----------|
| **default** | Klares Blau, professionell und neutral |
| **aurora** | Leuchtendes Lila/Teal, modern und energetisch |
| **midnight** | Dunkles Theme, ideal für abendliche Events |
| **sunset** | Warme Orange-/Rottöne, einladend |
| **future-is-green** | Nachhaltig-grüne Farbwelt |
| **calserver** | Dezentes Corporate-Design |
| **calhelp** | Hilfreich-freundliche Blaupalette |

### Barrierefreiheit & Dark Mode
- Automatische WCAG-AA-Kontrastberechnung für alle Primärfarben
- Jedes Preset enthält separate Light- und Dark-Mode-Paletten
- Design-Tokens werden als native CSS-Variablen mit `[data-theme="dark"]`-Overrides ausgespielt

### Weiteres Branding
- **Custom CSS**: Freies CSS je Namespace, serverseitig sanitized
- **Quiz-spezifisch**: Logo, Hintergrundfarbe, Button-Farbe und Seiten-Titel separat konfigurierbar
- **Open-Graph-Bild**: Eigenes Vorschaubild für Social-Media-Sharing

---

## 5. Teams & Teilnehmer

### Team-Verwaltung
- Beliebig viele Teams pro Event anlegen, benennen und sortieren
- Team-Zuweisung zu einem oder mehreren Events
- Team-QR-Codes für reibungslosen Check-in ohne Tippeingabe

### KI-Teamnamen-Generator
- Automatisch generierte, einzigartige Teamnamens-Vorschläge aus KI-Modell + lokalem Lexikon
- Konfigurierbar nach:
  - Sprache/Locale (`de`, `en`)
  - Thematischen Domänen
  - Tonalität (ernst, witzig, kreativ, …)
  - Puffergröße (wie viele Namen im Voraus generiert werden)
- Warmup-Cache sorgt für sofortige Verfügbarkeit

### Spieler-Tracking & DSGVO
- Optionale Player-UID-Sammlung für geräteübergreifende Wiedererkennung
- Kontaktdaten-Opt-in mit Double-Opt-In-E-Mail
- Username-Blockliste: kategorisierte Sperrliste (NSFW, NS-Bezug, Beleidigungen) mit Admin-Oberfläche
- Foto-Einwilligung: explizite Zustimmung vor jedem Upload, unveränderlich protokolliert

---

## 6. Ergebnisse & Auswertung

### Detailliertes Ergebnis-Tracking
Für jeden Spieler und jede Frage werden gespeichert:
- Spieler, Team, Katalog, Versuchsnummer
- Anzahl richtige Antworten, Rohpunkte, Zeitbonus-Punkte, Endpunkte
- Antwortzeit, Effizienz-Score, Lösungswort, Foto und Einwilligungsstatus

### Rangliste & Awards
- Echtzeit-Leaderboard mit Platzierungen
- Medal-Awards (Gold / Silber / Bronze) für die Top 3
- Team-Gesamtrangliste (aufsummierte Einzelergebnisse)

### Auswertungs-Tools
- **Falsche-Antworten-Analyse**: Welche Fragen werden am häufigsten falsch beantwortet?
- **Fragen-Statistiken**: Korrektquote, Durchschnittszeit, Effizienz pro Frage
- **Ergebnis-Filter**: Filtern nach Event, Team, Spieler und Zeitraum
- **Charts & Grafiken** im Admin-Bereich

### Export
- **CSV-Download** (UTF-8 mit BOM, Excel-kompatibel) für alle Ergebnisse
- **PDF-Zusammenfassung** mit Rangliste und Team-Fotos
- **JSON-API** für Drittsystem-Integration

---

## 7. Live-Dashboard

Das Live-Dashboard ist die Kommandozentrale für Moderatoren und Zuschauer während der Veranstaltung.

### Ansichten & Module
- **Echtzeit-Rangliste**: Aktualisiert sich automatisch, konfigurierbare Refresh-Intervalle
- **QR-Code-Grid**: Alle Team-QR-Codes auf einer Seite – ideal für Projektion oder Ausdrucke
- **Mediengalerie**: Foto-Stream der Teilnehmer in Echtzeit
- **Sponsor-Modul**: Separate Dashboard-Variante mit eigenem Zugangs-Token für Sponsoren

### TV-Modus & Präsentation
- **TV-Modus** (fixed height): Optimiert für Großbildschirme und Beamer ohne Scrollbalken
- **Dark/Light-Mode**: Umschalten per Toggle, passend zur Veranstaltungsatmosphäre
- **Öffentlicher Share-Link**: Dashboard ohne Login für Zuschauer freigeben

---

## 8. CMS & Seiteninhalte

quizrace enthält ein vollständiges Content-Management-System für Marketing-Seiten, Landing Pages und Informationsseiten – alles innerhalb derselben Plattform.

### Seiten-Verwaltung
- Unbegrenzt viele Seiten pro Namespace mit hierarchischer Struktur (Parent-Child)
- Status-Workflow: `draft` → `published` → `archived`
- Drag & Drop-Sortierung im Admin-Menü
- Mehrsprachige Seitenvarianten (Hreflang-Unterstützung)
- Domain-spezifische Startseiten: verschiedene Domains zeigen unterschiedliche Seiten

### Modulares Inhaltssystem
Seiten setzen sich aus konfigurierbaren **Page Modules** zusammen (Typ + JSON-Config, positionierbar in `header`, `body`, `footer`). Neue Modul-Typen können zentral entwickelt und sofort auf beliebig vielen Seiten eingesetzt werden.

### SEO & Open Graph
Jede Seite hat eine unabhängige SEO-Konfiguration:
- `<title>`, Meta-Description, Canonical-URL
- Open-Graph-Titel, -Beschreibung und -Bild
- robots.txt-Direktiven
- JSON-LD Schema Markup
- Hreflang-Attribute
- Favicon pro Seite

### Weitere CMS-Module
- **Wiki / Knowledge-Base**: Artikel-System mit eigenem Routing und Bearbeitungsworkflow
- **News / Blog**: Index-Seite und Einzelartikel-Ansicht für Landing-Page-News
- **Navigation**: Menüstrukturen und Footer-Blöcke mit eigener Admin-Oberfläche
- **E-Mail-Templates**: Willkommen, Passwort-Reset, Einladung, Double-Opt-In – alle anpassbar
- **Newsletter-Integration**: Konfigurierbar für Brevo, Sendgrid und Mailchimp

### KI-gestützte Inhaltserstellung *(experimentell)*
- Automatische Generierung von Landing-Page-Texten per KI
- RAG-Chatbot (Retrieval-Augmented Generation) auf Basis der eigenen Wissensbasis

---

## 9. Medien-Bibliothek

### Unterstützte Dateiformate

| Kategorie | Formate |
|-----------|---------|
| Bilder | PNG, JPG/JPEG, WebP, SVG |
| Dokumente | PDF |
| Audio | MP3 |
| Video | MP4, WebM |

### Technische Eigenschaften
- Max. Dateigröße: **5 MB** pro Upload
- Max. Bildauflösung: **20 Megapixel** (größere Bilder werden automatisch skaliert)
- Automatische Orientierungskorrektur und JPEG-Qualitätsoptimierung (Imagick / GD)
- Getrennte Qualitätsstufen: Logo (80%), Sticker (90%), Fotos (70%)

### Organisation
- **Scopes**: `global` (plattformweit), `event` (eventbezogen), `project` (projektbezogen) – vollständige Datenisolation zwischen Bereichen
- Ordner-Struktur und Tagging-System
- Automatisches Scanning des Upload-Verzeichnisses
- Metadaten-Datei (`.media-metadata.json`) je Verzeichnis

---

## 10. Administration & Betrieb

### Nutzerverwaltung & Rollen

| Rolle | Berechtigungen |
|-------|---------------|
| `admin` | Vollzugriff auf alles |
| `designer` | Design-Tokens, Themes, Custom CSS |
| `redakteur` | CMS-Seiten erstellen und bearbeiten |
| `catalog-editor` | Fragenkataloge verwalten |
| `event-manager` | Events, Teams, Konfiguration |
| `analyst` | Ergebnisse und Statistiken lesen |
| `team-manager` | Teams und Spieler verwalten |
| `service-account` | API-Zugang für Drittsysteme |

### Backup & Restore
- Zeitgestempelte Backups auf Knopfdruck
- ZIP-Download für Offline-Archivierung
- Restore aus Backup-Verzeichnis über Admin-Oberfläche
- Automatischer Import bei Deployment (Bootstrap-Skript)

### Multi-Tenant & Namespace-Isolation
- Vollständige Datenisolation zwischen Mandanten via PostgreSQL
- Subdomain-Routing: Jeder Mandant erhält eigene Subdomain (z. B. `kunde1.quizrace.app`)
- Namespace-System: logische Gruppierung von Events, Seiten, Design und rechtlichen Angaben
- **Stripe-Billing-Integration**: Abo-Verwaltung, Plan-Limits, Kündigungsfristen – vollständig in der Plattform abgebildet
- Onboarding-Workflow: Automatisches Setup für neue Mandanten inkl. Admin-User

### Domain-Management
- Mehrere Domains und Subdomains pro Mandant
- Domain-zu-Namespace-Mapping in der Admin-Oberfläche
- Startseiten-Mapping: verschiedene Domains zeigen unterschiedliche CMS-Seiten

### Sicherheit & Compliance
- **CSRF-Schutz** auf allen zustandsändernden Formularen und API-Endpunkten
- **Rate Limiting**: Anfragen-Drosselung per Middleware
- **Passwort-Reset** mit sicher gehashten Tokens und Ablaufzeit
- **Audit-Log**: unveränderliches Protokoll aller Admin-Aktionen mit Kontext-JSON
- **Session-Management**: datenbankgestützte Sessions, serverseitig verwaltbar
- **PHPStan** Statische Analyse beim Build-Prozess

### Mail-Provider
Wählbar und konfigurierbar je Namespace:
- Brevo (Sendinblue)
- Sendgrid
- Mailchimp

### Webhook & Analytics
- Webhook-URL je Event für externe Benachrichtigungen bei neuen Ergebnissen
- Analytics-ID (z. B. Google Analytics / Matomo) je Event einbindbar

---

## 11. Technische Highlights

| Aspekt | Details |
|--------|---------|
| **Backend** | PHP 8.2, Slim Framework 4, PSR-7/PSR-15 |
| **Frontend** | Twig-Templates, UIkit 3, Vanilla JS |
| **Datenbank** | PostgreSQL mit 200+ Migrationsdateien |
| **Deployment** | Docker Compose (Nginx, PostgreSQL, App, ACME) |
| **SSL** | Automatisch via Let's Encrypt + ACME Companion, Wildcard-Zertifikate |
| **Bildverarbeitung** | Imagick (primär) + GD (Fallback) |
| **KI-Integration** | OpenAI-kompatible API für Teamnamen & Seitengenerierung |
| **RAG-Pipeline** | Python-basierter Chatbot mit eigenem Wissensbasis-Index |
| **Codequalität** | PHPStan, Conventional Commits via Commitlint |
| **CI/CD** | GitHub Actions-kompatibel |

### Architektur-Prinzipien
- **Domain-Driven Design**: klare Trennung zwischen Domain-Modellen, Services und Controllern
- **Repository-Pattern**: abstrakte Datenbankzugriffe für einfache Testbarkeit
- **Middleware-Stack**: modulare Querschnittsfunktionen (Auth, CSRF, Rate Limit, Domain-Routing)
- **Event-Isolation**: alle Daten können einem Event zugeordnet und gefiltert werden
- **Namespace-Isolation**: Design, Seiten, Nutzer und rechtliche Angaben sind per Namespace getrennt

---

## 12. Einsatzszenarien

### Firmen-Events & Teambuilding
Ein Unternehmen richtet einen Sommerfest-Quizabend aus. Jede Abteilung bildet ein Team, scannt den Team-QR-Code mit dem Smartphone und spielt im Wettkampfmodus. Das Live-Dashboard läuft auf einem Beamer – die Rangliste aktualisiert sich in Echtzeit. Ergebnisse werden als PDF-Zusammenfassung mit Fotos der Teilnehmer exportiert.

### Bildungseinrichtungen
Eine Schule erstellt mehrere Fragenkataloge zu verschiedenen Unterrichtsthemen. Schülerinnen und Schüler spielen im Einzelmodus auf ihren Geräten. Die Falsche-Antworten-Analyse zeigt Lehrkräften auf einen Blick, welche Themen noch vertieft werden müssen.

### Messen & Konferenzen
Ein Unternehmen integriert quizrace als interaktives Stand-Element auf einer Messe. Besucher scannen einen Katalog-QR-Code, spielen ein kurzes Quiz und landen automatisch auf einer CMS-Seite mit Produkt-Informationen. Die Mediengalerie zeigt Selfies der Teilnehmer auf einem Display am Stand.

### Öffentliche Veranstaltungen & Volksfeste
Eine Stadt richtet einen Quiz-Abend für das Stadtfest aus. Verschiedene Vereine treten als Teams gegeneinander an. Das öffentliche Dashboard wird auf Großleinwand projiziert. Die Einladungs-PDFs mit QR-Codes wurden vorab an alle Teams gesendet.

### SaaS-Betrieb für Event-Agenturen
Eine Event-Agentur betreibt quizrace als White-Label-Plattform für ihre Kundschaft. Jeder Kunde erhält eine eigene Subdomain, eigenes Branding per Design-Tokens und vollständig isolierte Daten. Die Stripe-Billing-Integration übernimmt Abrechnung und Plan-Management automatisch.

---

*Alle aufgeführten Features sind im Quellcode implementiert und produktiv einsatzbereit, sofern nicht explizit als experimentell markiert.*
