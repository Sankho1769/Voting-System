-- =====================================================================
-- Migration: 004_security_hardening.sql
-- Description: Strengthen account status enum (pending, active, suspended, disabled)
--              and add correlation_id to audit_logs for request tracing.
-- =====================================================================

-- 1. Extend user account status enum to support pending approval and disabled states
ALTER TABLE `users` 
  MODIFY COLUMN `status` ENUM('pending', 'active', 'suspended', 'disabled', 'deactivated') 
  NOT NULL DEFAULT 'active' 
  COMMENT 'Account lifecycle state: pending, active, suspended, disabled, deactivated';

-- 2. Add correlation_id to audit_logs for distributed / session request tracing
ALTER TABLE `audit_logs`
  ADD COLUMN `correlation_id` VARCHAR(64) NULL AFTER `user_agent`,
  ADD INDEX `idx_audit_correlation` (`correlation_id`);
