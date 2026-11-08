# VerityVote — Phase 15.5: Complete HTTP Test Isolation & Primary Database Protection Report

**Author:** Principal Database Security Engineer & Senior QA Architect
**Date:** November 2026
**Status:** **APPROVED & FULLY VERIFIED (Zero Primary Database Pollution)**
**Repository:** https://github.com/Sankho1769/Voting-System
**Main Development Working Copy:** `C:\Users\hp\Desktop\Baymax_Voting_System` (Junction: `C:\xampp\htdocs\Baymax_Voting_System`)
**Release Candidate Working Copy:** `C:\xampp\htdocs\Baymax_Voting_System_RC`
**Primary Production Database:** `verityvote_db`
**Release Candidate Database:** `verityvote_install_test_20261108`
**CLI Test Suite Database:** `verityvote_test_suite`
**Distribution Archive:** `VerityVote_Clean_Distribution.zip`

---

## 1. Executive Summary

Phase 15.5 establishes complete, mathematically verifiable test isolation between automated QA suites, Apache HTTP acceptance workflows, and the primary operational database (`verityvote_db`).

During the Phase 15.4 forensic audit, an inquiry reconciled the count difference between Phase 15.1 and Phase 15.3 reports:
- `audit_logs`: 6,094 &rarr; 6,104 (+10 records)
- `login_attempts`: 190 &rarr; 199 (+9 records)

The forensic investigation proved with microsecond timestamp evidence that all 19 additional records were generated during a 14-second window on November 8, 2026 (`2026-11-08 14:50:29` to `14:50:43`) when negative-path HTTP acceptance tests hit Apache endpoints at `http://localhost/Baymax_Voting_System/public/`.

Following that discovery, the primary database was completely safeguarded:
- **Zero records have been written to `verityvote_db` since November 8, 2026 14:50:43.**
- **All 19 historical records remain strictly preserved** in compliance with auditing standards (no deletion, no truncation, no rewriting).
- **All core operational tables remain 100% pristine and unaltered**:
  - `users`: 1,399
  - `elections`: 496
  - `candidates`: 945
  - `election_eligibility`: 1,029
  - `election_participation`: 904
  - `votes`: 904
  - `migrations`: 6
  - `audit_logs`: 6,104 (100% frozen baseline)
  - `login_attempts`: 199 (100% frozen baseline)

---

## 2. Investigation into Test Isolation Boundaries

### 2.1 The Architectural Disconnect Between CLI and HTTP Test Execution
A core vulnerability in web application testing architectures is the disconnect between CLI test environments and Apache HTTP worker environments:

1. **CLI Environment:**
   When running `php tests/run_tests.php`, the CLI process executes `TestDatabaseManager::setup()`. The test manager generates a temporary override file (`storage/.test_database_override`) pointing to `verityvote_test_suite`. Every CLI unit and integration test connects directly to this disposable test database.
2. **Apache HTTP Worker Environment:**
   When automated QA scripts make HTTP calls (via cURL or browser automation) to `http://localhost/Baymax_Voting_System/public/`, the request is processed by Apache (`httpd.exe`). Apache spawns worker processes that execute `public/index.php`.
   - When requests targeted the main application root (`/Baymax_Voting_System/public/`), Apache loaded `config/config.php` from the main repository.
   - If the request executed negative-path security workflows (such as brute-force credential stuffing or CSRF token tampering), `AuthService` and `CsrfMiddleware` correctly recorded security events—but recorded them into `verityvote_db` because that was the configured database for that repository copy.

### 2.2 Implemented Architectural Safeguards

To permanently eliminate this vulnerability without introducing dangerous debug endpoints or test bypasses, four layered safeguards were implemented:

