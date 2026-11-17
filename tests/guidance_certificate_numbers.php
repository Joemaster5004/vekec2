<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\{DB,CertificateNumbers,CertificateDesign,HttpError};
if(App\env('APP_ENV')!=='test'||App\env('DB_DATABASE')!=='vekec_test')exit(1);
function check(bool $v,string $label):void {if(!$v)throw new RuntimeException($label);echo "PASS $label\n";}
$_SESSION=['user_id'=>DB::one("SELECT id FROM users WHERE email='admin@example.invalid'")['id']];
$previous=DB::one('SELECT range_id FROM guidance_certificate_numbering WHERE id=1')['range_id'];
$mainBefore=DB::all('SELECT * FROM certificate_number_ranges ORDER BY id');
$prefix='GUIDANCE-UNIT-'.bin2hex(random_bytes(4));$ranges=[];
try {
    $_POST=['prefix'=>$prefix,'year'=>2569,'start_number'=>101,'end_number'=>107,'digits'=>4];
    CertificateNumbers::create(true);
    $id=DB::one('SELECT id FROM guidance_certificate_number_ranges WHERE prefix=?',[$prefix])['id'];$ranges[]=$id;
    CertificateNumbers::activate((int)$id,true);
    try {DB::transaction(function(){CertificateNumbers::allocate(true);throw new RuntimeException('rollback');});}
    catch(RuntimeException $e){if($e->getMessage()!=='rollback')throw $e;}
    check((int)DB::one('SELECT next_number FROM guidance_certificate_number_ranges WHERE id=?',[$id])['next_number']===101,'failed issuance does not consume guidance serial');
    $jobs=[];
    for($i=0;$i<7;$i++){
        $script='require '.var_export(dirname(__DIR__).'/app/bootstrap.php',true).'; echo App\\DB::transaction(fn()=>App\\CertificateNumbers::allocate(true))[1];';
        $process=proc_open([PHP_BINARY,'-r',$script],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$jobs[]=[$process,$pipes];
    }
    $numbers=[];
    foreach($jobs as [$process,$pipes]){
        $numbers[]=(int)stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        check(proc_close($process)===0,'concurrent request succeeded '.$error);
    }
    sort($numbers);check($numbers===range(101,107),'concurrent guidance allocations are unique and bounded');
    try{DB::transaction(fn()=>CertificateNumbers::allocate(true));throw new RuntimeException('Expected exhaustion');}
    catch(HttpError $e){check($e->getCode()===409,'guidance exhaustion stops allocation');}
    check(DB::all('SELECT * FROM certificate_number_ranges ORDER BY id')===$mainBefore,'guidance serials do not change course serials');
    try {
        DB::transaction(function(){
            $main=CertificateDesign::current();$main['signature_path']=str_repeat('a',40).'.png';$main['emblem_path']=str_repeat('b',40).'.png';
            DB::run("INSERT INTO settings(setting_key,setting_value) VALUES('certificate_design',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[json_encode($main)]);
            DB::run("DELETE FROM settings WHERE setting_key='guidance_certificate_design'");
            $initial=CertificateDesign::current(true);
            check($initial['signature_path']===$main['signature_path']&&$initial['emblem_path']===$main['emblem_path'],'initial guidance design inherits college signature and seal');
            check(!$initial['show_hours']&&$initial['subtitle']==='CERTIFICATE OF PARTICIPATION','guidance defaults describe participation');
            $data=$initial;$data['director_name']='Guidance director';$data['institution']='Guidance college';$data['heading']='Guidance certificate';unset($data['show_hours']);
            $_FILES=[];CertificateDesign::save($data,true);
            check(CertificateDesign::current()['institution']===$main['institution'],'saving guidance design preserves course institution');
            $issued=CertificateDesign::issuance(true);$saved=json_decode($issued['design_snapshot'],true);
            check($saved['signature_path']===$main['signature_path']&&$saved['director_name']==='Guidance director','issuance snapshots inherited signature and guidance identity');
            throw new RuntimeException('rollback settings');
        });
    }catch(RuntimeException $e){if($e->getMessage()!=='rollback settings')throw $e;}
    // Existing guidance certificates retain the original renderer and gain no retrospective serial.
    $legacy=DB::one('SELECT code FROM department_video_certificates c WHERE NOT EXISTS(SELECT 1 FROM guidance_certificate_details d WHERE d.certificate_id=c.id) LIMIT 1');
    if($legacy){
        $certificate=App\DepartmentVideo::certificate($legacy['code']);
        check($certificate['design_snapshot']===null&&$certificate['registration_number']===null,'old guidance certificates retain their original format and no new serial');
    }
} finally {
    DB::run('UPDATE guidance_certificate_numbering SET range_id=? WHERE id=1',[$previous]);
    foreach($ranges as $id)DB::run('DELETE FROM guidance_certificate_number_ranges WHERE id=?',[$id]);
}
