<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\{DB,AiCredentials,AiBilling,AiError};
use function App\env;
if(PHP_SAPI!=='cli'||env('APP_ENV')!=='test'||env('DB_DATABASE')!=='vekec_test')exit(1);
if(($argv[1]??'')==='setup') {
    $u=DB::one("SELECT * FROM users WHERE email='teacher@example.invalid'");$_SESSION=['user_id'=>$u['id']];
    DB::run('INSERT INTO courses(teacher_id,title,code,description,category) VALUES(?,?,?,?,?)',[$u['id'],'Concurrency test','BYOK-RACE','Test only','ทดสอบระบบ']);$cid=DB::id();
    $c=AiCredentials::create((int)$u['id'],['provider'=>'mock','label'=>'Concurrent budget','secret'=>'test-valid-concurrent']);AiCredentials::verify((int)$u['id'],$c['credential_id'],['allow_billable_test'=>false,'confirmed_provider_revision'=>1]);
    AiBilling::save($u,$cid,['expected_revision'=>0,'confirmed_charge_acceptance'=>true,'bindings'=>[['capability'=>'text','payer'=>'teacher','credential_id'=>$c['credential_id'],'model'=>'mock-model','monthly_budget'=>0.01,'currency'=>'USD']]]);
    echo json_encode(['course_id'=>$cid,'user_id'=>$u['id']]);exit;
}
if(($argv[1]??'')==='reserve') {
    try{AiBilling::reserve((int)$argv[2],(int)$argv[3],'text',6000,$argv[4]);echo 'reserved';}
    catch(AiError $e){echo $e->reason;}
    exit;
}
if(($argv[1]??'')==='total')echo DB::one("SELECT COALESCE(SUM(reserved_micros),0) AS total FROM ai_usage_reservations WHERE course_id=? AND status<>'released'",[(int)$argv[2]])['total'];
