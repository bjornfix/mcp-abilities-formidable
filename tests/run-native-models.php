<?php
/** Registered ability contracts with isolated native model doubles. */
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
function current_user_can($cap) { return in_array($cap,$GLOBALS['test_caps']??[],true); }
function wp_delete_post($id, $force) { foreach(FrmFormAction::$actions as &$actions) { foreach($actions as $k=>$action) { if($action->ID===$id) { unset($actions[$k]);return $id; } } } return false; }
class FrmForm {
 public static $forms=[];
 public static $fail=false;
 public static function getOne($id) { foreach(self::$forms as $form) { if($form->id===$id||$form->form_key===$id)return wp_unslash($form); } return false; }
 public static function getAll($where) { return array_values(array_filter(self::$forms,fn($f)=>$f->form_key===$where['form_key'])); }
 public static function update($id,$values) { if(self::$fail)return false;foreach($values as $key=>$value)self::$forms[$id]->$key=$value;return 1; }
 public static function clear_form_cache() {}
 public static function duplicate($id) {
  $GLOBALS['native_duplicates']++;
  seed_copy();
  $GLOBALS['frm_duplicate_ids']=[10=>20,'original-field'=>20];
  if($GLOBALS['native_hook_copies']??false)(new TestActionHandler())->duplicate_form_actions(8,$id);
  return 8;
 }
 public static function destroy($id) { unset(self::$forms[$id],FrmField::$fields[20],FrmFormAction::$actions[$id]);return true; }
}
class FrmField {
 public static $fields=[];
 public static function getOne($id) { return isset(self::$fields[$id])?wp_unslash(self::$fields[$id]):false; }
 public static function get_all_for_form($id,$type='',$embedded=true) { return array_map('wp_unslash',array_values(array_filter(self::$fields,fn($f)=>$f->form_id===$id))); }
 public static function update($id,$values) { foreach($values as $key=>$value)self::$fields[$id]->$key=$value;return 1; }
}
class FrmFormAction {
 public static $actions=[];
 public static function get_action_for_form($id,...$args) { return self::$actions[$id]??[]; }
 public static function clear_cache() {}
}
class FrmFormActionsController { public static function get_form_actions($type='all') { return ['email'=>new TestActionHandler()]; } }
class TestActionHandler {
 public static $calls=0;
 public static $fail=false;
 public function duplicate_form_actions($target,$source) {
  self::$calls++;
  if(($GLOBALS['frm_duplicate_ids'][10]??null)!==20||($GLOBALS['frm_duplicate_ids']['original-field']??null)!==20)throw new RuntimeException('Native action handler did not receive the active field mapping');
  foreach(FrmFormAction::$actions[$source]??[] as $action) {
   $copy=clone $action;$copy->ID+=100;$copy->field_id=$GLOBALS['frm_duplicate_ids'][$copy->field_id];
   FrmFormAction::$actions[$target][]=$copy;
   if(self::$fail)throw new RuntimeException('Native action copy failed after one insert');
  }
 }
}
class FrmDb {
 public static $counts=[];
 public static function get_count($table,$where) { if($table!=='frm_items'||array_keys($where)!==['form_id']||$where['form_id']!==8)throw new RuntimeException('Entry guard must count only the explicit target');return self::$counts[8]??0; }
}
$GLOBALS['wpdb']=new class { public $prefix='wp_';public function __call($name,$args){throw new RuntimeException('Adapter bypassed native models with SQL: '.$name);} };
require dirname(__DIR__).'/mcp-abilities-formidable.php';
mcp_register_formidable_abilities();
FrmForm::$forms[2]=(object)['id'=>2,'form_key'=>'original','name'=>'Source','status'=>'draft','options'=>['before_html'=>'Keep','nested'=>['keep'=>true]]];
FrmField::$fields[10]=(object)['id'=>10,'form_id'=>2,'field_key'=>'original-field','name'=>'Order code','required'=>1,'type'=>'number','options'=>[],'field_options'=>['max'=>10]];
FrmFormAction::$actions[2]=[(object)['ID'=>90,'field_id'=>10,'recipient'=>'forms@example.test']];
$GLOBALS['native_duplicates']=0;$GLOBALS['frm_duplicate_ids']=['prior'=>99];
function seed_copy() {
 FrmForm::$forms[8]=clone FrmForm::$forms[2];FrmForm::$forms[8]->id=8;FrmForm::$forms[8]->form_key='copy';
 FrmField::$fields[20]=clone FrmField::$fields[10];FrmField::$fields[20]->id=20;FrmField::$fields[20]->form_id=8;FrmField::$fields[20]->field_key='copy-field';
 FrmFormAction::$actions[8]=[];FrmDb::$counts[8]=0;
}
$failed=0;
function check($label,$fn){global $failed;try{$pass=$fn();}catch(Throwable $e){$pass=false;echo $e->getMessage()."\n";}echo($pass?'PASS ':'FAIL ').$label."\n";if(!$pass)$failed++;}
FrmForm::$forms[3]=(object)['id'=>3,'form_key'=>'2'];
check('numeric form key resolves key rather than unrelated form ID',fn()=>mcp_formidable_get_form_row_by_key('2')->id===3);
check('clone copies actions when builder hooks are absent and restores prior mapping',function(){
 $before=serialize(FrmFormAction::$actions[2]);
 $r=$GLOBALS['abilities']['formidable/clone-form']['execute_callback'](['source_form_id'=>2,'name'=>'Copy','form_key'=>'copy']);
 return $r['success']&&$r['actions_copied']===1&&$r['field_map']===[10=>20]&&$r['field_key_map']===['original-field'=>'copy-field']&&FrmField::$fields[20]->field_options['max']===10&&TestActionHandler::$calls===1&&FrmFormAction::$actions[8][0]->field_id===20&&serialize(FrmFormAction::$actions[2])===$before&&$GLOBALS['frm_duplicate_ids']===['prior'=>99];
});
check('existing native duplication hooks do not cause duplicate actions',function(){
 $GLOBALS['native_hook_copies']=true;$before=TestActionHandler::$calls;
 $r=mcp_formidable_clone_form_internal(2,'Copy','copy');$GLOBALS['native_hook_copies']=false;
 return $r['success']&&$r['actions_copied']===1&&TestActionHandler::$calls===$before+1&&count(FrmFormAction::$actions[8])===1;
});
check('partial action-copy failure removes only the new form',function(){
 TestActionHandler::$fail=true;$r=mcp_formidable_clone_form_internal(2,'Copy','copy');TestActionHandler::$fail=false;
 return !$r['success']&&!isset(FrmForm::$forms[8])&&isset(FrmForm::$forms[2])&&count(FrmFormAction::$actions[2])===1&&$GLOBALS['frm_duplicate_ids']===['prior'=>99];
});
check('form options merge through native API without publishing draft',function(){
 $r=mcp_formidable_update_form_internal(2,['options'=>['nested'=>['new'=>true]]]);return $r['success']&&FrmForm::$forms[2]->status==='draft'&&FrmForm::$forms[2]->options===['before_html'=>'Keep','nested'=>['keep'=>true,'new'=>true]];
});
check('required field update uses native model without schema queries',function(){
 $r=mcp_formidable_update_field_internal(10,['required'=>0]);return $r['success']&&!$r['field']['required'];
});
check('native readback preserves literal backslashes in form and field text',function(){
 $literal='C:\\exports\\file';$form=mcp_formidable_update_form_internal(2,['description'=>$literal]);$field=mcp_formidable_update_field_internal(10,['description'=>$literal]);return $form['success']&&mcp_formidable_get_form_row_by_id(2)->description===$literal&&$field['field']['description']===$literal;
});
check('empty unedited copy gets native actions without changing either form or fields',function(){
 seed_copy();$before=serialize([FrmForm::$forms,FrmField::$fields,FrmFormAction::$actions[2]]);
 $r=$GLOBALS['abilities']['formidable/copy-form-actions']['execute_callback'](['source_form_id'=>2,'target_form_id'=>8,'field_map'=>[10=>20]]);
 return $r['success']&&$r['actions_copied']===1&&FrmFormAction::$actions[8][0]->field_id===20&&serialize([FrmForm::$forms,FrmField::$fields,FrmFormAction::$actions[2]])===$before&&$GLOBALS['frm_duplicate_ids']===['prior'=>99];
});
check('existing target actions are never replaced or appended',function(){
 $before=serialize(FrmFormAction::$actions);$r=mcp_formidable_copy_form_actions_internal(2,8,[10=>20]);return !$r['success']&&serialize(FrmFormAction::$actions)===$before;
});
check('entry guard refuses any target entries before action writes',function(){
 seed_copy();FrmDb::$counts[8]=1;$before=TestActionHandler::$calls;$r=mcp_formidable_copy_form_actions_internal(2,8,[10=>20]);FrmDb::$counts[8]=0;return !$r['success']&&TestActionHandler::$calls===$before&&FrmFormAction::$actions[8]===[];
});
check('incomplete or foreign field maps cannot copy actions',function(){
 seed_copy();$a=mcp_formidable_copy_form_actions_internal(2,8,[]);$b=mcp_formidable_copy_form_actions_internal(2,8,[10=>10]);return !$a['success']&&!$b['success']&&FrmFormAction::$actions[8]===[];
});
check('edited target fields cannot receive copied routing',function(){
 seed_copy();FrmField::$fields[20]->name='Different purpose';$r=mcp_formidable_copy_form_actions_internal(2,8,[10=>20]);return !$r['success']&&FrmFormAction::$actions[8]===[];
});
check('edited target form options cannot receive copied routing',function(){
 seed_copy();FrmForm::$forms[8]->options=['different'=>true];$r=mcp_formidable_copy_form_actions_internal(2,8,[10=>20]);return !$r['success']&&FrmFormAction::$actions[8]===[];
});
check('failed completion removes only the newly inserted actions',function(){
 seed_copy();TestActionHandler::$fail=true;$r=mcp_formidable_copy_form_actions_internal(2,8,[10=>20]);TestActionHandler::$fail=false;return !$r['success']&&$r['cleanup_complete']&&isset(FrmForm::$forms[8])&&FrmFormAction::$actions[8]===[]&&count(FrmFormAction::$actions[2])===1&&$GLOBALS['frm_duplicate_ids']===['prior'=>99];
});
check('action-copy ability requires form-edit capability',function(){
 $permission=$GLOBALS['abilities']['formidable/copy-form-actions']['permission_callback'];$GLOBALS['test_caps']=['edit_posts'];$denied=!$permission();$GLOBALS['test_caps']=['frm_edit_forms'];return $denied&&$permission();
});
check('failed clone naming removes only new form and restores native mapping',function(){
 FrmForm::$fail=true;$r=mcp_formidable_clone_form_internal(2,'Copy','copy');FrmForm::$fail=false;return !$r['success']&&isset(FrmForm::$forms[2])&&!isset(FrmForm::$forms[8])&&$GLOBALS['frm_duplicate_ids']===['prior'=>99];
});
exit($failed?1:0);
