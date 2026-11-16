<?php

use App\{DB, Learning};
use function App\{e, csrfField};

$title = $course ? 'แก้ไขรายวิชา' : 'สร้างรายวิชา';
$richEditor = true;
$aiDraftAssets = true;
require __DIR__ . '/layout-top.php';
$locked = $course && (bool)DB::one('SELECT id FROM enrollments WHERE course_id=? LIMIT 1', [$course['id']]); ?>
<div class="breadcrumb"><a href="/teach">จัดการรายวิชา</a><?= icon('chevron') ?><span><?= $title ?></span></div>
<div class="page-heading">
    <div>
        <div class="eyebrow dark">COURSE BUILDER</div>
        <h1><?= $title ?></h1>
        <p><?= $course ? e($course['title']) : 'เริ่มจากข้อมูลรายวิชา แล้วเพิ่มกิจกรรมการเรียนรู้' ?></p>
    </div><?php if ($course): ?><div class="button-row"><a href="/courses/<?= $course['id'] ?>" class="button outline">ดูตัวอย่าง</a><a href="/teach/<?= $course['id'] ?>/students" class="button"><?= icon('users') ?>ผู้เรียน</a></div><?php endif; ?>
</div>
<div class="editor-grid">
    <div>
        <section class="panel">
            <h2>ข้อมูลรายวิชา</h2>
            <form method="post" action="<?= $course ? '/teach/' . $course['id'] . '/save' : '/teach/courses' ?>" class="form-stack"><?= csrfField() ?><label>ชื่อรายวิชา<input name="title" required maxlength="240" value="<?= e($course['title'] ?? '') ?>" placeholder="เช่น การเขียนเว็บไซต์เบื้องต้น"></label>
                <div class="form-grid"><label>รหัสรายวิชา<input name="code" required maxlength="40" value="<?= e($course['code'] ?? '') ?>" placeholder="เช่น WEB-101"></label><label>หมวดหมู่<select name="category" required>
                            <option value="">เลือกหมวดหมู่</option><?php foreach (\App\Category::options() as $option): ?><option value="<?= e($option['name']) ?>" <?= ($course['category'] ?? '') === $option['name'] ? 'selected' : '' ?>><?= e($option['name']) ?></option><?php endforeach; ?>
                        </select><?php if ($user['role'] === 'admin'): ?><a class="text-link" href="/admin/categories">จัดการหมวดหมู่</a><?php else: ?><small>หากต้องการหมวดหมู่ใหม่ กรุณาติดต่อผู้ดูแลระบบ</small><?php endif; ?></label></div><label>คำอธิบายและสิ่งที่ผู้เรียนจะได้รับ<textarea name="description" required maxlength="30000"><?= e($course['description'] ?? '') ?></textarea></label>
                <div class="form-grid"><label>ระดับผู้เรียน<select name="level"><?php foreach (['all' => 'ทุกระดับ', 'voc1' => 'ปวช.', 'voc2' => 'ปวส.', 'public' => 'ประชาชนทั่วไป'] as $key => $label): ?><option value="<?= $key ?>" <?= ($course['level'] ?? 'all') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><label>ระยะเวลาเรียนรวม (ชั่วโมง)<input type="number" name="hours" min="1" max="1000" required value="<?= (int)($course['hours'] ?? 3) ?>"></label></div><label>สีประจำรายวิชา<select name="color"><?php foreach (['teal' => 'เขียว', 'orange' => 'ส้ม', 'blue' => 'ฟ้า', 'purple' => 'ม่วง', 'rose' => 'ชมพู', 'olive' => 'เขียวมะกอก'] as $key => $label): ?><option value="<?= $key ?>" <?= ($course['color'] ?? 'teal') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><label class="check-label"><input type="checkbox" name="sequential" <?= ($course['sequential'] ?? 1) ? 'checked' : '' ?>>ต้องเรียนและผ่านกิจกรรมตามลำดับ</label><button class="button"><?= icon('check') ?><?= $course ? 'บันทึกข้อมูลรายวิชา' : 'สร้างรายวิชาและเพิ่มบทเรียน' ?></button>
            </form>
        </section>
        <?php if ($course): ?><section class="panel" id="curriculum">
                <div class="panel-heading">
                    <h2>บทเรียนและกิจกรรม</h2><span class="pill">เรียงตามลำดับที่เพิ่ม</span>
                </div><?php if ($locked): ?><div class="notice">มีผู้เรียนแล้ว โครงสร้างบทเรียนและข้อสอบจะคงไว้เพื่อรักษาประวัติการเรียน หากต้องการปรับหลักสูตรให้สร้างรายวิชารุ่นใหม่</div><?php endif; ?><?php $lessons = Learning::lessons((int)$course['id']);
                                                                                                                                                                                                            foreach ($lessons as $i => $l): ?><div class="lesson-row"><span class="lesson-number"><?= $i + 1 ?></span>
                        <div class="lesson-row-text"><strong><?= e($l['title']) ?></strong><small><?= ['article' => 'บทเรียน', 'video' => 'วิดีโอ', 'assignment' => 'ใบงาน / แบบฝึกหัด', 'quiz' => 'แบบทดสอบ'][$l['kind']] ?><?= $l['require_watch'] ? ' · บังคับดูจนจบ' : '' ?></small></div>
                        <div class="button-row"><?php if ($l['kind'] === 'quiz'): ?><a class="button small outline" href="/teach/quizzes/<?= $l['id'] ?>">ข้อสอบ</a><?php endif; ?><a class="icon-button" href="/learn/<?= $l['id'] ?>" aria-label="ดูตัวอย่างบทเรียน"><?= icon('play') ?></a><?php if (!$locked): ?><form method="post" action="/teach/lessons/<?= $l['id'] ?>/delete" data-confirm="ลบบทเรียนนี้? รายวิชาจะกลับเป็นฉบับร่าง"><?= csrfField() ?><button class="text-button danger-text">ลบ</button></form><?php endif; ?></div>
                    </div><?php endforeach; ?><?php if (!$lessons): ?><p class="form-hint">ยังไม่มีบทเรียน เพิ่มกิจกรรมแรกด้านล่าง</p><?php endif; ?>
            </section>
            <?php if (!$locked): ?><section class="panel">
                    <h2>เพิ่มกิจกรรมการเรียนรู้</h2>
                    <form class="form-stack" method="post" action="/teach/<?= $course['id'] ?>/lessons" enctype="multipart/form-data" id="lesson-builder"><?= csrfField() ?><label>ประเภทกิจกรรม<select name="kind" id="lesson-kind">
                                <option value="article">บทเรียน / เอกสารเนื้อหา</option>
                                <option value="video">วิดีโอ</option>
                                <option value="assignment">ใบงาน / แบบฝึกหัดที่ครูตรวจ</option>
                                <option value="quiz">ข้อสอบปรนัย ตรวจอัตโนมัติ</option>
                            </select></label><label>ชื่อกิจกรรม<input name="title" required maxlength="240"></label><input type="hidden" name="body_format" value="text">
                        <div class="rich-field"><label for="lesson-body" id="lesson-body-label">เนื้อหา / คำชี้แจง</label><textarea id="lesson-body" class="rich-editor" data-course-id="<?= (int)$course['id'] ?>" name="body" maxlength="200000" rows="8" placeholder="เขียนเนื้อหา คำชี้แจง ใบงาน หรือแบบฝึกหัด พร้อมแทรกรูปภาพ"></textarea><small class="form-hint">จัดรูปแบบหัวข้อ สี รายการ และแทรกรูปภาพ JPG, PNG หรือ WebP ไม่เกิน 5 MB ต่อภาพ</small></div><label>เอกสารแนบ (ถ้ามี)<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPG, PNG ไม่เกิน 10 MB</small></label>
                        <div data-for-kind="video" hidden class="form-stack"><label>อัปโหลดวิดีโอ MP4<input type="file" name="video" accept=".mp4,video/mp4"><small>ไม่เกิน 256 MB ระบบอ่านความยาวไฟล์ให้อัตโนมัติ</small></label><label>หรือใส่ลิงก์วิดีโอ HTTPS<input type="url" name="external_url" placeholder="https://www.youtube.com/watch?v=..."></label><label>ความยาววิดีโอภายนอก (วินาที)<input type="number" name="duration_seconds" min="0" max="43200" value="0"><small>กรอกให้ตรงกับวิดีโอจริง สำหรับการบังคับดู MP4 ภายนอกหรือ YouTube</small></label><label class="check-label"><input type="checkbox" name="require_watch">บังคับดูวิดีโอจนจบก่อนผ่านกิจกรรมนี้</label></div>
                        <div data-for-kind="quiz assignment" hidden class="form-stack"><label>คะแนนผ่าน (%)<input name="pass_score" type="number" min="1" max="100" value="60"></label></div>
                        <div data-for-kind="quiz" hidden class="form-grid"><label>เวลาสอบ (นาที)<input name="time_limit" type="number" min="1" max="240" value="30"></label><label>จำนวนครั้งที่ทำได้<input name="max_attempts" type="number" min="1" max="20" value="3"></label></div><button class="button"><?= icon('plus') ?>เพิ่มกิจกรรม</button>
                    </form>
                </section><?php endif;
                    endif; ?>
    </div>
    <aside>
        <?php if (!$locked && $course): require __DIR__ . '/ai-draft.php'; endif; ?>
        <section class="panel sticky-panel">
            <h3>สถานะรายวิชา</h3><?php if ($course): ?><span class="pill status-published"><?= ['draft' => 'ฉบับร่าง', 'published' => 'เปิดรับสมัคร', 'archived' => 'ปิดรับสมัคร'][$course['status']] ?></span>
                <p class="form-hint">เปิดรับสมัครเมื่อมีเนื้อหาครบและตรวจสอบข้อสอบเรียบร้อย การปิดรับสมัครยังให้ผู้เรียนเดิมเข้าเรียนได้</p>
                <form method="post" action="/teach/<?= $course['id'] ?>/status" class="form-stack"><?= csrfField() ?><label>เปลี่ยนสถานะ<select name="status"><?php foreach (['draft' => 'ฉบับร่าง', 'published' => 'เปิดรับสมัคร', 'archived' => 'ปิดรับสมัคร'] as $key => $label): ?><option value="<?= $key ?>" <?= $course['status'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><button class="button full">อัปเดตสถานะ</button></form><?php else: ?><p class="form-hint">รายวิชาใหม่จะบันทึกเป็นฉบับร่าง สามารถเพิ่มบทเรียนและข้อสอบก่อนเปิดรับสมัคร</p><?php endif; ?>
        </section>
    </aside>
</div><?php require __DIR__ . '/layout-bottom.php'; ?>