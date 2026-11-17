<?php use function App\{e,levelLabel};$title='ค้นพบรายวิชา';require __DIR__.'/layout-top.php'; ?>
<div class="breadcrumb">พื้นที่การเรียนรู้ <?= icon('chevron') ?> <span>ค้นพบรายวิชา</span></div>
<?php require __DIR__.'/banner-carousel.php'; ?>
<section class="benefit-strip" aria-label="รูปแบบการเรียนรู้"><div><?= icon('school') ?><span><strong>เพื่อผู้เรียนทุกช่วงวัย</strong><small>ปวช. · ปวส. · ประชาชนทั่วไป</small></span></div><div><?= icon('play') ?><span><strong>เรียนตามจังหวะของคุณ</strong><small>วิดีโอ บทเรียน และกิจกรรมฝึกทักษะ</small></span></div><div><?= icon('award') ?><span><strong>ทุกการเรียนรู้มีความหมาย</strong><small>ติดตามความก้าวหน้าและเก็บความสำเร็จ</small></span></div></section>
<?php if($homeSections['department_videos'])require __DIR__.'/department-videos-section.php'; ?>
<?php require __DIR__.'/featured-courses.php'; ?>
<?php if($homeSections['course_catalog'])require __DIR__.'/course-catalog-section.php'; ?>
<section class="join-banner"><div class="join-icon"><?= icon('shield') ?></div><div><h3>เริ่มต้นเรียนรู้ได้ง่าย ๆ ในไม่กี่ขั้นตอน</h3><p>สมัครด้วยอีเมล หรือยืนยันตัวตนผ่าน ThaID เมื่อเปิดให้บริการ</p></div><a href="<?= $user?'/dashboard':'/register' ?>" class="button outline"><?= $user?'ไปที่การเรียนรู้ของฉัน':'สร้างบัญชีผู้เรียน' ?> <?= icon('arrow') ?></a></section>
<?php require __DIR__.'/layout-bottom.php'; ?>
