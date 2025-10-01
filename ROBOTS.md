# robots.md – Coding Guidelines für das Quiz-Projekt (PHP 8.x)

> Diese Datei definiert die verbindlichen Coding-Regeln für die Entwicklung und Erweiterung des Quiz-Modells.
> Die Einhaltung wird regelmäßig durch Tools wie Codacy, PHP_CodeSniffer und statische Analyse überprüft.
> **Alle Beiträge (auch Assistenten-generiert) müssen diese Standards erfüllen!**

## 1. **Allgemeines**

* **Quellcode muss klar, verständlich, modular und wartbar sein.**
* **Kommentare und PHPDoc** sind Pflicht für alle **Funktionen, Methoden, Klassen und komplexe Logik**.
* Der Code muss **vollständig PSR-12-konform** sein.
* **Automatische Codeanalyse (z.\u202fB. mit PHP_CodeSniffer)** ist verpflichtend; alle Warnungen und Fehler müssen vor dem Commit behoben sein.
* **Bestehende Migrationen dürfen niemals geändert werden.**
  Stattdessen ist bei Anpassungen immer eine **neue Migration** anzulegen, da frühere Migrationen bereits eingespielt sind.

## 2. **Datei- und Namenskonventionen**

* **Dateien**: UTF-8 (ohne BOM), `.php`-Endung, eine Klasse/Interface/Trait pro Datei.
* **Klassennamen**: `UpperCamelCase` (PSR-0/4 Autoloading).
* **Dateinamen**: Spiegeln den Klassennamen (z.\u202fB. `QuizService.php`).
* **Namespaces**: Gemäß PSR-4, Vendor/Projekt-Struktur.

## 3. **Struktur und Lesbarkeit**

* **Jede Zeile max. 120 Zeichen!**
  (Keine Zeile, weder Code noch Kommentar, darf die 120-Zeichen-Grenze überschreiten.)
* **Einrückung:** 4 Leerzeichen (KEIN Tab).
* **Keine gemischten Leerzeichen/Tabs.**
* **Codeblöcke** (if, foreach, etc.) **immer mit geschweiften Klammern**.
* **Keine verschachtelten/ineinandergeschachtelten Schleifen oder ifs, wenn es vermieden werden kann.**

## 4. **Code-Stil & Syntax**

* **Variablen/Properties:** `camelCase`
* **Methoden:** `camelCase`
* **Klassen:** `UpperCamelCase`
* **Konstanten:** `UPPER_CASE_SNAKE`
* **Keine Kurzschreibweise für PHP-Tags** (`<?php` statt `<?`)
* **Type Hints und Return Types** für alle Methoden (PHP 7.4+).
* **Strikte Typisierung:**
  Am Anfang jeder Datei:

  ```php
  declare(strict_types=1);
  ```
* **Vermeide Deprecated Features.**
* **Verwende nur Features, die mit PHP 8.x kompatibel sind.**

## 5. **Kommentare und Dokumentation**

* **Jede Funktion und Klasse erhält PHPDoc:**

  ```php
  /**
   * Fügt einen neuen Katalog hinzu.
   *
   * @param array $data Die Katalogdaten.
   * @return bool Erfolg oder Fehler.
   */
  public function addCatalog(array $data): bool { ... }
  ```
* **Keine unnötigen Kommentare!**
  (Kommentiere *was* und *warum*, nicht wie etwas funktioniert, wenn es selbsterklärend ist.)
* **TODO/FIXME-Kommentare:**
  Immer mit Jira-/Issue-Link o.ä.

## 6. **Fehlerbehandlung**

* **Ausnahmen (Exceptions) bevorzugen statt Error Codes.**
* **Kein Suppressing (`@`) von Fehlern.**
* **Alle Fehlerfälle explizit behandeln oder dokumentieren.**

## 7. **Sicherheit und Clean Code**

* **Kein unsicheres SQL!**
  IMMER Prepared Statements (PDO, Doctrine, etc.).
