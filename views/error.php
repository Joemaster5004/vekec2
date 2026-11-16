<?php

use function App\e;

$title = 'ไม่สามารถทำรายการได้';
require __DIR__ . '/layout-top.php'; ?><div class="empty-state error-state"><span class="error-code"><?= (int)$status ?></span>
    <h1><?= e($message) ?></h1>
    <p>ข้อมูลที่บันทึกสำเร็จแล้วจะยังอยู่ในระบบ</p>
    <div class="button-row"><button class="button" data-back>ย้อนกลับ</button><a class="button outline" href="/">กลับหน้าหลัก</a></div>
</div><?php require __DIR__ . '/layout-bottom.php'; ?>