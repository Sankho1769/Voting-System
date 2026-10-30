# VerityVote — Automated & Integration Testing Strategy

## 1. Overview
The VerityVote verification framework ensures 100% confidence across application logic, security gates, concurrency protection, and database integrity.

The test suite runs directly from the command line using the local PHP CLI:
```bash
php tests/run_tests.php
```

---

## 2. Test Suites Summary

### 2.1 Unit Tests (`tests/Unit/HelperTest.php`)
- **Sanitizer XSS Defense**: Verifies script tags are properly HTML-escaped.
- **Sanitizer Identifier Normalization**: Verifies roll numbers and employee IDs preserve dashes, slashes, and uppercase formatting.
- **Validator Identifier Rules**: Rejects invalid ID characters and lengths.
- **Validator Password Complexity**: Enforces minimum 8 characters, letters, and numbers.
- **Validator Election Date Ordering**: Enforces closing datetime strictly after opening datetime.
- **CSV Formula Injection Mitigation**: Verifies `=`, `+`, `-`, `@` leading characters are neutralized with a leading quote.

### 2.2 Authentication & Session Tests (`tests/Integration/AuthIntegrationTest.php`)
- **Provisioning Flow**: Verifies temporary password generation and 24h expiration timestamp.
- **Wrong Password Defense**: Verifies generic authentication failure without disclosing user existence.
- **Rate-Limit Throttling**: Verifies account lockout after 5 consecutive failed login attempts.
- **First-Login Password Policy**: Verifies user is flagged with `must_change_password = 1`.
- **Mandatory Password Change**: Verifies user can set personal password and clears change flag.
- **Session Revocation**: Verifies account suspension immediately revokes authentication.

### 2.3 Election Lifecycle & Lock Tests (`tests/Integration/ElectionLifecycleTest.php`)
- **Draft Creation**: Validates initial draft state.
- **Candidate Gate**: Rejects transition to `Open` when fewer than 2 approved candidates exist.
- **Opening Transition**: Transitions to `Open` once candidates are verified.
- **Ballot Lock Enforcement**: Rejects adding candidates once voting is open.
- **Configuration Lock**: Rejects changing election title or schedule once voting is open.
- **Closure & Results Publication**: Tests transition from `Open` &rarr; `Closed` &rarr; `Results Published`.

### 2.4 Voting Engine & Concurrency Tests (`tests/Integration/VotingConcurrencyTest.php`)
- **Atomic Ballot Commitment**: Eligible user casts ballot and receives HMAC-SHA256 receipt.
- **Double-Voting Defense**: Rejects duplicate submission by same user with clear error.
- **Ineligible User Rejection**: Rejects user not enrolled on voter whitelist.
- **Cross-Election Candidate Defense**: Rejects candidate ID from a different election.
- **Ballot Secrecy Verification**: Proves by schema inspection that `votes` table has NO `user_id` column.
- **Ledger Consistency**: Verifies count of valid votes in ballot box matches count of participation receipts.
- **Tie Detection**: Correctly identifies 1-1 ties between contenders.
- **Zero-Vote Defense**: Ensures percentage calculation does not trigger division-by-zero errors when total ballots = 0.
- **25 Concurrent Connections for Same Voter**: Verifies that 25 parallel processes for the same voter produce exactly 1 accepted vote and 24 rejections.
- **10 Distinct Concurrent Voters**: Verifies that 10 different voters submitting simultaneously all succeed with zero data loss or deadlocks.

### 2.5 Three Portals & Role Boundary Tests (`tests/Integration/ThreePortalsAuthTest.php`)
- **Admin Portal Login**: Administrator account successfully authenticates at `/login/admin`.
- **Candidate Portal Login**: Candidate account successfully authenticates at `/login/candidate`.
- **Candidate Code Sign-In**: Candidate can sign in using assigned candidate code (`CAND-01`).
- **Voter Portal Login**: Student voter authenticates at `/login/voter`.
- **Admin Mismatch Rejection**: Administrator attempting `/login/voter` is rejected with role mismatch error.
- **Voter Mismatch Rejection**: Student attempting `/login/admin` is rejected with role mismatch error.
- **Candidate Mismatch Rejection**: Non-candidate attempting `/login/candidate` is rejected with role mismatch error.
- **Suspended Account Defense**: Suspended accounts are rejected across all three portals.
- **Dual Candidate-Voter Eligibility**: Candidate who is an eligible voter successfully casts a ballot in their eligible election.
- **Dual Candidate-Voter Double Voting Rejection**: Duplicate ballot by candidate-voter is rejected.
- **Cross-Role URL Escalation**: Candidate accessing voter endpoints without permission is blocked.
- **Session Security**: Logout explicitly invalidates all authentication session tokens.

