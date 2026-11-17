<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\{DB,DepartmentVideo,HttpError};
use function App\env;
if(PHP_SAPI!=='cli'||env('APP_ENV')!=='test'||env('DB_DATABASE')!=='vekec_test')exit(1);
// Reproduce a source deployment before migration, preserving every row.
DB::run('RENAME TABLE department_videos TO department_videos_schema_test');
try {
    if(DepartmentVideo::all(true)!==[])throw new RuntimeException('Public catalog fallback failed');
    try {
        DepartmentVideo::all();
        throw new RuntimeException('Admin should explain the missing migration');
    } catch(HttpError $error) {
        if($error->getCode()!==503||!str_contains($error->getMessage(),'bin/migrate.php'))throw $error;
    }
} finally {
    DB::run('RENAME TABLE department_videos_schema_test TO department_videos');
}
DepartmentVideo::all(true);
echo "PASS public fallback, admin migration notice and restored schema.\n";
