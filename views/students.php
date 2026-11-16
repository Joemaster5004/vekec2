<?php

use App\{DB, Learning, Pagination};
use function App\{e, csrfField, dateThai, levelLabel};

$title = 'ผู้เรียนและผลการเรียน';
require __DIR__ . '/layout-top.php';
$studentPagination = new Pagination((int)DB::one('SELECT COUNT(*) AS total FROM enrollments WHERE course_id=?', [$course['id']])['total'], 'students_page', 'students_per_page');
$students = DB::all('SELECT u.id,u.name,u.email,u.level,e.enrolled_at FROM enrollments e JOIN users u ON u.id=e.user_id WHERE e.course_id=? ORDER BY u.name,u.id LIMIT ? OFFSET ?', [$course['id'], $studentPagination->perPage, $studentPagination->offset]);
$submissionPagination = new Pagination((int)DB::one('SELECT COUNT(*) AS total FROM submissions s JOIN lessons l ON l.id=s.lesson_id WHERE l.course_id=?', [$course['id']])['total'], 'submissions_page', 'submissions_per_page');
$submissions = DB::all('SELECT s.*,u.name,l.title,l.pass_score FROM submissions s JOIN users u ON u.id=s.user_id JOIN lessons l ON l.id=s.lesson_id WHERE l.course_id=? ORDER BY s.score IS NULL DESC,s.submitted_at DESC,s.id DESC LIMIT ? OFFSET ?', [$course['id'], $submissionPagination->perPage, $submissionPagination->offset]); ?>
<div class="breadcrumb"><a href="/teach/<?= $course['id'] ?>">จัดการรายวิชา</a><?= icon('chevron') ?><span>ผู้เรียนและผลการเรียน</span></div>
<div class="page-heading">
    <div>
        <h1>ติดตามการเรียนรู้</h1>
        <p><?= e($course['title']) ?> · <?= $studentPagination->total ?> ผู้เรียน</p>
    </div><a href="#enroll-student" class="button"><?= icon('plus') ?>เพิ่มผู้เรียน</a>
</div>
<section class="panel table-panel">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>ผู้เรียน</th>
                    <th>ระดับ</th>
                    <th>ลงทะเบียนเมื่อ</th>
                    <th>ความคืบหน้า</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody><?php foreach ($students as $s): $p = Learning::completion((int)$s['id'], (int)$course['id']); ?><tr>
                        <td><strong><?= e($s['name']) ?></strong><small><?= e($s['email'] ?? 'ยืนยันตัวตนด้วย ThaID') ?></small></td>
                        <td><?= levelLabel($s['level']) ?></td>
                        <td><?= dateThai($s['enrolled_at']) ?></td>
                        <td>
                            <div class="progress-label"><span><?= $p['completed'] ?>/<?= $p['total'] ?> กิจกรรม</span><strong><?= $p['percent'] ?>%</strong></div><progress value="<?= $p['percent'] ?>" max="100"></progress>
                        </td>
                        <td><span class="pill <?= $p['percent'] === 100 ? 'status-published' : '' ?>"><?= $p['percent'] === 100 ? 'ผ่านหลักสูตร' : ($p['completed'] ? 'กำลังเรียน' : 'ยังไม่จบกิจกรรม') ?></span></td>
                    </tr><?php endforeach; ?><?php if (!$students): ?><tr>
                        <td colspan="5" class="table-empty">ยังไม่มีผู้เรียนลงทะเบียน</td>
                    </tr><?php endif; ?></tbody>
        </table>
    </div><?php $pagination = $studentPagination;
            require __DIR__ . '/pagination.php'; ?>
