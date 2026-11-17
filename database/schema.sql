SET NAMES utf8mb4;
CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL, email VARCHAR(190) UNIQUE NULL, password_hash VARCHAR(255) NULL,
 role ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
 level ENUM('voc1','voc2','public') NOT NULL DEFAULT 'public',
 thaid_subject CHAR(64) UNIQUE NULL, active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
INSERT INTO categories(name) VALUES
 ('ดิจิทัลและเทคโนโลยี'),('ช่างอุตสาหกรรม'),('ธุรกิจและการเป็นผู้ประกอบการ'),('ภาษาและการสื่อสาร'),('ทักษะอาชีพและชีวิต');
CREATE TABLE courses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, teacher_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(240) NOT NULL, code VARCHAR(40) NOT NULL, description TEXT NOT NULL,
 category VARCHAR(100) NOT NULL, level ENUM('all','voc1','voc2','public') DEFAULT 'all',
 hours SMALLINT UNSIGNED NOT NULL DEFAULT 3, status ENUM('draft','published','archived') DEFAULT 'draft',
 sequential BOOLEAN NOT NULL DEFAULT TRUE, color VARCHAR(20) NOT NULL DEFAULT 'teal',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (teacher_id) REFERENCES users(id), INDEX(status,category),
 CONSTRAINT fk_courses_category FOREIGN KEY(category) REFERENCES categories(name) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE lessons (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, course_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(240) NOT NULL, kind ENUM('article','video','assignment','quiz') NOT NULL,
 body MEDIUMTEXT, body_format ENUM('text','html') NOT NULL DEFAULT 'text',
 position INT UNSIGNED NOT NULL, media_path VARCHAR(255), external_url VARCHAR(1000),
 duration_seconds INT UNSIGNED NOT NULL DEFAULT 0, require_watch BOOLEAN NOT NULL DEFAULT FALSE,
 attachment_path VARCHAR(255), pass_score TINYINT UNSIGNED NOT NULL DEFAULT 60,
 time_limit SMALLINT UNSIGNED NOT NULL DEFAULT 30, max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
 FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE, UNIQUE(course_id,position)
) ENGINE=InnoDB;
CREATE TABLE enrollments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, course_id BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NULL, enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(user_id,course_id), FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(course_id) REFERENCES courses(id), FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE progress (
 user_id BIGINT UNSIGNED NOT NULL, lesson_id BIGINT UNSIGNED NOT NULL,
 watched_seconds DECIMAL(10,2) NOT NULL DEFAULT 0, completed_at DATETIME NULL,
 PRIMARY KEY(user_id,lesson_id), FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE questions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, lesson_id BIGINT UNSIGNED NOT NULL,
 prompt TEXT NOT NULL, options JSON NOT NULL, answer TINYINT UNSIGNED NOT NULL,
 content_format ENUM('text','html') NOT NULL DEFAULT 'text', explanation TEXT NULL,
 FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE editor_images (
 path VARCHAR(80) PRIMARY KEY, course_id BIGINT UNSIGNED NOT NULL, uploaded_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
 FOREIGN KEY(uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE editor_image_uses (
 path VARCHAR(80) NOT NULL, lesson_id BIGINT UNSIGNED NOT NULL,
 context ENUM('body','question','explanation') NOT NULL,
 PRIMARY KEY(path,lesson_id,context),
 FOREIGN KEY(path) REFERENCES editor_images(path) ON DELETE CASCADE,
 FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, lesson_id BIGINT UNSIGNED NOT NULL,
 started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, submitted_at DATETIME NULL, score DECIMAL(5,2) NULL, answers JSON NULL,
 FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(lesson_id) REFERENCES lessons(id), INDEX(user_id,lesson_id)
) ENGINE=InnoDB;
CREATE TABLE submissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, lesson_id BIGINT UNSIGNED NOT NULL,
 body TEXT NOT NULL, file_path VARCHAR(255), score TINYINT UNSIGNED NULL, feedback TEXT,
 graded_by BIGINT UNSIGNED NULL, submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(user_id,lesson_id), FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(lesson_id) REFERENCES lessons(id), FOREIGN KEY(graded_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE certificates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, course_id BIGINT UNSIGNED NOT NULL,
 code CHAR(32) NOT NULL UNIQUE, recipient_name VARCHAR(180) NOT NULL, course_title VARCHAR(240) NOT NULL,
 hours SMALLINT UNSIGNED NOT NULL, institution VARCHAR(240) NOT NULL, signatory VARCHAR(180) NOT NULL,
 design_snapshot JSON NULL,
 issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id,course_id),
 FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(course_id) REFERENCES courses(id)
) ENGINE=InnoDB;
CREATE TABLE settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NOT NULL) ENGINE=InnoDB;
INSERT INTO settings VALUES ('institution','วิทยาลัยเทคนิคกำแพงเพชร'),('signatory','ผู้อำนวยการวิทยาลัยเทคนิคกำแพงเพชร'),('registration_open','1'),('contact_email','info@kpt.ac.th');
CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL, resource VARCHAR(200) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE rate_limits (bucket CHAR(64) PRIMARY KEY, hits INT UNSIGNED NOT NULL DEFAULT 1, expires_at BIGINT UNSIGNED NOT NULL) ENGINE=InnoDB;
CREATE TABLE schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
INSERT INTO schema_migrations(version) VALUES('001_managed_categories'),('002_rich_text_images'),('003_certificate_design');

CREATE TABLE IF NOT EXISTS banners (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(240) NOT NULL,
 image_path VARCHAR(80) NOT NULL,
 link_url VARCHAR(2000) NOT NULL DEFAULT '',
 sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 active TINYINT UNSIGNED NOT NULL DEFAULT 1,
 width INT UNSIGNED NOT NULL, height INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX banner_display (active,sort_order,id)
) ENGINE=InnoDB;
INSERT INTO schema_migrations(version) VALUES('004_home_banners');

CREATE TABLE IF NOT EXISTS certificate_number_ranges (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 prefix VARCHAR(30) NOT NULL DEFAULT '', year SMALLINT UNSIGNED NOT NULL,
 start_number INT UNSIGNED NOT NULL, end_number INT UNSIGNED NOT NULL,
 next_number INT UNSIGNED NOT NULL, digits TINYINT UNSIGNED NOT NULL DEFAULT 4,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS certificate_numbering (
 id TINYINT UNSIGNED PRIMARY KEY, range_id BIGINT UNSIGNED NULL,
 FOREIGN KEY(range_id) REFERENCES certificate_number_ranges(id)
) ENGINE=InnoDB;
INSERT IGNORE INTO certificate_numbering(id) VALUES(1);
ALTER TABLE certificates ADD COLUMN registration_number VARCHAR(80) NULL UNIQUE,
 ADD COLUMN number_range_id BIGINT UNSIGNED NULL,
 ADD COLUMN serial_number INT UNSIGNED NULL,
 ADD UNIQUE KEY certificate_range_serial(number_range_id,serial_number);
INSERT INTO schema_migrations(version) VALUES('005_certificate_numbers');

-- BEGIN AI BYOK SCHEMA (006_ai_byok)
-- Shared by fresh installations and migration 006_ai_byok. All statements are retryable.
CREATE TABLE IF NOT EXISTS ai_credentials (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, owner_user_id BIGINT UNSIGNED NOT NULL,
 provider VARCHAR(30) NOT NULL, label VARCHAR(100) NOT NULL, key_hint VARCHAR(12) NOT NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1, status VARCHAR(20) NOT NULL DEFAULT 'unverified',
 ciphertext TEXT NULL, nonce VARCHAR(64) NULL, tag VARCHAR(64) NULL, key_version VARCHAR(30) NOT NULL,
 last_verified_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, revoked_at DATETIME NULL,
 FOREIGN KEY(owner_user_id) REFERENCES users(id), INDEX(owner_user_id,status)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS ai_billing (
 course_id BIGINT UNSIGNED PRIMARY KEY, revision INT UNSIGNED NOT NULL DEFAULT 1, bindings JSON NOT NULL,
 accepted_by BIGINT UNSIGNED NOT NULL, accepted_at DATETIME NOT NULL,
 FOREIGN KEY(course_id) REFERENCES courses(id), FOREIGN KEY(accepted_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS ai_idempotency (
 actor_id BIGINT UNSIGNED NOT NULL, route_hash CHAR(64) NOT NULL, idem_key VARCHAR(128) NOT NULL,
 body_hash CHAR(64) NOT NULL, response_json JSON NULL, response_status SMALLINT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(actor_id,route_hash,idem_key)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS ai_usage_reservations (
 id CHAR(32) PRIMARY KEY, operation_key VARCHAR(128) NOT NULL, course_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL, billing_owner_id BIGINT UNSIGNED NOT NULL,
 capability VARCHAR(30) NOT NULL, binding_revision INT UNSIGNED NOT NULL, credential_id BIGINT UNSIGNED NULL,
 credential_version INT UNSIGNED NULL, payer VARCHAR(20) NOT NULL, provider VARCHAR(30) NOT NULL, model VARCHAR(200) NOT NULL,
 period CHAR(7) NOT NULL, day_key DATE NOT NULL, reserved_micros BIGINT UNSIGNED NOT NULL,
 actual_micros BIGINT UNSIGNED NULL, status VARCHAR(20) NOT NULL DEFAULT 'reserved',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(course_id,operation_key), INDEX(course_id,capability,period,status),
 INDEX(course_id,actor_id,capability,day_key),
 FOREIGN KEY(course_id) REFERENCES courses(id), FOREIGN KEY(actor_id) REFERENCES users(id),
 FOREIGN KEY(billing_owner_id) REFERENCES users(id), FOREIGN KEY(credential_id) REFERENCES ai_credentials(id)
) ENGINE=InnoDB;
INSERT IGNORE INTO schema_migrations(version) VALUES('006_ai_byok');

-- BEGIN ADMIN AI PROVIDERS (007_admin_ai_providers)
CREATE TABLE IF NOT EXISTS ai_providers (
 slug VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 name VARCHAR(100) NOT NULL, adapter VARCHAR(30) NOT NULL,
 base_url VARCHAR(1000) NOT NULL, models JSON NOT NULL,
 enabled BOOLEAN NOT NULL DEFAULT TRUE, revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
ALTER TABLE ai_credentials ADD COLUMN provider_revision INT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE ai_usage_reservations ADD COLUMN provider_revision INT UNSIGNED NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS ai_drafts (
 actor_id BIGINT UNSIGNED NOT NULL,
 course_id BIGINT UNSIGNED NOT NULL,
 operation_key VARCHAR(128) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'processing',
 response_json JSON NULL,
 error_code VARCHAR(80) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(actor_id,course_id,operation_key),
 FOREIGN KEY(actor_id) REFERENCES users(id),
 FOREIGN KEY(course_id) REFERENCES courses(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS department_videos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(240) NOT NULL,
 description TEXT NOT NULL,
 external_url VARCHAR(2000) NOT NULL DEFAULT '',
 media_path VARCHAR(80) NULL,
 duration_seconds INT UNSIGNED NOT NULL,
 application_url VARCHAR(2000) NOT NULL DEFAULT '',
 active TINYINT UNSIGNED NOT NULL DEFAULT 0,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE RESTRICT,
 INDEX department_video_display(active,id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS department_video_files (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 video_id BIGINT UNSIGNED NOT NULL,
 path VARCHAR(80) NOT NULL,
 name VARCHAR(240) NOT NULL,
 FOREIGN KEY(video_id) REFERENCES department_videos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS department_video_certificates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 video_id BIGINT UNSIGNED NOT NULL,
 watch_code CHAR(48) NOT NULL UNIQUE,
 code CHAR(32) NOT NULL UNIQUE,
 recipient_name VARCHAR(180) NOT NULL,
 school VARCHAR(240) NOT NULL,
 grade VARCHAR(100) NOT NULL,
 phone VARCHAR(40) NOT NULL,
 email VARCHAR(190) NOT NULL,
 video_title VARCHAR(240) NOT NULL,
 department_name VARCHAR(100) NOT NULL,
 institution VARCHAR(240) NOT NULL,
 signatory VARCHAR(180) NOT NULL,
 issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(video_id) REFERENCES department_videos(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS guidance_certificate_number_ranges (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 prefix VARCHAR(30) NOT NULL DEFAULT '', year SMALLINT UNSIGNED NOT NULL,
 start_number INT UNSIGNED NOT NULL, end_number INT UNSIGNED NOT NULL,
 next_number INT UNSIGNED NOT NULL, digits TINYINT UNSIGNED NOT NULL DEFAULT 4,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS guidance_certificate_numbering (
 id TINYINT UNSIGNED PRIMARY KEY, range_id BIGINT UNSIGNED NULL,
 FOREIGN KEY(range_id) REFERENCES guidance_certificate_number_ranges(id)
) ENGINE=InnoDB;
INSERT IGNORE INTO guidance_certificate_numbering(id) VALUES(1);
CREATE TABLE IF NOT EXISTS guidance_certificate_details (
 certificate_id BIGINT UNSIGNED PRIMARY KEY,
 design_snapshot JSON NOT NULL,
 registration_number VARCHAR(80) NOT NULL UNIQUE,
 number_range_id BIGINT UNSIGNED NOT NULL,
 serial_number INT UNSIGNED NOT NULL,
 UNIQUE KEY guidance_range_serial(number_range_id,serial_number),
 FOREIGN KEY(certificate_id) REFERENCES department_video_certificates(id) ON DELETE RESTRICT,
 FOREIGN KEY(number_range_id) REFERENCES guidance_certificate_number_ranges(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS schema_migrations (
 version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO schema_migrations(version) VALUES('010_guidance_certificates');
