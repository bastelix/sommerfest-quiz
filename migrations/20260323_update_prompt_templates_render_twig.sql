-- Update prompt template references from deleted legacy marketing template
-- to the current block-based rendering template.
UPDATE prompt_templates
SET prompt = REPLACE(prompt, 'templates/marketing/default.twig', 'templates/pages/render.twig'),
    updated_at = CURRENT_TIMESTAMP
WHERE prompt LIKE '%templates/marketing/default.twig%';
