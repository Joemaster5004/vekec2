# AI ช่วยครูสร้างบทเรียน ใบงาน และข้อสอบ

ส่วนสร้างฉบับร่างเพิ่มต่อจาก BYOK เดิม ใช้เฉพาะครูเจ้าของรายวิชา/ผู้ดูแล ไม่เปิด API ให้นักศึกษา และไม่แก้โครงสร้างรายวิชาที่มีผู้เรียนแล้ว

## ติดตั้ง

1. ตั้งค่า provider/key/แหล่งจ่าย capability `text` ตาม [คู่มือ BYOK](ai-byok-setup.md) เลือกโมเดลที่รองรับข้อความและ API ของ adapter นั้น
2. รัน `docker compose exec -T web php bin/migrate.php` เพิ่ม migration `008_ai_drafts` (ไม่เปลี่ยนเนื้อหาและผลเรียนเดิม)
3. ผู้ดูแลเปิด **จัดการระบบ → ผู้ให้บริการ AI → ราคาเนื้อหาและข้อสอบ** กรอก Input และ Output ของแต่ละโมเดล หน่วย USD ต่อหนึ่งล้าน token แล้วกด **บันทึกราคาโมเดล** ราคามีผลทันทีและไม่ทำให้ API key ต้องทดสอบใหม่ ใช้อัตราที่ตรงกับแผนบัญชีจริง รวมราคา context tier และค่าบริการเพิ่มเติมที่เกี่ยวข้อง กรอก 0 เฉพาะเมื่อไม่มีค่าใช้จ่ายจริง
4. ครูรีเฟรชหน้าสร้างเนื้อหา ระบบใช้ราคาที่บันทึกจากหน้า Admin ก่อน หากยังไม่มีจึงอ่าน `AI_TEXT_PRICING_JSON` ใน environment เป็นทางเลือกสำหรับผู้ดูแลเซิร์ฟเวอร์:

   ```dotenv
   AI_TEXT_PRICING_JSON='{"your-provider":{"your-model":{"input":1,"output":2}}}'
   ```

   ตัวเลขในตัวอย่างเป็นเพียงโครงสร้าง ต้องแทนด้วยอัตราจริงของ provider slug และ model ID ที่รายวิชาเลือก หากแก้ environment ต้องรัน `docker compose up -d --force-recreate web` ส่วนการบันทึกราคาจากหน้าเว็บไม่ต้อง restart หากไม่มีราคาในทั้งสองแหล่ง ระบบหยุดก่อนเรียก AI พร้อมแจ้งชื่อโมเดลที่ขาด

ราคาจากหน้า Admin เก็บใน settings ของ LMS พร้อม revision และ audit ไม่ต้องรัน migration เพิ่ม การบันทึกแก้เฉพาะโมเดลที่ส่งมา การเว้นช่องว่างทั้งสองไม่ลบราคาที่บันทึกไว้แล้ว

Production ต้อง rebuild image เมื่อ source เปลี่ยน และใช้ compose.production.yaml ตามคู่มือเดิม

## วิธีใช้สำหรับครู

- ในหน้าจัดการรายวิชา เปิด “ช่วยสร้างเนื้อหา / ใบงานด้วย AI” เลือกประเภท กรอกหัวข้อ จุดประสงค์ ข้อความอ้างอิง เวลา และคำสั่งเพิ่มเติม ระดับผู้เรียนใช้จากรายวิชา
- เลือกบทเรียนต้นทางได้เฉพาะบทเรียน article ในรายวิชาเดียวกัน หากบทเรียนยาวเกิน 15,000 ไบต์ ให้คัดลอกเฉพาะส่วนที่ต้องการมาวางแทน
- กดสร้าง ตรวจและแก้ฉบับร่าง แล้วกด “นำไปใส่ในแบบฟอร์ม” จากนั้นตรวจการตั้งค่ากิจกรรมและกด “เพิ่มกิจกรรม” ตามปกติ
- ในคลังข้อสอบ เปิด “สร้างข้อสอบด้วย AI” เลือกจำนวน 1–10 ข้อและความยาก ตรวจคำถาม ตัวเลือก และคำอธิบายเฉลย เลือกเฉพาะข้อที่ต้องการ แล้วกด “บันทึกข้อสอบที่เลือก” ข้อสอบเพิ่มต่อท้าย ไม่แทนข้อเดิม
- ทุกผลลัพธ์เป็นข้อความธรรมดา การนำเข้า Quill ใช้ setText จึงไม่ประมวลผล HTML จาก AI สามารถจัดรูปแบบต่อใน editor เดิมได้
- ใบงานร่างเฉพาะคำชี้แจงและเกณฑ์ที่เปิดให้ผู้เรียนอ่าน ไม่มีช่องแนวเฉลยลับสำหรับครู

ตัวอย่างคำสั่งเพิ่มเติม: “ยกตัวอย่างวงจรไฟฟ้าในงานช่าง อธิบายทีละขั้นตอน ใช้ภาษาสำหรับ ปวช.1” หรือ “เน้นการประยุกต์ใช้ มีตัวลวงจากความเข้าใจผิดที่พบบ่อย และอธิบายเหตุผลเฉลย”

## วงเงินและคำขอซ้ำ

