-- Switch calHelp FAQ block to accordion layout and add follow-up hint
UPDATE pages
SET content = REPLACE(
    content,
    $$    <dl class="calhelp-faq" aria-label="Häufig gestellte Fragen">
      <div class="uk-card uk-card-primary uk-card-body calhelp-faq__item">
        <dt>Bleibt MET/TEAM nutzbar?</dt>
        <dd>Ja. Bestehende Lösungen können angebunden bleiben (Fernsteuerung/Befüllen). Eine Ablösung ist optional und schrittweise.</dd>
      </div>
      <div class="uk-card uk-card-primary uk-card-body calhelp-faq__item">
        <dt>Was wird übernommen?</dt>
        <dd>Geräte, Historien, Zertifikate/PDFs, Kund:innen/Standorte, benutzerdefinierte Felder – soweit technisch verfügbar. Alles mit Mapping-Report und Abweichungsprotokoll.</dd>
      </div>
      <div class="uk-card uk-card-primary uk-card-body calhelp-faq__item">
        <dt>Wie sicher ist der Betrieb?</dt>
        <dd>Hosting in Deutschland oder On-Prem, Rollen/Rechte, Protokollierung. DSGVO-konform – inkl. transparentem Datenschutztext.</dd>
      </div>
      <div class="uk-card uk-card-primary uk-card-body calhelp-faq__item">
        <dt>Wie lange dauert der Umstieg?</dt>
        <dd>Abhängig von Datenumfang und Komplexität. Der Pilot liefert einen belastbaren Zeitplan für den Produktivlauf.</dd>
      </div>
    </dl>
$$,
    $$    <ul class="calhelp-faq" aria-label="Häufig gestellte Fragen" data-uk-accordion="multiple: true">
      <li class="calhelp-faq__item">
        <a class="uk-accordion-title" href="#">Bleibt MET/TEAM nutzbar?</a>
        <div class="uk-accordion-content">
          <p>Ja. Bestehende Lösungen können angebunden bleiben (Fernsteuerung/Befüllen). Eine Ablösung ist optional und schrittweise.</p>
        </div>
      </li>
      <li class="calhelp-faq__item">
        <a class="uk-accordion-title" href="#">Was wird übernommen?</a>
        <div class="uk-accordion-content">
          <p>Geräte, Historien, Zertifikate/PDFs, Kund:innen/Standorte, benutzerdefinierte Felder – soweit technisch verfügbar. Alles mit Mapping-Report und Abweichungsprotokoll.</p>
        </div>
      </li>
      <li class="calhelp-faq__item">
        <a class="uk-accordion-title" href="#">Wie sicher ist der Betrieb?</a>
        <div class="uk-accordion-content">
          <p>Hosting in Deutschland oder On-Prem, Rollen/Rechte, Protokollierung. DSGVO-konform – inkl. transparentem Datenschutztext.</p>
        </div>
      </li>
      <li class="calhelp-faq__item">
        <a class="uk-accordion-title" href="#">Wie lange dauert der Umstieg?</a>
        <div class="uk-accordion-content">
          <p>Abhängig von Datenumfang und Komplexität. Der Pilot liefert einen belastbaren Zeitplan für den Produktivlauf.</p>
        </div>
      </li>
    </ul>
    <div class="calhelp-faq__footer">
      <span class="calhelp-faq__footer-hint">Noch nicht fündig geworden?</span>
      <a class="calhelp-faq__footer-link" href="#conversation">Weitere Fragen → Gespräch</a>
    </div>
$$
)
WHERE slug = 'calhelp';
