<?php
declare(strict_types=1);
namespace App;

require (is_file('/opt/vekec-vendor/autoload.php') ? '/opt/vekec-vendor' : dirname(__DIR__).'/vendor') . '/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__ . '/' . substr($class, 4) . '.php';
        if (is_file($file)) require $file;
    }
});
date_default_timezone_set('Asia/Bangkok');
function env(string $key, string $default = ''): string { return (string)(getenv($key) === false ? $default : getenv($key)); }
function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return rtrim(env('APP_URL', 'http://localhost:8088'), '/') . $path; }
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function abort(int $code, string $message): never { throw new HttpError($message, $code); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrfField(): string { return '<input type="hidden" name="_csrf" value="'.e(csrf()).'">'; }
function flash(string $message): void { $_SESSION['flash'] = $message; }
function setting(string $key, string $default = ''): string { return DB::one('SELECT setting_value FROM settings WHERE setting_key=?', [$key])['setting_value'] ?? $default; }
function audit(string $action, string $resource): void { DB::run('INSERT INTO audit_logs(user_id,action,resource) VALUES(?,?,?)', [Auth::user()['id'] ?? null, $action, $resource]); }
function levelLabel(string $level): string { return ['all'=>'ทุกระดับ','voc1'=>'ปวช.','voc2'=>'ปวส.','public'=>'ประชาชนทั่วไป'][$level] ?? $level; }
function dateThai(string $date): string { $t=strtotime($date); return date('d/m/', $t).((int)date('Y',$t)+543); }
function view(string $template, array $data = []): void { extract($data); require dirname(__DIR__).'/views/'.$template.'.php'; }
function input(string $key, int $max = 10000, bool $required = true): string {
    $raw = $_POST[$key] ?? ''; if (!is_string($raw)) abort(422, 'ข้อมูลไม่ถูกต้อง');
    $value = trim($raw); if (($required && $value === '') || strlen($value)>$max || !preg_match('//u',$value)) abort(422, 'กรุณาตรวจสอบข้อมูล: '.$key);
    $columnLimits=['name'=>180,'title'=>240,'category'=>100,'institution'=>240,'signatory'=>180];
    if(isset($columnLimits[$key]) && preg_match_all('/./us',$value)>$columnLimits[$key]) abort(422,'ข้อความยาวเกินกำหนด: '.$key);
    return $value;
}
function integer(string $key, int $min, int $max, int $default): int {
    $value = $_POST[$key] ?? $default;
    if (filter_var($value,FILTER_VALIDATE_INT) === false || (int)$value<$min || (int)$value>$max) abort(422,'ค่าตัวเลขไม่อยู่ในช่วงที่กำหนด: '.$key);
    return (int)$value;
}
if (PHP_SAPI !== 'cli') {
    session_name('vekec_session');
    session_set_cookie_params(['secure'=>str_starts_with(env('APP_URL'),'https://'),'httponly'=>true,'samesite'=>'Lax','path'=>'/']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://www.youtube.com https://s.ytimg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; media-src 'self' https:; frame-src https://www.youtube.com https://www.youtube-nocookie.com; connect-src 'self' https://www.youtube.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    header('Cache-Control: no-store');
    // v1 validates JSON, Origin and CSRF for every mutating method in AiApi.
    if (!str_starts_with(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/api/v1/') && $_SERVER['REQUEST_METHOD'] === 'POST' && (!is_string($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') || !hash_equals(csrf(), $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) abort(403,'เซสชันหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
}
