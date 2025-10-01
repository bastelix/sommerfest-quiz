-- Update the calServer contact section to include the badge and ProvenExpert widget
UPDATE pages
SET content = replace(content,
$$          <div>
            <div class="uk-grid uk-child-width-1-1 uk-grid-small">
              <div><div class="uk-form-label" aria-hidden="true">&nbsp;</div></div>
              <div>
                <div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
                  <p class="uk-margin-small-bottom uk-text-large">E-Mail</p>
                  <a
                    class="uk-text-lead uk-link-reset js-email-link"
                    data-user="office"
                    data-domain="calhelp.de"
                    href="#"
                  >office [at] calhelp.de</a>
                </div>
              </div>
              <div>
                <div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
                  <p class="uk-margin-small-bottom uk-text-large">Telefon</p>
                  <a href="tel:+4933203609080" class="uk-text-lead uk-link-reset">+49 33203 609080</a>
                </div>
              </div>
            </div>
          </div>$$,
$$          <div>
            <div class="uk-grid uk-child-width-1-1 uk-grid-small">
              <div><div class="uk-form-label" aria-hidden="true">&nbsp;</div></div>
              <div>
                <div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
                  <div class="calserver-contact-card__entry">
                    <p class="uk-margin-small-bottom uk-text-large">E-Mail</p>
                    <a
                      class="uk-text-lead uk-link-reset js-email-link"
                      data-user="office"
                      data-domain="calhelp.de"
                      href="#"
                    >office [at] calhelp.de</a>
                  </div>
                  <div class="calserver-contact-card__entry uk-margin-small-top">
                    <p class="uk-margin-small-bottom uk-text-large">Telefon</p>
                    <a href="tel:+4933203609080" class="uk-text-lead uk-link-reset">+49 33203 609080</a>
                  </div>
                </div>
                <div class="calserver-proof-seals uk-margin-small-top">
                  <a class="calserver-proof-seals__badge-link" href="https://www.software-made-in-germany.org/produkt/calserver/?asp_highlight=calserver&amp;p_asid=10" target="_blank" rel="noopener">
                    <img
                      src="https://www.software-made-in-germany.org/wp-content/uploads/2021/06/Software-Made-in-Germany-Siegel.webp"
                      alt="Software Made in Germany Siegel"
                      loading="lazy"
                      decoding="async"
                    />
                  </a>
                  <div class="calserver-proof-seals__widget">
                    <noscript><a href="https://www.provenexpert.com/calhelp/?utm_source=seals&amp;utm_campaign=embedded-proseal&amp;utm_medium=profile&amp;utm_content=6d90d493-c9ba-4a43-a83a-25da0632ada1" target="_blank" title="Customer reviews &amp; experiences for calHelp" class="pe-pro-seal-more-infos">More info</a>
                    </noscript>
                    <script defer nowprocket id="proSeal">
                      window.loadProSeal = function(){
                        window.provenExpert.proSeal({
                          widgetId: "6d90d493-c9ba-4a43-a83a-25da0632ada1",
                          language:"de-DE",
                          usePageLanguage: false,
                          bannerColor: "#097E92",
                          textColor: "#FFFFFF",
                          showBackPage: true,
                          showReviews: true,
                          hideDate: true,
                          hideName: false,
                          googleStars: true,
                          displayReviewerLastName: false,
                          embeddedSelector: "#proSealWidget"
                        })
                      };
                      window.addEventListener(
                        "load",
                        function () {
                          var script = document.createElement('script');
                          script.src = "https://s.provenexpert.net/seals/proseal-v2.js";
                          script.onload = loadProSeal;
                          document.head.appendChild(script);
                        },
                        false
                      );
                    </script>
                    <div id="proSealWidget"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>$$)
WHERE slug = 'calserver';

