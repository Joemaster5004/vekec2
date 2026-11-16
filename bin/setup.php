<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\DB;
use function App\env;
if(PHP_SAPI!=='cli') exit(1);
$email=env('ADMIN_EMAIL'); $password=env('ADMIN_PASSWORD');
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12||str_starts_with($password,'replace-')) {fwrite(STDERR,"Set ADMIN_EMAIL and a strong ADMIN_PASSWORD in .env first.\n");exit(1);}
if(DB::one('SELECT id FROM users WHERE email=?',[$email])) {echo "Administrator already exists; no changes made.\n";exit;}
DB::run("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'admin')",[env('ADMIN_NAME','ผู้ดูแลระบบ'),$email,password_hash($password,PASSWORD_DEFAULT)]);
echo "Administrator created.\n";
