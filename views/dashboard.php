<?php

use App\{DB, Learning};
use function App\{e, csrfField};

$title = 'การเรียนรู้ของฉัน';
require __DIR__ . '/layout-top.php';
$enrollments = DB::all('SELECT c.*,e.enrolled_at FROM enrollments e JOIN courses c ON c.id=e.course_id WHERE e.user_id=? ORDER BY e.enrolled_at DESC', [$user['id']]);
$finished = 0;
foreach ($enrollments as &$c) {
    $c['progress'] = Learning::completion((int)$user['id'], (int)$c['id']);
    if ($c['progress']['percent'] === 100) $finished++;
}
unset($c);
$certCount = DB::one('SELECT COUNT(*) AS n FROM certificates WHERE user_id=?', [$user['id']])['n']; ?>
<div class="page-heading">
    <div>
        <div class="eyebrow dark">MY LEARNING JOURNEY</div>
        <h1>สวัสดี, <?= e($user['name']) ?></h1>
        <p>มาเรียนรู้สิ่งใหม่ และก้าวไปอีกขั้นในวันนี้</p>
    </div><a href="/" class="button outline"><?= icon('plus') ?>ค้นหารายวิชา</a>
</div>
<?php if (in_array($user['role'], ['teacher', 'admin'])): ?><section class="join-banner">
        <div class="join-icon"><?= icon('layers') ?></div>
        <div>
            <h3>พื้นที่สำหรับครูผู้สอน</h3>
            <p>สร้างรายวิชา จัดการผู้เรียน และติดตามผลการเรียน</p>
        </div><a href="/teach" class="button">จัดการรายวิชา <?= icon('arrow') ?></a>
    </section><?php endif; ?>
<div class="stats-grid">
    <div class="stat-card"><span><?= icon('book') ?>ลงทะเบียนแล้ว</span><strong><?= count($enrollments) ?><small>รายวิชา</small></strong></div>
    <div class="stat-card"><span><?= icon('clock') ?>กำลังเรียน</span><strong><?= count($enrollments) - $finished ?><small>รายวิชา</small></strong></div>
    <div class="stat-card"><span><?= icon('check') ?>เรียนครบแล้ว</span><strong><?= $finished ?><small>รายวิชา</small></strong></div>
    <div class="stat-card"><span><?= icon('award') ?>ใบประกาศนียบัตร</span><strong><?= (int)$certCount ?><small>ฉบับ</small></strong></div>
</div>
<div class="section-heading">
    <h2>รายวิชาของฉัน</h2>
</div><?php if (!$enrollments): ?><div class="empty-state"><?= icon('book') ?><h3>การเรียนรู้ครั้งใหม่กำลังรอคุณอยู่</h3>
        <p>เลือกรายวิชาที่สนใจ ดูหัวข้อเนื้อหา แล้วเริ่มต้นบทเรียนแรก</p><a class="button" href="/">สำรวจรายวิชา <?= icon('arrow') ?></a>
    </div><?php else: ?><div class="learning-grid"><?php foreach ($enrollments as $c): ?><article class="panel learning-card"><span class="pill"><?= e($c['category']) ?></span>
                <h3><a href="/courses/<?= $c['id'] ?>"><?= e($c['title']) ?></a></h3>
                <div class="progress-label"><span><?= $c['progress']['completed'] ?>/<?= $c['progress']['total'] ?> กิจกรรม</span><strong><?= $c['progress']['percent'] ?>%</strong></div><progress max="100" value="<?= $c['progress']['percent'] ?>"></progress><a class="button <?= $c['progress']['percent'] === 100 ? 'outline' : '' ?> full" href="/courses/<?= $c['id'] ?>"><?= $c['progress']['percent'] === 100 ? 'ดูผลการเรียน / รับใบประกาศ' : 'เรียนต่อ' ?> <?= icon('arrow') ?></a>
            </article><?php endforeach; ?></div><?php endif; ?><?php require __DIR__ . '/layout-bottom.php'; ?>