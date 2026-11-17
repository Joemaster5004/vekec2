<?php
declare(strict_types=1);
namespace App;

final class DepartmentVideo {
    public static function all(bool $public = false): array {
        try {
            return DB::all('SELECT v.*,c.name AS department FROM department_videos v JOIN categories c ON c.id=v.category_id'.($public?' WHERE v.active=1':'').' ORDER BY v.id DESC');
        } catch (\mysqli_sql_exception $error) {
            // An existing deployment may receive source before its database migration.
            // Keep the public catalog available only for missing-table errors.
            if ($error->getCode() !== 1146) throw $error;
            error_log('Department guidance schema unavailable; run bin/migrate.php (009_department_videos).');
            if ($public) return [];
            abort(503, 'ฐานข้อมูลวิดีโอแนะนำยังไม่พร้อม กรุณาให้ผู้ดูแลเซิร์ฟเวอร์รัน php bin/migrate.php เพื่ออัปเดตฐานข้อมูล');
        }
    }
    public static function find(int $id): array {
        $v=DB::one('SELECT v.*,c.name AS department FROM department_videos v JOIN categories c ON c.id=v.category_id WHERE v.id=?',[$id])??abort(404,'ไม่พบวิดีโอ');
        if(!$v['active']&&(Auth::user()['role']??'')!=='admin')abort(404,'ไม่พบวิดีโอ');
        return $v;
    }
    public static function youtube(string $url): ?string {
        $host=strtolower((string)parse_url($url,PHP_URL_HOST));
        if(!in_array($host,['youtube.com','www.youtube.com','m.youtube.com','youtu.be','www.youtube-nocookie.com'],true))return null;
        parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
        $id=$host==='youtu.be'?trim((string)parse_url($url,PHP_URL_PATH),'/'):($query['v']??basename((string)parse_url($url,PHP_URL_PATH)));
        return is_string($id)&&preg_match('/^[a-zA-Z0-9_-]{11}$/',$id)?$id:null;
    }
    public static function mode(array $v): string {
        return $v['media_path']||preg_match('/\.mp4$/i',(string)parse_url($v['external_url'],PHP_URL_PATH))?'mp4':(self::youtube($v['external_url'])?'youtube':'external');
    }
    private static function link(string $field, bool $required=false): string {
        $s=input($field,2000,$required);
        if($s!==''&&(!filter_var($s,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($s,PHP_URL_SCHEME)),['http','https'],true)||preg_match('/[\x00-\x20\x7f\\\\]/',$s)))abort(422,'ลิงก์ต้องเป็น http:// หรือ https:// ที่ถูกต้อง');
        return $s;
    }
    public static function save(?int $id=null): void {
        Auth::require(['admin']);
        $title=input('title',960);$description=input('description',12000,false);
        $category=integer('category_id',1,PHP_INT_MAX,0);
        $source=input('source',10);if(!in_array($source,['url','upload'],true))abort(422,'กรุณาเลือกประเภทวิดีโอ');
        $external=self::link('external_url',$source==='url');$application=self::link('application_url');
        $active=integer('active',0,1,0);$paths=[];$obsolete=null;
        try {
            $media=$source==='upload'?Upload::save('video','video'):null;if($media)$paths[]=$media;
            $duration=$media?Upload::duration($media):integer('duration_seconds',1,43200,1);
            $files=$_FILES['attachments']??null;$attachments=[];
            if($files){
                if(!is_array($files['name']??null)||count($files['name'])>10)abort(422,'แนบไฟล์ได้ไม่เกิน 10 ไฟล์ต่อครั้ง');
                foreach($files['name'] as $i=>$name){
                    if(!is_string($name)||strlen($name)>960||preg_match_all('/./us',$name)>240)abort(422,'ชื่อไฟล์ยาวเกินกำหนด');
                    $_FILES['department_attachment']=array_combine(['name','type','tmp_name','error','size'],array_map(fn($k)=>$files[$k][$i]??null,['name','type','tmp_name','error','size']));
                    $path=Upload::save('department_attachment','document');
                    if($path){$paths[]=$path;$attachments[]=[$path,basename($name)];}
                }
            }
            DB::transaction(function()use($id,$title,$description,$category,$external,$application,$source,$active,$media,$duration,$attachments,&$obsolete){
                $old=$id?DB::one('SELECT * FROM department_videos WHERE id=? FOR UPDATE',[$id]):null;
                if($id&&!$old)abort(404,'ไม่พบวิดีโอ');
                if(!DB::one('SELECT id FROM categories WHERE id=? LOCK IN SHARE MODE',[$category]))abort(422,'กรุณาเลือกแผนกวิชาในระบบ');
                $path=$source==='upload'?($media??$old['media_path']??null):null;
                if($source==='upload'&&!$path)abort(422,'กรุณาอัปโหลดไฟล์ MP4');
                $seconds=$source==='upload'&&!$media?(int)$old['duration_seconds']:$duration;
                $values=[$category,$title,$description,$source==='url'?$external:'',$path,$seconds,$application,$active];
                if($id){
                    // Every edit invalidates in-flight watch claims, but preserves issued certificates.
                    DB::run('UPDATE department_videos SET category_id=?,title=?,description=?,external_url=?,media_path=?,duration_seconds=?,application_url=?,active=?,revision=revision+1 WHERE id=?',[...$values,$id]);
                    if($old['media_path']!==$path)$obsolete=$old['media_path'];
                }else{DB::run('INSERT INTO department_videos(category_id,title,description,external_url,media_path,duration_seconds,application_url,active) VALUES(?,?,?,?,?,?,?,?)',$values);$id=DB::id();}
                foreach($attachments as [$p,$n])DB::run('INSERT INTO department_video_files(video_id,path,name) VALUES(?,?,?)',[$id,$p,$n]);
                audit('department_video.saved',(string)$id);
            });
        }catch(\Throwable $e){foreach($paths as $path)if(is_file(Upload::path($path)))unlink(Upload::path($path));throw $e;}
        if($obsolete&&is_file(Upload::path($obsolete)))unlink(Upload::path($obsolete));
    }
    public static function removeFile(int $id, int $file): void {
        Auth::require(['admin']);
        $p=DB::transaction(function()use($id,$file){
            $f=DB::one('SELECT * FROM department_video_files WHERE id=? AND video_id=? FOR UPDATE',[$file,$id])??abort(404,'ไม่พบไฟล์');
            DB::run('DELETE FROM department_video_files WHERE id=?',[$file]);audit('department_video.file_deleted',(string)$file);return $f['path'];
        });
        if(is_file(Upload::path($p)))unlink(Upload::path($p));
    }
    public static function watch(int $id, string $action): array {
        $v=self::find($id);if(!$v['active'])abort(409,'วิดีโอนี้ยังไม่เผยแพร่');
        $key=(string)$id;$s=$_SESSION['department_watches'][$key]??null;
        if($action==='start'){
            if(!$s||$s['revision']!==(int)$v['revision']||time()-$s['started']>86400){
                $s=['token'=>bin2hex(random_bytes(24)),'revision'=>(int)$v['revision'],'started'=>time(),'last'=>microtime(true),'watched'=>0.0,'done'=>false];
            }
            $s['last']=microtime(true);$_SESSION['department_watches'][$key]=$s;
            return ['token'=>$s['token'],'position'=>$s['watched'],'completed'=>$s['done']];
        }
        if(!$s||$s['revision']!==(int)$v['revision']||time()-$s['started']>86400||!hash_equals($s['token'],input('token',100)))abort(409,'กรุณาเปิดวิดีโอใหม่ก่อนรับเกียรติบัตร');
        if(self::mode($v)==='external'){
            if(input('confirmed',1)!=='1')abort(422,'กรุณายืนยันว่ารับชมแล้ว');
            if(time()-$s['started']<(int)$v['duration_seconds'])abort(422,'กรุณารับชมวิดีโอจนจบก่อน');
            $s['done']=true;
        }else{
            $position=filter_var($_POST['position']??null,FILTER_VALIDATE_FLOAT);
            if($position===false||!is_finite($position)||$position<0)abort(422,'ตำแหน่งวิดีโอไม่ถูกต้อง');
            $elapsed=max(0,min(15,microtime(true)-$s['last']));$s['last']=microtime(true);
            if($position>$s['watched']+$elapsed*1.1+0.5||$position>(int)$v['duration_seconds']+2){$_SESSION['department_watches'][$key]=$s;return ['position'=>$s['watched'],'completed'=>$s['done'],'reset'=>true];}
            $s['watched']=max($s['watched'],min($position,(int)$v['duration_seconds']));
            $s['done']=$s['done']||$s['watched']>=max((int)$v['duration_seconds']-1,0.98*(int)$v['duration_seconds']);
        }
        $_SESSION['department_watches'][$key]=$s;
        return ['position'=>$s['watched'],'completed'=>$s['done'],'reset'=>false];
    }
    public static function certificate(string $code): array {
        return DB::one('SELECT c.*,d.design_snapshot,d.registration_number,d.number_range_id,d.serial_number FROM department_video_certificates c LEFT JOIN guidance_certificate_details d ON d.certificate_id=c.id WHERE c.code=?',[$code])??abort(404,'ไม่พบเกียรติบัตร');
    }
    public static function issue(int $id): string {
        Auth::limit('department_certificate',30,3600);
        $name=input('name',540);$school=input('school',960);$grade=input('grade',400);$phone=input('phone',40);$email=strtolower(input('email',190));
        foreach([[$school,240],[$grade,100]] as [$text,$max])if(preg_match_all('/./us',$text)>$max)abort(422,'ข้อความยาวเกินกำหนด');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('/^\+?[0-9 ()-]{8,40}$/',$phone)||strlen(preg_replace('/\D/','',$phone))<8)abort(422,'กรุณาตรวจสอบอีเมลและเบอร์โทรศัพท์');
        if(input('consent',1)!=='1')abort(422,'กรุณายินยอมให้จัดเก็บข้อมูลเพื่อออกเกียรติบัตร');
        return DB::transaction(function()use($id,$name,$school,$grade,$phone,$email){
            $v=DB::one('SELECT v.*,c.name AS department FROM department_videos v JOIN categories c ON c.id=v.category_id WHERE v.id=? LOCK IN SHARE MODE',[$id])??abort(404,'ไม่พบวิดีโอ');
            $s=$_SESSION['department_watches'][(string)$id]??null;
            if(!$v['active']||!$s||!$s['done']||$s['revision']!==(int)$v['revision']||time()-$s['started']>86400)abort(403,'กรุณารับชมวิดีโอจนจบก่อนรับเกียรติบัตร');
            $old=DB::one('SELECT code FROM department_video_certificates WHERE watch_code=?',[$s['token']]);if($old)return $old['code'];
            [$rangeId,$serial,$registration]=CertificateNumbers::allocate(true);
            $design=CertificateDesign::issuance(true);
            $code=bin2hex(random_bytes(16));
            DB::run('INSERT INTO department_video_certificates(video_id,watch_code,code,recipient_name,school,grade,phone,email,video_title,department_name,institution,signatory) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$s['token'],$code,$name,$school,$grade,$phone,$email,$v['title'],$v['department'],$design['institution'],$design['signatory']]);
            $certificateId=DB::id();
            DB::run('INSERT INTO guidance_certificate_details(certificate_id,design_snapshot,registration_number,number_range_id,serial_number) VALUES(?,?,?,?,?)',[$certificateId,$design['design_snapshot'],$registration,$rangeId,$serial]);
            audit('department_certificate.issued',$code);return $code;
        });
    }
}
