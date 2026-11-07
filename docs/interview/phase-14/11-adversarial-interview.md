# 11 — The Adversarial Interview Playbook (25 Tough Grilling Questions)

**Audience:** Principal Engineering Interview Candidates (Google L6/Staff, Microsoft Principal, Meta E6)  
**Tone:** Uncompromising, Deeply Technical, Evidence-Based, and Refreshingly Honest  
**Objective:** How to handle aggressive, senior interviewers who probe for edge cases, challenge technical claims, and smell buzzword exaggeration from a mile away.

---

### Q1. "You claim a voter can only vote once. Prove it to me at the database level. Application code has bugs—show me the storage engine guarantee."
- **Strong Honest Answer:**  
  "In [`database/schema.sql`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/database/schema.sql#L127), the `election_participation` table enforces a compound unique key:
  ```sql
  UNIQUE KEY `uk_participation_election_user` (`election_id`, `user_id`)
  ```
  Even if my PHP application code suffered a complete logic failure and bypassed all pre-flight eligibility checks, MariaDB's InnoDB storage engine will abort any secondary insert attempt with `ERROR 1062 (23000): Duplicate entry for key 'uk_participation_election_user'`. In our Phase 12 load test, we fired 100 concurrent threads for the same voter ID; exactly 1 was committed by InnoDB and 99 triggered error 1062, resulting in exactly 1 participation record in the database."

---

### Q2. "What happens if two identical ballot HTTP requests from the same user hit your web server at the exact same millisecond?"
- **Strong Honest Answer:**  
  "Both requests spawn separate PHP processes and initiate transactions in `VotingRepository::recordBallot`. In step 4, the transaction issues:
  ```sql
  SELECT id FROM election_eligibility WHERE election_id = ? AND user_id = ? FOR UPDATE
  ```
  The first request ($R_1$) acquires the Exclusive Lock (X-lock) on the voter's whitelist row. The second request ($R_2$) is blocked at the database engine level and enters a lock wait queue. $R_1$ checks participation, inserts into `election_participation`, inserts into `votes`, and commits. Upon commit, $R_1$ releases the lock. $R_2$ immediately wakes up, checks participation, finds the row just written by $R_1$, rolls back, and returns: `'Ballot rejected: You have already cast your vote in this election.'`"

---

### Q3. "What happens if the database commits the ballot, but the network connection drops before the HTTP response reaches the voter's browser?"
- **Strong Honest Answer:**  
  "This is the classic Two Generals Problem. In VerityVote, the ballot is already permanently written to the redo log and cannot be rolled back. When the user reconnects and retries, `recordBallot` runs an idempotency pre-check on retry attempts:
  ```php
  $existing = $this->getParticipation($electionId, $userId);
  if ($existing !== null && !empty($existing['confirmation_hash'])) {
      return $existing['confirmation_hash'];
  }
  ```
  It recovers the original HMAC-SHA256 receipt hash and returns it with HTTP 200 without inserting a second ballot. We provide at-most-once ballot recording with idempotent result recovery."

---

### Q4. "How do you know your cache cannot accidentally authorize an ineligible voter?"
- **Strong Honest Answer:**  
  "By architectural enforcement in [`Cache::guardElectoralSafety(string $key)`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/app/Core/Cache.php#L327). Any cache call containing forbidden substrings (`voter`, `eligibility`, `participation`, `ballot`, `password`, `auth_token`, `csrf`) throws an immediate fatal `RuntimeException`. Electoral decision gates are prohibited from reading or writing cache; they query authoritative database rows inside ACID transactions."

---

### Q5. "Is the system truly anonymous? Can a rogue database administrator correlate my vote to my student ID?"
- **Strong Honest Answer:**  
  "**No, the system is NOT mathematically anonymous against a rogue database administrator with access to MariaDB binary logs.** While the `votes` table does not contain `user_id`, a DBA can perform a **timestamp correlation attack**: matching `voted_at` in `election_participation` with `cast_at` in `votes` down to the millisecond, or inspecting the sequential order of row inserts in the binary transaction log. VerityVote provides *practical privacy* against other users and reporting dashboards, but true zero-trust anonymity would require cryptographic mix-nets or zero-knowledge homomorphic tallies."

---

### Q6. "What happens if an administrator clicks 'Close Election' at the exact instant a voter submits their ballot?"
- **Strong Honest Answer:**  
  "In `VotingRepository::recordBallot`, step 1 executes:
  ```sql
  SELECT id, status, opens_at, closes_at FROM elections WHERE id = ? LOCK IN SHARE MODE
  ```
  The voter holds a Shared Read Lock (S-lock) on the election row. An administrator attempting `UPDATE elections SET status = 'closed'` requires an Exclusive Write Lock (X-lock). InnoDB forces the administrative update to wait until active in-flight voting transactions commit or roll back. If the voter transaction reads the row before closure, it commits. If the admin's transaction committed first, the voter's read sees `status = 'closed'` and aborts with an exception."

---

