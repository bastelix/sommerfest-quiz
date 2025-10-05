-- Link calServer CTA buttons to the contact form
UPDATE pages
SET content = replace(content, 'href="#offer"', 'href="#contact-form"')
WHERE slug IN ('calserver', 'calserver-en');
