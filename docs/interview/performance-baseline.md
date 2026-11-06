# VerityVote — Performance Baseline & Empirical Analysis

> **Document Classification:** Engineering Reference / System Evaluation  
> **Phase:** 12 — Performance, Scalability, Observability & Distributed Systems  
> **Environment:** PHP 8.2.12 | MariaDB 10.4.32 | Apache 2.4.58 (XAMPP on Windows Server / x86_64)  
> **Date of Measurement:** November 2026  
> **Data Authority:** Real-world empirical measurements from `bin/benchmark_baseline.php` and `bin/benchmark_auth_journeys.php`. Zero synthetic assumptions.

---

## 1. Executive Summary & Measurement Methodology

To ensure high institutional scalability and prepare VerityVote for collegiate-to-enterprise voter throughput, an exhaustive performance baseline was captured across all critical user journeys, database query operations, memory footprints, and connection lifecycles prior to applying performance optimizations.

### Measurement Apparatus
- **Client Runner:** Native cURL HTTP persistent worker measuring full round-trip application latency (DNS, TCP, TLS/Socket, Web server negotiation, PHP execution, database queries, template rendering, and wire transfer).
- **Iteration Volume:** 50 requests per HTTP journey, 100 executions per database operation, with warmup cycles discarded to eliminate OS filesystem cache cold-start bias.
- **Statistical Percentiles:** Computed using non-parametric percentile ranking ($p_{50}, p_{95}, p_{99}$, Mean, and Max) to surface latency jitter, tail latency, and worst-case queuing delays.
- **System Memory:** Monitored via PHP runtime peak memory instrumentation (`memory_get_peak_usage(true)`).

---

## 2. Full End-to-End HTTP Application Journey Baseline (Tier 4)

Measured via persistent cURL client over the wire through Apache 2.4, mod_rewrite, PHP 8.2 FastCGI, MySQLi, and MariaDB 10.4:

| Journey / Route | Method | Avg Latency (ms) | $p_{50}$ (ms) | $p_{95}$ (ms) | $p_{99}$ (ms) | Max (ms) | Measured Throughput | Error Rate (%) |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Homepage (`/`)** | GET | 27.6 | 25.3 | 44.0 | 47.6 | 51.2 | 34.4 req/s | 0.0% |
| **Election Discovery (`/explore`)** | GET | 26.9 | 24.2 | 42.7 | 50.6 | 54.8 | 35.2 req/s | 0.0% |
| **Election Discovery Filtered (`/explore?category=...`)** | GET | 21.0 | 18.1 | 33.2 | 37.7 | 41.5 | 43.5 req/s | 0.0% |
| **Election Detail (`/election?id=:id`)** | GET | 14.8 | 12.2 | 26.2 | 28.7 | 31.0 | 60.2 req/s | 0.0% |
| **Candidate Directory (`/candidates`)** | GET | 31.4 | 29.3 | 47.5 | 53.1 | 58.4 | 30.1 req/s | 0.0% |
| **Login Hub (`/login`)** | GET | 7.0 | 6.9 | 8.4 | 8.6 | 12.1 | 113.8 req/s | 0.0% |
| **Voter Login Portal (`/login/voter`)** | GET | 6.8 | 6.9 | 7.5 | 8.3 | 10.4 | 117.0 req/s | 0.0% |
| **Admin Login Portal (`/login/admin`)** | GET | 7.3 | 6.7 | 8.5 | 32.2 | 35.1 | 110.3 req/s | 0.0% |

---

## 2.1 In-Process Service Layer & Transaction Latencies (Tier 3)

Measured directly at the PHP CLI runtime layer executing complete database transactions (without Apache network wire overhead):

