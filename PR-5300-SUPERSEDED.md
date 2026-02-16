# PR #5300 – Superseded

PR #5300 ("refactor: simplify newsletter admin UI into single tabbed page") ist
durch die bereits gemergten PRs **#5303** und **#5312** vollständig ersetzt.

## Was bereits auf `main` ist

- Konsolidierte Newsletter-Admin-Seite mit Tabs (Kampagnen + Bestätigungsseite)
- Graceful Provider-Fallback mit try-catch (Review-Fix aus #5312)
- Inline Styles entfernt (Review-Fix aus #5312)
- Saubere Route-Extraktion nach `Routes/admin.php`
- Redirects mit `?tab=campaigns` Parameter
- Alle neuen Übersetzungskeys (de + en)

## Konflikte

Betroffen sind 6 Dateien:

| Datei | Ursache |
|-------|---------|
| `resources/lang/de.php` | Gleiche Keys an gleicher Stelle hinzugefügt |
| `resources/lang/en.php` | Gleiche Keys an gleicher Stelle hinzugefügt |
| `src/Controller/Admin/MarketingNewsletterController.php` | Gleiche Dependencies + Provider-Logic |
| `src/Controller/Admin/NewsletterCampaignController.php` | Redirect-URLs geändert |
| `src/routes.php` | Inline-Routes vs. extrahierte Routes |

Alle Konflikte entstehen, weil `main` und der PR-Branch dieselben Änderungen
an denselben Stellen gemacht haben. Eine Auflösung ist sinnlos.

## Empfehlung

PR schließen. Keine weitere Aktion nötig.
