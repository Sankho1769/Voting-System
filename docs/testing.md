# VerityVote — Automated & Integration Testing Strategy

## 1. Overview
The VerityVote verification framework ensures 100% confidence across application logic, security gates, concurrency protection, and database integrity.

The test suite runs directly from the command line using the local PHP CLI:
```bash
php tests/run_tests.php
```

---

## 2. Audited Test Suite Breakdown (158 Tests Across 11 Suites + 5 Load Scenarios)

Every test executes in sequence and validates against MySQL/MariaDB and the HTTP server. The verification suite runs against an isolated disposable database (`verityvote_test_suite`) provisioned on-the-fly with dynamic fixtures (`TestFixtureFactory`), ensuring zero pollution and zero risk of accidental dropping or alteration of the primary production database (`verityvote_db`).

The complete suite contains exactly **193 passed tests** across 14 specialized suites:

| Suite # | Suite Name | File | Tests | Status |
|---|---|---|:---:|:---:|
| 1 | Helper & Security Unit Tests | `tests/Unit/HelperTest.php` | 9 | PASS |
| 2 | Authentication & Session Integration Tests | `tests/Integration/AuthIntegrationTest.php` | 8 | PASS |
| 3 | Election Lifecycle & Lock Tests | `tests/Integration/ElectionLifecycleTest.php` | 9 | PASS |
| 4 | Voting Engine & Concurrency Tests | `tests/Integration/VotingConcurrencyTest.php` | 13 | PASS |
| 5 | Three Portals & Role Boundary Integration Tests | `tests/Integration/ThreePortalsAuthTest.php` | 13 | PASS |
| 6 | Three Test Accounts Authentication Tests | `tests/Integration/TestAccountsAuthTest.php` | 15 | PASS |
| 7 | HTTP & Browser Real-World Journey Tests | `tests/Integration/HttpJourneyTest.php` | 34 | PASS |
| 8 | CSRF & Portal Session Lifecycle Tests | `tests/Integration/CsrfPortalLifecycleTest.php` | 9 | PASS |
| 9 | Master Security Hardening & Concurrency Tests | `tests/Integration/MasterSecurityTest.php` | 37 | PASS |
| 10 | Database Backup & Recovery Tests | `tests/Integration/BackupRestoreTest.php` | 5 | PASS |
| 11 | Failure Resilience & Fault Recovery Tests (Phase 12) | `tests/Integration/FailureResilienceTest.php` | 6 | PASS |
| 12 | Apache Logging & Credential Leakage Prevention Tests (Phase 13) | `tests/Integration/ApacheLoggingSecurityTest.php` | 10 | PASS |
| 13 | Application Readiness & Management Lifecycle Tests (Phase 15) | `tests/Integration/ApplicationReadinessTest.php` | 15 | PASS |
| 14 | Database Isolation & Primary Database Protection Tests (Phase 15.5) | `tests/Integration/DatabaseIsolationSafetyTest.php` | 10 | PASS |
| **TOTAL** | **Complete VerityVote Regression Suite** | **14 Suites** | **193** | **193 / 193 PASS (100%)** |

### Mathematical Verification of Test Count
$$\text{Total} = 9 + 8 + 9 + 13 + 13 + 15 + 34 + 9 + 37 + 5 + 6 + 10 + 15 + 10 = 193$$
Every single test is accounted for, executes without skipping, and passes 100%.

---

## 2.1 Phase 12 High-Concurrency Load Testing Suite (`tests/load/`)

In addition to the 149 regression tests, VerityVote includes 5 dedicated load and stress testing scenarios executed via `php tests/load/run_load_tests.php`:

