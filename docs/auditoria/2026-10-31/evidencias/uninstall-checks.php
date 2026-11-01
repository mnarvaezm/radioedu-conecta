<?php
/** Execute only on the disposable audit site, with plugin skipped. */
defined( 'ABSPATH' ) || exit;
if ( 'RadioEdu-Audit' !== get_option( 'blogname' ) ) { throw new RuntimeException( 'Wrong environment.' ); }
global $wpdb;
$before_tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', '%radioedu_colaboraciones' ) );
$before_prefs = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE meta_value LIKE %s', $wpdb->usermeta, '%radioedu-conecta%' ) );
define( 'WP_UNINSTALL_PLUGIN', 'radioedu-conecta/radioedu-conecta.php' );
require WP_PLUGIN_DIR . '/radioedu-conecta/uninstall.php';
$site_results = [];
$sites = is_multisite() ? get_sites( [ 'fields' => 'ids', 'number' => 100 ] ) : [ get_current_blog_id() ];
foreach ( $sites as $site ) {
    if ( is_multisite() ) { switch_to_blog( $site ); }
    $site_results[] = [ 'site' => $site, 'centros' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_type = %s', $wpdb->posts, 'radioedu_centro' ) ), 'plugin_options' => $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $wpdb->options, 'radioedu_conecta_%' ) ), 'admin_cap' => get_role( 'administrator' )->has_cap( 'manage_radioedu' ) ];
    if ( is_multisite() ) { restore_current_blog(); }
}
echo wp_json_encode( [ 'multisite' => is_multisite(), 'tables_before' => $before_tables, 'tables_after' => $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', '%radioedu_colaboraciones' ) ), 'preferences_before' => $before_prefs, 'preferences_after' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE meta_value LIKE %s', $wpdb->usermeta, '%radioedu-conecta%' ) ), 'sites' => $site_results, 'current_blog' => get_current_blog_id() ], JSON_UNESCAPED_UNICODE ) . "\n";
