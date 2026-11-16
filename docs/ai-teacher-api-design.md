# ออกแบบ AI API สำหรับครูผู้สอน — VEKEC LMS
**ข้อกำหนดล่าสุด 26 ตุลาคม 2569:** AI ใช้เฉพาะครูผู้สอน ผู้ดูแลเพิ่ม/แก้ไข/เปิดปิดผู้ให้บริการและโมเดลผ่าน `/admin/ai-providers` ได้ ยกเลิก AI chat, คำแนะนำ AI และการใช้งบโดยนักศึกษาทั้งหมด รายการผู้ให้บริการในฐานข้อมูลเป็นแหล่งตั้งค่าหลักแทน environment allowlist เดิม


สถานะ: ข้อเสนอออกแบบใหม่ วันที่ 20 ตุลาคม 2569 ยังไม่ใช่ API ที่ติดตั้งหรือเรียกใช้งานได้จริง

ปรับปรุง 26 ตุลาคม 2569: เพิ่มทางเลือกให้ครูใช้ API key ของตนเอง (Bring Your Own Key: BYOK) ควบคู่กับ key ส่วนกลาง

สถานะ implementation ล่าสุด: ผู้ใช้ยืนยันให้พัฒนาเฉพาะ BYOK ทั้ง 5 องค์ประกอบ มีหน้า `/teach/ai-connections` และ API credentials/billing/usage แล้ว ดู [คู่มือติดตั้งและขอบเขตที่พัฒนา](ai-byok-setup.md) รายละเอียดด้านล่างยังเป็นสถาปัตยกรรมเป้าหมายของ AI ทั้งระบบ ส่วนสร้างงาน AI, worker และ release/version ยังไม่ถูกติดตั้ง

ขอบเขตอ้างอิงจาก 5 ฟังก์ชันที่ผู้ใช้ระบุในบทสนทนานี้ ไม่พบบันทึกแนวคิดจากช่วงเช้าในโครงการ ข้อมูลลงทะเบียนปัจจุบันเป็นข้อมูลทดสอบตามที่ผู้ใช้ยืนยัน จึงออกแบบให้ปรับโครงสร้างระบบเดิมได้ โดยเอกสารนี้ไม่ลบหรือย้ายข้อมูลจริง

## 1. ข้อเสนอหลัก

เพิ่ม **AI Studio** ในพื้นที่ครู เชื่อม API กลางของ LMS แล้วให้เซิร์ฟเวอร์เรียกผู้ให้บริการ AI อีกชั้นหนึ่ง ครูใช้บัญชี LMS เดิม เลือกใช้ API key ส่วนกลางที่ได้รับสิทธิ์ หรือกรอก API key ของตนเองผ่านหน้าตั้งค่าที่ปลอดภัยได้ โดยไม่ต้องส่ง key ซ้ำในแต่ละงาน

```text
ครู → AI Studio → LMS API → ตรวจสิทธิ์/งบประมาณ → คิวงาน
                                               ↓
                                AI Worker → Provider Adapter → บริการ AI
                                               ↓
                           ผลงานฉบับร่าง → ครูตรวจ/แก้ไข → เผยแพร่เข้า LMS

ครู → ผู้ช่วยงานสอน / ดูคำแนะนำ → LMS API → ตรวจเจ้าของรายวิชา → ข้อมูลที่อนุญาต
```

แยกงานเป็น 3 ส่วน: สร้างเนื้อหา, ช่วยเรียน, วิเคราะห์ผล โดยทุกส่วนสำหรับครูผูกกับ `course_id` ฝั่งเซิร์ฟเวอร์ ใช้ข้อมูลจาก LMS เป็นหลัก ไม่ให้โมเดลตัดสินสิทธิ์หรือเขียนคะแนนโดยตรง

ออกแบบ API แบบไม่ผูกผู้ให้บริการ แยก adapter สำหรับข้อความ, embeddings, เสียงภาษาไทย และการสร้างภาพ/วิดีโอ ไม่สมมติว่าผู้ให้บริการรายเดียวรองรับทั้งหมด

## 2. ประสบการณ์ครูและผลลัพธ์ของแต่ละฟังก์ชัน

| ฟังก์ชัน | ครูกำหนด | ผลลัพธ์ที่ตรวจแก้ได้ | การนำไปใช้ |
| --- | --- | --- | --- |
| AI Content Generator | หัวข้อ ผลลัพธ์การเรียนรู้ ระดับผู้เรียน แหล่งความรู้ ความยาว | โครงร่าง เนื้อหา ตัวอย่าง กิจกรรม และสรุป พร้อมแหล่งอ้างอิง | เผยแพร่เป็นบทเรียนหรือสรุปประกอบบทเรียน |
| AI Exam & Worksheet | จุดประสงค์ จำนวนข้อ ประเภทข้อ ระดับความยาก สัดส่วนหัวข้อ | ข้อสอบ/ใบงาน เฉลย คำอธิบาย rubric และตารางเชื่อมจุดประสงค์ | บันทึกเข้าคลังข้อสอบหรือกิจกรรม; นักศึกษาได้รับเฉพาะส่วนที่อนุญาต |
| AI Video Lesson | บทต้นทาง เวลาโดยประมาณ รูปแบบสไลด์ เสียงที่อนุญาต | บทพูด storyboard วิดีโอ MP4 เสียงไทย transcript และคำบรรยาย VTT | ครูอนุมัติบทก่อนสร้างสื่อ แล้วตรวจวิดีโอก่อนเผยแพร่ |
| AI Learning Assistant | ชุดความรู้ บทเรียนที่ใช้ตอบ นโยบายการช่วยตอบ | คำตอบอ้างอิงรายวิชา อธิบายเป็นขั้น และแนะนำบททบทวน | ครูใช้ผู้ช่วยในพื้นที่ส่วนตัวของรายวิชา ไม่มีบริการถามตอบ AI สำหรับนักศึกษา |
| AI Learning Analytics | ช่วงเวลา กลุ่มเรียน เป้าหมายวิเคราะห์ | สรุปความก้าวหน้า จุดที่ควรทบทวน และแผนรายบุคคล พร้อมหลักฐาน | ครูดูทั้งห้องและแผนช่วยเหลือรายบุคคล นักศึกษาไม่เรียกใช้บริการ AI |

