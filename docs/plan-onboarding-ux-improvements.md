# Plan: UX/UI-Verbesserungen Tenant-Onboarding

## Status quo

Das aktuelle Onboarding unter `/onboarding` besteht aus 5 Schritten:

1. **E-Mail eingeben & bestätigen** – Double-Opt-In
2. **Subdomain wählen** – mit Live-Verfügbarkeitsprüfung
3. **Rechnungsdaten/Impressum** – Adresse + "als Impressum verwenden"
4. **Tarif wählen** – Stripe Pricing Table
5. **App erstellen** – automatische Provisionierung mit Task-Fortschritt

### Identifizierte UX-Probleme

| # | Problem | Auswirkung |
|---|---------|------------|
| 1 | **Kein visueller Fortschrittsbalken** – Die Timeline zeigt nur Text-Tabs (`1. E-Mail`, `2. Subdomain`, …) ohne visuelle Verbindungslinie oder Prozentzahl | Nutzer erkennen nicht sofort, wie weit sie sind und wie viel noch kommt |
| 2 | **Keine Willkommens-/Einstiegsseite** – Das Onboarding startet direkt mit "E-Mail eingeben" ohne Kontext, warum man sich registriert oder was einen erwartet | Fehlende Motivation, hohe Abbruchquote |
| 3 | **"Neu starten"-Button prominent sichtbar** – Der Reset-Button steht direkt unter der Timeline und kann versehentlich geklickt werden | Datenverlust ohne Warnung |
| 4 | **Keine Inline-Validierung** – Fehler werden erst beim Klick auf "Senden"/"Speichern" angezeigt, nicht während der Eingabe | Frustrierende Nutzererfahrung |
| 5 | **Fehlermeldungen per `alert()`** – Fehlermeldungen nutzen Browser-Dialoge statt Inline-Hinweise | Wirkt unprofessionell, schlechte UX |
| 6 | **Kein E-Mail-Resend** – Wenn die Bestätigungsmail nicht ankommt, gibt es keine Möglichkeit, sie erneut zu senden (Rate Limit bei 3/h ohne UI-Feedback) | Nutzer bleibt stecken |
| 7 | **Step 5 zeigt technische Details** – "Mandant anlegen", "Proxy neu laden", "SSL aktivieren" sind Backend-Begriffe, die Endnutzer nicht verstehen | Verwirrung, Unsicherheit |
| 8 | **Keine Erfolgsmeldung** – Nach erfolgreicher Erstellung wird direkt zur neuen App weitergeleitet, ohne Bestätigung oder Zusammenfassung | Kein positives Abschluss-Erlebnis |
| 9 | **Formularfelder ohne Labels** – Nur Placeholder-Text statt echte `<label>`-Elemente (Barrierefreiheit + UX) | Schlechtere Zugänglichkeit, Placeholder verschwindet beim Tippen |
| 10 | **Keine Schritt-Beschreibungen** – Außer bei E-Mail und Subdomain fehlen erklärende Texte zu den einzelnen Schritten | Nutzer weiß nicht, warum Daten benötigt werden |

---

## Geplante Verbesserungen

### 1. Willkommensseite vor Step 1

