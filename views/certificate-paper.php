<?php
use App\CertificateDesign;
use function App\{e,dateThai,url};
$design=CertificateDesign::fromCertificate($certificate);
if ($design === null) { require __DIR__.'/certificate-legacy.php'; return; }
$isPreview=$isPreview??false;
$signatureUrl=$isPreview?'/admin/certificate-design/signature':'/'.(!empty($verify)?'verify':'certificate').'/'.$certificate['code'].'/signature';
$emblemUrl=$isPreview?'/admin/certificate-design/emblem':'/'.(!empty($verify)?'verify':'certificate').'/'.$certificate['code'].'/emblem';
$length=static fn(string $text): int => preg_match_all('/./us',$text);
?>
<article class="certificate-sheet cert-style-<?= e($design['style']) ?><?= $design['emblem_size']>6?' cert-large-emblem':'' ?>" style="--cert-accent:<?= e($design['accent']) ?>;--signature-width:<?= (int)$design['signature_width'] ?>cqw;--emblem-size:<?= (float)$design['emblem_size'] ?>cqw" aria-label="<?= $isPreview?'ตัวอย่างเกียรติบัตร':e($design['heading']) ?>">
    <div class="cert-sheet-frame">
        <div class="cert-sheet-topline"><span>VEKEC · VOCATIONAL LEARNING</span><span class="cert-registration-number"><?php if ($isPreview): ?>เลขที่ ศอช. 0101/2569 (ตัวอย่าง)<?php elseif (!empty($certificate['registration_number'])): ?>เลขที่ <?= e($certificate['registration_number']) ?><?php else: ?>CERTIFICATE OF COMPLETION<?php endif; ?></span></div>
        <div class="cert-sheet-emblem"><span class="cert-default-emblem" <?= $design['emblem_path']?'hidden':'' ?>><?= icon('award') ?></span><img <?= $design['emblem_path']?'src="'.e($emblemUrl).'"':'hidden' ?> alt="ตราใบประกาศ" data-saved-emblem="<?= $design['emblem_path']?e($emblemUrl):'' ?>"></div>
        <p data-cert-fit class="cert-sheet-institution <?= $length($certificate['institution'])>70?'cert-copy-long':'' ?>" data-cert-text="institution"><?= e($certificate['institution']) ?></p>
        <h2 data-cert-fit class="cert-sheet-heading" data-cert-text="heading"><?= e($design['heading']) ?></h2>
        <p data-cert-fit class="cert-sheet-subtitle" data-cert-text="subtitle"><?= e($design['subtitle']) ?></p>
        <p data-cert-fit class="cert-sheet-intro" data-cert-text="intro"><?= e($design['intro']) ?></p>
        <h3 data-cert-fit class="cert-sheet-recipient <?= $length($certificate['recipient_name'])>65?'cert-copy-long':'' ?>"><?= e($certificate['recipient_name']) ?></h3>
        <p data-cert-fit class="cert-sheet-completion" data-cert-text="completion"><?= e($design['completion']) ?></p>
        <h4 data-cert-fit class="cert-sheet-course <?= $length($certificate['course_title'])>90?'cert-copy-long':'' ?>"><?= e($certificate['course_title']) ?></h4>
        <p class="cert-sheet-hours" <?= $design['show_hours']?'':'hidden' ?>>ระยะเวลาการเรียนรู้ <?= (int)$certificate['hours'] ?> ชั่วโมง</p>
        <p class="cert-sheet-date">ให้ไว้ ณ วันที่ <?= dateThai($certificate['issued_at']) ?></p>
        <div class="cert-sheet-signature cert-signature-<?= e($design['signature_alignment']) ?>">
            <div class="cert-signature-image"><img <?= $design['signature_path']?'src="'.e($signatureUrl).'"':'hidden' ?> alt="ลายเซ็นผู้ลงนามรับรอง" data-saved-signature="<?= $design['signature_path']?e($signatureUrl):'' ?>"></div>
            <p data-cert-fit class="cert-director-name" data-cert-text="director_name"><?= e($design['director_name']?:($isPreview?'ชื่อ–นามสกุลผู้อำนวยการ':'')) ?></p>
            <p data-cert-fit class="cert-director-position" data-cert-text="director_position"><?= e($design['director_position']) ?></p>
        </div>
        <div class="cert-sheet-reference"><?php if ($isPreview): ?><span>ตัวอย่างสำหรับตรวจสอบรูปแบบ · ไม่ใช่เกียรติบัตรที่ออกจริง</span><?php else: ?><span>เลขอ้างอิง <?= e($certificate['code']) ?></span><span><?= e(url('/verify/'.$certificate['code'])) ?></span><?php endif; ?></div>
    </div>
</article>