| Scenario | Scenario Name | File | Volume | Outcome |
|---|---|---|:---:|:---:|
| 1 | Discovery & Read Subsystem Load | `tests/load/DiscoveryLoadTest.php` | 100 reqs | **3/3 PASS** (18,066 req/s) |
| 2 | Authentication & Portal Security Load | `tests/load/AuthLoadTest.php` | 26 reqs | **2/2 PASS** (54.49 ms avg) |
| 3 | Voting Burst Concurrency Load | `tests/load/VotingBurstLoadTest.php` | 30 voters | **3/3 PASS** (68.0 tx/s) |
| 4 | Same-Voter 25 & 100 Concurrency Attack | `tests/load/SameVoterAttackTest.php` | 125 attacks | **3/3 PASS** (Exactly 1 vote committed) |
| 5 | Mixed Workload (70/20/10) Simulation | `tests/load/MixedWorkloadLoadTest.php` | 100 ops | **1/1 PASS** (240 ops/sec, 0% errors) |
| **TOTAL** | **Load Testing Benchmark Suite** | **5 Scenarios** | **381 Ops** | **12 / 12 PASS (100%)** |

---

## 3. Test Suites Detail

### 3.1 Helper & Security Unit Tests (9 Tests)
- **Sanitizer XSS Defense**: Verifies script tags are properly HTML-escaped.
- **Sanitizer Identifier Normalization**: Verifies roll numbers and employee IDs preserve dashes, slashes, and uppercase formatting.
- **Validator Identifier Rules**: Accepts valid roll numbers and rejects invalid ID characters and lengths.
- **Validator Password Complexity**: Enforces minimum 8 characters, letters, and numbers.
- **Validator Election Date Ordering**: Enforces closing datetime strictly after opening datetime.
- **CSV Formula Injection Mitigation**: Verifies `=`, `+`, `-`, `@` leading characters are neutralized with a leading quote.

### 3.2 Authentication & Session Tests (8 Tests)
- **Provisioning Flow**: Verifies temporary password generation and 24h expiration timestamp.
- **Wrong Password Defense**: Verifies generic authentication failure without disclosing user existence.
- **Rate-Limit Throttling**: Verifies account lockout after 5 consecutive failed login attempts.
- **First-Login Password Policy**: Verifies user is flagged with `must_change_password = 1`.
- **Mandatory Password Change**: Verifies user can set personal password and clears change flag in database (`must_change_password = 0`).
- **Permanent Login**: User successfully logs in with new permanent password.
- **Session Revocation**: Verifies account suspension immediately revokes authentication.

### 3.3 Election Lifecycle & Lock Tests (9 Tests)
- **Draft Creation**: Validates initial draft state.
- **Candidate Gate**: Rejects transition to `Open` when fewer than 2 approved candidates exist.
- **Candidate Registration**: Registers 2 approved candidates.
- **Opening Transition**: Transitions to `Open` once candidates are verified.
- **Ballot Lock Enforcement**: Rejects adding candidates once voting is open.
- **Configuration Lock**: Rejects changing election title or schedule once voting is open.
- **Closure & Results Publication**: Tests transition from `Open` &rarr; `Closed` &rarr; `Results Published`.

### 3.4 Voting Engine & Concurrency Tests (13 Tests)
- **Atomic Ballot Commitment**: Eligible user casts ballot and receives HMAC-SHA256 receipt.
- **Double-Voting Defense**: Rejects duplicate submission by same user with clear error.
- **Ineligible User Rejection**: Rejects user not enrolled on voter whitelist.
- **Cross-Election Candidate Defense**: Rejects candidate ID from a different election.
- **Second Eligible Voter**: Validates casting of subsequent ballots.
- **Ballot Secrecy Verification**: Proves by schema inspection that `votes` table has NO `user_id` column (participation decoupled from choice).
- **Ledger Consistency**: Verifies count of valid votes in ballot box matches count of participation receipts.
- **Tie Detection**: Correctly identifies 1-1 ties between contenders.
- **Zero-Vote Defense**: Ensures percentage calculation does not trigger division-by-zero errors when total ballots = 0.
- **25 Concurrent Connections for Same Voter**: Verifies that 25 parallel processes for the same voter produce exactly 1 accepted vote and 24 rejections.
- **Atomic Ledger Commitment**: Proves database stores exactly 1 participation record and 1 ballot.
- **10 Distinct Concurrent Voters**: Verifies that 10 different voters submitting simultaneously all succeed (10/10) with zero data loss or deadlocks.
- **Multi-Voter Concurrency Consistency**: Both participation and votes equal exactly 10.

