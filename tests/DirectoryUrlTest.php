<?php
/**
 * The profiles link back to the page that really shows the directory.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Frontend\DirectorioShortcode;
use WP_UnitTestCase;

class DirectoryUrlTest extends WP_UnitTestCase {

	private function page( string $slug, string $content ): int {
		return self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_content' => $content ] );
	}

	public function test_the_page_that_shows_the_directory_becomes_the_back_link(): void {
		$page = $this->page( 'radios-escolares', '<!-- wp:radioedu-conecta/directorio /-->' );
		$this->assertSame( home_url( '/' ), DirectorioShortcode::get_directory_url() );

		$this->go_to( get_permalink( $page ) );
		DirectorioShortcode::render_shortcode();

		$this->assertSame( $page, (int) get_option( DirectorioShortcode::DIRECTORY_PAGE_OPTION ) );
		$this->assertSame( get_permalink( $page ), DirectorioShortcode::get_directory_url() );
	}

	public function test_a_page_that_no_longer_shows_the_directory_is_not_used(): void {
		$old      = $this->page( 'old-directory', '[radioedu_directorio]' );
		$fallback = $this->page( 'traspasando-fronteras', '[radioedu_directorio]' );
		update_option( DirectorioShortcode::DIRECTORY_PAGE_OPTION, $old );
		$this->assertSame( get_permalink( $old ), DirectorioShortcode::get_directory_url() );

		wp_update_post( [ 'ID' => $old, 'post_content' => 'Something else' ] );
		$this->assertSame( get_permalink( $fallback ), DirectorioShortcode::get_directory_url() );

		// A valid recorded page is kept when the directory also renders somewhere else.
		update_option( DirectorioShortcode::DIRECTORY_PAGE_OPTION, $fallback );
		$this->go_to( get_permalink( $this->page( 'second', '[radioedu_directorio]' ) ) );
		DirectorioShortcode::render_shortcode();
		$this->assertSame( $fallback, (int) get_option( DirectorioShortcode::DIRECTORY_PAGE_OPTION ) );
	}

	public function test_the_back_link_can_be_filtered(): void {
		$filter = static fn() => 'https://example.org/radios/';
		add_filter( 'radioedu_conecta_directory_url', $filter );
		try {
			$this->assertSame( 'https://example.org/radios/', DirectorioShortcode::get_directory_url() );
		} finally {
			remove_filter( 'radioedu_conecta_directory_url', $filter );
		}
	}
}
