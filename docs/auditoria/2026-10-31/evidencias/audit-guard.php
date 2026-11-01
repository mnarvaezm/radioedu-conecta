<?php
/** Isolated audit environment: no outgoing mail or HTTP. Not plugin code. */
defined( 'ABSPATH' ) || exit;
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'pre_http_request', static function () { return new WP_Error( 'audit_offline', 'External HTTP blocked in disposable audit.' ); } );
