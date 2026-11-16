<?php
declare(strict_types=1);
namespace App;
final class Upload {
    public static function path(string $name): string {
        if(!preg_match('/^[a-f0-9]{40}\.(mp4|pdf|jpg|png|webp)$/',$name))abort(404,'ไม่พบไฟล์');
        return dirname(__DIR__).'/storage/'.$name;
    }
    public static function save(string $field,string $kind): ?string {
        $file=$_FILES[$field]??null;if(!$file||$file['error']===UPLOAD_ERR_NO_FILE)return null;
        if(is_array($file['error'])||$file['error']!==UPLOAD_ERR_OK)abort(422,'อัปโหลดไม่สำเร็จ กรุณาตรวจสอบขนาดไฟล์');
        $max=$kind==='video'?256*1024*1024:10*1024*1024;if($file['size']>$max)abort(422,'ไฟล์ใหญ่เกินกำหนด');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed=$kind==='video'?['video/mp4'=>'mp4']:['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
        if(!isset($allowed[$mime]))abort(422,$kind==='video'?'รองรับเฉพาะไฟล์ MP4':'รองรับไฟล์ PDF, JPG และ PNG');
        $name=bin2hex(random_bytes(20)).'.'.$allowed[$mime];
        if(!move_uploaded_file($file['tmp_name'],self::path($name)))abort(500,'บันทึกไฟล์ไม่สำเร็จ');
        return $name;
    }
    public static function duration(string $name): int {
        $output=shell_exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '.escapeshellarg(self::path($name)).' 2>/dev/null');
        $duration=(float)trim((string)$output);if(!is_finite($duration)||$duration<=0||$duration>43200)abort(422,'ไม่สามารถอ่านความยาว MP4 หรือวิดีโอยาวเกิน 12 ชั่วโมง');
        return (int)ceil($duration);
    }
    public static function stream(string $name,bool $inline=false): never {
        $path=self::path($name);if(!is_file($path))abort(404,'ไม่พบไฟล์');
        $size=filesize($path);$start=0;$end=$size-1;
        if(isset($_SERVER['HTTP_RANGE'])) {
            if(!preg_match('/^bytes=(\d*)-(\d*)$/',$_SERVER['HTTP_RANGE'],$m)||($m[1]===''&&$m[2]==='')){http_response_code(416);header("Content-Range: bytes */$size");exit;}
            if($m[1]==='')$start=max(0,$size-(int)$m[2]);else{$start=(int)$m[1];if($m[2]!=='')$end=min($end,(int)$m[2]);}
            if($start>$end||$start>=$size){http_response_code(416);header("Content-Range: bytes */$size");exit;}
            http_response_code(206);header("Content-Range: bytes $start-$end/$size");
        }
        header('Content-Type: '.(new \finfo(FILEINFO_MIME_TYPE))->file($path));header('Accept-Ranges: bytes');header('Content-Length: '.($end-$start+1));
        header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.$name.'"');
        session_write_close();while(ob_get_level()>0)ob_end_clean();$f=fopen($path,'rb');fseek($f,$start);$remaining=$end-$start+1;
        while($remaining>0&&!feof($f)&&!connection_aborted()){$chunk=fread($f,min(65536,$remaining));echo $chunk;$remaining-=strlen($chunk);}
        fclose($f);exit;
    }
}
