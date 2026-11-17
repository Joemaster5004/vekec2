<?php
declare(strict_types=1);
namespace App;

final class EditorImage {
    private static function filename(string $path, int $status = 422): string {
        if (str_starts_with($path, '/media/images/')) $path = substr($path, strlen('/media/images/'));
        if (!preg_match('/^[a-f0-9]{40}\.(?:jpg|png|webp)$/D', $path)) abort($status, 'รูปภาพไม่ถูกต้อง');
        return $path;
    }

    public static function upload(int $courseId): array {
        Learning::manage($courseId);
        $user = Auth::require(['admin','teacher']);
        $file = $_FILES['image'] ?? null;
        if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            abort(422, 'กรุณาเลือกรูปภาพที่อัปโหลดสำเร็จ');
        }
        $size = filesize($file['tmp_name']);
        if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) abort(422, 'รูปภาพต้องมีขนาดไม่เกิน 5 MB');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = ['image/jpeg' => ['jpg', IMAGETYPE_JPEG], 'image/png' => ['png', IMAGETYPE_PNG], 'image/webp' => ['webp', IMAGETYPE_WEBP]];
        $dimensions = @getimagesize($file['tmp_name']);
        if (!isset($allowed[$mime]) || !$dimensions || $dimensions[2] !== $allowed[$mime][1] || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > intdiv(20000000, $dimensions[1])) {
            abort(422, 'รองรับรูปภาพ JPG, PNG และ WebP ที่ถูกต้อง ไม่เกิน 20 ล้านพิกเซล');
        }
        $name = bin2hex(random_bytes(20)) . '.' . $allowed[$mime][0];
        $destination = Upload::path($name);
        $moved = false;
        try {
            DB::transaction(function () use ($courseId, $user, $file, $name, $destination, &$moved): void {
                DB::one('SELECT id FROM courses WHERE id=? FOR UPDATE', [$courseId]) ?? abort(404, 'ไม่พบรายวิชา');
                Learning::curriculumEditable($courseId);
                if (!move_uploaded_file($file['tmp_name'], $destination)) abort(500, 'บันทึกรูปภาพไม่สำเร็จ');
                $moved = true;
                DB::run('INSERT INTO editor_images(path,course_id,uploaded_by) VALUES(?,?,?)', [$name, $courseId, $user['id']]);
                audit('editor.image_uploaded', $name);
            });
        } catch (\Throwable $error) {
            if ($moved && is_file($destination)) unlink($destination);
            throw $error;
        }
        return ['url' => '/media/images/' . $name];
    }

    public static function validateReferences(array $paths, int $courseId): void {
        $names = [];
        foreach ($paths as $path) {
            if (!is_string($path)) abort(422, 'รูปภาพไม่ถูกต้อง');
            $names[] = self::filename($path);
        }
        $names = array_values(array_unique($names));
        if (!$names) return;
        $found = DB::all('SELECT path FROM editor_images WHERE course_id=? AND path IN (' . implode(',', array_fill(0, count($names), '?')) . ') LOCK IN SHARE MODE', [$courseId, ...$names]);
        if (count($found) !== count($names)) abort(422, 'รูปภาพต้องเป็นภาพที่อัปโหลดในรายวิชานี้เท่านั้น');
    }

    public static function attach(int $lessonId, string $context, array $contents): void {
        if (!in_array($context, ['body','question','explanation'], true)) abort(422, 'ประเภทเนื้อหารูปภาพไม่ถูกต้อง');
        $lesson = DB::one('SELECT course_id FROM lessons WHERE id=?', [$lessonId]) ?? abort(404, 'ไม่พบบทเรียน');
        $paths = [];
        foreach ($contents as $content) {
            if (!is_string($content)) abort(422, 'เนื้อหารูปภาพไม่ถูกต้อง');
            $paths = array_merge($paths, RichText::imagePaths($content));
        }
        $paths = array_values(array_unique($paths));
        self::validateReferences($paths, (int)$lesson['course_id']);
        foreach ($paths as $path) DB::run('INSERT IGNORE INTO editor_image_uses(path,lesson_id,context) VALUES(?,?,?)', [$path, $lessonId, $context]);
    }

    public static function stream(string $path): never {
        $user = Auth::require();
        $name = self::filename($path, 404);
        $image = DB::one('SELECT i.*,c.teacher_id FROM editor_images i JOIN courses c ON c.id=i.course_id WHERE i.path=?', [$name]) ?? abort(404, 'ไม่พบรูปภาพ');
        $manager = $user['role'] === 'admin' || ($user['role'] === 'teacher' && (int)$image['teacher_id'] === (int)$user['id']);
        if ($manager) Upload::stream($name, true);
        $userId = (int)$user['id'];
        if (!Learning::enrolled($userId, (int)$image['course_id'])) abort(403, 'คุณไม่มีสิทธิ์เข้าถึงรูปภาพนี้');
        $uses = DB::all('SELECT l.*,c.sequential,u.context FROM editor_image_uses u JOIN lessons l ON l.id=u.lesson_id JOIN courses c ON c.id=l.course_id WHERE u.path=? AND l.course_id=?', [$name, $image['course_id']]);
        foreach ($uses as $lesson) {
            if (!Learning::unlocked($lesson, $userId)) continue;
            if ($lesson['context'] === 'body') Upload::stream($name, true);
            if ($lesson['kind'] !== 'quiz') continue;
            $review = Quiz::reviewAllowed((int)$lesson['id'], $userId);
            if ($lesson['context'] === 'explanation' && $review) Upload::stream($name, true);
            if ($lesson['context'] === 'question' && ($review || DB::one('SELECT id FROM attempts WHERE lesson_id=? AND user_id=? AND submitted_at IS NULL LIMIT 1', [$lesson['id'], $userId]))) Upload::stream($name, true);
        }
        abort(403, 'ยังไม่สามารถเข้าถึงรูปภาพในกิจกรรมนี้ได้');
    }
}
