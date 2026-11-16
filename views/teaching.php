<?php

use App\{DB, Pagination};
use function App\{e, csrfField};

$title = 'จัดการรายวิชา';
require __DIR__ . '/layout-top.php';
$courseWhere = $user['role'] === 'admin' ? '' : ' WHERE c.teacher_id=?';
$courseParams = $user['role'] === 'admin' ? [] : [$user['id']];
$pagination = new Pagination((int)DB::one('SELECT COUNT(*) AS total FROM courses c' . $courseWhere, $courseParams)['total']);
$courses = DB::all('SELECT c.*,(SELECT COUNT(*) FROM enrollments e WHERE e.course_id=c.id) AS students,(SELECT COUNT(*) FROM lessons l WHERE l.course_id=c.id) AS lesson_count FROM courses c' . $courseWhere . ' ORDER BY c.id DESC LIMIT ? OFFSET ?', [...$courseParams, $pagination->perPage, $pagination->offset]);
?>
<div class="page-heading">
    <div>
        <div class="eyebrow dark">TEACHING WORKSPACE</div>
        <h1>จัดการรายวิชา</h1>
        <p>เปลี่ยนความรู้ของคุณ ให้เป็นโอกาสของผู้เรียน</p>
    </div><a href="/teach/new" class="button"><?= icon('plus') ?>สร้างรายวิชา</a>
</div>
<?php if (!$courses): ?><div class="empty-state"><?= icon('layers') ?><h3>เริ่มต้นรายวิชาแรกของคุณ</h3>
        <p>สร้างโครงสร้างรายวิชา เพิ่มเนื้อหา แล้วเปิดรับผู้เรียน</p><a href="/teach/new" class="button">สร้างรายวิชา</a>
    </div><?php else: ?><div class="panel table-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>รายวิชา</th>
                        <th>สถานะ</th>
                        <th>กิจกรรม</th>
                        <th>ผู้เรียน</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($courses as $c): ?><tr>
                            <td><a class="table-title" href="/teach/<?= $c['id'] ?>"><?= e($c['title']) ?></a><small><?= e($c['code']) ?> · <?= e($c['category']) ?></small></td>
                            <td><span class="pill <?= $c['status'] === 'published' ? 'status-published' : '' ?>"><?= ['draft' => 'ฉบับร่าง', 'published' => 'เปิดรับสมัคร', 'archived' => 'ปิดรับสมัคร'][$c['status']] ?></span></td>
                            <td><?= $c['lesson_count'] ?></td>
                            <td><?= $c['students'] ?></td>
                            <td>
                                <div class="button-row"><a class="button small outline" href="/teach/<?= $c['id'] ?>">แก้ไข</a><a class="button small outline" href="/teach/<?= $c['id'] ?>/students"><?= icon('users') ?>ผู้เรียน</a></div>
                            </td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div><?php require __DIR__ . '/pagination.php'; ?>
    </div><?php endif; ?><?php require __DIR__ . '/layout-bottom.php'; ?>