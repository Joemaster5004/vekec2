-- LMS: department video statistics (existing installation).
-- Select the LMS database in phpMyAdmin before importing.
-- Safe to import again: existing counters and certificates are preserved.
SET NAMES utf8mb4;

SET @lms_video_stats_sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE department_videos ADD COLUMN view_count BIGINT UNSIGNED NOT NULL DEFAULT 0',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'department_videos'
      AND COLUMN_NAME = 'view_count'
);
PREPARE lms_video_stats_statement FROM @lms_video_stats_sql;
EXECUTE lms_video_stats_statement;
DEALLOCATE PREPARE lms_video_stats_statement;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO schema_migrations(version) VALUES ('011_department_video_views');