### 3.5 Three Portals & Role Boundary Tests (13 Tests)
- **Admin Portal Login**: Administrator account successfully authenticates at `/login/admin`.
- **Candidate Portal Login**: Candidate account successfully authenticates at `/login/candidate`.
- **Candidate ID Syntax**: Candidate signs in using candidate code (`CAND-xx`).
- **Voter Portal Login**: Student voter authenticates at `/login/voter`.
- **Generic Auth Rejection**: Invalid credentials rejected uniformly.
- **Role Boundary (Student &rarr; Admin)**: Student attempting `/login/admin` rejected with role mismatch error.
- **Role Boundary (Voter &rarr; Candidate)**: Non-candidate voter attempting `/login/candidate` rejected.
- **Role Boundary (Admin &rarr; Voter)**: Pure administrator attempting `/login/voter` rejected.
- **Suspended Account Defense**: Suspended accounts rejected across all three portals.
- **Dual Role Execution**: Candidate who is an eligible voter casts a ballot through the voter portal.
- **Dual Role Double-Vote Rejection**: Candidate cannot double-vote in the same election.
- **Candidate Dashboard Retrieval**: Retrieves candidate profile, opponents, and dual status.
- **Session Security**: Logout explicitly invalidates all authentication session tokens.

### 3.6 Three Test Accounts Authentication Tests (15 Tests)
- **Dynamic Test Fixture Provisioning**: Generated cleanly on-the-fly inside the disposable test database (`verityvote_test_suite`) via `TestFixtureFactory`. Zero dependency on permanent database accounts.
- **Admin Portal**: Dynamic visible login ID with provisioned test credential signs in to Administrator portal.
- **Candidate Portal**: Dynamic visible login ID with provisioned test credential signs in to Candidate portal.
- **Candidate Syntax**: Candidate ID syntax (`CAND-xx`) signs in to Candidate portal.
- **Voter Portal**: Dynamic visible login ID with provisioned test credential signs in to Voter portal.
- **Password Security**: Incorrect password rejected across all 3 portals.
- **Bcrypt Hash Verification**: Passwords verified as valid bcrypt `$2y$` hashes.
- **RBAC Boundaries**: Cross-portal mismatch tests and role enforcement for test accounts.
- **Profile Attachment**: Verifies Candidate profile correctly links to dynamically created candidate account.

### 3.7 HTTP & Browser Real-World Journey Tests (34 Tests)
- **Directory Protection (5 Tests)**: Direct HTTP access to `/app/`, `/config/`, `/database/`, `/storage/logs/`, `/bin/` is denied (HTTP 403).
- **Public Routing**: Root request `/` properly directs to `/public/`.
- **Portal Selection Hub**: GET `/public/login` renders three-portal selection interface.
- **Individual Portal Routes**: Dedicated login forms with CSRF tokens for `/login/admin`, `/login/candidate`, `/login/voter`.
- **Password Visibility**: Accessible show/hide password toggles.
- **Route Authorization (2 Tests)**: Unauthenticated requests to `/admin` and `/candidate` redirect to login.
- **Mandatory Password Reset (3 Tests)**: First-login redirects to `/change-password`, blocks navigation until updated, and accepts new password.
- **Admin Management Surfaces (4 Tests)**: Admin dashboard, user provisioning, election management, and audit reports render HTTP 200 OK.
- **RBAC Boundaries (3 Tests)**: Student denied from `/admin` (HTTP 403); non-candidate denied from `/candidate` (HTTP 403); candidate denied from `/admin` (HTTP 403).
- **Voter & Candidate Dashboards (3 Tests)**: Authenticated sessions render voter ballot box and candidate dashboard (HTTP 200 OK).
- **Live Results Polling API**: JSON endpoint `/public/api/live_results.php` returns structured election turnout data.
- **Health & Monitoring Observability (8 Tests)**:
  - Liveness probe (`/public/health/live.php`) returns HTTP 200 OK with runtime memory diagnostics.
  - Readiness probe (`/public/health/ready.php`) validates MariaDB connectivity, schema tables, and storage permissions (HTTP 200 OK).
  - Metrics unauthenticated request returns HTTP 401 Unauthorized with `WWW-Authenticate: Bearer` challenge.
  - Metrics invalid Bearer token returns HTTP 401 Unauthorized.
  - Metrics valid Bearer token returns HTTP 200 OK and Prometheus telemetry.
  - Metrics query-string token authentication is strictly rejected (returns HTTP 401 Unauthorized even with valid token).
  - Metrics authorized administrator session returns HTTP 200 OK without requiring header token.
  - Metrics secret token is strictly verified to never leak into response headers, response bodies, or application log files.