UPDATE pages
SET content = replace(content,
$$          <div>
            <div class="uk-grid uk-child-width-1-1 uk-grid-small">
              <div><div class="uk-form-label" aria-hidden="true">&nbsp;</div></div>
              <div>
                <div class="uk-grid uk-child-width-1-1 uk-child-width-1-2@s uk-grid-small uk-grid-match">
                  <div>
                    <div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
                      <p class="uk-margin-small-bottom uk-text-large">E-Mail</p>
                      <a
                        class="uk-text-lead uk-link-reset js-email-link"
                        data-user="office"
                        data-domain="calhelp.de"
                        href="#"
                      >office [at] calhelp.de</a>
                    </div>
                  </div>
                  <div>
                    <div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
                      <p class="uk-margin-small-bottom uk-text-large">Telefon</p>
                      <a href="tel:+4933203609080" class="uk-text-lead uk-link-reset">+49 33203 609080</a>
                    </div>
                  </div>
                </div>
                <div class="calserver-proof-seals uk-margin-small-top">
                  <div
                    class="calserver-proseal"
                    data-calserver-proseal
                    data-widget-id="1503aa9a-ae86-41d0-8ce9-05ed6f0a4856"
                    data-widget-language="de-DE">
                    <div class="calserver-proseal__placeholder" data-calserver-proseal-placeholder>
                      <p class="uk-text-small uk-margin-small-bottom">Bewertungen werden geladen, sobald du Marketing-Cookies erlaubst.</p>
                      <button class="uk-button uk-button-primary uk-button-small" type="button" data-calserver-proseal-consent>Bewertungen anzeigen</button>
                      <p class="uk-text-meta uk-margin-small-top">Du kannst deine Auswahl jederzeit in den Cookie-Einstellungen ändern.</p>
                      <p class="uk-text-meta uk-margin-small-top calserver-proseal__error" data-calserver-proseal-error hidden>Bewertungen konnten nicht geladen werden. Bitte versuche es später erneut.</p>
                    </div>
                    <div class="calserver-proseal__embed" id="proSealWidget" data-proseal-target hidden></div>
                    <noscript>
                      <p class="uk-text-small uk-margin-small-top">
                        <a class="uk-link-muted" href="https://www.provenexpert.com/calhelp/?utm_source=seals&amp;utm_campaign=embedded-proseal&amp;utm_medium=profile&amp;utm_content=1503aa9a-ae86-41d0-8ce9-05ed6f0a4856" rel="noopener" target="_blank">Kundenbewertungen auf ProvenExpert ansehen</a>
                      </p>
                    </noscript>
                  </div>
                  <figure class="calserver-proof-seals__figure">
                    <img
                      alt="Gütesiegel Hosting in Germany"
                      class="calserver-proof-seals__badge"
                      decoding="async"
                      height="160"
                      loading="lazy"
                      src="{{ basePath }}/uploads/calserver-hosting-in-germany.webp"
                      width="160"/>
                  </figure>
                </div>
              </div>
            </div>
          </div>$$,
$$          <div>
            <div class="uk-grid uk-child-width-1-1 uk-grid-small">
              <div><div class="uk-form-label" aria-hidden="true">&nbsp;</div></div>
              <div>
                <div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
                  <div class="calserver-contact-card__entry">
                    <p class="uk-margin-small-bottom uk-text-large">E-Mail</p>
                    <a
                      class="uk-text-lead uk-link-reset js-email-link"
                      data-user="office"
                      data-domain="calhelp.de"
                      href="#"
                    >office [at] calhelp.de</a>
                  </div>
                  <div class="calserver-contact-card__entry uk-margin-small-top">
                    <p class="uk-margin-small-bottom uk-text-large">Telefon</p>
                    <a href="tel:+4933203609080" class="uk-text-lead uk-link-reset">+49 33203 609080</a>
                  </div>
                </div>
                <div class="calserver-proof-seals uk-margin-small-top">
                  <a class="calserver-proof-seals__badge-link" href="https://www.software-made-in-germany.org/produkt/calserver/?asp_highlight=calserver&amp;p_asid=10" target="_blank" rel="noopener">
                    <img
                      src="https://www.software-made-in-germany.org/wp-content/uploads/2021/06/Software-Made-in-Germany-Siegel.webp"
                      alt="Software Made in Germany Siegel"
                      loading="lazy"
                      decoding="async"
                    />
                  </a>
                  <div class="calserver-proof-seals__widget">
                    <noscript><a href="https://www.provenexpert.com/calhelp/?utm_source=seals&amp;utm_campaign=embedded-proseal&amp;utm_medium=profile&amp;utm_content=6d90d493-c9ba-4a43-a83a-25da0632ada1" target="_blank" title="Customer reviews &amp; experiences for calHelp" class="pe-pro-seal-more-infos">More info</a>
                    </noscript>
                    <script defer nowprocket id="proSeal">
                      window.loadProSeal = function(){
                        window.provenExpert.proSeal({
                          widgetId: "6d90d493-c9ba-4a43-a83a-25da0632ada1",
                          language:"de-DE",
                          usePageLanguage: false,
                          bannerColor: "#097E92",
                          textColor: "#FFFFFF",
                          showBackPage: true,
                          showReviews: true,
                          hideDate: true,
                          hideName: false,
                          googleStars: true,
                          displayReviewerLastName: false,
                          embeddedSelector: "#proSealWidget"
                        })
                      };
                      window.addEventListener(
                        "load",
                        function () {
                          var script = document.createElement('script');
                          script.src = "https://s.provenexpert.net/seals/proseal-v2.js";
                          script.onload = loadProSeal;
                          document.head.appendChild(script);
                        },
                        false
                      );
                    </script>
                    <div id="proSealWidget"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>$$)
