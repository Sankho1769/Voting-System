# Phase 14.3 — Final Benchmark Claims, Capacity & Infrastructure Evidence Matrix

**Auditor:** Principal Performance Engineer & Google/Microsoft Software Engineering Interview Reviewer  
**Repository:** [https://github.com/Sankho1769/Voting-System](https://github.com/Sankho1769/Voting-System)  
**Date:** November 2026  
**Target Portfolio:** `docs/interview/phase-14/`  
**Current HEAD:** `7d5fe9f37429faf1a01100c2aa7405e63bc13a80`  
**Standard of Review:** Absolute technical honesty, zero unsupported capacity assertions, strict evidence-backed classification.

---

## 1. Classification Taxonomy

Every technical metric, scalability claim, performance figure, and infrastructure parameter in the VerityVote Software Engineering Interview Portfolio is classified according to the following five rigorous tiers:

| Tier | Classification | Definition | Evidence Requirement |
| :---: | :--- | :--- | :--- |
| **I** | **VERIFIED** | Factually proven by live configuration inspection, direct runtime SQL queries, or reproducible automated test assertion. | Direct file path, line number, SQL variable, or test execution log. |
| **II** | **MEASURED LOCALLY** | An actual empirical benchmark measurement produced on the local Windows XAMPP test host. Valid within its measured boundary; must not be extrapolated beyond its test conditions. | Timestamped benchmark run output in `storage/logs/` or `tests/load/`. |
| **III** | **THEORETICAL** | A mathematical calculation or modeling assumption derived from declared parameters. | Explicit formula and declared input variables. |
| **IV** | **UNVERIFIED** | A historical number, comparison factor, or boundary claim with insufficient empirical logging, unverified runtime controls, or missing multi-client stress data. | Explicit disclosure of what remains unmeasured. |
| **V** | **PROPOSED** | A documented architectural roadmap, future scalability pattern, or prospective deployment scenario; **zero production code in repository**. | Explicit architectural transition criteria. |

---

## 2. Complete Technical Claims & Evidence Matrix (26 Claims)

| # | System Area / Claim | Reported Value | Audit Classification | Measured Boundary & Empirical Evidence | Interview Talking Point & Caveat |
| :---: | :--- | :---: | :---: | :--- | :--- |
| **1** | **Cache Read-Aside Throughput** | `16,326.6 ops/sec` | **MEASURED LOCALLY** | `DiscoveryLoadTest.php`: 1,000 sequential loops after 50 warmups. Reads file descriptor via Windows OS page cache and executes PHP `@unserialize`. Zero network, zero HTTP, zero DB. | Single-process microbenchmark. OS page cache state is unisolated. Never cite as HTTP request throughput. |
| **2** | **Cold Election Query Latency** | `7.21 ms` | **MEASURED LOCALLY** | `DiscoveryLoadTest.php`: First uncached query executing SQL `SELECT` with `WHERE status = 'open'` against MariaDB over local socket. | Real database B-Tree traversal over socket. |
| **3** | **Warm Cache Lookup Latency** | `0.24 ms` (p95: `0.13 ms`) | **MEASURED LOCALLY** | `DiscoveryLoadTest.php`: Subsequent lookup retrieving serialized array from `FileCacheDriver` via `CacheService::rememberElections()`. | Serviced from primed OS filesystem page cache and PHP runtime memory. |
| **4** | **Cache Acceleration Factor** | `30.04x` speedup ($96.67\%$ reduction) | **MEASURED LOCALLY** | Calculated ratio of Cold DB Query ($7.21\ \text{ms}$) to Warm Cache Lookup ($0.24\ \text{ms}$). | **Cache Acceleration Factor** (local cache offload vs. database engine), NOT a pure database index optimization. |
| **5** | **Single-Process Voting Burst** | `45.1 tx/sec` (Tx: `7.47 ms`, Cycle: `22.17 ms`) | **MEASURED LOCALLY** | `VotingBurstLoadTest.php`: 30 distinct voter ballots in a sequential loop. Each iteration instantiates a new MySQLi connection (`Database::createConnection()`). | Measured sequential rate. Connection setup ($12\text{--}15\ \text{ms}$) and commit handling contribute to cycle time; throughput is not simply $1 / \text{latency}$. |
| **6** | **In-Process Mixed Service Workload** | `129.9 ops/sec` (Mean: `7.69 ms`, p95: `38.05 ms`) | **MEASURED LOCALLY** | `MixedWorkloadLoadTest.php`: 100 sequential operations (70 reads, 20 votes, 10 admin). Exercises domain services, cache, and MariaDB sockets. | **In-process service-layer benchmark.** Does NOT exercise Apache, HTTP/TCP, URL routing, or middlewares. |
| **7** | **Pre-Optimization HTTP Routes** | `30.1 – 117.0 req/s` (`6.8 – 31.4 ms`) | **MEASURED LOCALLY** | `docs/interview/performance-baseline.md`: Measured over the wire via persistent cURL client through Apache 2.4 and in-process PHP module. | Actual Apache HTTP route baseline. |
| **8** | **MariaDB Commit Durability** | `innodb_flush_log_at_trx_commit = 1` | **VERIFIED** | Runtime SQL query: `SHOW VARIABLES LIKE 'innodb_flush_log_at_trx_commit'` returns `1`. Flush method: `unbuffered`. | Synchronous redo log flush is verified. Plausible contributing factor to commit latency, though proving physical disk bottlenecking requires dedicated profiling. |
| **9** | **MariaDB Max Connections** | `max_connections = 151` | **VERIFIED** | Runtime SQL query: `SHOW VARIABLES LIKE 'max_connections'` returns `151`. | Default MariaDB connection pool ceiling on test environment. |
| **10** | **MariaDB Buffer Pool Size** | `innodb_buffer_pool_size = 16 MB` | **VERIFIED** | Runtime SQL query: `SHOW VARIABLES LIKE 'innodb_buffer_pool_size'` returns `16777216`. | XAMPP default buffer pool (16 MB), not 256 MB or 4 GB. |
| **11** | **Candidate Directory Filesort Elimination** | Filesort eliminated | **VERIFIED** | `EXPLAIN` query plan on candidate directory query confirms `idx_candidates_election_approval_order` eliminates `Using filesort`. | Structural optimization verified by query execution plan. |
| **12** | **Results Tally Covering Index Scan** | Covering index scan | **VERIFIED** | `EXPLAIN` query plan on results tally query confirms `idx_votes_election_candidate` achieves `Using index`. | Index-only covering scan verified by query execution plan. |
| **13** | **Concurrency: 25 Parallel OS Processes** | Exactly 1 success, 24 double-vote rejections | **VERIFIED** | `VotingConcurrencyTest.php` (Suite 4): Spawns 25 parallel child processes via `proc_open` running `bin/vote_worker.php`. MariaDB records exactly 1 participation and 1 ballot. | True OS-level parallelism testing race conditions and row-level locking. |
| **14** | **Concurrency: 100 Connection Deduplication** | Exactly 1 success, 99 double-vote rejections | **VERIFIED** | `SameVoterAttackTest.php` (Scenario 4): 100 sequential submissions using `Database::createConnection()`. MariaDB records exactly 1 participation and 1 ballot. | Rapid sequential submissions testing connection churn and unique key deduplication. NOT 100 simultaneous threads. |
| **15** | **Windows Apache MPM Architecture** | `mpm_winnt`, `ThreadsPerChild 150` | **VERIFIED** | `httpd.exe -V`, `httpd.exe -M`, and `conf/extra/httpd-mpm.conf`. Single child process (PID 23296, working set ~18 MB) with embedded `php_module`. | Single-process multi-threaded model. Linux prefork directives (`MaxRequestWorkers`) do not apply. |
| **16** | **Collegiate Election Window Duration** | 8 Hours ($28,800\ \text{seconds}$) | **THEORETICAL** | Domain assumption based on typical collegiate polling hours. | Declared parameter for mathematical arrival rate modeling. |
| **17** | **Average Voting Arrival Rate** | $0.174\ \text{votes/sec}$ ($5,000 / 28,800$) | **THEORETICAL** | Arithmetic rate calculation: $5,000\ \text{ballots} / 28,800\ \text{seconds}$. | Mathematical average rate model for a 5,000-voter election scenario. |
| **18** | **5-Minute Deadline Rush Rate** | $3.33\ \text{votes/sec}$ ($1,000 / 300$) | **THEORETICAL** | Illustrative workload assumption: 20% of 5,000 voters (1,000 ballots) cast in the final 5 minutes ($300\ \text{s}$). | Hypothetical surge model. |
| **19** | **Nominal Rate Comparison Ratio** | $45.1 / 3.33 \approx 13.5\times$ | **THEORETICAL** | Arithmetic comparison of measured single-process benchmark rate ($45.1\ \text{tx/s}$) to hypothetical rush arrival rate ($3.33\ \text{votes/s}$). | **Nominal rate comparison only.** Does NOT establish a 13.5x production concurrency safety margin. |
| **20** | **Candidate Query Historical Speedup** | `31.4 ms → 0.31 ms` (101.3x) | **UNVERIFIED** | Baseline (31.4 ms) was an HTTP route measured over cURL (`performance-baseline.md`); post-index 0.31 ms is a historical micro-measurement lacking automated per-query iteration logs. | Boundary mismatch: HTTP route vs historical micro-query. Filesort elimination is verified, but speedup factor is unverified. |
| **21** | **Results Tally Historical Speedup** | `6.39 ms → 0.45 ms` (14.2x) | **UNVERIFIED** | Baseline (6.39 ms) was an uncached DB query; post-index 0.45 ms is a historical micro-measurement lacking automated multi-iteration artifact logs in `load_benchmark_results.json`. | Covering index scan is verified, but exact 14.2x speedup factor is an unverified historical claim. |
| **22** | **Concurrent HTTP Sessions Beyond 150** | Multi-client concurrency | **UNVERIFIED** | Bounded on Windows host by Apache `ThreadsPerChild 150` and MariaDB `max_connections = 151`. Not verified by multi-client HTTP stress testing. | Real-world multi-client network concurrency beyond 150 simultaneous HTTP sessions remains unverified. |
| **23** | **Proposed 5,000 Registered User Capacity** | 5,000 users supported | **PROPOSED** | Active local database `verityvote_db` contains **1,399 users**. A 5,000-user dataset has not been populated or tested under load. | Proposed population scenario. Schema design supports this volume, but empirical capacity is unproven. |
| **24** | **Proposed 5,000 Eligible Voter Capacity** | 5,000 eligible voters | **PROPOSED** | Active local database contains **1,029 eligibility records**. | Proposed election scenario. |
| **25** | **Distributed Redis Shared Cache** | Redis Cluster for 25k+ voters | **PROPOSED** | Documented roadmap in `06-performance-and-scalability.md`. Current repository uses local `FileCacheDriver`. | Zero Redis code in repository. |
| **26** | **Asynchronous Queue Ingestion** | Kafka / RabbitMQ for 100k+ voters | **PROPOSED** | Documented roadmap in `09-system-design-scenarios.md`. Current repository uses synchronous MariaDB ACID transactions. | Zero message queue code in repository. |

---

## 3. Summary of Matrix Classifications & Category Totals

The 26 claims are distributed across the 5 rigorous audit categories as follows:

| Classification Tier | Count | Category Percentage | Scope of Evidence |
| :--- | :---: | :---: | :--- |
| **VERIFIED** | **8** | 30.8% | Factually proven by live runtime SQL queries, server config files, EXPLAIN plans, or automated tests. |
| **MEASURED LOCALLY** | **7** | 26.9% | Actual empirical benchmark measurements produced in the local Windows XAMPP environment. |
| **THEORETICAL** | **4** | 15.4% | Mathematical calculations and declared domain modeling assumptions. |
| **UNVERIFIED** | **3** | 11.5% | Historical speedup factors lacking full iteration logs, or unmeasured multi-client concurrency. |
| **PROPOSED** | **4** | 15.4% | Prospective workload scenarios (5,000 users/voters) and documented architectural roadmaps (Redis/Kafka). |
| **TOTAL** | **26** | **100.0%** | **Complete reconciliation: displayed claims (26) and category totals (26) agree.** |

---

## 4. Subsystem Audits & Causal Corrections

### 4.1 MariaDB Durability & Throughput Relationship
- **Verified Configuration:** `innodb_flush_log_at_trx_commit = 1` and `innodb_flush_method = unbuffered` verified via direct SQL queries on MariaDB 10.4.32.
- **Measured Performance:** Single-process sequential loop executed 30 transactions at **45.1 tx/sec** with mean inner transaction latency of **7.47 ms** and loop cycle time of **22.17 ms** (including `Database::createConnection()`).
- **Causal Distinction:**
  - Redo log flushing on commit is a **plausible contributing factor** to transaction duration alongside connection establishment overhead.
  - However, physical disk I/O has **NOT** been independently proven as the primary limiting bottleneck through dedicated system profiling (e.g. disk queue depth, ETW tracing, or I/O wait monitoring).
  - Candidates must present disk durability as a verified operational setting and a plausible factor, not an unprofiled conclusion.

### 4.2 Capacity Claims & The "5,000 Voters" Distinction
- 5,000 registered users is classified as a **proposed population scenario** (active local database has 1,399 user accounts).
- 5,000 eligible voters is classified as a **proposed election scenario** (active database has 1,029 eligibility records).
- 45.1 tx/sec is a **measured single-process benchmark result**.
- 3.33 votes/sec is an **illustrative workload assumption** (1,000 votes in 300s).
- The 13.5x ratio is a **nominal rate comparison**, NOT a verified production concurrency safety margin.
- Database schema and migration validation verifies structural integrity, but does **not** prove empirical throughput for 5,000 concurrent voters.

### 4.3 Windows XAMPP Apache Infrastructure
- Apache 2.4.58 on Windows runs `mpm_winnt` with `ThreadsPerChild 150` and `MaxConnectionsPerChild 0`.
- In-process `php_module` runs inside the worker threads of the single child process (PID 23296, working set ~18 MB).
- Linux MPM directives (`MaxRequestWorkers`) and unconfigured parameters (`ListenBacklog 511`) were removed.

---

## 5. Verification & Quality Gates Summary

All quality gates passed cleanly:

| Quality Gate | Command | Scope / Standard | Result | Status |
| :--- | :--- | :--- | :---: | :---: |
| **PHP Syntax Lint** | `bin/lint_all.php` | 108 PHP files checked across entire repo | 0 Syntax Errors | **PASSED** |
| **Database Schema Check** | `bin/check_db.php` | 10 tables present; 6 migrations verified (001–006) | Database schema and migration verification passed. The primary database was preserved. | **PASSED** |
| **Master Test Suite** | `tests/run_tests.php` | 168 tests across 12 specialized suites | 168 Passed, 0 Failed, 0 Skipped (100%) | **PASSED** |
| **Load Benchmark Suite** | `tests/load/run_load_tests.php`| 12 assertions across 5 load scenarios | 12 Passed, 0 Failed | **PASSED** |
| **Git Whitespace Audit** | `git diff --check` | Working tree whitespace and format integrity | 0 Whitespace Errors | **PASSED** |
| **Working Tree Safety** | `git status` | Pre-commit state verification | 0 commits, 0 pushes | **PASSED** |

---

## 6. Final Publication Readiness Assessment

The Phase 14 Software Engineering Interview Portfolio is completely synchronized and evidence-backed:
1. No unverified production capacity claims ("guaranteed 5,000 voters").
2. No conflated comparison boundaries (caching speedup vs. database indexing).
3. No inaccurate web server assumptions (Windows `mpm_winnt` accurately documented).
4. No unprofiled causal claims regarding disk I/O bottlenecks.
5. Exact agreement between displayed claims (26) and category totals (8 + 7 + 4 + 3 + 4 = 26).
