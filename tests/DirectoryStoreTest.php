<?php
/**
 * Tests for the directory rendered on the server with the Interactivity API.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Frontend\DirectorioShortcode;
use RadioEdu\Conecta\Frontend\DirectoryStore;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;
use WP_HTML_Tag_Processor;
use WP_UnitTestCase;

/**
 * The first page of results is rendered on the server, and the search in the URL is checked.
 */
class DirectoryStoreTest extends WP_UnitTestCase {

	private const CARD_ITEM = 'data-wp-each-child="radioedu-conecta/directorio::state.centros"';

	public function set_up(): void {
		parent::set_up();
		CentroPostType::register();
		TaxonomiesRegistrar::register_all();
		// The Interactivity API merges state for the whole request: start each test from none.
		$GLOBALS['wp_interactivity'] = new \WP_Interactivity_API();
		( new \ReflectionProperty( DirectoryStore::class, 'prepared' ) )->setValue( null, false );
	}

	public function tear_down(): void {
		foreach ( [ 'idioma', 'tematica', 'buscar', 'orden', 'pagina' ] as $param ) {
			unset( $_GET[ $param ] );
		}
		parent::tear_down();
	}

	/**
	 * Creates published stations.
	 *
	 * @param int                  $count How many.
	 * @param array<string, mixed> $args  Post arguments.
	 * @return array<int, int> IDs.
	 */
	private function centros( int $count, array $args = [] ): array {
		return self::factory()->post->create_many(
			$count,
			$args + [
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
			]
		);
	}

	/**
	 * Moves a tag processor to the element with an ID.
	 *
	 * @param WP_HTML_Tag_Processor $tags Processor.
	 * @param string                $id   Element ID.
	 * @return bool Whether it was found.
	 */
	private function seek_id( WP_HTML_Tag_Processor $tags, string $id ): bool {
		while ( $tags->next_tag() ) {
			if ( $id === $tags->get_attribute( 'id' ) ) {
				return true;
			}
		}
		return false;
	}

