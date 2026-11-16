<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\DB;
use function App\env;
if(PHP_SAPI!=='cli'||env('APP_ENV')!=='test'||env('DB_DATABASE')!=='vekec_test'){fwrite(STDERR,"Test environment required.\n");exit(1);}
DB::transaction(function(){
    $numbering=DB::one('SELECT range_id FROM certificate_numbering WHERE id=1 FOR UPDATE');
    if (!$numbering['range_id']) {
        $range=DB::one("SELECT id FROM certificate_number_ranges WHERE prefix='TEST-FIXTURE' AND year=2569");
        if (!$range) {
            DB::run("INSERT INTO certificate_number_ranges(prefix,year,start_number,end_number,next_number,digits) VALUES('TEST-FIXTURE',2569,1,999999,1,6)");
            $range=['id'=>DB::id()];
        }
        DB::run('UPDATE certificate_numbering SET range_id=? WHERE id=1',[$range['id']]);
    }

    DB::run("INSERT IGNORE INTO categories(name) VALUES('ทดสอบระบบ')");
    foreach(['admin','teacher','otherteacher'] as $role){
        DB::run('INSERT IGNORE INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Test '.$role,$role.'@example.invalid',password_hash('TestOnlyPassword!2026',PASSWORD_DEFAULT),$role==='otherteacher'?'teacher':$role]);
    }
});echo "Isolated test accounts ready.\n";
