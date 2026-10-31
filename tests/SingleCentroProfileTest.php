<?php
/**
 * Tests for the single centro profile page.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Frontend\DirectorioShortcode;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_Block_Templates_Registry;
use WP_UnitTestCase;

/**
 * Verifies that the public profile page shows the theme's title, meta and featured image only
 * once, inside the profile.
 */
class SingleCentroProfileTest extends WP_UnitTestCase {

	private int $image = 0;

	/**
	 * @var array<int, string>
	 */
	private array $theme_directories = [];

	private string $stylesheet = '';

	public function set_up(): void {
		parent::set_up();
		CentroPostType::register();
		$this->image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		$this->stylesheet        = get_stylesheet();
		$this->theme_directories = $GLOBALS['wp_theme_directories'];
		// Classic (default) and block (block-theme) test themes of the WordPress test library.
		$GLOBALS['wp_theme_directories'] = [ WP_CONTENT_DIR . '/themes', DIR_TESTDATA . '/themedir1' ];
		wp_clean_themes_cache();
	}

	public function tear_down(): void {
		if ( WP_Block_Templates_Registry::get_instance()->is_registered( DirectorioShortcode::PROFILE_BLOCK_TEMPLATE ) ) {
			unregister_block_template( DirectorioShortcode::PROFILE_BLOCK_TEMPLATE );
		}
		switch_theme( $this->stylesheet );
		$GLOBALS['wp_theme_directories'] = $this->theme_directories;
		wp_clean_themes_cache();
		parent::tear_down();
	}

	private function with_image( string $post_type ): int {
		$post_id = self::factory()->post->create( [ 'post_type' => $post_type, 'post_status' => 'publish' ] );
		set_post_thumbnail( $post_id, $this->image );

		return $post_id;
	}

	public function test_classic_themes_load_the_plugin_profile_page_template(): void {
		switch_theme( 'default' );
		$this->assertFalse( wp_is_block_theme() );
		$centro = $this->with_image( CentroPostType::POST_TYPE );
		$this->go_to( get_permalink( $centro ) );

		$plugin_template = RADIOEDU_CONECTA_DIR . 'templates/theme-single-centro.php';
		foreach ( [ 'single.php', 'singular.php', 'index.php' ] as $generic ) {
			$this->assertSame( $plugin_template, DirectorioShortcode::use_profile_page_template( get_template_directory() . '/' . $generic ) );
		}
		// A template made for centros (by the theme or a page builder) is kept.
		$own = get_template_directory() . '/single-radioedu_centro.php';
		$this->assertSame( $own, DirectorioShortcode::use_profile_page_template( $own ) );

		$post = $this->with_image( 'post' );
		$this->go_to( get_permalink( $post ) );
		$single = get_template_directory() . '/single.php';
		$this->assertSame( $single, DirectorioShortcode::use_profile_page_template( $single ) );
	}

	public function test_the_classic_template_prints_the_profile_without_the_theme_featured_image(): void {
		switch_theme( 'default' );
		$centro = $this->with_image( CentroPostType::POST_TYPE );
		$this->go_to( get_permalink( $centro ) );

		ob_start();
		require RADIOEDU_CONECTA_DIR . 'templates/theme-single-centro.php';
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'class="radioedu-single-profile"', $html );
		$this->assertStringNotContainsString( 'wp-post-image', $html );
	}

	public function test_block_themes_get_a_profile_template_without_title_meta_or_image(): void {
		switch_theme( 'block-theme' );
		$this->assertTrue( wp_is_block_theme() );

		DirectorioShortcode::register_profile_block_template();

		$template = WP_Block_Templates_Registry::get_instance()->get_registered( DirectorioShortcode::PROFILE_BLOCK_TEMPLATE );
		$this->assertNotNull( $template );
		$this->assertStringContainsString( '<!-- wp:post-content /-->', $template->content );
		foreach ( [ 'wp:post-title', 'wp:post-featured-image', 'wp:post-date', 'wp:post-author' ] as $block ) {
			$this->assertStringNotContainsString( $block, $template->content, $block );
		}

		// It is the template the hierarchy picks for a centro, ahead of the theme's single.html.
		$resolved = resolve_block_template( 'single', [ 'single-radioedu_centro', 'single' ], '' );
		$this->assertNotNull( $resolved );
		$this->assertSame( 'single-radioedu_centro', $resolved->slug );
	}

	public function test_classic_themes_do_not_register_the_block_template(): void {
		switch_theme( 'default' );
		DirectorioShortcode::register_profile_block_template();
		$this->assertFalse( WP_Block_Templates_Registry::get_instance()->is_registered( DirectorioShortcode::PROFILE_BLOCK_TEMPLATE ) );
	}

	public function test_theme_does_not_print_the_featured_image_of_the_profile_centro(): void {
		$centro = $this->with_image( CentroPostType::POST_TYPE );
		$other  = $this->with_image( CentroPostType::POST_TYPE );

		$this->go_to( get_permalink( $centro ) );

		$this->assertTrue( is_singular( CentroPostType::POST_TYPE ) );
		$this->assertFalse( has_post_thumbnail( $centro ) );
		$this->assertSame( '', get_the_post_thumbnail( $centro ) );
		// The profile cover is still available to the plugin template.
		$this->assertStringContainsString( 'canola', (string) get_the_post_thumbnail_url( $centro, 'large' ) );
		// Other posts on the same page (related centros, widgets) keep their images.
		$this->assertTrue( has_post_thumbnail( $other ) );
		$this->assertNotSame( '', get_the_post_thumbnail( $other ) );
	}

	public function test_the_page_head_keeps_the_featured_image(): void {
		$centro = $this->with_image( CentroPostType::POST_TYPE );
		$this->go_to( get_permalink( $centro ) );

		$in_head = null;
		remove_all_actions( 'wp_head' );
		add_action(
			'wp_head',
			static function () use ( $centro, &$in_head ): void {
				$in_head = has_post_thumbnail( $centro );
			}
		);
		do_action( 'wp_head' );

		$this->assertTrue( $in_head, 'Open Graph and SEO plugins read the image in <head>.' );
	}

	public function test_other_pages_are_not_affected(): void {
		$centro = $this->with_image( CentroPostType::POST_TYPE );
		$post   = $this->with_image( 'post' );

		$this->go_to( get_permalink( $post ) );
		$this->assertTrue( has_post_thumbnail( $post ) );
		$this->assertTrue( has_post_thumbnail( $centro ) );

		$this->go_to( home_url( '/' ) );
		$this->assertTrue( has_post_thumbnail( $centro ) );
	}
}
