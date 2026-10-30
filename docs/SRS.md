# VerityVote — Software Requirements Specification (SRS)
**Document Version:** 1.0.0  
**Project:** VerityVote Institutional College Voting System  
**Target Environment:** PHP 8.2+ / MySQLi / MariaDB / Apache (XAMPP on Windows)

---

## 1. Introduction & System Scope
VerityVote is an invitation-free, closed institutional electronic voting platform developed for college elections, student unions, class representatives, and faculty representation. All user accounts are provisioned exclusively by college administration; public self-registration is strictly disallowed. The system ensures ballot privacy by decoupling voter participation records from candidate ballot choices.

---

## 2. Institutional Stakeholders & Roles
1. **Student (`student`)**: Can authenticate with college roll number, review eligible elections, cast one atomic ballot per election, receive a cryptographic receipt, and view provisional/final results.
2. **Staff (`staff`)**: Can authenticate with employee ID and vote in designated staff representation ballots.
3. **Candidate (`candidate`)**: Nominated collegiate user contesting an election who can access the dedicated Candidate Portal (`/login/candidate`), inspect candidacy approval status (`approved`, `pending`, `rejected`), review platform manifesto, view contesting opponents, and monitor standings/returns. If whitelisted as a voter, retains full voting rights in `/login/voter` under a single account.
4. **Election Officer (`election_officer`)**: Can manage assigned elections, candidates, manifestos, candidate photos, and voter whitelist eligibility.
5. **System Administrator (`admin`)**: Can provision accounts, perform CSV bulk imports, reset temporary passwords, manage election officers, and audit system event ledgers.

---

## 3. Functional Requirements

### 3.1 Authentication & Account Management
- **FR-AUTH-01**: The system shall authenticate students using their institutional Roll Number and password.
- **FR-AUTH-02**: The system shall authenticate staff and administrators using their Employee ID / Admin ID and password.
- **FR-AUTH-03**: Public self-registration shall be permanently disabled.
- **FR-AUTH-04**: Passwords shall be securely hashed using PHP `password_hash()` with the current default bcrypt algorithm.
- **FR-AUTH-05**: Newly provisioned accounts shall be assigned a cryptographically random temporary password with a 24-hour expiry and mandatory change on first sign-in.
- **FR-AUTH-06**: Failed authentication attempts shall return a generic error without disclosing whether the institutional ID exists.
- **FR-AUTH-07**: Server-side rate limiting shall throttle sign-in attempts after 5 consecutive failures within a 15-minute sliding window.
- **FR-AUTH-08**: The system shall provide three separate dedicated login portals: Admin Login (`/login/admin`), Candidate Login (`/login/candidate`), and Voter Login (`/login/voter`), accessible through a central gateway (`/login`).
- **FR-AUTH-09**: Role mismatch protection shall be enforced at authentication time: administrative accounts cannot authenticate via the voter portal, student/staff accounts cannot authenticate via the admin portal, and non-candidate accounts cannot authenticate via the candidate portal.
- **FR-AUTH-10**: The system shall support dual-role authorization for candidates who are also eligible voters using a single institutional user account, allowing seamless sign-in at both `/login/candidate` and `/login/voter` without duplicate accounts.

### 3.2 Role-Based Access Control
- **FR-RBAC-01**: Every protected HTTP route and data endpoint shall enforce authorization server-side through dedicated middleware (`AuthMiddleware`, `RoleMiddleware`, `CandidateMiddleware`, `VoterMiddleware`).
- **FR-RBAC-02**: A student, staff, or candidate account shall never inherit administrative privileges without explicit database role assignment.
- **FR-RBAC-03**: Invalidation of sessions shall occur immediately upon account suspension or password change via monotonic `auth_version` checks.
- **FR-RBAC-04**: Idle sessions shall terminate after 30 minutes of inactivity; absolute session duration shall not exceed 4 hours.
- **FR-RBAC-05**: Unauthorized access attempts by candidates or voters to administrative endpoints (`/admin/*`) shall return an immediate HTTP 403 Forbidden response.

### 3.3 Election Lifecycle Management
- **FR-ELEC-01**: Each election shall contest exactly one institutional office (e.g., President, Class Representative).
- **FR-ELEC-02**: The election lifecycle state machine shall follow: `Draft` &rarr; `Scheduled` &rarr; `Open` &rarr; `Closed` &rarr; `Results Published` (with optional `Cancelled`).
- **FR-ELEC-03**: Transition to `Open` or `Scheduled` shall be rejected if fewer than two approved candidates contest the election.
- **FR-ELEC-04**: Transition to `Open` shall be rejected if the configured closing time is in the past.
- **FR-ELEC-05**: Once an election transitions to `Open`, candidate lists, voter eligibility mappings, and ballot definitions shall be permanently locked against modifications.
- **FR-ELEC-06**: Closing an election shall stop all ballot acceptance while preserving all previously recorded votes.

