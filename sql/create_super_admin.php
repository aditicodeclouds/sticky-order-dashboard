<?php
// Run only from CLI after schema import and config setup: php sql/create_super_admin.php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require __DIR__.'/../includes/bootstrap.php';
if((int)db()->query('SELECT COUNT(*) FROM users WHERE role="super_admin"')->fetchColumn()>0){fwrite(STDERR,"A Super Admin already exists. Refusing to create another.\n");exit(1);}
$name=trim(readline('Super Admin name: '));$email=trim(readline('Super Admin email: '));if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Valid name and email required.\n");exit(1);}
if(function_exists('readline')){$password='';fwrite(STDOUT,'Password (minimum 12 chars): ');system('stty -echo');$password=trim(fgets(STDIN));system('stty echo');fwrite(STDOUT,"\n");}else $password=trim(readline('Password: '));if(strlen($password)<12){fwrite(STDERR,"Password must be at least 12 characters.\n");exit(1);}
$s=db()->prepare('INSERT INTO users(name,email,password_hash,role,is_active,created_at) VALUES(?,?,?,"super_admin",1,NOW())');$s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);fwrite(STDOUT,"Super Admin created. Delete no files; keep config private.\n");
