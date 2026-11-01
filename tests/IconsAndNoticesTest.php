<?php
/**
 * Tests for the SVG icons (WordPress Icons API) and the admin notices.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Admin\ColaboracionesAdminPage;
use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\Frontend\DirectorioShortcode;
use RadioEdu\Conecta\Icons;
use WP_Icons_Registry;
use WP_UnitTestCase;

/**
 * Verifies that every icon used by the templates renders and that notices use the core API.
 */
class IconsAndNoticesTest extends WP_UnitTestCase {

	public function tear_down(): void {
		unset( $_GET['match_updated'] );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Returns the icon names referenced by the templates, the admin page and directorio.js.
	 *
	 * @return array<int, string>
	 */
	private function used_icon_names(): array {
		$sources = [
			'templates/directorio.php',
			'templates/match-drawer.php',
			'templates/single-centro.php',
			'src/Admin/ColaboracionesAdminPage.php',
		];
		$names   = [];
		foreach ( $sources as $source ) {
			preg_match_all( "/Icons::render\( '([a-z0-9-]+\/[a-z0-9-]+)'/", (string) file_get_contents( RADIOEDU_CONECTA_DIR . $source ), $matches );
			$names = array_merge( $names, $matches[1] );
		}

		return array_values( array_unique( $names ) );
	}

	public function test_plugin_icons_survive_the_core_svg_sanitizer(): void {
		foreach ( array_keys( Icons::get_icon_labels() ) as $slug ) {
			$svg = Icons::get( Icons::COLLECTION . '/' . $slug );

			// The core allowlist drops stroke attributes, so a stroke-based icon would lose its shape.
			$this->assertMatchesRegularExpression( '/<path[^>]+d="M/i', $svg, $slug );
			$this->assertStringContainsString( 'radioedu-svg-icon--' . $slug, $svg );
		}
	}

	public function test_every_icon_used_by_the_ui_is_registered(): void {
		$names = $this->used_icon_names();

		$this->assertGreaterThan( 15, count( $names ) );
		foreach ( $names as $name ) {
			$this->assertTrue( WP_Icons_Registry::get_instance()->is_registered( $name ), $name );
			$this->assertNotSame( '', Icons::get( $name ), $name );
		}
	}

	public function test_icons_are_decorative_unless_labelled(): void {
		$decorative = Icons::get( 'core/search' );
		$labelled   = Icons::get( 'core/search', [ 'label' => 'Buscar' ] );

		$this->assertStringContainsString( 'aria-hidden="true"', $decorative );
		$this->assertStringContainsString( 'focusable="false"', $decorative );
		$this->assertStringContainsString( 'role="img"', $labelled );
		$this->assertStringContainsString( 'aria-label="Buscar"', $labelled );
	}

	public function test_render_keeps_the_svg_markup_and_escapes_everything_else(): void {
		ob_start();
		Icons::render( 'radioedu-conecta/send', [ 'class' => 'extra" onmouseover="alert(1)' ] );
		$html = (string) ob_get_clean();

		$this->assertStringContainsStringIgnoringCase( 'viewBox="0 0 24 24"', $html );
		$processor = new \WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $processor->next_tag( 'svg' ) );
		$this->assertNull( $processor->get_attribute( 'onmouseover' ) );
		$this->assertTrue( $processor->has_class( 'radioedu-svg-icon--send' ) );
	}

	public function test_directory_markup_uses_svg_icons_instead_of_emoji(): void {
		$html = DirectorioShortcode::render_shortcode();

		$this->assertStringContainsString( 'radioedu-svg-icon--map-marker', $html );
		$this->assertStringContainsString( '<template id="radioedu-icon-templates">', $html );
		$this->assertStringContainsString( 'radioedu-svg-icon--star-empty', $html );
		foreach ( [ '📍', '🔍', '✈️', '✕', '➔' ] as $emoji ) {
			$this->assertStringNotContainsString( $emoji, $html );
		}
	}

	public function test_colaboraciones_notice_uses_the_core_admin_notice_api(): void {
		RoleCapabilities::add_capabilities();
		ColaboracionesTable::create_table();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_GET['match_updated'] = 'aceptada';
		$notices               = did_action( 'wp_admin_notice' );

		ob_start();
		ColaboracionesAdminPage::render_page();
		$html = (string) ob_get_clean();

		$this->assertSame( $notices + 1, did_action( 'wp_admin_notice' ) );
		$this->assertStringContainsString( 'notice-success', $html );
		$this->assertStringContainsString( 'is-dismissible', $html );
	}

	public function test_requirements_notice_is_an_escaped_error_notice(): void {
		ob_start();
		radioedu_conecta_requirements_notice( 'Requiere <WordPress 7.1>' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'Requiere &lt;WordPress 7.1&gt;', $html );
	}
}