หน้าจอ AI Studio ประกอบด้วยแท็บทั้ง 5 ส่วนและ **งานของฉัน / คลังผลงาน / การใช้งานและโควตา** ทุกงานแสดงสถานะ ต้นทุนประมาณการ แหล่งต้นทาง และวันที่ข้อมูล ครูแก้ฉบับร่างได้ก่อนเผยแพร่

## 3. สิทธิ์

บทบาท `teacher`, `student`, `admin` เดิมยังใช้ได้ เพิ่ม capability ที่ระบบตรวจจริง ไม่ต้องสร้าง role ใหม่สำหรับ AI

| Capability | ครูเจ้าของรายวิชา | ผู้เรียนที่ลงทะเบียน | ผู้ดูแล |
| --- | --- | --- | --- |
| `ai.content.generate`, `ai.assessment.generate` | ใช้ได้เมื่อเปิดฟังก์ชันและมีโควตา | ไม่ได้ | ใช้ได้ตามนโยบายระบบ |
| `ai.video.generate` | ใช้ได้เมื่อผู้ดูแลเปิดและกำหนดงบ | ไม่ได้ | เปิด/ปิดและกำหนดงบ |
| `ai.artifact.review`, `ai.artifact.publish` | ตรวจและเผยแพร่รายวิชาตนเอง | ไม่ได้ | จัดการได้ พร้อม audit |
| `ai.assistant.configure`, `ai.assistant.preview` | จัดการรายวิชาตนเอง | ไม่ได้ | จัดการได้ |
| `ai.analytics.read`, `ai.analytics.publish` | รายวิชาตนเอง | ไม่ได้ | จัดการได้ พร้อม audit |
| `ai.settings.manage` | ไม่ได้ | ไม่ได้ | ตั้งค่า provider, capability และโควตา |
| `ai.credentials.manage_self` | เพิ่ม/ทดสอบ/เปลี่ยน/เพิกถอน key ของตน | ไม่ได้ | จัดการ key ของตน; ระงับการใช้ BYOK ได้ แต่ไม่มี API อ่าน key ของครู |
| `ai.billing.configure_course` | ผูก key ของตนและยินยอมจ่ายสำหรับรายวิชาตน | ไม่ได้ | กำหนด key ส่วนกลางตามสิทธิ์ โดยไม่ผูก key ส่วนตัวของครูแทนเจ้าของ |

ลำดับตรวจ: บัญชียัง active → บทบาท → capability → เจ้าของรายวิชา/การลงทะเบียน → สิทธิ์ของทรัพยากรย่อย → โควตา ตรวจในทุก endpoint รวมถึงดูสถานะงาน ดาวน์โหลดไฟล์ และอ่านบทสนทนา

ห้ามเชื่อ `teacher_id`, `user_id`, `role` หรือสิทธิ์ที่ส่งมาจาก client; ตัวตนมาจาก session หรือ token ที่ผ่านการตรวจสอบเท่านั้น `lesson_id`, `artifact_id`, `conversation_id` ต้องอยู่ใน course ที่ร้องขอ

## 4. ปรับโครงสร้างรายวิชาให้แก้ไขได้หลังเปิดเรียน

ยกเลิกแนวทางเดิมที่ห้ามแก้หลักสูตรเพียงเพราะมี enrollment แล้ว ใช้การเก็บเวอร์ชันแทน:

- `courses` เป็นข้อมูลรายวิชาหลัก เพิ่ม `course_releases` สำหรับฉบับที่เผยแพร่แต่ละครั้ง
- เนื้อหาและข้อสอบอยู่ใน `lesson_versions`, `assessment_versions` และ `question_versions`; ฉบับเผยแพร่แล้วไม่แก้ทับ
- `course_release_items` ระบุบทเรียน/ข้อสอบและลำดับที่ใช้ใน release นั้น; `enrollments` ผูก `course_release_id`
- ครูแก้ไขฉบับร่างและเผยแพร่ release ใหม่ได้ทุกเวลา ผู้เรียนใหม่ใช้ release ปัจจุบัน
- ผู้เรียนที่เริ่มเรียนแล้วคง release เดิมเป็นค่าเริ่มต้น ครูเลือกย้ายรายบุคคลหรือทั้งกลุ่มได้ผ่านหน้าตรวจผลกระทบ
- การย้ายเทียบกิจกรรมด้วย stable activity ID และกฎความเทียบเท่าที่ครูยืนยัน งานที่เปลี่ยนเกณฑ์หรือข้อสอบไม่นำผลเดิมมานับผ่านอัตโนมัติ
- `attempts` ผูก assessment version และชุดข้อที่ใช้สอบ; `submissions` และ `progress` ผูก lesson version; ใบประกาศเก็บ release อ้างอิง
- การเผยแพร่ใช้ transaction และตรวจ revision ป้องกันครูสองหน้าจอแก้ทับกัน การเผยแพร่ซ้ำไม่สร้างกิจกรรมซ้ำ

