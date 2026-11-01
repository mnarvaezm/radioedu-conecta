<?php
/** Disposable audit fixture preparation. */
defined( 'ABSPATH' ) || exit;
if ( 'RadioEdu-Audit' !== get_option( 'blogname' ) ) { throw new RuntimeException( 'Wrong environment.' ); }
wp_set_current_user( 1 );
foreach ( get_posts( [ 'post_type' => 'radioedu_centro', 'posts_per_page' => 100, 'post_status' => 'publish' ] ) as $p ) {
    wp_update_post( [ 'ID' => $p->ID, 'post_status' => 'draft' ] );
}
$user = wp_insert_user( [ 'user_login' => 'audit_e2e', 'user_email' => 'e2e@example.invalid', 'user_pass' => 'local-audit-only', 'role' => 'administrator' ] );
echo is_wp_error( $user ) ? $user->get_error_message() : 'Prepared E2E fixture with fresh administrator and no published centers.';
