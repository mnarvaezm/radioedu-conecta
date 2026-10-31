<?php
/**
 * Tests for the registered post meta of radioedu_centro.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\PostTypes\CentroMeta;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Verifies that every write path sanitizes the meta and that no API can read or change it.
 */
class CentroMetaTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		RoleCapabilities::add_capabilities();
		// WP_UnitTestCase unregisters every meta key after each test.
		CentroPostType::register();
		CentroMeta::register();
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function center( int $author = 1 ): int {
		return self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish', 'post_author' => $author ] );
	}

	public function test_every_meta_key_is_registered_for_the_post_type(): void {
		foreach ( array_keys( CentroMeta::get_definitions() ) as $meta_key ) {
			$this->assertTrue( registered_meta_key_exists( 'post', $meta_key, CentroPostType::POST_TYPE ), $meta_key );
		}
	}

	public function test_direct_writes_are_sanitized_by_the_registered_callbacks(): void {
		$center = $this->center();

		update_post_meta( $center, '_radioedu_lat', '123.5' );
		update_post_meta( $center, '_radioedu_lng', 'not-a-number' );
		update_post_meta( $center, '_radioedu_bandera', 'xx-invalid' );
		update_post_meta( $center, '_radioedu_podcast_url', 'javascript:alert(1)' );
		update_post_meta( $center, '_radioedu_web_centro', [ 'https://example.org/' ] );
		update_post_meta( $center, '_radioedu_coordinador_email', 'not an email' );
		update_post_meta( $center, '_radioedu_edades', '<b>12-14</b> años' );
		update_post_meta( $center, '_radioedu_disponible_colaborar', 'yes' );
		update_post_meta( $center, '_radioedu_conecta_demo', 'true' );

		$this->assertSame( '90', get_post_meta( $center, '_radioedu_lat', true ) );
		$this->assertSame( '', get_post_meta( $center, '_radioedu_lng', true ) );
		$this->assertSame( '', get_post_meta( $center, '_radioedu_bandera', true ) );
		$this->assertSame( '', get_post_meta( $center, '_radioedu_podcast_url', true ) );
		$this->assertSame( '', get_post_meta( $center, '_radioedu_web_centro', true ) );
		$this->assertSame( '', get_post_meta( $center, '_radioedu_coordinador_email', true ) );
		$this->assertSame( '12-14 años', get_post_meta( $center, '_radioedu_edades', true ) );
		$this->assertSame( '0', get_post_meta( $center, '_radioedu_disponible_colaborar', true ) );
		$this->assertSame( '', get_post_meta( $center, '_radioedu_conecta_demo', true ) );
	}

	public function test_valid_values_are_kept(): void {
		$center = $this->center();

		update_post_meta( $center, '_radioedu_lat', '-38.8794' );
		update_post_meta( $center, '_radioedu_bandera', 'es-ex' );
		update_post_meta( $center, '_radioedu_podcast_url', 'https://radioedu.educarex.es/programa' );
		update_post_meta( $center, '_radioedu_coordinador_email', 'coordinacion@example.org' );
		update_post_meta( $center, '_radioedu_disponible_colaborar', '1' );

		$this->assertSame( '-38.8794', get_post_meta( $center, '_radioedu_lat', true ) );
		$this->assertSame( 'es-ex', get_post_meta( $center, '_radioedu_bandera', true ) );
		$this->assertSame( 'https://radioedu.educarex.es/programa', get_post_meta( $center, '_radioedu_podcast_url', true ) );
		$this->assertSame( 'coordinacion@example.org', get_post_meta( $center, '_radioedu_coordinador_email', true ) );
		$this->assertSame( '1', get_post_meta( $center, '_radioedu_disponible_colaborar', true ) );
	}

	public function test_logo_id_only_accepts_image_attachments(): void {
		$center   = $this->center();
		$image    = self::factory()->attachment->create_object( [ 'file' => 'logo.png', 'post_mime_type' => 'image/png' ] );
		$document = self::factory()->attachment->create_object( [ 'file' => 'manual.pdf', 'post_mime_type' => 'application/pdf' ] );

		update_post_meta( $center, '_radioedu_logo_id', (string) $image );
		$this->assertSame( $image, (int) get_post_meta( $center, '_radioedu_logo_id', true ) );

		update_post_meta( $center, '_radioedu_logo_id', $document );
		$this->assertSame( 0, (int) get_post_meta( $center, '_radioedu_logo_id', true ) );
	}

	public function test_meta_cannot_be_edited_through_the_meta_capabilities(): void {
		$admin  = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$center = $this->center( $admin );
		wp_set_current_user( $admin );

		foreach ( array_keys( CentroMeta::get_definitions() ) as $meta_key ) {
			$this->assertFalse( current_user_can( 'edit_post_meta', $center, $meta_key ), $meta_key );
			$this->assertFalse( current_user_can( 'add_post_meta', $center, $meta_key ), $meta_key );
		}
	}

	public function test_core_rest_endpoint_neither_exposes_nor_writes_the_meta(): void {
		$author = self::factory()->user->create( [ 'role' => 'author' ] );
		$center = $this->center( $author );
		update_post_meta( $center, '_radioedu_coordinador_email', 'privado@example.org' );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/centros/' . $center ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertStringNotContainsString( 'privado@example.org', (string) wp_json_encode( $response->get_data() ) );

		wp_set_current_user( $author );
		$request = new WP_REST_Request( 'POST', '/wp/v2/centros/' . $center );
		$request->set_body_params( [ 'meta' => [ '_radioedu_coordinador_email' => 'atacante@example.org' ] ] );
		rest_do_request( $request );
		$this->assertSame( 'privado@example.org', get_post_meta( $center, '_radioedu_coordinador_email', true ) );
	}
}