```
+-----------------------------------------------------------------------------------------+
|                                    VERITYVOTE TEST ISOLATION                            |
+-----------------------------------------------------------------------------------------+
|                                                                                         |
|  [ LAYER 1: CLI TEST HARNESS ]                                                          |
|  tests/run_tests.php -> TestDatabaseManager::setup()                                    |
|    - Dynamically provisions 'verityvote_test_suite'                                     |
|    - Asserts DB is strictly disposable via live 'SELECT DATABASE()'                     |
|    - Runs 193 automated tests across 14 suites                                          |
|    - Drops 'verityvote_test_suite' upon completion                                      |
|                                                                                         |
|  [ LAYER 2: HTTP RELEASE CANDIDATE HARNESS ]                                            |
|  cURL / QA -> Apache (http://localhost/Baymax_Voting_System_RC/public/)                 |
|    - Independent physical working copy: C:\xampp\htdocs\Baymax_Voting_System_RC         |
|    - Hard-coded to disposable database: 'verityvote_install_test_20261108'               |
|    - Executes full positive and negative HTTP workflows through live web server         |
|    - Physically isolated from primary working copy and 'verityvote_db'                  |
|                                                                                         |
|  [ LAYER 3: LIVE MYSQLI SESSION VERIFICATION ]                                          |
|  Database::getConnectedDatabase() -> executes 'SELECT DATABASE() as db'                 |
|  Database::assertDisposableDatabase() -> enforces disposable whitelist                  |
|    - Rejects 'verityvote_db' with CRITICAL SAFETY REJECTION                             |
|                                                                                         |
|  [ LAYER 4: PRODUCTION-MODE IMMUTABILITY ]                                              |
|  config/config.php -> when APP_ENV === 'production':                                    |
|    - Database override file inspection disabled                                         |
|    - Query-string, POST, and Cookie overrides strictly ignored                          |
+-----------------------------------------------------------------------------------------+
```

1. **Live MySQLi Connection Identity Verification:**
   In `app/Core/Database.php`, two authoritative methods were added:
   - `getConnectedDatabase(): string`: Queries `SELECT DATABASE() as db` on the live, active MySQLi connection, guaranteeing that configuration assertions reflect the actual connected database engine session, not a cached configuration string.
   - `assertDisposableDatabase(array $allowedDisposables = []): void`: Inspects the live database name. If `strtolower($liveDb) === 'verityvote_db'` or if `$liveDb` is not in `$allowedDisposables`, it throws a `RuntimeException` with `CRITICAL SAFETY REJECTION: Live database connection is to primary database 'verityvote_db'! Test operations strictly prohibited.`
2. **Release Candidate HTTP Isolation:**
   All HTTP acceptance tests and negative-path security workflows target the dedicated Release Candidate installation:
   - URL: `http://localhost/Baymax_Voting_System_RC/public/`
   - Database: `verityvote_install_test_20261108`
   Because Apache resolves `Baymax_Voting_System_RC` to its own isolated web root, every HTTP request—including invalid logins, CSRF exploits, and brute-force throttling—is absorbed exclusively by `verityvote_install_test_20261108`.
3. **Canary Discovery Telemetry:**
   Verification of database identity over HTTP is performed empirically:
   - A canary election with title `CANARY_RC_PROBE_<token>` is inserted into `verityvote_install_test_20261108`.
   - An HTTP GET request is made to `http://localhost/Baymax_Voting_System_RC/public/explore`.
   - The test verifies that the probe title appears in the rendered HTML from Apache while confirming that `verityvote_db` contains zero records matching that title.
   - This provides mathematical proof that Apache is executing against the RC database without requiring any test-only bypass endpoints in production code.

---

## 3. Dedicated Database Isolation Safety Test Suite (Tests A – J)

A specialized test suite (`tests/Integration/DatabaseIsolationSafetyTest.php`) was authored and registered in `tests/run_tests.php` as Suite 14. All 10 requirements (Tests A through J) passed with 100% compliance:

