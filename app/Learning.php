<?php
declare(strict_types=1);
namespace App;
final class Learning {
    public static function course(int $id): array {
        return DB::one('SELECT c.*,u.name AS teacher_name FROM courses c JOIN users u ON u.id=c.teacher_id WHERE c.id=?',[$id]) ?? abort(404,'ไม่พบรายวิชา');
    }
    public static function manage(int $id): array {
        $user=Auth::require(['admin','teacher']); $course=self::course($id);
        if($user['role']!=='admin' && (int)$course['teacher_id']!==(int)$user['id']) abort(403,'คุณไม่ได้เป็นผู้สอนในรายวิชานี้');
        return $course;
    }
    public static function curriculumEditable(int $id): void {
        if(DB::one('SELECT id FROM enrollments WHERE course_id=? LIMIT 1',[$id])) abort(409,'รายวิชามีผู้เรียนแล้ว หากต้องการเปลี่ยนบทเรียนหรือข้อสอบ กรุณาสร้างรายวิชารุ่นใหม่เพื่อรักษาประวัติการเรียนและผลสอบ');
    }
    public static function enrolled(int $uid,int $cid): bool {return (bool)DB::one('SELECT id FROM enrollments WHERE user_id=? AND course_id=?',[$uid,$cid]);}
    public static function lessons(int $cid,int $uid=0): array {
        return DB::all('SELECT l.*,p.watched_seconds,p.completed_at FROM lessons l LEFT JOIN progress p ON p.lesson_id=l.id AND p.user_id=? WHERE l.course_id=? ORDER BY l.position,l.id',[$uid,$cid]);
    }
    public static function completion(int $uid,int $cid): array {
        $row=DB::one('SELECT COUNT(l.id) AS total,COUNT(p.completed_at) AS completed FROM lessons l LEFT JOIN progress p ON p.lesson_id=l.id AND p.user_id=? WHERE l.course_id=?',[$uid,$cid]);
        return ['total'=>(int)$row['total'],'completed'=>(int)$row['completed'],'percent'=>$row['total']? (int)floor(100*$row['completed']/$row['total']):0];
    }
    public static function lesson(int $id,bool $checkLock=true): array {
        $user=Auth::require();
        $lesson=DB::one('SELECT l.*,c.teacher_id,c.sequential,c.status AS course_status,c.title AS course_title FROM lessons l JOIN courses c ON c.id=l.course_id WHERE l.id=?',[$id]) ?? abort(404,'ไม่พบบทเรียน');
        $manager=$user['role']==='admin' || ($user['role']==='teacher' && (int)$lesson['teacher_id']===(int)$user['id']);
        if(!$manager) {
            if(!self::enrolled((int)$user['id'],(int)$lesson['course_id'])) abort(403,'กรุณาลงทะเบียนรายวิชาก่อนเข้าเรียน');
            if($checkLock && !self::unlocked($lesson,(int)$user['id'])) abort(403,'กรุณาเรียนกิจกรรมก่อนหน้าให้ผ่านก่อน');
        }
        return $lesson;
    }
    public static function unlocked(array $lesson,int $uid): bool {
        if(empty($lesson['sequential'])) return true;
        return !DB::one('SELECT l.id FROM lessons l LEFT JOIN progress p ON p.lesson_id=l.id AND p.user_id=? WHERE l.course_id=? AND l.position<? AND p.completed_at IS NULL LIMIT 1',[$uid,$lesson['course_id'],$lesson['position']]);
    }
    public static function complete(int $uid,int $lid): void {
        DB::run('INSERT INTO progress(user_id,lesson_id,completed_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE completed_at=COALESCE(completed_at,NOW())',[$uid,$lid]);
    }
    public static function enroll(int $uid,int $cid,?int $by=null): void {
        DB::transaction(function() use($uid,$cid,$by) {
            $course=DB::one('SELECT * FROM courses WHERE id=? FOR UPDATE',[$cid]) ?? abort(404,'ไม่พบรายวิชา');
            if($course['status']!=='published') abort(422,'รายวิชานี้ยังไม่เปิดรับลงทะเบียน');
            $student=DB::one("SELECT id,level FROM users WHERE id=? AND active=1 AND role='student'",[$uid]) ?? abort(422,'ไม่พบบัญชีผู้เรียนที่ใช้งานได้');
            if($course['level']!=='all' && $course['level']!==$student['level']) abort(422,'ระดับของผู้เรียนไม่ตรงตามเงื่อนไขรายวิชา');
            DB::run('INSERT IGNORE INTO enrollments(user_id,course_id,created_by) VALUES(?,?,?)',[$uid,$cid,$by]);
        });
    }
    public static function saveCourse(?int $id=null): int {
        $user=Auth::require(['admin','teacher']); if($id) self::manage($id);
        $title=input('title',720);$code=input('code',40);$description=input('description',30000);$category=input('category',300);
        $level=input('level',20);if(!in_array($level,['all','voc1','voc2','public'],true)) abort(422,'ระดับรายวิชาไม่ถูกต้อง');
        $hours=integer('hours',1,1000,3);$sequential=isset($_POST['sequential'])?1:0;
        $color=input('color',20);if(!in_array($color,['teal','orange','blue','purple','rose','olive'],true))$color='teal';
        return DB::transaction(function() use($id,$user,$title,$code,$description,$category,$level,$hours,$sequential,$color): int {
        $category=Category::selectedName($category);
        if($id) {
            $current=self::course($id);
            if(self::completion(0,$id)['total'] && DB::one('SELECT id FROM enrollments WHERE course_id=? LIMIT 1',[$id]) && ($current['sequential']!=$sequential || $current['level']!==$level)) abort(409,'มีผู้เรียนแล้ว ไม่สามารถเปลี่ยนลำดับการเรียนหรือระดับได้');
            DB::run('UPDATE courses SET title=?,code=?,description=?,category=?,level=?,hours=?,sequential=?,color=? WHERE id=?',[$title,$code,$description,$category,$level,$hours,$sequential,$color,$id]);
        } else {
            DB::run('INSERT INTO courses(teacher_id,title,code,description,category,level,hours,sequential,color) VALUES(?,?,?,?,?,?,?,?,?)',[$user['id'],$title,$code,$description,$category,$level,$hours,$sequential,$color]);$id=DB::id();
        }
        audit('course.saved',(string)$id); return $id;
        });
    }
    public static function publish(int $id,string $status): void {
        self::manage($id);
        if(!in_array($status,['draft','published','archived'],true)) abort(422,'สถานะไม่ถูกต้อง');
        DB::transaction(function()use($id,$status){
            DB::one('SELECT id FROM courses WHERE id=? FOR UPDATE',[$id]);
            if($status==='published') {
                $lessons=self::lessons($id); if(!$lessons)abort(422,'กรุณาเพิ่มบทเรียนก่อนเปิดรับสมัคร');
                foreach($lessons as $l)if($l['kind']==='quiz'&&!DB::one('SELECT id FROM questions WHERE lesson_id=? LIMIT 1',[$l['id']]))abort(422,'แบบทดสอบต้องมีอย่างน้อย 1 ข้อก่อนเปิดรายวิชา');
            }
            DB::run('UPDATE courses SET status=? WHERE id=?',[$status,$id]);
        });audit('course.'.$status,(string)$id);
    }
    public static function addLesson(int $cid): int {
        self::manage($cid);
        return DB::transaction(function()use($cid){
            DB::one('SELECT id FROM courses WHERE id=? FOR UPDATE',[$cid]);self::curriculumEditable($cid);
            $kind=input('kind',20);if(!in_array($kind,['article','video','assignment','quiz'],true))abort(422,'ประเภทบทเรียนไม่ถูกต้อง');
            $title=input('title',720);$bodyFormat=input('body_format',10,false)?:'text';
            $body=RichText::prepare(input('body',200000,false),$bodyFormat,$cid);
            $media=null;$external=null;$duration=0;$required=isset($_POST['require_watch'])?1:0;
            $attachment=Upload::save('attachment','document');
            if($kind==='video') {
                $media=Upload::save('video','video');$external=input('external_url',1000,false)?:null;
                if($media && $external)abort(422,'เลือกอัปโหลดวิดีโอหรือใส่ลิงก์อย่างใดอย่างหนึ่ง');
                if(!$media&&!$external)abort(422,'กรุณาอัปโหลด MP4 หรือใส่ลิงก์วิดีโอ');
                if($media) $duration=Upload::duration($media);
                else {
                    $host=strtolower((string)parse_url($external,PHP_URL_HOST));
                    if(!filter_var($external,FILTER_VALIDATE_URL)||parse_url($external,PHP_URL_SCHEME)!=='https'||parse_url($external,PHP_URL_USER))abort(422,'ลิงก์วิดีโอต้องเป็น HTTPS');
                    $supported=in_array($host,['youtube.com','www.youtube.com','youtu.be','m.youtube.com'],true)||preg_match('/\.mp4$/i',(string)parse_url($external,PHP_URL_PATH));
                    if($required&&!$supported)abort(422,'การบังคับดูจนจบรองรับ MP4 และ YouTube เท่านั้น');
                    $duration=integer('duration_seconds',$required?1:0,43200,0);
                }
            }
            $position=(int)DB::one('SELECT COALESCE(MAX(position),0)+1 AS n FROM lessons WHERE course_id=?',[$cid])['n'];
            DB::run('INSERT INTO lessons(course_id,title,kind,body,body_format,position,media_path,external_url,duration_seconds,require_watch,attachment_path,pass_score,time_limit,max_attempts) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$cid,$title,$kind,$body,$bodyFormat,$position,$media,$external,$duration,$kind==='video'?$required:0,$attachment,integer('pass_score',1,100,60),integer('time_limit',1,240,30),integer('max_attempts',1,20,3)]);
            $id=DB::id();if($bodyFormat==='html')EditorImage::attach($id,'body',[$body]);audit('lesson.created',(string)$id);return $id;
        });
    }
    public static function deleteLesson(int $id): void {
        $lesson=self::lesson($id,false);self::manage((int)$lesson['course_id']);
        DB::transaction(function()use($lesson,$id){
            DB::one('SELECT id FROM courses WHERE id=? FOR UPDATE',[$lesson['course_id']]);self::curriculumEditable((int)$lesson['course_id']);
            DB::run('DELETE FROM lessons WHERE id=?',[$id]);DB::run("UPDATE courses SET status='draft' WHERE id=?",[$lesson['course_id']]);
        });
    }
    public static function issue(int $uid,int $cid): string {
        return DB::transaction(function()use($uid,$cid){
            DB::one('SELECT id FROM courses WHERE id=? FOR UPDATE',[$cid]);
            if(!DB::one('SELECT id FROM enrollments WHERE user_id=? AND course_id=? FOR UPDATE',[$uid,$cid]))abort(403,'คุณยังไม่ได้ลงทะเบียนรายวิชานี้');
            $p=self::completion($uid,$cid); if(!$p['total']||$p['completed']!==$p['total'])abort(422,'ต้องเรียนครบทุกบทเรียนและผ่านทุกกิจกรรมก่อนรับใบประกาศนียบัตร');
            if($existing=DB::one('SELECT code FROM certificates WHERE user_id=? AND course_id=?',[$uid,$cid]))return $existing['code'];
            $course=self::course($cid);$user=DB::one('SELECT name FROM users WHERE id=?',[$uid]);$code=bin2hex(random_bytes(16));
            $design=CertificateDesign::issuance();
            [$rangeId,$serial,$registration]=CertificateNumbers::allocate();
            DB::run('INSERT INTO certificates(user_id,course_id,code,recipient_name,course_title,hours,institution,signatory,design_snapshot,number_range_id,serial_number,registration_number) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$uid,$cid,$code,$user['name'],$course['title'],$course['hours'],$design['institution'],$design['signatory'],$design['design_snapshot'],$rangeId,$serial,$registration]);
            audit('certificate.issued',$code);return $code;
        });
    }
}