ข้อมูลทดสอบเดิมอาจย้ายเป็น release เริ่มต้น หรือ reset ผ่านคำสั่งเฉพาะ development ที่ป้องกัน environment และมีการยืนยันแยกต่างหาก ขั้นออกแบบนี้ยังไม่เลือกหรือลงมือ reset

## 5. หลักการ API และการยืนยันตัวตน

Base path: `/api/v1` ส่งและรับ JSON UTF-8 เวลาบน API ใช้ RFC 3339 UTC; UI แสดง Asia/Bangkok

**ระยะแรก:** หน้าเว็บ LMS เรียก same-origin ผ่าน session `vekec_session` เดิม ทุก POST/PUT/PATCH/DELETE ต้องตรวจ `X-CSRF-Token` และ Origin ที่อนุญาต ขยายการตรวจใน `bootstrap.php` ซึ่งปัจจุบันตรวจเฉพาะ POST

**การเรียกจากโปรแกรมภายนอก:** เป็นระยะถัดไป ใช้ OAuth 2.0 Authorization Code + PKCE ผ่าน authorization server ที่เลือก ไม่ใช้รหัสผ่าน LMS แลก token เอง กำหนด scopes ตาม capability, อายุสั้น, audience เฉพาะ LMS, ตรวจสถานะบัญชีและเจ้าของรายวิชาทุกครั้ง ไม่เปิด CORS กว้างและไม่ส่ง provider API key ให้ client

สเปก OpenAPI ที่แนบครอบคลุม session API ระยะแรก ยังไม่รวม OAuth endpoint และ API จัดการผู้ดูแล/การย้าย release

งาน AI ใช้ asynchronous job: `POST` รับงานด้วย `202 Accepted`, คืน `job_id` และ `Location`; client poll `GET` ตาม `Retry-After` ไม่รอสร้างวิดีโออยู่ใน HTTP request เดียว

POST ที่สร้างงานและเผยแพร่ต้องมี `Idempotency-Key` เก็บ key + canonical payload hash + ผลลัพธ์ 24 ชั่วโมง ผูกผู้เรียก + method + path; key เดิม/payload เดิมคืนงานเดิม, payload ต่างคืน `409 IDEMPOTENCY_CONFLICT` ตรวจสิทธิ์ใหม่ก่อนคืนผล และมี unique constraint ป้องกันคำขอแข่งกัน

ส่ง `request_id` ทุก response; error ใช้ `{ "error": { "code": "...", "message": "...", "request_id": "..." } }` ภายใน namespace v1 โดยไม่บังคับเปลี่ยน error ของ API เดิมทั้งระบบ

## 6. Endpoint ที่เสนอ

ทุก path ด้านล่างต่อจาก `/api/v1` และเป็น API ใหม่ที่ต้องพัฒนา

| Method / Path | หน้าที่ / สิทธิ์ |
| --- | --- |
| `GET /me/ai-capabilities` | ฟังก์ชันที่บัญชีใช้ได้และโควตาคงเหลือ |
| `POST /courses/{course_id}/ai/content-jobs` | ครูสร้างเนื้อหาหรือสรุป |
| `POST /courses/{course_id}/ai/assessment-jobs` | ครูสร้างข้อสอบ/ใบงาน/แบบฝึกหัดและเฉลย |
| `POST /courses/{course_id}/ai/video-jobs` | สร้าง storyboard หรือ render จาก storyboard version ที่ครูตรวจแล้ว |
| `GET /courses/{course_id}/ai/jobs/{job_id}` | เจ้าของรายวิชาอ่านสถานะและ artifact อ้างอิง |
| `POST /courses/{course_id}/ai/jobs/{job_id}/cancel` | ขอหยุดงาน; แสดงต้นทุนที่เกิดขึ้นแล้ว |
| `GET /courses/{course_id}/ai/artifacts/{artifact_id}` | ครูเปิดผลงานและ revision ปัจจุบัน |
| `PUT /courses/{course_id}/ai/artifacts/{artifact_id}/draft` | ครูแก้ฉบับร่าง พร้อม `expected_revision` |
| `POST /courses/{course_id}/ai/artifacts/{artifact_id}/publish` | ครูยืนยัน revision และปลายทาง ระบบสร้าง release/ทรัพยากรที่เหมาะสม |
| `PUT /courses/{course_id}/ai/assistant` | เปิด/ปิดผู้ช่วย กำหนดชุดความรู้และนโยบาย |
| `POST /courses/{course_id}/ai/knowledge-jobs` | ครูสร้างดัชนีความรู้จาก release/เอกสารที่ตรวจแล้ว |
| `POST /courses/{course_id}/ai/assistant-preview-jobs` | ผู้ช่วยส่วนตัวตามรายวิชาสำหรับครูเท่านั้น |
| `POST /courses/{course_id}/ai/analytics-jobs` | ครูสร้างรายงานและคำแนะนำรายบุคคล |
| `GET /courses/{course_id}/ai/assets/{asset_id}` | ไฟล์ส่วนตัว ตรวจสิทธิ์ตาม artifact/release/message ที่อ้างอิง |

