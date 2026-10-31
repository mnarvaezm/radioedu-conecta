<?php
/**
 * Tests for the PHP-only directory block.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Frontend\DirectorioShortcode;
use WP_Block_Type_Registry;
use WP_HTML_Tag_Processor;
use WP_UnitTestCase;

/**
 * Verifies the block registration and that it renders the same directory as the shortcode.
 */
class DirectorioBlockTest extends WP_UnitTestCase {

	public function test_block_is_registered_for_automatic_editor_registration(): void {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( DirectorioShortcode::BLOCK_NAME );

		$this->assertNotNull( $block_type );
		$this->assertTrue( $block_type->supports['autoRegister'] );
		$this->assertFalse( $block_type->supports['multiple'] );
		$this->assertIsCallable( $block_type->render_callback );
		$this->assertContains( 'radioedu-directorio-css', $block_type->style_handles );
		$this->assertTrue( wp_style_is( 'radioedu-directorio-css', 'registered' ) );
	}

	public function test_block_renders_the_directory_inside_the_block_wrapper(): void {
		$html = do_blocks( '<!-- wp:radioedu-conecta/directorio {"align":"wide"} /-->' );

		$this->assertStringContainsString( 'wp-block-radioedu-conecta-directorio', $html );
		$this->assertStringContainsString( 'alignwide', $html );
		$this->assertStringContainsString( 'id="radioedu-conecta-app"', $html );
		$this->assertTrue( wp_style_is( 'radioedu-directorio-css', 'enqueued' ) );
	}

	public function test_directory_adds_no_page_landmarks_inside_the_theme_content(): void {
		$html = do_blocks( '<!-- wp:radioedu-conecta/directorio /-->' );
		$tags = new WP_HTML_Tag_Processor( $html );

		$found          = [];
		$results_region = false;
		$heading_tag    = '';
		while ( $tags->next_tag() ) {
			$found[] = $tags->get_tag();
			if ( 'SECTION' === $tags->get_tag() && 'radioedu-results-heading' === $tags->get_attribute( 'aria-labelledby' ) ) {
				$results_region = true;
			}
			if ( 'radioedu-results-heading' === $tags->get_attribute( 'id' ) ) {
				$heading_tag = $tags->get_tag();
			}
		}

		// The theme already wraps the page content in <main>; a second one, or a <header> outside it, adds duplicate landmarks.
		$this->assertNotContains( 'MAIN', $found );
		$this->assertNotContains( 'HEADER', $found );
		$this->assertTrue( $results_region, 'The results are a region named by their heading.' );
		$this->assertSame( 'H2', $heading_tag );

		$region_start = strpos( $html, '<section class="radioedu-results"' );
		$grid         = strpos( $html, 'id="radioedu-cards-container"' );
		$this->assertGreaterThan( $region_start, $grid );
		$this->assertGreaterThan( $grid, strrpos( $html, '</section>' ) );
	}

	public function test_styles_are_enqueued_early_for_pages_with_the_block(): void {
		$page = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_content' => '<!-- wp:radioedu-conecta/directorio /-->',
			]
		);
		$this->go_to( get_permalink( $page ) );
		wp_dequeue_style( 'radioedu-directorio-css' );

		DirectorioShortcode::enqueue_assets();

		$this->assertTrue( wp_style_is( 'radioedu-directorio-css', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'radioedu-leaflet-css', 'enqueued' ) );
	}
}
