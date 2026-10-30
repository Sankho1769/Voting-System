# VerityVote Documentation Index

Welcome to the comprehensive documentation directory for the **VerityVote** Institutional College Voting System.

## Architecture & System Design
- [architecture.md](architecture.md) — Multi-tier software architecture, layering, and directory boundaries.
- [database-design.md](database-design.md) — Relational schema specification, tables, keys, indexes, and ACID transaction rules.
- [security.md](security.md) — Application security controls, OWASP ASVS alignment, and threat analysis.
- [UI_UX.md](UI_UX.md) — Visual design system, color tokens (`#F9EBD7`, `#046A38`, `#E34222`), typography, and components.

## Specifications & Requirements
- [PRD.md](PRD.md) — Product Requirements Document, target personas, and scope.
- [SRS.md](SRS.md) — Software Requirements Specification with testable requirement IDs.
- [traceability-matrix.md](traceability-matrix.md) — Complete Requirements Traceability Matrix linking SRS IDs to code and test cases.

## Operational & User Manuals
- [installation.md](installation.md) — Step-by-step setup guide for another student on Windows XAMPP.
- [user-manual.md](user-manual.md) — Student and staff voter instructions.
- [candidate-manual.md](candidate-manual.md) — Candidate control center and contender campaign guide.
- [admin-manual.md](admin-manual.md) — Election officer and system administrator handbook.
- [testing.md](testing.md) — Test suite documentation and automated test verification guide.

## Diagrams & Visual Models
- [diagrams/erd.md](diagrams/erd.md) — Entity-Relationship Diagram (ERD).
- [diagrams/dfd.md](diagrams/dfd.md) — Data Flow Diagrams (Level 0 and Level 1).
- [diagrams/sequences/](diagrams/sequences/) — Mermaid sequence diagrams:
  - `three_portals_authentication.mmd`
  - `authentication.mmd`
  - `account_provisioning.mmd`
  - `password_change.mmd`
  - `election_lifecycle.mmd`
  - `candidate_management.mmd`
  - `eligibility_verification.mmd`
  - `vote_submission.mmd`
  - `duplicate_concurrency_prevention.mmd`
  - `live_results.mmd`
  - `election_closure.mmd`
