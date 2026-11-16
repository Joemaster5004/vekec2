<?php

use App\DB;
use function App\{e, csrfField, dateThai};

$title = 'ข้อมูลส่วนตัว';
require __DIR__ . '/layout-top.php';
$record = DB::one('SELECT * FROM users WHERE id=?', [$user['id']]); ?>
<div class="page-heading">
    <div>
        <div class="eyebrow dark">MY ACCOUNT</div>
        <h1>ข้อมูลส่วนตัว</h1>
        <p>ดูแลข้อมูลให้ถูกต้องก่อนลงทะเบียนเรียนและรับใบประกาศ</p>
    </div>
</div>
<div class="two-panels">
    <section class="panel">
        <h2>ข้อมูลผู้เรียน</h2>
        <form method="post" action="/profile" class="form-stack"><?= csrfField() ?><label>ชื่อ–นามสกุล<input name="name" required maxlength="180" value="<?= e($user['name']) ?>" <?= $record['thaid_subject'] ? 'readonly' : '' ?>></label><?php if ($record['thaid_subject']): ?><p class="success-text"><?= icon('shield') ?>ยืนยันตัวตนด้วย ThaID</p><?php endif; ?><label>อีเมล<input type="text" value="<?= e($user['email'] ?? 'บัญชี ThaID — ไม่มีอีเมลในระบบ') ?>" readonly></label><label>ระดับผู้เรียน<select name="level"><?php foreach (['public' => 'ประชาชนทั่วไป', 'voc1' => 'ปวช.', 'voc2' => 'ปวส.'] as $key => $label): ?><option value="<?= $key ?>" <?= $user['level'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <p class="form-hint">สามารถเปลี่ยนระดับก่อนลงทะเบียนรายวิชาครั้งแรก ชื่อบนใบประกาศที่ออกแล้วจะไม่เปลี่ยนตามข้อมูลใหม่</p><button class="button">บันทึกข้อมูล</button>
        </form>
    </section>
    <section class="panel">
        <h2>ความปลอดภัยของบัญชี</h2><?php if ($record['password_hash']): ?><form method="post" action="/profile/password" class="form-stack"><?= csrfField() ?><label>รหัสผ่านปัจจุบัน<input type="password" name="current_password" required maxlength="72" autocomplete="current-password"></label><label>รหัสผ่านใหม่<input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"><small>อย่างน้อย 12 ตัวอักษร และไม่เกิน 72 ไบต์</small></label><button class="button outline">เปลี่ยนรหัสผ่าน</button></form><?php else: ?><p class="prose">บัญชีนี้ยืนยันตัวตนผ่าน ThaID และไม่มีรหัสผ่านอีเมลในระบบ LMS</p><?php endif; ?><div class="info-row"><span>วันที่สมัคร</span><strong><?= dateThai($user['created_at']) ?></strong></div>
    </section>
</div><?php require __DIR__ . '/layout-bottom.php'; ?>