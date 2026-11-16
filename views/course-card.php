<?php

use function App\{e, levelLabel}; ?>
<article class="course-card"><?php if (isset($c['catalog_rank'])): ?><div class="course-ranking"><span class="course-rank"><span><?= (int)$c['catalog_rank'] ?></span>อันดับที่ <?= (int)$c['catalog_rank'] ?></span><span class="course-enrollments"><?= icon('users') ?> <?= number_format((int)$c['enrollment_count']) ?> ผู้ลงทะเบียน</span></div><?php endif; ?><a href="/courses/<?= (int)$c['id'] ?>" class="course-visual theme-<?= e($c['color']) ?>" tabindex="-1" aria-hidden="true"><span class="course-category"><?= e($c['category']) ?></span><span class="course-symbol"><?= icon($c['category'] === 'ดิจิทัลและเทคโนโลยี' ? 'code' : ($c['category'] === 'ธุรกิจและการเป็นผู้ประกอบการ' ? 'chart' : 'book')) ?></span>
        <div class="visual-bottom"><span><?= e($c['code']) ?></span><span>VOCATIONAL<br>LEARNING</span></div>
    </a>
    <div class="course-body">
        <div class="course-badges"><span class="pill"><?= e(levelLabel($c['level'])) ?></span><span class="free-label">เรียนฟรี</span></div>
        <h3><a href="/courses/<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a></h3>
        <p class="card-description"><?= e($c['description']) ?></p>
        <div class="teacher-line"><span class="mini-avatar"><?= icon('user') ?></span><?= e($c['teacher_name']) ?></div>
        <div class="card-meta"><span><?= icon('clock') ?> <?= (int)$c['hours'] ?> ชั่วโมง</span><span><?= icon('layers') ?> <?= (int)$c['lesson_count'] ?> บทเรียน</span><a href="/courses/<?= (int)$c['id'] ?>" aria-label="ดูรายวิชา <?= e($c['title']) ?>"><?= icon('arrow') ?></a></div>
    </div>
</article>