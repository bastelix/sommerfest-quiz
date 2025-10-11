INSERT INTO pages (slug, title, content)
VALUES
    (
        'calserver-accessibility',
        'calServer Accessibility',
        $$<section class="legal-page" aria-labelledby="calserver-accessibility-title">
  <div class="uk-section uk-section-default">
    <div class="uk-container uk-container-small">
      <h1 id="calserver-accessibility-title">Accessibility statement for calServer</h1>
      <p>This accessibility statement applies to the calServer marketing website operated by René Buske (calhelp.de). It covers the content delivered at <a href="https://calhelp.de/calserver" rel="noopener">https://calhelp.de/calserver</a> and associated subpages.</p>
      <p>We strive to comply with the Web Content Accessibility Guidelines (WCAG) 2.1 at level AA. Based on an internal self-evaluation performed on 10 March 2025 we consider the site to be partially compliant.</p>
      <h2 id="non-accessible-content">Non-accessible content</h2>
      <p>The following content and functions are not fully accessible. Each item references the affected WCAG 2.1 success criteria.</p>
      <ul>
        <li><strong>Looping module preview videos</strong> – The autoplaying module videos on the landing page do not provide controls to pause or stop the animation and lack audio descriptions or transcripts. This violates success criteria 1.2.5 (Audio Description – Prerecorded) and 2.2.2 (Pause, Stop, Hide). We are preparing captioned recordings with accessible controls for Q2 2025.</li>
        <li><strong>Animated client logo marquee</strong> – The continuously moving client logo marquee cannot yet be paused and does not respect the prefers-reduced-motion setting. This violates success criteria 2.2.2 (Pause, Stop, Hide) and 2.3.3 (Animation from Interactions). We will add a pause button and motion preference detection in an upcoming release.</li>
        <li><strong>Downloadable PDF case studies</strong> – The linked PDF summaries of customer projects have not yet been tagged for assistive technologies and may contain missing alternate text. This violates success criteria 1.1.1 (Non-text Content) and 1.3.1 (Info and Relationships). Tagged replacements are scheduled for Q3 2025.</li>
      </ul>
      <h2 id="third-party-content">Third-party content</h2>
      <p>The scheduling links to Calendly and the Cloudflare Turnstile widget used in the contact form are third-party services. We have no full control over their accessibility but relay encountered issues to the providers.</p>
      <h2 id="feedback">Feedback and contact</h2>
      <p>If you discover barriers that prevent you from using calServer, please let us know. We respond within five working days.</p>
      <p>
        Email: <a class="js-email-link" data-user="office" data-domain="calhelp.de" href="#">office [at] calhelp.de</a><br>
        Phone: <a href="tel:+4933203609080">+49&nbsp;33203&nbsp;609080</a>
      </p>
      <h2 id="enforcement">Enforcement procedure</h2>
      <p>If you do not receive a satisfactory response to your feedback you can contact the Commissioner for Digital Accessibility of the State of Brandenburg (Ministry of Social Affairs, Health, Integration and Consumer Protection). Further information is available at <a href="https://msgiv.brandenburg.de/msgiv/de/themen/teilhabe-und-inklusion/barrierefreiheit-in-der-informationstechnik/" target="_blank" rel="noopener">msgiv.brandenburg.de</a>.</p>
      <h2 id="statement-date">Preparation of this statement</h2>
      <p>This statement was created on 10 March 2025 based on self-evaluation, manual audits with assistive technologies and automated tests (axe-core, WAVE).</p>
    </div>
  </div>
</section>$$
    ),
    (
        'calserver-accessibility-en',
        'calServer Accessibility',
        $$<section class="legal-page" aria-labelledby="calserver-accessibility-title">
  <div class="uk-section uk-section-default">
    <div class="uk-container uk-container-small">
      <h1 id="calserver-accessibility-title">Accessibility statement for calServer</h1>
      <p>This is the English reference version of the calServer accessibility statement. It applies to the marketing site operated by René Buske (calhelp.de) and mirrors the German declaration.</p>
      <p>The website aims to comply with WCAG 2.1 level AA and is currently assessed as partially compliant (self-evaluation on 10 March 2025).</p>
      <h2 id="non-accessible-content">Non-accessible content</h2>
      <ul>
        <li><strong>Looping module preview videos</strong> – No player controls, captions or audio descriptions are available yet. Affects WCAG 1.2.5 and 2.2.2. Fix planned for Q2 2025.</li>
        <li><strong>Animated client logo marquee</strong> – Motion cannot be paused and ignores system motion preferences. Affects WCAG 2.2.2 and 2.3.3. Fix planned for Q2 2025.</li>
        <li><strong>Downloadable PDF case studies</strong> – Missing tagging and alternate text in PDFs. Affects WCAG 1.1.1 and 1.3.1. Fix planned for Q3 2025.</li>
      </ul>
      <h2 id="third-party-content">Third-party content</h2>
      <p>Calendly scheduling links and the Cloudflare Turnstile CAPTCHA are external services outside our direct control.</p>
      <h2 id="feedback">Feedback and contact</h2>
      <p>Contact us at <a class="js-email-link" data-user="office" data-domain="calhelp.de" href="#">office [at] calhelp.de</a> or call <a href="tel:+4933203609080">+49&nbsp;33203&nbsp;609080</a>.</p>
      <h2 id="enforcement">Enforcement procedure</h2>
      <p>Complaints can be escalated to the Commissioner for Digital Accessibility of the State of Brandenburg (Ministry of Social Affairs, Health, Integration and Consumer Protection): <a href="https://msgiv.brandenburg.de/msgiv/de/themen/teilhabe-und-inklusion/barrierefreiheit-in-der-informationstechnik/" target="_blank" rel="noopener">msgiv.brandenburg.de</a>.</p>
      <h2 id="statement-date">Preparation of this statement</h2>
      <p>Last review: 10 March 2025.</p>
    </div>
  </div>
</section>$$
    )
ON CONFLICT (slug) DO UPDATE
SET
    title = EXCLUDED.title,
    content = EXCLUDED.content;
