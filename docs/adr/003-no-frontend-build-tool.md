# ADR-003: No frontend build tool (vanilla JS + UIkit)

## Status

Accepted

## Context

The project intentionally avoids a frontend build step. JavaScript files are
served as plain scripts from `public/js/`. The CSS framework UIkit is included
as a pre-built bundle. This keeps the development setup simple: no Node.js
build pipeline required for PHP developers.

As the frontend grew, several large files emerged (admin.js at 11k+ lines,
block-content-editor.js at 5k+ lines) and code duplication across the
marketing-menu-*.js files became significant.

## Decision

The project continues without a mandatory build tool. Code sharing is achieved
through shared script files loaded before page-specific scripts (e.g.
`marketing-menu-common.js`).

If bundle sizes or duplication become unmanageable, a lightweight bundler
(Vite) may be evaluated. This would be introduced as an optional enhancement,
not a hard requirement for development.

## Consequences

- No build step means instant feedback during development.
- Large files must be split manually into smaller scripts.
- Shared utilities must be loaded via `<script>` tag order rather than imports.
- Tree-shaking and minification are not available without a build tool.
