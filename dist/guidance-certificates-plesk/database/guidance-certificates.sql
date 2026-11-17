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
