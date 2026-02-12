# Virtual Media Folders - Add-On Manager

Install and manage add-ons that extend [Virtual Media Folders](https://github.com/soderlind/virtual-media-folders). Provides a dedicated admin screen under **Media → Add-on Manager** for installing, activating, updating, deactivating, and deleting supported add-ons directly from GitHub releases.

## Supported Add-ons

| Add-on | Description |
|--------|-------------|
| [AI Organizer](https://github.com/soderlind/vmfa-ai-organizer) | Uses vision-capable AI models to analyze image content and automatically organize your media library into virtual folders. |
| [Rules Engine](https://github.com/soderlind/vmfa-rules-engine) | Rule-based automatic folder assignment for media uploads, based on metadata, file type, or other criteria. |
| [Editorial Workflow](https://github.com/soderlind/vmfa-editorial-workflow) | Role-based folder access, move restrictions, and Inbox workflow. |
| [Media Cleanup](https://github.com/soderlind/vmfa-media-cleanup) | Tools to identify and clean up unused or duplicate media files. |
| [Folder Exporter](https://github.com/soderlind/vmfa-folder-exporter) | Export folders (or subtrees) as ZIP archives with optional CSV manifests. |

## Requirements

- WordPress 6.8+
- PHP 8.3+
- [Virtual Media Folders](https://github.com/soderlind/virtual-media-folders) (core plugin)

## Installation

1. Download the latest [`vmfa.zip`](https://github.com/soderlind/vmfa/releases/latest/download/vmfa.zip).
2. In WordPress, go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate the plugin.

The plugin updates itself automatically via GitHub releases using [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker).

## Usage

1. Go to **Media → Add-on Manager**.
2. **Install** any add-on — downloads the latest release zip from GitHub.
3. **Activate** / **Deactivate** add-ons as needed.
4. **Update** when a newer release is available (version comparison shown on the card).
5. **Delete** to remove an add-on completely (with confirmation prompt).
6. Click **Check updates now** to refresh cached version data.

## How It Works

- Add-on metadata is defined in `src/AddonCatalog.php`.
- Each add-on's latest version is fetched from the GitHub Releases API and cached as a transient for 6 hours.
- Installs and updates use WordPress `Plugin_Upgrader` with `Automatic_Upgrader_Skin`.
- All actions require the `manage_options` capability and are protected by nonce verification.

## Development

```bash
composer install
composer test    # Run tests (Pest)
composer lint    # Run PHPCS
```

## GitHub Actions

Two workflows ship with the plugin:

- **Manually Build release zip** — trigger manually with a tag to create and upload `vmfa.zip` to a release.
- **On Release, Build release zip** — runs automatically when a release is published.

Both verify that `plugin-update-checker` is included in the zip before uploading.

## License

GPL-2.0-or-later

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