WHERE slug = 'calserver';

UPDATE pages
SET content = replace(content,
$$<div class="uk-grid uk-child-width-1-1 uk-grid-small">
<div><div class="uk-form-label" aria-hidden="true"> </div></div>
<div>
<div class="uk-grid uk-child-width-1-1 uk-child-width-1-2@s uk-grid-small uk-grid-match">
<div>
<div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
<p class="uk-margin-small-bottom uk-text-large">Email</p>
<a class="uk-text-lead uk-link-reset js-email-link" data-domain="calhelp.de" data-user="office" href="#">office [at] calhelp.de</a>
</div>
</div>
<div>
<div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
<p class="uk-margin-small-bottom uk-text-large">Phone</p>
<a class="uk-text-lead uk-link-reset" href="tel:+4933203609080">+49 33203 609080</a>
</div>
</div>
</div>
<div class="calserver-proof-seals uk-margin-small-top">
<div
  class="calserver-proseal"
  data-calserver-proseal
  data-widget-id="1503aa9a-ae86-41d0-8ce9-05ed6f0a4856"
  data-widget-language="de-DE">
  <div class="calserver-proseal__placeholder" data-calserver-proseal-placeholder>
    <p class="uk-text-small uk-margin-small-bottom">Reviews load once you allow marketing cookies.</p>
    <button class="uk-button uk-button-primary uk-button-small" type="button" data-calserver-proseal-consent>Show reviews</button>
    <p class="uk-text-meta uk-margin-small-top">You can change this anytime in the cookie settings.</p>
    <p class="uk-text-meta uk-margin-small-top calserver-proseal__error" data-calserver-proseal-error hidden>Reviews could not be loaded. Please try again later.</p>
  </div>
  <div class="calserver-proseal__embed" id="proSealWidget" data-proseal-target hidden></div>
  <noscript>
    <p class="uk-text-small uk-margin-small-top">
      <a class="uk-link-muted" href="https://www.provenexpert.com/calhelp/?utm_source=seals&amp;utm_campaign=embedded-proseal&amp;utm_medium=profile&amp;utm_content=1503aa9a-ae86-41d0-8ce9-05ed6f0a4856" rel="noopener" target="_blank">View customer reviews on ProvenExpert</a>
    </p>
  </noscript>
</div>
<figure class="calserver-proof-seals__figure">
<img
  alt="Hosting in Germany quality seal"
  class="calserver-proof-seals__badge"
  decoding="async"
  height="160"
  loading="lazy"
  src="{{ basePath }}/uploads/calserver-hosting-in-germany.webp"
  width="160"/>
</figure>
</div>
</div>
</div>$$,
$$<div class="uk-grid uk-child-width-1-1 uk-grid-small">
<div><div class="uk-form-label" aria-hidden="true"> </div></div>
<div>
<div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
  <div class="calserver-contact-card__entry">
    <p class="uk-margin-small-bottom uk-text-large">Email</p>
    <a class="uk-text-lead uk-link-reset js-email-link" data-domain="calhelp.de" data-user="office" href="#">office [at] calhelp.de</a>
  </div>
  <div class="calserver-contact-card__entry uk-margin-small-top">
    <p class="uk-margin-small-bottom uk-text-large">Phone</p>
    <a class="uk-text-lead uk-link-reset" href="tel:+4933203609080">+49 33203 609080</a>
  </div>
</div>
<div class="calserver-proof-seals uk-margin-small-top">
  <a class="calserver-proof-seals__badge-link" href="https://www.software-made-in-germany.org/produkt/calserver/?asp_highlight=calserver&amp;p_asid=10" target="_blank" rel="noopener">
    <img
      src="https://www.software-made-in-germany.org/wp-content/uploads/2021/06/Software-Made-in-Germany-Siegel.webp"
      alt="Software Made in Germany seal"
      loading="lazy"
      decoding="async"
    />
  </a>
  <div class="calserver-proof-seals__widget">
    <noscript><a href="https://www.provenexpert.com/calhelp/?utm_source=seals&amp;utm_campaign=embedded-proseal&amp;utm_medium=profile&amp;utm_content=6d90d493-c9ba-4a43-a83a-25da0632ada1" target="_blank" title="Customer reviews &amp; experiences for calHelp" class="pe-pro-seal-more-infos">More info</a>
    </noscript>
    <script defer nowprocket id="proSeal">
      window.loadProSeal = function(){
        window.provenExpert.proSeal({
          widgetId: "6d90d493-c9ba-4a43-a83a-25da0632ada1",
          language:"en-US",
          usePageLanguage: false,
          bannerColor: "#097E92",
          textColor: "#FFFFFF",
          showBackPage: true,
          showReviews: true,
          hideDate: true,
          hideName: false,
          googleStars: true,
          displayReviewerLastName: false,
          embeddedSelector: "#proSealWidget"
        })
      };
      window.addEventListener(
        "load",
        function () {
          var script = document.createElement('script');
          script.src = "https://s.provenexpert.net/seals/proseal-v2.js";
          script.onload = loadProSeal;
          document.head.appendChild(script);
        },
        false
      );
    </script>
    <div id="proSealWidget"></div>
  </div>
</div>
</div>
</div>$$)
WHERE slug = 'calserver-en';

