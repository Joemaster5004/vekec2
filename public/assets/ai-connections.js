(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  let credentials = [], capabilities = {}, revision = 0, selectedCourse = '', loadSequence = 0;
  const labels = {text:'เนื้อหาและข้อสอบ',embedding:'ดัชนีความรู้',speech:'เสียงบรรยาย',analytics:'วิเคราะห์ผล',assistant:'ผู้ช่วยงานสอนสำหรับครู'};
  function notice(message, error = false) { $('ai-feedback').textContent = message; $('ai-feedback').dataset.error = String(error); }
  function node(tag, text) { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; return el; }
  function option(select, value, text) { const el = node('option', text); el.value = value; select.append(el); }
  async function api(path, method = 'GET', body, idempotencyKey) {
    const headers = {'Accept':'application/json'};
    if (method !== 'GET') {
      headers['Content-Type'] = 'application/json';
      headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]').content;
      if (method === 'POST' && !path.endsWith('ai-reauth')) headers['Idempotency-Key'] = idempotencyKey || crypto.randomUUID();
    }
    const res = await fetch('/api/v1' + path, {method, headers, credentials:'same-origin', cache:'no-store', body:body === undefined ? undefined : JSON.stringify(body)});
    let data; try { data = await res.json(); } catch { throw new Error('เซิร์ฟเวอร์ส่งคำตอบไม่ถูกต้อง กรุณาลองใหม่'); }
    if (!res.ok) throw new Error(data.error?.message || 'ดำเนินการไม่สำเร็จ');
    return data.data;
  }
  function bindForm(id, fn) {
    $(id).addEventListener('submit', async event => {
      event.preventDefault(); const button = event.submitter; if (button) button.disabled = true;
      try { await fn(event.currentTarget); } catch (err) { notice(err.message, true); }
      finally { if (button) button.disabled = false; }
    });
  }
  async function loadCredentials() {
    credentials = await api('/me/ai-credentials');
    const list = $('ai-credentials'); list.replaceChildren();
    const rotate = $('ai-rotate').elements.credential_id; rotate.replaceChildren();
    if (!credentials.length) list.append(node('p','ยังไม่มีการเชื่อมต่อ'));
    const statuses = {verified:'ทดสอบผ่าน',unverified:'ยังไม่ทดสอบ',invalid:'ตรวจการเชื่อมต่อไม่ผ่าน',revoked:'ปิดใช้งานแล้ว',provider_disabled:'ผู้ดูแลปิดผู้ให้บริการ',provider_changed:'ผู้ให้บริการเปลี่ยน ต้องตรวจปลายทางและทดสอบใหม่'};
    for (const c of credentials) {
      const card = node('div'); card.className = 'ai-credential';
      card.append(node('strong',c.label), node('small',`${c.provider_name || c.provider} · ลงท้าย ${c.key_hint} · รุ่น ${c.version}`),node('p',statuses[c.status] || c.status),node('small',c.provider_base_url || ''));
      if (!['revoked','provider_disabled'].includes(c.status)) {
        option(rotate,c.credential_id,c.label);
        const row = node('div'); row.className = 'button-row';
        for (const [action, title] of [['verify','ทดสอบการเชื่อมต่อ'],['revoke','ปิดใช้งานใน LMS']]) {
          const button = node('button',title); button.type = 'button'; button.className = 'button small outline';
          button.addEventListener('click', async () => {
            if (action === 'revoke' && !confirm(`ปิดใช้ ${c.label} ใน LMS? การเพิกถอนที่ผู้ให้บริการต้องทำแยกกัน`)) return;
            if (action === 'verify' && !confirm(`ส่ง API key ของคุณไปทดสอบกับ ${c.provider_name || c.provider} ที่ ${c.provider_base_url} (การตั้งค่ารุ่น ${c.provider_current_revision})?`)) return;
            button.disabled = true;
            try {
              await api(`/me/ai-credentials/${c.credential_id}/${action}`,'POST',action === 'verify' ? {allow_billable_test:false,confirmed_provider_revision:c.provider_current_revision} : {});
              notice(action === 'verify' ? 'ทดสอบการเชื่อมต่อผ่านแล้ว ไม่ได้ทดสอบสร้างผลลัพธ์หรือทุกโมเดล' : 'ปิดใช้งานใน LMS แล้ว หากเลิกใช้ให้เพิกถอน key ที่ผู้ให้บริการด้วย');
              await loadCredentials(); if (selectedCourse) await loadCourse(selectedCourse);
            } catch (err) { notice(err.message,true); button.disabled = false; }
          }); row.append(button);
        } card.append(row);
      } list.append(card);
    }
  }
  async function loadCourse(cid) {
    const sequence = ++loadSequence; selectedCourse = cid;
    $('ai-billing').hidden = true; $('ai-binding-rows').replaceChildren(); $('ai-usage').replaceChildren();
    if (!cid) return;
    try {
      const [config, usage] = await Promise.all([api(`/courses/${cid}/ai/billing`),api(`/courses/${cid}/ai/usage`)]);
      if (sequence !== loadSequence) return;
      revision = config.revision;
      for (const [cap, label] of Object.entries(labels)) {
        const current = config.bindings.find(b => b.capability === cap);
        const tr = node('tr'); tr.dataset.capability = cap; tr.append(node('td',label));
        const payer = node('select'); payer.dataset.field = 'payer'; payer.setAttribute('aria-label',label+' แหล่งจ่าย');
        option(payer,'off','ปิดการใช้'); option(payer,'teacher','key ของฉัน'); if (capabilities.institution_available) option(payer,'institution','ส่วนกลาง');
        if (current?.payer === 'institution' && !capabilities.institution_available) option(payer,'institution','ส่วนกลาง (ยังไม่มีสิทธิ์เปลี่ยน)');
        payer.value = current?.payer || 'off';
        const credential = node('select'); credential.dataset.field = 'credential'; credential.setAttribute('aria-label',label+' การเชื่อมต่อ');
        option(credential,'','เลือก key ที่ทดสอบแล้ว');
        for (const c of credentials.filter(c => c.status === 'verified')) option(credential,c.credential_id,c.label);
        if (current?.credential_id && !credentials.some(c => c.status === 'verified' && c.credential_id === current.credential_id)) option(credential,current.credential_id,'key เดิมใช้ไม่ได้ ต้องเลือกใหม่');
        credential.value = current?.credential_id || '';
        const model = node('select'); model.dataset.field = 'model'; model.setAttribute('aria-label',label+' โมเดล');
        const budget = node('input'); budget.type = 'number'; budget.min = '0.01'; budget.max = '10000'; budget.step = '0.01'; budget.value = current?.monthly_budget || '5'; budget.dataset.field = 'budget'; budget.setAttribute('aria-label',label+' งบ USD ต่อเดือน');
        for (const input of [payer,credential,model,budget]) {const td = node('td'); td.append(input); tr.append(td);}
        function changed(initial = false) {
          const previous = initial && current ? current.model : model.value;
          const key = credentials.find(c => c.credential_id === credential.value);
          const slug = payer.value === 'institution' ? capabilities.institution_provider : key?.provider;
          const provider = capabilities.provider_catalog?.find(p => p.slug === slug);
          model.replaceChildren();
          for (const m of provider?.models?.[cap] || []) option(model,m,m);
          if (!model.options.length) option(model,'','ผู้ให้บริการนี้ยังไม่มีโมเดลสำหรับฟังก์ชันนี้');
          if (Array.from(model.options).some(o => o.value === previous)) model.value = previous;
          credential.disabled = payer.value !== 'teacher'; model.disabled = budget.disabled = payer.value === 'off';
        }
        payer.addEventListener('change',() => changed()); credential.addEventListener('change',() => changed()); changed(true); $('ai-binding-rows').append(tr);
      }
      $('ai-billing').elements.confirmed.checked = false; $('ai-billing').hidden = false;
      $('ai-usage').append(node('h3','ประวัติการใช้ผ่าน LMS'));
      if (!usage.length) $('ai-usage').append(node('p','ยังไม่มีการเรียกใช้ผ่านระบบจองงบของ LMS'));
      for (const row of usage) $('ai-usage').append(node('p',`${row.period} · ${labels[row.capability] || row.capability} · ${row.payer === 'teacher' ? 'key ครู' : 'ส่วนกลาง'} · ${row.model} · ${row.requests} งาน · ยอดใช้รวมยอดจอง ${row.accounted_usd} USD`));
    } catch (err) { if (sequence === loadSequence) notice(err.message,true); }
  }
  bindForm('ai-create',async form => {
    const body = {provider:form.elements.provider.value,label:form.elements.label.value,secret:form.elements.secret.value};
    form.elements.secret.value = '';
    try { await api('/me/ai-credentials','POST',body); } finally { body.secret = ''; }
    form.elements.label.value = ''; await loadCredentials(); if (selectedCourse) await loadCourse(selectedCourse);
    notice('บันทึกการเชื่อมต่อแล้ว กดทดสอบก่อนนำไปผูกกับรายวิชา');
  });
  bindForm('ai-rotate',async form => {
    const id = form.elements.credential_id.value; const c = credentials.find(c => c.credential_id === id);
    if (!c) throw new Error('กรุณาเลือกการเชื่อมต่อ');
    const body = {expected_version:c.version,secret:form.elements.secret.value}; form.elements.secret.value = '';
    try { await api(`/me/ai-credentials/${id}/secret`,'PUT',body); } finally { body.secret = ''; }
    await loadCredentials(); if (selectedCourse) await loadCourse(selectedCourse); notice('เปลี่ยน key แล้ว กรุณาทดสอบการเชื่อมต่อใหม่');
  });
  bindForm('ai-reauth',async form => {
    const body = {password:form.elements.password.value}; form.elements.password.value = '';
    try { await api('/me/ai-reauth','POST',body); } finally { body.password = ''; }
    notice('ยืนยันตัวตนแล้ว สามารถแก้ไขการตั้งค่าได้ภายใน 15 นาที');
  });
  bindForm('ai-billing',async form => {
    if (!selectedCourse) throw new Error('กรุณาเลือกรายวิชา');
    const bindings = [];
    for (const row of $('ai-binding-rows').rows) {
      const field = key => row.querySelector(`[data-field="${key}"]`).value;
      if (field('payer') === 'off') continue;
      const b = {capability:row.dataset.capability,payer:field('payer'),model:field('model'),monthly_budget:Number(field('budget')),currency:'USD'};
      if (b.payer === 'teacher') b.credential_id = field('credential');
      bindings.push(b);
    }
    const cid = selectedCourse;
    await api(`/courses/${cid}/ai/billing`,'PUT',{expected_revision:revision,bindings,confirmed_charge_acceptance:form.elements.confirmed.checked});
    await loadCourse(cid); notice('บันทึกแหล่งจ่ายและวงเงินสำหรับครูแล้ว');
  });
  $('ai-course').addEventListener('change',event => loadCourse(event.target.value));
  (async () => {
    try {
      capabilities = await api('/me/ai-capabilities');
      const provider = $('ai-create').elements.provider; provider.replaceChildren();
      for (const p of capabilities.provider_catalog || []) option(provider,p.slug,p.name);
      if (!provider.options.length) option(provider,'','ยังไม่มีผู้ให้บริการที่เปิดใช้');
      await loadCredentials();
      notice(!capabilities.encryption_ready ? 'ผู้ดูแลต้องตั้งกุญแจเข้ารหัสก่อนเพิ่มการเชื่อมต่อ' : capabilities.reauth_required ? 'พร้อมใช้งาน กรุณายืนยันตัวตนอีกครั้งก่อนเปลี่ยนการตั้งค่า' : 'พร้อมตั้งค่าการเชื่อมต่อ AI ของคุณ');
    } catch (err) { notice(err.message,true); }
  })();
})();
