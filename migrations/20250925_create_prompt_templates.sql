CREATE TABLE IF NOT EXISTS prompt_templates (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name TEXT NOT NULL,
    prompt TEXT NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_prompt_templates_name ON prompt_templates(name);

INSERT INTO prompt_templates (name, prompt)
SELECT template.name, template.prompt
FROM (
    VALUES
        (
            'Standard',
            $$Erstelle deutsches UIkit-HTML für eine Marketing-Landingpage. Der HTML-Code wird in den Content-Block von
templates/marketing/landing.twig eingefügt (Header/Footer existieren). Orientiere dich am Landing-Layout:
- Verwende mehrere <section>-Blöcke mit uk-section und verschachtelten uk-container.
- Liefere eine Hero-Section mit Headline, Lead-Text und primärem Call-to-Action.
- Füge Sektionen mit den IDs innovations, how-it-works, scenarios, pricing, faq, contact-us hinzu (wenn relevant).
- Nutze uk-grid, uk-card, uk-list, uk-accordion und uk-button.
- Verwende ausschließlich UIkit-Klassen (Klassen beginnen mit "uk-").
- Nutze die Farb-Tokens. Mappe sie auf UIkit-Utilities oder CSS-Variablen auf einem Wrapper:
  Primary => --qr-landing-primary, Background => --qr-landing-bg, Accent => --qr-landing-accent.
- Schreibe prägnant, nutzenorientiert und auf Deutsch.
- Kein <html>, <head>, <body>, <script> oder <style>.
- Gib nur HTML zurück, kein Markdown.

Eingaben:
Slug: {{slug}}
Title: {{title}}
Theme: {{theme}}
Color scheme: {{colorScheme}}
Color tokens: Primary={{primaryColor}}, Background={{backgroundColor}}, Accent={{accentColor}}
Problem to address: {{problem}}$$
        ),
        (
            'Kompakt',
            $$Erstelle eine kompakte deutsche UIkit-Landingpage mit klaren Nutzenargumenten. Die HTML-Ausgabe wird in
templates/marketing/landing.twig eingesetzt. Anforderungen:
- Maximal 4 bis 5 <section>-Blöcke mit uk-section und uk-container.
- Eine kurze Hero-Section mit Headline, Subline und primärem Button.
- Eine Nutzenliste, eine Ablaufsektion und eine FAQ-Sektion (wenn relevant).
- Verwende nur UIkit-Klassen.
- Verwende die Farb-Tokens in einem Wrapper mit CSS-Variablen:
  Primary => --qr-landing-primary, Background => --qr-landing-bg, Accent => --qr-landing-accent.
- Texte kurz, prägnant, auf Deutsch.
- Keine <html>, <head>, <body>, <script> oder <style>.
- Ausgabe nur HTML.

Eingaben:
Slug: {{slug}}
Title: {{title}}
Theme: {{theme}}
Color scheme: {{colorScheme}}
Color tokens: Primary={{primaryColor}}, Background={{backgroundColor}}, Accent={{accentColor}}
Problem to address: {{problem}}$$
        ),
        (
            'Storytelling',
            $$Erstelle deutsches UIkit-HTML für eine Marketing-Landingpage, die eine kurze Story erzählt. Die HTML-Ausgabe
wird in templates/marketing/landing.twig eingefügt. Anforderungen:
- Nutze <section>-Blöcke mit uk-section und uk-container.
- Hero-Section mit Story-Hook, Lead-Text und primärem CTA.
- Eine "Warum"-Sektion, eine "So funktioniert es"-Sektion und eine "Ergebnisse"-Sektion (wenn relevant).
- Eine FAQ- oder Kontaktsektion als Abschluss.
- Verwende uk-grid, uk-card, uk-list, uk-accordion, uk-button.
- Verwende nur UIkit-Klassen ("uk-").
- Verwende die Farb-Tokens auf einem Wrapper:
  Primary => --qr-landing-primary, Background => --qr-landing-bg, Accent => --qr-landing-accent.
- Texte klar, benefit-orientiert, Deutsch.
- Keine <html>, <head>, <body>, <script> oder <style>.
- Ausgabe nur HTML.

Eingaben:
Slug: {{slug}}
Title: {{title}}
Theme: {{theme}}
Color scheme: {{colorScheme}}
Color tokens: Primary={{primaryColor}}, Background={{backgroundColor}}, Accent={{accentColor}}
Problem to address: {{problem}}$$
        )
) AS template(name, prompt)
WHERE NOT EXISTS (SELECT 1 FROM prompt_templates);
