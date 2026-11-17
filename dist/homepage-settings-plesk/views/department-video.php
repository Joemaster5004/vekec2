<?php
use App\{DB,DepartmentVideo};
use function App\{e,csrfField};
$title=$video['title'];require __DIR__.'/layout-top.php';$id=(int)$video['id'];$mode=DepartmentVideo::mode($video);
?>
<div class="breadcrumb"><a href="/department-videos">วิดีโอแนะนำแผนกวิชา</a><?= icon('chevron') ?><span><?= e($video['department']) ?></span></div>
<section class="panel" data-guidance-video="<?= $id ?>" data-mode="<?= e($mode) ?>" data-active="<?= (int)$video['active'] ?>">
<span class="pill"><?= e($video['department']) ?></span><h1><?= e($video['title']) ?></h1><p>ดูได้ทันทีโดยไม่ต้องสมัครสมาชิก</p>
<?php if(!$video['active']): ?><p class="notice">ตัวอย่างสำหรับผู้ดูแล · ยังไม่เผยแพร่ จึงยังรับเกียรติบัตรไม่ได้</p><?php endif; ?>
<div class="video-wrapper">
<?php if($mode==='mp4'): ?><video data-guidance-player controls playsinline preload="metadata" src="<?= $video['media_path']?'/department-videos/'.$id.'/media':e($video['external_url']) ?>"></video>
<?php elseif($mode==='youtube'): ?><div id="guidance-youtube" data-video-id="<?= e(DepartmentVideo::youtube($video['external_url'])) ?>"></div>
<?php else: ?><div class="external-video"><p>วิดีโอนี้รับชมที่เว็บไซต์ภายนอก เมื่อรับชมจบแล้วกลับมายืนยันที่หน้านี้</p><a href="<?= e($video['external_url']) ?>" target="_blank" rel="noopener noreferrer" class="button" data-guidance-external>เปิดวิดีโอแนะนำ <?= icon('play') ?></a></div><?php endif; ?>
</div><p class="form-hint" data-guidance-status role="status">รับชมวิดีโอจนจบเพื่อกรอกข้อมูลและรับเกียรติบัตร</p>
<?php if($mode==='external'): ?><button type="button" class="button outline" data-guidance-confirm disabled>ยืนยันว่ารับชมวิดีโอจบแล้ว</button><?php endif; ?>
<p class="guidance-description"><?= nl2br(e($video['description'])) ?></p>
<?php foreach(DB::all('SELECT * FROM department_video_files WHERE video_id=? ORDER BY id',[$id]) as $f): ?><p><a class="attachment" href="/department-videos/<?= $id ?>/media/<?= (int)$f['id'] ?>"><?= icon('download') ?><?= e($f['name']) ?></a></p><?php endforeach; ?>
<?php if($video['application_url']): ?><a class="button" href="<?= e($video['application_url']) ?>" target="_blank" rel="noopener noreferrer">สมัครเรียนสาขานี้ <?= icon('arrow') ?></a><?php endif; ?>
<section id="guidance-registration" hidden><h2>กรอกข้อมูลเพื่อรับเกียรติบัตร</h2><p>เกียรติบัตรเข้าร่วมกิจกรรมแนะแนวแผนกวิชา</p><form method="post" action="/department-videos/<?= $id ?>/certificate" class="form-stack">
<?= csrfField() ?><label>ชื่อ–นามสกุล<input name="name" maxlength="180" autocomplete="name" required></label>
<label>สถานศึกษาที่ศึกษาอยู่<input name="school" maxlength="240" required></label>
<label>ระดับชั้น<input name="grade" maxlength="100" placeholder="เช่น ม.3 หรือ ม.6" required></label>
<label>เบอร์โทรศัพท์<input type="tel" name="phone" maxlength="40" autocomplete="tel" required></label>
<label>อีเมล<input type="email" name="email" maxlength="190" autocomplete="email" required></label>
<label class="check-label"><input type="checkbox" name="consent" value="1" required>ยินยอมให้วิทยาลัยจัดเก็บข้อมูลนี้เพื่อออกเกียรติบัตรและใช้ในงานแนะแนว โดยชื่อจะปรากฏบนเกียรติบัตรที่แชร์ได้</label>
<button class="button"><?= icon('award') ?>รับเกียรติบัตร</button></form></section>
<noscript><p class="notice">กรุณาเปิด JavaScript เพื่อติดตามการรับชมและรับเกียรติบัตร</p></noscript>
</section><script src="/assets/department-video.js" defer></script>
<?php require __DIR__.'/layout-bottom.php'; ?>
