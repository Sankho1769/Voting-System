# VerityVote — Database Architecture & Schema Specification

## 1. Architectural Overview
The VerityVote database is engineered for **ACID compliance**, **referential integrity**, and **ballot secrecy**. It is implemented using MySQL 8.0+ / MariaDB 10.4+ through the **PHP MySQLi extension** utilizing prepared statements exclusively.

The relational schema strictly enforces foreign key constraints with `ON DELETE RESTRICT` for all election, candidate, and voting entities to guarantee that an accepted vote or contested candidate record cannot be inadvertently deleted or cascade-deleted.

---

## 2. Core Tables Specification

### 2.1 `users`
Stores all authorized institutional identities. Public self-registration is strictly disallowed; accounts are provisioned exclusively by authorized administrators.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Unique internal surrogate primary key |
| `identifier` | VARCHAR(64) | No | | College Roll Number, Faculty Employee ID, or Administrator ID (UNIQUE) |
| `role` | ENUM('student', 'staff', 'candidate', 'election_officer', 'admin') | No | 'student' | Institutional RBAC role |
| `full_name` | VARCHAR(128) | No | | Legal collegiate name |
| `email` | VARCHAR(128) | Yes | NULL | Optional institutional email |
| `department` | VARCHAR(64) | Yes | NULL | Department / School (e.g., Computer Science, Commerce) |
| `academic_year` | VARCHAR(16) | Yes | NULL | Student batch or class year (e.g., 2024–2027) |
| `password_hash` | VARCHAR(255) | No | | Bcrypt password hash generated via `password_hash()` |
| `must_change_password` | TINYINT(1) | No | 1 | Flag indicating mandatory first-login password update |
| `status` | ENUM('active', 'suspended', 'deactivated') | No | 'active' | Account lifecycle state |
| `temp_password_expires_at` | DATETIME | Yes | NULL | Expiration timestamp for temporary credentials (default 24h) |
| `auth_version` | INT UNSIGNED | No | 1 | Monotonic token version; incremented on status/password change to immediately revoke sessions |
| `created_at` | DATETIME | No | CURRENT_TIMESTAMP | Record creation timestamp |
| `updated_at` | DATETIME | No | CURRENT_TIMESTAMP | Automatic update timestamp |

**Constraints & Indexes:**
- Primary Key: `(`id`)`
- Unique Key: `uk_users_identifier` (`identifier`)
- Indexes: `idx_users_role` (`role`), `idx_users_status` (`status`), `idx_users_department` (`department`)

---

### 2.2 `elections`
Represents a collegiate election for **exactly one contested position**.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| `title` | VARCHAR(191) | No | | Official election title |
| `position` | VARCHAR(128) | No | | Single contested position (e.g., 'President', 'Secretary') |
| `description` | TEXT | Yes | NULL | Voter instructions and guidelines |
| `status` | ENUM('draft', 'scheduled', 'open', 'closed', 'results_published', 'cancelled') | No | 'draft' | Lifecycle state machine status |
| `opens_at` | DATETIME | No | | Real server datetime when voting opens |
| `closes_at` | DATETIME | No | | Real server datetime deadline |
| `results_visibility` | ENUM('live_provisional', 'after_closure_only') | No | 'live_provisional' | Results visibility policy during active voting |
| `created_by` | INT UNSIGNED | No | | Foreign key referencing `users(id)` |
| `created_at` | DATETIME | No | CURRENT_TIMESTAMP | Creation timestamp |
| `updated_at` | DATETIME | No | CURRENT_TIMESTAMP | Update timestamp |

