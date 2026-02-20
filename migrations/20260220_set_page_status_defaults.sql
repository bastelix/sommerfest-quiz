-- Set all existing pages without a status to 'published'
-- so that legacy content remains visible after introducing the publishing feature.
UPDATE pages SET status = 'published' WHERE status IS NULL OR status = '';
