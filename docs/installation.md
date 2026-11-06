# VerityVote — Installation & Portability Setup Guide

This guide enables any student or evaluator to install, run, inspect, and test VerityVote on Windows using standard XAMPP.

---

## 1. Prerequisites
1. **Windows 10 / 11**.
2. **XAMPP for Windows** with:
   - Apache 2.4+
   - PHP 8.2+ (with `mysqli`, `openssl`, `mbstring`, `fileinfo` enabled)
   - MariaDB 10.4+ or MySQL 8.0+
   - phpMyAdmin

---

## 2. Step-by-Step Installation

### Step 1: Copy Project Files
Place or extract the project folder into your XAMPP web root directory:
```
C:\xampp\htdocs\Baymax_Voting_System
```

### Step 2: Start Apache and MySQL
1. Launch the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
*(Verify that both modules display green indicators).*

### Step 3: Configure Local Database Settings
In the `config/` directory, copy the example configuration:
```bash
cd C:\xampp\htdocs\Baymax_Voting_System
copy config\config.example.php config\config.php
```
Open `config/config.php` in any text editor. By default, XAMPP credentials match:
```php
'database' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'verityvote_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
],
'app' => [
    'base_url' => '', // Leave empty for auto-detection
    'timezone' => 'Asia/Kolkata',
]
```

### Step 4: Run the Environment Check
Open PowerShell or Command Prompt and verify PHP extensions and directory permissions:
```bash
C:\xampp\php\php.exe bin/check_env.php
```
*Expected: 13 Passed | 0 Warnings | 0 Failures.*

### Step 5: Initialize the Database Schema
Run the automated database creation and schema validation tool:
```bash
C:\xampp\php\php.exe bin/check_db.php
```
This automatically connects to MariaDB/MySQL, creates `verityvote_db`, and applies the 9 core InnoDB tables from `database/schema.sql`.

To apply or verify incremental schema migrations:
```bash
C:\xampp\php\php.exe bin/migrate.php
```

### Step 6: Create the Initial Administrator
Run the protected CLI-only provisioning script (no passwords in CLI arguments):
```bash
C:\xampp\php\php.exe bin/create_admin.php ADMIN01 "College Administrator" admin@college.edu
```
*The utility will generate a cryptographically random one-time temporary credential (CSPRNG), display it once on the screen, and set mandatory first-login password change flag.*

### Step 7: Inspect via phpMyAdmin
1. Open your browser and navigate to:
   ```
   http://localhost/phpmyadmin
   ```
2. In the left sidebar, click `verityvote_db`.
3. Inspect all 10 tables: `users`, `elections`, `candidates`, `election_eligibility`, `election_participation`, `votes`, `audit_logs`, `login_attempts`, `migrations`, `portal_identifier_mappings`.

### Step 8: Access the Application & Login Portals
Open your browser and navigate to:
```
http://localhost/Baymax_Voting_System/public/
```
The central gateway displays three dedicated login options:
- **Admin Login** (`/login/admin`): Sign in using your administrator credentials (`ADMIN01` and the one-time temporary password generated in Step 6, or local test account provisioned via `bin/seed_test_accounts.php <id> <password>`).
- **Candidate Login** (`/login/candidate`): Contenders access their personal Candidate Control Center using their Candidate ID (`CAND-xx`), Roll Number, or local candidate test account.
- **Voter Login** (`/login/voter`): Student and staff voters access their whitelisted ballot boxes using Roll Number or local voter test account.
- **Multi-Election Hub** (`/explore`): Discover and filter active ballots across all 8 campus categories.

### Step 9: Run the Automated Verification Suite
To verify all 144 unit, integration, lifecycle, concurrency, three-portal, test account, HTTP journey, CSRF session, master security, and backup/restore tests:
```bash
C:\xampp\php\php.exe tests/run_tests.php
```
*Expected: 144 Passed | 0 Failed | 0 Skipped (100% pass rate across 10 suites).*
*(Note: Tests run safely against an isolated disposable database `verityvote_test_suite` and dynamic fixtures `TestFixtureFactory`, leaving the primary `verityvote_db` untouched).*

---

## 3. Alternative VirtualHost Setup (Optional Production Simulation)
If you wish to run VerityVote as a dedicated local domain (e.g. `http://verityvote.local`):
1. Add to `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
   ```apache
   <VirtualHost *:80>
       ServerName verityvote.local
       DocumentRoot "C:/xampp/htdocs/Baymax_Voting_System/public"
       <Directory "C:/xampp/htdocs/Baymax_Voting_System/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
2. Add to `C:\Windows\System32\drivers\etc\hosts`:
   ```
   127.0.0.1 verityvote.local
   ```
3. Restart Apache. Navigate to `http://verityvote.local`.

---

## 4. Continuous Integration Pipeline (GitHub Actions)

When contributing code via Git, VerityVote automatically runs the exact installation and verification steps in GitHub Actions on every push and pull request to `main`:
1. Sets up Ubuntu runner with **PHP 8.2** and **MariaDB 10.4**.
2. Configures **Apache 2.4** with `libapache2-mod-php8.2`, `mod_rewrite`, and `mod_headers`.
3. Validates PHP syntax across all 92 codebase files (`bin/lint_all.php`).
4. Creates database, validates schema, and applies migrations (`bin/check_db.php`).
5. Executes the full 144-test verification suite using the disposable test database (`tests/run_tests.php`).
6. Verifies concurrency invariants (including 100 simultaneous submissions yielding exactly 1 accepted ballot).
7. Generates dynamic execution metrics in `GITHUB_STEP_SUMMARY`.
