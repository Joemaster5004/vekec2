// Runtime tests for sensitive form handling, safe rendering, billing consent and request races.
const {JSDOM,VirtualConsole}=require('jsdom');
const fs=require('node:fs');
const assert=require('node:assert/strict');
const errors=[];const vc=new VirtualConsole();vc.on('jsdomError',e=>errors.push(e));
const html=fs.readFileSync('/checks/ai-connections.html','utf8');
const dom=new JSDOM(html,{url:'http://localhost:8090/teach/ai-connections',runScripts:'outside-only',virtualConsole:vc});
const w=dom.window,d=w.document,$=id=>d.getElementById(id);
const credentials=[{credential_id:'200',provider:'other-provider',provider_name:'Another provider',provider_base_url:'https://other.example.com/v1',provider_revision:1,provider_current_revision:1,label:'Other key',key_hint:'5678',status:'verified',version:1}];const requests=[];let heldOldCourse=null,holdRace=false,checks=0;
const tick=()=>new Promise(resolve=>setTimeout(resolve,0));
async function flush(){for(let i=0;i<8;i++)await tick();}
function check(value,label){assert.ok(value,label);checks++;console.log('PASS',label);}
function response(data,status=200){return {ok:status<400,json:async()=>status<400?{data}:{error:{message:data}}};}
w.fetch=async(url,options={})=>{
  const body=options.body?JSON.parse(options.body):null;requests.push({url,options,body});
  if(url.endsWith('/ai-capabilities'))return response({providers:['mock'],encryption_ready:true,institution_available:false,models:Object.fromEntries(['text','embedding','speech','analytics','assistant'].map(c=>[c,['mock-model']])),provider_catalog:[{slug:'mock',name:'Mock from Admin',models:Object.fromEntries(['text','embedding','speech','analytics','assistant'].map(c=>[c,['mock-model']]))},{slug:'other-provider',name:'Another provider',models:{text:['other-model']}}]});
  if(url==='/api/v1/me/ai-credentials'&&options.method==='GET')return response(credentials);
  if(url==='/api/v1/me/ai-credentials'&&options.method==='POST'){
    check($('ai-create').elements.secret.value==='','secret input cleared before fetch');
    credentials.push({credential_id:'100',provider:'mock',label:body.label,key_hint:'1234',status:'verified',version:1,provider_name:'Mock from Admin',provider_base_url:'https://mock.example.invalid/v1',provider_revision:1,provider_current_revision:1});return response(credentials[0],201);
  }
  if(url.endsWith('/ai/billing')&&options.method==='GET'){
    if(holdRace&&url.includes('/101/'))return new Promise(resolve=>{heldOldCourse=()=>resolve(response({revision:111,bindings:[]}));});
    return response({revision:url.includes('/102/')?222:1,bindings:[]});
  }
  if(url.endsWith('/ai/billing')&&options.method==='PUT')return response({revision:2,bindings:body.bindings});
  if(url.endsWith('/ai/usage'))return response([]);
  if(url.endsWith('/ai-reauth')){
    check($('ai-reauth').elements.password.value==='','reauth password input cleared before fetch');return response({authenticated:true});
  }
  throw new Error('Unexpected '+url);
};
function submit(form){form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));}
(async()=>{
  w.eval(fs.readFileSync('/work/public/assets/ai-connections.js','utf8'));await flush();
  check($('ai-create').elements.provider.value==='mock','provider initializes from server allowlist');
  const form=$('ai-create');form.elements.label.value='<img src=x onerror=alert(1)>';form.elements.secret.value='test-private-1234';submit(form);await flush();
  check(!$('ai-credentials').querySelector('img'),'credential labels render safely as text');
  check($('ai-credentials').textContent.includes('<img'),'label text remains readable');
  const create=requests.find(r=>r.url.endsWith('/ai-credentials')&&r.options.method==='POST');
  check(create.body.secret==='test-private-1234'&&create.options.headers['X-CSRF-Token'],'creation sends secret only in JSON with CSRF');
  check(!d.body.textContent.includes('test-private-1234'),'secret never rendered into page');
  check(w.localStorage.length===0&&w.sessionStorage.length===0,'secrets not persisted in browser storage');
  const course=$('ai-course');for(const value of ['101','102']){const o=d.createElement('option');o.value=value;o.textContent='Course '+value;course.append(o);}
  course.value='101';course.dispatchEvent(new w.Event('change'));await flush();
  check(!$('ai-billing').hidden&&$('ai-binding-rows').rows.length===5,'five supported billing rows render');
  const row=cap=>$('ai-binding-rows').querySelector(`[data-capability="${cap}"]`);
  const field=(cap,name)=>row(cap).querySelector(`[data-field="${name}"]`);
  field('text','payer').value='teacher';field('text','payer').dispatchEvent(new w.Event('change'));field('text','credential').value='100';
  check(!field('text','credential').disabled,'personal payer enables own credential selection');
  check(!$('ai-student-consent')&&!$('ai-student-limit'),'student AI controls removed');
  field('text','credential').value='200';field('text','credential').dispatchEvent(new w.Event('change'));
  check(field('text','model').value==='other-model','models follow the selected credential provider');
  check(!Array.from(field('text','model').options).some(o=>o.value==='mock-model'),'models from other providers are excluded');
  field('text','credential').value='100';field('text','credential').dispatchEvent(new w.Event('change'));
  field('assistant','payer').value='teacher';field('assistant','payer').dispatchEvent(new w.Event('change'));field('assistant','credential').value='100';field('assistant','credential').dispatchEvent(new w.Event('change'));
  $('ai-billing').elements.confirmed.checked=true;submit($('ai-billing'));await flush();
  const save=requests.findLast(r=>r.url.endsWith('/ai/billing')&&r.options.method==='PUT');const assistant=save.body.bindings.find(b=>b.capability==='assistant');
  check(!('allow_student_usage' in assistant)&&!('student_daily_request_limit' in assistant)&&save.body.confirmed_charge_acceptance,'billing contains only teacher settings');
  check(!JSON.stringify(save.body).includes('test-private-1234'),'billing references credential ID without sending secret');
  check(!$('ai-billing').elements.confirmed.checked,'charge acceptance resets after configuration reload');
  $('ai-reauth').elements.password.value='test-password';submit($('ai-reauth'));await flush();
  holdRace=true;course.value='101';course.dispatchEvent(new w.Event('change'));await flush();course.value='102';course.dispatchEvent(new w.Event('change'));await flush();heldOldCourse();await flush();
  field('text','payer').value='teacher';field('text','payer').dispatchEvent(new w.Event('change'));field('text','credential').value='100';$('ai-billing').elements.confirmed.checked=true;submit($('ai-billing'));await flush();
  const last=requests.findLast(r=>r.url.endsWith('/ai/billing')&&r.options.method==='PUT');
  check(last.url.includes('/102/')&&last.body.expected_revision===222,'late course response cannot overwrite current selection or revision');
  check(errors.length===0,'UI has no DOM runtime errors');
  console.log(`BYOK DOM checks passed: ${checks}`);w.close();
})().catch(error=>{console.error(error);process.exitCode=1;w.close();});
