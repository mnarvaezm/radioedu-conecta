<?php
/**
 * Regression coverage for authorization, public data and malformed form input.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Admin\CentroMetaBoxes;
use RadioEdu\Conecta\Admin\DemoDataImporter;
use RadioEdu\Conecta\Admin\Settings;
use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\Plugin;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Rest\ColaboracionesController;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;
use WP_REST_Request;
use WP_UnitTestCase;

class SecurityRegressionTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		RoleCapabilities::add_capabilities();
		ColaboracionesTable::create_table();
	}

	public function tear_down(): void {
		$_POST = [];
		unset( $_SERVER['HTTP_X_WP_NONCE'] );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function center( int $author, array $args = [] ): int {
		return self::factory()->post->create( array_merge( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish', 'post_author' => $author ], $args ) );
	}

	private function proposal( int $receiver ): int {
		global $wpdb;
		$wpdb->insert( ColaboracionesTable::get_table_name(), [ 'centro_emisor_id' => 1, 'centro_receptor_id' => $receiver, 'usuario_emisor_id' => 1, 'idea_proyecto' => 'Test', 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ] );
		return (int) $wpdb->insert_id;
	}

	public function test_subscriber_matching_coordinator_email_cannot_answer(): void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$center = $this->center( $subscriber );
		update_post_meta( $center, '_radioedu_coordinador_email', get_userdata( $subscriber )->user_email );
		$id = $this->proposal( $center );
		wp_set_current_user( $subscriber );
		$patch = new WP_REST_Request( 'PATCH', '/radioedu-conecta/v1/colaboraciones/' . $id );
		$patch->set_param( 'estado', 'aceptada' );
		$response = rest_do_request( $patch );
		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'pendiente', ColaboracionesTable::get_by_id( $id )->estado );
		$this->assertFalse( ColaboracionesController::user_can_manage_center( $center ) );
		$this->assertSame( [], ColaboracionesController::get_user_managed_center_ids( $subscriber ) );
	}

	public function test_service_rejects_unrelated_coordinator(): void {
		$owner = self::factory()->user->create( [ 'role' => 'author' ] );
		$other = self::factory()->user->create( [ 'role' => 'author' ] );
		$id = $this->proposal( $this->center( $owner ) );
		wp_set_current_user( $other );
		$result = ColaboracionesController::respond_to_proposal( $id, 'aceptada', '' );
		$this->assertWPError( $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	public function test_public_directory_excludes_password_protected_centers_and_contact_data(): void {
		$public = $this->center( 0 );
		$protected = $this->center( 0, [ 'post_password' => 'secret' ] );
		update_post_meta( $public, '_radioedu_coordinador_email', 'private@example.org' );
		update_post_meta( $public, '_radioedu_coordinador_nombre', 'Private Coordinator' );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/radioedu-conecta/v1/centros' ) );
		$this->assertSame( 200, $response->get_status() );
		$ids = wp_list_pluck( $response->get_data()['centros'], 'id' );
		$this->assertContains( $public, $ids );
		$this->assertNotContains( $protected, $ids );
		$json = wp_json_encode( $response->get_data() );
		$this->assertStringNotContainsString( 'private@example.org', $json );
		$this->assertStringNotContainsString( 'Private Coordinator', $json );
	}

	public function test_rest_rejects_invalid_schema_values(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'author' ] ) );
		$request = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/colaboraciones' );
		$request->set_body_params( [ 'centro_emisor_id' => -1, 'centro_receptor_id' => [], 'idea_proyecto' => str_repeat( 'x', 5001 ) ] );
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
	}

	public function test_affinity_is_sorted_before_pagination(): void {
		$reference = $this->center( 0, [ 'post_title' => 'Reference' ] );
		$unrelated = $this->center( 0, [ 'post_title' => 'A first alphabetically' ] );
		$related = $this->center( 0, [ 'post_title' => 'Z best match' ] );
		wp_set_object_terms( $reference, [ 'Shared topic' ], TaxonomiesRegistrar::TAX_TEMATICA );
		wp_set_object_terms( $related, [ 'Shared topic' ], TaxonomiesRegistrar::TAX_TEMATICA );
		$request = new WP_REST_Request( 'GET', '/radioedu-conecta/v1/centros' );
		$request->set_query_params( [ 'orden' => 'afines', 'afines_a' => $reference, 'per_page' => 1, 'page' => 1 ] );
		$response = rest_do_request( $request );
		$this->assertSame( $related, $response->get_data()['centros'][0]['id'] );
		$request->set_param( 'page', 2 );
		$this->assertSame( $unrelated, rest_do_request( $request )->get_data()['centros'][0]['id'] );
	}

	public function test_affinity_default_does_not_demote_an_arbitrary_centro_for_global_managers(): void {
		$first  = $this->center( 0, [ 'post_title' => 'A first alphabetically' ] );
		$second = $this->center( 0, [ 'post_title' => 'B second' ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$request = new WP_REST_Request( 'GET', '/radioedu-conecta/v1/centros' );
		$request->set_query_params( [ 'orden' => 'afines' ] );
		$this->assertSame( [ $first, $second ], wp_list_pluck( rest_do_request( $request )->get_data()['centros'], 'id' ) );
	}

	public function test_affinity_default_sinks_the_coordinators_own_centro(): void {
		$author = self::factory()->user->create( [ 'role' => 'author' ] );
		$own    = $this->center( $author, [ 'post_title' => 'A own school' ] );
		$other  = $this->center( 0, [ 'post_title' => 'B other school' ] );
		wp_set_current_user( $author );
		$request = new WP_REST_Request( 'GET', '/radioedu-conecta/v1/centros' );
		$request->set_query_params( [ 'orden' => 'afines' ] );
		$this->assertSame( [ $other, $own ], wp_list_pluck( rest_do_request( $request )->get_data()['centros'], 'id' ) );
	}

	public function test_author_can_use_a_readable_library_logo_but_not_a_private_one(): void {
		$admin   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$author  = self::factory()->user->create( [ 'role' => 'author' ] );
		$center  = $this->center( $author );
		$shared  = self::factory()->attachment->create_object( [ 'file' => 'shared-logo.jpg', 'post_mime_type' => 'image/jpeg', 'post_author' => $admin ] );
		$private = self::factory()->post->create( [ 'post_status' => 'private', 'post_author' => $admin ] );
		$hidden  = self::factory()->attachment->create_object( [ 'file' => 'hidden-logo.jpg', 'post_mime_type' => 'image/jpeg', 'post_author' => $admin, 'post_parent' => $private ] );
		wp_set_current_user( $author );
		$_POST = [ CentroMetaBoxes::NONCE_FIELD => wp_create_nonce( CentroMetaBoxes::NONCE_ACTION ), 'radioedu_logo_id' => (string) $shared ];
		CentroMetaBoxes::save_meta_boxes( $center, get_post( $center ) );
		$this->assertSame( $shared, (int) get_post_meta( $center, '_radioedu_logo_id', true ) );
		$_POST['radioedu_logo_id'] = (string) $hidden;
		CentroMetaBoxes::save_meta_boxes( $center, get_post( $center ) );
		$this->assertSame( $shared, (int) get_post_meta( $center, '_radioedu_logo_id', true ) );
	}

	public function test_multisite_frontend_initialization_and_site_lifecycle(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite-specific lifecycle.' );
		}
		$site = self::factory()->blog->create();
		$current = get_current_blog_id();
		switch_to_blog( $site );
		try {
			// Lazy initialization also runs before a public REST request on an unprovisioned site.
			delete_option( 'radioedu_conecta_db_version' );
			Plugin::instance()->maybe_update_db_schema();
			$this->assertSame( ColaboracionesTable::DB_VERSION, get_option( 'radioedu_conecta_db_version' ) );
			$this->assertTrue( get_role( 'author' )->has_cap( 'propose_radioedu_colaboracion' ) );
			$this->assertContains( ColaboracionesTable::get_table_name(), apply_filters( 'wpmu_drop_tables', [] ) );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( $current, get_current_blog_id() );
	}

	public function test_missing_and_invalid_rest_nonces_are_rejected_by_core(): void {
		global $wp_rest_auth_cookie;
		$original = $wp_rest_auth_cookie;
		$author = self::factory()->user->create( [ 'role' => 'author' ] );
		try {
			$wp_rest_auth_cookie = true;
			wp_set_current_user( $author );
			unset( $_SERVER['HTTP_X_WP_NONCE'] );
			rest_cookie_check_errors( null );
			$this->assertSame( 0, get_current_user_id() );
			$this->assertFalse( ( new ColaboracionesController() )->create_item_permissions_check( new WP_REST_Request() ) );
			wp_set_current_user( $author );
			$_SERVER['HTTP_X_WP_NONCE'] = 'invalid';
			$result = rest_cookie_check_errors( null );
			$this->assertWPError( $result );
			$this->assertSame( 'rest_cookie_invalid_nonce', $result->get_error_code() );
		} finally {
			$wp_rest_auth_cookie = $original;
		}
	}

	public function test_metabox_requires_nonce_and_ownership_and_rejects_array_urls(): void {
		$owner = self::factory()->user->create( [ 'role' => 'author' ] );
		$other = self::factory()->user->create( [ 'role' => 'author' ] );
		$center = $this->center( $owner );
		$post = get_post( $center );
		update_post_meta( $center, '_radioedu_web_centro', 'https://example.org/' );
		wp_set_current_user( $owner );
		$_POST = [ 'radioedu_web_centro' => 'https://attacker.example/' ];
		CentroMetaBoxes::save_meta_boxes( $center, $post );
		$this->assertSame( 'https://example.org/', get_post_meta( $center, '_radioedu_web_centro', true ) );
		$_POST[ CentroMetaBoxes::NONCE_FIELD ] = 'bad';
		CentroMetaBoxes::save_meta_boxes( $center, $post );
		$this->assertSame( 'https://example.org/', get_post_meta( $center, '_radioedu_web_centro', true ) );
		wp_set_current_user( $other );
		$_POST[ CentroMetaBoxes::NONCE_FIELD ] = wp_create_nonce( CentroMetaBoxes::NONCE_ACTION );
		CentroMetaBoxes::save_meta_boxes( $center, $post );
		$this->assertSame( 'https://example.org/', get_post_meta( $center, '_radioedu_web_centro', true ) );
		wp_set_current_user( $owner );
		$_POST = [ CentroMetaBoxes::NONCE_FIELD => wp_create_nonce( CentroMetaBoxes::NONCE_ACTION ), 'radioedu_web_centro' => [ 'invalid' ], 'radioedu_podcast_url' => [ 'invalid' ], 'radioedu_coordinador_email' => [ 'invalid' ], 'radioedu_logo_id' => [ 'invalid' ] ];
		CentroMetaBoxes::save_meta_boxes( $center, $post );
		$this->assertSame( 'https://example.org/', get_post_meta( $center, '_radioedu_web_centro', true ) );
	}

	public function test_malformed_checkbox_values_do_not_enable_notifications(): void {
		$result = Settings::sanitize_settings( [ 'notify_on_proposal' => [ '1' ], 'notify_on_status' => 'false' ] );
		$this->assertSame( '0', $result['notify_on_proposal'] );
		$this->assertSame( '0', $result['notify_on_status'] );
	}

	public function test_activation_does_not_publish_demo_content(): void {
		$before = wp_count_posts( CentroPostType::POST_TYPE )->publish;
		Plugin::activate( false );
		$this->assertSame( $before, wp_count_posts( CentroPostType::POST_TYPE )->publish );
	}

	public function test_demo_import_does_not_overwrite_user_content(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$center = $this->center( get_current_user_id(), [ 'post_name' => 'ceip-la-mar', 'post_title' => 'My school', 'post_content' => 'User content' ] );
		DemoDataImporter::import_demo_centers();
		$this->assertSame( 'User content', get_post_field( 'post_content', $center ) );
		$this->assertSame( '', get_post_meta( $center, '_radioedu_conecta_demo', true ) );
		$this->assertGreaterThan( 0, (int) get_option( 'radioedu_conecta_directory_page_id' ) );
	}

	public function test_demo_import_adopts_centros_and_page_created_before_the_demo_marker(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$legacy = $this->center( get_current_user_id(), [ 'post_name' => 'ceip-la-mar', 'post_title' => 'Old demo', 'post_content' => 'Stale demo content' ] );
		update_post_meta( $legacy, '_radioedu_podcast_url', 'https://radioedu.educarex.es/lamar' );
		$page = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'traspasando-fronteras', 'post_content' => '<!-- wp:shortcode -->[radioedu_directorio]<!-- /wp:shortcode -->' ] );
		DemoDataImporter::import_demo_centers();
		$this->assertSame( '1', get_post_meta( $legacy, '_radioedu_conecta_demo', true ) );
		$this->assertNotSame( 'Stale demo content', get_post_field( 'post_content', $legacy ) );
		$this->assertSame( '1', get_post_meta( $page, '_radioedu_conecta_demo', true ) );
		$this->assertSame( $page, (int) get_option( 'radioedu_conecta_directory_page_id' ) );
	}
}
