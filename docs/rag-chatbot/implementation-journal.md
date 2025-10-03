# RAG Chatbot Implementation Journal

This journal captures the step-by-step execution of the roadmap tasks for the multi-tenant RAG chatbot.

## Phase 0 – Architecture Review

### Middleware and Routing Inventory
- `src/routes.php` registers routes directly on the Slim app instance without grouping middleware stacks. Existing API routes (`/api/players`, `/api/tenants/...`) are defined alongside HTML controllers.
- Cross-cutting middleware such as `HeadRequestMiddleware`, `RoleAuthMiddleware`, `LanguageMiddleware`, `CsrfMiddleware`, and `RateLimitMiddleware` are added inside route definitions where needed instead of global groups.
- Admin-only sections rely on `RoleAuthMiddleware` with `Roles::ADMIN` or plan checks to protect routes.

### Bootstrap Flow
- `public/index.php` bootstraps Composer, loads environment variables from `.env`, and instantiates the Slim application.
- Twig is configured with `UikitExtension` and `TranslationExtension`; middleware for URLs, domain mapping, proxy headers, Twig rendering, and sessions are added in that order.
- `src/routes.php` is required and invoked with the Slim app instance and translator service before error middleware is registered.

### Container & Service Overview
- `docker-compose.yml` orchestrates the production-like stack with `nginx`, `docker-gen`, and `acme-companion` for automated reverse proxy and certificates.
- Application runtime is provided by the `slim` service (PHP 8.2) served through PHP's built-in server behind nginx.
- PostgreSQL 15 and Adminer are available as supporting services; volumes persist database and nginx assets.

## Next steps
Phase 1 focuses on the database schema extensions and pgvector enablement.

## Phase 1 – Datenbank & Infrastruktur vorbereiten

### Migration Planning
- Introduce dedicated tables for knowledge base documents (`kb_documents`), chunks (`kb_chunks`), conversations, messages, and tickets using timestamped SQL migrations under `migrations/`.
- Extend `tenants` with a JSONB `settings` column to capture prompt defaults, confidence thresholds, and DSGVO texts without disrupting existing provisioning logic.
- Use UUID primary keys generated at the application level via `ramsey/uuid`; include foreign keys for tenant isolation and indexes for frequent lookups (document titles, chunk vector index, conversation timestamps).

### pgvector Enablement
- Ensure migrations contain `CREATE EXTENSION IF NOT EXISTS vector;` wrapped in a guard to avoid failures on repeated execution.
- Update `docker-compose.yml` notes to document the requirement for pgvector; consider using a custom init script if further extensions become necessary.

### Tenant Settings Defaults
- Plan to extend `TenantService` to populate default chatbot settings when creating a tenant (confidence thresholds, keyword triggers, default prompt template).
- Store defaults in PHP configuration arrays to allow environment-specific overrides during rollout.

## Next steps
Phase 2 will focus on identifying content sources and defining the ingestion workflow.

## Phase 2 – Wissensquellen & Ingestion

### Source Inventory
- Existing static pages in `content/` (e.g., `faq.html`, `datenschutz.html`, `landing.html`) provide initial material for the knowledge base; marketing landing pages in `templates/marketing/` supply additional tenant-specific copy.
- Admin documentation resides under `docs/` and can seed internal-only knowledge; access control must ensure only relevant tenants ingest these sources.
- Each tenant will receive a manifest describing source URIs, file paths, and optional tags to aid incremental updates.

### Ingestion Workflow Outline
- Implement a CLI entry point under `scripts/` (e.g., `scripts/ingest_kb.php`) that accepts `--tenant`, `--source`, and `--rebuild` flags.
- Parsing pipeline: load document ➝ normalise to Markdown/plain text ➝ chunk with 750-token window and 150-token overlap ➝ persist metadata and embeddings.
- Track `checksum`/`hash` per document in `kb_documents.meta` to skip unchanged content; update `kb_chunks` atomically within a transaction.

### Admin Trigger
- Expose an admin route (e.g., `POST /admin/tenants/{slug}/kb/ingest`) protected by `RoleAuthMiddleware::requireAdmin()` that dispatches the CLI command asynchronously (Symfony Process or internal job queue).
- Provide activity logs using existing `AuditLogger` for traceability.

## Next steps
Phase 3 will establish the embedding, retrieval, and prompt services.

## Phase 3 – Embeddings & Retrieval Services

### Service Design
- Create `App\Service\EmbeddingService` to wrap external API clients (OpenAI, Ollama) with configurable model, rate limit, and timeout settings sourced from environment variables.
- Implement `App\Service\RetrievalService` leveraging `App\Infrastructure\Database` to query `kb_chunks` via pgvector's `<->` operator with configurable `top_k` and similarity threshold.
- Build `App\Service\PromptBuilder` that assembles system instructions, tenant-specific settings, and retrieved snippets into a structured prompt payload.

