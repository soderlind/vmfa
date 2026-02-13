<?php
/**
 * Tests for AddonCatalog.
 *
 * @package VMFA\Tests
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use VMFA\AddonCatalog;

beforeEach( function () {
	// Stub __() to return the string as-is.
	Functions\stubs(
		[
			'__' => static fn( string $text, string $domain = 'default' ): string => $text,
		]
	);
} );

// --- all() -------------------------------------------------------------------

it( 'returns all catalog entries', function () {
	$catalog = AddonCatalog::all();

	expect( $catalog )->toBeArray()->not->toBeEmpty();
	expect( array_keys( $catalog ) )->toContain(
		'vmfa-ai-organizer',
		'vmfa-rules-engine',
		'vmfa-editorial-workflow',
		'vmfa-media-cleanup',
		'vmfa-folder-exporter',
	);
} );

it( 'returns entries with required keys', function () {
	$catalog = AddonCatalog::all();

	foreach ( $catalog as $slug => $entry ) {
		expect( $entry )->toHaveKeys( [ 'slug', 'title', 'description', 'repo_url', 'zip_url', 'readme_url', 'plugin_file' ] );
	}
} );

it( 'builds correct repo_url for each entry', function () {
	$catalog = AddonCatalog::all();

	foreach ( $catalog as $slug => $entry ) {
		expect( $entry[ 'repo_url' ] )->toBe( 'https://github.com/soderlind/' . $slug );
	}
} );

it( 'builds correct readme_url for each entry', function () {
	$catalog = AddonCatalog::all();

	foreach ( $catalog as $slug => $entry ) {
		expect( $entry[ 'readme_url' ] )->toBe(
			'https://raw.githubusercontent.com/soderlind/' . $slug . '/main/readme.txt'
		);
	}
} );

it( 'builds correct zip_url using releases/latest/download pattern', function () {
	$catalog = AddonCatalog::all();

	foreach ( $catalog as $slug => $entry ) {
		expect( $entry[ 'zip_url' ] )->toBe(
			'https://github.com/soderlind/' . $slug . '/releases/latest/download/' . $slug . '.zip'
		);
	}
} );

it( 'builds correct plugin_file for each entry', function () {
	$catalog = AddonCatalog::all();

	foreach ( $catalog as $slug => $entry ) {
		expect( $entry[ 'plugin_file' ] )->toBe( $slug . '/' . $slug . '.php' );
	}
} );

it( 'sets slug field matching the array key', function () {
	$catalog = AddonCatalog::all();

	foreach ( $catalog as $slug => $entry ) {
		expect( $entry[ 'slug' ] )->toBe( $slug );
	}
} );

// --- get() -------------------------------------------------------------------

it( 'returns a single add-on by slug', function () {
	$entry = AddonCatalog::get( 'vmfa-rules-engine' );

	expect( $entry )->not->toBeNull();
	expect( $entry[ 'slug' ] )->toBe( 'vmfa-rules-engine' );
	expect( $entry[ 'title' ] )->toBe( 'Rules Engine' );
	expect( $entry[ 'repo_url' ] )->toBe( 'https://github.com/soderlind/vmfa-rules-engine' );
} );

it( 'returns null for unknown slug', function () {
	$entry = AddonCatalog::get( 'nonexistent-addon' );

	expect( $entry )->toBeNull();
} );

it( 'returns null for empty slug', function () {
	$entry = AddonCatalog::get( '' );

	expect( $entry )->toBeNull();
} );

it( 'returns same data from get() as from all() for the same slug', function () {
	$from_all = AddonCatalog::all()[ 'vmfa-folder-exporter' ];
	$from_get = AddonCatalog::get( 'vmfa-folder-exporter' );

	expect( $from_get )->toBe( $from_all );
} );