### Q7. "Why did you build this in PHP and not Go, Rust, or Node.js?"
- **Strong Honest Answer:**  
  "For an institutional collegiate voting application, modern strict-typed PHP 8.2 provides rapid iteration, clean monolithic layering, and native compatibility with ubiquitous university shared-hosting and XAMPP infrastructure. Performance bottlenecks in voting systems are not determined by language runtime speed; they are governed by database lock serialization and network round-trips. A database transaction in Go takes the exact same 7.47 ms as in PHP because both are waiting on MariaDB's disk flush."

---

### Q8. "Why should I trust your benchmark numbers? Did you test this on a real cluster or just a developer laptop?"
- **Strong Honest Answer:**  
  "The benchmarks in [`storage/logs/load_benchmark_results.json`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/storage/logs/load_benchmark_results.json) were executed on a single host (AMD/Intel CPU, NVMe SSD, MariaDB 10.4, PHP 8.2). That is why our documentation explicitly separates the **Four Benchmark Tiers**: the 16,326 ops/sec figure is an in-process cache microbenchmark; real end-to-end mixed HTTP throughput is 129.9 ops/sec, and voting burst throughput is 45.1 tx/sec. I do not claim these represent multi-node distributed cluster capacity."

---

### Q9. "What happens if two application servers share the same filesystem cache?"
- **Strong Honest Answer:**  
  "Local filesystem locking (`flock`) only operates within the kernel of a single operating system. If two separate servers attempt to use a shared NFS mount for `FileCacheDriver`, `flock` becomes unreliable and introduces massive network I/O latency. That is an acknowledged limitation of the single-node architecture. Scaling to multiple application nodes strictly requires replacing `FileCacheDriver` with a centralized Redis cluster."

---

### Q10. "How do you test a database backup? Most teams only test backups when disaster strikes."
- **Strong Honest Answer:**  
  "In [`tests/Integration/BackupRestoreTest.php`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/tests/Integration/BackupRestoreTest.php) (Suite 10 of our automated regression suite), we test backup and restore on every CI run:
  1. `bin/backup_db.php` exports live schema and fixtures to a standalone SQL file.
  2. `bin/restore_db.php` restores that SQL file into an isolated disposable database.
  3. The test queries the restored database to assert that all 10 tables, foreign keys, and rows exist intact.
  4. The temporary database and dump file are purged upon completion."

---

### Q11. "Why did you use MySQLi instead of PDO?"
- **Strong Honest Answer:**  
  "MySQLi is tightly coupled to the MySQL protocol, offering slightly lower invocation overhead and explicit parameter typing (`'i'`, `'s'`). However, the trade-off is zero portability: VerityVote cannot switch to PostgreSQL without rewriting the repository layer. In an enterprise system where multi-database vendor support is required, PDO is the superior choice."

---

### Q12. "What happens if MariaDB suffers a hard crash (power loss) during a ballot transaction?"
- **Strong Honest Answer:**  
  "InnoDB utilizes Write-Ahead Logging (WAL) via its redo log and undo log. If power fails mid-transaction:
  - If the commit statement had not completed, MariaDB's crash recovery process scans the undo log upon restart and completely rolls back any uncommitted changes.
  - If the commit had completed and flushed to the redo log, crash recovery replays the change forward.
  - In either case, the database is never left with an orphaned participation record without a corresponding ballot, or vice-versa."

---

### Q13. "Can an attacker bypass CSRF protection by forging a Bearer token?"
- **Strong Honest Answer:**  
  "No. Bearer token authentication in VerityVote is strictly restricted to the telemetry endpoint (`/public/metrics`). User actions (voting, creating elections, changing passwords) require session cookie authentication. [`CsrfMiddleware.php`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/app/Middleware/CsrfMiddleware.php) strictly enforces CSRF tokens on all session-authenticated POST requests; presenting an arbitrary Bearer header on user mutation routes has zero authorization effect."

---

### Q14. "Why did you remove `User-Agent` from Apache access logs in Phase 13? Doesn't that hurt debugging?"
- **Strong Honest Answer:**  
  "`User-Agent` is an arbitrary client-controlled header. In security audits, attackers frequently inject malicious payloads, SQL strings, or credentials into the `User-Agent` string (e.g. `User-Agent: curl -H token=SECRET`). Removing `%{User-Agent}i` from `verityvote_secure` access logs eliminates header injection and log poisoning entirely. For operational debugging, request latency (`%D`) and response status (`%>s`) provide sufficient telemetry without exposing log storage to injection vectors."

---

### Q15. "Your results tally uses `LEFT JOIN votes v ON c.id = v.candidate_id`. What happens when an election has 1,000,000 votes?"
- **Strong Honest Answer:**  
  "In a single-election tally with 1,000,000 votes, a live `COUNT(*)` query scanning 1,000,000 index entries takes ~150–300 ms. If 5,000 users refresh the page, the database will choke. To prevent this, VerityVote caches results for 5 seconds using `CacheService::rememberResults()`. At true massive scale (1M+ votes), we would transition to an **Increment-on-Write Counter Table** (`candidate_tallies`) updated atomically via triggers or asynchronous aggregation workers."

