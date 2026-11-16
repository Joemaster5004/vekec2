<?php
$title='ตัวอย่างเกียรติบัตร';$certificateAssets=true;$isPreview=true;
require __DIR__.'/layout-top.php';
?>
<div class="page-heading print-hide"><div><div class="eyebrow dark">CERTIFICATE PREVIEW</div><h1>ตัวอย่างเกียรติบัตร</h1><p>แสดงแม่แบบที่บันทึกไว้ พร้อมข้อมูลผู้รับและหลักสูตรสมมติ</p></div><div class="button-row"><a href="/admin/certificate-design" class="button outline">กลับไปแก้ไข</a><button class="button" data-print><?= icon('download') ?>พิมพ์ / บันทึก PDF</button></div></div>
<?php require __DIR__.'/certificate-paper.php'; ?>
<?php require __DIR__.'/layout-bottom.php'; ?>
