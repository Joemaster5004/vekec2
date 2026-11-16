<?php
declare(strict_types=1);

// Replace only DNS and transport in this process. Production code retains real DNS/cURL.
namespace App {
    function gethostbynamel(string $host): array|false {
        return match ($host) {
            'private.test'=>['10.0.0.8'], 'mixed.test'=>['93.184.216.34','127.0.0.1'],
            'shared.test'=>['100.64.0.1'], 'missing.test'=>false, default=>['93.184.216.34'],
        };
    }
    function curl_init(string $url): \stdClass { return (object)['url'=>$url, 'options'=>[], 'response'=>null]; }
    function curl_setopt_array(\stdClass $handle, array $options): bool { $handle->options=$options; return true; }
    function curl_exec(\stdClass $handle): bool {
        $GLOBALS['emblem_requests'][]=$handle;
        $handle->response=array_shift($GLOBALS['emblem_responses']) ?? throw new \RuntimeException('Unexpected network request');
        if (!empty($handle->response['failure'])) return false;
        foreach (str_split($handle->response['body']??'', 16384) as $chunk) {
            if (($handle->options[CURLOPT_WRITEFUNCTION])($handle,$chunk)!==strlen($chunk)) return false;
        }
        return true;
    }
    function curl_getinfo(\stdClass $handle, int $key): mixed { return $key===CURLINFO_RESPONSE_CODE?($handle->response['status']??200):($handle->response['redirect']??''); }
    function curl_close(\stdClass $handle): void {}
}

namespace {
    require dirname(__DIR__).'/app/bootstrap.php';
    if (PHP_SAPI!=='cli'||App\env('APP_ENV')!=='test'||App\env('DB_DATABASE')!=='vekec_test') exit(1);
    $checks=0;
    $check=static function(bool $value,string $message)use(&$checks):void {
        if(!$value)throw new RuntimeException($message);
        $checks++;echo "PASS $message\n";
    };
    $reject=static function(callable $fn,string $message)use($check):void {
        try{$fn();}catch(App\HttpError $error){$check($error->getCode()===422,$message);return;}
        throw new RuntimeException($message.' was allowed');
    };
    $fixture=static function(array $responses):void{$GLOBALS['emblem_responses']=$responses;$GLOBALS['emblem_requests']=[];};
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aH3sAAAAASUVORK5CYII=');
    $fixture([['body'=>$png]]);
    $check(App\CertificateImage::download('https://public.test/logo.png')===$png,'public HTTPS image downloads intact');
    $options=$GLOBALS['emblem_requests'][0]->options;
    $check($options[CURLOPT_RESOLVE]===['public.test:443:93.184.216.34'],'download pins the validated DNS address');
    $check($options[CURLOPT_PROXY]===''&&!$options[CURLOPT_FOLLOWLOCATION],'download disables proxy routing and automatic redirects');
    $check($options[CURLOPT_SSL_VERIFYPEER]&&$options[CURLOPT_SSL_VERIFYHOST]===2&&$options[CURLOPT_PROTOCOLS]===CURLPROTO_HTTPS,'download verifies TLS and allows HTTPS only');
    $fixture([['status'=>302,'redirect'=>'https://cdn.test/logo.png'],['body'=>$png]]);
    $check(App\CertificateImage::download('https://public.test/logo.png')===$png&&count($GLOBALS['emblem_requests'])===2,'public redirect resolves and downloads image');
    $check($GLOBALS['emblem_requests'][1]->options[CURLOPT_RESOLVE]===['cdn.test:443:93.184.216.34'],'redirect target is separately pinned');
    foreach(['https://private.test/image.png','https://mixed.test/image.png','https://shared.test/image.png','https://missing.test/image.png','https://127.1/image.png','https://0x7f000001/image.png','https://example.test\\@private.test/image.png',"https://example.test/\nimage.png"]as$url){
        $fixture([]);$reject(fn()=>App\CertificateImage::download($url),'non-public or malformed image destination rejected');
        $check(!$GLOBALS['emblem_requests'],'rejected destination makes no HTTP request');
    }
    foreach(['https://private.test/secret','https://127.0.0.1/secret','http://public.test/logo.png']as$url){
        $fixture([['status'=>302,'redirect'=>$url]]);
        $reject(fn()=>App\CertificateImage::download('https://public.test/redirect'),'redirect cannot reach an unsafe destination');
        $check(count($GLOBALS['emblem_requests'])===1,'unsafe redirect stops before second request');
    }
    $fixture(array_fill(0,4,['status'=>302,'redirect'=>'https://public.test/loop']));
    $reject(fn()=>App\CertificateImage::download('https://public.test/loop'),'redirect loop is bounded');
    $check(count($GLOBALS['emblem_requests'])===4,'at most three redirects followed');
    foreach([['body'=>'<html>Not an image</html>'],['body'=>$png,'status'=>404],['failure'=>true],['body'=>$png.str_repeat('x',2097152)]]as$response){
        $fixture([$response]);$reject(fn()=>App\CertificateImage::download('https://public.test/image.png'),'invalid oversized missing or failed image rejected');
    }
    $check(App\CertificateImage::inspect($png)['mime']==='image/png','validated image MIME comes from content');
    echo "All $checks certificate image transport checks passed.\n";
}
