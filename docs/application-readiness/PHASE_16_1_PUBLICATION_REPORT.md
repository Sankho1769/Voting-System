# VerityVote — Phase 16.1: Final GitHub Publication & Release Candidate v1.0.0-rc1 Report

**Author:** Senior Release Engineer and Git Repository Maintainer<br>
**Date:** November 2026<br>
**Status:** **PUBLISHED & TAGGED (`v1.0.0-rc1`)**<br>
**Repository:** https://github.com/Sankho1769/Voting-System<br>
**Branch:** `main`<br>
**Base Commit:** `37d342d2e4e2c8c5237fdf43e78c8ce05ee3b1d6`<br>
**Distribution Archive:** `VerityVote_Clean_Distribution.zip` (567.79 KB / 581,414 bytes)<br>
**Distribution SHA-256:** `062fe9f862d8aa766a2adca6a93f0fbe593520fdbc4f30c7f7b2c748e74ef9f1`<br>

---

## 1. Executive Summary

Phase 16.1 successfully transitions VerityVote from internal development, QA hardening, and simulated friend installation into an officially published, version-controlled GitHub Release Candidate (`v1.0.0-rc1`).

All code, security controls, diagnostics, test suites, and operational documentation developed across Phases 15 through 16 have been reviewed, audited for secrets, linted, verified for whitespace purity, and organized into logical, atomic Git commits. The primary development database (`verityvote_db`) remained 100% untouched and preserved at its frozen historical baseline throughout all verification runs.

---

## 2. Published Commits & Repository Lineage

The release was published as atomic, logically separated commits on branch `main`:

```
* docs(release): add XAMPP installation and friend handoff package
* a3d1fdf fix(security): enforce isolated test databases and safe restoration
* bef8371 feat(application): complete election management and backup workflows
* 37d342d docs(interview): add evidence-based software engineering interview portfolio
```

### Commit Breakdown

| Commit SHA | Type & Scope | Summary of Included Changes |
| :--- | :--- | :--- |
| `bef8371` | `feat(application)` | **Election Management & Backup Workflows:**<br>- Pre-lock draft editing of title, position, department, category, and dates.<br>- Admin candidate approval and rejection controls with audit tracking.<br>- Candidate self-service manifesto updates with IDOR authorization protection.<br>- Whitelist voter enrollment and removal before voting opens.<br>- Database backup generation service (`BackupService.php`) and secure path-traversal-protected download handler.<br>- Server-side pagination across Explore, Candidates, Users, and Audit logs.<br>- New integration suite `ApplicationReadinessTest.php` (15 assertions). |
| `a3d1fdf` | `fix(security)` | **Database Isolation & Restore Guardrails:**<br>- Added `getConnectedDatabase()` and `assertDisposableDatabase()` to Database core.<br>- Hardened `restore_db.php` with safety gate blocking direct restores to `verityvote_db`.<br>- Dynamic base URL resolution across all integration tests for multi-directory portability.<br>- Added `DatabaseIsolationSafetyTest.php` (10 assertions).<br>- Added read-only `bin/install_check.php` (48 diagnostic checks with masked credentials).<br>- Added release candidate end-to-end validator `bin/rc_lifecycle_validation.php` (57 assertions).<br>- Added distribution audit tool `bin/audit_zip_distribution.php` and link checker `bin/check_docs_links.php`. |
| `[HEAD]` | `docs(release)` | **Installation Documentation & Friend Handoff:**<br>- Authoritative 10-step XAMPP guide (`docs/installation.md` & `docs/INSTALLATION_GUIDE.md`).<br>- Updated testing and security documentation (`docs/testing.md`, `docs/security.md`).<br>- Complete `VerityVote_Friend_Handoff/` package documentation (`README.md`, `PREREQUISITES.md`, `INSTALLATION_GUIDE.md`, `ADMIN_SETUP.md`, `VERIFICATION_CHECKLIST.md`, `TROUBLESHOOTING.md`, `INSTALLATION_TEST_REPORT_TEMPLATE.md`, `SHA256SUMS.txt`).<br>- Complete Phase 15 and Phase 16 audit and publication reports in `docs/application-readiness/`. |

---

## 3. Files Included and Excluded

