# VerityVote — Step-by-Step Installation Guide

This guide takes you through installing and running **VerityVote** on your Windows computer using XAMPP.

No advanced programming knowledge is needed. Follow each step in order.

---

## Tool Guide: Which Tool to Use When

To make these instructions easy to follow, each step shows which tool you will use:

- 🗂️ **Windows File Explorer:** Managing files and folders on your computer.
- 🌐 **Web Browser:** Visiting websites and using phpMyAdmin (`http://localhost/`).
- 💻 **PowerShell / Command Prompt:** Typing setup commands into the terminal.
- 📝 **Notepad / Text Editor:** Reading and editing settings files.

---

## Step 1: Install and Open XAMPP 🗂️ / 🌐

1. Download **XAMPP for Windows (with PHP 8.2)** from:
   ```
   https://www.apachefriends.org/download.html
   ```
2. Run the installer and install it to the default path: `C:\xampp\`.
3. Open the **XAMPP Control Panel** from your Start Menu or by double-clicking:
   ```
   C:\xampp\xampp-control.exe
   ```

---

## Step 2: Start Apache and MySQL 🌐

In the XAMPP Control Panel window:
1. Find the row labeled **Apache** and click the **Start** button next to it.
   - The word *Apache* should turn green, and Port `80, 443` will appear.
2. Find the row labeled **MySQL** and click the **Start** button next to it.
   - The word *MySQL* should turn green, and Port `3306` will appear.
3. Open your web browser and go to:
   ```
   http://localhost/
   ```
   If you see the XAMPP welcome dashboard, Apache is running properly!

---

## Step 3: Extract the ZIP Archive into htdocs 🗂️

1. Locate the file `VerityVote_Clean_Distribution.zip` from this handoff package.
2. Open **Windows File Explorer** and navigate to your XAMPP web root directory:
   ```
   C:\xampp\htdocs\
   ```
3. Extract `VerityVote_Clean_Distribution.zip` so that its files sit in a folder named:
   ```
   C:\xampp\htdocs\Baymax_Voting_System
   ```

### How to extract using Windows File Explorer:
- Right-click `VerityVote_Clean_Distribution.zip`.
- Choose **Extract All...**.
- In the destination box, enter: `C:\xampp\htdocs\Baymax_Voting_System`.
- Click **Extract**.

### Alternatively, using PowerShell:
```powershell
Expand-Archive -Path "VerityVote_Clean_Distribution.zip" -DestinationPath "C:\xampp\htdocs\Baymax_Voting_System" -Force
```

*Verification:* Look inside `C:\xampp\htdocs\Baymax_Voting_System`. You should see folders named `app`, `bin`, `config`, `database`, `public`, `storage`, and `docs`.

---

## Step 4: Create a New Database in phpMyAdmin 🌐

1. Open your web browser and go to:
   ```
   http://localhost/phpmyadmin/
   ```
2. Click **New** in the top-left sidebar.
3. In the **Database name** box, type:
   ```
   verityvote_db
   ```
4. In the Collation dropdown next to it, select:
   ```
   utf8mb4_unicode_ci
   ```
   *(or `utf8mb4_general_ci`)*
5. Click the **Create** button.
6. You will see `verityvote_db` appear in the left list.

---

## Step 5: Configure Application Settings 🗂️ / 📝

1. In Windows File Explorer, open the `config` folder:
   ```
   C:\xampp\htdocs\Baymax_Voting_System\config\
   ```
2. Look for the file named `config.example.php`.
3. Make a copy of that file in the same folder and rename the copy to:
   ```
   config.php
   ```
   *(On Windows, ensure it is named `config.php`, not `config.php.txt`).*
4. Open `config.php` in **Notepad** to verify the settings. In standard XAMPP, the default settings work out-of-the-box:
   ```php
   'database' => [
       'host' => '127.0.0.1',
       'port' => 3306,
       'database' => 'verityvote_db',
       'username' => 'root',
       'password' => '',
       'charset' => 'utf8mb4',
   ],
   ```
   *(If you set a custom root password during XAMPP setup, enter it in `'password' => 'your_password'`).*
5. Save and close `config.php`.

---

## Step 6: Initialize Database Schema 💻

1. Open **Windows PowerShell** or **Command Prompt**:
   - Press `Windows Key + R`, type `powershell`, and press Enter.
2. Navigate to your VerityVote project directory:
   ```powershell
   cd C:\xampp\htdocs\Baymax_Voting_System
   ```
3. Run the database initialization tool:
   ```powershell
   C:\xampp\php\php.exe bin/check_db.php
   ```
   - This command connects to MariaDB, checks the database, and automatically builds all 10 core tables using `database/schema.sql`.
   - You should see `[SUCCESS]` and a table summary on your screen.

---

## Step 7: Apply Schema Migrations 💻

In the same PowerShell window, apply the versioned database updates:
```powershell
C:\xampp\php\php.exe bin/migrate.php
```
- You will see each of the 6 migrations apply with `[SUCCESS]`:
  - `001_initial_schema.sql`
  - `002_add_candidate_portal_support.sql`
  - `003_add_portal_identifier_mappings.sql`
  - `004_security_hardening.sql`
  - `005_add_election_categories.sql`
  - `006_performance_optimization_indexes.sql`

---

## Step 8: Create Your Initial Administrator Account 💻

For security, VerityVote does **not** include hardcoded default passwords. You create your own system administrator account using a protected command-line tool.

In your PowerShell window, run:
```powershell
C:\xampp\php\php.exe bin/create_admin.php
```

You will see:
```
========================================================
 VerityVote - Protected Administrator Provisioning
