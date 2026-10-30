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

---

## 3. Ballot Privacy & Anonymity Analysis

| Risk / Threat | Mitigation Implemented | Security Level |
|---|---|---|
| **Voter Identity Linkage** | `votes` table has NO `user_id` column. Participation is logged separately from candidate selection in `election_participation`. | Application-layer separation |
| **Double Voting** | ACID transaction with row-level locking on `election_eligibility` and `UNIQUE KEY uk_participation_election_user` guarantee at most one ballot is accepted per voter. | Database constraint enforced |
| **Receipt Verification** | Voters receive an HMAC-SHA256 participation hash confirming their ballot was committed. The receipt is independent of candidate selection to resist voter coercion/vote-buying. | Cryptographic HMAC |
| **Race Conditions (25+ Concurrent Requests)** | Concurrency tested with 25 independent processes for the same voter; serialized row locks and automatic transient deadlock retry ensure exactly 1 ballot is recorded. | High concurrency verified |

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
