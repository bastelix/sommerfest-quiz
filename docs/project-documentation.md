# QuizRace – Projektdokumentation für Marketing-Website

> **Zweck dieses Dokuments:** Diese Dokumentation ist ein eigenständiges Briefing, das alle
> Informationen enthält, die ein KI-Assistent benötigt, um ein vollständiges Konzept für eine
> Marketing-Website zu erstellen – ohne Zugang zum Quellcode. Die Daten stammen aus einer
> ganzheitlichen Analyse des Projekts (Code, Konfiguration, bestehende Marketing-Inhalte,
> Datenbank-Schema, Design-Tokens und Dokumentation).
>
> **Erstellt:** 2026-02-17 | **Version:** 0.9.18 | **Snapshot-Hinweis:** Kennzahlen, Preise
> und Feature-Umfang können sich seit Erstellung geändert haben.

---

## 1. Produktübersicht

### 1.1 Kernidentität

| Feld | Wert |
|---|---|
| **Produktname** | QuizRace |
| **Interner Projektname** | Sommerfest-Quiz |
| **Inhaber** | René Buske, Weidenbusch 8, 14532 Kleinmachnow, Deutschland |
| **USt-IdNr.** | DE 259645623 |
| **Domain** | quizrace.app |
| **Kontakt** | support@quizrace.app |
| **Lizenz** | Proprietär – kommerzielle Nutzung erlaubt. Alle Rechte bei René Buske. |
| **Version** | 0.9.18 |

### 1.2 Was ist QuizRace?

QuizRace ist eine **sofort einsetzbare Web-App**, mit der Veranstalter Besucherinnen und
Besucher spielerisch an Events beteiligen. Spieler scannen QR-Codes an physischen Stationen,
beantworten Quizfragen direkt im Browser und sehen ihre Platzierung in einer Echtzeit-Rangliste.
Hosts steuern das gesamte Geschehen aus einem zentralen Live-Dashboard.

**Kernproblem, das QuizRace löst:** Klassische Event-Unterhaltung (Papierfragebögen,
aufwändige Rallye-Organisation) ist zeitintensiv, schwer auswertbar und wenig interaktiv.
QuizRace digitalisiert diesen Prozess komplett: Fragen erstellen, QR-Sticker drucken, Event
starten – fertig. Ergebnisse fließen automatisch in eine Live-Rangliste.

### 1.3 Zielgruppen

#### Spieler / Teilnehmer (Endnutzer)
- Event-Besucher auf Sommerfesten, Firmenfeiern, Bildungsveranstaltungen, Messen
- Nutzen ausschließlich ein Smartphone oder Tablet
- Brauchen **keine App-Installation** und **keinen Account**
- Scannen einen QR-Code, wählen einen Namen und spielen sofort los

#### Admins / Hosts (Veranstalter)
- Event-Organisatoren in Unternehmen, Schulen, Vereinen, Kommunen oder Agenturen
- Erstellen Fragenkataloge, konfigurieren Events, drucken QR-Sticker
- Überwachen den Spielverlauf in Echtzeit über das Live-Dashboard
- Werten Ergebnisse aus und exportieren sie als CSV oder PDF

### 1.4 Typischer Nutzungsablauf

```
┌─────────────────────────────────────────────────────────────────┐
│  1. IDEE & AUFBAU                                               │
│  Rallye, Escape-Quiz oder Team-Challenge planen.                │
│  Aufgaben im Editor anlegen oder aus Katalogen übernehmen.      │
├─────────────────────────────────────────────────────────────────┤
│  2. STATIONEN MARKIEREN                                         │
│  QR-Sticker platzieren. Teams scannen ein und lösen Fragen      │
│  direkt im Browser – ohne Login und ohne App.                   │
├─────────────────────────────────────────────────────────────────┤
│  3. LIVE AUSWERTEN                                              │
│  Punkte, Zeitboni und Teamstatus fließen automatisch ins        │
│  Live-Ranking. Moderation erfolgt aus dem Dashboard.            │
└─────────────────────────────────────────────────────────────────┘
```

> **Fertig für Sommerfest, Unterricht oder Messe.**
> QuizRace bleibt leichtgewichtig: Browser-Link teilen, Spiel starten,
> Live-Ergebnisse projizieren oder auf dem Smartphone anzeigen.

### 1.5 Einsatzszenarien

| Szenario | Beschreibung |
|---|---|
| **Sommerfeste / Betriebsfeste** | Teams laufen Stationen ab, scannen QR-Codes, lösen Fragen – Siegerehrung per Live-Ranking |
| **Firmenfeiern / Teamevents** | Team-Challenges mit Wettbewerbsmodus und Rätselwort-Spiel |
| **Bildungsrallyes / Schulungen** | Lernstationen mit verschiedenen Fragetypen (Zuordnen, Sortieren, etc.) |
| **Escape-Quiz** | Rätselwort über mehrere Stationen sammeln, Puzzle lösen |
| **Messen / Konferenzen** | Interaktive Stationen am Messestand, Besucher-Engagement |
| **Stadtfeste / öffentliche Events** | Großveranstaltungen mit tausenden Teilnehmern gleichzeitig |