### 2.6 Real-World HTTP & Browser Journey Tests (`tests/Integration/HttpJourneyTest.php`)
- **Directory Protection**: Direct HTTP access to `/app/`, `/config/`, `/database/`, `/storage/logs/`, `/bin/` is denied (HTTP 403).
- **Public Routing**: Root request `/` properly redirects to `/public/`.
- **Portal Selection Hub**: GET `/public/login` renders three-portal selection interface.
- **Individual Portal Routes**: GET `/public/login/admin`, `/public/login/candidate`, and `/public/login/voter` render dedicated login interfaces with CSRF tokens.
- **Password Toggle UI**: Login forms render accessible show/hide password buttons.
- **Unauthenticated Protection**: Unauthenticated requests to `/public/admin` redirect to login.
- **Forced Password Reset**: First-login with temporary password redirects to `/change-password` and blocks navigation until updated.
- **Admin Journey**: Admin dashboard, account provisioning, election creation, and report export pages render successfully (HTTP 200).
- **Student RBAC Boundary**: Authenticated student navigating to `/admin` receives HTTP 403 Forbidden.
- **Candidate Control Center**: Candidate dashboard renders candidate profile, status badges, and opponents directory.
- **Candidate RBAC Boundary**: Authenticated candidate attempting `/admin` receives HTTP 403 Forbidden.
- **Live Results Polling API**: JSON endpoint `/public/api/live_results.php` returns structured election turnout data.

---

