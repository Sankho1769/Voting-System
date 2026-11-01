# VerityVote — Application Security & Threat Model

## 1. Security Philosophy
VerityVote is designed under the **Principle of Defense in Depth** referencing the **OWASP Application Security Verification Standard (ASVS v4.0)** level 2 guidelines for academic institutions.

The application recognizes that college environments present unique threat vectors:
- Malicious students attempting SQL injection or credential stuffing.
- Concurrent submission attempts from multiple devices to double-vote.
- Unauthorized elevation of privileges to access administrative endpoints.
- CSV spreadsheet formula injection when export files are opened in MS Excel.
- Malicious candidate photo uploads attempting PHP shell execution.

---

## 2. Implemented Security Controls

### 2.1 SQL Injection Defenses (ASVS V5)
- All dynamic database queries strictly use **MySQLi prepared statements** with parameter binding.
- String concatenation of user input into SQL statements is prohibited across all layers.
- Parameter types are validated and auto-inferred using strictly typed PHP helpers.

### 2.2 Cross-Site Request Forgery (CSRF) Defenses (ASVS V4)
- Cryptographic 256-bit random tokens generated via `random_bytes(32)`.
- Validated on every state-modifying request (`POST`, `PUT`, `DELETE`, `PATCH`).
- Token comparison uses `hash_equals()` to prevent timing attacks.
- Tokens are submitted via hidden form fields (`CSRF::field()`) and verified via `CsrfMiddleware`.

### 2.3 Cross-Site Scripting (XSS) Defenses (ASVS V5)
- Context-aware HTML escaping is applied to all dynamic outputs via `Sanitizer::e()`, which calls `htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- HTTP response headers enforce `X-Content-Type-Options: nosniff` and `X-XSS-Protection: 1; mode=block`.

### 2.4 Session Security & Authentication (ASVS V2, V3)
- Session cookies configured with `HttpOnly = true`, `SameSite = 'Lax'`, and dynamic `Secure` when served over HTTPS.
- Monotonic `auth_version` stored in user records; whenever a password is changed or an account is suspended, `auth_version` is incremented, immediately terminating all existing sessions across all browsers.
- Inactivity idle timeout: 30 minutes.
- Absolute maximum session duration: 4 hours.
- Session ID regenerated on login via `session_regenerate_id(true)` to eliminate session fixation.

### 2.5 Brute-Force & Credential Stuffing Throttling (ASVS V2)
- Server-side tracking in `login_attempts` table.
- Blocks sign-in after 5 consecutive failures for an institutional identifier within a 15-minute sliding window.
- Generic error messages ("Invalid institutional credentials") prevent account enumeration.

### 2.6 Safe File Uploads (ASVS V12)
- Candidate photos uploaded to `/storage/uploads/candidates/`.
- File content validated using PHP Fileinfo extension (`mime_content_type` / `finfo`) to verify authentic MIME type (`image/jpeg`, `image/png`, `image/webp`).
- Verified with `getimagesize()` to confirm readable image headers.
- File size restricted to 2MB maximum.
- Stored under a cryptographically random filename (`cand_<hex>.ext`). Direct PHP script execution is disabled via storage `.htaccess`.

### 2.7 Spreadsheet Formula Injection (CSV DDE) Defense
- When administrators export tallies or users to CSV, all cell values starting with formula trigger characters (`=`, `+`, `-`, `@`, `\t`, `\r`) are prepended with an apostrophe (`'`).
- Prevents remote code execution when opened in Microsoft Excel, LibreOffice Calc, or Google Sheets.

### 2.8 Three-Portal Separation & Role Mismatch Defenses
VerityVote enforces strict architectural boundaries across the three login portals (`/login/admin`, `/login/candidate`, `/login/voter`):
- **Authentication-Level Role Gatekeeping**: `AuthService::login` compares the requested login portal with the user's role and candidate linkage before emitting session tokens.
  - An administrator or election officer attempting to sign in through `/login/voter` is rejected: `"Access denied: Administrator accounts cannot sign in through the Voter portal."`
  - A student or staff member attempting to sign in through `/login/admin` is rejected: `"Access denied: Administrative credentials required."`
  - An account without a candidate role or linked candidate profile attempting `/login/candidate` is rejected: `"Access denied: No candidate profile found for this account."`
- **Route-Level Defense in Depth**:
  - `RoleMiddleware(['admin', 'election_officer'])` intercepts `/admin/*` routes, immediately denying unauthorized users with `403 Forbidden`.
  - `CandidateMiddleware` protects `/candidate`, ensuring only approved candidates can view candidate dashboards.
  - `VoterMiddleware` protects `/voter` and `/dashboard`, ensuring proper voter context.
- **Dual-Identity Authorization Integrity**:
  - When an authorized collegiate student is nominated and approved as a candidate, their candidate profile is linked via `candidates.user_id = users.id`.
  - They authenticate using their single, authentic institutional roll number and personal password across both portals.
  - Their voting privileges in eligible races remain strictly protected and cannot alter or compromise other candidates' tallies.

### 2.9 Master Security Architecture & Fraud Prevention (Migration 004)
To prevent fraudulent voter generation, duplicate voting, ballot manipulation, and administrative tampering, VerityVote implements the following hardened controls:

#### 1. Official Institutional Identity Lifecycle
- **Five Lifecycle States**: `users.status` supports `pending`, `active`, `suspended`, `disabled`, and `deactivated`.
  - `pending`: Unapproved accounts (e.g. self-registration or pre-vetted imports pending administrative sign-off) are barred from logging in or voting (`"Your account registration is pending approval."`).
  - `active`: Authorized, fully functional collegiate account.
  - `suspended`: Temporarily suspended accounts are immediately rejected at authentication and ballot casting (`"Your account is suspended."`).
  - `disabled`: Administrative permanent hold (`"Your account has been disabled."`).
  - `deactivated`: Departed students or former staff.