---

## 2. Features & Funktionsumfang

### 2.1 Spieler-Perspektive

#### Zugang & Einstieg
- **QR-Code scannen** – öffnet direkt den Browser, kein App-Download nötig
- **Kein Account erforderlich** – Name wählen (manuell oder KI-generierter Zufallsname) und sofort losspielen
- **QR-Code-Login** – optionale Schnellanmeldung für registrierte Teams/Personen
- **„Remember Me"** – gescannte Namen können gespeichert werden, erneutes Scannen entfällt

#### 6 interaktive Fragetypen

| # | Fragetyp | Beschreibung |
|---|---|---|
| 1 | **Multiple Choice** | Klassische Auswahlfragen mit einer oder mehreren korrekten Antworten |
| 2 | **Sortieren** | Elemente per Drag-and-drop in die richtige Reihenfolge bringen |
| 3 | **Zuordnen** | Begriffe oder Bilder korrekt zueinander zuordnen |
| 4 | **Swipe-Karten** | Aussagen per Wischgeste als richtig oder falsch bewerten |
| 5 | **Foto mit Texteingabe** | Bildfrage mit Freitextantwort (z. B. „Was zeigt dieses Foto?") |
| 6 | **„Hätten Sie es gewusst?"-Karten** | Wissens-Flip-Cards mit Lerneffekt |

#### Spielerlebnis
- **Echtzeit-Rangliste** – Live-Ranking nach Punkten, Effizienz und Zeit
- **Rätselwort-Spiel** – optionales Puzzle-Word, das über mehrere Stationen Buchstabe für Buchstabe gesammelt wird
- **Beweisfoto-Upload** – DSGVO-konforme Foto-Uploads als Teil des Spiels (z. B. Foto an der Station)
- **Fortschrittsanzeige** – Balken mit aktueller Frage und Live-Ansage
- **Ergebnisübersicht** – nach Abschluss aller Kataloge persönliche Ergebnisseite

#### Komfort & Barrierefreiheit
- **Dark Mode** – automatische Erkennung der Systemeinstellung oder manuell umschaltbar
- **Hochkontrastmodus** – für Menschen mit Seheinschränkungen
- **Mobile-first** – optimiert für Smartphones, funktioniert auf Tablets und Desktops
- **ARIA-Labels & Tastaturnavigation** – vollständige Bedienbarkeit ohne Maus
- **Fortschrittsbalken mit `aria-valuenow`** – Live-Ansage der aktuellen Frage für Screenreader

### 2.2 Admin/Host-Perspektive

#### Event-Management
- **Events erstellen und konfigurieren** – Name, Zeitraum, Logo, Farben, Texte
- **Aktives Event setzen** – ein Event als aktuell markieren, mehrere Events parallel verwalten
- **Wettbewerbsmodus** – Neustart-Buttons ausblenden, Katalog-Wiederholungen verhindern, Ergebnisse pro Station sperren
- **Einladungstext** – optionales Anschreiben für teilnehmende Teams

#### Fragenkatalog-Editor
- **Kataloge erstellen und verwalten** – Gruppierung von Fragen in thematische Kataloge
- **6 Fragetypen** im Editor – alle oben genannten Typen per Formular erstellbar
- **Drag-and-drop Sortierung** – Reihenfolge der Fragen und Kataloge per Drag-and-drop anpassen
- **Rätsel-Buchstabe** – pro Katalog einen Buchstaben für das Rätselwort festlegen
- **Import/Export** – Fragen als JSON importieren und exportieren

#### Team-Verwaltung
- **Teams/Personen anlegen** – Teilnehmerlisten pflegen
- **KI-generierte Teamnamen** – kreative Namensvorschläge per RAG-Backend (KI-Modus oder Lexikon)
- **QR-Code-Login-Beschränkung** – optional nur registrierte Teams/Personen zulassen
- **Zufallsnamen-Strategie** – pro Event konfigurierbar (KI oder Lexikon), mit Puffer-Vorschlägen

#### Live-Dashboard
- **Echtzeit-Ranking** – Live-Rangliste mit Punkten, Effizienz und Zeitboni
- **Teamstatus** – Übersicht welche Teams unterwegs, gelöst oder pausiert sind
- **Moderationsaktionen** – [EINSCHÄTZUNG: Aus dem Marketing-Content werden „Hint, Freeze, Bonuspunkte" als Moderationsoptionen erwähnt; die genaue Implementierungstiefe variiert je nach Version]
- **Dashboard-Token** – geschützter Zugang per individuellem URL-Token, auch ohne Admin-Login nutzbar

#### QR-Code-System
- **Lokale QR-Code-Generierung** – Codes werden serverseitig erzeugt (php-qrcode-Bibliothek)
- **Anpassbare Farben** – Katalog-Links rot, Team-Links blau (konfigurierbar)
- **QR-Sticker für Stationen** – druckfertige QR-Codes mit Labels und optionalem Logo
- **Data-URI oder Pfad** – flexible Einbettung in Druckvorlagen

#### Ergebnisse & Analyse
- **Ergebnisübersicht** – tabellarische Darstellung aller Resultate
- **Drei Ranglisten** – Ranking-Champions (gelöste Fragen), Punkte-Ranking, Effizienz-Ranking
- **CSV-Export** – UTF-8 mit BOM für Excel-Kompatibilität
- **Statistik-Tab** – detaillierte Auswertung aller Antworten, filterbar nach Teams
- **Beweisfoto-Anzeige** – hochgeladene Fotos in der Statistik einsehbar

#### Administration
- **9 Rollen** mit granularer Zugriffskontrolle:

| Rolle | Beschreibung |
|---|---|
| `admin` | Vollzugriff auf alle Funktionen |
| `designer` | Gestaltung und visuelle Anpassungen |
| `redakteur` | Inhalte bearbeiten und veröffentlichen |
| `catalog-editor` | Fragenkataloge erstellen und bearbeiten |
| `event-manager` | Events konfigurieren und verwalten |
| `analyst` | Ergebnisse und Statistiken einsehen |
| `team-manager` | Teams und Teilnehmer verwalten |
| `service-account` | Automatisierung (kein Dashboard-Zugang) |
| `customer` | Kundenbereich mit eingeschränktem Zugang |

- **CMS mit Page-Editor** – TipTap-basierter Rich-Text-Editor für statische Seiten
- **Wiki-Bereich** – optionaler Wissensbereich (per Feature-Flag aktivierbar)
- **News-Artikel** – Beiträge mit Veröffentlichungstermin und Sichtbarkeitsstatus
- **Media-Manager** – Bildupload mit automatischer Kompression (max. 1500px Kante, 70–80 % JPEG-Qualität)
- **Backup/Restore** – vollständige Datensicherung als JSON, wiederherstellbar
- **Nutzernamen-Moderation** – Blocklisten für NSFW, NS-Symbole, Beleidigungen, reservierte Namen
- **Audit-Logging** – lückenlose Protokollierung aller Admin-Aktionen
- **Passwort-Reset** – E-Mail-basiert mit zeitlich begrenztem Token (1 Stunde)
- **White-Label/Branding** – Logo, Farben, Texte pro Mandant anpassbar
- **Multi-Domain** – mehrere Domains pro Instanz, automatische Zonenverwaltung

### 2.3 Technische Besonderheiten (Marketing-relevant)

| Merkmal | Details |
|---|---|
| **Multi-Tenant-Architektur** | Mandantenisolierung über PostgreSQL-Schemas – jeder Kunde hat seinen eigenen Datenraum |
| **Docker-Deployment** | Container-basiertes Setup mit automatischer SSL-Zertifikatserneuerung (Let's Encrypt) |
| **Stripe-Abo-Integration** | Drei Preispläne mit 7-Tage-Testphase, automatische Abrechnung |
| **DSGVO-Konformität** | Hosting in Deutschland, Datenminimierung, Double-Opt-in für optionale E-Mail-Erfassung, nur Session-Cookie |
| **KI-Integration** | RAG-basierte Teamnamen-Generierung, optionaler KI-Chat für Marketing-Seiten |
| **Health-Monitoring** | `/healthz`-Endpoint für Betriebsüberwachung |
| **Automatische Bildkompression** | Uploads werden serverseitig verkleinert und optimiert |
| **Cloudflare Turnstile** | Optionaler Captcha-Schutz für Kontaktformulare |

---

## 3. USP & Differenzierung

### 3.1 Vergleich mit Wettbewerbern

| Merkmal | **QuizRace** | **Kahoot** | **Mentimeter** |
|---|:---:|:---:|:---:|
| Stationsbasiert (physische QR-Codes) | ✅ | ❌ | ❌ |
| Kein Account für Spieler nötig | ✅ | ❌ (App) | ✅ (Code) |
| Keine App-Installation | ✅ | ❌ | ✅ |
| Self-Hosted Option (Docker) | ✅ | ❌ | ❌ |
| White-Label / Multi-Tenant | ✅ | ❌ | ❌ |
| Rätselwort-Mechanik | ✅ | ❌ | ❌ |
| DSGVO-konformer Beweisfoto-Upload | ✅ | ❌ | ❌ |
| 6 verschiedene Fragetypen | ✅ | ✅ (ähnlich) | Teilweise |
| Wettbewerbsmodus für Lauf-Events | ✅ | ❌ | ❌ |
| KI-generierte Teamnamen | ✅ | ❌ | ❌ |
| Barrierefreiheit (WCAG) | ✅ | Teilweise | Teilweise |
| Hosting in Deutschland | ✅ | ❌ (US) | ❌ (SE) |
| Integriertes CMS | ✅ | ❌ | ❌ |

### 3.2 Kernunterscheidungsmerkmale

1. **Gebaut für physische Events, nicht für Präsentationen.** Während Kahoot und Mentimeter
   für Klassenzimmer und Meetings konzipiert sind (alle schauen auf einen Bildschirm), ist
   QuizRace für Lauf-Events optimiert: Teilnehmer bewegen sich zwischen Stationen, scannen
   QR-Codes und lösen Aufgaben eigenständig.

2. **Null Einstiegshürde für Spieler.** Kein Download, kein Account, kein Login. QR-Code
   scannen, Name wählen, spielen. Das ist entscheidend bei Events mit hunderten Teilnehmern,
   die spontan mitmachen möchten.

3. **Rätselwort über mehrere Stationen.** Ein einzigartiges Spielelement: An jeder Station
   wird ein Buchstabe freigeschaltet. Wer alle Stationen absolviert, kann das Lösungswort
   eingeben – inklusive Zeitmessung und Bestenliste.

4. **Volle Datenhoheit.** Self-Hosted per Docker oder als SaaS mit Hosting in Deutschland.
   Keine Daten in US-Clouds, DSGVO-konform by Design.

5. **White-Label für Agenturen und Unternehmen.** Eigenes Branding (Logo, Farben, Domain)
   pro Mandant. Ideal für Event-Agenturen, die QuizRace unter eigenem Namen anbieten.

### 3.3 Bewusste Designentscheidungen

| Entscheidung | Begründung |
|---|---|
| **HTTP-Polling statt WebSocket** | Einfachheit und Robustheit – funktioniert zuverlässig hinter Firewalls und Proxies, keine persistente Verbindung nötig |
| **Server-Side Rendering (Twig)** | Schnelle initiale Ladezeit, SEO-freundlich, weniger JavaScript-Abhängigkeiten |
| **PostgreSQL als einzige Datenbank** | Schemata für Multi-Tenant-Isolation, JSONB für flexible Fragenstruktur, bewusster Verzicht auf SQLite |
| **Deutsch als Primärsprache** | Fokus auf DACH-Markt, wo DSGVO-Konformität und deutscher Support besonders geschätzt werden |
| **UIKit 3 statt React/Vue** | Leichtgewichtig, bewährt, keine Build-Pipeline für Frontend nötig – schnelleres Laden auf mobilen Geräten |

---

## 4. Technische Architektur (Marketing-relevant)

### 4.1 Plattform

QuizRace ist eine **reine Web-App**. Es gibt keine native iOS- oder Android-App. Alles läuft
im Browser – sowohl die Spieler-Oberfläche als auch das Admin-Dashboard.

### 4.2 Zugangsweg für Spieler

```
QR-Code scannen → Browser öffnet sich automatisch → Name wählen → Sofort spielen
```

- **Kein Download** einer App erforderlich
- **Kein Account** oder Registrierung nötig
- **Kein Login** – optional QR-Code-Login für registrierte Teams
- **Funktioniert auf jedem Gerät** mit einem modernen Browser (Smartphone, Tablet, Laptop)

### 4.3 Zugangsweg für Admins

```
quizrace.app/login → Benutzername + Passwort → Admin-Dashboard
```

- Rollenbasierter Zugriff je nach zugewiesener Rolle
- Passwort-Reset per E-Mail verfügbar
- Onboarding-Assistent für neue Mandanten

### 4.4 Hosting-Modell

| Modell | Beschreibung |
|---|---|
| **SaaS (gehostet)** | Betrieb auf quizrace.app – sofort startklar, Updates und Wartung inklusive, Hosting in Deutschland |
| **Self-Hosted (Docker)** | Eigene Infrastruktur mit Docker Compose – volle Datenhoheit, eigene Domain, eigene Backups |

Das Self-Hosted-Setup umfasst:
- PHP-Applikation mit integriertem Webserver
- PostgreSQL-Datenbank
- nginx Reverse Proxy mit automatischer SSL-Zertifikatserneuerung (Let's Encrypt)
- Optional: Adminer für Datenbankzugang

### 4.5 Geräteunterstützung

| Gerät | Nutzung |
|---|---|
| **Smartphone** | Spieler-Oberfläche (primärer Einsatz) |
| **Tablet** | Spieler-Oberfläche und Admin-Dashboard |
| **Laptop/Desktop** | Admin-Dashboard und Fragenerstellung |
| **TV/Beamer** | Live-Dashboard zur Projektion des Rankings |

### 4.6 Technologie-Stack (Kurzfassung)

| Komponente | Technologie |
|---|---|
| Backend | PHP 8.2+, Slim Framework 4 |
| Frontend | UIKit 3, Vanilla JavaScript |
| Datenbank | PostgreSQL 15 |
| Template-Engine | Twig |
| Rich-Text-Editor | TipTap (Admin) |
| QR-Code-Generierung | chillerlan/php-qrcode |
| Bildverarbeitung | Intervention Image (GD/Imagick) |
| Zahlungsabwicklung | Stripe |
| E-Mail | Symfony Mailer (SMTP/API) |
| Containerisierung | Docker + Docker Compose |
| SSL | Let's Encrypt (automatisch) |
| KI-Teamnamen | RAG-Backend (OpenAI-kompatible API) |

---

## 5. Visuelle Identität & UX

### 5.1 Logo und Markenzeichen

Das aktuelle Logo ist eine **gelbe/goldene Sonne** mit den Initialen **„SF"** (Sommerfest)
in der Mitte. Um die Sonne herum befinden sich **farbige Konfetti-Punkte**, die eine
festliche, spielerische Atmosphäre vermitteln.

**Logo-Farben:**

| Element | Farbe | Hex |
|---|---|---|
| Sonnenkern | Gelb/Gold | `#FFD93B` |
| Sonnenstrahlen | Dunkleres Gold | `#FFC300` |
| Logo-Hintergrund | Warmes Gold | `#ffe17b` |
| Konfetti Pink | Pink | `#E9446A` |
| Konfetti Grün | Grün | `#3EC300` |
| Konfetti Blau | Hellblau | `#39C2F1` |
| Konfetti Orange | Orange | `#F8AC3C` |

**Verfügbare Logo-Formate:**
- SVG (skalierbar): `logo-160.svg`, `logo-320.svg`
- Favicon: `favicon.svg` (Sonne mit Konfetti, 64x64 Viewbox)
- Das Logo kann pro Mandant durch ein eigenes Logo ersetzt werden (Upload als PNG, WebP oder SVG)

[HINWEIS: Das aktuelle Logo mit „SF"-Initialen stammt aus der Ursprungsversion als „Sommerfest-Quiz". Für die Marketing-Website sollte geprüft werden, ob ein QuizRace-spezifisches Logo existiert oder erstellt werden soll.]

### 5.2 Farbpalette

#### App-Farben (Spieler & Admin)

| Rolle | Farbe | Hex |
|---|---|---|
| **Primärfarbe (Buttons, Links)** | UIKit-Blau | `#1e87f0` |
| **Akzent/Logo** | Gold | `#FFD93B` / `#ffe17b` |
| **Text (Light Theme)** | Dunkelgrau | `#111827` |
| **Hintergrund (Light Theme)** | Weiß | `#ffffff` |
| **Hintergrund (Dark Theme)** | Fast-Schwarz | `#050910` |
| **Cards (Dark Theme)** | Dunkelblau | `#172132` |
| **Footer-Hintergrund** | Weiß | `#ffffff` |
| **Footer-Text gedämpft** | Mittelgrau | `#4b5563` |
| **Rahmen gedämpft** | Hellgrau | `#dfe3eb` |

#### Marketing-Design-Presets

Die Anwendung bietet 6 vordefinierte Farbthemen für Marketing-Seiten, die per Konfiguration
aktivierbar sind:

| Preset | Primär | Akzent | Charakter |
|---|---|---|---|
| **aurora** | `#1f6feb` (Blau) | `#58a6ff` (Hellblau) | Modern, technisch, vertrauenswürdig |
| **future-is-green** | `#138f52` (Grün) | `#9cd78f` (Hellgrün) | Nachhaltig, frisch, natürlich |
| **sunset** | `#f97316` (Orange) | `#ec4899` (Pink) | Warm, energetisch, einladend |
| **midnight** | `#6366f1` (Indigo) | `#14b8a6` (Teal) | Elegant, dunkel, futuristisch |
| **monochrome** | `#111111` (Schwarz) | `#1f1f1f` (Dunkelgrau) | Minimalistisch, reduziert, sachlich |
| **uikit-default** | `#1e87f0` (Blau) | `#222` (Dunkelgrau) | Standard UIKit-Look, neutral |

Jedes Preset definiert ein vollständiges Token-System mit Light- und Dark-Mode-Varianten
für Oberflächen, Texte, Schatten, Buttons und Typografie.

### 5.3 Typografie

| Eigenschaft | Wert |
|---|---|
| **Body Font-Größe** | `clamp(14px, 1vw + 0.5rem, 18px)` (flüssig skalierend) |
| **Heading H1 (Marketing)** | `clamp(2.4rem, 4vw, 3.6rem)` |
| **Heading-Gewicht** | 600–700 (je nach Preset) |
| **Heading Line-Height** | 1.15 |
| **Font-Stacks** | Modern (System-Fonts), Classic (Serif-Ergänzung), Tech (Monospace-Akzent) |
| **Standard-Font-Stack** | `var(--marketing-font-stack-modern)` – systemnahe Sans-Serif |

### 5.4 Responsive Design

| Breakpoint | Gerät |
|---|---|
| < 640px | Smartphone (Portrait) |
| 640px – 959px | Tablet / Smartphone (Landscape) |
| 960px – 1199px | Tablet / kleiner Desktop |
| ≥ 1200px | Desktop |

Das Layout basiert auf UIKit 3 Grid mit `uk-*` Klassen. Marketing-Seiten nutzen ein
CSS-Grid-basiertes Footer-Layout mit `grid-template-columns: repeat(auto-fit, minmax(180px, 1fr))`.

### 5.5 Spieler-Oberfläche (Beschreibung)

- **Minimalistisch und kartenbasiert** – jede Frage wird als einzelne Karte dargestellt
- **Große Touch-Targets** – Buttons und interaktive Elemente sind für Daumensteuerung optimiert
- **Fortschrittsbalken** oben – zeigt aktuelle Frage von Gesamtanzahl
- **Animationen** – flüssige Übergänge zwischen Fragen (UIKit-Animationen)
- **Sofortige Rückmeldung** – nach Beantwortung wird das Ergebnis direkt angezeigt
- **Abschlussseite** – Zusammenfassung der Ergebnisse mit Punktzahl und Platzierung

### 5.6 Admin-Oberfläche (Beschreibung)

Die Administrationsoberfläche unter `/admin` gliedert sich in folgende Tabs:

1. **Veranstaltung konfigurieren** – Logo, Farben, Texte, Spielmodus-Einstellungen
2. **Übersicht** – Ergebnisse tabellarisch einsehen
3. **Kataloge** – Fragenkataloge erstellen und verwalten
4. **Fragen anpassen** – Fragen eines Katalogs hinzufügen, bearbeiten, löschen
5. **Teams/Personen** – Teilnehmerlisten pflegen
6. **Ergebnisse** – Spielstände einsehen und herunterladen
7. **Statistik** – Einzelne Antworten analysieren und nach Teams filtern
8. **News-Artikel** – Beiträge verwalten
9. **Administration** – Benutzer, Backups, Systemeinstellungen

### 5.7 Gesamteindruck

Die App vermittelt einen **spielerisch-professionellen** Eindruck:
- **Spielerisch** durch das Sonnen-/Konfetti-Logo, lebendige Farben und Animationen
- **Professionell** durch klare Struktur, aufgeräumtes Layout und konsistente Designsprache
- **Barrierefrei** durch ARIA-Labels, Tastatursteuerung und Kontrastmodi
- **Vertrauenswürdig** durch den Fokus auf Datenschutz und DSGVO

[EINSCHÄTZUNG: Für die Marketing-Website empfiehlt sich das „aurora"-Preset (Blau-Basis) als
Standardtheme, da es Vertrauen und Professionalität ausstrahlt und gleichzeitig modern wirkt.
Die Gold-/Sonnenfarben des Logos können als Akzent eingesetzt werden.]

---

## 6. Content & Texte

### 6.1 Bestehende Marketing-Texte

Die folgenden Texte stammen direkt aus der bestehenden QuizRace-Marketingseite und können
für die neue Website übernommen oder adaptiert werden:

**Tagline / Claim:**
> QuizRace verbindet Teams in Echtzeit.

**Eyebrow (Überschrift-Vorsatz):**
> Ohne App, sofort spielbar

**Subheadline:**
> Live-Ranking, QR-Stationen und Moderation aus einem Dashboard – ideal für Sommerfeste,
> Teamevents und Bildungsrallyes.

**Produktbeschreibung (aus README):**
> QuizRace ist eine sofort einsetzbare Web-App, mit der Sie Besucherinnen und Besucher
> spielerisch an Events beteiligen. Dank Slim Framework und UIkit3 funktioniert alles ohne
> komplizierte Server-Setups direkt im Browser.

### 6.2 Marquee / Laufband-Tags

Diese kurzen Aussagen eignen sich für Trust-Badges, Ticker oder Highlight-Leisten:

1. „DSGVO-konform gehostet in Deutschland"
2. „Keine App-Installation notwendig"
3. „Drag-and-drop Editor für Aufgaben"
4. „Live-Ranking & Teamstatus"
5. „QR-Sticker für Stationen"

### 6.3 Feature-Highlights (aus bestehender Marketingseite)

**Aufgaben & Medien:**
- Aufgabenbibliothek mit Kategorien
- Bild, Audio und Video einbetten
- Flexible Punkte- und Zeitlogik

**Live-Dashboard:**
- Live-Ranking mit Bonus-Regeln
- Status je Team: unterwegs, gelöst, pausiert
- Moderationsaktionen wie Hint, Freeze, Bonuspunkte

**QR-Stationen:**
- Stationen drucken oder digital teilen
- Optionaler Zeitbonus je Fundstelle
- Funktioniert offline nach dem Scannen

### 6.4 FAQ-Inhalte

| Frage | Antwort |
|---|---|
| Brauchen Teams einen Account? | Nein. Teams scannen einen QR-Code, wählen ihren Namen und spielen direkt im Browser. |
| Wie lang dauert das Setup? | Mit fertigen Katalogaufgaben bist du in wenigen Minuten startklar; eigene Fragen können importiert werden. |
| Welche Geräte unterstützen wir? | QuizRace läuft auf Smartphones, Tablets und Laptops. Das Live-Dashboard funktioniert auf TV/Beamer. |
| Wo liegen die Daten? | Der Dienst läuft in Deutschland und ist DSGVO-konform. Exporte für Auswertungen sind jederzeit möglich. |

### 6.5 Sprachen

| Sprache | Status |
|---|---|
| **Deutsch** | Primärsprache – vollständige UI-Übersetzung |
| **Englisch** | Unterstützt – Übersetzungen in `resources/lang/` und `public/js/i18n/` |

Die Sprachauswahl erfolgt per URL-Parameter (`?lang=de` / `?lang=en`) oder automatische
Erkennung.

### 6.6 Bestehende Content-Struktur (Block-System)

Die Marketingseiten nutzen ein **Block-basiertes Content-System** (`block-contract-v1`).
Folgende Block-Typen stehen zur Verfügung und können für die Marketing-Website genutzt werden:

| Block-Typ | Zweck |
|---|---|
| `hero` | Hero-Bereich mit Headline, Subheadline und CTAs |
| `feature_list` | Feature-Auflistung als Cards oder Grid |
| `content_slider` | Inhalte als Slider/Karussell |
| `process_steps` | Ablauf-Darstellung (Timeline/Schritte) |
| `testimonial` | Kundenzitate |
| `rich_text` | Freier Text mit Formatierung |
| `info_media` | Informationsbereich mit Bild/Video |
| `cta` | Call-to-Action-Bereich |
| `stat_strip` | Kennzahlen-Leiste |
| `audience_spotlight` | Zielgruppen-/Use-Case-Tabs |
| `package_summary` | Preisvergleich / Pakete |
| `faq` | FAQ-Akkordeon |
| `proof` | Social-Proof-Elemente |

---

## 7. Social Proof & Einsatz

### 7.1 Kennzahlen

| Metrik | Wert | Kontext |
|---|---|---|
| **Teilnehmer** | > 25.000 | Mitspielende Personen insgesamt |
| **Events** | 420 | Durchgeführte Events & Live-Runden |
| **Setup-Zeit** | unter 10 Minuten | Startklar – QR-Codes scannen, Teams wählen, Ranking läuft |

**Kontextbeschreibungen:**
- „Getestet bei Stadtfesten, Firmenfeiern und Bildungsprojekten."
- „Von Rallye bis Escape-Quiz – alles im Browser steuerbar."
- „QR-Codes scannen, Teams wählen, Ranking läuft."

### 7.2 Testimonials

[EINSCHÄTZUNG: Im Quellcode und in der Dokumentation wurden **keine QuizRace-spezifischen
Testimonials** gefunden. Es existieren Testimonials für das verwandte Produkt calHelp/calServer
(ProvenExpert: 4.91/5 Sterne, 63 Bewertungen, 100 % Weiterempfehlung), diese beziehen sich
jedoch auf Kalibriertechnik-Dienstleistungen, nicht auf QuizRace.

**Empfehlung für die Marketing-Website:**
- QuizRace-spezifische Testimonials von Event-Veranstaltern einholen
- Alternativ: Allgemeine Vertrauenssignale des Inhabers nutzen (Erfahrung, ProvenExpert-Bewertung)
- Die Kennzahlen (25.000+ Teilnehmer, 420 Events) sind bereits starke Social-Proof-Elemente]

### 7.3 Bisherige Einsätze

Aus den Kennzahlen und dem Produktnamen lassen sich folgende Einsatzkontexte ableiten:

- **Sommerfeste** (Namensgeber des Projekts: „Sommerfest-Quiz")
- **Firmenfeiern und Teamevents** (explizit in der Marketingseite erwähnt)
- **Bildungsprojekte** (Schulungen, Unterricht)
- **Stadtfeste** (öffentliche Großveranstaltungen)

[EINSCHÄTZUNG: Die Zahl von 25.000+ Teilnehmern und 420 Events deutet auf einen
produktiven Einsatz über mehrere Jahre hin. Konkrete Kundennamen oder Case Studies sind
im Code nicht dokumentiert.]

### 7.4 Datenschutz & Vertrauen

Die folgenden Punkte sind als Vertrauenssignale für die Marketing-Website verwendbar:

| Signal | Details |
|---|---|
| **Datenminimierung** | Spielteilnahme erfordert nur einen frei gewählten Benutzernamen (Pseudonym). E-Mail-Adressen werden ausschließlich mit expliziter Einwilligung per Double-Opt-in erhoben (z. B. für Ergebnisbenachrichtigungen). |
| **Serverstandort Deutschland** | Hosting auf deutschen Servern |
| **DSGVO-konform** | Datenschutzerklärung vorhanden, keine Weitergabe an Dritte, Einwilligungsflüsse mit Double-Opt-in |
| **Nur technisch notwendige Cookies** | Ein Session-Cookie (`PHPSESSID`) – kein Tracking |
| **Kein Tracking** | Keine Analytics, keine Werbecookies, keine Drittanbieter-Tracker |
| **Datenexport jederzeit** | Ergebnisse als CSV exportierbar |
| **Löschung nach Event** | Daten werden nach Abschluss gelöscht oder anonymisiert |
| **Foto-Einwilligung** | DSGVO-konforme Einwilligungsverwaltung für Beweisfotos |
| **Kontaktdaten-Einwilligung** | Optionale E-Mail-Erfassung nur mit Consent-Checkbox und Double-Opt-in-Bestätigungs-E-Mail |

**Hinweis zur Datenverarbeitung:** Die App erhebt im Grundbetrieb nur pseudonyme Spielerdaten
(frei gewählter Benutzername, Punktzahl, Zeitstempel). Darüber hinaus können Spieler
**optional und mit expliziter Einwilligung** ihre E-Mail-Adresse hinterlegen. Dieser Vorgang
erfolgt über ein Double-Opt-in-Verfahren: Nach Eingabe wird eine Bestätigungs-E-Mail versendet,
die der Spieler aktiv bestätigen muss. Zusätzlich werden im Rahmen des Opt-in-Prozesses
IP-Adressen protokolliert. Diese erweiterte Datenerhebung muss in der Datenschutzerklärung
der Marketing-Website korrekt abgebildet werden.

---

## 8. Preismodell & Call-to-Action

### 8.1 Preispläne

QuizRace bietet drei Abonnement-Stufen mit steigenden Funktionslimits:

| Feature | **Starter** | **Standard** | **Professional** |
|---|:---:|:---:|:---:|
| Gleichzeitige Events | 1 | 3 | 20 |
| Teams pro Event | 5 | 10 | 100 |
| Kataloge pro Event | 5 | 10 | 50 |
| Fragen pro Katalog | 5 | 10 | 50 |

[HINWEIS: Die konkreten EUR-Preise pro Plan sind in Stripe konfiguriert und nicht im
Quellcode hinterlegt. Die Monatsbeiträge müssen vom Produktverantwortlichen ergänzt werden.]

### 8.2 Kostenlose Testphase

- **7 Tage kostenlos testen** – jedes neue Abonnement startet mit einer Testphase
- Kein Risiko: Kündigung innerhalb der Testphase ohne Kosten
- Zahlungsabwicklung über Stripe (Kreditkarte, SEPA)

### 8.3 Empfohlene Call-to-Actions

Basierend auf dem bestehenden Marketing-Content und der Produktstruktur eignen sich folgende CTAs:

| CTA | Ziel | Priorität |
|---|---|---|
| **„Demo spielen"** | Direkt eine Quiz-Station ausprobieren | Primär |
| **„Live-Dashboard testen"** | Das Dashboard mit echten Daten erleben | Primär |
| **„Kostenlos testen"** | 7-Tage-Trial starten (Registrierung) | Primär |
| **„Kontakt aufnehmen"** | E-Mail an support@quizrace.app | Sekundär |
| **„QR-Station scannen"** | Demo-QR-Code scannen | Sekundär |

### 8.4 Verfügbare Demo-Links

Die folgenden Links können auf der Marketing-Website als interaktive Demos eingebunden werden:

| Demo | Beschreibung |
|---|---|
| **Quiz-Station** | Spieler-Perspektive einer Station erleben |
| **Live-Dashboard** | Echtzeit-Ranking und Teamstatus einsehen |
| **DFB-Quiz** | Beispiel-Quiz mit Fußball-Thema |

[HINWEIS: Die konkreten Demo-URLs sollten vor Veröffentlichung auf Aktualität geprüft werden,
da sie Event-spezifische UUIDs und Tokens enthalten.]

### 8.5 Geschäftsmodell-Zusammenfassung

```
┌─────────────────────────────────────────────────────────┐
│                    QuizRace                              │
│                                                         │
│  ┌─────────┐   ┌──────────┐   ┌──────────────────┐     │
│  │ STARTER │   │ STANDARD │   │  PROFESSIONAL    │     │
│  │         │   │          │   │                  │     │
│  │ 1 Event │   │ 3 Events │   │  20 Events       │     │
│  │ 5 Teams │   │ 10 Teams │   │  100 Teams       │     │
│  │ 5 Kat.  │   │ 10 Kat.  │   │  50 Kataloge     │     │
│  │ 5 Fragen│   │ 10 Fragen│   │  50 Fragen       │     │
│  └─────────┘   └──────────┘   └──────────────────┘     │
│                                                         │
│  ── 7 Tage kostenlos testen ──                          │
│  ── Self-Hosted (Docker) als Alternative ──             │
└─────────────────────────────────────────────────────────┘
```

---

## Anhang: Zusammenfassung für KI-Assistenten

### Kernbotschaften für die Marketing-Website

1. **„Ohne App, sofort spielbar"** – Die wichtigste Botschaft: Null Einstiegshürde
2. **„Verbindet Teams in Echtzeit"** – Live-Ranking und gemeinsames Erlebnis
3. **„Ideal für Sommerfeste, Teamevents und Bildungsrallyes"** – Klare Zielszenarien
4. **„DSGVO-konform, gehostet in Deutschland"** – Vertrauen und Datenschutz
5. **„In unter 10 Minuten startklar"** – Schnelle Einrichtung

### Zielgruppe der Marketing-Website

Die Website richtet sich primär an **Event-Veranstalter** (Admins/Hosts), die QuizRace für
ihre Events einsetzen möchten. Sekundär an **Agenturen**, die QuizRace als White-Label-Lösung
nutzen wollen.

### Tonalität

- **Sprache:** Deutsch (Sie-Form für formelle Texte, Du-Form für lockere Ansprache –
  die bestehenden Texte nutzen überwiegend „du")
- **Stil:** Klar, direkt, ohne Fachjargon – spielerisch aber seriös
- **Vermeiden:** Übertriebene Superlative, technische Details, englische Buzzwords

### Design-Empfehlung

- **Preset:** „aurora" (Blau-Basis) als Ausgangspunkt – ergänzt um Gold-Akzente des Logos
- **Layout:** Hero mit Demo-CTA → Kennzahlen-Leiste → 3-Schritte-Ablauf → Features → FAQ → Pricing → CTA
- **Mobile-first:** Die Zielgruppe (Event-Veranstalter) recherchiert auch mobil
- **Interaktive Elemente:** Eingebettete Demo-Links statt statischer Screenshots
