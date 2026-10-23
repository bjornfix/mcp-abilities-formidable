<?php
/**
 * Plugin Name: MCP Abilities - Formidable
 * Plugin URI: https://devenia.com/plugins/mcp-abilities-formidable/
 * Description: Formidable Forms abilities for MCP. Inspect forms, styles, settings, usage, and CSS cache/runtime behavior.
 * Version: 1.3.1
 * Author: basicus
 * Author URI: https://profiles.wordpress.org/basicus/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.9
 * Requires PHP: 8.0
 *
 * @package MCP_Abilities_Formidable
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', static function () {
	require_once __DIR__ . '/includes/devenia-updater-notice.php';
	mcp_abilities_formidable_Updater_Notice::register( __FILE__ );
} );

/**
 * Check if Abilities API is available.
 */
function mcp_formidable_check_dependencies(): bool {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p><strong>MCP Abilities - Formidable</strong> requires WordPress 6.9 or newer with the <a href="https://developer.wordpress.org/apis/abilities-api/">Abilities API</a> available.</p></div>';
			}
		);
		return false;
	}

	return true;
}

/**
 * Check if Formidable is active.
 */
function mcp_formidable_is_active(): bool {
	return class_exists( 'FrmAppHelper' ) || class_exists( 'FrmForm' ) || defined( 'FRM_VERSION' );
}

/**
 * Return a standard inactive error response.
 */
function mcp_formidable_require_active(): ?array {
	if ( mcp_formidable_is_active() ) {
		return null;
	}

	return array(
		'success' => false,
		'message' => 'Formidable Forms plugin is not active.',
	);
}

/**
 * Get Formidable settings as an object.
 *
 * @return object
 */
function mcp_formidable_get_settings_object(): object {
	if ( class_exists( 'FrmAppHelper' ) && method_exists( 'FrmAppHelper', 'get_settings' ) ) {
		$settings = FrmAppHelper::get_settings();
		if ( is_object( $settings ) ) {
			return $settings;
		}
	}

	$settings = get_option( 'frm_options', array() );
	if ( is_object( $settings ) ) {
		return $settings;
	}

	return (object) ( is_array( $settings ) ? $settings : array() );
}

/**
 * Normalize raw settings options for response payloads.
 *
 * @param mixed $value Option value.
 * @return array<string,mixed>
 */
function mcp_formidable_normalize_option_value( $value ): array {
	if ( is_object( $value ) ) {
		return get_object_vars( $value );
	}

	if ( is_array( $value ) ) {
		return $value;
	}

	return array();
}

/**
 * Return sanitized effective Formidable settings.
 *
 * @return array<string,mixed>
 */
function mcp_formidable_get_effective_settings(): array {
	$settings = mcp_formidable_get_settings_object();

	return array(
		'load_style'    => isset( $settings->load_style ) ? (string) $settings->load_style : 'all',
		'custom_style'  => isset( $settings->custom_style ) ? (bool) $settings->custom_style : false,
		'custom_css'    => isset( $settings->custom_css ) ? (string) $settings->custom_css : '',
		'jquery_css'    => isset( $settings->jquery_css ) ? (int) $settings->jquery_css : 0,
		'old_css'       => isset( $settings->old_css ) ? (bool) $settings->old_css : false,
		'accordion_js'  => isset( $settings->accordion_js ) ? (int) $settings->accordion_js : 0,
		'last_css_hash' => (string) get_option( 'frm_last_style_update', '' ),
	);
}

/**
 * Whitelist supported settings updates.
 *
 * @return array<string,string>
 */
function mcp_formidable_allowed_setting_sanitizers(): array {
	return array(
		'load_style'   => 'load_style',
		'custom_style' => 'bool',
		'custom_css'   => 'string',
		'jquery_css'   => 'bool',
		'old_css'      => 'bool',
		'accordion_js' => 'bool',
	);
}

/**
 * Sanitize a settings value for storage.
 *
 * @param string $key   Setting key.
 * @param mixed  $value Raw value.
 * @return mixed
 */
function mcp_formidable_sanitize_setting_value( string $key, $value ) {
	$mode = mcp_formidable_allowed_setting_sanitizers()[ $key ] ?? null;

	if ( 'load_style' === $mode ) {
		$value   = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		$allowed = array( 'all', 'dynamic', 'none' );
		return in_array( $value, $allowed, true ) ? $value : 'all';
	}

	if ( 'bool' === $mode ) {
		return empty( $value ) ? 0 : 1;
	}

	if ( 'string' === $mode ) {
		return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
	}

	return $value;
}

/**
 * Store supported properties through Formidable's shared settings object.
 *
 * @param array<string,mixed> $changes Sanitized setting changes.
 * @return array<string,mixed>
 */
function mcp_formidable_store_settings( array $changes ): array {
	$settings = mcp_formidable_get_settings_object();
	if ( ! is_callable( array( $settings, 'store' ) ) ) {
		return array( 'success' => false, 'message' => 'Formidable settings storage API is unavailable.' );
	}
	foreach ( $changes as $key => $value ) {
		if ( ! property_exists( $settings, $key ) ) {
			return array( 'success' => false, 'message' => 'Setting is unavailable in this Formidable version: ' . $key );
		}
	}
	$previous = clone $settings;
	foreach ( $changes as $key => $value ) {
		$settings->{$key} = $value;
	}
	$settings->store();
	$saved = mcp_formidable_normalize_option_value( get_option( 'frm_options', array() ) );
	foreach ( $changes as $key => $value ) {
		if ( ! array_key_exists( $key, $saved ) || $saved[ $key ] !== $value ) {
			foreach ( $changes as $restore_key => $unused ) {
				$settings->{$restore_key} = $previous->{$restore_key};
			}
			mcp_formidable_clear_runtime_transients();
			return array( 'success' => false, 'message' => 'Formidable settings could not be saved.' );
		}
	}
	return array( 'success' => true );
}

/**
 * Clear Formidable CSS and settings transients.
 */
function mcp_formidable_clear_runtime_transients(): void {
	delete_transient( 'frmpro_css' );
	delete_transient( 'frm_options' );
	delete_transient( 'frmpro_options' );
}

/**
 * Clear Formidable field caches and form field transients after field writes.
 *
 * @param int $field_id Field ID.
 * @param int $form_id  Form ID.
 */
function mcp_formidable_clear_field_runtime_cache( int $field_id, int $form_id = 0 ): void {
	wp_cache_delete( $field_id, 'frm_field' );

	if ( class_exists( 'FrmDb' ) && method_exists( 'FrmDb', 'cache_delete_group' ) ) {
		FrmDb::cache_delete_group( 'frm_field' );
	}

	if ( $form_id && class_exists( 'FrmField' ) && method_exists( 'FrmField', 'delete_form_transient' ) ) {
		FrmField::delete_form_transient( $form_id );
	}
}

/**
 * Clear Formidable form caches after form writes.
 *
 * @param int $form_id Form ID.
 */
function mcp_formidable_clear_form_runtime_cache( int $form_id ): void {
	wp_cache_delete( $form_id, 'frm_form' );

	if ( class_exists( 'FrmDb' ) && method_exists( 'FrmDb', 'cache_delete_group' ) ) {
		FrmDb::cache_delete_group( 'frm_form' );
	}

	mcp_formidable_clear_field_runtime_cache( 0, $form_id );
}

/**
 * Sanitize Formidable form option values while preserving nested structures.
 *
 * @param mixed $value Raw option value.
 * @return mixed
 */
function mcp_formidable_sanitize_form_option_value( $value ) {
	if ( is_array( $value ) ) {
		$sanitized = array();
		foreach ( $value as $key => $inner_value ) {
			$sanitized[ sanitize_key( (string) $key ) ] = mcp_formidable_sanitize_form_option_value( $inner_value );
		}
		return $sanitized;
	}

	if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
		return $value;
	}

	if ( is_scalar( $value ) ) {
		return wp_kses_post( (string) $value );
	}

	return '';
}

/**
 * Rebuild Formidable generated CSS if available.
 */
function mcp_formidable_rebuild_css(): bool {
	if ( ! class_exists( 'FrmStyle' ) ) {
		return false;
	}

	$style = new FrmStyle();
	if ( ! method_exists( $style, 'save_settings' ) ) {
		return false;
	}

	$style->save_settings();
	return true;
}

/**
 * Return a normalized summary for a Formidable form record.
 *
 * @param object $form Form row.
 * @return array<string,mixed>
 */
function mcp_formidable_normalize_form( object $form ): array {
	$options = array();
	if ( isset( $form->options ) ) {
		$options = maybe_unserialize( $form->options );
		if ( is_object( $options ) ) {
			$options = get_object_vars( $options );
		}
		if ( ! is_array( $options ) ) {
			$options = array();
		}
	}

	$style_id = $options['form_style'] ?? $options['custom_style'] ?? null;
	$style_id = is_scalar( $style_id ) ? (string) $style_id : '';

	return array(
		'id'             => isset( $form->id ) ? (int) $form->id : 0,
		'name'           => isset( $form->name ) ? (string) $form->name : '',
		'form_key'       => isset( $form->form_key ) ? (string) $form->form_key : '',
		'description'    => isset( $form->description ) ? (string) $form->description : '',
		'status'         => isset( $form->status ) ? (string) $form->status : '',
		'is_template'    => ! empty( $form->is_template ),
		'parent_form_id' => isset( $form->parent_form_id ) ? (int) $form->parent_form_id : 0,
		'logged_in'      => isset( $form->logged_in ) ? (int) $form->logged_in : 0,
		'editable'       => isset( $form->editable ) ? (int) $form->editable : 0,
		'created_at'     => isset( $form->created_at ) ? (string) $form->created_at : '',
		'style_id'       => $style_id,
		'shortcode'      => isset( $form->id ) ? '[formidable id="' . (int) $form->id . '"]' : '',
		'shortcode_key'  => isset( $form->form_key ) ? '[formidable key="' . (string) $form->form_key . '"]' : '',
	);
}

