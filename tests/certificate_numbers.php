<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\{DB,CertificateNumbers,Learning,HttpError};
if(App\env('APP_ENV')!=='test'||App\env('DB_DATABASE')!=='vekec_test')exit('Test environment required');
function check($value,$message){if(!$value)throw new RuntimeException($message);echo "PASS $message\n";}
function rejected(callable $fn,int $code){try{$fn();throw new RuntimeException('Expected rejection');}catch(HttpError $e){check($e->getCode()===$code,'rejection '.$code);}}
$admin=DB::one("SELECT id FROM users WHERE email='admin@example.invalid'")['id'];
$_SESSION=['user_id'=>$admin];
$previous=DB::one('SELECT range_id FROM certificate_numbering WHERE id=1')['range_id'];
$prefix='TEST'.bin2hex(random_bytes(5));$ranges=[];$users=[];$cid=null;
try{
 $_POST=['prefix'=>$prefix,'year'=>2569,'start_number'=>101,'end_number'=>102,'digits'=>4];
 CertificateNumbers::create();$ranges[]=DB::one('SELECT id FROM certificate_number_ranges WHERE prefix=?',[$prefix])['id'];
 rejected(fn()=>CertificateNumbers::create(),422);
 CertificateNumbers::activate((int)$ranges[0]);
 try{DB::transaction(function(){CertificateNumbers::allocate();throw new RuntimeException('rollback');});}catch(RuntimeException $e){if($e->getMessage()!=='rollback')throw $e;}
 check((int)DB::one('SELECT next_number FROM certificate_number_ranges WHERE id=?',[$ranges[0]])['next_number']===101,'rollback preserves next number');
 $category=DB::one('SELECT name FROM categories LIMIT 1')['name'];
 DB::run("INSERT INTO courses(teacher_id,title,code,description,category,status) VALUES(?,'Number test','NUM','test',?,'published')",[$admin,$category]);$cid=DB::id();
 DB::run("INSERT INTO lessons(course_id,title,kind,position) VALUES(?,'test','article',1)",[$cid]);$lid=DB::id();
 for($i=0;$i<3;$i++){DB::run("INSERT INTO users(name,role) VALUES('Number learner','student')");$uid=DB::id();$users[]=$uid;Learning::enroll($uid,$cid);Learning::complete($uid,$lid);}
 $code=Learning::issue($users[0],$cid);
 check(Learning::issue($users[0],$cid)===$code,'repeated issuance reuses certificate');
 $cert=DB::one('SELECT * FROM certificates WHERE code=?',[$code]);
 check($cert['registration_number']===$prefix.' 0101/2569','number format and first boundary');
 Learning::issue($users[1],$cid);
 rejected(fn()=>Learning::issue($users[2],$cid),409);
 check((int)DB::one('SELECT COUNT(*) AS n FROM certificates WHERE course_id=?',[$cid])['n']===2,'exhaustion creates no certificate');
 check(Learning::issue($users[0],$cid)===$code,'existing certificate available after exhaustion');
 CertificateNumbers::activate(0);rejected(fn()=>Learning::issue($users[2],$cid),409);
 $_POST['start_number']=103;$_POST['end_number']=103;CertificateNumbers::create();$ranges[]=DB::one('SELECT id FROM certificate_number_ranges WHERE prefix=? ORDER BY id DESC',[$prefix])['id'];CertificateNumbers::activate((int)$ranges[1]);
 Learning::issue($users[2],$cid);
 check(DB::one('SELECT registration_number FROM certificates WHERE user_id=? AND course_id=?',[$users[2],$cid])['registration_number']===$prefix.' 0103/2569','new range resumes issuance');
 require dirname(__DIR__).'/views/icons.php';
 $certificate=$cert;$isPreview=false;$verify=false;ob_start();require dirname(__DIR__).'/views/certificate-paper.php';$html=ob_get_clean();
 check(str_contains($html,'cert-registration-number')&&str_contains($html,$prefix.' 0101/2569'),'legacy top-right registration rendered');
 $certificate['design_snapshot']=json_encode(App\CertificateDesign::current());ob_start();require dirname(__DIR__).'/views/certificate-paper.php';$html=ob_get_clean();check(str_contains($html,$prefix.' 0101/2569'),'designed certificate registration rendered');
 $_POST['start_number']=104;$_POST['end_number']=107;CertificateNumbers::create();$ranges[]=DB::one('SELECT id FROM certificate_number_ranges WHERE prefix=? ORDER BY id DESC',[$prefix])['id'];CertificateNumbers::activate((int)$ranges[2]);
 $jobs=[];
 for($i=0;$i<4;$i++){
     $script='require '.var_export(dirname(__DIR__).'/app/bootstrap.php',true).'; echo App\\DB::transaction(fn()=>App\\CertificateNumbers::allocate())[1];';
     $process=proc_open([PHP_BINARY,'-r',$script],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
     $jobs[]=[$process,$pipes];
 }
 $allocated=[];
 foreach($jobs as [$process,$pipes]){$allocated[]=(int)stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($process)===0,'concurrent allocation '.$error);}
 sort($allocated);check($allocated===[104,105,106,107],'concurrent requests allocate unique bounded numbers');
 $_SESSION['user_id']=$users[0];rejected(fn()=>CertificateNumbers::activate(0),403);rejected(fn()=>CertificateNumbers::create(),403);
}finally{
 DB::run('UPDATE certificate_numbering SET range_id=? WHERE id=1',[$previous]);
 if($cid){DB::run('DELETE FROM certificates WHERE course_id=?',[$cid]);DB::run('DELETE FROM enrollments WHERE course_id=?',[$cid]);DB::run('DELETE FROM courses WHERE id=?',[$cid]);}
 foreach($users as $uid)DB::run('DELETE FROM users WHERE id=?',[$uid]);
 foreach($ranges as $id)DB::run('DELETE FROM certificate_number_ranges WHERE id=?',[$id]);
}
