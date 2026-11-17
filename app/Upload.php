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
        $path=self::path($name);
        if(!is_file($path)||!is_readable($path))abort(422,'ไม่พบไฟล์ MP4 หรือเซิร์ฟเวอร์ไม่มีสิทธิ์อ่านไฟล์');
        // Read the movie header directly: shared hosting need not enable shell_exec/ffprobe.
        $duration=self::mp4Duration($path);
        if($duration===null && function_exists('shell_exec')) {
            $output=@shell_exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '.escapeshellarg($path).' 2>/dev/null');
            $value=trim((string)$output);
            if(is_numeric($value) && is_finite((float)$value) && (float)$value>0)$duration=(float)$value;
        }
        if($duration===null)abort(422,'อ่านความยาวไฟล์ MP4 ไม่สำเร็จ กรุณาบันทึกวิดีโอเป็น MP4 ใหม่ หรือติดต่อผู้ดูแลให้ตรวจสอบ ffprobe บนเซิร์ฟเวอร์');
        if($duration>43200)abort(422,'วิดีโอต้องมีความยาวไม่เกิน 12 ชั่วโมง');
        return (int)ceil($duration);
    }

    private static function mp4Duration(string $path): ?float {
        $file=@fopen($path,'rb');
        if(!$file)return null;
        try {
            $size=fstat($file)['size'];
            $budget=4096;
            $movie=self::findMp4Box($file,0,$size,'moov',$budget);
            if(!$movie)return null;
            $header=self::findMp4Box($file,$movie[0],$movie[1],'mvhd',$budget);
            if(!$header || $header[1]-$header[0]<20)return null;
            fseek($file,$header[0]);
            $bytes=fread($file,min(32,$header[1]-$header[0]));
            $version=ord($bytes[0]);
            if($version===0 && strlen($bytes)>=20) {
                $scale=unpack('N',substr($bytes,12,4))[1];
                $ticks=unpack('N',substr($bytes,16,4))[1];
                if($ticks===0xffffffff)return null; // Unknown duration sentinel.
            } elseif($version===1 && strlen($bytes)>=32) {
                $scale=unpack('N',substr($bytes,20,4))[1];
                $parts=unpack('Nhigh/Nlow',substr($bytes,24,8));
                if($parts['high']===0xffffffff && $parts['low']===0xffffffff)return null;
                $ticks=$parts['high']*4294967296.0+$parts['low'];
            } else return null;
            if($scale<=0 || $ticks<=0)return null;
            $duration=$ticks/$scale;
            return is_finite($duration)?$duration:null;
        } finally {fclose($file);}
    }

    /** Scan siblings by offsets, skipping media payloads without loading them into memory. */
    private static function findMp4Box($file,int $start,int $end,string $type,int &$budget): ?array {
        for($offset=$start;$offset<=$end-8 && $budget>0;$offset+=$size) {
            --$budget;
            if(fseek($file,$offset)!==0)return null;
            $bytes=fread($file,8);
            if(strlen($bytes)!==8)return null;
            $box=unpack('Nsize/a4type',$bytes);$size=$box['size'];$header=8;
            if($size===1) {
                $extended=fread($file,8);
                if(strlen($extended)!==8)return null;
                $parts=unpack('Nhigh/Nlow',$extended);
                // Reject offsets outside this parent before any float-to-int conversion.
                $length=$parts['high']*4294967296.0+$parts['low'];
                if($length>$end-$offset)return null;
                $size=(int)$length;$header=16;
            } elseif($size===0)$size=$end-$offset;
            if($size<$header || $size>$end-$offset)return null;
            if($box['type']===$type)return [$offset+$header,$offset+$size];
        }
        return null;
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
