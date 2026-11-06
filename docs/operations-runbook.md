# VerityVote — Operations & Maintenance Runbook

This runbook serves as the day-to-day operational guide for Election Officers, System Administrators, and DevOps Engineers managing the live VerityVote voting infrastructure.

---

## 1. Routine System Health Checks

### 1.1 Automated Environment Diagnostic
Run the environment check script to verify directory permissions and PHP extensions:
```bash
php bin/check_env.php
```

### 1.2 Database Diagnostic & Record Count
Verify database connectivity, schema migrations, and record health:
```bash
php bin/check_db.php
```

---

## 2. Administrator Operational Procedures

### 2.1 User & Whitelist Provisioning
- **Single User Creation**: Navigate to `/admin/users/create` to register an individual student, staff member, or officer.
- **Bulk CSV Import**: Navigate to `/admin/users/import` to upload institutional rosters.
  - CSV Format: `identifier,full_name,email,department,academic_year`
  - Formula injection prefixes (`=`, `+`, `-`, `@`) are automatically sanitized on import and export.
- **Whitelist Allocation**: From `/admin/elections/detail?id=X`, select **Eligibility Whitelist** to grant voting rights by department, academic year, or individual roll number.

### 2.2 Election Lifecycle Execution
Every election progresses through strict state transitions:
```
[ Draft ] ──(Add >=2 Approved Candidates & Whitelist)──► [ Scheduled ] ──► [ Open ] ──► [ Closed ] ──► [ Results Published ]
```
- **Draft**: Configure title, position, category, description, and times. Add candidate contenders.
- **Open**: When opening time arrives or when manually opened. **Ballot Lock takes effect**: Candidates cannot be added, edited, or removed.
- **Closed**: Voting closes. No ballots accepted.
- **Results Published**: Final vote tallies and cryptographic turnout receipts become visible.

---

## 3. Log Inspection & Monitoring

### 3.1 Log File Architecture
All application logs reside in `storage/logs/`:
- `verityvote-YYYY-MM-DD.log`: Audit events, administrative operations, and voting milestones.
- `php_errors.log`: Private PHP runtime errors and uncaught exceptions.
- `backup.log`: Output from scheduled cron backups.

### 3.2 Real-Time Log Monitoring
To follow audit and error activity live:
```bash
tail -f storage/logs/verityvote-$(date +%Y-%m-%d).log
```

### 3.3 Log Redaction Verification
Verify that sensitive information is never printed into log files. The `Logger::redactSensitiveData()` engine automatically replaces passwords, password hashes, CSRF tokens, and voter ballot choices with `[REDACTED]`.

---

## 4. Failed Login & Brute-Force Investigation

### 4.1 Rate Limiting Architecture
The system throttles accounts after **5 failed login attempts** within a 15-minute sliding window per IP and identifier.

### 4.2 Querying Throttled Accounts
To check recent failed attempts in the database:
```sql
SELECT identifier, ip_address, attempted_at, is_successful 
FROM login_attempts 
WHERE is_successful = 0 
ORDER BY attempted_at DESC 
LIMIT 50;
```

### 4.3 Investigating Brute-Force Incidents
1. Identify high-frequency failed IPs:
   ```sql
   SELECT ip_address, COUNT(*) as fail_count 
   FROM login_attempts 
   WHERE is_successful = 0 AND attempted_at > NOW() - INTERVAL 1 HOUR 
   GROUP BY ip_address 
   HAVING fail_count > 10;
   ```
2. If an IP is conducting distributed credential stuffing, block the IP at the firewall or web server layer:
   ```bash
   sudo ufw deny from 192.0.2.50 to any
   ```
3. To clear accidental lockouts for legitimate users:
   ```sql
   DELETE FROM login_attempts 
   WHERE identifier = 'STUDENT_ROLL_NO' AND attempted_at > NOW() - INTERVAL 15 MINUTE;
   ```

---

## 5. Security Incident Response Protocol

| Incident Type | Severity | Immediate Action | Secondary Action |
|---|---|---|---|
| **Suspected Double-Voting Attempt** | Medium | Check `election_participation` table. Duplicate attempts are automatically blocked by the database unique constraint `(election_id, user_id)`. | Inspect `audit_logs` for user ID and correlation ID. Confirm only 1 vote counted. |
| **Compromised Account** | High | Immediate suspension via `/admin/users/status` or SQL: `UPDATE users SET status = 'suspended', auth_version = auth_version + 1 WHERE id = X;` | Invalidate all active sessions. Review user activity in `audit_logs`. |
| **Candidate Portal Mismatch** | Low | Verify candidate profile link in `candidates` table: `SELECT * FROM candidates WHERE user_id = X;` | Re-link candidate profile if institutional roll number was reassigned. |
| **Database Connection Failure** | Critical | Check MySQL service: `systemctl status mysql`. Inspect `storage/logs/php_errors.log`. | Restart service. If corrupt, execute restoration runbook from [backup-and-recovery.md](backup-and-recovery.md). |

---

## 6. Application Maintenance & Zero-Downtime Updates

### 6.1 Routine Cache and Session Pruning
PHP sessions expire automatically based on `session.gc_maxlifetime` (4 hours). To manually prune stale session files if using file-based sessions:
```bash
find /var/lib/php/sessions -type f -cmin +240 -delete
```

### 6.2 Pre-Update Checklist
1. Place server into maintenance mode.
2. Run database backup: `php bin/backup_db.php`.
3. Pull code update: `git pull origin main`.
4. Apply migrations: `php bin/migrate.php`.
5. Run test suite: `php tests/run_tests.php`.
6. Disable maintenance mode and monitor `storage/logs/verityvote-YYYY-MM-DD.log`.
