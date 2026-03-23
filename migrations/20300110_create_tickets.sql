-- Ticket system for QA, project management, and customer support.

CREATE TABLE IF NOT EXISTS customer_profiles (
    id            BIGSERIAL PRIMARY KEY,
    user_id       INTEGER NOT NULL UNIQUE,
    display_name  TEXT NULL,
    company       TEXT NULL,
    phone         TEXT NULL,
    avatar_url    TEXT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_customer_profiles_user_id ON customer_profiles(user_id);

CREATE TABLE IF NOT EXISTS tickets (
    id             BIGSERIAL PRIMARY KEY,
    namespace      TEXT NOT NULL,
    title          TEXT NOT NULL,
    description    TEXT NOT NULL DEFAULT '',
    status         TEXT NOT NULL DEFAULT 'open',
    priority       TEXT NOT NULL DEFAULT 'normal',
    type           TEXT NOT NULL DEFAULT 'task',
    reference_type TEXT NULL,
    reference_id   BIGINT NULL,
    assignee       TEXT NULL,
    labels         JSONB NOT NULL DEFAULT '[]'::jsonb,
    due_date       TIMESTAMP NULL,
    created_by     TEXT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_tickets_namespace ON tickets(namespace);
CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(namespace, status);
CREATE INDEX IF NOT EXISTS idx_tickets_reference ON tickets(namespace, reference_type, reference_id);
CREATE INDEX IF NOT EXISTS idx_tickets_assignee ON tickets(namespace, assignee);

CREATE TABLE IF NOT EXISTS ticket_comments (
    id         BIGSERIAL PRIMARY KEY,
    ticket_id  BIGINT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    author     TEXT NOT NULL,
    body       TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_ticket_comments_ticket_id ON ticket_comments(ticket_id);