---

### Q16. "How does your system prevent a student from voting in an election for a department they don't belong to?"
- **Strong Honest Answer:**  
  "Through the `election_eligibility` whitelist table. Elections do not rely on dynamic string matching (e.g. checking if user department matches election department). Instead, the institutional registrar imports an explicit whitelist (`election_eligibility`) mapping `(election_id, user_id)`. During ballot casting, the transaction verifies that an explicit row exists in `election_eligibility` under exclusive lock."

---

### Q17. "What happens if a voter inputs a negative integer or decimal for `candidate_id`?"
- **Strong Honest Answer:**  
  "In `public/index.php`, inputs are cast to integers `(int)($_POST['candidate_id'] ?? 0)`. [`Validator.php`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/app/Helpers/Validator.php) verifies that IDs are strictly positive integers ($> 0$). Furthermore, `VotingRepository::recordBallot` binds parameters using `'i'` (integer), and the candidate query checks `WHERE id = ? AND election_id = ?`. A negative ID returns null and triggers an immediate `RuntimeException: Selected candidate does not contest in this election`."

---

### Q18. "Why did you build custom authentication instead of using OAuth2 / OpenID Connect (Google Workspace / Microsoft Entra)?"
- **Strong Honest Answer:**  
  "In an institutional college context, student roll numbers and academic standings are managed by the campus registrar's local student information system (SIS), which often lacks federated SSO integration. Self-contained authentication with temporary credentials allowed the system to operate independently. In a production enterprise deployment, delegating authentication to SAML 2.0 or Microsoft Entra ID would be the preferred architecture."

---

### Q19. "What is your test coverage, and how do you know you didn't just write 168 superficial assertion tests?"
- **Strong Honest Answer:**  
  "Our 168 tests across 12 suites include end-to-end integration journeys, multi-threaded parallel concurrency attacks, cryptographic receipt validation, backup restoration verification, and Apache log leakage tests. We test real database state transitions and real HTTP responses against a live Apache front controller—not just unit tests asserting `true === true`."

---

### Q20. "If an election officer is corrupt, can they approve an unauthorized candidate?"
- **Strong Honest Answer:**  
  "The application logs all candidate approval state changes in `audit_logs` with the officer's `user_id`, client IP address, timestamp, and correlation ID. However, if an officer has authorized administrative credentials, the application permits approval. Mitigation requires a **Multi-Party Authorization (Dual-Control)** workflow where candidate approval requires cryptographic sign-off from two independent election commissioners."

---

### Q21. "Why did you use exponential backoff with jitter on deadlocks? Why not just retry immediately?"
- **Strong Honest Answer:**  
  "If transactions $T_1$ and $T_2$ collide and deadlock, retrying immediately causes both transactions to wake up at the exact same millisecond and collide again (livelock). Adding randomized jitter (`rand(5ms, 25ms)`) desynchronizes the competing transactions, allowing one to acquire the locks and commit while the other waits."

---

### Q22. "What happens if a user submits a vote while their account is being suspended by an admin?"
- **Strong Honest Answer:**  
  "Step 3 in `recordBallot` reads the voter's status under `LOCK IN SHARE MODE`:
  ```sql
  SELECT id, status, role, must_change_password FROM users WHERE id = ? LOCK IN SHARE MODE
  ```
  If the admin's suspension transaction commits first, the vote transaction sees `status = 'suspended'` and throws: `'User account is inactive, pending approval, suspended, or disabled.'` The ballot is rejected."

---

### Q23. "Why don't you use Redis for sessions instead of PHP's default file session handler?"
- **Strong Honest Answer:**  
  "For a single-server deployment, PHP's native file session handler is fast, reliable, and requires zero external daemon maintenance. For a multi-node load-balanced cluster, filesystem sessions fail because user requests bounce between nodes. Migrating to Redis for session storage is the immediate first step for horizontal scaling."

---

### Q24. "How do you defend against an administrator tampering with results in the database using phpMyAdmin?"
- **Strong Honest Answer:**  
  "Direct SQL tampering in phpMyAdmin cannot be prevented by application-level code. However, VerityVote issues voters an HMAC-SHA256 receipt hash upon voting. The total number of valid ballots in `votes` must mathematically equal the total count in `election_participation`. If a DBA injects 100 fake ballots into `votes`, the audit reconciliation query immediately flags a discrepancy: valid ballots ($N+100$) $\neq$ participation count ($N$)."

---

### Q25. "What is the single biggest architectural weakness in VerityVote today, and how would you fix it?"
- **Strong Honest Answer:**  
  "The biggest architectural bottleneck is **synchronous database write serialization during voting bursts**. Because every ballot executes an ACID transaction with row-level locks on a single MariaDB master, write throughput is hard-capped at ~45–150 tx/sec. To scale to a national election with 100,000+ voters, I would refactor ballot ingestion to an **asynchronous buffered architecture**: accepting ballots at an API Gateway backed by an Apache Kafka partition queue, issuing an immediate cryptographic intake receipt, and having a pool of background consumers batch-commit ballots to storage."
