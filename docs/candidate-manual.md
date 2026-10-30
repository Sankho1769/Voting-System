# VerityVote — Candidate Manual & Contender Guide

## 1. Overview of the Candidate Portal
The **VerityVote Candidate Portal** (`/login/candidate`) is a dedicated interface for approved collegiate contenders contesting in single-position student union, class representative, or faculty elections.

The Candidate Portal provides:
- Candidacy verification and approval tracking (`pending`, `approved`, `rejected`).
- Platform manifesto and campaign review.
- Contesting opponents directory for your assigned race.
- Real-time provisional standings or certified final returns (governed by the election disclosure policy).
- Dual-role electoral access: direct navigation to the **Voter Portal** if your collegiate account is also on the voter whitelist.

Public candidate registration is strictly disabled. All candidates must be nominated and authorized by college election officers or system administrators.

---

## 2. Authentication & First-Time Sign-In

### 2.1 Supported Credentials
Candidates authenticate at `/login/candidate` using either of the following supported identifiers:
1. **Collegiate Roll Number / Employee ID:** Your regular college identifier (e.g. `BCA2024010`).
2. **Assigned Candidate ID:** Your official candidacy token (e.g. `CAND-01` or `CAND-12`).

### 2.2 First-Time Login & Mandatory Password Change
1. When your candidacy is provisioned by the administration, you will receive a secure temporary password.
2. Navigate to the Candidate Portal:
   ```
   http://localhost/Baymax_Voting_System/public/login/candidate
   ```
3. Enter your Candidate ID or Roll Number and the temporary password.
4. You will be redirected immediately to the **Mandatory Password Reset** screen (`/change-password`).
5. Choose a permanent password of at least 8 characters containing both letters and digits.
6. Upon saving, you are directed to the **Candidate Control Center**.

> [!IMPORTANT]
> If your account has dual permissions (you are both an approved candidate and an eligible voter), **one single password** authenticates both your Candidate and Voter portals. Do not create duplicate accounts.

---

## 3. Navigating the Candidate Control Center (`/candidate`)

### 3.1 Candidate Profile Capsule
At the top of your dashboard, your identity capsule confirms:
- Full Legal / Academic Name.
- Institutional Identifier.
- Department & Academic Batch.
- Account Status (`Active`).
- Candidate Profile Photograph.

### 3.2 Candidacy & Election Details
Each single-position race you are contesting is displayed with:
- **Election Title & Office Contested:** (e.g. `Cultural Secretary`, `President`).
- **Election Status:** `Draft`, `Scheduled`, `Open`, `Closed`, or `Results Published`.
- **Candidacy Approval Status:**
  - `Approved` (Emerald Green): Validated by election commission; your name is locked onto the ballot.
  - `Pending Review` (Warm Ivory / Amber): Under review by election officers.
  - `Rejected` (Vermilion): Disqualified by election commission.
- **Voting Schedule:** Exact server opening time, closing deadline, and countdown.

### 3.3 Platform Manifesto Review
Your campaign platform statement is rendered in your dashboard as voters see it on the ballot card. Review this text to ensure your platform initiatives are accurately communicated.

### 3.4 Contending Opponents Directory
A directory of all other approved candidates contesting the same office is listed for each race, showing their names and departments.

### 3.5 Electoral Performance & Standings
- **During Active Voting (`Open` status):**
  - If the election policy is **Live Provisional**, real-time vote tallies, percentage shares, and current standings are displayed with animated progress bars.
  - If the election policy is **Confidential until Closure**, the tally module remains securely locked: *"Standings for this race are confidential during voting and will be certified after election closure."*
- **After Election Closure (`Closed` / `Results Published`):**
  - The final certified tally, total valid ballots cast, and outcome (including tie indications if votes are equal) are rendered.

---

## 4. Dual Authorization: Voting as a Candidate

A candidate may also be eligible to vote in collegiate elections. VerityVote supports this seamlessly through a single institutional account:

1. Look for the emerald green banner at the top of your Candidate Dashboard:
   ```
   Dual Authorization: You are an Eligible Voter
   Your collegiate account is on the voter whitelist for 1 active election(s).
   ```
2. Click **Enter Voter Ballot Box &rarr;** or navigate to `/voter`.
3. In the Voter Portal, select the race and cast your secret ballot.
4. Obtain your cryptographic HMAC participation receipt.

> [!NOTE]
> Even though you are a candidate, you are bound by standard electoral integrity rules: you may cast **at most one ballot** per election race, and your candidate access does not permit tampering with vote counters or viewing voter identities.

---

## 5. Security & Portal Boundaries
- **No Administrative Escalation:** Candidates cannot access administrative routes (`/admin`, `/admin/users`, `/admin/elections`). Any direct URL navigation to `/admin` returns `403 Forbidden`.
- **Session Security:** Candidate sessions automatically time out after 30 minutes of inactivity. Always click **Sign Out** when finished on public campus workstations.
