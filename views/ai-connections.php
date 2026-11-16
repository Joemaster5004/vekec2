<?php
use App\DB;
use function App\e;
$title='การเชื่อมต่อ AI ของฉัน';require __DIR__.'/layout-top.php';
$courses=DB::all('SELECT id,title FROM courses'.($user['role']==='admin'?'':' WHERE teacher_id=?').' ORDER BY id DESC',$user['role']==='admin'?[]:[$user['id']]);
?>
<link rel="stylesheet" href="/assets/ai-connections.css">
<script src="/assets/ai-connections.js" defer></script>
<div class="page-heading"><div><div class="eyebrow dark">TEACHER AI CONNECTIONS</div><h1>การเชื่อมต่อ AI ของฉัน</h1><p>ใช้ API key ของคุณ หรือแหล่งจ่ายส่วนกลางที่ได้รับอนุญาต</p></div></div>
<div id="ai-feedback" class="notice" role="status" aria-live="polite">กำลังโหลดการตั้งค่า…</div>
<div class="ai-grid">
<section class="panel"><h2>1. เพิ่มการเชื่อมต่อของฉัน</h2>
<p>รับ API key จากบัญชีผู้ให้บริการของคุณ ค่าใช้จ่ายจะเกิดกับบัญชีที่ key ผูกอยู่ เลือกจากผู้ให้บริการและโมเดลที่ผู้ดูแลเปิดใช้งาน ครูไม่ต้องกำหนด URL เอง</p>
<form id="ai-create" class="form-stack">
<label>ผู้ให้บริการ<select name="provider" required></select></label>
<label>ชื่อการเชื่อมต่อ<input name="label" maxlength="100" required placeholder="เช่น บัญชีสำหรับงานสอน"></label>
<label>API key<input name="secret" type="password" autocomplete="off" spellcheck="false" maxlength="8192" required></label>
<small>ส่งผ่านเซิร์ฟเวอร์ LMS และเก็บแบบเข้ารหัส หลังบันทึกจะเปิดดู key เต็มอีกไม่ได้ ไม่ใช่รหัสผ่านเข้าสู่ LMS</small>
<button class="button" type="submit">บันทึกการเชื่อมต่อ</button></form>
</section>
<section class="panel"><h2>2. จัดการ key อย่างปลอดภัย</h2>
<p>กดทดสอบก่อนนำไปใช้ การทดสอบตรวจสิทธิ์เชื่อมต่อโดยไม่สร้างเนื้อหา และไม่ได้ยืนยันว่าใช้ได้ทุกโมเดล</p>
<div id="ai-credentials" class="ai-credentials"></div>
<form id="ai-rotate" class="form-stack"><h3>เปลี่ยน API key</h3>
<label>การเชื่อมต่อ<select name="credential_id" required></select></label>
<label>API key ใหม่<input name="secret" type="password" autocomplete="off" spellcheck="false" maxlength="8192" required></label>
<small>key เดิมจะเริ่มงานใหม่ไม่ได้ งานที่จองไว้ต้องยืนยันใหม่ และต้องทดสอบ key ใหม่ก่อนใช้งาน</small><button class="button outline" type="submit">เปลี่ยน key</button></form>
</section></div>
<section class="panel ai-spaced"><h2>ยืนยันตัวตนสำหรับการตั้งค่าที่สำคัญ</h2><p>หลังเข้าสู่ระบบเกิน 15 นาที ให้ยืนยันรหัสผ่านอีกครั้ง บัญชี ThaID ให้ออกจากระบบแล้วเข้าสู่ระบบใหม่</p>
<form id="ai-reauth" class="ai-inline"><label>รหัสผ่าน LMS<input name="password" type="password" autocomplete="current-password" maxlength="72" required></label><button class="button outline" type="submit">ยืนยันตัวตน</button></form></section>
<section class="panel ai-spaced"><h2>3. เลือกแหล่งจ่ายของรายวิชา</h2>
<label>รายวิชา<select id="ai-course"><option value="">เลือกรายวิชา</option><?php foreach($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?></select></label>
<form id="ai-billing" class="form-stack" hidden>
<p>เลือก “ปิดการใช้” สำหรับฟังก์ชันที่ยังไม่ต้องการตั้งค่า ระบบจะไม่สลับแหล่งจ่ายให้อัตโนมัติเมื่อ key ใช้ไม่ได้</p>
<div class="table-scroll"><table><thead><tr><th>การใช้งาน</th><th>แหล่งจ่าย</th><th>การเชื่อมต่อของฉัน</th><th>โมเดลที่อนุญาต</th><th>งบ USD / เดือน</th></tr></thead><tbody id="ai-binding-rows"></tbody></table></div>
<p>วิดีโอจะใช้การเชื่อมต่อข้อความและเสียงแยกกัน การตั้งค่าในหน้านี้ยังไม่ได้ติดตั้งฟังก์ชันสร้างเนื้อหา ข้อสอบ วิดีโอ ผู้ช่วย หรือวิเคราะห์ผล</p>
<h3>4. งบประมาณและประวัติการใช้</h3><p>งบควบคุมเฉพาะงานที่เรียกผ่าน LMS และใช้ยอดจองจนกว่าจะทราบยอดจริง ไม่ใช่ยอดเรียกเก็บทั้งบัญชี ควรสร้าง key แยกสำหรับ LMS และตั้งวงเงินกับผู้ให้บริการด้วย</p>
<p>บริการ AI สำหรับครูผู้สอนเท่านั้น นักศึกษาไม่สามารถเรียกใช้งานหรือใช้งบ AI ของรายวิชาได้</p>
<label class="check-label"><input name="confirmed" type="checkbox" required>ฉันตรวจแหล่งจ่าย โมเดล และวงเงินแล้ว และยอมรับค่าใช้จ่ายตามการตั้งค่านี้</label>
<button class="button" type="submit">บันทึกแหล่งจ่ายและวงเงิน</button></form>
<div id="ai-usage" class="ai-spaced"></div></section>
<section class="panel ai-spaced"><h2>ปิดใช้ key ใน LMS และเพิกถอนที่ผู้ให้บริการ</h2><p>การปิดใช้จะห้ามการเรียกใหม่ผ่าน LMS และลบ secret ที่จัดเก็บ งานที่ส่งไปแล้วอาจยังมีค่าใช้จ่าย หากเลิกใช้หรือสงสัยว่า key รั่วไหล ให้เพิกถอน key ที่ผู้ให้บริการด้วย ผู้ดูแลโครงสร้างพื้นฐานของ LMS ยังเป็นส่วนที่คุณต้องเชื่อถือ</p></section>
<?php require __DIR__.'/layout-bottom.php'; ?>
