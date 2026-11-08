# VerityVote — Initial Administrator Account Setup

This document explains how the initial administrator account is provisioned and managed.

---

## 1. Security Philosophy: No Default Passwords

Unlike legacy software that ships with known default passwords (like `admin` / `password123` or `root` / `admin`), **VerityVote intentionally ships with zero default accounts or default passwords**.

Default credentials present severe security vulnerabilities:
- In production, default passwords are often forgotten and remain unchanged.
- Automated bots and scanners scan for known default combinations.
- Hardcoded credentials in source code can leak in Git repositories.

Instead, VerityVote generates a unique, cryptographically random temporary password on the host machine when you run the administrative setup tool.

---

## 2. Creating an Administrator via the CLI Tool

The administrator provisioning tool is located at:
```
bin/create_admin.php
```
It is strictly restricted to the **Command Line Interface (CLI)**. It cannot be triggered through a web browser, protecting it from remote attackers.

### Interactive Creation (Recommended)

1. Open **Windows PowerShell** or **Command Prompt**:
   ```powershell
   cd C:\xampp\htdocs\Baymax_Voting_System
   ```
2. Run the script:
   ```powershell
   C:\xampp\php\php.exe bin/create_admin.php
   ```
3. Follow the prompts on screen:
   - **Administrator ID:** Type your institutional employee ID or desired username (e.g., `ADMIN01`, `FACULTY_CHAIR`). Must be 3–64 characters alphanumeric.
   - **Full Name:** Enter your name (e.g., `Dr. Alex Morgan`).
   - **Contact Email:** Enter your institutional email address (e.g., `alex.morgan@college.edu`).

### Non-Interactive Command-Line Arguments

You can also pass metadata as arguments (Identifier, Full Name, Email):
```powershell
C:\xampp\php\php.exe bin/create_admin.php ADMIN01 "System Administrator" "admin@mycollege.edu"
```

> [!IMPORTANT]
> **Password Argument Security Barrier:**
> Passwords are **never** accepted as command-line arguments. This prevents credentials from being exposed in Windows process lists (`tasklist`, Task Manager), shell history logs, or shoulder-surfing observers.

---

## 3. Temporary Password & First-Login Security Policy

When `bin/create_admin.php` creates the account:

1. **CSPRNG Generation:** It generates a high-entropy password using PHP's cryptographically secure pseudo-random number generator (`random_bytes()`):
   - Example format: `Vv-X4f89a2#`
2. **One-Way Argon2 / Bcrypt Hash:** The password is immediately hashed using `password_hash()` with `PASSWORD_DEFAULT`. Only the hash is stored in the database.
3. **24-Hour Expiration:** Temporary credentials expire after 24 hours if unused.
4. **Mandatory Password Change (`must_change_password = 1`):**
   - When you sign in to `/login/admin` using the temporary password, the system detects `must_change_password = 1`.
   - Access to the administrative dashboard (`/admin`) is blocked until you set a permanent, personal password.
   - You will be redirected to `/change-password` to enter your personal password.
   - Once saved, `must_change_password` becomes `0`, and you are granted access to the console.

---

## 4. Creating Additional Administrators

Once inside the Administrator Dashboard:
- You can create additional election officers or administrators from the web interface at **Manage Users** (`/admin/users/create`).
- Or you can run `bin/create_admin.php` again with a different Administrator ID to provision another administrative user.

---

## 5. Troubleshooting Login Issues

If your temporary password expired or you forgot your password:
- Simply run `bin/create_admin.php` with a new Administrator ID (e.g., `ADMIN02`).
- Sign in with `ADMIN02`, access the administrative dashboard, and you can manage other accounts from **Manage Users** (`/admin/users`).
