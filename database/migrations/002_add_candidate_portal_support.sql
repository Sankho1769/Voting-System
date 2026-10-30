-- =====================================================================
-- Migration 002: Add Candidate Portal Support and User Account Linking
-- VerityVote - Institutional College Voting System
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Extend users.role ENUM to include 'candidate' role
ALTER TABLE `users` 
  MODIFY COLUMN `role` ENUM('student', 'staff', 'candidate', 'election_officer', 'admin') NOT NULL DEFAULT 'student';

-- 2. Add user_id column to candidates table to link candidate profiles to collegiate accounts
ALTER TABLE `candidates` 
  ADD COLUMN `user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `election_id`;

-- 3. Add foreign key from candidates.user_id to users.id
ALTER TABLE `candidates` 
  ADD CONSTRAINT `fk_candidates_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 4. Add index on candidates.user_id for efficient profile lookup
ALTER TABLE `candidates` 
  ADD INDEX `idx_candidates_user` (`user_id`);

-- 5. Add unique constraint: a user can contest at most once per election
ALTER TABLE `candidates` 
  ADD UNIQUE KEY `uk_candidates_election_user` (`election_id`, `user_id`);

SET FOREIGN_KEY_CHECKS = 1;
