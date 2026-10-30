# VerityVote — Product Requirements Document (PRD)

## 1. Product Summary
**Product Name:** VerityVote  
**Tagline:** Institutional College Voting System  
**Product Class:** Closed, invitation-free academic electronic voting system for colleges and universities.

VerityVote addresses the common failure modes of collegiate student union and faculty elections: duplicate paper voting, unauthorized non-student participation, ballot tampering, lack of election transparency, and slow vote counting.

---

## 2. Target Personas
1. **Student Voter (Primary Consumer)**: Wants a fast, confidential, mobile-friendly voting interface to review candidate platforms and cast a decisive ballot during the voting window.
2. **Staff / Faculty Representative Voter**: Participates in departmental and academic council elections.
3. **Election Returning Officer**: Responsible for approving candidate nominations, setting up eligibility whitelists, supervising live polling, and certifying election results.
4. **College Administrator (System Owner)**: Authorizes accounts from official enrollment lists via CSV, enforces security policies, and inspects the audit ledger.

---

## 3. Core Feature Pillars
1. **Administrative Access Control**: No self-registration. All voter accounts originate from official college registers.
2. **Deterministic Lifecycle**: Every election represents exactly one position and transitions strictly through validated stages: Draft &rarr; Scheduled &rarr; Open &rarr; Closed &rarr; Results Published.
3. **Ballot Privacy**: Participation is recorded, but individual candidate selections are stored in an anonymous ballot box.
4. **Cryptographic Proof of Participation**: Each voter receives an HMAC-SHA256 participation receipt token to verify that their ballot was recorded.
5. **Live Polling & Certified Tallies**: Real-time aggregate turnout percentages and vote tallies with automatic tie handling and formula-safe CSV exports.

---

## 4. Key Performance Indicators (KPIs)
- Zero duplicate ballots accepted across concurrent requests.
- Sub-second vote commit times on local standard hardware.
- 100% test pass rate across unit, integration, and security verification suites.
- 100% inspectability through standard phpMyAdmin on XAMPP.