* **Keine eval()-Funktion.**
* **Keine globalen Variablen.**
* **Keine Suppression-Operatoren (`@`).**
* **Saubere Trennung von Logik, Templates, Konfiguration und Datenhaltung.**

## 8. **Tests und Qualitätssicherung**

* **Unit-Tests** für jede Kernkomponente (z.\u202fB. PHPUnit).
* **Coverage-Threshold** mindestens 80% (Ziel).
* **Kein Code ohne Tests in „main“ oder „release“-Branch!**
* **Manuelle Tests auf allen unterstützten PHP 8.x-Versionen.**

## 9. **Versionskontrolle und Commits**

* **Ein Commit pro Issue/Feature/Fix.**
* **Aussagekräftige Commit-Messages (Konvention: [#Ticket] Kurze Beschreibung).**
* **Keine sensiblen Daten/Keys ins Repository!**

## 10. **Typische Fehler/Verbesserungshinweise (siehe Codacy/PHP_CodeSniffer)**

* **Nie mehr als 120 Zeichen pro Zeile!**
* **Jede Klammer auf neuer Zeile (außer Funktionsaufrufe).**
* **Immer Type Hints nutzen.**
* **Immer Sichtbarkeit (`public`, `private`, `protected`) angeben.**
* **Keine leeren catch-Blöcke.**
* **Keine mehrfachen, unnötigen Leerzeilen (>1).**
* **Keine Funktionsdefinitionen im if/else/loop.**
* **Keine gemischten String- und Array-Typen ohne explizite Typisierung.**
* **Schleifen, die break/continue verwenden, müssen nachvollziehbar und dokumentiert sein.**
* **Keine harte Codierung von Magic Numbers.**

## 11. **Beispiel für einen sauberen Methodenblock**

```php
declare(strict_types=1);

namespace App\Service;

use App\Model\Catalog;

/**
 * Verarbeitet Katalog-Daten.
 */
class CatalogService
{
    /**
     * Legt einen neuen Katalog an.
     *
     * @param string $name
     * @param int $eventId
     * @return Catalog
     * @throws CatalogException
     */
    public function createCatalog(string $name, int $eventId): Catalog
    {
        // Validierung
        if (empty($name)) {
            throw new CatalogException('Katalogname darf nicht leer sein.');
        }

        // Speichern...
        // (Prepared Statement Beispiel)
        // ...

        return new Catalog($name, $eventId);
    }
}
```

## 12. **Tools**

* **Automatische Prüfung:**

  * PHP_CodeSniffer (`phpcs`) mit PSR-12
  * PHPStan (Level 5+)
  * Codacy / SonarCloud
  * PHPUnit

* **Vor jedem Commit:**

  * `phpcs src/`
  * `phpstan analyse src/`
  * Alle Tests grün

## 13. **Review-Checkliste für Pull Requests**

* [ ] Keine Zeile > 120 Zeichen?
* [ ] Alle Methoden/Klassen mit PHPDoc?
* [ ] Nur geprüfte Typen/Type Hints verwendet?
* [ ] Keine Deprecated Features?
* [ ] Fehlerbehandlung robust und sauber?
* [ ] Keine sensiblen Daten im Code?
* [ ] Ein Commit pro Feature/Fix?
* [ ] Alle Tests laufen?

## 14. **Zusätzliche Hinweise für Code-Assistenz**

* **Kommentare im Stil von „// Kommentar“ sind nur als TODO/FIXME zulässig.**
* **Für alle von der Coding-Guideline abweichenden Vorschläge:
  Immer mit Hinweis und Link auf offizielle PHP-Standards (PSR, RFC, etc.)**

## 15. **Fehlervermeidung bei Tests**

* In Tests, die Rollen erfordern, muss stets `session_start()` aufgerufen und
  `$_SESSION['user']` mit der passenden Rolle belegt werden.
* Doppelte `session_start()`-Aufrufe innerhalb eines Tests sind zu vermeiden.
* Vor jedem Commit sind alle PHPUnit-Tests mit `./vendor/bin/phpunit` auszuführen.

# Ende robots.md

**→ Diese Datei muss jedem Pull Request beigelegt und beachtet werden!**