**Was:** Neue Einstiegsseite (Step 0) mit:
- Headline: „Erstelle deine eigene Quiz-App"
- 3-4 Bullet-Points zu den Vorteilen (z.B. „In 5 Minuten startklar", „Kostenlos testen", „Eigene Subdomain")
- CTA-Button „Jetzt starten"

**Wo:** `templates/onboarding.twig` – neues `#step0`-Div vor `#step1`
**Warum:** Kontext und Motivation vor dem ersten Formular senken die Abbruchquote

---

### 2. Verbesserter Stepper / Progress-Indicator

**Was:** Timeline ersetzen durch einen visuellen Stepper mit:
- Nummerierte Kreise statt nur Text
- Verbindungslinien zwischen den Schritten
- Zustände: ausstehend (grau), aktiv (blau, pulsierend), erledigt (grün mit Häkchen)
- Optional: Prozentzahl oder „Schritt 2 von 5"

**Wo:** `templates/onboarding.twig` (HTML), `public/css/onboarding.css` (neues CSS), `public/js/onboarding.js` (Stepper-Logik anpassen), `public/css/dark.css` + `public/css/highcontrast.css` (Theme-Varianten)
**Warum:** Klare visuelle Orientierung, wo der Nutzer steht

---

### 3. Inline-Validierung und bessere Fehlermeldungen

**Was:**
- Echtzeit-Validierung bei E-Mail- und Subdomain-Feldern (on `input`/`blur`)
- Grüner Rahmen + Häkchen bei gültiger Eingabe
- Roter Rahmen + Fehlertext bei ungültiger Eingabe
- `alert()`-Aufrufe durch Inline-Fehlerbanner im Card ersetzen (UIkit `uk-alert-danger`)

**Wo:** `public/js/onboarding.js` (Validierungslogik), `templates/onboarding.twig` (Platzhalter für Inline-Fehler)
**Warum:** Sofortiges Feedback reduziert Fehler und Frustration

---

### 4. Echte Labels und bessere Formularstruktur

**Was:**
- Sichtbare `<label>`-Elemente über jedem Eingabefeld
- Placeholder als Beispielwerte beibehalten, nicht als einzige Beschriftung
- Hilfetext (`uk-text-meta`) unter Feldern wo nötig (z.B. Subdomain-Regeln)
- Pflichtfeld-Kennzeichnung mit `*`

**Wo:** `templates/onboarding.twig`
**Warum:** Barrierefreiheit (WCAG), bessere Orientierung beim Ausfüllen

---

### 5. E-Mail-Resend-Button mit Countdown

**Was:**
- Nach dem Senden der Bestätigungsmail: Countdown-Timer (60s) anzeigen
- Danach „Erneut senden"-Button aktivieren
- Anzeige der Rate-Limit-Info: „Du kannst bis zu 3 Bestätigungen pro Stunde anfordern"
- Hinweis „Auch im Spam-Ordner nachschauen"

**Wo:** `templates/onboarding.twig` (UI), `public/js/onboarding.js` (Timer-Logik)
**Warum:** Nutzer bleibt nicht stecken, wenn E-Mail verzögert oder im Spam

---

### 6. Nutzerfreundliche Task-Anzeige in Step 5

**Was:** Technische Task-Labels durch nutzerfreundliche ersetzen:

| Aktuell | Neu |
|---------|-----|
| „Mandant anlegen" | „Deine App wird eingerichtet" |
| „Inhalte importieren" | „Beispiel-Inhalte werden geladen" |
| „Proxy neu laden" | „Verbindung wird konfiguriert" |
| „SSL aktivieren" | „Sichere Verbindung wird aktiviert" |
| „Warten auf Verfügbarkeit" | „Fast geschafft – deine App startet" |

Zusätzlich: Dezente Ladeanimation statt nur Spinner, eventuell animiertes Icon pro Schritt

**Wo:** `public/js/onboarding.js` (Task-Labels), `templates/onboarding.twig` (optionale Illustration)
**Warum:** Endnutzer sollen sich sicher fühlen, nicht verunsichert durch Fachbegriffe

---

### 7. Erfolgsseite nach Abschluss

**Was:** Statt direkter Weiterleitung eine Erfolgsseite mit:
- Konfetti-Animation oder Check-Icon
- „Deine App ist bereit!"
- Zusammenfassung: gewählte Subdomain, Tarif, E-Mail
- Primärer CTA: „Zur App" (Link auf neue Subdomain)
- Sekundärer CTA: „Zugangsdaten-E-Mail prüfen"

**Wo:** `templates/onboarding.twig` (neues `#step-success`-Div), `public/js/onboarding.js` (Redirect-Logik anpassen), `public/css/onboarding.css` (Animations-Styles)
**Warum:** Positives Abschluss-Erlebnis, wichtige Infos nochmal zusammengefasst

---

### 8. „Neu starten" absichern

**Was:**
- Button visuell zurücknehmen (klein, Text-Link statt Button, Sekundärfarbe)
- Bestätigungsdialog vor dem Reset: „Möchtest du wirklich neu starten? Alle bisherigen Eingaben gehen verloren."
- UIkit-Modal statt `alert()`/`confirm()`

**Wo:** `templates/onboarding.twig` (Button-Styling), `public/js/onboarding.js` (Confirm-Dialog)
**Warum:** Versehentlicher Datenverlust verhindern

---

## Zusammenfassung der betroffenen Dateien

| Datei | Änderungsart |
|-------|-------------|
| `templates/onboarding.twig` | Willkommensseite, Labels, Stepper-HTML, Erfolgsseite, Fehler-Platzhalter |
| `public/js/onboarding.js` | Inline-Validierung, Stepper-Logik, E-Mail-Resend, Task-Labels, Erfolgsseite, Confirm-Dialog |
| `public/css/onboarding.css` | Stepper-Styles, Animations, Erfolgsseite, Button-Styles |
| `public/css/dark.css` | Dark-Mode-Varianten für neue Stepper-Styles |
| `public/css/highcontrast.css` | High-Contrast-Varianten für neue Stepper-Styles |

## Nicht im Scope

- Backend-Änderungen (Controller, Services, API-Endpunkte)
- Datenbankmigrationen
- Neue Routen
- Internationalisierung (i18n)
- Post-Onboarding Guide / Dashboard-Tour

Alle Verbesserungen sind rein frontend-seitig (HTML, CSS, JS) und erfordern keine Backend-Anpassungen.
