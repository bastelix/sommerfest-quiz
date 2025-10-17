-- Create newsletter CTA configuration table
CREATE TABLE IF NOT EXISTS marketing_newsletter_configs (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug TEXT NOT NULL,
    position INTEGER NOT NULL DEFAULT 0,
    label TEXT NOT NULL,
    url TEXT NOT NULL,
    style TEXT NOT NULL DEFAULT 'primary'
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_marketing_newsletter_configs_unique
    ON marketing_newsletter_configs(slug, position);

CREATE INDEX IF NOT EXISTS idx_marketing_newsletter_configs_slug
    ON marketing_newsletter_configs(slug);

-- Seed initial CTA definitions for existing marketing pages
INSERT INTO marketing_newsletter_configs (slug, position, label, url, style) VALUES
    ('landing', 0, 'QuizRace entdecken', '/landing', 'primary'),
    ('landing', 1, 'Kontakt aufnehmen', '/landing#contact-us', 'secondary'),
    ('calserver', 0, 'Mehr über calServer', '/calserver', 'primary'),
    ('calserver', 1, 'Demo anfragen', '/calserver#contact', 'secondary'),
    ('calserver-maintenance', 0, 'Service anfragen', '/calserver-maintenance', 'primary'),
    ('calserver-maintenance', 1, 'Direkt Kontakt aufnehmen', '/calserver-maintenance#contact', 'secondary'),
    ('calserver-accessibility', 0, 'Barrierefreiheit entdecken', '/calserver-accessibility', 'primary'),
    ('calserver-accessibility', 1, 'Beratung vereinbaren', '/calserver#contact', 'secondary'),
    ('calhelp', 0, 'CALhelp kennenlernen', '/calhelp', 'primary'),
    ('calhelp', 1, 'Beratung anfordern', '/calhelp#contact-info', 'secondary'),
    ('future-is-green', 0, 'Future is Green entdecken', '/future-is-green', 'primary'),
    ('future-is-green', 1, 'Kontakt aufnehmen', '#contact', 'secondary'),
    ('fluke-metcal', 0, 'Met/Track kennenlernen', '/fluke-metcal', 'primary'),
    ('fluke-metcal', 1, 'Kontakt aufnehmen', '/calserver#contact-us', 'secondary')
ON CONFLICT (slug, position) DO UPDATE SET
    label = excluded.label,
    url = excluded.url,
    style = excluded.style;
