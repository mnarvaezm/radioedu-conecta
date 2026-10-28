<?php
/**
 * Tests for REST API permissions, IDOR protection, and input validation.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Rest\CentrosController;
use RadioEdu\Conecta\Rest\ColaboracionesController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Verifies REST API security controls, capability checks, and IDOR mitigations.
 */
class RestSecurityAndPermissionsTest extends WP_UnitTestCase {

	/**
	 * Set up test environment before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		CentroPostType::register_cpt();
		RoleCapabilities::add_capabilities();
		ColaboracionesTable::create_table();
	}

	/**
	 * Public directory endpoint (/centros) must allow anonymous read access.
	 */
	public function test_centros_endpoint_is_publicly_readable(): void {
		wp_set_current_user( 0 );
		$controller = new CentrosController();
		$request    = new WP_REST_Request( 'GET', '/radioedu-conecta/v1/centros' );

		$this->assertTrue( $controller->get_items_permissions_check( $request ) );
	}

	/**
	 * Unauthenticated users and subscribers without capability must be rejected on /colaboraciones.
	 */
	public function test_colaboraciones_rejects_unauthorized_users(): void {
		$controller = new ColaboracionesController();
		$request    = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/colaboraciones' );

		// Anonymous visitor.
		wp_set_current_user( 0 );
		$this->assertFalse( $controller->create_item_permissions_check( $request ) );
		$this->assertFalse( $controller->get_items_permissions_check( $request ) );

		// Subscriber without custom capabilities.
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse( $controller->create_item_permissions_check( $request ) );
		$this->assertFalse( $controller->get_items_permissions_check( $request ) );
	}

	/**
	 * Authenticated authors (coordinators) have permission to access /colaboraciones.
	 */
	public function test_colaboraciones_allows_coordinators(): void {
		$author_id = self::factory()->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $author_id );

		$controller = new ColaboracionesController();
		$request    = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/colaboraciones' );

		$this->assertTrue( $controller->create_item_permissions_check( $request ) );
		$this->assertTrue( $controller->get_items_permissions_check( $request ) );
	}

	/**
	 * Prevent self-proposals (sending a match proposal to the same school).
	 */
	public function test_create_collaboration_rejects_same_center(): void {
		$author_id = self::factory()->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $author_id );

		$center_id = self::factory()->post->create(
			[
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $author_id,
			]
		);

		$controller = new ColaboracionesController();
		$request    = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/colaboraciones' );
		$request->set_param( 'centro_emisor_id', $center_id );
		$request->set_param( 'centro_receptor_id', $center_id );
		$request->set_param( 'idea_proyecto', 'Proyecto conjunto de prueba' );

		$response = $controller->create_item( $request );
		$this->assertWPError( $response );
		$this->assertSame( 'radioedu_same_center', $response->get_error_code() );
	}

	/**
	 * Prevent IDOR: Coordinator A cannot send a proposal impersonating Coordinator B's school.
	 */
	public function test_create_collaboration_blocks_idor_impersonation(): void {
		$coord_a = self::factory()->user->create( [ 'role' => 'author', 'user_email' => 'coord_a@educarex.es' ] );
		$coord_b = self::factory()->user->create( [ 'role' => 'author', 'user_email' => 'coord_b@educarex.es' ] );
		$coord_c = self::factory()->user->create( [ 'role' => 'author', 'user_email' => 'coord_c@educarex.es' ] );

		$center_b = self::factory()->post->create(
			[
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $coord_b,
			]
		);
		$center_c = self::factory()->post->create(
			[
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $coord_c,
			]
		);

		// Coordinator A tries to send a proposal claiming to be Center B.
		wp_set_current_user( $coord_a );

		$controller = new ColaboracionesController();
		$request    = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/colaboraciones' );
		$request->set_param( 'centro_emisor_id', $center_b );
		$request->set_param( 'centro_receptor_id', $center_c );
		$request->set_param( 'idea_proyecto', 'Intento de suplantación IDOR' );

		$response = $controller->create_item( $request );
		$this->assertWPError( $response );
		$this->assertSame( 'radioedu_forbidden_center', $response->get_error_code() );
	}

	/**
	 * Prevent sending proposals to a school marked as unavailable for collaborations.
	 */
	public function test_create_collaboration_rejects_unavailable_target_center(): void {
		$coord_a = self::factory()->user->create( [ 'role' => 'author' ] );
		$coord_b = self::factory()->user->create( [ 'role' => 'author' ] );

		$center_a = self::factory()->post->create(
			[
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $coord_a,
			]
		);
		$center_b = self::factory()->post->create(
			[
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $coord_b,
			]
		);
		update_post_meta( $center_b, '_radioedu_disponible_colaborar', '0' );

		wp_set_current_user( $coord_a );

		$controller = new ColaboracionesController();
		$request    = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/colaboraciones' );
		$request->set_param( 'centro_emisor_id', $center_a );
		$request->set_param( 'centro_receptor_id', $center_b );
		$request->set_param( 'idea_proyecto', 'Propuesta a centro no disponible' );

		$response = $controller->create_item( $request );
		$this->assertWPError( $response );
		$this->assertSame( 'radioedu_receptor_unavailable', $response->get_error_code() );
	}
}
