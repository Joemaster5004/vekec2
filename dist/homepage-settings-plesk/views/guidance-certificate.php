<?php
use function App\{e,dateThai,url};
$title='เกียรติบัตรแนะแนวแผนกวิชา';$certificateAssets=true;require __DIR__.'/layout-top.php';
?>
<div class="page-heading print-hide"><div><h1>เกียรติบัตรแนะแนวแผนกวิชา</h1><p>กดพิมพ์ แล้วเลือก “บันทึกเป็น PDF” เพื่อดาวน์โหลดเกียรติบัตร</p></div><button class="button" data-print><?= icon('download') ?>ดาวน์โหลด / บันทึก PDF</button></div>
<?php $guidance=true;
if(empty($certificate['design_snapshot']))require __DIR__.'/guidance-certificate-legacy.php';
else {
    $certificate['course_title']=$certificate['video_title'].' · '.$certificate['department_name'];$certificate['hours']=0;
    require __DIR__.'/certificate-paper.php';
} ?>
<p class="print-hide"><a class="text-link" href="/department-videos">กลับไปชมวิดีโอแนะนำแผนกวิชา</a></p>
<?php require __DIR__.'/layout-bottom.php'; ?>
