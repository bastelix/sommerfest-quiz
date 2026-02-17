# CLAUDE.md – Instructions for Claude Code

## Commit messages

All commit messages **must** follow the [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/) specification.

### Format

```
<type>(<optional scope>): <description>

[optional body]

[optional footer(s)]
```

### Allowed types

| Type       | When to use                                        |
|------------|----------------------------------------------------|
| `feat`     | A new feature                                      |
| `fix`      | A bug fix                                          |
| `docs`     | Documentation only changes                         |
| `style`    | Formatting, missing semicolons, etc. (no logic)    |
| `refactor` | Code change that neither fixes a bug nor adds a feature |
| `perf`     | Performance improvement                            |
| `test`     | Adding or updating tests                           |
| `build`    | Build system or external dependencies              |
| `ci`       | CI configuration and scripts                       |
| `chore`    | Other changes that don't modify src or test files  |

### Rules

- The description must be in **English** and start with a lowercase letter.
- Keep the subject line under 100 characters.
- Use the body to explain *what* and *why*, not *how*.
- Mark breaking changes with a `!` after the type/scope (e.g. `feat!: remove legacy endpoint`) or add a `BREAKING CHANGE:` footer.
- Never commit without a conventional type prefix. The repository enforces this via commitlint.

### Examples

```
feat(quiz): add real-time score leaderboard

fix: prevent duplicate player registrations

refactor(auth): extract token validation into dedicated service

docs: update API endpoint documentation
```

## Branch naming

When creating a new branch, use the **commit type** as the branch prefix — not `claude/`.

### Format

```
<type>/<short-description>
```

### Mapping

| Branch prefix | When to use                              |
|---------------|------------------------------------------|
| `feature/`    | New features (`feat` commits)            |
| `fix/`        | Bug fixes                                |
| `refactor/`   | Refactoring                              |
| `docs/`       | Documentation changes                    |
| `chore/`      | Maintenance, dependencies, CI            |
| `test/`       | Adding or updating tests                 |
| `hotfix/`     | Urgent production fixes                  |

### Rules

- Use lowercase, kebab-case descriptions (e.g. `fix/duplicate-player-registration`).
- Keep the description short but meaningful (3–6 words max).
- If the branch relates to a GitHub issue, include the issue number (e.g. `fix/123-duplicate-registration`).

### Examples

```
feature/realtime-leaderboard
fix/duplicate-player-registration
fix/123-tablet-layout-regression
refactor/extract-token-validation
docs/update-api-endpoints
chore/bump-dependencies
```
