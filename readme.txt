=== Virtual Media Folders - Add-On Manager ===
Contributors: soderlind
Tags: media, folders, addons, manager
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.3
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Install and manage add-ons that extend Virtual Media Folders.

== Description ==

Adds a Media Library admin screen for installing, activating, updating, deactivating, and deleting supported Virtual Media Folders add-ons.

== Installation ==

1. Upload the `vmfa` folder to `wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to Media -> Add-on Manager.

== Frequently Asked Questions ==

= Does this plugin install add-ons automatically? =

It lets you install add-ons and then activate them manually.

= Where do updates come from? =

Update checks use the latest GitHub releases for each supported add-on.

== Changelog ==

= 0.1.0 =
* Initial release with add-on catalog and manager UI.
* Install, activate, update, deactivate, and delete supported add-ons.
* Manual "Check updates now" action.
* 3-column card grid with custom CSS (no WordPress plugin-install style dependencies).
* Cards sorted alphabetically by title.
* GitHub Updater integration for self-updates via plugin-update-checker.
* GitHub Actions workflows for automated zip builds on release.
* Pest + Brain Monkey test suite (27 tests, 84 assertions).
