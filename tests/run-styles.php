<?php
/** Registered style abilities exercised with the unchanged installed FrmStyle model. */
define( 'ABSPATH', __DIR__ . '/' );
$native = getenv( 'MCP_FORMIDABLE_STYLE_FILE' );
if ( ! $native || ! is_file( $native ) ) { throw new RuntimeException( 'Provide MCP_FORMIDABLE_STYLE_FILE with an unchanged native FrmStyle.php.' ); }
function add_action( ...$args ) {}
function wp_register_ability( $name, $definition ) { $GLOBALS['abilities'][ $name ] = $definition; }
function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['caps'] ?? array(), true ); }
function get_post( $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? clone $GLOBALS['posts'][ $id ] : null; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_parse_args( $a, $b ) { return array_merge( $b, $a ); }
function apply_filters( $name, $v ) { return $v; }
function is_rtl() { return false; }
function sanitize_textarea_field( $v ) { return strip_tags( (string) $v ); }
function sanitize_text_field( $v ) { return strip_tags( (string) $v ); }
function sanitize_title( $v ) { return strtolower( preg_replace( '/[^a-zA-Z0-9-]+/', '-', $v ) ); }
function clean_post_cache( $id ) {}
function delete_transient( $key ) { $GLOBALS['cleared'][] = $key; }
function is_wp_error( $v ) { return false; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function wp_cache_get( ...$args ) { return false; }
function remove_all_filters( ...$args ) {}
function get_posts( $args ) { return array_values( array_filter( $GLOBALS['posts'], static fn( $p ) => 'frm_styles' === $p->post_type ) ); }
function wp_insert_post( $values ) {
 ++$GLOBALS['native_writes'];
 if ( $GLOBALS['native_fail'] ) { return 0; }
 if ( ! $GLOBALS['native_drop'] ) {
  // Native FrmDb calls wp_insert_post rather than wp_update_post. Omitted post
  // fields receive insert defaults even for an existing ID.
  $defaults = array( 'post_type' => 'post', 'post_title' => '', 'post_status' => 'draft', 'menu_order' => 0, 'post_author' => 20, 'post_name' => '' );
  $GLOBALS['posts'][ $values['ID'] ] = (object) array_replace( $defaults, wp_unslash( $values ) );
 }
 return $values['ID'];
}
$GLOBALS['native_writes'] = 0; $GLOBALS['native_fail'] = $GLOBALS['native_drop'] = false;
require dirname( $native ) . '/FrmAppHelper.php';
require dirname( $native ) . '/FrmDb.php';
require $native;
require getenv( 'MCP_FORMIDABLE_ADDON_FILE' ) ?: dirname( __DIR__ ) . '/mcp-abilities-formidable.php';
mcp_register_formidable_abilities();
$GLOBALS['posts'] = array(
 42 => (object) array( 'ID' => 42, 'post_type' => 'frm_styles', 'post_title' => 'Selected', 'post_name' => 'selected', 'post_status' => 'draft', 'menu_order' => 1, 'post_author' => 0, 'post_date' => '2024-01-01 12:00:00', 'guid' => 'https://example.com/frm_styles/selected', 'post_content' => json_encode( array( 'font' => 'Arial', 'label_color' => '123456', 'border_color' => 'cccccc', 'field_height' => '34px', 'unrelated_extension_value' => 'preserve', 'custom_css' => 'literal\\path' ) ) ),
 43 => (object) array( 'ID' => 43, 'post_type' => 'page', 'post_status' => 'publish' ),
);
$failed = 0;
function check( $ok, $label ) { echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { ++$GLOBALS['failed']; } }
function call_style( $name, $args ) { $a = $GLOBALS['abilities'][ 'formidable/' . $name ]; return $a['permission_callback']() ? $a['execute_callback']( $args ) : array( 'success' => false ); }
$GLOBALS['caps'] = array( 'edit_posts' );
check( ! call_style( 'get-style', array( 'style_id' => 42 ) )['success'], 'ordinary post editing does not grant native form editing' );
$GLOBALS['caps'] = array( 'frm_edit_forms' );
$before = call_style( 'get-style', array( 'style_id' => 42 ) );
check( $before['success'] && 'Arial' === $before['style']['settings']['font'] && ! isset( $before['style']['settings']['font_size'] ) && isset( $before['effective_settings']['font_size'] ), 'read distinguishes stored settings from native defaults without saving' );
check( 0 === $GLOBALS['native_writes'] && ! call_style( 'get-style', array( 'style_id' => 43 ) )['success'] && ! call_style( 'get-style', array( 'style_id' => 999 ) )['success'], 'read never creates a default style or reads another post type' );
$request = array( 'style_id' => 42, 'expected_style_hash' => $before['style_hash'], 'settings' => array( 'font' => '"Lexend Deca", sans-serif', 'field_font_size' => '18px', 'label_color' => '#abcdef' ), 'rebuild_css' => false );
$result = call_style( 'update-style', $request );
$expected = $before['style']['settings']; $expected['font'] = '"Lexend Deca", sans-serif'; $expected['field_font_size'] = '18px'; $expected['label_color'] = 'abcdef';
check( $result['success'] && $result['updated'] && $result['style']['settings'] === $expected, 'native model changes font, size and colour while preserving every other stored key' );
check( 'draft' === $result['style']['status'] && 'selected' === $result['style']['slug'] && $result['style']['is_default'], 'style status, slug and default selection stay unchanged' );
check( ! $result['css_rebuild_requested'] && ! isset( $GLOBALS['options']['frm_last_style_update'] ), 'explicitly disabled rebuild performs no generation' );
$count = $GLOBALS['native_writes'];
check( ! call_style( 'update-style', $request )['success'] && $count === $GLOBALS['native_writes'], 'stale source hash rejects a write before native storage' );
$request['expected_style_hash'] = $result['style_hash'];
check( ! call_style( 'update-style', $request )['updated'] && $count === $GLOBALS['native_writes'], 'same normalized settings are idempotent' );
foreach ( array( array( 'not_a_native_setting' => 'x' ), array( 'font' => array( 'nested' ) ), array( 'font' => str_repeat( 'x', 200001 ) ) ) as $bad ) {
 $request['settings'] = $bad;
 check( ! call_style( 'update-style', $request )['success'] && $count === $GLOBALS['native_writes'], 'unsupported, nested and oversized values are rejected without writes' );
}
$request['settings'] = array( 'font' => '"Lexend Deca' );
$request['rebuild_css'] = true;
$result = call_style( 'update-style', $request );
check( $result['success'] && '"Lexend Deca"' === $result['style']['settings']['font'] && $result['css_rebuild_requested'] && isset( $GLOBALS['options']['frm_last_style_update'] ), 'native balanced font quoting and native generation are invoked' );
$request['expected_style_hash'] = $result['style_hash']; $request['settings'] = array( 'font' => 'Georgia' );
$GLOBALS['native_fail'] = true;
check( ! call_style( 'update-style', $request )['success'], 'native storage failure is reported' );
$GLOBALS['native_fail'] = false; $GLOBALS['native_drop'] = true;
check( ! call_style( 'update-style', $request )['success'], 'successful ID without persisted settings is detected by readback' );
$GLOBALS['native_drop'] = false;
$GLOBALS['caps'] = array( 'manage_options', 'edit_post', 'publish_posts', 'edit_others_posts' );
$native_settings = json_decode( wp_unslash( FrmAppHelper::prepare_and_encode( wp_slash( ( new FrmStyle() )->get_defaults() ) ) ), true );
$GLOBALS['posts'][44] = (object) array( 'ID' => 44, 'post_type' => 'post', 'post_title' => '', 'post_name' => 'recover-style', 'post_status' => 'draft', 'post_author' => 20, 'menu_order' => 0, 'post_content' => json_encode( $native_settings ) );
$metadata = array( 'post_title' => 'Restored style', 'post_name' => 'recover-style', 'post_status' => 'publish', 'menu_order' => 0, 'post_author' => 0, 'post_date' => '2024-01-01 12:00:00' );
$recovery = array( 'style_id' => 44, 'metadata' => $metadata );
$count = $GLOBALS['native_writes']; $preview = call_style( 'restore-style', $recovery );
check( $preview['success'] && ! $preview['applied'] && $count === $GLOBALS['native_writes'], 'recognized incomplete native save has a read-only metadata preview' );
$recovery['confirm'] = true; $recovery['expected_post_hash'] = str_repeat( '0', 64 );
check( ! call_style( 'restore-style', $recovery )['success'] && $count === $GLOBALS['native_writes'], 'stale metadata recovery never writes' );
$recovery['expected_post_hash'] = $preview['current_post_hash'];
$restored = call_style( 'restore-style', $recovery );
check( $restored['success'] && $restored['style']['title'] === 'Restored style' && $restored['style']['status'] === 'publish' && $GLOBALS['posts'][44]->post_type === 'frm_styles', 'native restoration preserves style settings and restores reviewed metadata' );
check( ! call_style( 'restore-style', array( 'style_id' => 43, 'metadata' => $metadata ) )['success'], 'page objects cannot be converted to styles' );
check( ! call_style( 'restore-style', array( 'style_id' => 42, 'metadata' => array( 'post_name' => 'changed-scope' ) ) )['success'], 'custom CSS prevents a style scope rename' );
$rename = array( 'style_id' => 44, 'metadata' => array( 'post_name' => 'restored-unique-style', 'menu_order' => 0 ) );
$preview = call_style( 'restore-style', $rename );
$rename['confirm'] = true; $rename['expected_post_hash'] = $preview['current_post_hash'];
$renamed = call_style( 'restore-style', $rename );
check( $renamed['success'] && $renamed['style']['slug'] === 'restored-unique-style' && $renamed['style']['settings'] === $restored['style']['settings'], 'empty custom-CSS style can be renamed without changing its settings' );
exit( $failed ? 1 : 0 );
