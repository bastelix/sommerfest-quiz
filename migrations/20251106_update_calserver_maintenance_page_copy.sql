UPDATE pages
SET title = 'calHelp Wartung',
    content = $$
<section class="calserver-maintenance-section uk-section uk-section-medium" id="timeline" aria-labelledby="maintenance-timeline-heading">
  <div class="uk-container">
    <div class="calserver-maintenance-section__header">
      <span class="calserver-maintenance-section__eyebrow">Wartungsfahrplan</span>
      <h2 class="calserver-maintenance-section__title" id="maintenance-timeline-heading">So begleiten wir die Wartung</h2>
      <p class="calserver-maintenance-section__intro">
        Wir aktualisieren Frameworks, Inhalte und Infrastruktur deines Projekts. Während des Wartungszeitraums behalten wir alle Systeme im Blick
        und informieren dich, sobald deine Website wieder vollständig erreichbar ist.
      </p>
    </div>
    <ol class="calserver-maintenance-timeline" role="list">
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">Tag 1</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Kick-off &amp; Sicherung</h3>
          <p>Wir informieren Stakeholder, frieren Deployments ein und erstellen vollständige Backups der aktuellen Website-Version.</p>
        </div>
      </li>
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">Tag 2</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Technische Updates</h3>
          <p>Frameworks, Plugins und Server werden aktualisiert. Automatisierte Tests prüfen Performance und Sicherheit.</p>
        </div>
      </li>
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">Tag 3</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Inhalte &amp; Qualitätssicherung</h3>
          <p>Wir kontrollieren Formulare, Tracking und redaktionelle Inhalte und stimmen Anpassungen mit dir ab.</p>
        </div>
      </li>
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">Tag 4</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Wieder live &amp; Monitoring</h3>
          <p>Die Website geht wieder online. Wir überwachen Metriken und Support-Kanäle, bis alles stabil läuft.</p>
        </div>
      </li>
    </ol>
    <div class="calserver-maintenance-note" role="note">
      <span class="calserver-maintenance-note__icon" aria-hidden="true" data-uk-icon="icon: info"></span>
      <p class="calserver-maintenance-note__text">
        Live-Statusmeldungen findest du jederzeit im <a href="{{ basePath }}/status">calHelp Statusbereich</a>. Dort kannst du dich auch für E-Mail-Updates registrieren.
      </p>
    </div>
  </div>
</section>
<section class="calserver-maintenance-section uk-section uk-section-medium" id="support" aria-labelledby="maintenance-support-heading">
  <div class="uk-container">
    <div class="uk-grid-large" data-uk-grid>
      <div class="uk-width-1-2@m">
        <div class="calserver-maintenance-support-card" role="group" aria-labelledby="maintenance-support-heading">
          <span class="calserver-maintenance-section__eyebrow">Support</span>
          <h2 class="calserver-maintenance-section__title" id="maintenance-support-heading">Wir sind erreichbar</h2>
          <p class="calserver-maintenance-section__intro">
            Unser Projektteam begleitet die Wartung durchgehend. Melde dich, wenn du Auffälligkeiten bemerkst oder Unterstützung benötigst.
          </p>
          <ul class="calserver-maintenance-support-list" role="list">
            <li class="calserver-maintenance-support-item">
              <span class="calserver-maintenance-support-icon" aria-hidden="true" data-uk-icon="icon: receiver"></span>
              <div>
                <strong>Telefon</strong>
                <p><a href="tel:+4933203609080">+49&nbsp;33203&nbsp;609080</a> (Mo–Fr 8–18&nbsp;Uhr)</p>
              </div>
            </li>
            <li class="calserver-maintenance-support-item">
              <span class="calserver-maintenance-support-icon" aria-hidden="true" data-uk-icon="icon: mail"></span>
              <div>
                <strong>E-Mail</strong>
                <p><a class="js-email-link" data-user="support" data-domain="calhelp.de" href="#">support [at] calhelp.de</a></p>
              </div>
            </li>
            <li class="calserver-maintenance-support-item">
              <span class="calserver-maintenance-support-icon" aria-hidden="true" data-uk-icon="icon: comments"></span>
              <div>
                <strong>Status-Updates</strong>
                <p><a href="{{ basePath }}/status">Status abonnieren</a> und Benachrichtigungen direkt erhalten.</p>
              </div>
            </li>
          </ul>
        </div>
      </div>
      <div class="uk-width-1-2@m">
        <div class="calserver-maintenance-support-card calserver-maintenance-support-card--secondary">
          <h3>Was du jetzt tun kannst</h3>
          <ul class="calserver-maintenance-checklist" role="list">
            <li>
              <span class="calserver-maintenance-check" aria-hidden="true" data-uk-icon="icon: check"></span>
              Informiere dein Team sowie wichtige Ansprechpartner:innen über den Wartungszeitraum.
            </li>
            <li>
              <span class="calserver-maintenance-check" aria-hidden="true" data-uk-icon="icon: check"></span>
              Sichere zeitkritische Inhalte oder Kampagnen als lokale Kopie, falls du während der Wartung darauf zugreifen musst.
            </li>
            <li>
              <span class="calserver-maintenance-check" aria-hidden="true" data-uk-icon="icon: check"></span>
              Prüfe nach der Wartung die wichtigsten Journeys (Kontaktformulare, Shop, Tracking) und gib uns Rückmeldung.
            </li>
          </ul>
          <p class="calserver-maintenance-note__text">
            Die nächste geplante Wartung kündigen wir mindestens 14 Tage vorher über den Statusbereich und per E-Mail an.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>
