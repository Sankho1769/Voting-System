# 12 — Live Technical Project Demonstration Runbook (10-Minute Script)

**Audience:** Hiring Managers, Tech Leads, and Live Technical Interviewers  
**Duration:** Exactly 10 Minutes  
**Objective:** A seamless, reproducible, zero-failure live demonstration of VerityVote highlighting architecture, security, and concurrency controls without resetting the production database.

---

## 1. Prerequisites & Environment Setup (Minute 0:00 – 1:00)

### 1.1 Local Environment Check
Open PowerShell in `C:\Users\hp\Desktop\Baymax_Voting_System`.

```powershell
# 1. Verify Apache and MySQL services are active
Get-Process -Name httpd, mysqld -ErrorAction SilentlyContinue | Select-Object Name, Id, WorkingSet64

# 2. Verify PHP 8.2 runtime
C:\xampp\php\php.exe -v

# 3. Check database schema integrity without modifying data
C:\xampp\php\php.exe bin\check_db.php
```

**Talking Point:**  
*"Before beginning the demo, I run our environment check. We are running PHP 8.2 with MariaDB 10.4 and Apache 2.4. Our database verification script confirms all 10 core tables and 6 schema migrations are present."*

---

## 2. Automated Quality Gates Demonstration (Minute 1:00 – 2:30)

Execute the syntax linter and master test suite:

```powershell
# 1. Execute repository-wide syntax linting
C:\xampp\php\php.exe bin\lint_all.php
# Expected: 108 files checked, 0 errors.

# 2. Execute automated regression suite
C:\xampp\php\php.exe tests\run_tests.php
```

**Talking Point:**  
*"Our master test suite contains 168 tests across 12 specialized suites. Notice that tests run against an isolated disposable database (`verityvote_test_suite`) provisioned on-the-fly with dynamic fixtures. This ensures zero risk of test data polluting or dropping our primary database."*

---

## 3. The Three Portals Walkthrough (Minute 2:30 – 4:30)

Open Chrome/Edge to: `http://localhost/Baymax_Voting_System/public/login`

### Step A: The Central Login Gateway
Show the three distinct portal cards:
1. **Voter Portal:** For students and faculty casting ballots.
2. **Candidate Portal:** For approved candidates editing bios and manifestos.
3. **Administrator Portal:** For election officers managing elections.

### Step B: Candidate Portal Demonstration
1. Log into the Candidate Portal (`/public/login/candidate`).
2. Show candidate dashboard:
   - Highlight profile information and manifesto editor.
   - Show contested opponents in the same election.
   - Show dual-role status: "You are also an eligible voter in this election."

### Step C: Administrator Portal Demonstration
1. Log into the Administrator Portal (`/public/login/admin`).
2. Show administrative dashboard:
   - Active elections overview.
   - Candidate approval queue.
   - Voter eligibility whitelist upload.
   - Immutable audit logs with correlation IDs.

---

## 4. The Live Voting Experience & Concurrency Defense (Minute 4:30 – 7:30)

### Step A: Voter Authentication & Ballot Casting
1. Open an Incognito window to `http://localhost/Baymax_Voting_System/public/login/voter`.
2. Sign in as an eligible voter.
3. Navigate to an open election:
   - Show approved candidate cards and manifestos.
   - Select candidate radio button.
   - Click **"Submit My Vote"**.
4. Show the **Confirmation Screen**:
   - Highlight the **Cryptographic Confirmation Receipt** (HMAC-SHA256).
   - Explain: *"This token proves the student participated, allowing independent audit verification without recording their candidate choice."*

### Step B: Duplicate Voting Prevention in Action
1. Attempt to navigate back or click "Submit Vote" again.
2. Show the immediate rejection:
   ```
   Ballot rejected: You have already cast your vote in this election.
   ```
3. Open `http://localhost/Baymax_Voting_System/public/student/dashboard`:
   - Election status updates from *"Vote Now"* to *"Voted — Ballot Recorded"*.

---

## 5. Security & Logging Verification (Minute 7:30 – 9:00)

Demonstrate web server log security:

```powershell
# 1. Fire an unauthorized request with a synthetic token
curl.exe -i "http://localhost/Baymax_Voting_System/public/metrics?token=SYNTHETIC_TEST_DEMO_SECRET"
# Expected: HTTP 401 Unauthorized

# 2. Inspect Apache access log to prove query string was stripped
Get-Content C:\xampp\apache\logs\access.log -Tail 1
# Expected output format: "GET /Baymax_Voting_System/public/metrics HTTP/1.1" 401 72 331822
# Notice: Zero query string and zero User-Agent logged!

# 3. Access metrics with valid Bearer token
curl.exe -i -H "Authorization: Bearer $(C:\xampp\php\php.exe -r '$c=require \"config/config.php\"; echo $c[\"security\"][\"metrics_token\"];')" http://localhost/Baymax_Voting_System/public/metrics
# Expected: HTTP 200 OK with Prometheus telemetry
```

**Talking Point:**  
*"In Phase 13, we conducted a security audit of our logging pipeline. Notice that even though curl sent a sensitive token in the query string, our custom Apache log format `verityvote_secure` completely stripped the query parameter from the server disk log."*

---

## 6. Wrap-Up & GitHub CI Pipeline (Minute 9:00 – 10:00)

Open the repository on GitHub:
`https://github.com/Sankho1769/Voting-System/actions`

Show the latest GitHub Actions run on `main`:
- Point out **`VerityVote CI`** completed with status **`success`**.
- Highlight that the pipeline sets up MariaDB, Apache 2.4 VirtualHost, PHP 8.2, runs linter, applies versioned migrations, and executes all 168 tests in 84 seconds.
- Conclude: *"Everything demonstrated locally is continuously verified on every commit in our automated CI/CD pipeline."*
