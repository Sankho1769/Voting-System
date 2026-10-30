# VerityVote — Institutional College Voting System
> A closed, invitation-free electronic voting system for colleges and universities built with **PHP 8.2+ and MySQLi** for local execution on Windows with XAMPP and phpMyAdmin.

---

## Key Highlights
- **Three Separate Dedicated Portals**: Distinct login gateways and dashboards for **Admin** (`/login/admin`), **Candidate** (`/login/candidate`), and **Voter** (`/login/voter`), unified under an institutional gateway hub (`/login`).
- **No Public Self-Registration**: Closed institutional access with accounts created exclusively by college administration using College Roll Numbers and Faculty IDs.
- **Candidate Control Center**: Contenders track nomination approval status, manifesto statements, opponent directory, and election standings, while preserving dual voter eligibility under a single account.
- **Editorial Design System**: Distinct visual identity featuring Warm Ivory (`#F9EBD7`), Emerald Green (`#046A38`), and Vermilion (`#E34222`) accents.
- **Strict Single-Position Elections**: Every election represents exactly one institutional office (President, General Secretary, Class Representative).
- **Deterministic State Machine**: `Draft` &rarr; `Scheduled` &rarr; `Open` &rarr; `Closed` &rarr; `Results Published` with ballot configuration locks once voting opens.
- **ACID Double-Voting Defense**: Database transactions with row-level locks guarantee each voter participates at most once across concurrent requests.
- **Ballot Privacy**: Participation tracking is strictly decoupled from candidate choices; the `votes` table contains no `user_id` column.
- **Cryptographic Participation Receipts**: Voters receive an HMAC-SHA256 receipt token upon ballot commitment.
- **Formula-Safe CSV Exports**: Protects administrators from spreadsheet formula injection (CSV DDE) in Microsoft Excel.
- **Zero Heavyweight Frameworks**: Built using native HTML5, CSS3, Vanilla JS, PHP 8.2+, and MySQLi prepared statements (No React, Node.js, Laravel, or PDO).

---

## Technology Stack
- **Backend**: PHP 8.2+ (utilizing strict typing and PSR-4 autoloading)
- **Database**: MySQL 8.0+ / MariaDB 10.4+ using MySQLi prepared statements (InnoDB engine)
- **Web Server**: Apache 2.4 via XAMPP on Windows
- **Inspection**: phpMyAdmin for database structure, queries, and backup export
- **Frontend**: Semantic HTML5, CSS custom properties design system, Vanilla JavaScript

---

## Quick Start (Windows XAMPP)

### 1. Place Project in XAMPP
Place the project in your XAMPP web root directory:
```
C:\xampp\htdocs\Baymax_Voting_System
```

### 2. Start Apache and MySQL
Open **XAMPP Control Panel** and start both **Apache** and **MySQL**.

### 3. Copy Configuration
```bash
cd C:\xampp\htdocs\Baymax_Voting_System
copy config\config.example.php config\config.php
```

### 4. Run Diagnostics & Database Initialization
```bash
# 1. Verify PHP extensions and directory permissions
C:\xampp\php\php.exe bin/check_env.php

# 2. Automatically create database and apply schema
C:\xampp\php\php.exe bin/check_db.php

# 3. Apply incremental migrations
C:\xampp\php\php.exe bin/migrate.php

# 4. Create initial administrator account (Protected CLI workflow)
C:\xampp\php\php.exe bin/create_admin.php ADMIN01 "System Administrator" admin@college.edu
```
*The command securely generates a random one-time temporary password (CSPRNG), outputs it to your terminal, and mandates a personal password change upon first login.*

### 5. Access the Web Application
Open your browser and navigate to:
```
http://localhost/Baymax_Voting_System/public/
```
The central gateway presents three login pathways:
- **Admin Login** (`/login/admin`): Sign in using your Administrator ID (`ADMIN01`) and temporary password.
- **Candidate Login** (`/login/candidate`): Contenders review their nomination, platform, opponents, and standings.
- **Voter Login** (`/login/voter`): Students and faculty cast ballots in eligible races.

### 6. Inspect Database via phpMyAdmin
Navigate to:
```
http://localhost/phpmyadmin/
```
Select `verityvote_db` to inspect tables, foreign keys, and indexes.

### 7. Run Automated Test Suite
```bash
C:\xampp\php\php.exe tests/run_tests.php
```
*Expected: 78 Passed | 0 Failed (100% pass rate).*

---

## Comprehensive Documentation
Complete academic documentation is available in the [`docs/`](docs/) directory:
- [Software Requirements Specification (SRS)](docs/SRS.md)
- [Product Requirements Document (PRD)](docs/PRD.md)
- [System Architecture](docs/architecture.md)
- [Database Design & Schema](docs/database-design.md)
- [UI/UX Design Tokens & System](docs/UI_UX.md)
- [Security Specification & Threat Model](docs/security.md)
- [Testing Strategy & Test Cases](docs/testing.md)
- [Requirements Traceability Matrix](docs/traceability-matrix.md)
- [Student & Staff Voter Manual](docs/user-manual.md)
- [Candidate Manual & Contender Guide](docs/candidate-manual.md)
- [Administrator & Election Officer Manual](docs/admin-manual.md)
- [Entity-Relationship Diagram](docs/diagrams/erd.md)
- [Data Flow Diagrams](docs/diagrams/dfd.md)
- [Sequence Diagrams (11 Diagrams)](docs/diagrams/sequences/)

---

## Academic Project License
BCA Academic Project. Built for educational, institutional governance, and software engineering demonstration.
