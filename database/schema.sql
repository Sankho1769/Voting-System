-- =====================================================================
-- VerityVote - Database Schema
-- Institutional College Voting System
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Compatible with MySQL 8.0+ and MariaDB 10.4+
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `verityvote_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `verityvote_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Table: users
-- Stores all authorized collegiate accounts (students, staff, officers, admins)
-- Public self-registration is strictly disallowed.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(64) NOT NULL COMMENT 'College roll number, Employee ID, or Admin ID',
  `role` ENUM('student', 'staff', 'candidate', 'election_officer', 'admin') NOT NULL DEFAULT 'student',
  `full_name` VARCHAR(128) NOT NULL,
  `email` VARCHAR(128) NULL,
  `department` VARCHAR(64) NULL COMMENT 'e.g. Computer Science, Commerce, Physics',
  `academic_year` VARCHAR(16) NULL COMMENT 'e.g. 2024-2027',
  `password_hash` VARCHAR(255) NOT NULL,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 for temporary credentials requiring first-login change',
  `status` ENUM('active', 'suspended', 'deactivated') NOT NULL DEFAULT 'active',
  `temp_password_expires_at` DATETIME NULL,
  `auth_version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Incremented on password reset/suspension to invalidate active sessions',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_identifier` (`identifier`),
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`),
  INDEX `idx_users_department` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: elections
-- Represents a single collegiate election for exactly one position.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `elections`;
CREATE TABLE `elections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(191) NOT NULL,
  `position` VARCHAR(128) NOT NULL COMMENT 'e.g. President, General Secretary, Class Representative',
  `description` TEXT NULL,
  `status` ENUM('draft', 'scheduled', 'open', 'closed', 'results_published', 'cancelled') NOT NULL DEFAULT 'draft',
  `opens_at` DATETIME NOT NULL,
  `closes_at` DATETIME NOT NULL,
  `results_visibility` ENUM('live_provisional', 'after_closure_only') NOT NULL DEFAULT 'live_provisional',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_elections_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  INDEX `idx_elections_status` (`status`),
  INDEX `idx_elections_dates` (`opens_at`, `closes_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: candidates
-- Candidates contesting in an election.
-- Locked once the election status changes to open.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `candidates`;
CREATE TABLE `candidates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `election_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Optional link to authenticated collegiate account for Candidate Portal access',
  `name` VARCHAR(128) NOT NULL,
  `manifesto` TEXT NULL,
  `photo_path` VARCHAR(255) NULL,
  `approval_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
  `display_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_candidates_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_candidates_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_candidates_election` (`election_id`, `approval_status`),
  INDEX `idx_candidates_user` (`user_id`),
  UNIQUE KEY `uk_candidates_election_user` (`election_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: election_eligibility
-- Whitelist mapping defining which authenticated accounts are permitted to vote.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `election_eligibility`;
CREATE TABLE `election_eligibility` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `election_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `assigned_by` INT UNSIGNED NOT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_eligibility_election_user` (`election_id`, `user_id`),
  CONSTRAINT `fk_eligibility_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_eligibility_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_eligibility_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: election_participation
-- Records THAT an eligible user participated, preventing duplicate votes.
-- Stores a cryptographic confirmation receipt hash.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `election_participation`;
CREATE TABLE `election_participation` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `election_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `voted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmation_hash` VARCHAR(64) NOT NULL COMMENT 'Cryptographic ballot receipt token given to voter',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_participation_election_user` (`election_id`, `user_id`),
  UNIQUE KEY `uk_participation_confirmation` (`confirmation_hash`),
  CONSTRAINT `fk_participation_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_participation_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  INDEX `idx_participation_election` (`election_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: votes (Privacy-conscious Ballot Box)
-- Stores the secret ballot choice.
-- CRITICAL SECURITY: Does NOT contain voter user_id!
-- Separates participation tracking from candidate selection.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `votes`;
CREATE TABLE `votes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `election_id` INT UNSIGNED NOT NULL,
  `candidate_id` INT UNSIGNED NOT NULL,
  `cast_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_votes_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_votes_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE RESTRICT,
  INDEX `idx_votes_election` (`election_id`),
  INDEX `idx_votes_candidate` (`candidate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: audit_logs
-- Immutable audit trail for all security, election, and account events.
-- Never records plain passwords, temporary secrets, or voter-candidate links.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(64) NOT NULL,
  `entity_type` VARCHAR(64) NOT NULL,
  `entity_id` VARCHAR(64) NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `details_json` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_audit_user` (`user_id`),
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: login_attempts
-- Server-side login attempt tracking for brute-force and credential-stuffing throttling.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(64) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_successful` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_login_ip` (`ip_address`, `attempted_at`),
  INDEX `idx_login_ident` (`identifier`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: migrations
-- Tracks executed database schema migrations.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL UNIQUE,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
