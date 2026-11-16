<?php
use App\CertificateDesign;
use function App\{e,csrfField};
$title='ออกแบบเกียรติบัตร';$certificateAssets=true;$certificateDesigner=true;
require __DIR__.'/layout-top.php';
$values=$values??$design;
$value=static fn(string $key): string => is_scalar($values[$key]??null)?(string)$values[$key]:'';
$emblemSource=$value('emblem_source')?:'keep';
$emblemSize=is_numeric($values['emblem_size']??null)?max(3,min(12,(float)$values['emblem_size'])):$design['emblem_size'];
$certificate=CertificateDesign::preview($design);$isPreview=true;
?>
<div class="breadcrumb"><a href="/admin">จัดการระบบ</a><?= icon('chevron') ?><span>ออกแบบเกียรติบัตร</span></div>
<div class="page-heading"><div><div class="eyebrow dark">CERTIFICATE DESIGN</div><h1>ออกแบบเกียรติบัตร</h1><p>ออกแบบแม่แบบ ใส่ลายเซ็น และกำหนดผู้ลงนามรับรอง</p></div><a href="/admin/certificate-design/preview" target="_blank" rel="noopener" class="button outline"><?= icon('file') ?>ดูแบบที่บันทึก / พิมพ์</a></div>
<?php if (!empty($error)): ?><div class="member-error" role="alert"><?= e($error) ?> · ยังไม่ได้บันทึกการเปลี่ยนแปลง<?php if(!empty($_FILES['signature']['name'])||!empty($_FILES['emblem']['name'])): ?> กรุณาเลือกไฟล์ภาพอีกครั้ง<?php endif; ?></div><?php endif; ?>
<div class="certificate-designer">
    <form method="post" action="/admin/certificate-design" enctype="multipart/form-data" id="certificate-design-form" class="cert-design-controls">
        <?= csrfField() ?><input type="hidden" name="MAX_FILE_SIZE" value="2097152">
        <section class="panel">
            <h2>รูปแบบและสี</h2>
            <div class="form-stack">
                <label for="cert-style">รูปแบบกรอบ<select id="cert-style" name="style" required><?php foreach(CertificateDesign::STYLES as $key=>$label): ?><option value="<?= $key ?>" <?= $value('style')===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
                <label for="cert-accent">สีหลักของเกียรติบัตร<input id="cert-accent" type="color" name="accent" value="<?= e(preg_match('/^#[a-f0-9]{6}$/iD',$value('accent'))?$value('accent'):$design['accent']) ?>" required></label>
                <div class="cert-palette" aria-label="ชุดสีแนะนำ"><button type="button" data-cert-color="#375a39" style="--swatch:#375a39" aria-label="สีเขียว"></button><button type="button" data-cert-color="#24476c" style="--swatch:#24476c" aria-label="สีน้ำเงิน"></button><button type="button" data-cert-color="#8b692a" style="--swatch:#8b692a" aria-label="สีทอง"></button><button type="button" data-cert-color="#754663" style="--swatch:#754663" aria-label="สีม่วง"></button></div>
            </div>
        </section>
        <section class="panel" id="certificate-emblem"><h2>ตราใบประกาศ</h2><div class="form-stack">
            <label for="cert-emblem-source">เลือกตราที่ใช้<select id="cert-emblem-source" name="emblem_source"><?php foreach(['keep'=>'ใช้ตราปัจจุบัน','upload'=>'อัปโหลดรูปตรา','url'=>'ใช้ URL รูปภาพภายนอก','default'=>'กลับไปใช้ตราเริ่มต้น'] as $key=>$label): ?><option value="<?= $key ?>" <?= $emblemSource===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <div data-emblem-source="upload" class="form-stack cert-emblem-inputs"><label for="cert-emblem-file">ไฟล์ตราใบประกาศ<input id="cert-emblem-file" type="file" name="emblem" accept="image/png,image/jpeg,image/webp" aria-describedby="cert-emblem-help"></label></div>
            <div data-emblem-source="url" class="form-stack cert-emblem-inputs"><label for="cert-emblem-url">URL รูปภาพ<input id="cert-emblem-url" type="url" name="emblem_url" maxlength="2048" value="<?= e($value('emblem_url')) ?>" placeholder="https://example.org/logo.png" autocomplete="off"></label><button type="button" class="button outline small" id="cert-load-emblem">โหลดภาพตัวอย่าง</button><p class="form-hint">ใช้ลิงก์รูปภาพ HTTPS โดยตรง เมื่อบันทึก ระบบจะเก็บรูปไว้กับแม่แบบเพื่อให้ตราคงเดิมแม้เว็บต้นทางเปลี่ยน</p></div>
            <p class="form-hint" id="cert-emblem-help">รองรับ PNG, JPG และ WebP ไม่เกิน 2 MB แนะนำภาพพื้นหลังโปร่งใสและตัดขอบว่างแล้ว</p>
            <p class="member-error" id="cert-emblem-error" role="alert" hidden></p><p class="form-hint" id="cert-emblem-status" role="status" hidden></p>
            <label for="cert-emblem-size">ขนาดตรา <output for="cert-emblem-size" id="cert-emblem-size-label"><?= (float)$emblemSize ?>%</output><input id="cert-emblem-size" type="range" name="emblem_size" min="3" max="12" step="0.1" value="<?= (float)$emblemSize ?>"></label>
            <p class="form-hint">ปรับได้ 3–12% ของความกว้างใบประกาศ ภาพคงสัดส่วนเดิม และปรับผลในตัวอย่างทันที</p>
        </div></section>
        <section class="panel"><h2>ข้อความบนเกียรติบัตร</h2><div class="form-stack">
            <?php foreach(['institution','heading','subtitle','intro','completion'] as $key): [$max,$label]=CertificateDesign::TEXT_FIELDS[$key]; ?>
                <label for="cert-<?= $key ?>"><?= e($label) ?><?= $key==='subtitle'?' (ไม่บังคับ)':'' ?><input id="cert-<?= $key ?>" name="<?= $key ?>" maxlength="<?= $max ?>" value="<?= e($value($key)) ?>" <?= $key==='subtitle'?'':'required' ?>></label>
            <?php endforeach; ?>
            <label class="check-label"><input type="checkbox" name="show_hours" value="1" <?= !empty($values['show_hours'])?'checked':'' ?>>แสดงจำนวนชั่วโมงเรียน</label>
            <p class="form-hint">ชื่อผู้รับ ชื่อหลักสูตร วันที่ และเลขอ้างอิง จะใช้ข้อมูลจริงเมื่อออกเกียรติบัตร</p>
        </div></section>
        <section class="panel"><h2>ลายเซ็นและผู้อำนวยการ</h2><div class="form-stack">
            <label for="cert-director_name">ชื่อ–นามสกุลผู้อำนวยการ<input id="cert-director_name" name="director_name" maxlength="120" value="<?= e($value('director_name')) ?>" placeholder="ระบุคำนำหน้า ชื่อ และนามสกุล" required></label>
            <label for="cert-director_position">ตำแหน่ง<input id="cert-director_position" name="director_position" maxlength="120" value="<?= e($value('director_position')) ?>" required></label>
            <label for="cert-signature-file">ภาพลายเซ็น<input id="cert-signature-file" name="signature" type="file" accept="image/png,image/jpeg,image/webp" aria-describedby="cert-signature-help"></label>
            <p class="form-hint" id="cert-signature-help">รองรับ PNG, JPG และ WebP ไม่เกิน 2 MB แนะนำ PNG พื้นหลังโปร่งใสที่ตัดขอบว่างรอบลายเซ็นแล้ว หากไม่เลือกไฟล์ใหม่จะใช้ภาพเดิม</p>
            <p class="member-error" id="cert-file-error" role="alert" hidden></p>
            <?php if($design['signature_path']): ?><label class="check-label"><input type="checkbox" name="remove_signature" value="1" <?= isset($values['remove_signature'])?'checked':'' ?>>นำลายเซ็นออกจากแม่แบบ</label><?php endif; ?>
            <label for="cert-signature-alignment">ตำแหน่งผู้ลงนาม<select id="cert-signature-alignment" name="signature_alignment" required><?php foreach(CertificateDesign::ALIGNMENTS as $key=>$label): ?><option value="<?= $key ?>" <?= $value('signature_alignment')===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <label for="cert-signature-width">ขนาดภาพลายเซ็น <output for="cert-signature-width" id="cert-signature-size"><?= (int)$value('signature_width') ?>%</output><input id="cert-signature-width" type="range" name="signature_width" min="12" max="30" step="1" value="<?= (int)$value('signature_width') ?>"></label>
        </div></section>
        <section class="panel cert-design-save"><p class="form-hint">การเปลี่ยนแม่แบบใช้กับเกียรติบัตรที่ออกใหม่ ใบที่ออกแล้วจะคงรูปแบบ ข้อมูล และลายเซ็นเดิม</p><button class="button full" type="submit"><?= icon('check') ?>บันทึกแม่แบบเกียรติบัตร</button><p class="form-hint" id="cert-unsaved" role="status" hidden>มีการเปลี่ยนแปลงที่ยังไม่ได้บันทึก</p></section>
    </form>
    <div class="cert-design-preview"><section class="panel"><div class="panel-heading"><h2>ภาพตัวอย่าง</h2><span class="pill">A4 · แนวนอน</span></div><p class="form-hint">ข้อความและลายเซ็นในภาพตัวอย่างเปลี่ยนตามแบบที่กำลังแก้ไข</p><noscript><p class="form-hint">เปิด JavaScript เพื่อดูตัวอย่างขณะแก้ไข หรือบันทึกแล้วกด “ดูแบบที่บันทึก / พิมพ์”</p></noscript><?php require __DIR__.'/certificate-paper.php'; ?><p class="cert-preview-caption">ตัวอย่างเท่านั้น · ชื่อผู้รับและหลักสูตรเป็นข้อมูลสมมติ</p></section></div>
</div>
<?php require __DIR__.'/layout-bottom.php'; ?>
