<?php
$title='ผู้ให้บริการ AI';require __DIR__.'/layout-top.php';
?>
<link rel="stylesheet" href="/assets/ai-connections.css">
<script src="/assets/ai-providers.js?v=<?= filemtime(dirname(__DIR__).'/public/assets/ai-providers.js') ?>" defer></script>
<script src="/assets/ai-pricing.js?v=<?= filemtime(dirname(__DIR__).'/public/assets/ai-pricing.js') ?>" defer></script>
<div class="page-heading"><div><div class="eyebrow dark">ADMIN · AI PROVIDERS</div><h1>ผู้ให้บริการ AI</h1><p>เพิ่มผู้ให้บริการและโมเดลให้ครูเลือกใช้ บริการ AI เปิดเฉพาะครูผู้สอน</p></div></div>
<?php $aiEnabled=\App\env('AI_ENABLED')==='true';$aiEncryptionReady=\App\AiCredentials::ready(); ?>
<section class="panel ai-spaced" aria-label="สถานะระบบ AI สำหรับครู">
<h2>สถานะระบบ AI สำหรับครู</h2>
<p><strong><?= $aiEnabled?'เปิดระบบ AI แล้ว':'ระบบ AI ยังปิดอยู่ที่เซิร์ฟเวอร์' ?></strong> · <?= $aiEncryptionReady?'กุญแจเข้ารหัสพร้อมใช้งาน':'ยังไม่ได้ตั้งกุญแจเข้ารหัส API key' ?></p>
<?php if(!$aiEnabled): ?><p class="form-hint">การเปิดผู้ให้บริการด้านล่างเป็นการเปิดรายการให้เลือก ต้องตั้ง AI_ENABLED=true บนเซิร์ฟเวอร์และโหลด environment ใหม่ด้วย ครูจึงจะใช้การเชื่อมต่อ AI ได้</p><?php endif; ?>
<?php if(!$aiEncryptionReady): ?><p class="form-hint">ตั้ง AI_CREDENTIALS_ENCRYPTION_KEY เป็น base64 ของค่าสุ่ม 32 ไบต์ และเก็บค่านี้ถาวร หากมี key ที่เข้ารหัสเดิมแล้ว ให้ใช้กุญแจเดิมจากข้อมูลสำรอง</p><?php endif; ?>
<?php if($aiEnabled&&$aiEncryptionReady): ?><p class="form-hint">ครูเลือกผู้ให้บริการที่เปิดไว้ เพิ่มและทดสอบ API key ของตน จากนั้นผูกโมเดลและแหล่งจ่ายรายวิชาได้ ตรวจราคาของโมเดลในส่วน “ราคาเนื้อหาและข้อสอบ” ด้านล่างก่อนสร้างงาน</p><?php else: ?><p class="form-hint">หลังแก้ environment ให้ผู้ดูแลเซิร์ฟเวอร์ recreate web ตามคู่มือติดตั้ง แล้วรีเฟรชหน้านี้</p><?php endif; ?>
</section>
<div id="ai-providers-feedback" class="notice" role="status" aria-live="polite">กำลังโหลดผู้ให้บริการ…</div>
<div class="ai-grid">
<section class="panel"><h2 id="ai-provider-form-title">เพิ่มผู้ให้บริการ</h2>
<p>เลือกแม่แบบแล้วตรวจ Base URL จากเอกสารของผู้ให้บริการ การเพิ่มรายการยังไม่เรียก API และไม่สร้างค่าใช้จ่าย</p>
<form id="ai-provider-form" class="form-stack">
<label>แม่แบบ<select id="ai-provider-template"><option value="custom">กำหนดเอง</option><option value="openai">OpenAI / API ที่เข้ากันได้</option><option value="anthropic">Anthropic (Claude)</option><option value="gemini">Google Gemini</option></select></label>
<label>รหัสผู้ให้บริการ<input name="slug" maxlength="30" pattern="[a-z][a-z0-9_\-]{1,29}" required placeholder="เช่น school-ai"></label>
<label>ชื่อที่ครูจะเห็น<input name="name" maxlength="100" required></label>
<label>รูปแบบ API<select name="adapter" required><option value="openai_compatible">OpenAI-compatible — Bearer token</option><option value="anthropic">Anthropic — x-api-key</option><option value="gemini">Gemini — x-goog-api-key</option></select></label>
<label>Base URL รวมเวอร์ชัน API<input name="base_url" type="url" maxlength="1000" placeholder="https://api.example.com/v1" required></label>
<small>ทดสอบผ่าน GET {Base URL}/models รองรับ HTTPS พอร์ต 443 ของเว็บไซต์สาธารณะ ไม่รับ URL ภายในหรือ API key ใน URL</small>
<h3>โมเดลที่อนุญาตแยกตามการใช้งาน</h3><p>ใส่ model ID จากผู้ให้บริการ หนึ่งรายการต่อบรรทัด หรือคั่นด้วย comma เว้นว่างเมื่อไม่เปิดฟังก์ชันนั้น</p>
<p class="form-hint">ช่องนี้เป็นรายการรหัสโมเดลที่อนุญาตให้ครูเลือก ไม่ใช่ช่องเขียนคำสั่ง AI สำหรับสร้างบทเรียน ใบงาน และข้อสอบ ให้กรอกช่อง “เนื้อหาและข้อสอบ” ก่อน ช่องอื่นเว้นว่างได้</p>
<div class="button-row"><button type="button" id="ai-provider-examples" class="button outline">เติมโมเดลตัวอย่างในช่องที่ว่าง</button></div>
<p id="ai-provider-example-hint" class="form-hint" role="status" aria-live="polite">เลือกแม่แบบผู้ให้บริการเพื่อดูตัวอย่างรหัสโมเดล</p>
<label>เนื้อหาและข้อสอบ<textarea name="models_text" rows="3" maxlength="22000" aria-describedby="ai-text-help"></textarea></label>
<small id="ai-text-help">ใช้ร่วมกันสำหรับสร้างเนื้อหาบทเรียน ใบงาน และข้อสอบปรนัย ใส่โมเดลข้อความของผู้ให้บริการนี้ และตรวจว่า API key มีสิทธิ์ใช้โมเดลนั้น</small>
<label>ดัชนีความรู้ / embedding<textarea name="models_embedding" rows="2" maxlength="22000" placeholder="เว้นว่าง — ยังไม่มีระบบสร้างดัชนีความรู้" aria-describedby="ai-embedding-help"></textarea></label>
<small id="ai-embedding-help">สำหรับแปลงข้อความเป็นเวกเตอร์เพื่อค้นหาความรู้ ต้องใช้โมเดล embedding โดยเฉพาะ ฟังก์ชันนี้ยังไม่ติดตั้งใน LMS</small>
<label>เสียงบรรยาย<textarea name="models_speech" rows="2" maxlength="22000" placeholder="เว้นว่าง — ยังไม่มีระบบสร้างเสียงบรรยาย" aria-describedby="ai-speech-help"></textarea></label>
<small id="ai-speech-help">สำหรับแปลงข้อความเป็นเสียง ต้องใช้โมเดลสร้างเสียง ฟังก์ชันนี้ยังไม่ติดตั้งใน LMS</small>
<label>วิเคราะห์ผลสำหรับครู<textarea name="models_analytics" rows="2" maxlength="22000" placeholder="เว้นว่าง — ยังไม่มีระบบวิเคราะห์ผลด้วย AI" aria-describedby="ai-analytics-help"></textarea></label>
<small id="ai-analytics-help">สำหรับสรุปและวิเคราะห์ผลการเรียน ใช้โมเดลข้อความ ฟังก์ชันนี้ยังไม่ติดตั้งใน LMS</small>
<label>ผู้ช่วยงานสอนสำหรับครู<textarea name="models_assistant" rows="2" maxlength="22000" placeholder="เว้นว่าง — ยังไม่มีผู้ช่วยสนทนาสำหรับครู" aria-describedby="ai-assistant-help"></textarea></label>
<small id="ai-assistant-help">สำหรับผู้ช่วยสนทนางานสอนของครู ใช้โมเดลข้อความ ฟังก์ชันนี้ยังไม่ติดตั้งใน LMS</small>
<p class="form-hint">การกรอกรหัสโมเดลไม่ได้เปิดฟังก์ชันที่ยังไม่ติดตั้ง ตัวอย่างไม่ใช่การยืนยันสิทธิ์ของ key หรือราคา ก่อนสร้างงานจริงต้องตั้งราคาโมเดลและผูกแหล่งจ่ายรายวิชา</p>
<label class="check-label"><input name="enabled" type="checkbox" checked>เปิดให้ครูเลือกใช้งาน</label>
<small>เมื่อแก้ไขการตั้งค่า key เดิมต้องยืนยันผู้ให้บริการและทดสอบใหม่ก่อนใช้งาน ไม่มีการส่ง key ของครูไปยังปลายทางใหม่อัตโนมัติ</small>
<div class="button-row"><button type="submit" class="button">บันทึกผู้ให้บริการ</button><button type="button" id="ai-provider-new" class="button outline">เพิ่มรายการใหม่</button></div>
</form></section>
<section class="panel"><h2>รายการผู้ให้บริการ</h2><div id="ai-provider-list" class="ai-credentials"></div><p>ปิดใช้งานเพื่อหยุดการเรียกใหม่และซ่อนจากตัวเลือกครู ประวัติและข้อมูล key เดิมยังคงอยู่</p></section></div>
<section class="panel ai-spaced" id="ai-pricing">
<h2>ราคาเนื้อหาและข้อสอบ</h2>
<p>กำหนดราคาของแต่ละโมเดลเพื่อคำนวณเงินจองก่อนครูสร้างบทเรียน ใบงาน และข้อสอบ หน่วยเป็น USD ต่อ 1 ล้าน token ตรวจอัตราจากผู้ให้บริการให้ตรงกับแผนที่ใช้งาน</p>
<form id="ai-pricing-form" class="form-stack">
<div class="table-scroll"><table><thead><tr><th>ผู้ให้บริการ / โมเดล</th><th>Input (USD / 1M token)</th><th>Output (USD / 1M token)</th><th>สถานะ</th></tr></thead><tbody id="ai-pricing-rows"></tbody></table></div>
<p class="form-hint">Input คือข้อความที่ส่งเข้า AI · Output คือข้อความและ token การคิดที่ผู้ให้บริการคิดเงิน เว้นว่างทั้งคู่หากยังไม่กำหนดราคา กรอก 0 เฉพาะเมื่อแผนที่ใช้ไม่มีค่าใช้จ่ายจริง ราคาบนเว็บมีผลทันทีหลังบันทึก ไม่ต้อง restart ระบบ</p>
<button class="button" type="submit">บันทึกราคาโมเดล</button>
</form><p id="ai-pricing-feedback" role="status" aria-live="polite"></p>
</section>
<section class="panel ai-spaced"><h2>ยืนยันตัวตนอีกครั้ง</h2><p>ต้องยืนยันตัวตนภายใน 15 นาทีก่อนเปลี่ยนการตั้งค่า บัญชี ThaID ให้เข้าสู่ระบบใหม่</p>
<form id="ai-provider-reauth" class="ai-inline"><label>รหัสผ่าน LMS<input name="password" type="password" autocomplete="current-password" required maxlength="72"></label><button type="submit" class="button outline">ยืนยันตัวตน</button></form></section>
<?php require __DIR__.'/layout-bottom.php'; ?>
