-- Refresh calHelp news section with mosaic layout and KPI badges
UPDATE pages
SET content = REPLACE(
    content,
    $$    <div class="uk-grid-large uk-child-width-1-2@m" data-uk-grid>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="news-changelog-title">
        <h3 id="news-changelog-title" class="uk-card-title">Changelog kompakt</h3>
        <p class="uk-text-meta">Zuletzt aktualisiert am 04.10.2025</p>
        <ul class="uk-list uk-list-bullet">
          <li>Migration: Delta-Sync für MET/TRACK erweitert.</li>
          <li>Reports: Konformitätslogik mit Guardband-Optionen ergänzt.</li>
          <li>Integrationen: MET/TEAM-Connector mit zusätzlichen Webhooks.</li>
        </ul>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="news-recipe-title">
        <h3 id="news-recipe-title" class="uk-card-title">Praxisrezept in 3 Schritten</h3>
        <p class="uk-text-meta">Zuletzt aktualisiert am 27.09.2025</p>
        <p><strong>Thema:</strong> Konformitätslegende sauber integrieren.</p>
        <ol class="uk-list uk-list-decimal">
          <li>Legende zentral in calHelp pflegen.</li>
          <li>Template-Varianten für Kund:innen definieren.</li>
          <li>Report-Diffs mit Golden Samples gegenprüfen.</li>
        </ol>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="news-usecase-title">
        <h3 id="news-usecase-title" class="uk-card-title">Use-Case-Spotlight</h3>
        <p class="uk-text-meta">Zuletzt aktualisiert am 18.09.2025</p>
        <p><strong>Ausgangslage:</strong> Stark gewachsene Kalibrierabteilung mit Inseltools.</p>
        <p><strong>Vorgehen:</strong> Migration aus MET/TRACK, Schnittstelle zu MET/TEAM, SSO.</p>
        <p><strong>Ergebnis:</strong> Auditberichte in 30 % weniger Zeit, klare Verantwortlichkeiten.</p>
        <p><strong>Learnings:</strong> Frühzeitig Rollenmodell definieren, Dokumentation als laufenden Prozess etablieren.</p>
        <p><strong>Nächste Schritte:</strong> Automatisierte Erinnerungen für Prüfmittel und Lieferant:innen.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="news-standards-title">
        <h3 id="news-standards-title" class="uk-card-title">Standards verständlich</h3>
        <p class="uk-text-meta">Zuletzt aktualisiert am 12.09.2025</p>
        <p><strong>Thema:</strong> Guardband &amp; MU in 5 Minuten erklärt.</p>
        <p>Beispiel: Messwert 10,0 mm mit MU 0,3 mm. Guardband reduziert die Toleranzgrenze auf 9,7–10,3 mm. calHelp dokumentiert automatisch, wie Entscheidung und Unsicherheit zusammenhängen.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card" aria-labelledby="news-roadmap-title">
        <h3 id="news-roadmap-title" class="uk-card-title">Roadmap-Ausblick</h3>
        <p class="uk-text-meta">Zuletzt aktualisiert am 05.09.2025</p>
        <ul class="uk-list uk-list-bullet">
          <li>Q1: Templates für Prüfaufträge &amp; Zertifikate.</li>
          <li>Q2: SSO-Starter für EntraID und Google.</li>
          <li>Q3: API-Rezepte für ERP- und MES-Anbindungen.</li>
        </ul>
      </article>
    </div>
$$,
    $$    <div class="calhelp-news-grid" role="list">
      <article class="uk-card uk-card-primary uk-card-body calhelp-card calhelp-news-card calhelp-news-card--changelog" aria-labelledby="news-changelog-title" role="listitem">
        <header class="calhelp-news-card__header">
          <span class="calhelp-news-card__icon" aria-hidden="true" data-uk-icon="icon: refresh"></span>
          <div>
            <h3 id="news-changelog-title" class="uk-card-title">Changelog kompakt</h3>
            <p class="uk-text-meta">Zuletzt aktualisiert am 04.10.2025</p>
          </div>
        </header>
        <ul class="uk-list uk-list-bullet">
          <li>Migration: Delta-Sync für MET/TRACK erweitert.</li>
          <li>Reports: Konformitätslogik mit Guardband-Optionen ergänzt.</li>
          <li>Integrationen: MET/TEAM-Connector mit zusätzlichen Webhooks.</li>
        </ul>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card calhelp-news-card calhelp-news-card--praxis" aria-labelledby="news-recipe-title" role="listitem">
        <header class="calhelp-news-card__header">
          <span class="calhelp-news-card__icon" aria-hidden="true" data-uk-icon="icon: file-text"></span>
          <div>
            <h3 id="news-recipe-title" class="uk-card-title">Praxisrezept in 3 Schritten</h3>
            <p class="uk-text-meta">Zuletzt aktualisiert am 27.09.2025</p>
          </div>
        </header>
        <p class="calhelp-news-card__intro"><strong>Thema:</strong> Konformitätslegende sauber integrieren.</p>
        <ol class="calhelp-news-steps" aria-label="Konformitätslegende integrieren">
          <li class="calhelp-news-step">
            <span class="calhelp-news-step__icon" aria-hidden="true" data-uk-icon="icon: file-text"></span>
            <div class="calhelp-news-step__body">
              <span class="calhelp-news-step__label">Schritt&nbsp;1</span>
              <p class="calhelp-news-step__text">Legende zentral in calHelp pflegen.</p>
            </div>
          </li>
          <li class="calhelp-news-step">
            <span class="calhelp-news-step__icon" aria-hidden="true" data-uk-icon="icon: cog"></span>
            <div class="calhelp-news-step__body">
              <span class="calhelp-news-step__label">Schritt&nbsp;2</span>
              <p class="calhelp-news-step__text">Template-Varianten für Kund:innen definieren.</p>
            </div>
          </li>
          <li class="calhelp-news-step">
            <span class="calhelp-news-step__icon" aria-hidden="true" data-uk-icon="icon: check"></span>
            <div class="calhelp-news-step__body">
              <span class="calhelp-news-step__label">Schritt&nbsp;3</span>
              <p class="calhelp-news-step__text">Report-Diffs mit Golden Samples gegenprüfen.</p>
            </div>
          </li>
        </ol>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card calhelp-news-card calhelp-news-card--usecase" aria-labelledby="news-usecase-title" role="listitem">
        <header class="calhelp-news-card__header">
          <span class="calhelp-news-card__icon" aria-hidden="true" data-uk-icon="icon: users"></span>
          <div>
            <h3 id="news-usecase-title" class="uk-card-title">Use-Case-Spotlight</h3>
            <p class="uk-text-meta">Zuletzt aktualisiert am 18.09.2025</p>
          </div>
        </header>
        <div class="calhelp-news-card__body">
          <p><strong>Ausgangslage:</strong> Stark gewachsene Kalibrierabteilung mit Inseltools.</p>
          <p><strong>Vorgehen:</strong> Migration aus MET/TRACK, Schnittstelle zu MET/TEAM, SSO.</p>
          <p><strong>Ergebnis:</strong> Auditberichte in 30&nbsp;% weniger Zeit, klare Verantwortlichkeiten.</p>
          <p><strong>Learnings:</strong> Frühzeitig Rollenmodell definieren, Dokumentation als laufenden Prozess etablieren.</p>
          <p><strong>Nächste Schritte:</strong> Automatisierte Erinnerungen für Prüfmittel und Lieferant:innen.</p>
        </div>
        <ul class="calhelp-news-kpis" role="list" aria-label="Use-Case KPIs">
          <li class="calhelp-news-kpi">
            <span class="calhelp-news-kpi__icon" aria-hidden="true" data-uk-icon="icon: calendar"></span>
            <span class="calhelp-news-kpi__value">30&nbsp;%</span>
            <span class="calhelp-news-kpi__label">schnellere Auditberichte</span>
          </li>
          <li class="calhelp-news-kpi">
            <span class="calhelp-news-kpi__icon" aria-hidden="true" data-uk-icon="icon: lock"></span>
            <span class="calhelp-news-kpi__value">0</span>
            <span class="calhelp-news-kpi__label">kritische Abweichungen beim Cutover</span>
          </li>
          <li class="calhelp-news-kpi">
            <span class="calhelp-news-kpi__icon" aria-hidden="true" data-uk-icon="icon: commenting"></span>
            <span class="calhelp-news-kpi__value">100&nbsp;%</span>
            <span class="calhelp-news-kpi__label">Team onboarding in zwei Wochen</span>
          </li>
        </ul>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card calhelp-news-card calhelp-news-card--insight" aria-labelledby="news-standards-title" role="listitem">
        <header class="calhelp-news-card__header">
          <span class="calhelp-news-card__icon" aria-hidden="true" data-uk-icon="icon: info"></span>
          <div>
            <h3 id="news-standards-title" class="uk-card-title">Standards verständlich</h3>
            <p class="uk-text-meta">Zuletzt aktualisiert am 12.09.2025</p>
          </div>
        </header>
        <p class="calhelp-news-card__intro"><strong>Thema:</strong> Guardband &amp; MU in 5 Minuten erklärt.</p>
        <p>Beispiel: Messwert 10,0 mm mit MU 0,3 mm. Guardband reduziert die Toleranzgrenze auf 9,7–10,3 mm. calHelp dokumentiert automatisch, wie Entscheidung und Unsicherheit zusammenhängen.</p>
      </article>
      <article class="uk-card uk-card-primary uk-card-body calhelp-card calhelp-news-card calhelp-news-card--roadmap" aria-labelledby="news-roadmap-title" role="listitem">
        <header class="calhelp-news-card__header">
          <span class="calhelp-news-card__icon" aria-hidden="true" data-uk-icon="icon: calendar"></span>
          <div>
            <h3 id="news-roadmap-title" class="uk-card-title">Roadmap-Ausblick</h3>
            <p class="uk-text-meta">Zuletzt aktualisiert am 05.09.2025</p>
          </div>
        </header>
        <ul class="uk-list uk-list-bullet">
          <li>Q1: Templates für Prüfaufträge &amp; Zertifikate.</li>
          <li>Q2: SSO-Starter für EntraID und Google.</li>
          <li>Q3: API-Rezepte für ERP- und MES-Anbindungen.</li>
        </ul>
      </article>
    </div>
$$),
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'calhelp';
