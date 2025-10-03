INSERT INTO pages (slug, title, content)
VALUES (
    'calserver-maintenance',
    'calServer Wartung',
    $$
<section class="calserver-maintenance-section uk-section uk-section-medium" id="timeline" aria-labelledby="maintenance-timeline-heading">
  <div class="uk-container">
    <div class="calserver-maintenance-section__header">
      <span class="calserver-maintenance-section__eyebrow">Wartungsfahrplan</span>
      <h2 class="calserver-maintenance-section__title" id="maintenance-timeline-heading">So läuft die Aktualisierung ab</h2>
      <p class="calserver-maintenance-section__intro">
        Wir aktualisieren Dienste, Sicherheitsrichtlinien und Infrastruktur. Während des Wartungsfensters behalten wir die Systeme im Blick
        und melden uns, sobald calServer wieder voll verfügbar ist.
      </p>
    </div>
    <ol class="calserver-maintenance-timeline" role="list">
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">18:00</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Start Wartungsmodus</h3>
          <p>Alle Nutzer:innen werden automatisch abgemeldet. Bereits geplante Kalibrierungen und Tickets bleiben unverändert gespeichert.</p>
        </div>
      </li>
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">18:10</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Backup-Verifizierung</h3>
          <p>Wir prüfen Hashwerte der Backups und synchronisieren Replikate in das Ausweichrechenzentrum.</p>
        </div>
      </li>
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">19:30</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Update &amp; Tests</h3>
          <p>Neue Versionen werden eingespielt. Automatisierte Smoke-Tests sichern Login, Geräteverwaltung und Dokumenten-Uploads.</p>
        </div>
      </li>
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">21:30</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Freigabe &amp; Monitoring</h3>
          <p>calServer geht wieder online. Wir beobachten Performance- und Fehlerindikatoren in Echtzeit.</p>
        </div>
      </li>
    </ol>
    <div class="calserver-maintenance-note" role="note">
      <span class="calserver-maintenance-note__icon" aria-hidden="true" data-uk-icon="icon: info"></span>
      <p class="calserver-maintenance-note__text">
        Live-Statusmeldungen findest du jederzeit im <a href="{{ basePath }}/status">calServer Statusbereich</a>. Dort kannst du dich auch für E-Mail-Updates registrieren.
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
            Unser Operations-Team begleitet die Wartung live. Melde dich, wenn du Auffälligkeiten bemerkst oder Unterstützung benötigst.
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
              Lade geplante Kalibrierlisten als PDF herunter, falls du offline weiterarbeiten möchtest.
            </li>
            <li>
              <span class="calserver-maintenance-check" aria-hidden="true" data-uk-icon="icon: check"></span>
              Informiere dein Team über das Wartungsfenster via Nachricht im internen Chat.
            </li>
            <li>
              <span class="calserver-maintenance-check" aria-hidden="true" data-uk-icon="icon: check"></span>
              Prüfe nach der Wartung die wichtigsten Workflows (Login, Geräteakte, Dokumentenupload) und gib uns Feedback.
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
)
ON CONFLICT (slug) DO UPDATE
SET title = EXCLUDED.title,
    content = EXCLUDED.content;

INSERT INTO pages (slug, title, content)
VALUES (
    'calserver-maintenance-en',
    'calServer Maintenance',
    $$
<section class="calserver-maintenance-section uk-section uk-section-medium" id="timeline" aria-labelledby="maintenance-timeline-heading">
  <div class="uk-container">
    <div class="calserver-maintenance-section__header">
      <span class="calserver-maintenance-section__eyebrow">Maintenance timeline</span>
      <h2 class="calserver-maintenance-section__title" id="maintenance-timeline-heading">How the update unfolds</h2>
      <p class="calserver-maintenance-section__intro">
        We are updating services, security policies and infrastructure. During the window we closely monitor system health and announce when calServer is fully available again.
      </p>
    </div>
    <ol class="calserver-maintenance-timeline" role="list">
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">6:00&nbsp;p.m.</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Maintenance mode starts</h3>
          <p>All users are signed out automatically. Scheduled calibrations and tickets remain safely stored.</p>
        </div>
      </li>
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">6:10&nbsp;p.m.</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Backup verification</h3>
          <p>We validate backup hashes and sync replicas to the secondary data centre.</p>
        </div>
      </li>
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">7:30&nbsp;p.m.</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Updates &amp; tests</h3>
          <p>New releases are deployed. Automated smoke tests cover login, device records and document uploads.</p>
        </div>
      </li>
      <li class="calserver-maintenance-timeline__item">
        <div class="calserver-maintenance-timeline__time">9:30&nbsp;p.m.</div>
        <div class="calserver-maintenance-timeline__body">
          <h3>Go-live &amp; monitoring</h3>
          <p>calServer comes back online. We keep an eye on performance and error signals in real time.</p>
        </div>
      </li>
    </ol>
    <div class="calserver-maintenance-note" role="note">
      <span class="calserver-maintenance-note__icon" aria-hidden="true" data-uk-icon="icon: info"></span>
      <p class="calserver-maintenance-note__text">
        Check the <a href="{{ basePath }}/status">calServer status page</a> for live updates and email notifications.
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
            Our operations team accompanies the maintenance in real time. Reach out if you notice anything unusual or need assistance.
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
              Export scheduled calibration lists as PDF if you need to work offline.
            </li>
            <li>
              <span class="calserver-maintenance-check" aria-hidden="true" data-uk-icon="icon: check"></span>
              Inform your team about the maintenance window via your internal chat.
            </li>
            <li>
              <span class="calserver-maintenance-check" aria-hidden="true" data-uk-icon="icon: check"></span>
              After the window verify the key workflows (login, device records, uploads) and share feedback with us.
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
)
ON CONFLICT (slug) DO UPDATE
SET title = EXCLUDED.title,
    content = EXCLUDED.content;
