<?php
/**
 * Runs uninstall.php for real and looks for anything the plugin left behind.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Admin\DemoDataImporter;
use RadioEdu\Conecta\Admin\Settings;
use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\Network\NetworkSync;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;
use WP_UnitTestCase;

/**
 * Uninstall removes the plugin data of every site and keeps everything else (audit F-20).
 *
 * Uninstall drops the real table of the main site, and that DDL commits the transaction each test
 * runs in, so this test runs apart, in its own PHPUnit process, whose bootstrap reinstalls the test
 * database: `phpunit --group uninstall`. phpunit.xml.dist leaves the group out of the normal run.
 *
 * @group uninstall
 */
class UninstallTest extends WP_UnitTestCase {

	/**
	 * Plugin capabilities, as RoleCapabilities grants them.
	 */
	private const CAPABILITIES = [
		'manage_radioedu',
		'edit_radioedu_centros',
		'edit_others_radioedu_centros',
		'edit_published_radioedu_centros',
		'edit_private_radioedu_centros',
		'publish_radioedu_centros',
		'read_private_radioedu_centros',
		'delete_radioedu_centros',
		'delete_private_radioedu_centros',
		'delete_published_radioedu_centros',
		'delete_others_radioedu_centros',
		'propose_radioedu_colaboracion',
	];

	/**
	 * Fills the current site with every kind of data the plugin stores.
	 *
	 * @param int $user User who has editor preferences.
	 * @return int ID of a page of the site owner, which uninstall must keep.
	 */
	private function fill_site( int $user ): int {
		global $wpdb;

		CentroPostType::register();
		TaxonomiesRegistrar::register_all();
		RoleCapabilities::add_capabilities();
		ColaboracionesTable::create_table();
		update_option( Settings::OPTION_KEY, Settings::get_default_settings() );

		$terms = [];
		foreach ( [ TaxonomiesRegistrar::TAX_ETAPA, TaxonomiesRegistrar::TAX_IDIOMA, TaxonomiesRegistrar::TAX_TEMATICA, TaxonomiesRegistrar::TAX_FORMATO, TaxonomiesRegistrar::TAX_LOCALIZACION ] as $taxonomy ) {
			$terms[ $taxonomy ] = (int) self::factory()->term->create( [ 'taxonomy' => $taxonomy ] );
		}
		foreach ( [ 'publish', 'draft', 'trash' ] as $status ) {
			$centro = self::factory()->post->create(
				[
					'post_type'   => CentroPostType::POST_TYPE,
					'post_status' => $status,
				]
			);
			update_post_meta( $centro, '_radioedu_edades', '12-14' );
			update_post_meta( $centro, '_radioedu_coordinador_email', 'coordinator@example.org' );
			foreach ( $terms as $taxonomy => $term ) {
				wp_set_object_terms( $centro, [ $term ], $taxonomy );
			}
		}

		$wpdb->insert(
			ColaboracionesTable::get_table_name(),
			[
				'centro_emisor_id'   => 1,
				'centro_receptor_id' => 2,
				'usuario_emisor_id'  => $user,
				'idea_proyecto'      => 'Podcast',
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			]
		);

		// The demo directory page goes; a page of the site owner stays.
		$demo_page = self::factory()->post->create( [ 'post_type' => 'page' ] );
		update_post_meta( $demo_page, DemoDataImporter::DEMO_META, '1' );
		update_option( 'radioedu_conecta_directory_page_id', $demo_page );
		$own_page = self::factory()->post->create( [ 'post_type' => 'page' ] );

		set_transient( 'radioedu_rate_' . $user, [ 'count' => 1 ], HOUR_IN_SECONDS );
		set_transient( 'radioedu_conecta_idem_' . md5( 'key' ), [ 'id' => 1 ], HOUR_IN_SECONDS );
		set_transient( 'radioedu_conecta_schema_retry', 1, MINUTE_IN_SECONDS );

		update_user_meta(
			$user,
			$wpdb->get_blog_prefix() . 'persisted_preferences',
			[
				'core'             => [ 'fixedToolbar' => true ],
				'radioedu-conecta' => [ 'centroPanelOpened' => true ],
			]
		);

		return $own_page;
	}

	/**
	 * Asserts the current site holds no plugin data and keeps the rest.
	 *
	 * @param int $user     User with editor preferences.
	 * @param int $own_page Page of the site owner.
	 */
	private function assert_site_is_clean( int $user, int $own_page ): void {
		global $wpdb;
		$site = get_current_blog_id();

		// SHOW COLUMNS, not SHOW TABLES: the tables of a site created by the test are temporary.
		$suppress = $wpdb->suppress_errors();
		$columns  = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $wpdb->prefix . 'radioedu_colaboraciones' ) );
		$wpdb->suppress_errors( $suppress );
		$this->assertSame( [], $columns, "Table, site $site" );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_type = %s', $wpdb->posts, CentroPostType::POST_TYPE ) ), "Centros, site $site" );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE meta_key LIKE %s', $wpdb->postmeta, $wpdb->esc_like( '_radioedu' ) . '%' ) ), "Post meta, site $site" );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE taxonomy LIKE %s', $wpdb->term_taxonomy, $wpdb->esc_like( 'radioedu_' ) . '%' ) ), "Terms, site $site" );
		$this->assertSame( [], $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $wpdb->options, '%' . $wpdb->esc_like( 'radioedu' ) . '%' ) ), "Options and transients, site $site" );

		wp_roles()->for_site( $site );
		foreach ( wp_roles()->role_objects as $name => $role ) {
			foreach ( self::CAPABILITIES as $capability ) {
				$this->assertFalse( $role->has_cap( $capability ), "$capability on $name, site $site" );
			}
		}

		$preferences = get_user_meta( $user, $wpdb->get_blog_prefix() . 'persisted_preferences', true );
		$this->assertSame( [ 'core' => [ 'fixedToolbar' => true ] ], $preferences, "Preferences, site $site" );
		$this->assertSame( 'page', get_post_type( $own_page ), "Own page, site $site" );
	}

	public function test_uninstall_leaves_no_plugin_data_on_any_site(): void {
		$user  = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$pages = [ get_current_blog_id() => $this->fill_site( $user ) ];

		if ( is_multisite() ) {
			$site = (int) self::factory()->blog->create();
			switch_to_blog( $site );
			$pages[ $site ] = $this->fill_site( $user );
			restore_current_blog();
			update_network_option( null, NetworkSync::GRANT_AUTHOR_OPTION, '0' );
		}

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', RADIOEDU_CONECTA_BASENAME );
		}
		// The test case turns DROP TABLE into DROP TEMPORARY TABLE, which would leave the real table
		// of the main site in place: uninstall drops it for real, as in production.
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );
		try {
			require RADIOEDU_CONECTA_DIR . 'uninstall.php';
		} finally {
			add_filter( 'query', [ $this, '_drop_temporary_tables' ] );
		}

		if ( ! is_multisite() ) {
			$this->assert_site_is_clean( $user, $pages[ get_current_blog_id() ] );
			return;
		}
		foreach ( $pages as $site => $own_page ) {
			switch_to_blog( $site );
			$this->assert_site_is_clean( $user, $own_page );
			restore_current_blog();
		}
		global $wpdb;
		$this->assertSame( [], $wpdb->get_col( $wpdb->prepare( 'SELECT meta_key FROM %i WHERE meta_key LIKE %s', $wpdb->sitemeta, '%' . $wpdb->esc_like( 'radioedu' ) . '%' ) ), 'Network options' );
	}
}
