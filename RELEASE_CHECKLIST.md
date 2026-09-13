# Migration Guard Release Checklist

## Before Release

- [ ] Confirm the release scope and version follow Semantic Versioning.
- [ ] Move completed entries from `CHANGELOG.md` `Unreleased` into the versioned release section.
- [ ] Run `composer validate --no-check-publish`.
- [ ] Run `composer test`.
- [ ] Confirm the GitHub Actions matrix passes for PHP 8.2, 8.3, and 8.4 with Laravel 11 and 12.
- [ ] Review supported PHP and Laravel versions against `composer.json`.
- [ ] Verify installation with `composer require shashankafre/migration-guard --dev` in a clean Laravel application.

## Publish

- [ ] Create and push an annotated version tag.
- [ ] Create the GitHub release with the corresponding changelog entries.
- [ ] Confirm Packagist has indexed the `shashankafre/migration-guard` tag.

## After Release

- [ ] Install the published version in a clean Laravel application and run `php artisan migration:safety`.
- [ ] Verify the release page and package metadata identify the project as Migration Guard.
