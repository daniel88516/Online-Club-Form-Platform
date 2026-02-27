-- 線上表單系統資料庫
-- 資料庫名稱請依組別修改 group_xx

CREATE DATABASE IF NOT EXISTS `group_01` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `group_01`;

-- 群組表
CREATE TABLE IF NOT EXISTS `groups` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 使用者表
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `role` ENUM('admin', 'member') DEFAULT 'member',
    `group_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表單表
CREATE TABLE IF NOT EXISTS `forms` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `target_group` INT DEFAULT NULL,
    `start_date` DATETIME DEFAULT NULL,
    `end_date` DATETIME DEFAULT NULL,
    `allow_multiple` TINYINT(1) DEFAULT 0,
    `is_published` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`target_group`) REFERENCES `groups`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 表單欄位表
CREATE TABLE IF NOT EXISTS `form_fields` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `form_id` INT NOT NULL,
    `field_type` ENUM('short_text', 'long_text', 'radio', 'checkbox', 'dropdown', 'date', 'time') NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `is_required` TINYINT(1) DEFAULT 0,
    `options` TEXT DEFAULT NULL,
    `order_num` INT DEFAULT 0,
    FOREIGN KEY (`form_id`) REFERENCES `forms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 填答記錄表
CREATE TABLE IF NOT EXISTS `form_responses` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `form_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`form_id`) REFERENCES `forms`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 填答內容表
CREATE TABLE IF NOT EXISTS `response_answers` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `response_id` INT NOT NULL,
    `field_id` INT NOT NULL,
    `answer` TEXT,
    FOREIGN KEY (`response_id`) REFERENCES `form_responses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`field_id`) REFERENCES `form_fields`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 預設群組
INSERT INTO `groups` (`name`, `description`) VALUES
('一般', '一般會員群組'),
('管理', '管理員群組');

-- 預設帳號 (密碼已用 password_hash 加密)
-- member 密碼: member123456
-- admin 密碼: admin123456
INSERT INTO `users` (`username`, `password`, `email`, `role`, `group_id`) VALUES
('member', '$2y$10$M23ONZlthncqP2eq.UFyOe/AsYpQFxhcJug35gN8R6sMpw8wtrq1m', 'member@example.com', 'member', 1),
('admin', '$2y$10$SbZTYdLX77jlDYXOGA2./.3om4CXlP7zkqcxzO3gcziisHR0Mse2a', 'admin@example.com', 'admin', 2);
