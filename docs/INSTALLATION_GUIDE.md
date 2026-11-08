# VerityVote — Installation Guide

> **Note:** The complete, authoritative 10-step installation manual with GUI vs. CLI breakdown is located at:
> 🔗 **[`docs/installation.md`](file:///c:/Users/hp/Desktop/Baymax_Voting_System/docs/installation.md)**

---

## Quick Reference Summary

For developers or evaluators already familiar with Windows XAMPP:

1. **Extract Distribution Archive:** Extract `VerityVote_Clean_Distribution.zip` to `C:\xampp\htdocs\Baymax_Voting_System`.
2. **Start Services:** Start **Apache** and **MySQL** in XAMPP Control Panel.
3. **Database Setup:** Create database `verityvote_db` via [phpMyAdmin](http://localhost/phpmyadmin/).
4. **Local Config:** Copy `config/config.example.php` &rarr; `config/config.php` and verify credentials.
5. **Schema & Migrations:**
   ```powershell
   C:\xampp\php\php.exe bin/check_db.php
   C:\xampp\php\php.exe bin/migrate.php
   ```
6. **Provision Initial Admin:**
   ```powershell
   C:\xampp\php\php.exe bin/create_admin.php ADMIN01 "Campus Administrator" admin@college.edu
   ```
7. **Verify Environment:**
   ```powershell
   C:\xampp\php\php.exe bin/check_env.php
   ```
8. **Run Verification Tests:**
   ```powershell
   C:\xampp\php\php.exe tests/run_tests.php
   ```
9. **Access Application:** Open `http://localhost/Baymax_Voting_System/public/`.

For in-depth explanations, troubleshooting, VirtualHost setup, and backup configurations, please refer to the primary guide: **[docs/installation.md](installation.md)**.