## 3. Running the Verification Suite
Execute the test runner:
```bash
php tests/run_tests.php
```
**Expected Output:**
```
========================================================
 VerityVote - Complete Automated Test Suite
========================================================

--- [ Helper & Security Unit Tests ] ---
  [PASS] Sanitizer escapes XSS tags
  [PASS] Sanitizer normalizes institutional ID
  [PASS] Validator accepts valid institutional IDs
  [PASS] Validator rejects invalid institutional IDs
  [PASS] Validator accepts strong password
  [PASS] Validator rejects weak password without digits
  [PASS] Validator rejects too short password
  [PASS] Validator enforces closing time after opening time
  [PASS] CSVExporter neutralizes spreadsheet formula injection prefixes (=, +, -, @)

--- [ Authentication & Session Integration Tests ] ---
  [PASS] AccountService creates student with secure temporary password
  [PASS] AuthService rejects incorrect password with generic error
  [PASS] AuthService throttles after 5 failed login attempts
  [PASS] AuthService authenticates with valid temporary password and flags mandatory change
  [PASS] AuthService successfully changes password and clears mandatory change flag
  [PASS] User must_change_password becomes 0 in DB
  [PASS] User successfully signs in with new permanent password
  [PASS] Suspended account is immediately rejected on authentication

--- [ Election Lifecycle & Lock Tests ] ---
  [PASS] ElectionService initializes election in draft state
  [PASS] ElectionService rejects opening an election with fewer than 2 approved candidates
  [PASS] ElectionService rejects opening with only 1 approved candidate
  [PASS] CandidateService successfully registers 2 approved candidates
  [PASS] Election successfully transitions from Draft to Open
  [PASS] CandidateService enforces ballot lock and rejects candidate addition once voting is open
  [PASS] ElectionService enforces lock on election configuration once voting is open
  [PASS] Election transitions from Open to Closed
  [PASS] Election transitions from Closed to Results Published

--- [ Voting Engine & Concurrency Tests ] ---
  [PASS] Eligible voter successfully casts ballot and receives cryptographic receipt
  [PASS] Double-voting attempt by same user is rejected with error
  [PASS] Ineligible voter is rejected from casting ballot
  [PASS] Ballot for candidate contesting a different election is rejected
  [PASS] Second eligible voter casts valid ballot
  [PASS] Ballot Privacy: votes table has no user_id column (Participation separated from choice)
  [PASS] Consistency: Total valid ballots in box exactly matches participation receipts count
  [PASS] Results Engine detects tie when vote counts are equal
  [PASS] Zero-vote handling computes 0.0% without division-by-zero error
  [PASS] 25 Concurrent Connections for Same Voter: Exactly 1 accepted (1) and exactly 24 rejected (24)
  [PASS] 25 Concurrent Submissions: Database stores exactly 1 participation record and 1 ballot in votes table
  [PASS] 10 Distinct Eligible Voters Voting Simultaneously: All 10 succeed (10/10, failures: 0)
  [PASS] Multi-Voter Concurrency: Total votes and participation records both equal exactly 10 without race-condition data loss

--- [ Three Portals & Role Boundary Integration Tests ] ---
  [PASS] Admin Login: Administrator successfully authenticates via Admin Portal (/login/admin)
  [PASS] Candidate Login: Candidate successfully authenticates via Candidate Portal (/login/candidate)
  [PASS] Candidate Login: Candidate can authenticate using official candidate code (e.g. CAND-01)
  [PASS] Voter Login: Student successfully authenticates via Voter Portal (/login/voter)
  [PASS] Role Mismatch Defense: Admin credentials rejected on Voter Portal
  [PASS] Role Mismatch Defense: Student credentials rejected on Admin Portal
  [PASS] Role Mismatch Defense: Non-candidate student credentials rejected on Candidate Portal
  [PASS] Suspended Account Defense: Suspended candidate rejected at Candidate Portal
  [PASS] Suspended Account Defense: Suspended voter rejected at Voter Portal
  [PASS] Dual Permission: Candidate who is an eligible voter can cast a valid ballot in an eligible election
  [PASS] Dual Permission: Candidate-voter cannot double-vote in the same election
  [PASS] Candidate Isolation: Candidate cannot access voter ballots for elections where not whitelisted
  [PASS] Session Security: Logout completely terminates session and removes credentials

--- [ HTTP & Browser Real-World Journey Tests ] ---
  [PASS] HTTP Directory Defense: Direct access to /app/bootstrap.php is rejected (HTTP 403)
  [PASS] HTTP Directory Defense: Direct access to /config/config.php is rejected (HTTP 403)
  [PASS] HTTP Directory Defense: Direct access to /database/schema.sql is rejected (HTTP 403)
  [PASS] HTTP Directory Defense: Direct access to /storage/logs/app.log is rejected (HTTP 403)
  [PASS] HTTP Directory Defense: Direct access to /bin/check_db.php is rejected (HTTP 403)
  [PASS] HTTP Root Redirect: Requesting / directs user into public/ front controller
  [PASS] HTTP Three Portals Hub: Accessible via GET /public/login rendering Admin, Candidate, and Voter cards
  [PASS] HTTP Admin Portal UI: Accessible via GET /public/login/admin with CSRF and Admin ID prompt
  [PASS] HTTP Candidate Portal UI: Accessible via GET /public/login/candidate with Candidate ID / Roll Number prompt
  [PASS] HTTP Voter Portal UI: Accessible via GET /public/login/voter with Voter Roll Number prompt
  [PASS] HTTP Password Toggle: Accessible Show/Hide password toggle present on portal login forms
  [PASS] HTTP Route Defense: Unauthenticated request to /public/admin is redirected to /login
  [PASS] HTTP Route Defense: Unauthenticated request to /public/candidate is redirected to /login
  [PASS] HTTP Auth: Valid login with temporary credentials succeeds and redirects to /change-password
  [PASS] HTTP Auth: /change-password renders mandatory password reset interface
  [PASS] HTTP Auth Enforcement: Attempting to bypass password reset redirects back to /change-password
  [PASS] HTTP Auth: Password update succeeds and redirects to authorized /admin dashboard
  [PASS] HTTP Portal: Admin dashboard renders with HTTP 200 OK post password change
  [PASS] HTTP Portal: Admin user provisioning surface renders with HTTP 200 OK
  [PASS] HTTP Portal: Admin election management surface renders with HTTP 200 OK
  [PASS] HTTP Portal: Admin reports and audit logs render with HTTP 200 OK
  [PASS] HTTP RBAC Boundary: Authenticated student session attempting /admin is denied with 403 Forbidden
  [PASS] HTTP Candidate Portal: Authenticated candidate session accessing /candidate renders Control Center (HTTP 200 OK)
  [PASS] HTTP RBAC Boundary: Authenticated candidate session attempting /admin is denied with 403 Forbidden
  [PASS] HTTP Portal: Authenticated student session accessing /dashboard renders successfully (HTTP 200 OK)
  [PASS] HTTP Live Results API: /public/api/live_results.php returns valid JSON payload with status and candidate data

========================================================
Test Execution Summary: 78 Passed | 0 Failed
========================================================
```
