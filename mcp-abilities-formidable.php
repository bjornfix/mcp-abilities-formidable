<?php
/**
 * Plugin Name: MCP Abilities - Formidable
 * Plugin URI: https://github.com/bjornfix/mcp-abilities-formidable
 * Description: Formidable Forms abilities for MCP. Inspect forms, styles, settings, usage, and CSS cache/runtime behavior.
 * Version: 1.2.5
 * Author: Devenia
 * Author URI: https://devenia.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 6.9
 * Requires PHP: 8.0
 *
 * @package MCP_Abilities_Formidable
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if Abilities API is available.
 */
function mcp_formidable_check_dependencies(): bool {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p><strong>MCP Abilities - Formidable</strong> requires the <a href="https://github.com/WordPress/abilities-api">Abilities API</a> plugin to be installed and activated.</p></div>';
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
		return is_string( $value ) ? wp_kses_post( $value ) : '';
	}

	return $value;
}

/**
 * Persist a Formidable option object/array.
 *
 * @param string               $option_name Option name.
 * @param array<string,mixed>  $changes     Setting changes.
 * @return bool
 */
function mcp_formidable_update_option_settings( string $option_name, array $changes ): bool {
	$current = get_option( $option_name, null );
	if ( null === $current ) {
		return false;
	}

	foreach ( $changes as $key => $value ) {
		if ( is_object( $current ) ) {
			$current->{$key} = $value;
		} elseif ( is_array( $current ) ) {
			$current[ $key ] = $value;
		} else {
			return false;
		}
	}

	return update_option( $option_name, $current );
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
 * Return a normalized table name for Formidable tables.
 *
 * @param string $suffix Table suffix without prefix.
 */
function mcp_formidable_table_name( string $suffix ): string {
	global $wpdb;

	return $wpdb->prefix . ltrim( $suffix, '_' );
}

/**
 * Get a raw Formidable form row by ID.
 *
 * @param int $form_id Form ID.
 * @return object|null
 */
function mcp_formidable_get_form_row_by_id( int $form_id ): ?object {
	global $wpdb;

	if ( $form_id <= 0 ) {
		return null;
	}

	$table = mcp_formidable_table_name( 'frm_forms' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP ability read.
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $form_id )
	);

	if ( is_object( $row ) ) {
		return $row;
	}

	if ( class_exists( 'FrmForm' ) && method_exists( 'FrmForm', 'getOne' ) ) {
		$form = FrmForm::getOne( $form_id );
		if ( is_object( $form ) ) {
			return $form;
		}
	}

	return null;
}

/**
 * Get a raw Formidable form row by key.
 *
 * @param string $form_key Form key.
 * @return object|null
 */
function mcp_formidable_get_form_row_by_key( string $form_key ): ?object {
	global $wpdb;

	$form_key = sanitize_key( $form_key );
	if ( '' === $form_key ) {
		return null;
	}

	if ( class_exists( 'FrmForm' ) && method_exists( 'FrmForm', 'getAll' ) ) {
		$rows = FrmForm::getAll(
			array(
				'form_key' => $form_key,
			),
			'name ASC'
		);
		if ( is_array( $rows ) && ! empty( $rows[0] ) && is_object( $rows[0] ) ) {
			return $rows[0];
		}
	}

	$table = mcp_formidable_table_name( 'frm_forms' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP ability read fallback.
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE form_key = %s LIMIT 1", $form_key )
	);

	return is_object( $row ) ? $row : null;
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

	return array(
		'id'            => isset( $field->id ) ? (int) $field->id : 0,
		'field_key'     => isset( $field->field_key ) ? (string) $field->field_key : '',
		'form_id'       => isset( $field->form_id ) ? (int) $field->form_id : 0,
		'name'          => isset( $field->name ) ? (string) $field->name : '',
		'description'   => isset( $field->description ) ? (string) $field->description : '',
		'type'          => isset( $field->type ) ? (string) $field->type : '',
		'default_value' => isset( $field->default_value ) ? (string) $field->default_value : '',
		'required'      => ! empty( $field_options['required'] ) || ! empty( $options['required'] ),
		'field_order'   => isset( $field->field_order ) ? (int) $field->field_order : 0,
		'options'       => $options,
		'field_options' => $field_options,
	);
}

/**
 * Resolve a raw field row by ID.
 *
 * @param int $field_id Field ID.
 * @return object|null
 */
