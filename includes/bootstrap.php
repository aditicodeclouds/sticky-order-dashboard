<?php
declare(strict_types=1);
$configPath = dirname(__DIR__).'/config/config.php';
if (!is_file($configPath)) { http_response_code(500); exit('Configuration missing. Copy config/config.example.php to config/config.php and edit it.'); }
$GLOBALS['config'] = require $configPath;
$config = $GLOBALS['config'];
date_default_timezone_set($config['app']['timezone'] ?? 'UTC');
ini_set('session.use_strict_mode','1'); ini_set('session.cookie_httponly','1'); ini_set('session.cookie_samesite','Lax');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name($config['app']['session_name'] ?? 'sticky_dashboard_session');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/functions.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/sticky.php';
