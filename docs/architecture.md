# VerityVote — System Architecture Document

## 1. Architectural Style
VerityVote is engineered as a layered, modular PHP application following clean architecture principles without heavyweight external frameworks. The system strictly separates concerns into:
- **Presentation Layer**: HTML5 views, editorial design system CSS (`style.css`), and vanilla JavaScript (`app.js`, `vote.js`).
- **Routing & Middleware**: Front controller (`public/index.php`) routing requests through `AuthMiddleware`, `RoleMiddleware`, `CandidateMiddleware`, `VoterMiddleware`, and `CsrfMiddleware`.
- **Domain Service Layer**: Business logic coordination (`AuthService`, `AccountService`, `ElectionService`, `CandidateService`, `EligibilityService`, `VotingService`, `ResultsService`).
- **Data Access Layer**: Repositories abstracting SQL queries with MySQLi prepared statements.
- **Persistence Layer**: Relational schema in MariaDB / MySQL with InnoDB ACID engine and foreign key constraints.

### 1.1 Three Login Portals Routing Architecture
VerityVote implements a role-partitioned front controller architecture:
1. **Portal Hub (`/login`)**: Gateway rendering three visual entry pathways (Admin, Candidate, Voter).
2. **Dedicated Login Endpoints**:
   - `/login/admin` &rarr; Administrative and Election Officer authentication.
   - `/login/candidate` &rarr; Contender authentication (via Candidate ID or Institutional Roll Number).
   - `/login/voter` &rarr; Student and Staff voter authentication.
3. **Dedicated Dashboards & Middleware Gates**:
   - `/admin/*` &rarr; Guarded by `AuthMiddleware` and `RoleMiddleware(['admin', 'election_officer'])`.
   - `/candidate` &rarr; Guarded by `AuthMiddleware` and `CandidateMiddleware` (verifies candidate role or linked candidacy in `candidates`).
   - `/voter` & `/dashboard` &rarr; Guarded by `AuthMiddleware` and `VoterMiddleware` (verifies voting eligibility and blocks administrative-only accounts).
4. **Dual-Authorization Architecture**: Candidates who are also collegiate voters maintain a single identity in `users`. The database links candidate profiles via `candidates.user_id = users.id`, giving candidates access to both the Candidate Dashboard and Voter Ballot Box without duplicate accounts.

---

## 2. Directory Structure & Boundary Protection

```
Voting-System/
├── app/                  # Private application core (Never directly web-accessible)
│   ├── Core/             # Database, Session, Auth, CSRF, View, Logger
│   ├── Helpers/          # Sanitizer, Validator, CSVExporter, TimeHelper
│   ├── Middleware/       # AuthMiddleware, RoleMiddleware, CandidateMiddleware, VoterMiddleware, CsrfMiddleware
│   ├── Repositories/     # UserRepository, ElectionRepository, CandidateRepository, VotingRepository, etc.
│   ├── Services/         # AuthService, ElectionService, CandidateService, VotingService, etc.
│   ├── Views/            # Layouts, Member, Candidate, Admin, Error templates
│   └── bootstrap.php     # Autoloader & environment initializers
├── bin/                  # CLI-only administrative and diagnostic utilities
│   ├── check_env.php     # Environment health diagnostics
│   ├── check_db.php      # Database connectivity & schema verifier
│   ├── create_admin.php  # Protected initial administrator setup
│   ├── migrate.php       # Schema migration runner
│   └── lint_all.php      # Syntax validation tool
├── config/               # Configuration templates (protected by .htaccess)
│   ├── config.example.php
│   └── config.php        # Environment configuration
├── database/             # Schema definitions and migrations
│   ├── schema.sql
│   └── migrations/       # Incremental SQL migration scripts
├── public/               # Web Document Root
│   ├── index.php         # Front controller gateway (3 portals + dashboards)
│   ├── api/              # Live polling JSON endpoints
│   ├── assets/           # CSS, JS, and UI images
│   └── .htaccess         # URL rewriting & security headers
├── storage/              # Private application storage (protected by .htaccess)
│   ├── logs/             # Daily audited security logs
│   └── uploads/          # Candidate photos (no script execution)
├── tests/                # Automated unit and integration test suite
│   ├── Unit/
│   ├── Integration/      # Concurrency, ThreePortalsAuthTest, HttpJourneyTest
│   └── run_tests.php
└── docs/                 # Complete system documentation folder
```

---

## 3. Technology Stack & Environment
- **Server Language:** PHP 8.2+ (Utilizing strict types, typed properties, match expressions).
- **Database Engine:** MariaDB 10.4+ / MySQL 8.0+ running on InnoDB with `utf8mb4_unicode_ci`.
- **Data Access:** Native PHP `mysqli` with prepared statements. (No PDO).
- **Web Server:** Apache 2.4 (via XAMPP on Windows) with `mod_rewrite` and `mod_headers`.
- **Frontend Stack:** Semantic HTML5, Central CSS3 Custom Properties Design System, Vanilla JavaScript (Fetch API, DOM manipulation).

---

## 4. Concurrency & Transaction Architecture
Every ballot submission executes inside a strict ACID transaction with row-level locks:
1. `LOCK IN SHARE MODE` on election state and candidate record.
2. `FOR UPDATE` exclusive lock on user participation row.
3. Insertion of participant receipt followed by anonymous insertion into the `votes` table.
4. Atomicity ensures that any failure, conflict, or race condition causes an immediate rollback.
