# VerityVote — External Installation Test Report Template

*Please fill out this report template after completing the installation on your computer and share it back with the developer.*

---

## 1. Environment Details

| Field | Tester Input |
|---|---|
| **Tester Name / Handle:** | `[Your Name / Initials]` |
| **Testing Date:** | `YYYY-MM-DD` |
| **Operating System:** | `Windows 10 / Windows 11 (Version: ____)` |
| **XAMPP Version:** | `XAMPP 8.2.____` |
| **PHP Version:** | `PHP 8.2.____ (Reported by php -v)` |
| **Database Server:** | `10.4.____-MariaDB / MySQL 8.0.____` |
| **Web Browser Used:** | `Chrome / Edge / Firefox (Version: ____)` |
| **Target URL Tested:** | `http://localhost/Baymax_Voting_System/public/` |
| **Target Database Name:** | `verityvote_db` |

---

## 2. Checksum Verification

Before extracting, did the SHA-256 hash of `VerityVote_Clean_Distribution.zip` match `SHA256SUMS.txt`?

- [ ] **YES — Checksum matched exactly.**
- [ ] **NO — Mismatch or could not verify.**

*Observed SHA-256 Hash:*
```
[Paste hash from Get-FileHash or CertUtil here]
```

---

## 3. Automated Health Check (`bin/install_check.php`)

Run `C:\xampp\php\php.exe bin/install_check.php` and record the summary counts:

| Diagnostic Metric | Count |
|---|---|
| **Passed Checks:** | _____ |
| **Warnings:** | _____ |
| **Failures:** | _____ |
| **Reported Status:** | `[READY / ACTION REQUIRED]` |

---

## 4. Acceptance Checklist Results (14 Functional Checks)

Please mark each check as **PASS** or **FAIL**:

| # | Functional Check | Status | Notes / Observations |
|---|---|:---:|---|
| 1 | Public Website Opens (HTTP 200, Landing Page loads) | [ ] PASS &nbsp; [ ] FAIL | |
| 2 | Administrator Sign-In Works (`/login/admin`) | [ ] PASS &nbsp; [ ] FAIL | |
| 3 | Admin Creates an Election (Draft status saved) | [ ] PASS &nbsp; [ ] FAIL | |
| 4 | Admin Adds Candidates (Alice & Bob approved) | [ ] PASS &nbsp; [ ] FAIL | |
| 5 | Admin Enrolls Eligible Voters (Whitelist updated) | [ ] PASS &nbsp; [ ] FAIL | |
| 6 | Election Transitions to Open (Pulsing green badge) | [ ] PASS &nbsp; [ ] FAIL | |
| 7 | Candidate Portal Login Works (`/login/candidate`) | [ ] PASS &nbsp; [ ] FAIL | |
| 8 | Voter Portal Login Works (`/login/voter`) | [ ] PASS &nbsp; [ ] FAIL | |
| 9 | Voter Casts Secret Ballot (HMAC receipt generated) | [ ] PASS &nbsp; [ ] FAIL | |
| 10 | Duplicate Vote Attempt Is Blocked (Double-voting rejected) | [ ] PASS &nbsp; [ ] FAIL | |
| 11 | Admin Closes Election (Voting locked) | [ ] PASS &nbsp; [ ] FAIL | |
| 12 | Admin Publishes Results & Tallies Display Correctly | [ ] PASS &nbsp; [ ] FAIL | |
| 13 | Administrator Generates Database Backup (`/admin/backups`) | [ ] PASS &nbsp; [ ] FAIL | |
| 14 | Unauthorized Backup Download Is Strictly Blocked | [ ] PASS &nbsp; [ ] FAIL | |

---

## 5. Issues Encountered & Resolutions

Did you encounter any problems during installation or testing? (If none, leave as "None").

1. **Issue:** `[Describe issue, e.g. Port 80 in use, permission error]`
   **Resolution:** `[How it was resolved, e.g. Stopped IIS, followed TROUBLESHOOTING.md]`

2. **Issue:** `[Describe issue]`
   **Resolution:** `[How it was resolved]`

---

## 6. Feedback & Ease-of-Installation Rating

- **Time taken to complete installation from scratch:** approximately _____ minutes.
- **Were the instructions easy to understand?**
  - [ ] Very Easy — No confusion
  - [ ] Moderate — Needed to check Troubleshooting guide once or twice
  - [ ] Difficult — Got stuck on several steps
- **Any suggested improvements to the instructions or setup process?**
  > `[Your feedback here]`

---

## 7. Sign-off

- **Tester Signature:** `_____________________________`
- **Date:** `_____________________________`
- **Overall Result:** [ ] **CERTIFIED WORKING ON INDEPENDENT MACHINE** &nbsp;&nbsp;&nbsp;&nbsp; [ ] **FAILED**
