<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\{DB,AiDraft,AiText,AiCredentials,AiBilling,AiError,Quiz,HttpError};
use function App\env;
if(PHP_SAPI!=='cli'||env('APP_ENV')!=='test'||env('DB_DATABASE')!=='vekec_test')exit(1);
$checks=0;
function check(bool $yes,string $label): void {global $checks;if(!$yes)throw new RuntimeException($label);echo 'PASS '.$label."\n";$checks++;}
function denied(callable $fn,string $reason): void {try{$fn();throw new RuntimeException('Expected '.$reason);}catch(AiError $e){check($e->reason===$reason,'reject '.$reason);}}
$t=DB::one("SELECT * FROM users WHERE email='teacher@example.invalid'");$uid=(int)$t['id'];$_SESSION=['user_id'=>$uid];
DB::run('INSERT INTO courses(teacher_id,title,code,description,category,level) VALUES(?,?,?,?,?,?)',[$uid,'AI draft test','AI-DRAFT','Test only','ทดสอบระบบ','voc1']);$cid=DB::id();
$c=AiCredentials::create($uid,['provider'=>'mock','label'=>'Draft tests','secret'=>'test-valid-draft-tests']);AiCredentials::verify($uid,$c['credential_id'],['allow_billable_test'=>false,'confirmed_provider_revision'=>1]);
$binding=['capability'=>'text','payer'=>'teacher','credential_id'=>$c['credential_id'],'model'=>'mock-model','monthly_budget'=>1,'currency'=>'USD'];
AiBilling::save($t,$cid,['expected_revision'=>0,'bindings'=>[$binding],'confirmed_charge_acceptance'=>true]);
$a=['kind'=>'article','topic'=>'กฎของโอห์ม','objectives'=>'เข้าใจความสัมพันธ์','reference'=>'ตัวอย่าง','instructions'=>'','count'=>2,'minutes'=>30,'difficulty'=>'mixed','mode'=>'individual','reference_lesson_id'=>0];
$config=AiDraft::config($cid);check($config['model']==='mock-model'&&$config['max_request_usd']>0,'config displays budget bound');
$r=AiDraft::generate($cid,$uid,$a,'test-article');check($r['kind']==='article'&&str_contains($r['body'],'สรุป'),'article generated');
check(AiDraft::generate($cid,$uid,$a,'test-article')==$r,'completed draft replay');
check((int)DB::one('SELECT COUNT(*) AS n FROM ai_usage_reservations WHERE course_id=?',[$cid])['n']===1,'replay charged once');
denied(fn()=>AiDraft::generate($cid,$uid,[...$a,'topic'=>'Different'],'test-article'),'IDEMPOTENCY_CONFLICT');
$r=AiDraft::generate($cid,$uid,[...$a,'kind'=>'assignment'],'test-assignment');check(str_contains($r['body'],'สิ่งที่ต้องส่ง'),'assignment generated');
$r=AiDraft::generate($cid,$uid,[...$a,'kind'=>'quiz'],'test-quiz');check(count($r['questions'])===2&&$r['questions'][0]['content_format']==='text','quiz generated with answer');
DB::run("INSERT INTO lessons(course_id,title,kind,position) VALUES(?,?,'quiz',1)",[$cid,'Generated quiz']);$lid=DB::id();
DB::transaction(function()use($lid,$r){Quiz::add($lid,$r['questions']);});
check((int)DB::one('SELECT COUNT(*) AS n FROM questions WHERE lesson_id=?',[$lid])['n']===2,'quiz saves inside idempotency transaction');
try{DB::transaction(function()use($lid,$r){Quiz::add($lid,$r['questions']);throw new RuntimeException('rollback');});}catch(RuntimeException){}
check((int)DB::one('SELECT COUNT(*) AS n FROM questions WHERE lesson_id=?',[$lid])['n']===2,'outer rollback undoes quiz save');
DB::transaction(function()use($cid){try{DB::transaction(function()use($cid){DB::run("UPDATE courses SET title='Wrong' WHERE id=?",[$cid]);throw new RuntimeException();});}catch(RuntimeException){}check(DB::one('SELECT title FROM courses WHERE id=?',[$cid])['title']==='AI draft test','nested savepoint rollback');});
denied(fn()=>AiDraft::generate($cid,$uid,[...$a,'count'=>11],'bad-count'),'INVALID_INPUT');
denied(fn()=>AiDraft::generate($cid,$uid,[...$a,'reference_lesson_id'=>$lid],'bad-source'),'REFERENCE_NOT_FOUND');
denied(fn()=>AiDraft::generate($cid,$uid,$a,''),'IDEMPOTENCY_REQUIRED');
DB::run("INSERT INTO ai_drafts(actor_id,course_id,operation_key,request_hash) VALUES(?,?,?,?)",[$uid,$cid,'in-progress',hash('sha256',App\AiInput::json(AiDraft::validate($a)))]);
denied(fn()=>AiDraft::generate($cid,$uid,$a,'in-progress'),'GENERATION_PENDING');
denied(fn()=>AiDraft::result('{"questions":[]}','quiz',2),'DRAFT_INVALID');
$invalid=$r['questions'];$invalid[0]['options'][1]=$invalid[0]['options'][0];
denied(fn()=>AiDraft::result(json_encode(['questions'=>$invalid]),'quiz',2),'DRAFT_INVALID');
check(AiDraft::result('{"title":"<script>text</script>","body":"plain"}','article',1)['title']==='<script>text</script>','draft stays plain text for safe UI');
$decoded=AiText::decode('openai_compatible',['choices'=>[['message'=>['content'=>'{}'],'finish_reason'=>'stop']],'usage'=>['prompt_tokens'=>11,'completion_tokens'=>12]]);check($decoded['input']===11&&$decoded['complete'],'OpenAI usage decoded');
$decoded=AiText::decode('anthropic',['content'=>[['type'=>'text','text'=>'{}']],'stop_reason'=>'max_tokens','usage'=>['input_tokens'=>11,'output_tokens'=>12]]);check(!$decoded['complete'],'truncation detected');
$decoded=AiText::decode('gemini',['candidates'=>[['content'=>['parts'=>[['text'=>'private','thought'=>true],['text'=>'{}']]],'finishReason'=>'STOP']],'usageMetadata'=>['promptTokenCount'=>11,'candidatesTokenCount'=>12,'thoughtsTokenCount'=>5]]);check($decoded['text']==='{}'&&$decoded['output']===17,'Gemini counts thinking and excludes thought text');
denied(fn()=>AiText::decode('openai_compatible',['choices'=>[]]),'USAGE_UNCERTAIN');
denied(fn()=>AiText::rates('unconfigured','missing'),'PRICING_NOT_CONFIGURED');
foreach(['openai_compatible','anthropic','gemini'] as $adapter){[$suffix,$payload]=AiText::payload($adapter,'model','prompt');check(str_starts_with($suffix,'/')&&!isset($payload['tools']),'bounded text payload '.$adapter);}
AiBilling::save($t,$cid,['expected_revision'=>1,'bindings'=>[[...$binding,'monthly_budget'=>0.000001]],'confirmed_charge_acceptance'=>true]);
denied(fn()=>AiDraft::generate($cid,$uid,$a,'no-budget'),'BUDGET_EXCEEDED');
$other=DB::one("SELECT * FROM users WHERE email='otherteacher@example.invalid'");$_SESSION=['user_id'=>$other['id']];
try{AiDraft::config($cid);throw new RuntimeException('Expected forbidden');}catch(HttpError $e){check($e->getCode()===403,'other teacher forbidden');}
$_SESSION=['user_id'=>$uid];
DB::run("INSERT INTO users(name,email,role) VALUES(?,?,'student')",['Draft student','draft-'.bin2hex(random_bytes(5)).'@example.invalid']);$sid=DB::id();DB::run('INSERT INTO enrollments(user_id,course_id) VALUES(?,?)',[$sid,$cid]);
try{AiDraft::generate($cid,$uid,$a,'locked');throw new RuntimeException('Expected locked');}catch(HttpError $e){check($e->getCode()===409,'enrollment locks AI generation');}
echo "Draft service checks passed: $checks\n";
