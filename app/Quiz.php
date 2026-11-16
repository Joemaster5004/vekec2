<?php
declare(strict_types=1);
namespace App;
final class Quiz {
    public static function add(int $lid,array $rows): int {
        $lesson=Learning::lesson($lid,false);Learning::manage((int)$lesson['course_id']);
        if($lesson['kind']!=='quiz')abort(422,'กิจกรรมนี้ไม่ใช่แบบทดสอบ');
        if(!$rows||count($rows)>500)abort(422,'นำเข้าได้ 1–500 ข้อต่อครั้ง');
        $validated=[];
        foreach($rows as $i=>$q) {
            if(!is_array($q)||!isset($q['prompt'],$q['options'],$q['answer'])||!is_string($q['prompt'])||!is_array($q['options'])||count($q['options'])!==4||!array_is_list($q['options']))abort(422,'รูปแบบข้อสอบไม่ถูกต้องที่ข้อ '.($i+1));
            $format=$q['content_format']??'text';$explanation=$q['explanation']??'';
            if(!is_string($format)||!is_string($explanation))abort(422,'รูปแบบเนื้อหาหรือคำอธิบายไม่ถูกต้อง');
            $q['prompt']=RichText::prepare($q['prompt'],$format,(int)$lesson['course_id'],true,50000);
            foreach($q['options'] as $key=>$opt){
                if(!is_string($opt))abort(422,'ต้องกรอกตัวเลือกให้ครบทั้ง 4 ตัวเลือก');
                $q['options'][$key]=RichText::prepare($opt,$format,(int)$lesson['course_id'],true,20000);
            }
            $q['explanation']=RichText::prepare($explanation,$format,(int)$lesson['course_id'],false,50000);
            $q['content_format']=$format;
            if(filter_var($q['answer'],FILTER_VALIDATE_INT)===false||(int)$q['answer']<0||(int)$q['answer']>3)abort(422,'เฉลยต้องเป็น 0, 1, 2 หรือ 3');
            $validated[]=$q;
        }
        return DB::transaction(function()use($lesson,$lid,$validated){
            DB::one('SELECT id FROM courses WHERE id=? FOR UPDATE',[$lesson['course_id']]);Learning::curriculumEditable((int)$lesson['course_id']);
            foreach($validated as $q){
                DB::run('INSERT INTO questions(lesson_id,prompt,options,answer,explanation,content_format) VALUES(?,?,?,?,?,?)',[$lid,$q['prompt'],json_encode($q['options'],JSON_UNESCAPED_UNICODE),(int)$q['answer'],$q['explanation'],$q['content_format']]);
                if($q['content_format']==='html'){
                    EditorImage::attach($lid,'question',array_merge([$q['prompt']],$q['options']));
                    EditorImage::attach($lid,'explanation',[$q['explanation']]);
                }
            }
            audit('questions.imported',(string)$lid);return count($validated);
        });
    }
    public static function import(int $lid): int {
        $f=$_FILES['questions']??null;if(!$f||is_array($f['error'])||$f['error']!==UPLOAD_ERR_OK||$f['size']>2*1024*1024)abort(422,'กรุณาเลือกไฟล์ JSON หรือ CSV ขนาดไม่เกิน 2 MB');
        $text=file_get_contents($f['tmp_name']);$text=preg_replace('/^\xEF\xBB\xBF/','',$text);
        if(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION))==='json'){
            try{$rows=json_decode($text,true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){abort(422,'ไฟล์ JSON ไม่ถูกต้อง');}
            if(!is_array($rows)||!array_is_list($rows))abort(422,'JSON ต้องเป็นรายการข้อสอบ');
        }else{
            $stream=fopen('php://temp','r+');fwrite($stream,$text);rewind($stream);$headers=fgetcsv($stream,0,',','"','');
            $baseHeaders=['prompt','option_a','option_b','option_c','option_d','answer'];
            if(!in_array($headers,[$baseHeaders,array_merge($baseHeaders,['explanation']),array_merge($baseHeaders,['explanation','content_format'])],true))abort(422,'หัวตาราง CSV ต้องตรงกับไฟล์ตัวอย่าง');
            $rows=[];while(($r=fgetcsv($stream,0,',','"',''))!==false){if($r===[null])continue;if(count($r)!==count($headers))abort(422,'จำนวนคอลัมน์ CSV ไม่ถูกต้อง');$rows[]=['prompt'=>$r[0],'options'=>array_slice($r,1,4),'answer'=>$r[5],'explanation'=>$r[6]??'','content_format'=>($r[7]??'')?:'text'];if(count($rows)>500)abort(422,'นำเข้าได้ไม่เกิน 500 ข้อ');}fclose($stream);
        }
        return self::add($lid,$rows);
    }
    public static function start(int $lid): int {
        $user=Auth::require(['student']);$lesson=Learning::lesson($lid);
        if($lesson['kind']!=='quiz')abort(422,'กิจกรรมนี้ไม่ใช่แบบทดสอบ');
        return DB::transaction(function()use($user,$lesson,$lid){
            DB::one('SELECT id FROM enrollments WHERE user_id=? AND course_id=? FOR UPDATE',[$user['id'],$lesson['course_id']]);
            if($open=DB::one('SELECT id FROM attempts WHERE user_id=? AND lesson_id=? AND submitted_at IS NULL',[$user['id'],$lid]))return (int)$open['id'];
            if(DB::one('SELECT lesson_id FROM progress WHERE user_id=? AND lesson_id=? AND completed_at IS NOT NULL',[$user['id'],$lid]))abort(409,'ผ่านแบบทดสอบนี้แล้ว สามารถทบทวนเฉลยได้');
            $count=(int)DB::one('SELECT COUNT(*) AS n FROM attempts WHERE user_id=? AND lesson_id=?',[$user['id'],$lid])['n'];
            if($count>=(int)$lesson['max_attempts'])abort(422,'ใช้สิทธิ์ทำแบบทดสอบครบแล้ว');
            if(!DB::one('SELECT id FROM questions WHERE lesson_id=?',[$lid]))abort(422,'แบบทดสอบยังไม่มีข้อสอบ');
            DB::run('INSERT INTO attempts(user_id,lesson_id) VALUES(?,?)',[$user['id'],$lid]);return DB::id();
        });
    }
    public static function reviewAllowed(int $lid,int $uid): bool {
        $state=DB::one('SELECT COUNT(*) AS attempts,COUNT(submitted_at) AS submitted FROM attempts WHERE lesson_id=? AND user_id=?',[$lid,$uid]);
        if(!$state['submitted']||(int)$state['attempts']!==(int)$state['submitted'])return false;
        if(DB::one('SELECT lesson_id FROM progress WHERE lesson_id=? AND user_id=? AND completed_at IS NOT NULL',[$lid,$uid]))return true;
        $lesson=DB::one('SELECT max_attempts FROM lessons WHERE id=?',[$lid]);
        return $lesson&&(int)$state['submitted']>=(int)$lesson['max_attempts'];
    }
    public static function submit(int $aid): array {
        $user=Auth::require(['student']);
        return DB::transaction(function()use($user,$aid){
            $attempt=DB::one('SELECT * FROM attempts WHERE id=? AND user_id=? FOR UPDATE',[$aid,$user['id']])??abort(404,'ไม่พบการทำแบบทดสอบ');
            if($attempt['submitted_at'])abort(409,'แบบทดสอบนี้ส่งแล้ว');
            $lesson=Learning::lesson((int)$attempt['lesson_id']);$questions=DB::all('SELECT * FROM questions WHERE lesson_id=?',[$lesson['id']]);
            $expired=time()>strtotime($attempt['started_at'])+(int)$lesson['time_limit']*60+5;
            $answers=$_POST['answers']??[];if(!is_array($answers))abort(422,'คำตอบไม่ถูกต้อง');$correct=0;$safe=[];
            foreach($questions as $q){$a=$answers[$q['id']]??null;if($a!==null&&(is_array($a)||!preg_match('/^[0-3]$/',(string)$a)))abort(422,'คำตอบไม่ถูกต้อง');$safe[$q['id']]=$a===null?null:(int)$a;if(!$expired&&$a!==null&&(int)$a===(int)$q['answer'])$correct++;}
            $score=$questions?round(100*$correct/count($questions),2):0;
            DB::run('UPDATE attempts SET score=?,answers=?,submitted_at=NOW() WHERE id=?',[$score,json_encode($safe),$aid]);
            if(!$expired&&$score>=(int)$lesson['pass_score'])Learning::complete((int)$user['id'],(int)$lesson['id']);
            return ['score'=>$score,'expired'=>$expired,'passed'=>!$expired&&$score>=(int)$lesson['pass_score'],'lesson_id'=>$lesson['id']];
        });
    }
}
