<?php
function current_user(): ?array { return $_SESSION['user']??null; }
function require_login(): void { if(!current_user()) redirect('login.php'); }
function require_guest(): void { if(current_user()) redirect('dashboard.php'); }
function can(string $permission): bool { $u=current_user(); if(!$u)return false; if($u['role']==='super_admin')return true; $s=db()->prepare('SELECT allowed FROM permissions WHERE role=? AND permission_key=?');$s->execute([$u['role'],$permission]);return (bool)$s->fetchColumn(); }
function require_permission(string $permission): void { require_login(); if(!can($permission)){http_response_code(403);exit('You do not have permission to access this page.');} }
function login_user(array $user): void { session_regenerate_id(true); $_SESSION['user']=['id'=>(int)$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role']]; audit('login'); }
function logout_user(): void { if(current_user())audit('logout'); $_SESSION=[]; if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',$p['secure'],$p['httponly']);} session_destroy(); }
