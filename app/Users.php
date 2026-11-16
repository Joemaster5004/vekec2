<?php
declare(strict_types=1);
namespace App;

final class Users {
    public const ROLES = ['student'=>'ผู้เรียน', 'teacher'=>'ครูผู้สอน', 'admin'=>'ผู้ดูแลระบบ'];
    public const LEVELS = ['public'=>'ประชาชนทั่วไป', 'voc1'=>'ปวช.', 'voc2'=>'ปวส.'];
    public const IMPORT_LIMIT = 200;
    public const IMPORT_BYTES = 1048576;

    public static function find(int $id): array {
        Auth::require(['admin']);
        return DB::one('SELECT id,name,email,role,level,active,created_at,thaid_subject IS NOT NULL AS thaid FROM users WHERE id=?', [$id])
            ?? abort(404, 'ไม่พบบัญชีผู้ใช้งาน');
    }

    private static function field(array $data, string $key, string $default = ''): string {
        $value = array_key_exists($key, $data) ? $data[$key] : $default;
        if (!is_string($value) || !preg_match('//u', $value) || str_contains($value, "\0")) abort(422, 'ข้อมูลไม่ถูกต้อง: '.$key);
        if ($key === 'password') return trim($value); // Match the existing login normalization.
        return preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $value);
    }

    private static function validate(array $data, bool $creating, ?array $record = null): array {
        $name = self::field($data, 'name');
        if ($name === '' || preg_match_all('/./us', $name) > 180) abort(422, 'กรุณากรอกชื่อ–นามสกุล 1–180 ตัวอักษร');
        $email = strtolower(self::field($data, 'email'));
        $thaid = !empty($record['thaid_subject']);
        if ($thaid && ($name !== $record['name'] || $email !== ($record['email'] ?? ''))) abort(422, 'ไม่สามารถแก้ไขชื่อหรืออีเมลของบัญชีที่ยืนยันตัวตนด้วย ThaID ได้');
        if ((!$thaid || $email !== '') && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) abort(422, 'รูปแบบอีเมลไม่ถูกต้อง');
        $password = self::field($data, 'password');
        if ($thaid && $password !== '') abort(422, 'บัญชี ThaID ใช้การยืนยันตัวตนผ่าน ThaID');
        if (($creating || $password !== '') && (strlen($password) < 12 || strlen($password) > 72)) abort(422, 'รหัสผ่านต้องมีอย่างน้อย 12 ไบต์ และไม่เกิน 72 ไบต์');
        $role = self::field($data, 'role', 'student');
        $level = self::field($data, 'level', 'public');
        $active = self::field($data, 'active', '0');
        if (!isset(self::ROLES[$role])) abort(422, 'บทบาทไม่ถูกต้อง');
        if (!isset(self::LEVELS[$level])) abort(422, 'ระดับผู้เรียนไม่ถูกต้อง');
        if (!in_array($active, ['0', '1'], true)) abort(422, 'สถานะบัญชีต้องเป็น 1 (ใช้งาน) หรือ 0 (ระงับ)');
        return ['name'=>$name, 'email'=>$email ?: null, 'password'=>$password, 'role'=>$role, 'level'=>$level, 'active'=>(int)$active];
    }

    // Serialize administrator changes and recheck the actor after acquiring locks.
    // Two administrators cannot concurrently remove each other's access.
    private static function lockAccounts(?int $id): ?array {
        $actor = Auth::require(['admin']);
        $rows = DB::all("SELECT * FROM users WHERE (role='admin' AND active=1) OR id IN (?,?) ORDER BY id FOR UPDATE", [$actor['id'], $id ?? 0]);
        $accounts = array_column($rows, null, 'id');
        $current = $accounts[$actor['id']] ?? null;
        if (!$current || $current['role'] !== 'admin' || !$current['active']) abort(403, 'คุณไม่มีสิทธิ์จัดการบัญชีผู้ใช้งาน');
        return $id === null ? null : ($accounts[$id] ?? abort(404, 'ไม่พบบัญชีผู้ใช้งาน'));
    }

    private static function checkAccess(int $id, string $role, int $active): void {
        if ($id === (int)Auth::user()['id'] && ($role !== 'admin' || $active !== 1)) abort(422, 'ไม่สามารถเปลี่ยนสิทธิ์หรือระงับบัญชีของตนเองได้');
        if ($role === 'student' && DB::one('SELECT id FROM courses WHERE teacher_id=? LIMIT 1', [$id])) abort(409, 'บัญชีนี้มีรายวิชาที่ดูแลอยู่ จึงยังเปลี่ยนเป็นผู้เรียนไม่ได้');
    }

    public static function save(array $data, ?int $id = null): int {
        Auth::require(['admin']);
        try {
            return DB::transaction(function () use ($data, $id): int {
                $record = self::lockAccounts($id);
                $values = self::validate($data, $id === null, $record);
                if (DB::one('SELECT id FROM users WHERE email=? AND id<>?', [$values['email'], $id ?? 0])) abort(422, 'อีเมลนี้มีบัญชีอยู่แล้ว กรุณาใช้อีเมลอื่น');
                if ($id === null) {
                    DB::run('INSERT INTO users(name,email,password_hash,role,level,active) VALUES(?,?,?,?,?,?)', [
                        $values['name'], $values['email'], password_hash($values['password'], PASSWORD_DEFAULT), $values['role'], $values['level'], $values['active'],
                    ]);
                    $id = DB::id();
                    audit('user.created', (string)$id);
                } else {
                    self::checkAccess($id, $values['role'], $values['active']);
                    DB::run('UPDATE users SET name=?,email=?,role=?,level=?,active=? WHERE id=?', [
                        $values['name'], $values['email'], $values['role'], $values['level'], $values['active'], $id,
                    ]);
                    if ($values['password'] !== '') {
                        DB::run('UPDATE users SET password_hash=? WHERE id=?', [password_hash($values['password'], PASSWORD_DEFAULT), $id]);
                        audit('user.password_reset', (string)$id);
                    }
                    audit('user.updated', (string)$id);
                }
                return $id;
            });
        } catch (\mysqli_sql_exception $error) {
            if ($error->getCode() === 1062) abort(422, 'อีเมลนี้มีบัญชีอยู่แล้ว กรุณาใช้อีเมลอื่น');
            throw $error;
        }
    }

    public static function updateAccess(int $id): void {
        Auth::require(['admin']);
        $role = input('role', 20);
        if (!isset(self::ROLES[$role])) abort(422, 'บทบาทไม่ถูกต้อง');
        $active = isset($_POST['active']) ? 1 : 0;
        DB::transaction(function () use ($id, $role, $active): void {
            self::lockAccounts($id);
            self::checkAccess($id, $role, $active);
            DB::run('UPDATE users SET role=?,active=? WHERE id=?', [$role, $active, $id]);
            audit('user.updated', (string)$id);
        });
    }

    public static function references(int $id): array {
        Auth::require(['admin']);
        $references = [];
        foreach ([
            ['courses', 'teacher_id=?', 'รายวิชาที่ดูแล'],
            ['enrollments', 'user_id=? OR created_by=?', 'ประวัติลงทะเบียน'],
            ['progress', 'user_id=?', 'ความคืบหน้าการเรียน'],
            ['attempts', 'user_id=?', 'การทำข้อสอบ'],
            ['submissions', 'user_id=? OR graded_by=?', 'การส่งงานหรือให้คะแนน'],
            ['certificates', 'user_id=?', 'ใบประกาศนียบัตร'],
            ['editor_images', 'uploaded_by=?', 'รูปภาพในบทเรียน'],
        ] as [$table, $where, $label]) {
            $count = (int)DB::one('SELECT COUNT(*) AS total FROM '.$table.' WHERE '.$where, array_fill(0, substr_count($where, '?'), $id))['total'];
            if ($count) $references[$label] = $count;
        }
        if(DB::one("SELECT version FROM schema_migrations WHERE version='006_ai_byok'")) {
            foreach([
                ['ai_credentials','owner_user_id=?','การเชื่อมต่อ AI'],
                ['ai_billing','accepted_by=?','การยืนยันแหล่งจ่าย AI'],
                ['ai_usage_reservations','actor_id=? OR billing_owner_id=?','ประวัติการใช้และจองงบ AI'],
            ] as [$table,$where,$label]) {
                $count=(int)DB::one('SELECT COUNT(*) AS total FROM '.$table.' WHERE '.$where,array_fill(0,substr_count($where,'?'),$id))['total'];
                if($count)$references[$label]=$count;
            }
        }
        if(DB::one("SELECT version FROM schema_migrations WHERE version='008_ai_drafts'")) {
            $count=(int)DB::one('SELECT COUNT(*) AS total FROM ai_drafts WHERE actor_id=?',[$id])['total'];
            if($count)$references['ฉบับร่าง AI']=$count;
        }
        return $references;
    }

    public static function delete(int $id): void {
        $actor = Auth::require(['admin']);
        if ($id === (int)$actor['id']) abort(422, 'ไม่สามารถลบบัญชีของตนเองได้');
        if (($_POST['confirm_delete'] ?? '') !== '1') abort(422, 'กรุณายืนยันการลบบัญชี');
        try {
            DB::transaction(function () use ($id): void {
                self::lockAccounts($id);
                if (self::references($id)) abort(409, 'บัญชีนี้มีรายวิชาหรือประวัติที่เกี่ยวข้อง กรุณาระงับบัญชีแทนการลบเพื่อรักษาข้อมูล');
                DB::run('DELETE FROM users WHERE id=?', [$id]);
                audit('user.deleted', (string)$id);
            });
        } catch (\mysqli_sql_exception $error) {
            if ($error->getCode() === 1451) abort(409, 'บัญชีนี้มีข้อมูลที่เกี่ยวข้อง กรุณาระงับบัญชีแทนการลบ');
            throw $error;
        }
    }

    private static function readImport(): array {
        $file = $_FILES['members'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) abort(422, 'กรุณาเลือกไฟล์สมาชิก CSV หรือ JSON แล้วลองใหม่');
        if (!is_string($file['name'] ?? null)) abort(422, 'ชื่อไฟล์ไม่ถูกต้อง');
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'json'], true)) abort(422, 'รองรับเฉพาะไฟล์ .csv และ .json');
        if (filesize($file['tmp_name']) > self::IMPORT_BYTES) abort(422, 'ไฟล์ต้องมีขนาดไม่เกิน 1 MB');
        $content = file_get_contents($file['tmp_name']);
        if ($content === false || !preg_match('//u', $content) || str_contains($content, "\0")) abort(422, 'กรุณาบันทึกไฟล์เป็น UTF-8');
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $rows = [];
        if ($extension === 'json') {
            try { $items = json_decode($content, false, 16, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { abort(422, 'รูปแบบ JSON ไม่ถูกต้อง กรุณาตรวจสอบไฟล์ตามตัวอย่าง'); }
            if (!is_array($items)) abort(422, 'JSON ต้องเป็นรายการสมาชิกในอาร์เรย์');
            foreach ($items as $index => $item) {
                if (!$item instanceof \stdClass) abort(422, 'รายการที่ '.($index + 1).': ต้องเป็นข้อมูลสมาชิก');
                $row = (array)$item;
                // JSON permits numeric/boolean status; all other fields must be strings.
                if (isset($row['active']) && in_array($row['active'], [0, 1, false, true], true)) $row['active'] = $row['active'] ? '1' : '0';
                $rows[] = ['label'=>'รายการที่ '.($index + 1), 'data'=>$row];
                if (count($rows) > self::IMPORT_LIMIT) abort(422, 'นำเข้าได้ไม่เกิน '.self::IMPORT_LIMIT.' สมาชิกต่อครั้ง');
            }
        } else {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $content);
            rewind($stream);
            try {
                $headers = fgetcsv($stream, 0, ',', '"', '');
                if (!$headers) abort(422, 'ไฟล์ CSV ต้องมีหัวคอลัมน์ name,email,password');
                $headers = array_map(static fn($value) => trim((string)$value), $headers);
                if (count(array_unique($headers)) !== count($headers) || array_diff(['name', 'email', 'password'], $headers) || array_diff($headers, ['name', 'email', 'password', 'role', 'level', 'active'])) abort(422, 'หัวคอลัมน์ CSV ต้องมี name,email,password และเลือกเพิ่ม role,level,active ได้ โดยไม่ซ้ำกัน');
                $line = 2;
                while (!feof($stream)) {
                    $start = ftell($stream);
                    $values = fgetcsv($stream, 0, ',', '"', '');
                    if ($values === false) break;
                    $raw = substr($content, $start, ftell($stream) - $start);
                    $label = 'แถวที่ '.$line;
                    $line += max(1, preg_match_all('/\r\n|\r|\n/', $raw));
                    if ($values === [null]) continue;
                    if (!preg_match('/\A(?:[^",\r\n]*|"(?:[^"]|"")*")(?:,(?:[^",\r\n]*|"(?:[^"]|"")*"))*(?:\r\n|\r|\n)?\z/s', $raw)) abort(422, $label.': รูปแบบเครื่องหมายคำพูดใน CSV ไม่ถูกต้อง');
                    if (count($values) !== count($headers)) abort(422, $label.': จำนวนช่องไม่ตรงกับหัวคอลัมน์');
                    $rows[] = ['label'=>$label, 'data'=>array_combine($headers, $values)];
                    if (count($rows) > self::IMPORT_LIMIT) abort(422, 'นำเข้าได้ไม่เกิน '.self::IMPORT_LIMIT.' สมาชิกต่อครั้ง');
                }
            } finally { fclose($stream); }
        }
        if (!$rows) abort(422, 'ไฟล์ไม่มีข้อมูลสมาชิก');
        return $rows;
    }

    public static function import(): int {
        Auth::require(['admin']);
        $rows = self::readImport();
        $prepared = [];
        $seen = [];
        foreach ($rows as $row) {
            try {
                $data = $row['data'];
                if (array_diff(array_keys($data), ['name', 'email', 'password', 'role', 'level', 'active'])) abort(422, 'พบชื่อฟิลด์ที่ไม่รองรับ');
                foreach (['role'=>'student', 'level'=>'public', 'active'=>'1'] as $key => $default) {
                    if (!array_key_exists($key, $data) || $data[$key] === '') $data[$key] = $default;
                }
                $values = self::validate($data, true);
                if (isset($seen[$values['email']]) || DB::one('SELECT id FROM users WHERE email=?', [$values['email']])) abort(422, 'อีเมลซ้ำในไฟล์หรือมีบัญชีอยู่แล้ว');
                $seen[$values['email']] = true;
                $prepared[] = ['label'=>$row['label'], 'data'=>$values];
            } catch (HttpError $error) { abort($error->getCode(), $row['label'].': '.$error->getMessage().' (ยังไม่นำเข้าสมาชิกใด ๆ)'); }
        }
        // Hash only after all rows validate, before holding database locks.
        foreach ($prepared as &$row) {
            $row['data']['password_hash'] = password_hash($row['data']['password'], PASSWORD_DEFAULT);
            unset($row['data']['password']);
        }
        unset($row);
        return DB::transaction(function () use ($prepared): int {
            self::lockAccounts(null);
            foreach ($prepared as $row) {
                $values = $row['data'];
                try {
                    DB::run('INSERT INTO users(name,email,password_hash,role,level,active) VALUES(?,?,?,?,?,?)', [
                        $values['name'], $values['email'], $values['password_hash'], $values['role'], $values['level'], $values['active'],
                    ]);
                    audit('user.created', (string)DB::id());
                } catch (\mysqli_sql_exception $error) {
                    if ($error->getCode() === 1062) abort(422, $row['label'].': อีเมลซ้ำในไฟล์หรือมีบัญชีอยู่แล้ว (ยังไม่นำเข้าสมาชิกใด ๆ)');
                    throw $error;
                }
            }
            audit('users.imported', 'count='.count($prepared));
            return count($prepared);
        });
    }
}
