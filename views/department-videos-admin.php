<?php
use App\{DB,Pagination};
use function App\{e,csrfField,dateThai};
$title='วิดีโอแนะนำแผนกวิชา';require __DIR__.'/layout-top.php';
?>
<div class="page-heading"><div><div class="eyebrow dark">DEPARTMENT GUIDANCE</div><h1>วิดีโอแนะนำแผนกวิชา</h1><p>เฉพาะผู้ดูแลระบบเท่านั้นที่จัดการวิดีโอได้ ผู้เข้าชมไม่ต้องมีบัญชีสมาชิก</p></div><a href="/#department-videos" class="button outline">ดูหน้าแรก</a></div>
<p class="notice">MP4 สูงสุด 256 MB · เอกสาร PDF / JPG / PNG สูงสุด 10 MB ต่อไฟล์ แนบได้ 10 ไฟล์ต่อครั้ง<br>URL รองรับ YouTube, MP4 และเว็บไซต์ภายนอก สำหรับ URL ให้ระบุความยาวจริงเป็นวินาที เว็บไซต์ภายนอกใช้การยืนยันการรับชมของผู้เข้าชม</p>
<?php foreach(array_merge([null],$videos) as $v): $id=$v?(int)$v['id']:'new'; ?>
<section class="panel" id="department-video-<?= $id ?>"><h2><?= $v?'แก้ไข: '.e($v['title']):'เพิ่มวิดีโอแนะนำแผนกวิชา' ?></h2>
<form method="post" action="/admin/department-videos<?= $v?'/'.$id.'/save':'' ?>" enctype="multipart/form-data" class="form-stack">
<?= csrfField() ?>
<label>แผนกวิชา / สาขาวิชา<select name="category_id" required><option value="">เลือกแผนกวิชา</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($v['category_id']??0)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
<label>ชื่อวิดีโอ<input name="title" maxlength="240" required value="<?= e($v['title']??'') ?>"></label>
<label>คำอธิบาย<textarea name="description" maxlength="4000"><?= e($v['description']??'') ?></textarea></label>
<label>ประเภทวิดีโอ<select name="source"><option value="url" <?= empty($v['media_path'])?'selected':'' ?>>URL ภายนอก</option><option value="upload" <?= !empty($v['media_path'])?'selected':'' ?>>อัปโหลดไฟล์ MP4</option></select></label>
<label>URL วิดีโอ<input type="url" name="external_url" maxlength="2000" value="<?= e($v['external_url']??'') ?>" placeholder="https://www.youtube.com/watch?v=..."></label>
<label>ไฟล์วิดีโอ MP4 <?= !empty($v['media_path'])?'(เว้นว่างเพื่อใช้ไฟล์เดิม)':'' ?><input type="file" name="video" accept="video/mp4,.mp4"></label>
<label>ความยาววิดีโอ URL (วินาที) / MP4 ระบบอ่านให้อัตโนมัติ<input type="number" name="duration_seconds" min="1" max="43200" required value="<?= (int)($v['duration_seconds']??60) ?>"></label>
<label>แนบไฟล์เพิ่มเติม (PDF / JPG / PNG)<input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png"></label>
<label>ลิงก์รับสมัครเรียน<input type="url" name="application_url" maxlength="2000" value="<?= e($v['application_url']??'') ?>" placeholder="https://..."></label>
<label class="check-label"><input type="checkbox" name="active" value="1" <?= !empty($v['active'])?'checked':'' ?>>เผยแพร่บนหน้าแรก</label>
<button class="button"><?= $v?'บันทึกการแก้ไข':'เพิ่มวิดีโอ' ?></button>
</form>
<?php if($v): ?><p><a class="text-link" href="/department-videos/<?= $id ?>">เปิดดูวิดีโอ</a></p>
<?php foreach(DB::all('SELECT * FROM department_video_files WHERE video_id=? ORDER BY id',[$id]) as $f): ?><div class="info-row"><a href="/department-videos/<?= $id ?>/media/<?= (int)$f['id'] ?>"><?= e($f['name']) ?></a><form method="post" action="/admin/department-videos/<?= $id ?>/files/<?= (int)$f['id'] ?>/delete" data-confirm="ลบไฟล์แนบนี้หรือไม่?"><?= csrfField() ?><button class="button danger small">ลบไฟล์</button></form></div><?php endforeach; ?>
<?php endif; ?></section><?php endforeach; ?>
<?php
$pagination=new Pagination((int)DB::one('SELECT COUNT(*) AS total FROM department_video_certificates')['total']);
$participants=DB::all('SELECT * FROM department_video_certificates ORDER BY id DESC LIMIT ? OFFSET ?',[$pagination->perPage,$pagination->offset]);
?>
<section class="panel" id="participants"><h2>ผู้รับเกียรติบัตรแนะแนว</h2><p class="form-hint">ข้อมูลสำหรับผู้ดูแลระบบเท่านั้น เกียรติบัตรแสดงเฉพาะชื่อผู้รับและกิจกรรม</p><div class="table-wrap"><table><thead><tr><th>ชื่อ–นามสกุล</th><th>สถานศึกษา / ระดับชั้น</th><th>เบอร์โทรศัพท์ / อีเมล</th><th>วิดีโอ / วันที่</th></tr></thead><tbody><?php foreach($participants as $p): ?><tr><td><?= e($p['recipient_name']) ?></td><td><?= e($p['school']) ?><br><?= e($p['grade']) ?></td><td><?= e($p['phone']) ?><br><?= e($p['email']) ?></td><td><a href="/guidance-certificate/<?= e($p['code']) ?>"><?= e($p['video_title']) ?></a><br><?= dateThai($p['issued_at']) ?></td></tr><?php endforeach; ?><?php if(!$participants): ?><tr><td colspan="4">ยังไม่มีผู้รับเกียรติบัตร</td></tr><?php endif; ?></tbody></table></div><?php require __DIR__.'/pagination.php'; ?></section>
<?php require __DIR__.'/layout-bottom.php'; ?>