- ใช้ binding capability `text` ที่ครูยอมรับค่าใช้จ่ายไว้แล้ว ไม่มี fallback เปลี่ยนผู้จ่าย/provider/model
- แสดงยอดจองสูงสุดต่อครั้งตามอัตราของผู้ดูแล จอง input สูงสุด 40,000 token (จำกัดข้อความ UTF-8 เป็นไบต์พร้อมเผื่อ overhead) และ output สูงสุด 8,192 token แล้ว settle ตาม usage ที่ provider ส่งกลับ อัตราใน LMS ไม่ใช่ใบแจ้งหนี้ของ provider
- คำขอที่ได้ usage แล้วแต่ JSON ไม่ถูกต้อง/ตอบไม่จบ ยังคงบันทึกต้นทุน เพราะ provider ทำงานไปแล้ว
- เมื่อส่งคำขอแล้ว timeout, provider ตอบ error หรือไม่มี usage ที่เชื่อถือได้ จะคงเงินจองไว้เพื่อไม่รายงานต้นทุนต่ำกว่าจริง ไม่ release หรือ retry อัตโนมัติ ต้องตรวจยอดกับ provider ก่อนตัดสินใจสร้างคำขอใหม่ ยังไม่มีระบบ reconcile อัตโนมัติ
- เก็บ claim ของคำขอและผลลัพธ์ใน `ai_drafts` ก่อนเรียก provider โดยไม่เปิด transaction ค้างขณะรอเครือข่าย รหัสเดิมไม่เรียก generation ซ้ำ แม้คำขอค้างหรือ process หยุด ครูตรวจผลผ่านปุ่ม “ตรวจผลคำขอเดิมอีกครั้ง” ได้เมื่อ browser ยืนยันผลไม่ได้
- การบันทึกข้อสอบใช้ idempotency แยก และ transaction/savepoint ร่วมกับ Quiz::add เพื่อให้ชุดข้อสอบและผลตอบกลับบันทึกพร้อมกัน

## API ที่เพิ่ม

ทุกเส้นทางใช้ active teacher/admin และสิทธิ์รายวิชาเดิม การเขียนต้องมี JSON, Origin, CSRF และ Idempotency-Key

- `GET /api/v1/courses/{id}/ai/drafts` — provider/model และยอดจองสูงสุด ไม่มี secret
- `POST /api/v1/courses/{id}/ai/drafts` — สร้างหรือเรียกคืนผลของคำขอเดิม
- `POST /api/v1/quizzes/{id}/ai-questions` — บันทึกข้อสอบที่ครูตรวจแล้ว 1–10 ข้อ

Generation จำกัด 30 คำขอต่อครูต่อชั่วโมง (รวม replay) ใช้การเข้าสู่ระบบปกติ ไม่บังคับยืนยันรหัสผ่านทุก 15 นาทีเหมือนการเปลี่ยน key/แหล่งจ่าย

Adapters ใช้ OpenAI-compatible Chat Completions (`max_completion_tokens`), Anthropic Messages (`max_tokens`) และ Gemini generateContent (`maxOutputTokens`) โมเดลที่ไม่รองรับพารามิเตอร์ของ adapter จะถูกปฏิเสธโดย provider ไม่มีการเปลี่ยนโมเดลหรือทดลองพารามิเตอร์อื่นที่อาจสร้างค่าใช้จ่ายซ้ำ

เอกสาร API ที่ตรวจขณะพัฒนา: [OpenAI](https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create), [Anthropic](https://platform.claude.com/docs/en/api/messages/create), [Gemini](https://ai.google.dev/api/generate-content)

## ทดสอบ

ใช้เฉพาะ compose.test.yaml และ provider จำลอง ไม่มีการเรียก AI จริงหรืออ่าน key ของผู้ใช้:

```sh
docker compose -f compose.test.yaml up -d
docker compose -f compose.test.yaml exec -T web php bin/migrate.php
docker compose -f compose.test.yaml exec -T web php tests/fixture.php
docker compose -f compose.test.yaml exec -T web php tests/ai_drafts.php
python3 tests/ai_drafts_http.py
```

ก่อนใช้กับ provider จริง ผู้ดูแลต้องตรวจราคาและสิทธิ์โมเดลตามบัญชีจริง การทดสอบจำลองไม่ได้รับรองสิทธิ์ generation ของ key จริง

ผลตรวจรอบพัฒนานี้: service ฉบับร่าง 28 กรณี, HTTP 36 กรณี และ regression BYOK/provider/rich text/users รวม 98 กรณีผ่าน ตรวจด้วย Microsoft Edge จริงแล้วทั้งสร้างบทเรียน นำลง Quill และสร้าง/บันทึกข้อสอบ ยังไม่ได้ตรวจ layout บนโทรศัพท์จริง

การแก้ปัญหาราคาขาด (3 พฤศจิกายน 2569): เพิ่ม editor ราคาผ่านหน้า Admin และ `GET/PUT /api/v1/admin/ai-providers/pricing` ใช้สิทธิ์ admin, Origin, CSRF และยืนยันตัวตนล่าสุด ทดสอบ service 18 กรณี และ HTTP 15 กรณีผ่าน การตรวจรายวิชาในระบบ local ผ่านโดยไม่มีการเรียก generation จริง
