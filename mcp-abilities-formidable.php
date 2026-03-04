<?php
/**
 * Plugin Name: MCP Abilities - Formidable
 * Plugin URI: https://github.com/bjornfix/mcp-abilities-formidable
 * Description: Formidable Forms abilities for MCP. Inspect forms, styles, settings, usage, and CSS cache/runtime behavior.
 * Version: 1.0.3
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
