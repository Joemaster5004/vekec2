<?php
declare(strict_types=1);
namespace App;

final class CertificateNumbers {
    public static function create(): void {
        Auth::require(['admin']);
        $prefix=input('prefix',120,false);
        if (preg_match_all('/./us',$prefix)>30 || preg_match('/[\x00-\x1f\x7f]/',$prefix)) abort(422,'คำนำหน้าไม่เกิน 30 ตัวอักษร');
        $year=integer('year',2500,3000,2569);
        $start=integer('start_number',1,999999999,1);
        $end=integer('end_number',$start,999999999,$start);
        $digits=integer('digits',1,9,4);
        DB::transaction(function()use($prefix,$year,$start,$end,$digits){
            DB::one('SELECT id FROM certificate_numbering WHERE id=1 FOR UPDATE');
            if(DB::one('SELECT id FROM certificate_number_ranges WHERE prefix=? AND year=? AND start_number<=? AND end_number>=?',[$prefix,$year,$end,$start])) abort(422,'ช่วงเลขทับซ้อนกับชุดเดิม กรุณาตรวจสอบกับงานทะเบียน');
            DB::run('INSERT INTO certificate_number_ranges(prefix,year,start_number,end_number,next_number,digits) VALUES(?,?,?,?,?,?)',[$prefix,$year,$start,$end,$start,$digits]);
            audit('certificate.range_created',(string)DB::id());
        });
    }
    public static function activate(int $id): void {
        Auth::require(['admin']);
        DB::transaction(function()use($id){
            DB::one('SELECT id FROM certificate_numbering WHERE id=1 FOR UPDATE');
            if($id && !DB::one('SELECT id FROM certificate_number_ranges WHERE id=? AND next_number<=end_number',[$id])) abort(422,'ไม่พบชุดเลขหรือเลขชุดนี้หมดแล้ว');
            DB::run('UPDATE certificate_numbering SET range_id=? WHERE id=1',[$id ?: null]);
            audit('certificate.range_activated',(string)$id);
        });
    }
    public static function format(array $range,int $number): string {
        return ($range['prefix']!==''?$range['prefix'].' ':'').str_pad((string)$number,(int)$range['digits'],'0',STR_PAD_LEFT).'/'.$range['year'];
    }
    // Called inside the same transaction that inserts the certificate. The singleton
    // lock serializes allocation across all courses and protects range activation.
    public static function allocate(): array {
        $config=DB::one('SELECT range_id FROM certificate_numbering WHERE id=1 FOR UPDATE');
        if(!$config['range_id']) abort(409,'ยังไม่ได้เปิดใช้ชุดเลขทะเบียน กรุณาติดต่อผู้ดูแลระบบ');
        $range=DB::one('SELECT * FROM certificate_number_ranges WHERE id=?',[$config['range_id']]);
        if((int)$range['next_number']>(int)$range['end_number']) abort(409,'เลขทะเบียนเกียรติบัตรชุดปัจจุบันหมดแล้ว กรุณาติดต่อผู้ดูแลระบบ');
        $number=(int)$range['next_number'];
        DB::run('UPDATE certificate_number_ranges SET next_number=next_number+1 WHERE id=?',[$range['id']]);
        return [$range['id'],$number,self::format($range,$number)];
    }
    public static function export(): never {
        Auth::require(['admin']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="certificate-register.csv"');
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['เลขทะเบียน','ชื่อผู้รับ','รายวิชา','รหัสผู้เรียน','รหัสรายวิชา','วันที่ออก','เลขอ้างอิง LMS']);
        $result=DB::run('SELECT registration_number,recipient_name,course_title,user_id,course_id,issued_at,code FROM certificates ORDER BY id');
        while($row=$result->fetch_assoc()) {
            fputcsv($out,array_map(static function($value){$value=(string)($value??'');return preg_match('/^[\s]*[=+@-]/u',$value)?"'".$value:$value;},array_values($row)));
        }
        fclose($out);exit;
    }
}
