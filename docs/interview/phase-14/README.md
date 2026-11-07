# VerityVote — Software Engineering Interview Portfolio (Phase 14)

**Target Candidates:** Computer Science Students, University Graduates & Entry-Level Software Engineers  
**Target Roles:** Software Engineer (University Placement / New Grad), Entry-Level Backend Engineer (Google L3, Microsoft SDE I)  
*(Advanced Staff & Senior Systems Design material is clearly labeled as optional extension reading).*  
**Repository:** [https://github.com/Sankho1769/Voting-System](https://github.com/Sankho1769/Voting-System)  
**Stack:** PHP 8.2 (Strict Types), MariaDB 10.4 / MySQL 8.0, Apache 2.4, HTML5/CSS3/Vanilla JS, GitHub Actions CI  
**Status:** Complete, Empirically Audited & Calibrated for New Grad Interviews  

---

## 1. Executive Portfolio Index

This directory (`docs/interview/phase-14/`) contains a comprehensive, evidence-based software engineering interview portfolio designed to help you explain, defend, and demonstrate the technical decisions behind VerityVote.

| Doc # | Document Title | Primary Focus & Contents | Recommended Timing |
| :---: | :--- | :--- | :---: |
| **01** | [`01-project-introduction.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/01-project-introduction.md) | 60-second elevator pitch, 2-minute technical summary, 5-minute deep architecture pitch | Day 1 & Day 7 |
| **02** | [`02-system-architecture.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/02-system-architecture.md) | Modular monolith rationale, layered architecture, 3 segregated portals, election state machine | Days 2–6 |
| **03** | [`03-database-engineering.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/03-database-engineering.md) | Full ER diagram, 10 InnoDB tables, Migration 006 composite covering indexes, EXPLAIN analysis | Days 8–10 |
| **04** | [`04-voting-concurrency.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/04-voting-concurrency.md) | 15-step atomic voting transaction, sequence diagram, race conditions, 100-burst attack proof | Days 15–16 |
| **05** | [`05-security-engineering.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/05-security-engineering.md) | 14 security controls (Threat → Vulnerability → Fix → Test → Limitation), administrator correlation reality | Days 19–20 |
| **06** | [`06-performance-and-scalability.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/06-performance-and-scalability.md) | The 4 benchmark tiers, empirical load data (30x cache speedup, 45.1 tx/s), anti-stampede mutex | Days 22–24 |
| **07** | [`07-failure-analysis.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/07-failure-analysis.md) | FMEA matrix (12 failure modes), deadlock exponential backoff, mid-stream network drop recovery | Days 11–12 |
| **08** | [`08-engineering-tradeoffs.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/08-engineering-tradeoffs.md) | 20 real architectural trade-offs (Monolith vs. Microservices, MySQLi vs. PDO, File vs. Redis) | Day 25 |
| **09** | [`09-system-design-scenarios.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/09-system-design-scenarios.md) | 10 realistic system design questions (10k to 100k scale, Kafka buffering, live results aggregation) | Days 23–24 |
| **10** | [`10-interview-question-bank.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/10-interview-question-bank.md) | 50 technical questions across 10 categories (PHP, OOP, SQL, OS, Networking, Security, Concurrency) | Days 14 & 21 |
| **11** | [`11-adversarial-interview.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/11-adversarial-interview.md) | 25 aggressive grilling questions from senior/staff interviewers with honest, high-caliber answers | Days 28–29 |
| **12** | [`12-live-demo-runbook.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/12-live-demo-runbook.md) | 10-minute reproducible live project demonstration script (Zero chance of failure) | Day 27 |
| **13** | [`13-resume-and-github-presentation.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/13-resume-and-github-presentation.md) | 3 tailored resume bullet sets (Backend, SRE, Security), GitHub summary, verifiable metrics | Day 30 |
| **14** | [`14-implemented-vs-proposed.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/14-implemented-vs-proposed.md) | Complete reality matrix: Implemented & Tested vs. Partially Implemented vs. Proposed Roadmap | Day 26 |
| **15** | [`15-study-plan.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/15-study-plan.md) | Daily 30-day master preparation roadmap combining theory, coding, and mock grilling | Days 1–30 |
| **Audit** | [`PHASE_14_3_FINAL_EVIDENCE_MATRIX.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/interview/phase-14/PHASE_14_3_FINAL_EVIDENCE_MATRIX.md) | Exhaustive evidence matrix classifying 25 performance, capacity, and infrastructure claims | Reference |

---

## 2. Tailored Learning Sequences by Interview Type

### Track A: The Backend & Systems Design Interview (Google SWE / Microsoft SDE II)
1. Start with `01-project-introduction.md` (Master the 2-minute elevator pitch).
2. Deep dive into `02-system-architecture.md` and `04-voting-concurrency.md` (Master the 15-step transaction lifecycle).
3. Study `03-database-engineering.md` and `08-engineering-tradeoffs.md` (Be ready to defend MySQLi, row locks, and composite indexes).
4. Review `06-performance-and-scalability.md` (Explain the 4 benchmark tiers without confusing microbenchmarks with HTTP throughput).
5. Practice Scenarios 1, 2, and 3 in `09-system-design-scenarios.md`.

### Track B: The Security & DevSecOps Interview (Google Cloud Security / Microsoft MSRC)
1. Read `05-security-engineering.md` (Memorize the 14 security controls and the administrator correlation limitation).
2. Study `docs/interview/logging-security-audit.md` and `05-security-engineering.md` (Explain the Phase 13 Apache logging audit and User-Agent removal).
3. Review `07-failure-analysis.md` and `11-adversarial-interview.md` (Questions Q1, Q4, Q5, Q13, Q14).
4. Run `tests/run_tests.php` to demonstrate the 10 Apache logging security tests in Suite 12.

### Track C: The Live Technical Demonstration & Hiring Manager Screen
1. Rehearse `01-project-introduction.md` out loud.
2. Follow `12-live-demo-runbook.md` step-by-step with a stopwatch (verify Apache and MySQL are running).
3. Review `13-resume-and-github-presentation.md` for verifiable bullet points.
4. Keep `14-implemented-vs-proposed.md` open to answer roadmap questions with absolute honesty.

---

## 3. Core Verification Evidence Backing This Portfolio

Every technical claim in this portfolio is backed by automated tests and benchmarks executing in this repository:
- **PHP Syntax:** 108 / 108 files syntax verified (`bin/lint_all.php`).
- **Regression Suite:** **168 Tests Passed | 0 Failed across 12 Suites** (`tests/run_tests.php`).
- **Load Suite:** **12 Assertions Passed across 5 Scenarios** (`tests/load/run_load_tests.php`).
- **Continuous Integration:** Automated GitHub Actions pipeline (`.github/workflows/ci.yml`) running on native Node.js 24 and MariaDB 10.4.
