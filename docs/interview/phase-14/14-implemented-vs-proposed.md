# 14 — Implemented vs. Proposed Architectural Reality Matrix

**Audience:** Technical Auditors, Hiring Managers, and Systems Interviewers  
**Purpose:** An uncompromising, transparent inventory of every feature in the repository, classified strictly by its true implementation status.  
**Strict Rule:** Documentation, design discussions, and future roadmaps are never represented as working code.

---

## 1. Classification Categories

1. **Implemented & Fully Tested:** Complete in codebase, executed daily, verified by automated unit, integration, or load test suites.
2. **Implemented but Not Independently Verified:** Working in production/local environment, but lacks isolated headless automated test assertions.
3. **Partially Implemented:** Core architectural components exist, but edge-case automation or full integration is incomplete.
4. **Proposed Future Improvement:** Documented design alternative, architectural pattern, or scalability roadmap; **zero production code written**.
5. **Not Implemented:** Deliberately excluded by design trade-offs.

---

## 2. Feature Inventory Matrix

| Feature / Subsystem | Status | Repository Evidence | Notes & Limitations |
| :--- | :---: | :--- | :--- |
| **Atomic Voting Transaction Engine** | **Implemented & Fully Tested** | [`VotingRepository::recordBallot`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/app/Repositories/VotingRepository.php#L51) | 13 voting engine tests; verified against 25 parallel OS processes and 100-connection rapid sequential submissions. |
| **Separated Participation vs. Ballot Box** | **Implemented & Fully Tested** | [`schema.sql`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/database/schema.sql#L120) (`election_participation` & `votes`) | Prevents plain-text correlation in application queries; verified in Suite 4. |
| **Database-Enforced Unique Double-Vote Key** | **Implemented & Fully Tested** | `uk_participation_election_user` | Tested in MasterSecurityTest and Phase 12 load test Scenario 4. |
| **Cryptographic Ballot Confirmation Hash** | **Implemented & Fully Tested** | `hash_hmac('sha256', ...)` in `VotingRepository` | Emits HMAC-SHA256 receipt; verified in Suite 4. |
| **Three Segregated User Portals** | **Implemented & Fully Tested** | `public/index.php` (`/login/voter`, `/candidate`, `/admin`) | Verified in Suite 5 (Three Portals & Role Boundary tests). |
| **Dual-Role Candidate & Voter Support** | **Implemented & Fully Tested** | `AuthService.php` & `CandidateService.php` | Candidate can edit manifesto while casting ballot as student; verified in Suite 5. |
| **Argon2id & Bcrypt Password Hashing** | **Implemented & Fully Tested** | `AuthService.php` & `users.password_hash` | Verified in Suite 2 and load benchmark Scenario 2. |
| **Session Versioning (`auth_version`)** | **Implemented & Fully Tested** | `Auth.php` (`SELECT auth_version FROM users`) | Increments on password reset/suspension, instantly killing sessions; verified. |
| **Route-Aware CSRF Protection** | **Implemented & Fully Tested** | `CsrfMiddleware.php` & `CSRF.php` | Verified in Suite 8 (CsrfPortalLifecycleTest) and Suite 9. |
| **Read-Aside Caching with Mutex Locking** | **Implemented & Fully Tested** | `Cache.php` (`flock` on lock files) | 30.04x read latency acceleration over uncached DB queries verified in load benchmark Scenario 1. |
| **Electoral Safety Caching Invariant Guard** | **Implemented & Fully Tested** | `Cache::guardElectoralSafety()` | Throws fatal exception if eligibility or votes are cached; verified in Suite 11. |
| **Composite Covering Indexes (Migration 006)** | **Implemented & Fully Tested** | `006_performance_optimization_indexes.sql` | 5 composite indexes; verified via `EXPLAIN` and DB check script. |
| **Prometheus Telemetry Endpoint (`/public/metrics`)** | **Implemented & Fully Tested** | `Metrics.php` & `public/index.php` | Bearer token auth verified in Suite 12 (ApacheLoggingSecurityTest). |
| **Liveness & Readiness Health Probes** | **Implemented & Fully Tested** | `public/health/live.php`, `ready.php` | Verifies memory, DB ping, and 10 tables; verified in CI pipeline. |
| **Hardened Apache Access Logging (`verityvote_secure`)** | **Implemented & Fully Tested** | `apache-hardened-logging.conf` (`%m %U %H`) | Strips query strings and User-Agent; verified in Suite 12. |
| **Hardened Apache Error Logging (`ErrorLogFormat`)** | **Implemented & Fully Tested** | `apache-hardened-logging.conf` | Omits raw URLs and referers; verified in Suite 12. |
| **Structured Application Log Redaction** | **Implemented & Fully Tested** | `Logger::redactSensitiveData()`, `sanitizeUri()` | Verified in Suite 12 and MasterSecurityTest. |
| **Automated DB Backup & Restore CLI** | **Implemented & Fully Tested** | `bin/backup_db.php`, `bin/restore_db.php` | Verified in Suite 10 (BackupRestoreTest). |
| **GitHub Actions CI/CD with Node.js 24** | **Implemented & Fully Tested** | `.github/workflows/ci.yml` (`actions/checkout@v7`) | 168 tests execute on MariaDB container in under 90s. |
| **Disposable Test Database Engine** | **Implemented & Fully Tested** | `TestDatabaseManager.php` (`verityvote_test_suite`) | Dynamically created/destroyed on every run; primary DB pristine. |
| **Spreadsheet Formula Injection Scrubbing** | **Implemented & Fully Tested** | `CSVExporter::sanitizeCell()` | Neutralizes `=`, `+`, `-`, `@`; verified in Suite 1. |
| **Candidate Photo Upload File Validation** | **Implemented & Fully Tested** | `CandidateService.php` (`finfo_file`, MIME whitelist) | Validates image MIME types (JPEG, PNG); verified in HTTP tests. |
| **Interactive Front-End Voting UI** | **Implemented & Fully Tested** | `public/assets/js/vote.js`, `app/Views/student/ballot.php` | Vanilla JS client-side button lock and modal confirmation. |
| **Multi-Node Redis Shared Cache** | **Proposed Future Improvement** | Discussed in `06-performance-and-scalability.md` | Currently uses single-host `FileCacheDriver`. No Redis code in repo. |
| **Asynchronous Kafka Ballot Ingestion** | **Proposed Future Improvement** | Discussed in `09-system-design-scenarios.md` | Currently uses synchronous MariaDB ACID transactions. |
| **Aurora Multi-AZ Read Replica Routing** | **Proposed Future Improvement** | Discussed in `09-system-design-scenarios.md` | Currently targets a single primary MariaDB connection handle. |
| **Zero-Knowledge Homomorphic Encryption** | **Proposed Future Improvement** | Discussed in `05-security-engineering.md` | Currently uses separated relational tables. No zk-SNARKs implemented. |
| **Federated SAML / OAuth2 Enterprise SSO** | **Proposed Future Improvement** | Discussed in `11-adversarial-interview.md` | Currently uses internal Argon2id credential store. |
| **Microservices Decomposition** | **Not Implemented (Rejected)** | Analyzed in `02-system-architecture.md` | Intentionally rejected in favor of the modular monolith. |

---

## 3. How to Speak About This in Technical Interviews

- **When asked about Kafka or Redis:**  
  *"In our production codebase today, we use synchronous MariaDB ACID transactions and file-backed mutex caching. When modeling a proposed collegiate election of 5,000 voters across an 8-hour polling day (averaging ~0.17 votes/sec), our measured single-process benchmark rate of 45.1 tx/sec provides ample nominal throughput. In our architectural roadmap, we documented the migration to Kafka and Redis for 100,000+ voters or extreme sub-minute bursts, but we intentionally avoided over-engineering those dependencies into our initial baseline."*
- **When asked about Concurrent Web Concurrency vs. Electoral Volume:**  
  *"We are careful to distinguish proposed electoral population scenarios from concurrent active HTTP connections. A 5,000-voter election is an illustrative scenario (our active database has 1,399 accounts). Furthermore, concurrent HTTP connections on Windows XAMPP are governed by Apache's `ThreadsPerChild 150` under `mpm_winnt` and MariaDB's `max_connections = 151`. Sustained multi-client HTTP concurrency under external load remains unverified by distributed load testing."*
- **When asked about Cryptographic Anonymity:**  
  *"Today, our schema physically separates participation from the ballot box to prevent plain-text correlation in queries. However, we are completely transparent that an administrator with MariaDB binary log access could perform timestamp correlation attacks. We proposed homomorphic tallies as our zero-trust research improvement."*
