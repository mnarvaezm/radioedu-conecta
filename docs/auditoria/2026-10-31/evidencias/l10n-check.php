<?php
defined( 'ABSPATH' ) || exit;
if ( 'RadioEdu-Audit' !== get_option( 'blogname' ) ) { throw new RuntimeException( 'Wrong environment.' ); }
require_once ABSPATH . WPINC . '/pomo/mo.php';
foreach ( [ 'ca', 'ca_valencia', 'gl_ES', 'eu' ] as $locale ) {
    $stem = WP_PLUGIN_DIR . '/radioedu-conecta/languages/radioedu-conecta-' . $locale;
    $compiled = require $stem . '.l10n.php';
    $mo = new MO();
    $mo->import_from_file( $stem . '.mo' );
    $mismatches = [];
    foreach ( $mo->entries as $key => $entry ) {
        if ( ( $compiled['messages'][$key] ?? null ) !== implode( "\0", $entry->translations ) ) { $mismatches[] = $key; }
    }
    echo wp_json_encode( [ 'locale' => $locale, 'php_entries' => count( $compiled['messages'] ), 'mo_entries' => count( $mo->entries ), 'mismatches' => $mismatches ] ) . "\n";
}
