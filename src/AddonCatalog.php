<?php
/**
 * Add-on catalog for Virtual Media Folders.
 *
 * @package VMFA
 * @since 0.1.0
 */

declare(strict_types=1);

namespace VMFA;

defined( 'ABSPATH' ) || exit;

/**
 * Provides metadata for supported add-ons.
 */
final class AddonCatalog {

	/**
	 * Base URL for GitHub repositories.
	 */
	private const GITHUB_BASE = 'https://github.com/soderlind';

	/**
	 * Catalog definition.
	 *
	 * Each entry maps a slug to title and description.
	 *
	 * @var array<string, array{title: string, description: string}>
	 */
	private const ITEMS = [
		'vmfa-ai-organizer'       => [
			'title'       => 'AI Organizer',
			'description' => 'Uses vision-capable AI models to analyze actual image content and automatically organize your media library into virtual folders. This add-on requires an API key from a supported AI service provider, or a local LLM.',
		],
		'vmfa-editorial-workflow' => [
			'title'       => 'Editorial Workflow',
			'description' => 'Role-based folder access, move restrictions, and Inbox workflow for Virtual Media Folders.',
		],
		'vmfa-folder-exporter'    => [
			'title'       => 'Folder Exporter',
			'description' => 'Export folders (or subtrees) as ZIP archives with optional CSV manifests.',
		],
		'vmfa-media-cleanup'      => [
			'title'       => 'Media Cleanup',
			'description' => 'Tools to identify and clean up unused or duplicate media files.',
		],
		'vmfa-rules-engine'       => [
			'title'       => 'Rules Engine',
			'description' => 'Rule-based automatic folder assignment for media uploads, based on metadata, file type, or other criteria.',
		],
	];

	/**
	 * Get the full add-on catalog.
	 *
	 * @return array<string, array{slug: string, title: string, description: string, repo_url: string, zip_url: string, readme_url: string, plugin_file: string}>
	 */
	public static function all(): array {
		$catalog = [];

		foreach ( self::ITEMS as $slug => $item ) {
			$catalog[ $slug ] = self::build_entry( $slug, $item );
		}

		return $catalog;
	}

	/**
	 * Get a single add-on by slug.
	 *
	 * @param string $slug Add-on slug.
	 * @return array{slug: string, title: string, description: string, repo_url: string, zip_url: string, readme_url: string, plugin_file: string}|null
	 */
	public static function get( string $slug ): ?array {
		if ( ! isset( self::ITEMS[ $slug ] ) ) {
			return null;
		}

		return self::build_entry( $slug, self::ITEMS[ $slug ] );
	}

	/**
	 * Build a catalog entry from a slug and item definition.
	 *
	 * @param string                          $slug Add-on slug.
	 * @param array{title: string, description: string} $item Item definition.
	 * @return array{slug: string, title: string, description: string, repo_url: string, zip_url: string, readme_url: string, plugin_file: string}
	 */
	private static function build_entry( string $slug, array $item ): array {
		return [
			'slug'        => $slug,
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			'title'       => __( $item[ 'title' ], 'vmfa' ),
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			'description' => __( $item[ 'description' ], 'vmfa' ),
			'repo_url'    => self::GITHUB_BASE . '/' . $slug,
			'zip_url'     => self::GITHUB_BASE . '/' . $slug . '/releases/latest/download/' . $slug . '.zip',
			'readme_url'  => 'https://raw.githubusercontent.com/soderlind/' . $slug . '/main/readme.txt',
			'plugin_file' => $slug . '/' . $slug . '.php',
		];
	}
}
