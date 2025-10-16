-- Update bullet point for auditfähige Zertifikate card on calServer page
UPDATE pages
SET content = REPLACE(
    content,
    $$                <li>REST-API, SSO (Azure/Google) und Hosting in Deutschland</li>$$,
    $$                <li>Endlich korrekte Anzeigen der erweiterten Messunsicherheit durch inteligente Feldformeln in der Software</li>$$
)
WHERE slug = 'calserver';
