<?php
require_once __DIR__.'/db.php';
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $url): never { header('Location: '.$url); exit; }
function flash(string $type,string $message): void { $_SESSION['_flash'][]=['type'=>$type,'message'=>$message]; }
function show_flashes(): void { foreach($_SESSION['_flash']??[] as $f) echo '<div class="alert '.e($f['type']).'">'.e($f['message']).'</div>'; unset($_SESSION['_flash']); }
function csrf_token(): string { if(empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { if($_SERVER['REQUEST_METHOD']==='POST' && !hash_equals($_SESSION['_csrf']??'', (string)($_POST['_csrf']??''))) { http_response_code(419); exit('Session expired or invalid security token. Go back and try again.'); } }
function setting(string $key, ?string $default=null): ?string { $s=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?'); $s->execute([$key]); $v=$s->fetchColumn(); return $v===false?$default:(string)$v; }
function save_setting(string $key,string $value): void { $s=db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'); $s->execute([$key,$value]); }
function normalize_ids(string $raw): string { $ids=array_values(array_unique(array_filter(array_map('trim',explode(',',$raw)),fn($x)=>$x!=='' && ctype_digit($x)))); usort($ids,fn($a,$b)=>(int)$b<=>(int)$a); return implode(',',$ids); }
function parse_ids(?string $raw): array { if(!$raw) return []; return array_map('intval',array_filter(explode(',',normalize_ids($raw)),fn($x)=>$x!=='')); }
function app_url(string $path=''): string { return rtrim($GLOBALS['config']['app']['base_url'],'/').'/'.ltrim($path,'/'); }
function active_brands(): array { return db()->query('SELECT * FROM brands WHERE is_active=1 ORDER BY name')->fetchAll(); }
function current_brand_id(): ?int { $ids=array_column(user_brands(), 'id'); $id=(int)($_SESSION['brand_id']??0); if(in_array($id,$ids,true)) return $id; if($ids){$_SESSION['brand_id']=$ids[0]; return $ids[0];} return null; }
function user_brands(): array { if(!current_user()) return []; if(can('brands.manage') && in_array(current_user()['role'],['super_admin','admin'],true)) return active_brands(); $s=db()->prepare('SELECT b.* FROM brands b JOIN user_brands ub ON ub.brand_id=b.id WHERE ub.user_id=? AND b.is_active=1 ORDER BY b.name'); $s->execute([current_user()['id']]); return $s->fetchAll(); }
function current_brand(): ?array { $id=current_brand_id(); if(!$id)return null; foreach(user_brands() as $b) if((int)$b['id']===$id)return $b; return null; }
function audit(string $action,?string $detail=null): void { try{$s=db()->prepare('INSERT INTO audit_log(user_id,action,detail,ip_address,created_at) VALUES(?,?,?,?,NOW())');$s->execute([current_user()['id']??null,$action,$detail?substr($detail,0,250):null,substr($_SERVER['REMOTE_ADDR']??'',0,45)]);}catch(Throwable $e){} }
