<?php
/** Focused field payload and registered metadata callback checks. */
define('ABSPATH', __DIR__ . '/');
function add_action(...$args) {}
function wp_register_ability($name, $definition) { $GLOBALS['abilities'][$name] = $definition; }
function current_user_can(...$args) { return !('edit_post_meta' === $args[0] && ($GLOBALS['denied_key'] ?? '') === ($args[2] ?? '')); }
function get_post($id) { return (object) ['ID' => $id]; }
function clean_post_cache($id) {}
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
function update_post_meta($id, $key, $value) { if (!empty($GLOBALS['fail_meta'])) return false; $GLOBALS['meta'][$id][$key] = wp_unslash($value); return true; }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function maybe_unserialize($value) { return $value; }
require dirname(__DIR__) . '/mcp-abilities-formidable.php';
mcp_register_formidable_abilities();
$failed = 0;
function check($condition, $label) { global $failed; echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n"; if (!$condition) $failed++; }
$payload = mcp_formidable_build_field_payload(['required' => true], 2);
check(($payload['required'] ?? null) === 1 && !isset($payload['field_options']['required']), 'required uses Formidable native field column');
$field = mcp_formidable_normalize_field((object) ['id' => 5, 'form_id' => 2, 'required' => 1]);
check($field['required'] === true, 'readback reflects native required column');
$value = ['path' => 'C:\\exports\\course.csv', 'json' => json_encode(['quoted' => 'a "quoted" value'])];
$result = $GLOBALS['abilities']['formidable/update-post-meta']['execute_callback'](['post_id' => 4, 'meta' => ['config' => $value]]);
check($result['success'] && get_post_meta(4, 'config', true) === $value, 'metadata preserves nested backslashes and JSON quotes');
$GLOBALS['fail_meta'] = true;
$result = $GLOBALS['abilities']['formidable/update-post-meta']['execute_callback'](['post_id'=>4,'meta'=>['broken'=>'new']]);
check(!$result['success'] && empty($result['updated']), 'failed metadata persistence is not reported as success');
$GLOBALS['fail_meta'] = false;
$GLOBALS['denied_key'] = 'restricted';
$result = $GLOBALS['abilities']['formidable/update-post-meta']['execute_callback'](['post_id'=>4,'meta'=>['before'=>'new','restricted'=>'new']]);
check(!$result['success'] && get_post_meta(4,'before',true)==='', 'all metadata permissions checked before first write');
exit($failed ? 1 : 0);
