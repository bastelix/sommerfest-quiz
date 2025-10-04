-- Move the calServer assistant chat button from the hero into the contact section
UPDATE pages
SET content = replace(
    content,
$$                </div>
                <div class="calserver-proof-seals uk-margin-small-top">$$,
$$                </div>
                <button class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top calserver-chat-trigger"
                        type="button"
                        data-calserver-chat-open
                        aria-haspopup="dialog"
                        aria-controls="calserver-chat-modal">
                  <span class="uk-margin-small-right" data-uk-icon="icon: commenting"></span>Assistent fragen
                </button>
                <div class="calserver-proof-seals uk-margin-small-top">$$
)
WHERE slug = 'calserver';

UPDATE pages
SET content = replace(
    content,
$$                </div>
                <div class="calserver-proof-seals uk-margin-small-top">$$,
$$                </div>
                <button class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top calserver-chat-trigger"
                        type="button"
                        data-calserver-chat-open
                        aria-haspopup="dialog"
                        aria-controls="calserver-chat-modal">
                  <span class="uk-margin-small-right" data-uk-icon="icon: commenting"></span>Ask assistant
                </button>
                <div class="calserver-proof-seals uk-margin-small-top">$$
)
WHERE slug = 'calserver-en';
