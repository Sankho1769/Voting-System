# VerityVote — Student & Staff Voter Manual

Welcome to **VerityVote**, your college's official electronic voting portal. This manual guides you through authentication, candidate review, ballot casting, receipt verification, and dual-permission access.

---

## 1. Signing In to the Voter Portal

### 1.1 Account Provisioning Notice
Public self-registration is disabled. Your account is created by the college administration based on official enrollment and employment records.
- **Students**: Use your official **College Roll Number** (e.g., `BCA2024-001`).
- **Staff / Faculty**: Use your official **Employee ID** (e.g., `EMP-104`).

### 1.2 Accessing the Voter Portal
You can sign in using either of the following paths:
1. **Central Login Hub**: Navigate to `http://localhost/Baymax_Voting_System/public/login` and click **Enter Voter Portal &rarr;**.
2. **Direct Voter Portal**: Navigate directly to `http://localhost/Baymax_Voting_System/public/login/voter`.

### 1.3 Initial Sign-In with Temporary Password
1. Enter your Institutional ID (Roll Number or Employee ID) and temporary password provided by your department office.
2. (Optional) Use the **Show/Hide password toggle** (eye icon) to visually verify your entered password.
3. Click **Authenticate & Continue**.

> [!NOTE]
> The Voter Portal is reserved for eligible collegiate voters. Administrative accounts attempting to sign in through the Voter Portal are rejected to enforce audit separation.

---

## 2. Mandatory First-Login Password Change
If you signed in with a temporary credential:
1. You will be automatically redirected to the **Set Personal Password** screen (`/change-password`).
2. Enter your current temporary password.
3. Enter your chosen new password (at least 8 characters, containing both letters and digits).
4. Confirm your new password and click **Save Password & Proceed**.
*Your temporary password is now retired, and your new personal password takes effect immediately.*

---

## 3. Reviewing Eligible Elections
1. On your **Institutional Elections** dashboard (`/dashboard` or `/voter`), you will see all elections where your account has been authorized on the voter whitelist.
2. Each election card displays:
   - Contested Office (e.g., President, General Secretary, Class Representative).
   - Voting Schedule Window (Opening time and Closing deadline).
   - Election Status Badge (`Open`, `Scheduled`, `Closed`, `Results Published`).
   - Approved Contenders Count.

---

## 4. Casting Your Official Ballot

1. When an election status is **Open**, click **Cast Official Ballot &rarr;**.
2. **Review Candidates**:
   - Each approved candidate is displayed with their name, profile photo, and campaign manifesto.
   - Click on any candidate's card or radio button to make your selection.
3. Click **Review & Cast Official Ballot**.
4. **Confirmation Modal**:
   - A modal dialog appears displaying the name of the candidate you selected.
   - **Review carefully:** Once committed, your vote is permanent and cannot be modified or withdrawn.
5. Click **Confirm & Submit Ballot**.

---

## 5. Participation Receipt & Ballot Privacy
1. Upon successful submission, you are redirected to the **Ballot Recorded Successfully** receipt page.
2. **Cryptographic Receipt Token**:
   - You will receive a unique HMAC-SHA256 participation receipt token (e.g., `b9e847c2...`).
   - This cryptographic hash serves as proof that your ballot was received into the electronic voting box without recording which candidate you chose.
3. Save or record this receipt for your personal verification.

---

## 6. Live Standings & Election Results
- If the election policy is configured for **Live Provisional Standings**, you can click **View Live Standings** to observe real-time turnout and candidate percentages as votes are recorded.
- Once the election deadline passes and the election is closed and certified by the returning officer, official final results and winner notices will be announced on the portal.

---

## 7. Dual Authorization: Voting as a Contesting Candidate
If you are running for office and your profile is linked to an approved candidacy:
1. **Single Collegiate Identity**: You do NOT need two separate accounts or passwords. Your single college Roll Number and password allow access to both portals.
2. **Candidate Notice Banner**: On your voter dashboard, you will see a banner notifying you of your active candidacy with a direct link to the **Candidate Control Center** (`/candidate`).
3. **Voting Rights**: You retain full eligibility to vote in any election for which you are whitelisted. Your vote is recorded with identical cryptographic protections and ballot secrecy as any other voter.
