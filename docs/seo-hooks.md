# SEO Hooks

Neue SEO-Features können über Events eingebunden werden. Bei Änderungen der Seiteneinstellungen
werden Events aus dem Domain-Layer ausgelöst, die Listener im Application-Layer verarbeiten.

## Verfügbare Events

- `SeoConfigSaved` – Wird ausgelöst, wenn eine SEO-Konfiguration erstmals gespeichert wird.
- `SeoConfigUpdated` – Wird ausgelöst, wenn eine bestehende Konfiguration aktualisiert wird.

## Listener registrieren

Listener befinden sich im Verzeichnis `src/Application/EventListener/`. Ein Listener registriert sich
am `EventDispatcher` und reagiert auf die oben genannten Events. Beispiel:

```php
use App\Application\EventListener\SeoConfigListener;
use App\Infrastructure\Event\EventDispatcher;
use App\Infrastructure\Cache\PageSeoCache;

$dispatcher = new EventDispatcher();
SeoConfigListener::register($dispatcher, new PageSeoCache());
```

Im Listener können Aktionen wie Cache-Invalidierung oder Pings an externe Dienste implementiert
werden. Eigene Listener können analog aufgebaut und über `addListener` am Dispatcher registriert
werden.
