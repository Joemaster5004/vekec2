<?php
use App\Users;
use function App\{e,csrfField};
$editing = $member !== null;
$title = $editing ? 'แก้ไขสมาชิก' : 'เพิ่มสมาชิก';
require __DIR__.'/layout-top.php';
$self = $editing && (int)$member['id'] === (int)$user['id'];
$thaid = $editing && $member['thaid'];
$values = $values ?? $member ?? ['role'=>'student','level'=>'public','active'=>'1'];
$value = static fn(string $key): string => is_scalar($values[$key] ?? null) ? (string)$values[$key] : '';
?>
<div class="breadcrumb"><a href="/admin#users">บัญชีผู้ใช้งาน</a><?= icon('chevron') ?><span><?= e($title) ?></span></div>
<div class="page-heading"><div><div class="eyebrow dark">USER ACCOUNTS</div><h1><?= e($title) ?></h1><p><?= $editing?'ปรับปรุงข้อมูล บทบาท และสถานะของสมาชิก':'สร้างบัญชีให้ผู้เรียน ครูผู้สอน หรือผู้ดูแลระบบ' ?></p></div><a href="/admin#users" class="button outline">กลับไปรายชื่อ</a></div>
<section class="panel member-form-panel">
    <?php if (!empty($error)): ?><div class="member-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= $editing?'/admin/users/'.(int)$member['id'].'/save':'/admin/users' ?>" class="form-stack">
        <?= csrfField() ?>
        <div class="form-grid">
            <label for="member-name">ชื่อ–นามสกุล<input id="member-name" name="name" required maxlength="180" autocomplete="off" value="<?= e($thaid?$member['name']:$value('name')) ?>" <?= $thaid?'readonly':'' ?>></label>
            <label for="member-email">อีเมล<input id="member-email" name="email" type="email" maxlength="190" autocomplete="off" value="<?= e($thaid?$member['email']:$value('email')) ?>" <?= $thaid?'readonly':'required' ?>></label>
        </div>
        <?php if ($thaid): ?>
            <p class="form-hint"><?= icon('shield') ?>บัญชีนี้ยืนยันตัวตนผ่าน ThaID ชื่อและอีเมลจึงแก้ไขไม่ได้ และไม่ใช้รหัสผ่านอีเมล</p>
        <?php else: ?>
            <label for="member-password"><?= $editing?'รหัสผ่านใหม่ (เว้นว่างเพื่อใช้รหัสผ่านเดิม)':'รหัสผ่าน' ?>
                <span class="password-field"><input id="member-password" name="password" type="password" autocomplete="new-password" <?= $editing?'':'required' ?> minlength="12" maxlength="72"><button type="button" data-toggle-password="member-password" aria-label="แสดงรหัสผ่าน">แสดง</button></span>
                <small>ใช้อย่างน้อย 12 ตัวอักษร และไม่เกิน 72 ไบต์ อักษรไทยใช้หลายไบต์ต่อตัวอักษร</small>
            </label>
            <?php if (!empty($error)): ?><p class="form-hint">หากต้องการตั้งรหัสผ่าน กรุณากรอกใหม่อีกครั้ง</p><?php endif; ?>
        <?php endif; ?>
        <div class="form-grid">
            <label for="member-role">บทบาท<select id="member-role" name="role" required <?= $self?'disabled':'' ?>><?php foreach (Users::ROLES as $key=>$label): ?><option value="<?= $key ?>" <?= ($self?'admin':$value('role'))===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <label for="member-level">ระดับผู้เรียน<select id="member-level" name="level" required><?php foreach (Users::LEVELS as $key=>$label): ?><option value="<?= $key ?>" <?= $value('level')===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        </div>
        <?php if ($self): ?><input type="hidden" name="role" value="admin"><input type="hidden" name="active" value="1"><p class="form-hint">บัญชีของคุณเป็นผู้ดูแลระบบและเปิดใช้งานอยู่ ไม่สามารถเปลี่ยนสิทธิ์หรือระงับบัญชีของตนเองได้</p>
        <?php else: ?><label class="check-label"><input type="checkbox" name="active" value="1" <?= $value('active')==='1'?'checked':'' ?>>เปิดใช้งานบัญชี</label><p class="form-hint">บัญชีที่ระงับจะเข้าสู่ระบบไม่ได้ แต่ยังเก็บประวัติการเรียนและรายวิชาไว้</p><?php endif; ?>
        <?php if ($editing): ?><p class="form-hint">ชื่อบนใบประกาศที่ออกแล้วจะคงเดิม การแก้ระดับผู้เรียนมีผลต่อการสมัครรายวิชาใหม่</p><?php endif; ?>
        <div class="button-row"><button class="button"><?= icon($editing?'check':'plus') ?><?= $editing?'บันทึกการแก้ไข':'เพิ่มสมาชิก' ?></button><a href="/admin#users" class="button outline">ยกเลิก</a></div>
    </form>
</section>
<?php require __DIR__.'/layout-bottom.php'; ?>