/**
 * Get forms using Formidable APIs when available.
 *
 * @param bool $include_templates Include template forms.
 * @return array<int,array<string,mixed>>
 */
function mcp_formidable_list_forms_internal( bool $include_templates ): array {
	$forms = array();

	if ( class_exists( 'FrmForm' ) && method_exists( 'FrmForm', 'getAll' ) ) {
		$where = array();
		if ( ! $include_templates ) {
			$where['is_template'] = 0;
		}

		$rows = FrmForm::getAll( $where, 'name ASC' );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $form ) {
				if ( is_object( $form ) ) {
					$forms[] = mcp_formidable_normalize_form( $form );
				}
			}
		}
	}

	return $forms;
}

/**
 * Resolve a single form by ID or key.
 *
 * @param int    $form_id  Form ID.
 * @param string $form_key Form key.
 * @return array{success:bool,form?:array<string,mixed>,message?:string}
 */
function mcp_formidable_resolve_form( int $form_id, string $form_key ): array {
	$forms = mcp_formidable_list_forms_internal( true );

	foreach ( $forms as $form ) {
		if ( $form_id > 0 && (int) $form['id'] === $form_id ) {
			return array(
				'success' => true,
				'form'    => $form,
			);
		}

		if ( '' !== $form_key && $form['form_key'] === $form_key ) {
			return array(
				'success' => true,
				'form'    => $form,
			);
		}
	}

	return array(
		'success' => false,
		'message' => 'Form not found.',
	);
}

/**
 * Return whether the current user may edit Formidable forms.
 */
function mcp_formidable_can_edit_forms(): bool {
	return current_user_can( 'frm_edit_forms' ) || current_user_can( 'manage_options' );
}

/**
 * Get a Formidable form through the native model by ID.
 *
 * @param int $form_id Form ID.
 * @return object|null
 */
function mcp_formidable_get_form_row_by_id( int $form_id ): ?object {
	if ( $form_id <= 0 || ! is_callable( array( 'FrmForm', 'getOne' ) ) ) {
		return null;
	}
	$form = FrmForm::getOne( $form_id );
	return is_object( $form ) ? $form : null;
}

/**
 * Get a Formidable form through the native model by key.
 *
 * @param string $form_key Form key.
 * @return object|null
 */
function mcp_formidable_get_form_row_by_key( string $form_key ): ?object {
	$form_key = sanitize_key( $form_key );
	if ( '' === $form_key || ! is_callable( array( 'FrmForm', 'getAll' ) ) ) {
		return null;
	}
	$forms = FrmForm::getAll( array( 'form_key' => $form_key ) );
	$form = is_array( $forms ) ? reset( $forms ) : null;
	return is_object( $form ) ? $form : null;
}

/**
 * Normalize serialized Formidable field payloads.
 *
 * @param mixed $value Raw option value.
 * @return array<string,mixed>
 */
function mcp_formidable_normalize_field_payload( $value ): array {
	$value = maybe_unserialize( $value );

	if ( is_object( $value ) ) {
		return get_object_vars( $value );
	}

	if ( is_array( $value ) ) {
		return $value;
	}

	return array();
}

/**
 * Return a normalized summary for a Formidable field row.
 *
 * @param object $field Formidable field row.
 * @return array<string,mixed>
 */
function mcp_formidable_normalize_field( object $field ): array {
	$options       = mcp_formidable_normalize_field_payload( $field->options ?? array() );
	$field_options = mcp_formidable_normalize_field_payload( $field->field_options ?? array() );
	$normalized    = array(
		'id'            => isset( $field->id ) ? (int) $field->id : 0,
		'field_key'     => isset( $field->field_key ) ? (string) $field->field_key : '',
		'form_id'       => isset( $field->form_id ) ? (int) $field->form_id : 0,
		'name'          => isset( $field->name ) ? (string) $field->name : '',
		'description'   => isset( $field->description ) ? (string) $field->description : '',
		'type'          => isset( $field->type ) ? (string) $field->type : '',
		'default_value' => isset( $field->default_value ) ? (string) $field->default_value : '',
		'required'      => ! empty( $field->required ),
		'field_order'   => isset( $field->field_order ) ? (int) $field->field_order : 0,
		'options'       => $options,
		'field_options' => $field_options,
	);

	if ( 'file' === $normalized['type'] ) {
		$normalized['max_file_size_mb']    = isset( $field_options['size'] ) ? (string) $field_options['size'] : '';
		$normalized['min_file_size_mb']    = isset( $field_options['min_size'] ) ? (string) $field_options['min_size'] : '';
		$normalized['max_files_per_entry'] = isset( $field_options['max'] ) ? (string) $field_options['max'] : '';
	}

	return $normalized;
}

/**
 * Normalize a Formidable numeric field option.
 *
 * Formidable stores file upload size limits as MB strings in field_options.
 *
 * @param mixed $value Input value.
 * @return string
 */
function mcp_formidable_normalize_numeric_option_string( $value ): string {
	if ( '' === $value || null === $value ) {
		return '';
	}

	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = trim( (string) $value );
	if ( '' === $value || ! is_numeric( $value ) ) {
		return '';
	}

	$number = max( 0, (float) $value );
	if ( floor( $number ) === $number ) {
		return (string) (int) $number;
	}

	return rtrim( rtrim( number_format( $number, 4, '.', '' ), '0' ), '.' );
}

/**
 * Resolve a field through the native model by ID.
 *
 * @param int $field_id Field ID.
 * @return object|null
 */
function mcp_formidable_get_field_row_by_id( int $field_id ): ?object {
	if ( $field_id <= 0 || ! is_callable( array( 'FrmField', 'getOne' ) ) ) {
		return null;
	}
	$field = FrmField::getOne( $field_id );
	return is_object( $field ) ? $field : null;
}

/**
 * Get all field rows for a form.
 *
 * @param int $form_id Form ID.
 * @return array<int,object>
 */
function mcp_formidable_get_field_rows_for_form( int $form_id ): array {
	if ( $form_id <= 0 || ! is_callable( array( 'FrmField', 'get_all_for_form' ) ) ) {
		return array();
	}
	$rows = FrmField::get_all_for_form( $form_id );
	return is_array( $rows ) ? array_values( array_filter( $rows, 'is_object' ) ) : array();
}

/**
 * Return a normalized summary for a form plus raw options.
 *
 * @param object $form Form row.
 * @return array<string,mixed>
 */
function mcp_formidable_normalize_form_detail( object $form ): array {
	$normalized = mcp_formidable_normalize_form( $form );
	$options    = array();

	if ( isset( $form->options ) ) {
		$options = mcp_formidable_normalize_field_payload( $form->options );
	}

	$normalized['options'] = $options;

	return $normalized;
}

/**
 * Build a Formidable field payload for create/update operations.
 *
 * @param array<string,mixed> $input            Input payload.
 * @param int                 $resolved_form_id Resolved form ID.
 * @param array<string,mixed> $current_field    Current normalized field for updates.
 * @return array<string,mixed>
 */
function mcp_formidable_build_field_payload( array $input, int $resolved_form_id, array $current_field = array() ): array {
	$payload = array();

	if ( $resolved_form_id > 0 ) {
		$payload['form_id'] = $resolved_form_id;
	}

	if ( array_key_exists( 'name', $input ) ) {
		$payload['name'] = sanitize_text_field( (string) $input['name'] );
	}

	if ( array_key_exists( 'description', $input ) ) {
		$payload['description'] = wp_kses_post( (string) $input['description'] );
	}

	if ( array_key_exists( 'type', $input ) ) {
		$payload['type'] = sanitize_key( (string) $input['type'] );
	}

	if ( array_key_exists( 'default_value', $input ) ) {
		$payload['default_value'] = is_scalar( $input['default_value'] ) ? (string) $input['default_value'] : '';
	}

	if ( array_key_exists( 'field_key', $input ) ) {
		$payload['field_key'] = sanitize_key( (string) $input['field_key'] );
	}

	if ( array_key_exists( 'field_order', $input ) ) {
		$payload['field_order'] = max( 0, (int) $input['field_order'] );
	}

	$options = array();
	if ( isset( $current_field['options'] ) && is_array( $current_field['options'] ) ) {
		$options = $current_field['options'];
	}
	if ( array_key_exists( 'options', $input ) && is_array( $input['options'] ) ) {
		$options = $input['options'];
	}

	$field_options = array();
	if ( isset( $current_field['field_options'] ) && is_array( $current_field['field_options'] ) ) {
		$field_options = $current_field['field_options'];
	}
	if ( array_key_exists( 'field_options', $input ) && is_array( $input['field_options'] ) ) {
		$field_options = array_replace_recursive( $field_options, $input['field_options'] );
	}
	if ( array_key_exists( 'required', $input ) ) {
		$payload['required'] = ! empty( $input['required'] ) ? 1 : 0;
		unset( $field_options['required'] );
	}
	if ( array_key_exists( 'max_file_size_mb', $input ) ) {
		$field_options['size'] = mcp_formidable_normalize_numeric_option_string( $input['max_file_size_mb'] );
	}
	if ( array_key_exists( 'min_file_size_mb', $input ) ) {
		$field_options['min_size'] = mcp_formidable_normalize_numeric_option_string( $input['min_file_size_mb'] );
	}
	if ( array_key_exists( 'max_files_per_entry', $input ) ) {
		$field_options['max'] = mcp_formidable_normalize_numeric_option_string( $input['max_files_per_entry'] );
	}

	$payload['options']       = $options;
	$payload['field_options'] = $field_options;

	return $payload;
}

