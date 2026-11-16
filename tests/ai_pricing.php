<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\{DB,AiPricing,AiText,AiProvider,AiError,HttpError};
use function App\env;
if(PHP_SAPI!=='cli'||env('APP_ENV')!=='test'||env('DB_DATABASE')!=='vekec_test')exit(1);
$checks=0;
function check(bool $yes,string $label): void {global $checks;if(!$yes)throw new RuntimeException($label);$checks++;echo 'PASS '.$label."\n";}
function denied(callable $fn,string $reason): void {try{$fn();throw new RuntimeException('Expected '.$reason);}catch(AiError $e){check($e->reason===$reason,'reject '.$reason);}}
$admin=DB::one("SELECT id FROM users WHERE email='admin@example.invalid'");$teacher=DB::one("SELECT id FROM users WHERE email='teacher@example.invalid'");$_SESSION=['user_id'=>$admin['id']];
$slug='price-'.bin2hex(random_bytes(4));$models=array_fill_keys(AiProvider::CAPABILITIES,[]);$models['text']=['priced-model','env-model'];
$p=AiProvider::save(null,['slug'=>$slug,'name'=>'Pricing test','adapter'=>'openai_compatible','base_url'=>'https://api.example.com/v1','models'=>$models,'enabled'=>true,'expected_revision'=>0]);
putenv('AI_TEXT_PRICING_JSON='.json_encode([$slug=>['env-model'=>['input'=>2,'output'=>3]]]));
check(AiText::rates($slug,'env-model')===['input'=>2.0,'output'=>3.0],'legacy environment prices still work');
denied(fn()=>AiText::rates($slug,'priced-model'),'PRICING_NOT_CONFIGURED');
$revision=AiPricing::stored()['revision'];$row=['provider'=>$slug,'model'=>'priced-model','input'=>0.3,'output'=>2.5];
$r=AiPricing::save(['expected_revision'=>$revision,'rows'=>[$row]]);
check($r['revision']===$revision+1,'pricing revision advanced');
check(AiText::rates($slug,'priced-model')===['input'=>0.3,'output'=>2.5],'generation uses saved website prices immediately');
check(AiText::cost(AiText::rates($slug,'priced-model'),40000,8192)===32480,'budget uses configured price');
check(AiProvider::find($slug)['revision']===$p['revision'],'price edit does not invalidate provider approval');
check(AiText::rates($slug,'env-model')['input']===2.0,'price patch preserves other model fallback');
denied(fn()=>AiPricing::save(['expected_revision'=>$revision,'rows'=>[$row]]),'REVISION_CONFLICT');
$revision++;
foreach([['input'=>-1],['input'=>'0.3'],['output'=>true],['output'=>10001],['model'=>'not-allowed']] as $change) {
 denied(fn()=>AiPricing::save(['expected_revision'=>$revision,'rows'=>[[...$row,...$change]]]),isset($change['model'])?'MODEL_NOT_ALLOWED':'INVALID_INPUT');
}
denied(fn()=>AiPricing::save(['expected_revision'=>$revision,'rows'=>[$row,$row]]),'INVALID_INPUT');
check(AiText::rates($slug,'priced-model')['output']===2.5,'invalid changes preserve saved prices');
AiPricing::save(['expected_revision'=>$revision,'rows'=>[[...$row,'input'=>0,'output'=>0]]]);check(AiText::rates($slug,'priced-model')===['input'=>0.0,'output'=>0.0],'explicit free-tier rates accepted');
$_SESSION=['user_id'=>$teacher['id']];
foreach([fn()=>AiPricing::catalog(),fn()=>AiPricing::save(['expected_revision'=>0,'rows'=>[$row]])] as $fn)try{$fn();throw new RuntimeException('Expected admin role');}catch(HttpError $e){check($e->getCode()===403,'teacher cannot manage pricing');}
echo "Pricing service checks passed: $checks\n";
