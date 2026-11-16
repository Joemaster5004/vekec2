<?php
declare(strict_types=1);
namespace App;
/** Admin-managed catalog. Teachers cannot override endpoints or authentication headers. */
final class AiProvider {
    public const CAPABILITIES=['text','embedding','speech','analytics','assistant'];
    public const ADAPTERS=['openai_compatible','anthropic','gemini'];
    public static function mockEnabled(): bool { return env('APP_ENV')==='test'&&env('AI_TEST_PROVIDER')==='true'; }
    private static function mock(): array { return ['slug'=>'mock','name'=>'จำลอง (เฉพาะระบบทดสอบ)','adapter'=>'mock','base_url'=>'https://mock.example.invalid/v1','models'=>array_fill_keys(self::CAPABILITIES,['mock-model']),'enabled'=>true,'revision'=>1]; }
    private static function hydrate(array $r): array { $r['models']=is_string($r['models'])?json_decode($r['models'],true):$r['models'];$r['enabled']=(bool)$r['enabled'];$r['revision']=(int)$r['revision'];return $r; }
    public static function catalog(bool $includeDisabled=false): array {
        $rows=array_map(self::hydrate(...),DB::all('SELECT slug,name,adapter,base_url,models,enabled,revision FROM ai_providers'.($includeDisabled?'':' WHERE enabled=1').' ORDER BY name,slug'));
        if(self::mockEnabled())$rows[]=self::mock();return $rows;
    }
    public static function find(string $slug,bool $requireEnabled=true): array {
        if($slug==='mock'&&self::mockEnabled())return self::mock();
        $r=DB::one('SELECT slug,name,adapter,base_url,models,enabled,revision FROM ai_providers WHERE slug=?',[$slug]);
        if(!$r)AiInput::fail('PROVIDER_NOT_ALLOWED','ไม่พบผู้ให้บริการที่ได้รับอนุญาต',422);
        $r=self::hydrate($r);if($requireEnabled&&!$r['enabled'])AiInput::fail('PROVIDER_DISABLED','ผู้ดูแลปิดใช้งานผู้ให้บริการนี้แล้ว',409);return $r;
    }
    public static function models(string $capability,?string $provider=null): array {
        if($provider!==null)return self::find($provider)['models'][$capability]??[];
        $all=[];foreach(self::catalog() as $p)$all=array_merge($all,$p['models'][$capability]??[]);return array_values(array_unique($all));
    }
    public static function validateUrl(string $url): string {
        if(strlen($url)>1000||preg_match('/[\x00-\x20\x7f\\\\]/',$url)||!filter_var($url,FILTER_VALIDATE_URL))AiInput::fail('INVALID_PROVIDER_URL','กรุณาระบุ Base URL แบบ HTTPS ที่ถูกต้อง');
        $p=parse_url($url);$host=strtolower($p['host']??'');
        if(($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['query'])||isset($p['fragment'])||($p['port']??443)!==443||!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/D',$host)||preg_match('/\.(local|localhost|internal|test|invalid)$/D',$host))AiInput::fail('INVALID_PROVIDER_URL','ต้องเป็น HTTPS ของเว็บไซต์สาธารณะ ไม่ใช้ IP พอร์ตพิเศษ หรือข้อมูลเข้าสู่ระบบ');
        if(!preg_match('#^/[a-zA-Z0-9/_-]*$#D',$p['path']??'/'))AiInput::fail('INVALID_PROVIDER_URL','path ของ Base URL ไม่ถูกต้อง');
        return 'https://'.$host.rtrim($p['path']??'','/');
    }
    public static function publicAddress(array $addresses): string {
        if(!$addresses)AiInput::fail('PROVIDER_UNAVAILABLE','ไม่พบ DNS ของผู้ให้บริการ',503);
        foreach($addresses as $ip)if(!is_string($ip)||!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_GLOBAL_RANGE))AiInput::fail('PROVIDER_ADDRESS_DENIED','ปลายทางผู้ให้บริการต้องไม่เป็นเครือข่ายภายใน');
        return $addresses[0];
    }
    public static function save(?string $slug,array $a): array {
        Auth::require(['admin']);AiInput::only($a,['slug','name','adapter','base_url','models','enabled','expected_revision']);
        $create=$slug===null;$value=AiInput::text($a,'slug',30);
        if(!preg_match('/^[a-z][a-z0-9_-]{1,29}$/D',$value)||$value==='mock'||(!$create&&$value!==$slug))AiInput::fail('INVALID_PROVIDER_SLUG','รหัสผู้ให้บริการต้องเป็นตัวอักษรอังกฤษเล็ก ตัวเลข _ หรือ - และแก้รหัสเดิมไม่ได้');
        $name=AiInput::text($a,'name',400);if(preg_match_all('/./us',$name)>100)AiInput::fail('INVALID_INPUT','ชื่อผู้ให้บริการยาวเกิน 100 ตัวอักษร');
        $adapter=AiInput::choice($a,'adapter',self::ADAPTERS);$url=self::validateUrl(AiInput::text($a,'base_url',1000));
        if(!is_bool($a['enabled']??null))AiInput::fail('INVALID_INPUT','กรุณาระบุสถานะผู้ให้บริการ');
        $models=$a['models']??null;if(!is_array($models)||array_diff(array_keys($models),self::CAPABILITIES))AiInput::fail('INVALID_INPUT','รายการโมเดลไม่ถูกต้อง');
        $clean=[];$total=0;
        foreach(self::CAPABILITIES as $cap) {
            $list=$models[$cap]??[];if(!is_array($list)||!array_is_list($list)||count($list)>100)AiInput::fail('INVALID_INPUT','โมเดลต่อฟังก์ชันต้องไม่เกิน 100 รายการ');
            foreach($list as $model)if(!is_string($model)||!preg_match('#^[A-Za-z0-9][A-Za-z0-9._:/-]{0,199}$#D',$model))AiInput::fail('INVALID_INPUT','รหัสโมเดลต้องไม่มีช่องว่างหรืออักขระพิเศษ');
            $clean[$cap]=array_values(array_unique($list));$total+=count($clean[$cap]);
        }
        if($a['enabled']&&$total===0)AiInput::fail('INVALID_INPUT','ผู้ให้บริการที่เปิดใช้ต้องมีโมเดลอย่างน้อยหนึ่งรายการ');
        $revision=AiInput::number($a,'expected_revision',0,PHP_INT_MAX);
        return DB::transaction(function()use($value,$name,$adapter,$url,$clean,$a,$revision,$create){
            if($create) {
                if($revision!==0)AiInput::fail('REVISION_CONFLICT','ผู้ให้บริการใหม่ต้องใช้ revision 0',409);
                try{DB::run('INSERT INTO ai_providers(slug,name,adapter,base_url,models,enabled) VALUES(?,?,?,?,?,?)',[$value,$name,$adapter,$url,AiInput::json($clean),(int)$a['enabled']]);}
                catch(\mysqli_sql_exception $e){if($e->getCode()===1062)AiInput::fail('PROVIDER_EXISTS','รหัสผู้ให้บริการซ้ำ',409);throw $e;}
            } else {
                $old=DB::one('SELECT * FROM ai_providers WHERE slug=? FOR UPDATE',[$value])??AiInput::fail('NOT_FOUND','ไม่พบผู้ให้บริการ',404);
                if((int)$old['revision']!==$revision)AiInput::fail('REVISION_CONFLICT','ข้อมูลผู้ให้บริการเปลี่ยนแล้ว กรุณาโหลดใหม่',409);
                DB::run('UPDATE ai_providers SET name=?,adapter=?,base_url=?,models=?,enabled=?,revision=revision+1 WHERE slug=?',[$name,$adapter,$url,AiInput::json($clean),(int)$a['enabled'],$value]);
            }
            audit($create?'ai.provider.created':'ai.provider.updated',$value);return self::find($value,false);
        });
    }
    public static function requestHeaders(string $adapter,string $secret): array {
        return match($adapter){'openai_compatible'=>['Authorization: Bearer '.$secret,'Accept: application/json'],'anthropic'=>['x-api-key: '.$secret,'anthropic-version: 2023-06-01','Accept: application/json'],'gemini'=>['x-goog-api-key: '.$secret,'Accept: application/json'],default=>AiInput::fail('PROVIDER_NOT_ALLOWED','ไม่รองรับรูปแบบ API นี้')};
    }
    public static function verify(array $connection): void {
        $p=$connection['configuration']??self::find($connection['provider']);
        if(self::find($connection['provider'])['revision']!==$p['revision'])AiInput::fail('PROVIDER_CONFIG_CHANGED','ผู้ให้บริการเปลี่ยนแล้ว กรุณาโหลดใหม่',409);
        if($p['slug']==='mock'&&self::mockEnabled()) {if(!str_starts_with($connection['secret'],'test-valid-'))AiInput::fail('CREDENTIAL_INVALID','API key ทดสอบไม่ถูกต้อง',422);return;}
        $url=self::validateUrl($p['base_url']).'/models';$host=parse_url($url,PHP_URL_HOST);
        $address=self::publicAddress(gethostbynamel($host)?:[]);$ch=curl_init($url);$body='';$tooLarge=false;
        curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>self::requestHeaders($p['adapter'],$connection['secret']),CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_RESOLVE=>[$host.':443:'.$address],CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body,&$tooLarge){if(strlen($body)+strlen($chunk)>2*1024*1024){$tooLarge=true;return 0;}$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($status===401||$status===403)AiInput::fail('CREDENTIAL_INVALID','API key ไม่ถูกต้องหรือไม่มีสิทธิ์ตรวจการเชื่อมต่อ');
        if($ok===false||$tooLarge||$status!==200)AiInput::fail('PROVIDER_UNAVAILABLE','ตรวจการเชื่อมต่อไม่ได้ชั่วคราว กรุณาตรวจ Base URL และสิทธิ์อ่าน models',503);
        self::validateModelsResponse($p['adapter'],$body);
    }
    public static function validateModelsResponse(string $adapter,string $body): void {
        try{$decoded=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch(\Throwable){AiInput::fail('PROVIDER_RESPONSE_INVALID','ผู้ให้บริการไม่ได้ตอบกลับ JSON สำหรับรายการ models',503);}
        $key=$adapter==='gemini'?'models':'data';if(!is_array($decoded)||!isset($decoded[$key])||!is_array($decoded[$key])||!array_is_list($decoded[$key]))AiInput::fail('PROVIDER_RESPONSE_INVALID','รูปแบบรายการ models ไม่ตรงกับ API ที่เลือก',503);
    }
    public static function institution(): array {
        $slug=env('AI_INSTITUTION_PROVIDER','openai');if($slug==='mock'&&self::mockEnabled())return ['provider'=>'mock','provider_revision'=>1,'secret'=>'test-valid-institution'];
        $p=self::find($slug);$key=env('AI_INSTITUTION_API_KEY');if($key==='')AiInput::fail('BILLING_NOT_CONFIGURED','ยังไม่ได้ตั้ง API key ส่วนกลาง',409);
        return ['provider'=>$p['slug'],'provider_revision'=>$p['revision'],'configuration'=>$p,'secret'=>$key];
    }
    public static function institutionReady(): bool {try{self::institution();return true;}catch(AiError){return false;}}
}