**Constraints & Indexes:**
- Primary Key: `(`id`)`
- Foreign Key: `fk_elections_creator` (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
- Indexes: `idx_elections_status` (`status`), `idx_elections_dates` (`opens_at`, `closes_at`)

---

### 2.3 `candidates`
Contenders contesting within an election ballot.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| `election_id` | INT UNSIGNED | No | | Foreign key referencing `elections(id)` |
| `user_id` | INT UNSIGNED | Yes | NULL | Foreign key referencing `users(id)` for candidate login and dual voter linkage |
| `name` | VARCHAR(128) | No | | Candidate name |
| `manifesto` | TEXT | Yes | NULL | Candidate platform statement |
| `photo_path` | VARCHAR(255) | Yes | NULL | Sanitized relative path to uploaded profile photograph |
| `approval_status` | ENUM('pending', 'approved', 'rejected') | No | 'approved' | Candidate vetting status |
| `display_order` | INT | No | 0 | Ballot presentation sort order |
| `created_at` | DATETIME | No | CURRENT_TIMESTAMP | Creation timestamp |
| `updated_at` | DATETIME | No | CURRENT_TIMESTAMP | Update timestamp |

**Constraints & Indexes:**
- Primary Key: `(`id`)`
- Foreign Keys:
  - `fk_candidates_election` (`election_id`) REFERENCES `elections` (`id`) ON DELETE RESTRICT
  - `fk_candidates_user` (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
- Unique Key: `uk_candidates_election_user` (`election_id`, `user_id`)
- Indexes: `idx_candidates_election` (`election_id`, `approval_status`), `idx_candidates_user` (`user_id`)

---

### 2.4 `election_eligibility`
Voter whitelist defining which accounts are authorized to cast a vote in a specific election.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| `election_id` | INT UNSIGNED | No | | Foreign key referencing `elections(id)` |
| `user_id` | INT UNSIGNED | No | | Foreign key referencing `users(id)` |
| `assigned_by` | INT UNSIGNED | No | | Foreign key referencing `users(id)` (authorizing officer) |
| `assigned_at` | DATETIME | No | CURRENT_TIMESTAMP | Authorization timestamp |

**Constraints & Indexes:**
- Primary Key: `(`id`)`
- Unique Key: `uk_eligibility_election_user` (`election_id`, `user_id`)
- Foreign Keys:
  - `fk_eligibility_election` REFERENCES `elections(id)` ON DELETE RESTRICT
  - `fk_eligibility_user` REFERENCES `users(id)` ON DELETE RESTRICT
  - `fk_eligibility_assigner` REFERENCES `users(id)` ON DELETE RESTRICT

---

### 2.5 `election_participation`
Tracks voter participation **without recording candidate selection**. Guarantees that each eligible voter participates at most once.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| `election_id` | INT UNSIGNED | No | | Foreign key referencing `elections(id)` |
| `user_id` | INT UNSIGNED | No | | Foreign key referencing `users(id)` |
| `voted_at` | DATETIME | No | CURRENT_TIMESTAMP | Timestamp when ballot was submitted |
| `confirmation_hash` | VARCHAR(64) | No | | Cryptographic HMAC-SHA256 participation receipt token |

**Constraints & Indexes:**
- Primary Key: `(`id`)`
- Unique Key: `uk_participation_election_user` (`election_id`, `user_id`)
- Unique Key: `uk_participation_confirmation` (`confirmation_hash`)
- Foreign Keys:
  - `fk_participation_election` REFERENCES `elections(id)` ON DELETE RESTRICT
  - `fk_participation_user` REFERENCES `users(id)` ON DELETE RESTRICT
- Index: `idx_participation_election` (`election_id`)

---

### 2.6 `votes` (Privacy-Conscious Ballot Box)
Anonymous electronic ballot box storing candidate votes.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| `election_id` | INT UNSIGNED | No | | Foreign key referencing `elections(id)` |
| `candidate_id` | INT UNSIGNED | No | | Foreign key referencing `candidates(id)` |
| `cast_at` | DATETIME | No | CURRENT_TIMESTAMP | Ballot timestamp |

**CRITICAL BALLOT PRIVACY PROPERTY & LIMITATIONS:**
Notice that the `votes` table contains **NO `user_id` column**. The system separates *who participated* (`election_participation`) from *what candidate was chosen* (`votes`).
- **Application-Layer Separation:** Standard queries, reports, and phpMyAdmin views cannot directly link a voter's identity to their candidate choice.
- **Privileged DBA Limitation:** A privileged database superuser with binary replication log (`binlog`) access or real-time query logging can correlate microsecond transaction timestamps between `election_participation` and `votes`. True cryptographic zero-knowledge anonymity requires distributed multi-party cryptographic systems beyond a single-server relational database.
- **Receipt Coercion Resistance:** The cryptographic HMAC confirmation receipt in `election_participation` is independent of the voter's choice, preventing vote-buying or coercion.

**Constraints & Indexes:**
- Primary Key: `(`id`)`
- Foreign Keys:
  - `fk_votes_election` REFERENCES `elections(id)` ON DELETE RESTRICT
  - `fk_votes_candidate` REFERENCES `candidates(id)` ON DELETE RESTRICT
- Indexes: `idx_votes_election` (`election_id`), `idx_votes_candidate` (`candidate_id`)

---

### 2.7 `audit_logs`
Immutable record of all administrative, authentication, and election lifecycle events.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| `user_id` | INT UNSIGNED | Yes | NULL | Actor ID (NULL for anonymous/unauthenticated events) |
| `action` | VARCHAR(64) | No | | Event code (e.g., `BALLOT_CAST_ACCEPTED`, `ACCOUNT_CREATED`) |
| `entity_type` | VARCHAR(64) | No | | Target domain entity (`user`, `election`, `candidate`) |
| `entity_id` | VARCHAR(64) | Yes | NULL | Target identifier |
| `ip_address` | VARCHAR(45) | Yes | NULL | IPv4 / IPv6 client address |
| `user_agent` | VARCHAR(255) | Yes | NULL | Browser User-Agent header |
| `details_json` | TEXT | Yes | NULL | Sanitized JSON payload (passwords & secrets strictly redacted) |
| `created_at` | DATETIME | No | CURRENT_TIMESTAMP | Event timestamp |

**Constraints & Indexes:**
- Primary Key: `(`id`)`
- Foreign Key: `fk_audit_user` REFERENCES `users(id)` ON DELETE SET NULL
- Indexes: `idx_audit_user` (`user_id`), `idx_audit_action` (`action`), `idx_audit_created` (`created_at`)

---

### 2.8 `login_attempts`
Server-side brute-force defense tracking table.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| `identifier` | VARCHAR(64) | No | | Attempted institutional roll number / employee ID |
| `ip_address` | VARCHAR(45) | No | | Origin IP address |
| `attempted_at` | DATETIME | No | CURRENT_TIMESTAMP | Timestamp of attempt |
| `is_successful` | TINYINT(1) | No | 0 | 1 for success, 0 for failure |

**Constraints & Indexes:**
- Primary Key: `(`id`)`
- Indexes: `idx_login_ident` (`identifier`, `attempted_at`), `idx_login_ip` (`ip_address`, `attempted_at`)

---

### 2.9 `schema_migrations`
Tracks incremental database migrations executed via `bin/migrate.php`.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Primary Key |
| `version` | VARCHAR(32) | No | | Migration version identifier (e.g. '001', '002') (UNIQUE) |
| `name` | VARCHAR(128) | No | | Descriptive migration name |
| `applied_at` | DATETIME | No | CURRENT_TIMESTAMP | Migration timestamp |

**Constraints & Indexes:**
- Primary Key: `(`id`)`
- Unique Key: `uk_migrations_version` (`version`)

---

## 3. ACID Concurrency & Double-Voting Prevention
Ballot submission executes under an explicit database transaction with row-level locks and retry-on-deadlock resilience:
```sql
START TRANSACTION;

-- 1. Lock voter eligibility row exclusively (ensures serial validation per voter)
SELECT id FROM election_eligibility WHERE election_id = ? AND user_id = ? FOR UPDATE;

-- 2. Verify voter has not already participated
SELECT id FROM election_participation WHERE election_id = ? AND user_id = ?;

-- 3. Insert participation receipt (Enforced by UNIQUE KEY uk_participation_election_user)
INSERT INTO election_participation (election_id, user_id, confirmation_hash, voted_at) VALUES (?, ?, ?, NOW());

-- 4. Insert unlinked ballot into anonymous votes box (no user_id)
INSERT INTO votes (election_id, candidate_id, cast_at) VALUES (?, ?, NOW());

COMMIT;
```

### 3.1 Lock Ordering & Deadlock Mitigation
1. **Lock Ordering**: Every vote transaction acquires an exclusive row lock (`FOR UPDATE`) on the voter's existing row in `election_eligibility`. Because this row already exists, InnoDB places an exclusive record lock (without acquiring non-existent next-key or gap locks).
2. **Transient Lock Contention Handling**: If transient lock contention or a deadlock occurs (MySQL error `1213` or `1205`), `VotingRepository::recordBallot` automatically rolls back and executes up to 3 retry attempts with randomized exponential backoff jitter (20ms – 100ms).
3. **Database-Level Defense in Depth**: Even in the event of parallel requests from 25 concurrent connections for the same voter, the `UNIQUE KEY uk_participation_election_user` constraint unconditionally guarantees that at most one ballot is committed and all remaining 24 attempts are aborted and rolled back.
