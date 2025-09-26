CREATE TABLE IF NOT EXISTS domain_contact_templates (
    domain TEXT PRIMARY KEY,
    sender_name TEXT,
    recipient_html TEXT,
    recipient_text TEXT,
    sender_html TEXT,
    sender_text TEXT,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE FUNCTION trg_domain_contact_templates_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_domain_contact_templates_update ON domain_contact_templates;
CREATE TRIGGER trg_domain_contact_templates_update
    BEFORE UPDATE ON domain_contact_templates
    FOR EACH ROW
    EXECUTE FUNCTION trg_domain_contact_templates_updated_at();
