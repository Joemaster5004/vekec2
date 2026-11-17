<?php use function App\{e,levelLabel};$title='ค้นพบรายวิชา';require __DIR__.'/layout-top.php'; ?>
<div class="breadcrumb">พื้นที่การเรียนรู้ <?= icon('chevron') ?> <span>ค้นพบรายวิชา</span></div>
<?php require __DIR__.'/banner-carousel.php'; ?>
<section class="benefit-strip" aria-label="รูปแบบการเรียนรู้"><div><?= icon('school') ?><span><strong>เพื่อผู้เรียนทุกช่วงวัย</strong><small>ปวช. · ปวส. · ประชาชนทั่วไป</small></span></div><div><?= icon('play') ?><span><strong>เรียนตามจังหวะของคุณ</strong><small>วิดีโอ บทเรียน และกิจกรรมฝึกทักษะ</small></span></div><div><?= icon('award') ?><span><strong>ทุกการเรียนรู้มีความหมาย</strong><small>ติดตามความก้าวหน้าและเก็บความสำเร็จ</small></span></div></section>
<?php require __DIR__.'/department-videos-section.php'; ?>
<?php require __DIR__.'/featured-courses.php'; ?>
<section id="courses" class="catalog-section"><div class="section-heading"><div><div class="eyebrow dark">EXPLORE YOUR POSSIBILITIES</div><h2>ค้นพบรายวิชาที่ใช่สำหรับคุณ</h2><p>เริ่มต้นความสนใจใหม่ หรือต่อยอดทักษะที่มีอยู่</p></div><span class="count-label"><?= count($courses) ?> รายวิชา<?= count($courses)===100?'แรก':'' ?></span></div>
<form method="get" action="/#courses" class="filter-bar catalog-filters">
  <div class="search-input"><?= icon('search') ?><input name="q" value="<?= e($q) ?>" placeholder="ค้นหาชื่อรายวิชา หรือทักษะที่สนใจ..." aria-label="ค้นหารายวิชา"></div>
  <div class="catalog-filter-field">
    <label for="department">แผนกวิชา</label>
    <select id="department" name="category" aria-describedby="department-hint">
      <option value="">รายวิชาทั้งหมด · ทุกแผนกวิชา</option>
      <?php foreach($categories as $cat): ?><option value="<?= e($cat['category']) ?>" <?= $category===$cat['category']?'selected':'' ?>><?= e($cat['category']) ?></option><?php endforeach; ?>
    </select>
    <span id="department-hint" class="sr-only">เมื่อเลือกแผนกวิชา ระบบจะแสดงผลการค้นหาอัตโนมัติ</span>
  </div>
  <div class="catalog-filter-field">
    <label for="level">ระดับผู้เรียน</label>
    <select id="level" name="level"><option value="">ทุกระดับการศึกษา</option><option value="voc1" <?= $level==='voc1'?'selected':'' ?>>ปวช.</option><option value="voc2" <?= $level==='voc2'?'selected':'' ?>>ปวส.</option><option value="public" <?= $level==='public'?'selected':'' ?>>ประชาชนทั่วไป</option></select>
  </div>
  <button class="button">ค้นหา</button>
</form>
<?php if(!$courses): ?><div class="empty-state"><?= icon('search') ?><h3><?= $q||$category||$level?'ไม่พบรายวิชาที่ตรงกับการค้นหา':'เตรียมพบกับรายวิชาใหม่เร็ว ๆ นี้' ?></h3><p><?= $q||$category||$level?'ลองเปลี่ยนคำค้นหาหรือตัวกรองระดับการศึกษา':'ครูผู้สอนสามารถสร้างและเปิดรับสมัครรายวิชาได้จากเมนูจัดการรายวิชา' ?></p><a href="/" class="button outline">ดูรายวิชาทั้งหมด</a></div><?php else: ?><div class="course-grid"><?php foreach($courses as $c): require __DIR__.'/course-card.php';endforeach; ?></div><?php endif; ?>
</section><section class="join-banner"><div class="join-icon"><?= icon('shield') ?></div><div><h3>เริ่มต้นเรียนรู้ได้ง่าย ๆ ในไม่กี่ขั้นตอน</h3><p>สมัครด้วยอีเมล หรือยืนยันตัวตนผ่าน ThaID เมื่อเปิดให้บริการ</p></div><a href="<?= $user?'/dashboard':'/register' ?>" class="button outline"><?= $user?'ไปที่การเรียนรู้ของฉัน':'สร้างบัญชีผู้เรียน' ?> <?= icon('arrow') ?></a></section>
<?php require __DIR__.'/layout-bottom.php'; ?>
