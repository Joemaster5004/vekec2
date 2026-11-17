<?php use function App\e; $departmentVideos=App\DepartmentVideo::all(true); ?>
<section class="catalog-section" id="department-videos">
<div class="section-heading"><div><div class="eyebrow dark">FIND YOUR FUTURE</div><h2>วิดีโอแนะนำแผนกวิชา</h2><p>ค้นหาสาขาที่ใช่สำหรับคุณ · ดูได้ฟรีโดยไม่ต้องสมัครสมาชิก · รับเกียรติบัตรหลังรับชม</p></div></div>
<?php if(!$departmentVideos): ?><div class="empty-state"><h3>เตรียมพบกับวิดีโอแนะนำแผนกวิชาเร็ว ๆ นี้</h3></div><?php else: ?><div class="course-grid">
<?php foreach($departmentVideos as $v): ?><article class="panel guidance-card"><div class="guidance-card-art"><?= icon('play') ?><span>สำรวจอนาคตของคุณ</span></div><span class="pill"><?= e($v['department']) ?></span><h3><?= e($v['title']) ?></h3><p><?= e($v['description']) ?></p><a class="button outline" href="/department-videos/<?= (int)$v['id'] ?>"><?= icon('play') ?>ชมวิดีโอแนะนำ</a></article><?php endforeach; ?>
</div><?php endif; ?></section>
