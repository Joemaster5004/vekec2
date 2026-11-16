<?php
declare(strict_types=1);
namespace App;
final class AiApi {
    private static string $requestId='';
    private static function respond(mixed $data,int $status=200): never {
        http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
        echo AiInput::json(['data'=>$data,'request_id'=>self::$requestId]);exit;
    }
    private static function recent(): void {
        if((int)($_SESSION['authenticated_at']??0)<time()-900)AiInput::fail('REAUTH_REQUIRED','กรุณายืนยันตัวตนอีกครั้งก่อนเปลี่ยนการเชื่อมต่อหรือค่าใช้จ่าย',403);
    }
    private static function canonical(mixed $v): mixed {
        if(!is_array($v))return $v;if(!array_is_list($v))ksort($v);foreach($v as &$child)$child=self::canonical($child);return $v;
    }
    private static function idempotent(array $u,string $path,array $body,callable $fn,int $status=200): never {
        $key=$_SERVER['HTTP_IDEMPOTENCY_KEY']??'';if(!is_string($key)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/',$key))AiInput::fail('IDEMPOTENCY_REQUIRED','กรุณาระบุ Idempotency-Key');
        $route=hash('sha256',($_SERVER['REQUEST_METHOD']??'').'|'.$path);
        $hash=AiCredentials::fingerprint(AiInput::json(self::canonical($body)));
        $result=DB::transaction(function()use($u,$route,$key,$hash,$fn,$status){
            DB::run('DELETE FROM ai_idempotency WHERE actor_id=? AND route_hash=? AND idem_key=? AND created_at<DATE_SUB(NOW(),INTERVAL 24 HOUR)',[$u['id'],$route,$key]);
            DB::run('INSERT IGNORE INTO ai_idempotency(actor_id,route_hash,idem_key,body_hash) VALUES(?,?,?,?)',[$u['id'],$route,$key,$hash]);
            $r=DB::one('SELECT * FROM ai_idempotency WHERE actor_id=? AND route_hash=? AND idem_key=? FOR UPDATE',[$u['id'],$route,$key]);
            if(!hash_equals($r['body_hash'],$hash))AiInput::fail('IDEMPOTENCY_CONFLICT','รหัสคำขอซ้ำแต่ข้อมูลต่างกัน',409);
            if($r['response_json']!==null)return [json_decode($r['response_json'],true),(int)$r['response_status']];
            try {$value=$fn();}
            catch(AiError $e) {
                // Persist authoritative credential invalidation, but let a failed request be retried.
                DB::run('DELETE FROM ai_idempotency WHERE actor_id=? AND route_hash=? AND idem_key=?',[$u['id'],$route,$key]);return [null,0,$e];
            }
            DB::run('UPDATE ai_idempotency SET response_json=?,response_status=? WHERE actor_id=? AND route_hash=? AND idem_key=?',[AiInput::json($value),$status,$u['id'],$route,$key]);return [$value,$status];
        });if(isset($result[2]))throw $result[2];self::respond($result[0],$result[1]);
    }
    public static function dispatch(string $path,string $method): never {
        self::$requestId=bin2hex(random_bytes(12));
        try {
            $u=Auth::require();
            AiInput::teacher();
            $adminProviderPath=str_starts_with($path,'/api/v1/admin/ai-providers');
            if($adminProviderPath)Auth::require(['admin']);
            if(!$adminProviderPath&&!($path==='/api/v1/me/ai-reauth'&&$u['role']==='admin')&&env('AI_ENABLED')!=='true')AiInput::fail('AI_DISABLED','เซิร์ฟเวอร์ยังปิดระบบ AI แม้เปิดผู้ให้บริการแล้ว กรุณาให้ผู้ดูแลตั้ง AI_ENABLED=true และโหลด environment ใหม่',503);
            $body=[];
            if(!in_array($method,['GET','POST','PUT'],true))AiInput::fail('METHOD_NOT_ALLOWED','ไม่รองรับวิธีเรียกนี้',405);
            if($method!=='GET') {
                if(env('APP_ENV')==='production'&&!str_starts_with(env('APP_URL'),'https://'))AiInput::fail('HTTPS_REQUIRED','ต้องใช้ HTTPS ในระบบจริง',503);
                $origin=$_SERVER['HTTP_ORIGIN']??'';
                if($origin!==rtrim(env('APP_URL'),'/'))AiInput::fail('ORIGIN_DENIED','แหล่งที่มาของคำขอไม่ถูกต้อง',403);
                $token=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(!is_string($token)||!hash_equals(csrf(),$token))AiInput::fail('CSRF_FAILED','เซสชันหมดอายุ กรุณาโหลดหน้าใหม่',403);
                if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')AiInput::fail('JSON_REQUIRED','ต้องส่ง application/json',415);
                $limit=preg_match('#^/api/v1/quizzes/[1-9][0-9]*/ai-questions$#',$path)?262144:32768;
                $raw=file_get_contents('php://input',false,null,0,$limit+1);if(strlen($raw)>$limit)AiInput::fail('PAYLOAD_TOO_LARGE','ข้อมูลใหญ่เกินกำหนด',413);
                try {$decoded=json_decode($raw,false,32,JSON_THROW_ON_ERROR);if(!$decoded instanceof \stdClass)throw new \RuntimeException();$body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){AiInput::fail('INVALID_JSON','รูปแบบ JSON ไม่ถูกต้อง');}
            }
            AiInput::teacher();
            if($path==='/api/v1/me/ai-reauth'&&$method==='POST') {
                AiInput::only($body,['password']);Auth::limit('ai-reauth:'.$u['id'],10,900);$password=AiInput::text($body,'password',72);
                $row=DB::one('SELECT password_hash FROM users WHERE id=? AND active=1',[$u['id']]);
                if(!$row['password_hash'])AiInput::fail('LOGIN_REQUIRED','บัญชี ThaID กรุณาออกแล้วเข้าสู่ระบบใหม่เพื่อยืนยันตัวตน',403);
                if(!password_verify($password,$row['password_hash']))AiInput::fail('REAUTH_FAILED','ยืนยันตัวตนไม่สำเร็จ',403);
                $_SESSION['authenticated_at']=time();self::respond(['authenticated'=>true]);
            }
            if(preg_match('#^/api/v1/courses/([1-9][0-9]*)/ai/drafts$#',$path,$m)) {
                $cid=(int)$m[1];Learning::manage($cid);Learning::curriculumEditable($cid);
                if($method==='GET')self::respond(AiDraft::config($cid));
                if($method==='POST') {
                    Auth::limit('ai-generation:'.$u['id'],30,3600);
                    self::respond(AiDraft::generate($cid,(int)$u['id'],$body,$_SERVER['HTTP_IDEMPOTENCY_KEY']??''));
                }
            }
            if(preg_match('#^/api/v1/quizzes/([1-9][0-9]*)/ai-questions$#',$path,$m)&&$method==='POST') {
                $lesson=Learning::lesson((int)$m[1],false);Learning::manage((int)$lesson['course_id']);Learning::curriculumEditable((int)$lesson['course_id']);
                AiInput::only($body,['questions']);
                if(!is_array($body['questions']??null)||!array_is_list($body['questions'])||count($body['questions'])<1||count($body['questions'])>10)AiInput::fail('INVALID_INPUT','เลือกข้อสอบ 1–10 ข้อ');
                foreach($body['questions'] as $q)if(!is_array($q)||($q['content_format']??'text')!=='text')AiInput::fail('INVALID_INPUT','ข้อสอบ AI ต้องเป็นข้อความธรรมดา');
                self::idempotent($u,$path,$body,fn()=>['added'=>Quiz::add((int)$m[1],$body['questions'])]);
            }
            if($method!=='GET')self::recent();
            if($path==='/api/v1/admin/ai-providers/pricing') {
                if($method==='GET')self::respond(AiPricing::catalog());
                if($method==='PUT')self::respond(AiPricing::save($body));
            }
            if($path==='/api/v1/admin/ai-providers') {
                if($method==='GET')self::respond(AiProvider::catalog(true));
                if($method==='POST')self::respond(AiProvider::save(null,$body),201);
            }
            if(preg_match('#^/api/v1/admin/ai-providers/([a-z][a-z0-9_-]{1,29})$#',$path,$m)&&$method==='PUT')self::respond(AiProvider::save($m[1],$body));
            if($path==='/api/v1/me/ai-capabilities'&&$method==='GET')self::respond([
                'capabilities'=>['ai.credentials.manage_self','ai.billing.configure_course'],
                'providers'=>AiCredentials::providers(),'encryption_ready'=>AiCredentials::ready(),
                'provider_catalog'=>AiProvider::catalog(),
                'institution_available'=>AiProvider::institutionReady()&&($u['role']==='admin'||env('AI_INSTITUTION_TEACHER_ACCESS')==='true'),
                'institution_provider'=>env('AI_INSTITUTION_PROVIDER','openai'),
                'models'=>array_combine(AiBilling::CAPABILITIES,array_map(AiProvider::models(...),AiBilling::CAPABILITIES)),
                'reauth_required'=>(int)($_SESSION['authenticated_at']??0)<time()-900
            ]);
            if($path==='/api/v1/me/ai-credentials') {
                if($method==='GET')self::respond(AiCredentials::list((int)$u['id']));
                if($method==='POST') {Auth::limit('ai-credential-create:'.$u['id'],30,3600);self::idempotent($u,$path,$body,fn()=>AiCredentials::create((int)$u['id'],$body),201);}
            }
            if(preg_match('#^/api/v1/me/ai-credentials/([1-9][0-9]*)/(verify|secret|revoke)$#',$path,$m)) {
                AiCredentials::owned((int)$u['id'],$m[1]);
                if($m[2]==='verify'&&$method==='POST') {Auth::limit('ai-verify:'.$u['id'],20,900);self::idempotent($u,$path,$body,fn()=>AiCredentials::verify((int)$u['id'],$m[1],$body));}
                if($m[2]==='secret'&&$method==='PUT')self::respond(AiCredentials::rotate((int)$u['id'],$m[1],$body));
                if($m[2]==='revoke'&&$method==='POST') {AiInput::only($body,[]);self::idempotent($u,$path,$body,fn()=>AiCredentials::revoke((int)$u['id'],$m[1]));}
            }
            if(preg_match('#^/api/v1/courses/([1-9][0-9]*)/ai/(billing|usage)$#',$path,$m)) {
                $cid=(int)$m[1];Learning::manage($cid);
                if($m[2]==='billing'&&$method==='GET')self::respond(AiBilling::get($cid));
                if($m[2]==='billing'&&$method==='PUT')self::respond(AiBilling::save($u,$cid,$body));
                if($m[2]==='usage'&&$method==='GET')self::respond(AiBilling::usage($cid));
            }
            AiInput::fail('NOT_FOUND','ไม่พบ API',404);
        } catch(\Throwable $e) {
            $status=($e instanceof AiError||$e instanceof HttpError)?($e->getCode()?:400):500;
            $code=$e instanceof AiError?$e->reason:($status===500?'INTERNAL_ERROR':'REQUEST_DENIED');
            if($status===500)error_log('AI API '.get_class($e).' at '.$e->getFile().':'.$e->getLine());
            http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
            echo AiInput::json(['error'=>['code'=>$code,'message'=>$status===500?'ระบบขัดข้องชั่วคราว':$e->getMessage(),'request_id'=>self::$requestId]]);exit;
        }
    }
}