/**
 * Prepare field input for native storage and its unslashed readback.
 *
 * @param array<string,mixed> $payload Unslashed ability input.
 * @return array<string,mixed>
 */
function mcp_formidable_prepare_native_field_input( array $payload ): array {
	$prepared = wp_slash( $payload );
	// The native model applies its own escaping to the format expression.
	if ( isset( $payload['field_options']['format'] ) ) {
		$prepared['field_options']['format'] = $payload['field_options']['format'];
	}
	return $prepared;
}

/**
 * Create a Formidable field through Formidable APIs.
 *
 * @param array<string,mixed> $payload Field payload.
 * @return array{success:bool,field?:array<string,mixed>,message?:string}
 */
function mcp_formidable_create_field_internal( array $payload ): array {
	if ( ! class_exists( 'FrmField' ) || ! method_exists( 'FrmField', 'create' ) ) {
		return array(
			'success' => false,
			'message' => 'Formidable field creation API is unavailable.',
		);
	}

	$field_id = FrmField::create( mcp_formidable_prepare_native_field_input( $payload ), true );
	$field_id = is_numeric( $field_id ) ? (int) $field_id : 0;
	$field    = mcp_formidable_get_field_row_by_id( $field_id );

	if ( ! $field ) {
		return array(
			'success' => false,
			'message' => 'Field creation did not return a readable field record.',
		);
	}

	return array(
		'success' => true,
		'field'   => mcp_formidable_normalize_field( $field ),
	);
}

/**
 * Update a Formidable field through Formidable APIs.
 *
 * @param int                 $field_id Field ID.
 * @param array<string,mixed> $payload  Field payload.
 * @return array{success:bool,field?:array<string,mixed>,message?:string}
 */
function mcp_formidable_update_field_internal( int $field_id, array $payload ): array {

	$current = mcp_formidable_get_field_row_by_id( $field_id );
	if ( ! $current ) {
		return array(
			'success' => false,
			'message' => 'Field not found.',
		);
	}

	$form_id = isset( $current->form_id ) ? (int) $current->form_id : 0;
	if ( ! isset( $payload['form_id'] ) && $form_id ) {
		$payload['form_id'] = $form_id;
	}
	if ( ! isset( $payload['type'] ) && isset( $current->type ) ) {
		$payload['type'] = (string) $current->type;
	}

	if ( ! is_callable( array( 'FrmField', 'update' ) ) ) {
		return array( 'success' => false, 'message' => 'Formidable field update API is unavailable.' );
	}

	$allowed = array(
		'name',
		'description',
		'type',
		'default_value',
		'field_key',
		'form_id',
		'field_order',
		'required',
		'options',
		'field_options',
	);
	$update  = array();

	foreach ( $allowed as $key ) {
		if ( ! array_key_exists( $key, $payload ) ) {
			continue;
		}

		$update[ $key ] = $payload[ $key ];
	}

	if ( empty( $update ) ) {
		return array(
			'success' => false,
			'message' => 'No supported field columns were provided.',
		);
	}

	$result = FrmField::update( $field_id, mcp_formidable_prepare_native_field_input( $update ) );
	if ( false === $result ) {
		return array( 'success' => false, 'message' => 'Field update failed through Formidable field API.' );
	}

	mcp_formidable_clear_field_runtime_cache( $field_id, $form_id );

	$field = mcp_formidable_get_field_row_by_id( $field_id );

	if ( ! $field ) {
		return array(
			'success' => false,
			'message' => 'Updated field could not be reloaded.',
		);
	}

	return array(
		'success' => true,
		'field'   => mcp_formidable_normalize_field( $field ),
	);
}

/**
 * Update basic Formidable form properties through the native model.
 *
 * @param int                 $form_id Form ID.
 * @param array<string,mixed> $payload Update payload.
 * @return array<string,mixed>
 */
function mcp_formidable_update_form_internal( int $form_id, array $payload ): array {

	$form = mcp_formidable_get_form_row_by_id( $form_id );
	if ( ! $form ) {
		return array(
			'success' => false,
			'message' => 'Form not found.',
		);
	}

	if ( ! is_callable( array( 'FrmForm', 'update' ) ) ) {
		return array( 'success' => false, 'message' => 'Formidable form update API is unavailable.' );
	}
	$update  = array();

	if ( array_key_exists( 'name', $payload ) ) {
		$update['name'] = sanitize_text_field( (string) $payload['name'] );
	}
	if ( array_key_exists( 'description', $payload ) ) {
		$update['description'] = wp_kses_post( (string) $payload['description'] );
	}
	if ( array_key_exists( 'form_key', $payload ) ) {
		$update['form_key'] = sanitize_key( (string) $payload['form_key'] );
	}
	if ( array_key_exists( 'options', $payload ) && is_array( $payload['options'] ) ) {
		$options = mcp_formidable_normalize_field_payload( $form->options ?? array() );
		$options = array_replace_recursive( $options, mcp_formidable_sanitize_form_option_value( $payload['options'] ) );

		$update['options'] = $options;
		$update['status']  = (string) ( $form->status ?? 'published' );
	}

	if ( empty( $update ) ) {
		return array(
			'success' => false,
			'message' => 'No supported form columns were provided.',
		);
	}

	$result = FrmForm::update( $form_id, wp_slash( $update ) );
	if ( false === $result ) {
		return array(
			'success' => false,
			'message' => 'Form update failed through Formidable form API.',
		);
	}

	mcp_formidable_clear_form_runtime_cache( $form_id );

	$updated = mcp_formidable_get_form_row_by_id( $form_id );

	if ( ! $updated ) {
		return array( 'success' => false, 'message' => 'Updated form could not be reloaded.' );
	}

	return array(
		'success' => true,
		'form'    => mcp_formidable_normalize_form( $updated ),
	);
}

/**
 * Return a normalized Formidable action post.
 */
function mcp_formidable_normalize_action_post( object $post ): array {
	$settings = json_decode( (string) $post->post_content, true );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return array(
		'id'       => (int) $post->ID,
		'title'    => (string) $post->post_title,
		'slug'     => (string) $post->post_name,
		'type'     => (string) $post->post_excerpt,
		'status'   => (string) $post->post_status,
		'settings' => $settings,
		'content'  => (string) $post->post_content,
	);
}

/**
 * Get a Formidable action post by ID.
 */
function mcp_formidable_get_action_post( int $action_id ): ?object {
	if ( $action_id <= 0 ) {
		return null;
	}

	$post = get_post( $action_id );
	if ( ! $post || 'frm_form_actions' !== $post->post_type ) {
		return null;
	}

	return $post;
}

/**
 * Update a Formidable action post using raw JSON settings.
 *
 * @param int                 $action_id Action post ID.
 * @param array<string,mixed> $payload   Update payload.
 * @return array<string,mixed>
 */
