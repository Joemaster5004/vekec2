<?php
declare(strict_types=1);
namespace App;

final class Banner {
    public static function all(bool $publishedOnly = false): array {
        return DB::all('SELECT * FROM banners'.($publishedOnly ? ' WHERE active=1' : '').' ORDER BY sort_order,id');
    }

    public static function save(?int $id = null): void {
        Auth::require(['admin']);
        $title = input('title', 960);
        $link = input('link_url', 2000, false);
        // Only explicit HTTP(S) URLs or local absolute paths; reject protocol-relative URLs and controls.
        if ($link !== '' && (preg_match('/[\x00-\x20\x7f\\\\]/', $link) ||
            !(preg_match('#^/(?!/)#', $link) || (filter_var($link, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($link, PHP_URL_SCHEME) ?? ''), ['http','https'], true))))) {
            abort(422, 'ลิงก์ต้องขึ้นต้นด้วย / ภายในระบบ หรือ http:// หรือ https://');
        }
        $order = integer('sort_order', 0, 9999, 0);
        $active = integer('active', 0, 1, 0);
        $file = $_FILES['image'] ?? null;
        $name = null;
        $dimensions = null;
        if ($file && ($file['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
            if (!is_int($file['error'] ?? null) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) abort(422, 'อัปโหลดภาพไม่สำเร็จ');
            $size = filesize($file['tmp_name']);
            if (!$size || $size > 5 * 1024 * 1024) abort(422, 'ภาพ Banner ต้องมีขนาดไม่เกิน 5 MB');
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
            $dimensions = @getimagesize($file['tmp_name']);
            if (!isset($allowed[$mime]) || !$dimensions || ($dimensions['mime'] ?? '') !== $mime || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > intdiv(20000000, $dimensions[1])) abort(422, 'รองรับ JPG, PNG และ WebP ไม่เกิน 20 ล้านพิกเซล');
            $name = bin2hex(random_bytes(20)).'.'.$allowed[$mime];
        }
        $oldPath = null;
        try {
            DB::transaction(function () use ($id, $title, $link, $order, $active, $file, $name, $dimensions, &$oldPath): void {
                $old = $id === null ? null : (DB::one('SELECT * FROM banners WHERE id=? FOR UPDATE', [$id]) ?? abort(404, 'ไม่พบ Banner'));
                if (!$old && !$name) abort(422, 'กรุณาเลือกภาพ Banner');
                if ($name && !move_uploaded_file($file['tmp_name'], Upload::path($name))) abort(500, 'บันทึกภาพไม่สำเร็จ');
                $values = [$title, $link, $order, $active, $name ?? $old['image_path'], $dimensions[0] ?? $old['width'], $dimensions[1] ?? $old['height']];
                if ($id === null) {
                    DB::run('INSERT INTO banners(title,link_url,sort_order,active,image_path,width,height) VALUES(?,?,?,?,?,?,?)', $values);
                    $id = DB::id();
                    audit('banner.created', (string)$id);
                } else {
                    DB::run('UPDATE banners SET title=?,link_url=?,sort_order=?,active=?,image_path=?,width=?,height=? WHERE id=?', [...$values, $id]);
                    if ($name) $oldPath = $old['image_path'];
                    audit('banner.updated', (string)$id);
                }
            });
        } catch (\Throwable $error) {
            if ($name && is_file(Upload::path($name))) unlink(Upload::path($name));
            throw $error;
        }
        if ($oldPath && is_file(Upload::path($oldPath))) unlink(Upload::path($oldPath));
    }

    public static function delete(int $id): void {
        Auth::require(['admin']);
        $path = DB::transaction(function () use ($id): string {
            $banner = DB::one('SELECT * FROM banners WHERE id=? FOR UPDATE', [$id]) ?? abort(404, 'ไม่พบ Banner');
            DB::run('DELETE FROM banners WHERE id=?', [$id]);
            audit('banner.deleted', (string)$id);
            return $banner['image_path'];
        });
        if (is_file(Upload::path($path))) unlink(Upload::path($path));
    }

    public static function stream(int $id): never {
        $banner = DB::one('SELECT * FROM banners WHERE id=?', [$id]) ?? abort(404, 'ไม่พบภาพ Banner');
        if (!$banner['active'] && (Auth::user()['role'] ?? '') !== 'admin') abort(404, 'ไม่พบภาพ Banner');
        Upload::stream($banner['image_path'], true);
    }
}