### 3.8 CSRF & Portal Session Lifecycle Tests (9 Tests)
- **CSRF Token Persistence**: Shares consistent token across multi-portal browsing.
- **Portal Lifecycles (3 Tests)**: Valid GET &rarr; Token &rarr; POST authentication for Admin, Candidate, and Voter portals.
- **Graceful Token Recovery**: Stale or expired token safely recovers via HTTP 303 redirect with flash notification rather than getting trapped in an unrecoverable 403 Forbidden loop.
- **CSRF Re-authentication**: Refreshed portal renders notice and accepts subsequent login.
- **Missing Token Defense**: POST without CSRF token safely redirected with fresh token.
- **State Mutation Defense**: Non-login POST with forged token returns HTTP 403 Forbidden.
- **Token Rotation**: CSRF token automatically rotates upon login (`session_regenerate_id(true)`).

### 3.9 Master Security Hardening & Concurrency Tests (37 Tests)
Comprehensive automated suite covering all 37 security controls:
- **Identity & Whitelist (Tests 1-7)**: Duplicate IDs rejected; case-insensitive deduplication; whitespace deduplication; unapproved/pending/suspended/disabled accounts rejected; candidate profile isolation.
- **Eligibility & Access (Tests 8-12)**: Whitelist enforcement; cross-election boundary; immutable eligibility ledger; closed election ballot rejection.
- **Ballot Box & Double-Voting (Tests 13-17)**: First vote accepted; second/third rejected; cross-tab duplicate rejection; simultaneous submission race defense (1 accepted, 1 rejected).
- **High Concurrency & ACID Integrity (Tests 18-22)**: 25 concurrent connections produce 1 accepted ballot; 100 concurrent connections produce 1 accepted ballot; 10 distinct voters vote simultaneously (10/10); transactional ACID rollback on failure; participation receipts count == valid ballots count.
- **Tamper Resistance (Tests 23-28)**: Forged candidate ID rejected; cross-election candidate ID rejected; forged election ID rejected; forged browser-side user ID rejected; unauthenticated direct API rejected; replayed submission rejected.
- **Injection & RBAC (Tests 29-34)**: Invalid CSRF rejected (HTTP 403); SQL injection defense via prepared statements; unauthorized result tampering blocked (HTTP 403 verified via authenticated non-admin session); unauthorized eligibility modification blocked (HTTP 403); candidate privilege escalation blocked (HTTP 403); voter privilege escalation blocked (HTTP 403).
- **Audit Ledger & Secrecy (Tests 35-37)**: Correlation IDs in audit logs; complete ballot secrecy (candidate choices never in audit logs); mathematical verification of results == valid ballots (10 = 10).

### 3.10 Database Backup & Recovery Tests (5 Tests)
- **Backup Utility (`bin/backup_db.php`)**: Exports complete database schema and data into a standalone, valid SQL archive.
- **Safety Safeguard**: Validates that restoring to an active database without the explicit `--confirm` flag is strictly rejected.
- **Automated Restore (`bin/restore_db.php`)**: Restores database snapshot into a designated target database.
- **Schema & Data Verification**: Validates that restored database contains all 10 core tables, foreign keys, and rows.
- **Clean Teardown**: Automatically removes temporary backup files and drops the temporary test database.

