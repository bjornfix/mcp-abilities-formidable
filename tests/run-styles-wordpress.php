<?php
/** Native WordPress and unchanged Formidable storage integration; dev fixtures only. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) { throw new RuntimeException( 'Run through the authorized dev WP-CLI environment.' ); }
$native_dir = getenv( 'MCP_FORMIDABLE_NATIVE_DIR' );
foreach ( array( 'FrmAppHelper.php', 'FrmDb.php', 'live-FrmStyle.php' ) as $file ) { require_once $native_dir . '/' . $file; }
if ( ! function_exists( 'mcp_register_formidable_abilities' ) ) { require WP_PLUGIN_DIR . '/mcp-abilities-formidable/mcp-abilities-formidable.php'; }
register_post_type( 'frm_styles', array( 'public' => false, 'supports' => array( 'title' ) ) );
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $admins ) { throw new RuntimeException( 'Existing dev administrator required.' ); }
wp_set_current_user( (int) $admins[0] );
wp_get_ability( 'formidable/get-style' );
$created = array();
$old_css = get_option( 'frm_last_style_update', null );
function mcp_formidable_style_contract( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } echo 'PASS ' . $message . "\n"; }
try {
	$model = new FrmStyle();
	$settings = $model->get_defaults();
	$settings['font'] = 'Arial';
	$settings['custom_css'] = '.fixture::after {content:"C:\\exports\\file";}';
	$id = $model->save( wp_slash( array( 'post_type' => 'frm_styles', 'post_status' => 'draft', 'post_title' => 'Native style fixture', 'post_name' => 'mcp-native-style-fixture', 'post_date' => '2024-01-01 12:00:00', 'post_author' => 0, 'post_content' => $settings, 'menu_order' => 0 ) ) );
	if ( ! $id || is_wp_error( $id ) ) { throw new RuntimeException( 'Native fixture creation failed.' ); }
	$created[] = (int) $id;
	$post_before = get_post( $id );
	$before = wp_get_ability( 'formidable/get-style' )->execute( array( 'style_id' => (int) $id ) );
	mcp_formidable_style_contract( $before['success'], 'native style is readable' );
	$changed = wp_get_ability( 'formidable/update-style' )->execute( array( 'style_id' => (int) $id, 'expected_style_hash' => $before['style_hash'], 'settings' => array( 'font' => '"Lexend Deca", sans-serif' ), 'rebuild_css' => false ) );
	mcp_formidable_style_contract( $changed['success'], 'native style settings save succeeds' );
	$expected = $before['style']['settings']; $expected['font'] = '"Lexend Deca", sans-serif';
	mcp_formidable_style_contract( $changed['style']['settings'] === $expected, 'all unedited settings and literal CSS backslashes survive native JSON storage' );
	$post_after = get_post( $id );
	foreach ( array( 'post_type', 'post_title', 'post_status', 'post_name', 'post_date', 'post_author', 'guid', 'menu_order' ) as $key ) {
		$old = (string) $post_before->{$key}; $new = (string) $post_after->{$key};
		// WordPress esc_url() encodes ampersands in a GUID on a subsequent save.
		if ( 'guid' === $key ) { $old = html_entity_decode( $old, ENT_QUOTES | ENT_HTML5, 'UTF-8' ); $new = html_entity_decode( $new, ENT_QUOTES | ENT_HTML5, 'UTF-8' ); }
		mcp_formidable_style_contract( $old === $new, 'native post metadata preserved: ' . $key );
	}
	wp_update_post( array( 'ID' => $id, 'post_type' => 'post', 'post_title' => '', 'post_status' => 'draft', 'menu_order' => 0 ) );
	$metadata = array();
	foreach ( array( 'post_title', 'post_status', 'post_name', 'post_date', 'post_date_gmt', 'post_author', 'guid', 'menu_order', 'comment_status', 'ping_status' ) as $key ) { $metadata[ $key ] = $post_before->{$key}; }
	$request = array( 'style_id' => (int) $id, 'metadata' => $metadata );
	$preview = wp_get_ability( 'formidable/restore-style' )->execute( $request );
	mcp_formidable_style_contract( $preview['success'] && ! $preview['applied'], 'native metadata restoration previews without a write' );
	$request['confirm'] = true; $request['expected_post_hash'] = $preview['current_post_hash'];
	$restored = wp_get_ability( 'formidable/restore-style' )->execute( $request );
	mcp_formidable_style_contract( $restored['success'] && get_post_type( $id ) === 'frm_styles' && $restored['style']['settings'] === $expected, 'native metadata restoration preserves the complete settings object' );
	$blocked = wp_get_ability( 'formidable/restore-style' )->execute( array( 'style_id' => (int) $id, 'metadata' => array( 'post_name' => 'must-not-rescope-custom-css' ) ) );
	mcp_formidable_style_contract( ! $blocked['success'], 'native custom CSS scope stays protected' );
	$clean_settings = $model->get_defaults();
	$clean_settings['custom_css'] = ''; $clean_settings['single_style_custom_css'] = '';
	$clean_id = $model->save( wp_slash( array( 'post_type' => 'frm_styles', 'post_status' => 'draft', 'post_title' => 'Clean style fixture', 'post_name' => 'mcp-clean-style-fixture', 'post_content' => $clean_settings, 'menu_order' => 0 ) ) );
	$created[] = (int) $clean_id;
	$clean_before = wp_get_ability( 'formidable/get-style' )->execute( array( 'style_id' => (int) $clean_id ) );
	$rename_request = array( 'style_id' => (int) $clean_id, 'metadata' => array( 'post_name' => 'mcp-clean-style-renamed', 'menu_order' => 0 ) );
	$rename_preview = wp_get_ability( 'formidable/restore-style' )->execute( $rename_request );
	$rename_request['confirm'] = true; $rename_request['expected_post_hash'] = $rename_preview['current_post_hash'];
	$renamed = wp_get_ability( 'formidable/restore-style' )->execute( $rename_request );
	mcp_formidable_style_contract( $renamed['success'] && $renamed['style']['slug'] === 'mcp-clean-style-renamed' && $renamed['style']['settings'] === $clean_before['style']['settings'], 'native style rename preserves settings when custom CSS is empty' );
	echo "Native WordPress/Formidable style integration passed.\n";
} finally {
	foreach ( $created as $id ) { wp_delete_post( $id, true ); }
	if ( null === $old_css ) { delete_option( 'frm_last_style_update' ); } else { update_option( 'frm_last_style_update', $old_css ); }
}
