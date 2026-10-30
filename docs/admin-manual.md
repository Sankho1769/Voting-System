# VerityVote — Administrator & Election Officer Manual

## 1. System Overview & Administrative Roles
VerityVote supports two administrative roles:
1. **System Administrator (`admin`)**: Unrestricted governance over collegiate accounts, CSV bulk provisioning, system configurations, and security audit logs.
2. **Election Officer (`election_officer`)**: Authorized to manage assigned elections, candidate nominations, voter eligibility whitelists, and state transitions.

### 1.1 Three Dedicated Login Portals
VerityVote separates user authentication into three distinct, secure portals accessible from the central gateway:
- **Central Gateway**: `http://localhost/Baymax_Voting_System/public/login` (presents options for Admin, Candidate, and Voter).
- **Admin Portal**: `http://localhost/Baymax_Voting_System/public/login/admin` (dedicated sign-in for Administrators and Election Officers).
- **Candidate Portal**: `http://localhost/Baymax_Voting_System/public/login/candidate` (dedicated sign-in for approved candidates).
- **Voter Portal**: `http://localhost/Baymax_Voting_System/public/login/voter` (dedicated sign-in for student and staff voters).

> [!IMPORTANT]
> **Role Mismatch Protection**: Administrators and Election Officers must sign in through `/login/admin`. If an administrator attempts to sign in via `/login/voter` or `/login/candidate`, the portal rejects authentication with a role mismatch notice to maintain strict audit separation.

---

## 2. Collegiate Account Provisioning

### 2.1 Single Account Creation
1. Go to **Accounts** &rarr; click **+ Provision Account**.
2. Fill in:
   - **Institutional Identifier**: Student Roll Number (e.g., `BCA2024-001`), Employee ID (`EMP-104`), or Candidate ID.
   - **Role**: Select `student`, `staff`, `candidate`, `election_officer`, or `admin`.
   - **Full Legal Name**: Academic student, candidate, or staff name.
   - **Department / Batch**: e.g., `Computer Science`, `2024-2027`.
3. Click **Provision Account & Generate Temporary Credential**.
4. The system generates a cryptographically random temporary password (e.g., `Vv-K3f8a2`).
5. **Security Policy**: The plain temporary password is shown **only once** upon creation. Store or securely distribute it to the user.

### 2.2 Bulk CSV Register Import
1. Go to **Accounts** &rarr; click **&uarr; Bulk Import CSV**.
2. Click **Download Sample CSV Template** to review expected column headers:
   `identifier, role, full_name, email, department, academic_year`
   *(Supported roles: `student`, `staff`, `candidate`, `election_officer`, `admin`)*
3. Upload your CSV file and click **Upload & Provision Accounts**.
4. The system validates all rows, detects duplicate institutional IDs, hashes individual temporary credentials, and displays an issued credentials table.

### 2.3 Password Resets for Students/Staff
If a student or staff member loses their credential:
1. Navigate to **Accounts**.
2. Locate the user via the search bar.
3. Click **Reset Password**.
4. A new 24-hour temporary password is generated, their `must_change_password` flag is reset to `1`, and their `auth_version` is incremented to revoke any active sessions.

### 2.4 Account Suspension
To temporarily prevent an account from accessing the portal:
1. Locate the account in **Accounts**.
2. Click **Suspend**. The account status updates immediately and any existing login sessions are revoked.

---

## 3. Election Lifecycle Management

Each election corresponds to **exactly one contested position**.

### 3.1 Creating a New Election
1. Navigate to **Elections** &rarr; click **+ Create Election**.
2. Fill in:
   - **Election Title**: e.g., `Annual Student Union Election 2026`.
   - **Contested Position**: e.g., `President` or `General Secretary`.
   - **Instructions / Guidelines**: Platform guidelines.
   - **Opening Datetime**: Real server time when voting begins.
   - **Closing Datetime**: Real server time when voting closes.
   - **Results Visibility**:
     - *Live Provisional Standings*: Authenticated voters observe real-time turnout and candidate percentages.
     - *Standings Hidden Until Official Closure*: Tally remains confidential until certified.
3. Click **Create Draft Election**.

### 3.2 Adding Approved Candidates & Linking User Accounts
1. In the election management screen, locate **Candidates Contesting**.
2. Enter Candidate Name and Manifesto platform.
3. (Optional) Upload Profile Photo (JPG, PNG, or WebP &le; 2MB).
4. **Collegiate User Account Linkage (Optional)**:
   - In the **Link Collegiate Account** dropdown, select the user's student or candidate account (e.g., `BCA2024-010 - Alex Mercer`).
   - Linking associates the candidate profile with that user record in `users.id`.
   - **Candidate Portal Access**: The linked user can then log into `/login/candidate` to view their personal candidate dashboard, manifesto, opponents, and election returns.
   - **Dual Authorization**: If the candidate is also a collegiate student whitelisted for an election, their voter rights remain fully active in the Voter Portal (`/login/voter`) using their single institutional credential.
5. Click **Add Candidate**.
*Rule: At least 2 approved candidates are required before an election can be scheduled or opened.*

### 3.3 Authorizing the Voter Whitelist
A collegiate user cannot vote unless explicitly enrolled in the election whitelist:
- **Criteria Assignment**: Select `Role` (e.g. `Student`) and `Department` (e.g. `Computer Science`), then click **Authorize Voters by Criteria**.
- The whitelist table displays all enrolled voters and tracks whether each voter has cast a ballot (`Voted` / `Awaiting`).

### 3.4 State Machine Transitions
- **Draft &rarr; Scheduled / Open**: Enforces candidate and date validation. Once `Open`, all candidate lists and eligibility whitelists are **locked against alteration**.
- **Open &rarr; Closed**: Manually close voting or allow automated deadline enforcement. Once closed, no further ballots are accepted.
- **Closed &rarr; Results Published**: Certify and release official standings to all voters.

---

## 4. Reports & CSV Exporting
1. Navigate to **Reports**.
2. Locate the election and click **&darr; Candidate Results CSV**.
3. The server tallies votes from stored ballots, calculates turnout percentages, escapes formula characters to neutralize spreadsheet injection, and downloads a certified `.csv` file.
