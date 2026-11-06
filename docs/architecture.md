# VerityVote — System Architecture Document

## 1. Architectural Style
VerityVote is engineered as a layered, modular PHP application following clean architecture principles without heavyweight external frameworks. The system strictly separates concerns into:
- **Presentation Layer**: HTML5 views, editorial design system CSS (`style.css`, `responsive.css`), and vanilla JavaScript (`app.js`, `vote.js`).
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
│   ├── Core/             # Database, Session, Auth, CSRF, View, Logger, Cache, Metrics
│   ├── Helpers/          # Sanitizer, Validator, CSVExporter, TimeHelper
│   ├── Middleware/       # AuthMiddleware, RoleMiddleware, CandidateMiddleware, VoterMiddleware, CsrfMiddleware
│   ├── Repositories/     # UserRepository, ElectionRepository, CandidateRepository, VotingRepository, etc.
│   ├── Services/         # AuthService, ElectionService, CandidateService, VotingService, CacheService, etc.
│   ├── Views/            # Layouts, Member, Candidate, Admin, Error templates
│   └── bootstrap.php     # Autoloader & environment initializers
├── bin/                  # CLI-only administrative and diagnostic utilities
│   ├── check_env.php     # Environment health diagnostics
│   ├── check_db.php      # Database connectivity & schema verifier
│   ├── create_admin.php  # Protected initial administrator setup
│   ├── migrate.php       # Schema migration runner
│   ├── benchmark_baseline.php # Performance baseline profiling
│   └── lint_all.php      # Syntax validation tool
├── config/               # Configuration templates (protected by .htaccess)
│   ├── config.example.php
│   └── config.php        # Environment configuration
├── database/             # Schema definitions and migrations
│   ├── schema.sql
│   └── migrations/       # 001 through 006 (006: Composite Performance Indexes)
├── public/               # Web Document Root
│   ├── index.php         # Front controller gateway (3 portals + dashboards)
│   ├── api/              # Live polling JSON endpoints
│   ├── health/           # Kubernetes / container liveness and readiness probes
│   │   ├── live.php      # Liveness probe (HTTP 200)
│   │   └── ready.php     # Readiness probe (DB, tables, storage, memory)
│   ├── assets/           # CSS, JS, and UI images
│   └── .htaccess         # URL rewriting & security headers
├── storage/              # Private application storage (protected by .htaccess)
│   ├── cache/            # Partitioned file-cache directory
│   ├── logs/             # Daily audited security logs & structured JSON traces
│   └── uploads/          # Candidate photos (no script execution)
├── tests/                # Automated unit, integration, and load test suites
│   ├── Unit/
│   ├── Integration/      # Concurrency, MasterSecurityTest, FailureResilienceTest
│   ├── load/             # Phase 12 High-concurrency load testing suite (5 scenarios)
│   └── run_tests.php
└── docs/                 # Complete system documentation folder
    └── interview/        # Phase 12 Engineering Deep Dives (8 technical references)
```

---

## 3. Technology Stack & Environment
- **Server Language:** PHP 8.2+ (Utilizing strict types, typed properties, match expressions).
- **Database Engine:** MariaDB 10.4+ / MySQL 8.0+ running on InnoDB with `utf8mb4_unicode_ci`.
- **Data Access:** Native PHP `mysqli` with prepared statements. (No PDO).
- **Web Server:** Apache 2.4 (via XAMPP on Windows) with `mod_rewrite` and `mod_headers`.
- **Frontend Stack:** Semantic HTML5, Central CSS3 Custom Properties Design System, Vanilla JavaScript (Fetch API, DOM manipulation).

---

## 4. Concurrency & Master Security Hardening Architecture (Migration 004)
Every ballot submission executes inside a strict ACID transaction with row-level locks and replay defense:
1. `LOCK IN SHARE MODE` on election state and candidate record.
2. `FOR UPDATE` exclusive lock on voter row in `election_eligibility` whitelist to serialize concurrent submissions per voter.
3. Verification of 10 atomic server-side pre-conditions (status, role, password state, dates, eligibility, prior participation).
4. Insertion of cryptographic HMAC-SHA256 receipt into `election_participation` (enforced by `UNIQUE KEY uk_participation_election_user`).
5. Insertion of unlinked ballot into anonymous `votes` table (contains NO `user_id`).
6. Atomicity ensures that any failure, conflict, or race condition causes an immediate rollback. Tested and proven with 25 and 100 simultaneous workers yielding exactly 1 accepted ballot and 99 rejected attempts with zero orphan records.
7. Audit trail records distributed `correlation_id` across all operations while strictly redacting voter candidate choices.

---

## 5. Frontend Responsive & Animation Architecture
The presentation layer is decoupled into two complementary stylesheets and an event-driven vanilla JavaScript client:
1. **Master Stylesheet (`public/assets/css/style.css`)**:
   - Central design tokens, CSS custom properties, and base element styles.
   - Editorial typography hierarchy (`Instrument Serif`, `Plus Jakarta Sans`, `JetBrains Mono`).
   - Component definitions for portal cards, election match cards, navigation headers, and modal backdrops.
2. **Responsive & Media Stylesheet (`public/assets/css/responsive.css`)**:
   - Grid utilities (`.grid-2`, `.grid-3`, `.grid-4`, `.stats-grid`).
   - Breakpoint system covering 11 viewports (320px, 360px, 375px, 390px, 430px, 600px, 768px, 1024px, 1280px, 1440px, 1920px).
   - Strict horizontal overflow containment: `html, body { width: 100%; max-width: 100%; overflow-x: hidden; }` ensuring `scrollWidth === clientWidth` on all screens including 320px.
   - Pinned mobile bottom navigation bar (`.mobile-bottom-bar`) with 5 core touch targets.
   - GPU-accelerated CSS keyframe animations:
     - `@keyframes heroFadeUp`: 0.8s entrance for hero titles and copy.
     - `@keyframes floatRibbon`: 7s continuous gentle floating movement on signature 3D sculpture.
     - `@keyframes floatSphere`: Asynchronous 4.5s/5.5s floating loops for neon lime spheres.
     - `@keyframes orbitSpin`: 45s continuous orbital rotation for lime planetary rings.
     - `@keyframes cardReveal`: Staggered entrance for cards with progressive animation delays.
     - `@keyframes pulseLive`: 2s radar pulse for live election pills.
   - Accessibility compliance: `@media (prefers-reduced-motion: reduce)` disabling all keyframe transforms and forcing instant transitions.
3. **Interactive Script Layer (`public/assets/js/app.js`)**:
   - Light/Dark theme switching with persistent `localStorage` synchronization.
   - CricHeroes live discovery filter engine: real-time multi-attribute search across title, department, position, and candidate names.
   - Dynamic countdown timer ticker updating time remaining until polls close.
   - Accessible show/hide password toggle for all login forms.