API และ budget gateway ทั้งหมดตรวจ role เป็น teacher/admin นักศึกษาถูกปฏิเสธแม้ลงทะเบียนในรายวิชาหรือมีการอนุญาตค้างจากรุ่นเดิม

### ตัวอย่างสร้างเนื้อหา

```http
POST /api/v1/courses/12/ai/content-jobs
Content-Type: application/json
X-CSRF-Token: <csrf จาก session LMS>
Idempotency-Key: 49d73432-0bd0-4206-b330-1193cab1ae3b

{
  "mode": "lesson",
  "topic": "การต่อวงจรไฟฟ้าแบบอนุกรม",
  "level": "voc1",
  "objectives": ["อธิบายความสัมพันธ์ระหว่างกระแส แรงดัน และความต้านทาน"],
  "source_lesson_version_ids": [31],
  "language": "th",
  "length": "medium"
}
```

```json
{
  "data": {
    "job_id": "job_example_001",
    "status": "queued",
    "estimated_cost_credits": 8
  },
  "request_id": "req_example_001"
}
```

ตัวเลข credits เป็นตัวอย่างของระบบโควตาที่เสนอ ไม่ใช่ราคาผู้ให้บริการ ปริมาณและราคาต้องกำหนดหลังเลือกบริการและทดสอบงานภาษาไทย

### สถานะและการเผยแพร่

งาน: `queued → running → succeeded | failed`; งานที่ขอยกเลิกเข้าสู่ `cancel_requested → cancelled` หรือ `succeeded` หากเสร็จก่อนยกเลิก งานที่ timeout เป็น failed พร้อม code และข้อมูล retry

ผลงานแยกจากสถานะงาน: `draft → published`; การแก้ผลงานที่เผยแพร่แล้วสร้าง revision ใหม่ในสถานะ draft เสมอ ระบบเก็บผู้ตรวจและเวลาจาก session ตอนเผยแพร่ ไม่รับค่าผู้อนุมัติจาก request

`publish` รับ `expected_revision`, `destination`, `confirmed_review: true`:

- `lesson`: สร้าง article/summary ใน release ใหม่; `assessment`: สร้าง quiz/assignment และเฉลยส่วนตัว; `video`: สร้าง video lesson
รายงาน analytics และคำแนะนำรายบุคคลเก็บเป็นผลงานส่วนตัวของครู ไม่เผยแพร่ผ่านบริการ AI สำหรับนักศึกษา
- storyboard เผยแพร่เป็นวิดีโอไม่ได้ ต้อง render และตรวจ asset สำเร็จก่อน
- เงื่อนไขประเภท artifact ต้องตรงปลายทาง ถ้าข้อมูลต้นทางเปลี่ยนแสดงคำเตือนและบังคับตรวจ revision; ถ้า expected_revision ไม่ตรงคืน `409 REVISION_CONFLICT`

## 7. ข้อกำหนดรายฟังก์ชัน

### เนื้อหาและข้อสอบ

โมเดลคืน structured output ตาม schema ของ artifact แล้วเซิร์ฟเวอร์ตรวจชนิดข้อมูล จำนวนข้อ ตัวเลือก เฉลย และการอ้างอิง แยก `student_content` ออกจาก `teacher_only` ตั้งแต่จัดเก็บและ serialize response ตามสิทธิ์ ห้ามส่ง teacher_only ไปให้ client ผู้เรียนแล้วซ่อนด้วย CSS

รองรับ `mcq`, `short_answer`, `essay`, `worksheet` และ `exercise` ในการออกแบบใหม่ แต่ต้องพัฒนาตัวแสดงผลและระบบส่งคำตอบให้ครบก่อนเปิดแต่ละชนิด เดิม `Quiz::add()` รองรับเฉพาะ 4 ตัวเลือกและเฉลย 0–3 จึงใช้ adapter แปลงเฉพาะ MCQ ที่ตรงรูปแบบได้ ส่วนชนิดอื่นเก็บใน assessment schema ใหม่

ครูตรวจความถูกต้อง ความเหมาะสม และ rubric ก่อนเผยแพร่ คะแนน MCQ คำนวณด้วยกฎเซิร์ฟเวอร์ ส่วนการให้คะแนนปลายเปิดยังให้ครูตัดสิน AI เสนอ feedback ได้โดยไม่เปลี่ยนคะแนนจริง

### วิดีโอพร้อมเสียงภาษาไทย

แยก 2 ขั้น: `stage=storyboard` สร้างบทพูดและฉาก → ครูแก้ revision → `stage=render` อ้าง `storyboard_artifact_id` และ `storyboard_revision` ที่ยืนยันตรวจแล้ว

เริ่มจากวิดีโอสไลด์พร้อมเสียงสังเคราะห์ภาษาไทยและคำบรรยาย ซึ่งควบคุมเนื้อหา/ต้นทุนได้ หากต้องการฉากเคลื่อนไหวจาก generative video ให้เพิ่ม adapter ภายหลังและทดสอบเสียงไทยจริงก่อนเปิดให้ใช้ ไม่ถือว่าการสร้างวิดีโอทั่วไปเท่ากับรองรับเสียงไทย