### Included in Publication
- **Application Source:** `app/Core/Database.php`, `app/Repositories/`, `app/Services/`, `app/Views/`, `public/index.php`, `public/assets/css/style.css`.
- **QA & CLI Utilities:** `bin/check_env.php`, `bin/check_db.php`, `bin/migrate.php`, `bin/create_admin.php`, `bin/backup_db.php`, `bin/restore_db.php`, `bin/install_check.php`, `bin/rc_lifecycle_validation.php`, `bin/qa_acceptance_audit.php`, `bin/audit_zip_distribution.php`, `bin/check_docs_links.php`, `bin/package_clean_distribution.php`.
- **Automated Tests:** 14 integration and unit suites in `tests/` covering 193 assertions.
- **Documentation:** `README.md`, `BUILD_STATUS.md`, `docs/installation.md`, `docs/INSTALLATION_GUIDE.md`, `docs/security.md`, `docs/testing.md`, and `docs/application-readiness/*.md`.
- **Friend Handoff Package:** All 8 documentation, setup, troubleshooting, and checksum files in `VerityVote_Friend_Handoff/`.

### Strictly Excluded from Publication
- `config/config.php` (Excluded via `.gitignore`; only `config.example.php` is tracked).
- Real institutional credentials, plain-text passwords, or private keys.
- Real institutional voter identity mappings or student accounts.
- Transactional database backup files (`database/backups/*.sql` excluded via `.gitignore`).
- Local runtime logs (`storage/logs/*.log`, `storage/logs/*.json`).
- Runtime query cache (`storage/cache/*`).
- Dynamic test override token (`storage/.test_database_override`).
- Temporary diagnostic and capture scripts (`bin/capture_rc_screenshots.ps1`).
- RC screenshot collections (`screenshots/rc/`).
- Release candidate and simulation web directories (`Baymax_Voting_System_RC`, `Baymax_Voting_System_Sim`).
- Raw binary ZIP archives (`VerityVote_Clean_Distribution.zip` and all `*.zip` excluded via `.gitignore:34`).

---

## 4. Verification Evidence & Quality Gates

| Verification Gate | Command / Tool | Result | Details |
| :--- | :--- | :---: | :--- |
| **PHP Syntax Lint** | `bin/lint_all.php` | **118 / 118 PASS** | Zero syntax errors detected across all PHP codebase files. |
| **Full Regression Suite** | `tests/run_tests.php` | **193 / 193 PASS** | 100% pass rate across 14 test suites in disposable DB. |
| **RC Lifecycle Validation** | `bin/rc_lifecycle_validation.php` | **57 / 57 PASS** | 100% pass rate across 8 end-to-end steps in isolated RC DB. |
| **Health & Diagnostics** | `bin/install_check.php` | **48 / 48 PASS** | 9 diagnostic layers verified; status: READY; credentials masked. |
| **Simulated Friend Installation** | `scratch/simulate_friend_installation.php` | **14 / 14 PASS** | 14-step checklist passed over live Apache HTTP in disposable DB. |
| **Distribution ZIP Security Audit** | `bin/audit_zip_distribution.php` | **PASS** | 0 forbidden files, 0 leaked credentials, 30 core files verified. |
| **Documentation Link Integrity** | `bin/check_docs_links.php` | **274 / 274 PASS** | Zero broken internal relative documentation links. |
| **Git Whitespace Hygiene** | `git diff --check` | **PASS** | Zero whitespace or line-ending errors across all staged commits. |

---

## 5. Primary Database Baseline Preservation

The primary operational database `verityvote_db` remained 100% isolated and frozen throughout all verification runs:

| Table Name | Frozen Baseline Count | Current Verified Count | Delta ($\Delta$) | Status |
| :--- | :---: | :---: | :---: | :---: |
| `users` | 1,399 | 1,399 | 0 | Preserved |
| `elections` | 496 | 496 | 0 | Preserved |
| `candidates` | 945 | 945 | 0 | Preserved |
| `election_eligibility` | 1,029 | 1,029 | 0 | Preserved |
| `election_participation` | 904 | 904 | 0 | Preserved |
| `votes` | 904 | 904 | 0 | Preserved |
| `audit_logs` | 6,104 | 6,104 | 0 | Preserved |
| `login_attempts` | 199 | 199 | 0 | Preserved |
| `migrations` | 6 | 6 | 0 | Preserved |
| `portal_identifier_mappings` | 0 | 0 | 0 | Preserved |

