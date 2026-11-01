<?php
/** Local disposable audit probes. Never use on a real site. */
defined( 'ABSPATH' ) || exit;
if ( 'RadioEdu-Audit' !== get_option( 'blogname' ) ) { throw new RuntimeException( 'Wrong environment.' ); }
use RadioEdu\Conecta\Rest\ColaboracionesController as C;
use RadioEdu\Conecta\Database\ColaboracionesTable as T;
function probe_out( $key, $value ) { echo $key . ': ' . wp_json_encode( $value, JSON_UNESCAPED_UNICODE ) . "\n"; }
function probe_rest( $method, $path, $params = [] ) {
    $r = new WP_REST_Request( $method, $path );
    foreach ( $params as $k => $v ) { $r->set_param( $k, $v ); }
    return rest_do_request( $r );
}
wp_set_current_user( 1 );
$centers = get_posts( [ 'post_type' => 'radioedu_centro', 'post_status' => 'publish', 'posts_per_page' => 100 ] );
foreach ( $centers as $index => $center ) {
    update_post_meta( $center->ID, '_radioedu_lat', (string) ( 39 + $index / 100 ) );
    update_post_meta( $center->ID, '_radioedu_lng', (string) ( -6 + $index / 100 ) );
}
$sender = $centers[0]->ID;
$receiver = $centers[1]->ID;
$payload = [ 'centro_emisor_id' => $sender, 'centro_receptor_id' => $receiver, 'idea_proyecto' => 'Audit race dummy' ];
set_transient( 'radioedu_rate_1', [ 'count' => 9, 'expires' => time() + 3600 ], 3600 );
// Deterministic interleaving: request B runs after A reads 9 but before A inserts/increments.
$nested = null;
$interleave = null;
$interleave = static function ( $query ) use ( &$interleave, &$nested, $payload ) {
    if ( str_starts_with( $query, 'INSERT INTO `' . T::get_table_name() . '`' ) ) {
        remove_filter( 'query', $interleave );
        $nested = probe_rest( 'POST', '/radioedu-conecta/v1/colaboraciones', $payload );
    }
    return $query;
};
add_filter( 'query', $interleave );
$outer = probe_rest( 'POST', '/radioedu-conecta/v1/colaboraciones', $payload );
remove_filter( 'query', $interleave );
probe_out( 'rate_interleaving', [ 'start_count' => 9, 'outer_status' => $outer->get_status(), 'inner_status' => $nested ? $nested->get_status() : null, 'counter' => get_transient( 'radioedu_rate_1' ), 'distinct_ids' => [ $outer->get_data()['id'] ?? null, $nested ? ( $nested->get_data()['id'] ?? null ) : null ] ] );
probe_out( 'notification_disabled_message', $outer->get_data()['message'] ?? '' );
delete_transient( 'radioedu_rate_1' );
global $wpdb;
$block_update = static function ( $query ) { return str_starts_with( $query, 'UPDATE `' . T::get_table_name() . '`' ) ? 'AUDIT SIMULATED UPDATE FAILURE' : $query; };
$saved = $wpdb->suppress_errors( true );
add_filter( 'query', $block_update );
$failed = C::respond_to_proposal( $outer->get_data()['id'], 'aceptada', 'Audit response' );
remove_filter( 'query', $block_update );
$wpdb->suppress_errors( $saved );
probe_out( 'failed_response', [ 'code' => is_wp_error( $failed ) ? $failed->get_error_code() : null, 'status' => is_wp_error( $failed ) ? $failed->get_error_data()['status'] : null, 'stored_state' => T::get_by_id( $outer->get_data()['id'] )->estado ] );

$ids = [];
foreach ( [ '000 Audit Reference', '001 Audit Unrelated', 'ZZZ Audit Related' ] as $title ) {
    $ids[] = wp_insert_post( [ 'post_type' => 'radioedu_centro', 'post_status' => 'publish', 'post_title' => $title ] );
}
wp_set_object_terms( $ids[0], [ 'Audit Unique Topic' ], 'radioedu_tematica' );
wp_set_object_terms( $ids[2], [ 'Audit Unique Topic' ], 'radioedu_tematica' );
$list = probe_rest( 'GET', '/radioedu-conecta/v1/centros', [ 'orden' => 'afines', 'per_page' => 100 ] )->get_data()['centros'];
probe_out( 'admin_default_affinity', [ 'expected_alphabetic' => $ids, 'actual_relative_order' => array_values( array_intersect( array_column( $list, 'id' ), $ids ) ) ] );
foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
$fake = wp_insert_post( [ 'post_type' => 'radioedu_centro', 'post_status' => 'publish', 'post_title' => 'Unrelated San Jose', 'post_name' => 'unrelated-san-jose' ] );
$list = probe_rest( 'GET', '/radioedu-conecta/v1/centros', [ 'search' => 'Unrelated San Jose' ] )->get_data()['centros'];
probe_out( 'unmarked_demo_image', [ 'marker' => get_post_meta( $fake, '_radioedu_conecta_demo', true ), 'logo' => $list[0]['logo_radio'] ?? null ] );
wp_delete_post( $fake, true );
probe_out( 'rendered_embed_kses', [ 'before' => '<figure><iframe src="https://example.invalid/embed" title="Podcast"></iframe></figure>', 'after' => wp_kses_post( '<figure><iframe src="https://example.invalid/embed" title="Podcast"></iframe></figure>' ) ] );
