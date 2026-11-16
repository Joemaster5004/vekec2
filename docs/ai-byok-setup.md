# ตั้งค่าผู้ให้บริการ AI และ BYOK สำหรับครู

ปรับปรุง 26 ตุลาคม 2569: Admin จัดการผู้ให้บริการและโมเดลเองจากหน้าเว็บ AI ใช้เฉพาะครูผู้สอนและผู้ดูแลตามสิทธิ์ ไม่เปิดบริการ AI ให้นักศึกษา ไม่มีตัวเลือกหรือโควตา AI ของนักศึกษา

## ติดตั้งหรืออัปเกรด

สำรองฐานข้อมูลและ `.env` ตามวิธีเดิม แล้วรัน migration:

```sh
docker compose exec -T web php bin/migrate.php
```

Migration `006_ai_byok` สร้างระบบ key/งบ และ `007_admin_ai_providers` เพิ่ม catalog/provider revision ลบ flag นักศึกษาจาก billing เก่า เพิ่ม revision เพื่อให้ครูบันทึกแหล่งจ่ายใหม่ และปล่อยเงินจองของนักศึกษาที่ยังไม่เริ่ม ไม่ลบยอดใช้หรืองานที่เริ่มไปแล้ว การเปลี่ยนนี้ไม่แก้ผลเรียนและ enrollment เดิม

สำหรับครูใช้ BYOK ต้องตั้ง `.env`:

```dotenv
AI_ENABLED=true
AI_CREDENTIALS_ENCRYPTION_KEY=<base64 ของข้อมูลสุ่ม 32 ไบต์>
AI_INSTITUTION_PROVIDER=openai
AI_INSTITUTION_API_KEY=
AI_INSTITUTION_TEACHER_ACCESS=false
```

สร้างกุญแจเข้ารหัสแยกจาก `APP_KEY` ด้วย `openssl rand -base64 32` เก็บใน `.env` หรือ secret store อย่างปลอดภัย ห้ามใส่ source control หรือส่งในแชต ต้องเก็บสำรองแยกจากฐานข้อมูล ไม่เปลี่ยนกุญแจนี้ทุกครั้งที่ deploy หากสูญหายจะอ่าน key เดิมไม่ได้ ยังไม่มีคำสั่ง reencrypt ทั้งระบบสำหรับเปลี่ยนกุญแจหลัก แต่ครูเปลี่ยน API key ของตนจากเว็บได้

หลังเปลี่ยน environment ให้ recreate web:

```sh
docker compose up -d --force-recreate web
```

Production ใช้ `docker compose -f compose.production.yaml ...` ต้อง rebuild image เมื่อ source เปลี่ยน และใช้ HTTPS จริงกับ `APP_URL=https://...` หน้า Admin จัดการ catalog และยืนยันรหัสผ่านได้แม้ `AI_ENABLED=false` ส่วน API ของครูถูกปิดด้วย feature flag นี้

## Admin เพิ่มผู้ให้บริการ

เปิด **จัดการระบบ → ผู้ให้บริการ AI** ที่ `/admin/ai-providers`

1. เลือกแม่แบบ OpenAI-compatible, Anthropic หรือ Gemini หรือกำหนดชื่อเองโดยใช้ adapter ที่รองรับ
2. กรอกรหัสไม่ซ้ำ ชื่อที่ครูเห็น และ Base URL รวมเวอร์ชัน เช่น `https://api.example.com/v1` ระบบจะตรวจ key ด้วย `GET {Base URL}/models`
3. กำหนด model ID ที่อนุญาต แยกเนื้อหา/ข้อสอบ, embedding, เสียง, analytics และผู้ช่วยครู หนึ่งรายการต่อบรรทัดหรือคั่น comma เว้นว่างเมื่อไม่เปิด modality นั้น ผู้ให้บริการที่เปิดใช้ต้องมี model อย่างน้อยหนึ่งรายการ
   ในหน้าเว็บมีปุ่ม **เติมโมเดลตัวอย่างในช่องที่ว่าง** สำหรับแม่แบบปลายทางทางการ: OpenAI `gpt-5.4-mini`, Anthropic `claude-haiku-5-5`, Gemini `gemini-3.5-flash-lite` (ตรวจเอกสาร 3 พฤศจิกายน 2569) เติมเฉพาะช่องเนื้อหาและข้อสอบเมื่อว่าง ไม่บันทึกอัตโนมัติ และคงโมเดลเดิมไว้หากมีข้อมูลแล้ว ผู้ให้บริการ compatible ที่กำหนดเองต้องใช้ model ID จากปลายทางนั้น

   สำหรับระบบที่ติดตั้งอยู่ขณะนี้ ช่อง `text` ใช้สร้างบทเรียน ใบงาน และข้อสอบ ส่วน embedding/speech/analytics/assistant เว้นว่างได้เพราะยังไม่มีตัวสร้างงาน การกรอกโมเดลไม่ใช่การติดตั้งฟังก์ชันเหล่านั้น

