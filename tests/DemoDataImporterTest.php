<?php
/**
 * Tests for the content created by the demo importer.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Admin\DemoDataImporter;
use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_UnitTestCase;

/**
 * Verifies that demo centros open as blocks in the block editor.
 */
class DemoDataImporterTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		RoleCapabilities::add_capabilities();
		CentroPostType::register();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_demo_descriptions_are_paragraph_blocks_not_classic_content(): void {
		$this->assertSame( 6, DemoDataImporter::import_demo_centers() );

		$centro = get_page_by_path( 'ies-sierra-de-gata-radio', OBJECT, CentroPostType::POST_TYPE );
		$this->assertNotNull( $centro );

		$blocks = parse_blocks( $centro->post_content );
		// parse_blocks() returns the whitespace between blocks as blocks without a name.
		$named = array_values( array_filter( $blocks, static fn ( array $block ): bool => null !== $block['blockName'] ) );
		$loose = array_filter( $blocks, static fn ( array $block ): bool => null === $block['blockName'] && '' !== trim( $block['innerHTML'] ) );

		$this->assertSame( [ 'core/paragraph', 'core/paragraph' ], array_column( $named, 'blockName' ) );
		$this->assertSame( [], $loose, 'No content may be left outside blocks (it would become a Classic block).' );
		$this->assertStringContainsString( 'divulgación medioambiental', $named[0]['innerHTML'] );
		$this->assertStringContainsString( 'Traspasando Fronteras', $named[1]['innerHTML'] );
	}
}
