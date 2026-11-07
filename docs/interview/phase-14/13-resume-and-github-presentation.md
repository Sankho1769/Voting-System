# 13 — Resume, GitHub & Portfolio Presentation Guide

**Target Candidates:** Computer Science Students, University Graduates, and Entry-Level Software Engineers  
**Target Roles:** Software Engineer (University Graduate / New Grad), Entry-Level Backend Engineer, Junior Systems Engineer  
**Standard:** 100% Truthful, Empirically Backed, and Optimized for Top-Tier Tech (Google, Microsoft, Meta, Amazon)  
**Strict Rule:** Zero invented user counts, zero fake awards, and zero unsubstantiated production claims.

---

## 1. Resume Bullet Point Variations

Depending on the specific engineering role (Backend, Systems/Infrastructure, or Security/DevSecOps), choose the tailored bullet set below. These are written specifically for a student or new-grad resume:

### Option A: Backend Software Engineer Focus (Recommended Default for New Grad SWE)
- **VerityVote — Institutional Digital Voting Platform | Independent Engineering Project**
  - Engineered a high-integrity modular monolith in PHP 8.2 & MariaDB 10.4, enforcing strict ACID ballot transactions with row-level locking (`LOCK IN SHARE MODE`, `FOR UPDATE`) and database-enforced unique constraints to prevent double-voting under high concurrency.
  - Architected a multi-tier Read-Aside caching engine with non-blocking mutex locks (`flock`) to prevent cache stampedes, achieving **30x read acceleration** over uncached database queries (reducing latency from **7.21 ms to 0.24 ms**, sustained **16,326 ops/sec** in-process microbenchmark).
  - Designed 5 composite covering indexes across 10 tables, eliminating temporary disk tables and in-memory filesorts on real-time election result aggregation queries.
  - Built an automated testing harness of **168 regression tests across 12 suites** and 5 load benchmark scenarios, integrated into a GitHub Actions CI pipeline executing against MariaDB and Apache VirtualHosts in under 90 seconds.

### Option B: Systems & Infrastructure / SRE Focus (New Grad)
- **VerityVote — High-Concurrency Electoral Engine & CI/CD Pipeline | Systems Engineering Project**
  - Designed an isolated disposable database testing architecture in PHP/MariaDB, dynamically provisioning and tearing down ephemeral test schemas to eliminate test-suite pollution.
  - Implemented end-to-end observability including Prometheus-compatible telemetry (`/public/metrics`), Kubernetes-style liveness/readiness health probes, and structured JSON audit logging.
  - Hardened Apache 2.4 server configuration with custom access logging (`verityvote_secure`), stripping URL query strings and client headers to eliminate credential leakage across server disk logs.
  - Optimized GitHub Actions CI workflows, migrating runner actions to native Node.js 24 runtimes and automating test step summaries.

### Option C: Application Security & DevSecOps Focus (New Grad)
- **VerityVote — Secure Electoral Platform & Threat Model | Security Engineering Project**
  - Implemented Defense-in-Depth application security controls including Argon2id password hashing, constant-time token comparison (`hash_equals`), and session version tracking (`auth_version`) for instant global revocation.
  - Built route-aware CSRF token validation and spreadsheet formula injection defenses (CSV prefix neutralization) across 3 role-segregated user portals (Voter, Candidate, Admin).
  - Conducted comprehensive logging security audits, implementing recursive secret redaction in application loggers and eliminating query-string credential persistence in web server logs.
  - Achieved a **100% pass rate across 168 automated security and integration tests**, validating concurrency collisions, role boundary enforcement, and SQL injection resistance.

---

## 2. GitHub Repository Presentation

### 2.1 Repository Description (One-Liner for GitHub About Box)
> "High-integrity institutional voting system in PHP 8.2 & MariaDB with ACID concurrency control, read-aside caching, 168 automated tests, and GitHub Actions CI."

### 2.2 Topics / Tags for GitHub
`php8`, `mariadb`, `acid-transactions`, `concurrency-control`, `caching`, `software-engineering`, `github-actions`, `application-security`, `benchmarking`

### 2.3 Portfolio Project Summary (For Personal Portfolio Website)
> **VerityVote** is a full-stack, modular monolithic institutional voting platform engineered with an uncompromising focus on data integrity, concurrency safety, and application security. Built in strict-typed PHP 8.2, MariaDB 10.4, and Apache 2.4, it addresses the unique challenges of collegiate elections: sharp traffic bursts, zero tolerance for double-voting fraud, and separation of voter participation from secret ballots.
> 
> The project features an empirical performance harness demonstrating 30x query acceleration via read-aside caching, an automated CI/CD pipeline running 168 integration tests across ephemeral database environments, and a comprehensive security audit eliminating credential leakage from server logs.

---

## 3. Measurable Achievements (Empirically Verified by Test Harness)

| Metric | Measured Baseline | Verification Method |
| :--- | :--- | :--- |
| **Automated Test Coverage** | **168 Tests Passed (100% Pass Rate across 12 Suites)** | `tests/run_tests.php` |
| **Load Benchmark Assertions** | **12 / 12 Assertions Passed across 5 Scenarios** | `tests/load/run_load_tests.php` |
| **Syntax Verification** | **108 / 108 PHP Files Checked (0 Errors)** | `bin/lint_all.php` |
| **Cache Latency Acceleration** | **7.21 ms (Cold DB query) → 0.24 ms (Warm cache lookup) (30.04x speedup)** | `storage/logs/load_benchmark_results.json` |
| **Cache Microbenchmark** | **16,326.6 ops/sec** (Single-process read-aside deserialization via OS page cache) | `storage/logs/load_benchmark_results.json` |
| **Voting Burst Throughput** | **45.1 tx/sec** (Single-process sequential loop of 30 ballots, avg 7.47 ms tx, 22.17 ms cycle) | `storage/logs/load_benchmark_results.json` |
| **Same-Voter Collision Protection**| **25 parallel OS processes & 100 connection submissions → Exactly 1 accepted, all duplicates rejected** | `tests/run_tests.php` Suite 4 & `tests/load/run_load_tests.php` |
| **CI/CD Execution Time** | **84 Seconds** (Full suite on GitHub Actions Ubuntu runner) | GitHub Actions Run ID `42345748651` |

---

## 4. Key Talking Points for Behavioral / Technical Interviews

1. **"Tell me about a challenging technical problem you solved in this project."**  
   *Talk about the two concurrency verification harnesses: 25 parallel OS processes spawned via `proc_open` racing in the process table (`VotingConcurrencyTest.php`), and 100 rapid sequential submissions over independent MySQLi connection handles (`SameVoterAttackTest.php`). Explain how relying solely on application-level checks resulted in race conditions, and how combining `SELECT ... FOR UPDATE` with an immutable database compound unique key (`election_id`, `user_id`) achieved 100% duplicate rejection under concurrency.*

2. **"How did you approach performance optimization?"**  
   *Describe the 4-tier benchmark methodology. Explain how you used `EXPLAIN` to identify filesorts in candidate directories and tally queries, leading to Migration 006's 5 composite covering indexes, and how file-backed mutex locks solved the cache stampede problem.*

3. **"Tell me about a trade-off you made that you would reconsider at scale."**  
   *Discuss the synchronous voting transaction design. For typical collegiate election durations where ballots are submitted across an 8-hour polling day (averaging ~0.17 to 0.35 votes/sec), synchronous commits provide immediate confirmation. But for large-scale elections or sub-minute bursts exceeding worker limits, you would transition to an asynchronous message queue buffer (Kafka/RabbitMQ) to decouple HTTP ingestion from database write serialization.*
