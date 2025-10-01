# ROBOTS.md – Engineering Guidelines for QuizRace

> These rules define the expected code quality for this repository. All contributors and assistants
> must follow them when they add or modify code.

## Scope

* The rules apply to the entire project unless a directory contains its own guidance file.
* Write code, comments, commit messages, and documentation in English.

## 1. General principles

* Optimise for readability, maintainability, and explicit behaviour over cleverness.
* Keep functions and classes focused; extract reusable logic into dedicated services or helpers.
* Avoid duplication—prefer shared abstractions in `src/Service/` or `src/Support/`.
* Ensure new features degrade gracefully when optional integrations (e.g., SMTP or Stripe) are not
  configured.

## 2. PHP coding standards

* Every PHP file must start with `<?php` and `declare(strict_types=1);` followed by a blank line.
* Follow PSR-12 as configured in `phpcs.xml`:
  * 4 spaces per indentation level, no hard tabs.
  * Maximum line length is 120 characters.
  * One class, interface, or trait per file. The file name mirrors the class name.
* Use typed properties and scalar/object type declarations for all parameters and return values.
* Mark visibility (`public`, `protected`, `private`) explicitly on methods and properties.
* Place opening braces on the same line as the declaration; place closing braces on their own line.
* Prefer dependency injection and constructor arguments over accessing globals or superglobals inside
  services. Limit direct use of `$_POST`, `$_GET`, and `$_SESSION` to controllers and middleware.
* Do not use PHP short tags (`<?`). Avoid `eval()`, `var_dump()`, `print_r()` in committed code.
* When you need to perform multi-step operations, wrap them in dedicated service methods so they can
  be unit-tested independently.

## 3. PHPDoc and comments

* Document public methods, controllers, and complex private helpers with PHPDoc blocks that describe
  behaviour, important side effects, and noteworthy invariants.
* Use `@param`, `@return`, and `@throws` annotations when type declarations are insufficient or when
  additional context helps the reader.
* Use `{@inheritdoc}` when implementing an interface that already documents its contract.
* Avoid redundant comments. Focus on *why* a decision was made rather than what the code does.

## 4. Error handling, security, and data integrity

* Prefer exceptions over returning error codes. Catch exceptions only when you can add context or
  handle the failure gracefully.
* Never suppress errors with `@`. Log unexpected failures through the existing Monolog channels.
* Validate external input and escape output in Twig templates. Avoid trusting request data even when
  it comes from authenticated users.
* Interact with databases through prepared statements or parameterised queries. Never concatenate
  user input into SQL strings.
* Protect secrets: do not hardcode credentials, API keys, or tokens in code or configuration files.

## 5. Database migrations

* Never modify an existing migration. If you need to change the schema, create a new SQL file in
  `migrations/`.
* Name migration files using the timestamped pattern already in use
  (`YYYYMMDD_description.sql`). Document intent in comments at the top of the file when the migration
  is non-trivial.
* Provide downgrade instructions in the pull request description if rolling back is risky or manual
  intervention is required.

## 6. Testing and quality assurance

* Keep the automated test suite green. Add PHPUnit, Python, or Node-based tests alongside new
  features to prevent regressions.
* Tests must be deterministic and runnable via `composer test`. Avoid relying on external services
  or network calls; mock integrations instead.
* Update fixtures under `tests/Support/` when data formats change.
* Run `vendor/bin/phpstan analyse -c phpstan.neon.dist` and address all reported issues. Increase the
  static analysis level only when the existing codebase passes.

## 7. Frontend (Twig, JavaScript, assets)

* Keep Twig templates accessible: provide meaningful ARIA labels, preserve keyboard navigation, and
  respect the existing light/dark theme switch (`data-theme` on `<body>`).
* Do not introduce frontend dependencies that require a build step. The project intentionally uses
  vanilla JavaScript and UIkit.
* Avoid polluting the global namespace. Attach functionality to `window.quizConfig` or module
  patterns already present in `public/js/`.
* Document DOM expectations with inline comments or Node.js tests so behaviour remains verifiable in
  the existing headless environment.
* When handling images or media uploads, reuse `App\Service\ImageUploadService` and its quality
  constants.

## 8. Logging, configuration, and operations

* Use the configured Monolog channels for observability. Avoid `echo`/`print` statements in runtime
  code.
* Guard optional integrations (Stripe, SMTP, webhook calls) behind configuration checks. Fail safe
  when required environment variables are missing.
* When adding environment variables, update `.env`, `sample.env`, relevant Docker compose files, and
  document the change in `README.md`.

Adhering to these guidelines keeps the codebase consistent and makes reviews faster. If a situation
requires an exception, document the rationale in the pull request so future maintainers understand
the trade-off.
