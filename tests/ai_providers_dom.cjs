const {JSDOM,VirtualConsole}=require('jsdom');
const fs=require('node:fs'),assert=require('node:assert/strict');
const vc=new VirtualConsole(),errors=[];vc.on('jsdomError',e=>errors.push(e));
const dom=new JSDOM(fs.readFileSync('/checks/ai-providers.html','utf8'),{url:'http://localhost:8090/admin/ai-providers',runScripts:'outside-only',virtualConsole:vc});
const w=dom.window,d=w.document,$=id=>d.getElementById(id),form=$('ai-provider-form');
let checks=0;const requests=[],rows=[{slug:'openai',name:'OpenAI',adapter:'openai_compatible',base_url:'https://api.openai.com/v1',models:{text:['existing-model']},enabled:true,revision:1}];
function check(value,label){assert.ok(value,label);checks++;console.log('PASS',label);}
async function flush(){for(let i=0;i<8;i++)await new Promise(r=>setTimeout(r,0));}
w.fetch=async(url,options)=>{
 const body=options.body?JSON.parse(options.body):null;requests.push({url,options,body});
 if(url.endsWith('/ai-reauth')) {check($('ai-provider-reauth').elements.password.value==='','admin reauth clears password before request');return {ok:true,json:async()=>({data:{authenticated:true}})};}
 if(options.method==='POST')rows.push({...body,revision:1});
 if(options.method==='PUT'){const index=rows.findIndex(p=>p.slug===body.slug);rows[index]={...body,revision:body.expected_revision+1};}
 return {ok:true,json:async()=>({data:options.method==='GET'?rows:rows.at(-1)})};
};
const submit=f=>f.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
(async()=>{
 w.eval(fs.readFileSync('/work/public/assets/ai-providers.js','utf8'));await flush();
 check($('ai-provider-list').textContent.includes('existing-model'),'admin catalog initializes');
 $('ai-provider-template').value='gemini';$('ai-provider-template').dispatchEvent(new w.Event('change'));
 check(form.elements.adapter.value==='gemini'&&form.elements.base_url.value==='https://generativelanguage.googleapis.com/v1beta','Gemini template sets adapter and versioned endpoint');
 form.elements.slug.value='custom-ai';form.elements.name.value='<img src=x onerror=alert(1)>';form.elements.models_text.value='model-one, model-two\nmodel-one';submit(form);await flush();
 const created=requests.find(r=>r.options.method==='POST');
 check(created.body.models.text.join(',')==='model-one,model-two','model lists trimmed and deduplicated');
 check(created.options.headers['X-CSRF-Token']&&created.body.expected_revision===0,'creation includes CSRF and initial revision');
 check(!$('ai-provider-list').querySelector('img')&&$('ai-provider-list').textContent.includes('<img'),'provider names rendered as text');
 check(!('secret' in created.body)&&!('api_key' in created.body),'admin catalog does not collect teacher secrets');
 const cards=$('ai-provider-list').querySelectorAll('.ai-credential');cards[1].querySelector('button').click();await flush();
 check(form.elements.slug.readOnly&&$('ai-provider-template').disabled,'editing preserves immutable provider identity');
 form.elements.models_text.value='replacement-model';submit(form);await flush();
 const edited=requests.findLast(r=>r.options.method==='PUT');
 check(edited.url.endsWith('/custom-ai')&&edited.body.expected_revision===1,'edit uses provider slug and expected revision');
 const fresh=$('ai-provider-list').querySelectorAll('.ai-credential')[1];fresh.querySelectorAll('button')[1].click();await flush();
 check(requests.findLast(r=>r.options.method==='PUT').body.enabled===false,'disable uses saved provider configuration');
 $('ai-provider-reauth').elements.password.value='test-password';submit($('ai-provider-reauth'));await flush();
 check(errors.length===0,'admin UI has no DOM runtime errors');
 console.log(`Admin provider DOM checks passed: ${checks}`);w.close();
})().catch(e=>{console.error(e);process.exitCode=1;w.close();});
