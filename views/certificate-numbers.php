<?php
use App\{DB,CertificateNumbers};
use function App\{e,csrfField};
$title='เลขทะเบียนเกียรติบัตร';require __DIR__.'/layout-top.php';
$config=DB::one('SELECT range_id FROM certificate_numbering WHERE id=1');
$ranges=DB::all('SELECT * FROM certificate_number_ranges ORDER BY id DESC');
?>
<div class="page-heading"><div><h1>เลขทะเบียนเกียรติบัตร</h1><p>ชุดเลขกลางที่งานทะเบียนจัดสรรให้ LMS ใช้ร่วมกันทุกหลักสูตร</p></div><a href="/admin" class="button outline">กลับหน้าจัดการระบบ</a></div>
<section class="panel"><h2>เพิ่มช่วงเลขจากงานทะเบียน</h2><p class="form-hint">ตัวอย่าง: คำนำหน้า ศอช. · ปี 2569 · เริ่ม 101 ถึง 200 · จำนวนหลัก 4 → ศอช. 0101/2569 ถึง ศอช. 0200/2569</p><p class="form-hint">กรอกเฉพาะช่วงเลขที่งานทะเบียนจัดสรรให้แล้ว ระบบไม่อนุญาตช่วงทับซ้อนในคำนำหน้าและปีเดียวกัน ชุดเลขที่บันทึกแล้วแก้ไขหรือลบไม่ได้เพื่อรักษาประวัติ</p>
<form method="post" action="/admin/certificate-numbers" class="form-stack"><?= csrfField() ?>
<label>คำนำหน้า (ไม่บังคับ)<input name="prefix" maxlength="30" placeholder="ศอช."></label>
<div class="form-grid"><label>ปี พ.ศ. ของชุดเลข<input type="number" name="year" min="2500" max="3000" value="<?= (int)date('Y')+543 ?>" required></label><label>จำนวนหลัก (เติมศูนย์ด้านหน้า)<input type="number" name="digits" min="1" max="9" value="4" required></label><label>เลขเริ่มต้น<input type="number" name="start_number" min="1" max="999999999" required></label><label>เลขสิ้นสุด<input type="number" name="end_number" min="1" max="999999999" required></label></div>
<button class="button">บันทึกช่วงเลข</button></form></section>
<section class="panel"><div class="panel-heading"><h2>ชุดเลขที่จัดสรร</h2><form method="post" action="/admin/certificate-numbers/activate" data-confirm="พักการออกใบประกาศใหม่จนกว่าจะเปิดใช้ชุดเลขอีกครั้ง?"><?= csrfField() ?><input type="hidden" name="range_id" value="0"><button class="button outline">พักการออกเลข</button></form></div>
<p class="form-hint">เปิดใช้ครั้งละหนึ่งชุด เมื่อเลขหมดระบบจะหยุดออกใบใหม่จนกว่า admin จะเปิดใช้ชุดถัดไป เลขที่ออกแล้วไม่เปลี่ยนเมื่อสลับชุดหรือพิมพ์ซ้ำ</p>
<?php if(!$config['range_id']): ?><p class="notice">ยังไม่ได้เปิดใช้ชุดเลข — ระบบพักการออกใบประกาศใหม่</p><?php endif; ?>
<div class="table-scroll"><table><thead><tr><th>ช่วงเลข</th><th>ใช้แล้ว / คงเหลือ</th><th>เลขถัดไป</th><th>สถานะ / จัดการ</th></tr></thead><tbody>
<?php foreach($ranges as $range): $remaining=max(0,(int)$range['end_number']-(int)$range['next_number']+1);$active=(int)$config['range_id']===(int)$range['id']; ?>
<tr><td><?= e(CertificateNumbers::format($range,(int)$range['start_number'])) ?><br>ถึง <?= e(CertificateNumbers::format($range,(int)$range['end_number'])) ?></td><td><?= (int)$range['next_number']-(int)$range['start_number'] ?> / <?= $remaining ?></td><td><?= $remaining?e(CertificateNumbers::format($range,(int)$range['next_number'])):'เลขหมด' ?></td><td><?php if($active): ?><span class="pill">ชุดปัจจุบัน<?= $remaining?'':' · เลขหมด' ?></span><?php elseif($remaining): ?><form method="post" action="/admin/certificate-numbers/activate" data-confirm="เปิดใช้ชุดเลขนี้สำหรับใบประกาศใหม่ทุกหลักสูตร?"><?= csrfField() ?><input type="hidden" name="range_id" value="<?= (int)$range['id'] ?>"><button class="button small outline">เปิดใช้ชุดนี้</button></form><?php else: ?>เลขหมด<?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if(!$ranges): ?><tr><td colspan="4">ยังไม่มีช่วงเลขจากงานทะเบียน</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="panel"><h2>ตรวจเทียบงานทะเบียน</h2><p class="form-hint">ส่งออกเลขทะเบียน ชื่อผู้รับ รายวิชา รหัสผู้เรียน รหัสรายวิชา วันที่ออก และเลขอ้างอิง LMS ใบประกาศเดิมก่อนมีระบบชุดเลขจะเว้นเลขทะเบียนว่าง โดยยังตรวจสอบผ่านลิงก์เดิมได้ การเปิดใช้ชุดเลขเป็นการจัดสรรหมายเลขใน LMS ไม่ใช่การบันทึกว่างานทะเบียนรับรองฉบับจริงแล้ว</p><a href="/admin/certificate-numbers/export" class="button outline">ส่งออกทะเบียน CSV</a></section>
<?php require __DIR__.'/layout-bottom.php'; ?>
