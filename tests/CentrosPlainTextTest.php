<?php
/**
 * Tests for the text fields of the centros endpoint.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Rest\CentrosController;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * The directory writes names and places as text, so the endpoint returns them without entities.
 */
class CentrosPlainTextTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		CentroPostType::register();
		TaxonomiesRegistrar::register_all();
	}

	public function test_names_places_and_tags_come_without_html_entities(): void {
		$center = self::factory()->post->create(
			[
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => "Radio d'Esplugues & Co",
			]
		);
		wp_set_object_terms( $center, [ 'Cáceres & Badajoz' ], TaxonomiesRegistrar::TAX_LOCALIZACION );
		wp_set_object_terms( $center, [ 'Ciencia & Arte' ], TaxonomiesRegistrar::TAX_TEMATICA );
		// The stored title and terms carry entities, as they do for users without unfiltered_html.
		$this->assertStringContainsString( '&#8217;', get_the_title( $center ) );

		$data   = ( new CentrosController() )->get_items( new WP_REST_Request( 'GET', '/radioedu-conecta/v1/centros' ) )->get_data();
		$centro = current( wp_list_filter( $data['centros'], [ 'id' => $center ] ) );

		$this->assertSame( "Radio d\u{2019}Esplugues & Co", $centro['nombre'] );
		$this->assertSame( 'Cáceres & Badajoz', $centro['ubicacion'] );
		$this->assertSame( 'Ciencia & Arte', $centro['tags'][0]['name'] );
	}
}