UPDATE pages
SET content = replace(content,
$$          <div class="uk-grid uk-child-width-1-1 uk-grid-small">
            <div><div class="uk-form-label" aria-hidden="true">&nbsp;</div></div>
            <div>
              <div class="uk-grid uk-child-width-1-1 uk-child-width-1-2@s uk-grid-small uk-grid-match">
                <div>
                  <div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
                    <p class="uk-margin-small-bottom uk-text-large">Email</p>
                    <a class="uk-text-lead uk-link-reset js-email-link" data-domain="calhelp.de" data-user="office" href="#">office [at] calhelp.de</a>
                  </div>
                </div>
                <div>
                  <div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
                    <p class="uk-margin-small-bottom uk-text-large">Phone</p>
                    <a class="uk-text-lead uk-link-reset" href="tel:+4933203609080">+49 33203 609080</a>
                  </div>
                </div>
              </div>
              <div class="calserver-proof-seals uk-margin-small-top">
                <div
                  class="calserver-proseal"
                  data-calserver-proseal
                  data-widget-id="1503aa9a-ae86-41d0-8ce9-05ed6f0a4856"
                  data-widget-language="de-DE">
                  <div class="calserver-proseal__placeholder" data-calserver-proseal-placeholder>
                    <p class="uk-text-small uk-margin-small-bottom">Reviews load once you allow marketing cookies.</p>
                    <button class="uk-button uk-button-primary uk-button-small" type="button" data-calserver-proseal-consent>Show reviews</button>
                    <p class="uk-text-meta uk-margin-small-top">You can change this anytime in the cookie settings.</p>
                    <p class="uk-text-meta uk-margin-small-top calserver-proseal__error" data-calserver-proseal-error hidden>Reviews could not be loaded. Please try again later.</p>
                  </div>
                  <div class="calserver-proseal__embed" id="proSealWidget" data-proseal-target hidden></div>
                  <noscript>
                    <p class="uk-text-small uk-margin-small-top">
                      <a class="uk-link-muted" href="https://www.provenexpert.com/calhelp/?utm_source=seals&amp;utm_campaign=embedded-proseal&amp;utm_medium=profile&amp;utm_content=1503aa9a-ae86-41d0-8ce9-05ed6f0a4856" rel="noopener" target="_blank">View customer reviews on ProvenExpert</a>
                    </p>
                  </noscript>
                </div>
                <figure class="calserver-proof-seals__figure">
                  <img
                    alt="Hosting in Germany quality seal"
                    class="calserver-proof-seals__badge"
                    decoding="async"
                    height="160"
                    loading="lazy"
                    src="{{ basePath }}/uploads/calserver-hosting-in-germany.webp"
                    width="160"/>
                </figure>
              </div>
            </div>
          </div>$$,
$$          <div class="uk-grid uk-child-width-1-1 uk-grid-small">
            <div><div class="uk-form-label" aria-hidden="true">&nbsp;</div></div>
            <div>
              <div class="uk-card uk-card-default uk-card-body uk-text-left padding-30px contact-card">
                <div class="calserver-contact-card__entry">
                  <p class="uk-margin-small-bottom uk-text-large">Email</p>
                  <a class="uk-text-lead uk-link-reset js-email-link" data-domain="calhelp.de" data-user="office" href="#">office [at] calhelp.de</a>
                </div>
                <div class="calserver-contact-card__entry uk-margin-small-top">
                  <p class="uk-margin-small-bottom uk-text-large">Phone</p>
                  <a class="uk-text-lead uk-link-reset" href="tel:+4933203609080">+49 33203 609080</a>
                </div>
              </div>
              <div class="calserver-proof-seals uk-margin-small-top">
                <a class="calserver-proof-seals__badge-link" href="https://www.software-made-in-germany.org/produkt/calserver/?asp_highlight=calserver&amp;p_asid=10" target="_blank" rel="noopener">
                  <img
                    src="https://www.software-made-in-germany.org/wp-content/uploads/2021/06/Software-Made-in-Germany-Siegel.webp"
                    alt="Software Made in Germany seal"
                    loading="lazy"
                    decoding="async"
                  />
                </a>
                <div class="calserver-proof-seals__widget">
                  <noscript><a href="https://www.provenexpert.com/calhelp/?utm_source=seals&amp;utm_campaign=embedded-proseal&amp;utm_medium=profile&amp;utm_content=6d90d493-c9ba-4a43-a83a-25da0632ada1" target="_blank" title="Customer reviews &amp; experiences for calHelp" class="pe-pro-seal-more-infos">More info</a>
                  </noscript>
                  <script defer nowprocket id="proSeal">
                    window.loadProSeal = function(){
                      window.provenExpert.proSeal({
                        widgetId: "6d90d493-c9ba-4a43-a83a-25da0632ada1",
                        language:"en-US",
                        usePageLanguage: false,
                        bannerColor: "#097E92",
                        textColor: "#FFFFFF",
                        showBackPage: true,
                        showReviews: true,
                        hideDate: true,
                        hideName: false,
                        googleStars: true,
                        displayReviewerLastName: false,
                        embeddedSelector: "#proSealWidget"
                      })
                    };
                    window.addEventListener(
                      "load",
                      function () {
                        var script = document.createElement('script');
                        script.src = "https://s.provenexpert.net/seals/proseal-v2.js";
                        script.onload = loadProSeal;
                        document.head.appendChild(script);
                      },
                      false
                    );
                  </script>
                  <div id="proSealWidget"></div>
                </div>
              </div>
            </div>
          </div>$$)
WHERE slug = 'calserver-en';