4. เปิด/ปิดได้จากรายการ ครูเห็นเฉพาะรายการที่เปิด และเห็นโมเดลเฉพาะ provider ของ key ที่เลือก
5. แก้ไขใช้ expected revision เพื่อป้องกันหน้าจอเก่าทับข้อมูล ไม่มีปุ่มลบ provider เพราะมี key/ประวัติอ้างอิง ใช้ปิดใช้งานแทน

รหัส provider เปลี่ยนไม่ได้ การแก้ configuration เพิ่ม revision และทำให้ key เดิมต้องได้รับการยืนยัน/ทดสอบใหม่ ครูต้องเห็นและยืนยันชื่อ/Base URL ปัจจุบันก่อนระบบส่ง key ไปทดสอบ ไม่เปลี่ยนปลายทางอัตโนมัติ

Base URL ต้องเป็น HTTPS พอร์ต 443 ของโดเมนสาธารณะ ไม่มี userinfo/query/fragment ไม่ใช้ IP literal หรือ hostname ภายใน ระบบตรวจ DNS ทุกการเชื่อมต่อ ปฏิเสธ IP ภายใน และ pin cURL กับ IPv4 สาธารณะที่ตรวจแล้ว ไม่ตาม redirect หรือใช้ proxy จาก environment ขณะนี้ไม่รองรับ provider ที่มีเฉพาะ IPv6

แม่แบบตรวจ key ที่รองรับ:

| Adapter | Base URL ตัวอย่าง | วิธีส่ง key |
| --- | --- | --- |
| `openai_compatible` | `https://api.openai.com/v1` หรือ compatible API | Authorization: Bearer |
| `anthropic` | `https://api.anthropic.com/v1` | x-api-key และ anthropic-version |
| `gemini` | `https://generativelanguage.googleapis.com/v1beta` | x-goog-api-key |

ไม่รับ header อิสระหรือ API key จากหน้า provider catalog การเพิ่ม provider ไม่ได้ติดตั้งตัวสร้างเนื้อหา/วิดีโอของ provider นั้น ต้องเชื่อม adapter สำหรับงานจริงในอนาคตตามแต่ละ API

ค่ารุ่นเก่า `AI_TEXT_MODELS`, `AI_SPEECH_MODELS`, `AI_EMBEDDING_MODELS` ถูกนำเข้า OpenAI ครั้งเดียวเมื่อรัน migration 007 ถ้ายังไม่มีโมเดล รายการเริ่มต้น OpenAI จะปิดไว้ก่อน หลังจากนั้นจัดการในหน้า Admin ไม่ต้องแก้ environment เพื่อเพิ่ม provider/model อีก

## ครูตั้ง key และรายวิชา

เปิด **การเชื่อมต่อ AI** ที่ `/teach/ai-connections`

