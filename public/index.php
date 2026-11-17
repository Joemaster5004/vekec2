<?php
declare(strict_types=1);
ob_start();
try {
    require dirname(__DIR__).'/app/bootstrap.php';
    (new App\Router())->dispatch();
} catch (Throwable $error) {
    ob_clean();
    $status=$error instanceof App\HttpError ? ($error->getCode() ?: 400) : 500;
    http_response_code($status);
    $message=$status===500?'ระบบขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้ง':$error->getMessage();
    if($status===500)error_log(get_class($error).' code='.$error->getCode().($error instanceof mysqli_sql_exception?' sqlstate='.$error->getSqlState():'').' at '.$error->getFile().':'.$error->getLine());
    if(preg_match('#^/department-videos/\d+/(start|tick)(?:\?|$)#',$_SERVER['REQUEST_URI']??'')||str_starts_with($_SERVER['REQUEST_URI']??'','/api/')||preg_match('#^(?:/teach/\d+/images|/admin/certificate-design/emblem-preview)(?:\?|$)#',$_SERVER['REQUEST_URI']??'')){header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>$message],JSON_UNESCAPED_UNICODE);}
    else {
        try {if(function_exists('App\\view')&&is_file(dirname(__DIR__).'/views/error.php'))App\view('error',compact('status','message'));else throw new RuntimeException('View unavailable');}
        catch(Throwable) {if(ob_get_level())ob_clean();echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ระบบ LMS</title><h1>ระบบกำลังเริ่มต้น</h1><p>กรุณาลองใหม่อีกครั้ง</p></html>';}
    }
}