	public function test_the_first_page_of_cards_is_rendered_on_the_server(): void {
		$this->centros( 30 );

		$html = DirectorioShortcode::render_shortcode();

		$this->assertSame( DirectoryStore::PER_PAGE, substr_count( $html, self::CARD_ITEM ) );
		$this->assertStringContainsString( '<span id="radioedu-count-number" data-wp-text="state.totalLabel">30</span>', $html );
		$tags = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $this->seek_id( $tags, 'radioedu-btn-load-more' ) );
		$this->assertStringContainsString( DirectoryStore::PAGE_PARAM . '=2', (string) $tags->get_attribute( 'href' ) );
		$this->assertNull( $tags->get_attribute( 'hidden' ) );
		$state = wp_interactivity_state( DirectoryStore::NAMESPACE );
		$this->assertCount( DirectoryStore::PER_PAGE, $state['centros'] );
		$this->assertSame( 2, $state['totalPages'] );
	}

	public function test_station_names_are_written_as_text(): void {
		$this->centros( 1, [ 'post_title' => 'Radio <b>Bold</b> & Co' ] );

		$html = DirectorioShortcode::render_shortcode();

		$this->assertStringNotContainsString( '<b>Bold</b>', $html );
		$this->assertMatchesRegularExpression( '#<h3 class="radioedu-card-title" data-wp-text="context\.centro\.nombre">[^<]*Bold[^<]*&amp; Co</h3>#', $html );
	}

	public function test_without_javascript_the_parts_that_need_it_start_hidden(): void {
		$available = $this->centros( 1, [ 'post_title' => 'A available' ] )[0];
		$closed    = $this->centros( 1, [ 'post_title' => 'B closed' ] )[0];
		update_post_meta( $closed, '_radioedu_disponible_colaborar', '0' );

		$tags = new WP_HTML_Tag_Processor( DirectorioShortcode::render_shortcode() );
		$this->assertTrue( $this->seek_id( $tags, 'radioedu-btn-toggle-map' ) );
		$this->assertTrue( $tags->get_attribute( 'hidden' ) );

		$buttons = [];
		while ( $tags->next_tag( 'article' ) ) {
			if ( null === $tags->get_attribute( 'data-wp-each-child' ) ) {
				continue;
			}
			$id = (int) $tags->get_attribute( 'data-id' );
			while ( $tags->next_tag( 'button' ) ) {
				if ( $tags->has_class( 'radioedu-btn-match' ) ) {
					$buttons[ $id ] = [
						'hidden'   => true === $tags->get_attribute( 'hidden' ),
						'disabled' => true === $tags->get_attribute( 'disabled' ),
					];
					break;
				}
			}
		}

		// The proposal needs JavaScript; the "No disponible" notice does not.
		$this->assertSame(
			[
				'hidden'   => true,
				'disabled' => false,
			],
			$buttons[ $available ]
		);
		$this->assertSame(
			[
				'hidden'   => false,
				'disabled' => true,
			],
			$buttons[ $closed ]
		);
	}

	public function test_the_search_in_the_url_is_rendered_and_checked(): void {
		$english = self::factory()->term->create(
			[
				'taxonomy' => TaxonomiesRegistrar::TAX_IDIOMA,
				'name'     => 'Inglés',
				'slug'     => 'ingles',
			]
		);
		$match   = $this->centros( 1, [ 'post_title' => 'Radio Uno' ] )[0];
		$this->centros( 1, [ 'post_title' => 'Radio Dos' ] );
		wp_set_object_terms( $match, [ $english ], TaxonomiesRegistrar::TAX_IDIOMA );

		$_GET['idioma'] = 'ingles';
		$html           = DirectorioShortcode::render_shortcode();
		$this->assertSame( 1, substr_count( $html, self::CARD_ITEM ) );
		$this->assertStringContainsString( 'Radio Uno', $html );
		$this->assertMatchesRegularExpression( '#<option value="ingles"\s+selected=\'selected\'#', $html );

		// A slug that is not a term of the filter is ignored rather than leaving no results.
		$GLOBALS['wp_interactivity'] = new \WP_Interactivity_API();
		( new \ReflectionProperty( DirectoryStore::class, 'prepared' ) )->setValue( null, false );
		$_GET['idioma'] = 'no-existe';
		$this->assertSame( 2, substr_count( DirectorioShortcode::render_shortcode(), self::CARD_ITEM ) );
	}

	public function test_url_values_outside_the_whitelists_are_dropped(): void {
		$_GET['idioma']   = 'no-existe';
		$_GET['tematica'] = [ 'array' ];
		$_GET['buscar']   = '<script>x</script>' . str_repeat( 'a', 300 );
		$_GET['orden']    = 'random';
		$_GET['pagina']   = '-7';

		$search = DirectoryStore::requested_search(
			[
				'idioma'   => [ 'ingles' ],
				'tematica' => [ 'ciencia' ],
			]
		);

		$this->assertSame( [], $search['filters'] );
		$this->assertStringNotContainsString( '<script>', $search['search'] );
		$this->assertLessThanOrEqual( 200, mb_strlen( $search['search'] ) );
		$this->assertSame( 'afines', $search['orden'] );
		$this->assertSame( 1, $search['page'] );

		$_GET['idioma'] = 'ingles';
		$_GET['orden']  = 'alfa';
		$_GET['pagina'] = '3';
		$search         = DirectoryStore::requested_search( [ 'idioma' => [ 'ingles' ] ] );
		$this->assertSame( [ 'idioma' => 'ingles' ], $search['filters'] );
		$this->assertSame( 'alfa', $search['orden'] );
		$this->assertSame( 3, $search['page'] );
	}

	public function test_page_links_keep_the_arguments_of_the_page_url(): void {
		$search = [
			'filters' => [ 'idioma' => 'ingles' ],
			'search'  => 'radio & mar',
			'orden'   => 'recientes',
			'page'    => 1,
		];

		$url = DirectoryStore::page_url( 'http://example.org/?page_id=5&pagina=9', $search, 2 );
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $args );

		$this->assertSame(
			[
				'page_id' => '5',
				'idioma'  => 'ingles',
				'buscar'  => 'radio & mar',
				'orden'   => 'recientes',
				'pagina'  => '2',
			],
			$args
		);
		// A GET form drops the query of its action, so it repeats the page arguments as fields.
		$this->assertSame( [ 'page_id' => '5' ], DirectoryStore::base_query_args( 'http://example.org/?page_id=5&buscar=x' ) );
	}

	public function test_the_state_holds_no_coordinator_data(): void {
		$center = $this->centros( 1 )[0];
		update_post_meta( $center, '_radioedu_coordinador_email', 'coordinator@example.org' );
		update_post_meta( $center, '_radioedu_coordinador_nombre', 'Private Coordinator' );

		$html  = DirectorioShortcode::render_shortcode();
		$state = (string) wp_json_encode( wp_interactivity_state( DirectoryStore::NAMESPACE ) );

		foreach ( [ 'coordinator@example.org', 'Private Coordinator' ] as $private ) {
			$this->assertStringNotContainsString( $private, $html );
			$this->assertStringNotContainsString( $private, $state );
		}
	}

	public function test_the_profile_description_cannot_use_the_plugin_store(): void {
		$description = '<fieldset class="trap" data-wp-interactive="radioedu-conecta/directorio" data-wp-on--mouseover="actions.submitProposal">'
			. '<p data-wp-text="state.drawer.receptorLabel">Written by the coordinator</p></fieldset>'
			. '<figure data-wp-interactive="core/image" data-wp-context="{}"><img src="https://example.org/a.png" alt=""></figure>';
		$center      = $this->centros( 1, [ 'post_title' => 'Radio Perfil' ] )[0];
		$this->go_to( get_permalink( $center ) );

		$html = '';
		while ( have_posts() ) {
			the_post();
			$html = DirectorioShortcode::render_single_centro_profile( $description );
		}

		$tags = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $tags->next_tag( [ 'class_name' => 'trap' ] ) );
		// Without the region of a plugin store, the directive left inside names no store.
		$this->assertNull( $tags->get_attribute( 'data-wp-interactive' ) );
		$this->assertSame( 'actions.submitProposal', $tags->get_attribute( 'data-wp-on--mouseover' ) );
		// The server does not run the directives the description keeps, and core blocks keep theirs.
		$this->assertStringContainsString( '<p data-wp-text="state.drawer.receptorLabel">Written by the coordinator</p>', $html );
		$this->assertStringContainsString( '<figure data-wp-interactive="core/image" data-wp-context="{}">', $html );
		$this->assertStringContainsString( 'value="Radio Perfil"', $html );
		$this->assertStringNotContainsString( 'radioedu-conecta-description-', $html );
	}

	public function test_directives_naming_a_plugin_store_are_removed_in_every_form(): void {
		$html = '<div data-wp-interactive=\'{"namespace":"radioedu-conecta/directorio"}\'>'
			. '<button data-wp-on--click="RadioEdu-Conecta/directorio::actions.openDrawer" data-wp-context="radioedu-conecta/directorio::{&quot;centro&quot;:{&quot;id&quot;:5}}">A</button>'
			. '<span data-wp-text="core/router::state.url" data-wp-bind--title="radioedu-conecta/otro::state.x">B</span></div>';

		$clean = DirectoryStore::remove_store_directives( $html );

		$this->assertStringNotContainsStringIgnoringCase( 'radioedu', $clean );
		$this->assertStringContainsString( 'data-wp-text="core/router::state.url"', $clean );
		$this->assertSame( 'Plain <b>text</b>', DirectoryStore::remove_store_directives( 'Plain <b>text</b>' ) );
	}

	public function test_the_module_is_registered_from_the_build(): void {
		// Core registers @wordpress/interactivity on wp_default_scripts, which fires when wp_scripts()
		// first runs; a module whose dependency is missing is not printed.
		wp_scripts();
		DirectoryStore::register_module();
		wp_enqueue_script_module( DirectoryStore::MODULE_ID );

		ob_start();
		wp_script_modules()->print_enqueued_script_modules();
		$printed = (string) ob_get_clean();

		$this->assertStringContainsString( 'build/directorio-view.js', $printed );
	}
}
