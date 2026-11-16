<?php
declare(strict_types=1);
namespace App;
final class Auth {
    public static function user(): ?array {
        if (empty($_SESSION['user_id'])) return null;
        return DB::one('SELECT id,name,email,role,level,created_at FROM users WHERE id=? AND active=1',[$_SESSION['user_id']]);
    }
    public static function require(array $roles=[]): array {
        $user=self::user(); if(!$user) { if(str_starts_with($_SERVER['REQUEST_URI']??'','/api/')) abort(401,'กรุณาเข้าสู่ระบบ'); redirect('/login'); }
        if($roles && !in_array($user['role'],$roles,true)) abort(403,'คุณไม่มีสิทธิ์ใช้งานส่วนนี้');
        return $user;
    }
    public static function login(int $id): void { session_regenerate_id(true); $_SESSION=['user_id'=>$id,'csrf'=>bin2hex(random_bytes(32)),'authenticated_at'=>time()]; }
    public static function limit(string $action,int $max=10,int $seconds=900): void {
        $bucket=hash_hmac('sha256',$action.'|'.($_SERVER['REMOTE_ADDR']??'cli'),env('APP_KEY'));
        DB::run('INSERT INTO rate_limits(bucket,hits,expires_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE hits=IF(expires_at<UNIX_TIMESTAMP(),1,hits+1),expires_at=IF(expires_at<UNIX_TIMESTAMP(),VALUES(expires_at),expires_at)',[$bucket,time()+$seconds]);
        if((int)DB::one('SELECT hits FROM rate_limits WHERE bucket=?',[$bucket])['hits']>$max) abort(429,'ทำรายการบ่อยเกินไป กรุณาลองใหม่ในภายหลัง');
    }
    public static function register(bool $byTeacher=false): int {
        if(!$byTeacher && setting('registration_open')!=='1') abort(403,'ขณะนี้ปิดรับสมัครสมาชิกชั่วคราว');
        $name=input('name',540); $email=strtolower(input('email',190)); $password=input('password',72);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) abort(422,'รูปแบบอีเมลไม่ถูกต้อง');
        if(strlen($password)<12) abort(422,'รหัสผ่านต้องมีอย่างน้อย 12 ตัวอักษร');
        $level=input('level',20); if(!in_array($level,['voc1','voc2','public'],true)) abort(422,'กรุณาเลือกระดับผู้เรียน');
        if(DB::one('SELECT id FROM users WHERE email=?',[$email])) abort(422,'อีเมลนี้ลงทะเบียนแล้ว กรุณาเข้าสู่ระบบ');
        DB::run('INSERT INTO users(name,email,password_hash,level) VALUES(?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),$level]);
        return DB::id();
    }
}
