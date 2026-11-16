<?php
declare(strict_types=1);
namespace App;
final class AiInput {
    public static function fail(string $code,string $message,int $status=422): never { throw new AiError($code,$message,$status); }
    public static function text(array $a,string $key,int $max=1000): string {
        $v=$a[$key]??null;
        if(!is_string($v)||trim($v)===''||strlen($v)>$max||!preg_match('//u',$v)||str_contains($v,"\0"))self::fail('INVALID_INPUT','กรุณาตรวจสอบข้อมูล: '.$key);
        return trim($v);
    }
    public static function number(array $a,string $key,int $min,int $max): int {
        $v=$a[$key]??null;
        if(!is_int($v)||$v<$min||$v>$max)self::fail('INVALID_INPUT','ตัวเลขไม่อยู่ในช่วงที่กำหนด: '.$key);
        return $v;
    }
    public static function choice(array $a,string $key,array $choices): string {
        $v=self::text($a,$key);if(!in_array($v,$choices,true))self::fail('INVALID_INPUT','ค่าที่ไม่รองรับ: '.$key);return $v;
    }
    public static function only(array $a,array $allowed): void { if(array_diff(array_keys($a),$allowed))self::fail('UNKNOWN_FIELD','พบฟิลด์ที่ไม่รองรับ'); }
    public static function json(mixed $v): string { return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
    public static function iso(?string $v): ?string { return $v===null?null:(new \DateTimeImmutable($v,new \DateTimeZone('Asia/Bangkok')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'); }
    public static function teacher(): array { return Auth::require(['teacher','admin']); }
    public static function manage(int $cid): array { return Learning::manage($cid); }
}
