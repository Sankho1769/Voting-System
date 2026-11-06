# VerityVote — Database Performance Optimization & Query Engineering

> **Document Classification:** Engineering Reference / Technical Architecture  
> **Phase:** 12 — Performance, Scalability, Observability & Distributed Systems  
> **Database Engine:** MariaDB 10.4.32 / InnoDB  
> **Migration Reference:** `database/migrations/006_performance_optimization_indexes.sql`

---

## 1. Architectural Strategy & Query Bottlenecks

In an institutional voting platform, read/write patterns diverge sharply into two categories:
1. **High-Concurrency Write Critical Section:** The ballot box (`election_participation` and `votes`), which demands atomic, low-latency row locks without deadlock cascades.
2. **High-Volume Analytical and Directory Reads:** Homepage, election explore filters, candidate directories, live results calculations, and administrative audit trails.

Prior to Migration 006, MariaDB's query optimizer was forced to execute in-memory temporary tables, file-sort algorithms, and index-merge intersections due to missing composite indexes matching the access predicates and order-by clauses.

---

## 2. EXPLAIN Analysis: Before vs. After Optimization

### A. Candidate Directory & Ballot Ordering
**Query:**
```sql
SELECT c.*, u.identifier as user_identifier, u.department as user_department
FROM candidates c 
LEFT JOIN users u ON c.user_id = u.id
WHERE c.election_id = ? AND c.approval_status = 'approved' 
ORDER BY c.display_order ASC, c.name ASC;
```

- **Before Migration 006:**
  - Key used: `uk_candidates_election_user`
  - Extra: `Using where; Using filesort`
  - *Diagnosis:* The engine filtered by `election_id`, then buffered rows into temporary memory to execute a QuickSort on `display_order, name`. Under concurrent traffic, this causes CPU thrashing and buffer-pool churn.
- **After Migration 006 (`idx_candidates_election_approval_order`):**
  - Key used: `idx_candidates_election_approval_order (election_id, approval_status, display_order, name)`
  - Extra: `Using where` (**`Using filesort` eliminated**)
  - *Mechanism:* The B-Tree index leaf nodes are already physically ordered by `(election_id, approval_status, display_order, name)`. MariaDB traverses the index in forward range sequence, streaming rows directly to the application layer.

---

### B. Vote Tallies & Aggregation Engine
**Query:**
```sql
SELECT candidate_id, COUNT(*) as vote_count 
FROM votes 
WHERE election_id = ? 
GROUP BY candidate_id;
```

- **Before Migration 006:**
  - Key used: `idx_votes_election`
  - Extra: `Using where; Using temporary; Using filesort`
  - *Diagnosis:* The index only contained `election_id`. MariaDB had to perform secondary clustered-index lookups to fetch `candidate_id`, build an in-memory temporary hash table, and sort groups.
- **After Migration 006 (`idx_votes_election_candidate`):**
  - Key used: `idx_votes_election_candidate (election_id, candidate_id)`
  - Extra: `Using where; Using index` (**`Using temporary; Using filesort` eliminated**)
  - *Mechanism:* This is a **Covering Index (Index-Only Scan)**. Both the filtering predicate (`election_id`) and the aggregation column (`candidate_id`) reside in the index page. MariaDB resolves the query entirely within the InnoDB buffer cache without touching a single data page on disk.

---

### C. Election Discovery & Multi-Faceted Filtering
**Query:**
```sql
SELECT * FROM elections 
WHERE status = 'open' AND category = 'student_council' 
ORDER BY opens_at DESC;
```

- **Before Migration 006:**
  - Key used: `idx_elections_status, idx_elections_category`
  - Extra: `Using intersect(idx_elections_status, idx_elections_category); Using where; Using filesort`
  - *Diagnosis:* The engine had to scan two distinct indexes, perform a bitmapped intersection of row IDs, and then perform a filesort on `opens_at`.
- **After Migration 006 (`idx_elections_status_category_dates`):**
  - Key used: `idx_elections_status_category_dates (status, category, opens_at, closes_at)`
  - Extra: `Using where` (**`Using intersect` and `Using filesort` eliminated**)

---

### D. Audit Log Compliance & Traceability
**Query:**
```sql
SELECT * FROM audit_logs 
WHERE entity_type = 'election' AND entity_id = ? 
ORDER BY created_at DESC LIMIT 50;
```

- **Before Migration 006:**
  - Key used: `idx_audit_created`
  - Extra: `Using where` (Table scan / full index scan backwards through created_at, testing entity match)
- **After Migration 006 (`idx_audit_logs_entity`):**
  - Key used: `idx_audit_logs_entity (entity_type, entity_id, created_at)`
  - Rows examined: Dropped from 50+ scan iterations to exactly the matching rows (**~3 rows examined**).

---

## 3. Migration 006 DDL Specification

```sql
-- database/migrations/006_performance_optimization_indexes.sql

-- 1. Candidates: Index election candidates by approval status, display order, and name
CREATE INDEX `idx_candidates_election_approval_order` 
  ON `candidates` (`election_id`, `approval_status`, `display_order`, `name`);

-- 2. Votes: Index election and candidate together for covering scan tallies
CREATE INDEX `idx_votes_election_candidate` 
  ON `votes` (`election_id`, `candidate_id`);

-- 3. Elections: Composite index for status, category, and date filtering/sorting
CREATE INDEX `idx_elections_status_category_dates` 
  ON `elections` (`status`, `category`, `opens_at`, `closes_at`);

CREATE INDEX `idx_elections_status_dates` 
  ON `elections` (`status`, `opens_at`, `closes_at`);

-- 4. Audit Logs: Composite indexes for entity lookups and user timeline queries
CREATE INDEX `idx_audit_logs_entity` 
  ON `audit_logs` (`entity_type`, `entity_id`, `created_at`);

CREATE INDEX `idx_audit_logs_user_created` 
  ON `audit_logs` (`user_id`, `created_at`);

-- 5. Users: Composite index for role and status lookups
CREATE INDEX `idx_users_role_status` 
  ON `users` (`role`, `status`);
```

---

## 4. Index Engineering Trade-offs & Storage Footprint

Every secondary index carries storage and write-amplification costs. These were carefully weighed:

1. **Write Penalty Evaluation:**
   - The `votes` table receives inserts during election polling. The addition of `idx_votes_election_candidate` adds one lightweight 8-byte entry per vote. Because `votes` is strictly append-only (no UPDATEs or DELETEs), B-Tree page splits are sequential and low-cost.
   - The read performance gain during real-time result tabulation (covering index) dramatically outweighs the nominal microsecond insertion cost.
2. **Deliberately Excluded Indexes:**
   - `candidates.manifesto`: Unstructured large text. A standard B-Tree index would waste buffer cache; full-text indexing is unnecessary for candidate profiles.
   - `election_participation.voted_at`: Unnecessary as participation is queried strictly by `(election_id, user_id)` or `confirmation_hash`, both of which have existing UNIQUE indexes.
   - Low-cardinality standalone columns: Avoided indexing `approval_status` alone; included it exclusively as a composite prefix member.
