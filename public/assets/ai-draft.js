(() => {
  'use strict';
  const panel = document.getElementById('ai-draft');
  if (!panel) return;
  const $ = id => document.getElementById(id), form = $('ai-draft-form');
  const cid = panel.dataset.courseId, quiz = Number(panel.dataset.quizId);
  let draft = null, pending = null, saveAttempt = null, busy = false;
  const node = (tag, text) => { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; return el; };
  const key = () => crypto.randomUUID();
  function status(message, error = false) { $('ai-draft-status').textContent = message; $('ai-draft-status').dataset.error = String(error); }
  async function api(path, method = 'GET', body, idem) {
    let response;
    try {
      response = await fetch('/api/v1' + path, {method, credentials:'same-origin', cache:'no-store', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content,...(idem ? {'Idempotency-Key':idem} : {})}, body:body === undefined ? undefined : JSON.stringify(body)});
    } catch { const e = new Error('การเชื่อมต่อขาดหาย ใช้ปุ่มตรวจผลคำขอเดิมเพื่อป้องกันการสร้างซ้ำ'); e.uncertain = true; throw e; }
    let result;
    try { result = await response.json(); } catch { const e = new Error('ยังยืนยันผลไม่ได้ กรุณาตรวจผลคำขอเดิมอีกครั้ง'); e.uncertain = true; throw e; }
    if (!response.ok) throw new Error(result.error?.message || 'ดำเนินการไม่สำเร็จ');
    return result.data;
  }
  function field(parent, title, value, type = 'textarea') {
    const label = node('label', title), input = node(type); input.value = value;
    if (type === 'textarea') { input.rows = 4; input.maxLength = 12000; }
    label.append(input); parent.append(label); return input;
  }
  function render(result) {
    draft = result; saveAttempt = null;
    const container = $('ai-draft-fields'); container.replaceChildren();
    if (result.kind === 'quiz') {
      for (const [i, q] of result.questions.entries()) {
        const box = node('div'); box.className = 'ai-question';
        const label = node('label', `เลือกข้อ ${i + 1}`), pick = node('input'); pick.type = 'checkbox'; pick.checked = true; pick.dataset.pick = '';
        label.prepend(pick); box.append(label);
        field(box, 'คำถาม', q.prompt).dataset.field = 'prompt';
        q.options.forEach((option, j) => { field(box, `ตัวเลือก ${['ก','ข','ค','ง'][j]}`, option).dataset.option = String(j); });
        const answer = field(box, 'คำตอบที่ถูกต้อง', '', 'select'); answer.dataset.field = 'answer';
        ['ก','ข','ค','ง'].forEach((text, j) => { const opt = node('option', text); opt.value = String(j); answer.append(opt); }); answer.value = String(q.answer);
        field(box, 'คำอธิบายเฉลย', q.explanation).dataset.field = 'explanation';
        container.append(box);
      }
    } else {
      field(container, 'ชื่อกิจกรรม', result.title, 'input').id = 'ai-draft-title'; $('ai-draft-title').maxLength = 240;
      field(container, 'เนื้อหา / คำชี้แจง', result.body).id = 'ai-draft-body'; $('ai-draft-body').maxLength = 100000; $('ai-draft-body').rows = 16;
    }
    $('ai-draft-preview').hidden = false;
  }
  async function generate(retry = false) {
    if (busy || saveAttempt) return;
    if (!retry) {
      if (draft && !confirm('สร้างฉบับร่างใหม่แทนผลที่กำลังแก้ไข? การสร้างใหม่มีค่าใช้จ่าย')) return;
      const body = Object.fromEntries(new FormData(form));
      for (const name of ['count','minutes','reference_lesson_id']) body[name] = Number(body[name]);
      pending = {body, key:key()};
    }
    if (!pending) return;
    busy = true; $('ai-draft-generate').disabled = true; $('ai-draft-retry').hidden = true; $('ai-draft-apply').disabled = true;
    status('กำลังสร้างฉบับร่าง อาจใช้เวลาประมาณ 45 วินาที…');
    try {
      const result = await api(`/courses/${cid}/ai/drafts`, 'POST', pending.body, pending.key);
      render(result); pending = null; status('สร้างฉบับร่างแล้ว กรุณาตรวจและแก้ไขก่อนบันทึก');
    } catch (e) { status(e.message, true); if (e.uncertain) $('ai-draft-retry').hidden = false; else pending = null; }
    finally { busy = false; $('ai-draft-generate').disabled = Boolean(pending); $('ai-draft-apply').disabled = false; }
  }
  function updateKind() { $('ai-draft-mode').hidden = form.elements.kind.value !== 'assignment'; }
  form.elements.kind.addEventListener('change', updateKind); updateKind();
  form.addEventListener('submit', event => { event.preventDefault(); void generate(); });
  $('ai-draft-retry').addEventListener('click', () => void generate(true));
  $('ai-draft-apply').addEventListener('click', async () => {
    if (!draft || busy) return;
    if (draft.kind !== 'quiz') {
      const builder = $('lesson-builder'), body = $('lesson-body');
      if ((builder.elements.title.value.trim() || (window.LmsRichEditor?.hasContent(body) ?? body.value.trim())) && !confirm('แทนที่ชื่อและเนื้อหาในแบบฟอร์มด้วยฉบับร่างที่ตรวจแล้ว?')) return;
      builder.elements.kind.value = draft.kind; builder.elements.kind.dispatchEvent(new Event('change', {bubbles:true}));
      builder.elements.title.value = $('ai-draft-title').value;
      if (window.LmsRichEditor) window.LmsRichEditor.setText(body, $('ai-draft-body').value); else body.value = $('ai-draft-body').value;
      status('นำไปใส่ในแบบฟอร์มแล้ว ตรวจค่าอื่นและกด “เพิ่มกิจกรรม” เพื่อบันทึก'); builder.scrollIntoView({behavior:'smooth'}); return;
    }
    const questions = [...$('ai-draft-fields').querySelectorAll('.ai-question')].filter(box => box.querySelector('[data-pick]').checked).map(box => ({prompt:box.querySelector('[data-field="prompt"]').value, options:[...box.querySelectorAll('[data-option]')].map(el => el.value), answer:Number(box.querySelector('[data-field="answer"]').value), explanation:box.querySelector('[data-field="explanation"]').value, content_format:'text'}));
    if (!questions.length) { status('กรุณาเลือกข้อสอบอย่างน้อย 1 ข้อ', true); return; }
    const body = {questions}, hash = JSON.stringify(body);
    if (saveAttempt && saveAttempt.hash !== hash) { status('มีคำขอบันทึกที่ยังยืนยันผลไม่ได้ กรุณากลับไปตรวจคลังข้อสอบก่อนบันทึกชุดใหม่', true); return; }
    saveAttempt ||= {hash, key:key()};
    busy = true; $('ai-draft-apply').disabled = true; $('ai-draft-generate').disabled = true;
    try {
      const result = await api(`/quizzes/${quiz}/ai-questions`, 'POST', body, saveAttempt.key);
      status(`บันทึกข้อสอบ ${result.added} ข้อแล้ว`); location.reload();
    } catch (e) { if (!e.uncertain) saveAttempt = null; status(e.message, true); }
    finally { busy = false; $('ai-draft-apply').disabled = false; $('ai-draft-generate').disabled = Boolean(pending) || Boolean(saveAttempt); }
  });
  panel.querySelector('details').addEventListener('toggle', async event => {
    if (!event.target.open) return;
    if (!quiz && !form.elements.topic.value) form.elements.topic.value = $('lesson-builder').elements.title.value;
    try {
      const config = await api(`/courses/${cid}/ai/drafts`);
      $('ai-draft-connection').textContent = `${config.provider} · ${config.model} · จองงบสูงสุด $${config.max_request_usd.toFixed(6)} ต่อครั้ง (USD) ตามอัตราที่ผู้ดูแลตั้งไว้`;
      $('ai-draft-generate').disabled = busy || Boolean(pending) || Boolean(saveAttempt);
    } catch (e) { $('ai-draft-connection').textContent = e.message; $('ai-draft-generate').disabled = true; }
  });
})();