เก็บ MP4, audio, transcript, VTT และข้อมูล license/แหล่งภาพใน private storage ตรวจชนิดไฟล์ ขนาด codec และ duration ด้วยเครื่องมือเซิร์ฟเวอร์ ใช้เสียงสำเร็จรูปที่ได้รับอนุญาตเป็นค่าเริ่มต้น กำหนดความยาวสูงสุดและประมาณต้นทุนก่อน render

### ผู้ช่วยงานสอนสำหรับครูตามรายวิชา

ใช้ retrieval จากเนื้อหารายวิชาที่ครูมีสิทธิ์ มี course/release/source version ในทุก chunk ตรวจเจ้าของรายวิชาฝั่งเซิร์ฟเวอร์ก่อน retrieval และก่อนส่งคำตอบ ผู้ช่วยนี้เป็นเครื่องมือส่วนตัวของครู ไม่เปิดให้นักศึกษาถาม AI หรือใช้ API key ของครู

ตอบพร้อม citations ที่ตรวจว่าอ้าง chunk ที่ได้รับจริง หากไม่พบหลักฐานให้บอกว่าข้อมูลไม่พอ แยกข้อมูลต้นทางจากคำสั่งระบบและตรวจผลลัพธ์หลายชั้น โมเดลไม่มีเครื่องมือ SQL/เขียนคะแนน/เปลี่ยนสิทธิ์ การเปลี่ยนเจ้าของรายวิชาหรือเพิกถอนสิทธิ์ต้องมีผลในคำถามครั้งถัดไป

### วิเคราะห์ผลและคำแนะนำรายบุคคล

อ่าน `enrollments`, `progress`, `attempts`, `submissions` แล้วคำนวณตัวเลขด้วย SQL/โค้ดก่อน ให้โมเดลช่วยอธิบายและเสนอแผนทบทวน ไม่ให้โมเดลคำนวณคะแนนต้นฉบับหรือเปลี่ยนผลผ่านรายวิชา

รายงานประกอบด้วยความคืบหน้า ผลสอบที่ส่งแล้ว งานที่ยังไม่ส่ง จุดประสงค์ที่ควรทบทวน และหลักฐาน เช่น lesson/attempt อ้างอิง ระบุ `data_as_of`, จำนวนข้อมูล และข้อจำกัด ถ้าข้อมูลไม่พอคืน `insufficient_data` แทนการสรุปความสามารถ

schema เดิมยังไม่มี due date, การผูกข้อกับจุดประสงค์ หรือ event การเข้าเรียนต่อเนื่อง จึงยังสรุป “ส่งช้า”, “ไม่เข้าเรียน X วัน” หรือ “อ่อนจุดประสงค์ใด” ไม่ได้จากข้อมูลเดิมเพียงอย่างเดียว ต้องเพิ่ม `due_at`, objective mapping และ event ที่จำเป็นก่อนเปิดตัวชี้วัดเหล่านี้ ไม่ใช้ `watched_seconds` เป็นเวลาที่มีสมาธิเรียน

ส่งข้อมูลแบบรหัสแทนตัวบุคคลและเฉพาะส่วนจำเป็นให้ provider แปลงกลับบน LMS คำแนะนำเป็นข้อเสนอที่ครูตรวจ ไม่ใช่การตัดสินความสามารถถาวรของผู้เรียน

## 8. ข้อมูลที่เพิ่ม

| กลุ่มตารางที่เสนอ | หน้าที่หลัก |
| --- | --- |
| `course_releases`, `course_release_items`, `lesson_versions` | ฉบับหลักสูตรและบทเรียนที่ผูกประวัติผู้เรียน |
| `assessment_versions`, `question_versions`, `learning_objectives`, `question_objectives` | รูปแบบข้อสอบใหม่ rubric และการวิเคราะห์ตามจุดประสงค์ |
| `ai_capability_grants`, `ai_course_settings` | สิทธิ์เพิ่มเติม feature flags และนโยบายผู้ช่วยรายวิชา |
| `ai_jobs`, `ai_job_attempts` | ประเภทงาน ผู้ร้องขอ course สถานะ lease/retry provider request ID และ failure code |
| `ai_artifacts`, `ai_artifact_revisions` | ผลงาน ชนิด revision payload แยก student/teacher ผู้ตรวจ และสิ่งที่เผยแพร่แล้ว |
| `ai_assets` | private path MIME ขนาด checksum duration และทรัพยากรแม่ที่ใช้ตรวจสิทธิ์ |
| `ai_sources`, `ai_source_chunks` | เอกสารที่ผ่านตรวจ chunk ขอบเขตสิทธิ์ และเวอร์ชันดัชนี |
| `ai_conversations`, `ai_messages` | เจ้าของบทสนทนา release ข้อความ citations และสถานะงานตอบ |
| `ai_analytics_runs`, `ai_recommendations` | snapshot ข้อมูล หลักฐาน ผู้เรียนเป้าหมาย และสถานะการตรวจโดยครู |
| `ai_usage_ledger`, `ai_budget_reservations`, `ai_idempotency_keys` | ปริมาณใช้จริง วงเงินสำรอง และการป้องกันคำขอซ้ำ |