- **Controlled CSV Bulk Provisioning Pipeline**:
  - Uploaded files are parsed, required columns (`institutional_id`, `full_name`, `account_category`) validated, and identities normalized via `Sanitizer::cleanIdentifier()`.
  - **In-File Duplicate Detection**: Case-insensitive and whitespace-normalized checks flag internal duplicates within the upload.
  - **Database Duplicate Detection**: Checks against existing `users.identifier` and `portal_identifier_mappings`.
  - **Pre-Commit Preview**: Displays metric cards (Total, Accepted, Duplicate in File, Duplicate in Database, Invalid Format), preview tables, and downloadable non-sensitive error CSV report.
  - **Cryptographic Preview Token**: Prevents unauthorized commits; requires explicit administrator confirmation before provisioning accounts.
  - Generates secure temporary passwords and logs `CSV_IMPORT_COMMITTED` audit records.

#### 2. Ten Atomic Pre-Conditions Enforced for Ballot Submission
When a voter casts a ballot, `VotingRepository::recordBallot` strictly enforces 10 server-side pre-conditions inside an atomic ACID transaction:
1. **Election Existence & Status**: Verifies election exists and `status === 'open'`.
2. **Election Timing**: Verifies server clock satisfies `opens_at <= NOW() < closes_at`.
3. **Candidate Existence & Scope**: Candidate must exist in database.
4. **Candidate Contest Boundary**: Candidate must belong to this specific election (`election_id = ?`).
5. **Candidate Approval**: Candidate `approval_status` must be `'approved'`.
6. **Voter Account State**: Voter `status` must be `'active'` (rejects `pending`, `suspended`, `disabled`, `deactivated`).
7. **Voter Role Authorization**: Voter role must be `student`, `staff`, or `candidate` (rejects pure administrators).
8. **Permanent Credential Enforcement**: Voter `must_change_password` must be `0` (forces update of temporary passwords).
9. **Eligibility Whitelist Check**: Voter row in `election_eligibility` must exist (locked `FOR UPDATE` to serialize concurrent requests).
10. **Duplicate Participation Check**: User must NOT exist in `election_participation` for this election.

#### 3. Replay & Double-Click Mitigation
- In addition to database locks, `/ballot` generates a single-use session-anchored token (`_ballot_token_$electionId`).
- The token is verified using `hash_equals()` and immediately invalidated upon submission, mitigating rapid double-clicks and browser form replays before database execution.

#### 4. Audit Log Correlation & Redaction
- **Correlation ID Tracing**: Every HTTP request and CLI command generates or propagates a unique 64-character trace identifier (`req_<hex>`) stored in `audit_logs.correlation_id`.
- **Absolute Choice Redaction**: `Logger::audit` strictly redacts `candidate_id`, `candidate_choice`, and choice payloads from audit logs. Voter candidate choices NEVER leak into audit trails or log files.

---

## 3. Ballot Privacy & Anonymity Analysis

| Risk / Threat | Mitigation Implemented | Security Level |
|---|---|---|
| **Voter Identity Linkage** | `votes` table has NO `user_id` column. Participation is logged separately from candidate selection in `election_participation`. | Application-layer separation |
| **Double Voting** | ACID transaction with row-level locking on `election_eligibility` and `UNIQUE KEY uk_participation_election_user` guarantee at most one ballot is accepted per voter. | Database constraint enforced |
| **Receipt Verification** | Voters receive an HMAC-SHA256 participation hash confirming their ballot was committed. The receipt is independent of candidate selection to resist voter coercion/vote-buying. | Cryptographic HMAC |
| **Race Conditions (25+ & 100 Concurrent Requests)** | Concurrency tested with 2 simultaneous workers, 25 parallel workers, and 100 concurrent workers for the same voter; serialized row locks and automatic transient deadlock retry ensure exactly 1 ballot is recorded. Multi-voter concurrency with 10 distinct voters achieves 10/10 zero-loss commitment. | Verified at 100 workers |

### 3.1 Documented Technical Boundaries & Anonymity Limitations

It is essential to distinguish between **application-layer pseudo-anonymity** and **mathematically verifiable zero-knowledge anonymity**:

1. **Privileged Database Superuser (DBA) Access:**
   The absence of `user_id` in the `votes` table prevents casual inspection or application-layer JOINs between voters and their candidate choices. However, a privileged database administrator with access to MySQL binary replication logs (`binlog`), general query logs, or continuous table sampling can correlate the microsecond commit timestamp of a record inserted in `election_participation` with the corresponding record inserted in `votes`. True cryptographic anonymity against a malicious database superuser requires distributed multi-authority mix-nets or homomorphic encryption (e.g. ElGamal / Paillier), which is beyond the scope of a single-node institutional BCA application.

2. **Timing Correlation in Low-Turnout Elections:**
   In elections with low turnout or long gaps between votes (for example, if only one student votes in an afternoon), observers who physically see the student enter the polling booth or note the participation timestamp could correlate that timestamp with the ballot in `votes`.

3. **Small Voter Pools & Unanimous Voting:**
   If all eligible voters in a class or department cast their ballots for the same candidate, anonymity is mathematically broken regardless of schema separation or hashing.

4. **Receipt Independence & Coercion Resistance:**
   The cryptographic receipt generated upon voting is derived from the election ID, user ID, high-resolution microtime, random salt, and the server's HMAC secret. It explicitly **does not include the candidate choice**. This design prevents vote-buying and voter coercion (a student cannot prove to a third party which candidate they voted for using their receipt).
