<?php use function App\e; ?>
<?php foreach($departmentVideos as $v): ?>
<article class="panel guidance-card">
<div class="guidance-card-art"><?= icon('play') ?><span>สำรวจอนาคตของคุณ</span></div>
<span class="pill"><?= e($v['department']) ?></span>
<h3><?= e($v['title']) ?></h3>
<p><?= e($v['description']) ?></p>
<div class="guidance-card-stats">
<span><?= icon('users') ?>เข้าชม <?= number_format((int)($v['view_count']??0)) ?> ครั้ง</span>
<span><?= icon('award') ?>รับเกียรติบัตร <?= number_format((int)$v['certificate_count']) ?> คน</span>
</div>
<a class="button outline" href="/department-videos/<?= (int)$v['id'] ?>"><?= icon('play') ?>ชมวิดีโอแนะนำ</a>
</article>
<?php endforeach; ?>
