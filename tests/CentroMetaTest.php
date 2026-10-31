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
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Verifies that every write path sanitizes the meta and that only editors of a centro can read
 * or change it through the REST API.
 */
class CentroMetaTest extends WP_UnitTestCase {

	private const LOCKED_KEYS = [ '_radioedu_logo_url', '_radioedu_cover_url', '_radioedu_conecta_demo' ];

	public function set_up(): void {
		parent::set_up();
		RoleCapabilities::add_capabilities();
		// WP_UnitTestCase unregisters every meta key after each test.
		CentroPostType::register();
		CentroMeta::register();
		// Rebuild the REST server so the posts controller schema includes the meta just registered.
		$GLOBALS['wp_rest_server'] = null;
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );
		$GLOBALS['wp_rest_server'] = null;
		parent::tear_down();
	}

	private function center( int $author = 1 ): int {
		return self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish', 'post_author' => $author ] );
	}

	private function author(): int {
		return self::factory()->user->create( [ 'role' => 'author' ] );
	}

	private function image( int $parent = 0 ): int {
		return self::factory()->attachment->create_object( [ 'file' => 'logo-' . wp_rand() . '.png', 'post_mime_type' => 'image/png', 'post_parent' => $parent ] );
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function save_meta( int $center, array $meta ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/wp/v2/centros/' . $center );
		$request->set_body_params( [ 'meta' => $meta ] );

		return rest_do_request( $request );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_centro( int $center, string $context = 'view' ): array {
		$request = new WP_REST_Request( 'GET', '/wp/v2/centros/' . $center );
		$request->set_param( 'context', $context );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		return $response->get_data();
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
		update_post_meta( $center, '_radioedu_disponible_colaborar', '0' );

		$this->assertSame( '-38.8794', get_post_meta( $center, '_radioedu_lat', true ) );
		$this->assertSame( 'es-ex', get_post_meta( $center, '_radioedu_bandera', true ) );
		$this->assertSame( 'https://radioedu.educarex.es/programa', get_post_meta( $center, '_radioedu_podcast_url', true ) );
		$this->assertSame( 'coordinacion@example.org', get_post_meta( $center, '_radioedu_coordinador_email', true ) );
		$this->assertSame( '0', get_post_meta( $center, '_radioedu_disponible_colaborar', true ) );
	}

	public function test_availability_defaults_to_available(): void {
		$center = $this->center();
		$this->assertSame( '1', get_post_meta( $center, '_radioedu_disponible_colaborar', true ) );

		update_post_meta( $center, '_radioedu_disponible_colaborar', '' );
		$this->assertSame( '1', get_post_meta( $center, '_radioedu_disponible_colaborar', true ) );
	}

	public function test_logo_id_only_accepts_image_attachments(): void {
		$center   = $this->center();
		$image    = $this->image();
		$document = self::factory()->attachment->create_object( [ 'file' => 'manual.pdf', 'post_mime_type' => 'application/pdf' ] );

		update_post_meta( $center, '_radioedu_logo_id', (string) $image );
		$this->assertSame( $image, (int) get_post_meta( $center, '_radioedu_logo_id', true ) );

		update_post_meta( $center, '_radioedu_logo_id', $document );
		$this->assertSame( 0, (int) get_post_meta( $center, '_radioedu_logo_id', true ) );
	}

	public function test_editable_meta_follows_the_edit_post_capability(): void {
		$admin  = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$owner  = $this->author();
		$other  = $this->author();
		$center = $this->center( $owner );

		foreach ( CentroMeta::get_definitions() as $meta_key => $definition ) {
			$locked = ! $definition['editable'];
			$this->assertSame( ! $locked, user_can( $owner, 'edit_post_meta', $center, $meta_key ), $meta_key );
			$this->assertSame( ! $locked, user_can( $admin, 'add_post_meta', $center, $meta_key ), $meta_key );
			$this->assertFalse( user_can( $other, 'edit_post_meta', $center, $meta_key ), $meta_key );
		}
		foreach ( self::LOCKED_KEYS as $meta_key ) {
			$this->assertFalse( CentroMeta::get_definitions()[ $meta_key ]['editable'], $meta_key );
		}
	}

	public function test_public_rest_responses_never_include_the_meta(): void {
		$center = $this->center( $this->author() );
		update_post_meta( $center, '_radioedu_coordinador_email', 'privado@example.org' );
		update_post_meta( $center, '_radioedu_coordinador_nombre', 'Nombre Privado' );

		$data = $this->get_centro( $center );

		$this->assertSame( [], array_intersect_key( (array) $data['meta'], CentroMeta::get_definitions() ) );
		$this->assertStringNotContainsString( 'privado@example.org', (string) wp_json_encode( $data ) );
		$this->assertStringNotContainsString( 'Nombre Privado', (string) wp_json_encode( $data ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/centros/' . $center );
		$request->set_param( 'context', 'edit' );
		$this->assertSame( 401, rest_do_request( $request )->get_status() );
	}

	public function test_edit_context_shows_the_meta_only_for_centros_the_user_can_edit(): void {
		$owner     = $this->author();
		$other     = $this->author();
		$center    = $this->center( $owner );
		$neighbour = $this->center( $other );
		update_post_meta( $center, '_radioedu_coordinador_email', 'owner@example.org' );
		update_post_meta( $neighbour, '_radioedu_coordinador_email', 'other@example.org' );
		update_post_meta( $center, '_radioedu_logo_url', 'https://example.org/demo-logo.png' );
		wp_set_current_user( $owner );

		$own = $this->get_centro( $center, 'edit' );
		$this->assertSame( 'owner@example.org', $own['meta']['_radioedu_coordinador_email'] );
		foreach ( self::LOCKED_KEYS as $meta_key ) {
			$this->assertArrayNotHasKey( $meta_key, $own['meta'], $meta_key );
		}

		// Any coordinator may list centros in the edit context; other centros keep their data private.
		$request = new WP_REST_Request( 'GET', '/wp/v2/centros' );
		$request->set_param( 'context', 'edit' );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$json = (string) wp_json_encode( $response->get_data() );
		$this->assertStringContainsString( 'owner@example.org', $json );
		$this->assertStringNotContainsString( 'other@example.org', $json );
	}

	public function test_rest_saves_and_sanitizes_the_meta_of_the_owner(): void {
		$owner  = $this->author();
		$center = $this->center( $owner );
		$image  = $this->image();
		wp_set_current_user( $owner );

		$response = $this->save_meta(
			$center,
			[
				'_radioedu_logo_id'              => $image,
				'_radioedu_lat'                  => '123',
				'_radioedu_lng'                  => '-6.3708',
				'_radioedu_bandera'              => 'xx-invalid',
				'_radioedu_podcast_url'          => 'javascript:alert(1)',
				'_radioedu_coordinador_email'    => 'nuevo@example.org',
				'_radioedu_disponible_colaborar' => '0',
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $image, (int) get_post_meta( $center, '_radioedu_logo_id', true ) );
		$this->assertSame( '90', get_post_meta( $center, '_radioedu_lat', true ) );
		$this->assertSame( '-6.3708', get_post_meta( $center, '_radioedu_lng', true ) );
		$this->assertSame( '', get_post_meta( $center, '_radioedu_bandera', true ) );
		$this->assertSame( '', get_post_meta( $center, '_radioedu_podcast_url', true ) );
		$this->assertSame( 'nuevo@example.org', get_post_meta( $center, '_radioedu_coordinador_email', true ) );
		$this->assertSame( '0', get_post_meta( $center, '_radioedu_disponible_colaborar', true ) );
	}

	public function test_saving_every_key_as_the_editor_does_keeps_the_defaults(): void {
		$owner  = $this->author();
		$center = $this->center( $owner );
		wp_set_current_user( $owner );

		// The block editor re-sends the whole meta object it received.
		$meta                                = $this->get_centro( $center, 'edit' )['meta'];
		$meta['_radioedu_coordinador_email'] = 'coordinacion@example.org';

		$this->assertSame( 200, $this->save_meta( $center, $meta )->get_status() );
		$this->assertSame( '1', get_post_meta( $center, '_radioedu_disponible_colaborar', true ) );
		$this->assertSame( 'coordinacion@example.org', get_post_meta( $center, '_radioedu_coordinador_email', true ) );
	}

	public function test_rest_rejects_meta_from_users_who_cannot_edit_the_centro(): void {
		$center = $this->center( $this->author() );
		update_post_meta( $center, '_radioedu_coordinador_email', 'privado@example.org' );
		wp_set_current_user( $this->author() );

		$response = $this->save_meta( $center, [ '_radioedu_coordinador_email' => 'atacante@example.org' ] );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'privado@example.org', get_post_meta( $center, '_radioedu_coordinador_email', true ) );
	}

	public function test_rest_ignores_the_locked_keys(): void {
		$admin  = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$center = $this->center( $admin );
		wp_set_current_user( $admin );

		$response = $this->save_meta(
			$center,
			[
				'_radioedu_conecta_demo' => '1',
				'_radioedu_logo_url'     => 'https://example.org/other.png',
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '', get_post_meta( $center, '_radioedu_conecta_demo', true ) );
		$this->assertSame( '', get_post_meta( $center, '_radioedu_logo_url', true ) );
	}

	public function test_rest_logo_must_be_an_image_the_user_can_read(): void {
		$admin    = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$private  = self::factory()->post->create( [ 'post_status' => 'private', 'post_author' => $admin ] );
		$hidden   = $this->image( $private );
		$document = self::factory()->attachment->create_object( [ 'file' => 'manual.pdf', 'post_mime_type' => 'application/pdf' ] );
		$owner    = $this->author();
		$center   = $this->center( $owner );
		wp_set_current_user( $owner );

		foreach ( [ $document, $hidden ] as $invalid ) {
			$response = $this->save_meta( $center, [ '_radioedu_logo_id' => $invalid ] );
			$this->assertSame( 400, $response->get_status() );
			$this->assertSame( 'radioedu_conecta_invalid_logo', $response->as_error()->get_error_code() );
			$this->assertSame( 0, (int) get_post_meta( $center, '_radioedu_logo_id', true ) );
		}
	}

	public function test_an_unchanged_logo_set_by_someone_else_does_not_block_saving(): void {
		$admin   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$private = self::factory()->post->create( [ 'post_status' => 'private', 'post_author' => $admin ] );
		$hidden  = $this->image( $private );
		$owner   = $this->author();
		$center  = $this->center( $owner );
		update_post_meta( $center, '_radioedu_logo_id', $hidden );
		wp_set_current_user( $owner );

		$response = $this->save_meta(
			$center,
			[
				'_radioedu_logo_id' => $hidden,
				'_radioedu_edades'  => '12–14 años',
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $hidden, (int) get_post_meta( $center, '_radioedu_logo_id', true ) );
		$this->assertSame( '12–14 años', get_post_meta( $center, '_radioedu_edades', true ) );
	}
}
