<?php
declare(strict_types=1);
namespace App;
final class AiCredentials {
    private static function master(): string {
        $key=base64_decode(env('AI_CREDENTIALS_ENCRYPTION_KEY'),true);
        if($key===false||strlen($key)!==32)AiInput::fail('ENCRYPTION_NOT_CONFIGURED','ผู้ดูแลต้องตั้งกุญแจเข้ารหัส AI ก่อนใช้งาน',503);
        return $key;
    }
    public static function ready(): bool { try{self::master();return true;}catch(AiError){return false;} }
    public static function fingerprint(string $body): string { return hash_hmac('sha256',$body,hash_hmac('sha256','ai-idempotency-v1',self::master(),true)); }
    public static function providers(): array { return array_column(AiProvider::catalog(),'slug'); }
    private static function aad(array $r): string { return 'vekec-ai|'.$r['id'].'|'.$r['owner_user_id'].'|'.$r['provider'].'|'.$r['version'].'|'.$r['key_version']; }
    private static function encrypt(array $r,string $secret): array {
        $nonce=random_bytes(12);$tag='';$cipher=openssl_encrypt($secret,'aes-256-gcm',self::master(),OPENSSL_RAW_DATA,$nonce,$tag,self::aad($r));
        if($cipher===false)AiInput::fail('ENCRYPTION_FAILED','เข้ารหัสไม่สำเร็จ',503);
        return [base64_encode($cipher),base64_encode($nonce),base64_encode($tag)];
    }
    public static function metadata(array $r): array {
        $p=AiProvider::find($r['provider'],false);$status=$r['status'];
        if($status!=='revoked') {if(!$p['enabled'])$status='provider_disabled';elseif((int)$r['provider_revision']!==$p['revision'])$status='provider_changed';}
        return ['credential_id'=>(string)$r['id'],'provider'=>$r['provider'],'provider_name'=>$p['name'],'provider_base_url'=>$p['base_url'],'provider_revision'=>(int)$r['provider_revision'],'provider_current_revision'=>$p['revision'],'label'=>$r['label'],'key_hint'=>$r['key_hint'],'status'=>$status,'version'=>(int)$r['version'],'last_verified_at'=>AiInput::iso($r['last_verified_at'])];
    }
    public static function owned(int $uid,string $id,bool $lock=false): array {
        if(!preg_match('/^[1-9][0-9]{0,18}$/D',$id))AiInput::fail('NOT_FOUND','ไม่พบการเชื่อมต่อ',404);
        return DB::one('SELECT * FROM ai_credentials WHERE id=? AND owner_user_id=?'.($lock?' FOR UPDATE':''),[$id,$uid])??AiInput::fail('NOT_FOUND','ไม่พบการเชื่อมต่อ',404);
    }
    public static function list(int $uid): array { return array_map(self::metadata(...),DB::all('SELECT * FROM ai_credentials WHERE owner_user_id=? ORDER BY id DESC',[$uid])); }
    public static function create(int $uid,array $a): array {
        AiInput::only($a,['provider','label','secret']);$provider=AiInput::choice($a,'provider',self::providers());$label=AiInput::text($a,'label',300);$secret=AiInput::text($a,'secret',8192);self::validateSecret($secret);self::master();
        if(preg_match_all('/./us',$label)>100)AiInput::fail('INVALID_INPUT','ชื่อการเชื่อมต่อยาวเกิน 100 ตัวอักษร');
        $p=AiProvider::find($provider);
        DB::run('INSERT INTO ai_credentials(owner_user_id,provider,label,key_hint,key_version,provider_revision) VALUES(?,?,?,?,?,?)',[$uid,$provider,$label,substr($secret,-4),'1',$p['revision']]);$id=DB::id();
        $r=self::owned($uid,(string)$id);$parts=self::encrypt($r,$secret);DB::run('UPDATE ai_credentials SET ciphertext=?,nonce=?,tag=? WHERE id=?',[...$parts,$id]);
        audit('ai.credential.created',(string)$id);return self::metadata(self::owned($uid,(string)$id));
    }
    public static function rotate(int $uid,string $id,array $a): array {
        AiInput::only($a,['expected_version','secret']);$version=AiInput::number($a,'expected_version',1,PHP_INT_MAX);$secret=AiInput::text($a,'secret',8192);self::validateSecret($secret);
        return DB::transaction(function()use($uid,$id,$version,$secret){
            $r=self::owned($uid,$id,true);if($r['status']==='revoked')AiInput::fail('CREDENTIAL_REVOKED','การเชื่อมต่อถูกเพิกถอนแล้ว',409);
            if((int)$r['version']!==$version)AiInput::fail('REVISION_CONFLICT','ข้อมูลการเชื่อมต่อเปลี่ยนแล้ว กรุณาโหลดใหม่',409);
            $r['version']=$version+1;$parts=self::encrypt($r,$secret);
            DB::run("UPDATE ai_credentials SET version=?,ciphertext=?,nonce=?,tag=?,key_hint=?,status='unverified',last_verified_at=NULL WHERE id=?",[$r['version'],...$parts,substr($secret,-4),$id]);audit('ai.credential.rotated',$id);return self::metadata(self::owned($uid,$id));
        });
    }
    public static function revoke(int $uid,string $id): array {
        self::owned($uid,$id);DB::run("UPDATE ai_credentials SET status='revoked',ciphertext=NULL,nonce=NULL,tag=NULL,revoked_at=NOW() WHERE id=? AND owner_user_id=?",[$id,$uid]);audit('ai.credential.revoked',$id);return self::metadata(self::owned($uid,$id));
    }
    public static function secret(int $uid,string $id,int $version,bool $verified=true,?int $approvedProviderRevision=null): array {
        $r=self::owned($uid,$id);if($r['status']==='revoked')AiInput::fail('CREDENTIAL_REVOKED','การเชื่อมต่อถูกปิดใช้งาน',409);
        if((int)$r['version']!==$version)AiInput::fail('CREDENTIAL_VERSION_RETIRED','API key เปลี่ยนแล้ว กรุณายืนยันงานใหม่',409);
        if($verified&&$r['status']!=='verified')AiInput::fail('CREDENTIAL_INVALID','กรุณาทดสอบการเชื่อมต่อก่อนใช้งาน',409);
        $p=AiProvider::find($r['provider']);
        if(($approvedProviderRevision??(int)$r['provider_revision'])!==$p['revision'])AiInput::fail('PROVIDER_CONFIG_CHANGED','ผู้ให้บริการเปลี่ยนแล้ว กรุณาตรวจปลายทางและทดสอบใหม่',409);
        $cipher=base64_decode((string)$r['ciphertext'],true);$nonce=base64_decode((string)$r['nonce'],true);$tag=base64_decode((string)$r['tag'],true);
        if($cipher===false||$nonce===false||strlen($nonce)!==12||$tag===false||strlen($tag)!==16)AiInput::fail('CREDENTIAL_DECRYPT_FAILED','ข้อมูลการเชื่อมต่อเสียหาย กรุณาติดต่อผู้ดูแล',503);
        $secret=openssl_decrypt($cipher,'aes-256-gcm',self::master(),OPENSSL_RAW_DATA,$nonce,$tag,self::aad($r));
        if($secret===false)AiInput::fail('CREDENTIAL_DECRYPT_FAILED','อ่านการเชื่อมต่อไม่ได้ กรุณาติดต่อผู้ดูแล',503);
        return ['provider'=>$r['provider'],'provider_revision'=>$p['revision'],'configuration'=>$p,'secret'=>$secret];
    }
    public static function verify(int $uid,string $id,array $a): array {
        AiInput::only($a,['allow_billable_test','confirmed_provider_revision']);if(($a['allow_billable_test']??false)!==false)AiInput::fail('INVALID_INPUT','การทดสอบนี้ตรวจสิทธิ์เท่านั้น ไม่สร้างงานคิดค่าใช้จ่าย');
        $r=self::owned($uid,$id);$approved=AiInput::number($a,'confirmed_provider_revision',1,PHP_INT_MAX);$connection=self::secret($uid,$id,(int)$r['version'],false,$approved);
        try { AiProvider::verify($connection); }
        catch(AiError $e) { if($e->reason==='CREDENTIAL_INVALID')DB::run("UPDATE ai_credentials SET status='invalid' WHERE id=? AND version=? AND status<>'revoked'",[$id,$r['version']]);throw $e; }
        if(AiProvider::find($r['provider'])['revision']!==$approved)AiInput::fail('PROVIDER_CONFIG_CHANGED','การตั้งค่าผู้ให้บริการเปลี่ยนระหว่างทดสอบ กรุณาโหลดใหม่',409);
        DB::run("UPDATE ai_credentials SET status='verified',provider_revision=?,last_verified_at=NOW() WHERE id=? AND version=? AND status<>'revoked'",[$approved,$id,$r['version']]);
        audit('ai.credential.verified',$id);return ['credential'=>self::metadata(self::owned($uid,$id)),'verified_capabilities'=>[],'billable_test_performed'=>false];
    }
    private static function validateSecret(string $secret): void { if(strlen($secret)<12||!preg_match('/^[!-~]+$/D',$secret))AiInput::fail('INVALID_INPUT','API key ต้องยาวอย่างน้อย 12 ตัวอักษร และไม่มีช่องว่างหรืออักขระควบคุม'); }
}
