<?php
function db(): PDO {
 static $pdo=null; if ($pdo instanceof PDO) return $pdo;
 $c=$GLOBALS['config']['db'];
 $pdo=new PDO('mysql:host='.$c['host'].';dbname='.$c['name'].';charset='.$c['charset'], $c['user'], $c['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 return $pdo;
}
