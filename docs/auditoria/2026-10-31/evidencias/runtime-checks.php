<?php
/** Disposable-local audit probes. Execute via WP-CLI eval-file, never on a real site. */
defined( 'ABSPATH' ) || exit;
if ( 'RadioEdu-Audit' !== get_option( 'blogname' ) ) { throw new RuntimeException( 'Wrong audit environment.' ); }
use RadioEdu\Conecta\Capabilities\CentroLimit;
use RadioEdu\Conecta\Database\ColaboracionesTable as Table;
use RadioEdu\Conecta\Rest\ColaboracionesController as Collaborations;
use RadioEdu\Conecta\Privacy\PrivacyPolicy;
use RadioEdu\Conecta\Emails\NotificationService;

function audit_out( $label, $data ) { echo $label . ': ' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) . "\n"; }
function audit_rest( $method, $path, $params = [] ) {
    $request = new WP_REST_Request( $method, $path );
    foreach ( $params as $key => $value ) { $request->set_param( $key, $value ); }
    return rest_do_request( $request );
}
function audit_center( $author, $title, $status = 'publish' ) {
    return wp_insert_post( [ 'post_type' => 'radioedu_centro', 'post_title' => $title, 'post_author' => $author, 'post_status' => $status ] );
}
$a = wp_insert_user( [ 'user_login' => 'audit_author_a', 'user_email' => 'author-a@example.invalid', 'user_pass' => 'audit-only', 'role' => 'author' ] );
$b = wp_insert_user( [ 'user_login' => 'audit_author_b', 'user_email' => 'author-b@example.invalid', 'user_pass' => 'audit-only', 'role' => 'author' ] );
$s = wp_insert_user( [ 'user_login' => 'audit_subscriber', 'user_email' => 'subscriber@example.invalid', 'user_pass' => 'audit-only', 'role' => 'subscriber' ] );
if ( is_wp_error( $a ) || is_wp_error( $b ) || is_wp_error( $s ) ) { throw new RuntimeException( 'Run once on a fresh audit database.' ); }
$ca = audit_center( $a, 'Audit A' );
$cb = audit_center( $b, 'Audit B' );
update_post_meta( $ca, '_radioedu_coordinador_email', 'author-a@example.invalid' );
update_post_meta( $ca, '_radioedu_coordinador_nombre', 'Audit Author A' );
update_post_meta( $cb, '_radioedu_coordinador_email', 'author-b@example.invalid' );
$payload = [ 'centro_emisor_id' => $ca, 'centro_receptor_id' => $cb, 'idea_proyecto' => 'Audit dummy project' ];
wp_set_current_user( 0 );
audit_out( 'anonymous_proposal', audit_rest( 'POST', '/radioedu-conecta/v1/colaboraciones', $payload )->get_status() );
wp_set_current_user( $s );
audit_out( 'subscriber_proposal', audit_rest( 'POST', '/radioedu-conecta/v1/colaboraciones', $payload )->get_status() );
wp_set_current_user( $b );
audit_out( 'other_author_sender', audit_rest( 'POST', '/radioedu-conecta/v1/colaboraciones', $payload )->get_status() );
wp_set_current_user( $a );
$made = audit_rest( 'POST', '/radioedu-conecta/v1/colaboraciones', $payload );
$id = $made->get_data()['id'];
audit_out( 'own_proposal', $made->get_status() );
audit_out( 'sender_response_forbidden', audit_rest( 'PATCH', '/radioedu-conecta/v1/colaboraciones/' . $id, [ 'estado' => 'aceptada' ] )->get_status() );
wp_set_current_user( $b );
audit_out( 'recipient_response', audit_rest( 'PATCH', '/radioedu-conecta/v1/colaboraciones/' . $id, [ 'estado' => 'aceptada', 'respuesta' => 'Audit B authored personal response' ] )->get_status() );
audit_out( 'repeat_response', audit_rest( 'PATCH', '/radioedu-conecta/v1/colaboraciones/' . $id, [ 'estado' => 'rechazada' ] )->get_status() );
$export_b = PrivacyPolicy::export_personal_data( 'author-b@example.invalid' );
$erase_b = PrivacyPolicy::erase_personal_data( 'author-b@example.invalid' );
audit_out( 'recipient_privacy', [ 'export_contains_response' => str_contains( wp_json_encode( $export_b ), 'Audit B authored personal response' ), 'response_after_erase' => Table::get_by_id( $id )->respuesta_mensaje, 'erase' => $erase_b, 'resolved_contact_after_erase' => NotificationService::get_center_contact_email( $cb ) ] );