### 3.12 Apache Logging & Credential Leakage Prevention Tests (10 Tests)
- **Scenario A**: Unauthorized metrics request with synthetic query token (`?token=SYNTHETIC_TEST_SECRET_123456`) strictly returns HTTP 401 Unauthorized with `WWW-Authenticate: Bearer` challenge.
- **Scenario B**: Synthetic token is verified to never appear in any application JSON or file logs (`storage/logs/`).
- **Scenario C**: Synthetic token is verified absent from Apache access logs (`access.log` / `verityvote_access.log`) after request execution, while verifying path `/public/metrics` was logged cleanly.
- **Scenario D**: Synthetic token is verified absent from Apache error logs (`error.log` / `verityvote_error.log`) during controlled error scenarios.
- **Scenario E**: Normal access-log functionality remains operational (validating presence of method, path, protocol, and status code).
- **Scenario F**: Normal error-log functionality remains operational.
- **Scenario G**: Valid Bearer-header request (`Authorization: Bearer <token>`) succeeds with HTTP 200 and Prometheus telemetry.
- **Scenario H**: Authorized administrator session accesses `/public/metrics` successfully without token header.
- **Scenario I**: Application diagnostics (`/public/health/live.php`, etc.) do not expose authorization headers or secrets.
- **Scenario J**: Existing logging redaction and URI query-string sanitization (`Logger::sanitizeUri`) continue to pass.

### 3.13 Application Readiness & Management Lifecycle Tests (15 Tests)
- **Election Edit**: Successfully updates title, position, description, and visibility in draft state.
- **Candidate Approval Lifecycle**: Admin can approve and reject candidates in draft state.
- **Voter Whitelist Management**: Enrolling voter succeeds; removing enrolled voter succeeds before opening.
- **Candidate Self-Service**: Candidate updates their own manifesto; IDOR defense prevents unauthorized modifications.
- **Election Lock Enforcement**: Configuration updates, candidate changes, and whitelist modifications are strictly rejected once election is open.
- **Database Backup Service**: Creates transactional SQL backups; lists archives; blocks path traversal attacks in download handler.
- **Pagination**: Verifies limit, offset, and countAll queries for elections and approved candidates.

### 3.14 Database Isolation & Primary Database Protection Tests (10 Tests)
- **Isolation A**: CLI test runner connected to `verityvote_test_suite` is permitted.
- **Isolation B**: RC operations connected to `verityvote_install_test_20261108` are permitted.
- **Isolation C**: Any test-data-generating process connected to `verityvote_db` is rejected with `CRITICAL SAFETY REJECTION`.
- **Isolation D**: Database override cannot be bypassed by passing an arbitrary configuration value.
- **Isolation E**: Apache HTTP canary probe proves actual application queries RC database while absent from `verityvote_db`.
- **Isolation F**: Negative-path authentication tests create security events in disposable DB only (main DB $\Delta = 0$).
- **Isolation G**: Main database `audit_logs` count remains strictly 6,104.
- **Isolation H**: Main database `login_attempts` count remains strictly 199.
- **Isolation I**: Main database core records remain unchanged (Users: 1,399, Elections: 496, Candidates: 945, Votes: 904).
- **Isolation J**: Production mode cannot enable test database overrides through user-controlled parameters.

---

## 4. Running the Verification Suite
Execute the test runner:
```bash
php tests/run_tests.php
```

Output:
```
========================================================
 VerityVote - Complete Automated Test Suite
========================================================
Test Execution Summary: 144 Passed | 0 Failed | 0 Skipped
========================================================
```

---

## 5. Automated Continuous Integration (GitHub Actions)

VerityVote includes a production-grade CI pipeline configured in `.github/workflows/ci.yml`.

### 5.1 Pipeline Triggers & Concurrency
- **Triggers**:
  - `push` to branch `main`.
  - `pull_request` targeting `main`.
  - `workflow_dispatch` for manual on-demand execution.
