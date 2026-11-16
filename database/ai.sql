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
