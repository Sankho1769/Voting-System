# VerityVote Database Documentation

## Overview
The VerityVote database is engineered for ACID compliance, referential integrity, and ballot secrecy using MySQL 8.0+ or MariaDB 10.4+ through the MySQLi extension.

## Core Schema Tables
1. `users` — Institutional identity records (Roll numbers / Employee IDs, roles, password hashes, auth version, status).
2. `elections` — Single-position elections with lifecycle management (`draft`, `scheduled`, `open`, `closed`, `results_published`, `cancelled`).
3. `candidates` — Contenders per election, manifesto, photo path, and approval state.
4. `election_eligibility` — Explicit voter whitelist mapping between users and elections.
5. `election_participation` — Unique record of voting occurrence and cryptographic confirmation receipt hash.
6. `votes` — Anonymous ballot box table. Contains only `election_id` and `candidate_id`. **Does not contain `user_id`**, ensuring ballot secrecy.
7. `audit_logs` — Immutable audit trail of operational and administrative actions.
8. `login_attempts` — Rate-limiting and brute-force throttling table.
9. `migrations` — Applied migration history.

## phpMyAdmin Inspection & Import
1. Open XAMPP Control Panel and ensure MySQL/MariaDB and Apache are running.
2. Navigate to `http://localhost/phpmyadmin`.
3. Select the `verityvote_db` database (or create it if it does not exist).
4. Go to the **Import** tab.
5. Choose `database/schema.sql` and click **Import**.
6. Inspect all tables, structure, indexes, and foreign keys.

## CLI Migration & Schema Check
Run the CLI environment and database check tool:
```bash
php bin/check_db.php
```

## Backup & Export
Export using `mysqldump`:
```bash
C:\xampp\mysql\bin\mysqldump.exe -u root -p verityvote_db > database/backups/backup_%date:~-4,4%%date:~-7,2%%date:~-10,2%.sql
```
Restore using `mysql`:
```bash
C:\xampp\mysql\bin\mysql.exe -u root -p verityvote_db < database/schema.sql
```
