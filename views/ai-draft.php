<?php
// Teacher-only panel, included outside the ordinary lesson/question forms.
use App\{DB};
use function App\e;
$aiCourseId=(int)($course['id']??$lesson['course_id']);
$aiQuizId=isset($lesson)?(int)$lesson['id']:0;
?>
<section class="panel ai-draft" id="ai-draft" data-course-id="<?= $aiCourseId ?>" data-quiz-id="<?= $aiQuizId ?>">
    <details>
        <summary><strong><?= $aiQuizId?'สร้างข้อสอบด้วย AI':'ช่วยสร้างเนื้อหา / ใบงานด้วย AI' ?></strong></summary>
        <p class="form-hint">AI ช่วยร่างให้ครูตรวจความถูกต้องก่อนบันทึก การสร้างใหม่แต่ละครั้งอาจมีค่าใช้จ่าย</p>
        <p id="ai-draft-connection" role="status">กำลังตรวจการเชื่อมต่อ…</p>
        <a href="/teach/ai-connections" class="text-link">ตั้งค่าการเชื่อมต่อ AI ของรายวิชา</a>
        <form id="ai-draft-form" class="form-stack">
            <?php if($aiQuizId): ?><input type="hidden" name="kind" value="quiz"><?php else: ?><label>ประเภทฉบับร่าง<select name="kind"><option value="article">เนื้อหาบทเรียน</option><option value="assignment">ใบงาน / แบบฝึกหัด</option></select></label><?php endif; ?>
            <label>หัวข้อ<input name="topic" required maxlength="240" value="<?= e($lesson['title']??'') ?>" placeholder="เช่น กฎของโอห์ม"></label>
            <label>จุดประสงค์การเรียนรู้<textarea name="objectives" rows="3" maxlength="1000" placeholder="ผู้เรียนควรอธิบายหรือปฏิบัติอะไรได้"></textarea></label>
            <label>บทเรียนต้นทาง<select name="reference_lesson_id"><option value="0">ใช้ข้อความอ้างอิงที่กรอกด้านล่าง</option><?php foreach(DB::all("SELECT id,title FROM lessons WHERE course_id=? AND kind='article' ORDER BY position,id",[$aiCourseId]) as $source): ?><option value="<?= (int)$source['id'] ?>"><?= e($source['title']) ?></option><?php endforeach; ?></select></label>
            <label>ข้อความอ้างอิงเพิ่มเติม<textarea name="reference" rows="5" maxlength="5000" placeholder="วางเนื้อหาที่ต้องการให้ AI ใช้อ้างอิง โดยไม่ใส่ข้อมูลส่วนบุคคลของผู้เรียน"></textarea></label>
            <?php if($aiQuizId): ?><label>จำนวนข้อ (1–10)<input name="count" type="number" min="1" max="10" value="5" required></label><label>ความยาก<select name="difficulty"><option value="mixed">คละระดับ</option><option value="easy">ง่าย</option><option value="medium">ปานกลาง</option><option value="hard">ยาก</option></select></label><?php else: ?><input type="hidden" name="count" value="1"><input type="hidden" name="difficulty" value="mixed"><?php endif; ?>
            <label>เวลาเรียน / ทำกิจกรรม (นาที)<input name="minutes" type="number" min="5" max="240" value="30" required></label>
            <label id="ai-draft-mode" hidden>รูปแบบงานใบงาน<select name="mode"><option value="individual">งานเดี่ยว</option><option value="group">งานกลุ่ม</option></select></label>
            <label>คำสั่งเพิ่มเติม<textarea name="instructions" rows="3" maxlength="1000" placeholder="เช่น ใช้ตัวอย่างงานช่างไฟฟ้า และอธิบายทีละขั้นตอน"></textarea></label>
            <button class="button" id="ai-draft-generate" disabled>สร้างฉบับร่างด้วย AI</button>
        </form>
        <p id="ai-draft-status" role="status" aria-live="polite"></p>
        <button type="button" class="button outline" id="ai-draft-retry" hidden>ตรวจผลคำขอเดิมอีกครั้ง</button>
        <div id="ai-draft-preview" hidden>
            <h3>ตรวจและแก้ไขฉบับร่าง</h3>
            <p class="form-hint">ตรวจเนื้อหา ตัวเลือก และเฉลยให้ถูกต้องก่อนนำไปใช้งาน</p>
            <div id="ai-draft-fields" class="form-stack"></div>
            <button type="button" class="button" id="ai-draft-apply"><?= $aiQuizId?'บันทึกข้อสอบที่เลือก':'นำไปใส่ในแบบฟอร์ม' ?></button>
        </div>
    </details>
</section>
