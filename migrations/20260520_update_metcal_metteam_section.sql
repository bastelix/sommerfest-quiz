UPDATE pages
SET content = REPLACE(
        REPLACE(
            REPLACE(content,
$$<section id="metteam" class="uk-section metcal-section metcal-section--accent" aria-labelledby="metcal-metteam-heading">
  <div class="uk-container">
    <span class="metcal-eyebrow">METTEAM</span>
    <h2 class="metcal-section__title" id="metcal-metteam-heading">Hybridbetrieb mit METTEAM – sauber geregelt.</h2>
    <p class="metcal-section__lead">Bidirektionale Synchronisation, klare Eigentümerschaften und vollständige Protokollierung halten MET/CAL, METTEAM und calServer im Gleichklang.</p>
    <div class="metcal-feature-grid" role="list">
      <article class="metcal-feature" role="listitem">
        <h3>Sync-Regeln pro Feld</h3>
        <p>Feldweise Festlegung, wer führend ist (MET/CAL, METTEAM oder calServer). Konflikte werden als Review-Aufgabe markiert.</p>
      </article>
      <article class="metcal-feature" role="listitem">
        <h3>Last-Write-Wins mit Journal</h3>
        <p>Änderungen werden versioniert, inklusive Delta-Listen, Autor:in und Zeitstempel – nachvollziehbar für Audits.</p>
      </article>
      <article class="metcal-feature" role="listitem">
        <h3>Aktivierung pro Gerät</h3>
        <p>Hybridmodus per Toggle aktivieren: vorhandene Daten werden direkt übernommen, Beenden inklusive Aufräumdialog.</p>
      </article>
    </div>
  </div>
</section>$$,
$$<section id="metteam" class="uk-section uk-section-large uk-section-primary uk-light calserver-metcal metcal-section metcal-metteam" aria-labelledby="metcal-metteam-heading">
  <div class="calserver-metcal__inner">
    <div class="uk-container">
      <div class="metcal-metteam__header">
        <span class="calserver-metcal__eyebrow">FLUKE MET/CAL · MET/TRACK</span>
        <h2 class="calserver-metcal__title" id="metcal-metteam-heading">Ein System. Klare Prozesse.</h2>
        <p class="calserver-metcal__lead">Bidirektionale Synchronisation, klare Eigentümerschaften und vollständige Protokollierung halten MET/CAL, METTEAM und calServer im Gleichklang.</p>
      </div>
      <div class="calserver-metcal__grid metcal-metteam__grid" role="list">
        <article class="calserver-metcal__card" role="listitem">
          <h3>
            <span class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: settings"></span>
            Sync-Regeln pro Feld
          </h3>
          <p>Feldweise Festlegung, wer führend ist (MET/CAL, METTEAM oder calServer). Konflikte werden als Review-Aufgabe markiert.</p>
        </article>
        <article class="calserver-metcal__card calserver-metcal__card--dark" role="listitem">
          <h3>
            <span class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: history"></span>
            Journal statt Doppelpflege
          </h3>
          <p>Änderungen werden versioniert, inklusive Delta-Listen, Autor:in und Zeitstempel – nachvollziehbar für Audits.</p>
        </article>
        <article class="calserver-metcal__card calserver-metcal__card--outline" role="listitem">
          <h3>
            <span class="calserver-metcal__icon-circle" aria-hidden="true" data-uk-icon="icon: bolt"></span>
            Aktivierung pro Gerät
          </h3>
          <p>Hybridmodus per Toggle aktivieren: vorhandene Daten werden direkt übernommen, Beenden inklusive Aufräumdialog.</p>
        </article>
      </div>
      <p class="metcal-metteam__note calserver-metcal__note"><strong>METTEAM bleibt produktiv.</strong> Aufträge laufen weiter – calServer sorgt für Überblick, Freigabe und Nachweise.</p>
    </div>
  </div>
</section>$$),
            'updated_at: 2025-04-05',
            'updated_at: 2025-05-20'
        ),
        'kb_version: 1.2',
        'kb_version: 1.3'
    )
WHERE slug = 'fluke-metcal';
