# VerityVote — Friend Handoff & Quick Start Package

Welcome to **VerityVote (Baymax Voting System)**!

This handoff package contains everything you need to install, configure, verify, and run the VerityVote collegiate voting application on your Windows computer using XAMPP.

---

## What Is Inside This Package?

| File / Folder | Purpose |
|---|---|
| **`VerityVote_Clean_Distribution.zip`** | Pristine application distribution package (clean release candidate, zero temporary files). |
| **`INSTALLATION_GUIDE.md`** | Step-by-step installation instructions written for first-time installers. |
| **`PREREQUISITES.md`** | Hardware, software, and XAMPP prerequisites (Apache, PHP 8.2+, MariaDB 10.4+). |
| **`ADMIN_SETUP.md`** | Instructions for creating the initial system administrator account using the secure CLI tool. |
| **`VERIFICATION_CHECKLIST.md`** | 14-point test checklist to verify that the installation is fully functional. |
| **`TROUBLESHOOTING.md`** | Solutions to common setup issues (port conflicts, database errors, rewrite rules). |
| **`INSTALLATION_TEST_REPORT_TEMPLATE.md`** | A simple form to fill out with your test results to share back with the developer. |
| **`SHA256SUMS.txt`** | Cryptographic hash to verify that your download has not been corrupted or altered. |

---

## 30-Second Quick Start Overview

If you already have XAMPP installed and running:
1. **Verify Checksum:** Ensure the hash in `SHA256SUMS.txt` matches `VerityVote_Clean_Distribution.zip`.
2. **Extract:** Unzip `VerityVote_Clean_Distribution.zip` into your XAMPP web root (`C:\xampp\htdocs\Baymax_Voting_System`).
3. **Database:** Open `http://localhost/phpmyadmin/` in your browser and create a database named `verityvote_db`.
4. **Configure:** Copy `config/config.example.php` to `config/config.php`.
5. **Initialize & Migrate:** Open PowerShell in the project folder and run:
   ```powershell
   C:\xampp\php\php.exe bin/check_db.php
   C:\xampp\php\php.exe bin/migrate.php
   ```
6. **Create Admin:** Run:
   ```powershell
   C:\xampp\php\php.exe bin/create_admin.php
   ```
   *(Save the temporary password printed on your screen!)*
7. **Health Check:** Run:
   ```powershell
   C:\xampp\php\php.exe bin/install_check.php
   ```
8. **Open in Browser:** Visit:
   ```
   http://localhost/Baymax_Voting_System/public/
   ```

---

## Need Help?
- Refer to [`INSTALLATION_GUIDE.md`](./INSTALLATION_GUIDE.md) for full step-by-step guidance.
- Refer to [`TROUBLESHOOTING.md`](./TROUBLESHOOTING.md) if you encounter any errors.
- Refer to [`VERIFICATION_CHECKLIST.md`](./VERIFICATION_CHECKLIST.md) to test all features.
