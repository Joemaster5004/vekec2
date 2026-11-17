<?php
declare(strict_types=1);
// Reproduce shared hosting with local vendor and a restrictive open_basedir.
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test')exit(1);
$source=file_get_contents(dirname(__DIR__).'/app/bootstrap.php');
$root=sys_get_temp_dir().'/lms-autoload-'.bin2hex(random_bytes(8));
mkdir($root.'/app',0700,true);mkdir($root.'/vendor',0700,true);
file_put_contents($root.'/app/bootstrap.php',$source);
file_put_contents($root.'/vendor/autoload.php','<?php $GLOBALS["local_vendor_loaded"]=true;');
set_error_handler(static function(int $severity,string $message): never {throw new RuntimeException($message);});
ini_set('open_basedir',$root);
try {
    require $root.'/app/bootstrap.php';
    if(empty($GLOBALS['local_vendor_loaded']))throw new RuntimeException('Local vendor not loaded');
    echo "PASS local vendor loaded with open_basedir restriction and no warnings.\n";
} finally {
    unlink($root.'/app/bootstrap.php');unlink($root.'/vendor/autoload.php');
    rmdir($root.'/app');rmdir($root.'/vendor');rmdir($root);
}
