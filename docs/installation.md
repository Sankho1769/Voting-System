# VerityVote — Official XAMPP Installation & Setup Guide

**Document Status:** Authoritative Master Installation Guide<br>
**Target Platform:** Windows 10 / 11 with XAMPP 8.2+<br>
**Architecture:** PHP 8.2+, Apache 2.4, MariaDB 10.4+ / MySQL 8.0+, MySQLi<br>
**Distribution Archive:** `VerityVote_Clean_Distribution.zip`

---

## 1. Overview & Interface Guide

This guide enables any developer, evaluator, or systems administrator to install and operate VerityVote on a local Windows machine using XAMPP.

### Interface Legend
Each step specifies the required interface:
- 🖥️ **Browser / phpMyAdmin (GUI):** Performed via web browser at `http://localhost/` or `http://localhost/phpmyadmin/`.
- ⚙️ **PowerShell / Terminal (CLI):** Executed in Windows PowerShell or Command Prompt using `C:\xampp\php\php.exe`.
- 📝 **Text Editor:** Any text editor (VS Code, Notepad++, Notepad) to edit local `.php` configuration.

---

## 2. 10-Step XAMPP Installation Sequence

### Step 1: Install Compatible XAMPP 🖥️
Ensure you have **XAMPP for Windows** installed with PHP 8.2 or later:
1. Default installation directory: `C:\xampp\`.
2. Launch the **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`).
3. Click **Start** for **Apache** (verify Port 80 and 443 illuminate green).
4. Click **Start** for **MySQL** (verify Port 3306 illuminates green).

---

