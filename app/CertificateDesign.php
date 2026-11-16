<?php
declare(strict_types=1);
namespace App;

final class CertificateDesign {
    public const STYLES = ['classic'=>'กรอบคลาสสิก', 'modern'=>'กรอบร่วมสมัย', 'minimal'=>'เรียบง่าย'];
    public const ALIGNMENTS = ['left'=>'ซ้าย', 'center'=>'กลาง', 'right'=>'ขวา'];
    public const TEXT_FIELDS = [
        'institution'=>[160, 'ชื่อสถานศึกษา'],
        'heading'=>[40, 'หัวเรื่องเกียรติบัตร'],
        'subtitle'=>[80, 'หัวเรื่องรอง'],
        'intro'=>[80, 'ข้อความก่อนชื่อผู้รับ'],
        'completion'=>[120, 'ข้อความรับรอง'],
        'director_name'=>[120, 'ชื่อผู้อำนวยการ'],
        'director_position'=>[120, 'ตำแหน่งผู้ลงนาม'],
    ];

    private static function settings(bool $lock = false): array {
        return array_column(DB::all("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('certificate_design','institution','signatory') ORDER BY setting_key".($lock?' FOR SHARE':'')), 'setting_value', 'setting_key');
    }

    private static function defaults(array $settings): array {
        $signatory = $settings['signatory'] ?? '';
        return [
            'version'=>1, 'style'=>'classic', 'accent'=>'#375a39',
            'institution'=>$settings['institution'] ?? '', 'heading'=>'เกียรติบัตร',
            'subtitle'=>'CERTIFICATE OF COMPLETION', 'intro'=>'ให้ไว้เพื่อแสดงว่า',
            'completion'=>'ได้ผ่านการเรียนรู้และการประเมินผลในหลักสูตร',
            'director_name'=>str_starts_with($signatory, 'ผู้อำนวยการ') ? '' : $signatory,
            'director_position'=>'ผู้อำนวยการ', 'signature_path'=>null,
            'signature_alignment'=>'center', 'signature_width'=>20, 'show_hours'=>true,
            'emblem_path'=>null, 'emblem_size'=>3.8,
        ];
    }

    public static function current(): array {
        $settings = self::settings();
        $stored = isset($settings['certificate_design']) ? json_decode($settings['certificate_design'], true, 16, JSON_THROW_ON_ERROR) : [];
        return array_replace(self::defaults($settings), $stored);
    }

