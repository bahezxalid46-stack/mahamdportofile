-- کۆدی دروستکردنی داتابەیس و خشتەی بەکارهێنەران
CREATE DATABASE IF NOT EXISTS `smart_meter_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `smart_meter_db`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- خشتەی تۆمارکردنی هەوڵە هەڵەکانی چوونەژوورەوە بۆ ڕێگری لە تاقیکردنەوەی پاسۆرد (Brute-Force Protection)
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `username` VARCHAR(100) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- خشتەی ڕێکخستنی ئاگادارکردنەوەکان (Alert Settings)
CREATE TABLE IF NOT EXISTS `alert_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `kwh_alert_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `kwh_threshold` INT NOT NULL DEFAULT 400,
    `amp_alert_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `amp_threshold` DECIMAL(5,1) NOT NULL DEFAULT 40.0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_alert_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- خشتەی تۆماری ئاگادارکردنەوەکان (Notifications / Alerts)
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` VARCHAR(20) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `value` DECIMAL(10,2) NOT NULL,
    `threshold` DECIMAL(10,2) NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`user_id`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- خشتەی مێژووی حیساباتی هەر بەکارهێنەرێک (User Calculation History)
CREATE TABLE IF NOT EXISTS `user_history` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `watt` DECIMAL(10,2) NOT NULL,
    `hours` DECIMAL(5,2) NOT NULL,
    `cost_month` DECIMAL(12,2) NOT NULL,
    `dom_type` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`user_id`),
    CONSTRAINT `fk_user_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- خشتەی ئامێرەکانی ماڵ بۆ هەر بەکارهێنەرێک (User Home Devices)
CREATE TABLE IF NOT EXISTS `user_devices` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `device_client_id` BIGINT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `watt` DECIMAL(10,2) NOT NULL,
    `hours` DECIMAL(5,2) NOT NULL,
    `qty` INT NOT NULL DEFAULT 1,
    `icon` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`user_id`),
    CONSTRAINT `fk_user_devices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- خشتەی پسوولەکانی کارەبا بۆ کێرڤ چارت و سکانەر (User Monthly Bills)
CREATE TABLE IF NOT EXISTS `user_bills` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `bill_client_id` VARCHAR(64) DEFAULT NULL,
    `month_name` VARCHAR(100) NOT NULL,
    `cost` DECIMAL(12,2) NOT NULL,
    `kwh` DECIMAL(10,2) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`user_id`),
    CONSTRAINT `fk_user_bills_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