กำหนด foreign key และดัชนีตาม course/owner/status; unique สำหรับ revision, idempotency และ ledger event เพื่อไม่คิดต้นทุนซ้ำ ห้ามใช้ JSON payload ที่ไม่มี schema เป็นตัวแทนความสัมพันธ์และสิทธิ์ทั้งหมด

embedding index ใช้บริการหรือส่วนจัดเก็บที่เลือกภายหลัง ระบบปัจจุบันเป็น MySQL 8.4 จึงไม่สมมติว่ามี vector search พร้อมใช้งานในฐานข้อมูลเดิม

## 9. คิวงาน ต้นทุน และข้อมูลส่วนตัว

- เริ่มด้วย MySQL queue และ PHP CLI worker แยก process; claim งานแบบ transaction/lease และ heartbeat รองรับ worker ตายแล้วกลับมาทำต่อ ไม่ต้องเพิ่ม Redis ตั้งแต่แรก
- API จองงบประมาณสูงสุดที่อนุญาตใน transaction ก่อนลงคิว ตรวจงบครู/รายวิชา/สถานศึกษาพร้อมกัน worker จำกัด token/duration ตามเงินจอง และ reconcile ledger หลังงานจบ
- คำขอซ้ำใน LMS ต้องไม่สร้างงานซ้ำ แต่ไม่รับประกัน provider ทำงาน exactly once: กรณี timeout หลังส่งงานต้องเช็ก provider request ID/status ก่อน retry หากตรวจไม่ได้ให้เข้าสถานะต้องตรวจสอบ ไม่ยิงซ้ำอัตโนมัติจนเสี่ยงคิดเงินซ้ำ
- จำกัดงานพร้อมกัน แยกคิว video จาก chat กำหนด timeout และ retry เฉพาะข้อผิดพลาดชั่วคราวด้วย backoff/jitter ไม่ retry validation หรือ permission error
- ยกเลิกเป็น best effort; ค่าใช้จ่ายที่ provider ประมวลผลแล้วอาจยังเกิดขึ้นและต้องแสดงในประวัติการใช้
- API key ส่วนกลางอยู่ใน environment/secret store; key ของครูเก็บใน secret store หรือฐานข้อมูลที่เข้ารหัสตามหัวข้อ 12 ไม่อยู่ใน artifact, log หรือ settings แบบข้อความธรรมดา หน้าเพิ่ม key รับข้อมูลครั้งเดียวและไม่ส่ง key กลับจากเซิร์ฟเวอร์
- แหล่งข้อมูลเลือกจาก ID ภายในที่มีสิทธิ์เท่านั้น ไม่เปิดให้ worker ดาวน์โหลด URL ใดก็ได้จาก prompt; provider asset download ใช้ allowlist และป้องกัน SSRF/redirect ไปเครือข่ายภายใน
- คง rich text sanitizer และ upload validation เดิมเป็นฐาน ตรวจผล AI เสมอก่อนแสดง ไม่ใช้ raw HTML จากโมเดลโดยตรง
- บันทึก audit เป็น actor/course/action/resource/revision/request ID; telemetry ไม่บันทึก prompt เต็ม เฉลย ข้อมูลผู้เรียน หรือ token โดยค่าเริ่มต้น
- retention ที่เสนอ: raw provider payload 7 วัน, chat 90 วัน, log ปฏิบัติการ 90 วัน; เนื้อหาที่เผยแพร่และหลักฐานผลเรียนคงตามนโยบายสถานศึกษา ตัวเลขนี้เป็นค่าตั้งต้นให้กำหนดก่อนเปิดใช้ ไม่ใช่ข้อกำหนดทางกฎหมาย
- ก่อนเลือก provider ตรวจข้อกำหนดการเก็บข้อมูล การใช้ข้อมูลฝึกโมเดล ที่ตั้งข้อมูล และช่องทางลบข้อมูลตามนโยบายสถานศึกษา

HTTP error ที่กำหนด: `401` ไม่เข้าสู่ระบบ, `403` ไม่มี capability/CSRF ไม่ผ่าน, `404` ไม่พบหรือทรัพยากรนอกขอบเขต, `409` revision/idempotency conflict, `413` ข้อมูลใหญ่เกิน, `422` schema/ชนิดผลงานผิด, `429` โควตาหรืออัตราเรียกเกิน พร้อม Retry-After เมื่อทราบ, `503` ระบบ AI ไม่พร้อม งานที่รับเข้าคิวแล้วรายงาน failure ผ่าน job ไม่ส่ง stack trace หรือ provider secret กลับ client

## 10. จุดเชื่อมกับโค้ดเดิมและแผนพัฒนา

