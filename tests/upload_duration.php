<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\{Upload,HttpError};
use function App\env;
if(PHP_SAPI!=='cli'||env('APP_ENV')!=='test'||env('DB_DATABASE')!=='vekec_test')exit(1);
$count=0;
$box=static fn(string $type,string $payload):string=>pack('N',strlen($payload)+8).$type.$payload;
$header=static fn(int $ticks,int $scale=1000):string=>str_repeat("\0",12).pack('NN',$scale,$ticks).str_repeat("\0",80);
$ftyp=$box('ftyp','isom'.pack('N',0).'isommp42');
$check=function(string $data,?int $expected,string $label)use(&$count):void {
    $name=bin2hex(random_bytes(20)).'.mp4';$path=Upload::path($name);file_put_contents($path,$data);
    try {
        try {
            $result=Upload::duration($name);
            if($expected===null||$result!==$expected)throw new RuntimeException($label.': unexpected duration '.$result);
        }catch(HttpError $error){
            if($expected!==null||$error->getCode()!==422)throw $error;
        }
    }finally{unlink($path);}
    ++$count;echo "PASS $label\n";
};
$check($ftyp.$box('moov',$box('mvhd',$header(162000))),162,'2 minutes 42 seconds without ffprobe');
$check($ftyp.$box('mdat',str_repeat('x',1024*1024)).$box('moov',$box('mvhd',$header(162001))),163,'movie header after media payload, rounded up');
$v1="\1".str_repeat("\0",19).pack('NNN',1000,0,162000).str_repeat("\0",80);
$check($ftyp.$box('moov',$box('mvhd',$v1)),162,'64-bit movie header');
$mvhd=$box('mvhd',$header(162000));
$check($ftyp.pack('Na4NN',1,'moov',0,strlen($mvhd)+16).$mvhd,162,'extended-size movie atom');
$check($ftyp.pack('Na4',0,'moov').$mvhd,162,'movie atom extending to EOF');
$check($ftyp.$box('moov',$box('mvhd',$header(43200000))),43200,'12 hour boundary');
$check($ftyp.$box('moov',$box('mvhd',$header(43200001))),null,'over 12 hours rejected');
$check($ftyp.$box('moov',$box('mvhd',$header(162000,0))),null,'zero timescale rejected');
$check($ftyp.$box('moov',$box('mvhd',"\0")),null,'truncated movie header rejected');
$check($ftyp.pack('Na4',4,'moov'),null,'invalid atom size rejected');
$check($ftyp.pack('Na4NN',1,'moov',0xffffffff,0xffffffff),null,'overflowing extended size rejected');
$check($ftyp.$box('moov',$box('mvhd',$header(0xffffffff))),null,'unknown duration rejected');
$check(str_repeat($box('free',''),4100).$box('moov',$mvhd),null,'atom scanning has a work limit');
$real=dirname(__DIR__).'/tests/.artifacts/department-clip.mp4';
if(is_file($real))$check(file_get_contents($real),8,'real ffmpeg-generated MP4');
echo "$count upload duration checks passed.\n";
