<?php
declare(strict_types=1);
namespace App;

final class CertificateImage {
    public const MAX_BYTES = 2097152;

    public static function inspect(string $content, string $label = 'ตราใบประกาศ'): array {
        if ($content === '' || strlen($content) > self::MAX_BYTES) abort(422, 'ภาพ'.$label.'ต้องมีขนาดไม่เกิน 2 MB');
        $allowed = ['image/png'=>['png', IMAGETYPE_PNG], 'image/jpeg'=>['jpg', IMAGETYPE_JPEG], 'image/webp'=>['webp', IMAGETYPE_WEBP]];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        $dimensions = @getimagesizefromstring($content);
        if (!isset($allowed[$mime]) || !$dimensions || $dimensions[2] !== $allowed[$mime][1] || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > intdiv(8000000, $dimensions[1])) abort(422, 'รองรับภาพ'.$label.' PNG, JPG หรือ WebP ที่ถูกต้อง ไม่เกิน 8 ล้านพิกเซล');
        return ['mime'=>$mime, 'extension'=>$allowed[$mime][0]];
    }

    public static function store(string $content, string $label = 'ตราใบประกาศ'): string {
        $info = self::inspect($content, $label);
        $name = bin2hex(random_bytes(20)).'.'.$info['extension'];
        $path = Upload::path($name);
        if (file_put_contents($path, $content, LOCK_EX) !== strlen($content)) {
            if (is_file($path)) unlink($path);
            abort(500, 'บันทึกภาพไม่สำเร็จ');
        }
        return $name;
    }

    public static function upload(string $field, string $label): ?string {
        $file = $_FILES[$field] ?? null;
        if ($file === null || (is_array($file) && ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE)) return null;
        if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) abort(422, 'อัปโหลด'.$label.'ไม่สำเร็จ กรุณาเลือกไฟล์ขนาดไม่เกิน 2 MB');
        $size = filesize($file['tmp_name']);
        if (!$size || $size > self::MAX_BYTES) abort(422, 'ภาพ'.$label.'ต้องมีขนาดไม่เกิน 2 MB');
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) abort(422, 'อ่านภาพ'.$label.'ไม่สำเร็จ');
        return self::store($content, $label);
    }

    private static function target(string $url): array {
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || !filter_var($url, FILTER_VALIDATE_URL)) abort(422, 'กรุณาใส่ URL รูปภาพ HTTPS ที่ถูกต้อง');
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || ($parts['port'] ?? 443) !== 443 || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host)) abort(422, 'URL ต้องเป็น HTTPS ของเว็บไซต์สาธารณะ ไม่ใช้ IP พอร์ตพิเศษ หรือข้อมูลเข้าสู่ระบบ');
        // Resolve once, reject non-public destinations, then pin cURL to the checked IP.
        // IPv4 resolution follows CNAMEs without permitting cURL to resolve a second time.
        $addresses = gethostbynamel($host);
        if (!$addresses) abort(422, 'ไม่พบเว็บไซต์ของรูปภาพ กรุณาตรวจสอบ URL หรืออัปโหลดไฟล์แทน');
        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_GLOBAL_RANGE)) abort(422, 'ไม่สามารถใช้ URL รูปภาพจากเครือข่ายภายในได้');
        }
        return [$host, $addresses[0]];
    }

    public static function download(string $url): string {
        $deadline = microtime(true) + 10;
        for ($redirect = 0; $redirect <= 3; $redirect++) {
            [$host, $address] = self::target($url);
            $remaining = (int)ceil($deadline - microtime(true));
            if ($remaining < 1) abort(422, 'ดาวน์โหลดรูปภาพหมดเวลา กรุณาอัปโหลดไฟล์แทน');
            $body = '';
            $oversized = false;
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_CONNECTTIMEOUT=>min(4, $remaining), CURLOPT_TIMEOUT=>$remaining,
                CURLOPT_PROXY=>'', CURLOPT_RESOLVE=>[$host.':443:'.$address],
                CURLOPT_HTTPHEADER=>['Accept: image/png, image/jpeg, image/webp'],
                CURLOPT_USERAGENT=>'VEKEC-Certificate-Image/1.0',
                CURLOPT_WRITEFUNCTION=>static function ($handle, string $chunk) use (&$body, &$oversized): int {
                    if (strlen($body) + strlen($chunk) > self::MAX_BYTES) { $oversized = true; return 0; }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            try {
                $ok = curl_exec($curl);
                $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                $next = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
            } finally { curl_close($curl); }
            if ($oversized) abort(422, 'ภาพตราใบประกาศต้องมีขนาดไม่เกิน 2 MB');
            if ($ok === false) abort(422, 'ดาวน์โหลดรูปภาพไม่สำเร็จ กรุณาตรวจสอบ URL หรืออัปโหลดไฟล์แทน');
            if (in_array($status, [301,302,303,307,308], true) && $next && $redirect < 3) { $url = $next; continue; }
            if ($status !== 200) abort(422, 'URL นี้ไม่ส่งรูปภาพโดยตรง กรุณาใช้ลิงก์รูปภาพ PNG, JPG หรือ WebP');
            self::inspect($body);
            return $body;
        }
        abort(422, 'ลิงก์รูปภาพเปลี่ยนปลายทางหลายครั้ง กรุณาอัปโหลดไฟล์แทน');
    }
}