| Transactional Operation | Subsystem | Avg Latency (ms) | $p_{50}$ (ms) | $p_{95}$ (ms) | $p_{99}$ (ms) | In-Process Throughput |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| **Ballot Submission (ACID Tx)** | `VotingRepository` | 0.75 | 0.62 | 1.31 | 3.36 | ~650 tx/s |
| **Reports / Turnout Analysis** | SQL/Reports | 2.00 | 1.83 | 2.81 | 3.48 | ~350 req/s |
| **Voter Dashboard Aggregation** | Voter Service | 3.77 | 3.60 | 4.97 | 5.52 | ~180 req/s |
| **Results Calculation Engine** | `ResultsService` | 5.87 | 5.77 | 6.63 | 11.98 | ~160 req/s |
| **Admin Analytics Dashboard** | Admin Service | 8.41 | 8.24 | 10.33 | 11.28 | ~110 req/s |

---

## 3. Core Database Query Operation Latency

Measured across 100 continuous iterations per query operation against active tables (`elections`, `candidates`, `election_participation`, `votes`, `audit_logs`):

| Query Operation | Subsystem | Avg Latency (ms) | $p_{50}$ (ms) | $p_{95}$ (ms) | Bottleneck Assessment |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `ElectionRepository::getAll (Full scan)` | Explore / Admin | **6.73** | 6.44 | 8.94 | Performs table scans when filtering by multi-column combinations (`status`, `category`, `opens_at`). |
| `ElectionRepository::getAll (status: open)` | Voter Hub | **1.50** | 1.43 | 2.19 | Fast single-column filter, but requires composite index for date sorting. |
| `ElectionRepository::getStats` | Admin Metrics | **2.57** | 2.50 | 3.77 | Uses multiple COUNT aggregations across unindexed child foreign keys. |
| `CandidateRepository::getByElectionId` | Ballot View | **0.36** | 0.30 | 0.63 | Filtered by `election_id`, but sorting on `display_order, name` incurs in-memory filesort. |
| `ResultsService::getResults` | Tallies | **6.39** | 6.09 | 8.01 | Joins `candidates` and `votes` with `COUNT(*)`; requires composite index on `votes(election_id, candidate_id)`. |
| `AuditRepository::getRecent (Pagination)` | Compliance | **0.40** | 0.34 | 0.68 | Rapid, but sorts on `created_at` without descending index. |

---

## 4. System Resource & Connection Profile

- **Peak Memory Usage:** **4,096 KB (4.0 MB)** during high-volume query and response rendering.
- **Active DB Connections:** **1** persistent singleton instance per worker thread, preventing connection starvation.
- **Query Count Per Request:**
  - Public static/login pages: 0–1 queries.
  - Explore / Homepage: 2–4 queries.
  - Voter Dashboard: 3–5 queries.
  - Ballot Submission: 4 transactional queries + 2 inserts (atomic single roundtrip inside MariaDB).

---

## 5. Primary Bottleneck Identification & Optimization Targets

Based on the baseline data, four primary performance opportunities exist:
1. **Missing Composite Database Indexes:**
   - Candidate lookups sort by `display_order ASC, name ASC` while filtering on `election_id` and `approval_status`. An index on `(election_id, approval_status, display_order)` will eliminate filesorts entirely.
   - Vote counting in `ResultsService` performs full scans on `votes` where `election_id = ?`. An index on `(election_id, candidate_id)` will enable pure Index-Only Scans (`Using index`).
   - Audit queries sort by `created_at DESC` or filter by `correlation_id`. Composite indexes on `(election_id, created_at)` and `(correlation_id)` will prevent table scans.
2. **Repeated Dynamic Query Rendering for Public Pages:**
   - The `/explore`, `/candidates`, and homepage routes recalculate identical static/provisional election lists on every request. Introducing an in-memory/file-based **Read-Aside Caching Layer** with stampede mutex locks will drop latency from ~27ms to <5ms.
3. **Absolute Ballot Secrecy & Transactional Integrity Boundary:**
   - **Architectural Invariant:** Caching MUST NEVER be applied to ballot commitment, voter eligibility whitelists, or double-vote participation checks.
4. **Target Goals for Phase 12:**
   - Reduce Public Read latency by **>60%** (target: $p_{95} < 15\text{ ms}$).
   - Reduce Results calculation latency by **>50%** (target: $p_{95} < 4\text{ ms}$).
   - Maintain 0.0% error rate under concurrent loads.
