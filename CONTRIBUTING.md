# Contributing to QuizRace

Thank you for your interest in improving QuizRace. This document explains how we collaborate, how to
set up a working environment, and which quality checks are required before opening a pull request.

## Ways to contribute

* **Report bugs** by opening an issue that describes the current behaviour, the expected behaviour,
  and reproduction steps.
* **Propose new features** by outlining the problem you want to solve, the impact on existing
  functionality, and suggested changes.
* **Improve documentation** by updating Markdown files in `docs/`, `README.md`, or UI copy in
  Twig templates.

## Development workflow

1. Fork the repository and clone your fork locally.
2. Create a feature branch from the latest `main` branch. Keep each branch focused on a single
   change.
3. Commit early and often with clear messages. Reference the related GitHub issue when possible.
4. Push your branch and open a pull request once the checklist in this guide is complete.

We follow a lightweight version of GitHub Flow. Small, focused pull requests with a well-written
summary are easier to review and merge.

## Environment setup

The project targets **PHP 8.2+** and ships with Docker support, but you can also work directly on
your host machine.

### Without Docker

1. Install [Composer](https://getcomposer.org/), Node.js 20 LTS, and Python 3.8 or newer.
2. Install PHP extensions `pdo_pgsql`, `gd`, and `exif` (required for database access and image
   processing).
3. Run `composer install` to fetch PHP dependencies.
4. Copy `sample.env` to `.env` and adapt the connection settings. For database-backed features you
   need access to a PostgreSQL 15 instance.
5. Execute the setup scripts when you need real data:
   * `php scripts/run_migrations.php`
   * `php scripts/import_to_pgsql.php`
6. Start a local server for manual testing:
   ```bash
   php -S localhost:8080 -t public public/router.php
   ```

### With Docker

Use the supplied compose file to spin up the full stack:
```bash
docker compose up --build slim postgres
```
The `slim` service exposes the application on <http://localhost:8080>. Docker automatically enables
all required PHP extensions.

## Coding standards

* All PHP code must follow [PSR-12](https://www.php-fig.org/psr/psr-12/) with the adjustments
  configured in `phpcs.xml`.
* Each PHP file must declare strict types: `declare(strict_types=1);`.
* Namespaces follow PSR-4; keep one class, interface, or trait per file.
* Prefer expressive names and keep functions small. Extract shared logic into services rather than
  duplicating code.
* Align with the detailed engineering rules in [`ROBOTS.md`](ROBOTS.md).

## Namespace fallbacks (SEO + page modules)

Marketing pages can be stored in multiple namespaces. When loading SEO metadata or page modules, the
application resolves data in this order:

1. Use the namespace-specific page first.
2. If no SEO configuration or modules exist for that page, fall back to the page with the same slug
   in the `default` namespace (`PageService::DEFAULT_NAMESPACE`).

This keeps tenant-specific overrides predictable while ensuring shared defaults remain available.

## Quality gates

Run the following commands before every pull request. They are the same checks executed in CI.

* `vendor/bin/phpcs` – lints PHP files against PSR-12 and project rules.
* `vendor/bin/phpstan analyse -c phpstan.neon.dist` – runs static analysis (level 4).
* `vendor/bin/phpunit` – executes the PHP unit test suite.
* `python3 tests/test_html_validity.py` – validates our Twig templates for correct HTML nesting.
* `python3 tests/test_json_validity.py` – ensures JSON catalogues remain well-formed.
* JavaScript behaviour tests (Node.js 20 LTS):
  ```bash
  node tests/test_competition_mode.js
  node tests/test_results_rankings.js
  node tests/test_random_name_prompt.js
  node tests/test_onboarding_plan.js
  node tests/test_onboarding_flow.js
  node tests/test_login_free_catalog.js
  node tests/test_catalog_smoke.js
  node tests/test_catalog_autostart_path.js
  node tests/test_shuffle_questions.js
  node tests/test_team_name_suggestion.js
  node tests/test_catalog_prevent_repeat.js
  node tests/test_event_summary_switch.js
  node tests/test_sticker_editor_save_events.js
  node tests/test_media_filters.js
  node tests/test_media_preview.js
  ```

You can run the complete pipeline via `composer test`, which executes the PHP,
Python, and JavaScript checks in the order listed above. Running the commands
individually often produces faster feedback while you iterate.

## Pull request checklist

Before requesting a review:

- [ ] Tests and static analysis pass locally.
- [ ] New functionality is covered by automated tests when feasible.
- [ ] Configuration changes are documented in `README.md` or the relevant file.
- [ ] Database changes are implemented through new migration files—existing migrations must never be
      modified.
- [ ] Screenshots are added to the pull request description for UI changes.
- [ ] The pull request description summarises the change and lists the commands you executed.

## Working on documentation

Documentation for GitHub Pages lives in `docs/`. Install Ruby bundler dependencies and serve the
site locally if you are editing that content:
```bash
bundle install
bundle exec jekyll serve
```

## Image standards

Image uploads use predefined quality levels. Use these constants from
`App\\Service\\ImageUploadService` to keep the output consistent:

| Type    | Quality | Notes                                   |
|---------|---------|-----------------------------------------|
| Logo    | 80      | Save losslessly when possible (PNG)     |
| Sticker | 90      | Use JPEG or WEBP for best compression   |
| Photo   | 70      | Applies to event and gallery snapshots  |

PNG files are stored without quality loss. JPEG and WEBP files follow the specified values. Refer to
`ImageUploadService::QUALITY_*` when you add new image processing code.

## Need help?

Open a discussion or reach out via an issue if anything is unclear. We appreciate improvements to
this guide as the project evolves.
