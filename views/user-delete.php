<?php
use function App\{e,csrfField};
$title='ลบสมาชิก';
require __DIR__.'/layout-top.php';
$self=(int)$member['id']===(int)$user['id'];
?>
<div class="breadcrumb"><a href="/admin#users">บัญชีผู้ใช้งาน</a><?= icon('chevron') ?><span>ลบสมาชิก</span></div>
<div class="page-heading"><div><div class="eyebrow dark">USER ACCOUNTS</div><h1>ลบสมาชิก</h1><p>ตรวจสอบบัญชีก่อนยืนยันการลบ</p></div></div>
<section class="panel member-form-panel">
    <h2><?= e($member['name']) ?></h2><p><?= e($member['email']??'บัญชี ThaID') ?></p>
    <?php if ($self): ?>
        <p class="member-error">ไม่สามารถลบบัญชีของตนเองได้</p>
    <?php elseif ($references): ?>
        <p class="member-error">บัญชีนี้มีข้อมูลที่เกี่ยวข้อง จึงลบไม่ได้ กรุณาระงับบัญชีเพื่อรักษาประวัติ</p>
        <?php foreach ($references as $label=>$count): ?><div class="info-row"><span><?= e($label) ?></span><strong><?= number_format($count) ?> รายการ</strong></div><?php endforeach; ?>
        <div class="button-row member-delete-actions"><a class="button outline" href="/admin/users/<?= (int)$member['id'] ?>/edit">แก้ไขสถานะบัญชี</a></div>
    <?php else: ?>
        <p class="form-hint">บัญชีนี้ยังไม่มีรายวิชาหรือประวัติการเรียนที่เกี่ยวข้อง เมื่อลบแล้วจะเข้าสู่ระบบไม่ได้ และไม่สามารถเรียกคืนบัญชีนี้ได้</p>
        <form method="post" action="/admin/users/<?= (int)$member['id'] ?>/delete" class="form-stack">
            <?= csrfField() ?>
            <label class="check-label"><input type="checkbox" name="confirm_delete" value="1" required>ยืนยันว่าต้องการลบบัญชี <?= e($member['name']) ?></label>
            <button class="button danger">ยืนยันลบสมาชิก</button>
        </form>
    <?php endif; ?>
    <div class="button-row member-delete-actions"><a class="button outline" href="/admin#users">กลับไปรายชื่อ</a></div>
</section>
<?php require __DIR__.'/layout-bottom.php'; ?>
