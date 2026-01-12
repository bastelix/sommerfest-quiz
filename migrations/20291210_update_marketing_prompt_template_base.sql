UPDATE prompt_templates
SET prompt = REPLACE(prompt, 'templates/marketing/landing.twig', 'templates/marketing/default.twig'),
    updated_at = CURRENT_TIMESTAMP
WHERE prompt LIKE '%templates/marketing/landing.twig%';
