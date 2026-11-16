<?php
declare(strict_types=1);
namespace App;
final class Actions {
    public function dispatch(string $path): never {
        if($path==='/register'){
            Auth::limit('register',10,3600);$id=Auth::register();Auth::login($id);flash('สมัครสมาชิกสำเร็จ ยินดีต้อนรับสู่พื้นที่การเรียนรู้');redirect('/dashboard');
        }
        if($path==='/login'){
            Auth::limit('login',20);$email=strtolower(input('email',190));$password=input('password',72);
            $u=DB::one('SELECT * FROM users WHERE email=? AND active=1',[$email]);
            $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            if(!password_verify($password,$u['password_hash']??$dummy)||!$u||!$u['password_hash'])abort(422,'อีเมลหรือรหัสผ่านไม่ถูกต้อง');
            Auth::login((int)$u['id']);redirect('/dashboard');
        }
        if($path==='/logout'){
            $_SESSION=[];session_destroy();setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','secure'=>str_starts_with(env('APP_URL'),'https://'),'httponly'=>true,'samesite'=>'Lax']);redirect('/');
        }
        $user=Auth::require();$uid=(int)$user['id'];
        if($path==='/admin/certificate-numbers'){CertificateNumbers::create();flash('บันทึกช่วงเลขแล้ว กรุณาเลือกเปิดใช้งานชุดเลข');redirect('/admin/certificate-numbers');}
        if($path==='/admin/certificate-numbers/activate'){Auth::require(['admin']);CertificateNumbers::activate(integer('range_id',0,PHP_INT_MAX,0));flash('เปลี่ยนชุดเลขที่ใช้งานแล้ว');redirect('/admin/certificate-numbers');}
        if($path==='/admin/banners'){
            Banner::save();flash('เพิ่ม Banner เรียบร้อยแล้ว');redirect('/admin/banners');
        }
        if(preg_match('#^/admin/banners/(\d+)/(save|delete)$#',$path,$m)){
            if($m[2]==='save')Banner::save((int)$m[1]);else Banner::delete((int)$m[1]);
            flash($m[2]==='save'?'บันทึก Banner เรียบร้อยแล้ว':'ลบ Banner เรียบร้อยแล้ว');redirect('/admin/banners');
        }
        if($path==='/admin/categories'){
            Category::save();flash('เพิ่มหมวดหมู่เรียบร้อยแล้ว');redirect('/admin/categories');
        }
        if(preg_match('#^/admin/categories/(\d+)/(save|delete)$#',$path,$m)){
            Auth::require(['admin']);
            if($m[2]==='save'){
                Category::save((int)$m[1]);flash('แก้ไขชื่อหมวดหมู่และอัปเดตรายวิชาที่เกี่ยวข้องแล้ว');
            }else{
                $count=Category::delete((int)$m[1],integer('replacement_id',0,PHP_INT_MAX,0));
                flash($count?'ลบหมวดหมู่และย้ายรายวิชา '.$count.' รายวิชาเรียบร้อยแล้ว':'ลบหมวดหมู่เรียบร้อยแล้ว');
            }
            redirect('/admin/categories');
        }
        if(preg_match('#^/courses/(\d+)/enroll$#',$path,$m)){Auth::require(['student']);Learning::enroll($uid,(int)$m[1]);audit('course.enrolled',$m[1]);flash('ลงทะเบียนเรียนสำเร็จ เริ่มเรียนรู้ได้เลย');redirect('/courses/'.$m[1]);}
        if($path==='/teach/courses'){$cid=Learning::saveCourse();flash('สร้างรายวิชาแล้ว เพิ่มบทเรียนและกิจกรรมก่อนเปิดรับสมัคร');redirect('/teach/'.$cid);}
        if(preg_match('#^/teach/(\d+)/images$#',$path,$m))$this->json(EditorImage::upload((int)$m[1]));
        if(preg_match('#^/teach/(\d+)/(save|status|lessons|enroll|register)$#',$path,$m)){
            $cid=(int)$m[1];Learning::manage($cid);
            if($m[2]==='save')Learning::saveCourse($cid);
            elseif($m[2]==='status')Learning::publish($cid,input('status',20));
            elseif($m[2]==='lessons'){Learning::addLesson($cid);flash('เพิ่มบทเรียนแล้ว');redirect('/teach/'.$cid.'#curriculum');}
            elseif($m[2]==='enroll'){$email=strtolower(input('email',190));$s=DB::one("SELECT id FROM users WHERE email=? AND role='student' AND active=1",[$email])??abort(422,'ไม่พบบัญชีผู้เรียนตามอีเมลนี้');Learning::enroll((int)$s['id'],$cid,$uid);audit('teacher.enrolled',$cid.':'.$s['id']);flash('ลงทะเบียนให้ผู้เรียนเรียบร้อย');redirect('/teach/'.$cid.'/students');}
            elseif($m[2]==='register'){
                DB::transaction(function()use($cid,$uid){$sid=Auth::register(true);Learning::enroll($sid,$cid,$uid);audit('teacher.registered',(string)$sid);});
                flash('สร้างบัญชีและลงทะเบียนรายวิชาแล้ว กรุณาแจ้งรหัสผ่านให้ผู้เรียนผ่านช่องทางส่วนตัว');redirect('/teach/'.$cid.'/students');
            }
            flash('บันทึกข้อมูลแล้ว');redirect('/teach/'.$cid);
        }
        if(preg_match('#^/teach/lessons/(\d+)/delete$#',$path,$m)){$l=Learning::lesson((int)$m[1],false);Learning::deleteLesson((int)$m[1]);flash('ลบบทเรียนแล้ว รายวิชากลับเป็นฉบับร่างเพื่อตรวจสอบอีกครั้ง');redirect('/teach/'.$l['course_id']);}
        if(preg_match('#^/teach/quizzes/(\d+)/(questions|import)$#',$path,$m)){
            $lid=(int)$m[1];$n=$m[2]==='import'?Quiz::import($lid):Quiz::add($lid,[['prompt'=>input('prompt',50000),'options'=>[input('option_a',20000),input('option_b',20000),input('option_c',20000),input('option_d',20000)],'answer'=>integer('answer',0,3,0),'explanation'=>input('explanation',50000,false),'content_format'=>input('content_format',10,false)?:'text']]);
            flash('เพิ่มข้อสอบแล้ว '.$n.' ข้อ');redirect('/teach/quizzes/'.$lid);
        }
        if(preg_match('#^/learn/(\d+)/complete$#',$path,$m)){
            Auth::require(['student']);$l=Learning::lesson((int)$m[1]);
            if(!in_array($l['kind'],['article','video'],true))abort(422,'กิจกรรมนี้ต้องส่งงานหรือทำแบบทดสอบ');
            if($l['kind']==='video'&&$l['require_watch'])abort(422,'ต้องดูวิดีโอครบก่อน ระบบจะบันทึกให้อัตโนมัติ');
            Learning::complete($uid,(int)$m[1]);flash('บันทึกการเรียนบทนี้แล้ว');redirect('/learn/'.$m[1]);
        }
        if(preg_match('#^/api/lessons/(\d+)/video/(start|tick)$#',$path,$m)){
            Auth::require(['student']);$l=Learning::lesson((int)$m[1]);if($l['kind']!=='video'||!$l['require_watch'])abort(422,'บทเรียนนี้ไม่ใช้การติดตามวิดีโอ');
            if($m[2]==='start'){
                $p=DB::one('SELECT watched_seconds,completed_at FROM progress WHERE user_id=? AND lesson_id=?',[$uid,$l['id']]);
                $_SESSION['video']=['lesson_id'=>(int)$l['id'],'token'=>bin2hex(random_bytes(24)),'last'=>microtime(true),'position'=>(float)($p['watched_seconds']??0)];
                $this->json(['token'=>$_SESSION['video']['token'],'position'=>(float)($p['watched_seconds']??0),'completed'=>!empty($p['completed_at'])]);
            }
            $s=$_SESSION['video']??null;$token=input('token',100);$position=filter_var($_POST['position']??null,FILTER_VALIDATE_FLOAT);
            if(!$s||(int)$s['lesson_id']!==(int)$l['id']||!hash_equals($s['token'],$token))abort(409,'วิดีโอถูกเปิดจากหน้าอื่น กรุณาโหลดหน้านี้ใหม่');
            if($position===false||!is_finite($position)||$position<0)abort(422,'ตำแหน่งวิดีโอไม่ถูกต้อง');
            $now=microtime(true);$elapsed=max(0,min(15,$now-$s['last']));$delta=$position-$s['position'];
            $_SESSION['video']['last']=$now;
            $p=DB::one('SELECT watched_seconds,completed_at FROM progress WHERE user_id=? AND lesson_id=?',[$uid,$l['id']]);$watched=(float)($p['watched_seconds']??0);
            if($delta>($elapsed*1.25+1.5)||$position>$watched+$elapsed*1.25+2||$position>(int)$l['duration_seconds']+3){$this->json(['position'=>$watched,'completed'=>!empty($p['completed_at']),'reset'=>true]);}
            $_SESSION['video']['position']=$position;$watched=max($watched,min($position,(int)$l['duration_seconds']));
            DB::run('INSERT INTO progress(user_id,lesson_id,watched_seconds) VALUES(?,?,?) ON DUPLICATE KEY UPDATE watched_seconds=GREATEST(watched_seconds,VALUES(watched_seconds))',[$uid,$l['id'],$watched]);
            $completed=$watched>=max(0.95*(int)$l['duration_seconds'],(int)$l['duration_seconds']-2);
            if($completed)Learning::complete($uid,(int)$l['id']);
            $this->json(['position'=>$watched,'completed'=>$completed||!empty($p['completed_at']),'reset'=>false]);
        }
        if(preg_match('#^/learn/(\d+)/submit$#',$path,$m)){
            Auth::require(['student']);$l=Learning::lesson((int)$m[1]);if($l['kind']!=='assignment')abort(422,'บทเรียนนี้ไม่ใช่ใบงาน');
            $body=input('body',30000,false);$file=Upload::save('file','document');if(!$body&&!$file)abort(422,'กรุณาเขียนคำตอบหรือแนบไฟล์');
            DB::transaction(function()use($uid,$l,$body,$file){
                DB::one('SELECT id FROM enrollments WHERE user_id=? AND course_id=? FOR UPDATE',[$uid,$l['course_id']]);
                $s=DB::one('SELECT * FROM submissions WHERE user_id=? AND lesson_id=? FOR UPDATE',[$uid,$l['id']]);
                if($s&&$s['score']!==null&&(int)$s['score']>=(int)$l['pass_score'])abort(409,'ใบงานนี้ผ่านเกณฑ์แล้ว ไม่สามารถแก้ไขได้');
                DB::run('INSERT INTO submissions(user_id,lesson_id,body,file_path) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE body=VALUES(body),file_path=VALUES(file_path),score=NULL,feedback=NULL,graded_by=NULL,submitted_at=NOW()',[$uid,$l['id'],$body,$file]);
            });flash('ส่งใบงานแล้ว รอครูผู้สอนตรวจให้คะแนน');redirect('/learn/'.$m[1]);
        }
        if(preg_match('#^/teach/submissions/(\d+)/grade$#',$path,$m)){
            $s=DB::one('SELECT s.*,l.course_id,l.pass_score FROM submissions s JOIN lessons l ON l.id=s.lesson_id WHERE s.id=?',[$m[1]])??abort(404,'ไม่พบใบงาน');Learning::manage((int)$s['course_id']);
            DB::transaction(function()use($s,$m,$uid){
                DB::one('SELECT id FROM enrollments WHERE user_id=? AND course_id=? FOR UPDATE',[$s['user_id'],$s['course_id']]);
                if(DB::one('SELECT id FROM certificates WHERE user_id=? AND course_id=?',[$s['user_id'],$s['course_id']]))abort(409,'ออกใบประกาศแล้ว ไม่สามารถเปลี่ยนคะแนนได้');
                $score=integer('score',0,100,0);DB::run('UPDATE submissions SET score=?,feedback=?,graded_by=? WHERE id=?',[$score,input('feedback',10000,false),$uid,$m[1]]);
                if($score>=(int)$s['pass_score'])Learning::complete((int)$s['user_id'],(int)$s['lesson_id']);else DB::run('UPDATE progress SET completed_at=NULL WHERE user_id=? AND lesson_id=?',[$s['user_id'],$s['lesson_id']]);
                audit('assignment.graded',$m[1]);
            });flash('บันทึกคะแนนแล้ว');redirect('/teach/'.$s['course_id'].'/students#submissions');
        }
        if(preg_match('#^/learn/(\d+)/quiz/start$#',$path,$m)){Quiz::start((int)$m[1]);redirect('/learn/'.$m[1]);}
        if(preg_match('#^/quiz/(\d+)/submit$#',$path,$m)){$result=Quiz::submit((int)$m[1]);flash($result['expired']?'หมดเวลาสอบ ผลการสอบครั้งนี้เป็น 0 คะแนน':'ได้ '.$result['score'].' คะแนน · '.($result['passed']?'ผ่านเกณฑ์':'ยังไม่ผ่านเกณฑ์'));redirect('/learn/'.$result['lesson_id']);}
        if(preg_match('#^/courses/(\d+)/certificate$#',$path,$m)){Auth::require(['student']);$code=Learning::issue($uid,(int)$m[1]);redirect('/certificate/'.$code);}
        if($path==='/profile'){
            $record=DB::one('SELECT * FROM users WHERE id=?',[$uid]);$name=input('name',540);$level=input('level',20);
            if(!in_array($level,['voc1','voc2','public'],true))abort(422,'ระดับผู้เรียนไม่ถูกต้อง');
            if($record['thaid_subject'])$name=$record['name'];
            if($level!==$record['level']&&DB::one('SELECT id FROM enrollments WHERE user_id=? LIMIT 1',[$uid]))abort(409,'มีประวัติลงทะเบียนแล้ว หากต้องการเปลี่ยนระดับ กรุณาติดต่อผู้ดูแลระบบ');
            DB::run('UPDATE users SET name=?,level=? WHERE id=?',[$name,$level,$uid]);flash('บันทึกข้อมูลส่วนตัวแล้ว');redirect('/profile');
        }
        if($path==='/profile/password'){
            Auth::limit('password',10);$record=DB::one('SELECT password_hash FROM users WHERE id=?',[$uid]);
            if(!$record['password_hash']||!password_verify(input('current_password',72),$record['password_hash']))abort(422,'รหัสผ่านปัจจุบันไม่ถูกต้อง');
            $new=input('password',72);if(strlen($new)<12)abort(422,'รหัสผ่านใหม่ต้องมีอย่างน้อย 12 ตัวอักษร');
            DB::run('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$uid]);Auth::login($uid);flash('เปลี่ยนรหัสผ่านเรียบร้อย');redirect('/profile');
        }
        if($path==='/admin/settings'){
            Auth::require(['admin']);$value=input('contact_email',190);if(!filter_var($value,FILTER_VALIDATE_EMAIL))abort(422,'รูปแบบอีเมลติดต่อไม่ถูกต้อง');DB::run("UPDATE settings SET setting_value=? WHERE setting_key='contact_email'",[$value]);
            DB::run("UPDATE settings SET setting_value=? WHERE setting_key='registration_open'",[isset($_POST['registration_open'])?'1':'0']);audit('settings.saved','system');flash('บันทึกการตั้งค่าแล้ว');redirect('/admin');
        }
        if($path==='/admin/certificate-design/emblem-preview'){
            Auth::require(['admin']);$content=CertificateImage::download(input('emblem_url',2048));
            $mime=CertificateImage::inspect($content)['mime'];
            $this->json(['data_url'=>'data:'.$mime.';base64,'.base64_encode($content)]);
        }
        if($path==='/admin/certificate-design'){
            Auth::require(['admin']);
            try {CertificateDesign::save($_POST);}
            catch(HttpError $error){
                if($error->getCode()!==422)throw $error;
                http_response_code(422);view('certificate-designer',['design'=>CertificateDesign::current(),'values'=>$_POST,'error'=>$error->getMessage()]);exit;
            }
            flash('บันทึกแม่แบบเกียรติบัตรแล้ว จะใช้กับเกียรติบัตรที่ออกใหม่');redirect('/admin/certificate-design');
        }
        if(preg_match('#^/admin/users/(\d+)$#',$path,$m)){
            Users::updateAccess((int)$m[1]);flash('อัปเดตบัญชีผู้ใช้แล้ว');redirect('/admin#users');
        }
        if($path==='/admin/users'||preg_match('#^/admin/users/(\d+)/save$#',$path,$m)){
            Auth::require(['admin']);$id=$path==='/admin/users'?null:(int)$m[1];
            $member=$id===null?null:Users::find($id);
            try {Users::save($_POST,$id);}
            catch(HttpError $error){
                if(!in_array($error->getCode(),[422,409],true))throw $error;
                http_response_code($error->getCode());view('user-form',['member'=>$member,'values'=>$_POST,'error'=>$error->getMessage()]);exit;
            }
            flash($id===null?'เพิ่มสมาชิกเรียบร้อยแล้ว':'แก้ไขข้อมูลสมาชิกเรียบร้อยแล้ว');redirect('/admin#users');
        }
        if($path==='/admin/users/import'){
            Auth::require(['admin']);
            try {$count=Users::import();}
            catch(HttpError $error){
                if($error->getCode()!==422)throw $error;
                http_response_code(422);view('user-import',['error'=>$error->getMessage()]);exit;
            }
            flash('นำเข้าสมาชิกสำเร็จ '.$count.' คน');redirect('/admin#users');
        }
        if(preg_match('#^/admin/users/(\d+)/delete$#',$path,$m)){
            Users::delete((int)$m[1]);flash('ลบบัญชีสมาชิกเรียบร้อยแล้ว');redirect('/admin#users');
        }
        abort(404,'ไม่พบคำสั่งที่ต้องการ');
    }
    private function json(array $data): never {header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
}
