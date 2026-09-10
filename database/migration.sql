-- ============================================
-- SIPEMENANG Database Schema
-- Sistem Informasi Pemenangan Pilkada/Pileg
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+07:00";

-- -------------------------------------------
-- Table: party_settings
-- -------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `vote_results`;
DROP TABLE IF EXISTS `tps_witnesses`;
DROP TABLE IF EXISTS `tps`;
DROP TABLE IF EXISTS `villages`;
DROP TABLE IF EXISTS `districts`;
DROP TABLE IF EXISTS `regencies`;
DROP TABLE IF EXISTS `provinces`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `party_settings`;

CREATE TABLE `party_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `party_name` VARCHAR(100) NOT NULL,
    `logo_url` VARCHAR(255) NULL,
    `primary_color` VARCHAR(7) DEFAULT '#1E3A8A',
    `secondary_color` VARCHAR(7) DEFAULT '#F59E0B',
    `accent_color` VARCHAR(7) DEFAULT '#F3F4F6',
    `is_active` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `party_settings` (`party_name`, `primary_color`, `secondary_color`, `is_active`) VALUES
('PDI-P', '#DC2626', '#000000', 1),
('Golkar', '#FACC15', '#1E3A8A', 0),
('Gerindra', '#B91C1C', '#F59E0B', 0),
('PKB', '#006B3F', '#003820', '#E8F5E9', 0),
('NasDem', '#1E40AF', '#F97316', 0),
('PKS', '#F97316', '#000000', 0),
('Demokrat', '#2563EB', '#DC2626', 0);

-- -------------------------------------------
-- Table: users (Admin & Operators)
-- -------------------------------------------
CREATE TABLE `users` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `role` ENUM('superadmin', 'admin', 'operator') DEFAULT 'operator',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`) VALUES
('admin', 'admin@sipemenang.id', '$2y$10$rAqELGDqW8ZDpNH8o.pH8ebR6YcgXDWxxPaFazuh1QpaYtSBkOoDa', 'Administrator', 'superadmin');

-- -------------------------------------------
-- Table: provinces (Master Wilayah Kemendagri)
-- -------------------------------------------
CREATE TABLE `provinces` (
    `id` CHAR(2) PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Table: regencies (Kabupaten/Kota)
-- -------------------------------------------
CREATE TABLE `regencies` (
    `id` CHAR(4) PRIMARY KEY,
    `province_id` CHAR(2) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`province_id`) REFERENCES `provinces`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Table: districts (Kecamatan)
-- -------------------------------------------
CREATE TABLE `districts` (
    `id` CHAR(6) PRIMARY KEY,
    `regency_id` CHAR(4) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`regency_id`) REFERENCES `regencies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Table: villages (Desa/Kelurahan)
-- -------------------------------------------
CREATE TABLE `villages` (
    `id` CHAR(10) PRIMARY KEY,
    `district_id` CHAR(6) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`district_id`) REFERENCES `districts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Table: tps (Data TPS KPU)
-- -------------------------------------------
CREATE TABLE `tps` (
    `id` VARCHAR(15) PRIMARY KEY,
    `village_id` CHAR(10) NOT NULL,
    `tps_number` INT NOT NULL,
    `total_dpt` INT DEFAULT 0,
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`village_id`) REFERENCES `villages`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_tps_village` (`village_id`, `tps_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Table: tps_witnesses (Saksi TPS)
-- -------------------------------------------
CREATE TABLE `tps_witnesses` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `tps_id` VARCHAR(15) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `nik` CHAR(16) NOT NULL UNIQUE,
    `phone_number` VARCHAR(20) NOT NULL,
    `photo_ktp_url` VARCHAR(255) NULL,
    `photo_selfie_url` VARCHAR(255) NULL,
    `register_lat` DECIMAL(10, 8) NOT NULL,
    `register_long` DECIMAL(11, 8) NOT NULL,
    `register_accuracy` DECIMAL(6, 2) NULL,
    `status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    `verified_by` BIGINT NULL,
    `verified_at` TIMESTAMP NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tps_id`) REFERENCES `tps`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Table: vote_results (Hasil Suara C1)
-- -------------------------------------------
CREATE TABLE `vote_results` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `tps_id` VARCHAR(15) NOT NULL,
    `witness_id` BIGINT NOT NULL,
    `candidate_votes` JSON NOT NULL,
    `invalid_votes` INT DEFAULT 0,
    `total_votes` INT DEFAULT 0,
    `c1_photo_url` VARCHAR(255) NOT NULL,
    `submit_lat` DECIMAL(10, 8) NOT NULL,
    `submit_long` DECIMAL(11, 8) NOT NULL,
    `submit_accuracy` DECIMAL(6, 2) NULL,
    `status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    `verified_by` BIGINT NULL,
    `verified_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tps_id`) REFERENCES `tps`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`witness_id`) REFERENCES `tps_witnesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Table: activity_logs
-- -------------------------------------------
CREATE TABLE `activity_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Indexes for performance
-- -------------------------------------------
CREATE INDEX `idx_regencies_province` ON `regencies`(`province_id`);
CREATE INDEX `idx_districts_regency` ON `districts`(`regency_id`);
CREATE INDEX `idx_villages_district` ON `villages`(`district_id`);
CREATE INDEX `idx_tps_village` ON `tps`(`village_id`);
CREATE INDEX `idx_witness_tps` ON `tps_witnesses`(`tps_id`);
CREATE INDEX `idx_witness_status` ON `tps_witnesses`(`status`);
CREATE INDEX `idx_votes_tps` ON `vote_results`(`tps_id`);
CREATE INDEX `idx_votes_witness` ON `vote_results`(`witness_id`);
CREATE INDEX `idx_votes_status` ON `vote_results`(`status`);

COMMIT;
