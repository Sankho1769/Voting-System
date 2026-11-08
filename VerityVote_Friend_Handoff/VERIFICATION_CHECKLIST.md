# VerityVote — Friend Verification & Quality Acceptance Checklist

This checklist allows you to verify that your installation of VerityVote is operating properly.

Follow these 14 test checks in order using your web browser. Check off each box as you complete the test.

---

## Pre-Check: Diagnostic Self-Test

Before beginning browser tests, run the automated health checker in PowerShell:
```powershell
cd C:\xampp\htdocs\Baymax_Voting_System
C:\xampp\php\php.exe bin/install_check.php
```
- [ ] **Pre-Check:** Health checker finishes with **0 Failures** and status `[STATUS: READY]`.

---

## Section 1: Application Access & Authentication Portals

### [ ] Check 1: Public Website Opens
1. Open your browser and visit:
   ```
   http://localhost/Baymax_Voting_System/public/
   ```
2. **Expected Result:**
   - Landing page loads with header, 3D artwork, and 3 distinct portal cards (Voter, Candidate, Admin).
   - Navigation links (Explore, Candidates, Help) are visible.
   - Status code is HTTP 200.

---

### [ ] Check 2: Administrator Sign-In Works
1. Navigate to:
   ```
   http://localhost/Baymax_Voting_System/public/login/admin
   ```
2. Enter your Administrator ID and password (created in Step 8 of Installation Guide).
3. Click **Sign In**.
4. **Expected Result:**
   - Redirects to `/admin` (Administrator Dashboard).
   - Top navigation shows your administrator name.
   - Overview metrics (Total Users, Active Elections, System Status) appear.

---

## Section 2: Election Setup & Candidate Administration

### [ ] Check 3: Admin Creates a New Election
1. In the Administrator Dashboard, click **Create Election** (or visit `/admin/elections/create`).
2. Fill in the form:
   - **Title:** `Campus Council President Election`
   - **Category:** `Student Council`
   - **Position:** `President`
   - **Department:** `All Departments`
   - **Description:** `Annual student government election.`
   - **Voting Opens:** Current time or a few minutes ago.
   - **Voting Closes:** 2 hours from now.
   - **Results Visibility:** `Always visible after election closes`
3. Click **Save Draft**.
4. **Expected Result:**
   - Redirects to election details with badge `Draft`.
   - Success message confirms election creation.

---

### [ ] Check 4: Admin Adds Candidates
1. In the election detail page, locate the **Add Candidate** section.
2. Add Candidate 1:
   - **Name:** `Alice Johnson`
   - **Manifesto:** `Championing campus sustainability and extended library hours.`
   - Click **Add Candidate**.
3. Add Candidate 2:
   - **Name:** `Bob Smith`
   - **Manifesto:** `Expanding student internship opportunities and campus WiFi.`
   - Click **Add Candidate**.
4. For both candidates, click the **Approve** button.
5. **Expected Result:**
   - Both Alice Johnson and Bob Smith appear in the candidate roster with badge `Approved`.

---

### [ ] Check 5: Admin Enrolls Eligible Voters
1. In the Administrator Console, navigate to **Manage Users** (`/admin/users/create`) and create a test student voter:
   - **Identifier (Roll Number):** `STU1001`
   - **Full Name:** `Sarah Connor`
   - **Email:** `sarah.connor@mycollege.edu`
   - **Role:** `student`
   - **Department:** `Computer Science`
   - **Academic Year:** `2024-2027`
   - Click **Create Account** and note the generated temporary password.
2. Return to the election detail page (`/admin/elections/detail?id=...`).
3. Scroll to **Voter Whitelist / Eligibility**.
4. Enter roll number `STU1001` and click **Add Voter**.
5. **Expected Result:**
   - `STU1001` (Sarah Connor) appears in the approved voter whitelist for this election.

---

### [ ] Check 6: Election Transitions to Open
1. On the election detail page, locate the **Election Status** control.
2. Click **Open Election**.
3. **Expected Result:**
   - Status badge changes from `Draft` to `Open` (green pulsing indicator).
   - Configuration fields are locked to prevent mid-election tampering.

---

## Section 3: Multi-Portal Role Integrity & Candidate Self-Service

### [ ] Check 7: Candidate Portal Login Works
1. In the Admin Console, create a candidate user account for `Alice Johnson` (`/admin/users/create`):
   - **Identifier:** `CAND101`
   - **Full Name:** `Alice Johnson`
   - **Role:** `student`
   - Note the temporary password.
