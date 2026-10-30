# VerityVote — Requirements Traceability Matrix (RTM)

This matrix maps every functional and non-functional requirement from the Software Requirements Specification (SRS) to its corresponding implementation modules and verification test cases.

| Req ID | Requirement Description | Implementation Modules | Verification Test Case | Status |
|---|---|---|---|---|
| **FR-AUTH-01** | Student sign-in using Roll Number & password | `app/Services/AuthService.php`, `app/Views/auth/login.php` | `AuthIntegrationTest::testStudentLogin` | **VERIFIED (PASS)** |
| **FR-AUTH-02** | Staff/Admin sign-in using Employee/Admin ID & password | `app/Services/AuthService.php`, `bin/create_admin.php` | `AuthIntegrationTest::testAdminLogin` | **VERIFIED (PASS)** |
| **FR-AUTH-03** | Public self-registration disabled | `public/index.php`, `app/Views/auth/login.php` | Architecture inspection | **VERIFIED (PASS)** |
| **FR-AUTH-04** | Secure password hashing (`password_hash()`) | `app/Services/AuthService.php`, `app/Services/AccountService.php` | `AuthIntegrationTest::testPasswordHash` | **VERIFIED (PASS)** |
| **FR-AUTH-05** | Cryptographic temporary passwords & first-login change | `app/Services/AccountService.php`, `app/Services/AuthService.php` | `AuthIntegrationTest::testTempPasswordChange` | **VERIFIED (PASS)** |
| **FR-AUTH-06** | Generic authentication failure messages | `app/Services/AuthService.php` | `AuthIntegrationTest::testGenericFailure` | **VERIFIED (PASS)** |
| **FR-AUTH-07** | Server-side login throttling after 5 attempts | `app/Repositories/LoginAttemptRepository.php`, `AuthService.php` | `AuthIntegrationTest::testRateLimitLockout` | **VERIFIED (PASS)** |
| **FR-AUTH-08** | Three distinct login portals (Admin, Candidate, Voter) | `app/Views/auth/login_hub.php`, `login_portal.php`, `public/index.php` | `ThreePortalsAuthTest`, `HttpJourneyTest` | **VERIFIED (PASS)** |
| **FR-AUTH-09** | Role mismatch protection at authentication | `app/Services/AuthService.php`, `app/Core/Auth.php` | `ThreePortalsAuthTest::testRoleMismatches` | **VERIFIED (PASS)** |
| **FR-AUTH-10** | Dual role authorization without identity duplication | `app/Repositories/CandidateRepository.php`, `VotingService.php` | `ThreePortalsAuthTest::testDualPermission` | **VERIFIED (PASS)** |
| **FR-RBAC-01** | Server-side role middleware enforcement | `app/Middleware/RoleMiddleware.php`, `public/index.php` | `RoleMiddlewareTest` | **VERIFIED (PASS)** |
| **FR-RBAC-02** | Strict role isolation (no automatic admin elevation) | `app/Middleware/RoleMiddleware.php`, `app/Core/Auth.php` | `RoleMiddlewareTest` | **VERIFIED (PASS)** |
| **FR-RBAC-03** | Monotonic session invalidation via `auth_version` | `app/Core/Auth.php`, `app/Repositories/UserRepository.php` | `AuthIntegrationTest::testSuspensionSessionRevoke` | **VERIFIED (PASS)** |
| **FR-RBAC-04** | Idle (30m) & absolute (4h) session timeouts | `app/Core/Session.php` | `SessionTest` | **VERIFIED (PASS)** |
| **FR-RBAC-05** | Direct route escalation prevention (403 Forbidden) | `app/Middleware/RoleMiddleware.php`, `CandidateMiddleware.php` | `HttpJourneyTest::testRBACBoundaries` | **VERIFIED (PASS)** |
| **FR-ELEC-01** | Each election represents exactly one position | `app/Services/ElectionService.php`, `database/schema.sql` | `ElectionLifecycleTest::testCreateElection` | **VERIFIED (PASS)** |
| **FR-ELEC-02** | Deterministic lifecycle state machine | `app/Services/ElectionService.php`, `app/Repositories/ElectionRepository.php` | `ElectionLifecycleTest::testStateTransitions` | **VERIFIED (PASS)** |
| **FR-ELEC-03** | At least 2 approved candidates required to open | `app/Services/ElectionService.php` | `ElectionLifecycleTest::testMinCandidatesGate` | **VERIFIED (PASS)** |
| **FR-ELEC-04** | Closing time must be strictly after opening time | `app/Helpers/Validator.php`, `app/Services/ElectionService.php` | `HelperTest::testElectionDateOrdering` | **VERIFIED (PASS)** |
| **FR-ELEC-05** | Configuration & candidates locked once voting is open | `app/Services/ElectionService.php`, `CandidateService.php` | `ElectionLifecycleTest::testBallotLock` | **VERIFIED (PASS)** |
| **FR-ELEC-06** | Closing prevents future votes without deleting ballots | `app/Repositories/VotingRepository.php` | `VotingConcurrencyTest::testClosedElectionRejection` | **VERIFIED (PASS)** |
| **FR-CAND-01** | Candidate profile, manifesto, photo, approval state | `app/Repositories/CandidateRepository.php`, `CandidateService.php` | `ElectionLifecycleTest::testCandidateRegistration` | **VERIFIED (PASS)** |
| **FR-CAND-02** | Safe file upload validation (size, MIME, getimagesize) | `app/Helpers/Validator.php`, `app/Services/CandidateService.php` | `CandidateServiceTest` | **VERIFIED (PASS)** |
| **FR-CAND-03** | Execution-safe photo storage with random filenames | `app/Services/CandidateService.php`, `storage/.htaccess` | Storage audit | **VERIFIED (PASS)** |
| **FR-CAND-04** | Dedicated Candidate Dashboard (`/candidate`) | `app/Views/candidate/dashboard.php`, `CandidateService.php` | `HttpJourneyTest::testCandidateDashboardAccess` | **VERIFIED (PASS)** |
| **FR-CAND-05** | Flexible candidate code / roll number lookup | `app/Repositories/CandidateRepository.php`, `AuthService.php` | `ThreePortalsAuthTest::testCandidateCodeLogin` | **VERIFIED (PASS)** |
| **FR-ELIG-01** | Explicit whitelist mapping required to vote | `app/Repositories/EligibilityRepository.php`, `VotingRepository.php` | `VotingConcurrencyTest::testIneligibleRejection` | **VERIFIED (PASS)** |
| **FR-ELIG-02** | Batch eligibility assignment by role/dept | `app/Services/EligibilityService.php` | `VotingConcurrencyTest::testEligibilityEnrollment` | **VERIFIED (PASS)** |
| **FR-VOTE-01** | ACID database transaction with row-level locks | `app/Repositories/VotingRepository.php` | `VotingConcurrencyTest::testVoteAtomicity` | **VERIFIED (PASS)** |
| **FR-VOTE-02** | Server-side time and eligibility checks | `app/Repositories/VotingRepository.php` | `VotingConcurrencyTest::testServerSideChecks` | **VERIFIED (PASS)** |
| **FR-VOTE-03** | Double-voting & concurrent submission defense | `app/Repositories/VotingRepository.php` | `VotingConcurrencyTest::testDoubleVotingRejection` | **VERIFIED (PASS)** |
| **FR-VOTE-04** | Cryptographic HMAC-SHA256 receipt generation | `app/Services/VotingService.php`, `app/Repositories/VotingRepository.php` | `VotingConcurrencyTest::testReceiptVerification` | **VERIFIED (PASS)** |
| **FR-VOTE-05** | Ballot privacy: `votes` table has no `user_id` | `database/schema.sql`, `app/Repositories/VotingRepository.php` | `VotingConcurrencyTest::testBallotPrivacy` | **VERIFIED (PASS)** |
| **FR-RES-01** | Server-side calculation from accepted ballots | `app/Repositories/VotingRepository.php`, `ResultsService.php` | `VotingConcurrencyTest::testResultsCalculation` | **VERIFIED (PASS)** |
| **FR-RES-02** | Zero-vote division-by-zero protection | `app/Repositories/VotingRepository.php` | `VotingConcurrencyTest::testZeroVoteHandling` | **VERIFIED (PASS)** |
| **FR-RES-03** | Automatic tie detection | `app/Repositories/VotingRepository.php` | `VotingConcurrencyTest::testTieDetection` | **VERIFIED (PASS)** |
| **FR-RES-04** | Real-time live polling (6s interval) | `public/api/live_results.php`, `public/assets/js/app.js` | API endpoint test | **VERIFIED (PASS)** |
| **FR-RES-05** | Spreadsheet formula injection defense in CSV | `app/Helpers/CSVExporter.php` | `HelperTest::testCSVFormulaEscaping` | **VERIFIED (PASS)** |
| **FR-AUD-01** | Comprehensive audit log of events | `app/Core/Logger.php`, `app/Repositories/AuditRepository.php` | Audit verification | **VERIFIED (PASS)** |
| **FR-AUD-02** | Redaction of passwords, tokens, and voter choices | `app/Core/Logger.php` | Redaction check | **VERIFIED (PASS)** |
| **NFR-SEC-01** | MySQLi prepared statements across all queries | `app/Core/Database.php` | Static analysis & tests | **VERIFIED (PASS)** |
| **NFR-SEC-02** | Cryptographic CSRF tokens on all POST requests | `app/Core/CSRF.php`, `app/Middleware/CsrfMiddleware.php` | `CsrfMiddlewareTest` | **VERIFIED (PASS)** |
| **NFR-SEC-03** | HTML output sanitization (`Sanitizer::e()`) | `app/Helpers/Sanitizer.php` | `HelperTest::testSanitizerXSS` | **VERIFIED (PASS)** |
| **NFR-REL-01** | InnoDB storage engine with referential integrity | `database/schema.sql`, `bin/check_db.php` | `bin/check_db.php` engine check | **VERIFIED (PASS)** |
| **NFR-PORT-01** | Standard XAMPP Windows compatibility | `bin/check_env.php`, `bin/check_db.php` | Diagnostics run | **VERIFIED (PASS)** |
| **NFR-PORT-03** | phpMyAdmin inspectability | `database/schema.sql`, `database/README.md` | phpMyAdmin inspection | **VERIFIED (PASS)** |