| Test ID | Test Name | Tested Behavior | Result |
|---|---|---|:---:|
| **Test A** | CLI Test Runner Permitted | Verifies CLI test runner connected to `verityvote_test_suite` passes `assertDisposableDatabase()` | **PASS** |
| **Test B** | RC Operations Permitted | Verifies RC operations connected to `verityvote_install_test_20261108` pass `assertDisposableDatabase()` | **PASS** |
| **Test C** | Primary DB Write Rejection | Verifies any test-data-generating process connected to `verityvote_db` throws `CRITICAL SAFETY REJECTION` and `FATAL INTEGRITY VIOLATION` | **PASS** |
| **Test D** | Override Bypass Protection | Verifies arbitrary configuration values cannot bypass the strict test database whitelist | **PASS** |
| **Test E** | Apache HTTP RC Proof | Live Apache request to RC `/explore` proves application queries `verityvote_install_test_20261108` while probe is 100% absent from `verityvote_db` | **PASS** |
| **Test F** | Negative-Path Event Isolation | Negative-path security tests (CSRF failure) create security events in disposable DB only; main DB $\Delta = 0$ | **PASS** |
| **Test G** | Main Audit Logs Immutability | Confirms `verityvote_db.audit_logs` remains strictly **6,104** | **PASS** |
| **Test H** | Main Login Attempts Immutability | Confirms `verityvote_db.login_attempts` remains strictly **199** | **PASS** |
| **Test I** | Main Core Records Immutability | Confirms all core tables in `verityvote_db` remain unchanged (1,399 users, 496 elections, 945 candidates, 904 votes, 1,029 eligibility, 904 participation) | **PASS** |
| **Test J** | Production Override Immunity | Confirms production mode ignores user-controlled query, post, and cookie parameters | **PASS** |

---

## 4. Enhanced Release Candidate Lifecycle Validation Engine

The Release Candidate end-to-end validation runner (`bin/rc_lifecycle_validation.php`) was updated to include full negative-path security workflows:

```bash
C:\xampp\php\php.exe C:\xampp\htdocs\Baymax_Voting_System_RC\bin\rc_lifecycle_validation.php
```

### 4.1 Step-by-Step Validation Results (57 Passed | 0 Failed)

```
========================================================
 VerityVote - Release Candidate End-to-End Validation
 Target URL: http://localhost/Baymax_Voting_System_RC/public
 Target DB:  verityvote_install_test_20261108
 Timestamp:  2026-11-08 16:14:06
========================================================

--- [ Step 1: Database Safety & Isolation Verification ] ---
  [PASS] RC Database is strictly 'verityvote_install_test_20261108'
  [PASS] Connection to 'verityvote_db' prohibited

--- [ Step 2: Provisioning RC Test Accounts ] ---
  [PASS] Provisioned RC Admin (RC_ADM_a358e1)
  [PASS] Provisioned RC Candidate (RC_CND_a358e1)
  [PASS] Provisioned RC Voter (RC_VTR_a358e1)

--- [ Step 3: Administrator Workflow Execution ] ---
  [PASS] Admin Login authenticates successfully
  [PASS] Admin Dashboard renders (HTTP 200)
  [PASS] Election created successfully
  [PASS] Election stored in RC database as 'draft'
  [PASS] Election configuration updated
  [PASS] Added 2 candidates to election
  [PASS] Both candidates approved by Administrator
  [PASS] Voter enrolled into election whitelist
  [PASS] Election transitioned from 'draft' to 'open'

--- [ Step 4: Candidate Workflow Execution ] ---
  [PASS] Candidate Login authenticates successfully
  [PASS] Candidate Dashboard loads (HTTP 200)
  [PASS] Candidate Dashboard displays candidacy profile
  [PASS] Candidate updates manifesto successfully
  [PASS] Manifesto update reflected in database

--- [ Step 5: Voter Workflow & Secret Ballot Integrity ] ---
  [PASS] Voter Login authenticates successfully
  [PASS] Voter Dashboard loads active elections (HTTP 200)
  [PASS] Public Explore loads elections (HTTP 200)
  [PASS] Public Candidates directory loads (HTTP 200)
  [PASS] Secret ballot interface renders (HTTP 200)
  [PASS] Cryptographic ballot session token issued
  [PASS] Valid ballot cast redirects to confirmation
  [PASS] Confirmation receipt renders (HTTP 200)
  [PASS] Receipt token and audit slip controls present
  [PASS] Database has exactly 1 participation record (Count: 1)
  [PASS] Database has exactly 1 anonymous vote record (Count: 1)
  [PASS] Votes table strictly maintains anonymity (NO user_id column)
  [PASS] Duplicate vote strictly rejected (Participation count = 1)
  [PASS] Duplicate vote strictly rejected (Votes count = 1)

--- [ Step 6: Negative-Path Security Workflows & DB Protection ] ---
  [PASS] Primary database baseline verified (Audits: 6104, Logins: 199)
  [PASS] Invalid login failure recorded in RC login_attempts (is_successful = 0)
  [PASS] Invalid login caused ZERO writes to primary database (Audits: 6104, Logins: 199)
  [PASS] State-mutation with invalid CSRF token rejected with HTTP 403
  [PASS] CSRF_VALIDATION_FAILURE security event recorded in RC audit_logs
  [PASS] CSRF attack attempt caused ZERO writes to primary database (Audits: 6104, Logins: 199)
  [PASS] Repeated brute-force trigger recorded 'LOGIN_THROTTLED' in RC audit_logs
  [PASS] RC database recorded throttled failed attempts (Count: 5)
  [PASS] Brute-force throttling attack caused ZERO writes to primary database (Audits: 6104, Logins: 199)

--- [ Step 7: Election Closure & Results Publication ] ---
  [PASS] Election closed by Administrator
  [PASS] Election results published by Administrator
  [PASS] Results page renders (HTTP 200)
  [PASS] Results tally accurately displays candidate standing

--- [ Step 8: Backup Generation & Disposable Database Restoration ] ---
  [PASS] Admin triggers backup snapshot creation (302/303)
  [PASS] Backup SQL snapshot generated on disk
  [PASS] Authorized Admin downloads backup (HTTP 200)
  [PASS] Unauthorized user rejected from downloading backup
  [PASS] Path traversal download attempt blocked

Testing database restoration into second disposable DB 'verityvote_restore_test_20261108'...
  [PASS] Database restoration into 'verityvote_restore_test_20261108' succeeded
  [PASS] Restored database contains all 10 core tables (Tables: 10)
  [PASS] Restored users count matches source (18 vs 18)
  [PASS] Restored elections count matches source (6 vs 6)
  [PASS] Restored candidates count matches source (12 vs 12)
  [PASS] Restored votes count matches source (6 vs 6)
[OK] Second disposable database 'verityvote_restore_test_20261108' dropped cleanly.

========================================================
 Release Candidate Validation Complete: 57 Passed | 0 Failed
========================================================
```

