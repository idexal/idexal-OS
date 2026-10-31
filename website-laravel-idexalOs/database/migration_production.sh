<?php
/**
 * Database Migration Script for idexal Os Production Server
 * Run this AFTER uploading files to create tables
 */

return [
    'migration_extension_installations' => "
        CREATE TABLE IF NOT EXISTS extension_installations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            extension_name VARCHAR(255) NOT NULL,
            extension_type ENUM('connector', 'skill', 'plugin') NOT NULL,
            version VARCHAR(50) DEFAULT '1.0.0',
            source TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_extension (user_id, extension_name, extension_type),
            CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'setup_queue_table' => "
        CREATE TABLE IF NOT EXISTS jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue VARCHAR(191) NOT NULL,
            payload LONGTEXT NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            available_at UNSIGNED INT NOT NULL,
            reserved_at UNSIGNED INT DEFAULT NULL,
            available_at INDEX(available_at),
            reserved_at INDEX(reserved_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS failed_jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(191) NOT NULL,
            connection TEXT NOT NULL,
            queue TEXT NOT NULL,
            payload LONGTEXT NOT NULL,
            exception LONGTEXT NOT NULL,
            failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY failed_jobs_uuid_unique (uuid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'setup_cache_table' => "
        CREATE TABLE IF NOT EXISTS cache (
            key VARCHAR(255) NOT NULL PRIMARY KEY,
            value MEDIUMTEXT NOT NULL,
            expiration INTEGER NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'setup_sessions_table' => "
        CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(255) NOT NULL PRIMARY KEY,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            payload LONGTEXT NOT NULL,
            last_activity INTEGER NOT NULL,
            INDEX sessions_user_id_idx(user_id),
            INDEX sessions_last_activity_idx(last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "
];

/* 
USAGE INSTRUCTIONS:

1. Access MySQL/phpMyAdmin at Plesk Panel
2. Select database: idexal_os
3. Run each SQL query from above array sequentially:
   - Click 'SQL' tab
   - Paste first query → Execute
   - Repeat for remaining queries

OR use command line:
mysql -u idexal -p idexal_os < migration.sql

IMPORTANT: This migration creates ONLY new tables needed for extensions marketplace.
Existing tables remain untouched.
*/