2. Link the candidate profile to user `CAND101` or sign into Candidate Portal:
   ```
   http://localhost/Baymax_Voting_System/public/login/candidate
   ```
3. Enter ID `CAND101` and password.
4. **Expected Result:**
   - Candidate logs in successfully and accesses the Candidate Dashboard (`/candidate`).
   - Candidate can view their campaign standing and opponents.

---

## Section 4: Voter Workflow & Secret Ballot Integrity

### [ ] Check 8: Voter Portal Login Works
1. Sign out of any existing session, then visit:
   ```
   http://localhost/Baymax_Voting_System/public/login/voter
   ```
2. Enter:
   - **Voter ID:** `STU1001`
   - **Password:** The temporary password created in Check 5.
3. Click **Sign In**.
4. If prompted to change temporary password, enter a permanent password (e.g. `VoterPass123!`).
5. **Expected Result:**
   - Successfully authenticated into the **Collegiate Voter Portal** (`/voter`).
   - The active election `Campus Council President Election` is listed under active voting events.

---

### [ ] Check 9: Voter Casts Secret Ballot
1. On the voter dashboard, click **Vote Now** next to `Campus Council President Election`.
2. The secret ballot interface opens (`/ballot?election_id=...`).
3. Select **Alice Johnson** and click **Submit Official Ballot**.
4. A confirmation dialog appears; confirm your vote.
5. **Expected Result:**
   - Ballot is committed atomically.
   - Redirects to **Vote Confirmation & Receipt** page (`/confirmation`).
   - A cryptographic participation receipt token (starting with `VV-`) and copyable audit slip are displayed.

---

### [ ] Check 10: Duplicate Vote Attempt Is Strictly Rejected
1. While still signed in as `STU1001`, attempt to return to the ballot page:
   ```
   http://localhost/Baymax_Voting_System/public/ballot?election_id=...
   ```
2. Attempt to submit a second ballot.
3. **Expected Result:**
   - The system strictly rejects the submission with error: *"You have already cast a ballot in this election."*
   - Double-voting is mathematically blocked.

---

## Section 5: Election Closure & Public Tally Verification

### [ ] Check 11: Admin Closes Election
1. Sign back into the Administrator Console (`/login/admin`).
2. Go to the election detail page.
3. Click **Close Election**.
4. **Expected Result:**
   - Status badge changes to `Closed`.
   - Voting is immediately terminated; no further ballots can be cast.

---

### [ ] Check 12: Admin Publishes Results & Tallies Display Correctly
1. Click **Publish Results**.
2. Visit the public results page:
   ```
   http://localhost/Baymax_Voting_System/public/results?id=...
   ```
3. **Expected Result:**
   - Results page loads (HTTP 200).
   - Tally shows Alice Johnson with 1 vote (100%) and Bob Smith with 0 votes (0%).
   - Total ballots cast displays `1`.

---

## Section 6: Backup Generation & Security Verification

### [ ] Check 13: Administrator Generates Database Backup
1. In the Administrator Console, navigate to **System Backups** (`/admin/backups`).
2. Click **Create Backup Now**.
3. **Expected Result:**
   - A timestamped `.sql` snapshot is generated on disk in `database/backups/`.
   - The backup appears in the backup list with its file size and date.
   - Clicking **Download** successfully downloads the SQL file.

---

### [ ] Check 14: Unauthorized Backup Download Is Strictly Blocked
1. Copy the download link for the backup file, for example:
   ```
   http://localhost/Baymax_Voting_System/public/admin/backups/download?file=...
   ```
2. Open a **Private / Incognito window** in your browser (where you are NOT signed in as an administrator).
3. Paste the download URL and press Enter.
4. **Expected Result:**
   - The server rejects the download request with `HTTP 403 Forbidden` or redirects to `/login/admin`.
   - Unauthorized users and visitors cannot download database backups.

---

## Verification Result Summary

| Total Checks | Completed | Status |
|:---:|:---:|:---:|
| 14 Checks | _____ / 14 | [ ] ALL PASSED &nbsp;&nbsp;&nbsp;&nbsp; [ ] ISSUES FOUND |

*When finished, copy your results into [`INSTALLATION_TEST_REPORT_TEMPLATE.md`](./INSTALLATION_TEST_REPORT_TEMPLATE.md) to share with the developer.*
