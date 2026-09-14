<?php
/** Native settings persistence contract, isolated from WordPress databases. */
define('ABSPATH', __DIR__.'/');
function add_action(...$args) {}
function wp_register_ability($name,$definition) { $GLOBALS['abilities'][$name]=$definition; }
function wp_kses_post($v) { return strip_tags($v); }
function sanitize_textarea_field($v) { return strip_tags($v); }
function get_option($key,$default=false) { $v=$GLOBALS['options'][$key]??$default; return is_object($v)?clone $v:$v; }
function update_option($key,$value) { if ($GLOBALS['fail_store']) return false; $GLOBALS['options'][$key]=is_object($value)?clone $value:$value; return true; }
function delete_transient(...$args) {}
class FrmSettings {
 public $load_style='all'; public $custom_style=1; public $custom_css='';
 public function store() { $GLOBALS['store_calls']++; update_option('frm_options',$this); }
}
class FrmAppHelper { public static function get_settings() { return $GLOBALS['settings']; } }
$GLOBALS['settings']=new FrmSettings();
$GLOBALS['options']=['frm_options'=>clone $GLOBALS['settings'],'frmpro_options'=>(object)['unrelated'=>'keep']];
$GLOBALS['fail_store']=false; $GLOBALS['store_calls']=0;
require dirname(__DIR__).'/mcp-abilities-formidable.php';
mcp_register_formidable_abilities();
$failed=0;
function check($condition,$label) { global $failed; echo ($condition?'PASS ':'FAIL ').$label."\n"; if (!$condition) $failed++; }
$call=$GLOBALS['abilities']['formidable/update-settings']['execute_callback'];
$r=$call(['load_style'=>'dynamic','rebuild_css'=>false]);
check($r['success'] && $GLOBALS['store_calls']===1 && $r['effective']['load_style']==='dynamic' && get_option('frmpro_options')==(object)['unrelated'=>'keep'], 'native settings store updates effective cache without copying settings to Pro');
$GLOBALS['fail_store']=true;
$r=$call(['load_style'=>'none','rebuild_css'=>false]);
check(!$r['success'] && $GLOBALS['settings']->load_style==='dynamic', 'failed settings persistence returns failure and restores runtime value');
$GLOBALS['fail_store']=false;
$r=$call(['jquery_css'=>true,'rebuild_css'=>false]);
check(!$r['success'] && !property_exists($GLOBALS['settings'],'jquery_css'), 'unavailable legacy settings rejected before mutation');
exit($failed?1:0);
