# Changelog

## 1.2.2

- Changed: Tested up to WordPress 7.0

## 1.2.1

- Updated: Norwegian (nb_NO) translations for new issue-link strings.

## 1.2.0

- Added: "Report bug" and "Request feature" links per add-on card, linking directly to GitHub issue templates.
- Added: GitHub issue templates (bug report & feature request) for all add-on repositories.

## 1.1.0

- Added: Norwegian (nb_NO) translation.
- Added: i18n build scripts via package.json.

## 1.0.1

- Tested up to WordPress 6.9.

## 1.0.0

- Added: "View details" modal using WordPress core's native plugin information renderer.
- Added: Client-side tab switching for instant navigation within the modal.
- Added: Patch-level version normalization for "Tested up to" to prevent false compatibility warnings.
- Removed: Activate/install button from the modal footer (managed from the card grid instead).
- Removed: Screenshots tab from the modal.

## 0.2.0

- Show update count badge on the Add-on Manager menu item (red bubble, same pattern as Editorial Workflow review count).

## 0.1.0

- Initial release with add-on catalog and manager UI.
- Install, activate, update, deactivate, and delete supported add-ons.
- Manual "Check updates now" action.
- 3-column card grid with custom CSS (no WordPress plugin-install style dependencies).
- Cards sorted alphabetically by title.
- GitHub Updater integration for self-updates via plugin-update-checker.
- GitHub Actions workflows for automated zip builds on release.
- Pest + Brain Monkey test suite (27 tests, 84 assertions).
