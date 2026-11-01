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
		CentroPostType::register();
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

	/**
	 * A proposal can only be answered once: later responses are rejected with HTTP 409.
	 */
	public function test_proposal_can_only_be_answered_once(): void {
		$coord_a  = self::factory()->user->create( [ 'role' => 'author' ] );
		$coord_b  = self::factory()->user->create( [ 'role' => 'author' ] );
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

		$controller = new ColaboracionesController();

		wp_set_current_user( $coord_a );
		$create = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/colaboraciones' );
		$create->set_param( 'centro_emisor_id', $center_a );
		$create->set_param( 'centro_receptor_id', $center_b );
		$create->set_param( 'idea_proyecto', 'Podcast conjunto' );
		$created = $controller->create_item( $create );
		$this->assertNotWPError( $created );
		$colab_id = (int) $created->get_data()['id'];

		// The sender cannot answer its own proposal.
		$patch = new WP_REST_Request( 'PATCH', '/radioedu-conecta/v1/colaboraciones/' . $colab_id );
		$patch->set_param( 'id', $colab_id );
		$patch->set_param( 'estado', 'aceptada' );
		$this->assertFalse( $controller->update_item_permissions_check( $patch ) );

		// The receiving coordinator answers once.
		wp_set_current_user( $coord_b );
		$this->assertTrue( $controller->update_item_permissions_check( $patch ) );
		$this->assertNotWPError( $controller->update_item( $patch ) );

		// A second answer must be rejected and must not overwrite the first one.
		$patch->set_param( 'estado', 'rechazada' );
		$second = $controller->update_item( $patch );
		$this->assertWPError( $second );
		$this->assertSame( 'radioedu_already_resolved', $second->get_error_code() );
		$this->assertSame( 'aceptada', ColaboracionesTable::get_by_id( $colab_id )->estado );
	}

	/**
	 * A coordinator sends up to 20 proposals per hour; the next one gets a 429.
	 */
	public function test_proposals_are_limited_to_twenty_per_hour(): void {
		$coord    = self::factory()->user->create( [ 'role' => 'author' ] );
		$sender   = self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish', 'post_author' => $coord ] );
		$receiver = self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish' ] );
		$this->assertSame( 20, ColaboracionesController::RATE_LIMIT_PER_HOUR );

		$controller = new ColaboracionesController();
		wp_set_current_user( $coord );
		$request = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/colaboraciones' );
		$request->set_param( 'centro_emisor_id', $sender );
		$request->set_param( 'centro_receptor_id', $receiver );
		$request->set_param( 'idea_proyecto', 'Podcast conjunto' );
		for ( $i = 1; $i <= 20; ++$i ) {
			$this->assertSame( 201, $controller->create_item( $request )->get_status(), "Proposal $i" );
		}

		$over = $controller->create_item( $request );
		$this->assertWPError( $over );
		$this->assertSame( 'radioedu_rate_limit', $over->get_error_code() );
		$this->assertSame( 429, $over->get_error_data()['status'] );
	}

	/**
	 * Two proposals sent at once with one place left: exactly one is accepted.
	 */
	public function test_two_proposals_at_once_cannot_both_take_the_last_place_of_the_hour(): void {
		$coord    = self::factory()->user->create( [ 'role' => 'author' ] );
		$sender   = self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish', 'post_author' => $coord ] );
		$receiver = self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish' ] );
		wp_set_current_user( $coord );
		set_transient( 'radioedu_rate_' . $coord, [ 'count' => 19, 'expires' => time() + HOUR_IN_SECONDS ], HOUR_IN_SECONDS );

		$request = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/colaboraciones' );
		$request->set_body_params( [ 'centro_emisor_id' => $sender, 'centro_receptor_id' => $receiver, 'idea_proyecto' => 'Podcast conjunto' ] );
		// The second proposal arrives while the first one is being written.
		$table      = ColaboracionesTable::get_table_name();
		$started    = false;
		$second     = null;
		$interleave = static function ( string $query ) use ( &$started, &$second, $request, $table ): string {
			if ( ! $started && str_contains( $query, $table ) && preg_match( '/^\s*INSERT\s/i', $query ) ) {
				$started = true;
				$second  = rest_do_request( clone $request );
			}
			return $query;
		};
		add_filter( 'query', $interleave );
		try {
			$first = rest_do_request( $request );
		} finally {
			remove_filter( 'query', $interleave );
		}

		$this->assertSame( 201, $first->get_status() );
		$this->assertSame( 429, $second->get_status() );
		$this->assertSame( 20, get_transient( 'radioedu_rate_' . $coord )['count'] );
		$this->assertSame( '1', (string) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT IS_FREE_LOCK( %s )', ColaboracionesController::rate_lock_name( $coord ) ) ), 'The lock is released afterwards.' );
	}

	/**
	 * The rate lock of a request that ended does not block the next proposal.
	 */
	public function test_the_rate_lock_of_a_request_that_ended_does_not_block_the_next_proposal(): void {
		$coord    = self::factory()->user->create( [ 'role' => 'author' ] );
		$sender   = self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish', 'post_author' => $coord ] );
		$receiver = self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish' ] );
		wp_set_current_user( $coord );
		$other = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$this->assertSame( '1', (string) $other->get_var( $other->prepare( 'SELECT GET_LOCK( %s, 0 )', ColaboracionesController::rate_lock_name( $coord ) ) ) );

		// That request ends: the server releases its lock with the connection.
		$other->close();
		$request = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/colaboraciones' );
		$request->set_body_params( [ 'centro_emisor_id' => $sender, 'centro_receptor_id' => $receiver, 'idea_proyecto' => 'Podcast conjunto' ] );
		$this->assertSame( 201, rest_do_request( $request )->get_status() );
	}

	/**
	 * A database error while answering is a retryable 500, not "already answered".
	 */
	public function test_a_storage_error_while_answering_is_not_reported_as_already_answered(): void {
		global $wpdb;
		$coord  = self::factory()->user->create( [ 'role' => 'author' ] );
		$center = self::factory()->post->create(
			[
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $coord,
			]
		);
		$wpdb->insert(
			ColaboracionesTable::get_table_name(),
			[
				'centro_emisor_id'   => 1,
				'centro_receptor_id' => $center,
				'usuario_emisor_id'  => 1,
				'idea_proyecto'      => 'Test',
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			]
		);
		$colab_id = (int) $wpdb->insert_id;
		$table    = ColaboracionesTable::get_table_name();
		$refuse   = static function ( string $query ) use ( $table ): string {
			return str_starts_with( ltrim( $query ), 'UPDATE `' . $table . '`' ) ? '' : $query;
		};

		wp_set_current_user( $coord );
		add_filter( 'query', $refuse );
		try {
			$result = ColaboracionesController::respond_to_proposal( $colab_id, 'aceptada', '' );
		} finally {
			remove_filter( 'query', $refuse );
		}
		$this->assertWPError( $result );
		$this->assertSame( 'radioedu_db_error', $result->get_error_code() );
		$this->assertSame( 500, $result->get_error_data()['status'] );
		$this->assertSame( 'pendiente', ColaboracionesTable::get_by_id( $colab_id )->estado );

		$this->assertNotWPError( ColaboracionesController::respond_to_proposal( $colab_id, 'aceptada', '' ) );
		$this->assertSame( 'aceptada', ColaboracionesTable::get_by_id( $colab_id )->estado );
	}

	/**
	 * Answering a non-existent proposal returns 404 instead of silently succeeding.
	 */
	public function test_answering_missing_proposal_returns_not_found(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$result = ColaboracionesController::respond_to_proposal( 999999, 'aceptada', '' );

		$this->assertWPError( $result );
		$this->assertSame( 'radioedu_not_found', $result->get_error_code() );
	}

	/**
	 * Authorization must be evaluated for the given user, not for the current user.
	 */
	public function test_user_can_manage_center_checks_the_given_user(): void {
		$owner    = self::factory()->user->create( [ 'role' => 'author' ] );
		$stranger = self::factory()->user->create( [ 'role' => 'author' ] );
		$admin    = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$center   = self::factory()->post->create(
			[
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $owner,
			]
		);

		wp_set_current_user( $admin );

		$this->assertTrue( ColaboracionesController::user_can_manage_center( $center, $owner ) );
		$this->assertFalse( ColaboracionesController::user_can_manage_center( $center, $stranger ) );
	}
}
