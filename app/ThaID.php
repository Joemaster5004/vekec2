<?php
declare(strict_types=1);
namespace App;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
final class ThaID {
    public static function enabled(): bool {
        return env('THAID_ENABLED')==='true'&&env('THAID_CLIENT_ID')!==''&&env('THAID_CLIENT_SECRET')!==''&&env('THAID_API_KEY')!=='';
    }
    private function ensureEnabled(): void {if(!self::enabled())abort(503,'ThaID ยังไม่เปิดให้บริการ กรุณาสมัครหรือเข้าสู่ระบบด้วยอีเมล');}
    private function request(string $endpoint,?array $body=null,bool $credentials=false): array {
        $allowed=parse_url(env('THAID_DISCOVERY_URL'),PHP_URL_HOST);
        if(parse_url($endpoint,PHP_URL_SCHEME)!=='https'||parse_url($endpoint,PHP_URL_HOST)!==$allowed||parse_url($endpoint,PHP_URL_USER))abort(502,'การตั้งค่าปลายทาง ThaID ไม่ถูกต้อง');
        $headers=['Accept: application/json'];
        if($credentials){
            $headers[]='Authorization: Basic '.base64_encode(rawurlencode(env('THAID_CLIENT_ID')).':'.rawurlencode(env('THAID_CLIENT_SECRET')));
            $name=env('THAID_API_KEY_HEADER','apikey');if(!preg_match('/^[a-zA-Z0-9-]+$/',$name))abort(503,'การตั้งค่าหัวข้อ API key ไม่ถูกต้อง');
            $headers[]=$name.': '.env('THAID_API_KEY');
        }
        $ch=curl_init($endpoint);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        if($body!==null){$headers[]='Content-Type: application/x-www-form-urlencoded';curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($body));}
        curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);$raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($raw===false||$status!==200||strlen($raw)>1024*1024)abort(502,'ไม่สามารถติดต่อระบบ ThaID ได้ กรุณาลองใหม่ภายหลัง');
        try{$data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){abort(502,'ข้อมูลตอบกลับ ThaID ไม่ถูกต้อง');}
        if(!is_array($data))abort(502,'ข้อมูลตอบกลับ ThaID ไม่ถูกต้อง');return $data;
    }
    private function discovery(): array {
        $d=$this->request(env('THAID_DISCOVERY_URL'));foreach(['issuer','authorization_endpoint','token_endpoint','jwks_uri'] as $key)if(empty($d[$key])||!is_string($d[$key]))abort(502,'การตั้งค่า OpenID Connect ไม่ครบ');
        foreach(['issuer','authorization_endpoint','token_endpoint','jwks_uri'] as $key)if(parse_url($d[$key],PHP_URL_SCHEME)!=='https'||parse_url($d[$key],PHP_URL_HOST)!==parse_url(env('THAID_DISCOVERY_URL'),PHP_URL_HOST))abort(502,'ปลายทาง OpenID Connect ไม่ตรงกับผู้ให้บริการที่กำหนด');
        return $d;
    }
    public function start(): never {
        $this->ensureEnabled();Auth::limit('thaid',20);$d=$this->discovery();
        if(parse_url(env('THAID_REDIRECT_URI'),PHP_URL_SCHEME)!=='https')abort(503,'ThaID ต้องใช้ Callback URL แบบ HTTPS');
        $pkce=env('THAID_PKCE','true')==='true';
        if($pkce&&!in_array('S256',$d['code_challenge_methods_supported']??[],true))abort(503,'กรุณาตรวจสอบการรองรับ PKCE ในการตั้งค่า ThaID');
        $_SESSION['oidc']=['state'=>bin2hex(random_bytes(32)),'nonce'=>bin2hex(random_bytes(32)),'verifier'=>bin2hex(random_bytes(32)),'time'=>time(),'issuer'=>$d['issuer'],'pkce'=>$pkce];
        $s=$_SESSION['oidc'];$query=['response_type'=>'code','client_id'=>env('THAID_CLIENT_ID'),'redirect_uri'=>env('THAID_REDIRECT_URI'),'scope'=>env('THAID_SCOPE','openid'),'state'=>$s['state'],'nonce'=>$s['nonce']];
        if($pkce){$query['code_challenge']=rtrim(strtr(base64_encode(hash('sha256',$s['verifier'],true)),'+/','-_'),'=');$query['code_challenge_method']='S256';}
        header('Location: '.$d['authorization_endpoint'].(str_contains($d['authorization_endpoint'],'?')?'&':'?').http_build_query($query));exit;
    }
    public function callback(): never {
        $this->ensureEnabled();$s=$_SESSION['oidc']??null;unset($_SESSION['oidc']);
        if(!$s||time()-$s['time']>600||!is_string($_GET['state']??null)||!hash_equals($s['state'],$_GET['state']))abort(400,'การยืนยันตัวตนหมดอายุหรือ state ไม่ถูกต้อง กรุณาเริ่มใหม่');
        if(isset($_GET['error'])){flash('ยกเลิกการยืนยันตัวตนด้วย ThaID');redirect('/login');}
        $code=$_GET['code']??'';if(!is_string($code)||!$code||strlen($code)>4096)abort(400,'ไม่พบรหัสยืนยันตัวตน');
        $d=$this->discovery();if($s['issuer']!==$d['issuer'])abort(400,'ผู้ให้บริการยืนยันตัวตนเปลี่ยนไป');
        $body=['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>env('THAID_REDIRECT_URI')];if($s['pkce'])$body['code_verifier']=$s['verifier'];
        $tokens=$this->request($d['token_endpoint'],$body,true);if(empty($tokens['id_token'])||!is_string($tokens['id_token']))abort(502,'ThaID ไม่ได้ส่ง ID token');
        $jwks=$this->request($d['jwks_uri']);
        // Only provider-signed asymmetric keys are accepted. Symmetric JWTs are never accepted.
        $jwks['keys']=array_values(array_filter($jwks['keys']??[],fn($key)=>is_array($key)&&($key['kty']??'')==='RSA'&&($key['use']??'sig')==='sig'&&($key['alg']??'RS256')==='RS256'));
        try{
            JWT::$leeway=30;$claims=(array)JWT::decode($tokens['id_token'],JWK::parseKeySet($jwks,'RS256'));
            self::validateClaims($claims,$d['issuer'],env('THAID_CLIENT_ID'),$s['nonce']);
        }catch(\Throwable){abort(401,'ไม่สามารถตรวจสอบความถูกต้องของข้อมูล ThaID ได้ กรุณาเริ่มใหม่');}
        $subject=hash_hmac('sha256',$claims['iss'].'|'.$claims['sub'],env('APP_KEY'));
        $u=DB::one('SELECT id,active FROM users WHERE thaid_subject=?',[$subject]);
        if($u&&!$u['active'])abort(403,'บัญชีนี้ถูกระงับ กรุณาติดต่อผู้ดูแลระบบ');
        if(!$u){
            if(setting('registration_open')!=='1')abort(403,'ขณะนี้ปิดรับสมัครสมาชิกใหม่');
            $name=$claims['name']??trim(($claims['given_name']??'').' '.($claims['family_name']??''));
            if(!is_string($name)||trim($name)===''||strlen($name)>540)abort(422,'ไม่ได้รับชื่อผู้เรียนจาก ThaID กรุณาตรวจสอบ scope ที่ได้รับอนุมัติ');
            // Do not automatically link accounts by an email or a citizen ID.
            DB::run('INSERT INTO users(name,thaid_subject) VALUES(?,?)',[$name,$subject]);$u=['id'=>DB::id()];
        }
        unset($tokens,$claims);Auth::login((int)$u['id']);audit('thaid.login',(string)$u['id']);flash('ยืนยันตัวตนด้วย ThaID สำเร็จ กรุณาเลือกระดับผู้เรียนก่อนลงทะเบียนครั้งแรก');redirect('/profile');
    }
    public static function validateClaims(array $c,string $issuer,string $client,string $nonce): void {
        $aud=$c['aud']??null;$audiences=is_array($aud)?$aud:[$aud];
        if(($c['iss']??null)!==$issuer||!in_array($client,$audiences,true)||(!empty($c['azp'])&&$c['azp']!==$client)||(count($audiences)>1&&($c['azp']??null)!==$client)||!is_string($c['nonce']??null)||!hash_equals($nonce,$c['nonce'])||!is_string($c['sub']??null)||$c['sub']===''||!is_numeric($c['exp']??null)||(int)$c['exp']<time()-30||!is_numeric($c['iat']??null)||(int)$c['iat']>time()+30)throw new \RuntimeException('Invalid OIDC claims');
    }
}
