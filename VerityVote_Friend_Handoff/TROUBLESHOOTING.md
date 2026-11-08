# VerityVote — Installation & Runtime Troubleshooting Guide

This guide provides practical solutions for the 10 most common setup and runtime issues on Windows XAMPP.

---

## Issue 1: Apache Port 80 Already in Use

### Symptom:
In the XAMPP Control Panel, clicking **Start** next to Apache fails. The log window displays:
```
Port 80 in use by "Unable to open process" with PID 4! Apache will not start.
```

### Cause:
Windows services like **World Wide Web Publishing Service (IIS)**, **Web Deployment Agent Service**, or **Skype** are occupying Port 80.

### Practical Solutions:

#### Option A: Stop the conflicting Windows service (Recommended)
1. Press `Windows Key + R`, type `services.msc`, and press Enter.
2. In the Services list, look for:
   - **World Wide Web Publishing Service**
   - **BranchCache**
3. Right-click the service, click **Stop**, then right-click and set **Startup type** to **Manual**.
4. In XAMPP Control Panel, click **Start** on Apache.

#### Option B: Change Apache's port to 8080
1. In XAMPP Control Panel, click **Config** next to Apache &rarr; **Apache (httpd.conf)**.
2. Search (`Ctrl + F`) for `Listen 80` and change it to `Listen 8080`.
3. Search for `ServerName localhost:80` and change it to `ServerName localhost:8080`.
4. Save the file and restart Apache.
5. You can now access VerityVote at:
   ```
   http://localhost:8080/Baymax_Voting_System/public/
   ```

---

## Issue 2: MySQL Port 3306 Already in Use

### Symptom:
In the XAMPP Control Panel, MySQL fails to start, reporting:
```
Port 3306 in use by "...mysqld.exe"! MySQL will not start.
```

### Cause:
An existing standalone installation of MySQL Server or MariaDB is already running as a Windows service on your machine.

### Practical Solutions:

#### Option A: Stop the existing standalone MySQL service
1. Open Windows Task Manager (`Ctrl + Shift + Esc`) &rarr; **Services** tab.
2. Look for `MySQL`, `MySQL80`, or `MariaDB`.
3. Right-click and choose **Stop**.
4. In XAMPP Control Panel, click **Start** on MySQL.

#### Option B: Configure VerityVote to use your existing MySQL service
If you prefer to keep your existing MySQL server running:
1. Ensure your existing MySQL server is active.
2. Open `config/config.php` and set `'port' => 3306` (or whatever port your existing MySQL uses, e.g., `3307`).
3. Enter your existing MySQL root username and password.

---

## Issue 3: Required PHP Extension Missing

### Symptom:
Running `bin/install_check.php` reports:
```
[FAIL]  PHP Extension: mysqli - MISSING! Enable extension=mysqli in php.ini
```

### Cause:
A required extension (`mysqli`, `curl`, `mbstring`, `fileinfo`, `openssl`) is disabled in your PHP configuration.

### Practical Solution:
1. In XAMPP Control Panel, click **Config** next to Apache &rarr; **PHP (php.ini)**.
2. Search (`Ctrl + F`) for the extension name, for example `;extension=mysqli`.
3. Remove the leading semicolon `;` to uncomment the line:
   ```ini
   extension=mysqli
   extension=curl
   extension=mbstring
   extension=fileinfo
   extension=openssl
   ```
4. Save `php.ini`.
5. In XAMPP Control Panel, click **Stop** then **Start** on Apache to reload the configuration.

---

## Issue 4: Database Connection Failure (Connection Refused)

### Symptom:
Running `bin/check_db.php` or `bin/install_check.php` outputs:
```
[FAIL] Database Engine: Server Connection - Connection refused: No connection could be made because the target machine actively refused it
```

### Cause:
The MySQL/MariaDB database server is not running in XAMPP, or the host/port settings are incorrect.

### Practical Solution:
1. Open XAMPP Control Panel and verify that **MySQL** has a green highlight and says **Stop** (meaning it is active).
2. If MySQL is not running, click **Start**.
3. If it is running, check `config/config.php`:
   - `'host' => '127.0.0.1'` (use `127.0.0.1` rather than `localhost` to avoid Windows IPv6 resolution issues).
   - `'port' => 3306`.

---

## Issue 5: Incorrect Database Credentials (Access Denied)

### Symptom:
The application displays:
```
Database Connection Error: Access denied for user 'root'@'localhost' (using password: YES)
```

### Cause:
The password in `config/config.php` does not match the actual password of your MySQL user.

