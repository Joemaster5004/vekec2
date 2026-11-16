<?php
declare(strict_types=1);
namespace App;
/** Admin-managed text prices, USD per million tokens. Environment remains a fallback. */
final class AiPricing {
    public static function stored(): array {
        $row=DB::one("SELECT setting_value FROM settings WHERE setting_key='ai_text_pricing'");
        if(!$row)return ['revision'=>0,'rates'=>[]];
        try{$data=json_decode($row['setting_value'],true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){AiInput::fail('PRICING_INVALID','ข้อมูลราคา AI ไม่ถูกต้อง กรุณาติดต่อผู้ดูแล',503);}
        if(!is_array($data)||!is_int($data['revision']??null)||!is_array($data['rates']??null))AiInput::fail('PRICING_INVALID','ข้อมูลราคา AI ไม่ถูกต้อง กรุณาติดต่อผู้ดูแล',503);
        return $data;
    }
    public static function environment(): array {
        try{$data=json_decode(env('AI_TEXT_PRICING_JSON','{}'),true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){return [];}
        return is_array($data)?$data:[];
    }
    public static function valid(mixed $r): bool {
        if(!is_array($r))return false;
        foreach(['input','output'] as $key)if(!isset($r[$key])||(!is_int($r[$key])&&!is_float($r[$key])&&!is_string($r[$key]))||!is_numeric($r[$key])||!is_finite((float)$r[$key])||(float)$r[$key]<0||(float)$r[$key]>10000)return false;
        return true;
    }
    public static function rate(string $provider,string $model): ?array {
        $stored=self::stored();$env=self::environment();$r=$stored['rates'][$provider][$model]??$env[$provider][$model]??null;
        return self::valid($r)?['input'=>(float)$r['input'],'output'=>(float)$r['output']]:null;
    }
    public static function catalog(): array {
        Auth::require(['admin']);$stored=self::stored();$env=self::environment();$rows=[];
        foreach(AiProvider::catalog(true) as $p)foreach($p['models']['text']??[] as $model) {
            if($p['slug']==='mock')continue;
            $r=$stored['rates'][$p['slug']][$model]??$env[$p['slug']][$model]??null;
            $rows[]=['provider'=>$p['slug'],'provider_name'=>$p['name'],'model'=>$model,'input'=>self::valid($r)?(float)$r['input']:null,'output'=>self::valid($r)?(float)$r['output']:null,'configured'=>self::valid($r)];
        }
        return ['revision'=>$stored['revision'],'rows'=>$rows];
    }
    public static function save(array $a): array {
        Auth::require(['admin']);AiInput::only($a,['expected_revision','rows']);$revision=AiInput::number($a,'expected_revision',0,PHP_INT_MAX);
        $rows=$a['rows']??null;if(!is_array($rows)||!array_is_list($rows)||count($rows)>200)AiInput::fail('INVALID_INPUT','กำหนดราคาได้ไม่เกิน 200 โมเดลต่อครั้ง');
        $rates=[];
        foreach($rows as $r) {
            if(!is_array($r))AiInput::fail('INVALID_INPUT','รูปแบบราคาไม่ถูกต้อง');
            AiInput::only($r,['provider','model','input','output']);$provider=AiInput::text($r,'provider',30);$model=AiInput::text($r,'model',200);
            $p=AiProvider::find($provider,false);
            if(!in_array($model,$p['models']['text']??[],true))AiInput::fail('MODEL_NOT_ALLOWED','โมเดลราคาไม่อยู่ในรายการเนื้อหาและข้อสอบ');
            foreach(['input','output'] as $key)if(!is_int($r[$key]??null)&&!is_float($r[$key]??null))AiInput::fail('INVALID_INPUT','ราคาต้องเป็นตัวเลข USD ต่อหนึ่งล้าน token');
            if(!self::valid($r)||isset($rates[$provider][$model]))AiInput::fail('INVALID_INPUT','ราคาไม่อยู่ในช่วง 0–10,000 USD หรือมีโมเดลซ้ำ');
            $rates[$provider][$model]=['input'=>(float)$r['input'],'output'=>(float)$r['output']];
        }
        DB::transaction(function()use($revision,$rates){
            DB::run("INSERT IGNORE INTO settings(setting_key,setting_value) VALUES('ai_text_pricing',?)",[AiInput::json(['revision'=>0,'rates'=>[]])]);
            $row=DB::one("SELECT setting_value FROM settings WHERE setting_key='ai_text_pricing' FOR UPDATE");$old=json_decode($row['setting_value'],true);
            if(($old['revision']??null)!==$revision)AiInput::fail('REVISION_CONFLICT','ราคาเปลี่ยนแล้ว กรุณาโหลดหน้าผู้ให้บริการใหม่ก่อนบันทึก',409);
            // Patch only submitted models: unpriced models and entries outside this page remain unchanged.
            $merged=$old['rates'];foreach($rates as $provider=>$models)foreach($models as $model=>$r)$merged[$provider][$model]=$r;
            $json=AiInput::json(['revision'=>$revision+1,'rates'=>$merged]);if(strlen($json)>60000)AiInput::fail('PAYLOAD_TOO_LARGE','รายการราคามากเกินกำหนด');
            DB::run("UPDATE settings SET setting_value=? WHERE setting_key='ai_text_pricing'",[$json]);audit('ai.pricing.saved','text');
        });return self::catalog();
    }
}
