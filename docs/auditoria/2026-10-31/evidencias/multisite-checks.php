<?php
/** Disposable audit network only. */
defined( 'ABSPATH' ) || exit;
if ( ! is_multisite() || 'RadioEdu-Audit' !== get_option( 'blogname' ) ) { throw new RuntimeException( 'Wrong environment.' ); }
use RadioEdu\Conecta\Capabilities\CentroLimit as L;
use RadioEdu\Conecta\Network\NetworkSync as N;
use RadioEdu\Conecta\Database\ColaboracionesTable as T;
use RadioEdu\Conecta\Plugin;
function net_out( $key, $data ) { echo $key . ': ' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) . "\n"; }
function net_table_exists() { global $wpdb; return null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( T::get_table_name() ) ) ); }
function net_request() { return rest_do_request( new WP_REST_Request( 'POST', '/radioedu-conecta/v1/network-sync' ) ); }
wp_set_current_user( 1 );
$network = get_network();
$owner = (int) get_user_by( 'login', 'audit_author_a' )->ID;
$site = wpmu_create_blog( $network->domain, '/audit-two/', 'Audit Two', $owner, [], get_current_network_id() );
if ( is_wp_error( $site ) ) { throw new RuntimeException( $site->get_error_message() ); }
switch_to_blog( $site );
$provisioned = net_table_exists();
add_user_to_blog( $site, $owner, 'author' );
update_option( 'radioedu_conecta_settings', [ 'max_centros_per_user' => '1' ] );
wp_insert_post( [ 'post_type' => 'radioedu_centro', 'post_title' => 'Audit site two radio', 'post_status' => 'publish', 'post_author' => $owner ] );
restore_current_blog();
update_option( 'radioedu_conecta_settings', [ 'max_centros_per_user' => '1' ] );
L::flush_count_cache();
$main_over = L::has_reached_limit( $owner );
switch_to_blog( $site );
$switched_over = L::has_reached_limit( $owner );
L::flush_count_cache();
$fresh_over = L::has_reached_limit( $owner );
restore_current_blog();
net_out( 'cross_site_limit_cache', [ 'site_created_table' => $provisioned, 'main_empty_over_limit' => $main_over, 'subsite_with_one_cached_over_limit' => $switched_over, 'subsite_after_cache_flush' => $fresh_over, 'current_blog' => get_current_blog_id() ] );

$admin = (int) get_user_by( 'login', 'audit_e2e' )->ID;
wp_set_current_user( $admin );
net_out( 'site_admin_sync_denied', net_request()->get_status() );
wp_set_current_user( 0 );
net_out( 'anonymous_sync_denied', net_request()->get_status() );
wp_set_current_user( 1 );
$first = N::sync_site( $site, false );
$second = N::sync_site( $site, false );
net_out( 'sequential_sync', [ 'first' => $first, 'second' => $second, 'current_blog' => get_current_blog_id() ] );
switch_to_blog( $site );
net_out( 'subsite_sync_denied', net_request()->get_status() );
restore_current_blog();

$race_site = wpmu_create_blog( $network->domain, '/audit-race/', 'Audit Race Site', $owner, [], get_current_network_id() );
$nested = null;
$interleave = null;
$interleave = static function ( $data, $postarr ) use ( &$interleave, &$nested, $race_site ) {
    if ( 'radioedu_centro' === $data['post_type'] && 'Audit Race Site' === $data['post_title'] ) {
        remove_filter( 'wp_insert_post_data', $interleave );
        $nested = N::sync_site( $race_site, false );
    }
    return $data;
};
add_filter( 'wp_insert_post_data', $interleave, 10, 2 );
$outer = N::sync_site( $race_site, false );
remove_filter( 'wp_insert_post_data', $interleave );
$fichas = get_posts( [ 'post_type' => 'radioedu_centro', 'post_status' => 'any', 'posts_per_page' => 10, 'meta_key' => N::SOURCE_META, 'meta_value' => $race_site, 'fields' => 'ids' ] );
net_out( 'sync_interleaving', [ 'outer' => $outer, 'inner' => $nested, 'ficha_ids' => $fichas ] );

switch_to_blog( $race_site );
$table_name = T::get_table_name();
restore_current_blog();
wp_delete_site( $race_site );
global $wpdb;
net_out( 'delete_site_table', [ 'table' => $table_name, 'still_exists' => null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ), 'current_blog' => get_current_blog_id() ] );
Plugin::deactivate( true );
net_out( 'network_deactivation_retains_data', [ 'main_table' => net_table_exists(), 'main_centros' => (int) wp_count_posts( 'radioedu_centro' )->publish, 'current_blog' => get_current_blog_id() ] );
