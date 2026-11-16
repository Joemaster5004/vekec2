/* Quill 2.0.3; all images are uploaded to this course before insertion. */
(() => {
  'use strict';
  if (!window.Quill) return;
  const imagePattern = /^\/media\/images\/[a-f0-9]{40}\.(jpg|png|webp)$/;
  const imagePath = value => {
    try { const url = new URL(value, location.origin); return url.origin === location.origin && !url.search && !url.hash && imagePattern.test(url.pathname) ? url.pathname : ''; }
    catch { return ''; }
  };
  const Delta = Quill.import('delta');
  const Image = Quill.import('formats/image');
  class CourseImage extends Image {
    static sanitize(value) { return imagePath(value) || '//:0'; }
    static formats(node) { return node.hasAttribute('alt') ? { alt: node.getAttribute('alt') } : {}; }
  }
  Quill.register(CourseImage, true);
  const editors = new Map();
  const toolbarHTML = `
    <span class="ql-formats"><select class="ql-header" aria-label="รูปแบบหัวข้อ"><option selected>ข้อความปกติ</option><option value="1">หัวข้อใหญ่</option><option value="2">หัวข้อกลาง</option><option value="3">หัวข้อย่อย</option></select></span>
    <span class="ql-formats"><button type="button" class="ql-bold" title="ตัวหนา" aria-label="ตัวหนา"></button><button type="button" class="ql-italic" title="ตัวเอียง" aria-label="ตัวเอียง"></button><button type="button" class="ql-underline" title="ขีดเส้นใต้" aria-label="ขีดเส้นใต้"></button><button type="button" class="ql-strike" title="ขีดทับ" aria-label="ขีดทับ"></button></span>
    <span class="ql-formats"><select class="ql-color" aria-label="สีข้อความ"></select><select class="ql-background" aria-label="สีพื้นหลังข้อความ"></select></span>
    <span class="ql-formats"><button type="button" class="ql-list" value="ordered" title="รายการตัวเลข" aria-label="รายการตัวเลข"></button><button type="button" class="ql-list" value="bullet" title="รายการหัวข้อ" aria-label="รายการหัวข้อ"></button><select class="ql-align" aria-label="จัดแนวข้อความ"></select></span>
    <span class="ql-formats"><button type="button" class="ql-script" value="sub" title="ตัวห้อย" aria-label="ตัวห้อย"></button><button type="button" class="ql-script" value="super" title="ตัวยก" aria-label="ตัวยก"></button><button type="button" class="ql-blockquote" title="ข้อความอ้างอิง" aria-label="ข้อความอ้างอิง"></button></span>
    <span class="ql-formats"><button type="button" class="ql-link" title="เพิ่มหรือแก้ไขลิงก์" aria-label="เพิ่มหรือแก้ไขลิงก์"></button><button type="button" class="ql-image" title="แทรกรูปภาพจากเครื่อง" aria-label="แทรกรูปภาพจากเครื่อง"></button><button type="button" class="ql-imageAlt rich-alt-button" title="เลือกภาพแล้วเพิ่มคำอธิบายภาพ" aria-label="คำอธิบายภาพ">Alt</button></span>
    <span class="ql-formats"><button type="button" class="ql-undo" title="เลิกทำ" aria-label="เลิกทำ">↶</button><button type="button" class="ql-redo" title="ทำซ้ำ" aria-label="ทำซ้ำ">↷</button><button type="button" class="ql-clean" title="ล้างรูปแบบ" aria-label="ล้างรูปแบบ"></button></span>`;

  document.querySelectorAll('textarea.rich-editor').forEach(textarea => {
    const form = textarea.form;
    const wrapper = document.createElement('div');
    wrapper.className = 'rich-editor-shell';
    const toolbar = document.createElement('div'); toolbar.className = 'rich-toolbar'; toolbar.innerHTML = toolbarHTML;
    toolbar.setAttribute('role', 'toolbar'); toolbar.setAttribute('aria-label', 'เครื่องมือจัดรูปแบบ ' + (textarea.labels[0]?.textContent || 'เนื้อหา'));
    const container = document.createElement('div');
    const status = document.createElement('p'); status.className = 'rich-editor-status'; status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite'); status.id = textarea.id + '-status';
    const picker = document.createElement('input'); picker.type = 'file'; picker.accept = 'image/jpeg,image/png,image/webp'; picker.hidden = true; picker.tabIndex = -1;
    wrapper.append(toolbar, container, status, picker); textarea.after(wrapper);
    const state = { textarea, form, pending: 0, required: textarea.required, selectedImage: null, range: null };
    let quill;
    const message = (text, error = false) => { status.textContent = text; status.classList.toggle('is-error', error); };
    const sync = () => { textarea.value = quill.getSemanticHTML(); };
    const selectImage = event => { if (event.target.tagName === 'IMG') state.selectedImage = event.target; else state.selectedImage = null; };
    const describeImage = () => {
      const node = state.selectedImage;
      if (!node || !quill.root.contains(node)) { message('คลิกรูปในเนื้อหาก่อน แล้วกด Alt เพื่อใส่คำอธิบายภาพ', true); return; }
      const alt = window.prompt('คำอธิบายภาพสำหรับผู้ใช้โปรแกรมอ่านหน้าจอ', node.getAttribute('alt') || '');
      if (alt === null) return;
      quill.formatText(quill.getIndex(Quill.find(node)), 1, 'alt', alt.slice(0, 200), 'user'); sync(); message('บันทึกคำอธิบายภาพแล้ว');
    };
    const upload = async files => {
      for (const file of files) {
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024 || !file.size) { message('เลือกรูป JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB', true); continue; }
        state.pending++; wrapper.setAttribute('aria-busy', 'true'); message('กำลังอัปโหลดรูปภาพ…');
        try {
          const data = new FormData(); data.append('image', file);
          const response = await fetch('/teach/' + textarea.dataset.courseId + '/images', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }, body: data });
          const result = await response.json().catch(() => { throw new Error('เซสชันหมดอายุหรือเซิร์ฟเวอร์ไม่ตอบสนอง กรุณาคัดลอกเนื้อหาเก็บไว้ก่อนโหลดหน้าใหม่'); });
          if (!response.ok) throw new Error(result.error || 'อัปโหลดรูปภาพไม่สำเร็จ');
          const url = imagePath(result.url); if (!url) throw new Error('ที่อยู่รูปภาพไม่ถูกต้อง');
          const index = Math.min((state.range || quill.getSelection() || { index: quill.getLength() - 1 }).index, quill.getLength() - 1);
          quill.insertEmbed(index, 'image', url, 'user'); quill.setSelection(index + 1, 0, 'silent'); state.range = { index: index + 1, length: 0 };
          sync(); message('เพิ่มรูปภาพแล้ว · คลิกภาพแล้วกด Alt เพื่อเพิ่มคำอธิบายภาพ');
        } catch (error) { message(error.message || 'อัปโหลดรูปภาพไม่สำเร็จ กรุณาลองใหม่', true); }
        finally { state.pending--; if (!state.pending) wrapper.removeAttribute('aria-busy'); }
      }
    };
    try {
      quill = new Quill(container, { theme: 'snow', placeholder: textarea.placeholder, modules: { toolbar: { container: toolbar, handlers: {
        image() { state.range = quill.getSelection(true); picker.click(); },
        imageAlt: describeImage,
        undo() { quill.history.undo(); }, redo() { quill.history.redo(); },
        link(value) {
          if (!value) { quill.format('link', false, 'user'); return; }
          const range = quill.getSelection(true); if (!range || !range.length) { message('เลือกข้อความที่ต้องการทำเป็นลิงก์ก่อน', true); return; }
          const href = window.prompt('ที่อยู่ลิงก์ เช่น https://example.com', quill.getFormat(range).link || 'https://');
          if (href === null) return;
          if (!/^(https?:\/\/|mailto:)/i.test(href.trim()) || /[\s<>"'\\]/.test(href)) { message('กรอกลิงก์ https://, http:// หรือ mailto: ที่ถูกต้อง', true); return; }
          quill.formatText(range.index, range.length, 'link', href.trim(), 'user');
        }
      } }, history: { userOnly: true } } });
      quill.clipboard.addMatcher('IMG', (node) => {
        const path = imagePath(node.getAttribute('src'));
        if (!path) { message('รูปจากเว็บไซต์ภายนอกไม่ถูกนำเข้า กรุณาอัปโหลดรูปจากเครื่อง', true); return new Delta(); }
        return new Delta().insert({ image: path }, { alt: (node.getAttribute('alt') || '').slice(0, 200) });
      });
      const format = form.querySelector('[name="body_format"], [name="content_format"]');
      if (format?.value === 'html') quill.setContents(quill.clipboard.convert({ html: textarea.value }), 'silent');
      else quill.setText(textarea.value, 'silent');
      quill.history.clear();
      quill.root.id = textarea.id + '-editor'; quill.root.setAttribute('role', 'textbox'); quill.root.setAttribute('aria-multiline', 'true'); quill.root.setAttribute('aria-labelledby', textarea.id + '-label'); quill.root.setAttribute('aria-describedby', status.id);
      if (state.required) quill.root.setAttribute('aria-required', 'true');
      [...textarea.labels].forEach(label => label.setAttribute('for', quill.root.id));
      toolbar.querySelectorAll('.ql-picker').forEach(p => { const label = p.querySelector('.ql-picker-label'); const original = p.nextElementSibling; const name = original?.getAttribute('aria-label') || (p.classList.contains('ql-color') ? 'สีข้อความ' : p.classList.contains('ql-background') ? 'สีพื้นหลังข้อความ' : 'จัดรูปแบบข้อความ'); label?.setAttribute('aria-label', name); p.title = name; });
      textarea.hidden = true; textarea.required = false;
      state.quill = quill; state.message = message; state.sync = sync; editors.set(textarea, state);
      quill.on('text-change', sync); quill.on('selection-change', range => { if (range) state.range = range; });
      quill.root.addEventListener('click', selectImage);
      picker.addEventListener('change', () => { const files = [...picker.files]; picker.value = ''; void upload(files); });
      quill.root.addEventListener('paste', event => { const files = [...(event.clipboardData?.files || [])]; if (files.length) { event.preventDefault(); event.stopImmediatePropagation(); state.range = quill.getSelection(); void upload(files); } }, true);
      quill.root.addEventListener('dragover', event => { if ([...(event.dataTransfer?.types || [])].includes('Files')) { event.preventDefault(); event.dataTransfer.dropEffect = 'copy'; } });
      quill.root.addEventListener('drop', event => { const files = [...(event.dataTransfer?.files || [])]; if (files.length) { event.preventDefault(); event.stopImmediatePropagation(); state.range = quill.getSelection() || { index: quill.getLength() - 1, length: 0 }; void upload(files); } }, true);
      sync();
    } catch (error) { wrapper.remove(); textarea.hidden = false; textarea.required = state.required; }
  });
  window.LmsRichEditor = {
    setText(textarea, text) {
      const state = editors.get(textarea);
      if (state && textarea.hidden) { state.quill.setText(text, 'user'); state.sync(); }
      else textarea.value = text;
      textarea.dispatchEvent(new Event('input', {bubbles:true}));
    },
    hasContent(textarea) {
      const state = editors.get(textarea);
      return state && textarea.hidden ? state.quill.getText().trim() !== '' || state.quill.root.querySelector('img') !== null : textarea.value.trim() !== '';
    }
  };
  const forms = new Set([...editors.values()].map(state => state.form));
  forms.forEach(form => {
    const states = [...editors.values()].filter(state => state.form === form);
    if (states.length !== form.querySelectorAll('textarea.rich-editor').length) {
      // Do not submit mixed HTML and plain text if any editor failed to initialize.
      states.forEach(state => { state.textarea.value = state.quill.getText(); state.textarea.hidden = false; state.textarea.required = state.required; state.textarea.nextElementSibling?.remove(); });
      return;
    }
    const format = form.querySelector('[name="body_format"], [name="content_format"]'); if (format) format.value = 'html';
    form.addEventListener('submit', event => {
      for (const state of states) {
        state.sync();
        const hasImage = state.quill.getContents().ops.some(op => op.insert && typeof op.insert === 'object' && imagePath(op.insert.image));
        let error = '';
        if (state.pending) error = 'กรุณารอให้อัปโหลดรูปภาพเสร็จก่อนบันทึก';
        else if (state.required && !state.quill.getText().replace(/[\s\p{Z}\p{C}]/gu, '') && !hasImage) error = 'กรุณาเพิ่มข้อความหรือรูปภาพในช่องนี้';
        else if (new TextEncoder().encode(state.textarea.value).length > state.textarea.maxLength) error = 'เนื้อหายาวเกินกำหนด กรุณาลดข้อความหรือแบ่งเป็นหลายกิจกรรม';
        if (error) { event.preventDefault(); state.message(error, true); state.quill.root.setAttribute('aria-invalid', 'true'); state.quill.focus(); return; }
        state.quill.root.removeAttribute('aria-invalid');
      }
    });
  });
})();