### Observability & Resilience
- Use the existing `AuditLogger` or Monolog channel to log embedding and LLM latency metrics; include request identifiers (session/conversation IDs).
- Apply retry with exponential backoff and jitter for transient HTTP failures using `GuzzleHttp\Client`.
- Surface failures with domain-specific exceptions (`EmbeddingException`, `RetrievalException`) for easier handling in controllers.

## Next steps
Phase 4 will wire these services into the chat endpoint and persistence layer.

## Phase 4 – Chat-Orchestrierung & Ticket-Flow

### API Endpoint Strategy
- Register a dedicated controller `App\Controller\ChatController` handling `POST /api/chat/{tenant}`; attach middleware for rate limiting and CSRF bypass similar to existing JSON APIs.
- Resolve tenants through `TenantService` by slug/subdomain; fallback with 404 when unknown.
- Request payload: `{ message: string, email?: string, sessionId?: string }`; validate inputs and normalise whitespace.

### Persistence Layer
- Create repositories (`ConversationRepository`, `MessageRepository`, `TicketRepository`) in `src/Repository/` to abstract SQL operations for new tables.
- Manage conversations by `tenant_id` + `session_id`, creating new records when absent; ensure assistant responses capture citations and confidence metrics.

### Ticket Automation
- Introduce `TicketAutomationService` evaluating tenant settings (`min_confidence_for_autoreply`, `auto_ticket_on_keywords`).
- When a ticket is created, emit notifications through existing mail/webhook infrastructure and audit logs.

## Next steps
Phase 5 will focus on the frontend widget and user experience.

## Phase 5 – Frontend-Widget & Nutzererlebnis

### Widget Implementation Plan
- Add a vanilla JavaScript module under `public/js/components/chat-widget.js` that exposes `window.quizraceChat` with methods for sending messages and resetting sessions.
- Persist a UUID session identifier in `localStorage` (`qr_chat_session`) and send it with every API request.
- Render messages within a lightweight UIkit card featuring response metadata (confidence badge, citations list) and DSGVO consent checkbox.

### Template Integration
- Include the widget via Twig partials (e.g., `templates/partials/chat-widget.html.twig`) and load it conditionally on landing pages by checking tenant capabilities.
- Provide data attributes for tenant slug, optional contact text, and privacy URLs to avoid inline scripts with dynamic string interpolation.

### Accessibility & Responsiveness
- Ensure keyboard navigation works (focus trapping when widget is expanded); support reduced-motion preference for bubble animations.
- Follow existing translation patterns by sourcing UI strings from the translation service (`translator.trans('chat.send')`).

## Next steps
Phase 6 will expand the admin interface and governance tooling.

## Phase 6 – Admin & Governance

### Admin UI Extensions
- Extend `templates/admin/` with a new navigation entry "Knowledge Base" linking to views for documents, ingestion status, and ticket rules.
- Controllers under `src/Controller/Admin` will leverage `RoleAuthMiddleware::requireAdmin()` and existing `AdminController::render()` helpers.
- Provide forms to edit tenant chatbot settings (confidence thresholds, keyword triggers, privacy copy) using `SettingsService` for persistence.

### Governance Processes
- Implement scheduled retention jobs (CLI command) to purge conversations older than 90 days, respecting tenant-specific overrides in `settings`.
- Offer an anonymisation endpoint triggered by the widget's delete link; reuse existing token-based flows for confirmation.
- Expand rate limiting to cap chat API usage per IP/session with configurable quotas stored in tenant settings.

## Next steps
Phase 7 will address testing, deployment, and monitoring.

## Phase 7 – Qualitätssicherung, Rollout & Betrieb

### Testing Strategy
- Add PHPUnit coverage for repositories and services, mocking external APIs via interfaces and stubs.
- Extend Node.js smoke tests to cover widget interactions using existing headless framework (see `tests/test_catalog_*`).
- Use Python HTML validator to ensure new Twig partials remain standards-compliant.

### Deployment Adjustments
- Update `docker-compose.yml` and Helm charts to include background workers (for ingestion) and environment variables for embedding providers.
- Provide migration guides in `docs/operations/rag-rollout.md` summarising schema changes and rollback steps.

### Monitoring & Analytics
- Instrument chat endpoints with latency and ticket creation metrics reported via existing logging stack; consider StatsD/Prometheus exporters.
- Build admin dashboards summarising conversation counts, resolution rates, and fallback tickets per tenant.

## Status
All roadmap phases now include actionable tasks; upcoming work will implement the described components.
