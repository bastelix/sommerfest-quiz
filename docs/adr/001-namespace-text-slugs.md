# ADR-001: Text slugs instead of UUIDs for namespaces

## Status

Accepted

## Context

The original AGENTS.md specified `namespace_id UUID NOT NULL` as the standard
column for namespace isolation. However, the implementation has always used
human-readable text slugs (`namespace TEXT NOT NULL`) with values like
`default`, `mein-projekt`, or `sommerfest-2025`.

UUIDs would provide guaranteed uniqueness but are opaque to operators. Text
slugs are immediately meaningful in logs, URLs, database queries, and QR codes.

## Decision

Namespaces use `TEXT` slugs as their identifier. The `namespace` column stores
a human-readable, URL-safe string. Uniqueness is enforced at the application
level during namespace creation (see `NamespaceValidator`).

The AGENTS.md has been corrected to reflect `namespace TEXT NOT NULL`.

## Consequences

- Namespace values are readable in database queries, logs, and URLs.
- Renaming a namespace requires updating all referencing tables (cascading update).
- Slug collisions must be prevented by validation, not by the data type.
