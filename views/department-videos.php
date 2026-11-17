<?php use function App\e; $title='วิดีโอแนะนำแผนกวิชาทั้งหมด';require __DIR__.'/layout-top.php'; ?>
<div class="breadcrumb"><a href="/">หน้าแรก</a><?= icon('chevron') ?><span>วิดีโอแนะนำแผนกวิชา</span></div>
<section class="catalog-section"><div class="section-heading"><div><div class="eyebrow dark">FIND YOUR FUTURE</div><h1>วิดีโอแนะนำแผนกวิชาทั้งหมด</h1><p>ดูได้ฟรีโดยไม่ต้องสมัครสมาชิก เลือกชมสาขาที่คุณสนใจ</p></div><span class="count-label"><?= number_format($pagination->total) ?> วิดีโอ</span></div>
<?php if(!$departmentVideos): ?><div class="empty-state"><h2>เตรียมพบกับวิดีโอแนะนำแผนกวิชาเร็ว ๆ นี้</h2></div><?php else: ?><div class="course-grid"><?php require __DIR__.'/department-video-cards.php'; ?></div><?php endif; ?>
<?php require __DIR__.'/pagination.php'; ?></section>
<?php require __DIR__.'/layout-bottom.php'; ?>
