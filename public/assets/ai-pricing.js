(() => {
  'use strict';
  const form = document.getElementById('ai-pricing-form'); if (!form) return;
  const list = document.getElementById('ai-pricing-rows'), feedback = document.getElementById('ai-pricing-feedback');
  let revision = 0, sequence = 0, saving = false;
  const node = (tag, text) => { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; return el; };
  function notice(message, error = false) { feedback.textContent = message; feedback.dataset.error = String(error); }
  async function api(method = 'GET', body) {
    const response = await fetch('/api/v1/admin/ai-providers/pricing', {method,credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content},body:body === undefined ? undefined : JSON.stringify(body)});
    const result = await response.json(); if (!response.ok) throw new Error(result.error?.message || 'อ่านหรือบันทึกราคาไม่สำเร็จ'); return result.data;
  }
  function render(data) {
    revision = data.revision; list.replaceChildren();
    for (const row of data.rows) {
      const tr = node('tr'); tr.dataset.provider = row.provider; tr.dataset.model = row.model;
      const identity = node('td'); identity.append(node('strong',row.provider_name),node('p',row.model)); tr.append(identity);
      for (const field of ['input','output']) {
        const td = node('td'), input = node('input'); input.type = 'number'; input.min = '0'; input.max = '10000'; input.step = 'any'; input.dataset.field = field;
        input.setAttribute('aria-label', `${row.provider_name} ${row.model} ${field} USD ต่อหนึ่งล้าน token`);
        input.value = row[field] === null ? '' : String(row[field]); input.placeholder = 'ยังไม่กำหนด'; td.append(input); tr.append(td);
      }
      tr.append(node('td',row.configured ? 'พร้อมคำนวณวงเงิน' : 'ยังสร้างงานไม่ได้: ขาดราคา')); list.append(tr);
    }
    if (!data.rows.length) { const tr = node('tr'), td = node('td','เพิ่มผู้ให้บริการและโมเดลในช่องเนื้อหาและข้อสอบก่อน'); td.colSpan = 4; tr.append(td); list.append(tr); }
  }
  async function load() {
    const current = ++sequence;
    try { const data = await api(); if (current === sequence) { render(data); notice('กำหนดราคาให้ครบทั้ง Input และ Output ของโมเดลที่ต้องการใช้งาน'); } }
    catch (e) { if (current === sequence) notice(e.message,true); }
  }
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (saving) return;
    const rows = [];
    for (const tr of list.querySelectorAll('tr[data-model]')) {
      const input = tr.querySelector('[data-field="input"]'), output = tr.querySelector('[data-field="output"]');
      if (input.value === '' && output.value === '') continue;
      if (input.value === '' || output.value === '') { notice('กรุณากรอกทั้ง Input และ Output สำหรับ '+tr.dataset.model,true); return; }
      rows.push({provider:tr.dataset.provider,model:tr.dataset.model,input:Number(input.value),output:Number(output.value)});
    }
    if (!rows.length) { notice('กรุณากำหนดราคาอย่างน้อยหนึ่งโมเดล',true); return; }
    saving = true; const button = form.querySelector('button[type="submit"]'); button.disabled = true;
    try { const data = await api('PUT',{expected_revision:revision,rows}); render(data); notice('บันทึกราคาแล้ว ครูรีเฟรชหน้าสร้างเนื้อหาเพื่อใช้งานได้ทันที'); }
    catch (e) { notice(e.message,true); }
    finally { saving = false; button.disabled = false; }
  });
  document.addEventListener('ai:providers-updated', () => void load()); void load();
})();
