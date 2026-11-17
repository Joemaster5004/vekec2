<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

use App\DB;

if (PHP_SAPI !== 'cli') exit(1);

if ((int)DB::one("SELECT GET_LOCK('vekec_schema_migrations', 30) AS acquired")['acquired'] !== 1) {
    fwrite(STDERR, "Another schema migration is running. Try again later.\n");
    exit(1);
}

try {
    DB::run('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    $version = '001_managed_categories';
    if (!DB::one('SELECT version FROM schema_migrations WHERE version=?', [$version])) {
        // DDL commits implicitly in MySQL; each step is safe to retry after interruption.
        DB::run('CREATE TABLE IF NOT EXISTS categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci');
        DB::run('INSERT INTO categories(name) SELECT DISTINCT category FROM courses WHERE TRUE ON DUPLICATE KEY UPDATE name=categories.name');
        foreach (['ดิจิทัลและเทคโนโลยี','ช่างอุตสาหกรรม','ธุรกิจและการเป็นผู้ประกอบการ','ภาษาและการสื่อสาร','ทักษะอาชีพและชีวิต'] as $name) {
            DB::run('INSERT INTO categories(name) VALUES(?) ON DUPLICATE KEY UPDATE name=categories.name', [$name]);
        }
        $foreignKey = DB::one("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='courses' AND COLUMN_NAME='category' AND REFERENCED_TABLE_NAME='categories'");
        if (!$foreignKey) {
            DB::run('ALTER TABLE courses ADD CONSTRAINT fk_courses_category FOREIGN KEY(category) REFERENCES categories(name) ON UPDATE CASCADE ON DELETE RESTRICT');
        }
        DB::run('INSERT INTO schema_migrations(version) VALUES(?)', [$version]);
        echo "Applied managed categories. Existing courses and category names preserved.\n";
    }
    $version = '002_rich_text_images';
    if (!DB::one('SELECT version FROM schema_migrations WHERE version=?', [$version])) {
        // Preserve existing content as text. Check each DDL step so interrupted migrations can resume.
        foreach ([
            ['lessons', 'body_format', "ENUM('text','html') NOT NULL DEFAULT 'text'"],
            ['questions', 'content_format', "ENUM('text','html') NOT NULL DEFAULT 'text'"],
            ['questions', 'explanation', 'TEXT NULL'],
        ] as [$table, $column, $definition]) {
            if (!DB::one('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$table, $column])) {
                DB::run('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
            }
        }
        DB::run('CREATE TABLE IF NOT EXISTS editor_images (
            path VARCHAR(80) PRIMARY KEY,
            course_id BIGINT UNSIGNED NOT NULL,
            uploaded_by BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY(uploaded_by) REFERENCES users(id)
        ) ENGINE=InnoDB');
        DB::run("CREATE TABLE IF NOT EXISTS editor_image_uses (
            path VARCHAR(80) NOT NULL,
            lesson_id BIGINT UNSIGNED NOT NULL,
            context ENUM('body','question','explanation') NOT NULL,
            PRIMARY KEY(path,lesson_id,context),
            FOREIGN KEY(path) REFERENCES editor_images(path) ON DELETE CASCADE,
            FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        DB::run('INSERT INTO schema_migrations(version) VALUES(?)', [$version]);
        echo "Applied rich text and private editor images. Existing plain text preserved.\n";
    }
    $version = '003_certificate_design';
    if (!DB::one('SELECT version FROM schema_migrations WHERE version=?', [$version])) {
        if (!DB::one("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='certificates' AND COLUMN_NAME='design_snapshot'")) {
            DB::run('ALTER TABLE certificates ADD COLUMN design_snapshot JSON NULL');
        }
        // NULL means the original renderer; never apply a new template to issued certificates.
        DB::run('INSERT INTO schema_migrations(version) VALUES(?)', [$version]);
        echo "Applied certificate design snapshots. Issued certificates preserved.\n";
    }
    $version = '004_home_banners';
    if (!DB::one('SELECT version FROM schema_migrations WHERE version=?', [$version])) {
        DB::run("CREATE TABLE IF NOT EXISTS banners (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(240) NOT NULL,
 image_path VARCHAR(80) NOT NULL,
 link_url VARCHAR(2000) NOT NULL DEFAULT '',
 sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 active TINYINT UNSIGNED NOT NULL DEFAULT 1,
 width INT UNSIGNED NOT NULL, height INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX banner_display (active,sort_order,id)
) ENGINE=InnoDB");
        DB::run('INSERT INTO schema_migrations(version) VALUES(?)', [$version]);
        echo "Applied homepage banners.\n";
    }
    $version = '005_certificate_numbers';
    if (!DB::one('SELECT version FROM schema_migrations WHERE version=?', [$version])) {
        DB::run("CREATE TABLE IF NOT EXISTS certificate_number_ranges (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 prefix VARCHAR(30) NOT NULL DEFAULT '', year SMALLINT UNSIGNED NOT NULL,
 start_number INT UNSIGNED NOT NULL, end_number INT UNSIGNED NOT NULL,
 next_number INT UNSIGNED NOT NULL, digits TINYINT UNSIGNED NOT NULL DEFAULT 4,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
        DB::run("CREATE TABLE IF NOT EXISTS certificate_numbering (
 id TINYINT UNSIGNED PRIMARY KEY, range_id BIGINT UNSIGNED NULL,
 FOREIGN KEY(range_id) REFERENCES certificate_number_ranges(id)
) ENGINE=InnoDB");
        DB::run("INSERT IGNORE INTO certificate_numbering(id) VALUES(1)");
        foreach (['registration_number'=>'VARCHAR(80) NULL','number_range_id'=>'BIGINT UNSIGNED NULL','serial_number'=>'INT UNSIGNED NULL'] as $column=>$definition) {
            if (!DB::one("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='certificates' AND COLUMN_NAME=?",[$column])) DB::run('ALTER TABLE certificates ADD COLUMN '.$column.' '.$definition);
        }
        foreach (['certificate_registration_number'=>'registration_number','certificate_range_serial'=>'number_range_id,serial_number'] as $index=>$columns) {
            if (!DB::one("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='certificates' AND INDEX_NAME=?",[$index])) DB::run('ALTER TABLE certificates ADD UNIQUE KEY '.$index.'('.$columns.')');
        }
        DB::run('INSERT INTO schema_migrations(version) VALUES(?)',[$version]);
        echo "Applied certificate numbering. Existing certificates preserved.\n";
    }
    $version='006_ai_byok';
    if(!DB::one('SELECT version FROM schema_migrations WHERE version=?',[$version])) {
        $sql=file_get_contents(dirname(__DIR__).'/database/ai.sql');
        foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')DB::run($statement);
        DB::run('INSERT INTO schema_migrations(version) VALUES(?)',[$version]);
        echo "Applied teacher AI credential encryption, billing and budget reservations.\n";
    }
    $version='007_admin_ai_providers';
    if(!DB::one('SELECT version FROM schema_migrations WHERE version=?',[$version])) {
        foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/ai-providers.sql')) as $sql)if(trim($sql)!=='')DB::run($sql);
        foreach(['ai_credentials','ai_usage_reservations'] as $table)if(!DB::one('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,'provider_revision']))DB::run('ALTER TABLE '.$table.' ADD COLUMN provider_revision INT UNSIGNED NOT NULL DEFAULT 1');
        $models=[];foreach(['text','embedding','speech','analytics','assistant'] as $cap) {
            $key=$cap==='speech'?'AI_SPEECH_MODELS':($cap==='embedding'?'AI_EMBEDDING_MODELS':'AI_TEXT_MODELS');
            $models[$cap]=array_values(array_filter(array_map('trim',explode(',',App\env($key)))));
        }
        DB::run("INSERT IGNORE INTO ai_providers(slug,name,adapter,base_url,models,enabled) VALUES('openai','OpenAI','openai_compatible','https://api.openai.com/v1',?,?)",[json_encode($models,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),(int)(array_sum(array_map('count',$models))>0)]);
        foreach(DB::all('SELECT course_id,bindings FROM ai_billing') as $row) {
            $bindings=json_decode($row['bindings'],true);foreach($bindings as &$b){unset($b['allow_student_usage'],$b['student_daily_request_limit']);}unset($b);
            DB::run('UPDATE ai_billing SET bindings=?,revision=revision+1 WHERE course_id=?',[json_encode($bindings,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$row['course_id']]);
        }
        DB::run("UPDATE ai_usage_reservations r JOIN users u ON u.id=r.actor_id SET r.status='released',r.actual_micros=0 WHERE u.role='student' AND r.status='reserved'");
        DB::run('INSERT INTO schema_migrations(version) VALUES(?)',[$version]);
        echo "Applied admin provider catalog and teacher-only AI access.\n";
    }
    $version='008_ai_drafts';
    if(!DB::one('SELECT version FROM schema_migrations WHERE version=?',[$version])) {
        foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/ai-drafts.sql')) as $sql)if(trim($sql)!=='')DB::run($sql);
        DB::run('INSERT INTO schema_migrations(version) VALUES(?)',[$version]);
        echo "Applied teacher AI generation drafts.\n";
    }
    $version='009_department_videos';
    if(!DB::one('SELECT version FROM schema_migrations WHERE version=?',[$version])) {
        foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/department-videos.sql')) as $sql)if(trim($sql)!=='')DB::run($sql);
        DB::run('INSERT INTO schema_migrations(version) VALUES(?)',[$version]);
        echo "Applied public department introduction videos.\n";
    }
    $version='010_guidance_certificates';
    if(!DB::one('SELECT version FROM schema_migrations WHERE version=?',[$version])) {
        foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/guidance-certificates.sql')) as $sql)if(trim($sql)!=='')DB::run($sql);
        echo "Applied guidance certificate designs and numbering.\n";
    }
    $version='011_department_video_views';
    if(!DB::one('SELECT version FROM schema_migrations WHERE version=?',[$version])) {
        if(!DB::one("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='department_videos' AND COLUMN_NAME='view_count'")) {
            DB::run('ALTER TABLE department_videos ADD COLUMN view_count BIGINT UNSIGNED NOT NULL DEFAULT 0');
        }
        DB::run('INSERT INTO schema_migrations(version) VALUES(?)',[$version]);
        echo "Applied department video view counters. Existing certificates preserved.\n";
    }
    echo "Schema is up to date.\n";
} finally {
    DB::one("SELECT RELEASE_LOCK('vekec_schema_migrations')");
}
