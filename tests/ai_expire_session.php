<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\DB;
use function App\env;
if(PHP_SAPI!=='cli'||env('APP_ENV')!=='test'||env('DB_DATABASE')!=='vekec_test'||!preg_match('/^[A-Za-z0-9,-]{16,128}$/',$argv[1]??''))exit(1);
session_id($argv[1]);session_start();
$u=DB::one('SELECT email FROM users WHERE id=?',[$_SESSION['user_id']??0]);
if(($u['email']??'')!=='teacher@example.invalid')exit(1);
$_SESSION['authenticated_at']=time()-901;session_write_close();echo "Test reauthentication expired.\n";
