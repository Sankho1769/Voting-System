-- =====================================================================
-- Migration 006: High-Performance Composite Indexes
-- Eliminates temporary tables, index-merges, and in-memory filesorts
-- on critical electoral queries, tallies, and audit pathways.
-- Compatible with MySQL 8.0+ and MariaDB 10.4+
-- =====================================================================

-- 1. Candidates: Index election candidates by approval status, display order, and name
-- Eliminates 'Using filesort' in CandidateRepository::getByElectionId
CREATE INDEX `idx_candidates_election_approval_order` 
  ON `candidates` (`election_id`, `approval_status`, `display_order`, `name`);

-- 2. Votes: Index election and candidate together
-- Enables Index-Only Covering Scan (Using index) for ResultsService tallies,
-- eliminating 'Using temporary' and 'Using filesort'
CREATE INDEX `idx_votes_election_candidate` 
  ON `votes` (`election_id`, `candidate_id`);

-- 3. Elections: Composite index for status, category, and date filtering/sorting
-- Eliminates 'Using intersect' index-merge and 'Using filesort' in ElectionRepository::getAll
CREATE INDEX `idx_elections_status_category_dates` 
  ON `elections` (`status`, `category`, `opens_at`, `closes_at`);

CREATE INDEX `idx_elections_status_dates` 
  ON `elections` (`status`, `opens_at`, `closes_at`);

-- 4. Audit Logs: Composite indexes for entity lookups and user timeline queries
-- Eliminates full index scans on created_at when querying by entity or user
CREATE INDEX `idx_audit_logs_entity` 
  ON `audit_logs` (`entity_type`, `entity_id`, `created_at`);

CREATE INDEX `idx_audit_logs_user_created` 
  ON `audit_logs` (`user_id`, `created_at`);

-- 5. Users: Composite index for role and status lookups
CREATE INDEX `idx_users_role_status` 
  ON `users` (`role`, `status`);
