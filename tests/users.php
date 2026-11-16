<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use App\{DB,HttpError,Users};
use function App\env;

if (PHP_SAPI !== 'cli' || env('APP_ENV') !== 'test' || env('DB_DATABASE') !== 'vekec_test') exit(1);
$_SESSION['user_id'] = DB::one("SELECT id FROM users WHERE email='admin@example.invalid'")['id'];
$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException($label);
    $checks++;
    echo 'PASS '.$label."\n";
};
$reject = static function (callable $fn, int $status, string $label) use ($check): void {
    try { $fn(); } catch (HttpError $error) { $check($error->getCode() === $status, $label); return; }
    throw new RuntimeException($label.' was allowed');
};

$suffix = bin2hex(random_bytes(6));
DB::run('INSERT INTO users(name,thaid_subject) VALUES(?,?)', ['สมาชิก ThaID ทดสอบ', hash('sha256', $suffix)]);
$id = DB::id();
$data = ['name'=>'สมาชิก ThaID ทดสอบ', 'email'=>'', 'password'=>'', 'role'=>'student', 'level'=>'voc1', 'active'=>'1'];
try {
    $check((bool)Users::find($id)['thaid'], 'ThaID account identified without exposing subject hash');
    Users::save($data, $id);
    $check(Users::find($id)['level'] === 'voc1', 'administrator can edit ThaID learner level');
    $reject(fn()=>Users::save(array_replace($data, ['name'=>'ชื่อปลอม']), $id), 422, 'ThaID verified name cannot be overwritten');
    $reject(fn()=>Users::save(array_replace($data, ['email'=>'forged@example.invalid']), $id), 422, 'ThaID account cannot acquire an email login');
    $reject(fn()=>Users::save(array_replace($data, ['password'=>'NewPassword!2026']), $id), 422, 'ThaID account cannot acquire a password');
    $record = DB::one('SELECT * FROM users WHERE id=?', [$id]);
    $check($record['password_hash'] === null && $record['email'] === null && $record['thaid_subject'] === hash('sha256', $suffix), 'failed changes preserve ThaID identity and authentication');
    Users::save(array_replace($data, ['active'=>'0']), $id);
    $check(!Users::find($id)['active'], 'administrator can suspend ThaID account');
} finally { DB::run('DELETE FROM users WHERE id=?', [$id]); }

$password = 'HashOnlyPassword!2026';
$id = Users::save(['name'=>'Hash test', 'email'=>$suffix.'@example.invalid', 'password'=>$password, 'active'=>'1']);
try {
    $record = DB::one('SELECT * FROM users WHERE id=?', [$id]);
    $check($record['password_hash'] !== $password && password_verify($password, $record['password_hash']), 'created member stores a verifiable hash only');
    $check(DB::one('SELECT COUNT(*) AS total FROM audit_logs WHERE resource LIKE ? OR action LIKE ?', ['%'.$password.'%', '%'.$password.'%'])['total'] === 0, 'audit trail contains no plaintext password');
} finally { DB::run('DELETE FROM users WHERE id=?', [$id]); }
echo "All $checks member service checks passed.\n";
