ALTER TABLE mail_providers
    ADD COLUMN IF NOT EXISTS namespace VARCHAR(128) NOT NULL DEFAULT 'default';

UPDATE mail_providers
SET namespace = 'default'
WHERE namespace IS NULL OR trim(namespace) = '';

ALTER TABLE mail_providers
    DROP CONSTRAINT IF EXISTS mail_providers_provider_name_key;

ALTER TABLE mail_providers
    ADD CONSTRAINT mail_providers_namespace_provider_key UNIQUE (namespace, provider_name);