========================================================

Enter Administrator ID (e.g., ADMIN01) [ADMIN01]: ADMIN01
Enter Administrator Full Name [System Administrator]: Jane Doe
Enter Contact Email Address [admin@verityvote.college]: admin@mycollege.edu
```

1. Press Enter to accept defaults, or type your preferred ID and name.
2. The tool will generate a strong, temporary password and display it on your screen, for example:
   ```
   Initial Temporary Password: Vv-X4f89a2#
   ```
3. **IMPORTANT:** Copy and write down your temporary password immediately! You will need it to sign in for the first time.

---

## Step 9: Run the Installation Health Checker 💻

Before opening the browser, verify that your installation is 100% healthy:
```powershell
C:\xampp\php\php.exe bin/install_check.php
```

- This tool runs 48 automated checks across PHP, extensions, directories, database tables, and permissions.
- Look at the bottom of the output:
  ```
  Health Check Summary: 48 Passed | 0 Warnings | 0 Failures
  [STATUS: READY] VerityVote is fully configured, healthy, and operational!
  ```
- If any check reports `[FAIL]`, consult [`TROUBLESHOOTING.md`](./TROUBLESHOOTING.md).

---

## Step 10: Open VerityVote and Sign In 🌐

1. Open your web browser and navigate to:
   ```
   http://localhost/Baymax_Voting_System/public/
   ```
   You should see the VerityVote landing page with the purple and lime visual theme and portal entry cards.
2. Click **Administrative Console** (or go to `http://localhost/Baymax_Voting_System/public/login/admin`).
3. Enter:
   - **Administrator ID:** `ADMIN01` (or the ID you chose in Step 8)
   - **Password:** The temporary password generated in Step 8 (e.g., `Vv-X4f89a2#`)
4. Click **Sign In**.
5. **Set Permanent Password:** Because you signed in with a temporary password, the application will prompt you to set a permanent, personal password.
   - Enter your temporary password once as the current password.
   - Choose a strong new password (minimum 8 characters, with letters and numbers).
   - Click **Update Password**.
6. You are now inside the **Administrator Dashboard** (`/admin`)!

---

## Next Step: Verify Functionality

Now that VerityVote is installed, open [`VERIFICATION_CHECKLIST.md`](./VERIFICATION_CHECKLIST.md) to test creating an election, adding candidates, casting a ballot, and publishing results!