| ส่วนเดิม | การปรับที่ต้องทำ |
| --- | --- |
| `app/Router.php`, `app/Actions.php` | แยก `ApiRouter` สำหรับ `/api/v1` ก่อน dispatch เดิม รองรับ JSON และ method ใหม่ |
| `app/Auth.php`, `app/Learning.php` | เพิ่ม policy ตรวจ capability + ownership และการตรวจ enrollment/release; worker ใช้ actor context ที่ตรวจจาก DB ไม่พึ่ง session |
| `app/bootstrap.php`, `public/index.php` | JSON body parser, CSRF ทุก mutation ของ session API และ response/error envelope ของ v1 |
| `Learning::curriculumEditable()` | แทนที่การปิดแก้หลังมีผู้เรียนด้วย release/version service และ migration ประวัติ |
| `app/Quiz.php`, `app/RichText.php`, `app/Upload.php` | แยก service ให้รับ DTO แทนอ่าน `$_POST` โดยตรง เพื่อใช้ทั้งเว็บ API และ worker; เพิ่ม assessment renderer และ validation |
| `bin/migrate.php`, `database/schema.sql` | migration ที่ทำซ้ำต่อได้และ schema สำหรับติดตั้งใหม่ตรงกัน |
| `views/`, `public/assets/` | AI Studio, หน้า review, ปุ่ม publish, chat และหน้ารายงานตามสิทธิ์ |
| `compose*.yaml`, `bin/` | worker service, private assets และ secret configuration โดยไม่เรียก provider จริงใน test |

ลำดับส่งมอบ:

1. **โครงสร้างหลัก:** release/version, capability, API envelope, queue, provider interface, mock provider, quota และ audit
2. **Content + Exam & Worksheet:** สร้าง/แก้/ตรวจ/เผยแพร่ครบวงจร เริ่ม MCQ และใบงานก่อนเพิ่มข้อสอบปลายเปิด
3. **Learning Assistant:** indexing แบบแยกสิทธิ์, citations, teacher preview สำหรับครูเท่านั้น
4. **Learning Analytics:** ตัวเลขจากข้อมูลที่มีจริง → คำแนะนำส่วนตัวให้ครูตรวจ → เพิ่ม objective/event mapping ตามตัวชี้วัดที่ต้องการ
5. **Video Lesson:** storyboard → เสียงไทย/สไลด์/คำบรรยาย → render worker → ตรวจและเผยแพร่

ก่อนใช้งานจริงต้องผ่านกรณีสำคัญ: ครูข้ามรายวิชาไม่ได้; นักศึกษาอ่าน draft/เฉลย/บทล็อก/บทสนทนาคนอื่นไม่ได้; สิทธิ์ถูกเพิกถอนมีผลกับงานค้างและ asset; source injection ไม่ทำให้ข้ามสิทธิ์; publish/request ซ้ำไม่สร้างข้อมูลซ้ำ; worker timeout ไม่คิด ledger ซ้ำ; concurrent requests ไม่ทะลุวงเงิน; เปลี่ยน release ไม่ทำลายข้อสอบที่กำลังทำ; retry และ cancel มีผลลัพธ์ชัดเจน; provider failure ยังเก็บฉบับร่างและประวัติได้; เสียงไทยและคำบรรยายผ่านการตรวจโดยผู้สอน

## 11. เอกสารประกอบ

