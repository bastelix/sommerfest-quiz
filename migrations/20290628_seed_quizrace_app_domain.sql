-- Ensure quizrace.app and its admin hostname resolve to the default namespace
INSERT INTO domains (host, normalized_host, zone, namespace, label, is_active)
VALUES
    ('quizrace.app', 'quizrace.app', 'quizrace.app', 'default', 'QuizRace (main/admin)', TRUE)
ON CONFLICT (normalized_host) DO UPDATE SET
    host = EXCLUDED.host,
    zone = EXCLUDED.zone,
    namespace = COALESCE(domains.namespace, EXCLUDED.namespace),
    label = COALESCE(EXCLUDED.label, domains.label),
    is_active = TRUE;
