<?php

use App\{DB, RichText};
use function App\{e, csrfField};

$title = 'คลังข้อสอบ';
$richEditor = true;
$aiDraftAssets = true;
require __DIR__ . '/layout-top.php';
$questions = DB::all('SELECT * FROM questions WHERE lesson_id=? ORDER BY id', [$lesson['id']]);
$locked = (bool)DB::one('SELECT id FROM enrollments WHERE course_id=? LIMIT 1', [$lesson['course_id']]); ?>
<div class="breadcrumb"><a href="/teach/<?= $lesson['course_id'] ?>">จัดการรายวิชา</a><?= icon('chevron') ?><span>คลังข้อสอบ</span></div>
<div class="page-heading">
    <div>
        <h1><?= e($lesson['title']) ?></h1>
        <p>ข้อสอบปรนัย 4 ตัวเลือก · <?= count($questions) ?> ข้อ</p>
    </div>
</div>
<div class="editor-grid">
    <div>
        <section class="panel">
            <h2>ข้อสอบในกิจกรรมนี้</h2><?php if (!$questions): ?><p class="form-hint">ยังไม่มีข้อสอบ เพิ่มเองหรือนำเข้าจากไฟล์ได้ด้านล่าง</p><?php endif; ?><?php foreach ($questions as $i => $q): ?><div class="question-preview">
                    <h3>ข้อที่ <?= $i + 1 ?></h3>
                    <div class="rich-content question-prompt"><?= RichText::render($q['prompt'], $q['content_format'] ?? 'text') ?></div><?php foreach (json_decode($q['options'], true) as $index => $option): ?><div class="question-option-preview <?= $index === (int)$q['answer'] ? 'correct-option' : '' ?>"><strong><?= ['ก', 'ข', 'ค', 'ง'][$index] ?>.</strong>
                            <div class="rich-content"><?= RichText::render($option, $q['content_format'] ?? 'text') ?></div><?php if ($index === (int)$q['answer']): ?><span class="sr-only">คำตอบที่ถูกต้อง</span><span aria-hidden="true">✓</span><?php endif; ?>
                        </div><?php endforeach; ?><?php if (!empty($q['explanation'])): ?><div class="question-explanation"><strong>คำอธิบายเฉลย</strong>
                            <div class="rich-content"><?= RichText::render($q['explanation'], $q['content_format'] ?? 'text') ?></div>
                        </div><?php endif; ?>
                </div><?php endforeach; ?>
        </section><?php if (!$locked): ?><section class="panel">
                <h2>เพิ่มข้อสอบด้วยตัวเอง</h2>
                <form method="post" action="/teach/quizzes/<?= $lesson['id'] ?>/questions" class="form-stack"><?= csrfField() ?><input type="hidden" name="content_format" value="text">
                    <p class="form-hint">จัดรูปแบบข้อความ แทรกรูปภาพ หรือวางและลากรูปจากเครื่องลงในช่องเขียนได้</p>
                    <div class="rich-field"><label for="question-prompt" id="question-prompt-label">คำถาม</label><textarea id="question-prompt" class="rich-editor" data-course-id="<?= (int)$lesson['course_id'] ?>" name="prompt" required maxlength="50000" rows="5" placeholder="เขียนคำถาม หรือแทรกภาพโจทย์"></textarea></div><?php foreach (['a' => 'ก', 'b' => 'ข', 'c' => 'ค', 'd' => 'ง'] as $key => $label): ?><div class="rich-field rich-field-compact"><label for="question-option-<?= $key ?>" id="question-option-<?= $key ?>-label">ตัวเลือก <?= $label ?></label><textarea id="question-option-<?= $key ?>" class="rich-editor" data-course-id="<?= (int)$lesson['course_id'] ?>" name="option_<?= $key ?>" required maxlength="20000" rows="3" placeholder="เขียนตัวเลือก <?= $label ?> หรือแทรกรูปภาพ"></textarea></div><?php endforeach; ?><div class="rich-field"><label for="question-explanation" id="question-explanation-label">คำอธิบายเฉลย (ถ้ามี)</label><textarea id="question-explanation" class="rich-editor" data-course-id="<?= (int)$lesson['course_id'] ?>" name="explanation" maxlength="50000" rows="4" placeholder="อธิบายเหตุผลของคำตอบหรือวิธีทำ"></textarea><small class="form-hint">ผู้เรียนจะเห็นเฉลยเมื่อสอบผ่านหรือใช้สิทธิ์สอบครบแล้ว</small></div><label>คำตอบที่ถูกต้อง<select name="answer">
                            <option value="0">ก</option>
                            <option value="1">ข</option>
                            <option value="2">ค</option>
                            <option value="3">ง</option>
                        </select></label><button class="button"><?= icon('plus') ?>เพิ่มข้อสอบ</button>
                </form>
            </section><?php endif; ?>
    </div>
    <aside>
        <?php if (!$locked): require __DIR__ . '/ai-draft.php'; endif; ?>
        <section class="panel">
            <h3>นำเข้าข้อสอบ</h3><?php if ($locked): ?><p class="form-hint">รายวิชามีผู้เรียนแล้ว ไม่สามารถเปลี่ยนข้อสอบได้</p><?php else: ?><p class="form-hint">รองรับ CSV (UTF-8) และ JSON ไม่เกิน 500 ข้อ / 2 MB ต่อครั้ง ข้อสอบใหม่จะเพิ่มต่อท้าย</p>
                <div class="download-links"><a class="text-link" href="/templates/questions.csv" download><?= icon('download') ?>ตัวอย่าง CSV</a><a class="text-link" href="/templates/questions.json" download><?= icon('download') ?>ตัวอย่าง JSON</a><a class="text-link" href="/templates/questions-rich.json" download><?= icon('download') ?>ตัวอย่าง JSON จัดรูปแบบ</a></div>
                <form method="post" action="/teach/quizzes/<?= $lesson['id'] ?>/import" enctype="multipart/form-data" class="form-stack"><?= csrfField() ?><label>ไฟล์ข้อสอบ<input type="file" name="questions" accept=".json,.csv" required></label><button class="button full">นำเข้าข้อสอบ</button></form>
                <p class="form-hint">ค่า answer ใช้ 0 = ก, 1 = ข, 2 = ค, 3 = ง เพิ่มคอลัมน์ explanation สำหรับคำอธิบายเฉลย และ content_format = html สำหรับข้อความที่จัดรูปแบบได้ ข้อมูลทั้งไฟล์ต้องถูกต้องจึงจะบันทึก</p><?php endif; ?>
        </section>
    </aside>
</div><?php require __DIR__ . '/layout-bottom.php'; ?>