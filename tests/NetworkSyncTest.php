<?php
/**
 * Tests for the network sync of fichas (Multisite only).
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Admin\Settings;
use RadioEdu\Conecta\Capabilities\CentroLimit;
use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Network\NetworkSync;
use RadioEdu\Conecta\PostTypes\CentroMeta;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Verifies that the network sync creates, updates and reuses one ficha per network site.
 */
class NetworkSyncTest extends WP_UnitTestCase {

	private int $super_admin = 0;

	public function set_up(): void {
		parent::set_up();
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'The network sync only exists in Multisite.' );
		}
		RoleCapabilities::add_capabilities();
		CentroPostType::register();
		CentroMeta::register();
		$this->super_admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		grant_super_admin( $this->super_admin );
		wp_set_current_user( $this->super_admin );
		$GLOBALS['wp_rest_server'] = null;
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );
		$GLOBALS['wp_rest_server'] = null;
		remove_all_filters( 'radioedu_conecta_network_sync_batch_size' );
		parent::tear_down();
	}

	/**
	 * Creates a network site run by $owner.
	 */
	private function site( string $name, int $owner, string $tagline = '' ): int {
		$blog_id = self::factory()->blog->create(
			[
				'title'   => $name,
				'user_id' => $owner,
			]
		);
		switch_to_blog( $blog_id );
		update_option( 'blogdescription', $tagline );
		update_option( 'admin_email', get_userdata( $owner )->user_email );
		restore_current_blog();

		return $blog_id;
	}

	private function teacher(): int {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		// Not a member of the directory site until the sync adds them.
		remove_user_from_blog( $user_id, get_main_site_id() );

		return $user_id;
	}

	private function ficha_of( int $blog_id ): ?\WP_Post {
		$posts = get_posts(
			[
				'post_type'      => CentroPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 5,
				'meta_key'       => NetworkSync::SOURCE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $blog_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertLessThanOrEqual( 1, count( $posts ), 'Never more than one ficha per site.' );

		return $posts[0] ?? null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function sync_all( bool $grant_author_role = true ): array {
		$offset = 0;
		do {
			$batch  = NetworkSync::sync_batch( $offset, $grant_author_role );
			$offset = $batch['next_offset'];
		} while ( ! $batch['done'] );

		return $batch;
	}

	public function test_the_author_role_option_is_checked_by_default(): void {
		delete_site_option( NetworkSync::GRANT_AUTHOR_OPTION );
		$this->assertTrue( NetworkSync::get_grant_author_role() );
	}

	public function test_unchecked_option_does_not_give_the_author_role(): void {
		$teacher = $this->teacher();
		$site    = $this->site( 'Radio Sin Rol', $teacher );

		$result = $this->sync_all( false );

		$this->assertSame( 0, $result['authors_added'] );
		$this->assertFalse( is_user_member_of_blog( $teacher, get_main_site_id() ) );
		// The site administrator is still the author of the ficha.
		$this->assertSame( $teacher, (int) $this->ficha_of( $site )->post_author );
	}

	public function test_checked_option_upgrades_lower_roles_but_leaves_higher_ones(): void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$editor     = self::factory()->user->create( [ 'role' => 'editor' ] );
		$newcomer   = $this->teacher();
		$this->site( 'Radio Suscriptor', $subscriber );
		$this->site( 'Radio Editor', $editor );
		$this->site( 'Radio Nueva', $newcomer );

		$result = $this->sync_all( true );

		$this->assertSame( 2, $result['authors_added'] );
		$this->assertContains( 'author', get_userdata( $subscriber )->roles );
		$this->assertContains( 'subscriber', get_userdata( $subscriber )->roles, 'The existing role is kept.' );
		$this->assertSame( [ 'editor' ], array_values( get_userdata( $editor )->roles ), 'A higher role is left as it is.' );
		$this->assertSame( [ 'author' ], array_values( get_userdata( $newcomer )->roles ) );
	}

	public function test_checking_the_option_later_gives_the_role_on_the_next_sync(): void {
		$teacher = $this->teacher();
		$this->site( 'Radio Más Tarde', $teacher );
		$this->sync_all( false );
		$this->assertFalse( is_user_member_of_blog( $teacher, get_main_site_id() ) );

		$result = $this->sync_all( true );

		$this->assertSame( 1, $result['authors_added'] );
		$this->assertTrue( is_user_member_of_blog( $teacher, get_main_site_id() ) );
		$this->assertSame( 0, $this->sync_all( true )['authors_added'], 'Nothing to add the second time.' );
	}

	public function test_rest_stores_the_choice_and_reuses_it_when_omitted(): void {
		$first  = $this->teacher();
		$second = $this->teacher();
		$this->site( 'Radio Elección', $first );

		$request = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/network-sync' );
		$request->set_param( 'offset', 0 );
		$request->set_param( 'grant_author_role', false );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertFalse( NetworkSync::get_grant_author_role() );
		$this->assertFalse( is_user_member_of_blog( $first, get_main_site_id() ) );

		// Without the parameter, the stored choice (unchecked) applies.
		$this->site( 'Radio Sin Parámetro', $second );
		$omitted = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/network-sync' );
		$omitted->set_param( 'offset', 0 );
		$this->assertSame( 0, rest_do_request( $omitted )->get_data()['authors_added'] );
		$this->assertFalse( is_user_member_of_blog( $second, get_main_site_id() ) );
	}

	public function test_creates_a_ficha_per_site_with_the_site_data(): void {
		$teacher = $this->teacher();
		$site    = $this->site( 'IES Prueba Radio', $teacher, 'La radio del instituto' );

		$result = NetworkSync::sync_batch( 0 );

		$this->assertSame( 1, $result['created'] );
		$this->assertTrue( $result['done'] );
		$ficha = $this->ficha_of( $site );
		$this->assertNotNull( $ficha );
		$this->assertSame( 'IES Prueba Radio', $ficha->post_title );
		$this->assertSame( 'publish', $ficha->post_status );
		$this->assertSame( $teacher, (int) $ficha->post_author );
		$this->assertSame( 'La radio del instituto', $ficha->post_excerpt );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $ficha->post_content );
		$this->assertSame( get_userdata( $teacher )->user_email, get_post_meta( $ficha->ID, '_radioedu_coordinador_email', true ) );
		$this->assertSame( get_home_url( $site, '/' ), get_post_meta( $ficha->ID, '_radioedu_web_centro', true ) );
		$this->assertSame( '', get_post_meta( $ficha->ID, '_radioedu_logo_url', true ) );

		// The site administrator can now edit their ficha on the directory site.
		$this->assertTrue( is_user_member_of_blog( $teacher, get_main_site_id() ) );
		$this->assertTrue( user_can( $teacher, 'edit_post', $ficha->ID ) );
		// The main site itself gets no ficha.
		$this->assertNull( $this->ficha_of( get_main_site_id() ) );

		// The batch lists the synced station for the final table.
		$this->assertCount( 1, $result['synced'] );
		$this->assertSame( $ficha->ID, $result['synced'][0]['id'] );
		$this->assertSame( 'IES Prueba Radio', $result['synced'][0]['title'] );
		$this->assertSame( $site, $result['synced'][0]['blog_id'] );
		$this->assertSame( get_home_url( $site, '/' ), $result['synced'][0]['site_url'] );
		$this->assertSame( 'created', $result['synced'][0]['outcome'] );
		$this->assertTrue( $result['synced'][0]['author_added'] );
		$this->assertStringContainsString( 'post=' . $ficha->ID, $result['synced'][0]['edit_url'] );
	}

	public function test_resync_refreshes_name_web_and_logo_but_keeps_what_the_coordinator_edited(): void {
		$site = $this->site( 'Radio Antigua', $this->teacher(), 'Lema inicial' );
		$this->sync_all();
		$ficha = $this->ficha_of( $site );
		wp_update_post(
			[
				'ID'           => $ficha->ID,
				'post_excerpt' => 'Descripción editada',
			]
		);
		update_post_meta( $ficha->ID, '_radioedu_lat', '40.1' );
		update_post_meta( $ficha->ID, '_radioedu_bandera', 'es-ex' );
		update_post_meta( $ficha->ID, '_radioedu_coordinador_email', 'otro@example.org' );

		switch_to_blog( $site );
		update_option( 'blogname', 'Radio Renovada' );
		update_option( 'blogdescription', 'Lema nuevo' );
		update_option( 'site_icon', self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' ) );
		restore_current_blog();

		$result = $this->sync_all();

		$this->assertSame( 1, $result['updated'] );
		$this->assertSame( 0, $result['created'] );
		$ficha = $this->ficha_of( $site );
		$this->assertSame( 'Radio Renovada', $ficha->post_title );
		$this->assertStringContainsString( 'canola', (string) get_post_meta( $ficha->ID, '_radioedu_logo_url', true ) );
		$this->assertSame( 'Descripción editada', $ficha->post_excerpt );
		$this->assertSame( '40.1', get_post_meta( $ficha->ID, '_radioedu_lat', true ) );
		$this->assertSame( 'es-ex', get_post_meta( $ficha->ID, '_radioedu_bandera', true ) );
		$this->assertSame( 'otro@example.org', get_post_meta( $ficha->ID, '_radioedu_coordinador_email', true ) );
	}

	public function test_reuses_a_hand_made_ficha_with_the_same_website(): void {
		$site   = $this->site( 'Radio Con Ficha', $this->teacher() );
		$author = self::factory()->user->create( [ 'role' => 'author' ] );
		$manual = self::factory()->post->create(
			[
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Ficha hecha a mano',
				'post_author' => $author,
			]
		);
		// Same website, other spelling: no trailing slash.
		update_post_meta( $manual, '_radioedu_web_centro', untrailingslashit( get_home_url( $site, '/' ) ) );

		$result = $this->sync_all();

		$this->assertSame( 1, $result['reused'] );
		$this->assertSame( 0, $result['created'] );
		$ficha = $this->ficha_of( $site );
		$this->assertSame( $manual, $ficha->ID );
		$this->assertSame( 'Radio Con Ficha', $ficha->post_title );
		$this->assertSame( $author, (int) $ficha->post_author, 'The hand-made ficha keeps its author.' );
		$this->assertSame( [ 'reused' ], array_column( $result['synced'], 'outcome' ) );
	}

	public function test_skips_archived_spam_and_deleted_sites(): void {
		$archived = $this->site( 'Archivado', $this->teacher() );
		$spam     = $this->site( 'Spam', $this->teacher() );
		$deleted  = $this->site( 'Borrado', $this->teacher() );
		update_blog_status( $archived, 'archived', '1' );
		update_blog_status( $spam, 'spam', '1' );
		update_blog_status( $deleted, 'deleted', '1' );

		$result = $this->sync_all();

		$this->assertSame( 0, $result['total'] );
		$this->assertSame( 0, $result['created'] );
		foreach ( [ $archived, $spam, $deleted ] as $blog_id ) {
			$this->assertNull( $this->ficha_of( $blog_id ) );
		}
	}

	public function test_keeps_and_reports_fichas_whose_site_is_gone(): void {
		$site = $this->site( 'Radio Cerrada', $this->teacher() );
		$this->sync_all();
		$ficha = $this->ficha_of( $site );

		wp_delete_site( $site );
		$result = $this->sync_all();

		$this->assertNotNull( get_post( $ficha->ID ), 'The ficha is not deleted.' );
		$this->assertSame( [ $ficha->ID ], array_column( $result['missing'], 'id' ) );
		$this->assertSame( $site, $result['missing'][0]['blog_id'] );
		$this->assertStringContainsString( 'post=' . $ficha->ID, $result['missing'][0]['edit_url'] );
	}

	public function test_trashed_fichas_are_neither_updated_nor_recreated(): void {
		$site = $this->site( 'Radio En Papelera', $this->teacher() );
		$this->sync_all();
		$ficha = $this->ficha_of( $site );
		wp_trash_post( $ficha->ID );

		$result = $this->sync_all();

		$this->assertSame( 1, $result['skipped'] );
		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 'trash', get_post_status( $ficha->ID ) );
		$this->assertSame( [ $ficha->ID ], array_column( $result['synced'], 'id' ) );
		$this->assertSame( [ 'skipped' ], array_column( $result['synced'], 'outcome' ) );
	}

	public function test_batches_advance_until_every_site_is_synced(): void {
		add_filter( 'radioedu_conecta_network_sync_batch_size', static fn (): int => 1 );
		$first  = $this->site( 'Radio Uno', $this->teacher() );
		$second = $this->site( 'Radio Dos', $this->teacher() );

		$batch = NetworkSync::sync_batch( 0 );
		$this->assertSame( 1, $batch['processed'] );
		$this->assertSame( 2, $batch['total'] );
		$this->assertSame( 1, $batch['next_offset'] );
		$this->assertFalse( $batch['done'] );
		$this->assertSame( [], $batch['missing'] );

		$batch = NetworkSync::sync_batch( $batch['next_offset'] );
		$this->assertTrue( $batch['done'] );
		$this->assertNotNull( $this->ficha_of( $first ) );
		$this->assertNotNull( $this->ficha_of( $second ) );
	}

	public function test_batch_size_is_clamped(): void {
		add_filter( 'radioedu_conecta_network_sync_batch_size', static fn (): int => 500 );
		$this->assertSame( 100, NetworkSync::get_batch_size() );
		remove_all_filters( 'radioedu_conecta_network_sync_batch_size' );
		add_filter( 'radioedu_conecta_network_sync_batch_size', static fn (): int => 0 );
		$this->assertSame( 1, NetworkSync::get_batch_size() );
	}

	public function test_the_radios_per_user_limit_does_not_block_the_sync(): void {
		update_option( Settings::OPTION_KEY, array_merge( Settings::get_default_settings(), [ CentroLimit::SETTING => '0' ] ) );
		$site = $this->site( 'Radio Con Límite', $this->teacher() );

		$this->assertSame( 1, $this->sync_all()['created'] );
		$this->assertNotNull( $this->ficha_of( $site ) );
	}

	public function test_rest_endpoint_is_only_for_network_administrators_on_the_main_site(): void {
		$this->site( 'Radio REST', $this->teacher() );
		$request = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/network-sync' );
		$request->set_param( 'offset', 0 );

		$site_admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $site_admin );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );

		wp_set_current_user( 0 );
		$this->assertSame( 401, rest_do_request( $request )->get_status() );

		wp_set_current_user( $this->super_admin );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_data()['created'] );

		// Only on the main site, where the directory lives.
		switch_to_blog( self::factory()->blog->create() );
		$on_subsite = rest_do_request( $request )->get_status();
		restore_current_blog();
		$this->assertSame( 404, $on_subsite );
	}
}
