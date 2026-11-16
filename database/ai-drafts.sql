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
