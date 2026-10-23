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
function clean_post_cache( $id ) {}
function delete_transient( $key ) { $GLOBALS['cleared'][] = $key; }
function is_wp_error( $v ) { return false; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
class FrmAppHelper {
 public static function maybe_json_decode( $value ) { return is_array( $value ) ? $value : json_decode( $value, true ); }
 public static function plugin_path() { return '/nonexistent-formidable-fixture'; }
}
class FrmDb {
 public static $writes = 0;
 public static $fail = false;
 public static $drop = false;
 public static function save_settings( $values, $group ) {
  if ( $group !== 'frm_styles' || array_keys( $values ) !== array( 'ID', 'post_content' ) ) { throw new RuntimeException( 'Unselected style identity or metadata write.' ); }
  ++self::$writes;
  if ( self::$fail ) { return 0; }
  if ( ! self::$drop ) { $GLOBALS['posts'][ $values['ID'] ]->post_content = json_encode( $values['post_content'] ); }
  return $values['ID'];
 }
}
require $native;
require dirname( __DIR__ ) . '/mcp-abilities-formidable.php';
mcp_register_formidable_abilities();
$GLOBALS['posts'] = array(
 42 => (object) array( 'ID' => 42, 'post_type' => 'frm_styles', 'post_title' => 'Selected', 'post_name' => 'selected', 'post_status' => 'draft', 'menu_order' => 1, 'post_content' => json_encode( array( 'font' => 'Arial', 'label_color' => '123456', 'border_color' => 'cccccc', 'field_height' => '34px', 'unrelated_extension_value' => 'preserve', 'custom_css' => 'literal\\path' ) ) ),
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
check( 0 === FrmDb::$writes && ! call_style( 'get-style', array( 'style_id' => 43 ) )['success'] && ! call_style( 'get-style', array( 'style_id' => 999 ) )['success'], 'read never creates a default style or reads another post type' );
$request = array( 'style_id' => 42, 'expected_style_hash' => $before['style_hash'], 'settings' => array( 'font' => '"Lexend Deca", sans-serif', 'field_font_size' => '18px', 'label_color' => '#abcdef' ), 'rebuild_css' => false );
$result = call_style( 'update-style', $request );
$expected = $before['style']['settings']; $expected['font'] = '"Lexend Deca", sans-serif'; $expected['field_font_size'] = '18px'; $expected['label_color'] = 'abcdef';
check( $result['success'] && $result['updated'] && $result['style']['settings'] === $expected, 'native model changes font, size and colour while preserving every other stored key' );
check( 'draft' === $result['style']['status'] && 'selected' === $result['style']['slug'] && $result['style']['is_default'], 'style status, slug and default selection stay unchanged' );
check( ! $result['css_rebuild_requested'] && ! isset( $GLOBALS['options']['frm_last_style_update'] ), 'explicitly disabled rebuild performs no generation' );
$count = FrmDb::$writes;
check( ! call_style( 'update-style', $request )['success'] && $count === FrmDb::$writes, 'stale source hash rejects a write before native storage' );
$request['expected_style_hash'] = $result['style_hash'];
check( ! call_style( 'update-style', $request )['updated'] && $count === FrmDb::$writes, 'same normalized settings are idempotent' );
foreach ( array( array( 'not_a_native_setting' => 'x' ), array( 'font' => array( 'nested' ) ), array( 'font' => str_repeat( 'x', 200001 ) ) ) as $bad ) {
 $request['settings'] = $bad;
 check( ! call_style( 'update-style', $request )['success'] && $count === FrmDb::$writes, 'unsupported, nested and oversized values are rejected without writes' );
}
$request['settings'] = array( 'font' => '"Lexend Deca' );
$request['rebuild_css'] = true;
$result = call_style( 'update-style', $request );
check( $result['success'] && '"Lexend Deca"' === $result['style']['settings']['font'] && $result['css_rebuild_requested'] && isset( $GLOBALS['options']['frm_last_style_update'] ), 'native balanced font quoting and native generation are invoked' );
$request['expected_style_hash'] = $result['style_hash']; $request['settings'] = array( 'font' => 'Georgia' );
FrmDb::$fail = true;
check( ! call_style( 'update-style', $request )['success'], 'native storage failure is reported' );
FrmDb::$fail = false; FrmDb::$drop = true;
check( ! call_style( 'update-style', $request )['success'], 'successful ID without persisted settings is detected by readback' );
exit( $failed ? 1 : 0 );