wp_set_current_user( $a );
update_option( 'radioedu_conecta_settings', [ 'max_centros_per_user' => '1', 'notify_on_proposal' => '0', 'notify_on_status' => '0' ] );
CentroLimit::flush_count_cache();
$trashed = audit_rest( 'DELETE', '/wp/v2/centros/' . $ca );
$new = audit_rest( 'POST', '/wp/v2/centros', [ 'title' => 'Audit Replacement', 'status' => 'publish' ] );
$restored = audit_rest( 'POST', '/wp/v2/centros/' . $ca, [ 'status' => 'publish' ] );
audit_out( 'limit_restore', [ 'trash_status' => $trashed->get_status(), 'create_status' => $new->get_status(), 'restore_status' => $restored->get_status(), 'active_count' => count( get_posts( [ 'post_type' => 'radioedu_centro', 'post_status' => 'publish', 'author' => $a, 'posts_per_page' => 10 ] ) ) ] );

update_option( 'radioedu_conecta_settings', [ 'max_centros_per_user' => '', 'notify_on_proposal' => '0', 'notify_on_status' => '0' ] );
$many = [];
for ( $i = 0; $i < 51; ++$i ) { $many[] = audit_center( $a, 'Audit Many ' . $i ); }
$managed = Collaborations::get_user_managed_center_ids( $a );
$missing = array_values( array_diff( $many, $managed ) );
audit_out( 'managed_center_cap', [ 'actual_published' => count( get_posts( [ 'post_type' => 'radioedu_centro', 'post_status' => 'publish', 'author' => $a, 'posts_per_page' => 100 ] ) ), 'listed' => count( $managed ), 'omitted' => $missing, 'can_manage_omitted' => $missing ? Collaborations::user_can_manage_center( $missing[0], $a ) : null ] );

global $wpdb;
for ( $i = 0; $i < 101; ++$i ) {
    $wpdb->insert( Table::get_table_name(), [ 'centro_emisor_id' => $ca, 'centro_receptor_id' => $cb, 'usuario_emisor_id' => $a, 'idea_proyecto' => 'Audit List ' . $i, 'estado' => 'pendiente', 'created_at' => gmdate( 'Y-m-d H:i:s', time() + $i ), 'updated_at' => current_time( 'mysql' ) ] );
}
$first = audit_rest( 'GET', '/radioedu-conecta/v1/colaboraciones' )->get_data()['colaboraciones'];
$second = audit_rest( 'GET', '/radioedu-conecta/v1/colaboraciones', [ 'page' => 2 ] )->get_data()['colaboraciones'];
audit_out( 'proposal_pagination', [ 'rows' => count( $first ), 'page_2_identical' => array_column( $first, 'id' ) === array_column( $second, 'id' ), 'database_rows' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', Table::get_table_name() ) ) ] );

wp_set_current_user( 1 );
$sensitive = audit_center( $b, 'Audit Private', 'private' );
update_post_meta( $sensitive, '_radioedu_coordinador_email', 'hidden@example.invalid' );
wp_set_current_user( $a );
$core = audit_rest( 'GET', '/wp/v2/centros/' . $cb, [ 'context' => 'edit' ] );
audit_out( 'core_other_edit_meta', [ 'status' => $core->get_status(), 'private_key_present' => str_contains( wp_json_encode( $core->get_data() ), '_radioedu_coordinador_email' ) ] );
wp_set_current_user( 0 );
$public = audit_rest( 'GET', '/radioedu-conecta/v1/centros', [ 'per_page' => 100 ] )->get_data();
audit_out( 'public_privacy', [ 'private_post_present' => in_array( $sensitive, array_column( $public['centros'], 'id' ), true ), 'contact_present' => str_contains( wp_json_encode( $public ), 'example.invalid' ) ] );

wp_set_current_user( 1 );
$real = wp_insert_post( [ 'post_type' => 'radioedu_centro', 'post_status' => 'publish', 'post_name' => 'ies-sierra-de-gata-radio', 'post_title' => 'Real station edited by owner', 'post_content' => 'Original manually created content' ] );
update_post_meta( $real, '_radioedu_podcast_url', 'https://radioedu.educarex.es/sierradegata' );
RadioEdu\Conecta\Admin\DemoDataImporter::import_demo_centers();
audit_out( 'demo_ownership', [ 'title_after_import' => get_the_title( $real ), 'original_content_retained' => str_contains( get_post_field( 'post_content', $real ), 'Original manually created content' ), 'marked_demo' => get_post_meta( $real, '_radioedu_conecta_demo', true ) ] );

Table::drop_table();
$block_create = static function ( $query ) { return str_starts_with( $query, 'CREATE TABLE ' . Table::get_table_name() ) ? 'AUDIT SIMULATED DDL FAILURE' : $query; };
$old_suppress = $wpdb->suppress_errors( true );
add_filter( 'query', $block_create );
Table::create_table();
remove_filter( 'query', $block_create );
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( Table::get_table_name() ) ) );
audit_out( 'failed_migration', [ 'table_exists' => null !== $exists, 'persisted_version' => get_option( 'radioedu_conecta_db_version' ) ] );
$wpdb->suppress_errors( $old_suppress );
Table::create_table();
