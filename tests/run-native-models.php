<?php
/** Registered ability contract tests with in-memory native model doubles. */
define('ABSPATH', __DIR__ . '/');
function add_action(...$args) {}
function wp_register_ability($name, $definition) { $GLOBALS['abilities'][$name] = $definition; }
function sanitize_text_field($v) { return strip_tags($v); }
function sanitize_key($v) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $v)); }
function wp_kses_post($v) { return $v; }
function maybe_unserialize($v) { return $v; }
function wp_slash($v) { return is_array($v) ? array_map('wp_slash',$v) : (is_string($v) ? addslashes($v) : $v); }
function wp_unslash($v) { if(is_object($v)) { $v=clone $v; foreach($v as $k=>$x) $v->$k=wp_unslash($x); return $v; } return is_array($v) ? array_map('wp_unslash',$v) : (is_string($v) ? stripslashes($v) : $v); }
function wp_cache_delete(...$args) {}
class FrmForm {
    public static $forms = [];
    public static $fail = false;
    public static function getOne($id) { foreach (self::$forms as $form) { if ($form->id === $id || $form->form_key === $id) return wp_unslash($form); } return false; }
    public static function getAll($where) { return array_values(array_filter(self::$forms, fn($f) => $f->form_key === $where['form_key'])); }
    public static function update($id, $values) { if (self::$fail) return false; foreach ($values as $key => $value) self::$forms[$id]->$key = $value; return 1; }
    public static function clear_form_cache() {}
    public static function duplicate($id) {
        $GLOBALS['native_duplicates']++;
        self::$forms[8] = clone self::$forms[$id]; self::$forms[8]->id = 8;
        FrmField::$fields[20] = (object) ['id'=>20, 'form_id'=>8, 'field_key'=>'copy-field', 'required'=>1, 'options'=>[], 'field_options'=>['max'=>10]];
        $GLOBALS['frm_duplicate_ids'] = [10=>20, 'original-field'=>20];
        return 8;
    }
    public static function destroy($id) { unset(self::$forms[$id]); unset(FrmField::$fields[20]); }
}
class FrmField {
    public static $fields = [];
    public static function getOne($id) { return isset(self::$fields[$id]) ? wp_unslash(self::$fields[$id]) : false; }
    public static function get_all_for_form($id, $type = '', $embedded = true) { return array_values(array_filter(self::$fields, fn($f) => $f->form_id === $id)); }
    public static function update($id, $values) { foreach ($values as $key => $value) self::$fields[$id]->$key = $value; return 1; }
}
class FrmFormAction { public static function get_action_for_form(...$args) { return [(object)['ID'=>90]]; } }
$GLOBALS['wpdb'] = new class { public $prefix='wp_'; public function __call($name, $args) { throw new RuntimeException('Adapter bypassed native model with SQL: '.$name); } };
require dirname(__DIR__).'/mcp-abilities-formidable.php';
mcp_register_formidable_abilities();
FrmForm::$forms[2] = (object)['id'=>2,'form_key'=>'original','name'=>'Source','status'=>'draft','options'=>['before_html'=>'Keep', 'nested'=>['keep'=>true]]];
FrmField::$fields[10] = (object)['id'=>10,'form_id'=>2,'field_key'=>'original-field','required'=>1,'type'=>'number','options'=>[],'field_options'=>['max'=>10]];
$GLOBALS['native_duplicates']=0;
$GLOBALS['frm_duplicate_ids']=['prior'=>99];
$failed=0;
FrmForm::$forms[3]=(object)['id'=>3,'form_key'=>'2'];
function check($label, $fn) { global $failed; try { $pass=$fn(); } catch (Throwable $e) { $pass=false; echo $e->getMessage()."\n"; } echo ($pass?'PASS ':'FAIL ').$label."\n"; if (!$pass) $failed++; }
check('numeric form key resolves key rather than unrelated form ID', fn() => mcp_formidable_get_form_row_by_key('2')->id===3);
check('clone delegates fields and actions and preserves unrelated numeric options', function() {
 $r=$GLOBALS['abilities']['formidable/clone-form']['execute_callback'](['source_form_id'=>2,'name'=>'Copy','form_key'=>'copy']);
 return $r['success'] && $r['actions_copied']===1 && $r['field_map']===[10=>20] && $r['field_key_map']===['original-field'=>'copy-field'] && FrmField::$fields[20]->field_options['max']===10 && $GLOBALS['native_duplicates']===1 && $GLOBALS['frm_duplicate_ids']===['prior'=>99];
});
check('form options merge through native API without publishing draft', function() {
 $r=mcp_formidable_update_form_internal(2,['options'=>['nested'=>['new'=>true]]]);
 return $r['success'] && FrmForm::$forms[2]->status==='draft' && FrmForm::$forms[2]->options===['before_html'=>'Keep','nested'=>['keep'=>true,'new'=>true]];
});
check('required field update uses native model without schema queries', function() {
 $r=mcp_formidable_update_field_internal(10,['required'=>0]); return $r['success'] && !$r['field']['required'];
});
check('native readback preserves literal backslashes in form and field text', function() {
 $literal='C:\\exports\\file';
 $form=mcp_formidable_update_form_internal(2,['description'=>$literal]);
 $field=mcp_formidable_update_field_internal(10,['description'=>$literal]);
 return $form['success'] && mcp_formidable_get_form_row_by_id(2)->description===$literal && $field['field']['description']===$literal;
});
check('failed clone naming removes only new form and restores native mapping', function() {
 FrmForm::$fail=true;
 $r=mcp_formidable_clone_form_internal(2,'Copy','copy');
 return !$r['success'] && isset(FrmForm::$forms[2]) && !isset(FrmForm::$forms[8]) && $GLOBALS['frm_duplicate_ids']===['prior'=>99];
});
exit($failed?1:0);
