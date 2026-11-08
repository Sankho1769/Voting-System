# VerityVote — Phase 16: Independent Computer Installation & Friend Handoff Report

**Author:** Senior Release Engineer & Installation QA Lead
**Date:** November 2026
**Status:** **PACKAGE READY FOR EXTERNAL EVALUATION (Simulated Installation 100% Verified)**
**Repository:** https://github.com/Sankho1769/Voting-System
**Handoff Package Directory:** `VerityVote_Friend_Handoff/`
**Distribution Archive:** `VerityVote_Clean_Distribution.zip`
**Distribution Archive SHA-256:** `062fe9f862d8aa766a2adca6a93f0fbe593520fdbc4f30c7f7b2c748e74ef9f1`
**Primary Development Database:** `verityvote_db` (100% Preserved & Untouched)

---

## 1. Executive Summary & Verification Classification

Phase 16 prepares VerityVote for deployment, configuration, and verification by another person on a separate Windows computer without requiring developer intervention.

### Critical Classification Boundary:
- **Simulated Second Installation (Verified Locally):** A full clean-slate installation was performed on the current Windows host using a separate web directory (`C:\xampp\htdocs\Baymax_Voting_System_Sim`) and a disposable database (`verityvote_friend_sim_test_20261108`). All 14 functional acceptance checks and the 48-point automated health check passed 100%. The primary database (`verityvote_db`) remained strictly untouched ($\Delta = 0$).
- **Actual Independent Computer Installation (Pending External Testing):** Independent installation success is **not** claimed until an external tester completes the verification checklist on a physically separate Windows computer and submits the completed report.

---

## 2. Friend Handoff Package (`VerityVote_Friend_Handoff/`)

A dedicated, self-contained handoff directory has been prepared at:
```
VerityVote_Friend_Handoff/
```

### 2.1 Package Contents

| File | Purpose | Size |
|---|---|:---:|
| **`VerityVote_Clean_Distribution.zip`** | Pristine release candidate ZIP containing the complete application, database schemas, migrations, test harness, and diagnostic tools. | 561.47 KB |
| **`README.md`** | Welcome guide, package overview, and 30-second quick-start instructions. | 2.6 KB |
| **`PREREQUISITES.md`** | Hardware, operating system, XAMPP 8.2+ requirements, and network port details. | 2.9 KB |
| **`INSTALLATION_GUIDE.md`** | Authoritative 10-step installation manual with clear tool indicators (Explorer, Browser, PowerShell). | 7.7 KB |
| **`ADMIN_SETUP.md`** | Instructions for provisioning the initial system administrator via the secure CLI tool (`bin/create_admin.php`). | 3.9 KB |
| **`VERIFICATION_CHECKLIST.md`** | 14-point functional acceptance checklist covering all core user journeys. | 8.5 KB |
| **`TROUBLESHOOTING.md`** | Practical solutions for the 10 most common Windows XAMPP installation and runtime issues. | 8.7 KB |
| **`INSTALLATION_TEST_REPORT_TEMPLATE.md`** | Structured form for the friend to record test results and share them with the development team. | 3.9 KB |
| **`SHA256SUMS.txt`** | Cryptographic checksum file with verification instructions for PowerShell and CertUtil. | 0.5 KB |

