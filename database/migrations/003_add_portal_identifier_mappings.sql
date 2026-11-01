-- =====================================================================
-- Migration 003: Add Portal-Specific Identifier Mappings Table
-- Allows visible login identifiers (e.g. 'Shubham') to map cleanly to 
-- distinct role accounts across the Admin, Candidate, and Voter portals
-- while preserving unique constraints on users.identifier.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `portal_identifier_mappings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `portal` ENUM('admin', 'candidate', 'voter') NOT NULL,
  `portal_identifier` VARCHAR(64) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_portal_identifier` (`portal`, `portal_identifier`),
  INDEX `idx_portal_user` (`user_id`),
  CONSTRAINT `fk_portal_map_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
