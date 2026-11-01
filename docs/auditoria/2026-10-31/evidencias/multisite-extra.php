<?php
defined( 'ABSPATH' ) || exit;
if ( ! is_multisite() || 'RadioEdu-Audit' !== get_option( 'blogname' ) ) { throw new RuntimeException( 'Wrong environment.' ); }
use RadioEdu\Conecta\Network\NetworkSync as N;
use RadioEdu\Conecta\Database\ColaboracionesTable as T;
use RadioEdu\Conecta\Plugin;
$user = wp_insert_user( [ 'user_login' => 'auditsiteadmin', 'user_email' => 'admin-site@example.invalid', 'user_pass' => 'local-audit-only', 'role' => 'administrator' ] );
if ( is_wp_error( $user ) ) { throw new RuntimeException( $user->get_error_message() ); }
wp_set_current_user( $user );
$denied = rest_do_request( new WP_REST_Request( 'POST', '/radioedu-conecta/v1/network-sync' ) );
echo 'site_admin_actual: ' . wp_json_encode( [ 'is_super_admin' => is_super_admin(), 'can_manage_network_options' => current_user_can( 'manage_network_options' ), 'status' => $denied->get_status() ] ) . "\n";
wp_set_current_user( 1 );
// Prove individual activation path on a subsite after explicitly deactivating network activation.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
deactivate_plugins( 'radioedu-conecta/radioedu-conecta.php', false, true );
switch_to_blog( 2 );
$activation = activate_plugin( 'radioedu-conecta/radioedu-conecta.php', '', false );
global $wpdb;
$exists = null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( T::get_table_name() ) ) );
echo 'individual_activation: ' . wp_json_encode( [ 'error' => is_wp_error( $activation ), 'table_exists' => $exists, 'author_capability' => get_role( 'author' )->has_cap( 'propose_radioedu_colaboracion' ) ] ) . "\n";
restore_current_blog();
$network = get_network();
$site = wpmu_create_blog( $network->domain, '/audit-inactive/', 'Audit Inactive Site', 1, [], get_current_network_id() );
switch_to_blog( $site );
$exists = null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( T::get_table_name() ) ) );
echo 'new_site_without_network_activation: ' . wp_json_encode( [ 'table_exists' => $exists ] ) . "\n";
restore_current_blog();
