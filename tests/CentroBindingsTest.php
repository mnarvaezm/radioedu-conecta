<?php
/**
 * Tests for the Block Bindings source of a station and the pattern that lists stations.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Frontend\CentroBindings;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;
use WP_Block;
use WP_Block_Patterns_Registry;
use WP_UnitTestCase;

/**
 * Blocks bound to the source show the public data of the station, escaped, and nothing when empty.
 */
class CentroBindingsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		CentroPostType::register();
		TaxonomiesRegistrar::register_all();
	}

	/**
	 * Creates a published station.
	 *
	 * @param array<string, mixed> $args Post arguments.
	 * @return int ID.
	 */
	private function centro( array $args = [] ): int {
		return self::factory()->post->create(
			$args + [
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
			]
		);
	}

	/**
	 * Renders a block bound to the source, inside the context of a station.
	 *
	 * @param string $markup  Block markup.
	 * @param int    $post_id Station ID.
	 * @return string Rendered block.
	 */
	private function render( string $markup, int $post_id ): string {
		$block = new WP_Block(
			parse_blocks( $markup )[0],
			[
				'postId'   => $post_id,
				'postType' => CentroPostType::POST_TYPE,
			]
		);

		return $block->render();
	}

	/**
	 * Binding metadata for the given attributes.
	 *
	 * @param array<string, string> $keys Attribute => source key.
	 * @return string JSON block attributes.
	 */
	private function bound( array $keys ): string {
		$bindings = [];
		foreach ( $keys as $attribute => $key ) {
			$bindings[ $attribute ] = [
				'source' => CentroBindings::SOURCE,
				'args'   => [ 'key' => $key ],
			];
		}

		return (string) wp_json_encode( [ 'metadata' => [ 'bindings' => $bindings ] ] );
	}

	public function test_the_source_is_registered(): void {
		$this->assertNotNull( get_block_bindings_source( CentroBindings::SOURCE ) );
	}

	public function test_values_are_the_public_data_of_the_station(): void {
		$center = $this->centro( [ 'post_title' => "Radio d'Esplugues" ] );
		update_post_meta( $center, '_radioedu_edades', '12-14 años' );
		update_post_meta( $center, '_radioedu_podcast_url', 'https://example.org/podcast' );
		update_post_meta( $center, '_radioedu_bandera', 'pt' );
		update_post_meta( $center, '_radioedu_disponible_colaborar', '0' );
		wp_set_object_terms( $center, [ 'Inglés', 'Francés' ], TaxonomiesRegistrar::TAX_IDIOMA );
		wp_set_object_terms( $center, [ 'Ciencia & Arte' ], TaxonomiesRegistrar::TAX_TEMATICA );

		$this->assertSame( "Radio d\u{2019}Esplugues", CentroBindings::value( $center, 'name' ) );
		$this->assertSame( '12-14 años', CentroBindings::value( $center, 'ages' ) );
		$this->assertSame( 'https://example.org/podcast', CentroBindings::value( $center, 'podcast_url' ) );
		$this->assertSame( '🇵🇹', CentroBindings::value( $center, 'flag' ) );
		$this->assertSame( 'No disponible actualmente', CentroBindings::value( $center, 'availability' ) );
		$this->assertEqualSets( [ 'Inglés', 'Francés' ], explode( ', ', CentroBindings::value( $center, 'languages' ) ) );
		$this->assertSame( 'Ciencia & Arte', CentroBindings::value( $center, 'tags' ) );
		$this->assertSame( get_permalink( $center ), CentroBindings::value( $center, 'profile_url' ) );
		$this->assertSame( '', CentroBindings::value( $center, 'website_url' ) );
	}

	public function test_coordinator_data_and_unknown_keys_are_not_given(): void {
		$center = $this->centro();
		update_post_meta( $center, '_radioedu_coordinador_email', 'coordinator@example.org' );
		update_post_meta( $center, '_radioedu_coordinador_nombre', 'Private Coordinator' );

		$html = $this->render( '<!-- wp:paragraph ' . $this->bound( [ 'content' => 'coordinador_email' ] ) . ' --><p>Saved text</p><!-- /wp:paragraph -->', $center );

		// The key is not one of the source, so the paragraph keeps what was saved.
		$this->assertMatchesRegularExpression( '#^<p[^>]*>Saved text</p>$#', trim( $html ) );
		foreach ( CentroBindings::KEYS as $key ) {
			$this->assertStringNotContainsString( 'coordinator', CentroBindings::value( $center, $key ) );
			$this->assertStringNotContainsString( 'Coordinator', CentroBindings::value( $center, $key ) );
		}
	}

	public function test_text_is_escaped_once_in_rich_text_and_in_attributes(): void {
		$center = $this->centro( [ 'post_title' => 'Radio <b>Bold</b> & Co' ] );
		update_post_meta( $center, '_radioedu_logo_url', 'https://example.org/logo.png' );

		$paragraph = $this->render( '<!-- wp:paragraph ' . $this->bound( [ 'content' => 'name' ] ) . ' --><p></p><!-- /wp:paragraph -->', $center );
		$image     = $this->render(
			'<!-- wp:image ' . $this->bound(
				[
					'url' => 'logo_url',
					'alt' => 'name',
				]
			) . ' --><figure class="wp-block-image"><img alt=""/></figure><!-- /wp:image -->',
			$center
		);

		$this->assertMatchesRegularExpression( '#<p[^>]*>Radio &lt;b&gt;Bold&lt;/b&gt; &amp; Co</p>#', $paragraph );
		$this->assertStringContainsString( 'src="https://example.org/logo.png"', $image );
		$this->assertStringContainsString( 'alt="Radio &lt;b&gt;Bold&lt;/b&gt; &amp; Co"', $image );
	}

	public function test_links_keep_only_http_and_https(): void {
		$center = $this->centro();
		$button = '<!-- wp:button ' . $this->bound( [ 'url' => 'podcast_url' ] ) . ' --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Escuchar</a></div><!-- /wp:button -->';

		update_post_meta( $center, '_radioedu_podcast_url', 'https://example.org/podcast' );
		$this->assertStringContainsString( 'href="https://example.org/podcast"', $this->render( $button, $center ) );

		// A value stored before the meta was sanitized, written straight to the table.
		global $wpdb;
		$wpdb->update(
			$wpdb->postmeta,
			[ 'meta_value' => 'javascript:alert(1)' ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Test fixture.
			[
				'post_id'  => $center,
				'meta_key' => '_radioedu_podcast_url', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Test fixture.
			]
		);
		wp_cache_delete( $center, 'post_meta' );
		$this->assertSame( 'javascript:alert(1)', get_post_meta( $center, '_radioedu_podcast_url', true ) );
		$this->assertSame( '', $this->render( $button, $center ) );

		// A text key does not fill a link.
		$ages = '<!-- wp:button ' . $this->bound( [ 'url' => 'ages' ] ) . ' --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.org/">Edades</a></div><!-- /wp:button -->';
		update_post_meta( $center, '_radioedu_edades', 'javascript:alert(1)' );
		$this->assertStringContainsString( 'href="https://example.org/"', $this->render( $ages, $center ) );
	}

	public function test_blocks_with_an_empty_value_are_not_printed(): void {
		$center    = $this->centro();
		$paragraph = '<!-- wp:paragraph ' . $this->bound( [ 'content' => 'ages' ] ) . ' --><p>Edades</p><!-- /wp:paragraph -->';

		$this->assertSame( '', $this->render( $paragraph, $center ) );

		update_post_meta( $center, '_radioedu_edades', '8-10 años' );
		$this->assertMatchesRegularExpression( '#<p[^>]*>8-10 años</p>#', $this->render( $paragraph, $center ) );
	}

	public function test_stations_the_visitor_cannot_see_give_nothing(): void {
		$draft     = $this->centro( [ 'post_status' => 'draft' ] );
		$protected = $this->centro( [ 'post_password' => 'secret' ] );
		$post      = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		foreach ( [ $draft, $protected, $post ] as $id ) {
			update_post_meta( $id, '_radioedu_edades', '12-14 años' );
		}
		wp_set_current_user( 0 );

		foreach ( [ $draft, $protected, $post ] as $id ) {
			$this->assertSame( '', CentroBindings::value( $id, 'ages' ), "Post $id" );
			$this->assertSame( '', CentroBindings::value( $id, 'name' ), "Post $id" );
		}

		// The author previewing a draft sees it.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( '12-14 años', CentroBindings::value( $draft, 'ages' ) );
	}

	public function test_the_pattern_lists_the_stations_with_their_data(): void {
		$with    = $this->centro( [ 'post_title' => 'A Radio con podcast' ] );
		$without = $this->centro( [ 'post_title' => 'B Radio sin podcast' ] );
		update_post_meta( $with, '_radioedu_podcast_url', 'https://example.org/podcast' );
		update_post_meta( $with, '_radioedu_edades', '12-14 años' );
		wp_set_object_terms( $without, [ 'Mérida' ], TaxonomiesRegistrar::TAX_LOCALIZACION );

		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( CentroBindings::PATTERN );
		$this->assertIsArray( $pattern );
		$html = do_blocks( $pattern['content'] );

		$this->assertStringContainsString( 'A Radio con podcast', $html );
		$this->assertStringContainsString( 'B Radio sin podcast', $html );
		$this->assertMatchesRegularExpression( '#<p[^>]*>12-14 años</p>#', $html );
		$this->assertMatchesRegularExpression( '#<p[^>]*>Mérida</p>#', $html );
		// Only the station with a podcast has the button, and the other one no empty row of buttons.
		$this->assertSame( 1, substr_count( $html, 'class="wp-block-button"' ) );
		$this->assertSame( 1, substr_count( $html, 'class="wp-block-buttons' ) );
		$this->assertStringContainsString( 'href="https://example.org/podcast"', $html );
		$this->assertDoesNotMatchRegularExpression( '#<p[^>]*></p>#', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}
}