### Practical Solution:
1. In standard fresh XAMPP installations, the `root` user has **no password** (an empty string).
2. Open `config/config.php` in Notepad:
   ```php
   'database' => [
       'host' => '127.0.0.1',
       'port' => 3306,
       'database' => 'verityvote_db',
       'username' => 'root',
       'password' => '', // Empty string for default XAMPP
   ],
   ```
3. If you previously set a password in phpMyAdmin, put that exact password between the quotes: `'password' => 'MySecretPassword'`.

---

## Issue 6: Apache URL Rewrite Rules Not Working (404 on Subpages)

### Symptom:
The homepage loads at `http://localhost/Baymax_Voting_System/public/`, but clicking any link (such as `/login/admin` or `/explore`) returns an **Apache 404 Not Found** error.

### Cause:
Apache's `mod_rewrite` is disabled, or `AllowOverride All` is not enabled in Apache's main configuration.

### Practical Solution:
1. In XAMPP Control Panel, click **Config** next to Apache &rarr; **Apache (httpd.conf)**.
2. Verify `mod_rewrite` is enabled:
   - Search for `LoadModule rewrite_module modules/mod_rewrite.so`.
   - Ensure there is NO semicolon `;` or `#` at the start of that line.
3. Verify `AllowOverride` is enabled:
   - Search for `<Directory "C:/xampp/htdocs">`.
   - Look inside that block for `AllowOverride None`.
   - Change it to:
     ```apache
     AllowOverride All
     ```
4. Save `httpd.conf` and restart Apache.
5. *Note:* VerityVote also includes automatic URL query parameter fallback (`?route=/login/admin`) in case rewrite is unavailable.

---

## Issue 7: Storage Permission Problems (Cannot Write Logs / Uploads)

### Symptom:
`bin/install_check.php` outputs:
```
[FAIL] Permissions: storage/logs - Directory is NOT writable
```
Or attempting to cast a ballot or log in results in an application error.

### Cause:
Windows folder permissions are restricting PHP from creating or modifying files in `storage/`.

### Practical Solution:
1. Open Windows File Explorer and navigate to:
   ```
   C:\xampp\htdocs\Baymax_Voting_System\
   ```
2. Right-click the `storage` folder &rarr; **Properties**.
3. Uncheck **Read-only** if it is checked, then click **Apply**.
4. Go to the **Security** tab:
   - Click **Edit...**.
   - Select your user account and ensure **Full control** or **Modify** is set to **Allow**.
   - Click **OK**.
5. Repeat for `database/backups/`.

---

## Issue 8: Missing Configuration File

### Symptom:
Opening the site or running tools produces:
```
[FAIL] Configuration: config/config.php - Missing configuration file!
```

### Cause:
`config/config.php` has not yet been created from `config.example.php`.

### Practical Solution:
1. Go to `C:\xampp\htdocs\Baymax_Voting_System\config\`.
2. Copy `config.example.php` and paste it in the same directory.
3. Rename the copy to `config.php`.
4. *Important Windows Tip:* Make sure File Explorer shows file extensions (in Explorer menu &rarr; View &rarr; Show &rarr; File name extensions). Ensure the file is named `config.php` and not `config.php.txt` or `config.php.php`.

---

## Issue 9: Migration Failures

### Symptom:
Running `bin/migrate.php` outputs:
```
Applying: 002_add_candidate_portal_support.sql... [ERROR: Table 'elections' doesn't exist]
```

### Cause:
Migrations are being run before the base database schema is initialized.

### Practical Solution:
1. Always run the initial schema checker first:
   ```powershell
   C:\xampp\php\php.exe bin/check_db.php
   ```
   This automatically reads `database/schema.sql` and builds all base tables.
2. Once the base schema is established, run migrations:
   ```powershell
   C:\xampp\php\php.exe bin/migrate.php
   ```

---

## Issue 10: Login Failure & "Security Token Invalid or Expired"

### Symptom:
Submitting the login form returns an error notice:
*"Your security token was invalid or expired due to inactivity."*

### Cause:
VerityVote uses cryptographic Cross-Site Request Forgery (CSRF) tokens tied to your browser session. If a login form sits idle in your browser tab for a long time, the session token expires to protect against session hijacking.

### Practical Solution:
1. VerityVote automatically regenerates a fresh token and redirects you to the login form.
2. Simply re-enter your identifier and password and click **Sign In** again.
3. If the problem persists across all pages, check that your browser accepts local session cookies (`PHPSESSID`) from `localhost`.