function mcp_formidable_get_field_row_by_id( int $field_id ): ?object {
	global $wpdb;

	if ( $field_id <= 0 ) {
		return null;
	}

	$table = mcp_formidable_table_name( 'frm_fields' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP ability read.
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $field_id )
	);

	if ( is_object( $row ) ) {
		return $row;
	}

	if ( class_exists( 'FrmField' ) && method_exists( 'FrmField', 'getOne' ) ) {
		$field = FrmField::getOne( $field_id );
		if ( is_object( $field ) ) {
			return $field;
		}
	}

	return null;
}

/**
 * Get all field rows for a form.
 *
 * @param int $form_id Form ID.
 * @return array<int,object>
 */
function mcp_formidable_get_field_rows_for_form( int $form_id ): array {
	global $wpdb;

	if ( $form_id <= 0 ) {
		return array();
	}

	$table = mcp_formidable_table_name( 'frm_fields' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP ability read.
	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE form_id = %d ORDER BY field_order ASC, id ASC", $form_id )
	);

	if ( is_array( $rows ) && ! empty( $rows ) ) {
		return array_values( array_filter( $rows, 'is_object' ) );
	}

	if ( class_exists( 'FrmField' ) && method_exists( 'FrmField', 'get_all_for_form' ) ) {
		$rows = FrmField::get_all_for_form( $form_id, '', 'field_order ASC' );
		if ( is_array( $rows ) ) {
			return array_values( array_filter( $rows, 'is_object' ) );
		}
	}

	return array();
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
		$field_options['required'] = ! empty( $input['required'] ) ? 1 : 0;
	}

	$payload['options']       = $options;
	$payload['field_options'] = $field_options;

	return $payload;
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

	$field_id = FrmField::create( $payload, true );
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
	global $wpdb;

	$table   = mcp_formidable_table_name( 'frm_fields' );
	$columns = mcp_formidable_table_columns( $table );
	if ( empty( $columns ) ) {
		return array(
			'success' => false,
			'message' => 'Formidable fields table is unavailable.',
		);
	}

	$allowed = array(
		'name',
		'description',
		'type',
		'default_value',
		'field_key',
		'field_order',
		'options',
		'field_options',
	);
	$update  = array();

	foreach ( $allowed as $key ) {
		if ( ! array_key_exists( $key, $payload ) || ! in_array( $key, $columns, true ) ) {
			continue;
		}

		$value = $payload[ $key ];
		if ( 'options' === $key || 'field_options' === $key ) {
			$value = maybe_serialize( $value );
		}

		$update[ $key ] = $value;
	}

	if ( empty( $update ) ) {
		return array(
			'success' => false,
			'message' => 'No supported field columns were provided.',
		);
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP field update ability.
	$result = $wpdb->update( $table, $update, array( 'id' => $field_id ) );
	if ( false === $result ) {
		return array(
			'success' => false,
			'message' => 'Field update failed: ' . $wpdb->last_error,
		);
	}

	wp_cache_delete( $field_id, 'frm_field' );
	if ( class_exists( 'FrmField' ) && method_exists( 'FrmField', 'clear_cache' ) ) {
		FrmField::clear_cache();
	}

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
 * Update basic Formidable form properties directly.
 *
 * @param int                 $form_id Form ID.
 * @param array<string,mixed> $payload Update payload.
 * @return array<string,mixed>
 */
function mcp_formidable_update_form_internal( int $form_id, array $payload ): array {
	global $wpdb;

	$form = mcp_formidable_get_form_row_by_id( $form_id );
	if ( ! $form ) {
		return array(
			'success' => false,
			'message' => 'Form not found.',
		);
	}

	$table   = mcp_formidable_table_name( 'frm_forms' );
	$columns = mcp_formidable_table_columns( $table );
	$update  = array();

	if ( array_key_exists( 'name', $payload ) && in_array( 'name', $columns, true ) ) {
		$update['name'] = sanitize_text_field( (string) $payload['name'] );
	}
	if ( array_key_exists( 'description', $payload ) && in_array( 'description', $columns, true ) ) {
		$update['description'] = wp_kses_post( (string) $payload['description'] );
	}
	if ( array_key_exists( 'form_key', $payload ) && in_array( 'form_key', $columns, true ) ) {
		$update['form_key'] = sanitize_key( (string) $payload['form_key'] );
	}

	if ( empty( $update ) ) {
		return array(
			'success' => false,
			'message' => 'No supported form columns were provided.',
		);
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP form update ability.
	$result = $wpdb->update( $table, $update, array( 'id' => $form_id ) );
	if ( false === $result ) {
		return array(
			'success' => false,
			'message' => 'Form update failed: ' . $wpdb->last_error,
		);
	}

	$updated = mcp_formidable_get_form_row_by_id( $form_id );

	return array(
		'success' => true,
		'form'    => $updated ? mcp_formidable_normalize_form( $updated ) : null,
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

/**
 * Return table columns for a Formidable table.
 *
 * @param string $table Table name.
 * @return array<int,string>
 */
function mcp_formidable_table_columns( string $table ): array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection for MCP write ability.
	$columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );
	return is_array( $columns ) ? array_map( 'strval', $columns ) : array();
}

/**
 * Return a unique key in a database table.
 */
function mcp_formidable_unique_db_key( string $table, string $column, string $base, int $max_length = 100 ): string {
	global $wpdb;

	$base = sanitize_key( $base );
	if ( '' === $base ) {
		$base = 'formidable-copy';
	}

	$base = substr( $base, 0, $max_length );
	$key  = $base;
	$i    = 2;

	while ( true ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uniqueness check for create ability.
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$column} = %s", $key ) );
		if ( 0 === (int) $exists ) {
			return $key;
		}

		$suffix = '-' . $i;
		$key    = substr( $base, 0, max( 1, $max_length - strlen( $suffix ) ) ) . $suffix;
		$i++;
	}
}

/**
 * Recursively replace exact scalar references in Formidable option arrays.
 *
 * @param mixed               $value Option value.
 * @param array<string,mixed> $map   Exact scalar replacement map.
 * @return mixed
 */
function mcp_formidable_replace_option_refs( $value, array $map ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = mcp_formidable_replace_option_refs( $item, $map );
		}
		return $value;
	}

	if ( is_object( $value ) ) {
		foreach ( get_object_vars( $value ) as $key => $item ) {
			$value->{$key} = mcp_formidable_replace_option_refs( $item, $map );
		}
		return $value;
	}

	if ( is_scalar( $value ) ) {
		$string_value = (string) $value;
		if ( array_key_exists( $string_value, $map ) ) {
			$replacement = $map[ $string_value ];
			return is_int( $value ) ? (int) $replacement : (string) $replacement;
		}
	}

	return $value;
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
	global $wpdb;

	$source = mcp_formidable_get_form_row_by_id( $source_form_id );
	if ( ! $source ) {
		return array(
			'success' => false,
			'message' => 'Source form not found.',
		);
	}

	$forms_table = mcp_formidable_table_name( 'frm_forms' );
	$form_cols   = mcp_formidable_table_columns( $forms_table );
	$form_key    = mcp_formidable_unique_db_key( $forms_table, 'form_key', '' !== $form_key ? $form_key : ( (string) $source->form_key . '-en' ), 100 );

	$form_data = array();
	foreach ( $form_cols as $column ) {
		if ( 'id' === $column || ! property_exists( $source, $column ) ) {
			continue;
		}
		$form_data[ $column ] = $source->{$column};
	}
	$form_data['name']     = '' !== $name ? sanitize_text_field( $name ) : sanitize_text_field( (string) $source->name . ' EN' );
	$form_data['form_key'] = $form_key;
	if ( in_array( 'created_at', $form_cols, true ) ) {
		$form_data['created_at'] = current_time( 'mysql' );
	}
	if ( in_array( 'is_template', $form_cols, true ) ) {
		$form_data['is_template'] = 0;
	}
	if ( in_array( 'parent_form_id', $form_cols, true ) ) {
		$form_data['parent_form_id'] = 0;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Controlled MCP clone operation.
	$inserted = $wpdb->insert( $forms_table, $form_data );
	if ( false === $inserted ) {
		return array(
			'success' => false,
			'message' => 'Could not create cloned form: ' . $wpdb->last_error,
		);
	}

	$new_form_id = (int) $wpdb->insert_id;
	$field_map   = array();
	$key_map     = array();
	$new_fields  = array();
	$fields      = mcp_formidable_get_field_rows_for_form( $source_form_id );
	$fields_table= mcp_formidable_table_name( 'frm_fields' );
	$field_cols  = mcp_formidable_table_columns( $fields_table );

	foreach ( $fields as $field ) {
		$field_data = array();
		foreach ( $field_cols as $column ) {
			if ( 'id' === $column || ! property_exists( $field, $column ) ) {
				continue;
			}
			$field_data[ $column ] = $field->{$column};
		}
		$field_data['form_id'] = $new_form_id;
		if ( in_array( 'field_key', $field_cols, true ) ) {
			$field_data['field_key'] = mcp_formidable_unique_db_key( $fields_table, 'field_key', (string) $field->field_key . '-en', 100 );
		}
		if ( in_array( 'created_at', $field_cols, true ) ) {
			$field_data['created_at'] = current_time( 'mysql' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Controlled MCP clone operation.
		$ok = $wpdb->insert( $fields_table, $field_data );
		if ( false === $ok ) {
			return array(
				'success' => false,
				'message' => 'Could not clone field ' . (int) $field->id . ': ' . $wpdb->last_error,
				'new_form_id' => $new_form_id,
			);
		}

		$new_field_id = (int) $wpdb->insert_id;
		$field_map[ (string) (int) $field->id ] = $new_field_id;
		if ( isset( $field->field_key ) ) {
			$key_map[ (string) $field->field_key ] = (string) $field_data['field_key'];
		}
		$new_fields[] = $new_field_id;
	}

	$ref_map = $field_map + $key_map;
	foreach ( $new_fields as $new_field_id ) {
		$field = mcp_formidable_get_field_row_by_id( $new_field_id );
		if ( ! $field ) {
			continue;
		}
		$options = mcp_formidable_replace_option_refs( mcp_formidable_normalize_field_payload( $field->options ?? array() ), $ref_map );
		$field_options = mcp_formidable_replace_option_refs( mcp_formidable_normalize_field_payload( $field->field_options ?? array() ), $ref_map );
		mcp_formidable_update_field_internal(
			$new_field_id,
			array(
				'options'       => $options,
				'field_options' => $field_options,
			)
		);
	}

	$actions_table = mcp_formidable_table_name( 'frm_form_actions' );
	$actions_copied = 0;
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $actions_table ) ) === $actions_table ) {
		$action_cols = mcp_formidable_table_columns( $actions_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Controlled MCP clone operation.
		$actions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$actions_table} WHERE form_id = %d", $source_form_id ) );
		if ( is_array( $actions ) ) {
			foreach ( $actions as $action ) {
				$action_data = array();
				foreach ( $action_cols as $column ) {
					if ( 'id' === $column || ! property_exists( $action, $column ) ) {
						continue;
					}
					$action_data[ $column ] = $action->{$column};
				}
				$action_data['form_id'] = $new_form_id;
				if ( in_array( 'action_key', $action_cols, true ) && isset( $action->action_key ) ) {
					$action_data['action_key'] = mcp_formidable_unique_db_key( $actions_table, 'action_key', (string) $action->action_key . '-en', 100 );
				}
				if ( in_array( 'created_at', $action_cols, true ) ) {
					$action_data['created_at'] = current_time( 'mysql' );
				}
				if ( in_array( 'updated_at', $action_cols, true ) ) {
					$action_data['updated_at'] = current_time( 'mysql' );
				}
				if ( isset( $action_data['post_content'] ) ) {
					$content = mcp_formidable_replace_option_refs( maybe_unserialize( $action_data['post_content'] ), $ref_map );
					$action_data['post_content'] = maybe_serialize( $content );
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Controlled MCP clone operation.
				if ( false !== $wpdb->insert( $actions_table, $action_data ) ) {
					$actions_copied++;
				}
			}
		}
	}

	$new_form = mcp_formidable_get_form_row_by_id( $new_form_id );
	return array(
		'success'        => true,
		'source_form_id' => $source_form_id,
		'new_form_id'    => $new_form_id,
		'form'           => $new_form ? mcp_formidable_normalize_form_detail( $new_form ) : array(),
		'field_map'      => $field_map,
		'field_key_map'  => $key_map,
		'fields_copied'  => count( $new_fields ),
		'actions_copied' => $actions_copied,
		'shortcode'      => '[formidable id="' . $new_form_id . '"]',
	);
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

				mcp_formidable_update_option_settings( 'frm_options', $changes );
				mcp_formidable_update_option_settings( 'frmpro_options', $changes );
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
			'description'         => 'Update basic Formidable form properties such as name and description.',
			'category'            => 'site',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'form_id' ),
				'properties'           => array(
					'form_id'     => array( 'type' => 'integer' ),
					'name'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'form_key'    => array( 'type' => 'string' ),
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

				$updated = array();
				foreach ( (array) ( $input['meta'] ?? array() ) as $key => $value ) {
					$key = (string) $key;
					if ( '' === $key || ! current_user_can( 'edit_post_meta', $post_id, $key ) ) {
						continue;
					}
					update_post_meta( $post_id, $key, is_scalar( $value ) ? (string) $value : $value );
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
