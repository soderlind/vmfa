<?php
/**
 * Pest.php configuration file.
 *
 * @package VMFA\Tests
 */

declare(strict_types=1);

use Brain\Monkey;

/*
|--------------------------------------------------------------------------
| Uses: Brain Monkey for all tests
|--------------------------------------------------------------------------
*/
uses()
	->beforeEach( function () {
		Monkey\setUp();
	} )
	->afterEach( function () {
		Monkey\tearDown();
	} )
	->in( 'unit' );
