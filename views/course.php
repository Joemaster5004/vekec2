<?php

use App\{Learning, Auth};
use function App\{e, csrfField, levelLabel};

$title = $course['title'];
require __DIR__ . '/layout-top.php';
$enrolled = $user && Learning::enrolled((int)$user['id'], (int)$course['id']);
$manager = $user && ($user['role'] === 'admin' || ($user['role'] === 'teacher' && (int)$course['teacher_id'] === (int)$user['id']));
$progress = Learning::completion((int)($user['id'] ?? 0), (int)$course['id']);
?>
<div class="breadcrumb"><a href="/">ค้นพบรายวิชา</a><?= icon('chevron') ?><span>รายละเอียดรายวิชา</span></div>
<section class="course-heading theme-<?= e($course['color']) ?>">
    <div class="eyebrow dark"><?= e($course['code']) ?> · <?= e($course['category']) ?></div>
    <h1><?= e($course['title']) ?></h1>
    <div class="course-heading-meta"><span><?= icon('user') ?> <?= e($course['teacher_name']) ?></span><span><?= icon('clock') ?> <?= (int)$course['hours'] ?> ชั่วโมง</span><span><?= icon('layers') ?> <?= count($lessons) ?> บทเรียน</span></div>
</section>
<div class="content-with-aside">
    <div>
        <section class="panel">
            <h2>เกี่ยวกับรายวิชา</h2>
            <div class="prose"><?= nl2br(e($course['description'])) ?></div>
        </section>
        <section class="panel">
            <div class="panel-heading">
                <h2>เนื้อหาที่คุณจะได้เรียนรู้</h2><span class="pill"><?= count($lessons) ?> กิจกรรม</span>
            </div>
            <p class="form-hint">ตรวจสอบหัวข้อทั้งหมดได้ก่อนลงทะเบียน <?= $course['sequential'] ? '· รายวิชานี้เรียนตามลำดับกิจกรรม' : '' ?></p>
            <div class="lesson-list"><?php foreach ($lessons as $i => $l): $l['sequential'] = $course['sequential'];
                                            $unlocked = $manager || ($enrolled && Learning::unlocked($l, (int)$user['id'])); ?><div class="lesson-row"><span class="lesson-number <?= $l['completed_at'] ? 'done' : '' ?>"><?= $l['completed_at'] ? icon('check') : str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <div class="lesson-row-text"><strong><?= e($l['title']) ?></strong><small><?= ['article' => 'บทเรียน', 'video' => 'วิดีโอ', 'assignment' => 'ใบงาน / แบบฝึกหัด', 'quiz' => 'แบบทดสอบปรนัย'][$l['kind']] ?><?= $l['require_watch'] ? ' · ต้องดูจนจบ' : '' ?></small></div><?php if ($unlocked): ?><a class="button small outline" href="/learn/<?= $l['id'] ?>"><?= $l['completed_at'] ? 'ทบทวน' : 'เข้าเรียน' ?></a><?php else: ?><?= icon('lock') ?><?php endif; ?>
                    </div><?php endforeach; ?></div>
        </section>
    </div>
    <aside>
        <section class="panel enrollment-panel"><span class="large-price">เรียนฟรี</span>
            <p>เปิดโอกาสให้ทุกคนเข้าถึงทักษะอาชีพ</p>
            <div class="info-row"><span>เหมาะสำหรับ</span><strong><?= e(levelLabel($course['level'])) ?></strong></div>
            <div class="info-row"><span>รูปแบบการเรียน</span><strong>ออนไลน์</strong></div>
            <div class="info-row"><span>ใบประกาศนียบัตร</span><strong>เมื่อผ่านเกณฑ์</strong></div>
            <?php if ($manager): ?><a class="button full" href="/teach/<?= $course['id'] ?>">จัดการรายวิชา <?= icon('settings') ?></a><?php elseif ($enrolled): ?><div class="progress-label"><strong>ความคืบหน้าของคุณ</strong><span><?= $progress['percent'] ?>%</span></div><progress value="<?= $progress['percent'] ?>" max="100"></progress>
                <p class="form-hint">สำเร็จ <?= $progress['completed'] ?> จาก <?= $progress['total'] ?> กิจกรรม</p><?php if ($lessons): $next = $lessons[0];
                                                                                                                                            foreach ($lessons as $l) if (!$l['completed_at']) {
                                                                                                                                                $next = $l;
                                                                                                                                                break;
                                                                                                                                            } ?><a class="button full" href="/learn/<?= $next['id'] ?>">เรียนต่อ <?= icon('play') ?></a><?php endif; ?><?php if ($progress['percent'] === 100): ?><form method="post" action="/courses/<?= $course['id'] ?>/certificate"><?= csrfField() ?><button class="button outline full"><?= icon('award') ?>รับใบประกาศนียบัตร</button></form><?php endif; ?><?php elseif (!$user): ?><a href="/login" class="button full">เข้าสู่ระบบเพื่อลงทะเบียน <?= icon('arrow') ?></a><?php elseif ($course['status'] === 'published' && $user['role'] === 'student'): ?><form method="post" action="/courses/<?= $course['id'] ?>/enroll"><?= csrfField() ?><button class="button full">ลงทะเบียนเรียน <?= icon('arrow') ?></button></form><?php else: ?><p class="form-hint">รายวิชานี้ยังไม่พร้อมให้บัญชีของคุณลงทะเบียน</p><?php endif; ?>
                <div class="enrollment-note"><?= icon('shield') ?><span>ผลการเรียนและความคืบหน้าจะบันทึกในบัญชีของคุณ</span></div>
        </section>
    </aside>
</div><?php require __DIR__ . '/layout-bottom.php'; ?>