- [OpenAPI 3.1 สำหรับ session API ระยะแรก](ai-teacher-api.openapi.json) — สัญญา request/response สำหรับเริ่มพัฒนา; schema ผลงานแยกประเภทและ API ผู้ดูแล/release migration ต้องขยายในขั้น implementation
- [OWASP: Broken Object Level Authorization](https://api-security.owasp.org/editions/2023/en/0xa1-broken-object-level-authorization/) — อ้างอิงการตรวจสิทธิ์ทุก object ที่เรียกผ่าน API
- [OWASP: LLM Prompt Injection Prevention](https://cheatsheetseries.owasp.org/cheatsheets/LLM_Prompt_Injection_Prevention_Cheat_Sheet.html) — อ้างอิงการแยกคำสั่ง/ข้อมูลและการป้องกันหลายชั้น
- [OWASP: Secrets Management](https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html) — อ้างอิงการจัดเก็บ การจำกัดสิทธิ์ และวงจรชีวิตของ secret
- [OWASP: SSRF Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html) — อ้างอิงการจำกัดปลายทางที่เซิร์ฟเวอร์เชื่อมต่อ

ยังต้องเลือก provider/model ทดสอบคุณภาพภาษาไทย กำหนดงบและนโยบายข้อมูลก่อนเชื่อมบริการจริง ข้อเสนอทั้งหมดนี้ยังไม่เรียกบริการ AI ที่มีค่าใช้จ่าย

## 12. ผู้ให้บริการที่ Admin จัดการ และ BYOK เฉพาะครู

ส่วนที่พัฒนาแล้วมีคู่มือใน [การติดตั้ง BYOK](ai-byok-setup.md) ส่วนสร้างฉบับร่างบทเรียน ใบงาน และข้อสอบเพิ่มแล้วตาม [คู่มือ AI สร้างฉบับร่าง](ai-drafts-setup.md) โดยใช้เส้นทางที่ระบุในคู่มือ ส่วนวิดีโอ ผู้ช่วยสอน วิเคราะห์ผล และ worker ยังเป็นแผนพัฒนา

### รายการผู้ให้บริการ

- Admin เปิด `/admin/ai-providers` เพิ่มรหัส/ชื่อผู้ให้บริการ เลือก adapter, Base URL, รายการ model ID แยก text/embedding/speech/analytics/assistant และสถานะเปิดใช้งาน
- รองรับการตรวจ key ผ่าน OpenAI-compatible (Bearer), Anthropic (x-api-key) และ Gemini (x-goog-api-key) โดยใช้ `GET {Base URL}/models` การเพิ่มรายการไม่เท่ากับรองรับการสร้างผลงานครบทุก modality; ต้องเพิ่มตัวสร้างตาม API ของบริการนั้นภายหลัง
- รหัส provider เปลี่ยนไม่ได้ แก้ไขใช้ expected_revision ป้องกันหน้าจอเก่าเขียนทับ ไม่มีการลบรายการที่อาจมี key/ประวัติอ้างอิง ให้ปิดใช้แทน
- URL ต้องเป็น HTTPS พอร์ต 443 ของโดเมนสาธารณะ ไม่มี userinfo/query/fragment เมื่อเชื่อมต่อจะตรวจ IP จาก DNS และ pin cURL กับ IP ที่ตรวจแล้ว ไม่ตาม redirect หรือ proxy จาก environment
- ครูเห็นเฉพาะผู้ให้บริการที่เปิดใช้ และเลือก model ได้ตาม capability ของ provider ที่ key นั้นผูกอยู่ ไม่รวม model จากทุก provider มาปนกัน
- เปลี่ยน configuration จะเพิ่ม revision ทุกครั้ง การเชื่อมต่อเดิมต้องยืนยัน revision/ปลายทางและทดสอบใหม่ ก่อนบันทึกแหล่งจ่ายรายวิชาใหม่ ไม่ส่ง key ไปยัง host ใหม่โดยอัตโนมัติ

### การจัดการ key ของครู

ครูเพิ่ม/เปลี่ยน/เพิกถอน key ของตนจาก `/teach/ai-connections` เก็บด้วย AES-256-GCM และกุญแจเข้ารหัสแยกจากฐานข้อมูล ใช้ authenticated metadata ผูก credential/owner/provider/version ไม่มี reveal API และ Admin ไม่สามารถอ่านหรือทดสอบ key ของครูคนอื่นได้

ก่อนกดทดสอบ แสดงชื่อและ Base URL ของผู้ให้บริการ รับ `confirmed_provider_revision` เพื่อยืนยันปลายทาง การเพิกถอนลบ secret ใน LMS ไม่ใช่การ revoke ที่บัญชี provider การเปลี่ยน key ทำให้ version เก่าเริ่มงานใหม่ไม่ได้

### แหล่งจ่ายและงบ

รายวิชาผูก key ครู หรือ key ส่วนกลางที่ได้รับสิทธิ์ แยกตาม capability พร้อม provider/model/revision และเพดาน USD/เดือน จองงบใน transaction ก่อนเรียก provider และตรวจสิทธิ์/สถานะ/provider revision ซ้ำก่อนเริ่ม ไม่เปลี่ยนผู้จ่ายหรือ model อัตโนมัติเมื่อผิดพลาด ยอด LMS ควบคุมเฉพาะการเรียกจาก LMS ไม่ใช่ยอดทั้งบัญชี provider

### ยกเลิกบริการ AI ของนักศึกษา

ไม่มีหน้าจอ AI, conversation API หรือ recommendation API สำหรับนักศึกษา ไม่รับ `allow_student_usage` หรือ `student_daily_request_limit` อีกต่อไป ทุก AI API และขั้น reserve/begin อนุญาตเฉพาะ teacher/admin แม้มี enrollment หรือ flag เก่าที่อนุญาตนักศึกษา

Migration `007_admin_ai_providers` ล้าง flag นักศึกษาและเพิ่ม revision ของ billing เก่า เพื่อให้ครูบันทึกแหล่งจ่ายใหม่ พร้อมปล่อยเงินจองของนักศึกษาที่ยังไม่เริ่ม งานที่ส่งไปแล้วและประวัติยอดใช้ไม่ถูกลบ

### API ที่พัฒนาเพิ่ม

| Method / Path ต่อจาก `/api/v1` | ผู้ใช้ / หน้าที่ |
| --- | --- |
| `GET /admin/ai-providers` | Admin อ่านทุกรายการรวมที่ปิด |
| `POST /admin/ai-providers` | Admin เพิ่มผู้ให้บริการ รหัสไม่ซ้ำ |
| `PUT /admin/ai-providers/{slug}` | Admin แก้ไข/เปิดปิด พร้อม expected_revision |
| `GET /me/ai-capabilities` | ครูอ่าน provider catalog ที่เปิดใช้และโมเดลของแต่ละราย |
| `GET/POST /me/ai-credentials` | ครูอ่าน metadata / เพิ่ม key ของตน |
| `POST /me/ai-credentials/{id}/verify` | ครูยืนยันปลายทางและทดสอบโดยไม่สร้างเนื้อหา |
| `PUT /me/ai-credentials/{id}/secret` | ครูเปลี่ยน key ของตน |
| `POST /me/ai-credentials/{id}/revoke` | ครูปิดใช้ key ใน LMS |
| `GET/PUT /courses/{id}/ai/billing` | ครูเจ้าของรายวิชาอ่าน/บันทึกแหล่งจ่าย |
| `GET /courses/{id}/ai/usage` | ครูเจ้าของรายวิชาอ่านประวัติยอดใช้รวมเงินจอง |

ทุก mutation ตรวจ CSRF, Origin และ recent authentication เพิ่มตาราง `ai_providers`, provider_revision ใน credentials และ reservations คง secret ของครูแยกจาก provider catalog ไม่มีการเรียก provider จริงระหว่างชุดทดสอบ