### Step 2: Extract ZIP into htdocs ⚙️
Extract the release candidate archive `VerityVote_Clean_Distribution.zip` into your XAMPP web root directory (`C:\xampp\htdocs\`).

**Using PowerShell:**
```powershell
Expand-Archive -Path "VerityVote_Clean_Distribution.zip" `
               -DestinationPath "C:\xampp\htdocs\Baymax_Voting_System" -Force
```
*Alternatively, you may use Windows Explorer (Right-click &rarr; Extract All) into `C:\xampp\htdocs\Baymax_Voting_System`.*

---

### Step 3: Create a Database 🖥️
Create the MySQL/MariaDB database for VerityVote.

**Using phpMyAdmin (GUI):**
1. Open your browser and navigate to:
   ```
   http://localhost/phpmyadmin/
   ```
2. Click **New** in the left sidebar.
3. In the **Database name** field, enter: `verityvote_db`.
4. In the **Collation** dropdown, select: `utf8mb4_unicode_ci` (or `utf8mb4_general_ci`).
5. Click **Create**.

*(Note: Step 5 below will also auto-create the database if it does not yet exist).*

---

### Step 4: Configure Local Credentials 📝
VerityVote ships with `config/config.example.php`. Copy this file to create your active `config/config.php`.

**Using PowerShell:**
```powershell
cd C:\xampp\htdocs\Baymax_Voting_System
copy config\config.example.php config\config.php
```

Open `config/config.php` in your text editor. Default XAMPP settings match automatically:
```php
'database' => [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'verityvote_db',
    'username' => 'root',
    'password' => '', // Default XAMPP root password is blank
    'charset'  => 'utf8mb4',
],
'app' => [
    'base_url' => '', // Leave empty for automatic directory-aware URL detection
    'timezone' => 'Asia/Kolkata',
],
'session' => [
    'cookie_name' => 'VERITYVOTE_SESSID',
],
'metrics' => [
    'metrics_token' => 'vv_local_metrics_secret_test_token_8f3a1c',
]
```

> [!IMPORTANT]
> Never commit `config/config.php` to version control. It is explicitly ignored by `.gitignore` and excluded from distribution packages.

---

### Step 5: Run Schema and Migration Commands ⚙️
Initialize the 10 core tables and apply all incremental migrations.

**Using PowerShell:**
```powershell
cd C:\xampp\htdocs\Baymax_Voting_System

# 1. Apply core schema (database/schema.sql)
C:\xampp\php\php.exe bin/check_db.php

# 2. Apply all versioned migrations (001 through 006)
C:\xampp\php\php.exe bin/migrate.php
```
*Expected output: All 10 tables validated and all 6 migrations reported as `[APPLIED]`.*

---

### Step 6: Create the Initial Administrator Securely ⚙️
VerityVote prohibits hardcoded default production credentials. Provision your administrator account via the protected CLI provisioning tool:

**Using PowerShell:**
```powershell
C:\xampp\php\php.exe bin/create_admin.php ADMIN01 "Campus Administrator" admin@college.edu
```
*The utility uses CSPRNG (`random_bytes`) to generate a cryptographically strong temporary password, displays it once in your terminal, and flags the account for mandatory password change upon initial login.*

> [!NOTE]
> Record the displayed temporary password immediately. If you need non-production test accounts across all 3 portals for local evaluation, you may optionally run:
> ```powershell
> C:\xampp\php\php.exe bin/seed_test_accounts.php TESTUSER TestPass!123
> ```

---

### Step 7: Verify Apache Rewriting 🖥️ / ⚙️
VerityVote uses front-controller routing via Apache `mod_rewrite`.

1. **Verify `.htaccess`:** Confirm that both `C:\xampp\htdocs\Baymax_Voting_System\.htaccess` and `public/.htaccess` are present.
2. **Verify Apache Configuration:** In `C:\xampp\apache\conf\httpd.conf`, verify that `LoadModule rewrite_module modules/mod_rewrite.so` is enabled (uncommented by default in XAMPP) and `AllowOverride All` is permitted for `htdocs`.
3. If changes were made to `httpd.conf`, restart Apache in the XAMPP Control Panel.

---

### Step 8: Open the Application 🖥️
Open your browser and navigate to:
```
http://localhost/Baymax_Voting_System/public/
```

The gateway displays:
- **Explore Elections Hub** (`/explore`): Multi-category election discovery.
- **Admin Portal** (`/login/admin`): Sign in using your `ADMIN01` ID and temporary password from Step 6.
- **Candidate Portal** (`/login/candidate`): Access the Candidate Control Center.
- **Voter Portal** (`/login/voter`): Access the confidential student/staff ballot box.

---

### Step 9: Run Environment and Database Checks ⚙️
Verify that the PHP runtime environment, extensions, directories, and database tables meet all operational requirements.

**Using PowerShell:**
```powershell
cd C:\xampp\htdocs\Baymax_Voting_System

# 1. Environment & installation health check (48 checks across 9 diagnostic layers)
C:\xampp\php\php.exe bin/install_check.php

# 2. Database schema and InnoDB verification
C:\xampp\php\php.exe bin/check_db.php

# 3. Optional: Run full automated regression suite (193 tests across 14 suites)
C:\xampp\php\php.exe tests/run_tests.php
```
*Expected: 48 Passed | 0 Warnings | 0 Failures on `install_check.php`, and 193 Passed | 0 Failed on `run_tests.php`.*

---

### Step 10: Configure and Test Backups 🖥️ / ⚙️
Ensure disaster recovery and backup procedures are operational:

**Via Web Interface (GUI):**
1. Sign into `/login/admin` as an administrator.
2. Navigate to **System Backups** (`/admin/backups`).
3. Click **Create Backup Now**.
4. Confirm that a timestamped `.sql` backup snapshot is listed and can be securely downloaded.

**Via Command Line (CLI):**
```powershell
# Create an on-demand SQL backup
C:\xampp\php\php.exe bin/backup_db.php

# Test backup restoration into a DISPOSABLE test database (never verityvote_db)
C:\xampp\php\php.exe bin/restore_db.php database/backups/<backup_filename>.sql verityvote_restore_eval_test --confirm
```
*Verify that the restore utility restores all 10 tables cleanly, then drop `verityvote_restore_eval_test` via phpMyAdmin.*

---

## 3. Dedicated VirtualHost Setup (Optional Production Simulation)

If you wish to access VerityVote via a local domain (e.g. `http://verityvote.local`):

1. Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf` and append:
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
2. Open `C:\Windows\System32\drivers\etc\hosts` in an elevated text editor (Run as Administrator) and append:
   ```
   127.0.0.1 verityvote.local
   ```
3. Restart Apache via XAMPP Control Panel.
4. Navigate to `http://verityvote.local/`.

---

## 4. Continuous Integration (GitHub Actions)

When contributing code via Git, VerityVote runs automated continuous integration on every push and pull request to `main`:
- Ubuntu runner with PHP 8.2 and MariaDB 10.4.
- Automated linting across all codebase files (`bin/lint_all.php`).
- Database schema provisioning and migrations (`bin/check_db.php`).
- Complete automated execution of all 193 tests across 14 suites (`tests/run_tests.php`).
- Concurrency invariant validation under parallel workloads.
- Automated metrics publication to `GITHUB_STEP_SUMMARY`.
