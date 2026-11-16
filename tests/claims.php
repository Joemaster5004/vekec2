<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\ThaID;
use Firebase\JWT\{JWT,Key};
$valid=['iss'=>'https://identity.example.invalid','aud'=>'test-client','nonce'=>'test-nonce','sub'=>'opaque-subject','exp'=>time()+300,'iat'=>time()];
ThaID::validateClaims($valid,$valid['iss'],'test-client','test-nonce');$count=1;
foreach(['iss'=>'https://attacker.invalid','aud'=>'wrong-client','nonce'=>'wrong-nonce','sub'=>'','exp'=>time()-300,'iat'=>time()+300,'azp'=>'other-client']as $key=>$value){
    try{ThaID::validateClaims(array_replace($valid,[$key=>$value]),$valid['iss'],'test-client','test-nonce');throw new LogicException('Accepted invalid '.$key);}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;$count++;}
}
try{ThaID::validateClaims(array_replace($valid,['aud'=>['test-client','other']]),$valid['iss'],'test-client','test-nonce');throw new LogicException('Accepted missing azp');}catch(RuntimeException){$count++;}
$key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);openssl_pkey_export($key,$private);$public=openssl_pkey_get_details($key)['key'];$jwt=JWT::encode($valid,$private,'RS256');JWT::decode($jwt,new Key($public,'RS256'));$count++;
$parts=explode('.',$jwt);$parts[1]=rtrim(strtr(base64_encode(json_encode(array_replace($valid,['sub'=>'forged']))),'+/','-_'),'=');
try{JWT::decode(implode('.',$parts),new Key($public,'RS256'));throw new LogicException('Accepted tampered JWT');}catch(Firebase\JWT\SignatureInvalidException){$count++;}
echo "OIDC checks passed: $count\n";
