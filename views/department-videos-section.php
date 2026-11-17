<?php use function App\e; $departmentVideos=App\DepartmentVideo::all(true,3); ?>
<section class="catalog-section" id="department-videos">
<div class="section-heading"><div><div class="eyebrow dark">FIND YOUR FUTURE</div><h2>วิดีโอแนะนำแผนกวิชา</h2><p>ค้นหาสาขาที่ใช่สำหรับคุณ · ดูได้ฟรีโดยไม่ต้องสมัครสมาชิก · รับเกียรติบัตรหลังรับชม</p></div><a class="featured-more" href="/department-videos">ดูวิดีโอทั้งหมด <?= icon('arrow') ?></a></div>
<?php if(!$departmentVideos): ?><div class="empty-state"><h3>เตรียมพบกับวิดีโอแนะนำแผนกวิชาเร็ว ๆ นี้</h3></div><?php else: ?><div class="course-grid">
<?php require __DIR__.'/department-video-cards.php'; ?>
</div><?php endif; ?></section>