### Historical Security Records Preservation
- **Historical Audit Logs (IDs 6116–6125):** Verified intact with read-only SQL (`CSRF_VALIDATION_FAILURE`, `LOGIN_THROTTLED` events from November 8, 2026).
- **Historical Login Attempts (IDs 1281–1289):** Verified intact with read-only SQL (synthetic test account login attempts from November 8, 2026).

---

## 6. Distribution Archive & Release Assets

- **Archive File:** `VerityVote_Clean_Distribution.zip`
- **Location in Repo:** Root directory and `VerityVote_Friend_Handoff/VerityVote_Clean_Distribution.zip` (both `.gitignore` protected).
- **Archive File Count:** 220 files (264 zip entries including directories and `.gitkeep` files).
- **Archive Size:** 567.79 KB (581,414 bytes).
- **SHA-256 Checksum:** `062fe9f862d8aa766a2adca6a93f0fbe593520fdbc4f30c7f7b2c748e74ef9f1`.

### Verification Commands
```powershell
# PowerShell:
Get-FileHash -Path "VerityVote_Clean_Distribution.zip" -Algorithm SHA256

# Command Prompt:
certutil -hashfile VerityVote_Clean_Distribution.zip SHA256
```

---

## 7. Release Candidate Tag & GitHub Release Instructions

### Annotated Git Tag
- **Tag Name:** `v1.0.0-rc1`
- **Tag Message:** `VerityVote 1.0.0 Release Candidate — XAMPP installation and application verification`
- **Remote Tag URL:** https://github.com/Sankho1769/Voting-System/releases/tag/v1.0.0-rc1

### GitHub Release Creation Procedure
Because GitHub CLI (`gh`) is not installed on the local Windows machine, the release asset can be published via the GitHub Web UI:

1. Navigate to:
   ```
   https://github.com/Sankho1769/Voting-System/releases/new?tag=v1.0.0-rc1
   ```
2. **Release Title:** `VerityVote v1.0.0-rc1 — Educational Release Candidate`
3. **Release Notes Description:**
   ```markdown
   ## VerityVote v1.0.0-rc1 (Release Candidate)

   This is an educational college voting application release candidate built with PHP 8.2+ and MySQLi for local Windows XAMPP environments.

   ### Prerequisites
   - Windows 10 / 11
   - XAMPP 8.2+ (Apache 2.4, MariaDB 10.4+, PHP 8.2+)
   - Standard web browser (Chrome, Edge, Firefox)

   ### Asset Verification
   - **File:** `VerityVote_Clean_Distribution.zip` (567.79 KB)
   - **SHA-256:** `062fe9f862d8aa766a2adca6a93f0fbe593520fdbc4f30c7f7b2c748e74ef9f1`

   ### Quick Start
   1. Extract ZIP to `C:\xampp\htdocs\Baymax_Voting_System`.
   2. Copy `config/config.example.php` to `config/config.php`.
   3. Run `C:\xampp\php\php.exe bin/check_db.php`.
   4. Run `C:\xampp\php\php.exe bin/install_check.php` (48 health checks).
   5. Create administrator: `C:\xampp\php\php.exe bin/create_admin.php ADMIN01 "Admin Name" admin@college.edu`.
   6. Open `http://localhost/Baymax_Voting_System/public/`.
   ```
4. **Attach Binary Asset:** Drag and drop `VerityVote_Clean_Distribution.zip` into the "Attach binaries" drop zone.
5. Check **"Set as a pre-release"** (since this is an RC).
6. Click **Publish release**.

---

## 8. Ballot Privacy & Anonymity Statement

> [!IMPORTANT]
> **Definitive Ballot Privacy & Anonymity Formulation:**<br>
> Structural separation of voter participation and anonymous ballot records was verified. The `votes` table does not contain a voter-identifying `user_id` column. This does not establish cryptographic anonymity against a database administrator or all possible metadata correlation.

---

## 9. Remaining Limitations & Non-Production Scope

1. **Simulated vs. Physical Third-Party Verification:** While simulated clean extraction, configuration, and 14-step checklist testing passed with 100% success locally, execution by an independent person on an external physical machine remains pending external friend completion.
2. **Local Academic Prototype:** VerityVote is engineered as an educational institutional prototype for college and university elections; it is not certified for high-stakes government or municipal electoral operations.