    public static function issuance(): array {
        // Called inside the issuance transaction; read one consistent template and identity.
        $settings = self::settings(true);
        $design = isset($settings['certificate_design']) ? json_decode($settings['certificate_design'], true, 16, JSON_THROW_ON_ERROR) : null;
        return [
            'institution'=>$design['institution'] ?? $settings['institution'] ?? '',
            'signatory'=>$design['director_name'] ?? $settings['signatory'] ?? '',
            'design_snapshot'=>$design === null ? null : json_encode($design, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }

    public static function fromCertificate(array $certificate): ?array {
        if (empty($certificate['design_snapshot'])) return null;
        return json_decode($certificate['design_snapshot'], true, 16, JSON_THROW_ON_ERROR) + ['emblem_path'=>null, 'emblem_size'=>3.8];
    }

    public static function validate(array $data): array {
        $values = ['version'=>1];
        foreach (self::TEXT_FIELDS as $key=>[$max, $label]) {
            $raw = $data[$key] ?? '';
            if (!is_string($raw) || !preg_match('//u', $raw) || preg_match('/[\x00-\x1f\x7f]/', $raw)) abort(422, 'กรุณาตรวจสอบ'.$label);
            $value = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $raw);
            if (($key !== 'subtitle' && $value === '') || preg_match_all('/./us', $value) > $max) abort(422, $label.'ต้อง'.($key==='subtitle'?'ยาวไม่เกิน ':'มี 1–').$max.' ตัวอักษร');
            $values[$key] = $value;
        }
        $style = $data['style'] ?? '';
        $alignment = $data['signature_alignment'] ?? '';
        $accent = $data['accent'] ?? '';
        if (!is_string($style) || !isset(self::STYLES[$style])) abort(422, 'กรุณาเลือกรูปแบบกรอบ');
        if (!is_string($alignment) || !isset(self::ALIGNMENTS[$alignment])) abort(422, 'กรุณาเลือกตำแหน่งลายเซ็น');
        if (!is_string($accent) || !preg_match('/^#[a-f0-9]{6}$/iD', $accent)) abort(422, 'รูปแบบสีไม่ถูกต้อง');
        $width = filter_var($data['signature_width'] ?? null, FILTER_VALIDATE_INT);
        if ($width === false || $width < 12 || $width > 30) abort(422, 'ขนาดลายเซ็นต้องอยู่ระหว่าง 12–30');
        if (array_key_exists('emblem_size', $data)) {
            $size = filter_var($data['emblem_size'], FILTER_VALIDATE_FLOAT);
            if ($size === false || !is_finite($size) || $size < 3 || $size > 12 || abs($size * 10 - round($size * 10)) > .000001) abort(422, 'ขนาดตราต้องอยู่ระหว่าง 3–12% และปรับครั้งละ 0.1%');
            $values['emblem_size'] = $size;
        }
        return $values + [
            'style'=>$style, 'accent'=>strtolower($accent), 'signature_alignment'=>$alignment,
            'signature_width'=>$width, 'show_hours'=>isset($data['show_hours']),
        ];
    }

    public static function save(array $data): void {
        Auth::require(['admin']);
        $values = self::validate($data);
        $hasFile = isset($_FILES['signature']) && ($_FILES['signature']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasFile && isset($data['remove_signature'])) abort(422, 'กรุณาเลือกอัปโหลดลายเซ็นใหม่หรือนำลายเซ็นออกอย่างใดอย่างหนึ่ง');
        $emblemSource = $data['emblem_source'] ?? 'keep';
        if (!is_string($emblemSource) || !in_array($emblemSource, ['keep','upload','url','default'], true)) abort(422, 'กรุณาเลือกแหล่งที่มาของตราใบประกาศ');
        $uploaded = null;
        $emblem = null;
        try {
            $uploaded = CertificateImage::upload('signature', 'ลายเซ็น');
            if ($emblemSource === 'upload') $emblem = CertificateImage::upload('emblem', 'ตราใบประกาศ') ?? abort(422, 'กรุณาเลือกไฟล์ตราใบประกาศ');
            if ($emblemSource === 'url') {
                $url = $data['emblem_url'] ?? '';
                if (!is_string($url)) abort(422, 'URL รูปภาพไม่ถูกต้อง');
                $emblem = CertificateImage::store(CertificateImage::download(trim($url)));
            }
            DB::transaction(function () use ($values, $data, $uploaded, $emblem, $emblemSource): void {
                // These permanent rows also serialize the first template save before its JSON row exists.
                DB::all("SELECT setting_key FROM settings WHERE setting_key IN ('certificate_design','institution','signatory') ORDER BY setting_key FOR UPDATE");
                $previous = self::current();
                $values['signature_path'] = isset($data['remove_signature']) ? null : ($uploaded ?? $previous['signature_path']);
                $values['emblem_path'] = $emblemSource === 'default' ? null : ($emblem ?? $previous['emblem_path']);
                $values['emblem_size'] = $values['emblem_size'] ?? $previous['emblem_size'];
                foreach ([
                    'certificate_design'=>json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'institution'=>$values['institution'], 'signatory'=>$values['director_name'],
                ] as $key=>$value) DB::run('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', [$key, $value]);
                audit('certificate.design_saved', 'template');
                if ($uploaded) audit('certificate.signature_uploaded', $uploaded);
                if (isset($data['remove_signature']) && $previous['signature_path']) audit('certificate.signature_removed', 'template');
                if ($emblem) audit('certificate.emblem_uploaded', $emblem);
                if ($emblemSource === 'default' && $previous['emblem_path']) audit('certificate.emblem_removed', 'template');
            });
        } catch (\Throwable $error) {
            if ($uploaded && is_file(Upload::path($uploaded))) unlink(Upload::path($uploaded));
            if ($emblem && is_file(Upload::path($emblem))) unlink(Upload::path($emblem));
            throw $error;
        }
        // Prior images are immutable: issued certificates may still reference them.
    }

    public static function streamSignature(?string $code = null, bool $public = false): never {
        self::streamImage('signature', $code, $public);
    }

    public static function streamImage(string $kind, ?string $code = null, bool $public = false): never {
        if (!in_array($kind, ['signature','emblem'], true)) abort(404, 'ไม่พบรูปภาพ');
        if ($code === null) {
            Auth::require(['admin']);
            $design = self::current();
        } else {
            $certificate = DB::one('SELECT * FROM certificates WHERE code=?', [$code]) ?? abort(404, 'ไม่พบเกียรติบัตร');
            if (!$public) {
                $user = Auth::require();
                if ((int)$user['id'] !== (int)$certificate['user_id'] && $user['role'] !== 'admin') abort(403, 'ไม่มีสิทธิ์เข้าถึงเกียรติบัตรนี้');
            }
            $design = self::fromCertificate($certificate);
        }
        $name = $design[$kind.'_path'] ?? null;
        if (!is_string($name) || !preg_match('/^[a-f0-9]{40}\.(?:png|jpg|webp)$/D', $name)) abort(404, 'ไม่มีภาพ'.($kind==='emblem'?'ตราใบประกาศ':'ลายเซ็น'));
        Upload::stream($name, true);
    }

    public static function preview(array $design): array {
        return [
            'recipient_name'=>'ชื่อ–นามสกุลผู้รับเกียรติบัตร',
            'course_title'=>'ตัวอย่างหลักสูตรการพัฒนาทักษะอาชีพ', 'hours'=>12,
            'issued_at'=>date('Y-m-d H:i:s'), 'code'=>'PREVIEW',
            'institution'=>$design['institution'], 'signatory'=>$design['director_name'],
            'design_snapshot'=>json_encode($design, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
}