### 3.4 Candidate Management
- **FR-CAND-01**: Each candidate record shall include full name, election association, platform manifesto, approval state, and optional photograph.
- **FR-CAND-02**: Uploaded photographs shall be validated for file size (&le; 2MB), MIME type via `finfo` (JPEG/PNG/WebP), and valid image dimensions using `getimagesize()`.
- **FR-CAND-03**: Uploaded media shall be stored using cryptographically random filenames to prevent script execution vulnerabilities.
- **FR-CAND-04**: The system shall provide a dedicated Candidate Dashboard (`/candidate`) displaying profile information, assigned election, candidacy approval status, platform manifesto, opponent list, and standings/returns according to election disclosure policy.
- **FR-CAND-05**: The system shall support candidate authentication using either their institutional roll number or official candidate code (`CAND-xx` / ID).

### 3.5 Voter Whitelist Eligibility
- **FR-ELIG-01**: A valid user account shall only be permitted to vote in an election if an explicit whitelist record exists in `election_eligibility`.
- **FR-ELIG-02**: Administrators and officers shall be able to assign voter eligibility individually or in batches by institutional criteria (Role, Department, Academic Batch).

### 3.6 Transaction-Safe Voting Engine
- **FR-VOTE-01**: Ballot submissions shall be executed within an ACID database transaction using MySQLi with row-level locks (`LOCK IN SHARE MODE` and `FOR UPDATE`).
- **FR-VOTE-02**: The voting engine shall verify that:
  1. The user account is active with an established password.
  2. The election status is `open` and real server time is between `opens_at` and `closes_at`.
  3. The selected candidate belongs to that election and is approved.
  4. The user exists on the eligibility whitelist.
  5. No record exists in `election_participation` for that user and election.
- **FR-VOTE-03**: Double-voting shall be strictly prevented. Concurrent submissions from the same user across tabs or sessions shall result in exactly one accepted ballot and one rollback.
- **FR-VOTE-04**: A cryptographic HMAC-SHA256 receipt token shall be generated and recorded in `election_participation` upon acceptance.
- **FR-VOTE-05**: The ballot choice shall be stored in the anonymous `votes` table, which contains **no `user_id` column**, separating voter participation from candidate choice.

### 3.7 Results & Reports
- **FR-RES-01**: Standings shall be calculated server-side from recorded ballots in the database.
- **FR-RES-02**: Zero-vote elections shall calculate vote percentages without division-by-zero errors.
- **FR-RES-03**: When two or more candidates receive equal leading votes, the system shall declare a tie rather than arbitrarily picking a winner.
- **FR-RES-04**: Live provisional standings shall poll via AJAX every 6 seconds when permitted by the election's visibility policy.
- **FR-RES-05**: CSV reports shall escape spreadsheet formula execution characters (`=`, `+`, `-`, `@`, `\t`, `\r`) with a leading single quote.

### 3.8 Audit Trail
- **FR-AUD-01**: All critical actions (account provisioning, status changes, election state transitions, candidate edits, and ballot participation) shall be recorded in `audit_logs`.
- **FR-AUD-02**: Audit records shall never contain plain passwords, temporary credentials, or voter-candidate correlation links.

---

## 4. Non-Functional Requirements

### 4.1 Security
- **NFR-SEC-01**: All database interactions shall use MySQLi prepared statements with bound parameters.
- **NFR-SEC-02**: All state-modifying requests (POST/PUT/DELETE) shall validate a cryptographic session CSRF token.
- **NFR-SEC-03**: All user-controlled output rendered into HTML shall be escaped using `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- **NFR-SEC-04**: Security headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `X-XSS-Protection`) shall be applied on all responses.

### 4.2 Reliability & ACID Integrity
- **NFR-REL-01**: Database tables shall exclusively use the MySQL InnoDB storage engine to guarantee full transaction commit and rollback capabilities.
- **NFR-REL-02**: Referential foreign keys shall enforce `ON DELETE RESTRICT` on voting and election records to protect against accidental deletion.

### 4.3 Portability & Maintainability
- **NFR-PORT-01**: The system shall run out of the box in standard XAMPP environments on Windows without requiring Node.js, Composer, or paid third-party APIs.
- **NFR-PORT-02**: Configuration shall be centralized in `config/config.php` and decoupled from application code.
- **NFR-PORT-03**: The database schema and data shall be completely inspectable and exportable using phpMyAdmin.

---

## 5. Known Limitations
1. **Host-Level Anonymity**: While the relational schema decouples voter identity (`election_participation`) from candidate selection (`votes`), a privileged database administrator with access to millisecond binary replication logs could theoretically perform timing correlation during low-traffic periods. True cryptographic anonymity would require ring signatures or zero-knowledge proofs, which exceeds the scope of this BCA academic project.
2. **Single Contested Office**: Each election ballot represents one office. Multi-seat proportional representation is not supported in this version.
