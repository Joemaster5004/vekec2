<?php
use function App\{e,csrfField};
$title = 'จัดการสาขาวิชา';
require __DIR__.'/layout-top.php';
?>
<div class="breadcrumb"><a href="/admin">จัดการระบบ</a><?= icon('chevron') ?><span>จัดการสาขาวิชา</span></div>
<div class="page-heading">
    <div><div class="eyebrow dark">COURSE CATEGORIES</div><h1>จัดการสาขาวิชา</h1><p>จัดระเบียบรายวิชา เพื่อให้ผู้เรียนค้นหาสิ่งที่สนใจได้ง่ายขึ้น</p></div>
    <a href="#add-category" class="button"><?= icon('plus') ?>เพิ่มสาขาวิชา</a>
</div>
<div class="editor-grid category-manager">
    <section class="panel">
        <div class="panel-heading"><h2>สาขาวิชาทั้งหมด</h2><span class="pill"><?= $pagination->total ?> สาขาวิชา</span></div>
        <?php if (!$categories): ?>
            <div class="empty-state"><?= icon('layers') ?><h3>ยังไม่มีสาขาวิชา</h3><p>เพิ่มสาขาวิชาแรกเพื่อให้ครูเลือกใช้ตอนสร้างรายวิชา</p></div>
        <?php endif; ?>
        <?php foreach ($categories as $category): $id=(int)$category['id']; $count=(int)$category['course_count']; ?>
            <article class="category-item" id="category-<?= $id ?>">
                <form method="post" action="/admin/categories/<?= $id ?>/save" class="category-edit-form">
                    <?= csrfField() ?>
                    <label for="category-name-<?= $id ?>">ชื่อสาขาวิชา <span class="pill"><?= $count ?> รายวิชา</span></label>
                    <div class="category-edit-fields">
                        <input id="category-name-<?= $id ?>" name="name" required maxlength="100" value="<?= e($category['name']) ?>">
                        <button class="button outline small"><?= icon('check') ?>บันทึก</button>
                    </div>
                </form>
                <details class="category-delete">
                    <summary>ลบสาขาวิชา</summary>
                    <form method="post" action="/admin/categories/<?= $id ?>/delete" class="form-stack" data-confirm="<?= $count?'ยืนยันการย้ายรายวิชาไปสาขาวิชาที่เลือกและลบสาขาวิชานี้?':'ยืนยันการลบสาขาวิชานี้?' ?>">
                        <?= csrfField() ?>
                        <?php if ($count): ?>
                            <p class="form-hint">มี <?= $count ?> รายวิชาในสาขาวิชานี้ รวมรายวิชาฉบับร่างและปิดรับสมัคร กรุณาเลือกสาขาวิชาปลายทาง ระบบจะย้ายรายวิชาก่อนลบสาขาวิชา</p>
                            <label for="replacement-<?= $id ?>">ย้ายรายวิชาไปที่
                                <select id="replacement-<?= $id ?>" name="replacement_id" required>
                                    <option value="">เลือกสาขาวิชาปลายทาง</option>
                                    <?php foreach ($categoryOptions as $target): if ((int)$target['id']===$id) continue; ?>
                                        <option value="<?= (int)$target['id'] ?>"><?= e($target['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <?php if (count($categoryOptions)<2): ?><p class="form-hint">กรุณาเพิ่มสาขาวิชาปลายทางก่อนลบสาขาวิชานี้</p><?php endif; ?>
                        <?php else: ?>
                            <p class="form-hint">สาขาวิชานี้ยังไม่มีรายวิชา สามารถลบได้ทันที</p>
                        <?php endif; ?>
                        <button class="button danger small" <?= $count && count($categoryOptions)<2?'disabled':'' ?>><?= $count?'ย้ายรายวิชาและลบสาขาวิชา':'ยืนยันลบสาขาวิชา' ?></button>
                    </form>
                </details>
            </article>
        <?php endforeach; ?>
        <?php require __DIR__.'/pagination.php'; ?>
    </section>
    <aside>
        <section class="panel" id="add-category">
            <h2>เพิ่มสาขาวิชาใหม่</h2>
            <p class="form-hint">รายการใหม่จะแสดงในหน้าสร้างรายวิชาและตัวกรองค้นหาทันที</p>
            <form method="post" action="/admin/categories" class="form-stack">
                <?= csrfField() ?>
                <label for="new-category-name">ชื่อสาขาวิชา<input id="new-category-name" name="name" required maxlength="100" placeholder="เช่น เกษตรและอาหาร"></label>
                <small class="form-hint">ไม่เกิน 100 ตัวอักษร และไม่ซ้ำกับชื่อที่มีอยู่</small>
                <button class="button full"><?= icon('plus') ?>เพิ่มสาขาวิชา</button>
            </form>
        </section>
        <section class="panel"><h3>อัปเดตรายวิชาที่เกี่ยวข้อง</h3><p class="form-hint">การแก้ชื่อสาขาวิชามีผลกับทุกรายวิชาที่ใช้สาขาวิชานั้น โดยคงเนื้อหา ผู้เรียน และประวัติการเรียนไว้</p></section>
    </aside>
</div>
<?php require __DIR__.'/layout-bottom.php'; ?>
