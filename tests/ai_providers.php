<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\{DB,AiProvider,AiCredentials,AiBilling,AiError};
use function App\env;
if(PHP_SAPI!=='cli'||env('APP_ENV')!=='test'||env('DB_DATABASE')!=='vekec_test')exit(1);
$checks=0;
function check(bool $yes,string $label): void {global $checks;if(!$yes)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function denied(callable $fn,string $code): void {try{$fn();throw new RuntimeException('Expected '.$code);}catch(AiError $e){check($e->reason===$code,'rejected '.$code);}}
$admin=DB::one("SELECT * FROM users WHERE email='admin@example.invalid'");$teacher=DB::one("SELECT * FROM users WHERE email='teacher@example.invalid'");$_SESSION=['user_id'=>$admin['id']];
$slug='test-'.bin2hex(random_bytes(4));$models=array_fill_keys(AiProvider::CAPABILITIES,[]);$models['text']=['custom-text'];
$data=['slug'=>$slug,'name'=>'Test Provider','adapter'=>'openai_compatible','base_url'=>'https://api.example.com/v1','models'=>$models,'enabled'=>true,'expected_revision'=>0];
$p=AiProvider::save(null,$data);check($p['revision']===1,'admin creates provider');
check(AiProvider::models('text',$slug)===['custom-text']&&AiProvider::models('speech',$slug)===[],'model allowlist is provider-specific');
denied(fn()=>AiProvider::save(null,$data),'PROVIDER_EXISTS');
foreach(['http://api.example.com/v1','https://127.0.0.1/v1','https://localhost/v1','https://api.example.com:8443/v1','https://user:pass@api.example.com/v1','https://api.example.com/v1?key=secret','https://api.example.com/v1#fragment','https://metadata.google.internal/v1','https://api.example.com/%2e%2e'] as $url)denied(fn()=>AiProvider::validateUrl($url),'INVALID_PROVIDER_URL');
foreach([['127.0.0.1'],['10.0.0.1'],['169.254.169.254'],['192.168.1.1'],['8.8.8.8','127.0.0.1'],['::1']] as $ips)denied(fn()=>AiProvider::publicAddress($ips),'PROVIDER_ADDRESS_DENIED');
check(AiProvider::publicAddress(['8.8.8.8'])==='8.8.8.8','public DNS address can be pinned');
foreach(['openai_compatible','anthropic'] as $adapter)AiProvider::validateModelsResponse($adapter,'{"data":[]}');
AiProvider::validateModelsResponse('gemini','{"models":[]}');check(true,'model responses validated per adapter');
denied(fn()=>AiProvider::validateModelsResponse('openai_compatible','<html>login</html>'),'PROVIDER_RESPONSE_INVALID');
denied(fn()=>AiProvider::validateModelsResponse('gemini','{"data":[]}'),'PROVIDER_RESPONSE_INVALID');
foreach(['openai_compatible'=>'Authorization: Bearer ','anthropic'=>'x-api-key: ','gemini'=>'x-goog-api-key: '] as $adapter=>$prefix)check(in_array($prefix.'test-valid-key',AiProvider::requestHeaders($adapter,'test-valid-key'),true),'correct authentication for '.$adapter);
$uid=(int)$teacher['id'];$c=AiCredentials::create($uid,['provider'=>$slug,'label'=>'Custom key','secret'=>'test-valid-custom-provider']);$id=$c['credential_id'];
check($c['provider_name']==='Test Provider'&&$c['provider_current_revision']===1,'teacher sees provider identity and endpoint');
// Skip real network verification: mark only this test fixture key verified.
DB::run("UPDATE ai_credentials SET status='verified' WHERE id=?",[$id]);
DB::run('INSERT INTO courses(teacher_id,title,code,description,category) VALUES(?,?,?,?,?)',[$uid,'Provider test','AI-PROVIDER','Test only','ทดสอบระบบ']);$cid=DB::id();
$_SESSION=['user_id'=>$uid];
$binding=['capability'=>'text','payer'=>'teacher','credential_id'=>$id,'model'=>'custom-text','monthly_budget'=>1,'currency'=>'USD'];
AiBilling::save($teacher,$cid,['expected_revision'=>0,'bindings'=>[$binding],'confirmed_charge_acceptance'=>true]);
$r=AiBilling::reserve($cid,$uid,'text',1000,'before-provider-change');
denied(fn()=>AiBilling::save($teacher,$cid,['expected_revision'=>1,'bindings'=>[[...$binding,'model'=>'mock-model']],'confirmed_charge_acceptance'=>true]),'INVALID_INPUT');
$_SESSION=['user_id'=>$admin['id']];
$p=AiProvider::save($slug,[...$data,'expected_revision'=>1,'base_url'=>'https://another.example.com/v1']);
denied(fn()=>AiCredentials::secret($uid,$id,1),'PROVIDER_CONFIG_CHANGED');
denied(fn()=>AiCredentials::verify($uid,$id,['allow_billable_test'=>false,'confirmed_provider_revision'=>1]),'PROVIDER_CONFIG_CHANGED');
denied(fn()=>AiBilling::begin($r['id']),'PROVIDER_CONFIG_CHANGED');
check(AiCredentials::metadata(AiCredentials::owned($uid,$id))['status']==='provider_changed','endpoint changes require teacher confirmation');
$p=AiProvider::save($slug,[...$data,'expected_revision'=>2,'enabled'=>false]);
denied(fn()=>AiCredentials::create($uid,['provider'=>$slug,'label'=>'Disabled','secret'=>'test-valid-disabled']),'INVALID_INPUT');
denied(fn()=>AiCredentials::secret($uid,$id,1),'PROVIDER_DISABLED');
check(!in_array($slug,AiCredentials::providers(),true),'disabled provider hidden from teacher selections');
check(AiCredentials::metadata(AiCredentials::owned($uid,$id))['status']==='provider_disabled','disabled provider reflected in key metadata');
denied(fn()=>AiProvider::save($slug,[...$data,'expected_revision'=>1]),'REVISION_CONFLICT');
echo "Provider service checks passed: $checks\n";