$$
WHERE slug = 'calserver-maintenance';

UPDATE pages
SET title = 'calHelp Maintenance',
    content = $$
<section class="calserver-maintenance-section uk-section uk-section-medium" id="timeline" aria-labelledby="maintenance-timeline-heading">
  <div class="uk-container">
    <div class="calserver-maintenance-section__header">
      <span class="calserver-maintenance-section__eyebrow">Maintenance timeline</span>
      <h2 class="calserver-maintenance-section__title" id="maintenance-timeline-heading">How we guide the process</h2>
      <p class="calserver-maintenance-section__intro">
        We update frameworks, content and infrastructure for your project. Throughout the maintenance period we monitor every system and let you know as soon as your site is fully available again.
      </p>
    </div>
    <ol class="calserver-maintenance-timeline" role="list">
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">Day 1</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Kick-off &amp; backup</h3>
          <p>We notify stakeholders, pause deployments and take complete snapshots of the current website.</p>
        </div>
      </li>
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">Day 2</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Technical upgrades</h3>
          <p>Frameworks, plugins and servers are updated. Automated tests cover performance and security.</p>
        </div>
      </li>
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">Day 3</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Content &amp; quality review</h3>
          <p>Forms, tracking and editorial content are verified and shared with you for approval.</p>
        </div>
      </li>
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">Day 4</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Back online &amp; monitoring</h3>
          <p>The site goes live again. We monitor metrics and support channels until everything runs smoothly.</p>
        </div>
      </li>
    </ol>
    <div class="calserver-maintenance-note" role="note">
      <span class="calserver-maintenance-note__icon" aria-hidden="true" data-uk-icon="icon: info"></span>
      <p class="calserver-maintenance-note__text">
        Check the <a href="{{ basePath }}/status">calHelp status page</a> for live updates and email notifications.
      </p>
    </div>
  </div>
</section>
<section class="calserver-maintenance-section uk-section uk-section-medium" id="support" aria-labelledby="maintenance-support-heading">
  <div class="uk-container">
    <div class="uk-grid-large" data-uk-grid>
      <div class="uk-width-1-2@m">
        <div class="calserver-maintenance-support-card" role="group" aria-labelledby="maintenance-support-heading">
          <span class="calserver-maintenance-section__eyebrow">Support</span>
          <h2 class="calserver-maintenance-section__title" id="maintenance-support-heading">We are available</h2>
          <p class="calserver-maintenance-section__intro">
            Our project team stays with you throughout the maintenance. Reach out if you notice anything unusual or need assistance.
          </p>
          <ul class="calserver-maintenance-support-list" role="list">
            <li class="calserver-maintenance-support-item">
              <span class="calserver-maintenance-support-icon" aria-hidden="true" data-uk-icon="icon: receiver"></span>
              <div>
                <strong>Phone</strong>
                <p><a href="tel:+4933203609080">+49&nbsp;33203&nbsp;609080</a> (Mon–Fri 8&nbsp;am–6&nbsp;pm CET)</p>
              </div>
            </li>
            <li class="calserver-maintenance-support-item">
              <span class="calserver-maintenance-support-icon" aria-hidden="true" data-uk-icon="icon: mail"></span>
              <div>
                <strong>Email</strong>
                <p><a class="js-email-link" data-user="support" data-domain="calhelp.de" href="#">support [at] calhelp.de</a></p>
              </div>
            </li>
            <li class="calserver-maintenance-support-item">
              <span class="calserver-maintenance-support-icon" aria-hidden="true" data-uk-icon="icon: comments"></span>
              <div>
                <strong>Status updates</strong>
                <p><a href="{{ basePath }}/status">Subscribe to updates</a> and receive notifications instantly.</p>
              </div>
            </li>
          </ul>
        </div>
      </div>
      <div class="uk-width-1-2@m">
        <div class="calserver-maintenance-support-card calserver-maintenance-support-card--secondary">
          <h3>Helpful checklist</h3>
          <ul class="calserver-maintenance-checklist" role="list">
            <li>
              <span class="calserver-maintenance-check" aria-hidden="true" data-uk-icon="icon: check"></span>
              Inform your team and key stakeholders about the maintenance period.
            </li>
            <li>
              <span class="calserver-maintenance-check" aria-hidden="true" data-uk-icon="icon: check"></span>
              Keep time-critical assets or campaigns as local copies in case you need access while the site is offline.
            </li>
            <li>
              <span class="calserver-maintenance-check" aria-hidden="true" data-uk-icon="icon: check"></span>
              After the window verify key journeys (forms, shop, tracking) and share feedback with us.
            </li>
          </ul>
          <p class="calserver-maintenance-note__text">
            We announce the next planned maintenance at least 14 days in advance through the status page and email.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>
$$
WHERE slug = 'calserver-maintenance-en';
