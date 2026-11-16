<?php
use App\Users;
use function App\{e,csrfField};
$title='นำเข้าสมาชิก';
require __DIR__.'/layout-top.php';
?>
<div class="breadcrumb"><a href="/admin#users">บัญชีผู้ใช้งาน</a><?= icon('chevron') ?><span>นำเข้าสมาชิก</span></div>
<div class="page-heading"><div><div class="eyebrow dark">IMPORT MEMBERS</div><h1>นำเข้าสมาชิก</h1><p>เพิ่มหลายบัญชีพร้อมกันด้วยไฟล์ CSV หรือ JSON</p></div><a href="/admin#users" class="button outline">กลับไปรายชื่อ</a></div>
<div class="two-panels">
    <section class="panel">
        <h2>อัปโหลดรายชื่อสมาชิก</h2>
        <?php if (!empty($error)): ?><div class="member-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="/admin/users/import" enctype="multipart/form-data" class="form-stack">
            <?= csrfField() ?><input type="hidden" name="MAX_FILE_SIZE" value="<?= Users::IMPORT_BYTES ?>">
            <label for="members-file">ไฟล์สมาชิก<input id="members-file" type="file" name="members" accept=".csv,.json" required aria-describedby="members-file-help"></label>
            <p id="members-file-help" class="form-hint">รองรับ CSV (UTF-8 คั่นด้วยจุลภาค) และ JSON ขนาดไม่เกิน 1 MB สูงสุด <?= Users::IMPORT_LIMIT ?> สมาชิกต่อครั้ง หากใช้ Excel ให้บันทึกเป็น CSV UTF-8 ก่อนอัปโหลด</p>
            <p class="form-hint">ระบบตรวจทุกแถวก่อนบันทึก หากข้อมูลผิดหรืออีเมลซ้ำในไฟล์หรือในระบบ จะไม่นำเข้าสมาชิกใด ๆ และจะแจ้งแถวหรือรายการที่ต้องแก้ไข</p>
            <div class="button-row"><button class="button"><?= icon('users') ?>นำเข้าสมาชิก</button><a href="/admin#users" class="button outline">ยกเลิก</a></div>
        </form>
    </section>
    <section class="panel">
        <h2>เตรียมไฟล์จากตัวอย่าง</h2>
        <div class="button-row"><a href="/templates/members.csv" download class="button outline small"><?= icon('download') ?>ตัวอย่าง CSV</a><a href="/templates/members.json" download class="button outline small"><?= icon('download') ?>ตัวอย่าง JSON</a></div>
        <p class="form-hint">แทนที่ข้อมูลตัวอย่างด้วยสมาชิกจริง และกำหนดรหัสผ่านเฉพาะของแต่ละคนก่อนนำเข้า</p>
        <dl class="member-import-fields">
            <div><dt>name *</dt><dd>ชื่อ–นามสกุล ไม่เกิน 180 ตัวอักษร</dd></div>
            <div><dt>email *</dt><dd>อีเมลที่ไม่ซ้ำกับสมาชิกอื่น</dd></div>
            <div><dt>password *</dt><dd>รหัสผ่าน 12–72 ไบต์</dd></div>
            <div><dt>role</dt><dd>student = ผู้เรียน, teacher = ครูผู้สอน, admin = ผู้ดูแลระบบ<br>ค่าเริ่มต้น student</dd></div>
            <div><dt>level</dt><dd>public = ประชาชนทั่วไป, voc1 = ปวช., voc2 = ปวส.<br>ค่าเริ่มต้น public</dd></div>
            <div><dt>active</dt><dd>1 = ใช้งาน, 0 = ระงับ<br>ค่าเริ่มต้น 1</dd></div>
        </dl>
        <p class="form-hint">* จำเป็นต้องกรอก · ตั้งหัวคอลัมน์ตามชื่อตัวพิมพ์เล็กด้านบน การนำเข้าสร้างบัญชีใหม่ และยังไม่ลงทะเบียนรายวิชาให้สมาชิก</p>
    </section>
</div>
<?php require __DIR__.'/layout-bottom.php'; ?>
