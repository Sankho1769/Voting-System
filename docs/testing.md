# VerityVote — Automated & Integration Testing Strategy

## 1. Overview
The VerityVote verification framework ensures 100% confidence across application logic, security gates, concurrency protection, and database integrity.

The test suite runs directly from the command line using the local PHP CLI:
```bash
php tests/run_tests.php
```

---

## 2. Audited Test Suite Breakdown (139 Tests Across 9 Suites)

Every test executes in sequence and validates against MySQL/MariaDB and the HTTP server. The complete suite contains exactly **139 passed tests** across 9 specialized suites:

| Suite # | Suite Name | File | Tests | Status |
|---|---|---|:---:|:---:|
| 1 | Helper & Security Unit Tests | `tests/Unit/HelperTest.php` | 9 | PASS |
| 2 | Authentication & Session Integration Tests | `tests/Integration/AuthIntegrationTest.php` | 8 | PASS |
| 3 | Election Lifecycle & Lock Tests | `tests/Integration/ElectionLifecycleTest.php` | 9 | PASS |
| 4 | Voting Engine & Concurrency Tests | `tests/Integration/VotingConcurrencyTest.php` | 13 | PASS |
| 5 | Three Portals & Role Boundary Integration Tests | `tests/Integration/ThreePortalsAuthTest.php` | 13 | PASS |
| 6 | Three Test Accounts (Shubham) Authentication Tests | `tests/Integration/TestAccountsAuthTest.php` | 15 | PASS |
| 7 | HTTP & Browser Real-World Journey Tests | `tests/Integration/HttpJourneyTest.php` | 26 | PASS |
| 8 | CSRF & Portal Session Lifecycle Tests | `tests/Integration/CsrfPortalLifecycleTest.php` | 9 | PASS |
| 9 | Master Security Hardening & Concurrency Tests | `tests/Integration/MasterSecurityTest.php` | 37 | PASS |
| **TOTAL** | **Complete VerityVote Test Suite** | **9 Suites** | **139** | **139 / 139 PASS (100%)** |

### Mathematical Verification of Test Count
Prior documentation contained an accidental omission where Suite 6 (15 tests) and partial counts from Suite 7 were truncated in log reports, creating a typographical sum of 119 in an earlier status summary. As audited directly from source code and runtime execution:
$$\text{Total} = 9 + 8 + 9 + 13 + 13 + 15 + 26 + 9 + 37 = 139$$
Every single test is accounted for, executes without skipping, and passes 100%.

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

### 3.6 Three Test Accounts (Shubham) Authentication Tests (15 Tests)
- **Admin Portal**: Visible login ID `Shubham` with `Shubham56ty2` signs in to Administrator portal.
- **Candidate Portal**: Visible login ID `Shubham` with `Shubham56ty2` signs in to Candidate portal.
- **Candidate Syntax**: Candidate ID syntax (`CAND-260`) signs in to Candidate portal.
- **Voter Portal**: Visible login ID `Shubham` with `Shubham56ty2` signs in to Voter portal.
- **Password Security**: Incorrect password with `Shubham` rejected across all 3 portals.
- **Bcrypt Hash Verification**: Passwords verified as valid bcrypt `$2y$` hashes.
- **RBAC Boundaries**: Cross-portal mismatch tests for test accounts.
- **Profile Attachment**: Verifies Candidate profile correctly links to `Shubham` candidate account.

### 3.7 HTTP & Browser Real-World Journey Tests (26 Tests)
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
- **Injection & RBAC (Tests 29-34)**: Invalid CSRF rejected (HTTP 403); SQL injection defense via prepared statements; unauthorized result tampering blocked; unauthorized eligibility modification blocked; candidate/voter privilege escalation blocked.
- **Audit Ledger & Secrecy (Tests 35-37)**: Correlation IDs in audit logs; complete ballot secrecy (candidate choices never in audit logs); mathematical verification of results == valid ballots (10 = 10).

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
Test Execution Summary: 139 Passed | 0 Failed
========================================================
```
