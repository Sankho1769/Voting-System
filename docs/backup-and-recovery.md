# VerityVote — Database Backup and Recovery Guide

This document establishes the official database backup, restoration, disaster recovery, and migration safety protocols for the VerityVote platform.

---

## 1. Overview & Strategy

VerityVote employs a zero-credential-leakage backup strategy:
- Automated scripts load credentials strictly from `config/config.php` via PHP memory, avoiding plain credentials in `ps` process lists or shell histories.
- Streaming query restore eliminates `max_allowed_packet` memory overflows.
- Restoration safety barriers require explicit `--confirm` flags when targeting active production databases.
- Backups are stored in `database/backups/`, protected by `.htaccess` and excluded from Git tracking.

---

## 2. Automated CLI Backup Utility

### 2.1 Standard Execution
To generate a timestamped backup of the active database:
```bash
php bin/backup_db.php
```
*Output:*
```
========================================================
 VerityVote - Database Backup Utility
========================================================
Source Host:     127.0.0.1:3306
Database:        verityvote_prod
Destination:     database/backups/backup_20261106_183000_verityvote_prod.sql
Timestamp:       2026-11-06 18:30:00
--------------------------------------------------------
Dumping table `audit_logs`... [5177 rows]
...
Backup Status:   SUCCESS
Tables Dumped:   10
Archive Size:    1.74 MB
========================================================
```

### 2.2 Custom Destination / Target Database
```bash
# Custom target DB and destination file:
php bin/backup_db.php verityvote_prod /secure/backups/pre_election_2026.sql
```

---

## 3. Database Restoration Procedure

### 3.1 Restoring to a Test or Staging Database
When restoring to a non-active database (e.g. for testing or verification), run:
```bash
php bin/restore_db.php database/backups/backup_20261106_183000_verityvote_prod.sql verityvote_staging
```

### 3.2 Restoring to the Active Production Database
Restoring to the active production database overwrites live tables. To prevent accidental data destruction, the utility requires the `--confirm` flag:

```bash
php bin/restore_db.php database/backups/backup_20261106_183000_verityvote_prod.sql verityvote_prod --confirm
```

The streaming engine reads statements line-by-line, toggles `SET FOREIGN_KEY_CHECKS = 0`, applies schemas and records, and conducts an automated post-restore row count audit.

---

## 4. Pre-Migration Protocol & Recovery Steps

Before applying any database schema migration or upgrade:

### Step 1: Execute Pre-Migration Snapshot
```bash
php bin/backup_db.php verityvote_prod database/backups/pre_migration_$(date +%Y%m%d_%H%M%S).sql
```

### Step 2: Apply the Migration
```bash
php bin/migrate.php
```

### Step 3: Verify Integrity
```bash
php bin/check_db.php
php tests/run_tests.php
```

### Step 4: Disaster Recovery (If Migration Fails)
If a migration fails or produces inconsistent data:
1. Immediately place the web server into maintenance mode (or temporarily point `public/.htaccess` to a maintenance splash).
2. Restore the pre-migration snapshot:
   ```bash
   php bin/restore_db.php database/backups/pre_migration_YYYYMMDD_HHMMSS.sql verityvote_prod --confirm
   ```
3. Run `php bin/check_db.php` to verify table integrity.
4. Remove maintenance mode and investigate the migration error in `storage/logs/`.

---

## 5. Automated Scheduling & Retention Policies

### 5.1 Linux Cron Automation
Add the following entry to `/etc/cron.d/verityvote-backup`:
```cron
# Run daily database backup at 02:00 AM
0 2 * * * www-data /usr/bin/php /var/www/verityvote/bin/backup_db.php > /var/www/verityvote/storage/logs/backup.log 2>&1

# Purge local backups older than 30 days every Sunday at 03:00 AM
0 3 * * 0 www-data find /var/www/verityvote/database/backups -type f -name "backup_*.sql" -mtime +30 -delete
```

### 5.2 Backup Retention Tiering (3-2-1 Strategy)
- **Local Daily Snapshots**: Retained for 30 days on local server.
- **Weekly Offsite Copy**: Encrypted (`gpg --symmetric`) and synchronized to offsite object storage (AWS S3 Glacier, Google Cloud Storage, or institutional NAS).
- **Election Archive**: After election results are published, an immutable archive of the election state is backed up to optical/cold media and retained for institutional accreditation (minimum 3 years).

---

## 6. Verification and Disaster Drills

1. **Monthly Restoration Drill**: On the first business day of each month, DevOps engineers must restore the latest backup into a temporary test database (`verityvote_drill_db`) using `bin/restore_db.php`.
2. **Automated Test Coverage**: The VerityVote test suite contains `tests/Integration/BackupRestoreTest.php`, which tests backup generation, safety barriers, and data restoration on every build.
