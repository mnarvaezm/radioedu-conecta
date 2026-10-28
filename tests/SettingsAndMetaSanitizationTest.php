<?php
/**
 * Tests for Settings API and Post Meta sanitization.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Admin\CentroMetaBoxes;
use RadioEdu\Conecta\Admin\Settings;
use WP_UnitTestCase;

/**
 * Verifies strict sanitization and whitelisting of plugin settings and metadata.
 */
class SettingsAndMetaSanitizationTest extends WP_UnitTestCase {

	/**
	 * Test default settings fallback when non-array input is passed.
	 */
	public function test_sanitize_settings_non_array_returns_defaults(): void {
		$result   = Settings::sanitize_settings( 'invalid_input' );
		$defaults = Settings::get_default_settings();

		$this->assertSame( $defaults, $result );
	}

	/**
	 * Test valid coordinates, zoom clamping, and checkbox boolean normalization.
	 */
	public function test_sanitize_settings_valid_and_clamped_values(): void {
		$input = [
			'default_lat'        => '38.8794',
			'default_lng'        => '-6.9707',
			'default_zoom'       => '12',
			'notify_on_proposal' => '1',
			// 'notify_on_status' omitted to test unchecked checkbox behavior.
		];

		$sanitized = Settings::sanitize_settings( $input );

		$this->assertSame( '38.8794', $sanitized['default_lat'] );
		$this->assertSame( '-6.9707', $sanitized['default_lng'] );
		$this->assertSame( '12', $sanitized['default_zoom'] );
		$this->assertSame( '1', $sanitized['notify_on_proposal'] );
		$this->assertSame( '0', $sanitized['notify_on_status'] );
	}

	/**
	 * Test out-of-range zoom levels fall back to default zoom (8).
	 */
	public function test_sanitize_settings_out_of_range_zoom(): void {
		$sanitized_high = Settings::sanitize_settings( [ 'default_zoom' => '99' ] );
		$sanitized_zero = Settings::sanitize_settings( [ 'default_zoom' => '0' ] );

		$this->assertSame( '8', $sanitized_high['default_zoom'] );
		$this->assertSame( '8', $sanitized_zero['default_zoom'] );
	}

	/**
	 * Test available country flags whitelist contains Spain and Portugal.
	 */
	public function test_available_flags_whitelist(): void {
		$flags = CentroMetaBoxes::get_available_flags();

		$this->assertArrayHasKey( '🇪🇸', $flags );
		$this->assertArrayHasKey( '🇵🇹', $flags );
		$this->assertArrayHasKey( '🇪🇺', $flags );
	}
}