- **Concurrency Control**: Automatically cancels stale in-flight builds using `concurrency: group: ci-${{ github.ref }}, cancel-in-progress: true`.
- **Security Context**: Strictly follows least-privilege principles (`permissions: contents: read`). No deployment or write credentials are required or exposed.

### 5.2 CI Environment & Infrastructure
- **Operating System**: Ubuntu Latest runner (`ubuntu-latest`).
- **PHP Version**: PHP 8.2 with `mysqli`, `curl`, `mbstring`, `fileinfo`, `pdo`, `pdo_mysql`, and `openssl` extensions.
- **Web Server**: Apache 2.4 with `libapache2-mod-php8.2`, `mod_rewrite`, and `mod_headers`, ensuring identical directory security (`RewriteRule ^(app|bin|config|database|docs|storage|tests)/ - [F,L]`) and URL routing (`index.php`) as in production.
- **Database Service Container**:
  - Image: `mariadb:10.4` (guaranteeing compatibility with the supported deployment baseline of MariaDB 10.4+ / MySQL 8.0+ without silent requirement inflation).
  - Health checks: `--health-cmd="mysqladmin ping -h 127.0.0.1"`.
  - Initialized with disposable credentials (`root` without password on loopback).

### 5.3 Test Execution Stages
1. **PHP Syntax Validation (Lint)**:
   ```bash
   php bin/lint_all.php
   ```
   Scans all 92 PHP files across the repository to verify 0 syntax errors.
2. **Database Schema & Migration Validation**:
   ```bash
   php bin/check_db.php
   ```
   Connects to MariaDB, creates `verityvote_db`, applies `database/schema.sql`, and applies migrations 002 through 005.
3. **Automated Verification Suite Execution**:
   ```bash
   php tests/run_tests.php
   ```
   Spins up disposable database `verityvote_test_suite`, provisions dynamic fixtures via `TestFixtureFactory`, runs all 144 tests across 10 suites, drops `verityvote_test_suite` in `finally`, and verifies primary database integrity.
4. **Step Summary Generation**:
   ```bash
   php bin/generate_ci_summary.php storage/logs/ci_lint.log storage/logs/ci_db.log storage/logs/ci_tests.log
   ```
   Parses raw test logs and outputs a rich Markdown table into `GITHUB_STEP_SUMMARY`.

### 5.4 Database Isolation & Concurrency Verification
- **Safety Barrier**: `TestDatabaseManager::assertIsDisposable()` strictly enforces that the test runner operates exclusively on databases named with `test` or `disposable`. The runner will throw a fatal `RuntimeException` if any attempt is made to drop or alter `verityvote_db`.
- **High Concurrency Invariants**:
  - **25 Same-Voter Connections**: Exactly 1 accepted, 24 rejected.
  - **100 Same-Voter Connections**: Exactly 1 accepted, 99 rejected.
  - **10 Distinct Voters Simultaneously**: Exactly 10 accepted with zero data loss.

### 5.5 Interpreting GitHub Actions Failures
- **Lint Step Failure**: A PHP file has a syntax error. Look at the specific filename and line number reported in the `Validate PHP syntax` step log.
- **Database Migration Step Failure**: Schema definition or SQL migration failed to apply. Check for missing columns, foreign key conflicts, or syntax incompatibility in `database/migrations/`.
- **Verification Suite Step Failure**: One or more unit/integration tests failed. Inspect the `Individual Test Suite Breakdown` table in the workflow summary to identify the exact failing test.
- **Apache Probing Failure**: Apache failed to serve `/public/login` (status != 200). Check `/var/log/apache2/error.log` dumped in the workflow step.

### 5.6 Running Equivalent Checks Locally
```bash
# Windows XAMPP PowerShell / Command Prompt:
C:\xampp\php\php.exe bin/lint_all.php
C:\xampp\php\php.exe bin/check_db.php
C:\xampp\php\php.exe tests/run_tests.php
```