- เลือก provider ที่ Admin เปิดใช้ ตั้งชื่อการเชื่อมต่อ และกรอก key ของตนผ่าน HTTPS
- แสดงชื่อ/Base URL ของ provider ให้ตรวจ ก่อนกดทดสอบยืนยัน revision ปัจจุบัน การทดสอบอ่าน models ไม่สร้างเนื้อหาที่มีค่าใช้จ่าย และไม่รับประกันสิทธิ์ใช้งานทุก model หาก key ไม่ให้สิทธิ์ models endpoint จะทดสอบไม่ผ่าน
- เก็บ key ด้วย AES-256-GCM พร้อม authenticated metadata ผูก credential/owner/provider/version คืนเฉพาะ metadata/เลขท้าย ไม่มี reveal API แม้ Admin ก็อ่านหรือทดสอบ key ของครูคนอื่นไม่ได้ ผู้ควบคุมเซิร์ฟเวอร์และกุญแจเข้ารหัสยังเป็นส่วนที่ต้องเชื่อถือ
- เลือกรายวิชา กำหนดแหล่งจ่าย key ตนเองหรือส่วนกลางที่ได้รับอนุญาต เลือกโมเดลที่อนุญาตสำหรับ provider และ capability นั้น ตั้งเพดาน USD ต่อเดือน และยอมรับค่าใช้จ่าย
- ผู้ช่วยเป็นผู้ช่วยงานสอนสำหรับครูเท่านั้น ไม่มี checkbox อนุญาตนักศึกษา API ไม่รับ `allow_student_usage` หรือ `student_daily_request_limit` แม้ส่ง false
- หลังเข้าสู่ระบบเกิน 15 นาที ต้องยืนยันรหัสผ่าน LMS ก่อนแก้การตั้งค่าสำคัญ บัญชี ThaID ให้เข้าสู่ระบบใหม่
- เปลี่ยน key เพิ่ม version และต้องทดสอบใหม่ ปิดใช้ใน LMS จะลบ ciphertext/nonce/tag และไม่เปิดกลับ ต้องเพิ่มการเชื่อมต่อใหม่ การ revoke ที่ provider ต้องทำแยกต่างหาก

ส่วนกลางใช้ key หนึ่งชุดใน environment และเลือก provider slug ผ่าน `AI_INSTITUTION_PROVIDER` ครูใช้ได้เมื่อผู้ดูแลตั้ง `AI_INSTITUTION_TEACHER_ACCESS=true` ไม่ใช้ key ครูคนอื่นเป็นส่วนกลาง ไม่สลับผู้จ่ายอัตโนมัติเมื่อผิดพลาด

## งบและสิทธิ์ฝั่งเซิร์ฟเวอร์

ทุก `/api/v1/...` และ budget gateway ตรวจ active role เป็น teacher/admin นักศึกษาไม่มี AI API แม้ลงทะเบียนในรายวิชา มี flag เก่า หรือมีงานค้างจากรุ่นเดิม `reserve()` และ `begin()` ปฏิเสธ actor ที่เป็นนักศึกษา การเผยแพร่เนื้อหาปกติให้เรียนใน LMS ยังใช้สิทธิ์เดิม

งบ USD ต่อ course/capability/เดือน Asia/Bangkok ใช้จำนวนเต็ม micro-USD (1 USD = 1,000,000 micros) จองใน transaction และล็อก course ป้องกันคำขอพร้อมกันเกินงบ ตรวจเจ้าของรายวิชา/บัญชี/key version/provider revision/billing revision อีกครั้งก่อนเริ่ม

สำหรับตัวสร้างงานในอนาคต ใช้เฉพาะ backend ภายใน:

```php
$reservation = AiBilling::reserve($courseId, $teacherId, 'text', $serverUpperBoundMicros, $operationKey);
// ใส่เฉพาะ reservation ID เข้าคิว ไม่ใส่ secret
$connection = AiBilling::begin($reservation['id']);
// connection มี provider/configuration/secret/model สำหรับ backend เท่านั้น
// บังคับ token/duration ceiling ตาม upper bound แล้วเรียก provider
AiBilling::settle($reservation['id'], $actualMicros);
```

ห้ามเปิด endpoint คืน connection หรือรับต้นทุนประมาณจาก browser โดยตรง `begin()` เริ่มได้ครั้งเดียวและไม่เปลี่ยน provider/model กลางงาน ใช้ `release()` เฉพาะ reserved ที่ยังไม่เริ่ม งานที่ timeout หลังส่ง provider ต้องตรวจสถานะก่อน retry ไม่ปล่อยเงินจอง/ยิงซ้ำอัตโนมัติ

ledger แสดงยอดจริงที่ทราบรวมยอดจองคงค้าง ไม่ใช่ใบแจ้งหนี้ provider และไม่รวมการใช้ key จากโปรแกรมอื่น การจองเงินไม่รับประกันบัญชี provider ทั้งหมด ถ้าประมาณ upper bound ต่ำกว่าจริง ระบบบันทึกยอดจริงที่สูงกว่าได้เพื่อไม่บิดเบือนบัญชี ต้องแก้ rate/เพดานในตัวสร้างก่อนเปิดใช้จริง

