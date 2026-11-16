(() => {
  'use strict';
  const form = document.getElementById('certificate-design-form');
  const sheet = document.querySelector('.cert-design-preview .certificate-sheet');
  if (!form || !sheet) return;
  const controls = form.elements;
  const image = sheet.querySelector('.cert-signature-image img');
  const file = controls.signature;
  const error = document.getElementById('cert-file-error');
  const unsaved = document.getElementById('cert-unsaved');
  const emblemImage = sheet.querySelector('.cert-sheet-emblem img');
  const emblemIcon = sheet.querySelector('.cert-default-emblem');
  const emblemError = document.getElementById('cert-emblem-error');
  const emblemStatus = document.getElementById('cert-emblem-status');
  const loadEmblem = document.getElementById('cert-load-emblem');
  let uploadedEmblem = '', remoteEmblem = '', emblemFileVersion = 0, remoteVersion = 0;
  let remoteRequest, loadingEmblem = false;
  let signature = image.dataset.savedSignature;
  let fileVersion = 0;
  const update = () => {
    sheet.querySelectorAll('[data-cert-text]').forEach(element => {
      const key = element.dataset.certText;
      element.textContent = controls[key].value.trim() || (key === 'director_name' ? 'ชื่อ–นามสกุลผู้อำนวยการ' : '');
    });
    sheet.classList.remove('cert-style-classic', 'cert-style-modern', 'cert-style-minimal');
    sheet.classList.add('cert-style-' + controls.style.value);
    sheet.style.setProperty('--cert-accent', controls.accent.value);
    sheet.style.setProperty('--signature-width', controls.signature_width.value + 'cqw');
    const signer = sheet.querySelector('.cert-sheet-signature');
    signer.classList.remove('cert-signature-left', 'cert-signature-center', 'cert-signature-right');
    signer.classList.add('cert-signature-' + controls.signature_alignment.value);
    sheet.querySelector('.cert-sheet-hours').hidden = !controls.show_hours.checked;
    document.getElementById('cert-signature-size').textContent = controls.signature_width.value + '%';
    const source = controls.remove_signature?.checked ? '' : signature;
    image.hidden = !source;
    if (source) image.src = source; else image.removeAttribute('src');
    const mode = controls.emblem_source.value;
    sheet.style.setProperty('--emblem-size', controls.emblem_size.value + 'cqw');
    sheet.classList.toggle('cert-large-emblem', Number(controls.emblem_size.value) > 6);
    document.getElementById('cert-emblem-size-label').textContent = controls.emblem_size.value + '%';
    form.querySelectorAll('[data-emblem-source]').forEach(group => {
      const visible = group.dataset.emblemSource === mode;
      group.hidden = !visible;
      group.querySelectorAll('input,button').forEach(control => { control.disabled = !visible; });
    });
    controls.emblem.required = mode === 'upload';
    controls.emblem_url.required = mode === 'url';
    loadEmblem.disabled = mode !== 'url' || loadingEmblem;
    const emblem = mode === 'default' ? '' : (mode === 'upload' ? uploadedEmblem : mode === 'url' ? remoteEmblem : '') || emblemImage.dataset.savedEmblem;
    emblemImage.hidden = !emblem;
    emblemIcon.hidden = Boolean(emblem);
    if (emblem) emblemImage.src = emblem; else emblemImage.removeAttribute('src');
    document.dispatchEvent(new Event('certificate:changed'));
  };
  form.addEventListener('input', () => { unsaved.hidden = false; update(); });
  form.addEventListener('change', () => { unsaved.hidden = false; update(); });
  form.querySelectorAll('[data-cert-color]').forEach(button => button.addEventListener('click', () => {
    controls.accent.value = button.dataset.certColor;
    controls.accent.dispatchEvent(new Event('input', {bubbles: true}));
  }));
  file.addEventListener('change', () => {
    const version = ++fileVersion;
    const selected = file.files[0];
    error.hidden = true;
    file.setCustomValidity('');
    signature = image.dataset.savedSignature;
    if (!selected) { update(); return; }
    const fail = message => {
      if (version !== fileVersion) return;
      error.textContent = message; error.hidden = false;
      file.setCustomValidity(message); signature = image.dataset.savedSignature; update();
    };
    if (selected.size > 2097152 || !['image/png', 'image/jpeg', 'image/webp'].includes(selected.type)) {
      fail('กรุณาเลือกภาพ PNG, JPG หรือ WebP ขนาดไม่เกิน 2 MB'); return;
    }
    const reader = new FileReader();
    reader.onerror = () => fail('อ่านภาพลายเซ็นไม่สำเร็จ กรุณาเลือกไฟล์ใหม่');
    reader.onload = () => {
      if (version !== fileVersion) return;
      const preview = new Image();
      preview.onerror = () => fail('ไฟล์นี้ไม่ใช่ภาพลายเซ็นที่อ่านได้');
      preview.onload = () => {
        if (version !== fileVersion) return;
        if (preview.naturalWidth * preview.naturalHeight > 8000000) { fail('ภาพลายเซ็นต้องไม่เกิน 8 ล้านพิกเซล'); return; }
        signature = reader.result;
        if (controls.remove_signature) controls.remove_signature.checked = false;
        update();
      };
      preview.src = reader.result;
    };
    reader.readAsDataURL(selected);
  });
  controls.remove_signature?.addEventListener('change', () => {
    if (controls.remove_signature.checked) { file.value = ''; fileVersion++; file.setCustomValidity(''); error.hidden = true; signature = image.dataset.savedSignature; }
    update();
  });
  controls.emblem_source.addEventListener('change', () => {
    remoteRequest?.abort(); remoteVersion++; loadingEmblem = false;
    emblemError.hidden = true; emblemStatus.hidden = true;
    update();
  });
  controls.emblem.addEventListener('change', () => {
    const version = ++emblemFileVersion;
    const selected = controls.emblem.files[0];
    uploadedEmblem = ''; emblemError.hidden = true;
    controls.emblem.setCustomValidity('');
    const fail = message => {
      if (version !== emblemFileVersion) return;
      emblemError.textContent = message; emblemError.hidden = false;
      controls.emblem.setCustomValidity(message); uploadedEmblem = ''; update();
    };
    if (!selected) { update(); return; }
    if (selected.size > 2097152 || !['image/png', 'image/jpeg', 'image/webp'].includes(selected.type)) {
      fail('กรุณาเลือกภาพ PNG, JPG หรือ WebP ขนาดไม่เกิน 2 MB'); return;
    }
    const reader = new FileReader();
    reader.onerror = () => fail('อ่านภาพตราไม่สำเร็จ กรุณาเลือกไฟล์ใหม่');
    reader.onload = () => {
      if (version !== emblemFileVersion) return;
      const preview = new Image();
      preview.onerror = () => fail('ไฟล์นี้ไม่ใช่ภาพตราที่อ่านได้');
      preview.onload = () => {
        if (version !== emblemFileVersion) return;
        if (preview.naturalWidth * preview.naturalHeight > 8000000) { fail('ภาพตราต้องไม่เกิน 8 ล้านพิกเซล'); return; }
        uploadedEmblem = reader.result; update();
      };
      preview.src = reader.result;
    };
    reader.readAsDataURL(selected);
  });
  controls.emblem_url.addEventListener('input', () => {
    remoteRequest?.abort(); remoteVersion++; loadingEmblem = false; remoteEmblem = '';
    emblemError.hidden = true; emblemStatus.hidden = true; update();
  });
  loadEmblem.addEventListener('click', async () => {
    if (!controls.emblem_url.reportValidity()) return;
    const version = ++remoteVersion;
    remoteRequest?.abort(); remoteRequest = new AbortController();
    const payload = new FormData();
    payload.set('_csrf', controls._csrf.value);
    payload.set('emblem_url', controls.emblem_url.value.trim());
    loadingEmblem = true; emblemError.hidden = true; emblemStatus.hidden = false;
    emblemStatus.textContent = 'กำลังโหลดภาพตรา…'; update();
    try {
      const response = await fetch('/admin/certificate-design/emblem-preview', {method: 'POST', body: payload, signal: remoteRequest.signal});
      const result = await response.json();
      if (version !== remoteVersion) return;
      if (!response.ok) throw new Error(result.error || 'โหลดรูปภาพไม่สำเร็จ');
      remoteEmblem = result.data_url;
      emblemStatus.textContent = 'โหลดภาพตัวอย่างแล้ว กดบันทึกแม่แบบเพื่อนำไปใช้';
    } catch (error) {
      if (version !== remoteVersion || error.name === 'AbortError') return;
      remoteEmblem = ''; emblemStatus.hidden = true;
      emblemError.textContent = error.message === 'Failed to fetch' ? 'เชื่อมต่อไม่สำเร็จ กรุณาลองอีกครั้ง' : error.message;
      emblemError.hidden = false;
    } finally {
      if (version === remoteVersion) { loadingEmblem = false; update(); }
    }
  });
  update();
})();
