-- Allow dismissing the MET/CAL sticky call-to-action card
UPDATE pages
SET content = REPLACE(
        REPLACE(
            REPLACE(content,
$$<div class="metcal-sticky-cta" data-metcal-sticky-cta>
  <div class="metcal-sticky-cta__inner">
    <div class="metcal-sticky-cta__copy">
      <strong>Bereit für den Wechsel?</strong>
      <span>Wir planen Migration, Hybridbetrieb und Berichte gemeinsam – ohne Stillstand.</span>
    </div>
    <div class="metcal-sticky-cta__actions" role="group" aria-label="Schnellzugriff">
      <a class="uk-button uk-button-primary"
         href="#berichte"
         data-analytics-event="click_cta_reports"
         data-analytics-context="sticky_metcal"
         data-analytics-page="/fluke-metcal"
         data-analytics-target="#berichte">
        <span class="uk-margin-small-right" data-uk-icon="icon: file-text"></span>Berichte ansehen
      </a>
      <a class="uk-button uk-button-default"
         href="{{ basePath }}/calserver#contact-us"
         data-analytics-event="click_cta_contact"
         data-analytics-context="sticky_metcal"
         data-analytics-target="/calserver#contact-us">
        <span class="uk-margin-small-right" data-uk-icon="icon: receiver"></span>Kontakt aufnehmen
      </a>
    </div>
  </div>
</div>$$,
$$<div class="metcal-sticky-cta" data-metcal-sticky-cta aria-hidden="true">
  <div class="metcal-sticky-cta__inner">
    <button class="metcal-sticky-cta__dismiss" type="button" data-metcal-sticky-dismiss aria-label="Hinweis ausblenden">
      <span aria-hidden="true" data-uk-icon="icon: close"></span>
    </button>
    <div class="metcal-sticky-cta__copy">
      <strong>Bereit für den Wechsel?</strong>
      <span>Wir planen Migration, Hybridbetrieb und Berichte gemeinsam – ohne Stillstand.</span>
    </div>
    <div class="metcal-sticky-cta__actions" role="group" aria-label="Schnellzugriff">
      <a class="uk-button uk-button-primary"
         href="#berichte"
         data-analytics-event="click_cta_reports"
         data-analytics-context="sticky_metcal"
         data-analytics-page="/fluke-metcal"
         data-analytics-target="#berichte">
        <span class="uk-margin-small-right" data-uk-icon="icon: file-text"></span>Berichte ansehen
      </a>
      <a class="uk-button uk-button-default"
         href="{{ basePath }}/calserver#contact-us"
         data-analytics-event="click_cta_contact"
         data-analytics-context="sticky_metcal"
         data-analytics-target="/calserver#contact-us">
        <span class="uk-margin-small-right" data-uk-icon="icon: receiver"></span>Kontakt aufnehmen
      </a>
    </div>
  </div>
</div>$$
            ),
            'updated_at: 2025-05-28',
            'updated_at: 2025-06-20'
        ),
        'kb_version: 1.3',
        'kb_version: 1.4'
    )
WHERE slug = 'fluke-metcal';
