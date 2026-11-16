<?php
use function App\{e, csrfField};
$title = 'จัดการ Banner';
require __DIR__.'/layout-top.php';
?>
<div class="page-heading"><div><div class="eyebrow dark">HOMEPAGE BANNERS</div><h1>จัดการ Banner</h1><p>ภาพประชาสัมพันธ์ใน Carousel หน้าแรก</p></div><div class="button-row"><a href="/" class="button outline">ดูหน้าแรก</a><a href="/admin" class="button outline">กลับหน้าจัดการระบบ</a></div></div>
<section class="panel"><h2>ขนาดภาพที่แนะนำ: 1600 × 640 พิกเซล (5:2)</h2><p class="form-hint">รองรับ JPG, PNG และ WebP ขนาดไม่เกิน 5 MB ต่อภาพ และไม่เกิน 20 ล้านพิกเซล ภาพจะแสดงเต็มภาพในกรอบ 5:2 หากสัดส่วนต่างกันจะมีพื้นที่ว่างด้านข้างหรือด้านบน–ล่าง ควรใช้ข้อความขนาดใหญ่เพื่อให้อ่านได้บนมือถือ</p><p class="form-hint">เรียงจากเลขลำดับน้อยไปมาก หากลำดับเท่ากันจะแสดงภาพที่เพิ่มก่อน เมื่อไม่มี Banner ที่เปิดแสดง ระบบใช้ Banner เริ่มต้น</p></section>
<?php $forms = array_merge([null], $banners); foreach ($forms as $banner): $id = $banner ? (int)$banner['id'] : 'new'; ?>
<section class="panel" id="banner-<?= $id ?>">
    <div class="panel-heading"><h2><?= $banner ? 'แก้ไข Banner: '.e($banner['title']) : 'เพิ่ม Banner' ?></h2><?php if ($banner): ?><span class="pill"><?= $banner['active'] ? 'แสดงบนหน้าแรก' : 'ซ่อน' ?></span><?php endif; ?></div>
    <form method="post" action="/admin/banners<?= $banner ? '/'.$id.'/save' : '' ?>" enctype="multipart/form-data" class="form-stack" data-banner-form>
        <?= csrfField() ?>
        <label>ชื่อ / คำอธิบายภาพ<input name="title" maxlength="240" required value="<?= e($banner['title'] ?? '') ?>" placeholder="เช่น เปิดรับสมัครหลักสูตรใหม่"></label>
        <label><?= $banner ? 'เปลี่ยนภาพ (เว้นว่างเพื่อใช้ภาพเดิม)' : 'ภาพ Banner' ?><input type="file" name="image" accept="image/jpeg,image/png,image/webp" <?= $banner ? '' : 'required' ?> aria-describedby="image-hint-<?= $id ?>" data-banner-image></label>
        <p class="form-hint" id="image-hint-<?= $id ?>">แนะนำ 1600 × 640 พิกเซล (5:2) · JPG / PNG / WebP · สูงสุด 5 MB</p>
        <div class="banner-preview" data-banner-preview <?= $banner ? '' : 'hidden' ?>><?php if ($banner): ?><img src="/media/banners/<?= $id ?>" alt="<?= e($banner['title']) ?>"><?php endif; ?></div>
        <p class="form-hint" data-banner-dimensions role="status"><?= $banner ? 'ขนาดภาพปัจจุบัน: '.(int)$banner['width'].' × '.(int)$banner['height'].' พิกเซล' : '' ?></p>
        <label>ลิงก์เมื่อคลิก (ไม่บังคับ)<input name="link_url" maxlength="2000" value="<?= e($banner['link_url'] ?? '') ?>" placeholder="/courses/1 หรือ https://example.com"></label>
        <label>ลำดับการแสดง<input type="number" name="sort_order" min="0" max="9999" required value="<?= (int)($banner['sort_order'] ?? 0) ?>"></label>
        <label class="check-label"><input type="checkbox" name="active" value="1" <?= !$banner || $banner['active'] ? 'checked' : '' ?>>แสดงบนหน้าแรก</label>
        <button class="button" type="submit"><?= $banner ? 'บันทึกการแก้ไข' : 'เพิ่ม Banner' ?></button>
    </form>
    <?php if ($banner): ?><form method="post" action="/admin/banners/<?= $id ?>/delete" data-confirm="ยืนยันลบ Banner นี้? ภาพจะถูกนำออกจากหน้าแรก" class="banner-delete"><?= csrfField() ?><button class="button danger" type="submit">ลบ Banner</button></form><?php endif; ?>
</section>
<?php endforeach; require __DIR__.'/layout-bottom.php'; ?>