</section>
<section class="panel" id="submissions">
    <div class="panel-heading">
        <h2>ใบงานและแบบฝึกหัดที่ส่ง</h2><span class="pill"><?= $submissionPagination->total ?> งาน</span>
    </div><?php if (!$submissions): ?><p class="form-hint">ยังไม่มีงานที่ส่งในรายวิชานี้</p><?php endif; ?><?php foreach ($submissions as $s): ?><details class="submission-detail" <?= $s['score'] === null ? 'open' : '' ?>>
            <summary><span><strong><?= e($s['name']) ?></strong> · <?= e($s['title']) ?></span><span class="pill"><?= $s['score'] === null ? 'รอตรวจ' : $s['score'] . '/100' ?></span></summary>
            <div class="submission-body">
                <p class="form-hint">ส่งเมื่อ <?= dateThai($s['submitted_at']) ?> · คะแนนผ่าน <?= $s['pass_score'] ?>%</p>
                <div class="prose"><?= nl2br(e($s['body'])) ?></div><?php if ($s['file_path']): ?><a class="attachment" href="/submissions/<?= $s['id'] ?>/file"><?= icon('download') ?>ดาวน์โหลดไฟล์งาน</a><?php endif; ?><form method="post" action="/teach/submissions/<?= $s['id'] ?>/grade" class="form-stack"><?= csrfField() ?><div class="form-grid"><label>คะแนน (0–100)<input type="number" name="score" required min="0" max="100" value="<?= e($s['score']) ?>"></label><label>ข้อเสนอแนะ<textarea name="feedback" maxlength="10000"><?= e($s['feedback']) ?></textarea></label></div><button class="button"><?= icon('check') ?>บันทึกผลตรวจ</button></form>
            </div>
        </details><?php endforeach; ?><?php $pagination = $submissionPagination;
                                        require __DIR__ . '/pagination.php'; ?>
</section>
<?php if ($students): ?><section class="panel">
        <h2>ความคืบหน้ารายกิจกรรม</h2>
        <p class="form-hint">แสดงผู้เรียนตามหน้าตารางด้านบน เปิดชื่อเพื่อดูวิดีโอที่รับชม กิจกรรมที่ผ่าน และคะแนนสอบ</p><?php foreach ($students as $s): ?><details class="submission-detail">
                <summary><strong><?= e($s['name']) ?></strong></summary>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>กิจกรรม</th>
                                <th>สถานะ</th>
                                <th>รับชมแล้ว</th>
                                <th>คะแนนสอบสูงสุด</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach (Learning::lessons((int)$course['id'], (int)$s['id']) as $l): $best = $l['kind'] === 'quiz' ? DB::one('SELECT MAX(score) AS score FROM attempts WHERE user_id=? AND lesson_id=?', [$s['id'], $l['id']])['score'] : null; ?><tr>
                                    <td><?= e($l['title']) ?></td>
                                    <td><?= $l['completed_at'] ? 'ผ่านแล้ว' : 'ยังไม่ผ่าน' ?></td>
                                    <td><?= $l['kind'] === 'video' ? (int)($l['watched_seconds'] ?? 0) . ' / ' . $l['duration_seconds'] . ' วินาที' : '—' ?></td>
                                    <td><?= $best === null ? '—' : e($best) . '%' ?></td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            </details><?php endforeach; ?>
    </section><?php endif; ?>
<div class="two-panels" id="enroll-student">
    <section class="panel">
        <h2>ลงทะเบียนให้ผู้เรียนที่มีบัญชีแล้ว</h2>
        <p class="form-hint">เพิ่มผู้เรียนเข้ารายวิชานี้ด้วยอีเมลที่ลงทะเบียนไว้</p>
        <form method="post" action="/teach/<?= $course['id'] ?>/enroll" class="form-stack"><?= csrfField() ?><label>อีเมลผู้เรียน<input type="email" name="email" required></label><button class="button"><?= icon('plus') ?>ลงทะเบียนรายวิชา</button></form>
    </section>
    <section class="panel">
        <h2>สร้างบัญชีและลงทะเบียนให้ผู้เรียน</h2>
        <form method="post" action="/teach/<?= $course['id'] ?>/register" class="form-stack"><?= csrfField() ?><label>ชื่อ–นามสกุล<input name="name" maxlength="180" required></label><label>อีเมล<input type="email" name="email" required maxlength="190"></label><label>รหัสผ่านเริ่มต้น<input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"><small>อย่างน้อย 12 ตัวอักษร แจ้งให้ผู้เรียนโดยตรงและให้เปลี่ยนหลังเข้าสู่ระบบ</small></label><label>ระดับผู้เรียน<select name="level">
                    <option value="public">ประชาชนทั่วไป</option>
                    <option value="voc1">ปวช.</option>
                    <option value="voc2">ปวส.</option>
                </select></label><button class="button">สร้างบัญชีและลงทะเบียน</button></form>
    </section>
</div><?php require __DIR__ . '/layout-bottom.php'; ?>