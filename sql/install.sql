-- =====================================================================
-- API Manager - 数据库结构
-- 兼容 MySQL 5.7（不使用 8.0+ 特性如 CTE、窗口函数、JSON 函数等）
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 用户表
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `encryption_key` VARCHAR(64) NOT NULL COMMENT '每用户独立 AES-256-CBC 密钥(hex)',
    `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=正常 0=禁用',
    `email_verified_at` DATETIME NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `last_login_ip` VARCHAR(45) NULL DEFAULT NULL,
    `remember_token` VARCHAR(120) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_users_email` (`email`),
    KEY `idx_users_remember` (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- API 供应商表
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `api_providers`;
CREATE TABLE `api_providers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `api_key_encrypted` TEXT NOT NULL,
    `base_url` VARCHAR(500) NOT NULL,
    `api_format` VARCHAR(200) NOT NULL COMMENT 'API 格式后缀，如 /v1/messages',
    `website_url` VARCHAR(500) NULL DEFAULT NULL,
    `notes` TEXT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_providers_user` (`user_id`),
    CONSTRAINT `fk_providers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 模型表
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `models`;
CREATE TABLE `models` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id` INT NOT NULL,
    `nickname` VARCHAR(100) NOT NULL,
    `model_id` VARCHAR(100) NOT NULL,
    `context_window` VARCHAR(20) NULL DEFAULT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_models_provider` (`provider_id`),
    CONSTRAINT `fk_models_provider` FOREIGN KEY (`provider_id`) REFERENCES `api_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- SMTP 配置表
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `smtp_configs`;
CREATE TABLE `smtp_configs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `host` VARCHAR(255) NOT NULL,
    `port` INT NOT NULL DEFAULT 465,
    `encryption` VARCHAR(10) NOT NULL DEFAULT 'ssl' COMMENT 'ssl/tls/none',
    `username` VARCHAR(255) NOT NULL,
    `password_encrypted` TEXT NOT NULL,
    `from_address` VARCHAR(255) NOT NULL,
    `from_name` VARCHAR(100) NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 邮箱验证码表
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `verification_codes`;
CREATE TABLE `verification_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(190) NOT NULL,
    `code` VARCHAR(6) NOT NULL,
    `type` ENUM('register','reset') NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_codes_email_type` (`email`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 系统设置表（key-value）
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `setting_value` TEXT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 默认设置
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'API Manager'),
('allow_registration', '1'),
('require_email_verification', '1');

SET FOREIGN_KEY_CHECKS = 1;
