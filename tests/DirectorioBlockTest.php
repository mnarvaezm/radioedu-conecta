<?php
/**
 * Tests for the PHP-only directory block.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Frontend\DirectorioShortcode;
use RadioEdu\Conecta\Frontend\DirectoryStore;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_Block_Type_Registry;
use WP_HTML_Tag_Processor;
use WP_UnitTestCase;

/**
 * Verifies the block registration and that it renders the same directory as the shortcode.
 */
class DirectorioBlockTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// The Interactivity API merges state for the whole request: start each test from none.
		$GLOBALS['wp_interactivity'] = new \WP_Interactivity_API();
		( new \ReflectionProperty( DirectoryStore::class, 'prepared' ) )->setValue( null, false );
	}

	public function test_block_is_registered_for_automatic_editor_registration(): void {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( DirectorioShortcode::BLOCK_NAME );

		$this->assertNotNull( $block_type );
		// Metadata comes from blocks/directorio/block.json, translated with the plugin text domain.
		$this->assertFileExists( RADIOEDU_CONECTA_DIR . 'blocks/directorio/block.json' );
		$this->assertSame( 'Directorio Traspasando Fronteras', $block_type->title );
		$this->assertSame( 'widgets', $block_type->category );
		$this->assertContains( 'podcast', $block_type->keywords );
		$this->assertTrue( $block_type->supports['autoRegister'] );
		// WordPress processes the directives of interactive blocks itself.
		$this->assertTrue( $block_type->supports['interactivity']['interactive'] );
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

	public function test_without_javascript_the_directory_shows_its_stations_and_count(): void {
		self::factory()->post->create_many( 3, [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish' ] );
		self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish', 'post_password' => 'secret' ] );
		self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'draft' ] );

		$html = DirectorioShortcode::render_shortcode();

		// Only the three published stations without a password, rendered on the server.
		$this->assertStringContainsString( '<span id="radioedu-count-number" data-wp-text="state.totalLabel">3</span>', $html );
		$this->assertSame( 3, substr_count( $html, 'data-wp-each-child="radioedu-conecta/directorio::state.centros"' ) );
		// The notice for browsers without JavaScript names what needs it.
		$this->assertStringContainsString( '<noscript><p id="radioedu-js-fallback"', $html );
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