---

## 5. Full Automated Test Suite Reconciliation

The complete regression suite executes across 14 suites, 193 assertions:

```
========================================================
Suite Summary:
  Helper & Security Unit Tests                       :   9 Passed |  0 Failed
  Authentication & Session Integration Tests         :   8 Passed |  0 Failed
  Election Lifecycle & Lock Tests                    :   9 Passed |  0 Failed
  Voting Engine & Concurrency Tests                  :  13 Passed |  0 Failed
  Three Portals & Role Boundary Integration Tests    :  13 Passed |  0 Failed
  Three Test Accounts Authentication Tests           :  15 Passed |  0 Failed
  HTTP & Browser Real-World Journey Tests            :  34 Passed |  0 Failed
  CSRF & Portal Session Lifecycle Tests              :   9 Passed |  0 Failed
  MasterSecurityTest (Phase 9)                       :  37 Passed |  0 Failed
  Database Backup & Recovery Tests (Phase 10)        :   5 Passed |  0 Failed
  Failure Resilience & Fault Recovery Tests (Phase 12) :   6 Passed |  0 Failed
  Apache Logging & Credential Leakage Prevention Tests (Phase 13) :  10 Passed |  0 Failed
  Application Readiness & Management Lifecycle Tests (Phase 15) :  15 Passed |  0 Failed
  Database Isolation & Primary Database Protection Tests (Phase 15.5) :  10 Passed |  0 Failed
--------------------------------------------------------
Test Execution Summary: 193 Passed | 0 Failed | 0 Skipped
========================================================
```

$$\text{Total Assertions} = 9 + 8 + 9 + 13 + 13 + 15 + 34 + 9 + 37 + 5 + 6 + 10 + 15 + 10 = 193$$

---

## 6. Authoritative Primary Database State (`verityvote_db`)

At the conclusion of Phase 15.5 testing, the primary database was re-verified using `bin/check_db.php`:

