<?php
use function App\e;
$featuredSections = [
    ['id'=>'latest-courses','title'=>'รายวิชาใหม่ล่าสุด','eyebrow'=>'LATEST COURSES','description'=>'อัปเดตทักษะกับรายวิชาที่เพิ่มเข้ามาล่าสุด','courses'=>$latestCourses],
    ['id'=>'popular-courses','title'=>'รายวิชาที่มีผู้สนใจเรียนมากที่สุด','eyebrow'=>'TOP 3 COURSES','description'=>'3 อันดับแรก เรียงตามจำนวนผู้ลงทะเบียนเรียน','courses'=>$popularCourses],
];
foreach ($featuredSections as $featuredSection): ?>
<section id="<?= $featuredSection['id'] ?>" class="featured-section" aria-labelledby="<?= $featuredSection['id'] ?>-title">
  <div class="section-heading">
    <div>
      <div class="eyebrow dark"><?= $featuredSection['eyebrow'] ?></div>
      <h2 id="<?= $featuredSection['id'] ?>-title"><?= $featuredSection['title'] ?></h2>
      <p><?= $featuredSection['description'] ?></p>
    </div>
    <a class="featured-more" href="#courses">ดูรายวิชาทั้งหมด <?= icon('arrow') ?></a>
  </div>
  <?php if (!$featuredSection['courses']): ?>
    <p class="featured-empty">ยังไม่มีรายวิชาที่เปิดรับสมัครในขณะนี้</p>
  <?php else: ?>
    <div class="course-grid">
    <?php foreach ($featuredSection['courses'] as $featuredIndex=>$c):
        if ($featuredSection['id']==='popular-courses') $c['catalog_rank']=$featuredIndex+1;
        require __DIR__.'/course-card.php';
    endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endforeach; ?>