### 2.2 Package Exclusion Safeguards
The clean distribution ZIP was constructed using [`bin/package_clean_distribution.php`](file:///C:/Users/hp/Desktop/Baymax_Voting_System/bin/package_clean_distribution.php) and verified using [`bin/audit_zip_distribution.php`](file:///C:/Users/hp/Desktop/Baymax_Voting_System/bin/audit_zip_distribution.php). It strictly excludes:
- Development database dumps and backups (`database/backups/*.sql`).
- Actual voter records, identities, or roll numbers.
- Cleartext account passwords, API secrets, or private keys.
- Local configuration files (`config/config.php`).
- Git version control metadata (`.git/`).
- Local log files (`storage/logs/*.log`, `*.json`).
- Temporary test override markers (`.test_database_override`).
- Binary scripts (`powershell.exe`).

---

## 3. Installation Health Checker (`bin/install_check.php`)

A dedicated, safe diagnostic utility was authored at [`bin/install_check.php`](file:///C:/Users/hp/Desktop/Baymax_Voting_System/bin/install_check.php) and packaged into the distribution archive.

### 3.1 Design Principles & Safety Invariants
1. **Strictly Read-Only:** The checker executes read-only inspections. It **never** drops, alters, truncates, or resets any database or table.
2. **Credential Masking:** Database credentials are never printed in cleartext. Passwords are displayed as `[EMPTY]` or `[PROTECTED/NON-EMPTY]`.
3. **Structured Reporting:** Clear categorization across 9 diagnostic layers with explicit `[PASS]`, `[WARN]`, and `[FAIL]` tags.
4. **Standardized Exit Codes:** Returns `0` on clean verification and `1` if critical requirements fail.

### 3.2 Health Check Output (48 Passed | 0 Warnings | 0 Failures)

```
========================================================
 VerityVote - Installation Health & Diagnostics Checker
 Timestamp: 2026-11-08 16:43:12
========================================================

[PASS]  PHP Runtime       : PHP 8.2.12 - Meets minimum requirement >= 8.2
[PASS]  PHP Extension     : mysqli - MySQL Improved Extension (Database Connectivity)
[PASS]  PHP Extension     : curl - cURL (HTTP Requests & Web Services)
[PASS]  PHP Extension     : mbstring - Multibyte String (Unicode & Text Processing)
[PASS]  PHP Extension     : fileinfo - File Information (Safe Candidate Photo MIME Detection)
[PASS]  PHP Extension     : openssl - OpenSSL (Cryptographic Tokens & Security Hashing)
[PASS]  PHP Extension     : json - JSON (Structured Logging & Payloads)
[PASS]  PHP Extension     : session - Session Management (Secure Web Portals)
[PASS]  PHP Extension (Rec): pdo - Installed
[PASS]  PHP Extension (Rec): pdo_mysql - Installed
[PASS]  Directory         : app - Core Application Source
[PASS]  Directory         : app/Core - Framework Core
[PASS]  Directory         : app/Services - Business Logic Services
[PASS]  Directory         : app/Views - User Interface Templates
[PASS]  Directory         : bin - CLI Utilities
[PASS]  Directory         : config - Application Configuration
[PASS]  Directory         : database - Database Scripts
[PASS]  Directory         : database/migrations - Versioned Schema Migrations
[PASS]  Directory         : public - Web Server Document Root
[PASS]  Directory         : storage - Storage Root
[PASS]  Directory         : storage/logs - Audit & Application Logs
[PASS]  Directory         : storage/cache - Query & View Cache
[PASS]  Directory         : storage/uploads/candidates - Candidate Photo Uploads
[PASS]  Directory         : database/backups - Transactional Database Backups
[PASS]  Permissions       : storage/logs - Directory is writable
[PASS]  Permissions       : storage/cache - Directory is writable
[PASS]  Permissions       : storage/uploads/candidates - Directory is writable
[PASS]  Permissions       : database/backups - Directory is writable
[PASS]  Configuration     : config.example.php - Template configuration present
[PASS]  Configuration     : config/config.php - Configuration loaded successfully
[PASS]  Database Config   : Parameters - Host: 127.0.0.1:3306 | User: root | Password: [EMPTY] | Database: verityvote_db
[PASS]  Database Engine   : Server Connection - Connected to MariaDB/MySQL (10.4.32-MariaDB)
[PASS]  Database Engine   : Database 'verityvote_db' - Target database exists
[PASS]  Database Table    : users - Institutional Accounts & Credentials
[PASS]  Database Table    : elections - Electoral Events & Configurations
[PASS]  Database Table    : candidates - Contenders & Manifestos
[PASS]  Database Table    : election_eligibility - Voter Eligibility Whitelist
[PASS]  Database Table    : election_participation - Anonymous Participation Audit Log
[PASS]  Database Table    : votes - Secret Cast Ballots (No user_id)
[PASS]  Database Table    : audit_logs - Immutable Security Audit Trail
[PASS]  Database Table    : login_attempts - Rate Limiting & Brute Force Tracker
[PASS]  Database Table    : migrations - Applied Versioned Migrations Ledger
[PASS]  Database Table    : portal_identifier_mappings - Portal-Specific ID Normalization
[PASS]  Database Engine   : InnoDB ACID Compliance - All core tables use InnoDB storage engine
[PASS]  Migrations        : Applied 6/6 - All versioned migrations applied
[PASS]  Admin Account     : Active Administrators (78) - System has at least one active administrator
[PASS]  Apache Config     : Root .htaccess - Present (Protects internal directories)
[PASS]  Apache Config     : public/.htaccess - RewriteEngine & Front Controller routing configured

========================================================
 Health Check Summary: 48 Passed | 0 Warnings | 0 Failures
========================================================
 [STATUS: READY] VerityVote is fully configured, healthy, and operational!
```

---

## 4. Simulated Independent Installation Results (14 / 14 Passed)

To validate that another user can extract and operate VerityVote on a pristine directory without relying on existing development state, an automated simulation was executed:
- **Simulation Web Directory:** `C:\xampp\htdocs\Baymax_Voting_System_Sim` (Extracted fresh from ZIP)
- **Simulation Database:** `verityvote_friend_sim_test_20261108` (Freshly created on Port 3306)
- **Simulation URL:** `http://localhost/Baymax_Voting_System_Sim/public`

### 4.1 Step-by-Step Simulation Execution Trace

```
========================================================
 VerityVote - Simulated Second Installation Test
 Target Directory: C:/xampp/htdocs/Baymax_Voting_System_Sim
 Disposable DB:   verityvote_friend_sim_test_20261108
 Distribution ZIP: VerityVote_Clean_Distribution.zip
========================================================

1. Verifying Primary Database Baseline...
   [OK] verityvote_db baseline: Audits = 6104, Logins = 199

2. Preparing fresh disposable simulation database 'verityvote_friend_sim_test_20261108'...
   [OK] Database 'verityvote_friend_sim_test_20261108' created.

4. Extracting 'VerityVote_Clean_Distribution.zip' into simulation directory...
   [OK] Extracted successfully.

5. Creating config/config.php from config.example.php...
   [OK] config/config.php created with database='verityvote_friend_sim_test_20261108'.
   [OK] Config verified: Target database is strictly 'verityvote_friend_sim_test_20261108'.

6. Executing bin/check_db.php to build base schema...
   [PASS] Base schema initialized with 10 tables.

7. Executing bin/migrate.php to apply versioned migrations...
   [PASS] All 6 migrations applied successfully.

8. Executing bin/create_admin.php to create initial administrator...
   [PASS] Initial administrator 'SIM_ADM_3c91' created with password.

9. Running bin/install_check.php on simulated installation...
   [PASS] Installation health checker reports 100% READY (0 Failures).

10. Executing 14 Functional Acceptance Checks via Apache HTTP...
   [PASS] Check 1: Public Website Opens
   [PASS] Check 2: Administrator Sign-In & Dashboard Access
   [PASS] Check 3: Admin Creates an Election (Draft)
   [PASS] Check 4: Admin Adds & Approves Candidates
   [PASS] Check 5: Admin Enrolls Eligible Voters into Whitelist
   [PASS] Check 6: Election Transitions to Open
   [PASS] Check 7: Candidate Portal Login & Profile Access
   [PASS] Check 8: Voter Portal Login Works
   [PASS] Check 9: Voter Casts Secret Ballot & Receives Receipt
   [PASS] Check 10: Duplicate Vote Attempt Strictly Rejected
   [PASS] Check 11: Admin Closes Election
   [PASS] Check 12: Results Published & Tallies Display Correctly
   [PASS] Check 13: Administrator Generates Database Backup
   [PASS] Check 14: Unauthorized Backup Download Strictly Blocked

11. Dropped disposable simulation database 'verityvote_friend_sim_test_20261108'.
12. Removed temporary simulation directory 'C:/xampp/htdocs/Baymax_Voting_System_Sim'.

========================================================
 Simulation Complete: 14 Passed | 0 Failed
 Primary Database Post-Check:
   Users:  1399 (Delta: 0)
   Audits: 6104 (Delta: 0)
   Logins: 199 (Delta: 0)
========================================================
SUCCESS: Simulated independent installation verified with 100% pass rate and zero primary database pollution!
```

---

## 5. Primary Database Stability Evidence (`verityvote_db`)

Throughout all packaging, health checking, and simulation operations, the primary database remained 100% untouched:

```
========================================================
 VerityVote - Database Schema & Connectivity Check
========================================================

Table Status & Record Counts:
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

[OK: All tables use InnoDB | Delta = 0 across all tables]
```

---

## 6. Required External Computer Test Steps

When sharing `VerityVote_Friend_Handoff/` with another person:

1. **Deliver the Handoff Folder:** Provide the entire `VerityVote_Friend_Handoff/` directory via USB drive, network share, or cloud transfer.
2. **Step 1 — Checksum Verification:** Ask the tester to open PowerShell and run:
   ```powershell
   Get-FileHash -Path "VerityVote_Clean_Distribution.zip" -Algorithm SHA256
   ```
   Compare the result with `SHA256SUMS.txt` (`062fe9f862d8aa766a2adca6a93f0fbe593520fdbc4f30c7f7b2c748e74ef9f1`).
3. **Step 2 — Execute 10-Step Installation:** Direct the tester to [`INSTALLATION_GUIDE.md`](file:///C:/Users/hp/Desktop/Baymax_Voting_System/VerityVote_Friend_Handoff/INSTALLATION_GUIDE.md).
4. **Step 3 — Run Health Checker:** Ask the tester to run:
   ```powershell
   C:\xampp\php\php.exe bin/install_check.php
   ```
   Confirm all 48 checks pass with `[STATUS: READY]`.
5. **Step 4 — Complete Verification Checklist:** Ask the tester to complete the 14 checks in [`VERIFICATION_CHECKLIST.md`](file:///C:/Users/hp/Desktop/Baymax_Voting_System/VerityVote_Friend_Handoff/VERIFICATION_CHECKLIST.md).
6. **Step 5 — Submit Test Report:** The tester fills out [`INSTALLATION_TEST_REPORT_TEMPLATE.md`](file:///C:/Users/hp/Desktop/Baymax_Voting_System/VerityVote_Friend_Handoff/INSTALLATION_TEST_REPORT_TEMPLATE.md) with their environment specifications, observed times, and signature.

---

## 7. Ballot Privacy & Anonymity Statement

> [!IMPORTANT]
> Structural separation of voter participation and anonymous ballot records was verified. The `votes` table does not contain a voter-identifying `user_id` column. This does not establish cryptographic anonymity against a database administrator or all possible metadata correlation.

---

## 8. Summary of Troubleshooting Solutions Included

[`TROUBLESHOOTING.md`](file:///C:/Users/hp/Desktop/Baymax_Voting_System/VerityVote_Friend_Handoff/TROUBLESHOOTING.md) provides concrete solutions for the 10 most common installation challenges:
1. **Apache Port 80 in Use:** Disabling conflicting Windows services (`World Wide Web Publishing Service` / IIS) or updating `httpd.conf` to port `8080`.
2. **MySQL Port 3306 in Use:** Stopping existing standalone MySQL services or adjusting port configuration in `config.php`.
3. **Missing PHP Extensions:** Step-by-step instructions to enable `mysqli`, `curl`, `mbstring`, `fileinfo`, `openssl` in `php.ini`.
4. **Connection Refused:** Verifying MySQL is running in XAMPP and using `127.0.0.1` rather than `localhost` on Windows.
5. **Database Access Denied:** Matching credentials in `config.php` (defaulting to empty password for standard XAMPP).
6. **Apache 404 on Subpages:** Enabling `AllowOverride All` in `httpd.conf` to activate `.htaccess` front controller routing.
7. **Storage Permissions:** Adjusting Windows NTFS folder permissions for `storage/` and `database/backups/`.
8. **Missing Configuration:** Copying `config.example.php` to `config.php` without duplicate `.php.txt` extensions.
9. **Migration Order Failures:** Running `bin/check_db.php` first to establish base schema before running `bin/migrate.php`.
10. **Expired Security Tokens:** Automatic CSRF token rotation and handling session cookie persistence.

---

## 9. Conclusion

Phase 16 establishes that VerityVote can be packaged, transferred, installed, and operated cleanly without dependency on the original development environment:
- The handoff package `VerityVote_Friend_Handoff/` is fully assembled, documented, and verified.
- The installation health checker `bin/install_check.php` provides instant, safe diagnostic validation.
- The simulated installation proved that a clean extraction into a secondary directory operates with 100% compliance across all 14 acceptance criteria.
- The primary database `verityvote_db` remains pristine with zero modifications.
- The package is ready for delivery to another evaluator for independent physical computer certification.