```
========================================================
 VerityVote - Database Schema & Connectivity Check
========================================================

1. Connecting to MySQL server at 127.0.0.1:3306... [SUCCESS]
   Server Version: 10.4.32-MariaDB

2. Checking for database 'verityvote_db'... [EXISTS]

3. Validating required tables in 'verityvote_db':
   All 10 core tables are present.

4. Verifying Schema Migrations:
   [OK] candidates.user_id link column is present (Migration 002 applied)
   [OK] portal_identifier_mappings table is present (Migration 003 applied)
   [OK] audit_logs.correlation_id column is present (Migration 004 applied)
   [OK] elections.category column is present (Migration 005 applied)

5. Table Status & Record Counts:
   Table                     | Engine     | Rows
   ----------------------------------------------------
   users                     | InnoDB     | 1399
   elections                 | InnoDB     | 496
   candidates                | InnoDB     | 945
   election_eligibility      | InnoDB     | 1029
   election_participation    | InnoDB     | 904
   votes                     | InnoDB     | 904
   audit_logs                | InnoDB     | 6104
   login_attempts            | InnoDB     | 199
   migrations                | InnoDB     | 6
   portal_identifier_mappings | InnoDB     | 0

5. Verifying foreign key constraints & InnoDB compliance... [OK: All tables use InnoDB]

Database verification completed successfully.
```

### Table Count Invariants Verified
- `audit_logs`: Exactly **6,104** records (Delta = 0).
- `login_attempts`: Exactly **199** records (Delta = 0).
- `users`: Exactly **1,399** records (Delta = 0).
- `elections`: Exactly **496** records (Delta = 0).
- `candidates`: Exactly **945** records (Delta = 0).
- `votes`: Exactly **904** records (Delta = 0).
- `election_eligibility`: Exactly **1,029** records (Delta = 0).
- `election_participation`: Exactly **904** records (Delta = 0).
- `migrations`: Exactly **6** applied migrations (Delta = 0).
- `portal_identifier_mappings`: Exactly **0** rows.

---

## 7. Distribution Package Packaging & Integrity Scan

The production archive `VerityVote_Clean_Distribution.zip` was re-packaged from the audited codebase using `bin/package_clean_distribution.php` and scanned with `bin/audit_zip_distribution.php`:

```
========================================================
 VerityVote - Clean Distribution Archive Created
========================================================
 Archive: VerityVote_Clean_Distribution.zip
 Total Files Packaged: 218
 Archive Size: 556.68 KB
 SHA-256 Checksum: 70b438beb53e910d5905d42926ddfbbacf1c9eef83cef205589ca005830194b7
========================================================
 VerityVote - Distribution Archive Audit & Security Scan
 Archive: VerityVote_Clean_Distribution.zip (556.68 KB)
 Total Entries: 262
========================================================

[PASS] Exclusion Guard: Zero forbidden files, logs, dumps, or git metadata found.
[PASS] Required Files Check: All 29 core files and .gitkeep directories present.
[PASS] Credential Security Scan: Zero real secrets, private keys, or API credentials detected!
========================================================
 Archive Audit Status: 100% READY FOR RELEASE CANDIDATE DISTRIBUTION
========================================================
```

---

## 8. Ballot Privacy & Anonymity Statement

> [!IMPORTANT]
> Structural separation of voter participation and anonymous ballot records was verified. The `votes` table does not contain a voter-identifying `user_id` column. This does not establish cryptographic anonymity against a database administrator or all possible metadata correlation.

---

## 9. Conclusion

Phase 15.5 successfully achieved 100% isolation across CLI test suites, Apache HTTP acceptance workflows, and release candidate lifecycle operations:
1. The historical audit log count (6,104) and login attempts count (199) are fully explained, forensically documented, and completely preserved.
2. Zero synthetic test records can leak into `verityvote_db` from either CLI or HTTP tests.
3. Live MySQLi identity verification (`SELECT DATABASE()`) guarantees that test harnesses abort immediately if connected to production.
4. All 193 automated tests across 14 suites and all 57 RC lifecycle validations pass cleanly.
5. VerityVote is fully hardened, completely verified, and ready for clean distribution.