function mcp_formidable_update_action_post_internal( int $action_id, array $payload ): array {
	$post = mcp_formidable_get_action_post( $action_id );
	if ( ! $post ) {
		return array(
			'success' => false,
			'message' => 'Formidable action post not found.',
		);
	}

	$update = array(
		'ID' => $action_id,
	);

	if ( array_key_exists( 'title', $payload ) ) {
		$update['post_title'] = sanitize_text_field( (string) $payload['title'] );
	}
	if ( array_key_exists( 'slug', $payload ) ) {
		$update['post_name'] = sanitize_title( (string) $payload['slug'] );
	}
	if ( array_key_exists( 'action_type', $payload ) ) {
		$update['post_excerpt'] = sanitize_key( (string) $payload['action_type'] );
	}
	if ( array_key_exists( 'settings', $payload ) && is_array( $payload['settings'] ) ) {
		$update['post_content'] = wp_json_encode( $payload['settings'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	} elseif ( array_key_exists( 'content', $payload ) ) {
		$update['post_content'] = (string) $payload['content'];
	}

	$result = wp_update_post( wp_slash( $update ), true );
	if ( is_wp_error( $result ) ) {
		return array(
			'success' => false,
			'message' => $result->get_error_message(),
		);
	}

	clean_post_cache( $action_id );
	$updated = mcp_formidable_get_action_post( $action_id );

	return array(
		'success' => true,
		'action'  => $updated ? mcp_formidable_normalize_action_post( $updated ) : null,
	);
}

/** Copy actions with native handlers while the field mapping is installed. */
function mcp_formidable_duplicate_actions_native( int $source_form_id, int $target_form_id ): array {
	$args = array( 'post_status' => 'any', 'limit' => 999 );
	$source = FrmFormAction::get_action_for_form( $source_form_id, 'all', $args );
	$target = FrmFormAction::get_action_for_form( $target_form_id, 'all', $args );
	$expected = is_array( $source ) ? count( $source ) : 0;
	$actual = is_array( $target ) ? count( $target ) : 0;
	if ( $actual === $expected ) {
		return array( 'success' => true, 'actions_copied' => $actual );
	}
	if ( 0 !== $actual ) {
		return array( 'success' => false, 'message' => 'The copied form has an incomplete action set.' );
	}
	$handlers = FrmFormActionsController::get_form_actions( 'all' );
	if ( ! is_array( $handlers ) ) {
		return array( 'success' => false, 'message' => 'Native Formidable action handlers are unavailable.' );
	}
	foreach ( $handlers as $handler ) {
		if ( ! is_callable( array( $handler, 'duplicate_form_actions' ) ) ) {
			return array( 'success' => false, 'message' => 'A native Formidable action copy API is unavailable.' );
		}
	}
	foreach ( $handlers as $handler ) {
		$handler->duplicate_form_actions( $target_form_id, $source_form_id );
	}
	$target = FrmFormAction::get_action_for_form( $target_form_id, 'all', $args );
	$actual = is_array( $target ) ? count( $target ) : 0;
	return $actual === $expected
		? array( 'success' => true, 'actions_copied' => $actual )
		: array( 'success' => false, 'message' => 'Formidable did not copy every source action.' );
}

/** Complete an unused, unedited form copy without replacing any actions. */
function mcp_formidable_copy_form_actions_internal( int $source_form_id, int $target_form_id, array $field_map ): array {
	global $frm_duplicate_ids;
	foreach ( array( array( 'FrmFormAction', 'get_action_for_form' ), array( 'FrmFormActionsController', 'get_form_actions' ), array( 'FrmDb', 'get_count' ) ) as $method ) {
		if ( ! is_callable( $method ) ) {
			return array( 'success' => false, 'message' => 'A required native Formidable API is unavailable.' );
		}
	}
	$source = mcp_formidable_get_form_row_by_id( $source_form_id );
	$target = mcp_formidable_get_form_row_by_id( $target_form_id );
	if ( ! $source || ! $target || $source_form_id === $target_form_id ) {
		return array( 'success' => false, 'message' => 'Select two different existing forms.' );
	}
	// Count every entry, including drafts and spam; do not read submitted values.
	$count = FrmDb::get_count( 'frm_items', array( 'form_id' => $target_form_id ) );
	if ( ! is_numeric( $count ) || 0 !== (int) $count ) {
		return array( 'success' => false, 'message' => 'The target form must have no entries.' );
	}
	$args = array( 'post_status' => 'any', 'limit' => 999 );
	$existing = FrmFormAction::get_action_for_form( $target_form_id, 'all', $args );
	if ( ! is_array( $existing ) || $existing ) {
		return array( 'success' => false, 'message' => 'The target form must have no actions.' );
	}
	$source_detail = mcp_formidable_normalize_form_detail( $source );
	$target_detail = mcp_formidable_normalize_form_detail( $target );
	if ( $source_detail['options'] !== $target_detail['options'] ) {
		return array( 'success' => false, 'message' => 'The target must retain the unedited copied form options.' );
	}
	$source_fields = mcp_formidable_get_field_rows_for_form( $source_form_id );
	$target_fields = mcp_formidable_get_field_rows_for_form( $target_form_id );
	if ( count( $field_map ) !== count( $source_fields ) || count( $target_fields ) !== count( $source_fields ) || count( array_unique( array_values( $field_map ) ) ) !== count( $field_map ) ) {
		return array( 'success' => false, 'message' => 'Provide a complete one-to-one field map for the copied form.' );
	}
	$mapping = array();
	foreach ( $source_fields as $field ) {
		$target_id = isset( $field_map[ $field->id ] ) ? (int) $field_map[ $field->id ] : 0;
		$copy = mcp_formidable_get_field_row_by_id( $target_id );
		if ( ! $copy || (int) $copy->form_id !== $target_form_id ) {
			return array( 'success' => false, 'message' => 'A mapped field does not belong to the target form.' );
		}
		$original_values = mcp_formidable_normalize_field( $field );
		$copied_values = mcp_formidable_normalize_field( $copy );
		foreach ( array( 'id', 'form_id', 'field_key' ) as $identity ) {
			unset( $original_values[ $identity ], $copied_values[ $identity ] );
		}
		if ( $original_values !== $copied_values ) {
			return array( 'success' => false, 'message' => 'The target fields must be an unedited copy of the source fields.' );
		}
		$mapping[ (int) $field->id ] = $target_id;
		$mapping[ (string) $field->field_key ] = $target_id;
	}
	$previous_ids = $frm_duplicate_ids;
	$frm_duplicate_ids = $mapping;
	try {
		$result = mcp_formidable_duplicate_actions_native( $source_form_id, $target_form_id );
	} catch ( Throwable $error ) {
		$result = array( 'success' => false, 'message' => 'The native Formidable action copy failed.' );
	} finally {
		$frm_duplicate_ids = $previous_ids;
	}
	if ( empty( $result['success'] ) ) {
		$created = FrmFormAction::get_action_for_form( $target_form_id, 'all', $args );
		foreach ( is_array( $created ) ? $created : array() as $action ) {
			wp_delete_post( (int) $action->ID, true );
		}
		FrmFormAction::clear_cache();
		$remaining = FrmFormAction::get_action_for_form( $target_form_id, 'all', $args );
		$result['cleanup_complete'] = is_array( $remaining ) && ! $remaining;
	}
	return array_merge( $result, array( 'source_form_id' => $source_form_id, 'target_form_id' => $target_form_id ) );
}

/**
 * Clone a Formidable form, fields, and form actions.
 *
 * @param int    $source_form_id Source form ID.
 * @param string $name           New form name.
 * @param string $form_key       Requested new form key.
 * @return array<string,mixed>
 */
function mcp_formidable_clone_form_internal( int $source_form_id, string $name, string $form_key ): array {
	global $frm_duplicate_ids;

	$name     = sanitize_text_field( $name );
	$form_key = sanitize_key( $form_key );
	if ( '' === $name || '' === $form_key ) {
		return array( 'success' => false, 'message' => 'Provide a nonempty name and form_key.' );
	}
	foreach ( array( array( 'FrmForm', 'duplicate' ), array( 'FrmForm', 'update' ), array( 'FrmForm', 'destroy' ), array( 'FrmField', 'get_all_for_form' ), array( 'FrmField', 'getOne' ), array( 'FrmFormAction', 'get_action_for_form' ), array( 'FrmFormActionsController', 'get_form_actions' ) ) as $method ) {
		if ( ! is_callable( $method ) ) {
			return array( 'success' => false, 'message' => 'Formidable native form duplication API is unavailable.' );
		}
	}
	if ( ! mcp_formidable_get_form_row_by_id( $source_form_id ) ) {
		return array( 'success' => false, 'message' => 'Source form not found.' );
	}

	$fields       = mcp_formidable_get_field_rows_for_form( $source_form_id );
	$previous_ids = $frm_duplicate_ids;
	$frm_duplicate_ids = array();
	$new_form_id = 0;
	$duplicate_ids = array();
	try {
		// Formidable owns repeating forms, reference remapping, and action hooks.
		$new_form_id = (int) FrmForm::duplicate( $source_form_id );
		$duplicate_ids = $frm_duplicate_ids;
		$action_copy = $new_form_id > 0 ? mcp_formidable_duplicate_actions_native( $source_form_id, $new_form_id ) : array( 'success' => false );
	} catch ( Throwable $error ) {
		$action_copy = array( 'success' => false, 'message' => 'The native Formidable form copy failed.' );
	} finally {
		$frm_duplicate_ids = $previous_ids;
	}
	if ( $new_form_id <= 0 ) {
		return array( 'success' => false, 'message' => 'Formidable could not duplicate the form.' );
	}

	$updated = ! empty( $action_copy['success'] )
		? mcp_formidable_update_form_internal( $new_form_id, array( 'name' => $name, 'form_key' => $form_key ) )
		: $action_copy;
	$field_map = array();
	$key_map   = array();
	foreach ( $fields as $field ) {
		$new_field_id = (int) ( $duplicate_ids[ $field->id ] ?? 0 );
		$new_field = mcp_formidable_get_field_row_by_id( $new_field_id );
		if ( ! $new_field ) {
			$updated = array( 'success' => false, 'message' => 'A duplicated field could not be reloaded.' );
			break;
		}
		$field_map[ (int) $field->id ] = $new_field_id;
		$key_map[ (string) $field->field_key ] = (string) $new_field->field_key;
	}
	if ( ! $updated['success'] ) {
		$removed = FrmForm::destroy( $new_form_id );
		return array(
			'success' => false,
			'message' => $updated['message'],
			'new_form_id' => $new_form_id,
			'cleanup_complete' => false !== $removed && ! mcp_formidable_get_form_row_by_id( $new_form_id ),
		);
	}

	$actions = FrmFormAction::get_action_for_form( $new_form_id, 'all', array( 'post_status' => 'any', 'limit' => 999 ) );
	$new_form = mcp_formidable_get_form_row_by_id( $new_form_id );
	return array(
		'success'        => true,
		'source_form_id' => $source_form_id,
		'new_form_id'    => $new_form_id,
		'form'           => mcp_formidable_normalize_form_detail( $new_form ),
		'field_map'      => $field_map,
		'field_key_map'  => $key_map,
		'fields_copied'  => count( $field_map ),
		'actions_copied' => is_array( $actions ) ? count( $actions ) : 0,
		'shortcode'      => '[formidable id="' . $new_form_id . '"]',
	);
}

/**
 * Read one existing native style without resolving a synthetic default or creating a style.
 *
 * @param int $style_id Existing style post ID.
 * @return array<string,mixed>
 */
function mcp_formidable_get_style_internal( int $style_id ): array {
	if ( ! class_exists( 'FrmStyle' ) || ! is_callable( array( 'FrmAppHelper', 'maybe_json_decode' ) ) ) {
		return array( 'success' => false, 'message' => 'Native Formidable style APIs are unavailable.' );
	}
	$post = get_post( $style_id );
	if ( ! $post || 'frm_styles' !== $post->post_type || 'trash' === $post->post_status ) {
		return array( 'success' => false, 'message' => 'Select an existing Formidable style ID.' );
	}
	// get_one() fills defaults on its returned post. Capture stored values first.
	$stored = FrmAppHelper::maybe_json_decode( $post->post_content );
	if ( ! is_array( $stored ) ) {
		return array( 'success' => false, 'message' => 'The selected style has invalid native settings.' );
	}
	$style = array(
		'id'         => (int) $post->ID,
		'title'      => (string) $post->post_title,
		'slug'       => (string) $post->post_name,
		'status'     => (string) $post->post_status,
		'is_default' => ! empty( $post->menu_order ),
		'settings'   => $stored,
	);
	$model     = new FrmStyle( $style_id );
	$effective = $model->get_one();
	if ( ! $effective || ! is_array( $effective->post_content ) ) {
		return array( 'success' => false, 'message' => 'Native Formidable style could not be read.' );
	}
	return array(
		'success'                => true,
		'style'                  => $style,
		'effective_settings'     => $effective->post_content,
		'available_setting_keys' => array_keys( $model->get_defaults() ),
		'style_hash'             => hash( 'sha256', (string) wp_json_encode( $style ) ),
	);
}

/**
 * Merge reviewed settings through the native style sanitizer and storage model.
 *
 * @param array<string,mixed> $input Selected ID, changes, hash and rebuild choice.
 * @return array<string,mixed>
 */
function mcp_formidable_update_style_internal( array $input ): array {
	$style_id = (int) ( $input['style_id'] ?? 0 );
	$before   = mcp_formidable_get_style_internal( $style_id );
	if ( ! $before['success'] ) {
		return $before;
	}
	if ( ( $input['expected_style_hash'] ?? '' ) !== $before['style_hash'] ) {
		return array( 'success' => false, 'message' => 'The style changed. Read it again before updating.' );
	}
	$changes = $input['settings'] ?? array();
	if ( ! is_array( $changes ) || ! $changes || count( $changes ) > 250 || array_diff( array_keys( $changes ), $before['available_setting_keys'] ) ) {
		return array( 'success' => false, 'message' => 'Provide supported settings returned by get-style.' );
	}
	foreach ( $changes as $value ) {
		if ( ! is_scalar( $value ) || strlen( (string) $value ) > 200000 ) {
			return array( 'success' => false, 'message' => 'Style settings must be bounded scalar values.' );
		}
	}
	$model = new FrmStyle( $style_id );
	if ( ! is_callable( array( $model, 'sanitize_post_content' ) ) || ! is_callable( array( $model, 'save' ) ) ) {
		return array( 'success' => false, 'message' => 'Native Formidable style storage is unavailable.' );
	}
	$sanitized = $model->sanitize_post_content( array_replace( $before['effective_settings'], $changes ) );
	$settings  = $before['style']['settings'];
	foreach ( array_keys( $changes ) as $key ) {
		$value = $sanitized[ $key ];
		if ( in_array( $key, $model->get_color_settings(), true ) ) {
			$value = str_replace( '#', '', $value );
		} elseif ( 'font' === $key ) {
			$value = $model->force_balanced_quotation( $value );
		} elseif ( 'single_style_custom_css' === $key && '' !== $value ) {
			if ( ! class_exists( 'FrmCssScopeHelper' ) ) {
				return array( 'success' => false, 'message' => 'Native style CSS scoping is unavailable.' );
			}
			$value = ( new FrmCssScopeHelper() )->nest( $value, 'frm_style_' . $before['style']['slug'] );
		}
		$settings[ $key ] = $value;
	}
	if ( array_intersect( array( 'base_font_size', 'use_base_font_size' ), array_keys( $changes ) ) ) {
		if ( ! is_callable( array( 'FrmStylesHelper', 'update_base_font_size' ) ) ) {
			return array( 'success' => false, 'message' => 'Native base-font conversion is unavailable.' );
		}
		$settings = FrmStylesHelper::update_base_font_size( $settings, $model->get_defaults() );
	}
	if ( $settings === $before['style']['settings'] ) {
		return $before + array( 'updated' => false, 'css_rebuild_requested' => false );
	}
	// save() accepts explicit values; update() reads $_POST and publishes the style.
	// FrmDb::save_settings() ultimately uses wp_insert_post(), whose omitted fields
	// take insert defaults even with an ID. Preserve the complete native post.
	$post_values = wp_slash( (array) get_post( $style_id ) );
	$post_values['post_content'] = wp_slash( $settings );
	$saved = $model->save( $post_values );
	if ( is_wp_error( $saved ) || (int) $saved !== $style_id ) {
		return array( 'success' => false, 'message' => 'Native style storage failed. Read the style again to check its current state.' );
	}
	clean_post_cache( $style_id );
	$after    = mcp_formidable_get_style_internal( $style_id );
	$expected = $before['style'];
	$expected['settings'] = $settings;
	if ( ! $after['success'] || $after['style'] !== $expected ) {
		return array( 'success' => false, 'updated' => true, 'message' => 'Style readback differs from the requested change. Read it again before further writes.' );
	}
	mcp_formidable_clear_runtime_transients();
	$rebuild = ! array_key_exists( 'rebuild_css', $input ) || ! empty( $input['rebuild_css'] );
	$rebuilt = $rebuild && mcp_formidable_rebuild_css();
	return $after + array( 'updated' => true, 'css_rebuild_requested' => $rebuilt );
}

/**
 * Define a selected native style operation.
 *
 * @param bool $write Whether to update the selected style.
 * @return array<string,mixed>
 */
function mcp_formidable_style_ability_definition( bool $write ): array {
	$id         = array( 'type' => 'integer', 'minimum' => 1 );
	$permission = static function (): bool {
		return current_user_can( 'frm_edit_forms' ) || current_user_can( 'manage_options' );
	};
	$properties = array( 'style_id' => $id );
	$required   = array( 'style_id' );
	if ( $write ) {
		$properties['settings'] = array( 'type' => 'object', 'minProperties' => 1, 'maxProperties' => 250, 'description' => 'Native setting keys from get-style. Only supplied keys change; other stored settings are preserved.' );
		$properties['expected_style_hash'] = array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' );
		$properties['rebuild_css'] = array( 'type' => 'boolean', 'default' => true );
		$required = array( 'style_id', 'settings', 'expected_style_hash' );
	}
	return array(
		'label'               => $write ? 'Update Formidable Style' : 'Get Formidable Style',
		'description'         => $write ? 'Merge reviewed native style settings, preserve its identity and status, verify storage and optionally regenerate CSS.' : 'Read stored and effective settings and supported setting keys for one existing native style.',
		'category'            => 'site',
		'input_schema'        => array( 'type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false ),
		'output_schema'       => array( 'type' => 'object', 'properties' => array( 'success' => array( 'type' => 'boolean' ) ) ),
		'execute_callback'    => static function ( array $input ) use ( $write ): array {
			$inactive = mcp_formidable_require_active();
			if ( $inactive ) { return $inactive; }
			return $write ? mcp_formidable_update_style_internal( $input ) : mcp_formidable_get_style_internal( (int) $input['style_id'] );
		},
		'permission_callback' => $permission,
		'meta'                => array( 'annotations' => array( 'readonly' => ! $write, 'destructive' => $write, 'idempotent' => true ) ),
	);
}

/**
 * Preview or restore metadata of a selected native style, preserving its settings.
 *
 * A misclassified post is accepted only for the exact empty-draft/native-style
 * shape produced by an incomplete FrmStyle::save call. Pages and arbitrary posts
 * are excluded, and native administrator plus object-edit permission is required.
 *
 * @param array<string,mixed> $input Explicit style ID and reviewed metadata.
 * @return array<string,mixed>
 */
function mcp_formidable_restore_style_internal( array $input ): array {
	$id   = (int) $input['style_id'];
	$post = get_post( $id );
	if ( ! $post || ! in_array( $post->post_type, array( 'frm_styles', 'post' ), true ) || ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $id ) || ! class_exists( 'FrmStyle' ) ) {
		return array( 'success' => false, 'message' => 'Native administrator and selected-object editing permissions are required.' );
	}
	$model    = new FrmStyle( $id );
	$settings = FrmAppHelper::maybe_json_decode( $post->post_content );
	$recovery = 'post' === $post->post_type && 'draft' === $post->post_status && '' === $post->post_title
		&& is_array( $settings ) && count( $settings ) >= 20
		&& ! array_diff( array_keys( $settings ), array_keys( $model->get_defaults() ) )
		&& isset( $settings['font'], $settings['field_font_size'], $settings['form_width'], $settings['theme_css'] );
	if ( ! is_array( $settings ) || ( 'frm_styles' !== $post->post_type && ! $recovery ) ) {
		return array( 'success' => false, 'message' => 'Select an existing native style or a recognized incomplete native style save.' );
	}
	$allowed = array( 'post_title', 'post_name', 'post_status', 'post_author', 'post_date', 'post_date_gmt', 'comment_status', 'ping_status', 'guid', 'menu_order' );
	$metadata = $input['metadata'] ?? array();
	if ( ! is_array( $metadata ) || ! $metadata || array_diff( array_keys( $metadata ), $allowed ) ) {
		return array( 'success' => false, 'message' => 'Provide only supported native style metadata.' );
	}
	foreach ( $metadata as $key => $value ) {
		if ( ! is_scalar( $value ) || strlen( (string) $value ) > 1000 ) {
			return array( 'success' => false, 'message' => 'Provide bounded native style metadata values.' );
		}
		$metadata[ $key ] = in_array( $key, array( 'post_author', 'menu_order' ), true ) ? (int) $value : sanitize_text_field( (string) $value );
	}
	if ( isset( $metadata['post_status'] ) && ! in_array( $metadata['post_status'], array( 'draft', 'publish', 'private' ), true ) ) {
		return array( 'success' => false, 'message' => 'Unsupported style publication status.' );
	}
	if ( isset( $metadata['menu_order'] ) && ! in_array( $metadata['menu_order'], array( 0, 1 ), true ) ) {
		return array( 'success' => false, 'message' => 'Style default selection must be zero or one.' );
	}
	if ( isset( $metadata['post_name'] ) && $metadata['post_name'] !== $post->post_name ) {
		return array( 'success' => false, 'message' => 'Preserve the existing style slug and native CSS scope.' );
	}
	if ( 'publish' === ( $metadata['post_status'] ?? $post->post_status ) && ! current_user_can( 'publish_posts' ) ) {
		return array( 'success' => false, 'message' => 'Native publishing permission is required.' );
	}
	if ( isset( $metadata['post_author'] ) && $metadata['post_author'] !== (int) $post->post_author && ! current_user_can( 'edit_others_posts' ) ) {
		return array( 'success' => false, 'message' => 'Native author editing permission is required.' );
	}
	if ( ! empty( $metadata['menu_order'] ) ) {
		foreach ( get_posts( array( 'post_type' => 'frm_styles', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 200 ) ) as $other ) {
			if ( (int) $other->ID !== $id && ! empty( $other->menu_order ) ) {
				return array( 'success' => false, 'message' => 'Another native default style exists. Preserve its selection before restoring this style.' );
			}
		}
	}
	$hash = hash( 'sha256', (string) wp_json_encode( (array) $post ) );
	if ( true !== ( $input['confirm'] ?? false ) ) {
		return array( 'success' => true, 'applied' => false, 'current_post_hash' => $hash, 'current_post_type' => $post->post_type, 'proposed_metadata' => $metadata );
	}
	if ( ( $input['expected_post_hash'] ?? '' ) !== $hash ) {
		return array( 'success' => false, 'message' => 'The selected style object changed after review.' );
	}
	$values = array_replace( (array) $post, $metadata, array( 'post_type' => 'frm_styles', 'post_content' => $settings ) );
	$saved  = $model->save( wp_slash( $values ) );
	if ( is_wp_error( $saved ) || (int) $saved !== $id ) {
		return array( 'success' => false, 'message' => 'Native style restoration failed. Read the object before another write.' );
	}
	clean_post_cache( $id );
	$after = get_post( $id );
	foreach ( $metadata + array( 'post_type' => 'frm_styles' ) as $key => $value ) {
		if ( (string) $after->{$key} !== (string) $value ) {
			return array( 'success' => false, 'applied' => true, 'message' => 'Native metadata readback differs. Inspect the selected object.' );
		}
	}
	if ( FrmAppHelper::maybe_json_decode( $after->post_content ) !== $settings ) {
		return array( 'success' => false, 'applied' => true, 'message' => 'Native settings readback differs. Inspect the selected style.' );
	}
	mcp_formidable_clear_runtime_transients();
	$rebuilt = mcp_formidable_rebuild_css();
	return mcp_formidable_get_style_internal( $id ) + array( 'applied' => true, 'css_rebuild_requested' => $rebuilt );
}

/** Register selected native style reads and updates. */
function mcp_formidable_register_style_abilities(): void {
	wp_register_ability( 'formidable/get-style', mcp_formidable_style_ability_definition( false ) );
	wp_register_ability( 'formidable/update-style', mcp_formidable_style_ability_definition( true ) );
	wp_register_ability( 'formidable/restore-style', array(
		'label' => 'Restore Formidable Style Metadata',
		'description' => 'Preview or restore reviewed native style metadata while preserving settings. Requires native administrator and object permissions; arbitrary posts and pages are excluded.',
		'category' => 'site',
		'input_schema' => array( 'type' => 'object', 'properties' => array(
			'style_id' => array( 'type' => 'integer', 'minimum' => 1 ),
			'metadata' => array( 'type' => 'object', 'minProperties' => 1, 'description' => 'Native style post_title, post_name (unchanged), post_status, post_author, post_date, post_date_gmt, comment_status, ping_status, guid and menu_order. Style settings and ID cannot be replaced.' ),
			'confirm' => array( 'type' => 'boolean', 'default' => false ),
			'expected_post_hash' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
		), 'required' => array( 'style_id', 'metadata' ), 'additionalProperties' => false ),
		'output_schema' => array( 'type' => 'object', 'properties' => array( 'success' => array( 'type' => 'boolean' ) ) ),
		'execute_callback' => 'mcp_formidable_restore_style_internal',
		'permission_callback' => static function (): bool { return current_user_can( 'manage_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ) ),
	) );
}

/**
 * List published Formidable styles.
 *
 * @param bool $include_default Include synthetic default style entry.
 * @return array<int,array<string,mixed>>
 */
function mcp_formidable_list_styles_internal( bool $include_default ): array {
	$styles = array();

	if ( $include_default ) {
		$styles[] = array(
			'id'         => 'default',
			'title'      => 'Default Style',
			'slug'       => 'default',
			'status'     => 'virtual',
			'is_default' => true,
		);
	}

	$posts = get_posts(
		array(
			'post_type'      => 'frm_styles',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	foreach ( $posts as $post ) {
		$styles[] = array(
			'id'         => (int) $post->ID,
			'title'      => $post->post_title,
			'slug'       => $post->post_name,
			'status'     => $post->post_status,
			'is_default' => false,
		);
	}

	return $styles;
}

/**
 * Search post content for form references.
 *
 * @param object              $post     Post object.
 * @param array<int,string>   $needles  Search needles.
 * @param string              $location Location label.
 * @return array<int,array<string,mixed>>
 */
function mcp_formidable_match_post_content( object $post, array $needles, string $location ): array {
	$matches = array();
	$content = (string) $post->post_content;

	foreach ( $needles as $needle ) {
		if ( '' !== $needle && false !== stripos( $content, $needle ) ) {
			$matches[] = array(
				'post_id'    => (int) $post->ID,
				'post_type'  => (string) $post->post_type,
				'post_title' => (string) get_the_title( $post ),
				'post_status'=> (string) $post->post_status,
				'location'   => $location,
				'needle'     => $needle,
				'link'       => (string) get_permalink( $post ),
			);
		}
	}

	return $matches;
}

/**
 * Find usage of a form in posts/pages and Elementor data.
 *
 * @param array<string,mixed> $form Normalized form.
 * @return array<int,array<string,mixed>>
 */
function mcp_formidable_find_usage_internal( array $form ): array {
	global $wpdb;

	$form_id  = (int) $form['id'];
	$form_key = (string) $form['form_key'];
	$needles  = array_filter(
		array(
			'[formidable id="' . $form_id . '"',
			"[formidable id='" . $form_id . "'",
			'[formidable key="' . $form_key . '"',
			"[formidable key='" . $form_key . "'",
			'"form_key":"' . $form_key . '"',
			'"id":"' . $form_id . '"',
			'"formId":"' . $form_id . '"',
			'"form_id":"' . $form_id . '"',
		)
	);

	$results = array();

	$post_types = get_post_types( array(), 'names' );
	$post_types = array_values( array_filter( $post_types, 'is_string' ) );

	$posts = get_posts(
		array(
			'post_type'      => $post_types,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
		)
	);

	foreach ( $posts as $post ) {
		$results = array_merge( $results, mcp_formidable_match_post_content( $post, $needles, 'post_content' ) );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Usage audit.
	$meta_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
			'_elementor_data'
		)
	);

	foreach ( $meta_rows as $row ) {
		$meta_value = (string) $row->meta_value;
		foreach ( $needles as $needle ) {
			if ( '' !== $needle && false !== stripos( $meta_value, $needle ) ) {
				$post = get_post( (int) $row->post_id );
				if ( ! $post ) {
					continue;
				}

				$results[] = array(
					'post_id'     => (int) $post->ID,
					'post_type'   => (string) $post->post_type,
					'post_title'  => (string) get_the_title( $post ),
					'post_status' => (string) $post->post_status,
					'location'    => 'elementor_data',
					'needle'      => $needle,
					'link'        => (string) get_permalink( $post ),
				);
				break;
			}
		}
	}

	$widget_forms = get_option( 'widget_frm_show_form', array() );
	if ( is_array( $widget_forms ) ) {
		foreach ( $widget_forms as $widget_id => $widget_instance ) {
			if ( ! is_array( $widget_instance ) ) {
				continue;
			}

			$widget_form_id = isset( $widget_instance['form_id'] ) ? (int) $widget_instance['form_id'] : 0;
			if ( $widget_form_id !== $form_id ) {
				continue;
			}

			$results[] = array(
				'post_id'     => 0,
				'post_type'   => 'widget',
				'post_title'  => 'Widget frm_show_form #' . $widget_id,
				'post_status' => 'active',
				'location'    => 'widget_frm_show_form',
				'needle'      => 'form_id=' . $form_id,
				'link'        => '',
			);
		}
	}

	$unique = array();
	foreach ( $results as $result ) {
		$key = implode(
			':',
			array(
				(string) $result['post_id'],
				(string) $result['location'],
				(string) $result['needle'],
			)
		);
		$unique[ $key ] = $result;
	}

	return array_values( $unique );
}

/**
 * Register Formidable abilities.
 */
function mcp_register_formidable_abilities(): void {
	if ( ! mcp_formidable_check_dependencies() ) {
		return;
	}
	mcp_formidable_register_style_abilities();

	wp_register_ability(
		'formidable/get-settings',
		array(
			'label'               => 'Get Formidable Settings',
			'description'         => 'Returns effective Formidable styling/runtime settings plus raw frm_options and frmpro_options.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'_' => array(
						'type'        => 'boolean',
						'description' => 'Optional no-op flag for proxy compatibility.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'effective' => array( 'type' => 'object' ),
					'frm'       => array( 'type' => 'object' ),
					'frmpro'    => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function ( ?array $input = null ): array {
				unset( $input );
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				return array(
					'success'   => true,
					'effective' => mcp_formidable_get_effective_settings(),
					'frm'       => mcp_formidable_normalize_option_value( get_option( 'frm_options', array() ) ),
					'frmpro'    => mcp_formidable_normalize_option_value( get_option( 'frmpro_options', array() ) ),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/update-settings',
		array(
			'label'               => 'Update Formidable Settings',
			'description'         => 'Updates supported Formidable styling/runtime settings and clears CSS/settings cache.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'load_style'   => array(
						'type'        => 'string',
						'enum'        => array( 'all', 'dynamic', 'none' ),
						'description' => 'How Formidable should load CSS on the frontend.',
					),
					'custom_style' => array(
						'type'        => 'boolean',
						'description' => 'Whether custom styling is enabled.',
					),
					'custom_css'   => array(
						'type'        => 'string',
						'description' => 'Stored custom CSS string. Set empty string to clear it.',
					),
					'jquery_css'   => array(
						'type'        => 'boolean',
						'description' => 'Whether to enable jQuery UI CSS fallback.',
					),
					'old_css'      => array(
						'type'        => 'boolean',
						'description' => 'Whether to use the legacy Formidable CSS mode.',
					),
					'accordion_js' => array(
						'type'        => 'boolean',
						'description' => 'Whether accordion JS support is enabled.',
					),
					'rebuild_css'  => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'If true, rebuild generated Formidable CSS after updating.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'updated'   => array( 'type' => 'object' ),
					'effective' => array( 'type' => 'object' ),
					'message'   => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				$allowed = array_keys( mcp_formidable_allowed_setting_sanitizers() );
				$changes = array();

				foreach ( $allowed as $key ) {
					if ( array_key_exists( $key, $input ) ) {
						$changes[ $key ] = mcp_formidable_sanitize_setting_value( $key, $input[ $key ] );
					}
				}

				if ( empty( $changes ) ) {
					return array(
						'success' => false,
						'message' => 'No supported settings were provided.',
					);
				}

				$result = mcp_formidable_store_settings( $changes );
				if ( ! $result['success'] ) {
					return $result;
				}
				mcp_formidable_clear_runtime_transients();

				$rebuilt = false;
				if ( ! array_key_exists( 'rebuild_css', $input ) || ! empty( $input['rebuild_css'] ) ) {
					$rebuilt = mcp_formidable_rebuild_css();
				}

				return array(
					'success'   => true,
					'updated'   => $changes,
					'effective' => mcp_formidable_get_effective_settings(),
					'message'   => $rebuilt ? 'Settings updated and CSS cache rebuilt.' : 'Settings updated and cache cleared.',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/list-forms',
		array(
			'label'               => 'List Formidable Forms',
			'description'         => 'Lists Formidable forms with keys, IDs, styles, and shortcode references.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'include_templates' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Whether to include template forms.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'forms'   => array( 'type' => 'array' ),
					'total'   => array( 'type' => 'integer' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				$include_templates = ! empty( $input['include_templates'] );
				$forms             = mcp_formidable_list_forms_internal( $include_templates );

				return array(
					'success' => true,
					'forms'   => $forms,
					'total'   => count( $forms ),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/list-styles',
		array(
			'label'               => 'List Formidable Styles',
			'description'         => 'Lists saved Formidable styles plus the synthetic default style entry.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'include_default' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Whether to include the default virtual style entry.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'styles'  => array( 'type' => 'array' ),
					'total'   => array( 'type' => 'integer' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				$styles = mcp_formidable_list_styles_internal( ! array_key_exists( 'include_default', $input ) || ! empty( $input['include_default'] ) );

				return array(
					'success' => true,
					'styles'  => $styles,
					'total'   => count( $styles ),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/clone-form',
		array(
			'label'               => 'Clone Formidable Form',
			'description'         => 'Clone a Formidable form, its fields, and form actions so translated pages can use independent forms.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'source_form_id', 'name', 'form_key' ),
				'properties'           => array(
					'source_form_id' => array( 'type' => 'integer' ),
					'name'           => array( 'type' => 'string' ),
					'form_key'       => array( 'type' => 'string' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'        => array( 'type' => 'boolean' ),
					'source_form_id' => array( 'type' => 'integer' ),
					'new_form_id'    => array( 'type' => 'integer' ),
					'form'           => array( 'type' => 'object' ),
					'field_map'      => array( 'type' => 'object' ),
					'field_key_map'  => array( 'type' => 'object' ),
					'fields_copied'  => array( 'type' => 'integer' ),
					'actions_copied' => array( 'type' => 'integer' ),
					'shortcode'      => array( 'type' => 'string' ),
					'message'        => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				return mcp_formidable_clone_form_internal(
					isset( $input['source_form_id'] ) ? (int) $input['source_form_id'] : 0,
					isset( $input['name'] ) ? (string) $input['name'] : '',
					isset( $input['form_key'] ) ? (string) $input['form_key'] : ''
				);
			},
			'permission_callback' => function (): bool {
				return mcp_formidable_can_edit_forms();
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/copy-form-actions',
		array(
			'label' => 'Complete Formidable Copy Actions',
			'description' => 'Copy native actions to an empty, unedited form copy using its exact field map. Refuses targets with entries or existing actions.',
			'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'required' => array( 'source_form_id', 'target_form_id', 'field_map' ),
				'properties' => array(
					'source_form_id' => array( 'type' => 'integer', 'minimum' => 1 ),
					'target_form_id' => array( 'type' => 'integer', 'minimum' => 1 ),
					'field_map' => array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'integer', 'minimum' => 1 ) ),
				),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type' => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'source_form_id' => array( 'type' => 'integer' ),
					'target_form_id' => array( 'type' => 'integer' ),
					'actions_copied' => array( 'type' => 'integer' ),
					'cleanup_complete' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback' => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}
				return mcp_formidable_copy_form_actions_internal(
					isset( $input['source_form_id'] ) ? (int) $input['source_form_id'] : 0,
					isset( $input['target_form_id'] ) ? (int) $input['target_form_id'] : 0,
					isset( $input['field_map'] ) && is_array( $input['field_map'] ) ? $input['field_map'] : array()
				);
			},
			'permission_callback' => function (): bool { return mcp_formidable_can_edit_forms(); },
			'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ) ),
		)
	);

	wp_register_ability(
		'formidable/find-form-usage',
		array(
			'label'               => 'Find Formidable Form Usage',
			'description'         => 'Find where a Formidable form is referenced in post content, Elementor data, and widget instances.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'form_id'  => array(
						'type'        => 'integer',
						'description' => 'Form ID to inspect.',
					),
					'form_key' => array(
						'type'        => 'string',
						'description' => 'Form key to inspect.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'form'    => array( 'type' => 'object' ),
					'usage'   => array( 'type' => 'array' ),
					'total'   => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				$form_id  = isset( $input['form_id'] ) ? (int) $input['form_id'] : 0;
				$form_key = isset( $input['form_key'] ) ? sanitize_key( (string) $input['form_key'] ) : '';

				if ( $form_id <= 0 && '' === $form_key ) {
					return array(
						'success' => false,
						'message' => 'Provide form_id or form_key.',
					);
				}

				$resolved = mcp_formidable_resolve_form( $form_id, $form_key );
				if ( ! $resolved['success'] ) {
					return $resolved;
				}

				$form  = $resolved['form'];
				$usage = mcp_formidable_find_usage_internal( $form );

				return array(
					'success' => true,
					'form'    => $form,
					'usage'   => $usage,
					'total'   => count( $usage ),
					'message' => empty( $usage ) ? 'No usage found.' : 'Usage retrieved successfully.',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/get-form',
		array(
			'label'               => 'Get Formidable Form',
			'description'         => 'Get one Formidable form with normalized options and optional fields.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'form_id'        => array(
						'type'        => 'integer',
						'description' => 'Form ID to retrieve.',
					),
					'form_key'       => array(
						'type'        => 'string',
						'description' => 'Form key to retrieve.',
					),
					'include_fields' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Whether to include normalized fields in the response.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'form'    => array( 'type' => 'object' ),
					'fields'  => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				$form_id  = isset( $input['form_id'] ) ? (int) $input['form_id'] : 0;
				$form_key = isset( $input['form_key'] ) ? sanitize_key( (string) $input['form_key'] ) : '';
				if ( $form_id <= 0 && '' === $form_key ) {
					return array(
						'success' => false,
						'message' => 'Provide form_id or form_key.',
					);
				}

				$form = $form_id > 0 ? mcp_formidable_get_form_row_by_id( $form_id ) : mcp_formidable_get_form_row_by_key( $form_key );
				if ( ! $form ) {
					return array(
						'success' => false,
						'message' => 'Form not found.',
					);
				}

				$result = array(
					'success' => true,
					'form'    => mcp_formidable_normalize_form_detail( $form ),
					'fields'  => array(),
				);

				if ( ! empty( $input['include_fields'] ) ) {
					$result['fields'] = array_map( 'mcp_formidable_normalize_field', mcp_formidable_get_field_rows_for_form( (int) $form->id ) );
				}

				return $result;
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/list-fields',
		array(
			'label'               => 'List Formidable Fields',
			'description'         => 'List normalized fields for a Formidable form.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'form_id'  => array(
						'type'        => 'integer',
						'description' => 'Form ID to inspect.',
					),
					'form_key' => array(
						'type'        => 'string',
						'description' => 'Form key to inspect.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'form'    => array( 'type' => 'object' ),
					'fields'  => array( 'type' => 'array' ),
					'total'   => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				$form_id  = isset( $input['form_id'] ) ? (int) $input['form_id'] : 0;
				$form_key = isset( $input['form_key'] ) ? sanitize_key( (string) $input['form_key'] ) : '';
				if ( $form_id <= 0 && '' === $form_key ) {
					return array(
						'success' => false,
						'message' => 'Provide form_id or form_key.',
					);
				}

				$form = $form_id > 0 ? mcp_formidable_get_form_row_by_id( $form_id ) : mcp_formidable_get_form_row_by_key( $form_key );
				if ( ! $form ) {
					return array(
						'success' => false,
						'message' => 'Form not found.',
					);
				}

				$fields = array_map( 'mcp_formidable_normalize_field', mcp_formidable_get_field_rows_for_form( (int) $form->id ) );

				return array(
					'success' => true,
					'form'    => mcp_formidable_normalize_form( $form ),
					'fields'  => $fields,
					'total'   => count( $fields ),
					'message' => empty( $fields ) ? 'No fields found.' : 'Fields retrieved successfully.',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/get-field',
		array(
			'label'               => 'Get Formidable Field',
			'description'         => 'Get one normalized Formidable field by ID.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'field_id' ),
				'properties'           => array(
					'field_id' => array(
						'type'        => 'integer',
						'description' => 'Field ID to retrieve.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'field'   => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				$field = mcp_formidable_get_field_row_by_id( isset( $input['field_id'] ) ? (int) $input['field_id'] : 0 );
				if ( ! $field ) {
					return array(
						'success' => false,
						'message' => 'Field not found.',
					);
				}

				return array(
					'success' => true,
					'field'   => mcp_formidable_normalize_field( $field ),
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/create-field',
		array(
			'label'               => 'Create Formidable Field',
			'description'         => 'Create a new Formidable field on a form. Supports file fields through type=file and field_options.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'type', 'name' ),
				'properties'           => array(
					'form_id'       => array( 'type' => 'integer' ),
					'form_key'      => array( 'type' => 'string' ),
					'type'          => array( 'type' => 'string' ),
					'name'          => array( 'type' => 'string' ),
					'description'   => array( 'type' => 'string' ),
					'default_value' => array( 'type' => 'string' ),
					'field_key'     => array( 'type' => 'string' ),
					'field_order'   => array( 'type' => 'integer' ),
					'required'      => array( 'type' => 'boolean' ),
					'options'       => array( 'type' => 'object' ),
					'field_options' => array( 'type' => 'object' ),
					'max_file_size_mb' => array(
						'type'        => array( 'number', 'string' ),
						'description' => 'For file fields, set the maximum file size in MB. Maps to Formidable field_options.size.',
					),
					'min_file_size_mb' => array(
						'type'        => array( 'number', 'string' ),
						'description' => 'For file fields, set the minimum file size in MB. Maps to Formidable field_options.min_size.',
					),
					'max_files_per_entry' => array(
						'type'        => array( 'integer', 'string' ),
						'description' => 'For multiple file fields, set the maximum number of files per entry. Maps to Formidable field_options.max.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'field'   => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				if ( empty( $input['type'] ) || empty( $input['name'] ) ) {
					return array(
						'success' => false,
						'message' => 'Provide type and name.',
					);
				}

				$form_id  = isset( $input['form_id'] ) ? (int) $input['form_id'] : 0;
				$form_key = isset( $input['form_key'] ) ? sanitize_key( (string) $input['form_key'] ) : '';
				$form     = $form_id > 0 ? mcp_formidable_get_form_row_by_id( $form_id ) : mcp_formidable_get_form_row_by_key( $form_key );
				if ( ! $form ) {
					return array(
						'success' => false,
						'message' => 'Form not found.',
					);
				}

				$payload = mcp_formidable_build_field_payload( $input, (int) $form->id );

				return mcp_formidable_create_field_internal( $payload );
			},
			'permission_callback' => function (): bool {
				return mcp_formidable_can_edit_forms();
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/update-field',
		array(
			'label'               => 'Update Formidable Field',
			'description'         => 'Update an existing Formidable field. Supports file fields and field_options updates.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'field_id' ),
				'properties'           => array(
					'field_id'      => array( 'type' => 'integer' ),
					'name'          => array( 'type' => 'string' ),
					'description'   => array( 'type' => 'string' ),
					'type'          => array( 'type' => 'string' ),
					'default_value' => array( 'type' => 'string' ),
					'field_key'     => array( 'type' => 'string' ),
					'field_order'   => array( 'type' => 'integer' ),
					'required'      => array( 'type' => 'boolean' ),
					'options'       => array( 'type' => 'object' ),
					'field_options' => array( 'type' => 'object' ),
					'max_file_size_mb' => array(
						'type'        => array( 'number', 'string' ),
						'description' => 'For file fields, set the maximum file size in MB. Maps to Formidable field_options.size.',
					),
					'min_file_size_mb' => array(
						'type'        => array( 'number', 'string' ),
						'description' => 'For file fields, set the minimum file size in MB. Maps to Formidable field_options.min_size.',
					),
					'max_files_per_entry' => array(
						'type'        => array( 'integer', 'string' ),
						'description' => 'For multiple file fields, set the maximum number of files per entry. Maps to Formidable field_options.max.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'field'   => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				$field_id = isset( $input['field_id'] ) ? (int) $input['field_id'] : 0;
				$field    = mcp_formidable_get_field_row_by_id( $field_id );
				if ( ! $field ) {
					return array(
						'success' => false,
						'message' => 'Field not found.',
					);
				}

				$payload = mcp_formidable_build_field_payload( $input, (int) $field->form_id, mcp_formidable_normalize_field( $field ) );

				return mcp_formidable_update_field_internal( $field_id, $payload );
			},
			'permission_callback' => function (): bool {
				return mcp_formidable_can_edit_forms();
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/update-form',
		array(
			'label'               => 'Update Formidable Form',
			'description'         => 'Update Formidable form properties and native form options.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'form_id' ),
				'properties'           => array(
					'form_id'     => array( 'type' => 'integer' ),
					'name'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'form_key'    => array( 'type' => 'string' ),
					'options'     => array(
						'type'        => 'object',
						'description' => 'Native Formidable form options to merge into the existing serialized form options.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'form'    => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				return mcp_formidable_update_form_internal( isset( $input['form_id'] ) ? (int) $input['form_id'] : 0, $input );
			},
			'permission_callback' => function (): bool {
				return mcp_formidable_can_edit_forms();
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/update-post-meta',
		array(
			'label'               => 'Update Post Meta',
			'description'         => 'Update post meta for related Formidable/WordPress maintenance tasks.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'post_id', 'meta' ),
				'properties'           => array(
					'post_id' => array( 'type' => 'integer' ),
					'meta'    => array( 'type' => 'object' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'updated' => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
				if ( $post_id <= 0 || ! get_post( $post_id ) ) {
					return array(
						'success' => false,
						'message' => 'Post not found.',
					);
				}
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return array(
						'success' => false,
						'message' => 'Current user cannot edit this post.',
					);
				}

				$meta = (array) ( $input['meta'] ?? array() );
				foreach ( $meta as $key => $value ) {
					if ( '' === (string) $key || ! current_user_can( 'edit_post_meta', $post_id, (string) $key ) ) {
						return array( 'success' => false, 'updated' => array(), 'message' => 'Current user cannot edit every requested metadata key.' );
					}
				}
				$updated = array();
				foreach ( $meta as $key => $value ) {
					$key = (string) $key;
					$value = is_scalar( $value ) ? (string) $value : $value;
					$saved = update_post_meta( $post_id, $key, wp_slash( $value ) );
					if ( false === $saved && get_post_meta( $post_id, $key, true ) !== $value ) {
						return array( 'success' => false, 'updated' => $updated, 'message' => 'Metadata could not be saved for key: ' . $key );
					}
					$updated[] = $key;
				}
				clean_post_cache( $post_id );

				return array(
					'success' => true,
					'updated' => $updated,
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'edit_posts' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/update-action',
		array(
			'label'               => 'Update Formidable Action',
			'description'         => 'Update a Formidable form action post using structured JSON settings.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'action_id' ),
				'properties'           => array(
					'action_id'   => array( 'type' => 'integer' ),
					'title'       => array( 'type' => 'string' ),
					'slug'        => array( 'type' => 'string' ),
					'action_type' => array( 'type' => 'string' ),
					'settings'    => array( 'type' => 'object' ),
					'content'     => array( 'type' => 'string' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'action'  => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				return mcp_formidable_update_action_post_internal( isset( $input['action_id'] ) ? (int) $input['action_id'] : 0, $input );
			},
			'permission_callback' => function (): bool {
				return mcp_formidable_can_edit_forms();
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'formidable/clear-css-cache',
		array(
			'label'               => 'Clear Formidable CSS Cache',
			'description'         => 'Clears Formidable CSS/settings transients and can optionally rebuild generated CSS.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'rebuild' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'If true, rebuild generated CSS after clearing cache.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'rebuilt' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( array $input = array() ): array {
				$inactive = mcp_formidable_require_active();
				if ( $inactive ) {
					return $inactive;
				}

				mcp_formidable_clear_runtime_transients();
				$rebuilt = false;

				if ( ! array_key_exists( 'rebuild', $input ) || ! empty( $input['rebuild'] ) ) {
					$rebuilt = mcp_formidable_rebuild_css();
				}

				return array(
					'success' => true,
					'rebuilt' => $rebuilt,
					'message' => $rebuilt ? 'Formidable CSS cache cleared and rebuilt.' : 'Formidable CSS cache cleared.',
				);
			},
			'permission_callback' => function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		)
	);
}

add_action( 'wp_abilities_api_init', 'mcp_register_formidable_abilities', 20 );
