(() => {
  'use strict';
  const $ = id => document.getElementById(id), form = $('ai-provider-form');
  const caps = {text:'เนื้อหา/ข้อสอบ',embedding:'ดัชนีความรู้',speech:'เสียง',analytics:'วิเคราะห์ผล',assistant:'ผู้ช่วยครู'};
  let revision = 0, editing = null;
  // Model examples checked against official API docs on 2026-11-03; never auto-save catalog changes.
  const examples = {
    openai:{model:'gpt-5.4-mini',url:'https://developers.openai.com/api/docs/models/gpt-5.4-mini'},
    anthropic:{model:'claude-haiku-5-5',url:'https://platform.claude.com/docs/en/models/haiku-5-5/overview'},
    gemini:{model:'gemini-3.5-flash-lite',url:'https://ai.google.dev/gemini-api/docs/models/gemini-3.5-flash-lite'}
  };
  function sample() {
    const template = $('ai-provider-template').value;
    const known = examples[form.elements.slug.value.trim()];
    // Compatible gateways can require their own IDs: only suggest examples for official endpoints.
    const endpoints = {openai:'https://api.openai.com/v1',anthropic:'https://api.anthropic.com/v1',gemini:'https://generativelanguage.googleapis.com/v1beta'};
    const provider = known ? form.elements.slug.value.trim() : template;
    return endpoints[provider] === form.elements.base_url.value.trim().replace(/\/$/,'') ? examples[provider] : null;
  }
  function updateExamples() {
    const current = sample(), hint = $('ai-provider-example-hint'); hint.replaceChildren();
    $('ai-provider-examples').disabled = !current;
    form.elements.models_text.placeholder = current ? current.model : 'รหัสโมเดลข้อความจากผู้ให้บริการ';
    if (!current) { hint.textContent = 'ผู้ให้บริการที่กำหนดเอง: ใช้ model ID ตามเอกสารหรือรายการ models ของปลายทางนั้น'; return; }
    hint.append(node('span', 'ตัวอย่างช่องเนื้อหาและข้อสอบ: ' + current.model + ' · ช่องอื่นเว้นว่างได้ · '));
    const link = node('a','เอกสารโมเดล'); link.href = current.url; link.target = '_blank'; link.rel = 'noopener noreferrer'; hint.append(link);
  }
  const node = (tag,text) => {const n = document.createElement(tag); if (text !== undefined) n.textContent = text; return n;};
  const feedback = (message,error = false) => { $('ai-providers-feedback').textContent = message; $('ai-providers-feedback').dataset.error = String(error); };
  async function api(path,method = 'GET',body) {
    const headers = {Accept:'application/json'};
    if (method !== 'GET') {headers['Content-Type'] = 'application/json'; headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]').content;}
    const r = await fetch('/api/v1'+path,{method,headers,credentials:'same-origin',cache:'no-store',body:body === undefined ? undefined : JSON.stringify(body)});
    let data;try {data = await r.json();} catch {throw new Error('เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง');}
    if (!r.ok) throw new Error(data.error?.message || 'ดำเนินการไม่สำเร็จ');return data.data;
  }
  function reset() { form.reset(); editing = null; revision = 0; form.elements.slug.readOnly = false; $('ai-provider-template').disabled = false; $('ai-provider-form-title').textContent = 'เพิ่มผู้ให้บริการ'; updateExamples(); }
  function edit(p) {
    reset(); editing = p.slug; revision = p.revision;
    for (const key of ['slug','name','adapter','base_url']) form.elements[key].value = p[key];
    form.elements.slug.readOnly = true; $('ai-provider-template').disabled = true; form.elements.enabled.checked = p.enabled;
    for (const cap of Object.keys(caps)) form.elements['models_'+cap].value = (p.models[cap] || []).join('\n');
    $('ai-provider-form-title').textContent = 'แก้ไข '+p.name; form.elements.name.focus(); updateExamples();
  }
  function payload(p) {return {slug:p.slug,name:p.name,adapter:p.adapter,base_url:p.base_url,models:p.models,enabled:p.enabled,expected_revision:p.revision};}
  async function load() {
    const rows = await api('/admin/ai-providers'); $('ai-provider-list').replaceChildren();
    if (!rows.length) $('ai-provider-list').append(node('p','ยังไม่มีผู้ให้บริการ'));
    for (const p of rows) {
      const card = node('div'); card.className = 'ai-credential';
      card.append(node('strong',p.name),node('small',p.slug+' · '+p.adapter+' · รุ่น '+p.revision),node('small',p.base_url),node('p',p.enabled ? 'เปิดใช้งาน' : 'ปิดใช้งาน'));
      for (const [cap,label] of Object.entries(caps)) if (p.models[cap]?.length) card.append(node('small',label+': '+p.models[cap].join(', ')));
      if (p.slug !== 'mock') {
        const buttons = node('div'); buttons.className = 'button-row';
        const e = node('button','แก้ไข');e.type = 'button';e.className = 'button small outline';e.addEventListener('click',()=>edit(p));
        const toggle = node('button',p.enabled ? 'ปิดใช้งาน' : 'เปิดใช้งาน');toggle.type = 'button';toggle.className = 'button small outline';
        toggle.addEventListener('click',async () => {
          toggle.disabled = true;
          try {await api('/admin/ai-providers/'+p.slug,'PUT',{...payload(p),enabled:!p.enabled});if (editing === p.slug) reset(); await load(); feedback('เปลี่ยนสถานะผู้ให้บริการแล้ว');}
          catch (err) {feedback(err.message,true);toggle.disabled = false;}
        });buttons.append(e,toggle);card.append(buttons);
      } $('ai-provider-list').append(card);
    }
  }
  $('ai-provider-template').addEventListener('change',event => {
    const templates = {openai:{slug:'openai',name:'OpenAI',adapter:'openai_compatible',base_url:'https://api.openai.com/v1'},anthropic:{slug:'anthropic',name:'Anthropic (Claude)',adapter:'anthropic',base_url:'https://api.anthropic.com/v1'},gemini:{slug:'gemini',name:'Google Gemini',adapter:'gemini',base_url:'https://generativelanguage.googleapis.com/v1beta'}};
    const t = templates[event.target.value]; if (t) for (const [key,value] of Object.entries(t)) form.elements[key].value = value;
    updateExamples();
  });
  $('ai-provider-examples').addEventListener('click', () => {
    const current = sample(); if (!current) return;
    if (form.elements.models_text.value.trim()) { feedback('ช่องเนื้อหาและข้อสอบมีข้อมูลแล้ว เก็บรายการเดิมไว้ สามารถเพิ่มรหัสตัวอย่างด้วยตัวเองได้'); return; }
    form.elements.models_text.value = current.model;
    feedback('เติมตัวอย่างในช่องเนื้อหาและข้อสอบแล้ว ตรวจ model ID และสิทธิ์บัญชีก่อนกดบันทึกผู้ให้บริการ');
  });
  for (const name of ['slug','base_url']) form.elements[name].addEventListener('input', updateExamples);
  updateExamples();
  $('ai-provider-new').addEventListener('click',reset);
  form.addEventListener('submit',async event => {
    event.preventDefault(); const button = event.submitter;if (button) button.disabled = true;
    try {
      const models = {};for (const cap of Object.keys(caps)) models[cap] = [...new Set(form.elements['models_'+cap].value.split(/[,\n\r]+/).map(m=>m.trim()).filter(Boolean))];
      const body = {slug:form.elements.slug.value.trim(),name:form.elements.name.value.trim(),adapter:form.elements.adapter.value,base_url:form.elements.base_url.value.trim(),models,enabled:form.elements.enabled.checked,expected_revision:revision};
      await api('/admin/ai-providers'+(editing ? '/'+editing : ''),editing ? 'PUT' : 'POST',body); reset();await load();document.dispatchEvent(new Event('ai:providers-updated'));feedback('บันทึกผู้ให้บริการแล้ว ครูเลือกได้จากรายการที่เปิดใช้งาน');
    } catch (err) {feedback(err.message,true);}finally {if (button) button.disabled = false;}
  });
  $('ai-provider-reauth').addEventListener('submit',async event => {
    event.preventDefault();const input = event.currentTarget.elements.password,body = {password:input.value};input.value = '';
    try {await api('/me/ai-reauth','POST',body);feedback('ยืนยันตัวตนแล้ว');}catch (err) {feedback(err.message,true);}finally {body.password = '';}
  });
  load().then(()=>feedback('พร้อมจัดการผู้ให้บริการสำหรับครู')).catch(err=>feedback(err.message,true));
})();
