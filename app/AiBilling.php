<?php
declare(strict_types=1);
namespace App;
/** Internal budget/credential gateway. Monetary amounts are integer micro-USD. No public reserve/secret endpoint. */
final class AiBilling {
    public const CAPABILITIES=AiProvider::CAPABILITIES;
    public static function get(int $cid): array {
        $r=DB::one('SELECT * FROM ai_billing WHERE course_id=?',[$cid]);
        return $r?['revision'=>(int)$r['revision'],'bindings'=>array_map(static function($b){unset($b['allow_student_usage'],$b['student_daily_request_limit']);return $b;},json_decode($r['bindings'],true)),'accepted_at'=>AiInput::iso($r['accepted_at'])]:['revision'=>0,'bindings'=>[],'accepted_at'=>null];
    }
    public static function save(array $user,int $cid,array $a): array {
        Learning::manage($cid);AiInput::only($a,['expected_revision','bindings','confirmed_charge_acceptance']);
        $revision=AiInput::number($a,'expected_revision',0,PHP_INT_MAX);
        if(($a['confirmed_charge_acceptance']??null)!==true)AiInput::fail('BILLING_CONSENT_REQUIRED','กรุณายืนยันแหล่งจ่ายและค่าใช้จ่าย');
        $rows=$a['bindings']??null;if(!is_array($rows)||!array_is_list($rows)||count($rows)>6)AiInput::fail('INVALID_INPUT','การตั้งค่ารายวิชาไม่ถูกต้อง');
        return DB::transaction(function()use($cid,$user,$rows,$revision){
            $course=DB::one('SELECT teacher_id FROM courses WHERE id=? FOR UPDATE',[$cid]);
            $current=self::get($cid);if($current['revision']!==$revision)AiInput::fail('REVISION_CONFLICT','การตั้งค่าเปลี่ยนแล้ว กรุณาโหลดใหม่',409);
            $seen=[];$bindings=[];
            foreach($rows as $b) {
                if(!is_array($b))AiInput::fail('INVALID_INPUT','การตั้งค่าไม่ถูกต้อง');
                AiInput::only($b,['capability','payer','credential_id','model','monthly_budget','currency']);
                $cap=AiInput::choice($b,'capability',self::CAPABILITIES);if(isset($seen[$cap]))AiInput::fail('INVALID_INPUT','ฟังก์ชันซ้ำ');$seen[$cap]=true;
                $payer=AiInput::choice($b,'payer',['teacher','institution']);
                if($cap==='video')AiInput::fail('CAPABILITY_NOT_AVAILABLE','วิดีโอต้องผูก text และ speech แยกกันก่อน ตัวสร้างวิดีโอยังไม่ติดตั้ง');
                $budget=$b['monthly_budget']??null;
                if((!is_int($budget)&&!is_float($budget))||!is_finite((float)$budget)||$budget<0.000001||$budget>10000)AiInput::fail('INVALID_INPUT','งบรายเดือนต้องอยู่ระหว่าง 0.000001 ถึง 10,000 USD');
                if(($b['currency']??null)!=='USD')AiInput::fail('INVALID_INPUT','ขณะนี้รองรับงบประมาณ USD เท่านั้น');
                $credential=null;
                if($payer==='teacher') {
                    if((int)$course['teacher_id']!==(int)$user['id'])AiInput::fail('FORBIDDEN','เฉพาะครูเจ้าของรายวิชาผูก key ส่วนตัวได้',403);
                    $credential=AiInput::text($b,'credential_id',30);$r=AiCredentials::owned((int)$user['id'],$credential,true);
                    if($r['status']!=='verified')AiInput::fail('CREDENTIAL_INVALID','กรุณาทดสอบ API key ให้ผ่านก่อนผูกกับรายวิชา',409);
                    $provider=AiProvider::find($r['provider']);if((int)$r['provider_revision']!==$provider['revision'])AiInput::fail('PROVIDER_CONFIG_CHANGED','กรุณาทดสอบการเชื่อมต่อกับผู้ให้บริการฉบับใหม่ก่อน',409);
                } else {
                    if($user['role']!=='admin'&&env('AI_INSTITUTION_TEACHER_ACCESS')!=='true')AiInput::fail('FORBIDDEN','ผู้ดูแลยังไม่อนุญาตให้ครูใช้งบส่วนกลาง',403);
                    if(isset($b['credential_id']))AiInput::fail('INVALID_INPUT','ส่วนกลางไม่รับ credential_id');$central=AiProvider::institution();$provider=AiProvider::find($central['provider']);
                }
                $model=AiInput::choice($b,'model',AiProvider::models($cap,$provider['slug']));
                $binding=['provider'=>$provider['slug'],'provider_revision'=>$provider['revision'],'capability'=>$cap,'payer'=>$payer,'model'=>$model,'monthly_budget'=>round((float)$budget,6),'currency'=>'USD','billing_owner_id'=>(int)$course['teacher_id']];
                if($credential!==null)$binding['credential_id']=$credential;
                $bindings[]=$binding;
            }
            DB::run('INSERT INTO ai_billing(course_id,revision,bindings,accepted_by,accepted_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE revision=VALUES(revision),bindings=VALUES(bindings),accepted_by=VALUES(accepted_by),accepted_at=NOW()',[$cid,$revision+1,AiInput::json($bindings),$user['id']]);
            audit('ai.billing.saved',(string)$cid);return self::get($cid);
        });
    }
    private static function binding(int $cid,string $cap): array {
        $config=self::get($cid);foreach($config['bindings'] as $b)if($b['capability']===$cap) {
            if(!isset($b['provider'],$b['provider_revision']))AiInput::fail('BILLING_CHANGED','กรุณาบันทึกแหล่งจ่ายรายวิชาใหม่',409);
            $provider=AiProvider::find($b['provider']);if($provider['revision']!==(int)$b['provider_revision'])AiInput::fail('PROVIDER_CONFIG_CHANGED','ผู้ให้บริการเปลี่ยนแล้ว กรุณาตรวจและบันทึกแหล่งจ่ายใหม่',409);
            if(!in_array($b['model'],AiProvider::models($cap,$b['provider']),true))AiInput::fail('MODEL_NOT_ALLOWED','โมเดลไม่ได้รับอนุญาตแล้ว กรุณาตั้งค่าใหม่',409);
            return [$config['revision'],$b];
        }
        AiInput::fail('BILLING_NOT_CONFIGURED','ยังไม่ได้ตั้งค่าแหล่งจ่ายสำหรับฟังก์ชันนี้',409);
    }
    private static function entitled(int $cid,int $actorId,array $b): array {
        $course=DB::one('SELECT teacher_id FROM courses WHERE id=?',[$cid])??AiInput::fail('NOT_FOUND','ไม่พบรายวิชา',404);
        $actor=DB::one('SELECT id,role FROM users WHERE id=? AND active=1',[$actorId])??AiInput::fail('FORBIDDEN','บัญชีถูกระงับ',403);
        if((int)$b['billing_owner_id']!==(int)$course['teacher_id'])AiInput::fail('BILLING_OWNER_CHANGED','ครูเจ้าของรายวิชาเปลี่ยนแล้ว กรุณาตั้งค่าแหล่งจ่ายใหม่',409);
        if(!DB::one("SELECT id FROM users WHERE id=? AND active=1 AND role IN ('teacher','admin')",[$course['teacher_id']]))AiInput::fail('BILLING_OWNER_INACTIVE','ครูเจ้าของแหล่งจ่ายถูกระงับ',403);
        if(!in_array($actor['role'],['teacher','admin'],true))AiInput::fail('TEACHER_ONLY','AI เปิดให้ครูผู้สอนเท่านั้น',403);
        if($actor['role']!=='admin'&&(int)$course['teacher_id']!==$actorId)AiInput::fail('FORBIDDEN','ไม่มีสิทธิ์ในรายวิชานี้',403);
        return $actor;
    }
    /** Called by future job services, with a server-computed upper bound, never a browser estimate. */
    public static function reserve(int $cid,int $actorId,string $cap,int $maxMicros,string $operationKey): array {
        if(env('AI_ENABLED')!=='true')AiInput::fail('AI_DISABLED','การเชื่อมต่อ AI ถูกปิด',503);
        $actor=DB::one('SELECT role FROM users WHERE id=? AND active=1',[$actorId]);if(!$actor||!in_array($actor['role'],['teacher','admin'],true))AiInput::fail('TEACHER_ONLY','AI เปิดให้ครูผู้สอนเท่านั้น',403);
        if($maxMicros<1||$maxMicros>10000000000||!preg_match('/^[A-Za-z0-9_-]{1,128}$/',$operationKey))AiInput::fail('INVALID_INPUT','ข้อมูลการจองงบไม่ถูกต้อง');
        return DB::transaction(function()use($cid,$actorId,$cap,$maxMicros,$operationKey){
            DB::one('SELECT id FROM courses WHERE id=? FOR UPDATE',[$cid]);[$revision,$b]=self::binding($cid,$cap);$actor=self::entitled($cid,$actorId,$b);
            $version=null;$credential=$b['credential_id']??null;
            if($b['payer']==='teacher'){$r=AiCredentials::owned((int)$b['billing_owner_id'],(string)$credential,true);$version=(int)$r['version'];AiCredentials::secret((int)$b['billing_owner_id'],(string)$credential,$version);}
            else {if($actor['role']!=='admin'&&env('AI_INSTITUTION_TEACHER_ACCESS')!=='true')AiInput::fail('FORBIDDEN','งบส่วนกลางถูกปิด',403);AiProvider::institution();}
            if($old=DB::one('SELECT * FROM ai_usage_reservations WHERE course_id=? AND operation_key=?',[$cid,$operationKey])) {
                if((int)$old['actor_id']!==$actorId||$old['capability']!==$cap||(int)$old['reserved_micros']!==$maxMicros)AiInput::fail('IDEMPOTENCY_CONFLICT','รหัสงานซ้ำแต่ข้อมูลต่างกัน',409);
                return $old;
            }
            $period=date('Y-m');$day=date('Y-m-d');$spent=(int)DB::one("SELECT COALESCE(SUM(COALESCE(actual_micros,reserved_micros)),0) AS total FROM ai_usage_reservations WHERE course_id=? AND capability=? AND period=? AND status<>'released'",[$cid,$cap,$period])['total'];
            if($spent+$maxMicros>(int)round($b['monthly_budget']*1000000))AiInput::fail('BUDGET_EXCEEDED','วงเงินรายเดือนของฟังก์ชันนี้ไม่เพียงพอ',429);
            $provider=$b['payer']==='teacher'?$r['provider']:AiProvider::institution()['provider'];$id=bin2hex(random_bytes(16));
            DB::run('INSERT INTO ai_usage_reservations(id,operation_key,course_id,actor_id,billing_owner_id,capability,binding_revision,credential_id,credential_version,payer,provider,model,period,day_key,reserved_micros,provider_revision) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$operationKey,$cid,$actorId,$b['billing_owner_id'],$cap,$revision,$credential,$version,$b['payer'],$provider,$b['model'],$period,$day,$maxMicros,$b['provider_revision']]);
            return DB::one('SELECT * FROM ai_usage_reservations WHERE id=?',[$id]);
        });
    }
    /** Resolve just before a provider call; no endpoint serializes its return value. */
    public static function begin(string $id): array {
        if(env('AI_ENABLED')!=='true')AiInput::fail('AI_DISABLED','การเชื่อมต่อ AI ถูกปิด',503);
        return DB::transaction(function()use($id){
            $r=DB::one('SELECT * FROM ai_usage_reservations WHERE id=? FOR UPDATE',[$id])??AiInput::fail('NOT_FOUND','ไม่พบการจองงบ',404);
            $actor=DB::one('SELECT role FROM users WHERE id=? AND active=1',[$r['actor_id']]);if(!$actor||!in_array($actor['role'],['teacher','admin'],true))AiInput::fail('TEACHER_ONLY','AI เปิดให้ครูผู้สอนเท่านั้น',403);
            if($r['status']!=='reserved')AiInput::fail('OPERATION_ALREADY_STARTED','งานเริ่มแล้ว ห้ามเรียก provider ซ้ำ',409);
            [$revision,$b]=self::binding((int)$r['course_id'],$r['capability']);self::entitled((int)$r['course_id'],(int)$r['actor_id'],$b);
            if($revision!==(int)$r['binding_revision'])AiInput::fail('BILLING_CHANGED','การตั้งค่าเปลี่ยนแล้ว กรุณายืนยันงานใหม่',409);
            if($r['payer']==='teacher')AiCredentials::owned((int)$r['billing_owner_id'],(string)$r['credential_id'],true);
            if($r['payer']==='institution'&&env('AI_INSTITUTION_TEACHER_ACCESS')!=='true') {
                $actor=DB::one('SELECT role FROM users WHERE id=?',[$r['actor_id']]);
                if($actor['role']!=='admin')AiInput::fail('FORBIDDEN','งบส่วนกลางถูกปิดสำหรับครู',403);
            }
            $connection=$r['payer']==='teacher'?AiCredentials::secret((int)$r['billing_owner_id'],(string)$r['credential_id'],(int)$r['credential_version']):AiProvider::institution();
            if($connection['provider']!==$r['provider']||$connection['provider_revision']!==(int)$r['provider_revision'])AiInput::fail('PROVIDER_CONFIG_CHANGED','ปลายทางผู้ให้บริการเปลี่ยนแล้ว กรุณายืนยันงานใหม่',409);
            DB::run("UPDATE ai_usage_reservations SET status='started' WHERE id=?",[$id]);
            return [...$connection,'model'=>$r['model'],'max_micros'=>(int)$r['reserved_micros']];
        });
    }
    public static function settle(string $id,int $actualMicros): void {
        if($actualMicros<0)AiInput::fail('INVALID_INPUT','ยอดใช้ไม่ถูกต้อง');
        DB::transaction(function()use($id,$actualMicros){
            $r=DB::one('SELECT * FROM ai_usage_reservations WHERE id=? FOR UPDATE',[$id])??AiInput::fail('NOT_FOUND','ไม่พบการจองงบ',404);
            if($r['status']==='settled'&&(int)$r['actual_micros']===$actualMicros)return;
            if($r['status']!=='started')AiInput::fail('OPERATION_STATE_CONFLICT','สถานะงานไม่รองรับการบันทึกยอด',409);
            DB::run("UPDATE ai_usage_reservations SET status='settled',actual_micros=? WHERE id=?",[$actualMicros,$id]);
        });
    }
    public static function release(string $id): void { DB::run("UPDATE ai_usage_reservations SET status='released',actual_micros=0 WHERE id=? AND status='reserved'",[$id]); }
    public static function usage(int $cid): array {
        return DB::all("SELECT capability,payer,provider,model,period,COUNT(*) AS requests,SUM(reserved_micros)/1000000 AS reserved_usd,SUM(COALESCE(actual_micros,reserved_micros))/1000000 AS accounted_usd FROM ai_usage_reservations WHERE course_id=? AND status<>'released' GROUP BY capability,payer,provider,model,period ORDER BY period DESC",[$cid]);
    }
}