## การทดสอบและขอบเขต

เพิ่มตัวสร้างฉบับร่างบทเรียน ใบงาน และข้อสอบแล้ว ดู [คู่มือ AI สร้างฉบับร่าง](ai-drafts-setup.md) ต้องรัน migration 008 และตั้งราคาของโมเดลก่อนใช้ ส่วน worker วิดีโอ ผู้ช่วยสอน วิเคราะห์ผล และ release/version ยังไม่ติดตั้ง

```sh
docker compose -f compose.test.yaml up -d
docker compose -f compose.test.yaml exec -T web php bin/migrate.php
docker compose -f compose.test.yaml exec -T web php tests/fixture.php
docker compose -f compose.test.yaml exec -T web php tests/ai_billing.php
docker compose -f compose.test.yaml exec -T web php tests/ai_providers.php
python3 tests/ai_providers_http.py
python3 tests/ai_concurrency.py
docker compose -f compose.test.yaml exec -T web php tests/ai_provider_migration.php
```

`ai_providers_http.py` รวม baseline BYOK HTTP แล้ว ไม่ต้องรัน baseline ซ้ำทันที ชุดเดิมมี rate limit และการทดสอบเกียรติบัตรที่ต้องเริ่มจากฐานข้อมูลว่าง จึงใช้ test environment ใหม่สำหรับ regression รอบเต็ม ไม่ reset ระบบหลัก

ทดสอบด้วย provider จำลองเฉพาะ APP_ENV=test และ AI_TEST_PROVIDER=true มี DOM checks สำหรับหน้าครูและ Admin ใช้ jsdom 26.1.0 ตรวจการกรองโมเดล การนำ UI นักศึกษาออก การป้องกัน XSS และการตั้งค่า provider ยังไม่ได้เรียก provider จริงหรือใช้ key ของผู้ใช้

ผลทดสอบรอบล่าสุด: HTTP BYOK 71 กรณี, HTTP provider 38 กรณี, service BYOK 22 กรณี, service provider 36 กรณี, DOM ครู 18 กรณี และ DOM Admin 11 กรณีผ่านทั้งหมด การจองงบพร้อมกัน 8 คำขอและ migration ล้างสิทธิ์นักศึกษา/คงประวัติผ่านด้วย Regression HTTP เดิมผ่าน 527 กรณีบนฐานข้อมูลทดสอบใหม่ ยังไม่ได้ตรวจภาพผ่าน browser จริง

บางบริการเปิด models endpoint แบบสาธารณะ การตรวจรายการ models สำเร็จจึงไม่รับประกันว่า key ใช้งาน generation ได้จริง ต้องตรวจสิทธิ์บัญชีและทดสอบตัวสร้างจริงเมื่อพัฒนา adapter งานนั้นแล้ว

อ้างอิง adapter: [Claude Models API](https://platform.claude.com/docs/en/api/models/list), [Gemini Models API](https://ai.google.dev/api/models) และ [OWASP SSRF Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html)

## เปิดผู้ให้บริการแล้ว แต่ครูยังพบ AI_DISABLED

สถานะผู้ให้บริการ (`enabled` ใน catalog) และสวิตช์ระบบ (`AI_ENABLED=true` ใน environment) เป็นคนละส่วน การเปิดผู้ให้บริการอย่างเดียวไม่เปิด API ของครู หน้า Admin แสดงสถานะสวิตช์ระบบและความพร้อมกุญแจเข้ารหัสแล้ว

ตรวจว่ามี `AI_ENABLED=true` และ `AI_CREDENTIALS_ENCRYPTION_KEY` ที่ถูกต้องใน `.env` จากนั้นรัน `docker compose up -d --force-recreate web` การ restart อย่างเดียวไม่โหลด environment ที่เพิ่มใหม่ ต้องเก็บกุญแจเข้ารหัสเดิมถาวร ห้ามสร้างทับเมื่อมี API key ที่เข้ารหัสอยู่แล้ว

หากระบบพร้อมแต่ยังไม่มีรายการให้ครูเลือก ให้ตรวจว่าผู้ให้บริการเปิดใช้งานและกำหนดโมเดลไว้แล้ว จากนั้นรีเฟรชหน้าการเชื่อมต่อ AI ของครู
