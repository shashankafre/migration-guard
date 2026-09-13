# Contributing to Migration Guard

Thank you for improving Migration Guard. Please open an issue before starting substantial work so the proposed change can be discussed.

## Development Setup

```bash
composer install
composer test
```

Migration Guard supports the Laravel versions and PHP releases declared in `composer.json`. Keep changes compatible with those constraints unless the change explicitly updates the support policy.

## Pull Requests

- Keep each pull request focused and include tests for behavior changes.
- Run `composer test` and ensure the GitHub Actions matrix is green.
- Update `CHANGELOG.md` under `Unreleased` for user-facing changes.
- Do not include unrelated formatting or generated files.
- Follow the existing code style and public API conventions.

## Reporting Issues

Include the Migration Guard version, Laravel version, PHP version, database driver and version, the command run, expected behavior, and actual behavior. Remove credentials and sensitive schema data before sharing logs.
