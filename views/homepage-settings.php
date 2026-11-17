<?php
use App\Homepage;
use function App\{e,csrfField};
$title='ตั้งค่าการแสดงผลหน้าแรก';require __DIR__.'/layout-top.php';
?>
<div class="page-heading"><div><div class="eyebrow dark">HOMEPAGE SETTINGS</div><h1>ตั้งค่าการแสดงผลหน้าแรก</h1><p>เลือกเปิดหรือปิดแต่ละส่วนสำหรับผู้เข้าชมหน้าแรก</p></div><a class="button outline" href="/">ดูหน้าแรก</a></div>
<section class="panel"><h2>ส่วนที่แสดงบนหน้าแรก</h2><form method="post" action="/admin/homepage" class="form-stack">
<?= csrfField() ?>
<?php foreach(Homepage::SECTIONS as $key=>$label): ?><label class="check-label"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= $sections[$key]?'checked':'' ?>><?= e($label) ?></label><?php endforeach; ?>
<p class="form-hint">เลือกช่องเพื่อเปิดแสดงผล นำเครื่องหมายออกเพื่อปิด วิดีโอแนะนำแสดงล่าสุดไม่เกิน 3 รายการพร้อมลิงก์ดูทั้งหมด การปิดส่วนบนหน้าแรกยังเปิดหน้าวิดีโอทั้งหมดและหน้ารายวิชาทั้งหมดได้</p>
<button class="button">บันทึกการแสดงผล</button>
</form></section>
<?php require __DIR__.'/layout-bottom.php'; ?>
