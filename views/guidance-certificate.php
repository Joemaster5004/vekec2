<?php
use function App\{e,dateThai,url};
$title='เกียรติบัตรแนะแนวแผนกวิชา';$certificateAssets=true;require __DIR__.'/layout-top.php';
?>
<div class="page-heading print-hide"><div><h1>เกียรติบัตรแนะแนวแผนกวิชา</h1><p>กดพิมพ์ แล้วเลือก “บันทึกเป็น PDF” เพื่อดาวน์โหลดเกียรติบัตร</p></div><button class="button" data-print><?= icon('download') ?>ดาวน์โหลด / บันทึก PDF</button></div>
<article class="certificate-paper"><div class="certificate-frame"><div class="certificate-topline"><span>VEKEC · DEPARTMENT GUIDANCE</span><span>กิจกรรมแนะแนวการศึกษา</span></div><div class="cert-emblem"><?= icon('award') ?></div><p class="certificate-institution"><?= e($certificate['institution']) ?></p><h2>เกียรติบัตร</h2><div class="certificate-english">CERTIFICATE OF PARTICIPATION</div><p class="cert-given">ให้ไว้เพื่อแสดงว่า</p><h3 class="recipient-name"><?= e($certificate['recipient_name']) ?></h3><p>ได้เข้าร่วมรับชมวิดีโอแนะแนวการศึกษา</p><h4 class="certificate-course"><?= e($certificate['video_title']) ?></h4><p><?= e($certificate['department_name']) ?></p><p class="cert-date">ให้ไว้ ณ วันที่ <?= dateThai($certificate['issued_at']) ?></p><div class="cert-signature"><span><?= e($certificate['signatory']) ?></span><small>กิจกรรมแนะแนวการศึกษา</small></div><div class="cert-reference"><span>เลขอ้างอิง <?= e($certificate['code']) ?></span><span><?= e(url('/guidance-certificate/'.$certificate['code'])) ?></span></div></div></article>
<p class="print-hide"><a class="text-link" href="/#department-videos">กลับไปชมวิดีโอแนะนำแผนกวิชา</a></p>
<?php require __DIR__.'/layout-bottom.php'; ?>
