-- Migration 005: Add category and department to elections for Multi-Election Discovery
-- Date: October 2026

ALTER TABLE `elections`
ADD COLUMN `category` VARCHAR(64) NOT NULL DEFAULT 'student_council' AFTER `position`,
ADD COLUMN `department` VARCHAR(64) NULL DEFAULT NULL AFTER `category`,
ADD INDEX `idx_elections_category` (`category`),
ADD INDEX `idx_elections_department` (`department`);
