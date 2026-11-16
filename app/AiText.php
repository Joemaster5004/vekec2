<?php
declare(strict_types=1);
namespace App;
/** Text-only generation. No tools, remote files, redirects or client-supplied endpoint. */
final class AiText {
    public const OUTPUT_TOKENS=8192;
    public const INPUT_BOUND=40000;
    public static function rates(string $provider,string $model): array {
        if($provider==='mock'&&AiProvider::mockEnabled())return ['input'=>1.0,'output'=>2.0];
        $r=AiPricing::rate($provider,$model);
        if($r===null)AiInput::fail('PRICING_NOT_CONFIGURED','ผู้ดูแลยังไม่ได้กำหนดราคาของโมเดล '.$model.' กรุณาตั้งที่ จัดการระบบ → ผู้ให้บริการ AI → ราคาเนื้อหาและข้อสอบ',409);
        return $r;
    }
    public static function cost(array $rates,int $input,int $output): int {
        return max(1,(int)ceil($rates['input']*$input+$rates['output']*$output));
    }
    public static function payload(string $adapter,string $model,string $prompt): array {
        return match($adapter) {
            'openai_compatible'=>['/chat/completions',['model'=>$model,'messages'=>[['role'=>'user','content'=>$prompt]],'max_completion_tokens'=>self::OUTPUT_TOKENS]],
            'anthropic'=>['/messages',['model'=>$model,'messages'=>[['role'=>'user','content'=>$prompt]],'max_tokens'=>self::OUTPUT_TOKENS]],
            'gemini'=>['/models/'.rawurlencode(preg_replace('#^models/#','',$model)).':generateContent',['contents'=>[['role'=>'user','parts'=>[['text'=>$prompt]]]],'generationConfig'=>['maxOutputTokens'=>self::OUTPUT_TOKENS,'responseMimeType'=>'application/json']]],
            default=>AiInput::fail('PROVIDER_NOT_ALLOWED','ยังไม่รองรับการสร้างข้อความกับผู้ให้บริการนี้')
        };
    }
    public static function generate(array $connection,string $prompt,array $mock): array {
        if(strlen($prompt)+1024>self::INPUT_BOUND)AiInput::fail('PAYLOAD_TOO_LARGE','ข้อความอ้างอิงยาวเกินกำหนด');
        if($connection['provider']==='mock'&&AiProvider::mockEnabled())return ['text'=>AiInput::json($mock),'input'=>100,'output'=>200,'complete'=>true];
        $p=$connection['configuration']??AiProvider::find($connection['provider']);
        [$suffix,$payload]=self::payload($p['adapter'],$connection['model'],$prompt);
        $base=AiProvider::validateUrl($p['base_url']);$host=parse_url($base,PHP_URL_HOST);
        $ip=AiProvider::publicAddress(gethostbynamel($host)?:[]);
        $ch=curl_init($base.$suffix);$body='';$tooLarge=false;
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>AiInput::json($payload),CURLOPT_HTTPHEADER=>[...AiProvider::requestHeaders($p['adapter'],$connection['secret']),'Content-Type: application/json'],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_RESOLVE=>[$host.':443:'.$ip],CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>45,CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body,&$tooLarge){if(strlen($body)+strlen($chunk)>512*1024){$tooLarge=true;return 0;}$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        // A sent request may have incurred cost. The caller retains its reservation on uncertainty.
        if($ok===false||$tooLarge)AiInput::fail('GENERATION_UNCERTAIN','ยังยืนยันผลจาก AI ไม่ได้ ระบบคงเงินจองไว้และไม่เรียกซ้ำอัตโนมัติ กรุณาตรวจยอดกับผู้ให้บริการ',503);
        if($status!==200)AiInput::fail('GENERATION_REJECTED','AI ปฏิเสธคำขอ กรุณาตรวจสิทธิ์ key โมเดล และยอดคงเหลือกับผู้ให้บริการ (HTTP '.$status.')',502);
        try{$r=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch(\Throwable){AiInput::fail('PROVIDER_RESPONSE_INVALID','AI ส่งข้อมูลไม่ถูกต้อง',502);}
        return self::decode($p['adapter'],$r);
    }
    public static function decode(string $adapter,array $r): array {
        $text='';$input=null;$output=null;$complete=false;
        if($adapter==='openai_compatible') {
            $text=$r['choices'][0]['message']['content']??'';$input=$r['usage']['prompt_tokens']??null;$output=$r['usage']['completion_tokens']??null;
            $complete=($r['choices'][0]['finish_reason']??'')==='stop';
        } elseif($adapter==='anthropic') {
            foreach($r['content']??[] as $part)if(($part['type']??'')==='text')$text.=$part['text']??'';
            $input=$r['usage']['input_tokens']??null;$output=$r['usage']['output_tokens']??null;$complete=($r['stop_reason']??'')==='end_turn';
        } elseif($adapter==='gemini') {
            foreach($r['candidates'][0]['content']['parts']??[] as $part)if(empty($part['thought']))$text.=$part['text']??'';
            $input=$r['usageMetadata']['promptTokenCount']??null;
            $c=$r['usageMetadata']['candidatesTokenCount']??null;$t=$r['usageMetadata']['thoughtsTokenCount']??0;
            if(is_int($c)&&is_int($t)&&$c>=0&&$t>=0)$output=$c+$t;
            $complete=($r['candidates'][0]['finishReason']??'')==='STOP';
        }
        if(!is_string($text)||!is_int($input)||!is_int($output)||$input<0||$output<0||$input>10000000||$output>10000000)AiInput::fail('USAGE_UNCERTAIN','AI ไม่ส่งจำนวน token ที่ตรวจสอบได้ ระบบคงเงินจองไว้ กรุณาตรวจยอดกับผู้ให้บริการ',502);
        return compact('text','input','output','complete');
    }
}
