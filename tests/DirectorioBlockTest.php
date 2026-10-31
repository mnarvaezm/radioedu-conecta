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
